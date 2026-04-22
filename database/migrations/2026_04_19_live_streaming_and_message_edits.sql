-- 2026-04-19: Live streaming schema + message edit support.
-- Safe to run multiple times on MySQL 8+ / MariaDB 10.5+.

ALTER TABLE messages
  ADD COLUMN IF NOT EXISTS is_edited TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS edited_at DATETIME DEFAULT NULL;

CREATE TABLE IF NOT EXISTS live_streams (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  host_user_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT DEFAULT NULL,
  category VARCHAR(80) DEFAULT NULL,
  visibility ENUM('public','friends') NOT NULL DEFAULT 'public',
  allow_recording TINYINT(1) NOT NULL DEFAULT 0,
  cover_image_url VARCHAR(1024) DEFAULT NULL,
  status ENUM('live','ended') NOT NULL DEFAULT 'live',
  viewer_count INT NOT NULL DEFAULT 0,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME DEFAULT NULL,
  heartbeat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_live_streams_host_status (host_user_id, status),
  INDEX idx_live_streams_status_heartbeat (status, heartbeat_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_stream_viewers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stream_id BIGINT UNSIGNED NOT NULL,
  user_id INT NOT NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  left_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_live_stream_viewers_stream (stream_id, left_at, last_seen_at),
  INDEX idx_live_stream_viewers_user (user_id, left_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_stream_signals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stream_id BIGINT UNSIGNED NOT NULL,
  sender_id INT NOT NULL,
  target_user_id INT DEFAULT NULL,
  signal_type ENUM('offer','answer','candidate','bye','ping') NOT NULL DEFAULT 'candidate',
  payload LONGTEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_live_stream_signals_stream (stream_id, id),
  INDEX idx_live_stream_signals_target (target_user_id, stream_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_stream_chat (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stream_id BIGINT UNSIGNED NOT NULL,
  user_id INT NOT NULL,
  body TEXT NOT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_live_stream_chat_stream (stream_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
