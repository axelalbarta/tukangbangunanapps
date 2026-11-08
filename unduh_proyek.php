<?php
// Unduh semua dokumen + foto progres satu proyek sebagai satu file ZIP.
// Susunan folder sama dengan format Import Arsip, jadi bisa dipakai untuk backup & diimpor ulang.
require __DIR__ . '/includes/init.php';
$u = require_role('ceo', 'admin');

$p = proyek_akses(input_int('id'), $u);
if (!$p) {
    halaman_404();
}
if (!class_exists('ZipArchive')) {
    flash('danger', 'Ekstensi ZIP di PHP belum aktif. Aktifkan extension=zip di php.ini lalu restart Apache.');
    redirect('proyek_detail?id=' . (int)$p['id']);
}

$hanyaDisetujui = input('isi') === 'disetujui';
$sql = 'SELECT d.*, kd.nama AS kategori FROM dokumen d JOIN kategori_dokumen kd ON kd.id = d.kategori_id WHERE d.proyek_id = ?';
if ($hanyaDisetujui) $sql .= " AND d.status_persetujuan = 'disetujui'";
$dokumen = q($sql . ' ORDER BY kd.urutan, d.judul, d.revisi', [$p['id']])->fetchAll();
$foto = q('SELECT f.path_file, pr.tanggal FROM progres_foto f JOIN progres pr ON pr.id = f.progres_id
           WHERE pr.proyek_id = ? ORDER BY pr.tanggal, f.id', [$p['id']])->fetchAll();

if (!$dokumen && !$foto) {
    flash('warning', 'Proyek ini belum punya dokumen atau foto untuk diunduh.');
    redirect('proyek_detail?id=' . (int)$p['id']);
}

@set_time_limit(0);
$bersih = fn(string $s) => trim(preg_replace('/[\\\\\/:*?"<>|]+/', '-', $s), " .-") ?: 'tanpa-nama';
$root = $bersih($p['nama_proyek'] . ' - ' . $p['nama_klien']);
$tmp = file_sementara('zip');
$zip = new ZipArchive();
if (!$tmp || $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    if ($tmp) @unlink($tmp);
    flash('danger', 'Gagal membuat file ZIP. ' . pesan_izin_uploads());
    redirect('proyek_detail?id=' . (int)$p['id']);
}

$terpakai = [];
$namaUnik = function (string $path) use (&$terpakai): string {
    $asli = $path;
    $i = 2;
    while (isset($terpakai[strtolower($path)])) {
        $info = pathinfo($asli);
        $path = $info['dirname'] . '/' . $info['filename'] . " ($i)" . (isset($info['extension']) ? '.' . $info['extension'] : '');
        $i++;
    }
    $terpakai[strtolower($path)] = true;
    return $path;
};

// Daftar isi (dibuka di Excel). Sel yang diawali = + - @ diberi tanda kutip agar tidak dijalankan sebagai rumus.
$selAman = fn($v) => (is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false) ? "'" . $v : $v;
$csv = fopen('php://temp', 'w+');
fwrite($csv, "\xEF\xBB\xBF");
fputcsv($csv, ['Kategori', 'Judul', 'Revisi', 'Tanggal dokumen', 'Status', 'Untuk klien', 'Nama file'], ';');

$jml = 0;
foreach ($dokumen as $d) {
    $src = UPLOAD_DIR . '/' . $d['path_file'];
    if (!is_file($src)) continue;
    $dalam = $namaUnik($root . '/' . $bersih($d['kategori']) . '/' . $bersih($d['nama_file']));
    $zip->addFile($src, $dalam);
    if ($d['tanggal_dokumen']) $zip->setMtimeName($dalam, (int)strtotime($d['tanggal_dokumen'] . ' 12:00:00'));
    fputcsv($csv, array_map($selAman, [
        $d['kategori'], $d['judul'], $d['revisi'] ? 'Rev.' . $d['revisi'] : '-',
        $d['tanggal_dokumen'] ?: substr($d['dibuat_pada'], 0, 10), STATUS_DOK[$d['status_persetujuan']][0] ?? $d['status_persetujuan'],
        (int)$d['tampil_ke_klien'] ? 'Ya' : 'Tidak', basename($dalam),
    ]), ';');
    $jml++;
}
foreach ($foto as $i => $f) {
    $src = UPLOAD_DIR . '/' . $f['path_file'];
    if (!is_file($src)) continue;
    $dalam = $namaUnik($root . '/Foto/' . $f['tanggal'] . '_' . sprintf('%03d', $i + 1) . '.jpg');
    $zip->addFile($src, $dalam);
    $zip->setMtimeName($dalam, (int)strtotime($f['tanggal'] . ' 12:00:00'));
    $jml++;
}
rewind($csv);
$zip->addFromString($root . '/Daftar Isi.csv', stream_get_contents($csv));
fclose($csv);
if (!$zip->close() || !is_file($tmp) || filesize($tmp) === 0) {
    @unlink($tmp);
    flash('danger', 'Gagal menyelesaikan file ZIP. ' . pesan_izin_uploads());
    redirect('proyek_detail?id=' . (int)$p['id']);
}

log_aktivitas('unduh_zip', 'proyek', (int)$p['id'], $p['nama_proyek'] . " — $jml file" . ($hanyaDisetujui ? ' (hanya disetujui)' : ''));

$namaZip = $bersih($p['kode_proyek'] . ' ' . $p['nama_proyek']) . ($hanyaDisetujui ? ' (disetujui)' : '') . '.zip';
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/zip');
header('Content-Length: ' . filesize($tmp));
header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w\-. ()]+/u', '_', $namaZip) . '"; filename*=UTF-8\'\'' . rawurlencode($namaZip));
header('Cache-Control: private, max-age=0');
readfile($tmp);
@unlink($tmp);
