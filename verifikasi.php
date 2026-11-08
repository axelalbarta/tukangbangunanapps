<?php
// Langkah kedua login: kode 6 digit dari aplikasi Authenticator, atau kode cadangan.
require __DIR__ . '/includes/init.php';

$tunda = $_SESSION['2fa'] ?? null;
if (!$tunda || time() - (int)$tunda['waktu'] > 300) {
    unset($_SESSION['2fa']);
    if ($tunda) flash('warning', 'Waktu verifikasi habis. Silakan login lagi.');
    redirect('login');
}
$u = q('SELECT * FROM users WHERE id = ? AND aktif = 1', [$tunda['uid']])->fetch();
if (!$u || (int)$u['totp_aktif'] !== 1 || !hash_equals($tunda['cap'], cap_password($u))) {
    unset($_SESSION['2fa']);
    redirect('login');
}

$error = '';
$pakaiCadangan = isset($_GET['cadangan']) || input('jenis') === 'cadangan';
if (is_post()) {
    verify_csrf();
    $kode = input('kode');
    $ok = false;
    if ($pakaiCadangan) {
        $sisa = pakai_kode_cadangan($u['totp_cadangan'], $kode);
        if ($sisa !== null) {
            q('UPDATE users SET totp_cadangan = ? WHERE id = ?', [json_encode($sisa), $u['id']]);
            log_aktivitas('login', 'user', (int)$u['id'], 'memakai kode cadangan (sisa ' . count($sisa) . ')', (int)$u['id']);
            if (count($sisa) <= 2) flash('warning', 'Kode cadangan Anda tinggal ' . count($sisa) . '. Buat kode cadangan baru di menu Keamanan akun.');
            $ok = true;
        }
    } else {
        $rahasia = dekripsi($u['totp_rahasia']);
        $langkah = $rahasia ? totp_cek($rahasia, $kode, $u['totp_langkah'] !== null ? (int)$u['totp_langkah'] : null) : null;
        if ($langkah !== null) {
            q('UPDATE users SET totp_langkah = ? WHERE id = ?', [$langkah, $u['id']]);
            $ok = true;
        }
    }
    if ($ok) {
        q('DELETE FROM login_percobaan WHERE email = ?', [$u['email']]);
        if (isset($_POST['percaya'])) percaya_buat($u);
        mulai_sesi_user($u);
        q('UPDATE users SET login_terakhir = NOW() WHERE id = ?', [$u['id']]);
        log_aktivitas('login', 'user', (int)$u['id'], ip_klien() . ' (2 langkah)', (int)$u['id']);
        redirect('dashboard');
    }
    // Kode salah ikut dihitung sebagai percobaan login gagal
    q('INSERT INTO login_percobaan (ip, email) VALUES (?, ?)', [ip_klien(), mb_substr($u['email'], 0, 150)]);
    $_SESSION['2fa']['coba'] = (int)$tunda['coba'] + 1;
    if ($_SESSION['2fa']['coba'] >= 5) {
        unset($_SESSION['2fa']);
        flash('danger', 'Terlalu banyak kode salah. Silakan login lagi.');
        redirect('login');
    }
    $error = $pakaiCadangan ? 'Kode cadangan salah atau sudah pernah dipakai.' : 'Kode salah. Pastikan jam di HP Anda tepat, lalu masukkan kode terbaru.';
}
kirim_csp();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#111111">
<title>Verifikasi · <?= e(APP_NAME) ?></title>
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
      <h1 class="h6 fw-bold mb-1">Verifikasi 2 langkah</h1>
      <div class="text-muted small">
        <?= $pakaiCadangan ? 'Masukkan salah satu kode cadangan Anda (contoh: AB12C-3DE45).' : 'Buka aplikasi Authenticator di HP Anda, lalu masukkan 6 digit kode untuk <b>Arsip TB</b>.' ?>
      </div>
    </div>
    <?= render_flash() ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="jenis" value="<?= $pakaiCadangan ? 'cadangan' : 'totp' ?>">
      <div class="mb-3">
        <label class="form-label" for="kode"><?= $pakaiCadangan ? 'Kode cadangan' : 'Kode 6 digit' ?></label>
        <?php if ($pakaiCadangan): ?>
          <input class="form-control text-center" id="kode" name="kode" required autofocus maxlength="11" autocapitalize="characters" spellcheck="false">
        <?php else: ?>
          <input class="form-control text-center" id="kode" name="kode" required autofocus inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" style="letter-spacing:.4em;font-weight:700">
        <?php endif; ?>
      </div>
      <div class="form-check mb-3 text-start">
        <input class="form-check-input" type="checkbox" name="percaya" id="percaya" value="1" checked>
        <label class="form-check-label small" for="percaya">Jangan tanya kode lagi di perangkat ini selama 30 hari</label>
      </div>
      <button class="btn btn-brand w-100 btn-masuk">Verifikasi</button>
    </form>
    <div class="text-center small mt-3">
      <?php if ($pakaiCadangan): ?>
        <a href="verifikasi">Pakai kode dari aplikasi Authenticator</a>
      <?php else: ?>
        <a href="verifikasi?cadangan=1">HP hilang? Pakai kode cadangan</a>
      <?php endif; ?>
      <div class="mt-2"><a href="login" class="text-muted">Batal</a></div>
    </div>
  </div>
</div>
</body>
</html>
