-- =====================================================================
-- AlMaktabah — Islamic Digital Library
-- Database Schema (MySQL 5.7+ / MariaDB 10.3+)
-- =====================================================================
-- Import with:  mysql -u root -p almaktabah < database.sql
-- (create the database first: CREATE DATABASE almaktabah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(50)  NOT NULL UNIQUE,
    email            VARCHAR(150) NOT NULL UNIQUE,
    password         VARCHAR(255) NOT NULL,
    email_verified   TINYINT(1) NOT NULL DEFAULT 0,
    display_name     VARCHAR(100) DEFAULT NULL,
    profile_image    VARCHAR(255) DEFAULT NULL,
    bio              TEXT DEFAULT NULL,
    role             ENUM('user','subadmin','admin') NOT NULL DEFAULT 'user',
    permissions      JSON DEFAULT NULL COMMENT 'granular permission keys for subadmins',
    status           ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
    theme_preference ENUM('light','dark','auto') NOT NULL DEFAULT 'auto',
    language_preference VARCHAR(10) NOT NULL DEFAULT 'en',
    remember_token   VARCHAR(255) DEFAULT NULL,
    reset_token      VARCHAR(255) DEFAULT NULL,
    reset_expires    DATETIME DEFAULT NULL,
    last_login       DATETIME DEFAULT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- categories (Islamic taxonomy, supports sub-categories)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT UNSIGNED DEFAULT NULL,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    icon        VARCHAR(50) NOT NULL DEFAULT 'book',
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- authors
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS authors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    slug       VARCHAR(180) NOT NULL UNIQUE,
    bio        TEXT DEFAULT NULL,
    image      VARCHAR(255) DEFAULT NULL,
    era        VARCHAR(100) DEFAULT NULL COMMENT 'e.g. Classical, Contemporary',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FULLTEXT INDEX ft_author_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- books
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(255) NOT NULL,
    slug             VARCHAR(280) NOT NULL,
    description      TEXT DEFAULT NULL,
    category_id      INT UNSIGNED DEFAULT NULL,
    author_id        INT UNSIGNED DEFAULT NULL,
    cover_image      VARCHAR(255) DEFAULT NULL,
    file_path        VARCHAR(255) DEFAULT NULL,
    file_type        VARCHAR(20)  DEFAULT NULL,
    file_size        INT UNSIGNED DEFAULT NULL COMMENT 'bytes',
    external_link    VARCHAR(500) DEFAULT NULL,
    uploader_id      INT UNSIGNED DEFAULT NULL,
    language         VARCHAR(50)  NOT NULL DEFAULT 'Arabic',
    publication_date DATE DEFAULT NULL,
    publisher        VARCHAR(150) DEFAULT NULL,
    isbn             VARCHAR(30)  DEFAULT NULL,
    pages            INT UNSIGNED DEFAULT NULL,
    edition          VARCHAR(50)  DEFAULT NULL,
    status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reject_reason    VARCHAR(255) DEFAULT NULL,
    featured         TINYINT(1) NOT NULL DEFAULT 0,
    views_count      INT UNSIGNED NOT NULL DEFAULT 0,
    downloads_count  INT UNSIGNED NOT NULL DEFAULT 0,
    avg_rating       DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    reviews_count    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id)   REFERENCES authors(id)    ON DELETE SET NULL,
    FOREIGN KEY (uploader_id) REFERENCES users(id)      ON DELETE SET NULL,
    FULLTEXT INDEX ft_book_search (title, description),
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_author (author_id),
    INDEX idx_uploader (uploader_id),
    INDEX idx_featured (featured),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- reading_history  (also drives "continue reading" + recommendation input)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reading_history (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    book_id         INT UNSIGNED NOT NULL,
    last_page       INT UNSIGNED NOT NULL DEFAULT 1,
    total_pages     INT UNSIGNED DEFAULT NULL,
    read_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_private      TINYINT(1) NOT NULL DEFAULT 0,
    last_read_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_book (user_id, book_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_book (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- wishlist
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wishlist (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    book_id    INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_book (user_id, book_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- favorite_categories (users follow categories → recommendation signal)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favorite_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_cat (user_id, category_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- reviews
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    book_id    INT UNSIGNED NOT NULL,
    rating     TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    title      VARCHAR(150) DEFAULT NULL,
    comment    TEXT DEFAULT NULL,
    status     ENUM('approved','hidden') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_book_review (user_id, book_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    INDEX idx_book (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- book_views / book_downloads (analytics + recommendation signals)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS book_views (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    viewed_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_book_time (book_id, viewed_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS book_downloads (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id       INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED DEFAULT NULL,
    ip_address    VARCHAR(45) DEFAULT NULL,
    downloaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_book_time (book_id, downloaded_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- visitor_sessions (real-time "active now" tracking via polling heartbeat)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visitor_sessions (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_token  VARCHAR(64) NOT NULL UNIQUE,
    user_id        INT UNSIGNED DEFAULT NULL,
    ip_address     VARCHAR(45) DEFAULT NULL,
    current_page   VARCHAR(255) DEFAULT NULL,
    user_agent     VARCHAR(255) DEFAULT NULL,
    first_seen     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- notifications
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(50) NOT NULL,
    message    VARCHAR(255) NOT NULL,
    link       VARCHAR(255) DEFAULT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- admin_logs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT UNSIGNED DEFAULT NULL,
    action     VARCHAR(100) NOT NULL,
    details    VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- login_attempts (basic brute-force rate limiting)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier   VARCHAR(150) NOT NULL COMMENT 'username or ip',
    attempts     INT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt DATETIME DEFAULT NULL,
    locked_until DATETIME DEFAULT NULL,
    UNIQUE KEY uq_identifier (identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- otp_verifications (email verification + password reset codes)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS otp_verifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(150) NOT NULL,
    purpose     VARCHAR(30) NOT NULL DEFAULT 'verify_email' COMMENT 'verify_email | reset_password',
    otp_hash    VARCHAR(64) NOT NULL COMMENT 'sha256 of the 6-digit code — codes are never stored in plain text',
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at  DATETIME NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_purpose (email, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- site_settings (key/value store, seeded with sensible defaults below)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Default admin account — username: admin / password: admin123
-- The hash below is a genuine bcrypt hash of "admin123" generated with PHP's password_hash().
-- CHANGE THIS PASSWORD IMMEDIATELY after first login (Admin → Settings).
INSERT INTO users (username, email, password, email_verified, display_name, role, status, theme_preference)
VALUES ('admin', 'admin@almaktabah.local', '$2y$10$4z3lkjcovRawTxNr2OjThuhXXTFg6Z8tyAbZwCQN6xfrToRGYgjta', 1, 'Site Administrator', 'admin', 'active', 'auto');

-- Islamic category taxonomy
INSERT INTO categories (name, slug, description, icon, sort_order) VALUES
('Quran & Tafsir', 'quran-tafsir', 'The Noble Quran, translations, and works of tafsir (exegesis) explaining its meanings.', 'quran', 1),
('Hadith Sciences', 'hadith-sciences', 'Collections of the Prophetic traditions and the sciences of hadith authentication.', 'hadith', 2),
('Aqeedah', 'aqeedah', 'Islamic creed and theology — the foundations of belief.', 'star', 3),
('Fiqh & Islamic Law', 'fiqh-islamic-law', 'Jurisprudence across the schools of thought, rulings, and worship guidance.', 'scale', 4),
('Seerah & Biography', 'seerah-biography', 'The life of Prophet Muhammad (peace be upon him) and biographies of the companions.', 'scroll', 5),
('Islamic History', 'islamic-history', 'The history of the Muslim ummah, civilizations, and key events.', 'history', 6),
('Dua & Dhikr', 'dua-dhikr', 'Supplications, remembrance, and daily adhkar.', 'hands', 7),
('Family & Parenting', 'family-parenting', 'Marriage, parenting, and family life from an Islamic perspective.', 'family', 8),
('Arabic Language', 'arabic-language', 'Learning Quranic and classical Arabic.', 'language', 9),
('Comparative Religion & Dawah', 'comparative-religion-dawah', 'Dawah literature and comparative religious study.', 'compass', 10),
('Contemporary Issues', 'contemporary-issues', 'Modern topics addressed through an Islamic lens.', 'globe', 11),
('Children''s Islamic Books', 'childrens-islamic-books', 'Age-appropriate Islamic stories and learning for children.', 'child', 12),
('Ramadan & Fasting', 'ramadan-fasting', 'Guides and reflections on Ramadan and voluntary fasting.', 'moon', 13),
('Hajj & Umrah', 'hajj-umrah', 'Guides for pilgrimage rites and preparation.', 'kaaba', 14),
('Islamic Finance', 'islamic-finance', 'Halal finance, zakat, and Islamic economic principles.', 'coin', 15);

-- Site settings defaults
INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'AlMaktabah'),
('site_tagline', 'Islamic Digital Library'),
('site_description', 'A free digital library of Islamic books — read online or download, with recommendations tailored to your interests.'),
('logo_path', 'assets/images/logo.png'),
('max_upload_mb', '100'),
('daily_upload_limit', '10'),
('require_book_approval', '1'),
('maintenance_mode', '0'),
('contact_email', 'contact@almaktabah.local'),
('allow_guest_reading', '1');
