<?php
require __DIR__ . '/includes/init.php';
$u = require_login();
$pageTitle = 'Proyek';
$activeMenu = 'proyek';

[$wp, $pp] = filter_proyek_sql($u);
$status = input('status');
$cari = input('q');

$sql = "SELECT p.*, k.nama AS nama_klien FROM proyek p JOIN klien k ON k.id = p.klien_id WHERE $wp";
$params = $pp;
if (isset(STATUS_PROYEK[$status])) {
    $sql .= ' AND p.status = ?';
    $params[] = $status;
}
if ($cari !== '') {
    $sql .= ' AND (p.nama_proyek LIKE ? OR p.kode_proyek LIKE ? OR k.nama LIKE ? OR p.lokasi LIKE ?)';
    array_push($params, "%$cari%", "%$cari%", "%$cari%", "%$cari%");
}
$sql .= " ORDER BY FIELD(p.status,'berjalan','tertunda','penawaran','selesai'), p.dibuat_pada DESC";
$list = q($sql, $params)->fetchAll();

require __DIR__ . '/includes/header.php';
$urlStatus = fn(string $st) => 'proyek?' . http_build_query(array_filter(['status' => $st, 'q' => $cari]));
?>
<div class="page-head">
  <h1 class="h4 mb-0"><?= $u['role'] === 'klien' ? 'Proyek saya' : 'Proyek' ?></h1>
</div>

<form method="get" class="mb-3" role="search">
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <div class="input-group">
    <span class="input-group-text bg-white border-end-0" style="border-radius:12px 0 0 12px"><i class="bi bi-search" aria-hidden="true"></i></span>
    <input class="form-control border-start-0" type="search" name="q" value="<?= e($cari) ?>" placeholder="Cari proyek, kode, klien, lokasi" aria-label="Cari proyek">
  </div>
</form>

<div class="chip-baris mb-3" aria-label="Filter status">
  <a href="<?= e($urlStatus('')) ?>" class="chip <?= $status === '' ? 'aktif' : '' ?>">Semua</a>
  <?php foreach (STATUS_PROYEK as $k => [$label]): ?>
    <a href="<?= e($urlStatus($k)) ?>" class="chip <?= $status === $k ? 'aktif' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="daftar-kartu">
  <?php foreach ($list as $p): ?><?= proyek_card($p, $u) ?><?php endforeach; ?>
  <?php if (!$list): ?><div class="panel"><?= empty_state('bi-buildings', 'Tidak ada proyek yang cocok.') ?></div><?php endif; ?>
</div>

<?php if ($u['role'] === 'admin'): ?>
  <a href="proyek_form" class="btn btn-brand w-100 mt-3 tombol-bawah" style="height:48px;display:flex;align-items:center;justify-content:center;gap:6px"><i class="bi bi-plus-lg" aria-hidden="true"></i> Proyek baru</a>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
