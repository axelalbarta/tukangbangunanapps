<?php
require __DIR__ . '/includes/init.php';
$u = require_role('admin');
$pageTitle = 'Klien';
$activeMenu = 'klien';

$cari = input('q');
$sql = 'SELECT k.*, us.email, us.aktif, (SELECT COUNT(*) FROM proyek p WHERE p.klien_id = k.id) AS jml_proyek
        FROM klien k LEFT JOIN users us ON us.id = k.user_id';
$params = [];
if ($cari !== '') {
    $sql .= ' WHERE k.nama LIKE ? OR k.telepon LIKE ? OR us.email LIKE ?';
    $params = ["%$cari%", "%$cari%", "%$cari%"];
}
$list = q($sql . ' ORDER BY k.nama', $params)->fetchAll();

$kembali = 'semua_menu';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1 class="h4 mb-0">Klien</h1>
</div>
<form class="mb-3" method="get" role="search">
  <input class="form-control" type="search" name="q" value="<?= e($cari) ?>" placeholder="Cari nama, telepon, email" aria-label="Cari klien">
</form>

<div class="daftar">
  <?php foreach ($list as $k): $inisial = mb_strtoupper(mb_substr(trim($k['nama']), 0, 1)); ?>
    <div class="baris">
      <a href="klien_form?id=<?= (int)$k['id'] ?>" class="d-flex align-items-center gap-3 flex-grow-1 min-w-0 text-decoration-none" style="color:inherit">
        <span class="avatar" style="width:40px;height:40px;font-size:1rem;background:var(--permukaan-3);color:var(--ink)"><?= e($inisial) ?></span>
        <span class="baris-isi">
          <span class="baris-judul"><?= e($k['nama']) ?></span>
          <span class="baris-sub"><?= e($k['telepon'] ?: ($k['alamat'] ?: 'Tanpa telepon')) ?></span>
          <span class="d-flex flex-wrap gap-1 mt-1">
            <span class="pil pil-info"><?= (int)$k['jml_proyek'] ?> proyek</span>
            <?php if ($k['email']): ?>
              <span class="pil <?= (int)$k['aktif'] ? 'pil-ok' : 'pil-draft' ?>"><?= (int)$k['aktif'] ? 'Bisa login' : 'Login nonaktif' ?></span>
            <?php else: ?>
              <span class="pil pil-draft">Tanpa akun</span>
            <?php endif; ?>
          </span>
        </span>
      </a>
      <a href="proyek_form?klien_id=<?= (int)$k['id'] ?>" class="btn btn-sm btn-light flex-shrink-0" title="Buat proyek untuk <?= e($k['nama']) ?>" aria-label="Buat proyek untuk <?= e($k['nama']) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Proyek</a>
    </div>
  <?php endforeach; ?>
  <?php if (!$list): ?><?= empty_state('bi-person-vcard', $cari !== '' ? 'Klien tidak ditemukan.' : 'Belum ada data klien.') ?><?php endif; ?>
</div>

<a href="klien_form" class="btn btn-brand w-100 mt-3 tombol-bawah" style="height:48px;display:flex;align-items:center;justify-content:center;gap:6px"><i class="bi bi-plus-lg" aria-hidden="true"></i> Klien baru</a>

<?php require __DIR__ . '/includes/footer.php'; ?>
