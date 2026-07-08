/# UrbanServe — Urban Company–style Home Services Platform

Client project. 3 roles (Customer / Provider / Admin), Laravel backend, live provider tracking, web-installer deployment.

## Source of truth

Read `docs/internal/00-Overview.md` first — it links the full doc set (architecture, modules, tech stack, DB schema, tracking spec, roadmap, conventions). The client-approved scope lives in `docs/client/Requirement-Analysis.md`.

## Hard constraints (client-mandated — never substitute)

- Backend: **Laravel 12** (PHP 8.3+), MySQL 8
- Frontend: **Inertia.js v2 + React 19 + TypeScript + shadcn/ui + Tailwind v4** — UI blocks researched via **shoogle.dev**
- Live tracking + realtime: **Laravel Reverb WebSockets + HTML5 Geolocation + Leaflet + OpenStreetMap** — no Node.js (client hosting ruled it out, ADR D11); single Laravel deployable
- Firebase: FCM push + optional phone OTP only (app must run with Firebase unconfigured)
- Payments: **Razorpay default** (UPI-first, INR, GST invoicing), Stripe secondary, pay-after-service enabled
- Must stay **easy installable** (web installer wizard) and **easy expandable** (domain events + settings flags + API-first)
- **White-label product rule (CodeCanyon build):** zero hardcoded branding/strings — settings + theme tokens + translation files (ADR D8–D10). Client-facing docs never mention resale.

## Working rules

- Follow build order in `docs/internal/06-Roadmap.md`; don't skip phase gates
- Code conventions, folder layout, and quality bar: `docs/internal/07-Conventions.md` (Actions pattern, thin controllers, Pest tests, Larastan L6, strict TS)
- Booking status changes only via `BookingStateMachine`; money columns are snapshots; ledgers append-only (`docs/internal/04-Database-Schema.md`)
- Scope/tech changes: update client doc + internal docs in the same commit; record decisions as ADR entries in `docs/internal/03-Tech-Stack.md`

## Current status

Phase 1 in flight — direction approved 2026-07-04. Decided: Razorpay; multi-city build, single-city India launch; India-first defaults; CodeCanyon product-ization; **Laravel-only realtime via Reverb (2026-07-06, D11 — client cannot host Node.js)**. 16 modules after Urban Company parity pass (M16 helpdesk; job-start OTP, cancellation fees, favorites/rebook, review photos added). Remaining client questions in client doc §10 (commission, payouts, OTP, brand, hosting, launch city — only Phase 4 blocks on them). Done: scaffold + Reverb smoke test, M01 auth/roles/layouts, M02 catalog (admin CRUD + storefront), M14 settings registry (branding/localization + admin UI), M15 installer base (`/install` wizard: requirements → DB/.env → migrate+seed → admin → lock), i18n skeleton (lang/en.json catalog + useTrans/`__()` everywhere + SetLocale middleware + catalog guard test — conventions in `docs/internal/07-Conventions.md` §i18n). Phase 1 gate (fresh-VPS wizard install) deferred by user — run before handover. Phase 2 started: M03 zones + addresses done (admin leaflet-draw polygon zones, customer address book w/ pin picker, Nominatim proxy cached+rate-limited, zone gate on catalog, PHP point-in-polygon ADR D12). M04 booking engine done (session-only cart w/ addons + guest merge, settings-driven slot picker, bookings + items + status-history tables, BookingStateMachine w/ 14 statuses + job-start OTP guard, address_snapshot JSON on bookings, cancellation fees flat/percent via settings, reschedule w/ provider release, favorites/rebook, problem photos on private disk, admin manual transitions, cash/pay-after-service only — gateways M08). M05 provider onboarding/panel done (provider_profiles/documents/categories/blackouts tables, `EnsureProviderApproved` middleware `provider.approved` gating panel w/ redirect to onboarding, profile form w/ bio/experience/categories/working-hours JSON/base-location pin/service-radius, KYC docs on private disk w/ replace-per-type + admin review queue, resubmission loop rejected→pending, online toggle + blackout CRUD, admin `/admin/providers` list+review incl. can't-approve-incomplete guard + suspend-forces-offline, `ProviderApprovalChanged` event for M11; blackout dispatch/slot enforcement deferred to M06). M06 dispatch & job assignment done (dispatch_offers table; app/Domain/Dispatch: DispatchStrategy interface + nearest/broadcast strategies, EligibleProviders finder — approved+online+category-or-ancestor+Haversine-within-radius+not-blackout+not-busy+not-already-offered, geo in PHP per D12; DispatchBooking/AcceptOffer/DeclineOffer actions, queued ExpireDispatchRound job guarded on expires_at so sync-queue installs degrade gracefully, BookingOffered/DispatchExhausted events; auto-dispatch on placement via DispatchPlacedBooking listener + dispatch.* settings mode/timeout/max_rounds/auto; accept = searching→assigned→accepted + expire siblings w/ row lock for broadcast race; provider Jobs screen w/ offer accept/decline + linear status buttons incl. job-start OTP; admin Run-dispatch button + offers list; FCM/Reverb push deferred to Phase 3). **Phase 2 gate met:** booking placed → dispatched → accepted → completed with status buttons, all transitions in history. Next: Phase 3 — M07 live tracking (Reverb + Geolocation + Leaflet, D11 locked stack) + M11 notifications.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
