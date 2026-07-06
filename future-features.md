# Future Features (parked — not Phase 2)

1. **Visit Summary WhatsApp message** — one-click message after Complete Visit with treatment performed, care instructions, follow-up date, thank-you. Depends on §8/§9 in feature-specs.md (build those first; this is just an extra send-template button once they exist).

2. **Loyalty Program** — doctor-configured free consultations / discounts / family benefits / priority appointments. Needs a rules engine + redemption tracking — bigger scope, revisit after core booking/payment flow is stable.

3. **Daily WhatsApp Summary** — automated end-of-day message to doctor: appointment counts, revenue, pending balance. Needs a cron/scheduler on the VPS (none exists yet) — straightforward once §17/§20 reporting queries exist, just needs a scheduled job to call them and format a message.

4. **Patient Birthday Database** — store DOB at booking/visit, auto-send birthday WhatsApp. Requires adding a DOB field somewhere in the booking or visit flow (extra step for patients) — decide where to collect it before building.
