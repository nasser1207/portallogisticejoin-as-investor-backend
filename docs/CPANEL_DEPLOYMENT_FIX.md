# cPanel Deployment Fix (Laravel API returning HTML)

This fixes the common issue where `/api/*` returns React HTML because `public_html/.htaccess` rewrites to `index.html`.

## 1) Run the automated script on cPanel terminal

Upload project, then from Laravel project root run:

```bash
bash scripts/cpanel_fix_deployment.sh /home/USERNAME /home/USERNAME/public_html /home/USERNAME/laravel_app
```

What it does:
- Moves/syncs full Laravel app to `/home/USERNAME/laravel_app`
- Keeps only Laravel `public` files in `/home/USERNAME/public_html`
- Replaces `public_html/.htaccess` with Laravel rewrite rules
- Replaces `public_html/index.php` paths to `../laravel_app/...`
- Removes root `public_html/index.html` (to stop React override)
- Creates `public_html/app` as React target folder
- Runs:
  - `php artisan config:clear`
  - `php artisan route:clear`
  - `php artisan cache:clear`
- Verifies route:
  - `php artisan route:list --path=test/nafath`

## 2) Deploy React safely

Deploy React build into:

```text
/home/USERNAME/public_html/app
```

Do **not** place React `index.html` in `public_html` root.

## 3) Final API test

```bash
curl -i -X POST https://portallogisticejoin-as-investor.com/api/test/nafath \
  -H "Content-Type: application/json" \
  -d '{"national_id":"1126305067"}'
```

Expected:
- JSON response (any status)
- Not HTML login page

