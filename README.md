# IRB Digital System — Backend

Research approval management system for an IRB (Institutional Review Board)
committee. Arabic-first (RTL) interface, electronic payments, blind review,
and automated certificate issuance.

This repository hosts the **REST API backend** (vanilla PHP core + Eloquent ORM)
and will hold the **Vue 3 frontend** under `frontend/` once DEV 1 scaffolds it.

## Tech stack

- **PHP 8.1+** with a custom lightweight framework (Router, Request, Response, Middleware)
- **Eloquent ORM** (`illuminate/database`) for data access
- **MySQL 8.x** (InnoDB, utf8mb4)
- **Firebase PHP-JWT** for access + refresh tokens
- **PHPMailer** for transactional email
- **Composer** for dependency management
- **Vue 3 + Vite + Pinia** (planned for `frontend/`)

## Repository layout

```
Certara_backend/
├── backend/                  REST API
│   ├── config/               env, database, cors bootstrap (DEV 5)
│   ├── enums/                Domain enums: roles, statuses, types (DEV 5)
│   ├── core/                 Router, Request, Response, Middleware, Database (DEV 5)
│   ├── middleware/           AuthMiddleware, RoleMiddleware (DEV 1)
│   ├── controllers/          HealthController + module controllers
│   ├── models/               Eloquent models
│   ├── helpers/              JWT, Email, SMS, NotificationService
│   ├── routes/               Per-module route files
│   ├── uploads/              documents/ + certificates/ (gitignored)
│   ├── index.php             Entry point (DEV 5)
│   ├── .htaccess             Apache rewrites (DEV 5)
│   └── composer.json
├── database/
│   ├── schema.sql            Full DB schema
│   ├── seeds/                roles.sql + test_users.sql + dev4_workflow.sql
│   └── migrations/           Future migrations
├── docs/
│   ├── API.md                Endpoint contract + ownership matrix
│   └── SETUP.md              Local setup in under 10 minutes
└── README.md
```

## Team

| Dev   | Role                         | Domain                                                              |
| ----- | ---------------------------- | ------------------------------------------------------------------- |
| DEV 1 | Auth & Infrastructure        | Registration, login, JWT, routing, shared UI                        |
| DEV 2 | Student & Research           | Research CRUD, document upload, payments                            |
| DEV 3 | Review & Sample Size         | Reviewer workflow, blind review, sample size officer                |
| DEV 4 | Admin & Manager              | Admin dashboard, manager approval, certificates                     |
| DEV 5 | Backend Core & Notifications | PHP core framework, DB schema, email/SMS, notifications (this PR)   |

## What this starter gives you

- A runnable PHP API exposing `GET /api/health` today. Notification endpoints
  (`/api/notifications/*`) are wired but gated behind DEV 1's `AuthMiddleware`
  and will return `501` until DEV 1 completes their module.
- Complete database schema + test seeds for all five roles.
- Domain enums under `backend/enums/` (roles, statuses, document/payment/
  notification types) matching the schema ENUMs.
- Empty skeletons for DEV 1's files (`AuthController`, `UserController`,
  `AuthMiddleware`, `RoleMiddleware`, `JWTHelper`, `User`, `auth.routes.php`)
  so DEV 1 has a clean canvas and predictable file locations.
- One-call notification pipeline: `NotificationService::notify(...)` creates
  the in-app record and fans out email + SMS.
- Conflict-prevention rules and endpoint ownership matrix in
  [docs/API.md](docs/API.md).

## Getting started

Follow [docs/SETUP.md](docs/SETUP.md).

TL;DR:

```bash
cd backend
composer install
cp .env.example .env             # then edit DB + JWT_SECRET
mysql -u root -p -e "CREATE DATABASE irb_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p irb_system < ../database/schema.sql
mysql -u root -p irb_system < ../database/seeds/roles.sql
mysql -u root -p irb_system < ../database/seeds/test_users.sql
mysql -u root -p irb_system < ../database/seeds/dev4_workflow.sql
php -S localhost:8000 index.php
curl http://localhost:8000/api/health
```

## Contributing rules (short form)

1. Never edit another developer's controllers, models, or routes — call their
   API endpoint instead.
2. Shared components, `core/*`, `index.php`, `config/*`, `enums/*`, `schema.sql`,
   and the notification helpers are **DEV 5-owned**. Request changes via PR.
3. New endpoints live in a new `routes/<domain>.routes.php` file. Ask DEV 5
   to register it in `index.php`'s `$routeFiles` array.
4. All responses go through `Response::json` / `Response::error` for envelope
   consistency.
5. Always send notifications via `NotificationService::notify(...)` — never
   call `EmailHelper` or `SMSHelper` directly from a controller.

See [docs/API.md](docs/API.md) for the full ownership matrix.
