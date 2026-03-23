ALTER TABLE users
MODIFY COLUMN role ENUM('public', 'field_officer', 'project_manager', 'admin') NOT NULL;
