-- Migration: Add social features schema
-- Run this migration on your MySQL/MariaDB database (review before running)

-- 1) Alter existing users table
ALTER TABLE `users`
  DROP COLUMN IF EXISTS `badge`,
  DROP COLUMN IF EXISTS `title`,
  DROP COLUMN IF EXISTS `face_enrolled`,
  DROP COLUMN IF EXISTS `face_enrolled_at`,
  DROP COLUMN IF EXISTS `face_descriptor`,
  ADD COLUMN IF NOT EXISTS `exact_location` VARCHAR(255) DEFAULT NULL;

-- 2) Linked accounts (oauth, socials)
CREATE TABLE IF NOT EXISTS `linked_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `provider` VARCHAR(64) NOT NULL,
  `provider_user_id` VARCHAR(255) NOT NULL,
  `data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_provider_user` (`provider`,`provider_user_id`),
  INDEX (`user_id`),
  CONSTRAINT `fk_linked_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Friends and friend requests
CREATE TABLE IF NOT EXISTS `friends` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `friend_id` INT NOT NULL,
  `status` ENUM('accepted','blocked') NOT NULL DEFAULT 'accepted',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_friend_pair` (`user_id`,`friend_id`),
  INDEX (`friend_id`),
  CONSTRAINT `fk_friends_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_friends_friend` FOREIGN KEY (`friend_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `friend_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `message` VARCHAR(512) DEFAULT NULL,
  `status` ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_request_pair` (`sender_id`,`receiver_id`),
  INDEX (`receiver_id`),
  CONSTRAINT `fk_friendreq_sender` FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_friendreq_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Conversations & messages (private + group support via conversation_type)
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('private','group') NOT NULL DEFAULT 'private',
  `name` VARCHAR(255) DEFAULT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`created_by`),
  CONSTRAINT `fk_conversations_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` ENUM('member','admin') NOT NULL DEFAULT 'member',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_conv_user` (`conversation_id`,`user_id`),
  INDEX (`user_id`),
  CONSTRAINT `fk_conv_member_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conv_member_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `body` TEXT DEFAULT NULL,
  `message_type` ENUM('text','image','video','audio','file','system') NOT NULL DEFAULT 'text',
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `edited_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX (`conversation_id`),
  INDEX (`sender_id`),
  CONSTRAINT `fk_messages_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `message_attachments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message_id` INT NOT NULL,
  `file_url` VARCHAR(1024) NOT NULL,
  `mime_type` VARCHAR(128) DEFAULT NULL,
  `size` INT UNSIGNED DEFAULT NULL,
  INDEX (`message_id`),
  CONSTRAINT `fk_msg_attach_msg` FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Group chats metadata
CREATE TABLE IF NOT EXISTS `group_chats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `icon_url` VARCHAR(1024) DEFAULT NULL,
  `topic` VARCHAR(255) DEFAULT NULL,
  CONSTRAINT `fk_group_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Stories and media
CREATE TABLE IF NOT EXISTS `stories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `author_id` INT NOT NULL,
  `caption` VARCHAR(1024) DEFAULT NULL,
  `visibility` ENUM('public','friends','close_friends','private') DEFAULT 'friends',
  `expire_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`author_id`),
  CONSTRAINT `fk_stories_author` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `story_media` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `story_id` INT NOT NULL,
  `media_url` VARCHAR(1024) NOT NULL,
  `media_type` ENUM('image','video') NOT NULL,
  `order_idx` INT UNSIGNED DEFAULT 0,
  CONSTRAINT `fk_story_media_story` FOREIGN KEY (`story_id`) REFERENCES `stories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `story_views` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `story_id` INT NOT NULL,
  `viewer_id` INT NOT NULL,
  `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_story_view` (`story_id`,`viewer_id`),
  CONSTRAINT `fk_story_view_story` FOREIGN KEY (`story_id`) REFERENCES `stories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_story_view_viewer` FOREIGN KEY (`viewer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) Performance indexes
CREATE INDEX IF NOT EXISTS `idx_messages_sender_created` ON `messages` (`sender_id`, `created_at` DESC);
CREATE INDEX IF NOT EXISTS `idx_stories_user_expire` ON `stories` (`user_id`, `expires_at`);

-- 8) Optional: scheduled event to purge expired stories (MySQL event scheduler required)
-- Uncomment and review if you want automatic deletion
--
-- DELIMITER $$
-- CREATE EVENT IF NOT EXISTS purge_expired_stories
-- ON SCHEDULE EVERY 1 HOUR
-- DO
-- BEGIN
--   DELETE FROM stories WHERE expire_at < NOW();
-- END$$
-- DELIMITER ;

-- End of migration
