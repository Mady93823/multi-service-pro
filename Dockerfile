# syntax=docker/dockerfile:1
#
# One image, every process. Built for a demo on a bare VPS with no domain and no
# TLS: nginx, php-fpm, the queue worker, the scheduler and Reverb all run under
# supervisor in this container, because all three of those background processes
# fail *silently* (P7.3) and a demo that quietly loses its notifications, its
# payouts and its live map is worse than one that does not start.
#
# The web assets are compiled here rather than committed: `public/build` is
# gitignored, and a stale bundle is how a UI change ships as a blank page.

# ── 1. The browser bundle ────────────────────────────────────────────────────
FROM node:22-alpine AS assets

WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js tsconfig.json components.json ./
COPY resources ./resources

RUN npm run build

# ── 2. PHP dependencies ──────────────────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /build

# --no-scripts: the package discovery script wants the full app, which is not
# here yet. It is re-run against the real tree below.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ── 3. The runtime ───────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

WORKDIR /var/www/html

# gd is not optional: the demo seed *draws* its avatars and sponsor wordmarks
# (a stock photo of a stranger captioned as a customer is a fabricated
# endorsement), and medialibrary generates every image conversion through it.
# pcntl is what lets Reverb and the queue worker shut down cleanly.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd zip bcmath exif pcntl opcache

RUN apk add --no-cache nginx supervisor curl tzdata \
    && mkdir -p /run/nginx /var/log/supervisor

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /build/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /build/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/app/public storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
