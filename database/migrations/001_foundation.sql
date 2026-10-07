CREATE TABLE technologies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 role ENUM('ADMIN','CANDIDATE') NOT NULL,
 full_name VARCHAR(120) NOT NULL,
 phone VARCHAR(16) NULL UNIQUE,
 email VARCHAR(190) NULL UNIQUE,
 username VARCHAR(80) NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_users_role (role, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE candidate_profiles (
 user_id BIGINT UNSIGNED PRIMARY KEY,
 technology_id BIGINT UNSIGNED NOT NULL,
 classification ENUM('Genuine','Fake') NOT NULL,
 photo_filename VARCHAR(80) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (technology_id) REFERENCES technologies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE resources (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE,
 resource_type ENUM('TOOL','NORMAL') NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_resource_type (resource_type, active, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE support_people (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL UNIQUE,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE business_hours (
 weekday TINYINT UNSIGNED PRIMARY KEY,
 is_open TINYINT(1) NOT NULL,
 opens_at TIME NOT NULL,
 closes_at TIME NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CHECK (weekday BETWEEN 1 AND 7),
 CHECK (closes_at > opens_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE app_settings (
 setting_key VARCHAR(80) PRIMARY KEY,
 setting_value VARCHAR(255) NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE interviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 candidate_id BIGINT UNSIGNED NOT NULL,
 resource_id BIGINT UNSIGNED NOT NULL,
 support_person_id BIGINT UNSIGNED NULL,
 technology_id BIGINT UNSIGNED NOT NULL,
 company_name VARCHAR(190) NOT NULL,
 job_role VARCHAR(190) NOT NULL,
 interview_round VARCHAR(100) NOT NULL,
 hr_name VARCHAR(120) NULL,
 hr_phone VARCHAR(16) NULL,
 hr_unavailable TINYINT(1) NOT NULL DEFAULT 0,
 duration_minutes SMALLINT UNSIGNED NOT NULL,
 interview_type ENUM('TOOL','NORMAL') NOT NULL,
 start_time DATETIME NOT NULL COMMENT 'UTC',
 end_time DATETIME NOT NULL COMMENT 'UTC',
 status ENUM('BOOKED','CANCELLED') NOT NULL DEFAULT 'BOOKED',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (candidate_id) REFERENCES candidate_profiles(user_id),
 FOREIGN KEY (resource_id) REFERENCES resources(id),
 FOREIGN KEY (support_person_id) REFERENCES support_people(id),
 FOREIGN KEY (technology_id) REFERENCES technologies(id),
 CHECK (duration_minutes IN (30,45,60)),
 CHECK (end_time > start_time),
 CHECK ((interview_type = 'TOOL' AND support_person_id IS NOT NULL) OR (interview_type = 'NORMAL' AND support_person_id IS NULL)),
 INDEX idx_resource_conflicts (resource_id, status, start_time, end_time),
 INDEX idx_support_conflicts (support_person_id, status, start_time, end_time),
 INDEX idx_candidate_conflicts (candidate_id, status, start_time, end_time),
 INDEX idx_calendar (start_time, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_id BIGINT UNSIGNED NULL,
 action VARCHAR(80) NOT NULL,
 entity_type VARCHAR(80) NOT NULL,
 entity_id BIGINT UNSIGNED NULL,
 metadata_json TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (actor_id) REFERENCES users(id),
 INDEX idx_audit_entity (entity_type, entity_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rate_limits (
 bucket_hash CHAR(64) PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL,
 expires_at DATETIME NOT NULL,
 INDEX idx_rate_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- All future booking/configuration mutations must lock this row first.
CREATE TABLE scheduling_mutex (
 id TINYINT UNSIGNED PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
