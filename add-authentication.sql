USE studenthub;

ALTER TABLE students
  ADD COLUMN role ENUM('student', 'admin') NOT NULL DEFAULT 'student';

CREATE TABLE remember_tokens (
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
