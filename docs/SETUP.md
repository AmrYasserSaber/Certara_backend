# IRB Digital System — Setup Guide

This document gets any team member from a fresh clone to a running API in under
ten minutes.

## 1. Requirements

- **PHP** 8.1 or newer, with extensions: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `json`
- **Composer** 2.x
- **MySQL** 8.x (or MariaDB 10.6+)
- **Node.js** 18+ (only for the Vue frontend once DEV 1 ships it)

Verify versions:

```bash
php -v && composer --version && mysql --version
```

## 2. Clone and install dependencies

```bash
git clone https://github.com/AmrYasserSaber/Certara_backend.git
cd Certara_backend/backend
composer install
```

## 3. Configure environment

```bash
cp .env.example .env
```

Open `backend/.env` and set at minimum:

- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `JWT_SECRET` — a long random string (use `openssl rand -hex 32`)
- `CORS_ALLOWED_ORIGINS` — the Vue dev URL (e.g. `http://localhost:5173`)

## 4. Create the database and import the schema + seeds

```bash
mysql -u root -p -e "CREATE DATABASE irb_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p irb_system < database/schema.sql
mysql -u root -p irb_system < database/seeds/roles.sql
mysql -u root -p irb_system < database/seeds/test_users.sql
```

Test accounts (all with password `password`):

| Role                  | Email                  |
| --------------------- | ---------------------- |
| Admin                 | `admin@irb.local`      |
| Manager               | `manager@irb.local`    |
| Sample Size Officer   | `sample@irb.local`     |
| Reviewer              | `reviewer@irb.local`   |
| Student (active)      | `student@irb.local`    |
| Student (pending)     | `pending@irb.local`    |

> Replace every password before deploying to a shared environment.

## 5. Run the API

Using PHP's built-in web server (recommended for local dev):

```bash
cd backend
php -S localhost:8000 index.php
```

Verify:

```bash
curl http://localhost:8000/api/health
```

Expected response:

```json
{
  "success": true,
  "data": { "service": "IRB Digital System", "database": { "ok": true } },
  "error": null,
  "meta": null
}
```

Logs are written to `backend/logs/app.log` (request + error logs), plus
`backend/logs/mail.log` and `backend/logs/sms.log` when MAIL/SMS drivers are
set to `log`. With `LOG_TO_STDERR=true`, logs are mirrored to the `php -S`
terminal output as well.

## 6. Apache / XAMPP deployment (optional)

- Point the DocumentRoot at `backend/`.
- Ensure `mod_rewrite` is enabled — the bundled `.htaccess` routes all
  non-file requests to `index.php`.
- Confirm PHP is running with the extensions listed in step 1.

## 7. Running the frontend (once added)

DEV 1 will add `frontend/` at the repo root. Standard Vue + Vite workflow:

```bash
cd ../frontend
npm install
npm run dev
```

## 8. Ownership reminders

See [docs/API.md](API.md) for the full endpoint contract and the ownership
matrix. Do not modify files marked **DEV 5-owned** without opening a PR.
