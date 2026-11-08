<?php
$u = current_user();
$tab = menu_tab($u);
// Halaman yang tidak punya tab sendiri menyorot "Menu"
$kunciTab = array_column($tab, 0);
$aktif = in_array($activeMenu, $kunciTab, true) ? $activeMenu : ($activeMenu === 'dashboard' ? 'dashboard' : 'semua');
?>
  </main>

  <nav class="tabbar <?= count($tab) === 4 ? 'tab-4' : '' ?>" aria-label="Navigasi utama">
    <?php foreach ($tab as [$key, $href, $icon, $label, $menonjol]): ?>
      <?php if ($menonjol): ?>
        <a href="<?= e($href) ?>" class="tab tab-fab <?= $aktif === $key ? 'aktif' : '' ?>" data-tour="tab-tengah">
          <span class="fab-bulat"><i class="bi <?= $icon ?>" aria-hidden="true"></i>
            <?php if ($key === 'persetujuan' && $menunggu > 0): ?><em><?= $menunggu > 99 ? '99+' : $menunggu ?></em><?php endif; ?>
          </span>
          <span><?= e($label) ?></span>
        </a>
      <?php else: ?>
        <a href="<?= e($href) ?>" class="tab <?= $aktif === $key ? 'aktif' : '' ?>" data-tour="tab-<?= $key ?>" <?= $aktif === $key ? 'aria-current="page"' : '' ?>>
          <i class="bi <?= $icon ?>" aria-hidden="true"></i>
          <span><?= e($label) ?></span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
</div>

<script nonce="<?= csp_nonce() ?>" src="<?= e(vendor('bs_js')) ?>"></script>
<script nonce="<?= csp_nonce() ?>" src="<?= aset('assets/js/app.js') ?>"></script>
<?php if (!empty($tourSteps)): ?>
<script nonce="<?= csp_nonce() ?>">window.TUR = <?= json_encode(['kunci' => 'tur_v2_' . $u['id'], 'paksa' => !empty($tourPaksa), 'langkah' => $tourSteps], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script nonce="<?= csp_nonce() ?>" src="<?= aset('assets/js/tour.js') ?>"></script>
<?php endif; ?>
</body>
</html>
