---
title: 02 — Modules (build spec)
tags:
  - internal
  - modules
---

# 02 — Modules (build spec)

Internal counterpart of the client doc's module list — with build notes and acceptance criteria. Build order lives in [[06-Roadmap]].

---

## M01 Authentication & Accounts
- Laravel Fortify-style auth (or Breeze-equivalent scaffold from the React starter) + Sanctum
- **Phone-first UX** (D10): login/register lead with phone + OTP; email+password secondary
- Roles via `spatie/laravel-permission`: `customer`, `provider`, `admin`
- Phone OTP: Firebase Phone Auth on frontend → verify ID token server-side (`kreait/firebase-php`); **feature-flagged** (`otp_required`) so app installs/works without Firebase configured
- ✅ *Done when:* all 3 roles can register/login/reset; middleware blocks cross-role access; OTP toggle works both ways.

## M02 Service Catalog
- `categories` (self-referencing parent_id, 2 levels), `services`, `service_addons`
- Pricing types enum: `fixed | hourly | inspection`
- Media via `spatie/laravel-medialibrary` (conversions: thumb/card/hero, WebP)
- Full-text search on services (MySQL FULLTEXT — no external search engine at launch)
- Related services cross-sell (`service_related` pivot, admin-curated): "people also book" strip on service page + checkout — UC-style upsell
- ✅ *Done when:* admin CRUDs full tree + services + addons; customer browses/searches; zone-filtered.

## M03 Locations, Zones & Addresses
- `zones`: polygon drawn by admin on Leaflet map (leaflet-draw), stored as GeoJSON; point-in-polygon check in PHP (`PointInPolygon` ray casting — D12, portable across MySQL/MariaDB/sqlite)
- `addresses`: customer address book, map-pin picker (Leaflet), reverse geocoding via **Nominatim** (respect usage policy: 1 req/s, proper User-Agent; cache results)
- Zone gate: customer's pin decides which services/providers are offered
- ✅ *Done when:* admin draws zone; out-of-zone address politely blocked at checkout.

## M04 Booking Engine
- Cart (session for guests → merged to user on login), slot picker (config: slot length, lead time, max days ahead)
- `bookings` + `booking_items` + `booking_status_history` (audit every transition, with actor)
- Status enum (canonical, used everywhere incl. tracking + notifications):
  `pending_payment → placed → searching → assigned → accepted → en_route → arrived → in_progress → completed`
  terminal alternates: `cancelled_customer | cancelled_provider | cancelled_admin | expired | failed_payment`
- State machine enforced in one place: `App\Domain\Bookings\BookingStateMachine` — throw on illegal transition
- Reschedule + cancellation windows from settings; invoice PDF (`barryvdh/laravel-dompdf`) in **GST format** (GSTIN, CGST/SGST/IGST breakup from settings) — *PDF ships with Phase 4 money work; the `tax_breakup` snapshot is written at checkout from day one*
- Cart is **session-only** (guest cart survives login because the session does); bookings snapshot the address into `address_snapshot` so address-book deletes never orphan a booking
- Booking photos: customer attaches problem photos at checkout; provider uploads before/after proof (spatie medialibrary collections on Booking)
- **Job-start OTP** (UC parity, settings flag `job_otp_required`): 4-digit code generated on booking, shown to customer; provider must enter it for `arrived → in_progress` — proves provider is on-site with the right customer
- **Cancellation fee** (settings: free-cancel window, then flat/percent fee): computed at cancel time, snapshotted to `bookings.cancellation_fee`, deducted from refund
- **Favorites + rebook**: customer favorites a provider (`favorite_providers`); "Book again" from booking history pre-fills cart; dispatch tries favorite first when eligible (settings flag)
- ✅ *Done when:* full happy path + every cancel path transitions correctly and is logged; OTP gate blocks wrong code; cancellation fee matches settings in tests.

## M05 Provider Onboarding & Management
- Provider profile: bio, photo, experience, service categories, working hours (JSON), service radius km, base location (lat/lng)
- KYC docs: private disk, admin review queue, statuses `pending → approved / rejected (reason)`
- Online/offline toggle (instant, affects dispatch)
- Vacation/blackout dates (`provider_blackouts`) — CRUD here; dispatch and slot picker enforce them in M06 (`ProviderBlackout::covers()` helper ready)
- ✅ *Done when:* unapproved provider can log in but sees only "complete onboarding" state; approval unlocks panel.
- **Shipped (2026-07-08):** `EnsureProviderApproved` middleware (`provider.approved`) gates the panel, redirect (not 403) to `provider.onboarding`; resubmission loop (rejected profile edit / doc re-upload auto-bumps to Pending, note cleared); doc replace-per-type (unique `provider_profile_id`+`type`); admin cannot approve an incomplete profile (base location + working hours + ≥1 category — `ValidationException` on `status`); non-approved decisions force `is_online = false`; `ProviderApprovalChanged` event fired for M11. Private-disk KYC serving via `provider-documents.show` (owner-or-admin guard).

## M06 Dispatch & Job Assignment
- Two strategies behind one interface (`DispatchStrategy`): `nearest` (Haversine on providers' base/last location, filtered by zone + category + online + not busy) and `broadcast` (offer to all eligible; first accept wins)
- Offer timeout (settings, default 60s) → next candidate / re-broadcast; exhausted → admin alert + `searching` stays visible
- Admin manual assign overrides everything
- Implemented with queued jobs + events; offers pushed via FCM + in-app realtime
- ✅ *Done when:* two test providers, one booking → correct provider gets it under both strategies; timeout re-offers work.
- **Shipped (2026-07-08):** `dispatch_offers` table (one row per booking×provider, unique pair, `round`+`distance_km` snapshots). `app/Domain/Dispatch`: `DispatchStrategy` interface + `NearestStrategy`/`BroadcastStrategy` (`StrategyFactory` resolves from `dispatch.mode`), `EligibleProviders` finder (approved + online + category-or-ancestor match + within own `service_radius_km` via Haversine, not on blackout, not on an overlapping active job, not already offered — geo in PHP per ADR D12), actions `DispatchBooking`/`AcceptOffer`/`DeclineOffer`, queued `ExpireDispatchRound` job, events `BookingOffered`/`DispatchExhausted` (M07/M11 push later). Auto-dispatch on placement via `DispatchPlacedBooking` listener (sync; setting `dispatch.auto`). Accept = `searching→assigned→accepted` + expire siblings (broadcast race locked on the booking row); decline / timeout re-offer the next candidate; `dispatch.*` settings (mode, offer_timeout_seconds=60, max_rounds=5, auto). **Timeout job guards on `expires_at` so a `sync` queue install degrades to no-auto-re-offer instead of collapsing.** Provider Jobs screen (offers accept/decline + linear status buttons Accept→On the way→Arrived→Start(OTP)→Complete + can't-take-it). Admin booking screen: Run-dispatch button + offers list (manual assign already lands `assigned` via the M04 transition). Real-time push (FCM/Reverb) deferred to Phase 3.

## M07 Live Location Tracking ⭐
Full spec: [[05-Live-Tracking]]. Locked stack (Laravel Reverb + Geolocation + Leaflet + OSM, D11) — do not substitute components.
- ✅ *Done when:* acceptance checklist in [[05-Live-Tracking]] passes end-to-end on two devices.
- **Shipped (2026-07-09):** `tracking_sessions` + `tracking_points` tables. `app/Domain/Tracking`: `TrackingSessionStatus` enum, `GeoPing` DTO, actions `StartTrackingSession` (idempotent: `accepted → en_route` + open session; a refresh re-uses the live one) / `RecordTrackingPing` (drops fixes above `tracking.max_accuracy_meters`, persists point + checkpoint, broadcasts) / `EndTrackingSession` (`en_route → arrived`, session ended), events `LocationUpdated` + `BookingStatusBroadcast` (both `ShouldBroadcastNow` on `private-tracking.booking.{id}`). Channel authorization in `routes/channels.php` returns member info, so **one callback guards both the private and the presence channel** (customer / assigned provider / admin only). Provider journey screen (`provider/journey.tsx`): `watchPosition` throttled to `tracking.ping_interval_seconds` + `tracking.min_move_meters` (Haversine), Start-journey / I-have-arrived, Wake Lock, permission-denied help, presence "customer is watching". Customer live map (`components/tracking/tracking-map.tsx`): react-leaflet + OSM, lerp-animated marker (no teleport), trail polyline, auto-fit that yields to a user pan + Recenter, ETA (15 km/h floor), and a **polling fallback** on `bookings/{booking}/tracking/last` whenever Echo is disconnected or pings go stale. **A dead Reverb never fails a ping** — the checkpoint is persisted first and the broadcast failure is logged (spec failure-mode table). `tracking:prune` command + daily schedule honours `tracking.points_retention_days`. Endpoints ride session auth on `web` routes (ADR D13). Two-device acceptance checklist stays a manual gate.



## M08 Payments & Wallet
- Gateway abstraction `PaymentGateway` interface → `RazorpayGateway` (**default — client confirmed**; UPI-first payment method ordering), `StripeGateway` (product requirement for non-India buyers, D8), `CashGateway` (pay-after-service, enabled at launch)
- Webhook-driven confirmation (never trust redirect alone); idempotent webhook handlers
- `wallets` + `wallet_transactions` (immutable ledger, running balance derived); refunds → wallet or gateway
- ✅ *Done when:* sandbox payment completes booking; webhook replay is idempotent; wallet ledger always sums correctly.

## M09 Commission & Payouts
- Commission resolved at booking completion: per-category override → else global %; snapshot the rate on the booking (rate changes must not rewrite history)
- `earnings` ledger per provider; `payout_requests` with admin approve/mark-paid + reference
- ✅ *Done when:* completed job splits gross→commission→net correctly incl. coupons and refunds.

## M10 Reviews & Ratings
- One review per completed booking; 1–5 stars + text + **photos** (spatie medialibrary collection on Review — UC parity)
- Provider `rating_avg` + `rating_count` denormalized (updated via event listener)
- Admin hide/unhide with reason (covers photos too)
- ✅ *Done when:* rating updates provider card everywhere; hidden reviews vanish from public.

## M11 Notifications
- Laravel notification classes, channels: `database` (in-app), `mail`, `fcm` (custom channel via `kreait/firebase-php`)
- FCM web push tokens registered on login (permission prompt UX: ask in context, not on landing)
- Realtime in-app (badge/toast) — Laravel broadcast notifications over Reverb, Echo private channel `user.{id}` (D11)
- Notification matrix (event × role × channel) maintained in this doc as it grows
- ✅ *Done when:* booking status change reaches customer as push + in-app within 2s.
- **Shipped (2026-07-09):** `notifications` (Laravel database channel) + `fcm_tokens` tables. `BookingStatusNotification` (customer) and `NewJobOfferNotification` (provider) — both `ShouldQueue` + `afterCommit()` so the state-machine transaction never waits on delivery. Channels: `database` + `broadcast` (Reverb, private `App.Models.User.{id}`); `FcmChannel` exists but stays out of `via()` until Firebase is configured (ADR D14) — `fcm-tokens` register/forget endpoints ship regardless. Listeners `SendBookingStatusNotification` (skips noisy interim states like `searching`), `BroadcastBookingStatus` (queued, mirrors the change onto the tracking channel), `NotifyProvidersOfOffer`. Frontend: `useEchoNotification` toast + bell dropdown with unread badge in both header shells, `notifications/index` page (mark one / mark all read). The bell feed is the shared Inertia prop `notifications`; the index page's own list is `entries` **so it does not shadow it**.

> [!warning] Listeners in `app/Listeners` are auto-discovered
> Laravel registers any `app/Listeners` class whose `handle()` type-hints an event. Adding an explicit `Event::listen()` for the same pair fires the listener **twice** (this bit `DispatchPlacedBooking` in M06 — every placed booking dispatched twice). Register in one place only; verify with `php artisan event:list`.

### Notification matrix

| Event | Recipient | Channels |
|---|---|---|
| booking `assigned`/`accepted`/`en_route`/`arrived`/`in_progress`/`completed`, provider or admin cancellation | customer | database, broadcast, (fcm) |
| dispatch offer sent | offered provider(s) | database, broadcast, (fcm) |

`(fcm)` = only once `services.fcm.credentials` is set.

## M12 Coupons & Promotions
- `coupons`: code, type flat/percent, min order, max discount, usage limit global/per-user, validity window, first-order-only flag
- Validation isolated in `CouponValidator` (testable); banners: admin-managed images + link + sort + schedule
- Referral program: unique code per user; both sides get wallet credit when referee's first booking completes (settings: amounts, on/off); `referrals` table
- ✅ *Done when:* every constraint has a passing + failing test.

## M13 Admin Dashboard & Reports
- KPI cards + charts (recharts): bookings/day, revenue, top services, provider leaderboard
- Filterable report tables → CSV export (`league/csv`), queued for big ranges
- Support tools: admin "login as user" (impersonation, fully audited) + admin activity log
- ✅ *Done when:* numbers reconcile with raw DB queries on seeded data.

## M14 CMS & Platform Settings
- `pages` (markdown/richtext), `faqs`, `banners`, `settings` (typed key-value: string/int/bool/json + group)
- Settings UI groups: General (branding: name/logo/colors/currency/timezone — D8 zero-hardcode rule), Booking, Payments (incl. GST config), Tracking, Notifications, Features
- Language manager (D9): `languages` table + JSON translation files + admin UI to add/edit languages; English default, Hindi = phase-2 content
- ✅ *Done when:* changing a flag (e.g., disable wallet) reflects across app without deploy.

## M15 Web Installer & Updates
- `/install` wizard (blocked after completion via lock file): requirements check (PHP ver/exts, writable dirs) → DB credentials → run migrations+seeders → create admin → write `.env` (incl. generated `REVERB_*` keys) → done
- Prints supervisor/systemd templates for the two long-running processes: `php artisan reverb:start` + queue worker; verifies Reverb reachable before finishing
- Update path: versioned migrations + `php artisan app:update` (migrate + cache clear + restart hints for reverb/queue)
- Envato purchase-code verification step in wizard — **optional toggle**, off for direct client install (D8)
- Demo mode (D8): `DEMO_MODE=true` → admin mutations blocked with friendly toast, nightly `migrate:fresh --seed` via scheduler
- ✅ *Done when:* fresh VPS → running platform using only a browser + the two documented process commands.

## M16 Support & Helpdesk (UC parity)
- `support_tickets` (customer or provider raises; optional booking link, category, priority, status `open → pending → resolved → closed`) + threaded `support_ticket_messages` with attachments (medialibrary)
- Customer/provider: "Help" section — raise ticket from a booking or standalone, see thread, reply
- Admin: ticket queue with filters, assign, reply, canned responses (settings-stored), close with resolution note
- Notifications on reply (FCM + in-app + mail via M11)
- ✅ *Done when:* ticket raised from a booking reaches admin queue; reply notifies user in-app within 2s; closed tickets read-only.

---

## Cross-cutting v1 features

- **PWA**: vite-plugin-pwa — installable icon, offline shell, FCM web push; app feel without app stores (big India + sales point)
- **SEO**: Inertia SSR for public pages, sitemap.xml, schema.org (`LocalBusiness`, `Service`), per-service meta/OG tags
- **i18n**: every user-facing string through translation helper from day one (D9)
- **Indian formatting**: ₹ + Indian digit grouping (1,00,000) via shared `format.ts` / PHP formatter

---

> [!todo] Cross-cutting definition of done (every module)
> - Feature tests (Pest) for happy path + failure paths
> - Policies/authorization for every endpoint
> - Seeded demo data so the module is demo-able immediately
> - Mobile-responsive UI checked at 375px
> - No hardcoded branding/strings — settings, theme tokens, translation files (D8/D9)

Related: [[04-Database-Schema]] · [[06-Roadmap]] · [[07-Conventions]]
