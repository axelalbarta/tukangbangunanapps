<?php
// Pengaturan umum aplikasi.
// Nilai di sini adalah bawaan untuk XAMPP. Di server produksi, nilai rahasia & khusus server
// ditaruh di config/produksi.php (tidak ikut di ZIP aplikasi, jadi tidak tertimpa saat update).
defined('APP_NAME') || define('APP_NAME', 'Arsip Tukang Bangunan');
defined('APP_SHORT') || define('APP_SHORT', 'Tukang Bangunan');
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Jayapura');

// true = tampilkan pesan error (pengembangan). Bawaan: hanya aktif di komputer sendiri (localhost),
// sehingga server online tidak pernah menampilkan detail error walaupun produksi.php hilang.
defined('APP_DEBUG') || define('APP_DEBUG', in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));

// Batas ukuran file dokumen (byte). 25 MB sesuai dokumen perancangan.
defined('MAX_UPLOAD_BYTES') || define('MAX_UPLOAD_BYTES', 25 * 1024 * 1024);

// Sesi berakhir setelah tidak aktif (detik). 2 jam.
defined('SESSION_TIMEOUT') || define('SESSION_TIMEOUT', 2 * 60 * 60);
// Batas maksimal satu sesi login walaupun terus aktif (detik). 12 jam, lalu wajib login ulang.
defined('SESSION_MAKS') || define('SESSION_MAKS', 12 * 60 * 60);

// Role yang WAJIB memakai verifikasi 2 langkah (kode dari aplikasi Authenticator di HP).
// Role lain tetap bisa mengaktifkannya sendiri di menu Keamanan akun.
defined('WAJIB_2FA') || define('WAJIB_2FA', ['ceo', 'admin']);

// Kunci enkripsi rahasia di database (opsional, isi di produksi.php dengan teks acak panjang).
// Jika kosong, kunci diturunkan dari password database.
defined('APP_KEY') || define('APP_KEY', '');

// Ekstensi dokumen yang diizinkan
defined('ALLOWED_DOC_EXT') || define('ALLOWED_DOC_EXT', ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'docx', 'doc', 'dwg']);

// Folder penyimpanan file (dilindungi .htaccess, file hanya bisa dibuka lewat download.php/foto.php)
defined('UPLOAD_DIR') || define('UPLOAD_DIR', dirname(__DIR__) . '/uploads');

// Kunci untuk halaman setup.php (membuat akun admin pertama). Kosong = setup hanya bisa
// dijalankan dari localhost. Di produksi diisi kunci acak di produksi.php.
defined('SETUP_KEY') || define('SETUP_KEY', '');
