-- ============================================================
-- DATABASE SISTEM PARKIR : PT. PARKIR BOGOR (ENTERPRISE EDITION)
-- Standard: Full Schema for Admin, Operator, and Public Portals
-- ============================================================
DROP DATABASE IF EXISTS db_parkir_bogor;
CREATE DATABASE db_parkir_bogor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_parkir_bogor;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- TABEL: perusahaan
-- ------------------------------------------------------------
CREATE TABLE perusahaan (
  id_perusahaan INT AUTO_INCREMENT PRIMARY KEY,
  nama_perusahaan VARCHAR(100) NOT NULL,
  alamat TEXT,
  telepon VARCHAR(20),
  email VARCHAR(100),
  npwp VARCHAR(30),
  logo VARCHAR(255) DEFAULT NULL,
  jam_operasional VARCHAR(50) DEFAULT '24 Jam',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT INTO perusahaan (nama_perusahaan, alamat, telepon, email, npwp) VALUES
('PT. Parkir Bogor', 'Jl. Pajajaran No. 1, Bogor, Jawa Barat 16143', '0251-123456', 'info@parkirbogor.co.id', '01.234.567.8-901.000');

-- ------------------------------------------------------------
-- TABEL: users
-- ------------------------------------------------------------
CREATE TABLE users (
  id_user INT AUTO_INCREMENT PRIMARY KEY,
  nama_lengkap VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) DEFAULT NULL,
  no_telepon VARCHAR(20) DEFAULT NULL,
  alamat TEXT DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','operator','pelanggan') NOT NULL DEFAULT 'operator',
  shift ENUM('pagi','siang','malam') DEFAULT 'pagi',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Passwords:
-- admin: admin123 ($2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBESc4a5lB8p7j7u)
-- operator: operator123 ($2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBESc4a5lB8p7j7u)
-- pelanggan: pelanggan123 ($2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBESc4a5lB8p7j7u)
INSERT INTO users (nama_lengkap, username, email, no_telepon, alamat, password, role, shift) VALUES
('Administrator Utama', 'admin', 'admin@parkirbogor.co.id', '081234567890', 'Bogor Tengah', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBESc4a5lB8p7j7u', 'admin', 'pagi'),
('Budi Santoso', 'operator1', 'budi@parkirbogor.co.id', '081234567891', 'Bogor Utara', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBESc4a5lB8p7j7u', 'operator', 'pagi'),
('Siti Aminah', 'operator2', 'siti@parkirbogor.co.id', '081234567892', 'Bogor Selatan', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBESc4a5lB8p7j7u', 'operator', 'siang'),
('Ahmad Fauzi', 'pelanggan1', 'ahmad@gmail.com', '081234567893', 'Bogor Barat', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZJ18JuywdBESc4a5lB8p7j7u', 'pelanggan', 'pagi');

-- ------------------------------------------------------------
-- TABEL: kendaraan_pelanggan
-- ------------------------------------------------------------
CREATE TABLE kendaraan_pelanggan (
  id_kendaraan INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plat_nomor VARCHAR(15) NOT NULL,
  jenis_kendaraan ENUM('motor','mobil') NOT NULL,
  merk VARCHAR(50) DEFAULT NULL,
  warna VARCHAR(30) DEFAULT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_user) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_plat (plat_nomor)
);
INSERT INTO kendaraan_pelanggan (user_id, plat_nomor, jenis_kendaraan, merk, warna, is_default) VALUES
(4, 'F 1234 AB', 'motor', 'Honda Vario', 'Hitam', 1),
(4, 'B 5678 CD', 'mobil', 'Toyota Avanza', 'Putih', 0);

-- ------------------------------------------------------------
-- TABEL: tarif_parkir
-- ------------------------------------------------------------
CREATE TABLE tarif_parkir (
  id_tarif INT AUTO_INCREMENT PRIMARY KEY,
  jenis_kendaraan ENUM('motor','mobil') NOT NULL UNIQUE,
  tarif_per_jam DECIMAL(10,2) NOT NULL,
  tarif_minimal DECIMAL(10,2) NOT NULL,
  tarif_maksimal DECIMAL(10,2) DEFAULT 50000.00,
  denda_hilang_tiket DECIMAL(10,2) DEFAULT 20000.00,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT INTO tarif_parkir (jenis_kendaraan, tarif_per_jam, tarif_minimal, tarif_maksimal, denda_hilang_tiket) VALUES
('motor', 2000.00, 2000.00, 20000.00, 10000.00),
('mobil', 5000.00, 5000.00, 50000.00, 25000.00);

-- ------------------------------------------------------------
-- TABEL: slot_parkir
-- ------------------------------------------------------------
CREATE TABLE slot_parkir (
  id_slot INT AUTO_INCREMENT PRIMARY KEY,
  kode_slot VARCHAR(10) NOT NULL UNIQUE,
  jenis_kendaraan ENUM('motor','mobil') NOT NULL,
  zona VARCHAR(20) DEFAULT 'A',
  status ENUM('kosong','terisi','maintenance') NOT NULL DEFAULT 'kosong',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_jenis (jenis_kendaraan)
);

-- Slot motor M-01..M-20, mobil C-01..C-10
INSERT INTO slot_parkir (kode_slot, jenis_kendaraan, zona) VALUES
('M-01','motor','A'),('M-02','motor','A'),('M-03','motor','A'),('M-04','motor','A'),('M-05','motor','A'),
('M-06','motor','A'),('M-07','motor','A'),('M-08','motor','A'),('M-09','motor','A'),('M-10','motor','A'),
('M-11','motor','B'),('M-12','motor','B'),('M-13','motor','B'),('M-14','motor','B'),('M-15','motor','B'),
('M-16','motor','B'),('M-17','motor','B'),('M-18','motor','B'),('M-19','motor','B'),('M-20','motor','B'),
('C-01','mobil','C'),('C-02','mobil','C'),('C-03','mobil','C'),('C-04','mobil','C'),('C-05','mobil','C'),
('C-06','mobil','C'),('C-07','mobil','C'),('C-08','mobil','C'),('C-09','mobil','C'),('C-10','mobil','C');

-- ------------------------------------------------------------
-- TABEL: transaksi_parkir
-- ------------------------------------------------------------
CREATE TABLE transaksi_parkir (
  id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
  id_slot INT NOT NULL,
  jenis_kendaraan ENUM('motor','mobil') NOT NULL,
  plat_nomor VARCHAR(15) NOT NULL,
  user_id INT DEFAULT NULL,
  operator_id INT DEFAULT NULL,
  disetujui_oleh INT DEFAULT NULL,
  kode_tiket VARCHAR(12) DEFAULT NULL UNIQUE,
  sumber ENUM('operator','kiosk','pelanggan') NOT NULL DEFAULT 'operator',
  via_kiosk TINYINT(1) NOT NULL DEFAULT 0,
  waktu_masuk DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  waktu_ajuan_keluar DATETIME DEFAULT NULL,
  waktu_persetujuan DATETIME DEFAULT NULL,
  waktu_keluar DATETIME DEFAULT NULL,
  durasi_jam INT DEFAULT NULL,
  status ENUM('aktif','menunggu_approval','selesai','batal') NOT NULL DEFAULT 'aktif',
  catatan VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (id_slot) REFERENCES slot_parkir(id_slot) ON UPDATE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id_user) ON DELETE SET NULL,
  FOREIGN KEY (operator_id) REFERENCES users(id_user) ON DELETE SET NULL,
  FOREIGN KEY (disetujui_oleh) REFERENCES users(id_user) ON DELETE SET NULL,
  INDEX idx_plat (plat_nomor),
  INDEX idx_status (status),
  INDEX idx_waktu_masuk (waktu_masuk),
  INDEX idx_waktu_keluar (waktu_keluar)
);

-- ------------------------------------------------------------
-- TABEL: pembayaran
-- ------------------------------------------------------------
CREATE TABLE pembayaran (
  id_pembayaran INT AUTO_INCREMENT PRIMARY KEY,
  id_transaksi INT NOT NULL UNIQUE,
  total_biaya DECIMAL(10,2) NOT NULL,
  denda DECIMAL(10,2) DEFAULT 0,
  metode_pembayaran ENUM('tunai','qris','e-wallet','member') NOT NULL DEFAULT 'tunai',
  bayar DECIMAL(10,2) DEFAULT 0,
  kembalian DECIMAL(10,2) DEFAULT 0,
  kasir_id INT DEFAULT NULL,
  waktu_bayar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_transaksi) REFERENCES transaksi_parkir(id_transaksi) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (kasir_id) REFERENCES users(id_user) ON DELETE SET NULL,
  INDEX idx_waktu_bayar (waktu_bayar)
);

-- ------------------------------------------------------------
-- TABEL: log_aktivitas
-- ------------------------------------------------------------
CREATE TABLE log_aktivitas (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  aksi VARCHAR(100) NOT NULL,
  keterangan TEXT,
  ip_address VARCHAR(45),
  waktu DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_user) ON DELETE SET NULL,
  INDEX idx_waktu (waktu)
);

-- ------------------------------------------------------------
-- TABEL: laporan_harian
-- ------------------------------------------------------------
CREATE TABLE laporan_harian (
  tanggal DATE PRIMARY KEY,
  jumlah_transaksi INT NOT NULL DEFAULT 0,
  jumlah_motor INT NOT NULL DEFAULT 0,
  jumlah_mobil INT NOT NULL DEFAULT 0,
  total_pendapatan DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_tunai DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_qris DECIMAL(10,2) NOT NULL DEFAULT 0,
  slot_terpakai_max INT NOT NULL DEFAULT 0,
  diperbarui DATETIME
);

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- VIEW: v_transaksi_aktif
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_transaksi_aktif AS
SELECT t.*, s.kode_slot, s.zona, u.nama_lengkap AS operator, up.nama_lengkap AS pelanggan_nama
FROM transaksi_parkir t
JOIN slot_parkir s ON t.id_slot = s.id_slot
LEFT JOIN users u ON t.operator_id = u.id_user
LEFT JOIN users up ON t.user_id = up.id_user
WHERE t.waktu_keluar IS NULL AND t.status IN ('aktif', 'menunggu_approval');

-- ------------------------------------------------------------
-- VIEW: v_ringkasan_hari_ini
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_ringkasan_hari_ini AS
SELECT
  (SELECT COUNT(*) FROM transaksi_parkir WHERE DATE(waktu_masuk)=CURDATE()) AS masuk_hari_ini,
  (SELECT COUNT(*) FROM transaksi_parkir WHERE waktu_keluar IS NULL AND status IN ('aktif', 'menunggu_approval')) AS masih_parkir,
  (SELECT COALESCE(SUM(total_biaya + IFNULL(denda,0)),0) FROM pembayaran WHERE DATE(waktu_bayar)=CURDATE()) AS pendapatan_hari_ini,
  (SELECT COUNT(*) FROM slot_parkir WHERE status='kosong') AS slot_kosong,
  (SELECT COUNT(*) FROM slot_parkir WHERE status='terisi') AS slot_terisi,
  (SELECT COUNT(*) FROM slot_parkir) AS slot_total;

-- ------------------------------------------------------------
-- VIEW: v_statistik_operator
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_statistik_operator AS
SELECT 
  u.id_user, u.nama_lengkap, u.username, u.shift, u.aktif,
  COUNT(t.id_transaksi) AS total_input,
  COUNT(p.id_pembayaran) AS total_checkout,
  COALESCE(SUM(p.total_biaya + IFNULL(p.denda,0)), 0) AS total_penerimaan
FROM users u
LEFT JOIN transaksi_parkir t ON t.operator_id = u.id_user
LEFT JOIN pembayaran p ON p.kasir_id = u.id_user
WHERE u.role = 'operator'
GROUP BY u.id_user;

-- ------------------------------------------------------------
-- VIEW: v_statistik_pelanggan
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_statistik_pelanggan AS
SELECT 
  u.id_user, u.nama_lengkap, u.username,
  COUNT(DISTINCT t.id_transaksi) AS total_transaksi,
  COALESCE(SUM(p.total_biaya + IFNULL(p.denda,0)), 0) AS total_bayar
FROM users u
LEFT JOIN transaksi_parkir t ON t.user_id = u.id_user
LEFT JOIN pembayaran p ON p.id_transaksi = t.id_transaksi
WHERE u.role = 'pelanggan'
GROUP BY u.id_user;