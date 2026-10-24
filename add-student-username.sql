-- Run once against databases created before username support was added.
USE studenthub;

ALTER TABLE students
  ADD COLUMN username VARCHAR(30) NULL AFTER full_name;

UPDATE students
SET username = CONCAT('student', student_id)
WHERE username IS NULL;

ALTER TABLE students
  MODIFY COLUMN username VARCHAR(30) NOT NULL,
  ADD UNIQUE KEY uq_students_username (username);
