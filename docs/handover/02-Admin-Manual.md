---
title: Admin Manual
tags:
  - handover
---

# Running UrbanServe

Everything an operator does is in the browser. There is no task in this document that requires a developer.

## The shape of a booking

```
customer books  →  placed  →  dispatched to providers  →  accepted
                                                            ↓
                          completed  ←  in progress  ←  arrived  ←  en route
```

- A **cash** booking is `placed` immediately.
- An **online** booking (card, UPI, wallet) starts as *pending payment* and is **only placed once the money actually settles** — so an unpaid booking is never sent to a provider. If nobody pays, it expires by itself.
- A **bank transfer** is not a payment until you verify it (**Payments → verify**). Until then the booking is still waiting.
- Status changes are recorded. **Bookings → a booking → History** shows who moved it and when. You can move a booking by hand from that screen when you need to.

## Money

- **Payments** lists every payment the platform has ever taken. Filter by gateway, status or date. The totals answer the *filtered* question, not the global one.
- **Bank transfers** appear here awaiting verification, with the customer's uploaded receipt. Verifying one settles the booking through exactly the same path a card payment takes. Rejecting one does **not** cancel the booking — the customer can still pay another way.
- **Refunds** (from a booking's page) credit the customer's **wallet**.
- **Commission** is a percentage of the pre-tax value, set globally in **Settings → Payouts** and overridable per category.
- **Payouts**: a provider requests one; you approve it and mark it paid. **On a cash job the provider owes you money** (they took the customer's full payment at the door, including your commission and the tax), so their balance can be negative — that debt is settled against their next positive earnings. This is normal and it is not a bug.
- **Invoices** are generated per booking as a GST PDF, from a snapshot taken when the booking was placed. Changing a tax rate later never rewrites an old invoice.

## Providers

- **Providers → review queue**: they sign up, upload their documents, and wait for you. You cannot approve an incomplete profile. Rejecting one lets them fix it and resubmit.
- Suspending a provider forces them offline immediately.
- A provider's rating is **recalculated** from their visible reviews — so hiding an unfair 1-star review really does pull it out of their average.

## Customers

- **Customers** shows what each one has booked, spent, and asked support about.
- **Block** a customer to end their session immediately and keep them out.
- **Login as** signs you in as them to see exactly what they see. An amber banner shows you are impersonating; it is recorded in the activity log.

## Content — the whole storefront is editable

| Screen | What it controls |
|---|---|
| **Content → Pages** | Your home page is a **page**, built from blocks (hero, service grids, testimonials, FAQ, …). Add, reorder, hide, schedule. |
| **Content → Menus** | Header and footer navigation. |
| **Content → Blog** | Markdown posts, categories, scheduled publishing, RSS. |
| **Marketing** | Banners, coupons, testimonials, sponsors, popups, the newsletter list. |
| **Settings → Appearance** | Header style, footer, login page, cookie banner, custom CSS/JS. |
| **Content → Languages** | Translate the whole interface. English is the fallback and is never deleted. |

## Communications

- **Settings → Email** is where SMTP lives. Send yourself a test from that screen — a bad host is a form error, not a mystery.
- **Notifications** is a matrix: which events go out over which channels. **In-app notifications cannot be switched off** — they are the record of what happened to a customer's booking and money.
- **Email templates** are optional. The shipped default sits underneath every one of them, so a broken template never eats a booking confirmation — it just falls back.
- **SMS** stays completely inert until you configure a gateway, and it is off by default on every event, because it costs money.

## Support

**Support** is the ticket queue. Customers and providers open tickets; the contact form on the public site opens one too, guests included. A closed ticket is final — nobody can reply to it, not even you.

## System

**Admin → System** is the page to check when something feels wrong.

- It separates **broken** (red) from **not configured** (grey). "No SMTP" is not an error — it is a choice you have not made yet.
- It tells you if **cron** or the **queue worker** has stopped, and gives you the exact commands to start them.
- **Run update** applies a new release: migrations, caches, and it shows you the output.

## Reports

**Reports** exports bookings, earnings, services, providers and subscribers as CSV. Small exports download immediately; large ones are prepared in the background and land in your notifications.
