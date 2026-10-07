ALTER TABLE audit_logs
  DROP FOREIGN KEY audit_logs_ibfk_1,
  ADD CONSTRAINT fk_audit_logs_actor
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL;
