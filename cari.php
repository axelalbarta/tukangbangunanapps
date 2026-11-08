<?php
require __DIR__ . '/includes/init.php';
$u = require_role('ceo', 'admin', 'tim');
$pageTitle = 'Cari Dokumen';
$activeMenu = 'cari';

[$wp, $pp] = filter_proyek_sql($u);
$wd = filter_dokumen_sql($u);
$proyekList = q("SELECT p.id, p.nama_proyek FROM proyek p WHERE $wp ORDER BY p.nama_proyek", $pp)->fetchAll();
$kategoriList = q('SELECT * FROM kategori_dokumen ORDER BY urutan')->fetchAll();

$f = [
    'q' => input('q'), 'proyek_id' => input_int('proyek_id'), 'kategori_id' => input_int('kategori_id'),
    'status' => input('status'), 'dari' => input('dari'), 'sampai' => input('sampai'),
];
$dicari = array_filter($f) !== [];
$hasil = [];
if ($dicari) {
    $sql = "SELECT d.*, kd.nama AS kategori, p.nama_proyek FROM dokumen d
            JOIN proyek p ON p.id = d.proyek_id JOIN kategori_dokumen kd ON kd.id = d.kategori_id
            WHERE $wp AND $wd";
    $params = $pp;
    if ($f['q'] !== '') {
        $sql .= ' AND (d.judul LIKE ? OR d.nama_file LIKE ? OR p.nama_proyek LIKE ?)';
        array_push($params, "%{$f['q']}%", "%{$f['q']}%", "%{$f['q']}%");
    }
    if ($f['proyek_id']) { $sql .= ' AND d.proyek_id = ?'; $params[] = $f['proyek_id']; }
    if ($f['kategori_id']) { $sql .= ' AND d.kategori_id = ?'; $params[] = $f['kategori_id']; }
    if (isset(STATUS_DOK[$f['status']])) { $sql .= ' AND d.status_persetujuan = ?'; $params[] = $f['status']; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['dari'])) { $sql .= ' AND d.dibuat_pada >= ?'; $params[] = $f['dari'] . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['sampai'])) { $sql .= ' AND d.dibuat_pada <= ?'; $params[] = $f['sampai'] . ' 23:59:59'; }
    $sql .= ' ORDER BY d.dibuat_pada DESC LIMIT 200';
    $hasil = q($sql, $params)->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1 class="h4 mb-0">Cari Dokumen</h1></div>

<form class="card mb-3" method="get"><div class="card-body">
  <div class="row g-2">
    <div class="col-12"><input class="form-control form-control-lg" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Judul dokumen, nama file, atau proyek" autofocus></div>
    <div class="col-md-4">
      <select class="form-select" name="proyek_id"><option value="">Semua proyek</option>
        <?php foreach ($proyekList as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $f['proyek_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nama_proyek']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select class="form-select" name="kategori_id"><option value="">Semua kategori</option>
        <?php foreach ($kategoriList as $k): if ($u['role'] === 'tim' && in_array((int)$k['id'], KATEGORI_RAHASIA_TIM, true)) continue; ?>
          <option value="<?= (int)$k['id'] ?>" <?= $f['kategori_id'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select class="form-select" name="status"><option value="">Semua status</option>
        <?php foreach (STATUS_DOK as $k => [$label]): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2"><input class="form-control" type="date" name="dari" value="<?= e($f['dari']) ?>" title="Dari tanggal"></div>
    <div class="col-6 col-md-2"><input class="form-control" type="date" name="sampai" value="<?= e($f['sampai']) ?>" title="Sampai tanggal"></div>
  </div>
  <div class="mt-2 d-flex gap-2"><button class="btn btn-brand"><i class="bi bi-search"></i> Cari</button><a href="cari" class="btn btn-light">Reset</a></div>
</div></form>

<?php if ($dicari): ?>
  <div class="small text-muted mb-2"><?= count($hasil) ?> dokumen ditemukan<?= count($hasil) === 200 ? ' (ditampilkan 200 terbaru)' : '' ?></div>
  <div class="card">
    <?php if (!$hasil): ?><?= empty_state('bi-search', 'Tidak ada dokumen yang cocok.') ?><?php else: ?>
    <ul class="list-group list-group-flush">
      <?php foreach ($hasil as $d): ?>
        <li class="list-group-item d-flex align-items-center gap-3">
          <i class="bi <?= ikon_file($d['nama_file']) ?> fs-4"></i>
          <div class="flex-grow-1 min-w-0">
            <a href="download?id=<?= (int)$d['id'] ?>"<?= atribut_lihat($d['nama_file'], $d['judul']) ?> target="_blank" class="fw-semibold"><?= e($d['judul']) ?><?= $d['revisi'] ? ' Rev.' . (int)$d['revisi'] : '' ?></a>
            <div class="small text-muted text-truncate"><?= e($d['kategori']) ?> · <a class="text-muted" href="proyek_detail?id=<?= (int)$d['proyek_id'] ?>"><?= e($d['nama_proyek']) ?></a> · <?= tgl($d['tanggal_dokumen'] ?: $d['dibuat_pada']) ?> · <?= format_bytes((int)$d['ukuran']) ?></div>
          </div>
          <?= badge(STATUS_DOK, $d['status_persetujuan']) ?>
          <a href="download?id=<?= (int)$d['id'] ?>&dl=1" class="btn btn-sm btn-light" title="Unduh"><i class="bi bi-download"></i></a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
