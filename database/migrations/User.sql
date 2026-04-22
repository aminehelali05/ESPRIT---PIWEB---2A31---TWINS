CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30),
    role VARCHAR(50) DEFAULT 'user',
    status BOOLEAN DEFAULT 1,
    avatar_url TEXT DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    exact_location VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    skills TEXT DEFAULT NULL,
    xp INT DEFAULT 0,
    is_blocked BOOLEAN DEFAULT 0,
    last_seen DATETIME DEFAULT NULL,
    face_images_path TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE users
    DROP COLUMN IF EXISTS badge,
    DROP COLUMN IF EXISTS title,
    DROP COLUMN IF EXISTS face_enrolled,
    DROP COLUMN IF EXISTS face_enrolled_at,
    DROP COLUMN IF EXISTS face_descriptor;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS exact_location VARCHAR(255) DEFAULT NULL AFTER country;

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
    INDEX idx_udr_status (status),
    CONSTRAINT fk_user_delete_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_signin_history (
    id            INT          PRIMARY KEY AUTO_INCREMENT,
    user_id       INT          NOT NULL,
    signed_in_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address    VARCHAR(64)  DEFAULT NULL,
    user_agent    VARCHAR(255) DEFAULT NULL,
    device_type   VARCHAR(20)  DEFAULT NULL,
    os            VARCHAR(60)  DEFAULT NULL,
    browser       VARCHAR(100) DEFAULT NULL,
    INDEX idx_user_signin_user (user_id),
    INDEX idx_user_signin_at   (signed_in_at),
    CONSTRAINT fk_user_signin_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS linked_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    platform VARCHAR(50) NOT NULL,
    account_label VARCHAR(80) DEFAULT NULL,
    username VARCHAR(120) DEFAULT NULL,
    profile_url VARCHAR(255) NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT 0,
    is_public BOOLEAN NOT NULL DEFAULT 1,
    metadata JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_linked_accounts_unique (user_id, platform, profile_url(191)),
    INDEX idx_linked_accounts_user_id (user_id),
    INDEX idx_linked_accounts_platform (platform),
    CONSTRAINT fk_linked_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS friend_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    request_message VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'accepted', 'declined', 'blocked', 'canceled') NOT NULL DEFAULT 'pending',
    responded_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_friend_requests_sender_receiver_status (sender_id, receiver_id, status),
    INDEX idx_friend_requests_receiver_status (receiver_id, status),
    INDEX idx_friend_requests_sender_status (sender_id, status),
    INDEX idx_friend_requests_created_at (created_at),
    CONSTRAINT fk_friend_requests_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friend_requests_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS friends (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_one_id INT NOT NULL,
    user_two_id INT NOT NULL,
    source_request_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_friends_pair_order CHECK (user_one_id < user_two_id),
    UNIQUE KEY uq_friends_pair (user_one_id, user_two_id),
    INDEX idx_friends_user_one (user_one_id),
    INDEX idx_friends_user_two (user_two_id),
    CONSTRAINT fk_friends_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friends_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friends_source_request FOREIGN KEY (source_request_id) REFERENCES friend_requests(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS private_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_one_id INT NOT NULL,
    user_two_id INT NOT NULL,
    last_message_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_private_conversations_pair_order CHECK (user_one_id < user_two_id),
    UNIQUE KEY uq_private_conversations_pair (user_one_id, user_two_id),
    INDEX idx_private_conversations_last_message_at (last_message_at),
    CONSTRAINT fk_private_conversations_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_private_conversations_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS group_chats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL,
    created_by INT NOT NULL,
    is_private BOOLEAN NOT NULL DEFAULT 0,
    last_message_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group_chats_created_by (created_by),
    INDEX idx_group_chats_last_message_at (last_message_at),
    CONSTRAINT fk_group_chats_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS group_chat_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_chat_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member') NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME DEFAULT NULL,
    is_muted BOOLEAN NOT NULL DEFAULT 0,
    UNIQUE KEY uq_group_chat_member (group_chat_id, user_id),
    INDEX idx_group_chat_members_user_id (user_id),
    INDEX idx_group_chat_members_left_at (left_at),
    CONSTRAINT fk_group_chat_members_group FOREIGN KEY (group_chat_id) REFERENCES group_chats(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_chat_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    private_conversation_id INT DEFAULT NULL,
    group_chat_id INT DEFAULT NULL,
    message_type ENUM('text', 'image', 'video', 'audio', 'file', 'system') NOT NULL DEFAULT 'text',
    body TEXT DEFAULT NULL,
    media_url VARCHAR(255) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    reply_to_message_id BIGINT DEFAULT NULL,
    is_edited BOOLEAN NOT NULL DEFAULT 0,
    is_deleted BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_messages_scope CHECK (
        (private_conversation_id IS NOT NULL AND group_chat_id IS NULL)
        OR (private_conversation_id IS NULL AND group_chat_id IS NOT NULL)
    ),
    INDEX idx_messages_private_conversation (private_conversation_id, created_at),
    INDEX idx_messages_group_chat (group_chat_id, created_at),
    INDEX idx_messages_sender (sender_id, created_at),
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_private_conversation FOREIGN KEY (private_conversation_id) REFERENCES private_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_group_chat FOREIGN KEY (group_chat_id) REFERENCES group_chats(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS message_reads (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    message_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_message_reads_unique (message_id, user_id),
    INDEX idx_message_reads_user_id (user_id, read_at),
    CONSTRAINT fk_message_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS message_reactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    message_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(20) NOT NULL DEFAULT 'like',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_message_reactions_unique (message_id, user_id, reaction),
    INDEX idx_message_reactions_message (message_id, reaction),
    CONSTRAINT fk_message_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS stories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    story_type ENUM('image', 'video', 'text') NOT NULL DEFAULT 'image',
    media_url VARCHAR(255) DEFAULT NULL,
    caption VARCHAR(280) DEFAULT NULL,
    location_label VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    is_archived BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_stories_user_expires (user_id, expires_at),
    INDEX idx_stories_archived (is_archived),
    CONSTRAINT fk_stories_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS story_views (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    story_id BIGINT NOT NULL,
    viewer_id INT NOT NULL,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_story_views_unique (story_id, viewer_id),
    INDEX idx_story_views_viewer (viewer_id, viewed_at),
    CONSTRAINT fk_story_views_story FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
    CONSTRAINT fk_story_views_viewer FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE
);

DROP TRIGGER IF EXISTS trg_friend_requests_before_insert;
DROP TRIGGER IF EXISTS trg_friend_requests_before_update;
DROP TRIGGER IF EXISTS trg_friend_requests_after_update;
DROP TRIGGER IF EXISTS trg_messages_after_insert;
DROP TRIGGER IF EXISTS trg_stories_before_insert;
DROP TRIGGER IF EXISTS trg_stories_before_update;

DELIMITER $$

CREATE TRIGGER trg_friend_requests_before_insert
BEFORE INSERT ON friend_requests
FOR EACH ROW
BEGIN
    IF NEW.sender_id = NEW.receiver_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'You cannot send a friend request to yourself.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM friends f
        WHERE f.user_one_id = LEAST(NEW.sender_id, NEW.receiver_id)
          AND f.user_two_id = GREATEST(NEW.sender_id, NEW.receiver_id)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Users are already connected as friends.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM friend_requests fr
        WHERE fr.status = 'pending'
          AND (
              (fr.sender_id = NEW.sender_id AND fr.receiver_id = NEW.receiver_id)
              OR
              (fr.sender_id = NEW.receiver_id AND fr.receiver_id = NEW.sender_id)
          )
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A pending friend request already exists for this user pair.';
    END IF;
END$$

CREATE TRIGGER trg_friend_requests_before_update
BEFORE UPDATE ON friend_requests
FOR EACH ROW
BEGIN
    IF NEW.status <> OLD.status
       AND NEW.status <> 'pending'
       AND NEW.responded_at IS NULL THEN
        SET NEW.responded_at = NOW();
    END IF;
END$$

CREATE TRIGGER trg_friend_requests_after_update
AFTER UPDATE ON friend_requests
FOR EACH ROW
BEGIN
    IF NEW.status = 'accepted' AND OLD.status <> 'accepted' THEN
        INSERT IGNORE INTO friends (user_one_id, user_two_id, source_request_id, created_at)
        VALUES (
            LEAST(NEW.sender_id, NEW.receiver_id),
            GREATEST(NEW.sender_id, NEW.receiver_id),
            NEW.id,
            NOW()
        );

        INSERT IGNORE INTO private_conversations (user_one_id, user_two_id, last_message_at, created_at, updated_at)
        VALUES (
            LEAST(NEW.sender_id, NEW.receiver_id),
            GREATEST(NEW.sender_id, NEW.receiver_id),
            NULL,
            NOW(),
            NOW()
        );
    END IF;
END$$

CREATE TRIGGER trg_messages_after_insert
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    IF NEW.private_conversation_id IS NOT NULL THEN
        UPDATE private_conversations
        SET last_message_at = NEW.created_at,
            updated_at = NEW.created_at
        WHERE id = NEW.private_conversation_id;
    END IF;

    IF NEW.group_chat_id IS NOT NULL THEN
        UPDATE group_chats
        SET last_message_at = NEW.created_at,
            updated_at = NEW.created_at
        WHERE id = NEW.group_chat_id;
    END IF;

    INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
    VALUES (NEW.id, NEW.sender_id, NEW.created_at);
END$$

CREATE TRIGGER trg_stories_before_insert
BEFORE INSERT ON stories
FOR EACH ROW
BEGIN
    IF NEW.expires_at IS NULL THEN
        SET NEW.expires_at = DATE_ADD(NOW(), INTERVAL 1 DAY);
    END IF;

    SET NEW.is_archived = 0;
END$$

CREATE TRIGGER trg_stories_before_update
BEFORE UPDATE ON stories
FOR EACH ROW
BEGIN
    IF NEW.expires_at IS NOT NULL AND NEW.expires_at <= NOW() THEN
        SET NEW.is_archived = 1;
    END IF;
END$$

DELIMITER ;

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

INSERT INTO users (first_name, last_name, email, password, phone, role, status, country, exact_location, bio, skills, xp)
VALUES
('Admin', 'Root', 'admin@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 00 000 000', 'admin', 1, 'Tunisia', 'Tunis, Tunisia', 'Platform administrator with full access.', 'PHP,MySQL,JavaScript', 500),
('Amine', 'LARPER', 'amine.larper@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 22 222 222', 'manager', 1, 'Tunisia', 'Sfax, Tunisia', 'Community builder and project manager.', 'Leadership,Translation,Logistics', 320)
ON DUPLICATE KEY UPDATE
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    phone = VALUES(phone),
    role = VALUES(role),
    status = VALUES(status),
    country = VALUES(country),
    exact_location = VALUES(exact_location),
    bio = VALUES(bio),
    skills = VALUES(skills),
    xp = VALUES(xp);
