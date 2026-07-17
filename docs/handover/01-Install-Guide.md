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

## 3. Copy the example configuration

```
cp .env.example .env
```

That is the only command in this guide, and it is the whole of "starting the installer": the example file carries a line reading `INSTALL=false`, and that line is what opens the wizard. The wizard writes the rest of the file for you — database credentials, site URL, WebSocket keys — and deletes the line when it finishes.

Do not fill anything in by hand. If you have no shell access, copy the file with your control panel's file manager and rename it; a `.env` that is a plain copy of `.env.example` is exactly right.

## 4. Create an empty database

Create a database and a user with full rights on it. The installer will not create the database for you — it connects to one that already exists, and it tells you plainly if it cannot.

MySQL 8 or MariaDB 10.6+. Leave it empty; the wizard creates every table.

## 5. Run the wizard

Open your site in a browser. You will be redirected to `/install`.

1. **Requirements** — green ticks, or a list of what to fix.
2. **Database** — your site name, your site URL, and the database credentials. The URL matters: it decides the secure-cookie setting and the WebSocket host, so enter the address people will actually type (`https://…`).
3. **Migrate** — creates the tables. Tick **demo data** if you want a browsable example site (services, providers, bookings, a couple of cities); leave it off for a clean start. Either way you get the settings, the languages, the legal pages and an editable home page.
4. **Administrator** — your own login.
5. **Finish** — the three processes below.

The wizard then closes itself by deleting the `INSTALL` line from your `.env`. `/install` will not open again, and the site is a normal application from that moment.

If you ever need the wizard back on a fresh, empty database, put `INSTALL=false` back into `.env`. Do not do this on a site that has traded: the wizard refuses to create a second administrator over a database that has already been installed, and it will tell you to remove the line again.

## 6. The three processes (do not skip this)

The site works without them **and quietly does less** — every page still loads, so nothing tells you they are missing. The finish screen and **Admin → System** both give you these blocks with your own paths in them; copy them from there rather than from here.

| Process | What dies without it |
|---|---|
| **Cron** (`schedule:run`) | Payouts never release, unpaid bookings never expire, exports pile up |
| **Queue worker** (`queue:work`) | Every notification and email is written and **never sent** |
| **Reverb** (`reverb:start`) | The live tracking map never moves; the notification bell never lights up |

Cron goes in the crontab. The queue worker and Reverb need a process supervisor (Supervisor or systemd) so they restart on reboot and on crash.

Point your web server to proxy WebSocket traffic (`/app` and `/apps`) to port **8080**, where Reverb listens.

## 7. First things to set up in the admin panel

Everything below is optional — the site runs with none of it — and everything below is in the browser. You will not edit a config file.

1. **Settings → Branding**: name, logo, colours.
2. **Locations → Cities**, then **Zones**: draw the areas you serve. A city's **timezone** decides the booking slots people are offered in that town, so set it correctly.
3. **Catalog**: categories and services, and which zones each service covers.
4. **Settings → Payments**: your Razorpay or Stripe keys, or switch on bank transfer and add an account. Cash-on-completion works with none of them.
5. **Settings → Email**: SMTP. Until you set this, emails are simply not sent — nothing breaks, and the in-app notifications still arrive.
6. **Admin → System**: check the scheduler and the queue worker are both showing as alive.

## Showing the site to someone before it has any trade

A brand-new install is correct and empty: no bookings, flat charts, no photographs. If you need to *demonstrate* the product — to a partner, an investor, your own staff — there is a fuller dataset than the wizard's demo tick:

```
php artisan demo:seed --fresh
```

It rebuilds the database as a company that has been trading for three months: photographs on every service, six providers with ratings, twelve customers, ninety days of bookings, payments, reviews, earnings and payouts. Every demo account signs in with the password `password`.

**`--fresh` deletes everything in the database first.** Never point it at a live site: it refuses to run in production, and that refusal is the only thing between it and your real bookings.

## Updating to a new release

Upload the new files over the old ones, keeping your `.env` and `storage/`. Then either run `php artisan app:update`, or press **Run update** on **Admin → System** — they are the same thing, and the button shows you the output.
