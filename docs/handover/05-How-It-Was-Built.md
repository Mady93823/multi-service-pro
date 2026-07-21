---
title: How It Was Built
tags:
  - handover
---

# How UrbanServe was built

This document is for you, the owner. It is not a technical manual — it explains **how the platform was delivered, and the care that went into making it dependable**. It exists so that when you hand this platform to staff, investors or a future developer, you can speak to the standard it was built to.

The short version: the platform was built in deliberate phases, each ending in something you could click through and approve; every feature is covered by an automated test suite that runs on every change; and the parts that touch **money, private data and live tracking** were engineered defensively from the first day, not patched later.

---

## Built in phases, each a working demo

Rather than disappearing for months and returning with everything at once, the platform was delivered in stages. Each stage ended with a genuinely working slice you could use.

| Stage | What became usable | 
|---|---|
| **1 — Foundation** | Sign-in for all three roles, the admin panel, the service catalogue, and the browser installer |
| **2 — Booking core** | Cities and zones on a map, the booking engine, professional onboarding, and automatic job dispatch |
| **3 — Live tracking** | The real-time map that shows a professional approaching, and the notification system |
| **4 — Money** | Online payments, GST invoices, the customer wallet, commission and professional payouts |
| **5 — Growth tools** | Reviews, coupons, referrals, the dashboard and reports, and the help centre |
| **6 — Product surface** | The full storefront content manager, page builder, blog, media library, offline payments, email/SMS templates and multi-city support |
| **7 — Hardening & handover** | A dedicated security pass, performance tuning, real-world install verification, and this documentation |

At the end of every stage there was a clear checkpoint — for example, *a booking can be placed, offered to a professional, accepted, tracked on a map and completed, with every step recorded* — before the next stage began.

---

## The quality bar

### An automated test suite that runs on every change

The platform ships with a comprehensive automated test suite — **over 850 individual tests, making several thousand checks**. These are not an afterthought; they were written alongside the features and they run every time the code changes. They cover the happy paths a user takes and, just as importantly, the failure paths: a payment that never completes, a cancelled booking, a coupon used twice at once, a professional going offline mid-search.

What this means for you: a change in one part of the platform cannot quietly break another. If something would misbehave, the tests catch it before it ever reaches a customer.

### A dedicated security review

Security was not left to chance. A focused review pass hardened the platform specifically around the things that matter to a marketplace:

- **Private files stay private.** Identity documents, payment receipts and support attachments are served only to the people entitled to see them, and can never be opened as something they are not.
- **Your secrets stay secret.** Payment-gateway keys and passwords are write-only in the admin panel — they can be set but never read back — and they never appear in any screen or log.
- **Every entry point is rate-limited** against abuse, and every payment confirmation is verified against the provider directly, never trusted from a redirect alone.
- **The whole platform sits behind protective security headers** and is designed to run over HTTPS.

### A performance budget

The platform was tuned so that **pages do not get slower as your data grows**. A screen that lists ten bookings and a screen that lists ten thousand do the same amount of work behind the scenes. This was verified with automated checks, not left to hope, so the platform stays fast as your business scales.

### Verified by a real install, not just in theory

Before handover, the platform was installed the way a new operator would install it — from an empty server, through the browser wizard — to prove the setup works end to end and to catch anything that only a real deployment would reveal. The installer, the background processes and the live features were all confirmed on a real environment.

---

## Principles that protect your business

A few design decisions run through the whole platform. They are the reason it can be trusted with real money and real customers.

- **Money is recorded as a snapshot.** The price, tax and commission on a booking are fixed at the moment it is placed. Changing a price or tax rate later never rewrites an old booking or invoice — history stays true.
- **An unpaid online booking is never sent to a professional.** Payment settles first; only then is the job dispatched. This single rule prevents the most common marketplace headache.
- **The books never lose track.** Earnings and wallet balances are kept as append-only records — nothing is ever silently edited. Even the awkward case of a professional owing commission after collecting cash at the door is accounted for correctly.
- **Failures degrade gracefully.** If the live-tracking connection drops, the professional's position is still saved and the map catches up. If email is not configured, in-app notifications still arrive. Nothing configured-but-broken takes the whole site down.
- **Nothing is hardcoded.** Your brand name, colours, currency, languages and content are all settings — the platform was built to be *yours*, not a fixed template.

---

## Built to last and to grow

- **A modern, well-supported technology stack** (Laravel, React and a self-contained real-time engine) with no exotic dependencies to maintain.
- **A single deployable application** — one thing to host, one thing to update, no separate services to keep in sync.
- **An API-ready architecture**, so native mobile apps or other future channels can be added on top of the same foundation.
- **Documented throughout**, so a future developer can find their footing quickly.

---

## What you are receiving

- The complete, running platform.
- The full automated test suite that keeps it dependable.
- This documentation set: a platform guide, role-by-role usage guides, an installation guide, and this delivery summary.
- A browser-based installer and one-click updates, so you are never dependent on anyone to keep it running.

The platform is finished, tested and ready for launch. A small number of advanced operator features (staff sub-accounts, a module on/off manager) are designed and reserved for after launch, to be shaped by real trade — everything a marketplace needs to open its doors is live today.
