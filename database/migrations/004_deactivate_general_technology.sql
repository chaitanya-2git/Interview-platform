UPDATE technologies
SET active = 0
WHERE LOWER(TRIM(name)) = 'general';
