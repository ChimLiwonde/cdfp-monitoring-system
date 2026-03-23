-- Optional supervisor demo seed.
-- Run this only when you want sample records after a clean reset.

SET @field_officer_id = (
    SELECT id
    FROM users
    WHERE role IN ('project_manager', 'field_officer')
    ORDER BY FIELD(role, 'project_manager', 'field_officer'), id
    LIMIT 1
);

SET @field_officer_role = (
    SELECT role
    FROM users
    WHERE id = @field_officer_id
    LIMIT 1
);

SET @public_user_id = (
    SELECT id
    FROM users
    WHERE role = 'public'
    ORDER BY id
    LIMIT 1
);

SET @admin_user_id = (
    SELECT id
    FROM users
    WHERE role = 'admin'
    ORDER BY id
    LIMIT 1
);

INSERT INTO projects (title, description, district, location, estimated_budget, contractor_fee, document, status, created_by)
VALUES
('Ndirande School Block', 'Construction of a classroom block for Ndirande community.', 'Blantyre', 'Ndirande', 2500000.00, 450000.00, '', 'pending', @field_officer_id),
('Chilomoni Borehole', 'Water access improvement project for Chilomoni area.', 'Blantyre', 'Chilomoni', 1800000.00, 250000.00, '', 'approved', @field_officer_id),
('Kameza Road Repair', 'Road rehabilitation pilot for Kameza ward.', 'Blantyre', 'Kameza', 3200000.00, 500000.00, '', 'denied', @field_officer_id);

SET @pending_project_id = (
    SELECT id
    FROM projects
    WHERE title = 'Ndirande School Block' AND created_by = @field_officer_id
    ORDER BY id DESC
    LIMIT 1
);

SET @approved_project_id = (
    SELECT id
    FROM projects
    WHERE title = 'Chilomoni Borehole' AND created_by = @field_officer_id
    ORDER BY id DESC
    LIMIT 1
);

SET @denied_project_id = (
    SELECT id
    FROM projects
    WHERE title = 'Kameza Road Repair' AND created_by = @field_officer_id
    ORDER BY id DESC
    LIMIT 1
);

INSERT INTO project_maps (project_id, latitude, longitude)
VALUES
(@pending_project_id, '-15.7900', '35.0050'),
(@approved_project_id, '-15.7780', '35.0180'),
(@denied_project_id, '-15.7700', '35.0400');

INSERT INTO project_stages (project_id, stage_name, planned_start, planned_end, actual_start, actual_end, allocated_budget, spent_budget, status, notes)
VALUES
(@approved_project_id, 'Site Preparation', '2026-03-01', '2026-03-10', '2026-03-02', '2026-03-09', 1025000.00, 890000.00, 'completed', 'Completed within planned budget.'),
(@approved_project_id, 'Drilling and Pump Installation', '2026-03-11', '2026-03-25', '2026-03-12', NULL, 1025000.00, 540000.00, 'in_progress', 'Work is progressing well.');

SET @site_stage_id = (
    SELECT id
    FROM project_stages
    WHERE project_id = @approved_project_id AND stage_name = 'Site Preparation'
    ORDER BY id DESC
    LIMIT 1
);

SET @drilling_stage_id = (
    SELECT id
    FROM project_stages
    WHERE project_id = @approved_project_id AND stage_name = 'Drilling and Pump Installation'
    ORDER BY id DESC
    LIMIT 1
);

INSERT INTO project_team_members (project_id, full_name, role_title, contact_info, created_by)
VALUES
(@approved_project_id, 'Grace Banda', 'Site Supervisor', '0888 100 200', @field_officer_id),
(@approved_project_id, 'Peter Mbewe', 'Procurement Officer', '0999 300 400', @field_officer_id);

SET @grace_member_id = (
    SELECT id
    FROM project_team_members
    WHERE project_id = @approved_project_id AND full_name = 'Grace Banda'
    ORDER BY id DESC
    LIMIT 1
);

SET @peter_member_id = (
    SELECT id
    FROM project_team_members
    WHERE project_id = @approved_project_id AND full_name = 'Peter Mbewe'
    ORDER BY id DESC
    LIMIT 1
);

INSERT INTO project_stage_assignments (stage_id, team_member_id, assigned_by, assignment_notes)
VALUES
(@site_stage_id, @grace_member_id, @field_officer_id, 'Coordinate early site works and daily progress updates.'),
(@drilling_stage_id, @peter_member_id, @field_officer_id, 'Manage supplier follow-ups and borehole equipment delivery.');

INSERT INTO project_expenses (project_id, stage_id, expense_title, category, vendor_name, amount, expense_date, notes, recorded_by)
VALUES
(@approved_project_id, @site_stage_id, 'Site clearing materials', 'Materials', 'BuildRight Supplies', 540000.00, '2026-03-04', 'Materials purchased for preparing the site.', @field_officer_id),
(@approved_project_id, @site_stage_id, 'Site preparation labour', 'Labour', 'Community Labour Group', 350000.00, '2026-03-06', 'Labour costs for the first completed status item.', @field_officer_id),
(@approved_project_id, @drilling_stage_id, 'Initial drilling mobilisation', 'Equipment', 'AquaTech Drillers', 540000.00, '2026-03-15', 'Mobilisation and drilling setup costs.', @field_officer_id);

INSERT INTO project_comments (project_id, user_id, comment, admin_reply, replied_at)
VALUES
(@approved_project_id, @public_user_id, 'We are happy to see this project progressing.', 'Thank you. Progress updates will continue to be shared here.', NOW());

INSERT INTO community_requests (user_id, district, area, title, description, status)
VALUES
(@public_user_id, 'Blantyre', 'Soche', 'Request for drainage improvement', 'Please consider drainage improvement near the market.', 'pending');

INSERT INTO project_collaboration_messages (project_id, sender_id, sender_role, message)
VALUES
(@approved_project_id, @field_officer_id, @field_officer_role, 'Site update: preparation is complete and drilling equipment has arrived.'),
(@approved_project_id, @admin_user_id, 'admin', 'Received. Keep budget updates flowing through the financial report and flag any overruns early.');

INSERT INTO project_activity_log (project_id, event_type, actor_id, actor_role, old_status, new_status, notes)
VALUES
(@pending_project_id, 'project_created', @field_officer_id, @field_officer_role, NULL, 'pending', 'Project created and submitted for admin review.'),
(@approved_project_id, 'project_created', @field_officer_id, @field_officer_role, NULL, 'pending', 'Project created and submitted for admin review.'),
(@approved_project_id, 'project_status_changed', @admin_user_id, 'admin', 'pending', 'approved', 'Approved by admin during demo setup.'),
(@approved_project_id, 'status_item_added', @field_officer_id, @field_officer_role, NULL, 'pending', 'Initial project status items added.'),
(@approved_project_id, 'team_member_added', @field_officer_id, @field_officer_role, NULL, NULL, 'Grace Banda added to the project team.'),
(@approved_project_id, 'team_member_added', @field_officer_id, @field_officer_role, NULL, NULL, 'Peter Mbewe added to the project team.'),
(@approved_project_id, 'task_assigned', @field_officer_id, @field_officer_role, NULL, NULL, 'Site Preparation assigned to Grace Banda.'),
(@approved_project_id, 'task_assigned', @field_officer_id, @field_officer_role, NULL, NULL, 'Drilling and Pump Installation assigned to Peter Mbewe.'),
(@approved_project_id, 'expense_recorded', @field_officer_id, @field_officer_role, NULL, NULL, 'Initial expenses recorded for approved project status items.'),
(@approved_project_id, 'collaboration_message_posted', @field_officer_id, @field_officer_role, NULL, NULL, 'Internal collaboration started for project progress updates.'),
(@approved_project_id, 'stage_status_changed', @field_officer_id, @field_officer_role, 'pending', 'completed', 'Site Preparation marked completed.'),
(@approved_project_id, 'stage_status_changed', @field_officer_id, @field_officer_role, 'pending', 'in_progress', 'Drilling and Pump Installation started.'),
(@denied_project_id, 'project_created', @field_officer_id, @field_officer_role, NULL, 'pending', 'Project created and submitted for admin review.'),
(@denied_project_id, 'project_status_changed', @admin_user_id, 'admin', 'pending', 'denied', 'Denied during demo setup.');
