-- Google Sign-In: stable Google account ID (the "sub" of the Google identity) linked to a candidate.
ALTER TABLE users
  ADD COLUMN google_sub VARCHAR(128) NULL AFTER username,
  ADD UNIQUE INDEX uniq_users_google_sub (google_sub);
