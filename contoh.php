<?php
// Isi / hapus data contoh untuk mencoba semua fitur. Khusus admin.
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/contoh.php';
$u = require_role('admin');
$pageTitle = 'Data contoh';
$activeMenu = 'contoh';
$kembali = 'semua_menu';
$akunBaru = $_SESSION['akun_contoh'] ?? null;
unset($_SESSION['akun_contoh']);

if (is_post()) {
    verify_csrf();
    @set_time_limit(120);
    try {
        if (input('aksi') === 'isi' && !contoh_ada()) {
            $_SESSION['akun_contoh'] = contoh_isi($u);
            log_aktivitas('tambah', 'proyek', null, 'mengisi data contoh');
            flash('success', 'Data contoh berhasil diisi: 6 proyek, 5 klien, 6 akun, 26 dokumen, dan 15 laporan progres dengan foto.');
        } elseif (input('aksi') === 'hapus' && contoh_ada()) {
            if (strtoupper(trim(input('konfirmasi'))) !== 'HAPUS') {
                flash('danger', 'Ketik HAPUS untuk konfirmasi.');
            } else {
                $r = contoh_hapus();
                log_aktivitas('hapus', 'proyek', null, "menghapus data contoh ({$r['proyek']} proyek, {$r['user']} akun, {$r['file']} file)");
                flash('success', "Data contoh dihapus: {$r['proyek']} proyek, {$r['klien']} klien, {$r['user']} akun, {$r['file']} file.");
            }
        }
    } catch (Throwable $ex) {
        flash('danger', 'Gagal: ' . $ex->getMessage());
    }
    redirect('contoh');
}

$ada = contoh_ada();
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1 class="h4 mb-0">Data contoh</h1></div>

<?php if ($akunBaru): ?>
  <section class="panel mb-3" style="border:2px solid var(--brand)">
    <h2 class="h6 fw-bold mb-1"><i class="bi bi-key" aria-hidden="true"></i> Akun contoh untuk mencoba login</h2>
    <p class="small text-muted mb-2">Password hanya ditampilkan sekali. Akun CEO contoh akan diminta mengaktifkan verifikasi 2 langkah saat pertama login.</p>
    <div class="table-responsive"><table class="table table-sm small mb-2">
      <thead><tr><th>Nama</th><th>Role</th><th>Email</th><th>Password</th></tr></thead>
      <tbody>
        <?php foreach ($akunBaru as [$nama, $role, $email, $pw]): ?>
          <tr><td><?= e($nama) ?></td><td><?= e($role) ?></td><td><code><?= e($email) ?></code></td><td><code><?= e($pw) ?></code></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <button type="button" class="btn btn-light btn-sm" data-salin="<?= e(implode("\n", array_map(fn($a) => $a[1] . ': ' . $a[2] . ' / ' . $a[3], $akunBaru))) ?>"><i class="bi bi-clipboard" aria-hidden="true"></i> Salin semua</button>
  </section>
<?php endif; ?>

<section class="panel mb-3">
  <h2 class="h6 fw-bold mb-1">Isi data contoh</h2>
  <p class="small text-muted">Untuk mencoba semua fitur: 6 proyek (berjalan, tertunda, selesai, penawaran) di sekitar Fakfak lengkap dengan titik peta,
    5 klien, akun CEO/arsitek/pengawas/mandor/klien, 26 dokumen (PDF & gambar denah) dengan berbagai status persetujuan,
    serta 15 laporan progres dengan foto. Semua data contoh diberi tanda <b>CTH-</b> / <b>@contoh.tb</b> dan bisa dihapus bersih kapan saja.</p>
  <?php if ($ada): ?>
    <div class="alert alert-info small mb-0">Data contoh sudah terisi.</div>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="isi">
      <button class="btn btn-brand" data-loading="Mengisi…"><i class="bi bi-magic" aria-hidden="true"></i> Isi data contoh</button>
    </form>
  <?php endif; ?>
</section>

<?php if ($ada): ?>
<section class="panel" style="border-left:4px solid var(--brand)">
  <h2 class="h6 fw-bold mb-1">Hapus data contoh</h2>
  <p class="small text-muted">Menghapus semua proyek CTH-, klien contoh, akun @contoh.tb, beserta dokumen & fotonya. Data asli Anda tidak tersentuh.</p>
  <form method="post" class="d-flex gap-2" data-confirm="Hapus semua data contoh?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
    <input class="form-control" name="konfirmasi" placeholder="Ketik HAPUS" required autocomplete="off" style="max-width:180px" aria-label="Ketik HAPUS">
    <button class="btn btn-outline-danger">Hapus data contoh</button>
  </form>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
