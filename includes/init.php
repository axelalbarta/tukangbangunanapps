<?php
declare(strict_types=1);

// Pengaturan khusus server (rahasia) dimuat lebih dulu bila ada
if (is_file(__DIR__ . '/../config/produksi.php')) {
    require __DIR__ . '/../config/produksi.php';
}
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/database.php';

date_default_timezone_set(APP_TIMEZONE);
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.sid_length', '48');
ini_set('session.sid_bits_per_character', '6');
ini_set('session.gc_maxlifetime', (string)SESSION_TIMEOUT);
// Awalan __Host- (khusus HTTPS): cookie terkunci ke domain ini, wajib aman, tidak bisa ditimpa subdomain
session_name($isHttps ? '__Host-ARSIPTB' : 'ARSIPTB');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $isHttps,
]);
session_start();

// Header keamanan (juga dikirim dari PHP, berjaga-jaga bila mod_headers di .htaccess tidak aktif)
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), camera=(self), microphone=(), payment=(), usb=(), interest-cohort=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ---------------------------------------------------------------------------
// Label & pilihan
// ---------------------------------------------------------------------------
const ROLE_LABEL = ['ceo' => 'CEO', 'admin' => 'Admin', 'tim' => 'Tim Internal', 'klien' => 'Klien'];
const STATUS_PROYEK = [
    'penawaran' => ['Penawaran', 'secondary'],
    'berjalan'  => ['Berjalan', 'dark'],
    'tertunda'  => ['Tertunda', 'warning'],
    'selesai'   => ['Selesai', 'success'],
];
const LAYANAN = ['perencanaan' => 'Perencanaan', 'pengawasan' => 'Pengawasan', 'pelaksanaan' => 'Pelaksanaan'];
const STATUS_DOK = [
    'draft'     => ['Draft', 'secondary'],
    'menunggu'  => ['Menunggu Persetujuan', 'warning'],
    'disetujui' => ['Disetujui', 'success'],
    'ditolak'   => ['Ditolak', 'danger'],
];
// Kategori Kontrak (3) & Invoice (4) tidak bisa dilihat/diunggah tim internal
const KATEGORI_RAHASIA_TIM = [3, 4];
const PERAN_TIM =['arsitek' => 'Arsitek', 'pengawas' => 'Pengawas', 'mandor' => 'Mandor', 'lainnya' => 'Lainnya'];

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '" . date('P') . "'");
            migrasi_skema($pdo);
        } catch (PDOException $ex) {
            http_response_code(500);
            echo '<h3>Koneksi database gagal.</h3><p>Periksa config/database.php dan pastikan MySQL di XAMPP sudah berjalan serta database sudah diimpor.</p>';
            if (APP_DEBUG) {
                echo '<pre>' . e($ex->getMessage()) . '</pre>';
            }
            exit;
        }
    }
    return $pdo;
}

/**
 * Perbarui struktur database otomatis saat aplikasi diperbarui,
 * supaya tidak perlu menjalankan file SQL update secara manual.
 * Dicek sekali per sesi login.
 */
const SKEMA_VERSI = 6;
function migrasi_skema(PDO $pdo): void
{
    if (($_SESSION['skema_versi'] ?? 0) >= SKEMA_VERSI) return;
    try {
        if (!$pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn()) return; // belum terpasang: ditangani setup
        $kolom = $pdo->query('SHOW COLUMNS FROM dokumen')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('tanggal_dokumen', $kolom, true)) {
            $pdo->exec('ALTER TABLE dokumen ADD COLUMN tanggal_dokumen DATE NULL AFTER ukuran');
        }
        if (!in_array('sumber', $kolom, true)) {
            $pdo->exec("ALTER TABLE dokumen ADD COLUMN sumber ENUM('upload','impor') NOT NULL DEFAULT 'upload' AFTER catatan_persetujuan");
        }
        $kolomProyek = $pdo->query('SHOW COLUMNS FROM proyek')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('latitude', $kolomProyek, true)) {
            $pdo->exec('ALTER TABLE proyek ADD COLUMN latitude DECIMAL(10,7) NULL AFTER lokasi, ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude');
        }
        $kolomUser = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('totp_rahasia', $kolomUser, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN totp_rahasia VARCHAR(255) NULL AFTER login_terakhir,
                ADD COLUMN totp_aktif TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_rahasia,
                ADD COLUMN totp_cadangan TEXT NULL AFTER totp_aktif,
                ADD COLUMN totp_langkah BIGINT NULL AFTER totp_cadangan,
                ADD COLUMN sesi_kunci VARCHAR(64) NULL AFTER totp_langkah');
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS login_percobaan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45) NOT NULL, email VARCHAR(150) NULL,
            waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_lp_ip (ip, waktu), KEY idx_lp_email (email, waktu)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS perangkat_dipercaya (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, selector CHAR(24) NOT NULL UNIQUE,
            validator_hash CHAR(64) NOT NULL, cap CHAR(64) NOT NULL, nama VARCHAR(120) NULL,
            dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, kedaluwarsa DATETIME NOT NULL, KEY idx_pd_user (user_id),
            CONSTRAINT fk_pd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS push_langganan (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, endpoint TEXT NOT NULL,
            endpoint_hash CHAR(64) NOT NULL UNIQUE, p256dh VARCHAR(120) NOT NULL, auth VARCHAR(40) NOT NULL, nama VARCHAR(120) NULL,
            dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_push_user (user_id),
            CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS pengaturan (
            kunci VARCHAR(50) NOT NULL PRIMARY KEY, nilai TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $_SESSION['skema_versi'] = SKEMA_VERSI;
    } catch (PDOException $ex) {
        if (APP_DEBUG) {
            echo '<div style="background:#fff3cd;padding:12px;font-family:sans-serif">Pembaruan database otomatis gagal: '
                . e($ex->getMessage()) . '. Jalankan file database/update_v2.sql dan update_v3.sql lewat phpMyAdmin.</div>';
        }
    }
}

/** Database sudah berisi tabel & minimal satu akun? */
function aplikasi_terpasang(): bool
{
    static $hasil = null;
    if ($hasil === null) {
        try {
            $hasil = (bool)db()->query("SHOW TABLES LIKE 'users'")->fetchColumn()
                && (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
        } catch (PDOException $ex) {
            $hasil = false;
        }
    }
    return $hasil;
}

function ip_klien(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

// ---------------------------------------------------------------------------
// Utilitas umum
// ---------------------------------------------------------------------------
function e(mixed $s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return (is_string($v) && is_numeric($v)) ? (int)$v : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function render_flash(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
        $out .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">' . e($msg)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

function rupiah(mixed $n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tgl(?string $d): string
{
    if (!$d) return '–';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($d);
    if ($t === false) return '–';
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function tgl_waktu(?string $d): string
{
    if (!$d || strtotime($d) === false) return '–';
    return tgl($d) . ', ' . date('H:i', (int)strtotime($d));
}

function format_bytes(int $b): string
{
    if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
    if ($b >= 1024) return number_format($b / 1024, 0, ',', '.') . ' KB';
    return $b . ' B';
}

function badge(array $map, string $key): string
{
    [$label, $color] = $map[$key] ?? [$key, 'secondary'];
    $kelas = match ($color) {
        'success' => 'pil-ok', 'warning' => 'pil-tunggu', 'danger' => 'pil-tolak',
        'primary', 'dark' => 'pil-gelap', 'info' => 'pil-info', default => 'pil-draft',
    };
    return '<span class="pil ' . $kelas . '">' . e($label) . '</span>';
}

function upload_limit_bytes(): int
{
    $toBytes = function (string $v): int {
        $v = trim($v);
        $n = (int)$v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824,
            'm' => $n * 1048576,
            'k' => $n * 1024,
            default => $n,
        };
    };
    return min(MAX_UPLOAD_BYTES, $toBytes((string)ini_get('upload_max_filesize')), $toBytes((string)ini_get('post_max_size')));
}

/** URL aset dengan penanda versi, supaya browser selalu memuat file terbaru setelah aplikasi diperbarui */
function aset(string $path): string
{
    $file = dirname(__DIR__) . '/' . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/**
 * Library pihak ketiga (Bootstrap, ikon, peta, font). Jika sudah disalin ke assets/vendor
 * (jalankan alat/ambil-vendor.sh), dipakai dari server sendiri; jika belum, dari CDN.
 */
const VENDOR = [
    'bs_css'      => ['assets/vendor/bootstrap/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'],
    'bs_js'       => ['assets/vendor/bootstrap/bootstrap.bundle.min.js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'],
    'ikon_css'    => ['assets/vendor/bootstrap-icons/bootstrap-icons.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'],
    'leaflet_css' => ['assets/vendor/leaflet/leaflet.css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'],
    'leaflet_js'  => ['assets/vendor/leaflet/leaflet.js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'],
    'font_css'    => ['assets/vendor/fonts/fonts.css', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap'],
];

function vendor(string $kunci): string
{
    [$lokal, $cdn] = VENDOR[$kunci];
    return is_file(dirname(__DIR__) . '/' . $lokal) ? aset($lokal) : $cdn;
}

/** Semua library sudah ada di server sendiri? (maka CSP tidak perlu mengizinkan CDN) */
function vendor_lokal(): bool
{
    static $hasil = null;
    if ($hasil === null) {
        $hasil = true;
        foreach (VENDOR as [$lokal]) if (!is_file(dirname(__DIR__) . '/' . $lokal)) $hasil = false;
    }
    return $hasil;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Tolak kiriman formulir yang berasal dari situs lain (lapisan tambahan selain token CSRF) */
function asal_permintaan_sah(): bool
{
    $asal = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($asal === '' || $asal === 'null') {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref === '') return true; // sebagian browser/aplikasi tidak mengirim keduanya; token CSRF tetap dicek
        $asal = $ref;
    }
    $hostAsal = strtolower((string)parse_url($asal, PHP_URL_HOST));
    $portAsal = parse_url($asal, PHP_URL_PORT);
    $asalLengkap = $hostAsal . ($portAsal ? ':' . $portAsal : '');
    $hostKini = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    return $hostAsal !== '' && ($asalLengkap === $hostKini || $hostAsal === $hostKini);
}

function verify_csrf(): void
{
    if (!asal_permintaan_sah()) {
        http_response_code(403);
        exit('Permintaan ditolak: formulir dikirim dari situs lain.');
    }
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Sesi formulir sudah kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.');
    }
}

// ---------------------------------------------------------------------------
// Autentikasi
// ---------------------------------------------------------------------------
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $u = q('SELECT * FROM users WHERE id = ? AND aktif = 1', [$_SESSION['user_id']])->fetch();
            // Sesi otomatis berakhir di semua perangkat bila password akun diganti
            if ($u && isset($_SESSION['pw_cap']) && !hash_equals($_SESSION['pw_cap'], cap_password($u))) $u = null;
            $user = $u ?: null;
        }
    }
    return $user;
}

/** Sidik pendek dari hash password, disimpan di sesi untuk mendeteksi penggantian password */
function cap_password(array $u): string
{
    return hash('sha256', 'tb|' . $u['id'] . '|' . $u['password_hash'] . '|' . ($u['sesi_kunci'] ?? ''));
}

/** Tandai sesi sebagai milik user ini (dipanggil setelah login / ganti password) */
function mulai_sesi_user(array $u): void
{
    session_regenerate_id(true);
    unset($_SESSION['2fa'], $_SESSION['totp_baru']);
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['pw_cap'] = cap_password($u);
    $_SESSION['last_activity'] = time();
    $_SESSION['mulai'] ??= time();
}

/** Nonce acak per halaman: hanya skrip bertanda nonce ini yang boleh berjalan (skrip sisipan peretas ditolak) */
function csp_nonce(): string
{
    static $n = null;
    return $n ??= base64_encode(random_bytes(18));
}

/** Content-Security-Policy untuk halaman HTML: hanya sumber yang memang dipakai aplikasi */
function kirim_csp(): void
{
    $cdnSkrip = vendor_lokal() ? '' : ' https://cdn.jsdelivr.net https://unpkg.com';
    $cdnGaya = vendor_lokal() ? '' : ' https://cdn.jsdelivr.net https://unpkg.com https://fonts.googleapis.com';
    $cdnFont = vendor_lokal() ? '' : ' https://cdn.jsdelivr.net https://fonts.gstatic.com';
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'nonce-" . csp_nonce() . "'" . $cdnSkrip . "; "
        . "script-src-attr 'none'; "
        . "style-src 'self' 'unsafe-inline'" . $cdnGaya . "; "
        . "font-src 'self' data:" . $cdnFont . "; "
        . "img-src 'self' data: blob: https://server.arcgisonline.com https://tile.openstreetmap.org https://*.tile.openstreetmap.org" . (vendor_lokal() ? '' : ' https://unpkg.com') . "; "
        . "connect-src 'self' https://nominatim.openstreetmap.org; "
        . "worker-src 'self'; manifest-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
        . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '; upgrade-insecure-requests' : ''));
}

/**
 * Halaman "tidak ditemukan" yang seragam. Dipakai untuk alamat yang tidak ada, halaman terlarang,
 * dan halaman yang dibuka tanpa login — sehingga orang luar tidak bisa membedakan mana yang benar-benar ada.
 */
function halaman_404(): never
{
    while (ob_get_level()) ob_end_clean();
    http_response_code(404);
    header('Cache-Control: no-store');
    kirim_csp();
    $dasar = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><meta name="theme-color" content="#111111"><title>404 · Tidak ditemukan</title>'
        . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . 'background:#111;color:#fff;text-align:center;padding:24px}b{display:block;font-size:4rem;line-height:1;color:#d71920}'
        . 'p{opacity:.75}a{display:inline-block;margin-top:12px;background:#d71920;color:#fff;padding:12px 22px;border-radius:12px;text-decoration:none;font-weight:600}</style>'
        . '</head><body><main><b>404</b><h1 style="font-size:1.25rem">Halaman tidak ditemukan</h1>'
        . '<p>Alamat yang Anda buka tidak tersedia.</p><a href="' . e($dasar) . '">Ke halaman utama</a></main></body></html>';
    exit;
}

function require_login(): array
{
    if (!empty($_SESSION['user_id'])) {
        $diam = isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT;
        $kelamaan = isset($_SESSION['mulai']) && time() - $_SESSION['mulai'] > SESSION_MAKS;
        if ($diam || $kelamaan) {
            session_unset();
            session_regenerate_id(true);
            flash('warning', $diam ? 'Sesi berakhir karena lama tidak aktif. Silakan login lagi.' : 'Demi keamanan, silakan login lagi.');
            redirect('login');
        }
    }
    $u = current_user();
    if (!$u) {
        unset($_SESSION['user_id']);
        if (!aplikasi_terpasang()) redirect('setup');
        // Hanya halaman awal yang mengarah ke Login; halaman lain tampil "tidak ditemukan" bagi yang belum login
        if (pathinfo($_SERVER['SCRIPT_NAME'] ?? '', PATHINFO_FILENAME) === 'dashboard') redirect('login');
        halaman_404();
    }
    $_SESSION['last_activity'] = time();
    // CEO & Admin wajib mengaktifkan verifikasi 2 langkah sebelum memakai aplikasi
    if (wajib_2fa($u) && empty($u['totp_aktif']) && !in_array(pathinfo($_SERVER['SCRIPT_NAME'] ?? '', PATHINFO_FILENAME), ['keamanan', 'logout'], true)) {
        redirect('keamanan?wajib=1');
    }
    return $u;
}

function has_role(string ...$roles): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role'], $roles, true);
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        halaman_404(); // role lain tidak perlu tahu halaman ini ada
    }
    return $u;
}

function log_aktivitas(string $aksi, string $objek, ?int $objekId = null, string $ket = '', ?int $userId = null): void
{
    $userId ??= current_user()['id'] ?? null;
    q('INSERT INTO log_aktivitas (user_id, aksi, objek, objek_id, keterangan) VALUES (?,?,?,?,?)',
        [$userId, $aksi, $objek, $objekId, mb_substr($ket, 0, 255)]);
}

// ---------------------------------------------------------------------------
// Hak akses proyek
// ---------------------------------------------------------------------------
/** id klien milik user dengan role klien */
function klien_id_user(array $u): ?int
{
    static $cache = [];
    if (!array_key_exists($u['id'], $cache)) {
        $r = q('SELECT id FROM klien WHERE user_id = ?', [$u['id']])->fetch();
        $cache[$u['id']] = $r ? (int)$r['id'] : null;
    }
    return $cache[$u['id']];
}

/** Potongan WHERE untuk membatasi daftar proyek sesuai role (alias tabel proyek = p) */
function filter_proyek_sql(array $u): array
{
    return match ($u['role']) {
        'ceo', 'admin' => ['1=1', []],
        'tim'   => ['p.id IN (SELECT proyek_id FROM proyek_tim WHERE user_id = ?)', [$u['id']]],
        'klien' => ['p.klien_id = ?', [klien_id_user($u) ?? 0]],
        default => ['1=0', []],
    };
}

/** Ambil proyek jika user boleh mengaksesnya, null jika tidak */
function proyek_akses(int $id, array $u): ?array
{
    [$where, $params] = filter_proyek_sql($u);
    $p = q("SELECT p.*, k.nama AS nama_klien, k.telepon AS telepon_klien, k.alamat AS alamat_klien
            FROM proyek p JOIN klien k ON k.id = p.klien_id
            WHERE p.id = ? AND $where", array_merge([$id], $params))->fetch();
    return $p ?: null;
}

/** Klien hanya melihat dokumen yang disetujui CEO dan ditandai tampil ke klien */
function dokumen_boleh_dilihat(array $dok, array $u): bool
{
    if (!proyek_akses((int)$dok['proyek_id'], $u)) return false;
    if ($u['role'] === 'klien') {
        return $dok['status_persetujuan'] === 'disetujui' && (int)$dok['tampil_ke_klien'] === 1;
    }
    if ($u['role'] === 'tim') {
        return !in_array((int)$dok['kategori_id'], KATEGORI_RAHASIA_TIM, true);
    }
    return true;
}

/** Potongan WHERE dokumen sesuai role (alias tabel dokumen = d) */
function filter_dokumen_sql(array $u): string
{
    return match ($u['role']) {
        'klien' => "d.status_persetujuan = 'disetujui' AND d.tampil_ke_klien = 1",
        'tim'   => 'd.kategori_id NOT IN (' . implode(',', KATEGORI_RAHASIA_TIM) . ')',
        default => '1=1',
    };
}

/** Pesan jika folder uploads tidak bisa ditulis web server (umum di XAMPP macOS) */
function pesan_izin_uploads(): string
{
    $dir = UPLOAD_DIR;
    $msg = 'Server tidak punya izin menyimpan file di folder uploads.';
    if (PHP_OS_FAMILY === 'Darwin') {
        $msg .= ' Di Mac, buka Terminal lalu jalankan: sudo chmod -R 777 ' . $dir;
    } elseif (PHP_OS_FAMILY === 'Linux') {
        $msg .= ' Atur izin folder ' . $dir . ' menjadi 755 atau 775 lewat File Manager.';
    } else {
        $msg .= ' Pastikan folder ' . $dir . ' tidak berstatus read-only.';
    }
    return $msg;
}

/** Pastikan sub-folder uploads ada dan bisa ditulis */
function siapkan_folder_upload(string $sub): bool
{
    $dir = UPLOAD_DIR . '/' . $sub;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return is_dir($dir) && is_writable($dir);
}

/**
 * Buat file sementara di uploads/tmp (bisa ditulis aplikasi), bukan di folder temp sistem
 * yang sering tidak bisa ditulis Apache di XAMPP macOS. Mengembalikan path atau null.
 */
function file_sementara(string $awalan = 'tb'): ?string
{
    if (siapkan_folder_upload('tmp')) {
        // bersihkan sisa file sementara lebih dari 1 hari
        foreach (glob(UPLOAD_DIR . '/tmp/*') ?: [] as $lama) {
            if (is_file($lama) && filemtime($lama) < time() - 86400) @unlink($lama);
        }
        $path = UPLOAD_DIR . '/tmp/' . $awalan . '_' . bin2hex(random_bytes(8));
        if (@touch($path)) return $path;
    }
    $path = @tempnam(sys_get_temp_dir(), $awalan);
    return ($path && is_writable($path)) ? $path : null;
}

/** Apakah file fisik dokumen/foto masih ada di folder uploads */
function file_ada(?string $pathRelatif): bool
{
    return $pathRelatif !== null && $pathRelatif !== '' && is_file(UPLOAD_DIR . '/' . $pathRelatif);
}

function jumlah_menunggu_persetujuan(): int
{
    return (int)q("SELECT COUNT(*) FROM dokumen WHERE status_persetujuan = 'menunggu'")->fetchColumn();
}

require __DIR__ . '/aman.php';
require __DIR__ . '/notif.php';
require __DIR__ . '/ui.php';
require __DIR__ . '/media.php';
