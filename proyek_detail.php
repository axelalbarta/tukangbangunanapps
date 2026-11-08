<?php
require __DIR__ . '/includes/init.php';
$u = require_login();
$activeMenu = 'proyek';

$id = input_int('id');
$p = proyek_akses($id, $u);
if (!$p) {
    halaman_404();
}
$pageTitle = $p['nama_proyek'];
$role = $u['role'];
$bisaIsi = in_array($role, ['admin', 'tim'], true);
$tab = input('tab', 'dokumen');

$wd = filter_dokumen_sql($u);
$dokumen = q("SELECT d.*, kd.nama AS kategori, us.nama AS pengunggah, ap.nama AS penyetuju
              FROM dokumen d JOIN kategori_dokumen kd ON kd.id = d.kategori_id
              LEFT JOIN users us ON us.id = d.diupload_oleh LEFT JOIN users ap ON ap.id = d.disetujui_oleh
              WHERE d.proyek_id = ? AND $wd ORDER BY kd.urutan, d.judul, d.revisi DESC", [$id])->fetchAll();
$perKategori = [];
foreach ($dokumen as $d) $perKategori[$d['kategori']][] = $d;

$progres = q('SELECT pr.*, us.nama AS pelapor FROM progres pr LEFT JOIN users us ON us.id = pr.dibuat_oleh
              WHERE pr.proyek_id = ? ORDER BY pr.tanggal DESC, pr.id DESC', [$id])->fetchAll();
$foto = [];
if ($progres) {
    $ids = array_column($progres, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (q("SELECT id, progres_id FROM progres_foto WHERE progres_id IN ($in) ORDER BY id", $ids)->fetchAll() as $f) {
        $foto[$f['progres_id']][] = (int)$f['id'];
    }
}
$tim = q('SELECT pt.peran, us.nama, us.no_hp, us.jabatan FROM proyek_tim pt JOIN users us ON us.id = pt.user_id
          WHERE pt.proyek_id = ? ORDER BY us.nama', [$id])->fetchAll();

$kembali = 'proyek';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><h1 class="h4 mb-0"><?= e($p['nama_proyek']) ?></h1></div>
<?php $adaPeta = $p['latitude'] !== null && $p['longitude'] !== null;
      $koord = $adaPeta ? rtrim(rtrim((string)$p['latitude'], '0'), '.') . ',' . rtrim(rtrim((string)$p['longitude'], '0'), '.') : ''; ?>

<section class="kartu-hitam mb-3" aria-label="Ringkasan proyek">
  <div class="d-flex justify-content-between align-items-center">
    <span class="kode"><?= e($p['kode_proyek']) ?></span>
    <span class="pil pil-putih"><?= e(STATUS_PROYEK[$p['status']][0]) ?></span>
  </div>
  <div>
    <div class="nama"><?= e($p['nama_proyek']) ?></div>
    <div class="sub"><?= e($p['nama_klien']) ?><?= $p['lokasi'] ? ' · ' . e($p['lokasi']) : '' ?></div>
  </div>
  <div class="d-flex flex-column gap-1">
    <div class="d-flex justify-content-between align-items-baseline"><span class="sub">Progres pekerjaan</span><span class="persen"><?= (int)$p['progres_persen'] ?>%</span></div>
    <?= progress_bar((int)$p['progres_persen']) ?>
    <span class="sub"><?= tgl($p['tgl_mulai']) ?> – <?= tgl($p['tgl_target_selesai']) ?> · <?= e(LAYANAN[$p['jenis_layanan']]) ?></span>
  </div>
</section>

<nav class="aksi-bulat-baris mb-3" aria-label="Aksi proyek">
  <?php if ($bisaIsi): ?>
    <a href="dokumen_upload?proyek_id=<?= $id ?>" class="aksi-bulat"><span class="bulat"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i></span>Unggah</a>
    <a href="progres_form?proyek_id=<?= $id ?>" class="aksi-bulat"><span class="bulat"><i class="bi bi-camera" aria-hidden="true"></i></span>Lapor</a>
  <?php endif; ?>
  <?php if ($role === 'ceo'): ?>
    <a href="persetujuan?proyek_id=<?= $id ?>" class="aksi-bulat"><span class="bulat"><i class="bi bi-patch-check" aria-hidden="true"></i></span>Setujui</a>
  <?php endif; ?>
  <?php if (in_array($role, ['ceo', 'admin'], true)): ?>
    <div class="dropdown d-flex justify-content-center">
      <button class="aksi-bulat dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><span class="bulat"><i class="bi bi-file-earmark-zip" aria-hidden="true"></i></span>Unduh</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="unduh_proyek?id=<?= $id ?>"><i class="bi bi-archive me-2"></i>Semua dokumen &amp; foto (ZIP)</a></li>
        <li><a class="dropdown-item" href="unduh_proyek?id=<?= $id ?>&isi=disetujui"><i class="bi bi-patch-check me-2"></i>Hanya yang disetujui (ZIP)</a></li>
      </ul>
    </div>
  <?php endif; ?>
  <?php if ($adaPeta): ?>
    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= e($koord) ?>" target="_blank" rel="noopener" class="aksi-bulat"><span class="bulat"><i class="bi bi-sign-turn-right" aria-hidden="true"></i></span>Arah</a>
  <?php endif; ?>
  <?php if ($role === 'admin'): ?>
    <div class="dropdown d-flex justify-content-center">
      <button class="aksi-bulat dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><span class="bulat"><i class="bi bi-three-dots" aria-hidden="true"></i></span>Lainnya</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="proyek_form?id=<?= $id ?>"><i class="bi bi-pencil me-2"></i>Ubah proyek</a></li>
        <?php if (!$adaPeta): ?><li><a class="dropdown-item" href="proyek_form?id=<?= $id ?>"><i class="bi bi-geo-alt me-2"></i>Tandai lokasi di peta</a></li><?php endif; ?>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="proyek_form?id=<?= $id ?>#hapus"><i class="bi bi-trash me-2"></i>Hapus proyek…</a></li>
      </ul>
    </div>
  <?php endif; ?>
</nav>

<details class="panel mb-3 small">
  <summary class="fw-bold">Info proyek</summary>
  <div class="d-flex flex-column gap-1 mt-2">
    <div><span class="text-muted">Klien:</span> <?= e($p['nama_klien']) ?><?= $p['telepon_klien'] && $role !== 'klien' ? ' · ' . e($p['telepon_klien']) : '' ?></div>
    <div><span class="text-muted">Lokasi:</span> <?= e($p['lokasi'] ?: '–') ?></div>
    <?php if ($role !== 'tim'): ?><div><span class="text-muted">Nilai kontrak:</span> <?= e(rupiah($p['nilai_kontrak'])) ?></div><?php endif; ?>
    <?php if ($p['keterangan']): ?><div><span class="text-muted">Keterangan:</span> <?= nl2br(e($p['keterangan'])) ?></div><?php endif; ?>
  </div>
</details>

<?php if ($adaPeta): ?>
<div class="card mb-3 overflow-hidden" id="lokasi">
  <div id="peta-detail" class="peta-detail" aria-label="Peta lokasi proyek"></div>
  <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
    <span class="small text-muted me-auto"><i class="bi bi-geo-alt-fill text-brand"></i> <?= e(str_replace(',', ', ', $koord)) ?></span>
    <a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= e($koord) ?>"><i class="bi bi-map"></i> Google Maps</a>
  </div>
</div>
<link rel="stylesheet" href="<?= e(vendor('leaflet_css')) ?>">
<script nonce="<?= csp_nonce() ?>" src="<?= e(vendor('leaflet_js')) ?>"></script>
<script nonce="<?= csp_nonce() ?>" src="<?= aset('assets/js/peta.js') ?>"></script>
<script nonce="<?= csp_nonce() ?>">petaTampil('peta-detail', <?= (float)$p['latitude'] ?>, <?= (float)$p['longitude'] ?>, <?= json_encode($p['nama_proyek'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);</script>
<?php endif; ?>

<div class="segmen mb-3" role="tablist" aria-label="Isi proyek">
  <button class="<?= $tab === 'dokumen' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-dokumen" type="button" role="tab">Dokumen <?= count($dokumen) ?></button>
  <button class="<?= $tab === 'progres' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-progres" type="button" role="tab">Progres <?= count($progres) ?></button>
  <button class="<?= $tab === 'tim' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-tim" type="button" role="tab">Tim <?= count($tim) ?></button>
</div>

<div class="tab-content">
  <!-- DOKUMEN -->
  <div class="tab-pane fade <?= $tab === 'dokumen' ? 'show active' : '' ?>" id="tab-dokumen">
    <?php if (!$dokumen): ?>
      <?= empty_state('bi-folder2-open', $role === 'klien' ? 'Belum ada dokumen yang dibagikan.' : 'Belum ada dokumen di proyek ini.') ?>
    <?php endif; ?>
    <?php if (count($perKategori) > 1): ?>
      <div class="chip-baris mb-1" aria-label="Filter kategori">
        <button type="button" class="chip aktif" data-kat="">Semua</button>
        <?php foreach (array_keys($perKategori) as $kat): ?><button type="button" class="chip" data-kat="<?= e($kat) ?>"><?= e($kat) ?></button><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php foreach ($perKategori as $kat => $docs): ?>
      <div class="kelompok-kat" data-kat="<?= e($kat) ?>">
      <h2 class="h6 section-title"><?= e($kat) ?></h2>
      <div class="card mb-3"><ul class="list-group list-group-flush">
        <?php foreach ($docs as $d): $did = (int)$d['id']; ?>
          <li class="list-group-item">
            <div class="d-flex gap-3 align-items-start">
              <i class="bi <?= ikon_file($d['nama_file']) ?> fs-3"></i>
              <div class="flex-grow-1 min-w-0">
                <a href="download?id=<?= $did ?>"<?= atribut_lihat($d['nama_file'], $d['judul']) ?> target="_blank" class="fw-semibold"><?= e($d['judul']) ?><?= $d['revisi'] ? ' Rev.' . (int)$d['revisi'] : '' ?></a>
                <div class="small text-muted">
                  <?= tgl($d['tanggal_dokumen'] ?: $d['dibuat_pada']) ?> · <?= format_bytes((int)$d['ukuran']) ?><?= $role !== 'klien' && $d['pengunggah'] ? ' · oleh ' . e($d['pengunggah']) : '' ?>
                </div>
                <?php if ($role !== 'klien'): ?>
                  <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                    <?= badge(STATUS_DOK, $d['status_persetujuan']) ?>
                    <?php if ($d['sumber'] === 'impor'): ?><span class="pil pil-draft">arsip lama</span><?php endif; ?>
                    <?php if (!file_ada($d['path_file'])): ?><span class="pil pil-tolak" title="Data ada di database, tetapi file di folder uploads sudah tidak ada. Unggah ulang dokumen ini."><i class="bi bi-exclamation-triangle"></i> File hilang</span><?php endif; ?>
                    <?php if ((int)$d['tampil_ke_klien']): ?><span class="pil pil-info"><i class="bi bi-eye"></i> Untuk klien</span><?php endif; ?>
                    <?php if ($d['status_persetujuan'] === 'disetujui' && $d['penyetuju']): ?><span class="small text-muted">oleh <?= e($d['penyetuju']) ?></span><?php endif; ?>
                    <?php if ($d['status_persetujuan'] === 'ditolak' && $d['catatan_persetujuan']): ?><span class="small text-danger">“<?= e($d['catatan_persetujuan']) ?>”</span><?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="d-flex flex-wrap gap-1 justify-content-end">
                <a href="download?id=<?= $did ?>&dl=1" class="btn btn-sm btn-light" title="Unduh"><i class="bi bi-download"></i></a>
                <?php if ($role === 'ceo' && $d['status_persetujuan'] === 'menunggu'): ?>
                  <form method="post" action="dokumen_aksi"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $did ?>"><input type="hidden" name="aksi" value="setujui"><input type="hidden" name="kembali" value="proyek">
                    <button class="btn btn-sm btn-success" title="Setujui"><i class="bi bi-check-lg"></i></button></form>
                  <a href="persetujuan#dok<?= $did ?>" class="btn btn-sm btn-outline-danger" title="Tolak dengan catatan"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
                <?php if ($bisaIsi && in_array($d['status_persetujuan'], ['draft', 'ditolak'], true)): ?>
                  <form method="post" action="dokumen_aksi"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $did ?>"><input type="hidden" name="aksi" value="ajukan"><input type="hidden" name="kembali" value="proyek">
                    <button class="btn btn-sm btn-outline-primary" title="Ajukan ke CEO"><i class="bi bi-send"></i> Ajukan</button></form>
                <?php endif; ?>
                <?php if ($role === 'admin'): ?>
                  <form method="post" action="dokumen_aksi"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $did ?>"><input type="hidden" name="aksi" value="toggle_klien"><input type="hidden" name="kembali" value="proyek">
                    <button class="btn btn-sm btn-light" title="<?= (int)$d['tampil_ke_klien'] ? 'Sembunyikan dari klien' : 'Tampilkan ke klien' ?>"><i class="bi <?= (int)$d['tampil_ke_klien'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i></button></form>
                  <form method="post" action="dokumen_aksi" data-confirm="Hapus dokumen ini secara permanen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $did ?>"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="kembali" value="proyek">
                    <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
                <?php endif; ?>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- PROGRES -->
  <div class="tab-pane fade <?= $tab === 'progres' ? 'show active' : '' ?>" id="tab-progres">
    <?php if (!$progres): ?><?= empty_state('bi-camera', 'Belum ada laporan progres.') ?><?php endif; ?>
    <div class="timeline">
      <?php foreach ($progres as $pr): ?>
        <div class="timeline-item">
          <div class="timeline-dot"></div>
          <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="fw-semibold"><?= tgl($pr['tanggal']) ?> · <?= (int)$pr['persentase'] ?>%</div>
                <div class="small text-muted">oleh <?= e($pr['pelapor'] ?? '–') ?></div>
              </div>
              <?php if ($role === 'admin'): ?>
                <form method="post" action="progres_hapus" data-confirm="Hapus laporan progres ini beserta fotonya?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
              <?php endif; ?>
            </div>
            <?php if ($pr['catatan']): ?><p class="mt-2 mb-1"><?= nl2br(e($pr['catatan'])) ?></p><?php endif; ?>
            <?php if ($pr['kendala']): ?><p class="mb-1 small text-danger"><i class="bi bi-exclamation-triangle"></i> Kendala: <?= nl2br(e($pr['kendala'])) ?></p><?php endif; ?>
            <?php if (!empty($foto[$pr['id']])): ?>
              <div class="foto-grid mt-2">
                <?php foreach ($foto[$pr['id']] as $fid): ?>
                  <a href="foto?id=<?= $fid ?>" data-lihat="gambar" data-judul="Foto progres <?= e(tgl($pr['tanggal'])) ?> · <?= (int)$pr['persentase'] ?>%" target="_blank" class="foto-thumb"><img src="foto?id=<?= $fid ?>" alt="Foto progres" loading="lazy"></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- TIM -->
  <div class="tab-pane fade <?= $tab === 'tim' ? 'show active' : '' ?>" id="tab-tim">
    <?php if (!$tim): ?><?= empty_state('bi-people', 'Belum ada tim yang ditugaskan.') ?><?php else: ?>
      <div class="card"><ul class="list-group list-group-flush">
        <?php foreach ($tim as $t): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div><div class="fw-semibold"><?= e($t['nama']) ?></div><div class="small text-muted"><?= e(PERAN_TIM[$t['peran']] ?? $t['peran']) ?></div></div>
            <?php if ($t['no_hp']): ?><a href="https://wa.me/<?= e(preg_replace('/^0/', '62', preg_replace('/\D/', '', $t['no_hp']))) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp"></i> <?= e($t['no_hp']) ?></a><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
  </div>
</div>

<script nonce="<?= csp_nonce() ?>">
// Filter dokumen per kategori lewat chip
document.querySelectorAll('[data-kat].chip').forEach(function (c) {
  c.addEventListener('click', function () {
    document.querySelectorAll('[data-kat].chip').forEach(function (x) { x.classList.toggle('aktif', x === c); });
    document.querySelectorAll('.kelompok-kat').forEach(function (k) { k.hidden = c.dataset.kat !== '' && k.dataset.kat !== c.dataset.kat; });
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
