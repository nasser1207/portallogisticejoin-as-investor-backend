# Postman – Test the API (tasheel-backend)

Base URL: **http://127.0.0.1:8000/api**

Set this in Postman: **Environment** or use full URL each time.

---

## 1. Health & hello (no auth)

| Method | URL | Body | Expected |
|--------|-----|------|----------|
| GET | `http://127.0.0.1:8000/api/health` | — | 200, `{"status":"ok"}` |
| GET | `http://127.0.0.1:8000/api/hello` | — | 200, `{"message":"Hello API","time":"..."}` |

**Steps:** New request → GET → paste URL → Send.

---

## 2. User login (returns token or requires OTP)

| Method | URL | Body (raw JSON) |
|--------|-----|------------------|
| POST | `http://127.0.0.1:8000/api/portallogistice/login` | `{"login":"0500000000","password":"password"}` |

- **Headers:** `Content-Type: application/json`, `Accept: application/json`
- If user has `is_first_login: true`: response has `requiresOTP: true` and `data.user` (no token).
- If not first login: response has `data.token` and `data.user`. Save the token for protected routes.

---

## 3. Admin login

| Method | URL | Body (raw JSON) |
|--------|-----|------------------|
| POST | `http://127.0.0.1:8000/api/portallogistice/admin/login` | `{"email":"admin@tasheel.test","password":"password"}` |

- **Headers:** `Content-Type: application/json`, `Accept: application/json`
- Response: `data.token`, `data.admin`. Copy the token.

---

## 4. Send OTP (for first-login flow)

| Method | URL | Body (raw JSON) |
|--------|-----|------------------|
| POST | `http://127.0.0.1:8000/api/portallogistice/send-otp` | `{"phone":"0500000000"}` |

- Response includes `otp_code` (for testing). Use it in verify-otp.

---

## 5. Verify OTP

| Method | URL | Body (raw JSON) |
|--------|-----|------------------|
| POST | `http://127.0.0.1:8000/api/portallogistice/verify-otp` | `{"phone":"0500000000","otp":"123456"}` |

- Use the code from send-otp response. Then call login again to get token.

---

## 6. Protected routes (Bearer token)

After login, copy `data.token`. In Postman:

- **Authorization** tab → Type: **Bearer Token** → paste token.

Then test:

| Method | URL | Body |
|--------|-----|------|
| POST | `http://127.0.0.1:8000/api/portallogistice/logout` | — |
| POST | `http://127.0.0.1:8000/api/portallogistice/admin/users` | `{"first_name":"Test","last_name":"User","phone":"0511111111","national_id":"1111111111","password":"secret123"}` |

- **Admin users** and **admin logout** require an **admin** token (from admin login).

---

## Headers (recommended for all requests)

- **Accept:** `application/json`
- **Content-Type:** `application/json` (for POST/PUT with body)

---

## Test accounts (after seeding)

Run: `php artisan db:seed --class=PortallogisticeSeeder`

- **Admin:** email `admin@tasheel.test`, password `password`
- **User (first login):** login `0500000000` or `1234567890` or `user@tasheel.test`, password `password`
