<?php
// Definisi menu aplikasi (gaya mobile banking): dipakai tab bawah dan halaman Semua menu.

/** Kelompok menu untuk halaman Semua menu, sudah disaring sesuai role */
function menu_grup(array $u): array
{
    $r = $u['role'];
    $semua = ['ceo', 'admin', 'tim', 'klien'];
    $grup = [
        'Utama' => [
            ['dashboard', 'dashboard', 'bi-house-door', 'Beranda', $semua],
            ['proyek', 'proyek', 'bi-buildings', $r === 'klien' ? 'Proyek saya' : 'Proyek', $semua],
            ['persetujuan', 'persetujuan', 'bi-patch-check', 'Persetujuan', ['ceo', 'admin']],
            ['cari', 'cari', 'bi-search', 'Cari dokumen', ['ceo', 'admin', 'tim']],
        ],
        'Lapangan' => [
            ['progres', 'progres_form', 'bi-camera', 'Lapor progres', ['admin', 'tim']],
            ['upload', 'dokumen_upload', 'bi-cloud-arrow-up', 'Unggah dokumen', ['admin', 'tim']],
        ],
        'Data & arsip' => [
            ['klien', 'klien', 'bi-person-vcard', 'Klien', ['admin']],
            ['import', 'import', 'bi-box-arrow-in-down', 'Import arsip', ['admin']],
            ['contoh', 'contoh', 'bi-magic', 'Data contoh', ['admin']],
        ],
        'Pengaturan' => [
            ['users', 'users', 'bi-people', 'User', ['admin']],
            ['log', 'log', 'bi-clock-history', 'Log aktivitas', ['ceo', 'admin']],
            ['tur', 'dashboard?tur=1', 'bi-signpost-split', 'Tur aplikasi', $semua],
            ['profil', 'profil', 'bi-person-circle', 'Profil', $semua],
            ['keamanan', 'keamanan', 'bi-shield-lock', 'Keamanan akun', $semua],
        ],
    ];
    $hasil = [];
    foreach ($grup as $nama => $item) {
        $item = array_values(array_filter($item, fn($i) => in_array($r, $i[4], true)));
        if ($item) $hasil[$nama] = $item;
    }
    return $hasil;
}

/**
 * Tab bawah: Beranda · Proyek · [tombol tengah sesuai role] · Cari · Menu.
 * Setiap item: [kunci, href, ikon, label, menonjol?]
 */
function menu_tab(array $u): array
{
    $tab = [['dashboard', 'dashboard', 'bi-house-door', 'Beranda', false],
            ['proyek', 'proyek', 'bi-buildings', 'Proyek', false]];
    $tengah = match ($u['role']) {
        'admin' => ['upload', 'dokumen_upload', 'bi-cloud-arrow-up', 'Unggah', true],
        'ceo'   => ['persetujuan', 'persetujuan', 'bi-patch-check', 'Setujui', true],
        'tim'   => ['progres', 'progres_form', 'bi-camera', 'Lapor', true],
        default => null,
    };
    if ($tengah) $tab[] = $tengah;
    $tab[] = $u['role'] === 'klien'
        ? ['profil', 'profil', 'bi-person-circle', 'Profil', false]
        : ['cari', 'cari', 'bi-search', 'Cari', false];
    $tab[] = ['semua', 'semua_menu', 'bi-grid', 'Menu', false];
    return $tab;
}
