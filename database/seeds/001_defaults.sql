INSERT INTO resources (name, resource_type) VALUES ('Tool PC 1','TOOL'),('Tool PC 2','TOOL'),('Normal PC 1','NORMAL'),('Normal PC 2','NORMAL');
INSERT INTO support_people (name) VALUES ('Hari'),('Arjun'),('Sahithi'),('Sreekanth');
INSERT INTO business_hours (weekday,is_open,opens_at,closes_at) VALUES (1,1,'10:00','19:00'),(2,1,'10:00','19:00'),(3,1,'10:00','19:00'),(4,1,'10:00','19:00'),(5,1,'10:00','19:00'),(6,1,'10:00','19:00'),(7,0,'10:00','19:00');
INSERT INTO app_settings (setting_key,setting_value) VALUES ('business_timezone','Asia/Kolkata'),('advance_booking_minutes','60'),('candidate_change_cutoff_minutes','60');
INSERT INTO scheduling_mutex (id) VALUES (1);
