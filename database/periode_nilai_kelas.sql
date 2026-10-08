CREATE TABLE IF NOT EXISTS periode_nilai_kelas (
    kelas VARCHAR(100) NOT NULL,
    tahun_ajaran VARCHAR(9) NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kelas),
    KEY idx_periode_tahun_ajaran (tahun_ajaran)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
