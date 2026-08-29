# Intelligent File Tracking System

Foundation for a Laravel API and Next.js frontend that will later support physical file custody and traceability workflows.

This phase intentionally does not implement file tracking, transfer, acknowledgement, custody, or AI functionality.

## Structure

```text
backend/     Laravel API application
frontend/    Next.js application with Tailwind CSS
docs/        Project documentation
evaluation/  Future evaluation scenarios and results
```

## Requirements

- PHP 8.0+
- Composer
- Node.js 20+
- npm
- MySQL through Laragon, XAMPP, or another local service

The backend currently uses Laravel 9 because the detected local PHP runtime is PHP 8.0.30.

## Backend Setup

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan serve --host=127.0.0.1 --port=8000
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

## Verification Commands

Backend tests:

```bash
cd backend
php artisan test
```

Frontend build:

```bash
cd frontend
npm run build
```

API smoke test:

```bash
curl http://127.0.0.1:8000/api/health
```

## Git Notes

Do not commit secrets. Local environment files are ignored, while `.env.example` files are committed as templates.
