---
title: 00 — Project Overview
tags:
  - internal
  - overview
status: active
---

# 00 — Project Overview

> [!abstract] What this is
> Internal build docs for **UrbanServe** — an Urban Company–style on-demand home services platform. These docs are the source of truth for every Claude/dev session working on this project. Read this file first, then follow links.

## The one-paragraph brief

Client wants a 3-role (Customer / Provider / Admin) home-services marketplace web app: Laravel backend, modern fast UI, live provider location tracking (WebSockets + HTML5 Geolocation + Leaflet + OpenStreetMap — served by **Laravel Reverb**, client's hosting cannot run Node.js, D11), Firebase for push/OTP, easy web-installer deployment, and a modular codebase that is easy to expand.

## Hard requirements (non-negotiable, from client)

1. **Laravel** backend
2. **3 roles**: Customer, Service Provider, Admin
3. **Live tracking stack locked**: WebSockets + HTML5 Geolocation + Leaflet + OpenStreetMap — realtime engine is **Laravel Reverb** (client ruled out Node.js on their hosting, 2026-07-06; see [[03-Tech-Stack]] D11)
4. **Firebase** allowed/expected (FCM push, phone OTP)
5. **Easy installable** (web installer wizard, no CLI knowledge for the buyer)
6. **Easy expandable** (modular, event-driven, API-first)
7. **Modern, fast, cool UI**
8. UI component research via **shoogle.dev** (shadcn registries)
9. **Sellable product quality** — codebase doubles as a white-label CodeCanyon script: zero hardcoded branding, license-check toggle, demo mode (see [[03-Tech-Stack]] D8). Client-facing docs never mention resale.

## Reading order

1. [[01-Architecture]] — system shape, repos, boundaries
2. [[02-Modules]] — all 16 modules with acceptance criteria
3. [[03-Tech-Stack]] — exact versions + decisions & why
4. [[04-Database-Schema]] — tables, relations, status enums
5. [[05-Live-Tracking]] — the tracking service spec (most critical feature)
6. [[06-Roadmap]] — build order, phase gates
7. [[07-Conventions]] — code style, folder layout, git, testing rules

## Client deliverable

The polished client-facing document lives at `docs/client/Requirement-Analysis.md`. Keep it in sync if scope changes — it is what the client approved.

## Status

- **Current phase:** 0 wrap-up — direction approved, key decisions landed 2026-07-04
- **Decided:** Razorpay gateway (D7 resolved); multi-city build / single-city India launch; India-first defaults + i18n day one (D9); phone-first auth (D10); CodeCanyon product-ization (D8); **Laravel Reverb realtime — no Node.js (D11, 2026-07-06)**
- **Urban Company parity pass (2026-07-06):** added job-start OTP, cancellation fees, favorite providers + rebook, review photos, related-services cross-sell, M16 helpdesk; backlog gains membership plans, re-service warranty, SMS channel, product marketplace
- **Awaiting:** client — commission model, payout cycle, OTP at launch, brand name, hosting, launch city (client doc §10; only Phase 4 blocks)
- **Next action:** scaffold Laravel app per [[07-Conventions]], start Phase 1 in [[06-Roadmap]]
