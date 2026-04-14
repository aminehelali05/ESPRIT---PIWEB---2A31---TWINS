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

CREATE TABLE IF NOT EXISTS job_offers (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    title            VARCHAR(180) NOT NULL,
    description      TEXT NOT NULL,
    budget           DECIMAL(12,2) NOT NULL DEFAULT 0,
    skills_required  TEXT DEFAULT NULL,
    location         VARCHAR(150) DEFAULT NULL,
    experience_level VARCHAR(40) DEFAULT NULL,
    project_type     VARCHAR(60) DEFAULT NULL,
    status           ENUM('open', 'in_progress', 'closed', 'archived') NOT NULL DEFAULT 'open',
    deadline_at      DATETIME DEFAULT NULL,
    client_id        INT NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_job_offers_client_id (client_id),
    INDEX idx_job_offers_status (status),
    CONSTRAINT fk_job_offers_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS job_offer_applications (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    job_offer_id    INT NOT NULL,
    freelancer_id   INT NOT NULL,
    cover_letter    TEXT DEFAULT NULL,
    status          ENUM('pending', 'accepted', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
    applied_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at      DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_offer_freelancer (job_offer_id, freelancer_id),
    INDEX idx_joa_offer_id (job_offer_id),
    INDEX idx_joa_freelancer_id (freelancer_id),
    INDEX idx_joa_status (status),
    CONSTRAINT fk_joa_offer FOREIGN KEY (job_offer_id) REFERENCES job_offers(id) ON DELETE CASCADE,
    CONSTRAINT fk_joa_freelancer FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contracts (
    id                    INT PRIMARY KEY AUTO_INCREMENT,
    job_offer_id          INT NOT NULL,
    freelancer_id         INT NOT NULL,
    client_id             INT NOT NULL,
    terms                 TEXT NOT NULL,
    status                ENUM('draft', 'active', 'completed', 'canceled') NOT NULL DEFAULT 'draft',
    amount                DECIMAL(12,2) NOT NULL DEFAULT 0,
    signed_at             DATETIME DEFAULT NULL,
    starts_at             DATETIME DEFAULT NULL,
    ends_at               DATETIME DEFAULT NULL,
    created_by_client_id  INT NOT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contracts_offer_id (job_offer_id),
    INDEX idx_contracts_client_id (client_id),
    INDEX idx_contracts_freelancer_id (freelancer_id),
    INDEX idx_contracts_status (status),
    CONSTRAINT fk_contracts_offer FOREIGN KEY (job_offer_id) REFERENCES job_offers(id) ON DELETE CASCADE,
    CONSTRAINT fk_contracts_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_contracts_freelancer FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_contracts_created_by FOREIGN KEY (created_by_client_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS projects (
    id                INT PRIMARY KEY AUTO_INCREMENT,
    title             VARCHAR(180) NOT NULL,
    description       TEXT NOT NULL,
    cover_image       VARCHAR(255) DEFAULT NULL,
    short_description VARCHAR(255) DEFAULT NULL,
    technologies      TEXT DEFAULT NULL,
    status            ENUM('planning', 'active', 'completed', 'on_hold', 'archived') NOT NULL DEFAULT 'planning',
    progress_percent  INT NOT NULL DEFAULT 0,
    budget            DECIMAL(12,2) DEFAULT NULL,
    due_date          DATE DEFAULT NULL,
    owner_id          INT NOT NULL,
    visibility        ENUM('private', 'team', 'public') NOT NULL DEFAULT 'team',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_projects_owner_id (owner_id),
    INDEX idx_projects_status (status),
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);
INSERT INTO users (first_name, last_name, email, password, phone, role, status, badge, country, bio, title, skills, xp)
VALUES
('Admin', 'Root', 'admin@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 00 000 000', 'admin', 1, 'Admin', 'Tunisia', 'Platform administrator with full access.', 'System Administrator', 'PHP,MySQL,JavaScript', 500),
('Amine', 'LARPER', 'amine.larper@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 22 222 222', 'manager', 1, 'Peace Maker', 'Tunisia', 'Community builder and project manager.', 'Community Manager', 'Leadership,Translation,Logistics', 320);
