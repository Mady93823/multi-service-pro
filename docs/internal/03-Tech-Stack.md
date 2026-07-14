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
| PDF | barryvdh/laravel-dompdf | 3.x |
| CSV | league/csv | 9.x |
| Charts | recharts | 3.x |
| Markdown | league/commonmark (ships with Laravel, via `Str::markdown`) | bundled |
| Prose styles | @tailwindcss/typography | 0.5.x |
| Payments | ~~razorpay/razorpay (PHP SDK)~~ raw HTTP client, no vendor SDK (D15) | — |
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

### D16 — Commission is signed, the earnings ledger is append-only, payouts claim whole balances (2026-07-10)

`M09` charges commission on the **pre-tax** service value (`subtotal + addons − discount`), never on the GST the platform must remit. The rate is resolved **per booking item**: the item's category override, then its parent's, then the global `payments.commission_percent` setting; the blended result is snapshotted onto `bookings.commission_rate_snapshot` at completion so a later rate change can never rewrite history.

Every earnings row obeys one invariant, asserted in tests:

```
net = gross − commission − collected_amount
```

`collected_amount` is what the provider already took at the door — the customer's full total, tax included — on a **cash** job, and zero otherwise. A cash job therefore lands with a **negative net**: the provider owes the platform its commission *and* the GST they pocketed. Nothing clamps that to zero; a payout claims the negative rows alongside the positive ones so the debt offsets rather than being quietly dropped. Positive earnings wait out `payouts.hold_days` (the window a refund can still reverse them in); a debt is available immediately, because a debt does not wait.

`earnings` is append-only like `wallet_transactions`: a refund appends a `reversal` row that negates the job row column for column rather than editing it. Negating a cash job's negative net *credits* the provider — the platform forgives commission on a job it refunded. Recovering the cash the provider physically holds is a real-world action outside the ledger. `unique(booking_id, type)` is the double-write backstop, mirroring `payments.unique(gateway, gateway_ref)`.

A payout request claims the provider's **whole** released balance in one row rather than an arbitrary amount, and the claimed earnings point back at it (`earnings.payout_request_id`). That makes "what did this payout settle" answerable, stops a second request double-spending the same rows without a partial-allocation algorithm, and makes rejection a clean unclaim. Only one request may be open per provider. Money leaves through the admin's own bank transfer — `reference` records the UTR — and the customer wallet is never touched: **a payout is not a wallet movement, it is money leaving the system.**

Invoices are **GST-format PDFs via `barryvdh/laravel-dompdf`** (the first Blade view outside `app.blade.php`; D1's "no Blade" rule was always scoped to UI). Every figure comes from the booking's own snapshot columns — `tax_breakup` was written at checkout — so reprinting an old invoice cannot pick up today's tax rate. The invoice number is derived from the booking id (`INV-2026-000123`), needing no column and never renumbering on reprint. `BookingPolicy@invoice` grants the customer and admins; the assigned provider is not a party to the customer's tax document. Amounts are formatted by `App\Support\Money`, which hand-rolls Indian digit grouping rather than depending on `ext-intl` (shared hosts often omit it, D8).

**Blade now counts for i18n.** `tests/Feature/Localization/TranslationCatalogTest.php` and `scratchpad/reconcile_catalog.php` both scan `resources/views/**/*.blade.php` for `__()`, because the invoice is the one user-facing surface React never renders.

### D17 — Ratings are denormalized-by-recompute; review photos are private-disk but guest-served (2026-07-10)

`M10` keeps `provider_profiles.rating_avg` / `rating_count` (and `jobs_completed`) as denormalized columns, but they are **never incremented** — every change triggers a full recompute over the surviving rows (`SyncProviderRatingOnReviewChange` over `reviews.visible()`, `SyncProviderJobStatsOnCompletion` over completed bookings). Recompute costs one aggregate query at review-write frequency (rare) and buys three properties an increment cannot: hiding a review automatically pulls its star out of the average, a re-fired listener is idempotent (the M06 double-fire lesson made this a design rule, not an optimization), and the columns can always be rebuilt from source of truth. The recompute lives in listeners on `ReviewChanged` / `BookingStatusChanged`, not in the actions — moderation and submission both flow through the same sync without knowing it exists.

Review photos follow the M04 private-disk rule (nothing user-uploaded is web-servable directly), but unlike booking problem photos the storefront must show them to **guests**. The photo route therefore carries no `auth` middleware; `ReviewPolicy@view` takes a *nullable* user — visible review → anyone, hidden review → only the author or an admin, checked via `Gate::allows` so a denial becomes **404, not 403**: after moderation there is nothing left to probe for. The admin check sits inside the policy method rather than a `before()` hook because `before()` never runs for guests.

Moderation **hides, never deletes**: the customer keeps their review (shown to them with the reason), the provider and the public lose it, and the row survives for audit. One review per completed booking is enforced in three layers — policy (owner + completed), FormRequest (duplicate check + `reviews.enabled` kill-switch + `reviews.max_photos`), and a `lockForUpdate` re-check in `SubmitReview` with the `unique(booking_id)` index as the race backstop — the same defense-in-depth shape as M08's payment confirmation.

### D18 — Coupon usage is spent at placement and audited, not counted; referral rewards are referrer-only wallet credits (2026-07-10)

`M12` funnels every coupon decision through one class: `CouponValidator` computes eligibility *and* the discount for both the checkout "apply" preview and the placement itself, so the two can only disagree on timing — and timing is closed by `PlaceBooking` re-running the validator inside the placement transaction under `lockForUpdate` on the coupon row (the M08/M10 lock-and-re-check shape; two tabs cannot double-spend the last `usage_limit` slot). The applied code lives only in the session next to the cart — no persistent "reserved coupon" state to leak or expire.

`coupon_usages` is an **append-only audit trail**, and the caps count it through a join instead of mutating it. A usage is spent at placement; **cancelling or refunding the booking never restores it** (UC parity — the customer exercised the offer). But a booking that dies *unpaid* (`expired` / `failed_payment`) stops counting, because money never moved and the customer never got anything: deleting the row would break the audit, so the counting query excludes those two states instead. The same two states define "first order" for `first_order_only` — a cancelled booking still counts as an order, an abandoned payment attempt does not. Rows are never deleted; a redeemed coupon cannot be deleted either (FK `restrictOnDelete` + action guard) — deactivate it.

The discount enters pricing through the seam M04 left (`bookings.discount` + `coupon_id`) and M09 built around on purpose: `PriceQuote` subtracts it **pre-tax**, and `CommissionResolver` already pro-rates a booking-level discount across lines — a coupon changes no earnings math.

Referral rewards are **referrer-only**: the referee's incentive is a first-order coupon (seeded WELCOME10), not a second wallet credit — one reward path, one wallet writer (`WalletService`, D15). Sign-up creates only a *pending* `referrals` row (`referee_id` unique — a user is referred once, ever); the reward fires on the referee's **first completed booking**, where `RewardReferral` flips pending→rewarded under a row lock, so a double-fired listener or racing completions cannot pay twice. `reward_amount` is snapshotted **at reward time** (null while pending) — the column records what was actually paid, not what the setting said at sign-up; setting it to `0` pauses payouts without hiding the program, and paused referrals stay pending rather than being burned.

### D19 — Dashboard reads snapshots, reports expose rows not builders, impersonation audits before it swaps (2026-07-11)

`M13`'s dashboard and reports never recompute money from current prices: `DashboardMetrics` and every report are plain aggregates over the snapshot columns (`bookings.total`, `earnings.gross/commission/net`, `payout_requests.amount`), and the gate test reconciles each figure against a raw SQL sum on the same data. Revenue charts read the **earnings ledger**, not bookings — reversal rows carry negative amounts, so a refund nets out on the day it happened instead of silently rewriting history.

The `Report` interface exposes **mapped rows only** (`paginate()` returns the `NativePaginated` array shape directly, `rows()` is a generator, `count()` an integer) — never a query builder. One implementation therefore feeds the screen, the inline CSV stream and the queued CSV job with byte-identical figures, and `ReportRegistry` turns an unknown slug into a 404. CSV export follows the M06 degrade rule: over 2 000 rows *and* a real queue driver → `GenerateReportExport` writes to `storage/app/exports` (7-day scheduled prune) and notifies the admin with an admin-gated download whose filename regex is the path-traversal guard; a sync-queue install always streams inline rather than presenting a dead button.

Admin accountability is one append-only table with one writer: `ActivityLogger` records manual transitions, dispatch, refunds, payout decisions, provider reviews, settings saves (**keys only** — settings values include gateway secrets) and both impersonation legs. `log()` swallows its own failure (an audit hiccup must not break a refund), but impersonation uses `mustLog()` — **no audit row, no impersonation**, written *before* the session swap. Impersonation itself: admin-only start, an admin can never be a target, no nesting, session regenerated on both legs (fixation guard), and the stop route deliberately sits outside the admin group because the actor at that moment *is* the impersonated customer/provider — the session key is its only guard. The banner is a shared Inertia prop rendered by every shell, so there is no page an impersonating admin can stand on without seeing it.

### D20 — CMS bodies are markdown sanitized at render, locale codes are filenames and validated like paths (2026-07-11)

`M14`'s public pages store **markdown source, never HTML**. `MarkdownRenderer` is the single output path (`Str::markdown` with `html_input: strip` + `allow_unsafe_links: false`): raw HTML in the body is discarded outright and `javascript:`/`data:` link schemes are dropped, so the output is a whitelist by construction — no purifier dependency, nothing an admin (untrusted on a CodeCanyon install) types can script the storefront. Public pages live under the reserved `/p/{slug}` prefix — a prefix, not a catch-all, so a page slug can never shadow a real route. Footer links come from the pages table through a cached `FooterPages` read model shared on every Inertia request (white-label rule: no hardcoded links), flushed by `SavePage`/`DeletePage`.

The language manager treats a locale code as what it really is — **a filename**. The code is validated against the strict `Language::CODE_PATTERN` (shared with `SetLocale`) at the FormRequest *and* re-checked inside `SaveTranslations`/`DeleteLanguage`, the only two things that ever touch `lang/{code}.json`; the code is immutable after creation. `en` is off-limits everywhere — `lang/en.json` belongs to the reconcile script and the catalog guard test. Saves keep only keys present in the English catalog (a forged payload cannot grow the file), drop blanks (fallback to English stays automatic), and the editor submits one JSON body because the catalog outgrew PHP's `max_input_vars`. Deleting a language removes its file too, but never the site's current locale. FAQs are plain text rendered escaped — only pages get markdown.

### D21 — Helpdesk tickets use a plain guarded enum, closed-read-only lives in the policy, and attachments mirror problem photos (2026-07-11)

`M16`'s ticket flow (`open → pending → resolved → closed`) is four states with two writers, so it is a plain `TicketStatus` enum guarded inside the actions — **not** a second `BookingStateMachine`; the machine earns its complexity at 14 booking statuses, not here. The status semantics encode who owes the next move: a staff reply flips to `pending` (waiting on the user) and auto-assigns the first responder; a user reply flips back to `open` — including reopening a `resolved` ticket; `closed` is final. The gate criterion "closed tickets are read-only" is enforced where it cannot be skipped: `SupportTicketPolicy@reply` (deliberately no admin `before()` bypass — it must hold for admins too) plus a `lockForUpdate` re-check inside `ReplyToTicket` so two tabs cannot race a close. Customers and providers share one controller behind `role:customer|provider` with the page shell picked by role (the notifications-page idiom), and the provider routes sit **outside** `provider.approved` — an unapproved provider stuck in KYC review is support's most important user.

Message attachments are user uploads, so they follow the booking-problem-photos precedent exactly: medialibrary on the **private disk**, no conversions (PDFs are allowed and image conversions would choke), served only through `support.attachments.show`, which authorizes against the *ticket* (`SupportTicketPolicy@view`) and then cross-checks that the media's message actually belongs to that ticket — a valid media id from someone else's thread 404s. Reply notifications reuse M11 wholesale (`database` + `broadcast`, `ShouldQueue` + `afterCommit`) — the "in-app within 2s" gate is just Reverb doing what it already does. Canned responses are a `support.canned_responses` JSON setting (list of `{title, body}`) edited on the Settings screen, so they stay white-label and install-specific; `support.max_attachments` caps uploads per message. Admin assign/resolve/close write `activity_logs` rows through the M13 `ActivityLogger`.

### D22 — The page builder is block-based, not a canvas (2026-07-12)

A drag-and-drop **canvas** builder (free rows/columns, resizable widgets, inline style controls) is a product in its own right: it means a layout engine, a style serializer, a responsive-breakpoint model and a migration story for every layout ever saved. It would cost more than the remaining eleven modules combined and would compete with the thing that actually sells this script — the booking + tracking core.

`M20` therefore models a page as an **ordered list of typed blocks** (`page_blocks`), each one a JSON payload validated against a schema declared in a single `BlockRegistry` (type → schema → admin form → React renderer). The admin reorders, duplicates and hides blocks; they never position pixels. Consequences that matter: the payload is validated on write (an admin cannot save a shape the renderer will choke on), a block type can gain fields without touching stored rows, and an **unknown type renders nothing** instead of 500ing a public page — the failure mode of a removed/renamed block is a gap, not an outage. Layout stays the theme's job, which keeps the storefront coherent no matter what the buyer assembles. M19's homepage sections collapse into blocks on a reserved `home` page once M20 lands, so there is one content model, not two.

### D23 — One currency per install; the setting is *format*, not conversion (2026-07-12)

Multi-currency means live FX rates, a rate snapshot on every booking, refunds at the original rate, and a tax model that stops being GST — the invoice (D16), the earnings ledger (`gross/commission/net`) and the wallet all assume one unit of account. Buying that complexity to serve a market this product does not launch in is a bad trade.

`M24`'s Currency screen configures **presentation only**: symbol, ISO code, position, decimals, and digit grouping (Indian `1,00,000` vs Western `100,000`). `App\Support\Money` — which already does Indian grouping without `ext-intl` — reads those settings instead of hardcoding ₹. A buyer in another market sets their symbol and grouping and everything downstream (checkout, invoice, ledger, reports) follows. True multi-currency stays a v2 add-on module, where an FX snapshot column can be introduced deliberately rather than retrofitted through the money path.

**Realised (2026-07-14, M24).** The decision was made *structural*: `Money::format()` no longer takes a currency argument at all. It used to accept a code (`format($amount, 'USD')`), which is a quiet invitation to print a booking in a currency the platform does not run on — the amounts in the database are snapshots in the platform currency, not values to convert. A caller cannot ask for another currency because there is none to give. The browser's `formatMoney()` implements the same four rules by hand rather than deferring to `Intl` — two locale databases agreeing is not something to rely on when the invoice PDF and the checkout total must match to the character. The currency **code** (`localization.currency`) is owned by the Currency group, not the Localization one: a screen group is not a storage group (D24), and the code and the way it prints belong on one screen.

### D35 — Optional third parties degrade to *off*, and fail *open* (2026-07-14)

`M24` adds reCaptcha, analytics, Google Maps and Firebase keys. Each is a place where a product that must install with **zero third-party keys** could quietly acquire a hard dependency — and reCaptcha is the sharpest, because the "safe" instinct (no token, no signup) turns a Google outage into a signup outage on an install that may not even use Google.

Two rules, applied to every optional integration:

**Unconfigured means off, never broken.** `RecaptchaToken::rules()` collapses to `nullable|string` when there are no keys or the form's switch is off; `NotificationChannels` never lists mail/SMS/push without a configured provider (D14/D34); the media library, the maps and the sitemap all work with nothing set. The System screen reflects this in its colours: "no SMTP" is *Not set up*, not an error — if an operator's install shows red for choices they made deliberately, they stop reading the page, and the one genuine failure (debug on in production, a missing storage link) goes unnoticed.

**A configured third party that is *down* must not take the platform with it.** reCaptcha verification that cannot reach Google lets the visitor through and logs it. This is the same doctrine already load-bearing elsewhere: a dead Reverb never fails a tracking ping (M07), a dead SMS gateway never fails a booking (M23), a broken email template never eats a confirmation (D25). The exception is money, which fails *closed* by construction — an unverified payment never places a booking (M08/D27).

One implementation note worth keeping: Laravel does not run a non-implicit rule on a missing key **or on an empty string**, so a bot that strips the token field would have sailed past a rule attached with `nullable`. The token is therefore `required` exactly when the form is protected — "no token" fails the same way "a bad token" does.

### D24 — Settings save per group, and each group owns its rules (2026-07-12)

The settings registry (D8) scaled; the settings *screen* did not. One `UpdateSettingsRequest` validates **every** key on **every** save, so adding one required key 422s every unrelated form — this has bitten the suite repeatedly (the `SettingsFixtures::validPayload()` landmine). At ~20 groups it becomes unworkable.

`M17` splits the screen into `/admin/settings/{group}` sub-pages, each posting only its own keys. Validation moves to **per-group rule providers**: a group declares its own rules, the request object composes only the rules of the group being saved, and `UpdateSettings` writes only that group's keys. A group's test fixture then covers that group alone. The `SettingsRegistry` stays the single source of defaults, types and grouping — nothing about D8 changes; what changes is that the *write path* stops being global. Secrets keep the M08 rule everywhere: write-only fields, `*_set` booleans out, blank = keep, `remove_*` = erase; `ActivityLogger` keeps recording **keys only**.

**Shipped 2026-07-12.** A `SettingsGroup` is a class in `app/Domain/Settings/Groups` that declares its **keys**, its rules, the props its screen needs, and how to apply a validated payload; `SettingsGroupRegistry` lists them in sidebar order and an unknown group 404s. Validation is composed from the routed group alone, so a payload cannot smuggle another group's key past the request — `safe()` never returns it, `apply()` never sees it (there is a test that tries). Two consequences worth naming. First, a *screen* group is not a *storage* group: `payouts` owns `payments.commission_percent` because an admin thinks of the cut and the withdrawal as one policy, and `booking` owns `booking.payment_timeout_minutes` — the mapping from form field to settings key lives in `apply()`, which is why `keys()` exists as an explicit declaration rather than a prefix convention. Second, the thing that keeps that honest is `SettingsGroupCoverageTest`: every key in `SettingsRegistry::defaults()` must be claimed by exactly one group. Forgetting a group used to be silent (the key simply became uneditable); now it fails the suite. Dispatch and tracking gained their first admin UI in the process — their settings had been code-only since M06/M07 because nobody wanted to touch the 300-rule request object.

### D28 — `is_active` was a column with no enforcement; blocking makes it real (2026-07-12)

`users.is_active` shipped with M01's profile-fields migration and nothing ever read it. `M17` turns it into the block switch the Customers screen needs: `SetUserActive` is the only writer (and refuses to block an admin — the last admin must not be able to lock the platform out of itself), `users.blocked_reason` records why, `ActivityLogger` records who, and `EnsureUserActive` sits in the **`web` middleware group**, not behind `auth`, because the case a block must end is a session that is *already open*.

Two traps came with it, both worth remembering. **A database default is not hydrated back onto the model that inserted the row** — a freshly created `User` read `is_active` as `null`, which a naive `! $user->is_active` treats as blocked; every newly registered user (and the installer's own admin) would have been logged out on their next request. The fix is belt and braces: `protected $attributes = ['is_active' => true]` on the model, and the middleware tests `=== false` rather than falsiness, so an unset attribute can never lock anyone out. And **a blocked user can no longer be impersonated** (M13): the swap would succeed, then `EnsureUserActive` would log the *impersonated* session out on the next request and take the admin's session with it.

### D25 — Templates and gateways are optional layers with a shipped fallback underneath (2026-07-12)

`M23` lets an admin rewrite the subject and body of every transactional message, and lets them plug in an SMS gateway. Both are places where an install can go dark on its most important message — the booking confirmation — because someone deleted a variable or typo'd a template.

So the shipped default always sits underneath: a notification renders through `email_templates` **only if** an enabled row exists for its event key and it renders successfully; otherwise it falls back to the class's shipped Blade/markdown. A broken template degrades one message's *styling*, never its delivery. The SMS channel follows the FCM precedent exactly (D14): the `sms` channel joins `via()` only when a gateway is configured, drivers sit behind an `SmsGateway` contract and speak raw HTTP (no vendor SDKs, D15), and an unconfigured install simply never selects the channel. Same for SMTP: mail settings live in the DB (write-only secrets), and a `Send test email` button proves them before an admin trusts them. Delivery is audited (`sms_logs`) because "the customer says they never got it" is a support ticket, not a mystery.

**Realised (2026-07-14, M23).** The fallback has four triggers, not one: no row, a row switched off, a row that renders to an empty subject or body, and a renderer that throws. All four return `null` from `EmailTemplateRenderer::render()`, and `null` means "send the shipped default". Placeholders are `{{ name }}` and an **unknown one renders as nothing rather than as itself** — a template is not a way to probe what the server knows. Bodies go through the one `MarkdownRenderer` (D20), so raw HTML in an admin's template is stripped and the wrapper Blade can echo the result unescaped. The admin's live preview calls the *same* renderer a real send calls, so a template that previews cannot break in production. On the SMS side the contract held exactly as written: `SmsManager::active()` returns null for "no gateway picked" **and** for "picked but half-configured", and that null is the whole reason a fresh install never selects the channel.

### D34 — One notification base class; via() is decided in exactly one place (2026-07-14)

By M22 there were eight notification classes, each carrying its own copy of `via()` (`['database', 'broadcast']` plus an FCM check), its own `toBroadcast()`, and its own re-derivation of the same title/body for each channel. M23 was about to add three more decisions to every one of them: is mail configured, is SMS configured, has this user opted out. Eleven classes × four channels is where a channel quietly stops working for one event and nobody notices for a month.

So `PlatformNotification` is the base for all of them. A subclass declares its `NotificationEvent` and fills **one payload** (`title`, `body`, `url` are load-bearing); the base derives the in-app row, the broadcast, the push, the SMS text and the email from it. `via()` delegates to a single `NotificationChannels` resolver, which applies the same two-step rule to every channel: **the provider must be configured, and only then may the preference decide.** Configuration is checked first on purpose — an admin switching mail on for an install with no SMTP must not queue mail into a void.

Two things are deliberately *not* switchable: `database` and `broadcast`. There is no enum case for them. They are the platform's own record of what it did to a user's booking and money — a user who could turn them off would lose the notification feed and the live bell with them, and would have no way to see that a refund was paid. `NotificationChannel` therefore only has `mail`, `sms` and `fcm`, and the matrix screen can only show what it is allowed to change.

### D26 — Custom CSS/JS is a real capability with a real blast radius (2026-07-12)

`M19` ships a Custom CSS & JS panel because every script in this category has one and buyers expect it. It is worth being explicit about what it is: **admin-authored code injected into the storefront shell**. There is no sanitizing it — sanitized JS is not JS. It means an admin account (or a staff account holding that permission) is XSS-equivalent by design.

Containment: the capability is **off by default** (empty settings inject nothing); it is gated behind its own `custom_code.manage` permission (M26) so a staff account cannot reach it by default; both snippets render only in the **public storefront shell**, never in the admin or provider shells (a stored payload cannot then harvest an admin session that isn't there); CSS and JS live in separate keys with the JS block emitted last, right before `</body>`; and every save writes an `activity_logs` row with the actor. The install guide states plainly that granting `custom_code.manage` is equivalent to granting site-wide script execution. Analytics IDs (M24) are deliberately **not** implemented via this panel — they are typed ID fields rendering a known snippet, so the common case never needs the dangerous tool.

### D27 — Offline payments reuse the payment state machine; they do not get a second money path (2026-07-12)

`M22` adds "pay offline / bank transfer", which is exactly the kind of feature that grows a parallel money path: a second table, a second way to mark a booking paid, a second refund route, and a reconciliation gap between them.

It gets none of that. An offline payment is a `payments` row with `gateway = offline`, created in `initiated` at checkout with the customer's uploaded proof on the **private disk**. The booking stays `pending_payment` — the M08 invariant holds unchanged: **an unpaid booking is never dispatched**. Admin verification calls the same row-locked, idempotent `ConfirmPayment` action every gateway webhook calls, so the booking reaches `placed`, the cash/commission math (D16) sees an ordinary settled payment, and a double-click cannot double-settle. Rejection sets the payment `failed` with a reason and notifies the customer; the booking expires on the existing `bookings:expire-unpaid` schedule. Bank details shown at checkout come from a `bank_accounts` table (settings-adjacent, admin-managed) rather than a free-text blob, so the same account can be referenced by the payment row for reconciliation. Provider payout details move the same way: `payout_accounts` replaces the free-text UPI/bank string typed into M09's payout dialog, and the payout request references an account row.

**Realised (2026-07-14, M22).** One detail worth writing down: the offline flow adds **no new `PaymentState`**. An offline row waits in the existing `initiated` state and the discriminator is `gateway = offline` (`Payment::scopeAwaitingVerification()` is exactly that pair). A new state would have forced every existing money query — the payments hub totals, `bookings:expire-unpaid`, the refund path — to learn about it, and one of them would have been missed. `VerifyOfflinePayment` therefore only *guards* (gateway is offline, status is initiated, not already reviewed) and then delegates to `ConfirmPayment`; it never touches booking state itself, so placement, dispatch and the earnings ledger see the same event they see for a Razorpay capture. `PaymentProvider::Offline` is deliberately **not** an "online gateway", so `ConfirmPayment` does not rewrite the booking's `payment_method` — it really was a transfer, and the invoice must keep saying so. Proof is a `singleFile()` medialibrary collection on the private disk, so re-declaring a transfer replaces the receipt rather than growing a pile, and the serve route is policy-checked (owner or admin) with a media-belongs-to-payment cross-check.

### D33 — A payout destination is a row, but the payout request still snapshots it (2026-07-14)

`payout_accounts` makes the provider's UPI id / bank block a first-class row: it can be defaulted, admin-verified, and reused across requests instead of being retyped into the payout dialog every time.

The temptation is then to drop `payout_requests.method_details` and read the account through the foreign key. That would quietly break the ledger rule (M09: money rows are snapshots). A provider who edits their UPI id after a payout is approved would rewrite what an already-settled request *says* it paid to, and the admin's proof of where the money went would change under them. So the request keeps the snapshot — `PayoutAccount::toSnapshot()` is taken at request time — *and* keeps `payout_account_id` as a back-reference for the screens. Editing an account clears its `is_verified` tick (verifying a UPI id and then letting it be swapped for another is worse than not verifying at all), and an account cannot be deleted while an open request rides on it.

### D29 — Picking a library asset copies it; the library is an inventory, not a shared pointer (2026-07-13)

A medialibrary row belongs to exactly one model. The obvious media-manager design — consumers *reference* a library row — therefore cannot work: deleting the asset would silently blank every banner, page block and blog post that pointed at it, and medialibrary has no notion of a media row with several owners.

`M18` copies instead. `AttachLibraryAsset` writes the file into the consumer's own collection (`preservingOriginal()`, or the source is *moved* out of the library) and stamps `library_asset_id` on the copy's custom properties. That single stamp buys everything: "used in 3 places" is answerable without a join table, `DeleteMediaAsset` can refuse an asset that is in use, `media:prune-library` can find the ones that never were, and a library tidy-up can never reach through into the storefront. The cost is disk — the same picture stored twice — which on a marketing library is the cheaper half of the trade.

The library is also the *whole* inventory, not a parallel one: the banner form's own file input routes through `UploadMediaAsset` first, so an image uploaded on a consumer's form still shows up in the manager. Two other rules are load-bearing. The library lives on the **public** disk and lists **only** its own collection — KYC documents, booking photos, review photos and ticket attachments are customer data on the private disk, and a media *manager* that browsed them would be a data leak wearing a CRUD screen. And uploads are raster-only (jpg/png/webp): conversions are generated eagerly, and an SVG is a script container that the storefront renders inline. The picker talks JSON rather than Inertia because it opens on top of a half-filled form — an Inertia visit would replace the page and throw that form away.

### D30 — Menus are resolved on the server, and an item that cannot be resolved is dropped (2026-07-13)

A menu item points at *something*: a storefront route, a CMS page, or a URL. All three are admin-supplied strings, and all three can rot — a page gets unpublished, a route gets renamed, a URL gets pasted with a `javascript:` scheme.

`SiteMenus` resolves the whole tree to plain `{label, url, children}` links **on the server**, caches it, and **drops** anything it cannot resolve. A route target must be in an explicit allowlist (`MenuItemType::allowedRoutes()`), because `route($name)` on user input throws for an unknown name — and a valid-but-wrong name (an admin route, a POST-only endpoint) would put a link in the header that no visitor can follow. A page target must currently be published. A URL must start with `http(s)://` or `/`, the same rule banner links already carry: an `href` is a script sink. The React side therefore receives links it can render blindly; it never resolves, never validates, and cannot 500 on a rotten item.

Visibility (everyone / signed-out / signed-in) is **not** baked into the cached tree — one cache serves guests and signed-in users, and the filter runs per request. Locations are fixed (`header`, `footer_1..3`) and each owns exactly one menu, created on first sight by `EnsureMenus`: an admin can never create a menu that no location renders.

### D31 — Homepage sections are not built twice; they are M20 blocks from the start (2026-07-13)

The M19 spec listed "homepage sections" (hero copy, how-it-works, counters, CTA band) as its own content model, with a note that they would *become* page-builder blocks once M20 landed. That is two content models, two admin screens and a migration between them, for the same pixels.

They are dropped from M19 and built once, in M20, as blocks on a reserved `home` page (D22). M19 ships the site *chrome* — menus, header/footer style, footer content, social, cookie banner, custom code, login-page look — plus its marketing content (testimonials, sponsors, popups, contact, newsletter). The storefront home keeps its current hardcoded body until M20 replaces it wholesale.

### D32 — Blocks resolve on the server and return models; the HTTP layer presents them (2026-07-14)

A block's props are not its payload. A `services_grid` stores `{featured_only, limit}` and has to *fetch* services; a `faq` block has to fetch FAQs. That resolution belongs on the server — the browser must never be handed a query to run — and it lands in the block class, next to the schema and the rules that describe the same thing.

Which raises a layering question, because the storefront already has wire shapes for those models (`ServiceResource`, `FaqResource`, …) and they live in `App\Http`, which the domain is forbidden to touch (arch rule). The two bad answers were: let blocks import resources (the arch rule exists precisely because that import is how business logic starts assuming a request), or let each block hand-map its models to arrays (fourteen quiet copies of a shape that already exists — and a service card that silently loses a field the day `ServiceResource` gains one).

So `Block::data()` returns **models**, and a single `BlockPresenter` in the HTTP layer maps any model it meets — at any depth — through the resource registered for its class. The domain never names `App\Http`; the wire shape stays defined in exactly one place; the blocks stay about content. A block type that needs a model the presenter does not know is a one-line addition there, and the arch suite keeps the rest honest.

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
