<?php
/**
 * Kerangka halaman. Tiga mode tampilan otomatis menurut lebar layar:
 *   HP (<768px)        : satu kolom + tab bawah
 *   Tablet (768-1099px): isi lebar + dok tab melayang (kaca)
 *   Laptop (>=1100px)  : bilah navigasi atas (kaca) + isi lebar
 * Tema terang/gelap: html[data-theme], disimpan di localStorage 'tb_tema' (terang|gelap|otomatis).
 *
 * Variabel opsional dari halaman:
 *   $pageTitle   judul halaman
 *   $activeMenu  kunci menu aktif (lihat includes/menu.php)
 *   $beranda     true = tanpa top bar (halaman menampilkan header sendiri)
 *   $kembali     URL tombol kembali (bawaan: dashboard)
 */
require_once __DIR__ . '/menu.php';
$u = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$activeMenu = $activeMenu ?? '';
$beranda = $beranda ?? false;
$kembali = $kembali ?? 'dashboard';
kirim_csp();
$menunggu = ($u && in_array($u['role'], ['ceo', 'admin'], true)) ? jumlah_menunggu_persetujuan() : 0;
?>
<!doctype html>
<html lang="id" data-theme="light" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#111111">
<meta name="color-scheme" content="light dark">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle) ?> · <?= e(APP_SHORT) ?></title>
<script nonce="<?= csp_nonce() ?>">
// Pasang tema sebelum halaman tampil (mencegah kedip putih di mode gelap)
(function () {
  var p = 'otomatis';
  try { p = localStorage.getItem('tb_tema') || 'otomatis'; } catch (e) {}
  var gelap = p === 'gelap' || (p === 'otomatis' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
  var h = document.documentElement;
  h.dataset.theme = gelap ? 'dark' : 'light';
  h.dataset.bsTheme = gelap ? 'dark' : 'light';
  h.dataset.temaPilihan = p;
})();
</script>
<link rel="manifest" href="manifest.json">
<link rel="icon" href="assets/img/icon-192.png">
<link rel="apple-touch-icon" href="assets/img/icon-192.png">
<link href="<?= e(vendor('font_css')) ?>" rel="stylesheet">
<link href="<?= e(vendor('bs_css')) ?>" rel="stylesheet">
<link href="<?= e(vendor('ikon_css')) ?>" rel="stylesheet">
<link href="<?= aset('assets/css/app.css') ?>" rel="stylesheet">
<?= intro_kepala() ?>
</head>
<body class="<?= $beranda ? 'mode-beranda' : 'mode-halaman' ?>">
<?= intro_lapisan() ?>
<?php if ($u):
    // Bilah navigasi atas — hanya tampil di laptop (>=1100px). Di HP/tablet diganti tab bawah.
    $inisialAtas = mb_strtoupper(mb_substr(trim($u['nama']), 0, 1));
    $navAtas = array_values(array_filter(menu_tab($u), fn($t) => !$t[4] && $t[0] !== 'semua'));
    if (in_array($u['role'], ['ceo', 'admin'], true)) array_splice($navAtas, 2, 0, [['persetujuan', 'persetujuan', 'bi-patch-check', 'Persetujuan', false]]);
    if ($u['role'] === 'admin') $navAtas[] = ['klien', 'klien', 'bi-person-vcard', 'Klien', false];
    $navAtas[] = ['semua', 'semua_menu', 'bi-grid', 'Semua menu', false];
    $tabUtama = array_values(array_filter(menu_tab($u), fn($t) => $t[4]))[0] ?? null;
?>
<header class="navatas" aria-label="Navigasi atas">
  <div class="navatas-isi">
    <a href="dashboard" class="navatas-logo" aria-label="Beranda <?= e(APP_NAME) ?>">
      <img src="assets/img/logo-light.png" alt="" class="logo-terang">
      <img src="assets/img/logo-dark.png" alt="" class="logo-gelap">
    </a>
    <nav class="navatas-menu" aria-label="Menu utama">
      <?php foreach ($navAtas as [$key, $href, $icon, $label]):
          $aktifAtas = $activeMenu === $key || ($key === 'semua' && !in_array($activeMenu, array_column($navAtas, 0), true) && $activeMenu !== '' && !($tabUtama && $activeMenu === $tabUtama[0])); ?>
        <a href="<?= e($href) ?>" class="<?= $aktifAtas ? 'aktif' : '' ?>" <?= $aktifAtas ? 'aria-current="page"' : '' ?> data-tour="atas-<?= e($key) ?>">
          <i class="bi <?= $icon ?>" aria-hidden="true"></i><span><?= e($label) ?></span>
          <?php if ($key === 'persetujuan' && $menunggu > 0): ?><em><?= $menunggu > 99 ? '99+' : $menunggu ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="navatas-kanan">
      <?php if ($tabUtama): [$kTab, $hTab, $iTab, $lTab] = $tabUtama; ?>
        <a href="<?= e($hTab) ?>" class="btn btn-brand navatas-utama" data-tour="atas-utama"><i class="bi <?= $iTab ?>" aria-hidden="true"></i> <?= e($lTab) ?></a>
      <?php endif; ?>
      <button type="button" class="tombol-bulat" data-ganti-tema aria-label="Ganti tema terang/gelap" title="Ganti tema">
        <i class="bi bi-moon-stars ikon-ke-gelap" aria-hidden="true"></i><i class="bi bi-sun ikon-ke-terang" aria-hidden="true"></i>
      </button>
      <a href="profil" class="navatas-profil" title="<?= e($u['nama']) ?> · <?= e(ROLE_LABEL[$u['role']]) ?>">
        <span class="avatar avatar-kecil"><?= e($inisialAtas) ?></span>
      </a>
    </div>
  </div>
</header>
<?php endif; ?>
<div class="app">
<?php if (!$beranda): ?>
  <header class="app-bar">
    <a href="<?= e($kembali) ?>" class="app-bar-btn" aria-label="Kembali" data-kembali><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
    <div class="app-bar-title"><?= e($pageTitle) ?></div>
    <?php if ($menunggu > 0 && $activeMenu !== 'persetujuan'): ?>
      <a href="persetujuan" class="app-bar-btn" aria-label="<?= $menunggu ?> dokumen menunggu persetujuan">
        <i class="bi bi-bell" aria-hidden="true"></i><span class="titik-merah"></span>
      </a>
    <?php else: ?>
      <button type="button" class="app-bar-btn" data-ganti-tema aria-label="Ganti tema terang/gelap">
        <i class="bi bi-moon-stars ikon-ke-gelap" aria-hidden="true"></i><i class="bi bi-sun ikon-ke-terang" aria-hidden="true"></i>
      </button>
    <?php endif; ?>
  </header>
<?php endif; ?>
  <main class="konten">
    <?= render_flash() ?>
