<?php
// Logika Import Arsip dari file ZIP.
//
// Susunan folder yang dibaca:
//   Nama Proyek - Nama Klien / Kategori / file.pdf
//   Nama Proyek - Nama Klien / Foto / foto1.jpg     -> menjadi foto progres
// Folder kategori dikenali dari namanya (Gambar, RAB, Kontrak, Invoice, Laporan, Foto).
// File langsung di folder proyek atau folder lain masuk kategori "Lainnya".

const IMPOR_KATEGORI_ALIAS = [
    1 => ['gambar', 'desain', 'design', 'drawing', 'denah', 'gambar kerja', 'dwg', 'cad'],
    2 => ['rab', 'anggaran', 'boq', 'rencana anggaran biaya', 'estimasi', 'penawaran'],
    3 => ['kontrak', 'spk', 'perjanjian', 'mou', 'surat perjanjian'],
    4 => ['invoice', 'tagihan', 'kwitansi', 'kuitansi', 'faktur', 'pembayaran', 'termin'],
    5 => ['laporan', 'laporan pengawasan', 'pengawasan', 'laporan mingguan', 'laporan harian', 'berita acara'],
];
const IMPOR_FOTO_ALIAS = ['foto', 'foto progres', 'dokumentasi', 'progres', 'photo', 'photos', 'gambar lapangan'];
const IMPOR_ABAIKAN = ['__macosx', '.ds_store', 'thumbs.db', 'desktop.ini'];

function impor_nama_utf8(string $s): string
{
    if (mb_check_encoding($s, 'UTF-8')) return $s;
    $c = @iconv('CP437', 'UTF-8//IGNORE', $s);
    return $c !== false ? $c : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
}

function impor_kategori_folder(string $folder): int|string
{
    $f = strtolower(trim(preg_replace('/^\d+[\s._-]*/', '', $folder)));
    if (in_array($f, IMPOR_FOTO_ALIAS, true)) return 'foto';
    foreach (IMPOR_KATEGORI_ALIAS as $id => $alias) {
        if (in_array($f, $alias, true)) return $id;
    }
    foreach (IMPOR_KATEGORI_ALIAS as $id => $alias) {
        foreach ($alias as $a) {
            if (strlen($a) > 3 && str_contains($f, $a)) return $id;
        }
    }
    return 6; // Lainnya
}

/** Pecah nama folder "Nama Proyek - Nama Klien" */
function impor_pecah_proyek(string $folder): array
{
    $folder = trim(preg_replace('/\s+/', ' ', $folder));
    $pos = strrpos($folder, ' - ');
    if ($pos === false) return [$folder, ''];
    return [trim(substr($folder, 0, $pos)), trim(substr($folder, $pos + 3))];
}

/** Judul & nomor revisi dari nama file: "Denah Lt1 Rev2.pdf" -> ["Denah Lt1", 2] */
function impor_judul(string $file): array
{
    $base = pathinfo($file, PATHINFO_FILENAME);
    $rev = 0;
    if (preg_match('/[\s_.\-(]*(?:rev(?:isi)?|r)[\s._-]*(\d{1,3})\)?\s*$/i', $base, $m)) {
        $rev = (int)$m[1];
        $base = substr($base, 0, -strlen($m[0]));
    }
    $judul = trim(preg_replace('/[_]+/', ' ', $base), " -.");
    return [$judul !== '' ? $judul : pathinfo($file, PATHINFO_FILENAME), $rev];
}

/**
 * Baca isi ZIP tanpa mengekstrak. Hasil:
 * ['proyek' => [key => [nama, klien, dokumen[], foto[]]], 'lewati' => [[path, alasan]], 'total' => n]
 */
function impor_pindai(ZipArchive $zip): array
{
    $entri = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        $raw = (string)$zip->getNameIndex($i);
        if ($st === false || str_ends_with($raw, '/')) continue;
        $nama = str_replace('\\', '/', impor_nama_utf8($raw));
        $parts = array_values(array_filter(explode('/', $nama), fn($p) => $p !== ''));
        $low = array_map('strtolower', $parts);
        if (array_intersect($low, IMPOR_ABAIKAN) || str_starts_with((string)end($parts), '.') || str_starts_with((string)end($parts), '~$')) continue;
        $entri[] = ['raw' => $raw, 'parts' => $parts, 'size' => (int)$st['size'], 'mtime' => (int)$st['mtime']];
    }

    // Buang folder induk bersama, mis. "Arsip Lama/Proyek - Klien/..." -> "Proyek - Klien/..."
    // Folder induk dibuang jika semua file ada di dalamnya dan namanya bukan format "Proyek - Klien".
    for ($ulang = 0; $ulang < 2; $ulang++) {
        if (!$entri) break;
        $first = $entri[0]['parts'][0];
        $semua = !str_contains($first, ' - ');
        foreach ($entri as $e) {
            if (!$semua) break;
            if (count($e['parts']) < 2 || $e['parts'][0] !== $first) $semua = false;
        }
        if (!$semua) break;
        foreach ($entri as &$e) array_shift($e['parts']);
        unset($e);
    }

    $hasil = ['proyek' => [], 'lewati' => [], 'total' => 0];
    foreach ($entri as $e) {
        $path = implode('/', $e['parts']);
        if (count($e['parts']) < 2) {
            $hasil['lewati'][] = [$path, 'berada di luar folder proyek'];
            continue;
        }
        $file = end($e['parts']);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        [$namaProyek, $namaKlien] = impor_pecah_proyek($e['parts'][0]);
        $key = mb_strtolower($namaProyek . '|' . $namaKlien);
        $hasil['proyek'][$key] ??= ['nama' => $namaProyek, 'klien' => $namaKlien, 'folder' => $e['parts'][0], 'dokumen' => [], 'foto' => []];

        $kat = count($e['parts']) >= 3 ? impor_kategori_folder($e['parts'][1]) : 6;
        $item = ['raw' => $e['raw'], 'file' => $file, 'ext' => $ext, 'size' => $e['size'], 'mtime' => $e['mtime'], 'path' => $path];

        if ($kat === 'foto' && in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $hasil['proyek'][$key]['foto'][] = $item;
        } elseif (in_array($ext, ALLOWED_DOC_EXT, true)) {
            $item['kategori_id'] = $kat === 'foto' ? 6 : $kat;
            [$item['judul'], $item['revisi']] = impor_judul($file);
            $hasil['proyek'][$key]['dokumen'][] = $item;
        } else {
            $hasil['lewati'][] = [$path, 'format .' . ($ext ?: '?') . ' tidak didukung'];
            continue;
        }
        $hasil['total']++;
    }
    foreach ($hasil['proyek'] as $k => $p) {
        if (!$p['dokumen'] && !$p['foto']) unset($hasil['proyek'][$k]);
    }
    ksort($hasil['proyek']);
    return $hasil;
}

/** Salin satu entri ZIP ke file tujuan tanpa memuat seluruhnya ke memori */
function impor_ekstrak(ZipArchive $zip, string $raw, string $dest): bool
{
    $in = $zip->getStream($raw);
    if (!$in) return false;
    $out = @fopen($dest, 'wb');
    if (!$out) { fclose($in); return false; }
    $ok = stream_copy_to_stream($in, $out) !== false;
    fclose($in);
    fclose($out);
    return $ok;
}

function impor_cari_klien(string $nama): ?int
{
    $r = q('SELECT id FROM klien WHERE LOWER(nama) = LOWER(?) ORDER BY id LIMIT 1', [$nama])->fetch();
    return $r ? (int)$r['id'] : null;
}

function impor_cari_proyek(string $nama, ?int $klienId): ?array
{
    if (!$klienId) return null;
    $r = q('SELECT * FROM proyek WHERE LOWER(nama_proyek) = LOWER(?) AND klien_id = ? ORDER BY id LIMIT 1', [$nama, $klienId])->fetch();
    return $r ?: null;
}

function impor_kode_proyek(string $tahun): string
{
    $n = (int)q('SELECT COUNT(*) FROM proyek WHERE kode_proyek LIKE ?', ["TB-$tahun-%"])->fetchColumn();
    do {
        $n++;
        $kode = sprintf('TB-%s-%03d', $tahun, $n);
    } while (q('SELECT 1 FROM proyek WHERE kode_proyek = ?', [$kode])->fetch());
    return $kode;
}

/**
 * Jalankan impor. $opsi: status, layanan, kategori_klien (int[]), user_id
 * Mengembalikan ringkasan jumlah.
 */
function impor_proses(ZipArchive $zip, array $scan, array $opsi): array
{
    @set_time_limit(0);
    $r = ['klien_baru' => 0, 'proyek_baru' => 0, 'proyek_lama' => 0, 'dokumen' => 0, 'foto' => 0, 'duplikat' => 0, 'gagal' => []];
    $dirDok = UPLOAD_DIR . '/dokumen';
    $dirFoto = UPLOAD_DIR . '/foto';
    if (!siapkan_folder_upload('dokumen') || !siapkan_folder_upload('foto')) throw new RuntimeException(pesan_izin_uploads());
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($scan['proyek'] as $p) {
        $namaKlien = $p['klien'] !== '' ? $p['klien'] : 'Klien belum diisi';
        $klienId = impor_cari_klien($namaKlien);
        if (!$klienId) {
            q('INSERT INTO klien (nama, catatan) VALUES (?, ?)', [$namaKlien, 'Dibuat otomatis dari Import Arsip']);
            $klienId = (int)db()->lastInsertId();
            $r['klien_baru']++;
        }

        $proyek = impor_cari_proyek($p['nama'], $klienId);
        if ($proyek) {
            $proyekId = (int)$proyek['id'];
            $persen = (int)$proyek['progres_persen'];
            $r['proyek_lama']++;
        } else {
            $tanggal = array_map(fn($i) => $i['mtime'], array_merge($p['dokumen'], $p['foto']));
            $mulai = $tanggal ? date('Y-m-d', min($tanggal)) : null;
            $selesai = $tanggal ? date('Y-m-d', max($tanggal)) : null;
            $persen = $opsi['status'] === 'selesai' ? 100 : 0;
            q('INSERT INTO proyek (kode_proyek, klien_id, nama_proyek, jenis_layanan, tgl_mulai, tgl_target_selesai, status, progres_persen, keterangan)
               VALUES (?,?,?,?,?,?,?,?,?)', [
                impor_kode_proyek($mulai ? substr($mulai, 0, 4) : date('Y')), $klienId, $p['nama'], $opsi['layanan'],
                $mulai, $selesai, $opsi['status'], $persen, 'Dibuat dari Import Arsip (folder: ' . $p['folder'] . ')',
            ]);
            $proyekId = (int)db()->lastInsertId();
            $r['proyek_baru']++;
        }

        foreach ($p['dokumen'] as $d) {
            if (q('SELECT 1 FROM dokumen WHERE proyek_id = ? AND nama_file = ? AND ukuran = ?', [$proyekId, mb_substr($d['file'], 0, 255), $d['size']])->fetch()) {
                $r['duplikat']++;
                continue;
            }
            if ($d['size'] > 200 * 1048576) {
                $r['gagal'][] = [$d['path'], 'lebih dari 200 MB'];
                continue;
            }
            $nama = date('Ym') . '_' . bin2hex(random_bytes(12)) . '.' . $d['ext'];
            $dest = $dirDok . '/' . $nama;
            if (!impor_ekstrak($zip, $d['raw'], $dest)) {
                $r['gagal'][] = [$d['path'], 'gagal diekstrak'];
                continue;
            }
            $mime = $finfo->file($dest) ?: 'application/octet-stream';
            if ($salah = cek_isi_dokumen($dest, $d['ext'])) {
                @unlink($dest);
                $r['gagal'][] = [$d['path'], mb_strtolower(rtrim($salah, '.'))];
                continue;
            }
            q('INSERT INTO dokumen (proyek_id, kategori_id, judul, revisi, nama_file, path_file, tipe_file, ukuran, tanggal_dokumen,
                 tampil_ke_klien, status_persetujuan, sumber, diupload_oleh) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $proyekId, $d['kategori_id'], mb_substr($d['judul'], 0, 200), $d['revisi'], mb_substr($d['file'], 0, 255),
                'dokumen/' . $nama, $mime, $d['size'], date('Y-m-d', $d['mtime'] ?: time()),
                in_array($d['kategori_id'], $opsi['kategori_klien'], true) ? 1 : 0, 'menunggu', 'impor', $opsi['user_id'],
            ]);
            $r['dokumen']++;
        }

        // Foto dikelompokkan per tanggal menjadi laporan progres
        $perTanggal = [];
        foreach ($p['foto'] as $f) $perTanggal[date('Y-m-d', $f['mtime'] ?: time())][] = $f;
        ksort($perTanggal);
        foreach ($perTanggal as $tgl => $fotos) {
            $baru = [];
            foreach ($fotos as $f) {
                $penanda = mb_substr('impor:' . $f['file'] . ':' . $f['size'], 0, 255);
                if (q('SELECT 1 FROM progres_foto f JOIN progres pr ON pr.id = f.progres_id WHERE pr.proyek_id = ? AND f.keterangan = ?', [$proyekId, $penanda])->fetch()) {
                    $r['duplikat']++;
                    continue;
                }
                if ($f['size'] > 50 * 1048576) {
                    $r['gagal'][] = [$f['path'], 'foto lebih dari 50 MB'];
                    continue;
                }
                $tmp = file_sementara('imp');
                if (!$tmp || !impor_ekstrak($zip, $f['raw'], $tmp)) {
                    $r['gagal'][] = [$f['path'], 'gagal diekstrak'];
                    if ($tmp) @unlink($tmp);
                    continue;
                }
                $nama = date('Ym') . '_' . bin2hex(random_bytes(12)) . '.jpg';
                if (simpan_foto($tmp, $dirFoto . '/' . $nama)) {
                    $baru[] = ['foto/' . $nama, $penanda];
                } else {
                    $r['gagal'][] = [$f['path'], 'bukan foto yang valid'];
                }
                @unlink($tmp);
            }
            if (!$baru) continue;
            q('INSERT INTO progres (proyek_id, tanggal, persentase, catatan, dibuat_oleh) VALUES (?,?,?,?,?)',
                [$proyekId, $tgl, $persen, 'Foto dokumentasi dari arsip lama (' . count($baru) . ' foto)', $opsi['user_id']]);
            $prid = (int)db()->lastInsertId();
            foreach ($baru as [$path, $penanda]) {
                q('INSERT INTO progres_foto (progres_id, path_file, keterangan) VALUES (?,?,?)', [$prid, $path, $penanda]);
                $r['foto']++;
            }
        }
    }
    return $r;
}
