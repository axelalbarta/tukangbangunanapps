-- =========================================================
-- Update v2: fitur Import Arsip
-- Jalankan HANYA jika database db_arsip sudah dibuat dengan versi sebelumnya
-- dan sudah berisi data. (Instalasi baru cukup impor db_arsip.sql.)
-- phpMyAdmin -> pilih database db_arsip -> tab SQL / Import -> file ini
-- =========================================================
ALTER TABLE dokumen
  ADD COLUMN tanggal_dokumen DATE NULL AFTER ukuran,
  ADD COLUMN sumber ENUM('upload','impor') NOT NULL DEFAULT 'upload' AFTER catatan_persetujuan;
