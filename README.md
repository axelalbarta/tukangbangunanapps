# Arsip Tukang Bangunan

Aplikasi web pengarsipan proyek untuk jasa arsitek & kontraktor.
PHP 8.1+ (native, PDO) · MySQL/MariaDB · Bootstrap 5 · bisa dipasang di HP sebagai aplikasi (PWA).

## A. Jalankan di XAMPP (localhost)

1. Ekstrak ZIP, lalu salin folder `arsip-tb` ke `C:\xampp\htdocs\`
2. Buka XAMPP Control Panel → **Start** Apache dan MySQL
3. Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `arsip-tb/database/db_arsip.sql` → **Go**
   (database `db_arsip` dibuat otomatis)
4. Buka `http://localhost/arsip-tb`
5. Login dengan salah satu akun contoh (password semua: `tb12345`):

| Role  | Email                       |
|-------|-----------------------------|
| Admin | admin@tukangbangunan.test   |
| CEO   | ceo@tukangbangunan.test     |
| Tim   | tim@tukangbangunan.test     |
| Klien | klien@tukangbangunan.test   |

**Segera ganti password** lewat menu Profil, lalu ganti email akun contoh lewat menu User (admin).

### Khusus XAMPP di Mac (wajib sekali saja)
Apache di Mac berjalan sebagai user lain, jadi folder `uploads` perlu diberi izin tulis.
Buka Terminal, lalu jalankan:
```
sudo chmod -R 777 /Applications/XAMPP/xamppfiles/htdocs/arsip-tb/uploads
```
Masukkan password Mac. Tanpa langkah ini, upload dokumen/foto akan gagal.
File php.ini di Mac ada di `/Applications/XAMPP/xamppfiles/etc/php.ini`.

### Pengaturan php.ini di XAMPP (wajib sekali saja)
Buka XAMPP → Apache → Config → `php.ini`, cari dan ubah:
```
upload_max_filesize = 256M
post_max_size = 260M
max_execution_time = 300
extension=zip          (hapus tanda ; di depannya jika ada)
```
Simpan, lalu Stop dan Start lagi Apache.
Dokumen biasa tetap dibatasi 25 MB oleh aplikasi; angka besar hanya untuk ZIP Import Arsip.

### Memperbarui aplikasi dengan aman
JANGAN mengganti (Replace) seluruh folder `arsip-tb`, karena folder `uploads` berisi semua file dokumen & foto akan ikut terhapus.
- **Mac**: ekstrak ZIP baru ke Downloads, lalu di Terminal jalankan
  `ditto ~/Downloads/arsip-tb /Applications/XAMPP/xamppfiles/htdocs/arsip-tb`
  (menggabungkan file; isi `uploads` tetap aman)
- **Windows**: salin isi folder baru ke folder lama dan pilih "Replace the files in the destination" (Windows menggabungkan folder).
- **Hostinger**: unggah ZIP ke `public_html` lalu Extract; jangan hapus folder `uploads`.

### Sudah memasang versi sebelumnya?
Jangan impor ulang `db_arsip.sql` (data akan terhapus). Cukup timpa file aplikasi
kecuali `config/database.php` dan folder `uploads/`. Struktur database diperbarui otomatis
saat login berikutnya. Jika gagal, jalankan `database/update_v2.sql` lewat phpMyAdmin.

## B. Pindah ke Hostinger

1. hPanel → **Databases → MySQL Databases** → buat database + user + password
2. hPanel → phpMyAdmin database tersebut → **Import** `db_arsip.sql`
   (hapus dulu baris `CREATE DATABASE ...` dan `USE db_arsip;` di bagian atas file)
3. Upload semua isi folder `arsip-tb` ke `public_html` (File Manager → Upload ZIP → Extract)
4. Edit `config/database.php`: isi `DB_NAME`, `DB_USER`, `DB_PASS` dari langkah 1 (host tetap `localhost`)
5. Edit `config/app.php`: ubah `APP_DEBUG` menjadi `false`
6. Aktifkan SSL di hPanel, lalu di `.htaccess` hapus tanda `#` pada 3 baris "Paksa HTTPS"
7. Uji semua role dari HP: login, upload dokumen, lapor progres dengan foto

File `.user.ini` sudah mengatur batas upload di Hostinger (dokumen 25 MB, ZIP impor hingga 256 MB; batas paket hosting bisa lebih kecil).

## C. Import arsip lama (menu Import Arsip, khusus admin)
1. Rapikan arsip di komputer dengan susunan:
```
Arsip Lama/
├── Nama Proyek - Nama Klien/
│   ├── Gambar/   ├── RAB/   ├── Kontrak/
│   ├── Invoice/  ├── Laporan/  └── Foto/
└── Proyek Lain - Klien Lain/ ...
```
2. Klik kanan folder `Arsip Lama` → Send to → Compressed (zipped) folder
3. Menu **Import Arsip** → unggah ZIP → periksa pratinjau → **Proses impor**
   (ZIP sangat besar: salin ke folder `uploads/import` dulu, lalu pilih "Cara 2")
4. CEO membuka **Persetujuan** → filter "Hasil impor arsip lama" → **Pilih semua** → **Setujui terpilih**

Klien & proyek dibuat otomatis, tanggal dokumen diambil dari tanggal file, "Rev2" di nama file
dibaca sebagai revisi, isi folder Foto menjadi foto progres, dan file yang sama tidak masuk dua kali
(impor boleh diulang dengan aman).

## D. Pasang di HP (PWA)
Buka alamat aplikasi di Chrome HP → menu ⋮ → **Tambahkan ke layar utama**.
Aplikasi tampil dengan ikon sendiri dan layar penuh seperti aplikasi biasa.
(PWA butuh HTTPS, jadi lakukan setelah di Hostinger.)

## Aturan akses
- **CEO**: lihat semua proyek dan dokumen, **satu-satunya yang menyetujui/menolak dokumen**
- **Admin**: kelola user, klien, proyek, tim, semua dokumen; menampilkan/menyembunyikan dokumen ke klien; menghapus
- **Tim internal**: hanya proyek yang ditugaskan; upload dokumen & lapor progres; tidak bisa melihat Kontrak & Invoice; tidak bisa menghapus
- **Klien**: hanya proyeknya sendiri; melihat progres & foto; melihat dokumen **hanya jika sudah disetujui CEO dan ditandai "untuk klien"**

## Struktur
```
config/        pengaturan aplikasi & database
includes/      fungsi bersama (login, akses, tampilan)
database/      file SQL
uploads/       file dokumen & foto (terkunci, hanya lewat download.php / foto.php)
assets/        CSS, JS, ikon
*.php          halaman aplikasi
```

## Cadangan (backup)
Minimal seminggu sekali: ekspor database dari phpMyAdmin dan unduh folder `uploads/`.

## Aplikasi Android (GitHub)

Folder `android/` dan `.github/workflows/` dipakai untuk membuat aplikasi Android (APK/AAB) otomatis di GitHub Actions.
Panduan lengkap: `android/README.md`. Kedua folder ini tidak perlu diupload ke Hostinger.

## Keamanan

- **Verifikasi 2 langkah wajib untuk CEO & Admin** (atur di `WAJIB_2FA`, config/app.php). Role lain bisa mengaktifkannya sendiri di menu **Keamanan akun**.
- HP CEO/tim hilang: Admin membuka **User → ubah user → centang "Reset verifikasi 2 langkah"**.
- Admin sendiri kehilangan HP dan kode cadangan: buka phpMyAdmin di hPanel, lalu jalankan
  `UPDATE users SET totp_aktif=0, totp_rahasia=NULL, totp_cadangan=NULL WHERE email='email-admin@...';`
- Rahasia 2FA dienkripsi memakai `APP_KEY` di produksi.php, atau dari password database bila `APP_KEY` kosong.
  Jika password database diganti, semua pengguna harus mengaktifkan ulang verifikasi 2 langkah.
- Sesi berakhir setelah 2 jam tidak aktif dan maksimal 12 jam. Mengganti password akan mengeluarkan akun dari semua perangkat lain.

## Library tanpa CDN (disarankan)

Secara bawaan Bootstrap, ikon, peta, dan font dimuat dari CDN. Agar semuanya dimuat dari server sendiri (lebih aman dan tidak bergantung pihak luar):
- **Mac:** di Terminal, masuk ke folder aplikasi lalu jalankan `bash alat/ambil-vendor.sh`. Upload `tb-vendor.zip` yang dihasilkan ke `public_html`, lalu Extract.
- **GitHub:** buka tab Actions → **Ambil library ke server sendiri** → Run workflow. Unduh artifact `tb-vendor`, upload ke `public_html`, lalu Extract.

Aplikasi otomatis memakai file lokal begitu tersedia, dan aturan keamanan (CSP) langsung menutup akses ke CDN.

## Alamat rapi & halaman tersembunyi

- Alamat tampil tanpa `.php` (mis. `/proyek`, `/users`). Alamat lama `.php` otomatis dialihkan.
- Orang yang belum login, atau tidak berhak, akan melihat halaman **404 Tidak ditemukan** yang sama persis dengan alamat yang memang tidak ada.
- Fitur ini butuh `mod_rewrite` (Hostinger: aktif). Di XAMPP biasanya sudah aktif. Jika link tidak bisa dibuka di XAMPP,
  aktifkan `LoadModule rewrite_module` dan `AllowOverride All` di httpd.conf.
