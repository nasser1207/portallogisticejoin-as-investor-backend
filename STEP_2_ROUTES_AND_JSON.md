# Step 2: Routes + api.php + First JSON Response

---

## 1. Concept (short explanation)

- **Routes** = “When someone hits this URL with this HTTP method, run this code.”
- **api.php** = File where we define API routes. Laravel automatically adds the prefix **`/api`** to every route in this file.
- **First JSON response** = We return data as JSON (not HTML) so frontends (React, Postman, mobile apps) can use it. We use `response()->json([...])`.

So: **URL + Method** → **Route** → **Code runs** → **JSON returned**.

---

## 2. Code (with clear file names)

### File: `routes/api.php`

Add these lines **at the top** of the file (before any `Route::prefix(...)`):

```php
Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello API',
        'time' => now()->toDateTimeString(),
    ]);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
```

**What this does:**

- `Route::get('/hello', ...)` = For **GET** requests to **/api/hello** (Laravel adds `/api` automatically).
- `function () { ... }` = Closure: the code that runs when the route is hit.
- `response()->json([...])` = Return a JSON response with the right `Content-Type: application/json`.

**Full URLs:**

- `http://127.0.0.1:8000/api/hello`
- `http://127.0.0.1:8000/api/health`

---

## 3. What you should do in Postman (step-by-step)

1. Open **Postman**.
2. Create a **new request** (or use the default one).
3. Set method to **GET**.
4. In the URL bar type: **`http://127.0.0.1:8000/api/hello`**
5. Click **Send**.
6. Repeat with URL: **`http://127.0.0.1:8000/api/health`**

(No Body, no Headers required for this step.)

---

## 4. Expected result

**GET `/api/hello`**

- **Status:** 200 OK  
- **Body (JSON):**
```json
{
    "message": "Hello API",
    "time": "2025-03-17 12:34:56"
}
```
(The `time` value will be current server time.)

**GET `/api/health`**

- **Status:** 200 OK  
- **Body (JSON):**
```json
{
    "status": "ok"
}
```

---

## 5. Common mistakes

| Mistake | Why it happens | Fix |
|--------|----------------|-----|
| **404 Not Found** | URL wrong or server not running | Use exactly `http://127.0.0.1:8000/api/hello` (with `/api`). Ensure `php artisan serve` is running. |
| **Getting HTML instead of JSON** | You opened the URL in the browser or hit the wrong path | In Postman, use **GET** and the full URL including `/api`. Browser is fine for a quick check; Postman is for proper API testing. |
| **Route not working after editing** | Cached routes or typo in file | Run `php artisan route:clear` then try again. Check `routes/api.php` for typos (e.g. `Route::get` not `Route::post`). |
| **Forgetting `/api`** | Laravel prefixes api.php routes with `/api` | Always use `http://127.0.0.1:8000/api/hello`, not `http://127.0.0.1:8000/hello`. |

---

**Next (Step 3):** Controllers — move this logic into a controller class and call it from the route.
