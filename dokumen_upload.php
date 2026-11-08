<?php
require __DIR__ . '/includes/init.php';
$u = require_role('admin', 'tim');
$pageTitle = 'Upload Dokumen';
$activeMenu = 'proyek';

[$wp, $pp] = filter_proyek_sql($u);
$proyekList = q("SELECT p.id, p.kode_proyek, p.nama_proyek FROM proyek p WHERE $wp AND p.status <> 'selesai' OR (p.id = ? AND $wp)
                 ORDER BY p.nama_proyek", array_merge($pp, [input_int('proyek_id')], $pp))->fetchAll();
$kategoriList = q('SELECT * FROM kategori_dokumen ORDER BY urutan')->fetchAll();
if ($u['role'] === 'tim') {
    $kategoriList = array_values(array_filter($kategoriList, fn($k) => !in_array((int)$k['id'], KATEGORI_RAHASIA_TIM, true)));
}
$limit = upload_limit_bytes();

$data = ['proyek_id' => input_int('proyek_id'), 'kategori_id' => 0, 'judul' => '', 'revisi' => 0, 'tampil_ke_klien' => 0, 'status' => 'menunggu', 'tanggal_dokumen' => date('Y-m-d')];
$errors = [];

if (is_post()) {
    // Jika file melebihi post_max_size, PHP mengosongkan $_POST
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'File terlalu besar. Batas maksimal ' . format_bytes($limit) . '.';
    } else {
        verify_csrf();
        $data = [
            'proyek_id'       => input_int('proyek_id'),
            'kategori_id'     => input_int('kategori_id'),
            'judul'           => input('judul'),
            'revisi'          => max(0, min(999, input_int('revisi'))),
            'tampil_ke_klien' => isset($_POST['tampil_ke_klien']) ? 1 : 0,
            'status'          => input('status') === 'draft' ? 'draft' : 'menunggu',
            'tanggal_dokumen' => input('tanggal_dokumen'),
        ];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['tanggal_dokumen'])) $data['tanggal_dokumen'] = date('Y-m-d');
        $proyek = proyek_akses($data['proyek_id'], $u);
        if (!$proyek) $errors[] = 'Pilih proyek.';
        if (!in_array($data['kategori_id'], array_map('intval', array_column($kategoriList, 'id')), true)) $errors[] = 'Pilih kategori.';
        if ($data['judul'] === '') $errors[] = 'Judul dokumen wajib diisi.';

        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Pilih file yang akan diunggah.';
        } elseif (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > $limit) {
            $errors[] = 'File terlalu besar. Batas maksimal ' . format_bytes($limit) . '.';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload gagal (kode ' . (int)$f['error'] . '). Coba lagi.';
        } else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';
            if (!in_array($ext, ALLOWED_DOC_EXT, true)) {
                $errors[] = 'Format file tidak diizinkan. Gunakan: ' . strtoupper(implode(', ', ALLOWED_DOC_EXT)) . '.';
            } elseif ($salah = cek_isi_dokumen($f['tmp_name'], $ext)) {
                $errors[] = $salah;
            }
        }

        if (!$errors) {
            $dir = UPLOAD_DIR . '/dokumen';
            $namaSimpan = date('Ym') . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
            if (!siapkan_folder_upload('dokumen') || !@move_uploaded_file($f['tmp_name'], $dir . '/' . $namaSimpan)) {
                $errors[] = pesan_izin_uploads();
            } else {
                q('INSERT INTO dokumen (proyek_id, kategori_id, judul, revisi, nama_file, path_file, tipe_file, ukuran, tanggal_dokumen, tampil_ke_klien, status_persetujuan, diupload_oleh)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
                    $data['proyek_id'], $data['kategori_id'], $data['judul'], $data['revisi'],
                    mb_substr(basename($f['name']), 0, 255), 'dokumen/' . $namaSimpan, $mime, (int)$f['size'], $data['tanggal_dokumen'],
                    $data['tampil_ke_klien'], $data['status'], $u['id'],
                ]);
                $did = (int)db()->lastInsertId();
                log_aktivitas('upload', 'dokumen', $did, $data['judul'] . ' — ' . $proyek['nama_proyek']);
                if ($data['status'] === 'menunggu') {
                    notif_kirim(tanpa_saya(notif_ceo()), 'Dokumen menunggu persetujuan', $data['judul'] . ' · ' . $proyek['nama_proyek'] . ' (oleh ' . $u['nama'] . ')', 'persetujuan', 'menunggu');
                }
                flash('success', $data['status'] === 'menunggu'
                    ? 'Dokumen terunggah dan diajukan ke CEO untuk disetujui.'
                    : 'Dokumen tersimpan sebagai draft. Ajukan ke CEO saat sudah siap.');
                redirect('proyek_detail?id=' . $data['proyek_id']);
            }
        }
    }
}

$kembali = $data['proyek_id'] ? 'proyek_detail?id=' . $data['proyek_id'] : 'proyek';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1 class="h4 mb-0">Upload Dokumen</h1>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card" data-maxsize="<?= $limit ?>"><div class="card-body">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Proyek *</label>
      <select class="form-select" name="proyek_id" required>
        <option value="">— pilih proyek —</option>
        <?php foreach ($proyekList as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $data['proyek_id'] ? 'selected' : '' ?>><?= e($p['kode_proyek'] . ' · ' . $p['nama_proyek']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Kategori *</label>
      <select class="form-select" name="kategori_id" required>
        <option value="">— pilih kategori —</option>
        <?php foreach ($kategoriList as $k): ?>
          <option value="<?= (int)$k['id'] ?>" <?= (int)$k['id'] === $data['kategori_id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Judul dokumen *</label>
      <input class="form-control" name="judul" value="<?= e($data['judul']) ?>" maxlength="200" required placeholder="mis. Gambar Denah Lantai 1">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Tanggal dokumen</label>
      <input class="form-control" type="date" name="tanggal_dokumen" value="<?= e($data['tanggal_dokumen']) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Revisi ke-</label>
      <input class="form-control" type="number" name="revisi" min="0" max="999" value="<?= (int)$data['revisi'] ?>">
      <div class="form-text">0 = versi pertama</div>
    </div>
    <div class="col-12">
      <label class="form-label">File *</label>
      <input class="form-control" type="file" name="file" required accept=".<?= implode(',.', ALLOWED_DOC_EXT) ?>">
      <div class="form-text">PDF, gambar, Excel, Word, atau DWG. Maksimal <?= format_bytes($limit) ?>.</div>
    </div>
    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="tampil_ke_klien" id="tkk" value="1" <?= $data['tampil_ke_klien'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="tkk">Tampilkan ke klien setelah disetujui CEO</label>
      </div>
    </div>
    <div class="col-12">
      <label class="form-label d-block">Setelah diunggah</label>
      <div class="btn-group" role="group">
        <input type="radio" class="btn-check" name="status" id="st1" value="menunggu" <?= $data['status'] === 'menunggu' ? 'checked' : '' ?>>
        <label class="btn btn-outline-dark" for="st1"><i class="bi bi-send"></i> Ajukan ke CEO</label>
        <input type="radio" class="btn-check" name="status" id="st2" value="draft" <?= $data['status'] === 'draft' ? 'checked' : '' ?>>
        <label class="btn btn-outline-dark" for="st2"><i class="bi bi-pencil"></i> Simpan draft</label>
      </div>
      <div class="form-text">Semua dokumen wajib disetujui CEO sebelum bisa dilihat klien.</div>
    </div>
  </div>
  <div class="mt-4"><button class="btn btn-brand" data-loading="Mengunggah…"><i class="bi bi-upload"></i> Upload</button></div>
</div></form>

<?php require __DIR__ . '/includes/footer.php'; ?>
