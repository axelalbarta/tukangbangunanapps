-- Update v3: titik peta lokasi proyek
-- Biasanya TIDAK perlu dijalankan manual: aplikasi menambahkan kolom ini otomatis saat login.
-- Jalankan hanya jika muncul pesan "Pembaruan database otomatis gagal".
ALTER TABLE proyek
  ADD COLUMN latitude DECIMAL(10,7) NULL AFTER lokasi,
  ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;
