CREATE TABLE IF NOT EXISTS ringkasan_perhitungan_kmeans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kelas VARCHAR(100) NOT NULL,
    tahun_ajaran VARCHAR(50) NOT NULL,
    jenis VARCHAR(50) NOT NULL DEFAULT 'keseluruhan',
    fitur_json LONGTEXT NOT NULL,
    minimum_json LONGTEXT NOT NULL,
    maksimum_json LONGTEXT NOT NULL,
    centroid_normal_json LONGTEXT NOT NULL,
    centroid_asli_json LONGTEXT NOT NULL,
    jumlah_iterasi INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unik_ringkasan_kelas_jenis (kelas, jenis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS detail_perhitungan_kmeans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kelas VARCHAR(100) NOT NULL,
    tahun_ajaran VARCHAR(50) NOT NULL,
    jenis VARCHAR(50) NOT NULL DEFAULT 'keseluruhan',
    siswa_id INT NOT NULL,
    nilai_asli_json LONGTEXT NOT NULL,
    nilai_normalisasi_json LONGTEXT NOT NULL,
    jarak_centroid_json LONGTEXT NOT NULL,
    cluster_terpilih VARCHAR(20) NOT NULL,
    jarak_terdekat DOUBLE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unik_detail_kelas_jenis_siswa (kelas, jenis, siswa_id),
    KEY idx_detail_siswa (siswa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
