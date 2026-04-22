-- 2026-04-18: Realtime calls, group moderation, and richer story payload support.
-- Safe to run multiple times on MySQL 8+ / MariaDB 10.5+.

ALTER TABLE stories
  ADD COLUMN IF NOT EXISTS music_url VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS music_title VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS drawing_data LONGTEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS text_layers JSON DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS sticker_layers JSON DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS filter_css VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS gradient_bg VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS duration INT NOT NULL DEFAULT 5,
  ADD COLUMN IF NOT EXISTS visibility ENUM('public','friends') NOT NULL DEFAULT 'public',
  ADD COLUMN IF NOT EXISTS location_label VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS call_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_type ENUM('private','group') NOT NULL,
  thread_id INT NOT NULL,
  caller_id INT NOT NULL,
  callee_id INT DEFAULT NULL,
  call_type ENUM('audio','video') NOT NULL DEFAULT 'video',
  status ENUM('ringing','accepted','rejected','ended','missed') NOT NULL DEFAULT 'ringing',
  started_at DATETIME DEFAULT NULL,
  answered_at DATETIME DEFAULT NULL,
  ended_at DATETIME DEFAULT NULL,
  ended_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_call_sessions_thread (thread_type, thread_id),
  INDEX idx_call_sessions_callee (callee_id, status),
  INDEX idx_call_sessions_caller (caller_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_signals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  sender_id INT NOT NULL,
  signal_type ENUM('offer','answer','candidate','renegotiate','bye') NOT NULL,
  payload LONGTEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_call_signals_session (session_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_chat_id INT NOT NULL,
  reporter_id INT NOT NULL,
  reported_user_id INT DEFAULT NULL,
  message_id BIGINT DEFAULT NULL,
  reason VARCHAR(190) NOT NULL,
  details TEXT DEFAULT NULL,
  status ENUM('pending','reviewed','resolved','dismissed') NOT NULL DEFAULT 'pending',
  moderator_id INT DEFAULT NULL,
  moderation_note TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_group_reports_group (group_chat_id, status),
  INDEX idx_group_reports_reporter (reporter_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
