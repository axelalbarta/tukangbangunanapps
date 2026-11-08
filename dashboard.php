<?php
require __DIR__ . '/includes/init.php';
$u = require_login();
$pageTitle = 'Beranda';
$activeMenu = 'dashboard';
$beranda = true;
$role = $u['role'];

[$wp, $pp] = filter_proyek_sql($u);
$wd = filter_dokumen_sql($u);

$jam = (int)date('G');
$salam = $jam < 11 ? 'Selamat pagi,' : ($jam < 15 ? 'Selamat siang,' : ($jam < 19 ? 'Selamat sore,' : 'Selamat malam,'));
$menunggu = in_array($role, ['ceo', 'admin'], true) ? jumlah_menunggu_persetujuan() : 0;

$proyekAktif = q("SELECT p.*, k.nama AS nama_klien FROM proyek p JOIN klien k ON k.id = p.klien_id
                  WHERE $wp AND p.status IN ('berjalan','tertunda')
                  ORDER BY p.tgl_target_selesai IS NULL, p.tgl_target_selesai", $pp)->fetchAll();

// ---- Angka ringkasan per role ----
$ringkas = [];
$pita = null;
if (in_array($role, ['ceo', 'admin'], true)) {
    $perStatus = q("SELECT status, COUNT(*) n FROM proyek GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $nilaiBerjalan = (float)q("SELECT COALESCE(SUM(nilai_kontrak),0) FROM proyek WHERE status IN ('berjalan','tertunda')")->fetchColumn();
    $ringkas = [
        [(int)($perStatus['berjalan'] ?? 0), 'Berjalan', 'proyek?status=berjalan'],
        [(int)($perStatus['tertunda'] ?? 0), 'Tertunda', 'proyek?status=tertunda'],
        [(int)($perStatus['selesai'] ?? 0), 'Selesai', 'proyek?status=selesai'],
    ];
    $pita = $menunggu > 0
        ? ['pita-kuning', 'bi-hourglass-split', "$menunggu dokumen menunggu persetujuan CEO", 'persetujuan']
        : null;
} elseif ($role === 'tim') {
    $laporBulan = (int)q("SELECT COUNT(*) FROM progres WHERE dibuat_oleh = ? AND tanggal >= ?", [$u['id'], date('Y-m-01')])->fetchColumn();
    $ditolak = (int)q("SELECT COUNT(*) FROM dokumen d JOIN proyek p ON p.id = d.proyek_id WHERE $wp AND $wd AND d.status_persetujuan = 'ditolak'", $pp)->fetchColumn();
    $ringkas = [
        [count($proyekAktif), 'Proyek aktif', 'proyek'],
        [$laporBulan, 'Laporan bln ini', 'progres_form'],
        [$ditolak, 'Perlu revisi', 'cari?status=ditolak'],
    ];
    $pita = $ditolak > 0 ? ['pita-merah', 'bi-arrow-repeat', "$ditolak dokumen ditolak CEO, perlu direvisi", 'cari?status=ditolak'] : null;
} else {
    $semuaProyek = q("SELECT p.*, k.nama AS nama_klien FROM proyek p JOIN klien k ON k.id = p.klien_id WHERE $wp ORDER BY p.dibuat_pada DESC", $pp)->fetchAll();
    $jmlDok = (int)q("SELECT COUNT(*) FROM dokumen d JOIN proyek p ON p.id = d.proyek_id WHERE $wp AND $wd", $pp)->fetchColumn();
    $jmlFoto = (int)q("SELECT COUNT(*) FROM progres_foto f JOIN progres pr ON pr.id = f.progres_id JOIN proyek p ON p.id = pr.proyek_id WHERE $wp", $pp)->fetchColumn();
    $ringkas = [
        [count($semuaProyek), 'Proyek', 'proyek'],
        [$jmlDok, 'Dokumen', 'proyek'],
        [$jmlFoto, 'Foto progres', 'proyek'],
    ];
}

// ---- Aksi cepat per role: [kunci, href, ikon, label, lencana] ----
$aksi = match ($role) {
    'admin' => [
        ['proyek-baru', 'proyek_form', 'bi-plus-lg', 'Proyek baru', 0],
        ['unggah', 'dokumen_upload', 'bi-cloud-arrow-up', 'Unggah', 0],
        ['lapor', 'progres_form', 'bi-camera', 'Lapor', 0],
        ['setujui', 'persetujuan', 'bi-patch-check', 'Persetujuan', $menunggu],
        ['cari', 'cari', 'bi-search', 'Cari', 0],
        ['klien', 'klien', 'bi-person-vcard', 'Klien', 0],
        ['import', 'import', 'bi-box-arrow-in-down', 'Import', 0],
        ['semua', 'semua_menu', 'bi-grid', 'Semua', 0],
    ],
    'ceo' => [
        ['setujui', 'persetujuan', 'bi-patch-check', 'Setujui', $menunggu],
        ['proyek', 'proyek', 'bi-buildings', 'Proyek', 0],
        ['cari', 'cari', 'bi-search', 'Cari', 0],
        ['log', 'log', 'bi-clock-history', 'Aktivitas', 0],
        ['profil', 'profil', 'bi-person-circle', 'Profil', 0],
        ['tur', 'dashboard?tur=1', 'bi-signpost-split', 'Panduan', 0],
        ['semua', 'semua_menu', 'bi-grid', 'Semua', 0],
    ],
    'tim' => [
        ['lapor', 'progres_form', 'bi-camera', 'Lapor', 0],
        ['unggah', 'dokumen_upload', 'bi-cloud-arrow-up', 'Unggah', 0],
        ['proyek', 'proyek', 'bi-buildings', 'Proyek', 0],
        ['cari', 'cari', 'bi-search', 'Cari', 0],
        ['profil', 'profil', 'bi-person-circle', 'Profil', 0],
        ['tur', 'dashboard?tur=1', 'bi-signpost-split', 'Panduan', 0],
        ['semua', 'semua_menu', 'bi-grid', 'Semua', 0],
    ],
    default => [
        ['proyek', 'proyek', 'bi-buildings', 'Proyek saya', 0],
        ['profil', 'profil', 'bi-person-circle', 'Profil', 0],
        ['tur', 'dashboard?tur=1', 'bi-signpost-split', 'Panduan', 0],
        ['semua', 'semua_menu', 'bi-grid', 'Semua', 0],
    ],
};

// ---- Aktivitas terbaru ----
$aktivitas = [];
if (in_array($role, ['ceo', 'admin'], true)) {
    $gaya = [
        'upload' => ['bi-cloud-arrow-up', 'ikon-kuning', 'mengunggah'], 'setujui' => ['bi-check-lg', 'ikon-hijau', 'menyetujui'],
        'tolak' => ['bi-x-lg', 'ikon-merah', 'menolak'], 'lapor' => ['bi-camera', 'ikon-merah', 'melapor progres'],
        'impor' => ['bi-box-arrow-in-down', 'ikon-abu', 'mengimpor arsip'], 'ajukan' => ['bi-send', 'ikon-kuning', 'mengajukan'],
        'tambah' => ['bi-plus-lg', 'ikon-abu', 'menambah'], 'hapus' => ['bi-trash', 'ikon-merah', 'menghapus'],
        'unduh_zip' => ['bi-file-earmark-zip', 'ikon-abu', 'mengunduh berkas'],
    ];
    $in = implode(',', array_fill(0, count($gaya), '?'));
    foreach (q("SELECT l.*, us.nama FROM log_aktivitas l LEFT JOIN users us ON us.id = l.user_id
                WHERE l.aksi IN ($in) ORDER BY l.waktu DESC, l.id DESC LIMIT 6", array_keys($gaya))->fetchAll() as $l) {
        [$ikon, $warna, $kata] = $gaya[$l['aksi']];
        $aktivitas[] = [
            'ikon' => $ikon, 'warna' => $warna, 'href' => null,
            'judul' => ($l['keterangan'] ?: ucfirst($l['objek'])),
            'sub' => ($l['nama'] ?? 'User terhapus') . ' ' . $kata . ' ' . $l['objek'],
            'waktu' => $l['waktu'],
        ];
    }
} else {
    foreach (q("SELECT d.*, p.nama_proyek, kd.nama AS kategori FROM dokumen d
                JOIN proyek p ON p.id = d.proyek_id JOIN kategori_dokumen kd ON kd.id = d.kategori_id
                WHERE $wp AND $wd ORDER BY d.dibuat_pada DESC LIMIT 6", $pp)->fetchAll() as $d) {
        $aktivitas[] = [
            'ikon' => 'bi-file-earmark-text', 'warna' => 'ikon-merah', 'href' => 'download?id=' . (int)$d['id'], 'lihat' => atribut_lihat($d['nama_file'], $d['judul']),
            'judul' => $d['judul'] . ($d['revisi'] ? ' Rev.' . (int)$d['revisi'] : ''),
            'sub' => $d['kategori'] . ' · ' . $d['nama_proyek'],
            'waktu' => $d['dibuat_pada'], 'status' => $role === 'tim' ? $d['status_persetujuan'] : null,
        ];
    }
}
$waktuSingkat = function (string $w): string {
    return substr($w, 0, 10) === date('Y-m-d') ? date('H.i', strtotime($w)) : tgl($w);
};

require __DIR__ . '/includes/tour.php';
$tourSteps = langkah_tur($u);
$tourPaksa = isset($_GET['tur']);

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-atas">
    <img src="assets/img/logo-dark.png" alt="<?= e(APP_NAME) ?>">
    <div class="hero-tombol">
      <button type="button" class="hero-btn" data-ganti-tema aria-label="Ganti tema terang/gelap"><i class="bi bi-moon-stars ikon-ke-gelap" aria-hidden="true"></i><i class="bi bi-sun ikon-ke-terang" aria-hidden="true"></i></button>
      <a href="dashboard?tur=1" class="hero-btn" aria-label="Panduan aplikasi" data-tour="tur"><i class="bi bi-question-lg" aria-hidden="true"></i></a>
      <?php if (in_array($role, ['ceo', 'admin'], true)): ?>
        <a href="persetujuan" class="hero-btn" aria-label="<?= $menunggu ? "$menunggu dokumen menunggu persetujuan" : 'Persetujuan' ?>">
          <i class="bi bi-bell" aria-hidden="true"></i><?php if ($menunggu): ?><span class="titik-merah"></span><?php endif; ?>
        </a>
      <?php else: ?>
        <a href="profil" class="hero-btn" aria-label="Profil"><i class="bi bi-person" aria-hidden="true"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <div class="hero-sapa">
    <small><?= e($salam) ?></small>
    <b><?= e($u['nama']) ?></b>
  </div>
</section>

<section class="kartu-ringkas" data-tour="statistik" aria-label="Ringkasan">
  <div class="kartu-ringkas-judul">
    <span><?= $role === 'tim' ? 'Tugas Anda' : ($role === 'klien' ? 'Proyek Anda' : 'Ringkasan proyek') ?></span>
    <small><?= tgl(date('Y-m-d')) ?></small>
  </div>
  <div class="angka-grid">
    <?php foreach ($ringkas as [$angka, $label, $href]): ?>
      <a href="<?= e($href) ?>" class="angka"><b><?= (int)$angka ?></b><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
    <?php if (in_array($role, ['ceo', 'admin'], true)): ?>
      <div class="angka angka-lebar"><span>Nilai kontrak berjalan</span><b class="fs-5"><?= e(rupiah($nilaiBerjalan)) ?></b></div>
    <?php endif; ?>
  </div>
  <?php if ($pita): [$kelas, $ikon, $teks, $href] = $pita; ?>
    <a href="<?= e($href) ?>" class="pita <?= $kelas ?>"><i class="bi <?= $ikon ?>" aria-hidden="true"></i><span><?= e($teks) ?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
  <?php endif; ?>
</section>

<div class="isi-bawah-hero mt-3">
  <?php if ($role === 'admin' && !(siapkan_folder_upload('dokumen') && siapkan_folder_upload('foto'))): ?>
    <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle"></i> <?= e(pesan_izin_uploads()) ?></div>
  <?php endif; ?>
  <?php if ($role === 'admin'):
      $hilang = 0;
      foreach (q('SELECT path_file FROM dokumen')->fetchAll(PDO::FETCH_COLUMN) as $pf) if (!file_ada($pf)) $hilang++;
      $fotoHilang = 0;
      foreach (q('SELECT path_file FROM progres_foto')->fetchAll(PDO::FETCH_COLUMN) as $pf) if (!file_ada($pf)) $fotoHilang++;
      if ($hilang || $fotoHilang): ?>
    <div class="alert alert-danger mb-0 small">
      <i class="bi bi-exclamation-triangle"></i> <strong><?= $hilang ?> dokumen dan <?= $fotoHilang ?> foto</strong> tercatat di database, tetapi filenya tidak ada di folder <code>uploads</code>.
      Pulihkan isi <code>uploads</code> dari cadangan, atau unggah ulang dokumen bertanda <span class="pil pil-tolak">File hilang</span>.
    </div>
  <?php endif; endif; ?>

  <div class="panel d-flex align-items-center gap-3" data-notif-ajak hidden>
    <span class="baris-ikon ikon-merah"><i class="bi bi-bell" aria-hidden="true"></i></span>
    <span class="flex-grow-1 small"><b class="d-block">Aktifkan notifikasi</b><?= $role === 'ceo' ? 'Langsung tahu saat ada dokumen menunggu persetujuan.' : ($role === 'klien' ? 'Langsung tahu saat ada progres atau dokumen baru.' : 'Langsung tahu saat dokumen disetujui atau ditolak.') ?></span>
    <button type="button" class="btn btn-brand btn-sm" data-notif-aktifkan>Aktifkan</button>
    <button type="button" class="btn btn-light btn-sm" data-notif-nanti aria-label="Nanti saja">Nanti</button>
  </div>
  <div class="dasbor-kolom">
  <div class="kol-utama">
  <nav class="panel grid-ikon" aria-label="Aksi cepat" data-tour="aksi">
    <?php foreach ($aksi as [$key, $href, $icon, $label, $lencana]): ?>
      <a href="<?= e($href) ?>" class="ikon-aksi" data-tour="aksi-<?= $key ?>">
        <span class="ikon-kotak"><i class="bi <?= $icon ?>" aria-hidden="true"></i><?php if ($lencana): ?><em class="lencana"><?= (int)$lencana ?></em><?php endif; ?></span>
        <span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ($role === 'klien'): ?>
    <section data-tour="daftar-proyek">
      <div class="panel-judul"><h2>Proyek Anda</h2></div>
      <div class="daftar-kartu">
        <?php foreach ($semuaProyek as $p): ?><?= proyek_card($p, $u) ?><?php endforeach; ?>
        <?php if (!$semuaProyek): ?><div class="panel"><?= empty_state('bi-buildings', 'Belum ada proyek yang terhubung ke akun Anda.') ?></div><?php endif; ?>
      </div>
    </section>
    <?php $fotoTerbaru = q("SELECT f.id, pr.tanggal FROM progres_foto f JOIN progres pr ON pr.id = f.progres_id JOIN proyek p ON p.id = pr.proyek_id
                            WHERE $wp ORDER BY pr.tanggal DESC, f.id DESC LIMIT 6", $pp)->fetchAll(); ?>
    <?php if ($fotoTerbaru): ?>
      <section data-tour="foto">
        <div class="panel-judul"><h2>Foto progres terbaru</h2></div>
        <div class="foto-grid">
          <?php foreach ($fotoTerbaru as $f): ?>
            <a href="foto?id=<?= (int)$f['id'] ?>" data-lihat="gambar" data-judul="Foto progres <?= e(tgl($f['tanggal'])) ?>" target="_blank" class="foto-thumb"><img src="foto?id=<?= (int)$f['id'] ?>" alt="Foto progres <?= tgl($f['tanggal']) ?>" loading="lazy"><span><?= tgl($f['tanggal']) ?></span></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  <?php else: ?>
    <section data-tour="daftar-proyek">
      <div class="panel-judul"><h2><?= $role === 'tim' ? 'Proyek yang Anda tangani' : 'Proyek aktif' ?></h2><a href="proyek">Lihat semua</a></div>
      <div class="daftar-kartu">
        <?php foreach (array_slice($proyekAktif, 0, 3) as $p): ?><?= proyek_card($p, $u) ?><?php endforeach; ?>
        <?php if (!$proyekAktif): ?><div class="panel"><?= empty_state('bi-buildings', $role === 'tim' ? 'Belum ada proyek yang ditugaskan ke Anda.' : 'Belum ada proyek berjalan.') ?></div><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
  </div>

  <div class="kol-samping">
  <section data-tour="dokumen">
    <div class="panel-judul">
      <h2><?= in_array($role, ['ceo', 'admin'], true) ? 'Aktivitas terbaru' : ($role === 'klien' ? 'Dokumen untuk Anda' : 'Dokumen terbaru') ?></h2>
      <?php if (in_array($role, ['ceo', 'admin'], true)): ?><a href="log">Lihat semua</a><?php elseif ($role === 'tim'): ?><a href="cari">Cari</a><?php endif; ?>
    </div>
    <div class="daftar">
      <?php foreach ($aktivitas as $a): $tag = $a['href'] ? 'a' : 'div'; ?>
        <<?= $tag ?> class="baris"<?= $a['href'] ? ' href="' . e($a['href']) . '"' . ($a['lihat'] ?? '') . ' target="_blank"' : '' ?>>
          <span class="baris-ikon <?= $a['warna'] ?>"><i class="bi <?= $a['ikon'] ?>" aria-hidden="true"></i></span>
          <span class="baris-isi"><span class="baris-judul"><?= e($a['judul']) ?></span><span class="baris-sub"><?= e($a['sub']) ?></span></span>
          <span class="baris-kanan"><?php if (!empty($a['status'])): ?><?= badge(STATUS_DOK, $a['status']) ?><br><?php endif; ?><?= e($waktuSingkat($a['waktu'])) ?></span>
        </<?= $tag ?>>
      <?php endforeach; ?>
      <?php if (!$aktivitas): ?><?= empty_state('bi-inbox', 'Belum ada aktivitas.') ?><?php endif; ?>
    </div>
  </section>
  </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
