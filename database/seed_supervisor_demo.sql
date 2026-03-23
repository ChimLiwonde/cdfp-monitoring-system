-- Optional supervisor demo seed.
-- Run this only when you want sample records after a clean reset.

SET @field_officer_id = (
    SELECT id
    FROM users
    WHERE role = 'field_officer'
    ORDER BY id
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

INSERT INTO project_comments (project_id, user_id, comment, admin_reply, replied_at)
VALUES
(@approved_project_id, @public_user_id, 'We are happy to see this project progressing.', 'Thank you. Progress updates will continue to be shared here.', NOW());

INSERT INTO community_requests (user_id, district, area, title, description, status)
VALUES
(@public_user_id, 'Blantyre', 'Soche', 'Request for drainage improvement', 'Please consider drainage improvement near the market.', 'pending');

INSERT INTO project_activity_log (project_id, event_type, actor_id, actor_role, old_status, new_status, notes)
VALUES
(@pending_project_id, 'project_created', @field_officer_id, 'field_officer', NULL, 'pending', 'Project created and submitted for admin review.'),
(@approved_project_id, 'project_created', @field_officer_id, 'field_officer', NULL, 'pending', 'Project created and submitted for admin review.'),
(@approved_project_id, 'project_status_changed', @admin_user_id, 'admin', 'pending', 'approved', 'Approved by admin during demo setup.'),
(@approved_project_id, 'status_item_added', @field_officer_id, 'field_officer', NULL, 'pending', 'Initial project status items added.'),
(@approved_project_id, 'stage_status_changed', @field_officer_id, 'field_officer', 'pending', 'completed', 'Site Preparation marked completed.'),
(@approved_project_id, 'stage_status_changed', @field_officer_id, 'field_officer', 'pending', 'in_progress', 'Drilling and Pump Installation started.'),
(@denied_project_id, 'project_created', @field_officer_id, 'field_officer', NULL, 'pending', 'Project created and submitted for admin review.'),
(@denied_project_id, 'project_status_changed', @admin_user_id, 'admin', 'pending', 'denied', 'Denied during demo setup.');
