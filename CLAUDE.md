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

Phase 1 in flight — direction approved 2026-07-04. Decided: Razorpay; multi-city build, single-city India launch; India-first defaults; CodeCanyon product-ization; **Laravel-only realtime via Reverb (2026-07-06, D11 — client cannot host Node.js)**. 16 modules after Urban Company parity pass (M16 helpdesk; job-start OTP, cancellation fees, favorites/rebook, review photos added). Remaining client questions in client doc §10 (commission, payouts, OTP, brand, hosting, launch city — only Phase 4 blocks on them). Done: scaffold + Reverb smoke test, M01 auth/roles/layouts, M02 catalog (admin CRUD + storefront), M14 settings registry (branding/localization + admin UI), M15 installer base (`/install` wizard: requirements → DB/.env → migrate+seed → admin → lock). Next: i18n skeleton (item 6), then Phase 1 gate.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
