ALTER TABLE interviews
  ADD COLUMN candidate_reference_id BIGINT UNSIGNED NULL AFTER candidate_id,
  ADD COLUMN candidate_name_snapshot VARCHAR(120) NULL AFTER candidate_reference_id,
  ADD COLUMN candidate_classification_snapshot ENUM('Genuine','Fake') NULL AFTER candidate_name_snapshot;

UPDATE interviews i
JOIN users u ON u.id = i.candidate_id
JOIN candidate_profiles p ON p.user_id = i.candidate_id
SET i.candidate_reference_id = i.candidate_id,
    i.candidate_name_snapshot = u.full_name,
    i.candidate_classification_snapshot = p.classification;

ALTER TABLE interviews
  DROP FOREIGN KEY interviews_ibfk_1;

ALTER TABLE interviews
  MODIFY candidate_id BIGINT UNSIGNED NULL,
  MODIFY candidate_reference_id BIGINT UNSIGNED NOT NULL,
  MODIFY candidate_name_snapshot VARCHAR(120) NOT NULL,
  MODIFY candidate_classification_snapshot ENUM('Genuine','Fake') NOT NULL,
  ADD INDEX idx_interviews_candidate_reference (candidate_reference_id),
  ADD CONSTRAINT fk_interviews_candidate
    FOREIGN KEY (candidate_id) REFERENCES candidate_profiles(user_id) ON DELETE SET NULL;
