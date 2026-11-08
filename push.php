<?php
// Langganan notifikasi HP/laptop (Web Push) untuk user yang sedang login.
require __DIR__ . '/includes/init.php';
$u = require_login();

if (!is_post()) {
    [$pub] = vapid_kunci();
    $jml = (int)q('SELECT COUNT(*) FROM push_langganan WHERE user_id = ?', [$u['id']])->fetchColumn();
    json_out(['kunci' => $pub, 'perangkat' => $jml]);
}
verify_csrf();
$aksi = input('aksi');
$endpoint = input('endpoint');

if ($aksi === 'simpan') {
    $p256dh = input('p256dh');
    $auth = input('auth');
    if (!push_endpoint_sah($endpoint) || strlen($endpoint) > 1000 || strlen(b64u_dec($p256dh)) !== 65 || strlen(b64u_dec($auth)) !== 16) {
        json_out(['ok' => false, 'pesan' => 'Data langganan tidak valid.'], 422);
    }
    $h = hash('sha256', $endpoint);
    q('DELETE FROM push_langganan WHERE endpoint_hash = ?', [$h]);
    q('INSERT INTO push_langganan (user_id, endpoint, endpoint_hash, p256dh, auth, nama) VALUES (?,?,?,?,?,?)',
        [$u['id'], $endpoint, $h, $p256dh, $auth, percaya_nama_perangkat()]);
    // batasi 10 perangkat per user
    $lama = q('SELECT id FROM push_langganan WHERE user_id = ? ORDER BY dibuat DESC, id DESC', [$u['id']])->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_slice($lama, 10) as $id) q('DELETE FROM push_langganan WHERE id = ?', [$id]);
    log_aktivitas('ubah', 'user', (int)$u['id'], 'mengaktifkan notifikasi di ' . percaya_nama_perangkat());
    json_out(['ok' => true]);
}
if ($aksi === 'hapus') {
    q('DELETE FROM push_langganan WHERE endpoint_hash = ? AND user_id = ?', [hash('sha256', $endpoint), $u['id']]);
    json_out(['ok' => true]);
}
if ($aksi === 'tes') {
    notif_kirim([$u['id']], 'Notifikasi aktif', 'Anda akan menerima kabar dokumen & progres proyek di perangkat ini.', 'dashboard', 'tes');
    json_out(['ok' => true]);
}
json_out(['ok' => false], 400);
