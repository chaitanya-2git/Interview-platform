CREATE TABLE candidate_technologies (
 candidate_id BIGINT UNSIGNED NOT NULL,
 technology_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (candidate_id, technology_id),
 INDEX idx_candidate_technologies_technology_candidate (technology_id, candidate_id),
 CONSTRAINT fk_candidate_technologies_candidate FOREIGN KEY (candidate_id) REFERENCES candidate_profiles(user_id) ON DELETE CASCADE,
 CONSTRAINT fk_candidate_technologies_technology FOREIGN KEY (technology_id) REFERENCES technologies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO candidate_technologies (candidate_id, technology_id)
SELECT p.user_id, p.technology_id
FROM candidate_profiles p
JOIN technologies t ON t.id = p.technology_id
WHERE LOWER(TRIM(t.name)) <> 'general';

ALTER TABLE candidate_profiles
  MODIFY technology_id BIGINT UNSIGNED NULL;

UPDATE candidate_profiles SET technology_id = NULL;

UPDATE technologies t
LEFT JOIN interviews i ON i.technology_id = t.id
LEFT JOIN candidate_technologies ct ON ct.technology_id = t.id
SET t.active = 0
WHERE LOWER(TRIM(t.name)) = 'general'
  AND i.id IS NULL
  AND ct.candidate_id IS NULL;
