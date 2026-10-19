#!/usr/bin/env bash
#
# Production deploy for ifotoset (aaPanel host, Apache + PHP 8.4, pm2 workers).
# The aaPanel webhook on push to main should run exactly this script:
#
#   bash /www/wwwroot/ifotoset.com/deploy.sh >> /www/wwwroot/ifotoset.com/deploy.log 2>&1
#
# Back up the database before pushing anything that migrates data.
set -euo pipefail

APP_DIR="${APP_DIR:-/www/wwwroot/ifotoset.com}"
PHP="${PHP:-/www/server/php/84/bin/php}"
COMPOSER="${COMPOSER:-/usr/bin/composer}"

cd "$APP_DIR"
echo "== Deploy started $(date -u +%FT%TZ)"

git fetch --quiet origin main
git reset --hard origin/main

"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress

npm ci --no-audit --no-fund
npm run build

# Uncomment for risky migrations; visitors get the 503 page meanwhile.
# "$PHP" artisan down --render="errors::503" --retry=15

"$PHP" artisan migrate --force

# Only idempotent seeders: they never overwrite rows admins have edited.
"$PHP" artisan db:seed --class=PlanSeeder --force
"$PHP" artisan db:seed --class=FeatureSeeder --force

# config, route, view and event caches
"$PHP" artisan optimize

# Workers keep the old code in memory until told to restart
"$PHP" artisan queue:restart

# "$PHP" artisan up

# This script and the pm2 workers run as root, but PHP-FPM runs as www. Root-owned
# compiled views, caches or log files make web requests fail with file_put_contents errors.
chown -R www:www storage bootstrap/cache

pm2 startOrRestart ecosystem.config.js
# The legacy Next.js app was removed from ecosystem.config.js; make sure it is gone.
pm2 delete ifotoset >/dev/null 2>&1 || true
pm2 save

echo "== Deploy finished $(date -u +%FT%TZ)"
