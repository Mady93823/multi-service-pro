---
title: 07 — Conventions & Working Rules
tags:
  - internal
  - conventions
---

# 07 — Conventions & Working Rules

Rules every session must follow when writing code for this project.

## Laravel code organization

```
app/
├── Domain/                     ← business logic, grouped by module
│   ├── Bookings/
│   │   ├── BookingStateMachine.php
│   │   ├── Actions/            ← PlaceBooking, CancelBooking, ... (one verb per class)
│   │   ├── Events/             ← BookingPlaced, BookingAccepted, ...
│   │   └── Enums/BookingStatus.php
│   ├── Dispatch/  Tracking/  Payments/  Providers/  Catalog/ ...
├── Http/
│   ├── Controllers/{Customer,Provider,Admin,Api}/   ← thin; call Actions
│   ├── Requests/               ← ALL validation lives here
│   └── Middleware/
├── Models/                     ← Eloquent only; relationships, scopes, casts. No business logic
├── Policies/                   ← one per model touched by users
└── Support/                    ← Settings accessor, Geocoder, PaymentGateway contracts
```

- **Actions pattern**: controllers never contain business logic; an Action does one thing, is Pest-tested directly
- **Events for cross-module effects**: dispatch module never imports notification code — it fires events, listeners react
- PHP enums for every status; match statements over string comparisons

## Frontend organization

```
resources/js/
├── pages/{customer,provider,admin}/...   ← Inertia pages, kebab-case routes
├── components/ui/                        ← shadcn primitives (CLI-managed, don't hand-edit heavily)
├── components/{booking,tracking,catalog}/ ← feature components
├── layouts/
├── hooks/          ← use-tracking-channel.ts, use-geolocation.ts, ...
└── lib/            ← echo.ts (Echo/Reverb client), geo.ts (haversine, throttle), format.ts
```

- TypeScript strict; no `any` (use `unknown` + narrowing)
- Shared types for API payloads in `resources/js/types/` — keep in sync with Laravel Resources
- Tracking channel/Echo logic lives in hooks, never inline in pages

## Quality bar (first project = reputation project)

| Check | Tool | When |
|---|---|---|
| PHP style | Pint (default preset) | pre-commit |
| PHP static analysis | Larastan level 6 | CI + pre-push |
| PHP tests | Pest (Feature-first) | every Action + every endpoint happy/deny path |
| JS lint/format | ESLint + Prettier | pre-commit |
| Types | `tsc --noEmit` | CI |

- Never commit failing tests; never `--no-verify`
- Every migration must run **up and down** cleanly
- Seeders must always leave a fully demo-able app (`php artisan migrate:fresh --seed` = working demo)

## Git

- Branches: `feature/m04-booking-engine`, `fix/...`
- Conventional Commits (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`)
- `main` is always installable/demo-able; phase gates get tags (`v0.1-phase1`)

## Security rules (recurring checklist)

- Every route: auth middleware + Policy check (no "admin can't reach it via UI" excuses)
- All uploads: validated mime+size, stored on private disk, served via signed temporary URLs (KYC docs especially)
- Tracking channels: private/presence only, authorization policy in `routes/channels.php` (booking's customer/provider/admin) — never public channels for location data
- Webhooks: signature verification + idempotency keys
- Rate limiting: auth endpoints, OTP endpoints, tracking ping endpoint (1/s per booking)
- No secrets in code — `.env` only; installer generates strong secrets (`REVERB_APP_SECRET` etc.)

## Environment & running (dev)

```bash
# Laravel (root)
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
composer dev            # runs: php artisan serve + queue:listen + vite (starter script)
php artisan reverb:start --debug   # WebSocket server (second terminal), :8080
```

- Windows dev box: use Laragon or Herd for PHP/MySQL
- Real-GPS testing needs HTTPS → use `npm run dev -- --https` or tunnel (dev domain) when testing on phone

## Documentation upkeep

- Scope change → update `docs/client/Requirement-Analysis.md` **and** [[02-Modules]] in the same commit
- Decision change → append to [[03-Tech-Stack]] ADR list (D8, D9, …) with why; never silently switch tech
- New notification → add to matrix in [[02-Modules]] M11
- Phase done → tick gates in [[06-Roadmap]]

Related: [[00-Overview]] · [[03-Tech-Stack]]
