<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/impor.php';
$u = require_role('admin');
$pageTitle = 'Import Arsip';
$activeMenu = 'import';

$dirImpor = UPLOAD_DIR . '/import';
siapkan_folder_upload('import');
$zipTersedia = class_exists('ZipArchive');
$errors = [];
$tahap = 'awal';
$scan = null;
$hasil = null;
$kategoriList = q('SELECT * FROM kategori_dokumen ORDER BY urutan')->fetchAll();
$namaKategori = array_column($kategoriList, 'nama', 'id');

function buka_zip(string $path): ?ZipArchive
{
    $zip = new ZipArchive();
    return $zip->open($path) === true ? $zip : null;
}

if (is_post() && $zipTersedia) {
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'File ZIP terlalu besar untuk diunggah lewat browser (batas server ' . ini_get('post_max_size') . '). Gunakan cara kedua: unggah ZIP ke folder uploads/import lewat File Manager.';
    } else {
        verify_csrf();
        $aksi = input('aksi');

        if ($aksi === 'pindai') {
            $path = null;
            $hapusSetelah = false;
            $f = $_FILES['zip'] ?? null;
            if ($f && $f['error'] === UPLOAD_ERR_OK) {
                if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'zip') {
                    $errors[] = 'File harus berformat .zip';
                } else {
                    $path = $dirImpor . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
                    if (!siapkan_folder_upload('import') || !@move_uploaded_file($f['tmp_name'], $path)) {
                        $errors[] = pesan_izin_uploads();
                        $path = null;
                    }
                    $hapusSetelah = true;
                    $namaAsli = $f['name'];
                }
            } elseif ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
                $errors[] = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                    ? 'ZIP melebihi batas upload server (' . ini_get('upload_max_filesize') . '). Gunakan cara kedua lewat File Manager.'
                    : 'Upload gagal (kode ' . (int)$f['error'] . ').';
            } elseif (($pilih = basename(input('file_ada'))) !== '' && is_file($dirImpor . '/' . $pilih) && str_ends_with(strtolower($pilih), '.zip')) {
                $path = $dirImpor . '/' . $pilih;
                $namaAsli = $pilih;
            } else {
                $errors[] = 'Pilih file ZIP terlebih dahulu.';
            }

            if ($path && !$errors) {
                $zip = buka_zip($path);
                if (!$zip) {
                    $errors[] = 'File ZIP rusak atau tidak bisa dibuka.';
                    if ($hapusSetelah) @unlink($path);
                } else {
                    $scan = impor_pindai($zip);
                    $zip->close();
                    if (!$scan['proyek']) {
                        $errors[] = 'Tidak ditemukan folder proyek berisi file yang bisa diimpor. Periksa susunan folder di ZIP.';
                        if ($hapusSetelah) @unlink($path);
                    } else {
                        $_SESSION['impor'] = ['path' => $path, 'hapus' => $hapusSetelah, 'nama' => $namaAsli];
                        $tahap = 'pratinjau';
                    }
                }
            }
        } elseif ($aksi === 'proses' && !empty($_SESSION['impor'])) {
            $sesi = $_SESSION['impor'];
            $zip = is_file($sesi['path']) ? buka_zip($sesi['path']) : null;
            if (!$zip) {
                $errors[] = 'File ZIP sudah tidak ada. Silakan ulangi.';
                unset($_SESSION['impor']);
            } else {
                $opsi = [
                    'status'  => isset(STATUS_PROYEK[input('status')]) ? input('status') : 'selesai',
                    'layanan' => isset(LAYANAN[input('layanan')]) ? input('layanan') : 'pelaksanaan',
                    'kategori_klien' => array_map('intval', (array)($_POST['kategori_klien'] ?? [])),
                    'user_id' => (int)$u['id'],
                ];
                $scan = impor_pindai($zip);
                try {
                    $hasil = impor_proses($zip, $scan, $opsi);
                } catch (Throwable $ex) {
                    $errors[] = 'Impor berhenti: ' . ($ex instanceof RuntimeException ? $ex->getMessage() : 'terjadi kesalahan.' . (APP_DEBUG ? ' ' . $ex->getMessage() : '')) . ' Data yang sudah masuk tetap tersimpan; ulangi impor untuk melanjutkan (file yang sama otomatis dilewati).';
                }
                $zip->close();
                if ($hasil) {
                    if ($sesi['hapus']) @unlink($sesi['path']);
                    unset($_SESSION['impor']);
                    log_aktivitas('impor', 'dokumen', null, sprintf('%s: %d proyek, %d dokumen, %d foto',
                        $sesi['nama'], $hasil['proyek_baru'] + $hasil['proyek_lama'], $hasil['dokumen'], $hasil['foto']));
                    if ($hasil['dokumen'] > 0) notif_kirim(tanpa_saya(notif_ceo()), 'Arsip lama menunggu persetujuan', $hasil['dokumen'] . ' dokumen hasil import siap diperiksa.', 'persetujuan?sumber=impor', 'menunggu');
                    $tahap = 'selesai';
                }
            }
        } elseif ($aksi === 'batal') {
            if (!empty($_SESSION['impor']['hapus'])) @unlink($_SESSION['impor']['path']);
            unset($_SESSION['impor']);
            redirect('import');
        }
    }
}

$zipDiFolder = $zipTersedia ? array_map('basename', glob($dirImpor . '/*.zip') ?: []) : [];

$kembali = 'semua_menu';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Import Arsip</h1>
    <div class="text-muted small">Masukkan arsip lama sekaligus dari satu file ZIP.</div>
  </div>
  <?php if ($tahap !== 'awal'): ?><a href="import" class="btn btn-light"><i class="bi bi-arrow-left"></i> Mulai lagi</a><?php endif; ?>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<?php if (!$zipTersedia): ?>
  <div class="alert alert-warning">
    <strong>Ekstensi ZIP di PHP belum aktif.</strong><br>
    XAMPP: buka Apache → Config → <code>php.ini</code>, cari baris <code>;extension=zip</code>, hapus tanda <code>;</code> di depannya, simpan, lalu Stop dan Start Apache.
    Di Hostinger ekstensi ini biasanya sudah aktif.
  </div>
<?php endif; ?>

<?php if ($tahap === 'awal' && $zipTersedia): ?>
  <div class="row g-3">
    <div class="col-lg-7">
      <form method="post" enctype="multipart/form-data" class="card mb-3"><div class="card-body">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="pindai">
        <h2 class="h6">Cara 1 — Unggah ZIP</h2>
        <input class="form-control" type="file" name="zip" accept=".zip">
        <div class="form-text">Batas server saat ini: <?= e(ini_get('upload_max_filesize')) ?>. Untuk arsip yang lebih besar, pakai cara 2 atau pecah menjadi beberapa ZIP.</div>
        <button class="btn btn-brand mt-3" data-loading="Membaca ZIP…"><i class="bi bi-search"></i> Periksa isi ZIP</button>
      </div></form>

      <form method="post" class="card"><div class="card-body">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="pindai">
        <h2 class="h6">Cara 2 — ZIP besar lewat File Manager</h2>
        <p class="small text-muted mb-2">Unggah ZIP ke folder <code>uploads/import</code> (File Manager hPanel / FTP, atau salin langsung ke folder XAMPP), lalu pilih di sini.</p>
        <?php if ($zipDiFolder): ?>
          <select class="form-select" name="file_ada">
            <?php foreach ($zipDiFolder as $z): ?><option value="<?= e($z) ?>"><?= e($z) ?> (<?= format_bytes((int)filesize($dirImpor . '/' . $z)) ?>)</option><?php endforeach; ?>
          </select>
          <button class="btn btn-outline-dark mt-3"><i class="bi bi-search"></i> Periksa isi ZIP</button>
        <?php else: ?>
          <div class="small text-muted fst-italic">Belum ada file ZIP di folder tersebut.</div>
        <?php endif; ?>
      </div></form>
    </div>

    <div class="col-lg-5">
      <div class="card"><div class="card-body small">
        <h2 class="h6">Susunan folder di dalam ZIP</h2>
<pre class="bg-light p-2 rounded mb-2">Arsip Lama/
├── Rumah Bpk Andi - Andi/
│   ├── Gambar/   Denah Rev2.pdf
│   ├── RAB/      RAB Final.xlsx
│   ├── Kontrak/  SPK.pdf
│   ├── Invoice/  Termin 1.pdf
│   ├── Laporan/  Minggu 1.pdf
│   └── Foto/     IMG_001.jpg
└── Ruko Trikora - Ibu Sari/
    └── ...</pre>
        <ul class="mb-0 ps-3">
          <li>Nama folder proyek: <strong>Nama Proyek - Nama Klien</strong> (dipisah spasi-strip-spasi).</li>
          <li>Klien &amp; proyek dibuat otomatis. Jika nama sama sudah ada, dokumen ditambahkan ke proyek tersebut.</li>
          <li>Folder kategori dikenali dari namanya: Gambar, RAB, Kontrak, Invoice, Laporan, Foto. Lainnya masuk "Lainnya".</li>
          <li>Isi folder <strong>Foto</strong> menjadi foto progres, dikelompokkan per tanggal.</li>
          <li>"Rev2" / "Rev.2" di nama file dibaca sebagai nomor revisi.</li>
          <li>Tanggal dokumen diambil dari tanggal file.</li>
          <li>File yang sama tidak akan masuk dua kali.</li>
          <li>Semua dokumen masuk antrian <strong>persetujuan CEO</strong>. CEO bisa menyetujui sekaligus.</li>
        </ul>
      </div></div>
    </div>
  </div>
<?php endif; ?>

<?php if ($tahap === 'pratinjau'):
    $jmlDok = array_sum(array_map(fn($p) => count($p['dokumen']), $scan['proyek']));
    $jmlFoto = array_sum(array_map(fn($p) => count($p['foto']), $scan['proyek']));
?>
  <div class="alert alert-info">
    <strong><?= e($_SESSION['impor']['nama']) ?></strong>: ditemukan <?= count($scan['proyek']) ?> proyek, <?= $jmlDok ?> dokumen, dan <?= $jmlFoto ?> foto. Periksa daftar di bawah sebelum memproses.
  </div>

  <div class="card mb-3"><div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Proyek</th><th>Klien</th><th>Keterangan</th><th>Dokumen</th><th class="text-end">Foto</th></tr></thead>
      <tbody>
      <?php foreach ($scan['proyek'] as $p):
          $kid = impor_cari_klien($p['klien'] !== '' ? $p['klien'] : 'Klien belum diisi');
          $ada = impor_cari_proyek($p['nama'], $kid);
          $perKat = [];
          foreach ($p['dokumen'] as $d) $perKat[$d['kategori_id']] = ($perKat[$d['kategori_id']] ?? 0) + 1;
          ksort($perKat);
      ?>
        <tr>
          <td class="fw-semibold"><?= e($p['nama']) ?></td>
          <td><?= $p['klien'] !== '' ? e($p['klien']) : '<span class="text-danger small">nama klien tidak ada di folder</span>' ?></td>
          <td class="small"><?= $ada ? '<span class="badge bg-secondary">proyek sudah ada</span> ditambahkan ke ' . e($ada['kode_proyek']) : '<span class="badge bg-success">proyek baru</span>' . ($kid ? '' : ' + klien baru') ?></td>
          <td class="small"><?php foreach ($perKat as $k => $n): ?><span class="badge bg-light text-dark border me-1"><?= e($namaKategori[$k] ?? '?') ?> <?= $n ?></span><?php endforeach; ?></td>
          <td class="text-end"><?= count($p['foto']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div>

  <?php if ($scan['lewati']): ?>
    <details class="mb-3"><summary class="small text-muted"><?= count($scan['lewati']) ?> file akan dilewati</summary>
      <ul class="small text-muted mt-2"><?php foreach (array_slice($scan['lewati'], 0, 100) as [$path, $why]): ?><li><?= e($path) ?> — <?= e($why) ?></li><?php endforeach; ?></ul>
    </details>
  <?php endif; ?>

  <form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="proses">
    <h2 class="h6">Pengaturan impor</h2>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Status proyek baru</label>
        <select class="form-select" name="status">
          <?php foreach (STATUS_PROYEK as $k => [$label]): ?><option value="<?= $k ?>" <?= $k === 'selesai' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <div class="form-text">Arsip lama biasanya proyek yang sudah selesai.</div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Jenis layanan proyek baru</label>
        <select class="form-select" name="layanan">
          <?php foreach (LAYANAN as $k => $label): ?><option value="<?= $k ?>" <?= $k === 'pelaksanaan' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <div class="form-text">Bisa diubah per proyek setelah impor.</div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Kategori yang ditampilkan ke klien</label>
        <?php foreach ($kategoriList as $k): ?>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="kategori_klien[]" value="<?= (int)$k['id'] ?>" id="kk<?= (int)$k['id'] ?>" <?= in_array((int)$k['id'], [1, 5], true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="kk<?= (int)$k['id'] ?>"><?= e($k['nama']) ?></label></div>
        <?php endforeach; ?>
        <div class="form-text">Tetap baru terlihat setelah disetujui CEO.</div>
      </div>
    </div>
    <div class="d-flex gap-2 mt-4 flex-wrap">
      <button class="btn btn-brand" data-loading="Mengimpor… jangan tutup halaman"><i class="bi bi-box-arrow-in-down"></i> Proses impor</button>
    </div>
  </div></form>
  <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><button class="btn btn-link text-muted p-0">Batalkan</button></form>
<?php endif; ?>

<?php if ($tahap === 'selesai'): ?>
  <div class="card mb-3"><div class="card-body">
    <h2 class="h5 text-success"><i class="bi bi-check-circle"></i> Impor selesai</h2>
    <div class="row g-3 mt-1">
      <div class="col-6 col-md-3"><div class="stat-label small text-muted">Dokumen masuk</div><div class="fs-3 fw-bold"><?= $hasil['dokumen'] ?></div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">Foto progres</div><div class="fs-3 fw-bold"><?= $hasil['foto'] ?></div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">Proyek baru / ditambah</div><div class="fs-3 fw-bold"><?= $hasil['proyek_baru'] ?> / <?= $hasil['proyek_lama'] ?></div></div>
      <div class="col-6 col-md-3"><div class="small text-muted">Klien baru</div><div class="fs-3 fw-bold"><?= $hasil['klien_baru'] ?></div></div>
    </div>
    <?php if ($hasil['duplikat']): ?><p class="small text-muted mt-2 mb-0"><?= $hasil['duplikat'] ?> file dilewati karena sudah pernah diimpor.</p><?php endif; ?>
    <?php if ($hasil['gagal']): ?>
      <details class="mt-2"><summary class="small text-danger"><?= count($hasil['gagal']) ?> file gagal</summary>
        <ul class="small mt-2"><?php foreach ($hasil['gagal'] as [$path, $why]): ?><li><?= e($path) ?> — <?= e($why) ?></li><?php endforeach; ?></ul></details>
    <?php endif; ?>
  </div></div>
  <div class="alert alert-warning">
    Dokumen hasil impor sekarang <strong>menunggu persetujuan CEO</strong>. CEO bisa menyetujui semuanya sekaligus di menu Persetujuan.
    Lengkapi juga data klien (telepon, akun login) dan nilai kontrak proyek yang baru dibuat.
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="persetujuan" class="btn btn-brand">Lihat antrian persetujuan</a>
    <a href="proyek" class="btn btn-outline-dark">Lihat proyek</a>
    <a href="klien" class="btn btn-light">Lengkapi data klien</a>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
