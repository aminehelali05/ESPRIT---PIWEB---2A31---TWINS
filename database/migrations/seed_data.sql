-- ═══════════════════════════════════════════════════════════════
--  Seed: 20 users with REAL latitude/longitude coordinates
--  Password for all: "password123" (bcrypt hash below)
-- ═══════════════════════════════════════════════════════════════

-- First run the migration
-- SOURCE database/migrations/add_coordinates_and_stories.sql;

UPDATE users SET latitude = 36.8065, longitude = 10.1815 WHERE email = 'admin@example.com';
UPDATE users SET latitude = 34.7406, longitude = 10.7603 WHERE email = 'amine.larper@example.com';

INSERT INTO users (first_name, last_name, email, password, phone, role, status, country, exact_location, latitude, longitude, bio, skills, xp, avatar_url)
VALUES
('Sofia', 'Fernandez', 'sofia.fernandez@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+34 612 345 678', 'freelancer', 1, 'Spain', 'Madrid, Spain', 40.4168, -3.7038, 'UI/UX designer passionate about accessible design.', 'Figma,Sketch,CSS,React', 180, 'https://api.dicebear.com/9.x/adventurer/svg?seed=sofia-fernandez'),
('Liam', 'O''Brien', 'liam.obrien@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+353 87 123 4567', 'freelancer', 1, 'Ireland', 'Dublin, Ireland', 53.3498, -6.2603, 'Full-stack developer focused on fintech solutions.', 'Node.js,TypeScript,PostgreSQL', 250, 'https://api.dicebear.com/9.x/adventurer/svg?seed=liam-obrien'),
('Yuki', 'Tanaka', 'yuki.tanaka@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+81 90 1234 5678', 'client', 1, 'Japan', 'Tokyo, Japan', 35.6762, 139.6503, 'Startup founder building the next travel tech.', 'Product Management,Strategy', 420, 'https://api.dicebear.com/9.x/adventurer/svg?seed=yuki-tanaka'),
('Amara', 'Diallo', 'amara.diallo@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+221 77 123 4567', 'freelancer', 1, 'Senegal', 'Dakar, Senegal', 14.7167, -17.4677, 'Mobile developer specializing in offline-first apps.', 'Flutter,Dart,Firebase', 190, 'https://api.dicebear.com/9.x/adventurer/svg?seed=amara-diallo'),
('Max', 'Müller', 'max.muller@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+49 171 234 5678', 'freelancer', 1, 'Germany', 'Berlin, Germany', 52.5200, 13.4050, 'DevOps engineer and cloud architect.', 'AWS,Docker,Kubernetes,Terraform', 310, 'https://api.dicebear.com/9.x/adventurer/svg?seed=max-muller'),
('Priya', 'Sharma', 'priya.sharma@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+91 98765 43210', 'client', 1, 'India', 'Mumbai, India', 19.0760, 72.8777, 'Tech recruiter connecting global talent.', 'Hiring,HR Tech,Recruiting', 275, 'https://api.dicebear.com/9.x/adventurer/svg?seed=priya-sharma'),
('Lucas', 'Santos', 'lucas.santos@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+55 11 98765 4321', 'freelancer', 1, 'Brazil', 'São Paulo, Brazil', -23.5505, -46.6333, 'Backend developer and open-source contributor.', 'Go,Rust,PostgreSQL', 340, 'https://api.dicebear.com/9.x/adventurer/svg?seed=lucas-santos'),
('Emma', 'Johansson', 'emma.johansson@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+46 70 123 4567', 'freelancer', 1, 'Sweden', 'Stockholm, Sweden', 59.3293, 18.0686, 'Data scientist with ML expertise.', 'Python,TensorFlow,Pandas,SQL', 290, 'https://api.dicebear.com/9.x/adventurer/svg?seed=emma-johansson'),
('Omar', 'Hassan', 'omar.hassan@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+20 100 123 4567', 'freelancer', 1, 'Egypt', 'Cairo, Egypt', 30.0444, 31.2357, 'Graphics designer and brand identity specialist.', 'Photoshop,Illustrator,Branding', 215, 'https://api.dicebear.com/9.x/adventurer/svg?seed=omar-hassan'),
('Chloe', 'Dupont', 'chloe.dupont@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+33 6 12 34 56 78', 'client', 1, 'France', 'Paris, France', 48.8566, 2.3522, 'Creative director at a Paris agency.', 'Branding,Motion Design,Leadership', 380, 'https://api.dicebear.com/9.x/adventurer/svg?seed=chloe-dupont'),
('Jin', 'Wei', 'jin.wei@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+86 138 1234 5678', 'freelancer', 1, 'China', 'Shanghai, China', 31.2304, 121.4737, 'AI/ML researcher and Python developer.', 'PyTorch,NLP,Computer Vision', 410, 'https://api.dicebear.com/9.x/adventurer/svg?seed=jin-wei'),
('Aisha', 'Mohammed', 'aisha.mohammed@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+971 50 123 4567', 'client', 1, 'UAE', 'Dubai, UAE', 25.2048, 55.2708, 'Venture partner investing in MENA startups.', 'Investing,Strategy,Fintech', 350, 'https://api.dicebear.com/9.x/adventurer/svg?seed=aisha-mohammed'),
('Noah', 'Williams', 'noah.williams@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+1 415 555 0123', 'freelancer', 1, 'United States', 'San Francisco, USA', 37.7749, -122.4194, 'iOS and SwiftUI developer.', 'Swift,SwiftUI,Xcode,UIKit', 280, 'https://api.dicebear.com/9.x/adventurer/svg?seed=noah-williams'),
('Maria', 'Rossi', 'maria.rossi@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+39 333 123 4567', 'freelancer', 1, 'Italy', 'Rome, Italy', 41.9028, 12.4964, 'Illustrator and visual storyteller.', 'Procreate,Figma,After Effects', 230, 'https://api.dicebear.com/9.x/adventurer/svg?seed=maria-rossi'),
('Kwame', 'Asante', 'kwame.asante@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+233 24 123 4567', 'freelancer', 1, 'Ghana', 'Accra, Ghana', 5.6037, -0.1870, 'Cybersecurity analyst and ethical hacker.', 'Pentesting,OSCP,Burp Suite', 260, 'https://api.dicebear.com/9.x/adventurer/svg?seed=kwame-asante'),
('Hana', 'Kim', 'hana.kim@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+82 10 1234 5678', 'client', 1, 'South Korea', 'Seoul, South Korea', 37.5665, 126.9780, 'Product manager at a K-pop tech startup.', 'Product,Agile,Data Analytics', 330, 'https://api.dicebear.com/9.x/adventurer/svg?seed=hana-kim'),
('Carlos', 'Mendez', 'carlos.mendez@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+52 55 1234 5678', 'freelancer', 1, 'Mexico', 'Mexico City, Mexico', 19.4326, -99.1332, 'Game developer and Unity specialist.', 'Unity,C#,Blender,3D', 200, 'https://api.dicebear.com/9.x/adventurer/svg?seed=carlos-mendez'),
('Fatima', 'Ben Ali', 'fatima.benali@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+216 55 123 456', 'freelancer', 1, 'Tunisia', 'Sousse, Tunisia', 35.8256, 10.6369, 'Frontend wizard and accessibility champion.', 'React,Vue,A11y,CSS', 270, 'https://api.dicebear.com/9.x/adventurer/svg?seed=fatima-benali'),
('Alex', 'Petrov', 'alex.petrov@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+7 916 123 4567', 'freelancer', 1, 'Russia', 'Moscow, Russia', 55.7558, 37.6173, 'Blockchain developer and DeFi enthusiast.', 'Solidity,Web3,Ethereum', 360, 'https://api.dicebear.com/9.x/adventurer/svg?seed=alex-petrov'),
('Olivia', 'Chen', 'olivia.chen@example.com', '$2y$10$T6fU4i1Rm5s6cJF0vE9w9u4Aakm0Qnhv5T9vLfH0V5ChR.MQ9vY0i', '+61 412 345 678', 'client', 1, 'Australia', 'Sydney, Australia', -33.8688, 151.2093, 'COO scaling remote-first companies.', 'Operations,HR,Remote Work', 400, 'https://api.dicebear.com/9.x/adventurer/svg?seed=olivia-chen')
ON DUPLICATE KEY UPDATE
    latitude = VALUES(latitude),
    longitude = VALUES(longitude),
    country = VALUES(country),
    exact_location = VALUES(exact_location),
    bio = VALUES(bio),
    skills = VALUES(skills);

-- ═══════════════════════════════════════════════════════════════
--  Seed: Sample stories for testing
-- ═══════════════════════════════════════════════════════════════
INSERT INTO stories (user_id, story_type, gradient_bg, caption, duration, visibility, expires_at)
SELECT id, 'text', 'linear-gradient(160deg,#0f0c29,#302b63,#24243e)', 'Just joined Diversity.is! 🚀', 5, 'public', DATE_ADD(NOW(), INTERVAL 1 DAY)
FROM users WHERE email = 'sofia.fernandez@example.com' LIMIT 1;

INSERT INTO stories (user_id, story_type, gradient_bg, caption, duration, visibility, expires_at)
SELECT id, 'text', 'linear-gradient(160deg,#f093fb,#f5576c)', 'Working on something exciting ✨', 10, 'public', DATE_ADD(NOW(), INTERVAL 1 DAY)
FROM users WHERE email = 'liam.obrien@example.com' LIMIT 1;

INSERT INTO stories (user_id, story_type, gradient_bg, caption, duration, visibility, expires_at)
SELECT id, 'text', 'linear-gradient(160deg,#0652c5,#11abe9)', 'Hello from Tokyo! 🗼', 5, 'public', DATE_ADD(NOW(), INTERVAL 1 DAY)
FROM users WHERE email = 'yuki.tanaka@example.com' LIMIT 1;

INSERT INTO stories (user_id, story_type, gradient_bg, caption, duration, visibility, expires_at)
SELECT id, 'text', 'linear-gradient(160deg,#12c2e9,#c471ed,#f64f59)', 'Building the future of Africa 🌍', 7, 'friends', DATE_ADD(NOW(), INTERVAL 1 DAY)
FROM users WHERE email = 'amara.diallo@example.com' LIMIT 1;

-- ═══════════════════════════════════════════════════════════════
--  Seed: Sample friend connections
-- ═══════════════════════════════════════════════════════════════
-- Note: friends table requires user_one_id < user_two_id
INSERT IGNORE INTO friends (user_one_id, user_two_id)
SELECT LEAST(a.id, b.id), GREATEST(a.id, b.id)
FROM users a, users b
WHERE a.email = 'admin@example.com' AND b.email = 'sofia.fernandez@example.com';

INSERT IGNORE INTO friends (user_one_id, user_two_id)
SELECT LEAST(a.id, b.id), GREATEST(a.id, b.id)
FROM users a, users b
WHERE a.email = 'admin@example.com' AND b.email = 'liam.obrien@example.com';

INSERT IGNORE INTO friends (user_one_id, user_two_id)
SELECT LEAST(a.id, b.id), GREATEST(a.id, b.id)
FROM users a, users b
WHERE a.email = 'admin@example.com' AND b.email = 'yuki.tanaka@example.com';

INSERT IGNORE INTO friends (user_one_id, user_two_id)
SELECT LEAST(a.id, b.id), GREATEST(a.id, b.id)
FROM users a, users b
WHERE a.email = 'admin@example.com' AND b.email = 'max.muller@example.com';

INSERT IGNORE INTO friends (user_one_id, user_two_id)
SELECT LEAST(a.id, b.id), GREATEST(a.id, b.id)
FROM users a, users b
WHERE a.email = 'sofia.fernandez@example.com' AND b.email = 'chloe.dupont@example.com';

-- ═══════════════════════════════════════════════════════════════
--  Seed: Sample linked accounts
-- ═══════════════════════════════════════════════════════════════
INSERT IGNORE INTO linked_accounts (user_id, platform, username, profile_url)
SELECT id, 'github', 'sofia-dev', 'https://github.com/sofia-dev' FROM users WHERE email = 'sofia.fernandez@example.com';

INSERT IGNORE INTO linked_accounts (user_id, platform, username, profile_url)
SELECT id, 'linkedin', 'liam-obrien-dev', 'https://linkedin.com/in/liam-obrien-dev' FROM users WHERE email = 'liam.obrien@example.com';

INSERT IGNORE INTO linked_accounts (user_id, platform, username, profile_url)
SELECT id, 'github', 'max-devops', 'https://github.com/max-devops' FROM users WHERE email = 'max.muller@example.com';
