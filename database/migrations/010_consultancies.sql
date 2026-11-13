-- Partner consultancies: one login per consultancy (Google), admin approval, students booked on their behalf.
ALTER TABLE users
  MODIFY role ENUM('ADMIN','CANDIDATE','CONSULTANCY') NOT NULL;

CREATE TABLE consultancies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  contact_name VARCHAR(120) NOT NULL,
  phone VARCHAR(16) NOT NULL,
  city VARCHAR(80) NULL,
  website VARCHAR(190) NULL,
  status ENUM('PENDING','APPROVED','REJECTED','SUSPENDED') NOT NULL DEFAULT 'PENDING',
  review_note VARCHAR(255) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_consultancies_owner (owner_user_id),
  INDEX idx_consultancies_status (status, name),
  CONSTRAINT fk_consultancies_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
  CONSTRAINT fk_consultancies_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE candidate_profiles
  ADD COLUMN consultancy_id BIGINT UNSIGNED NULL AFTER user_id,
  ADD INDEX idx_candidate_profiles_consultancy (consultancy_id),
  ADD CONSTRAINT fk_candidate_profiles_consultancy FOREIGN KEY (consultancy_id) REFERENCES consultancies(id);

ALTER TABLE interviews
  ADD COLUMN consultancy_id BIGINT UNSIGNED NULL AFTER candidate_classification_snapshot,
  ADD COLUMN booked_by_user_id BIGINT UNSIGNED NULL AFTER consultancy_id,
  ADD INDEX idx_interviews_consultancy (consultancy_id, start_time),
  ADD CONSTRAINT fk_interviews_consultancy FOREIGN KEY (consultancy_id) REFERENCES consultancies(id),
  ADD CONSTRAINT fk_interviews_booked_by FOREIGN KEY (booked_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
