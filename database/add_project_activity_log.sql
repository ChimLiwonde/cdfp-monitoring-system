CREATE TABLE IF NOT EXISTS `project_activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `actor_id` int DEFAULT NULL,
  `actor_role` varchar(50) DEFAULT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_project` (`project_id`),
  KEY `idx_activity_event` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
