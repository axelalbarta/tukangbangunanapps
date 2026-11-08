<?php
// Isi tur panduan per role. Setiap langkah:
//   't' => daftar target [data-tour] (dipakai yang pertama terlihat; kosong = di tengah layar)
//   'j' => judul, 'i' => isi penjelasan

function langkah_tur(array $u): array
{
    $nama = explode(' ', $u['nama'])[0];
    $mulai = ['t' => [], 'j' => "Selamat datang, $nama!",
        'i' => 'Ini Sistem Arsip Proyek Tukang Bangunan. Tur singkat ini menunjukkan bagian-bagian penting untuk Anda. Tekan Lanjut, atau Lewati jika sudah paham.'];

    $langkah = match ($u['role']) {
        'ceo' => [
            $mulai,
            ['t' => ['statistik'], 'j' => 'Ringkasan perusahaan',
             'i' => 'Jumlah proyek per status dan total nilai kontrak yang sedang berjalan. Ketuk angkanya untuk melihat daftar proyeknya.'],
            ['t' => ['tab-tengah', 'atas-utama', 'aksi-setujui'], 'j' => 'Tombol Setujui',
             'i' => 'Tugas utama Anda. Semua dokumen wajib Anda setujui sebelum bisa dilihat klien. Angka hitam menunjukkan jumlah yang menunggu. Centang "Pilih semua" untuk menyetujui banyak dokumen sekaligus.'],
            ['t' => ['daftar-proyek'], 'j' => 'Proyek aktif',
             'i' => 'Ketuk kartu proyek untuk melihat dokumen, foto progres, tim, dan lokasi. Di sana ada tombol Unduh untuk mengambil seluruh berkas proyek sebagai ZIP.'],
            ['t' => ['dokumen'], 'j' => 'Aktivitas terbaru',
             'i' => 'Siapa mengunggah, menyetujui, atau melapor progres, dan kapan.'],
        ],
        'admin' => [
            $mulai,
            ['t' => ['statistik'], 'j' => 'Ringkasan',
             'i' => 'Gambaran cepat semua proyek dan jumlah dokumen yang masih menunggu persetujuan CEO.'],
            ['t' => ['aksi-klien'], 'j' => '1. Data klien',
             'i' => 'Mulai dari sini: tambahkan klien. Isi email dan password jika klien perlu login untuk memantau proyeknya.'],
            ['t' => ['aksi-proyek-baru'], 'j' => '2. Buat proyek',
             'i' => 'Buat proyek untuk klien, isi nilai kontrak, jadwal, dan titik lokasi di peta, lalu tugaskan arsitek, pengawas, atau mandor.'],
            ['t' => ['tab-tengah', 'atas-utama'], 'j' => '3. Tombol Unggah',
             'i' => 'Tombol merah (di tengah bawah pada HP/tablet, kanan atas pada laptop) selalu ada di setiap halaman untuk mengunggah dokumen dengan cepat. Dokumen diajukan ke CEO untuk disetujui.'],
            ['t' => ['aksi-import'], 'j' => 'Import arsip lama',
             'i' => 'Masukkan arsip lama sekaligus dari satu file ZIP. Klien, proyek, dokumen, dan foto dibuat otomatis.'],
            ['t' => ['tab-semua', 'atas-semua'], 'j' => 'Semua menu',
             'i' => 'Menu lengkap ada di sini: User (akun CEO dan tim), Klien, Log aktivitas, Profil, dan lainnya.'],
        ],
        'tim' => [
            $mulai,
            ['t' => ['tab-tengah', 'atas-utama', 'aksi-lapor'], 'j' => 'Tombol Lapor',
             'i' => 'Dari lokasi proyek: pilih proyek, geser persentase, tulis pekerjaan hari ini, lalu ambil foto langsung dari kamera HP. Foto otomatis diperkecil supaya hemat kuota.'],
            ['t' => ['daftar-proyek'], 'j' => 'Proyek Anda',
             'i' => 'Hanya proyek yang ditugaskan kepada Anda yang tampil di sini. Ketuk kartu untuk membuka dokumen, riwayat progres, dan petunjuk arah ke lokasi.'],
            ['t' => ['aksi-unggah'], 'j' => 'Unggah dokumen',
             'i' => 'Unggah gambar, RAB, atau laporan pengawasan. Dokumen akan diajukan ke CEO untuk disetujui.'],
            ['t' => ['statistik'], 'j' => 'Tugas Anda',
             'i' => 'Jumlah proyek aktif, laporan bulan ini, dan dokumen yang ditolak CEO sehingga perlu direvisi.'],
        ],
        'klien' => [
            ['t' => [], 'j' => "Selamat datang, $nama!",
             'i' => 'Di sini Anda bisa memantau perkembangan pembangunan bersama Tukang Bangunan kapan saja dari HP.'],
            ['t' => ['daftar-proyek'], 'j' => 'Proyek Anda',
             'i' => 'Persentase menunjukkan progres pekerjaan terbaru. Ketuk kartu untuk melihat detail, dokumen, foto, dan lokasi.'],
            ['t' => ['foto'], 'j' => 'Foto progres',
             'i' => 'Foto terbaru dari lokasi proyek yang dikirim langsung oleh tim lapangan. Ketuk foto untuk melihat ukuran penuh.'],
            ['t' => ['dokumen'], 'j' => 'Dokumen untuk Anda',
             'i' => 'Gambar, RAB, kontrak, dan invoice yang sudah disetujui dan dibagikan kepada Anda. Ketuk untuk membuka atau mengunduh.'],
        ],
        default => [$mulai],
    };

    $langkah[] = ['t' => ['tab-semua', 'atas-semua'], 'j' => 'Profil & password',
        'i' => 'Buka Menu lalu Profil untuk mengganti password Anda, terutama pada login pertama.'];
    $langkah[] = ['t' => ['tur'], 'j' => 'Selesai!',
        'i' => 'Tur ini bisa diulang kapan saja lewat tombol tanda tanya di pojok kanan atas Beranda.'];
    return $langkah;
}
