#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   bash scripts/cpanel_fix_deployment.sh /home/USERNAME /home/USERNAME/public_html /home/USERNAME/laravel_app
#
# Defaults:
#   HOME_DIR      = $HOME
#   PUBLIC_HTML   = $HOME/public_html
#   LARAVEL_ROOT  = $HOME/laravel_app

HOME_DIR="${1:-$HOME}"
PUBLIC_HTML="${2:-$HOME_DIR/public_html}"
LARAVEL_ROOT="${3:-$HOME_DIR/laravel_app}"

NOW="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$HOME_DIR/deploy_backups/$NOW"

echo "== cPanel Laravel deployment fix =="
echo "HOME_DIR:     $HOME_DIR"
echo "PUBLIC_HTML:  $PUBLIC_HTML"
echo "LARAVEL_ROOT: $LARAVEL_ROOT"

if [ ! -f "artisan" ] || [ ! -d "public" ] || [ ! -d "routes" ]; then
  echo "ERROR: Run this script from Laravel project root (where artisan exists)."
  exit 1
fi

mkdir -p "$BACKUP_DIR"
mkdir -p "$PUBLIC_HTML"
mkdir -p "$LARAVEL_ROOT"

echo "-- Backup current public_html --"
if [ -n "$(ls -A "$PUBLIC_HTML" 2>/dev/null || true)" ]; then
  cp -a "$PUBLIC_HTML"/. "$BACKUP_DIR/public_html/"
fi

echo "-- Sync Laravel project outside public_html --"
rsync -a --delete \
  --exclude ".git" \
  --exclude "node_modules" \
  --exclude "vendor/bin" \
  --exclude "storage/logs/*" \
  --exclude "storage/framework/cache/*" \
  --exclude "storage/framework/sessions/*" \
  --exclude "storage/framework/views/*" \
  ./ "$LARAVEL_ROOT/"

echo "-- Ensure storage/framework paths exist --"
mkdir -p "$LARAVEL_ROOT/storage/framework/cache/data"
mkdir -p "$LARAVEL_ROOT/storage/framework/sessions"
mkdir -p "$LARAVEL_ROOT/storage/framework/views"
mkdir -p "$LARAVEL_ROOT/storage/logs"

echo "-- Replace public_html with Laravel public files only --"
find "$PUBLIC_HTML" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
cp -a "$LARAVEL_ROOT/public"/. "$PUBLIC_HTML/"

echo "-- Force Laravel .htaccess in public_html --"
cat > "$PUBLIC_HTML/.htaccess" <<'HTACCESS'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS

echo "-- Force index.php paths to laravel_app --"
cat > "$PUBLIC_HTML/index.php" <<'INDEXPHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../laravel_app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../laravel_app/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../laravel_app/bootstrap/app.php';

$app->handleRequest(Request::capture());
INDEXPHP

echo "-- Remove root React index.html if present --"
rm -f "$PUBLIC_HTML/index.html"

echo "-- Create /app folder for React deployment target --"
mkdir -p "$PUBLIC_HTML/app"

echo "-- Composer install/update (if vendor missing) --"
if [ ! -f "$LARAVEL_ROOT/vendor/autoload.php" ]; then
  (cd "$LARAVEL_ROOT" && composer install --no-dev --optimize-autoloader)
fi

echo "-- Laravel cache clear --"
(cd "$LARAVEL_ROOT" && php artisan config:clear)
(cd "$LARAVEL_ROOT" && php artisan route:clear)
(cd "$LARAVEL_ROOT" && php artisan cache:clear)

echo "-- Verify route exists --"
(cd "$LARAVEL_ROOT" && php artisan route:list --path=test/nafath)

echo
echo "DONE."
echo "Backup saved at: $BACKUP_DIR"
echo "Next test:"
echo "curl -i -X POST https://portallogisticejoin-as-investor.com/api/test/nafath \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d '{\"national_id\":\"1126305067\"}'"
