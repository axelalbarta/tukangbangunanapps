<?php
require __DIR__ . '/includes/init.php';
$u = require_role('admin');
$activeMenu = 'users';

$id = input_int('id');
$r = $id ? q('SELECT * FROM users WHERE id = ?', [$id])->fetch() : null;
if ($id && !$r) {
    flash('danger', 'User tidak ditemukan.');
    redirect('users');
}
if ($r && $r['role'] === 'klien') {
    $k = q('SELECT id FROM klien WHERE user_id = ?', [$id])->fetch();
    if ($k) redirect('klien_form?id=' . $k['id']);
}
$pageTitle = $r ? 'Ubah User' : 'User Baru';
$roles = ['tim' => 'Tim Internal (arsitek, pengawas, mandor)', 'admin' => 'Admin', 'ceo' => 'CEO'];

$data = $r ?: ['nama' => '', 'email' => '', 'role' => 'tim', 'jabatan' => '', 'no_hp' => '', 'aktif' => 1];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = [
        'nama'    => input('nama'),
        'email'   => strtolower(input('email')),
        'role'    => input('role'),
        'jabatan' => input('jabatan'),
        'no_hp'   => input('no_hp'),
        'aktif'   => isset($_POST['aktif']) ? 1 : 0,
    ];
    $password = (string)($_POST['password'] ?? '');

    if ($data['nama'] === '') $errors[] = 'Nama wajib diisi.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
    elseif (q('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$data['email'], $id])->fetch()) $errors[] = 'Email sudah dipakai user lain.';
    if (!isset($roles[$data['role']])) $errors[] = 'Role tidak valid.';
    if ((!$r || $password !== '') && ($lemah = cek_password($password, $data['email'], $data['nama']))) $errors[] = $lemah;
    if ($r && (int)$r['id'] === (int)$u['id'] && (!$data['aktif'] || $data['role'] !== 'admin')) {
        $errors[] = 'Anda tidak bisa menonaktifkan atau mengubah role akun sendiri.';
    }

    if (!$errors) {
        $vals = [$data['nama'], $data['email'], $data['role'], $data['jabatan'] ?: null, $data['no_hp'] ?: null, $data['aktif']];
        if ($r) {
            q('UPDATE users SET nama=?, email=?, role=?, jabatan=?, no_hp=?, aktif=? WHERE id=?', array_merge($vals, [$id]));
            if ($password !== '') {
                q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            $reset2fa = isset($_POST['reset_2fa']) && (int)$r['totp_aktif'] === 1 && (int)$r['id'] !== (int)$u['id'];
            if ($reset2fa) {
                // HP user hilang/ganti: matikan verifikasi 2 langkah & keluarkan dari semua perangkat
                q('UPDATE users SET totp_aktif=0, totp_rahasia=NULL, totp_cadangan=NULL, totp_langkah=NULL, sesi_kunci=? WHERE id=?', [bin2hex(random_bytes(16)), $id]);
            }
            log_aktivitas('ubah', 'user', $id, $data['nama'] . ($password !== '' ? ' (reset password)' : '') . ($reset2fa ? ' (reset verifikasi 2 langkah)' : ''));
        } else {
            q('INSERT INTO users (nama, email, role, jabatan, no_hp, aktif, password_hash) VALUES (?,?,?,?,?,?,?)',
                array_merge($vals, [password_hash($password, PASSWORD_DEFAULT)]));
            $id = (int)db()->lastInsertId();
            log_aktivitas('tambah', 'user', $id, $data['nama']);
        }
        flash('success', 'User berhasil disimpan.');
        redirect('users');
    }
}

$kembali = 'users';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1 class="h4 mb-0"><?= e($pageTitle) ?></h1>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<form method="post" class="card" autocomplete="off"><div class="card-body">
  <?= csrf_field() ?>
  <?php if ($r): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Nama *</label><input class="form-control" name="nama" value="<?= e($data['nama']) ?>" required maxlength="100"></div>
    <div class="col-md-6"><label class="form-label">Email (untuk login) *</label><input class="form-control" type="email" name="email" value="<?= e($data['email']) ?>" required maxlength="150"></div>
    <div class="col-md-4">
      <label class="form-label">Role *</label>
      <select class="form-select" name="role">
        <?php foreach ($roles as $k => $label): ?><option value="<?= $k ?>" <?= $data['role'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Jabatan</label><input class="form-control" name="jabatan" value="<?= e($data['jabatan']) ?>" maxlength="50" list="jabatan-list" placeholder="mis. Pengawas">
      <datalist id="jabatan-list"><option value="Arsitek"><option value="Pengawas"><option value="Mandor"><option value="Admin"><option value="CEO"></datalist></div>
    <div class="col-md-4"><label class="form-label">No. HP / WhatsApp</label><input class="form-control" name="no_hp" value="<?= e($data['no_hp']) ?>" maxlength="30" inputmode="tel"></div>
    <div class="col-md-6">
      <label class="form-label"><?= $r ? 'Password baru (kosongkan jika tidak diganti)' : 'Password *' ?></label>
      <input class="form-control" type="password" name="password" minlength="10" autocomplete="new-password" <?= $r ? '' : 'required' ?>>
      <div class="form-text">Minimal 10 karakter, berisi huruf dan angka. Mengganti password membuat user ini keluar dari semua perangkat.</div>
    </div>
    <div class="col-md-6 d-flex align-items-end">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="aktif" id="aktif" value="1" <?= (int)$data['aktif'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="aktif">Akun aktif (bisa login)</label>
      </div>
    </div>
    <?php if ($r && (int)$r['totp_aktif'] === 1 && (int)$r['id'] !== (int)$u['id']): ?>
      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="reset_2fa" id="reset_2fa" value="1">
          <label class="form-check-label" for="reset_2fa">Reset verifikasi 2 langkah <span class="text-muted small">(jika HP user hilang/ganti; user harus mengaktifkannya lagi saat login berikutnya)</span></label>
        </div>
      </div>
    <?php elseif ($r): ?>
      <div class="col-12 small text-muted">Verifikasi 2 langkah: <?= (int)$r['totp_aktif'] ? 'aktif' : 'belum aktif' ?></div>
    <?php endif; ?>
  </div>
  <div class="mt-4"><button class="btn btn-brand"><i class="bi bi-check-lg"></i> Simpan</button></div>
</div></form>

<?php require __DIR__ . '/includes/footer.php'; ?>
