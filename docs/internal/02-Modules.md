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
- **Shipped (2026-07-09):** `payments` / `wallets` / `wallet_transactions` tables. `app/Domain/Payments`: `PaymentGateway` contract + `RazorpayGateway`/`StripeGateway` over the **raw HTTP client, no vendor SDKs** (ADR D15), `GatewayManager` (`all`/`configured`/`get`), `WebhookResult` DTO, `WalletService` (sole writer; locked `apply()` keeps `balance == credits − debits`), actions `InitiateGatewayPayment` (reuses an open attempt — a refresh never opens a second upstream order), `ConfirmPayment` (row-locked + idempotent; replays no-op), `PayWithWallet`, `RefundBookingToWallet`. Cash starts `placed`; gateway/wallet start `pending_payment` and only reach `placed` when money settles — **an unpaid online booking is never dispatched**. Razorpay callback + both webhooks are HMAC-verified against the raw body; Stripe's return leg re-asks the API (`isSessionPaid`) because a redirect proves nothing. `webhooks/*` is CSRF-exempt and throttled `60,1`. `bookings:expire-unpaid` scheduled every 5 min; a payment landing on a terminal booking stays captured, logs a warning and is refundable from the admin screen. `SettleCashOnCompletion` (auto-discovered) writes the cash payment row on completion. Refunds v1 always credit the wallet: customer cancel = total − cancellation fee, admin cancel = full. **Gateway secrets are write-only in the admin UI** — the edit screen receives `*_set` booleans, never the values; blank = keep, `remove_*` = erase. Screens: checkout method picker, `customer/bookings/pay` (Razorpay modal / Stripe redirect / wallet + expiry countdown), `customer/wallet` ledger, admin settings Payments card, admin booking payments card + Refund dialog.

## M09 Commission & Payouts
- Commission resolved at booking completion: per-category override → else global %; snapshot the rate on the booking (rate changes must not rewrite history)
- `earnings` ledger per provider; `payout_requests` with admin approve/mark-paid + reference
- ✅ *Done when:* completed job splits gross→commission→net correctly incl. coupons and refunds.
- **Shipped (2026-07-10):** `earnings` / `payout_requests` tables + `categories.commission_percent` nullable override. `app/Domain/Earnings`: `CommissionResolver` (per-item rate — category → parent → global `payments.commission_percent`; charged on the **pre-tax** value; discount spread across lines pro-rata so M12 coupons slot in; returns the blended rate), `EarningsLedger` read model, actions `RecordBookingEarning` (row-locked, idempotent, snapshots `commission_rate_snapshot`/`commission_amount`/`provider_earning`), `ReverseBookingEarning`, `RequestPayout`, `ProcessPayout` (approve / markPaid / reject). Listeners `RecordEarningOnCompletion` (on `BookingStatusChanged`) and `ReverseEarningOnRefund` (on the new `BookingRefunded` event) — both auto-discovered, registered once. `earnings:release` runs daily to end the `payouts.hold_days` window.
  - **Signed net (ADR D16):** `net = gross − commission − collected_amount`. A **cash** job's `collected_amount` is the customer's full total, so its net is **negative** — the provider owes commission plus the GST they took at the door. Never clamped. A payout claims negative rows too, so the debt offsets instead of vanishing. Debts skip the hold window; positive earnings serve it.
  - **Append-only:** a refund appends a `reversal` row negating the job row rather than editing it; `unique(booking_id, type)` is the double-write backstop. Refunding a cash job therefore *credits* the provider back the commission.
  - **Payouts** claim the provider's whole released balance (`earnings.payout_request_id` back-reference), one open request at a time; rejection unclaims. Money leaves via the admin's bank transfer + `reference` (UTR) — the wallet is never touched.
  - **GST invoice PDF** (`barryvdh/laravel-dompdf`, `resources/views/invoices/booking.blade.php`): seller GSTIN/address/state from `invoice.*` settings, CGST/SGST/IGST from the booking's `tax_breakup` snapshot, number derived from the booking id so a reprint never renumbers. `BookingPolicy@invoice` = customer or admin, never the assigned provider. Available once `payment_status !== unpaid`. `App\Support\Money` does Indian digit grouping without `ext-intl`.
  - Screens: `provider/earnings` (summary cards incl. "Commission owed", ledger, payout dialog with UPI/bank details), `admin/payouts/index` (filter + approve / mark-paid / reject), admin settings *Commission and payouts* + *Invoice* cards, commission override on the category form, invoice download on both booking screens, commission split on the admin booking screen.
  - Settings: `payments.commission_percent` (20), `payouts.enabled`, `payouts.min_amount` (500), `payouts.hold_days` (7), `invoice.prefix|company_name|gstin|address|state`.
  - **Blade now counts for i18n** — the catalog guard test and `scratchpad/reconcile_catalog.php` both scan `resources/views` for `__()`, because the invoice is the one user-facing surface React never renders.

## M10 Reviews & Ratings
- One review per completed booking; 1–5 stars + text + **photos** (spatie medialibrary collection on Review — UC parity)
- Provider `rating_avg` + `rating_count` denormalized (updated via event listener)
- Admin hide/unhide with reason (covers photos too)
- ✅ *Done when:* rating updates provider card everywhere; hidden reviews vanish from public.
- **Shipped 2026-07-10 (ADR D17).** Details:
  - `reviews` table: `booking_id` **unique** (one review per booking), `is_hidden` + `hidden_reason`; `app/Domain/Reviews` — `SubmitReview` (locked duplicate re-check, photo attach, provider notification), `ModerateReview` (hide/unhide), `ReviewChanged` event.
  - **Rating sync is a full recompute over visible reviews**, in `SyncProviderRatingOnReviewChange` (auto-discovered, registered once): hiding a 1-star pulls it out of the average, unhiding restores it, and a re-fired event stays idempotent. `SyncProviderJobStatsOnCompletion` recomputes `jobs_completed` the same way — the dashboard counter had been stuck at 0 since M05.
  - **Three guard layers** on submission: `BookingPolicy@review` (owner + completed), `SubmitReviewRequest` (rating 1–5, `reviews.max_photos` cap, duplicate check, `reviews.enabled` kill-switch → 403), and the action re-checks under `lockForUpdate` with `unique(booking_id)` as the last-resort backstop.
  - **Photos:** `review_photos` collection on the private disk, served by `reviews.photos.show` — **guest-reachable** because the storefront shows them; `ReviewPolicy@view` takes a nullable user, and a hidden review's photo 404s (not 403) so moderation leaves nothing to probe.
  - **Storefront:** the service page joins reviews through `booking_items`, so a multi-service booking's review reaches every service page it bought from. Aggregate header (avg + count), 5→1 distribution bars, reviews paginated on their own `reviews_page` param.
  - Screens: review form + own-review card on customer booking show (the owner still sees a hidden review, with the reason), recent-reviews card on provider dashboard, admin `/admin/reviews` queue with visibility + star filters and hide/unhide dialogs.
  - Settings: `reviews.enabled` (true), `reviews.max_photos` (4, 0 disables photos) + admin *Reviews* card.
  - Seeder reviews all three completed demo jobs (avg **4.67**); **ProviderSeeder now runs before BookingSeeder** — the stat listeners no-op against a missing profile row. 26 new tests (372 suite-wide).

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
- **Shipped 2026-07-10 (ADR D18).** Details:
  - **Coupons** — `coupons` + `coupon_usages` tables (`booking_id` unique, `created_at` only — an append-only audit trail); `app/Domain/Coupons`: `CouponValidator` is the **single eligibility + discount path** (active, window, min_order, first_order_only, usage_limit, per_user_limit; flat vs percent w/ `max_discount` cap, never exceeding the pre-tax base) used by the checkout apply endpoint *and* re-run by `PlaceBooking` inside the placement transaction under `lockForUpdate` on the coupon row — two tabs cannot double-spend the last `usage_limit` slot. Session-only apply (`cart.coupon`, cleared with the cart); apply endpoint throttled `10,1` (codes are guessable). Discount plugs into the existing `PriceQuote` seam **pre-tax** (`taxable = subtotal + addons − discount`), so M09's discount-aware `CommissionResolver` needed no change; `bookings.coupon_id` (dormant since M04) gained its FK.
  - **Redemption lifecycle:** the usage row is written at placement and a cancel/refund never restores it (UC parity) — but a booking that dies unpaid (`expired`/`failed_payment`) stops counting via the join, so abandoned checkouts don't burn a limited pool. "First order" = any prior booking not in those two states (a cancelled order still counts as an order).
  - **Banners** — `banners` table (`placement` home_hero/home_strip, sort, windows, `scopeLive`); medialibrary `image` collection on the **public** disk (marketing content, not user uploads) with hero/card webp conversions; `link_url` validated `url:http,https` (a `javascript:` href rendered by the storefront would be stored XSS). Storefront home renders a rotating hero + scrolling strip; a banner without an image falls back to a brand-gradient title card so a fresh install never shows a broken image.
  - **Referrals** — `users.referral_code` (lazy-generated by `EnsureReferralCode`, never mass-assignable) + `referrals` table (`referee_id` unique — referred once, ever). Optional code at registration (`?ref=` prefill) creates a **pending** row via `RegisterReferral`; sign-up alone pays nothing. `RewardReferrerOnFirstCompletion` (auto-discovered, registered once) fires on the first completed booking: `RewardReferral` flips pending→rewarded under a row lock and credits the **referrer's wallet through `WalletService` only**, snapshotting `reward_amount` at reward time. The referee's incentive is the seeded first-order coupon (WELCOME10), not a second wallet credit — one reward path, one writer.
  - Screens: coupon box on checkout (apply/remove, discount line, stale-coupon notice), refer & earn card on the wallet page (code, copy link, referral list), storefront home banners, admin `/admin/coupons` + `/admin/banners` CRUD, admin settings *Referrals* card, referral code field on register.
  - Settings: `referrals.enabled` (true), `referrals.reward_amount` (100; 0 pauses payouts without hiding the program).
  - Seeder: WELCOME10 (10% first-order, cap 200) + FLAT50 (min 500, 2/user) + two imageless banners. 46 new tests (418 suite-wide).

## M13 Admin Dashboard & Reports
- KPI cards + charts (recharts): bookings/day, revenue, top services, provider leaderboard
- Filterable report tables → CSV export (`league/csv`), queued for big ranges
- Support tools: admin "login as user" (impersonation, fully audited) + admin activity log
- ✅ *Done when:* numbers reconcile with raw DB queries on seeded data.
- **Shipped (ADR D19):** `app/Domain/Reports` — `DashboardMetrics` read model (9 KPI tiles + bookings/day + gross/commission per day + top services + provider leaderboard; every figure a raw aggregate over snapshot columns, reconcile-tested against raw SQL). `Report` interface + 4 reports (bookings/earnings/services/providers) behind `ReportRegistry` (unknown slug = 404), each exposing mapped rows only — the screen, the inline CSV and the queued CSV read identical figures. `ExportReportCsv` streams ≤2 000 rows inline (UTF-8 BOM for Excel); above that on a real queue driver it dispatches `GenerateReportExport` (sync queue always degrades to inline, M06 pattern) — file lands in `storage/app/exports` (pruned after 7 days), admin notified with an admin-gated download link. `app/Domain/Activity/ActivityLogger` is the sole writer of append-only `activity_logs`; logged: manual transitions, dispatch, refunds, payout decisions, settings saves (**keys only — values may contain gateway secrets**), provider reviews, impersonation start/stop. Impersonation (`app/Domain/Admin`): admin-only start, target never an admin, no nesting, `mustLog()` audit row *before* the swap (failed insert aborts), session regenerated on both legs, stop route outside the admin group (the actor is the impersonated user), amber banner + Leave control on every shell via the shared `impersonation` prop. Charts use the dataviz-validated `--chart-1/--chart-2` palette (validated against the real card surfaces, light + dark). Screens: rebuilt `/admin/dashboard`, `/admin/reports/{slug}` with date+status filters + CSV export, `/admin/activity`, "Login as" on provider show + booking show. 34 new tests (452 suite-wide).

## M14 CMS & Platform Settings
- `pages` (markdown/richtext), `faqs`, `banners`, `settings` (typed key-value: string/int/bool/json + group)
- Settings UI groups: General (branding: name/logo/colors/currency/timezone — D8 zero-hardcode rule), Booking, Payments (incl. GST config), Tracking, Notifications, Features
- Language manager (D9): `languages` table + JSON translation files + admin UI to add/edit languages; English default, Hindi = phase-2 content
- ✅ *Done when:* changing a flag (e.g., disable wallet) reflects across app without deploy.
- **Shipped (ADR D20):** settings/banners halves shipped earlier (M14-early in Phase 1, M12); this pass added the rest. `pages` — markdown body, `MarkdownRenderer` (`Str::markdown` w/ `html_input: strip` + `allow_unsafe_links: false`) is the only output path, public route under the reserved `/p/{slug}` prefix (published only, else 404), `SavePage` derives/dedupes slugs, footer links served by the cached `FooterPages` read model as a shared Inertia prop (white-label). `faqs` — plain-text Q&A, active+sorted on the storefront home as a native `<details>` accordion, admin CRUD. Language manager — `languages` table (`en` seeded + protected: never editable, never deletable, its catalog owned by the reconcile script), locale code strictly pattern-validated (it is the `lang/{code}.json` filename — path-traversal guard) and immutable after creation, `/admin/languages` list w/ per-language translated-count + add/toggle/delete, `/admin/languages/{id}/translations` editor (search + untranslated-only filter, single JSON body — catalog > `max_input_vars`), `SaveTranslations` keeps only catalog keys + drops blanks, `DeleteLanguage` also removes the file but refuses the site locale. Storefront: footer page links + FAQ section; admin nav: Pages/FAQs/Languages. Seeded: About/Terms/Privacy (footer), 5 FAQs, en+hi rows. New deps: @tailwindcss/typography (prose styles). 37 new tests (489 suite-wide).

## M15 Web Installer & Updates
- `/install` wizard (blocked after completion via lock file): requirements check (PHP ver/exts, writable dirs incl. `public/`) → DB credentials → run migrations+seeders **+ `storage:link`** → create admin → write `.env` (incl. generated `REVERB_*` keys) → done
- **`public/storage` is load-bearing:** banners, media-library assets and the branding logo are served from the public disk — without the link every image on the site 404s. The migrate step publishes it (non-fatal: a host that forbids symlinks must still finish installing), and the requirements step flags a read-only `public/` before the user gets there. `APP_URL` is captured from the browser's own origin on the DB step, so the emitted image URLs match the host being installed on.
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

> [!done] Shipped (ADR D21)
> `support_tickets` + `support_ticket_messages`; `app/Domain/Support` (OpenTicket / ReplyToTicket / AssignTicket / ResolveTicket / CloseTicket — plain enum + guarded transitions, deliberately **not** `BookingStateMachine`). One cross-role controller behind `role:customer|provider` (`/support/tickets`, page shell picked by role — notifications-page idiom); providers reach it **before approval** (KYC trouble is what support is for). Attachments ride medialibrary on the **private disk**, served only by the policy-checked `support.attachments.show` route (mirrors booking problem photos, one hop longer — media hangs off the message and is cross-checked against the ticket). Closed-read-only enforced in `SupportTicketPolicy@reply` (no admin `before()` bypass) **and** re-checked under a row lock in `ReplyToTicket`. Staff reply → `pending` + auto-assign first responder + notify owner; owner reply → back to `open` (reopens `resolved`) + notify assignee. Notifications ride M11 (`database`+`broadcast`, ShouldQueue + afterCommit → ≤2s gate via Reverb; mail deferred with FCM per D14). Admin queue w/ status/priority/assignee filters, canned responses from the `support.canned_responses` JSON setting (+ `support.max_attachments`), resolve requires a note, close is final; assign/resolve/close audit to `activity_logs`. `SupportSeeder` ships one live + one closed demo thread.

---

# Phase 6 — Product surface (M17–M27)

> [!abstract] Why this phase exists
> M01–M16 built the *business*: a booking can be placed, dispatched, tracked, paid, invoiced, reviewed and supported. What is missing is the **product surface** a buyer sees on day one — a marketable storefront the admin can restyle without code, a settings panel that scales past one giant form, and the operator depth (staff accounts, offline payments, email templates, module toggles) a CodeCanyon script is judged on. Scope agreed 2026-07-12; decisions recorded as ADRs D22–D27.
>
> **Nothing here is a rewrite.** Every module either regroups shipped screens or adds a new one behind the existing seams (settings registry, medialibrary, `MarkdownRenderer`, `ActivityLogger`, M11 notifications).

## Admin information architecture (target)

The admin sidebar is currently 17 flat items and Settings is one long form. Both hit their ceiling here. Target tree — **M17 builds the shell, later modules fill their slots**:

```
Dashboard
Bookings          → All bookings · Dispatch offers
Catalog           → Categories · Services
Providers         → All providers · KYC queue · Payout requests
Customers         → All customers · Wallets                                  [M17]
Locations         → Cities · Zones                                           [M25]
Payments          → Online received · Offline / bank · Refunds · Wallet ledger [M22]
Marketing         → Coupons · Sliders & banners · Referrals · Popups
                    · Testimonials · Sponsors · Subscribers                  [M19]
Reviews
Support           → Tickets · Canned responses · Inquiries                   [M19]
Blog              → Categories · Posts · Blog settings                       [M21]
Frontend CMS      → Menus · Homepage sections · Pages · Page builder · FAQs
                    · Footer · Login page · Custom CSS & JS · Header/Footer style [M19, M20]
Media manager                                                                [M18]
Reports           → Bookings · Earnings · Services · Providers · Activity log
System settings   → General · Commission · Payment methods · Payout · Email setup
                    · Email templates · SMS · API keys · SEO · Analytics · reCaptcha
                    · Cookie & GDPR · Language · Currency · Timezone · Cron status
                    · Module manager · Staff & roles · About & update    [M17, M23, M24, M26, M27]
```

Rules: a group with one child is not a group; every leaf is a route, never a modal-only screen; nav items are **declared by their module** (M27's registry), never hardcoded in the layout.

## M17 Admin IA & Settings Hub
- Collapsible sidebar groups: `NavItem` gains `children`; active-trail expansion; group visibility driven by role/permission (ready for M26) — no group renders empty
- **Settings stops being one form.** `/admin/settings/{group}` sub-pages, each posting only its own group. Today's single `UpdateSettingsRequest` validates every key on every save; with ~20 groups that is a 300-rule request object and a landmine factory (a new required key 422s every unrelated save — landmine 13). Replace with **per-group rule providers** (ADR D24)
- Customers screen (`/admin/customers`): list, search, filters (spend, bookings, joined), detail drawer (bookings, wallet, referrals, tickets), "Login as" (M13 impersonation), block/unblock
- ✅ *Done when:* every existing admin screen is reachable from its group; saving the Booking settings group cannot be broken by adding a key to the Payments group; a new customer's whole history is on one screen.

> [!done] Shipped 2026-07-12 (ADR D24)
> **Settings hub.** `app/Domain/Settings/Groups` — abstract `SettingsGroup` (key/label/description/**keys()**/rules/values/apply) + 12 groups (branding, localization, features, booking, dispatch, tracking, payments, payouts, invoice, reviews, referrals, support), resolved through `SettingsGroupRegistry`; `SaveSettingsGroup` action replaces the old `UpdateSettings` (which wrote every key on every save). Routes are `/admin/settings` (redirect to the first group) → `/admin/settings/{group}` GET/PUT; an unknown group **404s**. `UpdateSettingsRequest` composes the rules of exactly the group named in the route, so a payload physically cannot carry — let alone write — another group's keys. **`SettingsGroupCoverageTest` is the guard that makes D24 hold:** every key in `SettingsRegistry::defaults()` is owned by exactly one group (no duplicates, no orphans, no phantom keys) — adding a key without a group now fails loudly instead of leaving a setting no admin can reach. **Dispatch and tracking got their first UI** (their settings had been code-only since M06/M07), and `features.otp_required` finally has a switch. Test fixtures follow: `SettingsFixtures::payload($group)` — landmine 13 is now scoped to one group's payload.
> **Grouped sidebar.** `NavItem` gained `children`; `NavMain` renders collapsible groups (shadcn `Collapsible` + `SidebarMenuSub`) with active-trail auto-expansion, and the flat 17-item admin nav became Dashboard · Bookings · Catalog · Providers · **Customers** · Zones · Marketing · Reviews · Support · Content · Reports · Settings. Single-child groups stay plain items. Customer/provider shells pass flat items and are unchanged.
> **Customers screen.** `/admin/customers` (search name/email/phone, active/blocked filter, bookings count + lifetime spend + wallet balance per row) and `/admin/customers/{customer}` (6 KPI tiles, recent bookings, wallet ledger, tickets, Login-as, Block/Unblock). **Blocking:** `users.is_active` existed since M01 and nothing enforced it — now `SetUserActive` (admin-proof: an admin can never be blocked) + `users.blocked_reason` + `EnsureUserActive` in the `web` group logs a blocked user out on their next request. A blocked user also **cannot be impersonated** (M13's swap would have torn down the admin's own session on the next hop). Both legs audited via `ActivityLogger`.
> 26 new tests (531 suite-wide, 2500 assertions).

## M18 Media Manager
- Central library over the medialibrary already in use: grid + list, search by name/type, filter by collection/owner, folder-ish tagging, bulk delete, storage usage
- **`MediaPicker` component** — the single upload/choose UI reused by banners, pages, blocks, blog, testimonials, sponsors, popups (a per-module uploader in seven places is seven bugs)
- Uploads land in a `library` collection on the **public** disk (marketing assets). User uploads (KYC, problem photos, review photos, ticket attachments) stay on the private disk and are **never listed here** — the library is not a file browser for customer data
- Orphan report + prune command (`media:prune-library`, dry-run default)
- ✅ *Done when:* an image uploaded once is reusable across a banner, a page block and a blog post; deleting it warns about every model that references it; no private-disk file is ever visible.

> [!done] Shipped 2026-07-13 (ADR D29)
> `media_assets` + the medialibrary `library` collection on the **public** disk. `app/Domain/Media`: `UploadMediaAsset` (the only way a file enters the library — the banner form's own upload routes through it, so the manager sees every image on the site), `AttachLibraryAsset` (**picking copies the file** into the consumer's own collection and stamps `library_asset_id` on the copy), `DeleteMediaAsset` (refuses an asset in use), `MediaLibrary` read model (assets + usage counts + storage stats; grouped in PHP, not a JSON-path aggregate, so MySQL/MariaDB/SQLite agree). Screens: `/admin/media` (grid, search, upload, usage badge, storage tiles, delete dialog) and the shared **`MediaPicker`** dialog — pick-or-upload, JSON endpoints (`admin.media.picker`) because it opens over a half-filled form and an Inertia visit would throw it away. Banners are the first consumer; M19/M20/M21 reuse the picker unchanged. `media:prune-library` lists unused files and only deletes with `--force` (never scheduled — it deletes an admin's uploads). Uploads are raster-only (jpg/png/webp): the library generates webp conversions, and an SVG is a script container the storefront renders inline. **Private-disk collections — KYC, booking photos, review photos, ticket attachments — are never listed** (asserted). 11 new tests (558 suite-wide, 2757 assertions).

## M19 Frontend CMS Pack
The storefront becomes admin-editable end to end. All content markdown-or-plaintext, rendered through `MarkdownRenderer` (D20) — never raw HTML.
- **Menu builder** — `menus` + `menu_items` (nested, sortable); locations `header` / `footer_1..3`; item targets: internal route, CMS page, blog post/category, custom URL; visibility per role/guest
- ~~**Homepage sections**~~ — **moved to M20** (ADR D31): they were always going to become page-builder blocks, and building the model twice buys nothing. The storefront home keeps its current body until M20 replaces it with blocks
- **Testimonials** — CRUD (name, role, avatar, quote, rating) **or** promote a real review to a testimonial (one click from M10's queue)
- **Sponsors / partners** — logo strip (image + link + sort)
- **Popup content** — scheduled promo modal (markdown + image + CTA), audience (guest / customer / provider), show-once cookie, frequency cap
- **Footer builder** — columns from menus + about blurb + contact block + app-store links + payment badges
- **Social links** — settings group, consumed by footer + share buttons
- **Login & registration page appearance** — side image, headline, sub-copy, toggle social-proof strip (no auth-logic changes here)
- **Header / footer style** — layout variant (classic / centered / minimal), sticky, transparent-over-hero, container width
- **Custom CSS & JS** — admin-authored snippets injected into the shell (see ADR D26 — permission-gated, off by default, escaping rules)
- **Cookie / GDPR banner** — configurable text + link to the privacy page + accept/decline stored client-side
- **"Become a provider" landing page** — CMS-driven pitch page + apply CTA into M05 onboarding
- **Contact form → inquiries** — public contact page; a submission **opens a support ticket** (M16) rather than a second inbox; honeypot + rate limit + optional reCaptcha (M24)
- **Newsletter subscribers** — footer signup, list + CSV export (reuses M13's export pipeline), double-opt-in off by default
- ✅ *Done when:* a fresh install can rebrand the entire storefront — menus, hero, sections, testimonials, footer, legal — without touching a `.tsx` file.

> [!done] Shipped in two parts — part 1, site chrome (2026-07-13, ADR D30/D31)
> **Menus.** `menus` (one row per location, `location` unique) + `menu_items` (self-referencing `parent_id`, `type` route/page/url, `target`, `visibility`, `sort_order`, `is_active`). `app/Domain/Cms`: `EnsureMenus` (locations create themselves), `SaveMenuItem` (re-parents defensively: a parent from another menu, itself, or a second nesting level is silently flattened to root), `ReorderMenu` (scoped to the menu — an id from another menu in the payload is ignored, not stolen), `DeleteMenuItem` (children cascade). **`SiteMenus` resolves the tree to plain links on the server and drops what it cannot resolve** (D30): route targets are allowlisted, page targets must be published, URLs must be `http(s)://` or `/`. Cached forever, flushed by the actions; **visibility is filtered per request, not cached**. Admin screen `/admin/menus`: one card per location, add/edit dialog (type-aware destination picker), up/down reorder, sub-links.
> **Storefront chrome.** New `SiteContent` read model feeds one `site` shared prop (menus + appearance + social + cookie + custom code). `PublicLayout` is now assembled from `SiteHeader` (3 variants, optional sticky), `SiteFooter` (columns from the footer menus + about blurb + contact block + social; falls back to the M14 footer-page row when the menus are empty or the simple variant is chosen), `CookieBanner` (choice stored client-side only — recording a decline would *be* the tracking the visitor refused) and `CustomCode`. The login/registration page grows an admin-owned side panel (headline, sub-copy, image via `MediaPicker`) and stays the plain centered card when all three are blank.
> **Custom CSS/JS (D26).** Off by default; injected **only** on storefront routes (`HandleInertiaRequests::isStorefront()`) — the admin panel never runs the snippet, so a broken one can always be removed from the screen that saved it. Injected as `textContent` on a real DOM node, never `dangerouslySetInnerHTML` (React does not execute a `<script>` it renders, and `innerHTML` would truncate a snippet containing `</script>`). Saves are audited by the existing settings logger, which records **keys only** — the code itself never lands in the activity log (asserted).
> Four new settings groups: **Appearance**, **Social links**, **Cookie & GDPR**, **Custom CSS & JS** (16 groups now; `SettingsFixtures::payload()` and the coverage test carry them). 14 new tests (572 suite-wide, 2956 assertions).

> [!done] Part 2 — marketing content & inbound (2026-07-13)
> `testimonials` (optional `review_id`, nullOnDelete), `sponsors`, `popups` (audience + schedule + frequency), `subscribers` (unique email). `app/Domain/Marketing`: SaveTestimonial / SaveSponsor / SavePopup (each takes an optional `MediaAsset` and copies it in via `AttachLibraryAsset`), **`PromoteReviewToTestimonial`** (one click from M10's queue; the quote is **copied, not referenced** — a customer editing the review later must not silently rewrite the marketing page; a hidden review cannot be promoted; promoting twice returns the same row), `Subscribe` (idempotent by lower-cased address; re-subscribes an opted-out row) and `Unsubscribe` (**stamps `unsubscribed_at`, never deletes** — a deleted row would be silently re-added by the next signup, losing the opt-out).
> **Popups** ride the `site` shared prop: the server picks the newest live popup whose audience matches the visitor and renders its markdown through `MarkdownRenderer` (raw HTML stripped, D20); the browser only remembers *when it last showed it* (localStorage, frequency cap) — no table, no per-visitor row.
> **Contact form → support ticket (M16), guests included.** `support_tickets.user_id` becomes nullable and gains `guest_name` / `guest_email`; `OpenContactTicket` writes an owned ticket for a signed-in visitor and an **admin-only** one for a guest. Every existing notification path was already `?->`-guarded, so a guest ticket notifies nobody until mail lands in M23. Honeypot (`website` must be empty → `prohibited`) + `throttle:5,1`. **Landmine:** `whenLoaded()` collapses a *loaded-but-null* relation to `null` before the closure runs, so the resource uses `when(relationLoaded('user'))` to fall back to the guest's name.
> **Newsletter:** footer signup (honeypot + throttle, hidden by `appearance.newsletter_enabled`), `/admin/subscribers` list, and CSV through **the M13 report pipeline** (`SubscribersReport` in `ReportRegistry`) rather than a second export path. Screens: `/admin/testimonials`, `/admin/sponsors`, `/admin/popups`, `/admin/subscribers`, public `/contact`, plus testimonial + sponsor sections on the storefront home. 13 new tests (585 suite-wide, 3068 assertions).

## M20 Page Builder (block-based)
- A page is an ordered list of **typed blocks** (`page_blocks`), each a validated JSON payload against a block schema — **not** a free-form drag canvas (ADR D22)
- Block types v1: `hero`, `rich_text`, `services_grid`, `categories_grid`, `steps`, `stats`, `testimonials`, `faq`, `cta`, `gallery`, `embed` (sanitized allowlist), `spacer`
- Each block: React renderer + admin form, registered in one `BlockRegistry` (type → schema → form → renderer). An unknown/renamed type renders nothing rather than crashing the page
- Drag to reorder, duplicate, hide-without-delete, per-block visibility window
- Homepage sections (M19) become blocks on a reserved `home` page once this lands — one content model, not two
- ✅ *Done when:* the home page and a landing page are both built from blocks in the admin; removing a block type from the registry degrades to a blank slot, never a 500.

> [!done] Shipped 2026-07-14 (ADR D22 realised, new D32)
> `page_blocks` + `app/Domain/Blocks`: an abstract `Block` (type · label · **fields()** · rules · data) and 14 types behind `BlockRegistry` — hero, rich_text, banners, categories_grid, services_grid, steps, stats, gallery, testimonials, sponsors, faq, cta, embed, spacer. **One class owns everything about a block**: its schema drives the admin form (`BlockFields` renders it — fourteen types, one form, and a new field is a line of PHP), its rules are composed into `SavePageBlockRequest` so a payload is validated against the schema of *its own type* (and on update the type comes from the stored row, never the payload), and its `data()` resolves the props the renderer gets.
> **Resolution is total, the way menus are (D30):** `PageBlocks` drops a row whose type is not in the registry and a block whose `data()` returns null — so a removed block type, or an embed pointing at a host we no longer allow, leaves a **gap**, never a 500 on a public page (asserted with a bogus `type` row). `data()` may hand back models; `BlockPresenter` (HTTP) maps them through the resources that already exist, so the domain keeps its distance from `App\Http` (arch rule) and no block re-implements a wire shape (**ADR D32**).
> **The storefront home is now a page.** Reserved slug `home`, undeletable, served at `/` (`HomeController`) and 404 at `/p/home`; its blocks *are* the front page (hero with the search box, banners, category grid, featured services, how-it-works, testimonials, sponsors, FAQ — all seeded, all removable). `/services` keeps the catalog + search. A page with no blocks falls back to a categories + services grid, so an admin who deletes everything still has a storefront. A CMS page renders **either** blocks **or** its markdown body — one page, one source of truth.
> **Embeds are derived, not echoed:** the admin pastes a normal YouTube/Vimeo/Maps/OSM link and the block derives the embed URL from a host allowlist; anything else is rejected on write (an `<iframe src>` taking what it is given is a stored XSS). Pictures ride M18: picking copies the file into the block (D29) and dropping it from the payload deletes the copy, so usage counting and delete-refusal keep working. Reorder is scoped to the page (a foreign id is ignored, not stolen); duplicate copies the pictures too; hide-without-delete and a per-block schedule window are on every block. Two arch guards: every PHP block type has a React renderer *and* is mapped in the renderer registry; every block class is registered. 16 new tests (601 suite-wide, 3243 assertions).

## M21 Blog
- `blog_categories` + `blog_posts` (markdown body via `MarkdownRenderer`, cover image via `MediaPicker`, excerpt, author, tags, publish window, `is_featured`)
- Public `/blog` index (paginated, category filter, search) + `/blog/{slug}`; related posts; RSS feed
- Per-post SEO fields (meta title/description/OG image) — consumed by M24's SEO layer
- Blog settings: posts per page, comments off (v1), show author, featured-post layout
- ✅ *Done when:* a post published from the admin appears on `/blog`, in the sitemap, and renders correct OG tags; an unpublished/scheduled post 404s.

> [!done] Shipped 2026-07-14
> `blog_categories` + `blog_posts` (markdown body, excerpt, tags, cover via the M18 library, per-post SEO fields, `is_featured`, `published_at`). `app/Domain/Blog`: SavePost (derives + de-duplicates the slug; **publishing without a date means "now", and unpublishing clears the date** — the flag and the moment can never disagree), DeletePost, SaveBlogCategory, DeleteBlogCategory.
> **Publication is a moment, not a flag.** `scopePublished` = `is_published` **and** `published_at <= now`, so a scheduled post is *absent* until its time comes — `/blog/{slug}` 404s, the index skips it, the feed skips it. Bodies render through the one sanitizing `MarkdownRenderer` (D20), and the markdown **source never reaches a public page** (the resource only exposes `body` on admin screens).
> Public `/blog` (paginated, category filter, search, one featured lead) + `/blog/{slug}` (OG/Twitter tags from the SEO fields, falling back to title + excerpt; related posts from the same category) + `/blog/feed` (**RSS 2.0**, a Blade view — the second non-Inertia view after the invoice; declared *before* `{post:slug}` so a post called "feed" cannot swallow the feed URL). A deleted category leaves its posts alone (`nullOnDelete`) — they become uncategorised, not deleted.
> New settings group **Blog** (enabled, posts per page, show author, related count) — 17 groups. **`blog.enabled` off 404s the index, every post and the feed**: a buyer who does not want a blog gets no blog, rather than an empty page for search engines to index. `blog.index` joins the menu route allowlist (D30), so the header can link to it. The sitemap half of the gate belongs to M24's SEO layer, which consumes the same `meta_*` fields. 14 new tests (615 suite-wide, 3394 assertions).

## M22 Payments Hub
- **Admin payments list** (`/admin/payments`) — the `payments` table has existed since M08 but is only visible per-booking. Filters: gateway, status, method, date; totals row; link to booking + refund action
- **Offline payment method** — customer selects "Pay offline", uploads proof (private disk), booking stays `pending_payment`; admin verifies → `ConfirmPayment` (the existing idempotent, row-locked action — **no second money path**, ADR D27) → booking reaches `placed`. Reject → reason + notification
- **Bank transfer** — a configured bank-accounts list (`bank_accounts`: label, account name/number, IFSC, UPI ID, QR image, notes) shown at checkout as the offline instructions
- **Payout accounts** — `payout_accounts` per provider (UPI / bank, verified flag); replaces the free-text details typed into M09's payout dialog; admin sees the account on the payout request
- **Wallet ledger** admin view + manual adjustment (credit/debit with reason) — writes through `WalletService` only (D15), audited
- ✅ *Done when:* an offline-paid booking follows the exact same state path as a Razorpay one; every rupee on the payments screen reconciles with the earnings ledger.

> [!done] Shipped 2026-07-14
> `bank_accounts` + `payout_accounts`; `payments` gains `bank_account_id` / `reference` / `reviewed_by` / `reviewed_at` / `failure_reason`, `payout_requests` gains `payout_account_id`. `app/Domain/Payments`: SubmitOfflinePayment, VerifyOfflinePayment, RejectOfflinePayment, AdjustWallet, SaveBankAccount, DeleteBankAccount. `app/Domain/Earnings`: SavePayoutAccount, DeletePayoutAccount, VerifyPayoutAccount (+ `RequestPayout` now takes a `PayoutAccount`).
> **D27 held: there is one money path.** An offline payment is an ordinary `payments` row with `gateway = offline` waiting in the existing `initiated` state — **no new `PaymentState`**, or every money query written since M08 would have had to learn about it. Declaring a transfer does **not** place the booking (`pending_payment` stands; an unpaid booking is never dispatched), and a second declaration updates the open row instead of queueing another. Admin verification only *guards* (offline gateway, still initiated, not already reviewed) and then delegates to the row-locked idempotent `ConfirmPayment` every gateway webhook calls, so the booking is `placed` through `BookingPlaced` exactly once — a double-click settles nothing twice. Rejection fails the payment with a reason and notifies the customer, but never cancels the booking: it stays payable and dies on the existing `bookings:expire-unpaid` schedule. `PaymentProvider::Offline` is not an "online gateway", so the booking's `payment_method` is never rewritten — the invoice keeps saying it was a transfer. Proof is a `singleFile()` private-disk collection served behind a policy (owner or admin) with a media-belongs-to-payment cross-check.
> **Payout destinations became rows, and the request still snapshots them (ADR D33).** `RequestPayout` takes one of the provider's own accounts, stores `payout_account_id` *and* `method_details = $account->toSnapshot()` — editing the account afterwards cannot rewrite what a settled payout says it paid to. Editing clears the admin's verified tick; an account with an open payout on it cannot be deleted.
> Screens: `/admin/payments` (KPI tiles computed on the *filtered* query, gateway/status/search/date filters, proof link, verify + reject dialog), `/admin/bank-accounts` (cards + QR via the M18 picker), the customer pay page's bank-transfer card ("we are checking your transfer"), the provider's saved payout accounts, and a wallet credit/debit dialog on the admin customer screen that writes through `WalletService` alone (D15) — so it cannot overdraw and the ledger still reconciles. `payments.offline_enabled` is **off by default**, and the method is only offered when it is on *and* an active bank account exists. 35 new tests (650 suite-wide, 3628 assertions).

## M23 Communications
- **Email setup** — SMTP host/port/user/password/encryption/from in **settings, not `.env`** (write-only secrets, M08 pattern), plus a **Send test email** button. Mail was deferred at M11 (D14); this is what turns it on
- **Email templates** — `email_templates` (event key, subject, markdown body, variables, enabled). Every notification renders through a template if one exists, **else the shipped default** (ADR D25 — a mangled template must never break a booking confirmation). Variable picker + live preview + send-test-to-me
- **SMS channel** — `SmsGateway` contract + MSG91 / Twilio drivers over the raw HTTP client (no vendor SDKs, D15 precedent); `sms` channel joins `via()` only when configured (D14 precedent); `sms_logs` for delivery audit. Pulls the "SMS notification channel" item out of the v1.5 backlog
- **Notification matrix UI** — the event × role × channel table from M11, but as toggles (`notification_preferences`): admin decides which events send mail/SMS/push; users get an opt-out page
- **Push composer** — admin sends a broadcast notification (in-app + FCM when configured) to a segment (all / customers / providers / a city), scheduled or now
- ✅ *Done when:* a booking confirmation arrives by mail with an admin-edited template; deleting the template still delivers the shipped default; an unconfigured SMS gateway silently drops out of `via()` and nothing 500s.

> [!done] Shipped 2026-07-14
> `email_templates` + `notification_preferences` + `sms_logs`. `app/Domain/Comms`: `NotificationEvent` (8 events — the key an override and a preference hang off) + `NotificationChannel` (mail/sms/fcm **only**), `MailConfigurator`, `EmailTemplateRenderer`, `NotificationPreferences`, `NotificationChannels`, `SmsManager` + `SmsGateway` contract with MSG91/Twilio drivers over the raw HTTP client (no SDKs, D15), and 5 actions (SaveEmailTemplate / DeleteEmailTemplate / SendTestEmail / SaveNotificationPreferences / SendAnnouncement).
> **One base class now owns every notification's delivery.** `PlatformNotification` names its event and fills one payload; the base derives the in-app row, the Reverb broadcast, the push, the SMS and the email from it. `via()` is decided in exactly one place (`NotificationChannels`) instead of being copy-pasted into each class, and **a channel joins only when its provider is configured *and* the preference allows it** — configuration is checked first, so switching mail on for an install with no SMTP queues nothing into a void (D14).
> **D25 held: the template is an optional layer.** Missing row, disabled row, empty render, or a renderer that throws — every one of them falls back to the shipped default email. Bodies are markdown through the one `MarkdownRenderer` (D20), so raw HTML in an admin's template is stripped and an unknown `{{ placeholder }}` renders as *nothing* rather than as itself. The admin's live preview calls the same renderer the real send calls, so what previews is what arrives.
> **SMS is inert by construction.** No gateway selected (or half-filled credentials) = `SmsManager::active()` is null = the channel never appears in `via()`. When it is live, `SmsChannel` **never throws** — a dead provider writes a `failed` row in `sms_logs` and the booking that triggered it carries on (M07's rule for a dead Reverb). SMS is off on every event by shipped default: each message costs the operator money.
> **`database` and `broadcast` are not switchable.** They are the platform's own record of what it did to your booking and your money; a user who could turn them off would lose the bell and the feed with them. Preferences cover mail/SMS/push, and a user's opt-out beats the admin's default.
> Screens: settings groups **Email** (SMTP, write-only password, synchronous *Send test email* so a bad host is a form error, not a worker log) and **SMS** (gateway picker, write-only keys) — 19 groups; `/admin/notifications` (event × channel matrix that says which channels are actually *available*, plus the announcement composer — segment, title, message, link, `url:http,https` because an href is a script sink); `/admin/email-templates` (per-event editor, variable chips, live preview, send-test-to-me); `/settings/notifications` (the user's own opt-outs). An announcement is an ordinary notification, so it rides the same channels and lands in the same feed — no second delivery path — and blocked accounts are skipped. 42 new tests (692 suite-wide, 3798 assertions).

## M24 System Settings Hub
Fills the System-settings group with the remaining leaves. Each is small; together they are the "professional script" checklist.
- **SEO** — global meta defaults, per-page/service/post overrides, OG image fallback, `sitemap.xml` (generated, cached), `robots.txt`, schema.org JSON-LD (`LocalBusiness`, `Service`, `Article`)
- **Currency** — symbol, code, position, decimals, digit grouping (Indian vs Western). **Single currency per install** (ADR D23); `App\Support\Money` becomes settings-driven
- **Timezone** — select (the setting exists, the UI does not)
- **API keys** — Firebase/FCM, Google Maps (optional tile/geocode alternative), SMS, analytics — all write-only secrets, `*_set` booleans (M08 rule)
- **reCaptcha** — v3 keys + per-form toggles (register, login, contact, ticket); **off by default and inert when unconfigured** (the app must install with no third-party keys)
- **Analytics** — GA4 / GTM / Meta Pixel IDs injected into the shell head; consent-aware (respects the M19 cookie banner)
- **Cron status** — the scheduled-command list with last-run/next-run + the exact crontab line to paste; a red banner when the scheduler has not run in 24h (the most common broken install)
- **About & update** — version, PHP/DB/queue/Reverb health, changelog, `app:update` runner (M15's update path gets a UI), optional Envato purchase-code field (D8)
- ✅ *Done when:* a fresh install with **zero** third-party keys boots, browses, books and pays cash — every optional integration degrades to off, not to an error.

> [!done] Shipped 2026-07-14
> Five new settings groups — **Currency**, **SEO**, **Analytics**, **API keys**, **reCaptcha** (24 groups) — plus a `/admin/system` screen and `pages.meta_title` / `meta_description` + the same pair on `services` (blog posts already had them from M21).
> **D23 realised, harder than specced: `Money::format()` has no currency argument any more.** One currency per install means a caller *cannot* ask for another one, because the platform does not have one to give. Symbol, position, decimals and Indian-vs-Western grouping are settings, and `resources/js/lib/format.ts` implements the same rules by hand so the browser and the invoice PDF print the identical string (ext-intl is not on every shared host, D8). The currency **code** moved onto the Currency screen with them — a screen group is not a storage group (D24), and the code and its formatting must be edited together or not at all.
> **SEO: one definition of "public".** `SitemapBuilder` walks the same scopes the storefront walks, so an inactive service, an unpublished page and a *scheduled* post are absent from `/sitemap.xml` for the same reason they 404 (M21). A switched-off sitemap **404s** rather than serving an empty `<urlset>` — an empty sitemap tells a crawler the site has no pages. `robots.txt` is generated too (`public/robots.txt` was deleted), and `SeoMeta` resolves title/description/image in three layers: the record's own fields, then its natural title, then the site defaults. `schema.org` JSON-LD (LocalBusiness / Service / Article) is built on the server and injected as a `textContent` script node — `JSON.stringify` is the escaping, so no admin string is ever interpolated into markup.
> **reCaptcha is inert, and fails open (ADR D35).** No keys, or the form's switch off, and the rule collapses to `nullable|string` — a fresh install never asks a visitor to prove themselves to a service the operator has not signed up for. When it *is* on, the token is `required` (a rule does not run on a missing-or-empty value, so "no token" had to be made to fail like "a bad token"). If Google is unreachable, the visitor gets through and the failure is logged: **a CAPTCHA outage must not become a signup outage.**
> **Analytics are ids, not snippets** (D26's rule: the common case must never need the site-wide script-execution permission). GA4 / GTM / Meta Pixel IDs are validated to their known shapes, shared only on storefront routes, and **nothing loads until the cookie banner has been accepted** — the consent lives in localStorage and the server never learns who declined (M19).
> **`/admin/system`** separates *broken* from *not configured*: debug-on-in-production and a missing storage link are red; no SMTP, no SMS and no Firebase are simply "Not set up" — if those were red an operator would learn to ignore the page. It also carries the **cron heartbeat** (`system:heartbeat` every five minutes stamps a settings key; a stale stamp raises the loudest banner in the panel, because a scheduler nobody wired up is the most common broken install of a self-hosted product) and an **`app:update` runner** — the same command an operator with SSH would type, with its output shown on the screen that started it. 37 new tests (729 suite-wide, 4073 assertions).

## M25 Cities & Multi-City — **shipped 2026-07-15** (ADR D36)
- `cities` (name, slug, state, timezone, center lat/lng, is_active, sort_order) — zones belong to a city (`zones.city_id`, FK **restrict**). The old free-text `zones.city` string is **backfilled into rows and dropped**: two spellings of one town used to be two towns, and nothing could hang off a string.
- **The city gate is the zone gate one level up, never a second geography.** `Service::scopeInCity()` reads the very `service_zone` rows the pin already resolves against (D12) — grouped by `zones.city_id`. A visitor with an address is gated by their zone; a guest, or a customer browsing a town they have no address in, is gated by the **city**. `ActiveCity` is the one resolver: explicit choice (session) → detected from the default address pin → first active city.
- **A zone only serves while its city does** (`Zone::scopeActive` requires an active city): switching a city off closes the whole town in one click — it leaves the switcher, stops resolving new address pins, and its services drop off the storefront — **while every historical booking keeps its `zone_id` and its money rows untouched** (the snapshot rule, D33).
- **The city's timezone is load-bearing, not decoration (D36):** the slot grid is drawn in the *city's* clock, because "we work 08:00–20:00" is a claim about the town the visit happens in. `SlotGenerator::days(?City)` / `isBookable($utc, ?City)` — the platform timezone is the fallback when there is no city. The checkout re-asks for the grid (partial reload) when the customer picks an address in another city, and drops the chosen slot: it was a different wall-clock hour.
- Admin: `/admin/cities` CRUD (timezone picker validated against `timezone_identifiers_list()`, centre pin, sort, active), the zone form's city **select**, a **Locations** nav group (Cities · Zones), and a **Cities (last 30 days)** dashboard card — **a city with no trade is a row of zeroes, never a missing row** (that row is the answer to "how is the new city doing").
- Deleting a city with zones is refused (deactivate instead); the FK is `restrict` behind it.
- ✅ *Done when:* two cities with distinct zones both work end to end; deactivating a city hides it from the storefront without touching its historical bookings. **Met** — seeded demo: Bengaluru (2 zones) + Mysuru (1 zone), plus a demo address outside every zone. 24 new tests (745 suite-wide, 4180 assertions).

## M26 Staff Roles & Permissions — **deferred past launch** (client decision, 2026-07-15)
> The operator is one person at launch, and a permission system nobody is on the other side of is a system with no users. Deferred until the client's team grows — after some days in production. Nothing in Phase 7 depends on it; the three roles from M01 still gate every route (`RouteGuardTest` sweeps them).
- `spatie/laravel-permission` has been installed since M01 but only three roles are used. Introduce **granular permissions** (`bookings.view`, `payouts.approve`, `settings.manage`, `cms.publish`, `custom_code.manage`, …) grouped by module
- `staff` role + admin-managed staff accounts; custom roles (name + permission checkboxes); permissions gate **routes, nav items and actions** (one `can:` middleware + the M27 registry drive all three)
- Admin (super) is un-deletable and always holds every permission; staff can never grant themselves a permission they lack
- All staff actions already flow through `ActivityLogger` (M13) — actor is now a named staff member, not "admin"
- ✅ *Done when:* a staff account with only `bookings.*` sees exactly the Bookings group, and every other route 403s (tested per permission, not per screen).

## M27 Module Manager — **deferred past launch** (client decision, 2026-07-15)
> It was always "last, by construction". It is a CodeCanyon-buyer feature, not a launch feature: this install wants every module on. Deferred with M26.
- One `ModuleRegistry`: each module declares **key, name, nav items, routes, settings groups, dependencies, default state**
- Admin toggles a module off → its nav group disappears, its routes 404, its settings group hides, its scheduled commands stop registering. Data is never deleted
- Dependency guard: cannot disable a module another enabled module depends on (Payments cannot go while Bookings is on)
- Core modules (auth, bookings, settings) are **not toggleable** — the list is explicit, not "everything is optional"
- **Built last on purpose** — every module must exist before it can register itself
- ✅ *Done when:* disabling Blog removes it from nav, 404s `/blog`, hides its settings group, and re-enabling it restores everything with data intact.

---

## P7.1 Security & Hardening pass — **shipped 2026-07-15** (ADR D37)

Not a module: a sweep across the sixteen that exist. Every item below is a hole that no policy check could have closed, because each one lives *outside* the request the policy guards.

- **`UploadRules` is the one allowlist.** Ten form requests each carried their own `mimes:jpg,jpeg,png,webp`. They agreed — which is exactly why nobody would have noticed the day one drifted, and the one that drifts is the one that admits the file the rest refuse. An arch test fails any form request that spells a mime list by hand.
- **`ServesPrivateFiles`: an image renders inline, everything else downloads, and nothing sniffs.** A policy decides *who* may read a private file; this decides *what the browser may do with it*, which is a different question. KYC docs, bank receipts and ticket attachments accept PDFs, and a PDF served inline from our own origin is a script host.
- **The private disk is no longer served by the framework.** The starter's `'serve' => true` on the `local` disk registers `GET /storage/{path}` **and** `PUT /storage/{path}` straight into the folder holding KYC documents — signature-gated, but gated by a signature rather than by our policies, i.e. a second door no `BookingPolicy` has a say over. Closed; a test asserts the routes are gone.
- **`SecurityHeaders` is a *global* middleware, and that is the whole point.** `auth` sits in Laravel's middleware priority list, so it is sorted ahead of anything appended to the `web` group: an unauthenticated request redirects out before a group middleware runs, and the login redirect — with every 403/404/500 the exception handler renders — would have shipped bare. `nosniff`, `X-Frame-Options: DENY` (an admin panel that can be framed is a Refund button that can be clicked), `Referrer-Policy`, and a `Permissions-Policy` that keeps **`geolocation=(self)`** because M07's journey screen reads GPS.
- **Rate limits, swept not sampled.** Three named limiters (`auth` per IP, `uploads` per user, `public-write` per IP) plus a test that fails any state-changing route reachable **without a session** that has no throttle. That is the class that gets forgotten: the ordinary POST that happens to need no login, which an attacker can loop for free.
- **Credentials cannot reach the browser, and the sweep derives that from the key names** — not from a declaration a new settings group could forget. Every `*secret*`/`*password*`/`*_token*` key gets a sentinel value; every one of the 24 settings screens is rendered; the sentinel must appear in none of them. The public halves (`razorpay_key_id`, `stripe_publishable_key`, `recaptcha.site_key`) must still render, or checkout has no key to open the gateway with.
- **Webhook replay** is pinned for both gateways: a duplicated delivery is normal gateway traffic, and it settles the booking exactly once (one payment row, one `BookingPlaced`, one status-history row).
- ✅ *Done when:* the sweeps pass and each one fails loudly on a fresh omission. **Met** — 17 new tests (762 suite-wide, 4338 assertions). Two live findings fixed on the way: the framework's private-disk routes, and the header middleware that never ran on a redirect.

## P7.2 Performance & query budget — **shipped 2026-07-15**

- **`Model::preventLazyLoading()` outside production.** A lazy load is now a **test failure**, not a slow page. It stays off in production: a violation in front of a customer must degrade to a slow response, never to a 500. The whole suite passed the moment it was switched on — the eager-loading discipline had held across sixteen modules.
- **`QueryBudgetTest`: the query count must not grow when the data does.** Not "fewer than N queries" — a magic number nobody can justify and that rots on the next join. The property is data-independence, and it needs no constant: measure a page with 3 rows, add 20 more, measure again. (Going *down* is allowed: Eloquent skips an eager-load query entirely when no parent row has a key to load.)
- **It immediately found one real N+1** that `preventLazyLoading` structurally *cannot* see: `/admin/payments` called `getFirstMedia('proof')` per row, and medialibrary answers that with **its own query** rather than by touching the relation — so no lazy-load violation, and one extra query per payment. Fixed by eager-loading `media`. Every other media-bearing list already did.
- **`DB::prohibitDestructiveCommands()` in production.** `migrate:fresh`, `migrate:refresh` and `db:wipe` now refuse to run on a live install. The buyer's server is one mistyped command from an empty database, and M24's `app:update` runs artisan from a **browser button**.
- **Fixed a flaky test that had nothing to do with the test that failed.** Roughly one parallel run in three died with an `ErrorException` in a random Zones test: `rename(bootstrap\cache\packages.php): Access is denied`. Cause: the System-screen test really ran `app:update` → `optimize:clear` → deleted `bootstrap/cache/packages.php`, and **bootstrap/cache is real, shared state across the parallel workers** (the `lang/` landmine again). Whichever worker was booting at that moment raced the others to rebuild the manifest and lost. `RunUpdate` is swapped in that test now.
- ✅ *Done when:* the suite fails on a lazy load, and on a page whose query count scales with its rows. **Met** — 8 new tests (770 suite-wide, 4382 assertions), five consecutive clean parallel runs.

## P7.3 Installer finalization — **shipped 2026-07-15**

The wizard was written in Phase 1 against a 12-table app. It now installs a 40-table one. What it was missing was not features — it was the difference between an install that *runs* and an install that *works*.

- **The bug that only a real install could have shown us: the WebSocket key was baked into the JavaScript.** `configureEcho()` read `import.meta.env.VITE_REVERB_APP_KEY`, which is compiled into `public/build` — and the installer mints a **fresh random `REVERB_APP_KEY` for every install**. We ship the bundle prebuilt. So every buyer's browser would have carried *our* key, Reverb would have rejected every connection, and **live tracking and the notification bell would have failed silently on a site that otherwise looked perfectly healthy**. Echo is now configured from the server's response (`App\Support\ReverbConfig`, a shared Inertia prop): public key, host, port, scheme. The secret and the app id stay on the server. *A build-time constant cannot describe a runtime install.*
- **The wizard now writes a deployable `.env`, not just a connectable one**: `BROADCAST_CONNECTION=reverb` with the full Reverb host/port/scheme pair (what the *browser* dials) and `REVERB_SERVER_*` (what `reverb:start` binds to behind the proxy); durable `SESSION_DRIVER`/`CACHE_STORE`/`QUEUE_CONNECTION=database`; and `SESSION_SECURE_COOKIE` set from the scheme of the URL the buyer typed — the wizard knows it, so the buyer never has to learn that the rule exists.
- **The base seed is now what a working site needs, not what a demo needs**: roles, settings defaults **and the CMS** — `lang/en.json` hangs off the `en` `languages` row, the footer hangs off the legal pages, and the home page *is* a page (M20). A buyer with no `home` row would never discover that the home page is editable at all.
- **Three processes, handed over as copy-paste text with this install's own paths** (`DeploymentGuide`): the cron line, a Supervisor block for the queue worker and Reverb, and systemd units as the alternative. Shown on the wizard's last screen *and* on `/admin/system`, for the operator who clicked past it. **Every one of the three fails silently** — no cron and payouts never release; no worker and every notification is written and never sent; no Reverb and the live map never moves. The site keeps loading and quietly does less.
- **The queue can now be caught being dead.** `system:heartbeat` (cron) dispatches a queued `QueueHeartbeat`, and the *worker* stamps the clock — the scheduler cannot vouch for the queue, only a worker picking the job up can. A stale worker gets its own red banner, but **only once the scheduler is alive**: with no cron there is nothing to pick up, and blaming the worker would send the operator after the wrong process.
- ✅ *Done when:* a fresh install reaches a working site with realtime, and the operator is told the three things it needs. **Met** — 10 new tests (779 suite-wide, 4430 assertions). **The fresh-VPS run itself is still the open gate; this is what makes it worth running.**

---

## Cross-cutting v1 features

- **PWA**: vite-plugin-pwa — installable icon, offline shell, FCM web push; app feel without app stores (big India + sales point)
- **SEO**: Inertia SSR for public pages, sitemap.xml, schema.org (`LocalBusiness`, `Service`), per-service meta/OG tags
- **i18n**: every user-facing string through translation helper from day one (D9)
- **Indian formatting**: ₹ + Indian digit grouping (1,00,000) via shared `format.ts` / PHP formatter

---

> [!todo] Cross-cutting definition of done (every module)
> - Feature tests (Pest) for happy path + failure paths — and the right *kinds* of test: see the catalogue in [[07-Conventions]] § Testing (policy matrix incl. the admin case, validation failures, idempotency/replay, concurrency where a row lock exists, degradation with the optional service switched off, security exposure, registry sweep)
> - Policies/authorization for every endpoint — proven by the route-guard **sweep**, not only by the endpoint's own test
> - Seeded demo data so the module is demo-able immediately
> - Mobile-responsive UI checked at 375px, and **clicked through in a real browser** — Pest asserts props server-side and never runs React
> - Migration runs up **and** down; `migrate:fresh --seed` still yields a working demo
> - No hardcoded branding/strings — settings, theme tokens, translation files (D8/D9)

Related: [[04-Database-Schema]] · [[06-Roadmap]] · [[07-Conventions]]
