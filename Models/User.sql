DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30),
    role VARCHAR(50) DEFAULT 'user',
    status BOOLEAN DEFAULT 1,
    avatar_url TEXT DEFAULT NULL,
    badge VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    skills TEXT DEFAULT NULL,
    xp INT DEFAULT 0,
    is_blocked BOOLEAN DEFAULT 0,
    last_seen DATETIME DEFAULT NULL,
    face_descriptor LONGTEXT DEFAULT NULL,
    face_images_path TEXT DEFAULT NULL,
    face_enrolled BOOLEAN DEFAULT 0,
    face_enrolled_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_delete_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    requested_by INT NOT NULL,
    reason TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_udr_user_id (user_id),
    INDEX idx_udr_status (status)
);

CREATE TABLE IF NOT EXISTS user_signin_history (
    id            INT          PRIMARY KEY AUTO_INCREMENT,
    user_id       INT          NOT NULL,
    signed_in_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address    VARCHAR(64)  DEFAULT NULL,
    user_agent    VARCHAR(255) DEFAULT NULL,
    device_type   VARCHAR(20)  DEFAULT NULL,   -- Desktop | Mobile | Tablet | Bot
    os            VARCHAR(60)  DEFAULT NULL,   -- e.g. Windows 10, macOS 14.2, Android 14
    browser       VARCHAR(100) DEFAULT NULL,   -- e.g. Chrome 124.0, Firefox 126.0
    INDEX idx_user_signin_user (user_id),
    INDEX idx_user_signin_at   (signed_in_at)
);
INSERT INTO users (first_name, last_name, email, password, phone, role, status, badge, country, bio, title, skills, xp)
VALUES
('Admin', 'Root', 'admin@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 00 000 000', 'admin', 1, 'Admin', 'Tunisia', 'Platform administrator with full access.', 'System Administrator', 'PHP,MySQL,JavaScript', 500),
('Amine', 'LARPER', 'amine.larper@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 22 222 222', 'manager', 1, 'Peace Maker', 'Tunisia', 'Community builder and project manager.', 'Community Manager', 'Leadership,Translation,Logistics', 320);
