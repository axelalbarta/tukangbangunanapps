<?php
require __DIR__ . '/includes/init.php';
$u = require_login();
$pageTitle = 'Profil';
$activeMenu = 'profil';
$errors = [];

if (is_post()) {
    verify_csrf();
    $aksi = input('aksi');
    if ($aksi === 'profil') {
        $nama = input('nama');
        $hp = input('no_hp');
        if ($nama === '') {
            $errors[] = 'Nama wajib diisi.';
        } else {
            q('UPDATE users SET nama=?, no_hp=? WHERE id=?', [$nama, $hp ?: null, $u['id']]);
            if ($u['role'] === 'klien') q('UPDATE klien SET telepon=? WHERE user_id=?', [$hp ?: null, $u['id']]);
            flash('success', 'Profil diperbarui.');
            redirect('profil');
        }
    } elseif ($aksi === 'password') {
        $lama = (string)($_POST['lama'] ?? '');
        $baru = (string)($_POST['baru'] ?? '');
        $ulang = (string)($_POST['ulang'] ?? '');
        if (!password_verify($lama, $u['password_hash'])) $errors[] = 'Password lama salah.';
        elseif ($lemah = cek_password($baru, $u['email'], $u['nama'])) $errors[] = $lemah;
        elseif (password_verify($baru, $u['password_hash'])) $errors[] = 'Password baru harus berbeda dari password lama.';
        elseif ($baru !== $ulang) $errors[] = 'Ulangi password baru dengan benar.';
        else {
            q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($baru, PASSWORD_DEFAULT), $u['id']]);
            // Perangkat lain yang masih login otomatis keluar; perangkat ini tetap masuk
            mulai_sesi_user(q('SELECT * FROM users WHERE id = ?', [$u['id']])->fetch());
            log_aktivitas('ubah', 'user', (int)$u['id'], 'ganti password');
            flash('success', 'Password berhasil diganti.');
            redirect('profil');
        }
    }
}

$kembali = 'semua_menu';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1 class="h4 mb-0">Profil</h1></div>
<?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <form method="post" class="card h-100"><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="profil">
      <h2 class="h6 mb-3">Data diri</h2>
      <div class="mb-3"><label class="form-label">Nama</label><input class="form-control" name="nama" value="<?= e($u['nama']) ?>" required maxlength="100"></div>
      <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= e($u['email']) ?>" disabled><div class="form-text">Untuk mengganti email, hubungi admin.</div></div>
      <div class="mb-3"><label class="form-label">No. HP / WhatsApp</label><input class="form-control" name="no_hp" value="<?= e($u['no_hp']) ?>" maxlength="30" inputmode="tel"></div>
      <div class="mb-3 small text-muted">Role: <?= e(ROLE_LABEL[$u['role']]) ?><?= $u['jabatan'] ? ' · ' . e($u['jabatan']) : '' ?></div>
      <button class="btn btn-brand">Simpan</button>
    </div></form>
  </div>
  <div class="col-lg-6">
    <form method="post" class="card h-100" autocomplete="off"><div class="card-body">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="password">
      <h2 class="h6 mb-3">Ganti password</h2>
      <div class="mb-3"><label class="form-label">Password lama</label><input class="form-control" type="password" name="lama" required autocomplete="current-password"></div>
      <div class="mb-3"><label class="form-label">Password baru</label><input class="form-control" type="password" name="baru" minlength="10" required autocomplete="new-password"><div class="form-text">Minimal 10 karakter, berisi huruf dan angka.</div></div>
      <div class="mb-3"><label class="form-label">Ulangi password baru</label><input class="form-control" type="password" name="ulang" minlength="10" required autocomplete="new-password"></div>
      <button class="btn btn-brand">Ganti password</button>
    </div></form>
  </div>
</div>

<a href="keamanan" class="card mt-3 text-decoration-none"><div class="card-body d-flex align-items-center gap-3">
  <span class="baris-ikon ikon-hijau"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
  <span class="flex-grow-1"><b class="d-block" style="color:var(--ink)">Keamanan akun</b><small class="text-muted">Verifikasi 2 langkah, perangkat yang login, riwayat login</small></span>
  <span class="pil <?= (int)$u['totp_aktif'] ? 'pil-ok' : 'pil-tunggu' ?>"><?= (int)$u['totp_aktif'] ? 'Aktif' : 'Belum aktif' ?></span>
</div></a>

<div class="card mt-3 d-lg-none"><div class="card-body">
  <form method="post" action="logout"><?= csrf_field() ?><button class="btn btn-outline-danger w-100"><i class="bi bi-box-arrow-right"></i> Keluar</button></form>
</div></div>

<?php require __DIR__ . '/includes/footer.php'; ?>
