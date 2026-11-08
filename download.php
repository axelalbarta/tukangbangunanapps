<?php
// Mengirim file dokumen setelah hak akses dicek. File tidak pernah dibuka lewat link langsung.
require __DIR__ . '/includes/init.php';
$u = require_login();

$d = q('SELECT * FROM dokumen WHERE id = ?', [input_int('id')])->fetch();
if (!$d || !dokumen_boleh_dilihat($d, $u)) {
    halaman_404();
}
$path = realpath(UPLOAD_DIR . '/' . $d['path_file']);
if (!$path || !str_starts_with($path, realpath(UPLOAD_DIR)) || !is_file($path)) {
    http_response_code(404);
    $kembali = 'proyek_detail?id=' . (int)$d['proyek_id'];
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:15vh auto;padding:24px;border:1px solid #eee;border-radius:12px;border-top:5px solid #d71920">'
        . '<h2 style="margin-top:0">File tidak ditemukan</h2><p>Dokumen <b>' . e($d['judul']) . '</b> tercatat di arsip, tetapi filenya sudah tidak ada di folder <code>uploads</code> server.</p>'
        . '<p style="color:#555">Kemungkinan folder aplikasi tertimpa saat pembaruan. Hubungi admin untuk memulihkan file dari cadangan atau mengunggah ulang dokumen ini.</p>'
        . '<a href="' . e($kembali) . '" style="display:inline-block;background:#d71920;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">Kembali ke proyek</a></div>');
}

$inline = !isset($_GET['dl']) && bisa_preview($d['nama_file']);
$ext = strtolower(pathinfo($d['nama_file'], PATHINFO_EXTENSION));
$mime = match ($ext) {
    'pdf' => 'application/pdf',
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    default => 'application/octet-stream',
};
$namaUnduh = preg_replace('/[^\w\-. ]+/u', '_', $d['nama_file']);

if (!isset($_GET['dl'])) {
    log_aktivitas('lihat', 'dokumen', (int)$d['id'], $d['judul']);
}

while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $namaUnduh . '"; filename*=UTF-8\'\'' . rawurlencode($d['nama_file']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0');
readfile($path);
