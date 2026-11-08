<?php
require __DIR__ . '/includes/init.php';
$u = require_role('admin');
$activeMenu = 'klien';

$id = input_int('id');
$k = $id ? q('SELECT k.*, us.email, us.aktif FROM klien k LEFT JOIN users us ON us.id = k.user_id WHERE k.id = ?', [$id])->fetch() : null;
if ($id && !$k) {
    flash('danger', 'Klien tidak ditemukan.');
    redirect('klien');
}
if ($k && $k['aktif'] === null) $k['aktif'] = 1; // klien belum punya akun
$pageTitle = $k ? 'Ubah Klien' : 'Klien Baru';

$data = $k ?: ['nama' => '', 'alamat' => '', 'telepon' => '', 'catatan' => '', 'email' => '', 'aktif' => 1, 'user_id' => null];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = array_merge($data, [
        'nama'    => input('nama'),
        'alamat'  => input('alamat'),
        'telepon' => input('telepon'),
        'catatan' => input('catatan'),
        'email'   => strtolower(input('email')),
        'aktif'   => isset($_POST['aktif']) ? 1 : 0,
    ]);
    $password = (string)($_POST['password'] ?? '');
    $userId = $k['user_id'] ?? null;

    if ($data['nama'] === '') $errors[] = 'Nama klien wajib diisi.';
    if ($data['email'] !== '') {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
        elseif (q('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$data['email'], (int)$userId])->fetch()) $errors[] = 'Email sudah dipakai akun lain.';
        if ((!$userId || $password !== '') && ($lemah = cek_password($password, $data['email'], $data['nama']))) $errors[] = ($userId ? '' : 'Akun login klien: ') . $lemah;
    } elseif ($userId) {
        $errors[] = 'Email akun login tidak boleh dikosongkan. Matikan "Akun aktif" jika ingin memblokir login klien.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($data['email'] !== '') {
                if ($userId) {
                    q('UPDATE users SET nama=?, email=?, no_hp=?, aktif=? WHERE id=?', [$data['nama'], $data['email'], $data['telepon'] ?: null, $data['aktif'], $userId]);
                    if ($password !== '') q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
                } else {
                    q("INSERT INTO users (nama, email, password_hash, role, no_hp, aktif) VALUES (?,?,?,'klien',?,?)",
                        [$data['nama'], $data['email'], password_hash($password, PASSWORD_DEFAULT), $data['telepon'] ?: null, $data['aktif']]);
                    $userId = (int)$pdo->lastInsertId();
                }
            }
            $vals = [$data['nama'], $data['alamat'] ?: null, $data['telepon'] ?: null, $data['catatan'] ?: null, $userId];
            if ($k) {
                q('UPDATE klien SET nama=?, alamat=?, telepon=?, catatan=?, user_id=? WHERE id=?', array_merge($vals, [$id]));
                log_aktivitas('ubah', 'klien', $id, $data['nama']);
            } else {
                q('INSERT INTO klien (nama, alamat, telepon, catatan, user_id) VALUES (?,?,?,?,?)', $vals);
                $id = (int)$pdo->lastInsertId();
                log_aktivitas('tambah', 'klien', $id, $data['nama']);
            }
            $pdo->commit();
            flash('success', 'Data klien tersimpan.');
            redirect(input('lanjut') === 'proyek' ? 'proyek_form?klien_id=' . $id : 'klien');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan.' . (APP_DEBUG ? ' ' . $ex->getMessage() : '');
        }
    }
}

$kembali = 'klien';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1 class="h4 mb-0"><?= e($pageTitle) ?></h1>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<form method="post" class="card" autocomplete="off"><div class="card-body">
  <?= csrf_field() ?>
  <?php if ($k): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Nama klien *</label><input class="form-control" name="nama" value="<?= e($data['nama']) ?>" required maxlength="150"></div>
    <div class="col-md-6"><label class="form-label">Telepon / WhatsApp</label><input class="form-control" name="telepon" value="<?= e($data['telepon']) ?>" maxlength="30" inputmode="tel"></div>
    <div class="col-12"><label class="form-label">Alamat</label><textarea class="form-control" name="alamat" rows="2"><?= e($data['alamat']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Catatan</label><textarea class="form-control" name="catatan" rows="2"><?= e($data['catatan']) ?></textarea></div>
  </div>

  <h2 class="h6 mt-4 mb-1">Akun login klien</h2>
  <p class="small text-muted mb-2">Opsional. Isi supaya klien bisa login dan memantau proyeknya. Klien hanya melihat dokumen yang sudah disetujui CEO dan ditandai untuk klien.</p>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($data['email']) ?>" maxlength="150"></div>
    <div class="col-md-6">
      <label class="form-label"><?= !empty($k['user_id']) ? 'Password baru (kosongkan jika tidak diganti)' : 'Password' ?></label>
      <input class="form-control" type="password" name="password" minlength="10" autocomplete="new-password">
    </div>
    <div class="col-12">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="aktif" id="aktif" value="1" <?= (int)$data['aktif'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="aktif">Akun aktif (bisa login)</label>
      </div>
    </div>
  </div>
  <div class="mt-4 d-flex gap-2 flex-wrap">
    <button class="btn btn-brand"><i class="bi bi-check-lg"></i> Simpan</button>
    <?php if (!$k): ?><button class="btn btn-outline-dark" name="lanjut" value="proyek">Simpan &amp; buat proyek</button><?php endif; ?>
  </div>
</div></form>

<?php require __DIR__ . '/includes/footer.php'; ?>
