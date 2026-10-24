USE studenthub;

ALTER TABLE events
  ADD COLUMN badge_class ENUM('success', 'warning', 'info', 'danger') NOT NULL DEFAULT 'info',
  ADD COLUMN poster_filename VARCHAR(80) DEFAULT NULL;
