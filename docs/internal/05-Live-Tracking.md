---
title: 05 — Live Tracking (spec)
tags:
  - internal
  - tracking
  - critical
---

# 05 — Live Tracking (spec)

> [!danger] Locked stack — no substitutions
> **Laravel Reverb (WebSockets) + HTML5 Geolocation + Leaflet + OpenStreetMap.**
> Node.js removed 2026-07-06 — client hosting cannot run it (ADR D11 in [[03-Tech-Stack]]).
> This is the flagship feature. It must feel flawless in the client demo.

## Overview

No separate service. Laravel Reverb (first-party WebSocket server, Pusher protocol) runs as a second process of the same codebase. Provider GPS enters Laravel over authenticated HTTP pings; Laravel validates, persists, and broadcasts to the booking's customer over a private channel. Laravel is the single authority on *who may track what* — enforced by standard channel authorization, no custom tokens.

## Flow

1. Booking enters `accepted` → provider's Jobs screen links to the journey screen; customer booking page shows "will set off soon"
2. Provider taps **Start Journey** → `POST provider/jobs/{booking}/tracking/start` → `BookingStateMachine` transitions to `en_route`, creates `tracking_sessions` row (idempotent — a refresh re-uses the live session)
3. Provider browser watches GPS and pings:
   ```
   POST provider/jobs/{booking}/tracking/ping   (session auth + CSRF, ADR D13)
   { lat, lng, accuracy, heading, speed, recorded_at }
   ```
   Route: provider-role + `provider.approved`; the controller checks the actor is that booking's provider and the booking is `en_route` (else 409). Laravel validates payload, inserts `tracking_points`, updates `tracking_sessions.last_*`, broadcasts.
4. Customer subscribes via Laravel Echo (`@laravel/echo-react` hooks):
   ```ts
   useEcho(`tracking.booking.${id}`, '.LocationUpdated', updateMarker, [id], 'private');
   useEchoPresence(`tracking.booking.${id}`); // who's connected
   ```
   Channel authorization in `routes/channels.php`: booking's customer / provider / admin only. Wrong user = subscription refused. **One `tracking.booking.{booking}` callback serves both channels** — it returns member info (`{id,name,role}`), which authorizes the private channel (truthy) and populates the presence roster.
5. Provider taps **Arrived** → `tracking/stop` → session `ended`, state machine to `arrived`, map freezes to summary

## Broadcast events

| Event | Channel | Payload |
|---|---|---|
| `LocationUpdated` | `private-tracking.booking.{id}` | `{ lat, lng, heading, speed, ts }` — `ShouldBroadcastNow` (skip queue, latency matters) |
| `BookingStatusChanged` | `private-tracking.booking.{id}` + `private-user.{id}` | `{ status }` — arrived, in_progress, … |
| presence join/leave | `presence-tracking.booking.{id}` | Echo built-in → "customer watching" / "provider online" indicators |

Server-side validation on every ping: policy check, lat ∈ [-90,90], lng ∈ [-180,180], rate limit 1/s per booking (throttle middleware), drop points with `accuracy > 100m`.

## Provider client (React page in provider panel)

```ts
const watchId = navigator.geolocation.watchPosition(onPos, onErr, {
  enableHighAccuracy: true, maximumAge: 2000, timeout: 10000
});
// throttle to 3s, skip if moved < 5m (haversine) to save battery/traffic
```

- Requires HTTPS (Geolocation API refuses insecure origins) — dev via `localhost` or vite https
- UX: big Start Journey / Arrived buttons; live "customer can see you" indicator (presence channel); permission-denied help screen (how to re-enable location)
- Keep-awake hint: Screen Wake Lock API where supported

## Customer client (React page)

- `react-leaflet` map, OSM standard tiles + required attribution
- Provider marker: custom divIcon (avatar in a pin), smooth animated transitions between points (lerp over 1s — no teleporting markers)
- Polyline trail of last N points; auto-fit bounds (provider + destination), user-pan disables auto-fit until "recenter" tapped
- ETA: haversine distance ÷ rolling avg speed (min 15 km/h floor). Optional upgrade: OSRM public demo server for road ETA — behind `Routing` interface + feature flag (public OSRM has no SLA)
- **Fallback:** if Echo disconnected > 10s → poll `GET /api/v1/bookings/{id}/tracking/last` (serves `tracking_sessions.last_*`) every 10s + banner "live connection reconnecting…"

## Server internals (Laravel)

```
app/Domain/Tracking/
  Actions/StartTrackingSession.php   // accepted → en_route, opens session (idempotent)
  Actions/RecordTrackingPing.php     // validate → persist → broadcast
  Actions/EndTrackingSession.php     // en_route → arrived, ends session
  Events/LocationUpdated.php         // ShouldBroadcastNow
  Events/BookingStatusBroadcast.php  // ShouldBroadcastNow — status onto the map
  Enums/TrackingSessionStatus.php
  GeoPing.php                        // validated reading DTO
app/Console/Commands/PruneTrackingPoints.php   // tracking:prune, scheduled daily
routes/channels.php                  // tracking.booking.{booking} + App.Models.User.{id}
resources/js/lib/http.ts             // CSRF fetch for the non-Inertia JSON loop
resources/js/lib/geo.ts              // haversine / ETA / lerp
```

Settings (`tracking.*`, admin-editable, D8): `ping_interval_seconds` (3), `min_move_meters` (5), `max_accuracy_meters` (100), `stale_after_seconds` (30), `points_retention_days` (30).

> [!important] A ping must never fail because Reverb is down
> `RecordTrackingPing` persists the point and checkpoint **before** broadcasting, and swallows `BroadcastException` with a warning log. The customer's polling fallback covers the gap. Tests pin `BROADCAST_CONNECTION=null` so the suite never reaches for a live Reverb; the channel-authorization tests opt back in with a throwaway driver and replay `routes/channels.php` onto it (channels register against whichever driver was default at boot).

- Reverb config via `.env`: `REVERB_APP_ID/KEY/SECRET`, `REVERB_HOST`, `REVERB_PORT=8080` — generated by installer
- Run: `php artisan reverb:start` under supervisor/systemd (installer prints templates); nginx proxies wss upgrade
- `tracking_points` inserted per accepted ping (~1 row/3s per active journey — fine at launch scale); pruned after 30 days via scheduler
- Health: `/up` (Laravel health route) + scheduled command pings Reverb port, alerts admin if down

## In-app realtime (rest of platform)

Same Reverb. Private channel `user.{id}` carries Laravel **broadcast notifications** (toasts/badges: booking updates, new job offers, payout status). One realtime brain — no second system.

## Failure modes & handling

| Failure | Handling |
|---|---|
| Provider denies location permission | Blocking modal with browser-specific instructions; status stays `accepted`, customer sees "provider preparing" |
| GPS jitter / bad accuracy | Drop points accuracy>100m; 5m movement threshold |
| Provider phone locks / browser bg-throttles | Wake Lock; ping gap >30s → scheduled check emits stale presence → customer banner "location paused"; map keeps last-known |
| Reverb process down | Health-check command alerts admin; customer map falls back to polling last checkpoint; bookings unaffected (tracking degrades, never blocks jobs) |
| WS blocked by network | pusher-js automatic transport retry, then REST polling fallback |
| Session expiry mid-journey | Sanctum session — same as rest of app; re-auth redirect preserves booking URL |

## Acceptance checklist (demo-ready = all pass)

> Built 2026-07-09 and covered by `tests/Feature/Tracking/TrackingTest.php` at the HTTP/domain level (start, ping, accuracy drop, 409 off-journey, ownership, stop, fallback endpoint, channel auth both ways, Reverb-down degrade, prune). The list below is the **physical-device gate** and is still to be run — it needs real GPS over HTTPS, which no test can stand in for.

- [ ] Two real devices (phone as provider, laptop as customer): marker moves smoothly < 5s latency
- [ ] Provider walks 100m → trail draws, ETA updates, no marker teleport
- [ ] Kill Reverb process mid-journey → customer sees fallback banner + last position; restart → live resumes without reload
- [ ] Refresh both pages mid-journey → both rejoin and resume
- [ ] Second customer account cannot subscribe to `tracking.booking.{id}` (channel auth refuses, logged)
- [ ] `arrived` → session ends, map freezes to summary, history saved in `tracking_points`
- [ ] Whole flow works over HTTPS with real GPS (not just devtools sensor mock)

Related: [[01-Architecture]] · [[04-Database-Schema]] · [[02-Modules]]
