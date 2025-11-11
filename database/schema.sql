CREATE DATABASE IF NOT EXISTS db_smartlibrary
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_smartlibrary;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(120) NOT NULL,
  role ENUM('kepala_perpustakaan','staff','anggota') NOT NULL DEFAULT 'anggota',
  email VARCHAR(150) UNIQUE,
  no_telp VARCHAR(20),
  alamat TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kategori_buku (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(80) NOT NULL UNIQUE,
  deskripsi TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS buku (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  judul VARCHAR(255) NOT NULL,
  pengarang VARCHAR(120) NOT NULL,
  penerbit VARCHAR(120),
  tahun_terbit YEAR NULL,
  isbn VARCHAR(32),
  kategori VARCHAR(80),
  jumlah_stok INT UNSIGNED NOT NULL DEFAULT 0,
  lokasi_rak VARCHAR(60),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS peminjaman (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  buku_id INT UNSIGNED NOT NULL,
  tanggal_pinjam DATE NOT NULL,
  tanggal_kembali DATE NOT NULL,
  status ENUM('dipinjam','dikembalikan','terlambat') NOT NULL DEFAULT 'dipinjam',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_peminjaman_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_peminjaman_buku FOREIGN KEY (buku_id) REFERENCES buku(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS log_aktivitas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  aktivitas VARCHAR(255) NOT NULL,
  tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO users (username, password, nama_lengkap, role, email, no_telp, alamat)
VALUES (
  'kepalalib',
  '$2y$10$KbQiIfsekYHu/xG4Aky6ueKleChX2NDSSSXqoQZnIcXtNCmieYwHG', -- admin123
  'Kepala Perpustakaan',
  'kepala_perpustakaan',
  'kepala@smartlibrary.local',
  '08123456789',
  'Jl. Library Raya No. 1'
);

INSERT IGNORE INTO kategori_buku (id, nama_kategori, deskripsi) VALUES
  (1, 'Teknologi', 'Referensi teknologi & programming'),
  (2, 'Literatur', 'Novel dan sastra populer'),
  (3, 'Sains', 'Buku ilmiah dan penelitian');

INSERT IGNORE INTO buku (judul, pengarang, penerbit, tahun_terbit, isbn, kategori, jumlah_stok, lokasi_rak) VALUES
  ('Clean Architecture', 'Robert C. Martin', 'Pearson', 2017, '9780134494166', 'Teknologi', 3, 'Rak A1'),
  ('Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', 2005, '9789793062792', 'Literatur', 5, 'Rak B2'),
  ('Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', 'Harper', 2011, '9780062316097', 'Sains', 2, 'Rak C4');
