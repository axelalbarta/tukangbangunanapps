<?php
require __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/menu.php';
$u = require_login();
$pageTitle = 'Semua menu';
$activeMenu = 'semua';
$beranda = true;

$inisial = mb_strtoupper(mb_substr(trim($u['nama']), 0, 1));
$menunggu = in_array($u['role'], ['ceo', 'admin'], true) ? jumlah_menunggu_persetujuan() : 0;

require __DIR__ . '/includes/header.php';
?>
<section class="hero hero-pendek">
  <h1 class="hero-judul">Semua menu</h1>
  <a href="profil" class="kartu-profil">
    <span class="avatar"><?= e($inisial) ?></span>
    <span class="flex-grow-1 min-w-0">
      <b class="d-block text-truncate"><?= e($u['nama']) ?></b>
      <small class="d-block text-truncate"><?= e(ROLE_LABEL[$u['role']]) ?> · <?= e($u['email']) ?></small>
    </span>
    <i class="bi bi-chevron-right" aria-hidden="true"></i>
  </a>
</section>

<div class="isi-bawah-hero">
  <div class="semua-grup">
  <?php foreach (menu_grup($u) as $grup => $items): ?>
    <section class="panel menu-grup">
      <h2><?= e($grup) ?></h2>
      <nav class="grid-ikon" aria-label="<?= e($grup) ?>">
        <?php foreach ($items as [$key, $href, $icon, $label]): ?>
          <a href="<?= e($href) ?>" class="ikon-aksi" data-tour="menu-<?= e($key) ?>">
            <span class="ikon-kotak"><i class="bi <?= $icon ?>" aria-hidden="true"></i>
              <?php if ($key === 'persetujuan' && $menunggu > 0): ?><em class="lencana"><?= $menunggu ?></em><?php endif; ?>
            </span>
            <span><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </section>
  <?php endforeach; ?>
    <section class="panel menu-grup">
      <h2>Tampilan</h2>
      <div class="px-2">
        <div class="pilih-tema" role="group" aria-label="Tema tampilan">
          <button type="button" data-tema-pilih="terang" aria-pressed="false"><i class="bi bi-sun" aria-hidden="true"></i> Terang</button>
          <button type="button" data-tema-pilih="gelap" aria-pressed="false"><i class="bi bi-moon-stars" aria-hidden="true"></i> Gelap</button>
          <button type="button" data-tema-pilih="otomatis" aria-pressed="false"><i class="bi bi-circle-half" aria-hidden="true"></i> Otomatis</button>
        </div>
        <p class="small text-muted mt-2 mb-0">Otomatis mengikuti pengaturan HP atau laptop Anda.</p>
      </div>
    </section>
    <section class="panel menu-grup" data-notif-panel>
      <h2>Notifikasi</h2>
      <div class="px-2">
        <p class="small mb-2" data-notif-status>Memeriksa…</p>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-brand btn-sm" data-notif-aktifkan hidden><i class="bi bi-bell" aria-hidden="true"></i> Aktifkan notifikasi</button>
          <button type="button" class="btn btn-light btn-sm" data-notif-tes hidden>Kirim notifikasi uji</button>
          <button type="button" class="btn btn-light btn-sm" data-notif-matikan hidden>Matikan di perangkat ini</button>
        </div>
      </div>
    </section>
    <section class="panel menu-grup" data-pasang hidden>
      <h2>Aplikasi</h2>
      <div class="px-2">
        <button type="button" class="btn btn-brand w-100" data-pasang-tombol hidden><i class="bi bi-download" aria-hidden="true"></i> Pasang aplikasi di perangkat ini</button>
        <p class="small text-muted mt-2 mb-0" data-pasang-ios hidden>Di iPhone/iPad: ketuk tombol <b>Bagikan</b> di Safari, lalu pilih <b>Tambah ke Layar Utama</b>.</p>
      </div>
    </section>
  </div>

  <form method="post" action="logout" class="mt-1">
    <?= csrf_field() ?>
    <button class="btn-keluar"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Keluar</button>
  </form>
  <p class="text-center small text-muted mt-3 mb-0"><?= e(APP_NAME) ?></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
