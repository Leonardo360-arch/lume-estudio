CREATE TABLE IF NOT EXISTS photo_albums (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 client_id BIGINT UNSIGNED NOT NULL,
 booking_id BIGINT UNSIGNED NULL,
 title VARCHAR(160) NOT NULL,
 description VARCHAR(1000) NOT NULL DEFAULT '',
 status ENUM('DRAFT','PUBLISHED','PURGED') NOT NULL DEFAULT 'DRAFT',
 published_at DATETIME NULL,
 expires_at DATETIME NULL,
 purge_at DATETIME NULL,
 version INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_album_client (client_id, status),
 INDEX idx_album_purge (status, purge_at),
 FOREIGN KEY (client_id) REFERENCES admins(id),
 FOREIGN KEY (booking_id) REFERENCES agendamentos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS album_photos (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 album_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 storage_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 thumbnail_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 original_name VARCHAR(200) NOT NULL,
 mime VARCHAR(30) NOT NULL,
 bytes BIGINT UNSIGNED NOT NULL,
 width INT UNSIGNED NOT NULL,
 height INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_photo_album (album_id, created_at, id),
 FOREIGN KEY (album_id) REFERENCES photo_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS album_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 album_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 kind ENUM('PUBLISHED','REMINDER_7','REMINDER_2','EXTENDED') NOT NULL,
 album_version INT UNSIGNED NOT NULL,
 due_at DATETIME NOT NULL,
 status ENUM('PENDING','SENT','SKIPPED') NOT NULL DEFAULT 'PENDING',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 last_error VARCHAR(250) NULL,
 sent_at DATETIME NULL,
 UNIQUE KEY uk_album_notification (album_id, album_version, kind),
 INDEX idx_album_notification_due (status, due_at),
 FOREIGN KEY (album_id) REFERENCES photo_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
