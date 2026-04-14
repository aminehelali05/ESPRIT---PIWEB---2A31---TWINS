CREATE TABLE user (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30),
    role VARCHAR(50) DEFAULT 'user',
    status BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

<<<<<<< Updated upstream
INSERT INTO user (first_name, last_name, email, password, phone, role, status)
=======
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
>>>>>>> Stashed changes
VALUES
('Admin', 'Root', 'admin@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 00 000 000', 'admin', 1),
('Sarah', 'Kim', 'sarah.kim@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 11 111 111', 'manager', 1);
