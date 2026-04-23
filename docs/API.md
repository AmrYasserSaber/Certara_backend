# IRB Digital System — API Reference (Starter)

All endpoints live under the `/api` prefix. The responses share one envelope:

```json
{
  "success": true,
  "data":    <payload or null>,
  "error":   { "code": "...", "message": "...", "details": {...} } | null,
  "meta":    { "page": 1, "limit": 20, "total": 0 } | null
}
```

Unless stated otherwise, protected endpoints require
`Authorization: Bearer <access_token>`.

## CSRF (cookie-based auth)

This API uses **cookie-based auth** (access + refresh tokens stored as cookies). For any
state-changing request (`POST`, `PUT`, `PATCH`, `DELETE`), clients must also send a CSRF header:

- Cookie: `IRB_CSRF_TOKEN`
- Header: `X-CSRF-Token: <value of IRB_CSRF_TOKEN>`

If the header is missing or doesn’t match the cookie, the API returns `403` with
`error.code = "csrf_missing"` or `error.code = "csrf_mismatch"`.

---

## Endpoint status legend

- **READY** — implemented in the starter.
- **STUB** — endpoint exists but returns `501 not_implemented`; dev listed below owns completion.
- **TODO** — not yet wired; owner adds controller, route, and tests.

---

## Auth (DEV 1)

| Method | Path                 | Auth | Status | Notes                                                                             |
| ------ | -------------------- | ---- | ------ | --------------------------------------------------------------------------------- |
| POST   | `/api/auth/register` | No   | STUB   | Issues access + refresh tokens on success.                                        |
| POST   | `/api/auth/login`    | No   | STUB   | Returns `{ user, access_token, refresh_token }`.                                  |
| POST   | `/api/auth/refresh`  | No   | STUB   | Accepts refresh token, returns new access token (and optionally rotates refresh). |
| POST   | `/api/auth/logout`   | Yes  | STUB   | Revokes the current refresh token.                                                |
| GET    | `/api/auth/me`       | Yes  | STUB   | Returns the authenticated user profile.                                           |

### Auth validation notes (DEV 1)

- **Validation errors**: return `422` with `error.code = "validation_error"` and `error.details` keyed by field name.
- **`phone`**: optional on register; when present must match Egyptian mobile (local only): `^01[0-2,5][0-9]{8}$`.
- **`department` / `faculty`**: optional strings; trimmed; length bounds enforced.

## Users (DEV 1)

| Method | Path                       | Auth | Status |
| ------ | -------------------------- | ---- | ------ |
| GET    | `/api/users/{id}`          | Yes  | TODO   |
| PUT    | `/api/users/{id}`          | Yes  | TODO   |
| POST   | `/api/users/{id}/id-photo` | Yes  | TODO   |

## Research (DEV 2)

| Method | Path                                    | Auth | Status |
| ------ | --------------------------------------- | ---- | ------ |
| POST   | `/api/research`                         | Yes  | TODO   |
| GET    | `/api/research`                         | Yes  | TODO   |
| GET    | `/api/research/{id}`                    | Yes  | TODO   |
| PUT    | `/api/research/{id}`                    | Yes  | TODO   |
| DELETE | `/api/research/{id}`                    | Yes  | TODO   |
| POST   | `/api/research/{id}/documents`          | Yes  | TODO   |
| GET    | `/api/research/{id}/documents`          | Yes  | TODO   |
| DELETE | `/api/research/{id}/documents/{doc_id}` | Yes  | TODO   |
| POST   | `/api/research/{id}/payment`            | Yes  | TODO   |
| GET    | `/api/research/{id}/payment/receipt`    | Yes  | TODO   |

## Sample Size (DEV 3)

| Method | Path                             | Auth | Status |
| ------ | -------------------------------- | ---- | ------ |
| GET    | `/api/sample-size/pending`       | Yes  | TODO   |
| POST   | `/api/sample-size/{research_id}` | Yes  | TODO   |

## Review (DEV 3)

| Method | Path                                  | Auth | Status |
| ------ | ------------------------------------- | ---- | ------ |
| GET    | `/api/reviews/assigned`               | Yes  | TODO   |
| GET    | `/api/reviews/{research_id}`          | Yes  | TODO   |
| POST   | `/api/reviews/{research_id}/comment`  | Yes  | TODO   |
| PUT    | `/api/reviews/{research_id}/decision` | Yes  | TODO   |

> Reviewer-facing responses MUST strip student PII (id, name, email, phone, national*id, id_photo*\*).

## Admin (DEV 4)

| Method | Path                                       | Auth | Status |
| ------ | ------------------------------------------ | ---- | ------ |
| GET    | `/api/admin/users/pending`                 | Yes  | TODO   |
| PUT    | `/api/admin/users/{id}/activate`           | Yes  | TODO   |
| PUT    | `/api/admin/users/{id}/reject`             | Yes  | TODO   |
| GET    | `/api/admin/research`                      | Yes  | TODO   |
| PUT    | `/api/admin/research/{id}/assign-reviewer` | Yes  | TODO   |
| POST   | `/api/admin/research/{id}/serial`          | Yes  | TODO   |
| GET    | `/api/admin/reviewers`                     | Yes  | TODO   |
| GET    | `/api/admin/logs`                          | Yes  | TODO   |

## Manager (DEV 4)

| Method | Path                                     | Auth | Status |
| ------ | ---------------------------------------- | ---- | ------ |
| GET    | `/api/manager/research/reviewed`         | Yes  | TODO   |
| GET    | `/api/manager/research/{id}`             | Yes  | TODO   |
| PUT    | `/api/manager/research/{id}/decision`    | Yes  | TODO   |
| POST   | `/api/manager/research/{id}/certificate` | Yes  | TODO   |
| GET    | `/api/manager/dashboard/stats`           | Yes  | TODO   |

## Notifications (DEV 5)

| Method | Path                           | Auth | Status |
| ------ | ------------------------------ | ---- | ------ |
| GET    | `/api/notifications`           | Yes  | READY  |
| PUT    | `/api/notifications/{id}/read` | Yes  | READY  |
| PUT    | `/api/notifications/read-all`  | Yes  | READY  |

## Health (DEV 5)

| Method | Path          | Auth | Status |
| ------ | ------------- | ---- | ------ |
| GET    | `/api/health` | No   | READY  |

---

## Research lifecycle (status machine)

```
draft
  -> pending_activation (student submits)
  -> awaiting_payment_1 (admin activates + assigns serial)
  -> awaiting_sample_size (first payment confirmed)
  -> awaiting_payment_2 (sample size officer submits size + fee)
  -> in_review (second payment confirmed; admin assigns reviewer)
  -> revision_requested (reviewer asks for changes; returns to student)
  -> reviewer_approved (reviewer approves)
  -> manager_reviewing (manager picks up)
  -> approved | rejected
```

Notifications fire on every transition via
`App\Helpers\NotificationService::notify(...)`.

---

## Ownership and conflict-prevention rules

These files are **DEV 5-owned**; open a PR to request changes:

- `backend/index.php`
- `backend/.htaccess`
- `backend/core/*`
- `backend/config/*`
- `backend/enums/*`
- `backend/helpers/EmailHelper.php`
- `backend/helpers/SMSHelper.php`
- `backend/helpers/NotificationService.php`
- `backend/models/Notification.php`
- `backend/controllers/NotificationController.php`
- `backend/controllers/HealthController.php`
- `database/schema.sql` (schema changes go through DEV 5)

DEV 1 owns auth + shared user infra (DEV 5 shipped empty skeletons only):

- `backend/helpers/JWTHelper.php`
- `backend/middleware/AuthMiddleware.php`, `middleware/RoleMiddleware.php`
- `backend/models/User.php`
- `backend/controllers/AuthController.php`, `UserController.php`
- `backend/routes/auth.routes.php`

> The notification endpoints shipped by DEV 5 are gated behind
> `AuthMiddleware`, so they will return `501 not_implemented` until DEV 1
> implements the middleware + JWTHelper.

DEV 2/3/4 each add:

- Their own controllers under `backend/controllers/`
- Their own models under `backend/models/`
- Their own `routes/<domain>.routes.php` file
- **Register the route file** in `backend/index.php` by opening a PR that edits
  only the `$routeFiles` array there.

### Response envelope rule

Every controller MUST return via `Response::json(...)` or `Response::error(...)`
(or the `ok()` / `fail()` helpers in the base `Controller` class).
Do not `echo` JSON directly.

### Notification rule

Modules MUST NOT call `EmailHelper::send` / `SMSHelper::send` directly. Always
go through `NotificationService::notify(...)` so the in-app record, email, and
SMS stay in sync.
