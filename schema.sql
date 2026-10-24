CREATE DATABASE IF NOT EXISTS studenthub
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE studenthub;

CREATE TABLE IF NOT EXISTS students (
  student_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(60) NOT NULL,
  username VARCHAR(30) NOT NULL,
  role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
  email VARCHAR(254) NOT NULL,
  mobile VARCHAR(16) NOT NULL,
  course VARCHAR(40) NOT NULL,
  study_year TINYINT UNSIGNED NOT NULL,
  gender VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  terms_accepted_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id),
  UNIQUE KEY uq_students_username (username),
  UNIQUE KEY uq_students_email (email),
  UNIQUE KEY uq_students_mobile (mobile),
  CONSTRAINT chk_students_study_year CHECK (study_year BETWEEN 1 AND 4)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  category VARCHAR(60) NOT NULL,
  event_date DATE NOT NULL,
  event_time VARCHAR(60) NOT NULL,
  location VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  badge_class ENUM('success', 'warning', 'info', 'danger') NOT NULL DEFAULT 'info',
  poster_filename VARCHAR(80) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id),
  KEY idx_events_date (event_date),
  KEY idx_events_category_date (category, event_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registrations (
  registration_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  status ENUM('registered', 'cancelled') NOT NULL DEFAULT 'registered',
  registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (registration_id),
  UNIQUE KEY uq_registrations_student_event (student_id, event_id),
  KEY idx_registrations_event (event_id),
  CONSTRAINT fk_registrations_student
    FOREIGN KEY (student_id) REFERENCES students (student_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_registrations_event
    FOREIGN KEY (event_id) REFERENCES events (event_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS remember_tokens (
  selector CHAR(32) NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (selector),
  KEY idx_remember_tokens_student (student_id),
  KEY idx_remember_tokens_expiry (expires_at),
  CONSTRAINT fk_remember_tokens_student
    FOREIGN KEY (student_id) REFERENCES students (student_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
