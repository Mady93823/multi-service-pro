---
title: Welcome & Platform Guide
tags:
  - handover
---

# UrbanServe — Platform Guide

Welcome to your home-services platform. This document is the front door to your documentation set: it explains how the platform is organised, walks through everything it can do, and points you to the right guide for each role.

Everything described here is already built, tested and running. Nothing in day-to-day operation requires a developer — the whole platform is run from the browser.

---

## The three people the platform serves

The platform is one application with three doors. Each person sees only their own world.

| Role | Who they are | What they do |
|---|---|---|
| **Customer** | The person booking a service | Browses services, picks a time slot, pays, watches the professional arrive live on a map, rates the visit |
| **Professional** (provider) | The verified person doing the work | Sets up a profile, goes online for jobs, accepts offers, shares live location while travelling, gets paid |
| **Administrator** | You, the platform owner | Approve professionals, watch bookings, set pricing and commission, handle payouts, run reports, and control the entire storefront |

A single sign-up screen serves all three. A customer sees the shopfront; a professional sees a work panel; you see the control room.

---

## Your documentation set

| Guide | For whom | What it covers |
|---|---|---|
| **This document** | Everyone | How the platform is organised and everything it does |
| **Installation Guide** | Whoever sets up the server | Installing from a browser, and the three background processes |
| **Administrator Manual** | You | Running the marketplace day to day |
| **Professional Guide** | Your service professionals | Getting approved, taking jobs, getting paid |
| **Customer Guide** | Your customers | Booking, tracking, paying, reviewing |
| **How It Was Built** | You | The build journey, and the care taken to make it dependable |

---

## What the platform does — a complete tour

The platform is organised into clear feature areas. Every one of them is finished and in daily use.

### Booking & the storefront

- **Service catalogue** — Categories and sub-categories down to individual services (for example *Home Cleaning → Kitchen → Deep Kitchen Cleaning*). Each service carries images, a description, a duration and one of three pricing styles: a **fixed price**, an **hourly rate**, or **"inspection first"** where the final quote is given on site. Services can have paid add-ons, and you can curate a *"people also book"* strip to cross-sell.
- **Event Management** — A dedicated storefront page for events (weddings, birthday parties, kitty parties and the like), with the same smooth booking flow as any other service. Moving a category onto the Events page is a single choice in the admin panel.
- **Search & discovery** — Customers search by name, browse by category, and see featured services first.
- **Cart, slots & checkout** — A cart that survives sign-in, a time-slot picker you control (slot length, how far ahead people can book, how much notice you need), and a checkout that collects a contact phone and the service address.

### Where you operate

- **Cities & zones** — You define the cities you serve and draw service zones directly on a map. A customer's pinned address decides which services and professionals are available to them — book outside your zones and the platform declines politely.
- **One clock per city** — Each city carries its own timezone, so the slots a customer is offered are always in the local time of the town the visit happens in.
- **Address book** — Customers save addresses with a map pin, and the platform remembers the exact location for the professional.

### Matching a job to a professional

- **Automatic dispatch** — When a booking is placed, the platform offers it to the right professionals: the nearest ones, in the correct category, who are online and within their travel radius. An offer has a time limit; if it is not answered it moves to the next person.
- **Two matching styles** — Offer to the single nearest professional, or broadcast to all eligible and let the first to accept win. You choose, and you can always assign a job by hand.

### Live location tracking — the headline feature

- The customer watches the assigned professional approach **in real time on a map**, exactly like a food-delivery app.
- Location is shared **only during a job** — from the moment the professional starts the journey until they arrive — and never before or after.
- The map animates smoothly, shows an estimated arrival, and keeps working through a shaky mobile connection. If the network drops for a moment, the map simply catches up; a job is never lost to a hiccup.
- A **job-start code** the customer reads to the professional confirms the right person is at the right door before work begins.
- The professional gets one-tap **Google Maps navigation** to the customer's door.

### Money

- **Online payments** — UPI, cards, netbanking and wallets through Razorpay (with PayU, Stripe and PayPal also available). An online booking is only confirmed **once the money actually arrives**, so an unpaid booking is never sent to a professional.
- **Pay after service** — Cash or UPI on completion, and you can switch this on or off **per city and per zone**, so some areas can be online-only.
- **Bank transfer** — Customers can pay into a bank account you list and upload a receipt; you verify it and the booking settles the same way a card payment does.
- **Wallet** — Every customer has a wallet. Refunds land there, and it can be spent at checkout.
- **GST invoices** — A compliant GST invoice (with CGST/SGST/IGST breakdown) is generated for each booking as a PDF, from figures fixed at the time of booking, so a later tax change never rewrites an old invoice.
- **Commission & payouts** — You set commission as a percentage, globally or per category. Professionals accrue earnings, and request payouts you approve and mark paid. The books always balance — even the case where a professional owes you commission after collecting cash at the door is handled correctly and automatically.

### Trust & growth

- **Reviews & ratings** — One review per completed job, with stars, text and photos. A professional's rating is always recalculated from the reviews currently visible, so removing an unfair review genuinely corrects their average.
- **Coupons** — Flat or percentage discounts, with minimum-order rules, usage limits, per-customer limits, validity windows and first-order-only offers.
- **Referrals** — Every customer gets a referral code; the referrer is rewarded when the person they invited completes their first booking.
- **Banners & popups** — Promotional artwork on the home page, and timed popups, all managed by you.
- **Newsletter** — A subscribe box on the storefront and an exportable subscriber list.

### The storefront is yours to shape

- **Page builder** — Your home page and other pages are built from blocks you arrange yourself: hero banners, service grids, testimonials, statistics, galleries, FAQs, calls to action and more. Reorder, hide or schedule any block.
- **Menus** — Header and footer navigation, edited in the panel.
- **Blog** — Markdown articles with categories, scheduled publishing and an RSS feed.
- **Content pages** — About, Terms, Privacy and any other page you need.
- **Appearance** — Header and footer style, the login page, a cookie banner, and your own custom styling — all without touching code.
- **Media library** — One place for every image on the site, with a picker used across the panel.
- **Languages** — Translate the entire interface. English is the always-available fallback; Hindi ships ready.

### Communication

- **Notifications** — Customers and professionals are kept informed of every meaningful change to a booking, in the app, by email, and — once you connect it — by SMS and push. In-app notifications are always on, because they are the record of what happened.
- **Email & SMS** — Connect your own email server and SMS provider from the settings screen, with a test-send so you know it works before you rely on it. Email templates are optional; a dependable default sits underneath every one.
- **Announcements** — Send a message to everyone from one screen.

### Support

- **Help centre** — Customers and professionals raise tickets (from a booking or on their own), and the public contact form opens one too. You reply from an admin queue with saved canned responses. Professionals can reach support even before they are approved — which is exactly when they most need it.

### Running the business

- **Dashboard** — Key numbers at a glance: bookings, revenue, commission, top services, a professional leaderboard and city performance, with charts.
- **Reports & exports** — Bookings, earnings, services, professionals and subscribers exported to CSV.
- **Activity log** — A record of significant admin actions.
- **Impersonation** — "Log in as" a customer or professional to see exactly what they see, clearly marked and recorded.
- **System health** — One screen tells you what is healthy, what is simply not set up yet, and — if a background process has stopped — the exact command to restart it. Updates are applied from a button.

### Deployment

- **Browser-based installer** — The platform installs through a setup wizard in the browser: it checks the server, writes its own configuration, creates the database tables, and sets up your administrator account. No config files to edit by hand.
- **Easy updates** — A new release is applied from the admin panel.

---

## What is planned next

A short list of capabilities is designed and reserved for after launch, when there is real trade to shape them:

- **Staff accounts with fine-grained permissions** — additional admin logins, each limited to what they need.
- **Module manager** — turning whole feature areas on and off from the panel.

Everything else in this guide is live today.

---

## Where to go next

- Setting up the server for the first time → **Installation Guide**
- Running the marketplace → **Administrator Manual**
- Onboarding your professionals → **Professional Guide**
- Helping your customers → **Customer Guide**
- Understanding the care that went in → **How It Was Built**
