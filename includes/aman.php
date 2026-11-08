<?php
// Fungsi keamanan: verifikasi 2 langkah (TOTP), enkripsi rahasia, kekuatan password.

// ---------------------------------------------------------------------------
// Base32 (format kunci aplikasi Authenticator)
// ---------------------------------------------------------------------------
const B32_ABJAD = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function b32_encode(string $bin): string
{
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $p) $out .= B32_ABJAD[bindec(str_pad($p, 5, '0'))];
    return $out;
}

function b32_decode(string $s): string
{
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s));
    $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos(B32_ABJAD, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $p) if (strlen($p) === 8) $out .= chr(bindec($p));
    return $out;
}

// ---------------------------------------------------------------------------
// TOTP (RFC 6238): 6 digit, 30 detik, SHA1 — cocok dengan Google Authenticator,
// Microsoft Authenticator, Authy, dll.
// ---------------------------------------------------------------------------
function totp_rahasia_baru(): string
{
    return b32_encode(random_bytes(20));
}

function totp_kode(string $rahasiaB32, int $langkah): string
{
    $hmac = hash_hmac('sha1', pack('J', $langkah), b32_decode($rahasiaB32), true);
    $o = ord($hmac[19]) & 0x0F;
    $n = ((ord($hmac[$o]) & 0x7F) << 24) | (ord($hmac[$o + 1]) << 16) | (ord($hmac[$o + 2]) << 8) | ord($hmac[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Cek kode 6 digit (toleransi ±30 detik untuk jam HP yang sedikit meleset).
 * Mengembalikan langkah waktu yang cocok, atau null. $terakhir mencegah kode yang sama dipakai dua kali.
 */
function totp_cek(string $rahasiaB32, string $kode, ?int $terakhir = null): ?int
{
    $kode = preg_replace('/\D/', '', $kode);
    if (strlen($kode) !== 6) return null;
    $kini = intdiv(time(), 30);
    for ($d = -1; $d <= 1; $d++) {
        $l = $kini + $d;
        if ($terakhir !== null && $l <= $terakhir) continue;
        if (hash_equals(totp_kode($rahasiaB32, $l), $kode)) return $l;
    }
    return null;
}

function totp_uri(string $rahasiaB32, string $akun): string
{
    $penerbit = 'Arsip TB';
    return 'otpauth://totp/' . rawurlencode($penerbit . ':' . $akun) . '?secret=' . $rahasiaB32
        . '&issuer=' . rawurlencode($penerbit) . '&algorithm=SHA1&digits=6&period=30';
}

/** 8 kode cadangan sekali pakai: [teks untuk ditampilkan, hash untuk disimpan] */
function kode_cadangan_baru(): array
{
    $teks = [];
    $hash = [];
    for ($i = 0; $i < 8; $i++) {
        $k = strtoupper(bin2hex(random_bytes(5)));
        $k = substr($k, 0, 5) . '-' . substr($k, 5, 5);
        $teks[] = $k;
        $hash[] = password_hash(str_replace('-', '', $k), PASSWORD_DEFAULT);
    }
    return [$teks, $hash];
}

/** Pakai satu kode cadangan; mengembalikan daftar hash sisa, atau null jika kode salah */
function pakai_kode_cadangan(?string $json, string $kode): ?array
{
    $daftar = json_decode((string)$json, true) ?: [];
    $kode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $kode));
    if (strlen($kode) !== 10) return null;
    foreach ($daftar as $i => $h) {
        if (password_verify($kode, $h)) {
            unset($daftar[$i]);
            return array_values($daftar);
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// Enkripsi rahasia di database (AES-256-GCM). Kunci dari APP_KEY di produksi.php,
// atau diturunkan dari password database bila APP_KEY belum diatur.
// ---------------------------------------------------------------------------
function kunci_aplikasi(): string
{
    $dasar = defined('APP_KEY') && APP_KEY !== '' ? APP_KEY : ('tb-arsip|' . DB_NAME . '|' . DB_PASS);
    return hash('sha256', $dasar, true);
}

function enkripsi(string $teks): string
{
    $iv = random_bytes(12);
    $tag = '';
    $c = openssl_encrypt($teks, 'aes-256-gcm', kunci_aplikasi(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $c);
}

function dekripsi(?string $data): ?string
{
    if (!$data || !str_starts_with($data, 'v1:')) return null;
    $raw = base64_decode(substr($data, 3), true);
    if ($raw === false || strlen($raw) < 29) return null;
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', kunci_aplikasi(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? null : $p;
}

// ---------------------------------------------------------------------------
// Password
// ---------------------------------------------------------------------------
const PASSWORD_UMUM = ['password', 'password1', 'password123', '1234567890', '12345678', '123456789', 'qwerty123', 'qwertyuiop',
    'admin12345', 'admin123', 'administrator', 'iloveyou', 'sayang123', 'bismillah', 'bismillah123', 'indonesia', 'indonesia123',
    'tukangbangunan', 'kerjafakfak', 'fakfak123', 'rahasia123', 'katasandi', 'passw0rd', 'abc12345', 'abcd1234', 'tb12345'];

/** Mengembalikan pesan jika password lemah, null jika cukup kuat */
function cek_password(string $pw, string $email = '', string $nama = ''): ?string
{
    if (strlen($pw) < 10) return 'Password minimal 10 karakter.';
    if (strlen($pw) > 200) return 'Password terlalu panjang.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Password harus berisi huruf dan angka.';
    $kecil = strtolower($pw);
    if (in_array($kecil, PASSWORD_UMUM, true)) return 'Password terlalu umum, mudah ditebak.';
    if (preg_match('/^(.)\1+$/', $pw) || str_contains('01234567890123456789', $pw) || str_contains('abcdefghijklmnopqrstuvwxyz', $kecil)) {
        return 'Password terlalu mudah ditebak.';
    }
    $lokal = strtolower(strstr($email, '@', true) ?: '');
    if ($lokal !== '' && strlen($lokal) >= 4 && str_contains($kecil, $lokal)) return 'Password tidak boleh memuat email Anda.';
    foreach (preg_split('/\s+/', strtolower($nama)) ?: [] as $kata) {
        if (strlen($kata) >= 4 && str_contains($kecil, $kata)) return 'Password tidak boleh memuat nama Anda.';
    }
    return null;
}

/** Role yang wajib memakai verifikasi 2 langkah */
function wajib_2fa(array $u): bool
{
    return in_array($u['role'], WAJIB_2FA, true);
}

// ---------------------------------------------------------------------------
// Perangkat dipercaya: lewati kode 2 langkah selama 30 hari di HP/laptop yang sama.
// Cookie berisi selector.validator; database hanya menyimpan hash validator.
// Otomatis tidak berlaku bila password diganti, akun "keluar dari semua perangkat", atau 2FA direset.
// ---------------------------------------------------------------------------
const PERCAYA_HARI = 30;

function percaya_nama_cookie(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return $https ? '__Host-TBPERCAYA' : 'TBPERCAYA';
}

function percaya_nama_perangkat(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $os = match (true) {
        str_contains($ua, 'Android') => 'Android', str_contains($ua, 'iPhone') => 'iPhone', str_contains($ua, 'iPad') => 'iPad',
        str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Linux') => 'Linux', default => 'Perangkat',
    };
    $br = match (true) {
        str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'Firefox/') => 'Firefox', str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Safari/') => 'Safari', default => 'browser',
    };
    return "$br di $os";
}

function percaya_buat(array $u): void
{
    $sel = bin2hex(random_bytes(12));
    $val = bin2hex(random_bytes(32));
    $habis = time() + PERCAYA_HARI * 86400;
    q('INSERT INTO perangkat_dipercaya (user_id, selector, validator_hash, cap, nama, kedaluwarsa) VALUES (?,?,?,?,?,?)',
        [$u['id'], $sel, hash('sha256', $val), cap_password($u), percaya_nama_perangkat(), date('Y-m-d H:i:s', $habis)]);
    $https = str_starts_with(percaya_nama_cookie(), '__Host-');
    setcookie(percaya_nama_cookie(), "$sel.$val", ['expires' => $habis, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
}

/** Apakah perangkat ini sudah dipercaya oleh user tersebut */
function percaya_cek(array $u): bool
{
    $isi = (string)($_COOKIE[percaya_nama_cookie()] ?? '');
    if (!preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $isi, $m)) return false;
    $r = q('SELECT * FROM perangkat_dipercaya WHERE selector = ?', [$m[1]])->fetch();
    if (!$r || (int)$r['user_id'] !== (int)$u['id'] || strtotime($r['kedaluwarsa']) < time()) return false;
    if (!hash_equals($r['validator_hash'], hash('sha256', $m[2])) || !hash_equals($r['cap'], cap_password($u))) {
        q('DELETE FROM perangkat_dipercaya WHERE id = ?', [$r['id']]);
        return false;
    }
    return true;
}

function percaya_hapus_semua(int $userId): void
{
    q('DELETE FROM perangkat_dipercaya WHERE user_id = ?', [$userId]);
}
