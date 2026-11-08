<?php
// Data contoh untuk mencoba aplikasi. Semua data contoh diberi penanda supaya bisa dihapus bersih:
//   user: email @contoh.tb · klien: catatan "[CONTOH]" · proyek: kode "CTH-…" · log: keterangan "[contoh]"

const CONTOH_DOMAIN = '@contoh.tb';
const CONTOH_KODE = 'CTH-';

function contoh_ada(): bool
{
    return (bool)q('SELECT 1 FROM proyek WHERE kode_proyek LIKE ? LIMIT 1', [CONTOH_KODE . '%'])->fetch();
}

// ---------------------------------------------------------------------------
// Pembuat file contoh (tanpa library luar)
// ---------------------------------------------------------------------------
/** PDF sederhana 1 halaman A4 berisi judul & baris teks */
function contoh_pdf(string $judul, array $baris): string
{
    $esc = fn(string $t) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'Windows-1252//TRANSLIT', $t) ?: $t);
    $isi = "0.843 0.098 0.125 rg 0 812 595 30 re f\n"                 // pita merah atas
        . "BT /F2 11 Tf 1 1 1 rg 40 822 Td (TUKANG BANGUNAN  -  DATA CONTOH) Tj ET\n"
        . "BT /F2 18 Tf 0.07 0.07 0.07 rg 40 770 Td (" . $esc($judul) . ") Tj ET\n"
        . "0.85 0.85 0.85 RG 40 755 m 555 755 l S\n";
    $y = 730;
    foreach ($baris as $b) {
        $tebal = str_starts_with($b, '#');
        $t = ltrim($b, '# ');
        $isi .= 'BT /' . ($tebal ? 'F2 12' : 'F1 11') . ' Tf 0.15 0.15 0.15 rg 40 ' . $y . ' Td (' . $esc($t) . ") Tj ET\n";
        $y -= $tebal ? 22 : 17;
        if ($y < 60) break;
    }
    $isi .= "BT /F1 8 Tf 0.5 0.5 0.5 rg 40 30 Td (Dokumen ini adalah data contoh untuk mencoba aplikasi Arsip Tukang Bangunan.) Tj ET\n";
    $obj = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        '<< /Length ' . strlen($isi) . " >>\nstream\n" . $isi . "endstream",
    ];
    $pdf = "%PDF-1.4\n";
    $off = [];
    foreach ($obj as $i => $o) {
        $off[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
    return $pdf . "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
}

/** Tulisan besar di gambar GD (font bawaan diperbesar) */
function contoh_teks($img, string $teks, int $x, int $y, int $skala, int $warna): void
{
    $w = imagefontwidth(5) * strlen($teks);
    $h = imagefontheight(5);
    $kecil = imagecreatetruecolor($w, $h);
    imagesavealpha($kecil, true);
    imagefill($kecil, 0, 0, imagecolorallocatealpha($kecil, 0, 0, 0, 127));
    imagestring($kecil, 5, 0, 0, $teks, imagecolorallocate($kecil, ($warna >> 16) & 255, ($warna >> 8) & 255, $warna & 255));
    imagecopyresampled($img, $kecil, $x, $y, 0, 0, $w * $skala, $h * $skala, $w, $h);
    imagedestroy($kecil);
}

/** Foto progres contoh: langit, tanah, bangunan setengah jadi sesuai persentase */
function contoh_foto(string $label, int $persen, int $variasi): string
{
    $W = 1200; $H = 800;
    $im = imagecreatetruecolor($W, $H);
    for ($y = 0; $y < 520; $y++) {               // langit
        $t = $y / 520;
        imageline($im, 0, $y, $W, $y, imagecolorallocate($im, (int)(120 + 80 * $t), (int)(170 + 60 * $t), (int)(225 + 20 * $t)));
    }
    imagefilledrectangle($im, 0, 520, $W, $H, imagecolorallocate($im, 150 - $variasi * 6, 118, 80));   // tanah
    $abu = imagecolorallocate($im, 170, 170, 165);
    $bata = imagecolorallocate($im, 186, 92, 62);
    $kayu = imagecolorallocate($im, 120, 84, 48);
    $merah = imagecolorallocate($im, 215, 25, 32);
    $x0 = 260 + $variasi * 30; $lebar = 620; $dasar = 560;
    imagefilledrectangle($im, $x0 - 20, $dasar - 20, $x0 + $lebar + 20, $dasar, $abu);              // fondasi
    $tinggi = (int)(320 * min(1, $persen / 70));
    for ($r = 0; $r * 24 < $tinggi; $r++) {                                                       // dinding bata
        for ($c = 0; $c * 52 < $lebar; $c++) {
            $bx = $x0 + $c * 52 + ($r % 2 ? 26 : 0);
            if ($bx + 48 > $x0 + $lebar) continue;
            imagefilledrectangle($im, $bx, $dasar - 20 - ($r + 1) * 24 + 2, $bx + 48, $dasar - 20 - $r * 24, $bata);
        }
    }
    foreach ([0, $lebar / 2, $lebar] as $k) {                                                     // kolom
        imagefilledrectangle($im, (int)($x0 + $k - 12), $dasar - 20 - min(360, $tinggi + 40), (int)($x0 + $k + 12), $dasar - 20, $abu);
    }
    if ($persen >= 75) {                                                                           // atap
        imagefilledpolygon($im, [$x0 - 40, $dasar - 360, $x0 + $lebar / 2, $dasar - 480, $x0 + $lebar + 40, $dasar - 360], $merah);
    } elseif ($persen >= 40) {
        imagesetthickness($im, 8);
        imageline($im, $x0 - 40, $dasar - 360, (int)($x0 + $lebar / 2), $dasar - 480, $kayu);
        imageline($im, (int)($x0 + $lebar / 2), $dasar - 480, $x0 + $lebar + 40, $dasar - 360, $kayu);
    }
    imagefilledrectangle($im, 0, 0, $W, 70, imagecolorallocatealpha($im, 0, 0, 0, 50));
    contoh_teks($im, $label, 24, 14, 3, 0xFFFFFF);
    contoh_teks($im, 'DATA CONTOH  -  PROGRES ' . $persen . '%', 24, $H - 60, 3, 0xFFFFFF);
    ob_start(); imagejpeg($im, null, 78); imagedestroy($im);
    return (string)ob_get_clean();
}

/** Gambar denah sederhana (PNG) */
function contoh_denah(string $judul, int $variasi): string
{
    $W = 1400; $H = 1000;
    $im = imagecreatetruecolor($W, $H);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    $grid = imagecolorallocate($im, 225, 235, 245);
    for ($x = 0; $x < $W; $x += 40) imageline($im, $x, 0, $x, $H, $grid);
    for ($y = 0; $y < $H; $y += 40) imageline($im, 0, $y, $W, $y, $grid);
    $hitam = imagecolorallocate($im, 30, 30, 30);
    $merah = imagecolorallocate($im, 215, 25, 32);
    imagesetthickness($im, 10);
    imagerectangle($im, 160, 160, 1240, 840, $hitam);
    imagesetthickness($im, 6);
    $bagi = 560 + $variasi * 40;
    imageline($im, $bagi, 160, $bagi, 840, $hitam);
    imageline($im, 160, 500, $bagi, 500, $hitam);
    imageline($im, $bagi, 620, 1240, 620, $hitam);
    imagesetthickness($im, 3);
    imagearc($im, $bagi, 720, 160, 160, 180, 270, $merah);
    contoh_teks($im, 'R. TAMU', 260, 300, 3, 0x333333);
    contoh_teks($im, 'K. TIDUR', 260, 640, 3, 0x333333);
    contoh_teks($im, 'R. KELUARGA', $bagi + 120, 360, 3, 0x333333);
    contoh_teks($im, 'DAPUR', $bagi + 160, 700, 3, 0x333333);
    contoh_teks($im, strtoupper($judul), 160, 60, 3, 0xD71920);
    contoh_teks($im, 'SKALA 1:100  -  DATA CONTOH', 160, 900, 2, 0x666666);
    ob_start(); imagepng($im, null, 6); imagedestroy($im);
    return (string)ob_get_clean();
}

function contoh_simpan_file(string $sub, string $ext, string $isi): string
{
    if (!siapkan_folder_upload($sub)) throw new RuntimeException(pesan_izin_uploads());
    $nama = date('Ym') . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (file_put_contents(UPLOAD_DIR . '/' . $sub . '/' . $nama, $isi) === false) throw new RuntimeException('Gagal menulis file contoh.');
    return $sub . '/' . $nama;
}

// ---------------------------------------------------------------------------
// Isi & hapus
// ---------------------------------------------------------------------------
/** Mengisi data contoh. Mengembalikan daftar akun contoh beserta password-nya (ditampilkan sekali). */
function contoh_isi(array $admin): array
{
    if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('Ekstensi GD PHP tidak aktif, foto contoh tidak bisa dibuat.');
    $hari = fn(int $n) => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
    $waktu = fn(int $n, string $jam = '09:15:00') => $hari($n) . ' ' . $jam;
    $akun = [];
    $fileBaru = [];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // --- User contoh ---
        $buatUser = function (string $nama, string $email, string $role, ?string $jabatan, string $hp) use (&$akun) {
            $pw = 'Tb' . substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 8) . random_int(10, 99);
            q('INSERT INTO users (nama, email, password_hash, role, jabatan, no_hp, aktif) VALUES (?,?,?,?,?,?,1)',
                [$nama, $email, password_hash($pw, PASSWORD_DEFAULT), $role, $jabatan, $hp]);
            $akun[] = [$nama, ROLE_LABEL[$role] . ($jabatan ? ' · ' . $jabatan : ''), $email, $pw];
            return (int)db()->lastInsertId();
        };
        $ceo = $buatUser('Yohanes Hindom (Contoh)', 'ceo' . CONTOH_DOMAIN, 'ceo', 'Direktur', '0811 4800 101');
        $ars = $buatUser('Rina Patiran', 'arsitek' . CONTOH_DOMAIN, 'tim', 'Arsitek', '0812 4800 202');
        $pgw = $buatUser('Daniel Kabes', 'pengawas' . CONTOH_DOMAIN, 'tim', 'Pengawas', '0813 4800 303');
        $mdr = $buatUser('Abdul Iba', 'mandor' . CONTOH_DOMAIN, 'tim', 'Mandor', '0852 4800 404');
        $uk1 = $buatUser('Bapak Markus Tuturop', 'klien.markus' . CONTOH_DOMAIN, 'klien', null, '0821 4800 505');
        $uk2 = $buatUser('Ibu Siti Rumagesan', 'klien.siti' . CONTOH_DOMAIN, 'klien', null, '0822 4800 606');

        // --- Klien ---
        $klien = [];
        foreach ([
            ['Bapak Markus Tuturop', $uk1, 'Jl. Izak Telussa No. 12, Fakfak', '0821 4800 505'],
            ['Ibu Siti Rumagesan', $uk2, 'Kel. Wagom, Distrik Pariwari, Fakfak', '0822 4800 606'],
            ['CV Teluk Berau Mandiri', null, 'Jl. Salasa Namudat, Fakfak', '0956 22 100'],
            ['Toko Bangunan Sinar Kokas', null, 'Distrik Kokas, Fakfak', '0853 4800 707'],
            ['Yayasan Pendidikan Mambruk', null, 'Kel. Fakfak Utara, Fakfak', '0956 22 200'],
        ] as [$nama, $uid, $alamat, $telp]) {
            q('INSERT INTO klien (user_id, nama, alamat, telepon, catatan) VALUES (?,?,?,?,?)', [$uid, $nama, $alamat, $telp, '[CONTOH] Data contoh untuk uji coba aplikasi.']);
            $klien[] = (int)db()->lastInsertId();
        }

        // --- Proyek ---
        $tahun = date('Y');
        $proyek = [
            // kode, klien, nama, lokasi, lat, lng, layanan, nilai, mulai, target, status, persen, tim, keterangan
            ['001', 0, 'Rumah Tinggal 2 Lantai Bpk Markus', 'Jl. Izak Telussa No. 12, Fakfak', -2.9268, 132.2985, 'pelaksanaan', 685000000, -95, 85, 'berjalan', 68, [[$ars, 'arsitek'], [$pgw, 'pengawas'], [$mdr, 'mandor']], 'Rumah 2 lantai, luas bangunan 180 m², struktur beton bertulang.'],
            ['002', 1, 'Renovasi Rumah & Dapur Ibu Siti', 'Kel. Wagom, Distrik Pariwari', -2.9131, 132.3172, 'pelaksanaan', 145000000, -40, 35, 'berjalan', 42, [[$pgw, 'pengawas'], [$mdr, 'mandor']], 'Renovasi atap, perluasan dapur, dan keramik baru.'],
            ['003', 2, 'Ruko 3 Pintu CV Teluk Berau', 'Jl. Salasa Namudat, Fakfak', -2.9302, 132.2938, 'pengawasan', 98000000, -60, 120, 'berjalan', 31, [[$pgw, 'pengawas']], 'Jasa pengawasan pembangunan ruko 3 lantai.'],
            ['004', 3, 'Gudang Material Sinar Kokas', 'Distrik Kokas, Fakfak', -2.7121, 132.4243, 'pelaksanaan', 420000000, -210, -30, 'selesai', 100, [[$mdr, 'mandor'], [$pgw, 'pengawas']], 'Gudang baja ringan 20 x 30 m, sudah serah terima.'],
            ['005', 4, 'Gedung Kelas Baru Yayasan Mambruk', 'Kel. Fakfak Utara', -2.9205, 132.3051, 'perencanaan', 75000000, -20, 40, 'penawaran', 0, [[$ars, 'arsitek']], 'Perencanaan 4 ruang kelas dan toilet. Menunggu persetujuan desain.'],
            ['006', 0, 'Pagar & Carport Bpk Markus', 'Jl. Izak Telussa No. 12, Fakfak', -2.9269, 132.2987, 'pelaksanaan', 38500000, -15, 30, 'tertunda', 20, [[$mdr, 'mandor']], 'Tertunda: menunggu material besi hollow dari Sorong.'],
        ];
        $idProyek = [];
        foreach ($proyek as [$no, $ki, $nama, $lokasi, $lat, $lng, $layanan, $nilai, $mulai, $target, $status, $persen, $tim, $ket]) {
            q('INSERT INTO proyek (kode_proyek, klien_id, nama_proyek, lokasi, latitude, longitude, jenis_layanan, nilai_kontrak, tgl_mulai, tgl_target_selesai, status, progres_persen, keterangan)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [CONTOH_KODE . $tahun . '-' . $no, $klien[$ki], $nama, $lokasi, $lat, $lng, $layanan, $nilai, $hari($mulai), $hari($target), $status, $persen, $ket]);
            $pid = (int)db()->lastInsertId();
            $idProyek[$no] = $pid;
            foreach ($tim as [$uid, $peran]) q('INSERT INTO proyek_tim (proyek_id, user_id, peran) VALUES (?,?,?)', [$pid, $uid, $peran]);
        }

        // --- Dokumen ---
        $dok = function (string $no, int $kat, string $judul, int $rev, string $jenis, int $tgl, string $status, int $klienLihat, int $pengunggah, ?string $catatan = null)
            use ($idProyek, $hari, $waktu, $ceo, &$fileBaru, $proyek) {
            $namaProyek = '';
            foreach ($proyek as $p) if ($p[0] === $no) $namaProyek = $p[2];
            if ($jenis === 'png') {
                $isi = contoh_denah($judul, $rev);
                $ext = 'png'; $mime = 'image/png';
            } else {
                $isi = contoh_pdf($judul . ($rev ? ' (Rev. ' . $rev . ')' : ''), [
                    '#Proyek: ' . $namaProyek,
                    'Nomor dokumen: TB/' . $no . '/' . date('m/Y', strtotime($hari($tgl))) . '/' . str_pad((string)random_int(1, 99), 3, '0', STR_PAD_LEFT),
                    'Tanggal: ' . tgl($hari($tgl)),
                    '',
                    '#Ringkasan',
                    'Dokumen ' . strtolower($judul) . ' untuk keperluan arsip proyek.',
                    'Seluruh angka dan nama pada dokumen ini adalah contoh.',
                    '',
                    '#Rincian',
                    '1. Pekerjaan persiapan dan pengukuran',
                    '2. Pekerjaan tanah dan pondasi',
                    '3. Pekerjaan struktur beton',
                    '4. Pekerjaan dinding, plester, dan acian',
                    '5. Pekerjaan atap dan plafon',
                    '6. Pekerjaan finishing dan serah terima',
                ]);
                $ext = 'pdf'; $mime = 'application/pdf';
            }
            $path = contoh_simpan_file('dokumen', $ext, $isi);
            $fileBaru[] = $path;
            $namaFile = preg_replace('/[^A-Za-z0-9]+/', '_', $judul) . ($rev ? '_Rev' . $rev : '') . '.' . $ext;
            q('INSERT INTO dokumen (proyek_id, kategori_id, judul, revisi, nama_file, path_file, tipe_file, ukuran, tanggal_dokumen, tampil_ke_klien,
                 status_persetujuan, catatan_persetujuan, disetujui_oleh, disetujui_pada, diupload_oleh, dibuat_pada) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $idProyek[$no], $kat, $judul, $rev, $namaFile, $path, $mime, strlen($isi), $hari($tgl), $klienLihat, $status, $catatan,
                in_array($status, ['disetujui', 'ditolak'], true) ? $ceo : null,
                in_array($status, ['disetujui', 'ditolak'], true) ? $waktu($tgl + 1, '10:30:00') : null,
                $pengunggah, $waktu($tgl, '14:20:00'),
            ]);
        };
        $adm = (int)$admin['id'];
        // Proyek 001
        $dok('001', 1, 'Denah Lantai 1', 0, 'png', -100, 'disetujui', 1, $ars);
        $dok('001', 1, 'Denah Lantai 2', 1, 'png', -92, 'disetujui', 1, $ars);
        $dok('001', 1, 'Tampak Depan & Potongan', 0, 'pdf', -90, 'disetujui', 1, $ars);
        $dok('001', 2, 'RAB Pembangunan Rumah', 2, 'pdf', -97, 'disetujui', 1, $adm);
        $dok('001', 3, 'Kontrak Kerja Pelaksanaan', 0, 'pdf', -95, 'disetujui', 1, $adm);
        $dok('001', 4, 'Invoice Termin 1 (30%)', 0, 'pdf', -80, 'disetujui', 1, $adm);
        $dok('001', 4, 'Invoice Termin 2 (30%)', 0, 'pdf', -5, 'menunggu', 1, $adm);
        $dok('001', 5, 'Laporan Pengawasan Minggu 10', 0, 'pdf', -6, 'menunggu', 1, $pgw);
        $dok('001', 5, 'Laporan Pengawasan Minggu 9', 0, 'pdf', -13, 'disetujui', 1, $pgw);
        $dok('001', 1, 'Detail Tangga', 0, 'png', -3, 'ditolak', 0, $ars, 'Ukuran anak tangga belum sesuai standar, mohon revisi tinggi optrede maks. 18 cm.');
        // Proyek 002
        $dok('002', 1, 'Denah Renovasi Dapur', 0, 'png', -45, 'disetujui', 1, $ars);
        $dok('002', 2, 'RAB Renovasi', 1, 'pdf', -42, 'disetujui', 1, $adm);
        $dok('002', 3, 'Kontrak Renovasi', 0, 'pdf', -41, 'disetujui', 1, $adm);
        $dok('002', 5, 'Laporan Pengawasan Minggu 5', 0, 'pdf', -2, 'menunggu', 1, $pgw);
        $dok('002', 6, 'Daftar Pilihan Keramik', 0, 'pdf', -10, 'draft', 0, $mdr);
        // Proyek 003
        $dok('003', 3, 'Kontrak Jasa Pengawasan', 0, 'pdf', -62, 'disetujui', 1, $adm);
        $dok('003', 5, 'Laporan Pengawasan Bulan 1', 0, 'pdf', -30, 'disetujui', 1, $pgw);
        $dok('003', 5, 'Laporan Pengawasan Bulan 2', 0, 'pdf', -1, 'menunggu', 1, $pgw);
        // Proyek 004 (selesai)
        $dok('004', 1, 'Gambar Kerja Gudang', 2, 'pdf', -215, 'disetujui', 1, $ars);
        $dok('004', 2, 'RAB Gudang', 0, 'pdf', -212, 'disetujui', 1, $adm);
        $dok('004', 3, 'Kontrak Pelaksanaan Gudang', 0, 'pdf', -210, 'disetujui', 1, $adm);
        $dok('004', 4, 'Invoice Pelunasan', 0, 'pdf', -32, 'disetujui', 1, $adm);
        $dok('004', 6, 'Berita Acara Serah Terima', 0, 'pdf', -30, 'disetujui', 1, $adm);
        // Proyek 005 (penawaran)
        $dok('005', 1, 'Konsep Desain Gedung Kelas', 0, 'png', -8, 'menunggu', 1, $ars);
        $dok('005', 2, 'Penawaran Biaya Perencanaan', 0, 'pdf', -18, 'disetujui', 1, $adm);
        // Proyek 006 (tertunda)
        $dok('006', 2, 'RAB Pagar & Carport', 0, 'pdf', -16, 'disetujui', 1, $adm);

        // --- Laporan progres + foto ---
        $lapor = function (string $no, int $tgl, int $persen, string $catatan, ?string $kendala, int $oleh, int $jmlFoto) use ($idProyek, $hari, $waktu, &$fileBaru) {
            q('INSERT INTO progres (proyek_id, tanggal, persentase, catatan, kendala, dibuat_oleh, dibuat_pada) VALUES (?,?,?,?,?,?,?)',
                [$idProyek[$no], $hari($tgl), $persen, $catatan, $kendala, $oleh, $waktu($tgl, '16:45:00')]);
            $prid = (int)db()->lastInsertId();
            for ($i = 0; $i < $jmlFoto; $i++) {
                $path = contoh_simpan_file('foto', 'jpg', contoh_foto(strtoupper(tgl($hari($tgl))) . '  -  FOTO ' . ($i + 1), $persen, $i + $persen % 5));
                $fileBaru[] = $path;
                q('INSERT INTO progres_foto (progres_id, path_file) VALUES (?,?)', [$prid, $path]);
            }
        };
        $lapor('001', -88, 8, 'Pembersihan lahan dan pengukuran bowplank selesai.', null, $mdr, 2);
        $lapor('001', -74, 18, 'Galian dan pondasi batu kali selesai 100%.', 'Hujan 2 hari, galian sempat tergenang.', $mdr, 2);
        $lapor('001', -52, 33, 'Sloof dan kolom lantai 1 selesai dicor.', null, $mdr, 2);
        $lapor('001', -30, 50, 'Pengecoran plat lantai 2 selesai, mulai pasang bata lantai 2.', null, $pgw, 3);
        $lapor('001', -9, 62, 'Dinding lantai 2 terpasang, kuda-kuda baja ringan mulai dirakit.', 'Semen sempat langka di toko, sudah teratasi.', $mdr, 2);
        $lapor('001', -2, 68, 'Rangka atap selesai, pemasangan genteng 40%.', null, $mdr, 2);
        $lapor('002', -35, 12, 'Pembongkaran atap lama dan dapur selesai.', null, $mdr, 2);
        $lapor('002', -18, 28, 'Perluasan dinding dapur selesai, rangka atap baru terpasang.', null, $mdr, 2);
        $lapor('002', -4, 42, 'Atap seng baru terpasang, mulai plester dinding dapur.', 'Menunggu pilihan keramik dari klien.', $pgw, 2);
        $lapor('003', -45, 15, 'Pengawasan pengecoran pondasi footplat. Mutu beton sesuai (K-250).', null, $pgw, 2);
        $lapor('003', -10, 31, 'Kolom lantai 1 selesai. Ditemukan selimut beton kurang di 2 kolom, sudah diperbaiki.', 'Kontraktor perlu menambah tenaga besi.', $pgw, 2);
        $lapor('004', -150, 35, 'Pondasi dan sloof gudang selesai.', null, $mdr, 2);
        $lapor('004', -80, 75, 'Rangka baja dan atap terpasang.', null, $mdr, 2);
        $lapor('004', -32, 100, 'Pekerjaan selesai, serah terima kepada pemilik.', null, $pgw, 3);
        $lapor('006', -12, 20, 'Pondasi pagar selesai, pekerjaan berhenti menunggu material.', 'Besi hollow belum tiba dari Sorong.', $mdr, 1);

        // --- Riwayat aktivitas contoh ---
        $log = fn(int $uid, string $aksi, string $objek, string $ket, int $n, string $jam) =>
            q('INSERT INTO log_aktivitas (user_id, aksi, objek, keterangan, waktu) VALUES (?,?,?,?,?)', [$uid, $aksi, $objek, '[contoh] ' . $ket, $waktu($n, $jam)]);
        $log($mdr, 'lapor', 'progres', 'Rumah Tinggal 2 Lantai Bpk Markus — 68%, 2 foto', -2, '16:45:00');
        $log($pgw, 'upload', 'dokumen', 'Laporan Pengawasan Bulan 2 — Ruko 3 Pintu CV Teluk Berau', -1, '14:20:00');
        $log($ceo, 'tolak', 'dokumen', 'Detail Tangga — ukuran anak tangga belum sesuai', -2, '10:30:00');
        $log($adm, 'upload', 'dokumen', 'Invoice Termin 2 (30%) — Rumah Tinggal 2 Lantai Bpk Markus', -5, '14:20:00');
        $log($ceo, 'setujui', 'dokumen', 'Laporan Pengawasan Minggu 9', -12, '10:30:00');

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        foreach ($fileBaru as $f) @unlink(UPLOAD_DIR . '/' . $f);
        throw $ex;
    }
    return $akun;
}

/** Menghapus semua data contoh beserta filenya. Mengembalikan ringkasan jumlah. */
function contoh_hapus(): array
{
    $proyek = q('SELECT id FROM proyek WHERE kode_proyek LIKE ?', [CONTOH_KODE . '%'])->fetchAll(PDO::FETCH_COLUMN);
    $file = [];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($proyek as $id) {
            $file = array_merge($file,
                q('SELECT path_file FROM dokumen WHERE proyek_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN),
                q('SELECT f.path_file FROM progres_foto f JOIN progres pr ON pr.id = f.progres_id WHERE pr.proyek_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN));
            q('DELETE FROM dokumen WHERE proyek_id = ?', [$id]);
            q('DELETE FROM progres_foto WHERE progres_id IN (SELECT id FROM progres WHERE proyek_id = ?)', [$id]);
            q('DELETE FROM progres WHERE proyek_id = ?', [$id]);
            q('DELETE FROM proyek_tim WHERE proyek_id = ?', [$id]);
            q('DELETE FROM proyek WHERE id = ?', [$id]);
        }
        $klien = q("DELETE FROM klien WHERE catatan LIKE '[CONTOH]%' AND id NOT IN (SELECT klien_id FROM proyek)")->rowCount();
        q("DELETE FROM log_aktivitas WHERE keterangan LIKE '[contoh]%'");
        $user = q('DELETE FROM users WHERE email LIKE ?', ['%' . CONTOH_DOMAIN])->rowCount();
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    foreach ($file as $f) if (file_ada($f)) @unlink(UPLOAD_DIR . '/' . $f);
    return ['proyek' => count($proyek), 'klien' => $klien, 'user' => $user, 'file' => count($file)];
}
