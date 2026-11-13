-- Office locations (e.g. KPHB, Ameerpet) that admins assign to booked interviews, usually the evening before.
CREATE TABLE interview_locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  address VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_interview_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE interviews
  ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER resource_id,
  ADD COLUMN location_assigned_by BIGINT UNSIGNED NULL AFTER location_id,
  ADD COLUMN location_assigned_at DATETIME NULL AFTER location_assigned_by,
  ADD INDEX idx_interviews_location_pending (status, location_id, start_time),
  ADD CONSTRAINT fk_interviews_location FOREIGN KEY (location_id) REFERENCES interview_locations(id),
  ADD CONSTRAINT fk_interviews_location_assigned_by FOREIGN KEY (location_assigned_by) REFERENCES users(id) ON DELETE SET NULL;
