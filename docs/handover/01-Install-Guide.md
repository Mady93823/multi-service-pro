---
title: Installation Guide
tags:
  - handover
---

# Installing UrbanServe

You install this from a browser. There is no terminal step you cannot skip, except starting the three background processes at the end — and the installer prints those for you, with your own server's paths already filled in.

## 1. What the server needs

| Requirement | Minimum |
|---|---|
| PHP | 8.2 or newer |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`, `zip`, `xml` |
| MySQL / MariaDB | MySQL 8.0+ or MariaDB 10.6+ |
| Web server | Nginx or Apache, document root pointed at **`public/`** |
| HTTPS | Required in practice — the provider app reads GPS, and browsers only give geolocation to a secure origin |

The installer's first screen checks all of this and tells you exactly what is missing. You do not have to check it yourself.

## 2. Upload and point the web server at `public/`

Upload the release, then set the document root to the **`public/`** directory — not the project root. If the document root is wrong, the whole application source is downloadable, so this matters more than anything else on this page.

Make sure these are writable by the web server user:

```
storage/
bootstrap/cache/
public/
.env
```

## 3. Create an empty database

Create a database and a user with full rights on it. The installer will not create the database for you — it connects to one that already exists, and it tells you plainly if it cannot.

## 4. Run the wizard

Open your site in a browser. You will be redirected to `/install`.

1. **Requirements** — green ticks, or a list of what to fix.
2. **Database** — your site name, your site URL, and the database credentials. The URL matters: it decides the secure-cookie setting and the WebSocket host, so enter the address people will actually type (`https://…`).
3. **Migrate** — creates the tables. Tick **demo data** if you want a browsable example site (services, providers, bookings, a couple of cities); leave it off for a clean start. Either way you get the settings, the languages, the legal pages and an editable home page.
4. **Administrator** — your own login.
5. **Finish** — the three processes below.

The wizard then locks itself. `/install` will not open again.

## 5. The three processes (do not skip this)

The site works without them **and quietly does less** — every page still loads, so nothing tells you they are missing. The finish screen and **Admin → System** both give you these blocks with your own paths in them; copy them from there rather than from here.

| Process | What dies without it |
|---|---|
| **Cron** (`schedule:run`) | Payouts never release, unpaid bookings never expire, exports pile up |
| **Queue worker** (`queue:work`) | Every notification and email is written and **never sent** |
| **Reverb** (`reverb:start`) | The live tracking map never moves; the notification bell never lights up |

Cron goes in the crontab. The queue worker and Reverb need a process supervisor (Supervisor or systemd) so they restart on reboot and on crash.

Point your web server to proxy WebSocket traffic (`/app` and `/apps`) to port **8080**, where Reverb listens.

## 6. First things to set up in the admin panel

Everything below is optional — the site runs with none of it — and everything below is in the browser. You will not edit a config file.

1. **Settings → Branding**: name, logo, colours.
2. **Locations → Cities**, then **Zones**: draw the areas you serve. A city's **timezone** decides the booking slots people are offered in that town, so set it correctly.
3. **Catalog**: categories and services, and which zones each service covers.
4. **Settings → Payments**: your Razorpay or Stripe keys, or switch on bank transfer and add an account. Cash-on-completion works with none of them.
5. **Settings → Email**: SMTP. Until you set this, emails are simply not sent — nothing breaks, and the in-app notifications still arrive.
6. **Admin → System**: check the scheduler and the queue worker are both showing as alive.

## Updating to a new release

Upload the new files over the old ones, keeping your `.env` and `storage/`. Then either run `php artisan app:update`, or press **Run update** on **Admin → System** — they are the same thing, and the button shows you the output.
