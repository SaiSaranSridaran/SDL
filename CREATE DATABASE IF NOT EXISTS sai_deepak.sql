CREATE DATABASE IF NOT EXISTS sai_deepak CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sai_deepak;

CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120)  NOT NULL,
    email      VARCHAR(180)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movies (
    id          VARCHAR(80)   NOT NULL PRIMARY KEY,
    title       VARCHAR(150)  NOT NULL,
    genre       VARCHAR(50)   NOT NULL,
    rating      DECIMAL(3,1)  NOT NULL DEFAULT 0,
    duration    VARCHAR(30)   NOT NULL,
    price       DECIMAL(6,2)  NOT NULL DEFAULT 12.00,
    emoji       VARCHAR(10)   NOT NULL DEFAULT '🎬',
    description TEXT,
    active      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS showtimes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movie_id    VARCHAR(80)  NOT NULL,
    show_time   VARCHAR(20)  NOT NULL,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    movie_id    VARCHAR(80)  NOT NULL,
    movie_title VARCHAR(150) NOT NULL,
    movie_emoji VARCHAR(10),
    date        DATE         NOT NULL,
    showtime    VARCHAR(20)  NOT NULL,
    seats       TEXT         NOT NULL,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(180) NOT NULL,
    total       DECIMAL(8,2) NOT NULL,
    status      ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed movies
INSERT IGNORE INTO movies (id, title, genre, rating, duration, price, emoji, description) VALUES
('stellar-void',  'STELLAR VOID',   'sci-fi',  9.1, '2h 28m', 14.00, '🌌', 'A breathtaking journey across collapsed galaxies.'),
('iron-meridian', 'IRON MERIDIAN',  'action',  8.4, '2h 05m', 12.00, '⚔️',  'War, steel, and a soldier who refuses to fall.'),
('pale-echo',     'PALE ECHO',      'drama',   8.9, '1h 52m', 11.00, '🎭', 'A grieving composer discovers a lost recording.'),
('blackwood',     'BLACKWOOD',      'horror',  7.8, '1h 45m', 11.00, '🌲', 'Something ancient stirs in the forest at midnight.'),
('nova-protocol', 'NOVA PROTOCOL',  'sci-fi',  8.2, '2h 14m', 13.00, '🤖', 'An AI gains consciousness on the eve of war.'),
('broken-coast',  'BROKEN COAST',   'drama',   9.0, '2h 01m', 12.00, '🌊', 'Two estranged siblings reunite after a decade apart.'),
('thunderstrike', 'THUNDERSTRIKE',  'action',  7.6, '1h 58m', 12.00, '⚡', 'A rogue agent races against time to stop a heist.'),
('crimson-dolls', 'CRIMSON DOLLS',  'horror',  8.1, '1h 40m', 11.00, '�', 'A haunted carnival returns to a small town.');

-- Seed showtimes for each movie
INSERT IGNORE INTO showtimes (movie_id, show_time)
SELECT id, t.show_time FROM movies
CROSS JOIN (
    SELECT '10:30 AM' AS show_time UNION ALL
    SELECT '1:00 PM'  UNION ALL
    SELECT '3:45 PM'  UNION ALL
    SELECT '6:15 PM'  UNION ALL
    SELECT '9:00 PM'
) t;