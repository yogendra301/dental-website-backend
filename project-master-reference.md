# Project: Local Clinic Booking Websites — Master Reference

## Business Model
- Build websites + booking systems for local businesses, starting niche: **dental clinics**.
- Direct client acquisition via personal WhatsApp outreach — no platform/marketplace, no commission cut.
- Pricing: Website ₹3-8k / Booking system ₹5-15k / Bundle ₹10-20k.
- Target: 3-4 bundle clients in 5 months.
- Payment: 50% advance, 50% on delivery.

## Hosting Plan (sequencing)
1. Finish building the full website/system first.
2. Only then start Google Maps lead-digging + outreach.
3. Only then pay for Hostinger VPS (~₹500-700/mo) — avoid paying before confirming commitment.

## Tech Stack (final)
- **Frontend:** Plain HTML + Tailwind CSS + vanilla JavaScript (no framework). Single-page scroll layout.
- **Backend:** CodeIgniter 3 (PHP) REST API with MySQL database.
- **DB:** MySQL — single shared instance across all clients, multi-tenant via `clinic_id`.
- **Hosting:** Everything on Hostinger VPS (frontend files + API + MySQL). One codebase serves all clinics via config/URL username.
- **Asset handling:** `assetUrl()` helper function in frontend resolves paths - demo mode serves from `/assets/`, production from backend uploads.
- **Translation:** i18n system with `en.json` and `hi.json` language files.

## Core Principle (non-negotiable)
**One master template, reused via config — never rebuild per client.**
New client = new config object/row + their assets (logo/photos). Zero code changes. No per-client forks, no drift.

---

## Database Schema

### `clinics`
- id
- username (unique, used in URL: `/username` and `/username/admin`)
- name
- services (JSON)
- working_hours (JSON)
- slot_duration_min
- contact info (phone, address, map_embed_url)
- admin_password_hash (bcrypt)
- whatsapp_number (for owner alerts)

### `appointments`
- id
- clinic_id
- patient_name
- patient_phone
- service
- date
- time_slot
- status (pending/confirmed)
- source ("online" / "phone") — for clinic's own tracking

### `slots_blocked` (manual blackout dates/times)
- id
- clinic_id
- date
- time_slot

---

## Multi-Tenancy & Auth

- Each clinic identified by `username` in URL.
- Public booking page: open, no login — patients just book. `clinic_id` derived from username.
- Admin page (`/username/admin`): single password per clinic (not per-staff — intentional, avoids overkill auth system).
- Login issues a signed session token (JWT/signed cookie) containing `clinic_id`.
- **Critical security pattern:** every admin API call derives `clinic_id` from the verified session token — NEVER from URL params or request body. Prevents Clinic A from editing Clinic B's data. This rule applies everywhere in the codebase, no exceptions.
- Onboarding new clinic = insert DB row (username, name, hashed temp password) + send owner their admin link via WhatsApp. No new code.

---

## Functionality Scope (dental clinic v1)

### Public site (single-page scroll)
1. **Hero** — headline, subtext, hero image/video, optional Spline 3D embed, CTA to booking. Supports layouts: split, centered, fullbg, journey.
2. **Services** — card grid from config, click pre-fills booking widget. Cards have teal-tinted background with ripple hover effect.
3. **Doctor** — photo + bio + credentials
4. **Gallery** — photo grid (4 cols desktop, 3 cols mobile) + lightbox. Images use h-64 class (~256px height).
5. **Booking widget** — 4 steps: service → date/time slot → name/phone → confirm. Animated step transitions, success checkmark.
6. **Contact** — address, map embed, phone, hours table
7. **Footer** — basic info + quick links
8. **Lead capture form** — quick inquiry mini-form in standalone section
No payments, no patient records, no patient accounts/auth needed.

### Admin page (receptionist tool — core feature, not optional)
- Login (clinic password)
- Slot grid (today/week view)
- Tap free slot → quick modal (name+phone+service) → books it, source="phone", blocks slot from public widget instantly
- "Block slot" button for holidays/staff leave → writes `slots_blocked`
- Upcoming appointments list
- No animation here — speed over polish, it's a work tool

### Why admin page is core, not bonus
Real problem to solve: clinics currently take phone bookings manually (notebook/memory), causing double-bookings and no tracking. Online booking widget alone doesn't fix this — both online AND phone bookings must write to the same `appointments` table in real time, or you create two conflicting sources of truth. The admin page is what makes receptionists adopt the system (faster than a paper diary) and is the actual sales pitch — "stop double-booking," not "here's a website."

---

## WhatsApp Notifications

- **Provider:** Meta WhatsApp Cloud API (direct, not paid wrappers like Twilio/Gupshup) — free tier covers ~1000 conversations/month, easily enough at this scale.
- **Trigger:** on new appointment (any source) → WhatsApp message to clinic owner's number with booking details.
- **Optional:** patient also gets a WhatsApp booking confirmation (toggle per clinic in config).
- SMS was considered and rejected as default — costs from message #1 (no free tier), needs DLT registration in India. WhatsApp has zero registration hassle and better patient read-rates.
- Position as a value-add bundled into pricing, not a separate line item — cost to you is ~₹0 at this volume.

---

## Design Direction

**Goal:** Clinic owner's reaction should be "wow," not "okay, generic website." But must stay within HTML+Tailwind+vanilla JS — no Three.js/heavy frameworks, to protect timeline.

**How "wow" is achieved (2-3 signature moments, not effects everywhere):**
1. **Hero section** — animated CSS gradient/mesh background, staggered text reveal on load, optional one real 3D element via **Spline** (visual editor, exports embeddable code — no Three.js coding needed). Skip if time runs short.
2. **Scroll-triggered reveals** on every section (Intersection Observer + CSS, ~20 lines reusable JS).
3. **Magnetic/hover button effects** on CTAs (pure CSS+JS).
4. **Booking widget feels premium** — animated step transitions, success checkmark animation on confirm.
5. Clean typography, generous whitespace, one bold accent color — does more for "premium feel" than animation volume.

**Making each clinic feel different (not 10 identical sites):**
- 3-4 theme presets baked into the one codebase, swapped via config: `accentColor`, `font`, `heroLayout` (split/centered/fullbg/journey), `cardStyle` (soft/sharp), `iconStyle` (line/filled).
- Same HTML/JS structure, different config values = visually distinct sites, zero code duplication.

**Photos:**
- Config has a `gallery[]` array of image paths/URLs.
- Start with AI-generated realistic clinic/dental images as placeholders.
- When client provides real photos, just swap the URLs in config — no code/rebuild needed.

---

## Build Order (function before polish)
1. DB schema + API endpoints (appointments CRUD, slot availability check, auth)
2. Booking widget logic — working flow first, no animation
3. Admin page — same API, build right after booking works (this is core, not last)
4. Static public sections (Hero/Services/Doctor/Gallery/Contact) + theme/config system
5. Animation/polish pass — last, so a working booking system ships even if time runs short

---

## Config File
A separate `client-config.example.js` was generated and downloaded already — defines the full per-clinic data contract (identity, theme, hero, services, doctors, gallery, working hours, contact, WhatsApp settings). Every new clinic = copy this file, fill values, drop in assets. No template code is touched per client.

---

## WhatsApp Cloud API — Setup Plan
1. Create Meta Developer account → create an App → add "WhatsApp" product.
2. Get a free test number; verify business number for production later.
3. Generate a **permanent System User access token** (not the 24hr temp token).
4. Create message templates in Meta Business Manager (required — can't free-text first-time numbers):
   - `booking_alert_owner` — "New booking: {{1}} ({{2}}) on {{3}} at {{4}}. Phone: {{5}}"
   - `booking_confirmation_patient` — "Hi {{1}}, your appointment at {{2}} is confirmed for {{3}} at {{4}}."
   - Submit for approval (usually fast for simple utility templates).
5. Send via `POST graph.facebook.com/v19.0/{phone_number_id}/messages` with template name + params, fired from backend right after `appointments` insert.
6. `phone_number_id` + access token live as server env vars — one Meta app serves all clinics, only the `to` number changes per send.

## Admin Page — Layout & Animation Plan

**Desktop:**
| Area | Layout | Animation |
|---|---|---|
| Login | Centered card, clinic logo, password field | Fade-in on load; shake on wrong password |
| Top bar | Logo+name left, date+logout right | Static — speed over polish |
| Main view | Mini week calendar (left) + day's slot grid (right), color-coded: green=free, blue=booked-online, purple=booked-phone, grey=blocked | Grid fades in on day-switch; free slots scale+glow on hover |
| Add booking | Slide-up modal: name, phone, service, "Book" | Slides up ~300ms; success = checkmark animation, auto-close |
| View booking | Small popover: patient details + cancel | Fade+scale in |
| Block slot/day | Toggle → click to mark blocked | Cross-fade to grey with strike pattern |
| Upcoming list | Collapsible side panel, today highlighted | Expand/collapse slide |

**Mobile (same codebase, responsive breakpoints — not a separate build):**
| Area | Mobile behavior |
|---|---|
| Calendar | Single day view, swipe left/right to change day |
| Slots | Vertical list, one tappable row per slot (min 44px tap height) |
| Add booking | Full-screen bottom sheet instead of modal |

**Principle:** color-coded spatial grid > text scanning, for speed. All animation kept under ~300ms and functional, never decorative — this tool gets used dozens of times a day under time pressure.

---

## Spline 3D Hero Element — Plan
1. Start from Spline's free template library (search "dental," "tooth 3D," "abstract floating shapes") rather than modeling from scratch.
2. Recolor material to match clinic's `theme.accentColor`; add ambient lighting for premium look.
3. Add two interactions: slow continuous idle auto-rotation + mouse-parallax tilt.
4. Export via Spline "Code Export" — iframe (simplest) or runtime JS (lighter, more control).
5. Lazy-load after main content; provide a static fallback image for slow connections/SEO. Non-negotiable — 3D embeds are heavy.

---

## DNS / Domain Setup — Plan

**Default path (recommended — zero new server work per client):**
- Every clinic gets a subdomain: `username.yourbrand.com`.
- One-time: wildcard DNS record `*.yourbrand.com → VPS IP` + one wildcard SSL cert (Certbot) covers all subdomains forever.
- New clinic = new `username` row in DB only. Zero hosting work, ever.

**If a client insists on their own domain:**
1. Client adds A record `@ → VPS IP` (+ CNAME `www → @`) at their registrar.
2. Wait for DNS propagation (10 min–24 hrs).
3. On VPS: add one Nginx server block for that domain, run `certbot --nginx -d theirdomain.com`.
4. Backend resolves clinic by request `Host` header (custom domain) instead of username when present.

**Schema addition:** `clinics.custom_domain` (nullable) — only used for clients with their own domain.

---

## Mobile Responsiveness — Pattern
- **Public site:** built mobile-first in Tailwind (default styles = mobile, `md:`/`lg:` prefixes scale up). Sections stack vertically on phone, expand to grid/columns on desktop. 4-step booking widget is naturally one-step-per-screen already.
- **Admin portal:** same codebase, different rendering per breakpoint — see mobile rows in the Admin Page table above. Not a separate build; one responsive layer over the same components.

---

## Slot Booking Modes — Fixed vs Custom
Config gets `slotMode: "fixed" | "custom"`. Same booking widget + admin grid render off this one flag — no forked logic.

- **`fixed`:** slots auto-generated from `workingHours` + `slotDurationMin` (e.g. every 30 min, or `slotDurationMin: 120` for 10-12/12-2/2-4 style blocks). Zero manual entry.
- **`custom`:** clinic defines an explicit slot list for irregular hours, stored as `customSlots` array, e.g.:
  ```js
  customSlots: [
    { label: "10:00 - 12:00" },
    { label: "12:00 - 14:00" },
    { label: "16:00 - 18:00" }
  ]
  ```
  Widget/admin grid read this list directly instead of computing.

**Schema addition:** `clinics.slot_mode` (enum) + `clinics.custom_slots` (JSON, nullable — used only when mode=custom).

---

## AI Image Generation — Full Prompt Library

**Tool:** Gemini app → "Create images" → **Nano Banana Pro** (Gemini 3 Pro Image) model — best realism/resolution available on a Gemini Pro account.

**Universal rules (apply to every image below):**
1. Every Gemini output has a visible logo watermark (bottom-right) + invisible SynthID — crop the watermark corner before using on a client site. Same crop region every time, so this is a one-line step in your asset pipeline, not manual guesswork per image.
2. Replace `[Clinic Name]` and `[accent color]` with that clinic's config values — same prompts, different values, every time.
3. For visual consistency across one clinic's full image set, after generating the first image, prefix later prompts with: *"Same lighting style, color grading, and interior design language as the previous image."*
4. Always include `no people, no text, no watermark, no logos` in interior/exterior shots unless a generic (non-real) person is explicitly wanted (e.g. doctor portrait).
5. Generate at 4K where offered — gives room to crop the watermark without losing quality.

### Asset list (fixed — every clinic needs exactly this set)

| Asset | Filename | Aspect ratio | Prompt |
|---|---|---|---|
| **Logo** | `logo.png` | Square (1:1), transparent bg | "Minimalist modern logo for a dental clinic named '[Clinic Name]'. Clean geometric tooth or smile icon paired with the clinic name in a modern sans-serif font. Flat vector-style design, single accent color [accent color] on a transparent background, no gradients, professional healthcare branding, high resolution." |
| **Hero image** | `hero.jpg` | 16:9 (wide) | "Photorealistic modern dental clinic reception area, warm and welcoming atmosphere, soft natural light through large windows, clean minimalist interior design, [accent color] accent decor elements, comfortable seating, a few plants, wide-angle shot, shot with a 24mm lens, shallow depth of field, 4K, no people, no text, no logos." |
| **Doctor portrait** | `doctor1.jpg` | 4:5 (portrait) | "Photorealistic professional portrait of a friendly dentist in a white coat, warm confident smile, standing in a modern dental clinic interior, soft studio-style lighting, 85mm lens, shot at f/1.8, shallow depth of field, ultra-realistic, 4K, no text overlay. Generic person, not based on any real individual." |
| **Gallery 1 — Waiting area** | `gallery1.jpg` | 4:3 | "Photorealistic dental clinic waiting area, comfortable modern seating, soft ambient lighting, clean walls with subtle [accent color] accents, a few plants and magazines, welcoming atmosphere, wide shot, 4K, no people, no text." |
| **Gallery 2 — Treatment room** | `gallery2.jpg` | 4:3 | "Photorealistic modern dental treatment room, dental chair and equipment visible, clean white and [accent color] color scheme, bright clinical lighting, organized and hygienic, professional medical photography style, 4K, no people, no text." |
| **Gallery 3 — Reception desk** | `gallery3.jpg` | 4:3 | "Photorealistic dental clinic reception desk, modern minimalist design, computer monitor visible, warm lighting, blank accent wall behind desk in [accent color] (no text/signage), 4K, no people." |
| **Gallery 4 — Equipment close-up** | `gallery4.jpg` | 4:3 or 1:1 | "Photorealistic close-up of modern dental equipment and tools neatly arranged on a tray, clean stainless steel and white tones, soft reflective lighting, macro photography style, sharp focus, 4K, no people, no text." |
| **Gallery 5 — Exterior/entrance** | `gallery5.jpg` | 16:9 | "Photorealistic modern dental clinic entrance with glass doors, blank signage area above the door (no text), daytime, clean welcoming storefront, 4K, no people." |
| **Spline fallback (static)** | `hero-3d-fallback.jpg` | 1:1 or 4:3, transparent/white bg | "Abstract 3D render of a glossy tooth or smile shape, smooth glass-like material in white and [accent color], soft studio lighting, floating on a plain white background, high resolution, no text." — used as the lazy-load fallback image for the Spline hero element (see Spline section above). |

**Total per clinic:** 8 images, ~10-15 min to generate + crop, fully covered before any real client photos arrive. When real photos come in later, same filenames get overwritten — zero code or config structure changes.

---

## Current Implementation Status

### ✅ Completed
- **Frontend:** Pure HTML + Tailwind + vanilla JS single-page website
- **Backend:** CodeIgniter 3 PHP REST API with MySQL
- **Hero layouts:** split, centered, fullbg, journey (dark video background)
- **Service cards:** teal-tinted background (`rgba(6, 21, 32, 0.06)`), ripple hover effect (reduced opacity `0.07`)
- **Gallery:** 4-column grid (desktop), 3-column (mobile), `h-64` image height
- **Booking widget:** 4-step flow with animated transitions and success checkmark
- **i18n:** English and Hindi translation support
- **Demo mode:** Works with local config/assets for testing
- **Admin portal:** Login, slot management, appointment booking

### 🔄 In Progress
- WhatsApp Cloud API integration (pending Meta app setup)
- Production deployment to Hostinger VPS

### 📁 File Structure (Actual)
```
dental-website-backend/
├── application/              # CodeIgniter 3 MVC
│   ├── controllers/Admin.php # Main admin + API endpoints
│   ├── models/Admin_model.php
│   ├── config/routes.php     # API routes
│   └── core/MY_Controller.php
├── js/app.js                 # Frontend logic + API calls
└── dental_clinic.sql         # Database schema

dental-website-frontend/
├── index.html                # Main landing page
├── admin.html                # Admin dashboard
├── manage-booking.html       # Patient booking management
├── js/
│   ├── app.js               # Dynamic rendering, animations
│   ├── admin.js             # Admin portal interactions
│   ├── i18n.js              # Translation system
│   └── resolve-config.js    # Clinic config loader
├── i18n/en.json, hi.json    # Translations
└── client-config.example.js   # Per-clinic config template