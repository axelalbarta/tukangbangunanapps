<?php
// Pengolahan file foto & dokumen yang dipakai bersama

/** Simpan foto; perkecil ke maks 1600 px jika GD tersedia */
function simpan_foto(string $tmp, string $dest): bool
{
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return false;
    [$w, $h] = $info;
    if ($w < 1 || $h < 1 || $w * $h > 50000000) return false; // tolak gambar raksasa (bisa menghabiskan memori server)
    $max = 1600;
    if (function_exists('imagecreatetruecolor') && ($w > $max || $h > $max || filesize($tmp) > 1048576)) {
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        };
        if ($src) {
            $r = min(1, $max / max($w, $h));
            $nw = (int)round($w * $r);
            $nh = (int)round($h * $r);
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ok = @imagejpeg($dst, $dest, 80);
            imagedestroy($src);
            imagedestroy($dst);
            return $ok;
        }
    }
    return (is_uploaded_file($tmp) && @move_uploaded_file($tmp, $dest)) || @copy($tmp, $dest);
}

/**
 * Pastikan isi file benar-benar sesuai ekstensinya (bukan skrip/HTML yang diganti namanya).
 * Mengembalikan pesan kesalahan, atau null jika file aman.
 */
function cek_isi_dokumen(string $path, string $ext): ?string
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    $office = ['application/zip', 'application/octet-stream', 'application/x-zip-compressed'];
    $ole = ['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'];
    $boleh = match ($ext) {
        'pdf'         => ['application/pdf'],
        'jpg', 'jpeg' => ['image/jpeg'],
        'png'         => ['image/png'],
        'docx'        => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $office),
        'xlsx'        => array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $office),
        'doc'         => $ole,
        'xls'         => $ole,
        'dwg'         => ['image/vnd.dwg', 'image/x-dwg', 'application/acad', 'application/x-acad', 'application/autocad_dwg', 'application/dwg', 'application/x-dwg', 'application/octet-stream'],
        default       => [],
    };
    if (!in_array($mime, $boleh, true)) {
        return $ext === 'pdf' ? 'File PDF tidak valid.' : 'Isi file tidak sesuai dengan formatnya (.' . $ext . ').';
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        $info = @getimagesize($path);
        $tipe = $ext === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
        if (!$info || $info[2] !== $tipe) return 'File gambar rusak atau tidak valid.';
    }
    // Tolak file yang menyisipkan kode PHP/skrip di awal isi (mis. gambar "polyglot")
    $awal = (string)@file_get_contents($path, false, null, 0, 65536);
    if (preg_match('/<\?php|<script\b/i', $awal)) return 'Isi file tidak valid.';
    return null;
}
