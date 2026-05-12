-- Migration V2: Advanced Features for Event & Resource Module

-- 1. Update events table with new fields
ALTER TABLE events ADD COLUMN capacite_max INT DEFAULT 0;
ALTER TABLE events ADD COLUMN nb_inscrits INT DEFAULT 0;
ALTER TABLE events ADD COLUMN statut_inscription ENUM('OUVERT', 'COMPLET') DEFAULT 'OUVERT';
ALTER TABLE events ADD COLUMN qr_code LONGTEXT DEFAULT NULL; -- To store Base64 QR Code

-- 2. Create registrations table
CREATE TABLE IF NOT EXISTS registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, event_id),
    CONSTRAINT fk_registration_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_registration_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- 3. Create favoris table
CREATE TABLE IF NOT EXISTS favoris (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    evenement_id INT NOT NULL,
    date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, evenement_id),
    CONSTRAINT fk_favori_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favori_event FOREIGN KEY (evenement_id) REFERENCES events(id) ON DELETE CASCADE
);
