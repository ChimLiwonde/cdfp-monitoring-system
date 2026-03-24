ALTER TABLE projects
ADD COLUMN IF NOT EXISTS review_notes TEXT NULL AFTER status,
ADD COLUMN IF NOT EXISTS reviewed_by INT NULL AFTER review_notes,
ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER reviewed_by;

ALTER TABLE community_requests
ADD COLUMN IF NOT EXISTS review_notes TEXT NULL AFTER status,
ADD COLUMN IF NOT EXISTS reviewed_by INT NULL AFTER review_notes,
ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER reviewed_by;

CREATE TABLE IF NOT EXISTS user_notifications (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  notification_type VARCHAR(60) NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notification_user (user_id),
  KEY idx_notification_read (user_id, is_read),
  KEY idx_notification_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
