<?php
// Hapus proyek beserta semua dokumen, laporan progres, foto, dan penugasan tim. Khusus admin.
require __DIR__ . '/includes/init.php';
$u = require_role('admin');
if (!is_post()) redirect('proyek');
verify_csrf();

$p = q('SELECT * FROM proyek WHERE id = ?', [input_int('id')])->fetch();
if (!$p) {
    flash('danger', 'Proyek tidak ditemukan.');
    redirect('proyek');
}
if (strtoupper(trim(input('konfirmasi'))) !== strtoupper($p['kode_proyek'])) {
    flash('danger', 'Kode proyek yang diketik tidak cocok. Proyek tidak dihapus.');
    redirect('proyek_form?id=' . (int)$p['id'] . '#hapus');
}

$id = (int)$p['id'];
$fileDok = q('SELECT path_file FROM dokumen WHERE proyek_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN);
$fileFoto = q('SELECT f.path_file FROM progres_foto f JOIN progres pr ON pr.id = f.progres_id WHERE pr.proyek_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN);

$pdo = db();
$pdo->beginTransaction();
try {
    q('DELETE FROM dokumen WHERE proyek_id = ?', [$id]);
    q('DELETE FROM progres_foto WHERE progres_id IN (SELECT id FROM progres WHERE proyek_id = ?)', [$id]);
    q('DELETE FROM progres WHERE proyek_id = ?', [$id]);
    q('DELETE FROM proyek_tim WHERE proyek_id = ?', [$id]);
    q('DELETE FROM proyek WHERE id = ?', [$id]);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    flash('danger', 'Gagal menghapus proyek.' . (APP_DEBUG ? ' ' . $ex->getMessage() : ''));
    redirect('proyek_detail?id=' . $id);
}

// File fisik dihapus setelah database berhasil
foreach (array_merge($fileDok, $fileFoto) as $f) {
    if (file_ada($f)) @unlink(UPLOAD_DIR . '/' . $f);
}

log_aktivitas('hapus', 'proyek', $id, sprintf('%s %s — %d dokumen, %d foto', $p['kode_proyek'], $p['nama_proyek'], count($fileDok), count($fileFoto)));
flash('success', sprintf('Proyek "%s" dihapus beserta %d dokumen dan %d foto.', $p['nama_proyek'], count($fileDok), count($fileFoto)));
redirect('proyek');
