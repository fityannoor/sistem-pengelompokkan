CREATE TABLE IF NOT EXISTS aktivitas_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    nama_pengguna VARCHAR(100) NOT NULL,
    role_pengguna VARCHAR(20) NOT NULL,
    aksi VARCHAR(50) NOT NULL,
    entitas VARCHAR(50) NOT NULL,
    entitas_id INT NULL,
    keterangan VARCHAR(500) NOT NULL DEFAULT '',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_aktivitas_created_at (created_at),
    KEY idx_aktivitas_user_id (user_id),
    KEY idx_aktivitas_entitas (entitas, entitas_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
