-- ═══════════════════════════════════════════════════════════════
--  Migration: Add latitude/longitude to users + story extras
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS latitude DOUBLE DEFAULT NULL AFTER exact_location,
    ADD COLUMN IF NOT EXISTS longitude DOUBLE DEFAULT NULL AFTER latitude;

-- Add spatial index for faster location queries
-- ALTER TABLE users ADD INDEX idx_users_location (latitude, longitude);

-- ═══════════════════════════════════════════════════════════════
--  Stories: Add music and drawing data columns
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE stories
    ADD COLUMN IF NOT EXISTS music_url VARCHAR(500) DEFAULT NULL AFTER media_url,
    ADD COLUMN IF NOT EXISTS music_title VARCHAR(255) DEFAULT NULL AFTER music_url,
    ADD COLUMN IF NOT EXISTS drawing_data LONGTEXT DEFAULT NULL AFTER music_title,
    ADD COLUMN IF NOT EXISTS text_layers JSON DEFAULT NULL AFTER drawing_data,
    ADD COLUMN IF NOT EXISTS sticker_layers JSON DEFAULT NULL AFTER text_layers,
    ADD COLUMN IF NOT EXISTS filter_css VARCHAR(255) DEFAULT NULL AFTER sticker_layers,
    ADD COLUMN IF NOT EXISTS gradient_bg VARCHAR(255) DEFAULT NULL AFTER filter_css,
    ADD COLUMN IF NOT EXISTS duration INT DEFAULT 5 AFTER gradient_bg,
    ADD COLUMN IF NOT EXISTS visibility ENUM('public','friends') DEFAULT 'public' AFTER duration;
