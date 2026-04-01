# Pre-flight checklist (tasheel-backend)

Run these before starting the server. All commands from project root: `tasheel-backend/`.

---

## 1. Verify .env

- [x] **APP_KEY** is set (e.g. `base64:...`). If missing: `php artisan key:generate`
- [x] **DB_CONNECTION=mysql**
- [x] **DB_HOST=127.0.0.1**, **DB_PORT=3306**
- [x] **DB_DATABASE=tasheel**, **DB_USERNAME=root**, **DB_PASSWORD=** (empty or your password)

---

## 2. Check database connection

```bash
php artisan migrate:status
```

- If you see migrations listed with "Ran", the connection works.
- If you see "SQLSTATE" connection error: fix DB_* in `.env` and ensure MySQL is running and database `tasheel` exists.

---

## 3. Migrations

```bash
php artisan migrate:status
```

All 4 migrations should show **Ran**. If any are "Pending":

```bash
php artisan migrate
```

---

## 4. Routes (api.php)

```bash
php artisan route:list --path=api
```

You should see: `api/hello`, `api/health`, and all `api/portallogistice/*` routes.

---

## 5. Start the server

```bash
php artisan serve
```

Server runs at **http://127.0.0.1:8000**. Base URL for API: **http://127.0.0.1:8000/api**.
