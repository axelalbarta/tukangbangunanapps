<?php
require __DIR__ . '/includes/init.php';
$u = require_role('ceo', 'admin');
$pageTitle = 'Persetujuan Dokumen';
$activeMenu = 'persetujuan';

// Setujui banyak dokumen sekaligus (CEO)
if (is_post() && $u['role'] === 'ceo' && input('aksi') === 'setujui_banyak') {
    verify_csrf();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $akanDisetujui = q("SELECT d.id, d.proyek_id, d.diupload_oleh, d.tampil_ke_klien, p.nama_proyek FROM dokumen d JOIN proyek p ON p.id = d.proyek_id
                            WHERE d.status_persetujuan = 'menunggu' AND d.id IN ($in)", $ids)->fetchAll();
        $st = q("UPDATE dokumen SET status_persetujuan='disetujui', disetujui_oleh=?, disetujui_pada=NOW(), catatan_persetujuan=NULL
                 WHERE status_persetujuan='menunggu' AND id IN ($in)", array_merge([$u['id']], $ids));
        $n = $st->rowCount();
        log_aktivitas('setujui', 'dokumen', null, "$n dokumen disetujui sekaligus");
        // Satu notifikasi ringkas per penerima (bukan satu per dokumen)
        $perPengunggah = []; $perProyekKlien = [];
        foreach ($akanDisetujui as $a) {
            if ($a['diupload_oleh']) $perPengunggah[(int)$a['diupload_oleh']] = ($perPengunggah[(int)$a['diupload_oleh']] ?? 0) + 1;
            if ((int)$a['tampil_ke_klien']) $perProyekKlien[(int)$a['proyek_id']] = [$a['nama_proyek'], ($perProyekKlien[(int)$a['proyek_id']][1] ?? 0) + 1];
        }
        foreach ($perPengunggah as $uid => $jml) notif_kirim(tanpa_saya([$uid]), 'Dokumen disetujui CEO', "$jml dokumen Anda sudah disetujui.", 'dashboard', 'setuju-banyak');
        foreach ($perProyekKlien as $pid => [$nmP, $jml]) notif_kirim(notif_klien_proyek($pid), 'Dokumen baru untuk Anda', "$jml dokumen baru · $nmP", 'proyek_detail?id=' . $pid, 'dok-proyek-' . $pid);
        flash('success', "$n dokumen disetujui.");
    } else {
        flash('warning', 'Belum ada dokumen yang dipilih.');
    }
    redirect('persetujuan' . (input('kembali_q') !== '' ? '?' . input('kembali_q') : ''));
}

$fProyek = input_int('proyek_id');
$fSumber = in_array(input('sumber'), ['upload', 'impor'], true) ? input('sumber') : '';
$where = "d.status_persetujuan = 'menunggu'";
$params = [];
if ($fProyek) { $where .= ' AND d.proyek_id = ?'; $params[] = $fProyek; }
if ($fSumber) { $where .= ' AND d.sumber = ?'; $params[] = $fSumber; }

$totalMenunggu = jumlah_menunggu_persetujuan();
$proyekMenunggu = q("SELECT p.id, p.nama_proyek, COUNT(*) n FROM dokumen d JOIN proyek p ON p.id = d.proyek_id
                     WHERE d.status_persetujuan = 'menunggu' GROUP BY p.id, p.nama_proyek ORDER BY p.nama_proyek")->fetchAll();
$list = q("SELECT d.*, kd.nama AS kategori, p.nama_proyek, p.kode_proyek, us.nama AS pengunggah
           FROM dokumen d JOIN kategori_dokumen kd ON kd.id = d.kategori_id JOIN proyek p ON p.id = d.proyek_id
           LEFT JOIN users us ON us.id = d.diupload_oleh
           WHERE $where ORDER BY p.nama_proyek, kd.urutan, d.dibuat_pada LIMIT 500", $params)->fetchAll();
$kembaliQ = http_build_query(array_filter(['proyek_id' => $fProyek ?: null, 'sumber' => $fSumber ?: null]));

$riwayat = q("SELECT d.id, d.judul, d.revisi, d.status_persetujuan, d.disetujui_pada, d.catatan_persetujuan, p.nama_proyek
              FROM dokumen d JOIN proyek p ON p.id = d.proyek_id
              WHERE d.status_persetujuan IN ('disetujui','ditolak') AND d.disetujui_pada IS NOT NULL
              ORDER BY d.disetujui_pada DESC LIMIT 15")->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Persetujuan Dokumen</h1>
  </div>
</div>

<?php if ($u['role'] === 'admin'): ?>
  <div class="alert alert-info small">Anda masuk sebagai admin. Hanya CEO yang bisa menyetujui atau menolak dokumen.</div>
<?php endif; ?>

<p class="small text-muted mb-2"><?= $totalMenunggu ?> dokumen menunggu persetujuan · semua dokumen wajib disetujui CEO sebelum terlihat klien.</p>
<?php if ($totalMenunggu):
    $urlF = fn(array $ubah) => 'persetujuan?' . http_build_query(array_filter(array_merge(['proyek_id' => $fProyek ?: null, 'sumber' => $fSumber ?: null], $ubah))); ?>
  <div class="chip-baris mb-2" aria-label="Filter sumber">
    <a href="<?= e($urlF(['sumber' => null])) ?>" class="chip <?= $fSumber === '' ? 'aktif' : '' ?>">Semua</a>
    <a href="<?= e($urlF(['sumber' => 'upload'])) ?>" class="chip <?= $fSumber === 'upload' ? 'aktif' : '' ?>">Upload baru</a>
    <a href="<?= e($urlF(['sumber' => 'impor'])) ?>" class="chip <?= $fSumber === 'impor' ? 'aktif' : '' ?>">Arsip lama</a>
  </div>
  <?php if (count($proyekMenunggu) > 1 || $fProyek): ?>
  <form method="get" class="mb-3">
    <?php if ($fSumber): ?><input type="hidden" name="sumber" value="<?= e($fSumber) ?>"><?php endif; ?>
    <select class="form-select" name="proyek_id" data-kirim-otomatis aria-label="Filter proyek">
      <option value="">Semua proyek</option>
      <?php foreach ($proyekMenunggu as $pm): ?><option value="<?= (int)$pm['id'] ?>" <?= $fProyek === (int)$pm['id'] ? 'selected' : '' ?>><?= e($pm['nama_proyek']) ?> (<?= (int)$pm['n'] ?>)</option><?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
<?php endif; ?>
<?php if ($u['role'] === 'ceo' && $list): ?>
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="form-check mb-0 d-flex align-items-center gap-2" style="min-height:44px">
      <input class="form-check-input mt-0" type="checkbox" id="pilih-semua" style="width:20px;height:20px">
      <label class="form-check-label fw-bold" for="pilih-semua">Pilih semua (<?= count($list) ?>)</label>
    </div>
  </div>
  <form method="post" id="bulk" class="bilah-aksi">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="setujui_banyak"><input type="hidden" name="kembali_q" value="<?= e($kembaliQ) ?>">
    <div class="flex-grow-1 lh-sm"><span class="small text-muted">Dipilih</span><br><b><span id="jml-pilih">0</span> dokumen</b></div>
    <button class="btn btn-brand" id="btn-bulk" disabled style="height:48px;padding:0 20px"><i class="bi bi-check2-all" aria-hidden="true"></i> Setujui <span id="jml-pilih-2">0</span> dokumen</button>
  </form>
<?php endif; ?>
<?php if (!$list): ?>
  <div class="card mb-4"><?= empty_state('bi-patch-check', 'Tidak ada dokumen yang menunggu persetujuan.') ?></div>
<?php endif; ?>
<?php foreach ($list as $d): $did = (int)$d['id']; ?>
  <div class="card mb-3" id="dok<?= $did ?>"><div class="card-body">
    <div class="d-flex gap-3 align-items-start">
      <?php if ($u['role'] === 'ceo'): ?><input class="form-check-input mt-2 pilih-dok" type="checkbox" name="ids[]" value="<?= $did ?>" form="bulk" aria-label="Pilih dokumen"><?php endif; ?>
      <i class="bi <?= ikon_file($d['nama_file']) ?> fs-2"></i>
      <div class="flex-grow-1 min-w-0">
        <a href="download?id=<?= $did ?>"<?= atribut_lihat($d['nama_file'], $d['judul']) ?> target="_blank" class="fw-semibold"><?= e($d['judul']) ?><?= $d['revisi'] ? ' Rev.' . (int)$d['revisi'] : '' ?></a>
        <div class="small text-muted"><?= e($d['kategori']) ?><?= $d['sumber'] === 'impor' ? ' · <span class="badge bg-light text-dark border">arsip lama</span>' : '' ?> · <a href="proyek_detail?id=<?= (int)$d['proyek_id'] ?>" class="text-muted"><?= e($d['kode_proyek'] . ' ' . $d['nama_proyek']) ?></a></div>
        <div class="small text-muted">Diunggah <?= e($d['pengunggah'] ?? '–') ?> · <?= tgl_waktu($d['dibuat_pada']) ?><?= $d['tanggal_dokumen'] ? ' · tgl dokumen ' . tgl($d['tanggal_dokumen']) : '' ?> · <?= format_bytes((int)$d['ukuran']) ?>
          <?= (int)$d['tampil_ke_klien'] ? ' · <span class="text-info-emphasis"><i class="bi bi-eye"></i> akan terlihat klien</span>' : '' ?></div>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
      <a href="download?id=<?= $did ?>"<?= atribut_lihat($d['nama_file'], $d['judul']) ?> target="_blank" class="btn btn-sm btn-light"><i class="bi bi-eye"></i> Buka</a>
      <?php if ($u['role'] === 'ceo'): ?>
        <form method="post" action="dokumen_aksi"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $did ?>"><input type="hidden" name="aksi" value="setujui"><input type="hidden" name="kembali" value="persetujuan">
          <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Setujui</button></form>
        <form method="post" action="dokumen_aksi" class="d-flex gap-2 flex-grow-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $did ?>"><input type="hidden" name="aksi" value="tolak"><input type="hidden" name="kembali" value="persetujuan">
          <input class="form-control form-control-sm" name="catatan" maxlength="255" placeholder="Alasan penolakan (opsional)">
          <button class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-x-lg"></i> Tolak</button></form>
      <?php endif; ?>
    </div>
  </div></div>
<?php endforeach; ?>

<?php if ($riwayat): ?>
  <h2 class="h6 section-title mt-4">Keputusan terakhir</h2>
  <div class="card"><ul class="list-group list-group-flush">
    <?php foreach ($riwayat as $r): ?>
      <li class="list-group-item d-flex justify-content-between gap-2">
        <div class="min-w-0">
          <a href="download?id=<?= (int)$r['id'] ?>"<?= atribut_lihat($r['nama_file'] ?? '', $r['judul']) ?> target="_blank"><?= e($r['judul']) ?><?= $r['revisi'] ? ' Rev.' . (int)$r['revisi'] : '' ?></a>
          <div class="small text-muted"><?= e($r['nama_proyek']) ?> · <?= tgl_waktu($r['disetujui_pada']) ?><?= $r['catatan_persetujuan'] ? ' · “' . e($r['catatan_persetujuan']) . '”' : '' ?></div>
        </div>
        <div><?= badge(STATUS_DOK, $r['status_persetujuan']) ?></div>
      </li>
    <?php endforeach; ?>
  </ul></div>
<?php endif; ?>

<?php if (count($list) === 500): ?><p class="small text-muted">Menampilkan 500 dokumen pertama. Setujui lalu muat ulang untuk melihat sisanya.</p><?php endif; ?>

<script nonce="<?= csp_nonce() ?>">
(function () {
  const semua = document.getElementById('pilih-semua');
  if (!semua) return;
  const boxes = () => document.querySelectorAll('.pilih-dok');
  const hitung = () => {
    const n = [...boxes()].filter(b => b.checked).length;
    document.getElementById('jml-pilih').textContent = n;
    document.getElementById('jml-pilih-2').textContent = n;
    document.getElementById('btn-bulk').disabled = n === 0;
    semua.checked = n > 0 && n === boxes().length;
  };
  semua.addEventListener('change', () => { boxes().forEach(b => b.checked = semua.checked); hitung(); });
  boxes().forEach(b => b.addEventListener('change', hitung));
  document.getElementById('bulk').addEventListener('submit', e => {
    const n = document.getElementById('jml-pilih').textContent;
    if (!confirm('Setujui ' + n + ' dokumen sekaligus? Dokumen yang ditandai untuk klien akan langsung terlihat oleh klien.')) e.preventDefault();
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
