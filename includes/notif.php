<?php
// Notifikasi di HP / laptop (Web Push) tanpa library luar.
// Standar: RFC 8291 (enkripsi aes128gcm) + RFC 8292 (VAPID, tanda tangan ES256).
// Kunci VAPID dibuat otomatis sekali dan disimpan di tabel `pengaturan` (kunci privat terenkripsi).

function b64u(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64u_dec(string $s): string
{
    $s = strtr($s, '-_', '+/');
    return (string)base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

/** Kunci publik EC P-256 mentah (65 byte) → PEM */
function ec_pem_publik(string $raw): string
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Kunci privat EC P-256 dari d (32 byte) + publik (65 byte) → PEM */
function ec_pem_privat(string $d, string $pub): string
{
    $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $pub;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

/** Bagian publik mentah (0x04 || X || Y) dari kunci EC */
function ec_publik_mentah($kunci): string
{
    $d = openssl_pkey_get_details($kunci)['ec'];
    return "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
}

function ec_kunci_baru()
{
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$k) throw new RuntimeException('OpenSSL tidak bisa membuat kunci EC.');
    return $k;
}

function pengaturan(string $kunci): ?string
{
    $r = q('SELECT nilai FROM pengaturan WHERE kunci = ?', [$kunci])->fetch();
    return $r ? $r['nilai'] : null;
}

function pengaturan_simpan(string $kunci, string $nilai): void
{
    q('DELETE FROM pengaturan WHERE kunci = ?', [$kunci]);
    q('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)', [$kunci, $nilai]);
}

/** [kunci publik base64url, resource kunci privat] — dibuat otomatis pertama kali */
function vapid_kunci(): array
{
    $pub = pengaturan('vapid_publik');
    $priv = dekripsi(pengaturan('vapid_privat'));
    if (!$pub || !$priv) {
        $k = ec_kunci_baru();
        openssl_pkey_export($k, $pem);
        $pub = b64u(ec_publik_mentah($k));
        pengaturan_simpan('vapid_publik', $pub);
        pengaturan_simpan('vapid_privat', enkripsi($pem));
        $priv = $pem;
    }
    return [$pub, openssl_pkey_get_private($priv)];
}

/** Tanda tangan ECDSA DER → r||s 64 byte (format JWT ES256) */
function der_ke_jose(string $der): string
{
    $pos = 2;
    if (ord($der[1]) & 0x80) $pos += ord($der[1]) & 0x7f;
    $ambil = function () use ($der, &$pos) {
        $len = ord($der[$pos + 1]);
        $v = substr($der, $pos + 2, $len);
        $pos += 2 + $len;
        return str_pad(ltrim($v, "\0"), 32, "\0", STR_PAD_LEFT);
    };
    return $ambil() . $ambil();
}

/** Header Authorization VAPID untuk satu endpoint */
function vapid_header(string $endpoint): string
{
    [$pub, $priv] = vapid_kunci();
    $u = parse_url($endpoint);
    $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $isi = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.' . b64u(json_encode([
        'aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:admin@' . (preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')) ?: 'kerjafakfak.com'),
    ]));
    openssl_sign($isi, $sig, $priv, OPENSSL_ALGO_SHA256);
    return 'vapid t=' . $isi . '.' . b64u(der_ke_jose($sig)) . ', k=' . $pub;
}

/**
 * Enkripsi isi pesan untuk satu langganan (RFC 8291, aes128gcm).
 * $asPrivPem & $garam hanya diisi untuk pengujian; normalnya dibuat acak.
 */
function push_enkripsi(string $pesan, string $uaPublik, string $authRahasia, ?string $asPrivPem = null, ?string $garam = null): string
{
    $as = $asPrivPem ? openssl_pkey_get_private($asPrivPem) : ec_kunci_baru();
    $asPublik = ec_publik_mentah($as);
    $rahasiaEcdh = openssl_pkey_derive(openssl_pkey_get_public(ec_pem_publik($uaPublik)), $as, 32);
    if ($rahasiaEcdh === false) throw new RuntimeException('ECDH gagal.');
    $ikm = hash_hkdf('sha256', $rahasiaEcdh, 32, "WebPush: info\0" . $uaPublik . $asPublik, $authRahasia);
    $garam ??= random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $garam);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $garam);
    $tag = '';
    $sandi = openssl_encrypt($pesan . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    return $garam . pack('N', 4096) . chr(65) . $asPublik . $sandi . $tag;
}

/** Endpoint hanya boleh ke layanan push resmi (mencegah server dipakai menembak alamat lain) */
function push_endpoint_sah(string $endpoint): bool
{
    $u = parse_url($endpoint);
    if (($u['scheme'] ?? '') !== 'https' || empty($u['host'])) return false;
    $host = strtolower($u['host']);
    foreach (['fcm.googleapis.com', 'android.googleapis.com', '.push.services.mozilla.com', '.notify.windows.com', '.push.apple.com', 'web.push.apple.com'] as $izin) {
        if ($host === ltrim($izin, '.') || (str_starts_with($izin, '.') && str_ends_with($host, $izin))) return true;
    }
    return false;
}

// ---------------------------------------------------------------------------
// Antrean: pesan dikirim setelah halaman selesai dikirim ke pengguna (tidak memperlambat)
// ---------------------------------------------------------------------------
function notif_kirim(array $userIds, string $judul, string $isi, string $url, string $tag = ''): void
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (!$userIds) return;
    static $didaftar = false;
    $GLOBALS['tb_notif_antrean'][] = [$userIds, $judul, $isi, $url, $tag];
    if (!$didaftar) {
        $didaftar = true;
        register_shutdown_function('notif_proses');
    }
}

function notif_proses(): void
{
    $antrean = $GLOBALS['tb_notif_antrean'] ?? [];
    if (!$antrean || !function_exists('curl_multi_init')) return;
    // Selesaikan respons ke browser lebih dulu
    if (function_exists('litespeed_finish_request')) litespeed_finish_request();
    elseif (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    @set_time_limit(30);
    try {
        $mh = curl_multi_init();
        $tugas = [];
        foreach ($antrean as [$userIds, $judul, $isi, $url, $tag]) {
            $in = implode(',', array_fill(0, count($userIds), '?'));
            $pesan = json_encode(['judul' => $judul, 'isi' => $isi, 'url' => $url, 'tag' => $tag], JSON_UNESCAPED_UNICODE);
            foreach (q("SELECT * FROM push_langganan WHERE user_id IN ($in)", $userIds)->fetchAll() as $s) {
                if (!push_endpoint_sah($s['endpoint'])) continue;
                $badan = push_enkripsi($pesan, b64u_dec($s['p256dh']), b64u_dec($s['auth']));
                $ch = curl_init($s['endpoint']);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true, CURLOPT_POSTFIELDS => $badan, CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm',
                        'TTL: 86400', 'Urgency: high', 'Authorization: ' . vapid_header($s['endpoint'])],
                ]);
                curl_multi_add_handle($mh, $ch);
                $tugas[] = [$ch, (int)$s['id']];
            }
        }
        do {
            $st = curl_multi_exec($mh, $jalan);
            if ($jalan) curl_multi_select($mh, 1.0);
        } while ($jalan && $st === CURLM_OK);
        foreach ($tugas as [$ch, $id]) {
            $kode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (in_array($kode, [404, 410], true)) q('DELETE FROM push_langganan WHERE id = ?', [$id]); // langganan sudah tidak berlaku
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    } catch (Throwable $e) {
        // notifikasi bersifat tambahan; kegagalan tidak boleh mengganggu aplikasi
    }
    $GLOBALS['tb_notif_antrean'] = [];
}

// ---------------------------------------------------------------------------
// Penerima
// ---------------------------------------------------------------------------
function notif_ceo(): array
{
    return q("SELECT id FROM users WHERE role = 'ceo' AND aktif = 1")->fetchAll(PDO::FETCH_COLUMN);
}

function notif_pimpinan(): array
{
    return q("SELECT id FROM users WHERE role IN ('ceo','admin') AND aktif = 1")->fetchAll(PDO::FETCH_COLUMN);
}

function notif_klien_proyek(int $proyekId): array
{
    return q('SELECT k.user_id FROM proyek p JOIN klien k ON k.id = p.klien_id JOIN users us ON us.id = k.user_id
              WHERE p.id = ? AND us.aktif = 1', [$proyekId])->fetchAll(PDO::FETCH_COLUMN);
}

function tanpa_saya(array $ids): array
{
    $saya = (int)(current_user()['id'] ?? 0);
    return array_values(array_filter($ids, fn($i) => (int)$i !== $saya));
}
