ALTER TABLE `projects`
MODIFY `status` enum('pending','approved','in_progress','completed','denied') DEFAULT 'pending';

UPDATE `projects`
SET `status` = 'pending'
WHERE `status` = '' OR `status` IS NULL;

CREATE TABLE IF NOT EXISTS `project_expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `stage_id` int NOT NULL,
  `expense_title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `notes` text,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_expenses_project` (`project_id`),
  KEY `idx_project_expenses_stage` (`stage_id`),
  KEY `idx_project_expenses_recorded_by` (`recorded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `project_team_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role_title` varchar(150) NOT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_team_members_project` (`project_id`),
  KEY `idx_project_team_members_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `project_stage_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stage_id` int NOT NULL,
  `team_member_id` int NOT NULL,
  `assigned_by` int DEFAULT NULL,
  `assignment_notes` text,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_stage_member` (`stage_id`, `team_member_id`),
  KEY `idx_project_stage_assignments_stage` (`stage_id`),
  KEY `idx_project_stage_assignments_member` (`team_member_id`),
  KEY `idx_project_stage_assignments_assigned_by` (`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
