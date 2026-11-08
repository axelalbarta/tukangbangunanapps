<?php
require __DIR__ . '/includes/init.php';
$u = require_login();

if (!is_post()) redirect('dashboard');
verify_csrf();

$id = input_int('id');
$aksi = input('aksi');
$d = q('SELECT * FROM dokumen WHERE id = ?', [$id])->fetch();
if (!$d || !dokumen_boleh_dilihat($d, $u)) {
    flash('danger', 'Dokumen tidak ditemukan.');
    redirect('dashboard');
}
$role = $u['role'];
$namaProyek = (string)q('SELECT nama_proyek FROM proyek WHERE id = ?', [$d['proyek_id']])->fetchColumn();
$urlProyek = 'proyek_detail?id=' . (int)$d['proyek_id'];
$kembali = input('kembali') === 'persetujuan' ? 'persetujuan' : 'proyek_detail?id=' . (int)$d['proyek_id'];

switch ($aksi) {
    case 'setujui':
        if ($role !== 'ceo' || $d['status_persetujuan'] !== 'menunggu') break;
        q("UPDATE dokumen SET status_persetujuan='disetujui', disetujui_oleh=?, disetujui_pada=NOW(), catatan_persetujuan=NULL WHERE id=?", [$u['id'], $id]);
        log_aktivitas('setujui', 'dokumen', $id, $d['judul']);
        if ($d['diupload_oleh']) notif_kirim(tanpa_saya([$d['diupload_oleh']]), 'Dokumen disetujui CEO', $d['judul'] . ' · ' . $namaProyek, $urlProyek, 'dok-' . $id);
        if ((int)$d['tampil_ke_klien']) notif_kirim(notif_klien_proyek((int)$d['proyek_id']), 'Dokumen baru untuk Anda', $d['judul'] . ' · ' . $namaProyek, $urlProyek, 'dok-' . $id);
        flash('success', 'Dokumen "' . $d['judul'] . '" disetujui.' . ((int)$d['tampil_ke_klien'] ? ' Sekarang terlihat oleh klien.' : ''));
        redirect($kembali);

    case 'tolak':
        if ($role !== 'ceo' || $d['status_persetujuan'] !== 'menunggu') break;
        $catatan = mb_substr(input('catatan'), 0, 255);
        q("UPDATE dokumen SET status_persetujuan='ditolak', disetujui_oleh=?, disetujui_pada=NOW(), catatan_persetujuan=? WHERE id=?", [$u['id'], $catatan ?: null, $id]);
        log_aktivitas('tolak', 'dokumen', $id, $d['judul'] . ($catatan ? " — $catatan" : ''));
        if ($d['diupload_oleh']) notif_kirim(tanpa_saya([$d['diupload_oleh']]), 'Dokumen ditolak, perlu revisi', $d['judul'] . ($catatan ? ': ' . $catatan : '') . ' · ' . $namaProyek, $urlProyek, 'dok-' . $id);
        flash('warning', 'Dokumen "' . $d['judul'] . '" ditolak dan dikembalikan ke pengunggah.');
        redirect($kembali);

    case 'ajukan':
        if (!in_array($role, ['admin', 'tim'], true) || !in_array($d['status_persetujuan'], ['draft', 'ditolak'], true)) break;
        q("UPDATE dokumen SET status_persetujuan='menunggu', catatan_persetujuan=NULL, disetujui_oleh=NULL, disetujui_pada=NULL WHERE id=?", [$id]);
        log_aktivitas('ajukan', 'dokumen', $id, $d['judul']);
        notif_kirim(tanpa_saya(notif_ceo()), 'Dokumen menunggu persetujuan', $d['judul'] . ' · ' . $namaProyek . ' (oleh ' . $u['nama'] . ')', 'persetujuan', 'menunggu');
        flash('success', 'Dokumen diajukan ke CEO untuk disetujui.');
        redirect($kembali);

    case 'toggle_klien':
        if ($role !== 'admin') break;
        $baru = (int)$d['tampil_ke_klien'] ? 0 : 1;
        q('UPDATE dokumen SET tampil_ke_klien=? WHERE id=?', [$baru, $id]);
        log_aktivitas($baru ? 'tampilkan_klien' : 'sembunyikan_klien', 'dokumen', $id, $d['judul']);
        flash('success', $baru
            ? ($d['status_persetujuan'] === 'disetujui' ? 'Dokumen sekarang terlihat oleh klien.' : 'Dokumen akan terlihat oleh klien setelah disetujui CEO.')
            : 'Dokumen disembunyikan dari klien.');
        redirect($kembali);

    case 'hapus':
        if ($role !== 'admin') break;
        q('DELETE FROM dokumen WHERE id=?', [$id]);
        $path = UPLOAD_DIR . '/' . $d['path_file'];
        if (is_file($path)) @unlink($path);
        log_aktivitas('hapus', 'dokumen', $id, $d['judul']);
        flash('success', 'Dokumen dihapus.');
        redirect($kembali);
}

flash('danger', 'Aksi tidak diizinkan.');
redirect($kembali);
