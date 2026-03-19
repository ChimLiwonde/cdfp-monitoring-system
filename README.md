# CDFP Monitoring System

PHP-based monitoring system for CDF projects, with public, field officer, and admin workflows.

## Local setup

1. Copy `config/db.example.php` to `config/db.php`.
2. Copy `config/mail.example.php` to `config/mail.php`.
3. Update both config files with your local credentials.
4. Create a MySQL database named `cdf_system`.
5. Import your local SQL dump into that database.
6. Serve the project with PHP/XAMPP and open `index.php`.

## Notes

- Live config files are ignored so secrets stay local.
- Uploaded files are ignored except for `uploads/.gitkeep`.
- The original database dump is not tracked because it contains real data.
