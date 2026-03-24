-- Move legacy core tables to InnoDB and add foreign-key integrity for the
-- current workflow. Run this after the newer feature migrations so the
-- referenced tables already exist. This script is designed to be safe to rerun.

SET @schema_name = DATABASE();
SET FOREIGN_KEY_CHECKS = 0;

-- Clean orphaned legacy rows before constraints are added.
DELETE cr
FROM comment_reactions cr
LEFT JOIN project_comments pc ON pc.id = cr.comment_id
LEFT JOIN users u ON u.id = cr.user_id
WHERE pc.id IS NULL OR u.id IS NULL;

DELETE pc
FROM project_comments pc
LEFT JOIN projects p ON p.id = pc.project_id
LEFT JOIN users u ON u.id = pc.user_id
WHERE p.id IS NULL OR u.id IS NULL;

DELETE pm
FROM project_maps pm
LEFT JOIN projects p ON p.id = pm.project_id
WHERE p.id IS NULL;

DELETE ps
FROM project_stages ps
LEFT JOIN projects p ON p.id = ps.project_id
WHERE p.id IS NULL;

DELETE cp
FROM contractor_projects cp
LEFT JOIN contractors c ON c.id = cp.contractor_id
LEFT JOIN projects p ON p.id = cp.project_id
WHERE (cp.contractor_id IS NOT NULL AND c.id IS NULL)
   OR (cp.project_id IS NOT NULL AND p.id IS NULL);

DELETE p
FROM projects p
LEFT JOIN users u ON u.id = p.created_by
WHERE u.id IS NULL;

DELETE crq
FROM community_requests crq
LEFT JOIN users u ON u.id = crq.user_id
WHERE u.id IS NULL;

UPDATE projects p
LEFT JOIN users reviewer ON reviewer.id = p.reviewed_by
SET p.reviewed_by = NULL
WHERE p.reviewed_by IS NOT NULL AND reviewer.id IS NULL;

UPDATE community_requests crq
LEFT JOIN users reviewer ON reviewer.id = crq.reviewed_by
SET crq.reviewed_by = NULL
WHERE crq.reviewed_by IS NOT NULL AND reviewer.id IS NULL;

UPDATE contractors c
LEFT JOIN users u ON u.id = c.created_by
SET c.created_by = NULL
WHERE c.created_by IS NOT NULL AND u.id IS NULL;

UPDATE contractor_projects cp
LEFT JOIN users u ON u.id = cp.assigned_by
SET cp.assigned_by = NULL
WHERE cp.assigned_by IS NOT NULL AND u.id IS NULL;

UPDATE project_expenses pe
LEFT JOIN users u ON u.id = pe.recorded_by
SET pe.recorded_by = NULL
WHERE pe.recorded_by IS NOT NULL AND u.id IS NULL;

UPDATE project_team_members ptm
LEFT JOIN users u ON u.id = ptm.created_by
SET ptm.created_by = NULL
WHERE ptm.created_by IS NOT NULL AND u.id IS NULL;

UPDATE project_stage_assignments psa
LEFT JOIN users u ON u.id = psa.assigned_by
SET psa.assigned_by = NULL
WHERE psa.assigned_by IS NOT NULL AND u.id IS NULL;

UPDATE project_activity_log pal
LEFT JOIN users u ON u.id = pal.actor_id
SET pal.actor_id = NULL
WHERE pal.actor_id IS NOT NULL AND u.id IS NULL;

-- Convert legacy core tables to InnoDB for transaction and FK support.
ALTER TABLE comment_reactions ENGINE=InnoDB;
ALTER TABLE community_requests ENGINE=InnoDB;
ALTER TABLE contractors ENGINE=InnoDB;
ALTER TABLE contractor_projects ENGINE=InnoDB;
ALTER TABLE projects ENGINE=InnoDB;
ALTER TABLE project_comments ENGINE=InnoDB;
ALTER TABLE project_maps ENGINE=InnoDB;
ALTER TABLE project_stages ENGINE=InnoDB;

-- Align the legacy reaction uniqueness rule with the current app behavior.
SET @drop_old_reaction_key = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema_name
              AND table_name = 'comment_reactions'
              AND index_name = 'comment_id'
        ),
        'ALTER TABLE comment_reactions DROP INDEX comment_id',
        'SELECT 1'
    )
);
PREPARE stmt FROM @drop_old_reaction_key;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_reaction_index = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = @schema_name
              AND table_name = 'comment_reactions'
              AND index_name = 'uq_comment_reaction'
        ),
        'SELECT 1',
        'ALTER TABLE comment_reactions ADD UNIQUE KEY uq_comment_reaction (comment_id, user_id, emoji)'
    )
);
PREPARE stmt FROM @add_reaction_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign keys only when they do not already exist.
SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_projects_created_by'),
        'SELECT 1',
        'ALTER TABLE projects ADD CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_projects_reviewed_by'),
        'SELECT 1',
        'ALTER TABLE projects ADD CONSTRAINT fk_projects_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_community_requests_user'),
        'SELECT 1',
        'ALTER TABLE community_requests ADD CONSTRAINT fk_community_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_community_requests_reviewed_by'),
        'SELECT 1',
        'ALTER TABLE community_requests ADD CONSTRAINT fk_community_requests_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_contractors_created_by'),
        'SELECT 1',
        'ALTER TABLE contractors ADD CONSTRAINT fk_contractors_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_contractor_projects_contractor'),
        'SELECT 1',
        'ALTER TABLE contractor_projects ADD CONSTRAINT fk_contractor_projects_contractor FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_contractor_projects_project'),
        'SELECT 1',
        'ALTER TABLE contractor_projects ADD CONSTRAINT fk_contractor_projects_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_contractor_projects_assigned_by'),
        'SELECT 1',
        'ALTER TABLE contractor_projects ADD CONSTRAINT fk_contractor_projects_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_maps_project'),
        'SELECT 1',
        'ALTER TABLE project_maps ADD CONSTRAINT fk_project_maps_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_stages_project'),
        'SELECT 1',
        'ALTER TABLE project_stages ADD CONSTRAINT fk_project_stages_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_comments_project'),
        'SELECT 1',
        'ALTER TABLE project_comments ADD CONSTRAINT fk_project_comments_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_comments_user'),
        'SELECT 1',
        'ALTER TABLE project_comments ADD CONSTRAINT fk_project_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_comment_reactions_comment'),
        'SELECT 1',
        'ALTER TABLE comment_reactions ADD CONSTRAINT fk_comment_reactions_comment FOREIGN KEY (comment_id) REFERENCES project_comments(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_comment_reactions_user'),
        'SELECT 1',
        'ALTER TABLE comment_reactions ADD CONSTRAINT fk_comment_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_expenses_project'),
        'SELECT 1',
        'ALTER TABLE project_expenses ADD CONSTRAINT fk_project_expenses_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_expenses_stage'),
        'SELECT 1',
        'ALTER TABLE project_expenses ADD CONSTRAINT fk_project_expenses_stage FOREIGN KEY (stage_id) REFERENCES project_stages(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_expenses_recorded_by'),
        'SELECT 1',
        'ALTER TABLE project_expenses ADD CONSTRAINT fk_project_expenses_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_team_members_project'),
        'SELECT 1',
        'ALTER TABLE project_team_members ADD CONSTRAINT fk_project_team_members_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_team_members_created_by'),
        'SELECT 1',
        'ALTER TABLE project_team_members ADD CONSTRAINT fk_project_team_members_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_stage_assignments_stage'),
        'SELECT 1',
        'ALTER TABLE project_stage_assignments ADD CONSTRAINT fk_project_stage_assignments_stage FOREIGN KEY (stage_id) REFERENCES project_stages(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_stage_assignments_member'),
        'SELECT 1',
        'ALTER TABLE project_stage_assignments ADD CONSTRAINT fk_project_stage_assignments_member FOREIGN KEY (team_member_id) REFERENCES project_team_members(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_stage_assignments_assigned_by'),
        'SELECT 1',
        'ALTER TABLE project_stage_assignments ADD CONSTRAINT fk_project_stage_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_collaboration_messages_project'),
        'SELECT 1',
        'ALTER TABLE project_collaboration_messages ADD CONSTRAINT fk_project_collaboration_messages_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_collaboration_messages_sender'),
        'SELECT 1',
        'ALTER TABLE project_collaboration_messages ADD CONSTRAINT fk_project_collaboration_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_activity_log_project'),
        'SELECT 1',
        'ALTER TABLE project_activity_log ADD CONSTRAINT fk_project_activity_log_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_project_activity_log_actor'),
        'SELECT 1',
        'ALTER TABLE project_activity_log ADD CONSTRAINT fk_project_activity_log_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = @schema_name AND constraint_name = 'fk_user_notifications_user'),
        'SELECT 1',
        'ALTER TABLE user_notifications ADD CONSTRAINT fk_user_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE'
    )
);
PREPARE stmt FROM @fk_sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
