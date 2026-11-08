<?php
require __DIR__ . '/includes/init.php';
$u = require_role('admin');
if (!is_post()) redirect('proyek');
verify_csrf();

$pr = q('SELECT pr.*, p.nama_proyek FROM progres pr JOIN proyek p ON p.id = pr.proyek_id WHERE pr.id = ?', [input_int('id')])->fetch();
if (!$pr) {
    flash('danger', 'Laporan tidak ditemukan.');
    redirect('proyek');
}
$fotos = q('SELECT path_file FROM progres_foto WHERE progres_id = ?', [$pr['id']])->fetchAll(PDO::FETCH_COLUMN);
q('DELETE FROM progres WHERE id = ?', [$pr['id']]);
foreach ($fotos as $f) {
    $path = UPLOAD_DIR . '/' . $f;
    if (is_file($path)) @unlink($path);
}
q('UPDATE proyek SET progres_persen = COALESCE((SELECT persentase FROM progres WHERE proyek_id = ? ORDER BY tanggal DESC, id DESC LIMIT 1), 0) WHERE id = ?',
    [$pr['proyek_id'], $pr['proyek_id']]);
log_aktivitas('hapus', 'progres', (int)$pr['id'], $pr['nama_proyek'] . ' — ' . $pr['tanggal']);
flash('success', 'Laporan progres dihapus.');
redirect('proyek_detail?id=' . (int)$pr['proyek_id'] . '&tab=progres');
