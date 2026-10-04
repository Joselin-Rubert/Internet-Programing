-- =============================================================
--  ONLINE MOVIE TICKET BOOKING SYSTEM
--  Database file for phpMyAdmin / XAMPP
--  Database: movie_booking
-- =============================================================

CREATE DATABASE IF NOT EXISTS `movie_booking`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `movie_booking`;

-- Drop tables in reverse dependency order so the file can be
-- imported more than once without errors.
-- (Foreign key checks are switched off so the drops always succeed,
--  and switched back on straight after.)
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS `v_booking_report`;
DROP TABLE IF EXISTS `booking_details`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `shows`;
DROP TABLE IF EXISTS `seats`;
DROP TABLE IF EXISTS `theatres`;
DROP TABLE IF EXISTS `movies`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;


-- =============================================================
--  1. USERS  (people who book tickets)
-- =============================================================
CREATE TABLE `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `full_name`  VARCHAR(100) NOT NULL,
  `email`      VARCHAR(120) NOT NULL UNIQUE,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =============================================================
--  2. ADMINS
--  Default login -> username: admin  /  password: admin123
-- =============================================================
CREATE TABLE `admins` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(60)  NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `full_name`  VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admins` (`username`, `password`, `full_name`) VALUES
('admin', '$2y$10$s4hl579GePPPfIed0UxUwOaeLCWyrP8vVJW6xnW5QjVxNX.g1YxO.', 'Site Administrator');


-- =============================================================
--  3. MOVIES
--  status = 'now_showing' or 'upcoming'
-- =============================================================
CREATE TABLE `movies` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `title`         VARCHAR(150) NOT NULL,
  `description`   TEXT,
  `duration`      VARCHAR(20)  DEFAULT NULL,
  `language`      VARCHAR(60)  DEFAULT NULL,
  `genre`         VARCHAR(100) DEFAULT NULL,
  `rating`        DECIMAL(2,1) DEFAULT 0.0,
  `release_date`  DATE         DEFAULT NULL,
  `ticket_price`  DECIMAL(6,2) NOT NULL DEFAULT 150.00,
  `poster`        VARCHAR(255) DEFAULT 'default-poster.svg',
  `status`        ENUM('now_showing','upcoming') NOT NULL DEFAULT 'now_showing',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `movies`
(`title`, `description`, `duration`, `language`, `genre`, `rating`, `release_date`, `ticket_price`, `poster`, `status`) VALUES
('Midnight Protocol',
 'A disgraced cyber-security analyst is pulled back into the game one last time when a black-box AI starts choosing its own targets. To stop it she must trust the one man who betrayed her.',
 '2h 16m', 'English', 'Action Thriller', 8.6, CURDATE() - INTERVAL 12 DAY, 220.00, 'midnight-protocol.svg', 'now_showing'),

('The Velvet Circus',
 'In a dusty travelling circus, a silent acrobat and a runaway heiress uncover a family secret that could bankrupt an empire. Their bond is tested by firelight, betrayal and the rumble of a storm that never arrives.',
 '2h 34m', 'Hindi', 'Drama Romance', 8.1, CURDATE() - INTERVAL 5 DAY, 180.00, 'velvet-circus.svg', 'now_showing'),

('Ironclad Dawn',
 'Two estranged brothers command rival war machines on opposite sides of the last siege. As the sun rises over the ruined capital, they must decide which flag is worth more than blood.',
 '2h 47m', 'English', 'Action Adventure', 7.9, CURDATE() - INTERVAL 1 DAY, 260.00, 'ironclad-dawn.svg', 'now_showing'),

('Echoes of Tomorrow',
 'A sound engineer records a voice from the year 2087 buried inside a collapsing radio station. The more she listens, the more the future rewrites itself around her.',
 '1h 58m', 'English', 'Sci-Fi Mystery', 8.4, CURDATE() - INTERVAL 20 DAY, 200.00, 'echoes-of-tomorrow.svg', 'now_showing'),

('Paper Lanterns',
 'A small-town paper maker falls for a travelling painter who only has three days left before the monsoon. Told in three chapters, one for each day.',
 '1h 45m', 'Tamil', 'Romance', 8.8, CURDATE() + INTERVAL 9 DAY, 140.00, 'paper-lanterns.svg', 'upcoming'),

('Crimson Harbor',
 'A retired detective is dragged back to the dockside case that made him famous, only to discover the killer he convicted was protecting the town all along.',
 '2h 05m', 'English', 'Crime Thriller', 7.6, CURDATE() + INTERVAL 16 DAY, 210.00, 'crimson-harbor.svg', 'upcoming'),

('The Longest Winter',
 'When the sun disappears over an alpine village, a teacher keeps nine children alive for eleven nights using nothing but an old stove and a stubborn refusal to give up.',
 '2h 22m', 'Korean', 'Family Drama', 9.0, CURDATE() + INTERVAL 24 DAY, 230.00, 'longest-winter.svg', 'upcoming'),

('Solaris Rising',
 'The first crew sent to warm a frozen moon discovers the planet is not frozen at all. It has been waiting, patiently, for exactly ninety seconds.',
 '2h 39m', 'English', 'Sci-Fi Adventure', 8.3, CURDATE() + INTERVAL 31 DAY, 250.00, 'solaris-rising.svg', 'upcoming');


-- =============================================================
--  4. THEATRES
-- =============================================================
CREATE TABLE `theatres` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(120) NOT NULL,
  `location`   VARCHAR(120) DEFAULT NULL,
  `screen_name` VARCHAR(80) DEFAULT 'Screen 1',
  `is_active`  TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `theatres` (`name`, `location`, `screen_name`) VALUES
('CineVerse Grand',      'MG Road, Bengaluru',  'Screen 1 - IMAX'),
('Starlight Cinemas',    'Linking Road, Mumbai', 'Screen 2'),
('Nova Multiplex',       'Banjara Hills, Hyderabad', 'Screen 1');


-- =============================================================
--  5. SEATS
--  Every theatre gets rows A..E with 10 seats each.
--  Rows A and B are REGULAR, rows C..E are PREMIUM.
-- =============================================================
CREATE TABLE `seats` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `theatre_id`  INT NOT NULL,
  `seat_row`    CHAR(2) NOT NULL,
  `seat_number` INT NOT NULL,
  `seat_label`  VARCHAR(10) NOT NULL,
  `seat_type`   ENUM('regular','premium') NOT NULL DEFAULT 'regular',
  CONSTRAINT `fk_seat_theatre` FOREIGN KEY (`theatre_id`)
    REFERENCES `theatres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS `sp_create_seats`;

-- Build the seat map for every theatre in one go:
-- rows A-E, 10 seats each. Rows A and B are REGULAR, rows C-E are PREMIUM.
INSERT INTO `seats` (`theatre_id`, `seat_row`, `seat_number`, `seat_label`, `seat_type`)
SELECT
    t.id,
    r.lbl,
    n.n,
    CONCAT(r.lbl, n.n),
    IF(r.i < 2, 'regular', 'premium')
FROM `theatres` t
JOIN (
    SELECT 0 AS i, 'A' AS lbl UNION ALL SELECT 1, 'B' UNION ALL
    SELECT 2, 'C'                     UNION ALL SELECT 3, 'D' UNION ALL
    SELECT 4, 'E'
) r
JOIN (
    SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
    SELECT 5        UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL
    SELECT 9        UNION ALL SELECT 10
) n;


-- =============================================================
--  6. SHOWS  (one movie, at one theatre, on one date/time)
--  Made for today + the next 6 days.
-- =============================================================
CREATE TABLE `shows` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `movie_id`   INT NOT NULL,
  `theatre_id` INT NOT NULL,
  `show_date`  DATE NOT NULL,
  `show_time`  TIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_show` (`movie_id`, `theatre_id`, `show_date`, `show_time`),
  CONSTRAINT `fk_show_movie`   FOREIGN KEY (`movie_id`)   REFERENCES `movies` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_show_theatre` FOREIGN KEY (`theatre_id`) REFERENCES `theatres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS `sp_seed_shows`;

-- Schedule every "now showing" movie in every active theatre,
-- for today plus the next 6 days, at 5 daily showtimes.
-- INSERT IGNORE skips any slot that already exists (see the UNIQUE key above).
INSERT IGNORE INTO `shows` (`movie_id`, `theatre_id`, `show_date`, `show_time`)
SELECT
    m.id,
    t.id,
    DATE_ADD(CURDATE(), INTERVAL d.n DAY),
    tm.t
FROM `movies` m
JOIN `theatres` t ON t.is_active = 1
JOIN (
    SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3
    UNION ALL SELECT 4   UNION ALL SELECT 5 UNION ALL SELECT 6
) d
JOIN (
    SELECT '10:30:00' AS t UNION ALL SELECT '13:10:00' UNION ALL
    SELECT '15:50:00'       UNION ALL SELECT '18:30:00' UNION ALL
    SELECT '21:10:00'
) tm
WHERE m.status = 'now_showing';


-- =============================================================
--  7. BOOKINGS  (one row per confirmed ticket purchase)
-- =============================================================
CREATE TABLE `bookings` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `booking_code`    VARCHAR(20) NOT NULL UNIQUE,
  `user_id`         INT NOT NULL,
  `show_id`         INT NOT NULL,
  `seats_count`     INT NOT NULL DEFAULT 1,
  `subtotal`        DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `convenience_fee` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `total_amount`    DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `payment_method`  VARCHAR(30) NOT NULL DEFAULT 'Card',
  `payment_status`  ENUM('paid','failed','pending') NOT NULL DEFAULT 'paid',
  `status`          ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  `booked_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `cancelled_at`    DATETIME DEFAULT NULL,
  CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_show` FOREIGN KEY (`show_id`) REFERENCES `shows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =============================================================
--  8. BOOKING_DETAILS  (the individual seats inside a booking)
--     This is what stops a seat being sold twice.
-- =============================================================
CREATE TABLE `booking_details` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id`  INT NOT NULL,
  `seat_id`     INT NOT NULL,
  `seat_label`  VARCHAR(10) NOT NULL,
  `seat_price`  DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `is_cancelled` TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT `fk_bd_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bd_seat`    FOREIGN KEY (`seat_id`)    REFERENCES `seats` (`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =============================================================
--  DEMO USER
--   email: demo@user.com   password: user123
-- =============================================================
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`) VALUES
('Demo User', 'demo@user.com', '9876543210', '$2y$10$pofx3WUZ9Yf6Z.pcq4H/O.8uETm92HiokkWtYf2o9k.CvOoyxHsjy');


-- =============================================================
--  USEFUL VIEWS FOR THE ADMIN DASHBOARD
-- =============================================================
CREATE OR REPLACE VIEW `v_booking_report` AS
SELECT
    b.id,
    b.booking_code,
    u.full_name AS customer,
    u.email,
    m.title     AS movie,
    m.poster    AS poster,
    t.name      AS theatre,
    s.show_date,
    s.show_time,
    b.seats_count,
    b.total_amount,
    b.status,
    b.booked_at
FROM bookings b
JOIN users   u ON u.id = b.user_id
JOIN shows   s ON s.id = b.show_id
JOIN movies  m ON m.id = s.movie_id
JOIN theatres t ON t.id = s.theatre_id;
