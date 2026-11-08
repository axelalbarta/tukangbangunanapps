<?php
require __DIR__ . '/includes/init.php';
$u = require_role('admin');
$activeMenu = 'proyek';

$id = input_int('id');
$p = $id ? q('SELECT * FROM proyek WHERE id = ?', [$id])->fetch() : null;
if ($id && !$p) {
    flash('danger', 'Proyek tidak ditemukan.');
    redirect('proyek');
}
$pageTitle = $p ? 'Ubah Proyek' : 'Proyek Baru';

$klienList = q('SELECT id, nama FROM klien ORDER BY nama')->fetchAll();
$timList = q("SELECT id, nama, jabatan FROM users WHERE role = 'tim' AND aktif = 1 ORDER BY nama")->fetchAll();
$timTerpilih = $p ? q('SELECT user_id, peran FROM proyek_tim WHERE proyek_id = ?', [$id])->fetchAll(PDO::FETCH_KEY_PAIR) : [];

$data = $p ?: [
    'kode_proyek' => '', 'klien_id' => input_int('klien_id'), 'nama_proyek' => '', 'lokasi' => '', 'latitude' => null, 'longitude' => null, 'jenis_layanan' => 'pelaksanaan',
    'nilai_kontrak' => '', 'tgl_mulai' => date('Y-m-d'), 'tgl_target_selesai' => '', 'status' => 'berjalan', 'keterangan' => '',
];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = [
        'klien_id'           => input_int('klien_id'),
        'nama_proyek'        => input('nama_proyek'),
        'lokasi'             => input('lokasi'),
        'latitude'           => is_numeric(input('latitude')) ? round((float)input('latitude'), 7) : null,
        'longitude'          => is_numeric(input('longitude')) ? round((float)input('longitude'), 7) : null,
        'jenis_layanan'      => input('jenis_layanan'),
        'nilai_kontrak'      => preg_replace('/[^0-9]/', '', input('nilai_kontrak')),
        'tgl_mulai'          => input('tgl_mulai'),
        'tgl_target_selesai' => input('tgl_target_selesai'),
        'status'             => input('status'),
        'keterangan'         => input('keterangan'),
        'kode_proyek'        => $p['kode_proyek'] ?? '',
    ];
    if ($data['nama_proyek'] === '') $errors[] = 'Nama proyek wajib diisi.';
    if (!q('SELECT 1 FROM klien WHERE id = ?', [$data['klien_id']])->fetch()) $errors[] = 'Pilih klien.';
    if (!isset(LAYANAN[$data['jenis_layanan']])) $errors[] = 'Jenis layanan tidak valid.';
    if ($data['latitude'] === null || $data['longitude'] === null
        || abs($data['latitude']) > 90 || abs($data['longitude']) > 180) {
        $data['latitude'] = $data['longitude'] = null;
    }
    if (!isset(STATUS_PROYEK[$data['status']])) $errors[] = 'Status tidak valid.';
    foreach (['tgl_mulai', 'tgl_target_selesai'] as $f) {
        if ($data[$f] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[$f])) $errors[] = 'Format tanggal tidak valid.';
    }

    $timBaru = [];
    foreach ((array)($_POST['tim'] ?? []) as $uid => $on) {
        $uid = (int)$uid;
        $peran = (string)($_POST['peran'][$uid] ?? 'lainnya');
        if (in_array($uid, array_map('intval', array_column($timList, 'id')), true)) {
            $timBaru[$uid] = isset(PERAN_TIM[$peran]) ? $peran : 'lainnya';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $vals = [
                $data['klien_id'], $data['nama_proyek'], $data['lokasi'] ?: null, $data['jenis_layanan'],
                $data['nilai_kontrak'] === '' ? 0 : $data['nilai_kontrak'],
                $data['tgl_mulai'] ?: null, $data['tgl_target_selesai'] ?: null, $data['status'], $data['keterangan'] ?: null,
                $data['latitude'], $data['longitude'],
            ];
            if ($p) {
                q('UPDATE proyek SET klien_id=?, nama_proyek=?, lokasi=?, jenis_layanan=?, nilai_kontrak=?, tgl_mulai=?, tgl_target_selesai=?, status=?, keterangan=?, latitude=?, longitude=? WHERE id=?',
                    array_merge($vals, [$id]));
                log_aktivitas('ubah', 'proyek', $id, $data['nama_proyek']);
            } else {
                $tahun = date('Y');
                $n = (int)q("SELECT COUNT(*) FROM proyek WHERE kode_proyek LIKE ?", ["TB-$tahun-%"])->fetchColumn();
                do {
                    $n++;
                    $kode = sprintf('TB-%s-%03d', $tahun, $n);
                } while (q('SELECT 1 FROM proyek WHERE kode_proyek = ?', [$kode])->fetch());
                q('INSERT INTO proyek (klien_id, nama_proyek, lokasi, jenis_layanan, nilai_kontrak, tgl_mulai, tgl_target_selesai, status, keterangan, latitude, longitude, kode_proyek) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                    array_merge($vals, [$kode]));
                $id = (int)$pdo->lastInsertId();
                log_aktivitas('tambah', 'proyek', $id, $data['nama_proyek']);
            }
            q('DELETE FROM proyek_tim WHERE proyek_id = ?', [$id]);
            foreach ($timBaru as $uid => $peran) {
                q('INSERT INTO proyek_tim (proyek_id, user_id, peran) VALUES (?,?,?)', [$id, $uid, $peran]);
            }
            $pdo->commit();
            flash('success', 'Proyek berhasil disimpan.');
            redirect('proyek_detail?id=' . $id);
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan proyek.' . (APP_DEBUG ? ' ' . $ex->getMessage() : '');
        }
    }
    $timTerpilih = $timBaru;
}

$kembali = $p ? 'proyek_detail?id=' . $id : 'proyek';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1 class="h4 mb-0"><?= e($pageTitle) ?></h1>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<?php if (!$klienList): ?>
  <div class="alert alert-warning">Belum ada data klien. <a href="klien_form">Tambah klien dulu</a> sebelum membuat proyek.</div>
<?php endif; ?>

<form method="post" class="card"><div class="card-body">
  <?= csrf_field() ?>
  <?php if ($p): ?><input type="hidden" name="id" value="<?= $id ?>"><div class="mb-3 small text-muted">Kode proyek: <strong><?= e($p['kode_proyek']) ?></strong></div><?php endif; ?>
  <div class="row g-3">
    <div class="col-md-8">
      <label class="form-label">Nama proyek *</label>
      <input class="form-control" name="nama_proyek" value="<?= e($data['nama_proyek']) ?>" required maxlength="200" placeholder="mis. Rumah Tinggal 2 Lantai Bpk. Andi">
    </div>
    <div class="col-md-4">
      <label class="form-label">Klien *</label>
      <select class="form-select" name="klien_id" required>
        <option value="">— pilih klien —</option>
        <?php foreach ($klienList as $k): ?>
          <option value="<?= (int)$k['id'] ?>" <?= (int)$data['klien_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <a href="klien_form" class="small">+ klien baru</a>
    </div>
    <div class="col-md-8">
      <label class="form-label">Lokasi</label>
      <input class="form-control" name="lokasi" value="<?= e($data['lokasi']) ?>" maxlength="255">
    </div>
    <div class="col-md-4">
      <label class="form-label">Jenis layanan *</label>
      <select class="form-select" name="jenis_layanan">
        <?php foreach (LAYANAN as $k => $label): ?>
          <option value="<?= $k ?>" <?= $data['jenis_layanan'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <label class="form-label d-flex justify-content-between align-items-end flex-wrap gap-2">
        <span>Titik lokasi di peta <span class="text-muted small">(opsional)</span></span>
        <span class="small text-muted" id="peta-koordinat"><?= $data['latitude'] !== null ? e(rtrim(rtrim((string)$data['latitude'], '0'), '.') . ', ' . rtrim(rtrim((string)$data['longitude'], '0'), '.')) : 'Belum ditandai' ?></span>
      </label>
      <div class="input-group mb-2">
        <input class="form-control" id="peta-cari" placeholder="Cari alamat, atau tempel link / koordinat Google Maps">
        <button class="btn btn-outline-dark" type="button" id="peta-tombol-cari"><i class="bi bi-search"></i><span class="d-none d-sm-inline"> Cari</span></button>
        <button class="btn btn-outline-dark" type="button" id="peta-saya" title="Pakai lokasi saya sekarang"><i class="bi bi-crosshair"></i><span class="d-none d-sm-inline"> Lokasi saya</span></button>
      </div>
      <div id="peta" class="peta-proyek"></div>
      <div class="d-flex justify-content-between mt-1 flex-wrap gap-2">
        <div class="form-text mt-0">Klik peta atau geser pin merah untuk menandai lokasi proyek.</div>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" id="peta-hapus">Hapus titik</button>
      </div>
      <div class="small text-danger" id="peta-pesan"></div>
      <input type="hidden" name="latitude" id="lat" value="<?= e($data['latitude']) ?>">
      <input type="hidden" name="longitude" id="lng" value="<?= e($data['longitude']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Nilai kontrak (Rp)</label>
      <input class="form-control" name="nilai_kontrak" inputmode="numeric" data-rupiah value="<?= $data['nilai_kontrak'] !== '' ? number_format((float)$data['nilai_kontrak'], 0, ',', '.') : '' ?>" placeholder="0">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Tanggal mulai</label>
      <input class="form-control" type="date" name="tgl_mulai" value="<?= e($data['tgl_mulai']) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Target selesai</label>
      <input class="form-control" type="date" name="tgl_target_selesai" value="<?= e($data['tgl_target_selesai']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Status</label>
      <select class="form-select" name="status">
        <?php foreach (STATUS_PROYEK as $k => [$label]): ?>
          <option value="<?= $k ?>" <?= $data['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <label class="form-label">Keterangan</label>
      <textarea class="form-control" name="keterangan" rows="2"><?= e($data['keterangan']) ?></textarea>
    </div>
  </div>

  <h2 class="h6 mt-4 mb-2">Tim yang ditugaskan</h2>
  <?php if (!$timList): ?>
    <div class="text-muted small">Belum ada user tim internal. <a href="user_form">Tambah user tim</a>.</div>
  <?php else: ?>
    <div class="row g-2">
      <?php foreach ($timList as $t): $tid = (int)$t['id']; $on = isset($timTerpilih[$tid]); ?>
        <div class="col-md-6">
          <div class="border rounded p-2 d-flex align-items-center gap-2">
            <input class="form-check-input mt-0" type="checkbox" name="tim[<?= $tid ?>]" value="1" id="tim<?= $tid ?>" <?= $on ? 'checked' : '' ?>>
            <label class="flex-grow-1" for="tim<?= $tid ?>"><?= e($t['nama']) ?> <span class="text-muted small"><?= e($t['jabatan']) ?></span></label>
            <select class="form-select form-select-sm w-auto" name="peran[<?= $tid ?>]">
              <?php foreach (PERAN_TIM as $k => $label): ?>
                <option value="<?= $k ?>" <?= ($timTerpilih[$tid] ?? strtolower((string)$t['jabatan'])) === $k ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="mt-4"><button class="btn btn-brand"><i class="bi bi-check-lg"></i> Simpan proyek</button></div>
</div></form>

<?php if ($p):
    $jmlDok = (int)q('SELECT COUNT(*) FROM dokumen WHERE proyek_id = ?', [$id])->fetchColumn();
    $jmlFoto = (int)q('SELECT COUNT(*) FROM progres_foto f JOIN progres pr ON pr.id = f.progres_id WHERE pr.proyek_id = ?', [$id])->fetchColumn();
?>
<div class="card border-danger mt-4" id="hapus"><div class="card-body">
  <h2 class="h6 text-danger mb-1"><i class="bi bi-trash"></i> Hapus proyek</h2>
  <p class="small mb-2">Proyek <strong><?= e($p['kode_proyek']) ?></strong> beserta <strong><?= $jmlDok ?> dokumen</strong>, <strong><?= $jmlFoto ?> foto progres</strong>, dan penugasan tim akan dihapus <strong>permanen</strong> dan tidak bisa dikembalikan. Data klien tidak ikut terhapus.</p>
  <?php if ($jmlDok || $jmlFoto): ?>
    <p class="small mb-3">Sebaiknya <a href="unduh_proyek?id=<?= $id ?>">unduh semua berkas proyek (ZIP)</a> dulu sebagai cadangan. ZIP itu bisa diimpor ulang kapan saja.</p>
  <?php endif; ?>
  <form method="post" action="proyek_hapus" class="row g-2 align-items-end" data-confirm="Yakin menghapus proyek ini secara permanen?">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="col-sm-6 col-md-4">
      <label class="form-label small mb-1">Ketik <code><?= e($p['kode_proyek']) ?></code> untuk konfirmasi</label>
      <input class="form-control" name="konfirmasi" autocomplete="off" required>
    </div>
    <div class="col-auto"><button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Hapus proyek permanen</button></div>
  </form>
</div></div>
<?php endif; ?>

<link rel="stylesheet" href="<?= e(vendor('leaflet_css')) ?>">
<script nonce="<?= csp_nonce() ?>" src="<?= e(vendor('leaflet_js')) ?>"></script>
<script nonce="<?= csp_nonce() ?>" src="<?= aset('assets/js/peta.js') ?>"></script>
<script nonce="<?= csp_nonce() ?>">petaPilihLokasi();</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
