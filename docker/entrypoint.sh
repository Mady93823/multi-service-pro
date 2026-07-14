#!/bin/sh
set -e

# Boot the demo.
#
# Everything here is idempotent: the container can be restarted, redeployed or
# rebuilt and it will do the right thing. The one destructive step (the demo
# reseed) is guarded by a marker file that lives on the storage volume, so a
# `docker compose restart` in front of the client cannot wipe the data they are
# looking at.

cd /var/www/html

log() { echo "[boot] $*"; }

# ── The address the browser will actually type ───────────────────────────────
#
# APP_URL is not decoration: every uploaded image URL is generated from it, and
# an APP_URL that disagrees with the browsed origin means every photograph on
# the site points at the wrong host and 404s (landmine 23). With no domain, that
# address is the VPS's public IP — which the container cannot see from inside,
# so it has to ask.
if [ -z "${APP_URL:-}" ] || [ "${APP_URL}" = "auto" ]; then
    IP=""

    for endpoint in "https://api.ipify.org" "https://ifconfig.me/ip" "https://icanhazip.com"; do
        IP=$(curl -fsS --max-time 5 "$endpoint" 2>/dev/null | tr -d '[:space:]') || IP=""

        case "$IP" in
            *[!0-9.]*|"") IP="" ;;   # not an IPv4 — try the next one
            *) break ;;
        esac
    done

    # Last resort: the container's own address. Wrong on most VPS setups, but a
    # site that loads on the LAN beats a site that will not boot.
    [ -z "$IP" ] && IP=$(hostname -i | awk '{print $1}')

    export APP_URL="http://${IP}"
    log "no APP_URL given — detected public address ${APP_URL}"
fi

log "APP_URL=${APP_URL}"

# ── Wait for MySQL ───────────────────────────────────────────────────────────
#
# depends_on with a healthcheck already covers this in compose; the loop is here
# for anyone running the image on its own against an external database.
log "waiting for the database at ${DB_HOST:-db}…"

ATTEMPTS=0
until php -r '
    $dsn = sprintf("mysql:host=%s;port=%s", getenv("DB_HOST") ?: "db", getenv("DB_PORT") ?: "3306");
    try { new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); }
    catch (Throwable $e) { exit(1); }
' 2>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))

    if [ "$ATTEMPTS" -ge 60 ]; then
        log "database never came up — giving up"
        exit 1
    fi

    sleep 2
done

log "database is up"

# ── Filesystem ───────────────────────────────────────────────────────────────
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public storage/logs
chown -R www-data:www-data storage bootstrap/cache

# Without this every uploaded image 404s. Non-fatal on hosts that forbid
# symlinks — the site still runs, it just cannot show what was uploaded.
php artisan storage:link --force >/dev/null 2>&1 || log "storage:link failed (uploads will not be visible)"

# ── Data ─────────────────────────────────────────────────────────────────────
MARKER="storage/app/.demo-seeded"

if [ "${DEMO_SEED:-true}" = "true" ] && [ ! -f "$MARKER" ]; then
    log "seeding the showcase demo (90 days of trade) — this takes a minute…"

    # APP_ENV=local for this one command, on purpose. `demo:seed` refuses to run
    # in production and `migrate:fresh` is blocked there by
    # DB::prohibitDestructiveCommands() — both guards exist to stop exactly this
    # command from eating a real business's data. The container *runs* as
    # production; only the seeding process pretends otherwise, and only once.
    APP_ENV=local php artisan demo:seed --fresh --no-interaction

    su -s /bin/sh www-data -c "touch $MARKER"
    log "demo data ready"
else
    log "running migrations"
    php artisan migrate --force
fi

# ── Caches ───────────────────────────────────────────────────────────────────
#
# After APP_URL is settled, never before: config:cache freezes the environment
# as it stands, and caching a placeholder URL bakes the wrong image host into
# every page.
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

log "ready — open ${APP_URL}"

exec "$@"
