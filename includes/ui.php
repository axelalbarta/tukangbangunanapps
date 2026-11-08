<?php
// Komponen tampilan yang dipakai di beberapa halaman

function progress_bar(int $persen): string
{
    $persen = max(0, min(100, $persen));
    return '<div class="bar' . ($persen >= 100 ? ' selesai' : '') . '" role="progressbar" aria-valuenow="' . $persen . '" aria-valuemin="0" aria-valuemax="100" aria-label="Progres ' . $persen . '%"><span style="width:' . $persen . '%"></span></div>';
}

function proyek_card(array $p, array $u): string
{
    $nilai = in_array($u['role'], ['ceo', 'admin', 'klien'], true)
        ? '<span class="sub">' . e(rupiah($p['nilai_kontrak'])) . '</span>' : '';
    return '<a href="proyek_detail?id=' . (int)$p['id'] . '" class="kartu-proyek">'
        . '<div class="d-flex justify-content-between align-items-start gap-2">'
        . '<span class="kode">' . e($p['kode_proyek']) . ' · ' . e(LAYANAN[$p['jenis_layanan']] ?? '') . '</span>'
        . badge(STATUS_PROYEK, $p['status']) . '</div>'
        . '<div class="nama">' . e($p['nama_proyek']) . '</div>'
        . '<div class="sub mb-2">' . e($p['nama_klien'] ?? '') . ($p['lokasi'] ? ' · ' . e($p['lokasi']) : '') . '</div>'
        . progress_bar((int)$p['progres_persen'])
        . '<div class="d-flex justify-content-between mt-1"><b class="small">' . (int)$p['progres_persen'] . '%</b>' . $nilai . '</div>'
        . '</a>';
}

function ikon_file(string $namaFile): string
{
    $ext = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => 'bi-file-earmark-pdf text-danger',
        'jpg', 'jpeg', 'png' => 'bi-file-earmark-image text-primary',
        'xls', 'xlsx' => 'bi-file-earmark-spreadsheet text-success',
        'doc', 'docx' => 'bi-file-earmark-word text-primary',
        'dwg' => 'bi-file-earmark-ruled text-warning',
        default => 'bi-file-earmark',
    };
}

function bisa_preview(string $namaFile): bool
{
    return in_array(strtolower(pathinfo($namaFile, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png'], true);
}

/** Atribut agar gambar dibuka di penampil dalam aplikasi (tidak membuka tab baru) */
function atribut_lihat(?string $namaFile, string $judul = ''): string
{
    $ext = strtolower(pathinfo((string)$namaFile, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png'], true) ? ' data-lihat="gambar" data-judul="' . e($judul) . '"' : '';
}

function empty_state(string $icon, string $text): string
{
    return '<div class="text-center text-muted py-5"><i class="bi ' . $icon . ' display-6 d-block mb-2"></i>' . e($text) . '</div>';
}

/** Skrip penentu animasi pembuka (taruh di <head>): aktif hanya saat aplikasi terpasang baru dibuka */
function intro_kepala(): string
{
    return '<script nonce="' . csp_nonce() . '">(function(){try{var m=window.matchMedia;'
        . 'var app=(m&&m("(display-mode: standalone)").matches)||navigator.standalone===true||document.referrer.indexOf("android-app://")===0||/[?&]intro=1/.test(location.search);'
        . 'if(app){try{sessionStorage.setItem("tb_app","1")}catch(e){}}if(app&&!sessionStorage.getItem("tb_intro")&&!(m&&m("(prefers-reduced-motion: reduce)").matches))document.documentElement.classList.add("intro-aktif")}catch(e){}})();</script>';
}

/** Lapisan animasi pembuka (taruh tepat setelah <body>) */
function intro_lapisan(): string
{
    return '<div class="intro" aria-hidden="true"><img data-src="' . e(aset('assets/img/intro.svg')) . '" alt="" width="240" height="215"></div>'
        . '<script nonce="' . csp_nonce() . '" src="' . e(aset('assets/js/intro.js')) . '" defer></script>';
}
