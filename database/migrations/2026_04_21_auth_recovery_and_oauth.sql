ALTER TABLE users
    ADD COLUMN IF NOT EXISTS google_id VARCHAR(191) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS google_avatar_url VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_resets_email (email),
    INDEX idx_password_resets_token (token),
    INDEX idx_password_resets_expires_at (expires_at)
);

ALTER TABLE job_offers
    ADD COLUMN IF NOT EXISTS title VARCHAR(180) NOT NULL DEFAULT '' AFTER id;
