# tasheel-backend — Project Structure

**Scope:** This document describes only the contents of **tasheel-backend**. No parent or sibling directories are referenced.

---

## Root (tasheel-backend)

```
tasheel-backend/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── vendor/           # Composer dependencies (do not edit)
├── .env
├── .env.example
├── artisan
├── composer.json
├── composer.lock
├── PORTALLOGISTICE_API.md
├── REST_API_TUTORIAL.md
├── STEP_2_ROUTES_AND_JSON.md
└── PROJECT_STRUCTURE.md  (this file)
```

---

## app/

| Path | Purpose |
|------|--------|
| `app/Http/Controllers/Controller.php` | Base controller |
| `app/Http/Controllers/AuthController.php` | Login, logout, OTP, reset-password |
| `app/Http/Controllers/Admin/UserController.php` | Admin: create user, register admin |
| `app/Http/Middleware/AuthByToken.php` | Bearer token auth for API |
| `app/Http/Middleware/EnsureAdmin.php` | Restrict to admin role |
| `app/Models/User.php` | User model (roles, OTP, api_token) |
| `app/Providers/AppServiceProvider.php` | App service provider |

---

## bootstrap/

| Path | Purpose |
|------|--------|
| `bootstrap/app.php` | App bootstrap, routing (web + api), middleware aliases |

---

## config/

| Path | Purpose |
|------|--------|
| `config/app.php` | App name, locale, timezone |
| `config/auth.php` | Guards, providers |
| `config/database.php` | DB connections (MySQL, etc.) |
| `config/cache.php` | Cache driver |
| `config/filesystems.php` | Disks |
| `config/logging.php` | Log channels |
| `config/mail.php` | Mail |
| `config/queue.php` | Queue |
| `config/session.php` | Session |
| `config/services.php` | Third-party services |

---

## database/

| Path | Purpose |
|------|--------|
| `database/migrations/0001_01_01_000000_create_users_table.php` | Initial users table |
| `database/migrations/0001_01_01_000001_create_cache_table.php` | Cache table |
| `database/migrations/0001_01_01_000002_create_jobs_table.php` | Jobs table |
| `database/migrations/2025_03_17_000001_modify_users_table_for_portallogistice.php` | Users: phone, national_id, role, OTP, api_token, etc. |
| `database/seeders/DatabaseSeeder.php` | Main seeder |
| `database/seeders/PortallogisticeSeeder.php` | Seeds admin + test user |

---

## routes/

| Path | Purpose |
|------|--------|
| `routes/api.php` | All API routes (prefix `/api`) — hello, health, portallogistice/* |
| `routes/web.php` | Web routes (e.g. GET /) |
| `routes/console.php` | Artisan commands |

---

## public/

| Path | Purpose |
|------|--------|
| `public/index.php` | Entry point for web server |

---

## storage/

| Path | Purpose |
|------|--------|
| `storage/app/` | App files |
| `storage/framework/` | Cache, sessions, views |
| `storage/logs/` | Laravel log files |

---

## Tech stack (inside this project)

- **PHP** ^8.3  
- **Laravel** ^13.0  
- **Database:** MySQL (configured in `.env`: DB_DATABASE, DB_USERNAME, DB_PASSWORD)

All edits, new files, and commands are confined to **tasheel-backend**. No references to parent or sibling folders.
