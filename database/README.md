This folder is for local database exports and setup files.

The original SQL dump was intentionally left out of Git because it contains real application data.

For local setup:

1. Create a MySQL database named `cdf_system`.
2. Import your local SQL dump into that database.
3. Copy `config/db.example.php` to `config/db.php` and update the connection details if needed.

Included helper scripts:

1. `add_project_activity_log.sql`
   Adds the workflow history table used to show project approval and status changes.
2. `add_finance_and_task_tables.sql`
   Adds the expenditure ledger, project team members, and task-assignment tables used by the finance and task planning pages.
3. `add_internal_collaboration.sql`
   Adds the private project collaboration channel for internal admin and project-lead communication.
4. `add_project_manager_role.sql`
   Extends the `users.role` enum so the shared project workflow can support both field officers and project managers.
5. `add_notifications_and_review_notes.sql`
   Adds in-app user notifications plus admin review-note fields for projects and community requests.
6. `reset_demo_data.sql`
   Resets projects, project status items, comments, requests, maps, contractors, and activity history back to zero while keeping users.
7. `seed_supervisor_demo.sql`
   Loads a small demo dataset for supervisor review after a clean reset.
