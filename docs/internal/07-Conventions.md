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

## i18n (D8/D9 — no hardcoded user-facing strings)

- Catalog: `lang/en.json`, Laravel JSON convention — keys are the natural English source strings, other locales are `lang/{locale}.json` maps. Buyers can also reword English by editing `en.json` values.
- Frontend: `const t = useTrans()` from `resources/js/lib/i18n.ts`; `t('Save settings')`, placeholders Laravel-style — `t(':minutes min', { minutes })`. Keys must be **literal strings** (no template literals/variables) — the guard test scans for them.
- Backend: `__('Natural sentence')` for flash/validation messages; dot keys (`auth.failed`) stay in `lang/en/*.php`.
- Active locale: `SetLocale` middleware reads the `localization.locale` setting; `TranslationLoader` shares the catalog via Inertia `translations` prop.
- Guard: `tests/Feature/Localization/TranslationCatalogTest.php` fails the suite if a `t()`/`__()` key is missing from `en.json` **or** `en.json` has orphaned keys — regenerate entries when adding UI strings.
- Module-level `NavItem[]`/breadcrumb arrays can't call hooks — build them inside the component.

## Quality bar (first project = reputation project)

| Check | Tool | When |
|---|---|---|
| PHP style | Pint (default preset) | pre-commit |
| PHP static analysis | Larastan level 6 | CI + pre-push |
| PHP tests | Pest (`tests/Arch`, `tests/Unit`, `tests/Feature`) | every Action + every endpoint happy/deny path |
| JS lint/format | ESLint + Prettier | pre-commit |
| Types | `tsc --noEmit` | CI |

- Never commit failing tests; never `--no-verify`
- Every migration must run **up and down** cleanly
- Seeders must always leave a fully demo-able app (`php artisan migrate:fresh --seed` = working demo)

## Testing (what to write, not just how much)

A green suite means nothing if it only ever tests the happy path of the thing you just wrote. Coverage of *kinds of test* matters more than coverage of lines. Each kind below has caught a real bug in this codebase; the ones marked ⚠ caught one that shipped.

### The catalogue

| Kind | What it proves | Where it lives |
|---|---|---|
| **Action / unit** | one verb, in isolation, incl. its failure modes | `tests/Unit`, or a Feature test calling the Action directly |
| **HTTP feature** | the endpoint's happy path + every deny path (guest / wrong role / wrong owner) | `tests/Feature/...` |
| **Policy matrix** | owner ✓, stranger ✗, admin ✓/✗ — *state the admin case explicitly*, it is usually the interesting one | with the feature test |
| **Validation** | every rule has a failing case, not just a passing payload | with the feature test |
| **State machine** | every legal transition, and at least one illegal one that throws | `BookingStateMachine`, ticket status |
| **Idempotency / replay** ⚠ | the same webhook, the same click, the same queued job twice = one effect | M08 payments, M09 earnings |
| **Concurrency** ⚠ | two callers race a row lock (coupon last slot, offer accept, close-vs-reply) | M06/M08/M12/M16 |
| **Ledger invariants** | `balance == credits − debits`; append-only tables are never updated; a reversal negates rather than edits | M08/M09 |
| **Reconciliation** | the read model equals a raw SQL aggregate on the same data (dashboards, reports) | `DashboardTest` |
| **Event / listener wiring** ⚠ | the listener fires **once** (`php artisan event:list`), and the notification lands on the right channels | M06/M11 |
| **Degradation** | Reverb down, queue on `sync`, Firebase absent, gateway unconfigured → the app still works, just less | M06/M07/M11/M14 |
| **Security: exposure** ⚠ | secrets never reach the browser (`assertDontSee` the value, `missing()` the prop) — Inertia serializes every prop into the page HTML | `SettingsManagementTest` |
| **Security: private files** | the serve route policy-checks, and a *valid id from someone else's record* 404s | M05/M10/M16 |
| **Route guard sweep** | *every* route in a prefix carries its middleware — not just the ones you remembered to test | `tests/Feature/Security/RouteGuardTest.php` |
| **Architecture** | layering + hygiene rules that hold codebase-wide | `tests/Arch/ArchitectureTest.php` |
| **Registry coverage** | every declared thing is reachable: settings key → group, module → nav | `SettingsGroupCoverageTest` |
| **i18n catalog guard** | no missing key, no orphan key, in `.tsx`, `.php` and `.blade.php` | `TranslationCatalogTest` |
| **Migration up/down** | `migrate:fresh --seed`, then `migrate:rollback --step=1`, then `migrate` | manual gate, every module |
| **Browser click-through** ⚠ | the page actually renders — Pest asserts props server-side and **never runs React** | manual, per milestone |

### Rules that follow from the above

- **Sweeps beat samples.** If a rule must hold for *every* route / key / action, assert it in a loop over the real registry — not once, on the one you happened to write. The bug is always in the one nobody remembered.
- **Assert the negative.** A test that only proves the thing works cannot fail when the guard is deleted. Every policy gets a stranger; every validation gets a bad payload; every kill-switch gets an "off" case.
- **Test the seam, not the mock.** Gateways are faked at the HTTP boundary (`Http::fake`), never by mocking our own classes. `Notification::fake()` asserts channels via `via()`, not delivery.
- **Scope every count.** The full `DatabaseSeeder` runs before each test (`$seed = true`) — `Model::count()` includes demo rows. Scope to the record under test (`$booking->payments()->count()`), and filter list assertions so seeded rows can't drift into them.
- **A new required setting** goes in *its* group's payload in `Tests\Support\SettingsFixtures` — nowhere else (D24).
- **Shared fixtures are classes** in `tests/Support` (PSR-4), never Pest helper functions: helper functions share a global namespace and are not reliably visible across files under `--parallel`.
- **Anything that writes to a real path is namespaced per test file** (`lang/{code}.json` — LocaleTest owns `zz`, LanguageManagerTest owns `xa`–`xd`). Two files writing the same path = a flaky suite under parallel.
- **Rebuild before Pest** after adding an Inertia page (`npm run build`) — a missing Vite manifest entry 500s the page.

### Order of the gates (run them in this order; each one is cheaper than the next)

```bash
./vendor/bin/pint                 # style first — it rewrites files
npm run build                     # Vite manifest, before Pest touches a new page
./vendor/bin/pest --parallel      # Arch + Unit + Feature
./vendor/bin/phpstan analyse      # Larastan level 6
npx tsc --noEmit && npm run lint && npx prettier --check resources/
php artisan migrate:fresh --seed && php artisan migrate:rollback --step=1 && php artisan migrate
php scratchpad/reconcile_catalog.php   # i18n catalog, then re-run Pest
```

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
