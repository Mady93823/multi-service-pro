---
title: Production VPS Setup
tags:
  - handover
---

# Going live on a VPS with your own domain

This guide takes you from a blank server to a running site on your own domain, ready for real customers. It is the server-provisioning companion to the **Installation Guide** — that guide covers the browser wizard; this one covers everything around it: the domain, the web server, HTTPS, and the background services.

Plan for about 30–45 minutes. The commands assume a fresh **Ubuntu 22.04 / 24.04** server; adjust package names for another distribution. Anywhere you see `yourdomain.com` or `/var/www/urbanserve`, use your own.

> You only edit files and run commands in this guide **once**, to set the server up. After it is live, everything about running the business is done in the browser.

---

## What you need before you start

- A **VPS** (any provider — DigitalOcean, Hetzner, Linode, AWS Lightsail, a cPanel host, …) with root or `sudo` access. 1 vCPU / 2 GB RAM is a comfortable start.
- A **domain name** you own.
- The **UrbanServe release** (the project files) to upload.
- Your database will be created on the same server below.

---

## 1. Point your domain at the server

In your domain registrar's DNS settings, create two **A records** pointing at your server's public IP:

| Type | Name | Value |
|---|---|---|
| A | `@` | your server's IP |
| A | `www` | your server's IP |

DNS can take a few minutes to a few hours to propagate. You can continue with the next steps meanwhile; you only need it working before the HTTPS step (step 6).

---

## 2. Prepare the server

Connect over SSH (`ssh root@your-server-ip`), then update and install what the platform needs.

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.3 and the required extensions
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-gd php8.3-zip php8.3-bcmath php8.3-intl

# Web server and database
sudo apt install -y nginx mariadb-server unzip

# Composer (dependency manager) — only needed if your release is not pre-built
sudo apt install -y composer
```

> The browser installer checks every required PHP extension on its first screen and tells you if one is missing, so you do not have to verify this list by hand.

---

## 3. Create the database

```bash
sudo mysql
```

At the `MariaDB>` prompt, create an empty database and a user (replace the password):

```sql
CREATE DATABASE urbanserve CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'urbanserve'@'localhost' IDENTIFIED BY 'a-strong-password-here';
GRANT ALL PRIVILEGES ON urbanserve.* TO 'urbanserve'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Leave the database **empty** — the installer creates every table. Keep the database name, user and password for the wizard.

---

## 4. Upload the project

Put the files in a directory such as `/var/www/urbanserve`, then set ownership and permissions so the web server can write where it needs to.

```bash
sudo mkdir -p /var/www/urbanserve
# upload/extract your release into that directory, then:

cd /var/www/urbanserve
sudo chown -R www-data:www-data /var/www/urbanserve
sudo find storage bootstrap/cache public -type d -exec chmod 775 {} \;
```

If your release does **not** already include its `vendor/` folder and a built front-end, run these once (skip if it does):

```bash
composer install --no-dev --optimize-autoloader
```

---

## 5. Configure Nginx

The single most important rule: the site's document root points at the **`public/`** directory, not the project root. If it points at the project root, your whole application is downloadable.

Create `/etc/nginx/sites-available/urbanserve`:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/urbanserve/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Live tracking and notifications travel over WebSockets (Reverb, port 8080).
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }
    location /apps {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 20M;
}
```

Enable it and reload:

```bash
sudo ln -s /etc/nginx/sites-available/urbanserve /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 6. Turn on HTTPS (required)

The platform needs HTTPS in practice — browsers only give live GPS location to a secure site, so tracking silently fails without it. A free Let's Encrypt certificate takes one command:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

Certbot edits your Nginx config to serve HTTPS and sets up automatic renewal. Choose the option to **redirect HTTP to HTTPS** when it asks.

---

## 7. Open the installer

The whole of "starting the installer" is copying the example configuration file:

```bash
cd /var/www/urbanserve
cp .env.example .env
sudo chown www-data:www-data .env
```

That is the only preparation. **Do not edit `.env` by hand** — the wizard writes it for you.

---

## 8. Run the browser wizard

Open **`https://yourdomain.com`** in a browser. You are taken to the setup wizard. Follow the **Installation Guide** for the five short steps:

1. **Requirements** — green ticks.
2. **Database** — your site name, your site URL (`https://yourdomain.com`), and the database credentials from step 3.
3. **Migrate** — creates the tables. Tick demo data only if you want a sample site to click through; leave it off for a clean launch.
4. **Administrator** — your own login.
5. **Finish** — the wizard closes itself and shows the three background services below. Use **Go to login** to sign in, or **Go to home page** to see your storefront.

From this point the site is a normal, production-configured application on your domain.

---

## 9. Start the three background services

The site loads without these — and quietly does less, with nothing on screen to say so. The finish screen and **Admin → System** both print these blocks **with your own paths already filled in**; copy them from there. Templates:

**Cron** (payouts, expiring unpaid bookings, scheduled jobs). Run `sudo crontab -e -u www-data` and add:

```
* * * * * cd /var/www/urbanserve && php artisan schedule:run >> /dev/null 2>&1
```

**Queue worker** (sends every email and notification). Create `/etc/systemd/system/urbanserve-worker.service`:

```ini
[Unit]
Description=UrbanServe queue worker
After=network.target

[Service]
User=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/urbanserve/artisan queue:work --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
```

**Reverb** (the live map and the notification bell). Create `/etc/systemd/system/urbanserve-reverb.service`:

```ini
[Unit]
Description=UrbanServe realtime server
After=network.target

[Service]
User=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/urbanserve/artisan reverb:start

[Install]
WantedBy=multi-user.target
```

Enable and start both:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now urbanserve-worker urbanserve-reverb
```

---

## 10. Final checks

1. Open **Admin → System**. The scheduler and queue worker should show as alive; anything you have not set up yet (email, SMS, push) shows as "not configured", which is not an error.
2. Place a test booking to confirm the flow end to end.
3. Set up your essentials in the panel: **Branding** (name, logo, colours), **Locations** (cities and zones), your **Catalogue**, and — when ready — your **Payments** keys and **Email** (SMTP).

You are live.

---

## Updating to a new release later

Upload the new files over the old ones, keeping your `.env` and `storage/` directories. Then press **Run update** on **Admin → System** (or run `php artisan app:update`), and restart the two services so they pick up the new code:

```bash
sudo systemctl restart urbanserve-worker urbanserve-reverb
```

---

## Quick troubleshooting

| Symptom | Likely cause |
|---|---|
| Images are broken | The `public/storage` link is missing — re-run the update, or check `public/` is writable |
| The live map never moves, the bell never lights | The Reverb service is not running, or the Nginx `/app` proxy is missing |
| No emails arrive | SMTP is not configured (**Settings → Email**), or the queue worker is not running |
| Payouts never release, unpaid bookings never expire | Cron is not running |
| The whole project source is downloadable | The document root is the project root, not `public/` — fix step 5 immediately |
