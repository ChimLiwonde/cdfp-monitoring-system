-- Reset dashboard-related demo data while keeping established user roles/accounts.
-- Run this after importing the schema or against an existing cdf_system database.

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM comment_reactions;
DELETE FROM project_comments;
DELETE FROM project_maps;
DELETE FROM project_stage_assignments;
DELETE FROM project_team_members;
DELETE FROM project_expenses;
DELETE FROM project_collaboration_messages;
DELETE FROM project_stages;
DELETE FROM project_activity_log;
DELETE FROM contractor_projects;
DELETE FROM contractors;
DELETE FROM community_requests;
DELETE FROM projects;

ALTER TABLE comment_reactions AUTO_INCREMENT = 1;
ALTER TABLE project_comments AUTO_INCREMENT = 1;
ALTER TABLE project_maps AUTO_INCREMENT = 1;
ALTER TABLE project_stage_assignments AUTO_INCREMENT = 1;
ALTER TABLE project_team_members AUTO_INCREMENT = 1;
ALTER TABLE project_expenses AUTO_INCREMENT = 1;
ALTER TABLE project_collaboration_messages AUTO_INCREMENT = 1;
ALTER TABLE project_stages AUTO_INCREMENT = 1;
ALTER TABLE project_activity_log AUTO_INCREMENT = 1;
ALTER TABLE contractor_projects AUTO_INCREMENT = 1;
ALTER TABLE contractors AUTO_INCREMENT = 1;
ALTER TABLE community_requests AUTO_INCREMENT = 1;
ALTER TABLE projects AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Optional check queries
SELECT 'projects' AS table_name, COUNT(*) AS total FROM projects
UNION ALL
SELECT 'project_stages', COUNT(*) FROM project_stages
UNION ALL
SELECT 'project_comments', COUNT(*) FROM project_comments
UNION ALL
SELECT 'comment_reactions', COUNT(*) FROM comment_reactions
UNION ALL
SELECT 'project_stage_assignments', COUNT(*) FROM project_stage_assignments
UNION ALL
SELECT 'project_team_members', COUNT(*) FROM project_team_members
UNION ALL
SELECT 'project_expenses', COUNT(*) FROM project_expenses
UNION ALL
SELECT 'project_collaboration_messages', COUNT(*) FROM project_collaboration_messages
UNION ALL
SELECT 'project_activity_log', COUNT(*) FROM project_activity_log
UNION ALL
SELECT 'community_requests', COUNT(*) FROM community_requests
UNION ALL
SELECT 'contractor_projects', COUNT(*) FROM contractor_projects
UNION ALL
SELECT 'contractors', COUNT(*) FROM contractors
UNION ALL
SELECT 'project_maps', COUNT(*) FROM project_maps;
