ALTER TABLE users
  ADD INDEX idx_users_candidate_name (role, full_name);

ALTER TABLE interviews
  ADD INDEX idx_interviews_company (company_name),
  ADD INDEX idx_interviews_report_dimensions (start_time, interview_type, technology_id, status);

ALTER TABLE audit_logs
  ADD INDEX idx_audit_timeline (created_at, id),
  ADD INDEX idx_audit_actor_timeline (actor_id, created_at, id),
  ADD INDEX idx_audit_action_timeline (action, created_at, id);
