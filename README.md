# Intelligent Physical File Tracking System

A document/file registry and transfer management web application for tracking the custody of physical paper files across departments in an organization.

## Project Overview

Organizations that handle physical documents often lose track of where a file is, who holds it, and whether it has been returned on time. This application provides a central registry for official files and a structured workflow for moving them between departments, so every hand-off is recorded, acknowledged, and auditable.

The system is built as a Laravel API backend with a Next.js frontend. It supports role-based access control, file registration, department management, document transfers with acknowledgement/rejection, due dates and overdue detection, issue reporting, notifications, user management, and a full audit trail.

## Core Features

- **Authentication** — Login, logout, and current-user retrieval using Laravel Sanctum token authentication.
- **Role-based access control** — Server-side authorization enforced with Laravel Gates.
- **File/document registry** — List, register, view, search, and filter files.
- **File registration and tracking** — Each file has a unique file number, a title, a category, a confirmed department and holder, and a status.
- **Department management** — List departments and manage a hierarchical (parent/child) department structure.
- **Document transfers** — Move a file from one department/holder to another, with custody tracking.
- **Transfer acknowledgement/rejection** — Recipients can accept or reject an incoming transfer.
- **Due dates and overdue transfers** — Transfers carry a due date; overdue transfers are detected and flagged.
- **Issue reporting and issue status management** — Report issues against files and transition them through a defined lifecycle.
- **Notifications** — Generated for transfer, overdue, and issue events, with a notification UI.
- **Audit logging** — Records important actions across the application.
- **User management** — Admins can list, create, edit, and deactivate users, and reset passwords.
- **User profile management** — Users can edit their own name/email and change their password.
- **Admin password reset** — Admins can reset another user's password without exposing it.
- **Department creation/editing** — Admins can create and edit departments.
- **Search/filter functionality** — Files, transfers, and issues support search and status/category/department filters.

## User Roles

The application defines four roles:

| Role | Description |
| --- | --- |
| `admin` | Full administrative control over users, departments, and organization data. |
| `registry_staff` | Manages the file registry and initiates transfers. |
| `department_staff` | Works with files within their department and can acknowledge/reject transfers addressed to them. |
| `supervisor` | Can initiate transfers, act on transfers, and manage issue statuses. |

### Permissions

The following permissions reflect the actual server-side authorization implemented in the backend:

- **Admins** can manage users (create, edit, deactivate, reset passwords) and manage departments (create, edit).
- **Admins and registry staff** can register files.
- **Admins, registry staff, and supervisors** can create transfers.
- **Transfer acknowledgement/rejection** is allowed for the transfer's intended holder, admins, and supervisors.
- **Issue status changes** are allowed for admins, registry staff, and supervisors.
- **Issue reporting** is available to any authenticated user.
- **Department creation/editing** is restricted to admins.

Non-admins receive server-side `403` responses for admin-only operations.

## Main Workflow

The intended document lifecycle is:

1. **File registration** — A file is registered in the registry with a file number, title, category, and confirmed department/holder.
2. **Transfer** — The file is transferred to another department/holder, with a due date.
3. **Acknowledgement/rejection** — The recipient acknowledges (accepts) or rejects the transfer.
4. **Issue reporting** — If a problem arises, an issue can be reported against the file and tracked through its lifecycle.
5. **Notifications** — Transfer, overdue, and issue events generate notifications for the relevant users.
6. **Audit trail** — Every step is recorded in the audit log.

### Overdue-transfer workflow

Each transfer carries a due date. A scheduled/on-demand check detects transfers that are still pending past their due date and flags them as overdue. Overdue transfers are surfaced in the UI and generate overdue notifications so they can be followed up.

## Technology Stack

**Backend**
- Laravel 9 / PHP API
- Laravel Sanctum token authentication
- MySQL for local development
- SQLite in-memory for automated backend tests
- PHPUnit feature tests

**Frontend**
- Next.js 16
- React 19
- TypeScript
- Tailwind CSS

## Project Structure

```text
backend/     Laravel API application (controllers, models, services, routes, migrations, tests)
frontend/    Next.js application with Tailwind CSS (app routes, components, API client)
docs/        Project documentation
evaluation/  Evaluation scenarios and results
```

- `backend/app/` — Controllers, models, services, requests, and authorization providers.
- `backend/database/` — Migrations, factories, and the database seeder.
- `backend/tests/` — PHPUnit feature tests.
- `frontend/src/app/` — Next.js App Router pages (files, transfers, issues, notifications, audit logs, users, departments, profile, dashboard).
- `frontend/src/components/` — Reusable UI components.
- `frontend/src/lib/` — API client and authentication state.

## Setup Instructions

### Prerequisites

- PHP 8.0+ with Composer
- Node.js with npm
- MySQL

### Backend

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Configure MySQL in `backend/.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=intelligent_file_tracking
DB_USERNAME=root
DB_PASSWORD=
```

Create the database in MySQL before running migrations:

```sql
CREATE DATABASE intelligent_file_tracking;
```

Run migrations and seed development data:

```bash
php artisan migrate:fresh --seed
```

Start the Laravel API:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Health endpoint:

```text
http://127.0.0.1:8000/api/health
```

### Frontend

```bash
cd frontend
npm install
copy .env.example .env.local
npm run dev
```

Default frontend URL:

```text
http://localhost:3000
```

The frontend reads the API base URL from:

```env
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api
```

### Development Credentials

Seeded development credentials are safe local defaults and must not be used in production:

```text
Admin: admin@example.com / password
Registry staff: registry@example.com / password
Finance staff: finance@example.com / password
HR staff: hr@example.com / password
Procurement staff: procurement@example.com / password
Legal staff: legal@example.com / password
Supervisor: supervisor@example.com / password
```

The initial admin seeder can be customized with:

```env
ADMIN_NAME="System Administrator"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=password
```

## Testing

Backend:

```bash
cd backend
php artisan test
```

Frontend:

```bash
cd frontend
npm run lint
npm run build
```

Verification result for this submission: **249 backend tests passing**, frontend lint clean, and frontend build passing.

## API Overview

The API is grouped into the following areas (all routes except `/api/health` and `/api/auth/login` require a Sanctum bearer token):

- **Auth** — `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`, `PATCH /auth/profile`, `POST /auth/change-password`.
- **Users** — `GET/POST /users`, `PATCH /users/{user}`, `PATCH /users/{user}/password` (admin only for writes).
- **Departments** — `GET /departments`, `POST /departments`, `PATCH /departments/{department}` (admin only for writes).
- **File categories** — `GET /file-categories`, `POST /file-categories`.
- **Files** — `GET/POST /files`, `GET /files/{file}`.
- **Transfers** — `GET /transfers`, `GET /transfers/overdue`, `POST /transfers`, `GET /transfers/{transfer}`, `POST /transfers/{transfer}/acknowledge`, `POST /transfers/{transfer}/reject`, plus per-file `GET /files/{file}/transfers`.
- **Issues** — `GET /issues`, `GET /issues/{issue}`, `PATCH /issues/{issue}`, plus per-file `GET/POST /files/{file}/issues`.
- **Audit logs** — `GET /audit-logs`, `GET /files/{file}/audit-logs`.
- **Notifications** — `GET /notifications`, `GET /notifications/{notification}`, `PATCH /notifications/{notification}/read`, `POST /notifications/read-all`.

## Security / Authorization

- **Sanctum authentication** — API requests are authenticated with personal access tokens issued at login.
- **Server-side authorization** — Role-based permissions are enforced with Laravel Gates and request authorization, not just hidden UI.
- **Password hashing** — All passwords are stored using bcrypt via Laravel's `Hash` facade.
- **Password reset behavior** — Resetting a user's password hashes the new value, revokes the target user's existing tokens (forcing re-login), and does not affect the administrator's own session.
- **Sensitive data exclusion** — Passwords and other sensitive values are never written to audit logs.

## Demo

The application includes seeded/demo data (departments, users, file categories, files, transfers, issues, notifications, and audit logs) suitable for demonstrating the document-transfer workflow end to end. The seeder also creates a set of development users covering each role so the role-based behavior can be exercised.

## Git Notes

Do not commit secrets. Local environment files are ignored, while `.env.example` files are committed as templates.
