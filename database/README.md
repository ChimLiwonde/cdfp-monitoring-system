This folder is for local database exports and setup files.

The original SQL dump was intentionally left out of Git because it contains real application data.

For local setup:

1. Create a MySQL database named `cdf_system`.
2. Import your local SQL dump into that database.
3. Copy `config/db.example.php` to `config/db.php` and update the connection details if needed.
