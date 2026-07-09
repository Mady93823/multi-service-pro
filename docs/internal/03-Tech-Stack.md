---
title: 03 — Tech Stack & Decisions
tags:
  - internal
  - tech-stack
  - decisions
---

# 03 — Tech Stack & Decisions

## Locked versions (pin at scaffold time)

| Layer | Choice | Version |
|---|---|---|
| PHP | php-fpm | 8.3+ |
| Framework | Laravel | 12.x (latest stable at kickoff) |
| DB | MySQL | 8.0+ (needs spatial functions + FULLTEXT) |
| Frontend bridge | Inertia.js | v2 |
| UI runtime | React + TypeScript | 19 / TS 5 strict |
| Styling | Tailwind CSS | v4 |
| Components | shadcn/ui | latest (CLI-managed) |
| Charts | recharts | (comes with shadcn charts) |
| Maps | Leaflet + react-leaflet | 1.9.x / latest |
| Tiles | OpenStreetMap standard tiles | — (attribution required) |
| Realtime server | Laravel Reverb (Pusher protocol) | 1.x |
| Realtime client | laravel-echo + pusher-js | latest |
| Push/OTP | Firebase FCM + Phone Auth via `kreait/firebase-php` | latest |
| Auth tokens | Laravel Sanctum | bundled |
| Roles | spatie/laravel-permission | 6.x |
| Media | spatie/laravel-medialibrary | 11.x |
| PDF | barryvdh/laravel-dompdf | latest |
| Payments | razorpay/razorpay (PHP SDK) | latest |
| i18n | laravel-react-i18n | latest |
| PWA | vite-plugin-pwa | latest |
| Build | Vite | bundled with Laravel |

## Key decisions & why (ADR-lite)

### D1 — Inertia + React + shadcn/ui (not Livewire, not Blade)
Client demands "modern fast cool" UI **and** told us to source components from shoogle.dev. shoogle.dev is a **shadcn search engine** → shadcn/ui is React → Inertia+React is the official Laravel starter pairing. One deploy, no separate SPA/API split, SSR-capable, and every shoogle.dev block drops in directly. **Consequence:** all UI work is React/TSX; no Blade views except emails/installer.

### D2 — Modular monolith (updated by D11: no microservice)
Everything in one Laravel app for easy install + handover. Originally one Node.js tracking microservice existed per client mandate; D11 removed it. See [[01-Architecture]]. Rejected: nwidart/laravel-modules — adds packaging ceremony a first project doesn't need; "expandable" is delivered via domain folders + events + settings flags instead.

### D3 — Leaflet + OSM (no Google Maps anywhere)
Client-specified, and it kills the Google billing account requirement. Geocoding via Nominatim (rate-limited, cached, proper User-Agent). If volume ever exceeds Nominatim policy → swap to self-hosted Nominatim or LocationIQ; isolate behind `Geocoder` interface from day one.

### D4 — Firebase scope = FCM + optional phone OTP only
Not Firebase Auth as primary auth, not Firestore, not Realtime DB. Laravel owns identity and data (client owns their data, installer stays simple). Firebase config = env keys; app must boot and run with Firebase unconfigured (features flag off gracefully).

### D5 — Database queue/cache default, Redis optional
Easy-install first: default `QUEUE_CONNECTION=database`, `CACHE_STORE=database`. `.env` switch to Redis documented for scale. Reverb single instance at launch; Redis pub/sub only when horizontally scaling. Location broadcasts use `ShouldBroadcastNow` (skip queue — latency).

### D6 — ~~WebSockets via Socket.IO on the Node service~~ SUPERSEDED by D11
Original: one Node Socket.IO service carrying `/tracking` + `/app` namespaces. Dead — client cannot run Node.js on their hosting. Principle survives: ONE realtime brain — now Laravel Reverb (D11).

### D7 — Payments behind an interface — RESOLVED: Razorpay default
Client confirmed Razorpay (2026-07-04). `PaymentGateway` contract stays: `RazorpayGateway` (default; UPI-first checkout ordering, INR), `StripeGateway` (second implementation — non-India product buyers), `CashGateway` (pay-after-service, enabled at launch). Webhooks are source of truth; Razorpay webhook signature verification mandatory.

### D8 — CodeCanyon product-ization
Codebase is a white-label product; the client is customer #1. Consequences: zero hardcoded branding (name/logo/colors/currency/timezone/language all from settings + theme tokens); installer gains optional Envato purchase-code verification (off for direct client install); demo mode (read-only admin, nightly reset); buyer-grade docs; Envato reviewer quality bar. The client-facing doc frames all this as "white-label benefit" and never mentions resale.

### D9 — India-first defaults, i18n from day one
Launch defaults: ₹ INR with Indian digit grouping, GST-compliant invoicing (GSTIN, CGST/SGST/IGST breakup), pay-after-service enabled, UPI-first checkout. Translation JSON files + admin language manager from Phase 1 — retrofitting i18n is the most expensive refactor there is, and product buyers demand it. English ships first; Hindi is a phase-2 content task.

### D10 — Phone-first auth
Registration/login lead with phone + OTP (Firebase Phone Auth, feature-flagged); email+password secondary. Indian users are phone-first; matches field onboarding of providers.

### D11 — Laravel Reverb replaces the Node.js tracking service (2026-07-06)
Client's hosting cannot run Node.js; client chose to continue with Laravel. **Laravel Reverb** = first-party WebSocket server (Pusher protocol), runs as `php artisan reverb:start` under supervisor/systemd; frontend uses **laravel-echo + pusher-js**. HTML5 Geolocation + Leaflet + OpenStreetMap unchanged — only the transport engine changed.

Consequences:
- One deployable, one language — installer no longer generates a second `.env`, no PM2/Node docs
- Custom tracking JWTs + HMAC checkpoint sync **deleted** — standard Laravel channel authorization (`routes/channels.php` policies) and direct DB writes replace them; `firebase/php-jwt`, `jsonwebtoken`, `TRACKING_JWT_SECRET`, `CHECKPOINT_HMAC_SECRET` all die
- Provider GPS pings go to a Laravel endpoint → validate, store, broadcast `LocationUpdated` on `private-tracking.booking.{id}` (the route landed on session-auth `web` rather than Sanctum `/api/v1` — see D13)
- Presence channels give "provider connected / customer watching" for free
- In-app realtime (old `/app` namespace) becomes Echo private channel `user.{id}` via Laravel broadcast notifications
- Hosting: VPS still required (long-running process + queue worker) but PHP-only stack
- Scale path: Reverb horizontal scaling via Redis pub/sub — same as before
- Full spec rewritten in [[05-Live-Tracking]]

### D12 — Point-in-polygon in PHP, not MySQL spatial (2026-07-07)
Zone membership (`ST_Contains` in the original schema) is computed by a PHP ray-casting check (`App\Domain\Zones\PointInPolygon`) over the zone's GeoJSON instead of a MySQL 8 `GEOMETRY ... SRID 4326` column + spatial index. Why: dev runs MariaDB (XAMPP), tests run sqlite, and CodeCanyon buyers land on shared hosting where MySQL-8 SRID columns break installs (D8 portability beats micro-optimization). Zones are a handful of small polygons per city — the PHP check is microseconds and runs only on address save / zone edits, never per catalog request (addresses snapshot their `zone_id`). Revisit only if a deployment ever has thousands of zones; the `ZoneResolver` seam is where a spatial implementation would slot in.

### D13 — Tracking endpoints ride session auth on `web` routes (2026-07-09)
The tracking loop was specced as `POST /api/v1/bookings/{id}/tracking/ping` behind Sanctum (D11 bullet). Shipped instead as `web` routes returning JSON (`provider/jobs/{booking}/tracking/{start,ping,stop}` and `bookings/{booking}/tracking/last`), authenticated by the same session cookie as the rest of the panel. Why: the only client is the Inertia SPA already inside that session — adding Sanctum bought a second auth path, a token store, and an extra failure mode for zero benefit. CSRF comes from Laravel's `XSRF-TOKEN` cookie, forwarded by a small `resources/js/lib/http.ts` fetch helper (no axios dependency). The public API (`/api/v1`, mobile apps) is still a clean add later — controllers are thin and the actions carry the logic.

### D14 — Push (FCM) ships as an inert channel until Firebase exists (2026-07-09)
`M11` delivers `database` + `broadcast` (Reverb) notification channels now. The FCM channel is a real class (`App\Notifications\FcmChannel`) but `via()` only lists it when `services.fcm.credentials` is set, so an unconfigured install never touches it — the platform must run with Firebase absent (client constraint), and the client has not supplied a Firebase project (client doc §10). `fcm_tokens` + the registration endpoint ship now so tokens accumulate from day one and push lights up the moment credentials land. No Firebase SDK dependency is added to `composer.json` before it is needed.

### D15 — Payments: no vendor SDKs, credentials in settings, money settles before dispatch (2026-07-09)
`M08` talks to Razorpay and Stripe through the raw Laravel HTTP client behind a `PaymentGateway` contract — no `razorpay/razorpay` or `stripe/stripe-php` dependency. Why: the single-deployable, easy-install promise (a buyer uploads a zip; every added SDK is another version to keep current) and the surface we use is three endpoints per gateway.

Gateway credentials live in **admin-editable settings, not `.env`**, so the installer wizard can configure a live site without shell access. Consequences, all shipped:
- Secrets are **write-only in the admin UI**. `SettingsController::edit` sends the publishable halves (`razorpay_key_id`, `stripe_publishable_key`) plus a `*_set` boolean per secret — never the secret itself, because Inertia serializes every prop into the page HTML. A blank field on save means "keep the stored value"; a paired `remove_*` flag is the only way to erase one.
- A gateway is offered at checkout only when `isConfigured()` — no keys, no button.

**Cash bookings start `placed`; gateway and wallet bookings start `pending_payment`.** `ConfirmPayment` / `PayWithWallet` move them to `placed` and fire `BookingPlaced` only once money settles, so **an unpaid online booking is never dispatched to a provider**. `bookings:expire-unpaid` (every 5 min) closes the window using `booking.payment_timeout_minutes`.

Trust rules: **never trust a redirect.** Razorpay's checkout.js callback is HMAC-verified (`hash_hmac('sha256', "$orderId|$paymentId", $keySecret)` compared with `hash_equals`); Stripe's return leg is re-verified against the API (`isSessionPaid`). Webhooks are the backstop and are signature-verified against the **raw body** (Razorpay: body vs `X-Razorpay-Signature`; Stripe: `"{$t}.{$body}"` vs the `v1=` part of `Stripe-Signature`), CSRF-exempt (`webhooks/*`) and throttled `60,1`. `ConfirmPayment` locks the payment and booking rows and no-ops a replay, so a retried webhook cannot double-place a booking. Money captured against an already-terminal booking stays `Paid`, logs a warning, and is refunded from the admin booking screen.

Wallet is an **append-only ledger** (`wallet_transactions` with `balance_after`) plus a cached `wallets.balance` written in the same locked transaction, so `balance == credits − debits` always holds. **Refunds v1 always go to the wallet** — instant, no gateway round-trip, and the same seam takes gateway-side refunds later. Cancelling a paid booking refunds total minus the cancellation fee; admin cancellation refunds in full. Cash settles on completion via the auto-discovered `SettleCashOnCompletion` listener (registered once — see the M06 double-fire lesson).

## UI sourcing workflow (shoogle.dev)

1. Need a block (e.g., booking form, dashboard, pricing card) → search **shoogle.dev**
2. Confirmed useful registries: Watermelon UI (map, map-marker), Eldora UI (map), Stow (booking forms), Shadcn UI Kit (dashboards), lndev/UI (visualize bookings), shadcnblocks.com
3. Install via shadcn CLI (`npx shadcn add <registry-url>`), then restyle to project theme tokens
4. Rule: components are copied in (shadcn model) → we own the code; never leave registry styling that fights the theme

## Design language

- Theme: shadcn neutral base + one accent (client brand color when provided); light + dark
- Font: Inter (self-hosted via Fontsource — no external font CDN)
- Motion: subtle only (Tailwind transitions; no heavy animation libs)
- Every list gets skeleton loaders; every mutation gets optimistic or pending state; toasts via `sonner`

Related: [[01-Architecture]] · [[05-Live-Tracking]] · [[07-Conventions]]
