# Build a Professional REST API with Laravel + Postman

A step-by-step guide from zero to a working, testable API.  
We'll build a **Products API** (CRUD) as the example.

---

## Step 1: Creating a Laravel Project

### What you need first

- **PHP** 8.2+ (with extensions: mbstring, xml, curl, pdo, etc.)
- **Composer** ([getcomposer.org](https://getcomposer.org))
- **MySQL** (or MariaDB) running locally, or use Laravel’s default SQLite for learning

### Create the project

Open a terminal in the folder where you want the project (e.g. `C:\Projects` or `~/projects`).

**Option A – New project (recommended for learning):**

```bash
composer create-project laravel/laravel products-api
cd products-api
```

- `laravel/laravel` is the official “skeleton” app.
- `products-api` is your project folder name (you can change it).
- Composer will download Laravel and install dependencies (may take 1–2 minutes).

**Option B – You already have a Laravel app (e.g. `tasheel-backend`):**

```bash
cd path/to/your/laravel/project
```

You can follow the same steps; we’ll add an API inside this project.

### Check that Laravel runs

Start the built-in server:

```bash
php artisan serve
```

You should see something like:

```
INFO  Server running on [http://127.0.0.1:8000]
```

- Open **http://127.0.0.1:8000** in the browser.
- You should see the default Laravel welcome page.

**What you just did:**

- You created a full Laravel application with a standard structure.
- `php artisan serve` starts a development server so you can send HTTP requests to your app.

---

### Postman – Step 1

1. Open **Postman**.
2. Create a **GET** request.
3. URL: **`http://127.0.0.1:8000`**
4. Click **Send**.

You should get **HTML** (the Laravel welcome page). That confirms Laravel is running and Postman can talk to it.

---

## Step 2: Understanding Routes (`api.php`)

### Why routes matter

Every HTTP request (GET, POST, etc.) hits a **URL**. Laravel uses **routes** to decide which code runs for each URL and method.

- **Web routes** (`routes/web.php`) – typically for HTML pages, sessions, redirects.
- **API routes** (`routes/api.php`) – for REST APIs (JSON, stateless). We use these.

### How Laravel registers API routes

In **`bootstrap/app.php`** you should see something like:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',  // API routes
    ...
)
```

So anything we put in `routes/api.php` is loaded and **prefixed with `/api`** by default.

Example: a route in `api.php`:

```php
Route::get('/hello', function () {
    return response()->json(['message' => 'Hello API']);
});
```

- **Full URL:** `http://127.0.0.1:8000/api/hello`
- **Method:** GET
- **Response:** JSON `{"message":"Hello API"}`

### Create or open `routes/api.php`

If the file doesn’t exist, create it:

**File: `routes/api.php`**

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Simple test route – no database
Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello API',
        'time' => now()->toDateTimeString(),
    ]);
});

// Health check (useful for Postman and deployment)
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
```

**What this does:**

- `Route::get('/hello', ...)` = for **GET** requests to `/api/hello`.
- `response()->json([...])` = return JSON with proper `Content-Type: application/json`.

---

### Postman – Step 2

1. **GET** **`http://127.0.0.1:8000/api/hello`**  
   - You should see JSON: `{"message":"Hello API","time":"..."}`.

2. **GET** **`http://127.0.0.1:8000/api/health`**  
   - You should see: `{"status":"ok"}`.

So: **routes in `api.php` live under `/api`**, and we return JSON.

---

## Step 3: Creating Controllers

### Why use controllers

Putting logic in `api.php` is fine for tiny examples. For a real API we use **Controllers**: one class per “resource” (e.g. products), with methods for each action (index, store, update, destroy).

### Create the Products controller

In the terminal (inside your Laravel project):

```bash
php artisan make:controller Api/ProductController --api
```

- Creates **`app/Http/Controllers/Api/ProductController.php`**.
- `--api` adds typical REST methods: `index`, `store`, `show`, `update`, `destroy`.

Open the file; it will look like this (Laravel 11+):

**File: `app/Http/Controllers/Api/ProductController.php`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index() {}
    public function store(Request $request) {}
    public function show(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}
}
```

We’ll fill these in the next steps. For now, add a simple response in `index` so we can hit it:

```php
public function index()
{
    return response()->json([
        'message' => 'List of products (coming soon)',
        'data' => [],
    ]);
}
```

### Register the route

**File: `routes/api.php`**

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello API', 'time' => now()->toDateTimeString()]);
});

// Products resource: one line registers GET/POST/PUT/DELETE
Route::apiResource('products', ProductController::class);
```

**What this does:**

- `Route::apiResource('products', ProductController::class)` registers:
  - GET `/api/products` → `index`
  - POST `/api/products` → `store`
  - GET `/api/products/{id}` → `show`
  - PUT/PATCH `/api/products/{id}` → `update`
  - DELETE `/api/products/{id}` → `destroy`

---

### Postman – Step 3

1. **GET** **`http://127.0.0.1:8000/api/products`**  
   - You should see: `{"message":"List of products (coming soon)","data":[]}`.

So: **Controllers** hold the logic; **routes** map URLs to controller methods.

---

## Step 4: Handling GET, POST, PUT, DELETE

We’ll implement each verb in the controller. First we need a **Model** and **migration** (next step); for step 4 we can still show the structure and use in-memory or static data so you see how each method is called.

### Typical REST mapping

| Method   | URL                  | Controller method | Meaning        |
|----------|----------------------|-------------------|----------------|
| GET      | /api/products        | index()           | List all       |
| POST     | /api/products        | store()           | Create one     |
| GET      | /api/products/1      | show(1)           | Get one        |
| PUT/PATCH| /api/products/1      | update(1)         | Update one     |
| DELETE   | /api/products/1      | destroy(1)        | Delete one     |

After we add the Product model and migration (Step 7), we’ll wire these to the database. For now, here’s the **structure** with fake data so you can test every verb in Postman.

**File: `app/Http/Controllers/Api/ProductController.php`** (temporary version without DB):

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // In-memory store for demo only (we'll replace with DB in Step 7)
    private static $products = [
        ['id' => 1, 'name' => 'Product A', 'price' => 100],
        ['id' => 2, 'name' => 'Product B', 'price' => 200],
    ];
    private static $nextId = 3;

    public function index()
    {
        return response()->json(['data' => self::$products]);
    }

    public function store(Request $request)
    {
        $product = [
            'id' => self::$nextId++,
            'name' => $request->input('name', 'Unnamed'),
            'price' => (float) $request->input('price', 0),
        ];
        self::$products[] = $product;
        return response()->json(['data' => $product], 201);
    }

    public function show(string $id)
    {
        $product = collect(self::$products)->firstWhere('id', (int) $id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json(['data' => $product]);
    }

    public function update(Request $request, string $id)
    {
        $index = array_search((int) $id, array_column(self::$products, 'id'));
        if ($index === false) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        self::$products[$index]['name'] = $request->input('name', self::$products[$index]['name']);
        self::$products[$index]['price'] = (float) $request->input('price', self::$products[$index]['price']);
        return response()->json(['data' => self::$products[$index]]);
    }

    public function destroy(string $id)
    {
        $index = array_search((int) $id, array_column(self::$products, 'id'));
        if ($index === false) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        array_splice(self::$products, $index, 1);
        return response()->json(null, 204);
    }
}
```

**Explanation:**

- **GET index:** Return all items as `data`.
- **POST store:** Read `name` and `price` from body, add to list, return 201 and the new item.
- **GET show:** Find by `id`, return 404 if missing.
- **PUT update:** Find by `id`, update `name`/`price`, return updated item.
- **DELETE destroy:** Remove item, return 204 No Content.

---

### Postman – Step 4

1. **GET** **`http://127.0.0.1:8000/api/products`**  
   - Body: none.  
   - Expect: list of 2 products.

2. **POST** **`http://127.0.0.1:8000/api/products`**  
   - Body → **raw** → **JSON**  
   - Content: `{"name":"Product C","price":300}`  
   - Expect: 201 and the new product.

3. **GET** **`http://127.0.0.1:8000/api/products/1`**  
   - Expect: one product.

4. **PUT** **`http://127.0.0.1:8000/api/products/1`**  
   - Body → raw → JSON: `{"name":"Product A Updated","price":150}`  
   - Expect: updated product.

5. **DELETE** **`http://127.0.0.1:8000/api/products/2`**  
   - No body.  
   - Expect: status 204, empty body.

So: **GET = read, POST = create, PUT = update, DELETE = delete**, and each maps to one controller method.

---

## Step 5: Validating Request Data

We should never trust client input. Laravel’s **Form Request** or **$request->validate()** checks types, required fields, and rules, and returns 422 + JSON errors if validation fails.

### Validate in the controller

**File: `app/Http/Controllers/Api/ProductController.php`** – use validation in `store` and `update` (when we switch to DB we’ll use the same rules):

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
    ]);

    // $validated is safe to use
    $product = [
        'id' => self::$nextId++,
        'name' => $validated['name'],
        'price' => (float) $validated['price'],
        'created_at' => now()->toIso8601String(),
    ];
    self::$products[] = $product;
    return response()->json(['data' => $product], 201);
}

public function update(Request $request, string $id)
{
    $validated = $request->validate([
        'name' => 'sometimes|string|max:255',
        'price' => 'sometimes|numeric|min:0',
    ]);

    $index = array_search((int) $id, array_column(self::$products, 'id'));
    if ($index === false) {
        return response()->json(['message' => 'Product not found'], 404);
    }
    if (isset($validated['name'])) {
        self::$products[$index]['name'] = $validated['name'];
    }
    if (isset($validated['price'])) {
        self::$products[$index]['price'] = (float) $validated['price'];
    }
    return response()->json(['data' => self::$products[$index]]);
}
```

**Rules:**

- `required` = field must be present.
- `sometimes` = validate only if the field is sent (good for PATCH).
- `string`, `numeric`, `max:255`, `min:0` = type and range.

If validation fails, Laravel automatically returns **422** with a JSON body like:

```json
{
  "message": "The name field is required.",
  "errors": {
    "name": ["The name field is required."],
    "price": ["The price field must be at least 0."]
  }
}
```

---

### Postman – Step 5

1. **POST** **`http://127.0.0.1:8000/api/products`**  
   - Body: `{}` (empty JSON).  
   - Expect: **422**, body with `errors` for `name` and `price`.

2. **POST** again with: `{"name":"Test","price":-10}`  
   - Expect: **422**, error for `price` (min 0).

3. **POST** with: `{"name":"Valid Product","price":99}`  
   - Expect: **201** and the new product.

So: **validation** keeps your API safe and returns clear error messages.

---

## Step 6: Connecting to the Database (MySQL)

### Configure environment

1. Copy the env file (if not already):  
   `cp .env.example .env`  
   (on Windows: `copy .env.example .env`)

2. Generate app key:  
   `php artisan key:generate`

3. Edit **`.env`** and set your DB:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=products_api
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create the database (e.g. in MySQL or phpMyAdmin):

```sql
CREATE DATABASE products_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Test the connection:

```bash
php artisan db:show
```

You should see your database and driver. If it fails, check credentials and that MySQL is running.

---

### Postman – Step 6

No API call needed here. If `php artisan db:show` works, you’re ready for migrations.

---

## Step 7: Migrations and Models

### Create migration for products

```bash
php artisan make:model Product -m
```

- Creates **`app/Models/Product.php`**
- Creates **`database/migrations/xxxx_create_products_table.php`**

Edit the migration **up()** method:

```php
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('price', 10, 2);
        $table->timestamps();
    });
}
```

Run migrations:

```bash
php artisan migrate
```

### Set fillable on the model

**File: `app/Models/Product.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'price'];
}
```

So: **migrations** define the table; **Model** defines which columns can be mass-assigned.

---

### Postman – Step 7

Still use the same endpoints; in the next step we’ll switch the controller to use `Product::query()` so data is saved and loaded from MySQL.

---

## Step 8: Saving and Retrieving Data

Replace the in-memory array in the controller with the **Product** model.

**File: `app/Http/Controllers/Api/ProductController.php`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::orderBy('id')->get();
        return response()->json(['data' => $products]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);
        $product = Product::create($validated);
        return response()->json(['data' => $product], 201);
    }

    public function show(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json(['data' => $product]);
    }

    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
        ]);
        $product->update($validated);
        return response()->json(['data' => $product->fresh()]);
    }

    public function destroy(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $product->delete();
        return response()->json(null, 204);
    }
}
```

Now all data is stored in MySQL.

---

### Postman – Step 8

Repeat the same requests as in Step 4 and 5. Create a few products, then restart the server and **GET /api/products** again – data should persist.

---

## Step 9: Clean JSON Responses

Keep responses consistent. Example structure:

- **List:** `{ "data": [ ... ] }`
- **One resource:** `{ "data": { ... } }`
- **Error:** `{ "message": "...", "errors": { ... } }`

You can create a **Resource** class or a simple helper. Example helper in the controller:

```php
private function success($data, int $code = 200)
{
    return response()->json(['data' => $data], $code);
}

private function error(string $message, int $code = 400, $errors = null)
{
    $body = ['message' => $message];
    if ($errors) {
        $body['errors'] = $errors;
    }
    return response()->json($body, $code);
}
```

Then use `return $this->success($product, 201);` and `return $this->error('Product not found', 404);` for a uniform format.

---

## Step 10: Handling Errors Properly

- **404** – Resource not found (e.g. product id doesn’t exist).
- **422** – Validation failed (Laravel does this automatically when `validate()` fails).
- **500** – Server error (e.g. DB down). Laravel will return a JSON error in API context if you don’t override it.

You can add a global exception handler or use `abort(404, 'Product not found')` and Laravel will turn it into JSON when the request expects JSON (e.g. `Accept: application/json`).

In Postman, always set **Header:**  
`Accept: application/json`  
so Laravel returns JSON even for errors.

---

## Step 11: Testing All Endpoints in Postman

Suggested collection:

| Method | URL | Body (raw JSON) |
|--------|-----|------------------|
| GET | http://127.0.0.1:8000/api/products | - |
| POST | http://127.0.0.1:8000/api/products | {"name":"Laptop","price":999.99} |
| GET | http://127.0.0.1:8000/api/products/1 | - |
| PUT | http://127.0.0.1:8000/api/products/1 | {"name":"Laptop Pro","price":1299} |
| DELETE | http://127.0.0.1:8000/api/products/1 | - |

Add a **Collection** and one request per row. Use **Tests** tab to assert status code (e.g. `pm.response.code === 200`).

---

## Step 12: Headers, Body, and Authentication (Bearer Token)

### Headers

- **Accept:** `application/json` – so Laravel returns JSON (including validation/error responses).
- **Content-Type:** `application/json` – when sending a JSON body (POST/PUT).

In Postman: **Headers** tab → add `Accept` and `Content-Type` (Postman often sets Content-Type automatically when you pick “raw” + “JSON”).

### Body

- **GET/DELETE:** usually no body.
- **POST/PUT:** Body → **raw** → **JSON** → type e.g. `{"name":"X","price":10}`.

### Bearer token

1. Add a login or “create token” endpoint that returns a token (e.g. from Laravel Sanctum or a simple `api_token` on the users table).
2. For protected routes, middleware checks `Authorization: Bearer <token>`.
3. In Postman: **Authorization** tab → Type: **Bearer Token** → paste the token.  
   Or in **Headers**: `Authorization: Bearer your_token_here`.

Example protected route:

```php
Route::apiResource('products', ProductController::class)->middleware('auth:sanctum');
```

Then only requests with a valid Bearer token can access `/api/products`.

---

## Quick reference

- **Routes:** `routes/api.php` (prefix `/api`).
- **Controller:** one class per resource; methods: `index`, `store`, `show`, `update`, `destroy`.
- **Validation:** `$request->validate([...])` in controller or Form Request.
- **Model:** `Product::create()`, `Product::find()`, `$product->update()`, `$product->delete()`.
- **JSON:** `response()->json(['data' => $data], 201)`.
- **Postman:** set `Accept: application/json`, use Body → raw → JSON for POST/PUT, use Bearer token for protected routes.

If you tell me your Laravel version and whether you use MySQL or SQLite, I can adapt any step (e.g. exact migration or env) for your setup. Start with **Step 1** (create project + `php artisan serve` + GET in Postman), then move to Step 2 and so on.
