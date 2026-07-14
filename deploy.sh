#!/usr/bin/env bash
#
# Put the demo on a VPS, from nothing, in one command:
#
#   git clone https://github.com/Mady93823/multi-service-pro.git
#   cd multi-service-pro
#   ./deploy.sh
#
# It writes a .env with this machine's own secrets, builds the image, brings up
# the stack and seeds three months of trade. Re-running it is safe: existing
# secrets and existing data are kept.

set -euo pipefail

cd "$(dirname "$0")"

info() { printf '\033[1;36m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!!\033[0m %s\n' "$*"; }
die() { printf '\033[1;31mxx\033[0m %s\n' "$*" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || die "Docker is not installed. See https://docs.docker.com/engine/install/"
docker compose version >/dev/null 2>&1 || die "The Docker Compose plugin is missing. Install docker-compose-plugin."

# ── The public address ───────────────────────────────────────────────────────
#
# No domain, so the site's address is this machine's public IP. Everything the
# browser is told — image URLs, the WebSocket host, the invoice links — is
# generated from APP_URL, so if it disagrees with what you type in the address
# bar, every photograph on the site 404s.
detect_ip() {
    local ip=""

    for endpoint in "https://api.ipify.org" "https://ifconfig.me/ip" "https://icanhazip.com"; do
        ip=$(curl -fsS --max-time 5 "$endpoint" 2>/dev/null | tr -d '[:space:]') || ip=""
        [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] && { echo "$ip"; return; }
    done

    return 1
}

PUBLIC_IP="${PUBLIC_IP:-$(detect_ip || true)}"
[ -n "$PUBLIC_IP" ] || die "Could not work out this machine's public IP. Re-run as: PUBLIC_IP=1.2.3.4 ./deploy.sh"

APP_PORT="${APP_PORT:-80}"
if [ "$APP_PORT" = "80" ]; then
    APP_URL="http://${PUBLIC_IP}"
else
    APP_URL="http://${PUBLIC_IP}:${APP_PORT}"
fi

rand() { openssl rand -hex "${1:-16}"; }

# ── The .env ─────────────────────────────────────────────────────────────────
#
# Written once and then left alone. Regenerating APP_KEY on a second run would
# make every existing session, and every encrypted column, unreadable.
if [ -f .env ]; then
    info "Keeping the existing .env (delete it to start over)."

    # The IP can change (a rebooted VPS, a new provider). The rest must not.
    sed -i.bak -E "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env && rm -f .env.bak
else
    info "Writing .env for ${APP_URL}"

    cat > .env <<ENV
APP_NAME="UrbanServe Demo"
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=${APP_URL}
APP_PORT=${APP_PORT}

APP_LOCALE=en
APP_TIMEZONE=Asia/Kolkata

# This container provisions itself — database, migrations, demo data. Without
# this the web installer (M15) would intercept every request and ask a human to
# do it all again through the browser.
APP_INSTALLED=true

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=urbanserve
DB_USERNAME=urbanserve
DB_PASSWORD=$(rand 20)
DB_ROOT_PASSWORD=$(rand 20)

# Durable, because the queue worker and the scheduler live in another process
# and an in-memory driver would leave them talking to themselves.
SESSION_DRIVER=database
SESSION_LIFETIME=120
# There is no TLS on a bare IP. A secure cookie here would simply never be sent,
# and nobody could log in.
SESSION_SECURE_COOKIE=false

CACHE_STORE=database
QUEUE_CONNECTION=database

BROADCAST_CONNECTION=reverb

# What the *browser* dials. Empty host = the site's own host, and port 80
# because nginx proxies /app and /apps through to Reverb — so the firewall only
# ever has to know about port 80.
REVERB_APP_ID=$(( RANDOM * RANDOM ))
REVERB_APP_KEY=$(rand 16)
REVERB_APP_SECRET=$(rand 24)
REVERB_HOST=
REVERB_PORT=${APP_PORT}
REVERB_SCHEME=http

# What \`reverb:start\` binds to, inside the container.
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

MAIL_MAILER=log

# Set to false and rebuild to keep the database you already have.
DEMO_SEED=true
ENV
fi

# ── Up ───────────────────────────────────────────────────────────────────────
info "Building the image (a few minutes the first time)…"
docker compose build

info "Starting the stack…"
docker compose up -d

info "Waiting for the app to answer…"
for _ in $(seq 1 90); do
    if curl -fsS --max-time 3 "http://127.0.0.1:${APP_PORT}/up" >/dev/null 2>&1; then
        break
    fi
    sleep 5
done

if ! curl -fsS --max-time 3 "http://127.0.0.1:${APP_PORT}/up" >/dev/null 2>&1; then
    warn "The app has not answered yet. It may still be seeding — watch it with:"
    warn "  docker compose logs -f app"
    exit 0
fi

cat <<DONE

  ────────────────────────────────────────────────────────────
   The demo is up:  ${APP_URL}

   Admin      ${APP_URL}/login    admin@demo.test / password
   Customer                       customer@demo.test / password
   Provider                       provider@demo.test / password

   Dashboard  ${APP_URL}/admin/dashboard

   Logs       docker compose logs -f app
   Stop       docker compose down
   Wipe       docker compose down -v      (deletes the demo data)
  ────────────────────────────────────────────────────────────

DONE
