# Running the demo on a VPS

No domain, no TLS, no manual setup. One command, and the site comes up on the server's own IP address with three months of trade already in it.

## On the server

```bash
git clone https://github.com/Mady93823/multi-service-pro.git
cd multi-service-pro
./deploy.sh
```

That is the whole thing. It will:

1. work out the machine's public IP and use it as the site's address,
2. write a `.env` with fresh secrets for **this** install,
3. build the image (compiling the browser bundle inside it),
4. start MySQL, nginx, PHP, the queue worker, the scheduler and Reverb,
5. migrate the database and seed the showcase demo,
6. print the address and the logins.

First run takes a few minutes — mostly the image build and the ~170 seeded bookings.

### Requirements

Docker with the Compose plugin, port **80** open, and about **2 GB** of RAM.

```bash
curl -fsSL https://get.docker.com | sh    # if Docker is not installed yet
```

## Logins

Every demo account uses the password `password`.

| Role | Email |
|---|---|
| Admin | `admin@demo.test` |
| Customer | `customer@demo.test` |
| Provider | `provider@demo.test` |

## Everyday commands

```bash
docker compose logs -f app     # what it is doing
docker compose restart app     # restart (keeps the data)
docker compose down            # stop  (keeps the data)
docker compose down -v         # stop  and DELETE the demo data
```

## If the IP changes

A rebooted VPS can come back on a new address. Re-run `./deploy.sh` — it keeps your data and your secrets, and only rewrites `APP_URL`.

**This matters more than it looks.** Every image URL on the site is generated from `APP_URL`. If it disagrees with the address you actually type in the browser, every photograph on the site 404s while the page itself looks fine.

## What is running inside the container

Five processes, under supervisor. Three of them fail *silently* if they are not running, which is why they are not left to be started by hand:

| Process | What dies without it |
|---|---|
| nginx + php-fpm | The site (this one at least fails loudly) |
| Queue worker | Every notification is written and **never sent** |
| Scheduler | Payouts never release; unpaid bookings never expire |
| Reverb | The live tracking map never moves; the bell never lights up |

WebSockets are proxied through port 80, so the firewall never has to know about port 8080.

## Notes for a demo, not a business

- `APP_DEBUG=false` and the app runs as `production`, so a stray error degrades instead of printing a stack trace at the client.
- Mail goes to the log (`MAIL_MAILER=log`) — nothing is actually sent to `@demo.test`.
- The demo seed runs **once**. It is guarded by a marker file on the storage volume, so restarting the stack in front of the client cannot wipe what they are looking at.
- To rebuild the demo data from scratch: `docker compose down -v && ./deploy.sh`.

## Before this becomes a real site

1. Point a domain at the server and put TLS in front of it (Caddy or nginx + certbot).
2. Set `APP_URL=https://your-domain`, `SESSION_SECURE_COOKIE=true`, `REVERB_SCHEME=https`, `REVERB_PORT=443`.
3. Set `DEMO_SEED=false` and start from a clean database.
4. Fill in real SMTP, and real payment gateway keys, in **Settings**.
