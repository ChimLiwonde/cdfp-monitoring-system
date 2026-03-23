# CDFP Monitoring System

PHP-based monitoring system for CDF projects, with public, field officer, and admin workflows.

## Local setup

1. Copy `config/db.example.php` to `config/db.php`.
2. Copy `config/mail.example.php` to `config/mail.php`.
3. Update both config files with your local credentials.
4. Create a MySQL database named `cdf_system`.
5. Import your local SQL dump into that database.
6. Serve the project with PHP/XAMPP and open `index.php`.

## Demo scripts

- `database/reset_demo_data.sql` clears project/dashboard demo records while keeping the user accounts and roles.
- `database/add_project_activity_log.sql` adds the workflow history table used by the admin project details page.
- `database/add_finance_and_task_tables.sql` adds the expenditure ledger and task-assignment tables used by the newer project workflow pages.
- `database/add_internal_collaboration.sql` adds the private admin/field-officer collaboration channel used for internal project communication.
- `database/seed_supervisor_demo.sql` loads a small supervisor-friendly sample dataset with one pending, one approved, and one denied project tied to the same field officer.

## Notes

- Live config files are ignored so secrets stay local.
- Uploaded files are ignored except for `uploads/.gitkeep`.
- The original database dump is not tracked because it contains real data.
