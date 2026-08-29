# Intelligent Physical File Tracking System

Laravel and Next.js foundation for tracking physical paper-file custody in organizations.

Phase 1 implements the core data foundation and authentication layer only:

- Users
- Departments
- File categories
- Physical files
- Initial confirmed file custodian at registration

This phase does not implement transfers, acknowledgements, overdue detection, notifications, advanced dashboards, AI, or agent tools.

## Technology Stack

- Laravel 9 / PHP API backend
- Laravel Sanctum token authentication
- Next.js 16 frontend
- Tailwind CSS
- MySQL for local development
- SQLite in-memory for automated backend tests
- Laragon-compatible local setup

Laravel 9 is used because the detected local PHP runtime is PHP 8.0.30.

## Project Structure

```text
backend/     Laravel API application
frontend/    Next.js application with Tailwind CSS
docs/        Project documentation
evaluation/  Future evaluation scenarios and results
```

## Backend Setup

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

## Frontend Setup

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

## Development Credentials

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

## Phase 1 API Endpoints

Public:

```text
GET  /api/health
POST /api/auth/login
```

Authenticated:

```text
POST /api/auth/logout
GET  /api/auth/me
GET  /api/users
GET  /api/departments
GET  /api/file-categories
GET  /api/files
GET  /api/files/{file}
```

Admin only:

```text
POST /api/departments
POST /api/file-categories
```

Admin or registry staff:

```text
POST /api/files
```

## Phase 1 Custody Rule

At registration time, the selected initial department and holder become the file's confirmed custodian:

```text
files.confirmed_department_id
files.confirmed_holder_user_id
```

There is no transfer or acknowledgement logic in Phase 1.

## Verification Commands

Backend:

```bash
cd backend
php artisan migrate:fresh --seed
php artisan test
```

Frontend:

```bash
cd frontend
npm run lint
npm run build
```

API smoke test:

```bash
curl http://127.0.0.1:8000/api/health
```

## Git Notes

Do not commit secrets. Local environment files are ignored, while `.env.example` files are committed as templates.
