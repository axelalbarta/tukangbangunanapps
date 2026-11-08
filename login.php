<?php
require __DIR__ . '/includes/init.php';

if (!aplikasi_terpasang()) {
    redirect('setup');
}
if (current_user()) {
    redirect('dashboard');
}

$error = '';
$email = '';
if (is_post()) {
    verify_csrf();
    $email = input('email');
    $password = (string)($_POST['password'] ?? '');

    // Batasi percobaan login: maks 5x gagal per email dan 30x per IP (CDN bisa menggabungkan IP) dalam 15 menit
    $ip = ip_klien();
    q('DELETE FROM login_percobaan WHERE waktu < NOW() - INTERVAL 1 DAY');
    $gagalIp = (int)q('SELECT COUNT(*) FROM login_percobaan WHERE ip = ? AND waktu > NOW() - INTERVAL 15 MINUTE', [$ip])->fetchColumn();
    $gagalEmail = (int)q('SELECT COUNT(*) FROM login_percobaan WHERE email = ? AND waktu > NOW() - INTERVAL 15 MINUTE', [$email])->fetchColumn();
    if ($gagalIp >= 30 || $gagalEmail >= 5) {
        $error = 'Terlalu banyak percobaan gagal. Tunggu 15 menit lalu coba lagi.';
    } else {
        $u = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
        // Selalu jalankan password_verify (juga untuk email yang tidak terdaftar) agar waktu respons sama
        $cocok = password_verify($password, $u['password_hash'] ?? '$2y$10$6/k6F013M1MiKT/301xVw.U2SuNusBTWcXaRIItYpOe.B8jVy1GSe');
        if ($u && $cocok && (int)$u['aktif'] === 1) {
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
                $u = q('SELECT * FROM users WHERE id = ?', [$u['id']])->fetch();
            }
            if ((int)$u['totp_aktif'] === 1 && !percaya_cek($u)) {
                // Langkah 2: minta kode dari aplikasi Authenticator
                session_regenerate_id(true);
                $_SESSION['2fa'] = ['uid' => (int)$u['id'], 'waktu' => time(), 'coba' => 0, 'cap' => cap_password($u)];
                redirect('verifikasi');
            }
            q('DELETE FROM login_percobaan WHERE email = ?', [$email]);
            mulai_sesi_user($u);
            q('UPDATE users SET login_terakhir = NOW() WHERE id = ?', [$u['id']]);
            log_aktivitas('login', 'user', (int)$u['id'], ip_klien(), (int)$u['id']);
            redirect('dashboard');
        }
        q('INSERT INTO login_percobaan (ip, email) VALUES (?, ?)', [$ip, mb_substr($email, 0, 150)]);
        // Status nonaktif hanya diberitahukan jika password benar (tidak membocorkan akun yang terdaftar)
        $error = ($u && $cocok && (int)$u['aktif'] !== 1) ? 'Akun ini sudah dinonaktifkan. Hubungi admin.' : 'Email atau password salah.';
    }
}
kirim_csp();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#111111">
<title>Login · <?= e(APP_NAME) ?></title>
<link rel="manifest" href="manifest.json">
<link rel="icon" href="assets/img/icon-192.png">
<link href="<?= e(vendor('bs_css')) ?>" rel="stylesheet">
<link href="<?= e(vendor('ikon_css')) ?>" rel="stylesheet">
<link href="<?= aset('assets/css/app.css') ?>" rel="stylesheet">
<?= intro_kepala() ?>
</head>
<body class="login-page">
<?= intro_lapisan() ?>
<div class="login-card card shadow-sm">
  <div class="card-body">
    <div class="text-center mb-3">
      <img src="assets/img/logo-light.png" alt="<?= e(APP_NAME) ?>" class="login-logo mb-2">
      <h1 class="h6 fw-bold mb-0">Sistem Arsip Proyek</h1>
      <div class="text-muted small">Perencanaan · Pengawasan · Pelaksanaan</div>
    </div>
    <?= render_flash() ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-brand w-100 btn-masuk">Masuk</button>
    </form>
    <div class="text-center small text-muted mt-4">Jasa Arsitek &amp; Kontraktor Bangunan<br>&copy; <?= date('Y') ?> Tukang Bangunan</div>
  </div>
</div>
<script nonce="<?= csp_nonce() ?>" src="<?= aset('assets/js/app.js') ?>"></script>
</body>
</html>
