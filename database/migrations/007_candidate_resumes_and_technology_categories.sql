CREATE TABLE technology_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE,
 slug VARCHAR(100) NOT NULL UNIQUE,
 sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_technology_categories_sort (sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE technologies
 ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER name,
 ADD INDEX idx_technologies_category_active_name (category_id, active, name),
 ADD CONSTRAINT fk_technologies_category FOREIGN KEY (category_id)
   REFERENCES technology_categories(id) ON DELETE SET NULL;

CREATE TABLE technology_aliases (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 technology_id BIGINT UNSIGNED NOT NULL,
 alias VARCHAR(100) NOT NULL,
 normalized_alias VARCHAR(100) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_technology_alias_normalized (normalized_alias),
 INDEX idx_technology_alias_technology (technology_id),
 CONSTRAINT fk_technology_alias_technology FOREIGN KEY (technology_id)
   REFERENCES technologies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candidate_resumes (
 candidate_id BIGINT UNSIGNED PRIMARY KEY,
 stored_filename VARCHAR(80) NOT NULL UNIQUE,
 original_filename VARCHAR(190) NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_candidate_resume_candidate FOREIGN KEY (candidate_id)
   REFERENCES candidate_profiles(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
