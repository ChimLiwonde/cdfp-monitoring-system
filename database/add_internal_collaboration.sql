CREATE TABLE IF NOT EXISTS `project_collaboration_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `sender_id` int NOT NULL,
  `sender_role` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_collaboration_project` (`project_id`),
  KEY `idx_project_collaboration_sender` (`sender_id`),
  KEY `idx_project_collaboration_role` (`sender_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
