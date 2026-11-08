<?php
// Menampilkan foto progres setelah hak akses proyek dicek.
require __DIR__ . '/includes/init.php';
$u = require_login();

$f = q('SELECT f.*, pr.proyek_id FROM progres_foto f JOIN progres pr ON pr.id = f.progres_id WHERE f.id = ?', [input_int('id')])->fetch();
if (!$f || !proyek_akses((int)$f['proyek_id'], $u)) {
    halaman_404();
}
$path = realpath(UPLOAD_DIR . '/' . $f['path_file']);
if (!$path || !str_starts_with($path, realpath(UPLOAD_DIR)) || !is_file($path)) {
    http_response_code(404);
    exit('File foto tidak ditemukan.');
}
$info = @getimagesize($path);
while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . ($info['mime'] ?? 'image/jpeg'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
readfile($path);
