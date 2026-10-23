#!/usr/bin/env bash
#
# Zero-downtime production deploy for ifotoset (aaPanel host, Apache + PHP 8.4, pm2 workers).
#
# The aaPanel webhook on push to main fetches origin/main and runs THIS file from that commit,
# through a temporary copy (git reset below rewrites deploy.sh while bash is still reading it):
#
#   git fetch --quiet origin main && git show origin/main:deploy.sh > "$tmp" && bash "$tmp"
#
# The site keeps serving throughout:
#   1. The database is backed up, then the new migrations run against the code that is still live.
#      Migrations must therefore be additive (new tables, nullable columns, backfills). Drop or
#      rename a column only one release after the code has stopped using it.
#   2. The code switch (git reset, composer, caches) takes a few seconds and needs no 503 page.
#   3. Assets are built into a side folder and swapped in, so the Vite manifest never goes missing.
#   4. Queue workers finish their current job before picking up the new code.
set -euo pipefail

APP_DIR="${APP_DIR:-/www/wwwroot/ifotoset.com}"
PHP="${PHP:-/www/server/php/84/bin/php}"
export HOME="${HOME:-/root}"
export COMPOSER_HOME="${COMPOSER_HOME:-/root/.composer}"
export COMPOSER_ALLOW_SUPERUSER=1
export PATH="/www/server/php/84/bin:/usr/local/bin:/usr/bin:/bin:$PATH"
COMPOSER="${COMPOSER:-$(command -v composer)}"

cd "$APP_DIR"

# Two quick pushes must not deploy on top of each other
exec 9>/tmp/ifotoset-deploy.lock
flock 9

echo "== Deploy started $(date -u +%FT%TZ)"

git config --global --get-all safe.directory 2>/dev/null | grep -qxF "$APP_DIR" \
    || git config --global --add safe.directory "$APP_DIR"

git fetch --quiet origin main
TARGET="$(git rev-parse origin/main)"
echo "== Deploying $(git log -1 --format='%h %s' "$TARGET") (live: $(git rev-parse --short HEAD))"

# --- 1. Backup, then migrate against the live code -------------------------------------------
# Credentials are read from /root/.my.cnf
mkdir -p /root/backups
mysqldump --defaults-extra-file=/root/.my.cnf --single-transaction --routines --triggers ifotoset > /root/ifotoset-before-deploy.sql
cp /root/ifotoset-before-deploy.sql "/root/backups/ifotoset_$(date +%Y%m%d_%H%M%S).sql"
find /root/backups -name "ifotoset_*.sql" -mtime +7 -delete 2>/dev/null || true

git checkout "$TARGET" -- database/migrations
if ! "$PHP" artisan migrate --force; then
    # Leave the live code as it is. A migration that needs the new code has to be run by hand.
    git checkout HEAD -- database/migrations
    git clean -fdq database/migrations
    chown -R www:www storage bootstrap/cache
    echo "== Deploy ABORTED: migrations failed against the live code; nothing was switched"
    exit 1
fi

# --- 2. Switch the code ------------------------------------------------------------------------
git reset --hard "$TARGET"

"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress

# Drop stale caches (an old cached config can point at services that are gone), then rebuild
# config, route, view and event caches
rm -f bootstrap/cache/*.php
"$PHP" artisan optimize

# Normally a no-op: everything already ran in step 1
"$PHP" artisan migrate --force

# Only idempotent seeders: they never overwrite rows admins have edited
"$PHP" artisan db:seed --class=PlanSeeder --force
"$PHP" artisan db:seed --class=FeatureSeeder --force

# This script runs as root, but PHP-FPM runs as www. Root-owned compiled views, caches or log
# files make web requests fail with file_put_contents errors.
chown -R www:www storage bootstrap/cache

# --- 3. Assets, swapped in whole -------------------------------------------------------------
if [ -f package.json ]; then
    npm ci --include=dev --no-audit --no-fund
    rm -rf public/build-next public/build-prev
    npm run build -- --outDir public/build-next --emptyOutDir

    # Pages already open in a browser still ask for the previous hashed files; keep them a week
    cp -Rnp public/build/assets/. public/build-next/assets/ 2>/dev/null || true
    find public/build-next/assets -type f -mtime +7 -delete

    mv public/build public/build-prev
    mv public/build-next public/build
    rm -rf public/build-prev
fi

# --- 4. Workers --------------------------------------------------------------------------------
# Each worker exits after its current job and pm2 starts it again on the new code. A pm2 restart
# here would kill a long ZIP export or video transcode halfway.
"$PHP" artisan queue:restart
chown -R www:www storage bootstrap/cache

# Start workers newly added to ecosystem.config.cjs; running ones are left to queue:restart
for app in $(node -e 'console.log(require("./ecosystem.config.cjs").apps.map(a => a.name).join(" "))'); do
    pm2 describe "$app" >/dev/null 2>&1 || pm2 start ecosystem.config.cjs --only "$app"
done
# The legacy Next.js app was removed from the pm2 config; make sure it is gone
pm2 delete ifotoset >/dev/null 2>&1 || true
pm2 save

status="$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: ifotoset.com' http://127.0.0.1:8083/ || true)"
echo "== Deploy finished $(date -u +%FT%TZ), home page HTTP $status"
