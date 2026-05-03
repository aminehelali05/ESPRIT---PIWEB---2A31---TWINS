
-- Create brainstormings table
CREATE TABLE IF NOT EXISTS brainstormings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('EN_ATTENTE', 'ACCEPTE', 'REFUSE') DEFAULT 'EN_ATTENTE',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_brainstorm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create ideas table
CREATE TABLE IF NOT EXISTS ideas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    brainstorming_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_idea_brainstorm FOREIGN KEY (brainstorming_id) REFERENCES brainstormings(id) ON DELETE CASCADE,
    CONSTRAINT fk_idea_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
