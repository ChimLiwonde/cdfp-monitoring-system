# CDFP Monitoring System

A multi-role PHP/MySQL web application for monitoring community development projects from proposal to approval, implementation, expenditure tracking, reporting, and citizen feedback.

This project was developed as an academic system with a strong real-world focus: improving visibility, accountability, and coordination around publicly funded projects. It brings together administrators, project leads, and community members in one centralized workflow instead of splitting them across disconnected tools.

## Project Overview

The CDFP Monitoring System was built to solve a common problem in public project delivery: project information is often scattered, approvals are hard to track, spending is not always transparent, and citizens have limited visibility into progress.

This system provides one platform where:

- project proposals can be submitted, reviewed, approved, denied, and tracked
- project managers and field officers can manage status items, tasks, expenditure, and internal collaboration
- administrators can monitor budgets, review requests, generate reports, and manage users
- citizens can view local projects, submit community requests, comment on projects, and receive replies

## Who Uses the System

### Admin

- Reviews submitted projects and community requests
- Approves or denies projects with review notes
- Manages users and roles
- Monitors dashboard metrics, notifications, and budget alerts
- Generates financial and progress reports
- Views complete project records, timelines, comments, and internal collaboration

### Project Lead

This includes both `Field Officer` and `Project Manager` roles.

- Creates and manages projects
- Adds project status/progress items
- Allocates stage budgets and records expenditure
- Assigns team members and tasks
- Uses the internal collaboration channel with admins
- Monitors project performance, finances, and progress

### Public User

- Creates an account and logs in through the same centralized system
- Views projects relevant to their district
- Submits comments and reactions on visible projects
- Sends community development requests
- Tracks replies, review notes, and notifications

## Core Features

### Centralized Role-Based Workflow

- Single login flow with automatic routing based on role
- Shared project records across admin, project-lead, and public views
- Consistent project IDs displayed across reports, details pages, and dashboards

### Project Lifecycle Management

- Project creation with location, description, budget, and document upload
- Admin review with approval, denial, and review-note support
- Activity history showing major workflow events
- Dedicated project detail pages for admin and project leads

### Monitoring and Progress Tracking

- Project status/progress items separated from expenditure tracking
- Dashboard summaries for pending, approved, denied, in-progress, and completed work
- Project-level timeline and monitoring views
- Visibility of project ownership and status changes

### Financial Management

- Separate expenditure ledger for project spending
- Budget allocation at status-item level
- Budget overrun protection for both stage allocation and expense entry
- Financial summaries shown on dashboards and project detail pages

### Task Planning and Resource Coordination

- Team member registration and task assignment
- Assignment notes linked to project status items
- Resource allocation visibility for project leads and admins
- Support for both field officers and project managers in the same workflow

### Collaboration and Communication

- Private internal collaboration channel for admins and project leads
- Public citizen feedback through project comments and replies
- In-app notifications for project reviews, updates, and responses
- Review notes on both projects and community requests

### Reporting

- Separate progress reports and financial reports
- Excel export
- PDF export
- Report views connected to the same project records and project IDs

## Technical Highlights

- Multi-role authentication and authorization
- Secure session handling with CSRF protection on write flows
- Protected project document uploads with validation and controlled access
- Input validation for coordinates and key workflow actions
- Notification system for cross-role updates
- Database migrations for finance, collaboration, notifications, roles, and integrity constraints
- Foreign key constraints and InnoDB-based integrity cleanup
- Responsive UI/UX redesign across dashboards, workflow pages, and detail views

## Tech Stack

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Local environment:** XAMPP
- **Email integration:** PHPMailer
- **PDF generation:** FPDF
- **Charts:** Chart.js
- **Maps:** Leaflet
- **Version control:** Git and GitHub

## What This Project Demonstrates

This project is a strong representation of my full-stack development skills. It demonstrates my ability to:

- design and build multi-role business workflows
- develop full-stack PHP/MySQL applications
- structure database changes through migration scripts
- implement access control, CSRF protection, session hardening, and safer file handling
- build reporting, notifications, and workflow dashboards
- separate financial tracking from project progress tracking
- improve UI/UX consistency across a legacy codebase
- work iteratively with feedback from review, testing, and supervisor comments

## Security and Engineering Improvements

Compared with the earlier version of the project, this repository now includes several important hardening and architecture improvements:

- removed hard-coded admin authentication in favor of database-backed role login
- protected sensitive actions with CSRF validation
- moved project documents to protected storage with validation for type and size
- tightened public comment/reaction authorization
- made password reset and delete flows safer
- added review notes and in-app notifications
- standardized secure session bootstrap across dashboards
- added database integrity constraints and foreign keys for core workflow tables

## Project Workflow Summary

1. A project lead creates a project proposal.
2. The admin reviews the submission and either approves or denies it.
3. Approved projects move into status tracking, budget allocation, and implementation monitoring.
4. Project leads manage expenditure, assignments, and internal collaboration during implementation.
5. Citizens view projects in their district, submit feedback, and receive replies.
6. Admins monitor system-wide progress through dashboards, alerts, reports, and detailed project records.

## Repository Structure

- `Pages/` - authentication and account pages
- `dashboards/` - role-based workflow pages and main application logic
- `config/` - shared helpers and local configuration templates
- `database/` - migration, reset, and demo SQL scripts
- `assets/` - CSS, images, PDF library assets
- `PHPMailer/` - mail library used by the system
- `private_uploads/` - protected project document storage

## Local Setup

1. Copy `config/db.example.php` to `config/db.php`.
2. Copy `config/mail.example.php` to `config/mail.php`.
3. Update both config files with your local database and mail credentials.
4. Create a MySQL database named `cdf_system`.
5. Import the base project database used for the academic system.
   The original full SQL dump is not committed to this repository because it contained real/local data.
6. Apply the tracked migration scripts in the `database/` folder if they are not already included in your local database.
7. Run the project through XAMPP or another PHP/MySQL local server.
8. Open `index.php` or browse to your local app URL.

For additional database notes, see [database/README.md](database/README.md).

## Demo and Helper Scripts

- `database/reset_demo_data.sql`
  Resets projects, requests, comments, collaboration messages, notifications, and related workflow records while keeping user accounts and roles.

- `database/add_project_activity_log.sql`
  Adds workflow history for project approval and status transitions.

- `database/add_finance_and_task_tables.sql`
  Adds expenditure tracking, team members, and task assignment tables.

- `database/add_internal_collaboration.sql`
  Adds the private collaboration channel used by admins and project leads.

- `database/add_project_manager_role.sql`
  Adds support for the `project_manager` role.

- `database/add_notifications_and_review_notes.sql`
  Adds in-app notifications and review-note support.

- `database/add_core_integrity_constraints.sql`
  Adds core foreign keys and integrity cleanup for the evolved workflow.

- `database/seed_supervisor_demo.sql`
  Loads a clean demo dataset suitable for supervisor presentation after a reset.

## Current Status

This project is currently in a strong **feature-complete MVP** state for academic presentation and portfolio use.

Implemented areas include:

- centralized multi-role access
- project approval workflow
- monitoring and progress tracking
- financial tracking and budget controls
- task assignment and collaboration
- reporting and export
- notifications and review notes
- security hardening
- polished UI/UX

## Future Improvements

Possible next steps beyond the current academic/portfolio scope:

- richer analytics and KPI trends
- calendar or Gantt-style scheduling views
- deployment to a live hosting environment
- automated testing
- screenshot gallery or short demo video in the repository

## Portfolio Note

If you are reviewing this repository as an employer, supervisor, or collaborator, the key takeaway is that this project showcases end-to-end ownership of a non-trivial information system: requirements interpretation, workflow design, role-based access, database evolution, reporting, security improvement, and UI/UX refinement inside an existing PHP/MySQL codebase.
