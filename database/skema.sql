-- =========================================================
-- Arsip Tukang Bangunan - skema database PRODUKSI (tanpa data contoh)
-- Dipakai otomatis oleh setup.php. Bisa juga diimpor manual lewat phpMyAdmin.
-- =========================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ceo','admin','tim','klien') NOT NULL,
  jabatan VARCHAR(50) NULL,
  no_hp VARCHAR(30) NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  login_terakhir DATETIME NULL,
  totp_rahasia VARCHAR(255) NULL,
  totp_aktif TINYINT(1) NOT NULL DEFAULT 0,
  totp_cadangan TEXT NULL,
  totp_langkah BIGINT NULL,
  sesi_kunci VARCHAR(64) NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS klien (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  alamat TEXT NULL,
  telepon VARCHAR(30) NULL,
  catatan TEXT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_klien_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proyek (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode_proyek VARCHAR(30) NOT NULL UNIQUE,
  klien_id INT UNSIGNED NOT NULL,
  nama_proyek VARCHAR(200) NOT NULL,
  lokasi VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  jenis_layanan ENUM('perencanaan','pengawasan','pelaksanaan') NOT NULL,
  nilai_kontrak DECIMAL(15,2) NOT NULL DEFAULT 0,
  tgl_mulai DATE NULL,
  tgl_target_selesai DATE NULL,
  status ENUM('penawaran','berjalan','tertunda','selesai') NOT NULL DEFAULT 'penawaran',
  progres_persen TINYINT UNSIGNED NOT NULL DEFAULT 0,
  keterangan TEXT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_proyek_klien FOREIGN KEY (klien_id) REFERENCES klien(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proyek_tim (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proyek_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  peran ENUM('arsitek','pengawas','mandor','lainnya') NOT NULL DEFAULT 'lainnya',
  UNIQUE KEY uq_proyek_user (proyek_id, user_id),
  CONSTRAINT fk_pt_proyek FOREIGN KEY (proyek_id) REFERENCES proyek(id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kategori_dokumen (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(50) NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dokumen (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proyek_id INT UNSIGNED NOT NULL,
  kategori_id INT UNSIGNED NOT NULL,
  judul VARCHAR(200) NOT NULL,
  revisi SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  nama_file VARCHAR(255) NOT NULL,
  path_file VARCHAR(255) NOT NULL,
  tipe_file VARCHAR(100) NULL,
  ukuran INT UNSIGNED NOT NULL DEFAULT 0,
  tanggal_dokumen DATE NULL,
  tampil_ke_klien TINYINT(1) NOT NULL DEFAULT 0,
  status_persetujuan ENUM('draft','menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  catatan_persetujuan VARCHAR(255) NULL,
  sumber ENUM('upload','impor') NOT NULL DEFAULT 'upload',
  disetujui_oleh INT UNSIGNED NULL,
  disetujui_pada DATETIME NULL,
  diupload_oleh INT UNSIGNED NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dok_proyek (proyek_id),
  KEY idx_dok_status (status_persetujuan),
  CONSTRAINT fk_dok_proyek FOREIGN KEY (proyek_id) REFERENCES proyek(id) ON DELETE RESTRICT,
  CONSTRAINT fk_dok_kategori FOREIGN KEY (kategori_id) REFERENCES kategori_dokumen(id),
  CONSTRAINT fk_dok_setuju FOREIGN KEY (disetujui_oleh) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_dok_upload FOREIGN KEY (diupload_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progres (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proyek_id INT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  persentase TINYINT UNSIGNED NOT NULL DEFAULT 0,
  catatan TEXT NULL,
  kendala TEXT NULL,
  dibuat_oleh INT UNSIGNED NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_progres_proyek (proyek_id, tanggal),
  CONSTRAINT fk_progres_proyek FOREIGN KEY (proyek_id) REFERENCES proyek(id) ON DELETE CASCADE,
  CONSTRAINT fk_progres_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progres_foto (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  progres_id INT UNSIGNED NOT NULL,
  path_file VARCHAR(255) NOT NULL,
  keterangan VARCHAR(255) NULL,
  CONSTRAINT fk_foto_progres FOREIGN KEY (progres_id) REFERENCES progres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_aktivitas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  aksi VARCHAR(50) NOT NULL,
  objek VARCHAR(50) NOT NULL,
  objek_id INT UNSIGNED NULL,
  keterangan VARCHAR(255) NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log_waktu (waktu),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_percobaan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(150) NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lp_ip (ip, waktu),
  KEY idx_lp_email (email, waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kategori dokumen
INSERT IGNORE INTO kategori_dokumen (id, nama, urutan) VALUES
(1, 'Gambar', 1),
(2, 'RAB', 2),
(3, 'Kontrak', 3),
(4, 'Invoice', 4),
(5, 'Laporan Pengawasan', 5),
(6, 'Lainnya', 6);

CREATE TABLE IF NOT EXISTS perangkat_dipercaya (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL UNIQUE,
  validator_hash CHAR(64) NOT NULL,
  cap CHAR(64) NOT NULL,
  nama VARCHAR(120) NULL,
  dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  kedaluwarsa DATETIME NOT NULL,
  KEY idx_pd_user (user_id),
  CONSTRAINT fk_pd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_langganan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  endpoint TEXT NOT NULL,
  endpoint_hash CHAR(64) NOT NULL UNIQUE,
  p256dh VARCHAR(120) NOT NULL,
  auth VARCHAR(40) NOT NULL,
  nama VARCHAR(120) NULL,
  dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_push_user (user_id),
  CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pengaturan (
  kunci VARCHAR(50) NOT NULL PRIMARY KEY,
  nilai TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
