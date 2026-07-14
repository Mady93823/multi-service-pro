---
title: 06 — Roadmap & Build Order
tags:
  - internal
  - roadmap
---

# 06 — Roadmap & Build Order

> [!tip] Rule
> Every phase ends **demo-able**: seeded data, clickable flow, deployed preview. Client sees progress; nothing stays 80%-done. Do not start a phase before the previous phase's gate passes.

## Phase 0 — Sign-off (now)
- [x] Requirement analysis docs (this set)
- [x] Client approves direction (client doc v1.1, 2026-07-04)
- [x] Decisions: **Razorpay** gateway; **single-city India launch on multi-city build**; pay-after-service enabled
- [x] Realtime engine: **Laravel Reverb — no Node.js** (client hosting constraint, 2026-07-06, D11)
- [x] Urban Company parity pass: job OTP, cancellation fee, favorites/rebook, review photos, cross-sell, M16 helpdesk
- [ ] Client answers remaining (client doc §10): commission model, payout cycle, OTP, brand name, hosting, launch city
- [ ] Low-fi wireframes of the 6 key screens: home, service page, checkout, customer tracking map, provider job screen, admin dashboard

**Gate:** written client approval.

## Phase 1 — Foundation
1. Scaffold: Laravel 12 + Inertia React starter (TS), Tailwind v4, shadcn/ui init, Pint + Larastan + Pest, ESLint/Prettier
2. Reverb install + hello-world broadcast (Echo toast on a test event) — proves the WebSocket deploy shape early
3. M01 auth + roles + 3 layout shells (Customer/Provider/Admin navigation)
4. M02 catalog (admin CRUD + public browse/search)
5. M14 settings registry (branding tokens: name/logo/colors/currency/timezone — D8) + M15 installer base (requirements check, .env writer, migrate+seed, admin create)
6. i18n skeleton (D9): translation JSON structure + helper wired into all layouts
7. Seeders: demo categories/services (cleaning, salon, AC repair, plumbing, electrician, painting)

**Gate:** fresh-VPS install via browser wizard → browsable catalog, 3 role logins.

## Phase 2 — Booking core
1. M03 zones (admin polygon draw) + customer address book with Leaflet pin picker ← *first Leaflet integration, de-risks maps early*
2. M04 booking engine + state machine + slots + history + job-start OTP + cancellation fee + favorites/rebook
3. M05 provider onboarding + KYC review queue
4. M06 dispatch (nearest + broadcast + manual, offer timeouts)

**Gate:** end-to-end booking placed → dispatched → accepted → completed (status buttons, no tracking yet), all transitions in history table.

## Phase 3 — Live tracking + realtime ⭐
1. Tracking channels + `routes/channels.php` authorization + ping endpoint (validate/persist/broadcast)
2. Provider journey screen (Geolocation watch, Start/Arrived, presence indicator)
3. Customer live map (react-leaflet, animated marker, ETA, fallback polling)
4. `user.{id}` broadcast notifications + M11 (FCM + in-app + mail)

**Gate:** full acceptance checklist in [[05-Live-Tracking]] on two physical devices.

## Phase 4 — Money
1. M08 gateway abstraction + `RazorpayGateway` (UPI-first ordering) + webhooks + wallet
2. `StripeGateway` second implementation (product requirement, D8)
3. M09 commission snapshots + earnings ledger + payout requests
4. GST invoices (PDF, tax_breakup snapshot), refund flows

**Gate:** Razorpay sandbox payment → booking paid → completed → correct earning; refund → wallet; ledgers reconcile; GST invoice correct.

## Phase 5 — Growth tools
1. M10 reviews (incl. photos), M12 coupons + banners + referral program
2. M13 dashboard KPIs + reports + CSV export + admin support tools (login-as, audit log)
3. M16 helpdesk (tickets + admin queue)
4. M14 CMS pages/FAQs finalized

**Gate:** admin can run the business without touching code.

## Phase 6 — Product surface & admin depth (M17–M27, scoped 2026-07-12)

The business logic is done; this is the product a buyer sees and the depth an operator needs. Order below is a **dependency order, not a wish list** — the shell before the screens, the media picker before everything that picks media, the module registry last because it can only register modules that exist.

1. ~~**M17 Admin IA & settings hub**~~ — **done 2026-07-12**: collapsible sidebar groups, `/admin/settings/{group}` sub-pages with per-group rule providers + a key-ownership guard test (D24), admin Customers screen with block/unblock (D28). Dispatch, tracking and `features.otp_required` got their first UI on the way.
2. ~~**M18 Media Manager**~~ — **done 2026-07-13**: `media_assets` + public-disk `library` collection, `/admin/media`, the shared `MediaPicker` (pick-or-upload, JSON so it can sit on top of a form), copy-on-pick with usage stamping (D29), `media:prune-library`. Banners are its first consumer.
3. ~~**M19 Frontend CMS pack**~~ — **done 2026-07-13**: menus resolved server-side with rotten items dropped (D30), storefront header/footer/cookie banner/custom code (D26), login-page appearance, testimonials, sponsors, popups, contact→ticket, newsletter. Homepage sections were dropped here and built once as blocks (D31).
4. ~~**M20 Page builder**~~ — **done 2026-07-14**: `page_blocks` + `BlockRegistry`, 14 typed blocks behind one schema-driven admin form (D22), server-resolved blocks presented through the existing resources (D32); the storefront home *is* a page now.
5. ~~**M21 Blog**~~ — **done 2026-07-14**: categories, markdown posts, publication as a moment (scheduled posts 404 until their time), public `/blog` + RSS feed, per-post SEO fields for M24.
6. ~~**M22 Payments hub**~~ — **done 2026-07-14**: `/admin/payments`, offline + bank-transfer payments settled through the existing row-locked `ConfirmPayment` with **no new payment state** (D27), payout accounts that the request still snapshots (D33), admin wallet adjustment through `WalletService`.
7. ~~**M23 Communications**~~ — **done 2026-07-14**: SMTP in settings + synchronous test send, email templates as an optional layer with the shipped default underneath (D25), `SmsGateway` contract (MSG91/Twilio, raw HTTP) that is inert until configured, one `PlatformNotification` base + a single `via()` resolver (D34), event × channel matrix + user opt-outs, announcement composer.
8. ~~**M24 System settings hub**~~ — **done 2026-07-14**: generated `sitemap.xml` + `robots.txt` + schema.org JSON-LD + per-record meta overrides, currency as *format only* (D23 realised — `Money::format()` has no currency argument left to abuse), API keys / reCaptcha / analytics that stay inert until configured and fail open when they are (D35), `/admin/system` with a cron heartbeat and the `app:update` runner.
9. ~~**M25 Cities**~~ — **done 2026-07-15**: `cities` table, `zones.city_id` (the free-text string backfilled into rows and dropped), storefront city switcher (detected from the address pin, overridable), a city gate that is the zone gate one level up and never a second geography, a city's **timezone deciding its slot grid** (D36), and a per-city dashboard card. Switching a city off closes the town without touching its bookings.
10. ~~**M26 Staff roles & permissions**~~ — **deferred past launch** (client, 2026-07-15): the operator is one person at launch, and a permission system with nobody on the other side of it has no users. Revisit after some days in production.
11. ~~**M27 Module manager**~~ — **deferred past launch** with M26: it is a CodeCanyon-buyer feature, and this install wants every module on.

**Gate (revised):** on a fresh install with **zero** third-party keys, an admin can rebrand the storefront (menus, home blocks, footer, legal, blog), take an offline payment end to end and send a templated email — all from the browser, no code, no `.env` edit. *(The staff-account and module-toggle halves of the original gate move with M26/M27.)*

> [!warning] Deferred gates come due here
> The Phase 1 gate (fresh-VPS wizard install) and the Phase 3 gate (two-device tracking checklist) are still open. Phase 6 adds ~15 tables and a dozen settings groups to the installer's surface area, so **run the installer gate before or during Phase 6, not after it**.

## Phase 7 — Hardening & handover

> Started 2026-07-15, with **M26 + M27 deferred past launch** (above). The remaining Phase 6 work was product depth; the road to a live server runs through here.

1. ~~Security pass: authz audit on every route, rate limits, file upload validation, webhook idempotency replay tests~~ — **done 2026-07-15 (P7.1, ADR D37)**: one `UploadRules` allowlist, `ServesPrivateFiles` (inline images / downloaded documents / always `nosniff`), the framework's private-disk routes **closed** (`'serve' => false`), a **global** `SecurityHeaders` middleware (a `web`-group one never runs on a login redirect — `auth` is priority-sorted ahead of it), three named rate limiters plus a sweep that fails any unauthenticated write route without one, a secret-exposure sweep derived from the key names, and webhook replay pinned for both gateways. **Still open here: a CSP** (D26's custom JS and M24's analytics IDs make today's policy `unsafe-inline`-only), and `SESSION_SECURE_COOKIE` set by the installer when `APP_URL` is https.
2. ~~Performance: query audit (N+1)~~ — **done 2026-07-15 (P7.2)**: `Model::preventLazyLoading()` outside production (a lazy load is a test failure, not a slow page) + `QueryBudgetTest`, which asserts the property rather than a magic number — **the query count must not grow when the data does**. It found one real N+1 the lazy-load guard structurally cannot see (`/admin/payments` asked medialibrary for a proof badge per row, and medialibrary answers with its own query instead of touching the relation). Also: `DB::prohibitDestructiveCommands()` in production, and the parallel-suite flake fixed (the System test really ran `optimize:clear`, and `bootstrap/cache` is shared across workers). **Still open here: image conversions and Lighthouse ≥ 90 on key pages.**
3. ~~M15 installer finalized incl. Reverb env + supervisor/systemd templates (reverb + queue worker) + update command~~ — **done 2026-07-15 (P7.3)**: the wizard writes a deployable `.env` (Reverb, durable session/cache/queue, `SESSION_SECURE_COOKIE` from the URL's scheme), seeds the CMS as part of the *base* install, and hands over cron + Supervisor + systemd as copy-paste text with this install's own paths. **It also found the bug only a real install could show: the browser was reading `VITE_REVERB_APP_KEY` from the prebuilt bundle, while the installer mints a fresh key per install — every buyer's realtime would have failed silently.** Echo now takes its config from the response. A dead queue worker gets its own banner (the worker stamps the heartbeat, so only a worker can vouch for one). **Still open: the Envato purchase-code toggle (D8), and the fresh-VPS run itself.**
4. Product-ization pass (D8/D9): ~~demo mode~~ + nightly reset, PWA (vite-plugin-pwa), SEO pack (SSR, sitemap, schema.org), branding audit — zero hardcoded strings/colors/names
   - **Showcase demo done 2026-07-15 (P7.5)**: `php artisan demo:seed [--fresh]` → `ShowcaseSeeder` — 20 committed photographs, 6 providers, 12 customers, **90 days of trade** (~170 bookings, cash/gateway/wallet, cancellations, reviews, released earnings, two payouts), all of it written by the **real** machinery (state machine, `ConfirmPayment`, earnings listener, `SubmitReview`) so every figure reconciles and every row can be clicked. **The paper trail is then backdated**, because `DashboardMetrics` groups by `created_at` and ninety days seeded in ninety seconds is one spike on today. **Not** in `DatabaseSeeder`; no test runs it. Still open in this item: the nightly reset (a public demo site needs it; a client screen-share does not).
5. ~~Docs: install guide, admin manual, provider guide~~ — **done 2026-07-15 (P7.4)**: `docs/handover/` — install guide (requirements → wizard → **the three processes that fail silently**), admin manual (the booking/money model an operator has to hold in their head, incl. *why a cash job's provider balance goes negative*), provider guide. Written for the reader, not the codebase: no file paths, no artisan, nothing that needs a developer.
6. Deploy to client's VPS, seed real catalog, smoke-test tracking on-site

**Gate:** client acceptance on their own server.

## Backlog (post-v1)

**v1.5:** extra charges mid-job (provider adds items after inspection, customer approves + pays difference in-app — critical for repair category, UC does this), recurring bookings (subscriptions), provider tips, service bundles/packages, **membership plans** (UC Plus style: paid plan, discount % on every booking, settings-driven), **re-service warranty** (free revisit within X days if issue — linked zero-charge booking), Hindi translation shipped. *(SMS channel was promoted out of this list into M23; multi-currency stays out — see D23.)*

**v2 / sellable add-on modules:** in-app chat customer↔provider (reuse Reverb channels), native mobile apps (API-first ready), WhatsApp notification channel (Gupshup/Interakt), multi-vendor companies (provider teams), city-manager role (franchise), **product marketplace** (UC-style spare parts/consumables sold with services), provider training/certification module, AI provider matching / review summaries.

## Standing risks

| Risk | Mitigation |
|---|---|
| Tracking demo fails on real devices | Phase 3 gate = physical-device checklist; Leaflet touched already in Phase 2 |
| Client answers late (gateway etc.) | Interfaces isolate the wait; only Phase 4 hard-blocks |
| Nominatim rate limits | Cache aggressively; `Geocoder` interface allows swap |
| Scope creep ("small additions") | Client doc §2.2 defines out-of-scope; new asks = written change requests |
| First-project quality pressure | Cross-cutting definition of done in [[02-Modules]]; no phase-skipping |

Related: [[00-Overview]] · [[02-Modules]]
