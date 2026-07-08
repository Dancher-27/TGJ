-- ============================================================
--  The Greatest Journey — Database
-- ============================================================

CREATE DATABASE IF NOT EXISTS tgj CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tgj;

-- Seasons / arcs
CREATE TABLE IF NOT EXISTS seasons (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    number       INT NOT NULL,
    title        VARCHAR(255) NOT NULL,
    subtitle     VARCHAR(255) DEFAULT '',
    description  TEXT,
    cover_image  VARCHAR(255) DEFAULT NULL,
    episode_count INT DEFAULT 0,
    status       ENUM('released','ongoing','upcoming') DEFAULT 'upcoming',
    release_date DATE DEFAULT NULL,
    end_date     DATE DEFAULT NULL,
    sort_order   INT DEFAULT 0
);

-- Episodes
CREATE TABLE IF NOT EXISTS episodes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    season_id    INT NOT NULL,
    number       INT NOT NULL,
    title        VARCHAR(255) NOT NULL,
    description  TEXT,
    content      LONGTEXT,
    release_date DATE DEFAULT NULL,
    sort_order   INT DEFAULT 0,
    status       ENUM('draft','published','upcoming') NOT NULL DEFAULT 'published',
    reading_time INT DEFAULT 0,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);
-- Migration for existing databases:
-- ALTER TABLE episodes ADD COLUMN content LONGTEXT AFTER description;
-- ALTER TABLE episodes ADD COLUMN sort_order INT DEFAULT 0 AFTER release_date;
-- ALTER TABLE episodes ADD COLUMN status ENUM('draft','published','upcoming') NOT NULL DEFAULT 'published' AFTER sort_order;
-- ALTER TABLE episodes ADD COLUMN reading_time INT DEFAULT 0 AFTER status;

-- Arcs (story arcs that span episodes)
CREATE TABLE IF NOT EXISTS arcs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    color       VARCHAR(7) DEFAULT '#d4af37',
    season_id   INT DEFAULT NULL,
    sort_order  INT DEFAULT 0,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL
);

-- Timeline events
CREATE TABLE IF NOT EXISTS timeline_events (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(255) NOT NULL,
    description   TEXT,
    event_year    INT DEFAULT NULL,
    event_label   VARCHAR(100) DEFAULT NULL,
    category      ENUM('battle','discovery','death','birth','political','romance','mystery','flashback','other') DEFAULT 'other',
    arc_id        INT DEFAULT NULL,
    episode_number INT DEFAULT NULL,
    image         VARCHAR(255) DEFAULT NULL,
    importance    ENUM('minor','major','critical') DEFAULT 'major',
    is_spoiler    TINYINT(1) DEFAULT 0,
    sort_order    INT DEFAULT 0,
    FOREIGN KEY (arc_id) REFERENCES arcs(id) ON DELETE SET NULL
);

-- Factions
CREATE TABLE IF NOT EXISTS factions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    color       VARCHAR(7) DEFAULT '#7c3aed',
    emblem      VARCHAR(255) DEFAULT NULL,
    is_good     TINYINT(1) DEFAULT 1
);

-- Characters
CREATE TABLE IF NOT EXISTS characters (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL,
    alias        VARCHAR(255) DEFAULT '',
    role         ENUM('protagonist','antagonist','supporting','villain','anti-hero') DEFAULT 'supporting',
    faction_id   INT DEFAULT NULL,
    age          VARCHAR(50) DEFAULT '',
    status       ENUM('alive','deceased','unknown') DEFAULT 'alive',
    description  TEXT,
    backstory    TEXT,
    abilities    TEXT,
    image        VARCHAR(255) DEFAULT NULL,
    is_spoiler   TINYINT(1) DEFAULT 0,
    sort_order   INT DEFAULT 0,
    FOREIGN KEY (faction_id) REFERENCES factions(id) ON DELETE SET NULL
);

-- Character extra images (gallery per character)
CREATE TABLE IF NOT EXISTS character_images (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    image        VARCHAR(255) NOT NULL,
    caption      VARCHAR(255) DEFAULT '',
    sort_order   INT DEFAULT 0,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
);
-- Migration: CREATE TABLE IF NOT EXISTS character_images (id INT AUTO_INCREMENT PRIMARY KEY, character_id INT NOT NULL, image VARCHAR(255) NOT NULL, caption VARCHAR(255) DEFAULT '', sort_order INT DEFAULT 0, FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE);

-- Character relations
CREATE TABLE IF NOT EXISTS character_relations (
    character_id INT NOT NULL,
    related_id   INT NOT NULL,
    relation     ENUM('friend','enemy','rival','family','mentor','student','love') DEFAULT 'friend',
    description  VARCHAR(255) DEFAULT '',
    PRIMARY KEY (character_id, related_id),
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (related_id)   REFERENCES characters(id) ON DELETE CASCADE
);

-- Character appearances (which seasons they appear in)
CREATE TABLE IF NOT EXISTS character_seasons (
    character_id INT NOT NULL,
    season_id    INT NOT NULL,
    PRIMARY KEY (character_id, season_id),
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id)    REFERENCES seasons(id)    ON DELETE CASCADE
);

-- Lore / World building
CREATE TABLE IF NOT EXISTS lore (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    category    VARCHAR(100) DEFAULT 'Algemeen',
    content     TEXT,
    image       VARCHAR(255) DEFAULT NULL,
    sort_order  INT DEFAULT 0
);

-- Gallery
CREATE TABLE IF NOT EXISTS gallery (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description VARCHAR(500) DEFAULT '',
    image       VARCHAR(255) NOT NULL,
    category    VARCHAR(100) DEFAULT 'Art',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin account
CREATE TABLE IF NOT EXISTS admin (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Default admin (password: admin123 — change this!)
INSERT IGNORE INTO admin (id, username, password)
VALUES (1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
