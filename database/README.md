This folder is for local database exports and setup files.

The original SQL dump was intentionally left out of Git because it contains real application data.

For local setup:

1. Create a MySQL database named `cdf_system`.
2. Import your local SQL dump into that database.
3. Copy `config/db.example.php` to `config/db.php` and update the connection details if needed.

Included helper scripts:

1. `add_project_activity_log.sql`
   Adds the workflow history table used to show project approval and status changes.
2. `reset_demo_data.sql`
   Resets projects, project status items, comments, requests, maps, contractors, and activity history back to zero while keeping users.
3. `seed_supervisor_demo.sql`
   Loads a small demo dataset for supervisor review after a clean reset.
