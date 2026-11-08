# Aplikasi Android — Arsip TB

Aplikasi Android ini membuka **https://kerjafakfak.com** dalam layar penuh (Trusted Web Activity).
Isi aplikasi selalu sama dengan situs. Jika situs diperbarui, aplikasi di HP ikut berubah tanpa perlu dipasang ulang.

Nama paket: `com.tukangbangunan.arsip`

## Langkah pertama (sekali saja)

1. **Buat repository di GitHub**, jenis **Private**. Upload seluruh isi folder `arsip-tb`.
   File `config/produksi.php` dan isi `uploads/` sudah otomatis diabaikan oleh `.gitignore`.
2. **Buat kunci tanda tangan**: buka tab **Actions**, pilih **Buat kunci Android**, lalu klik **Run workflow**.
   Setelah selesai (hijau), buka hasilnya dan unduh artifact **kunci-android**.
3. Buka file `PETUNJUK.txt` dari artifact tersebut. Isi 4 secret di
   **Settings → Secrets and variables → Actions → New repository secret**:
   - `KEYSTORE_BASE64`
   - `KEYSTORE_PASSWORD`
   - `KEY_ALIAS`
   - `KEY_PASSWORD`

   Simpan file `arsiptb.jks` dan password-nya di tempat aman. Tanpa kunci yang sama,
   aplikasi tidak bisa diperbarui di HP maupun di Play Store.
4. **Bangun aplikasi**: buka tab **Actions**, pilih **Bangun aplikasi Android**, lalu klik **Run workflow**.
   Proses ini memakan waktu sekitar 5 menit.
5. Unduh hasilnya dari tab **Releases** (atau dari artifact **ArsipTB-android**):
   - `ArsipTB-v1.0.X.apk`: pasang langsung di HP Android.
   - `ArsipTB-v1.0.X.aab`: untuk diupload ke Google Play Console.
   - `assetlinks.json`: upload ke server (lihat langkah berikut).

## Menghilangkan bilah alamat browser

Upload `assetlinks.json` ke Hostinger di lokasi berikut:

```
public_html/.well-known/assetlinks.json
```

Buat folder `.well-known` jika belum ada. Cek hasilnya dengan membuka
https://kerjafakfak.com/.well-known/assetlinks.json. Isinya harus tampil.

Setelah itu tutup aplikasi di HP, lalu buka lagi. Bilah alamat di atas akan hilang.

## Memasang APK di HP

1. Buka halaman Releases di HP, lalu unduh file `.apk`.
2. Buka file tersebut. Jika muncul peringatan, izinkan **Instal dari sumber ini**.
3. Ikon **Arsip TB** muncul di layar utama HP.

HP harus memiliki Google Chrome versi terbaru.

## Versi baru

Setiap kali menjalankan **Bangun aplikasi Android**, nomor versi naik otomatis (v1.0.1, v1.0.2, dst.).
Aplikasi hanya perlu dibangun ulang jika ikon, nama, atau domain berubah.
Perubahan tampilan dan fitur cukup diupload ke Hostinger.

## Mengganti domain

Ubah `HOST_NAME` di `android/app/build.gradle` dan alamat situs di `android/app/src/main/res/values/strings.xml`.
