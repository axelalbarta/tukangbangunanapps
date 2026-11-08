<?php
require __DIR__ . '/includes/init.php';
$u = require_role('ceo', 'admin');
$pageTitle = 'Log Aktivitas';
$activeMenu = 'log';

$objek = input('objek');
$sql = 'SELECT l.*, us.nama FROM log_aktivitas l LEFT JOIN users us ON us.id = l.user_id';
$params = [];
if (in_array($objek, ['dokumen', 'proyek', 'progres', 'user', 'klien'], true)) {
    $sql .= ' WHERE l.objek = ?';
    $params[] = $objek;
}
$list = q($sql . ' ORDER BY l.waktu DESC, l.id DESC LIMIT 300', $params)->fetchAll();

$ikon = [
    'login' => 'bi-box-arrow-in-right', 'logout' => 'bi-box-arrow-right', 'tambah' => 'bi-plus-circle', 'ubah' => 'bi-pencil',
    'hapus' => 'bi-trash text-danger', 'upload' => 'bi-upload', 'lihat' => 'bi-eye', 'setujui' => 'bi-check-circle text-success',
    'tolak' => 'bi-x-circle text-danger', 'ajukan' => 'bi-send', 'lapor' => 'bi-camera', 'impor' => 'bi-box-arrow-in-down', 'unduh_zip' => 'bi-file-earmark-zip',
    'tampilkan_klien' => 'bi-eye text-info', 'sembunyikan_klien' => 'bi-eye-slash',
];

// Percobaan login gagal 24 jam terakhir (tanda ada yang mencoba menebak password)
$gagal = q('SELECT email, COUNT(*) AS jml, MAX(waktu) AS terakhir, COUNT(DISTINCT ip) AS jml_ip FROM login_percobaan
            WHERE waktu > ? GROUP BY email ORDER BY jml DESC LIMIT 10', [date('Y-m-d H:i:s', time() - 86400)])->fetchAll();

$kembali = 'semua_menu';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1 class="h4 mb-0">Log Aktivitas</h1></div>
<?php if ($gagal): ?>
  <section class="panel mb-3" style="border-left:4px solid var(--brand)">
    <h2 class="h6 fw-bold mb-1"><i class="bi bi-shield-exclamation text-brand" aria-hidden="true"></i> Percobaan login gagal (24 jam)</h2>
    <p class="small text-muted mb-2">Banyak percobaan pada satu akun bisa berarti ada yang menebak password. Akun otomatis dikunci 15 menit setelah 5 kali gagal.</p>
    <?php foreach ($gagal as $g): ?>
      <div class="d-flex justify-content-between small py-1 border-bottom">
        <span class="text-truncate me-2"><?= e($g['email'] ?: '(kosong)') ?></span>
        <span class="text-nowrap"><b><?= (int)$g['jml'] ?>×</b> dari <?= (int)$g['jml_ip'] ?> IP · <?= e(tgl_waktu($g['terakhir'])) ?></span>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
<div class="d-flex gap-2 flex-wrap mb-3">
  <?php foreach (['' => 'Semua', 'dokumen' => 'Dokumen', 'progres' => 'Progres', 'proyek' => 'Proyek', 'klien' => 'Klien', 'user' => 'User & login'] as $k => $label): ?>
    <a href="log<?= $k ? '?objek=' . $k : '' ?>" class="btn btn-sm <?= $objek === $k ? 'btn-dark' : 'btn-light' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>
<div class="card"><ul class="list-group list-group-flush">
  <?php foreach ($list as $l): ?>
    <li class="list-group-item d-flex gap-3">
      <i class="bi <?= $ikon[$l['aksi']] ?? 'bi-dot' ?> fs-5"></i>
      <div class="flex-grow-1 min-w-0">
        <div><strong><?= e($l['nama'] ?? 'User terhapus') ?></strong> <?= e(str_replace('_', ' ', $l['aksi'])) ?> <?= e($l['objek']) ?></div>
        <?php if ($l['keterangan']): ?><div class="small text-muted text-break"><?= e($l['keterangan']) ?></div><?php endif; ?>
      </div>
      <div class="small text-muted text-nowrap"><?= tgl_waktu($l['waktu']) ?></div>
    </li>
  <?php endforeach; ?>
  <?php if (!$list): ?><li class="list-group-item"><?= empty_state('bi-clock-history', 'Belum ada aktivitas.') ?></li><?php endif; ?>
</ul></div>
<p class="small text-muted mt-2">Menampilkan 300 aktivitas terbaru.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
