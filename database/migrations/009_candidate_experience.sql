-- Candidate experience: total years on the profile and years per tech-stack skill (searchable, e.g. "Gen AI 2+").
ALTER TABLE candidate_profiles
  ADD COLUMN experience_years DECIMAL(4,1) NULL AFTER classification;
ALTER TABLE candidate_technologies
  ADD COLUMN years_experience DECIMAL(4,1) NULL AFTER technology_id,
  ADD INDEX idx_candidate_technologies_experience (technology_id, years_experience);
