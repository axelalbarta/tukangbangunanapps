<?php
// Pemasangan pertama: membuat tabel database dan akun admin pertama.
// Terkunci otomatis setelah ada akun. Di server, wajib membuka dengan ?kunci=SETUP_KEY.
require __DIR__ . '/includes/init.php';

if (aplikasi_terpasang()) {
    halaman_404(); // setelah terpasang, halaman setup seolah tidak ada
}
foreach (glob(__DIR__ . '/*.zip') ?: [] as $zipSisa) @unlink($zipSisa); // jangan biarkan ZIP pemasangan tertinggal

$lokal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$kunci = (string)($_POST['kunci'] ?? $_GET['kunci'] ?? '');
$kunciBenar = SETUP_KEY !== '' ? hash_equals(SETUP_KEY, $kunci) : $lokal;

function tampil_setup(string $isi): never
{
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>Setup · ' . e(APP_NAME) . '</title>'
        . '<link href="' . e(vendor('bs_css')) . '" rel="stylesheet">'
        . '<link href="' . e(vendor('ikon_css')) . '" rel="stylesheet"><link href="' . aset('assets/css/app.css') . '" rel="stylesheet"></head><body class="login-page">'
        . '<div class="login-card card shadow-sm" style="max-width:460px"><div class="card-body p-4">'
        . '<div class="text-center mb-3"><img src="assets/img/logo-light.png" alt="" class="login-logo mb-2"><h1 class="h6 fw-bold mb-0">Pemasangan Aplikasi</h1></div>'
        . $isi . '</div></div><script src="' . aset('assets/js/app.js') . '"></script></body></html>';
    exit;
}

if (!$kunciBenar) {
    http_response_code(403);
    tampil_setup('<div class="alert alert-warning mb-0">Halaman setup memerlukan kunci pemasangan yang benar. '
        . 'Buka alamat setup lengkap yang diberikan saat aplikasi dipasang.</div>');
}

$errors = [];
$data = ['nama' => '', 'email' => ''];

if (is_post()) {
    verify_csrf();
    $data = ['nama' => input('nama'), 'email' => strtolower(input('email'))];
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    if ($data['nama'] === '') $errors[] = 'Nama wajib diisi.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
    if ($lemah = cek_password($pw, $data['email'], $data['nama'])) $errors[] = $lemah;
    if ($pw !== $pw2) $errors[] = 'Ulangi password dengan benar.';

    if (!$errors) {
        try {
            // 1. Buat tabel dari database/skema.sql (aman diulang: CREATE TABLE IF NOT EXISTS)
            $sql = (string)file_get_contents(__DIR__ . '/database/skema.sql');
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
                if (trim($stmt) !== '') db()->exec($stmt);
            }
            // 2. Akun admin pertama (dicek ulang agar tidak dobel jika diklik dua kali)
            if ((int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
                q("INSERT INTO users (nama, email, password_hash, role, jabatan) VALUES (?,?,?,'admin','Admin')",
                    [$data['nama'], $data['email'], password_hash($pw, PASSWORD_DEFAULT)]);
                $uid = (int)db()->lastInsertId();
                log_aktivitas('pasang', 'user', $uid, 'Pemasangan aplikasi & akun admin pertama', $uid);
            }
            unset($_SESSION['skema_versi']);
            // Hapus ZIP pemasangan yang tertinggal di folder situs (berisi pengaturan rahasia)
            foreach (glob(__DIR__ . '/*.zip') ?: [] as $zipSisa) @unlink($zipSisa);
            flash('success', 'Aplikasi berhasil dipasang. Silakan login dengan akun admin yang baru dibuat.');
            redirect('login');
        } catch (Throwable $ex) {
            $errors[] = 'Pemasangan gagal: ' . (APP_DEBUG ? $ex->getMessage() : 'periksa koneksi database di config/produksi.php.');
        }
    }
}

$err = $errors ? '<div class="alert alert-danger py-2">' . implode('<br>', array_map('e', $errors)) . '</div>' : '';
tampil_setup($err . '
  <p class="small text-muted">Buat akun <strong>Admin</strong> pertama. Akun CEO, tim, dan klien dibuat setelah login lewat menu User dan Klien.</p>
  <form method="post" autocomplete="off">' . csrf_field() . '
    <input type="hidden" name="kunci" value="' . e($kunci) . '">
    <div class="mb-3"><label class="form-label">Nama</label><input class="form-control" name="nama" value="' . e($data['nama']) . '" required maxlength="100"></div>
    <div class="mb-3"><label class="form-label">Email (untuk login)</label><input class="form-control" type="email" name="email" value="' . e($data['email']) . '" required maxlength="150"></div>
    <div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required minlength="10" autocomplete="new-password">
      <div class="form-text">Minimal 10 karakter, berisi huruf dan angka.</div></div>
    <div class="mb-3"><label class="form-label">Ulangi password</label><input class="form-control" type="password" name="password2" required minlength="10" autocomplete="new-password"></div>
    <button class="btn btn-brand w-100 btn-masuk">Pasang aplikasi</button>
  </form>');
