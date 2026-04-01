# Login API contract (tasheel-backend)

Use this so the frontend sends the correct fields. All routes are under `/api/portallogistice`.

---

## 1. User login — `POST /api/portallogistice/login`

**Accepted body (JSON):**

| Field    | Type   | Required | Description                                      |
|----------|--------|----------|--------------------------------------------------|
| `login`  | string | Yes      | Phone OR national_id OR email (single field)     |
| `password` | string | Yes    | Password                                         |

**Do NOT send:** `email` as the identifier. Use `login` only.

**Example:**

```json
{
  "login": "0500000000",
  "password": "password"
}
```

Or with national_id: `{"login": "1234567890", "password": "password"}`  
Or with email: `{"login": "user@example.com", "password": "password"}`

**Validation errors (422):**

- Missing `login`: `"The login field (phone, national_id, or email) is required."`
- If client sends `email` instead of `login`: backend returns a message telling to use `login`, not `email`.

---

## 2. Admin login — `POST /api/portallogistice/admin/login`

**Accepted body (JSON):**

| Field    | Type   | Required | Description   |
|----------|--------|----------|---------------|
| `email`  | string | Yes      | Valid email   |
| `password` | string | Yes    | Password      |

**Do NOT send:** `login`. Use `email` only.

**Example:**

```json
{
  "email": "admin@tasheel.test",
  "password": "password"
}
```

**Validation errors (422):**

- Missing `email`: `"The email field is required for admin login."`
- Invalid email format: `"The email must be a valid email address."`
- If client sends `login` instead of `email`: backend returns a message telling to use `email`, not `login`.

---

## Differences (summary)

| Endpoint        | Identifier field | Allowed values              | Wrong field to avoid |
|----------------|------------------|-----------------------------|----------------------|
| User login     | `login`          | phone / national_id / email | Do not send `email`  |
| Admin login    | `email`          | email only                  | Do not send `login`  |

Frontend must call user login with `login` + `password`, and admin login with `email` + `password`.
