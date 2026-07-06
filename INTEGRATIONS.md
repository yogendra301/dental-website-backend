# Third-Party Integrations Setup Guide

This document describes how to set up each third-party integration used by this dental booking platform.

---

## Google Maps Embed

**What it does:** Shows the exact clinic location in the contact section.

**Steps to set up:**
1. Go to [Google Maps](https://maps.google.com)
2. Type your clinic's address
3. Click **Share** → **Embed a map**
4. Copy the `<iframe>` src URL (the part like `https://www.google.com/maps/embed?pb=...`)
5. Paste it into your clinic's database `config` JSON under `contact.mapEmbedUrl`
   - Example: `"mapEmbedUrl": "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!..."`

> **Note:** Basic embed requires **no API key**. It's free and unlimited.

---

## Google Maps Static API

**What it does:** For a scalable, zoomable static map image (alternative to iframe embed).

**Steps to set up:**
1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project (or select existing)
3. Enable **"Maps Static API"**
4. Go to **Credentials** → **Create API key**
5. Restrict the API key to your domain (e.g., `*.yourclinic.com`)
6. Use the URL format:
   ```
   https://maps.googleapis.com/maps/api/staticmap?center=28.6139,77.2090&zoom=15&size=600x400&key=YOUR_KEY
   ```

---

## WhatsApp Cloud API (Per-Clinic)

**What it does:** Sends booking confirmations, owner alerts, OTP resets, and review requests via WhatsApp — using each clinic's own WhatsApp Business number.

**Architecture:** Every clinic has an independent Meta Business Account + WhatsApp number. Credentials live in `clinics.config.whatsapp` (JSON), never in `.env`. `.env` holds zero clinic-specific WhatsApp values.

### Pre-requisite: dedicated number check
Ask the clinic: do they have a phone number not actively used on the personal WhatsApp app?
- **Yes (dedicated number)** → proceed below.
- **No** → WhatsApp stays disabled for this clinic (`connected: false`). Booking flow falls back to [email/SMS — decide fallback].

### Per-clinic setup (operator does this, not the clinic)
1. Create/access clinic's Meta Business Account at business.facebook.com
2. WhatsApp Manager → Add phone number → verify via OTP (number must be removed from personal WhatsApp app afterward; existing chat history is not preserved)
3. Copy **Phone Number ID** and **WABA ID** from API Setup tab
4. Business Settings → System Users → Add → assign WhatsApp Business Account → Generate permanent token (`whatsapp_business_messaging`, `whatsapp_business_management`, expiry: Never)
5. Create Message Templates (same 9 templates as before — table unchanged) → submit for Meta approval (24-48h)
6. Once approved, paste Phone Number ID + Token + WABA ID into admin panel "Connect WhatsApp" (operator-filled, not clinic-filled)
7. Send test message to confirm before going live

### Required Message Templates

| Template Name | Purpose | Body Example |
|---|---|---|
| `booking_confirmation_patient` | Confirms booking to patient | "Hello {{1}}, your appointment at {{2}} is confirmed for {{3}} at {{4}}. Address: {{5}}. Contact: {{6}}." |
| `booking_alert_owner` | Alerts clinic owner of new booking | "New booking: {{1}} requested {{2}} on {{3}} at {{4}}. Phone: {{5}}" |
| `booking_alert_owner_emergency` | Emergency booking alert | "🚨 EMERGENCY: {{1}} booked {{2}} on {{3}} at {{4}}. Phone: {{5}}" |
| `booking_reschedule_patient` | Reschedule confirmation to patient | "Hi {{1}}, your appointment at {{2}} has been rescheduled to {{3}} at {{4}}." |
| `booking_reschedule_owner` | Reschedule alert to owner | "Reschedule: {{1}} moved their appointment from {{2}} to {{3}}." |
| `booking_cancel_owner` | Cancellation alert to owner | "Cancellation: {{1}} cancelled their {{2}} appointment on {{3}} at {{4}}." |
| `booking_password_reset_otp` | Password reset OTP | "Your OTP for password reset is {{1}}. It is valid for 10 minutes." |
| `booking_review_request` | Google review request | "Hi {{1}}, thank you for visiting {{2}}. We'd love your feedback! Please leave a review: {{3}}" |
| `new_lead_owner` | New lead inquiry alert | "New inquiry: {{1}} - {{2}} is interested in {{3}}. Call back: {{4}}" |

### Storage
```json
"whatsapp": {
  "phone_number_id": "",
  "access_token": "",
  "business_account_id": "",
  "connected": false
}
```
`access_token` encrypted at rest (AES, key in `.env`). `phone_number_id` also duplicated as an indexed DB column for webhook routing (see below).

### Webhook
Single endpoint `/webhooks/whatsapp` for all clinics. Meta payload includes `phone_number_id` → look up clinic by that column → route message/status to correct clinic.

### Templates
Each clinic submits/approves their own 9 templates independently — template approval is per-WABA, not shared.

### `.env` Configuration
The only WhatsApp-related `.env` variable is the encryption key:
```
WHATSAPP_TOKEN_ENCRYPTION_KEY=...
```
This key is used to encrypt/decrypt each clinic's `access_token` stored in the database.

---

## PM2 (Process Manager)

**What it does:** Keeps the Node.js app alive even if the server restarts or the app crashes.

**Setup:**
```bash
# Install PM2 globally
npm install -g pm2

# Start the app
pm2 start backend/server.js --name dental-api

# Save the process list (so it auto-starts on reboot)
pm2 save

# Generate startup script
pm2 startup
```

### Useful PM2 Commands
| Command | Description |
|---|---|
| `pm2 status` | View all running processes |
| `pm2 logs dental-api` | View live logs |
| `pm2 restart dental-api` | Restart the app |
| `pm2 stop dental-api` | Stop the app |
| `pm2 monit` | Real-time CPU/Memory monitoring |

---

## SSL / NGINX

**What it does:** Provides HTTPS for each clinic subdomain (e.g., `clinic-001.yourdomain.com`).

**Setup with Certbot (Let's Encrypt):**
```bash
# Install Certbot and NGINX plugin
sudo apt install certbot python3-certbot-nginx

# Get SSL certificate for your domain
sudo certbot --nginx -d clinic-001.yourdomain.com

# For wildcard (all subdomains)
sudo certbot --nginx -d *.yourdomain.com -d yourdomain.com

# Auto-renewal (Certbot adds a systemd timer automatically)
sudo certbot renew --dry-run
```

**Sample NGINX config:**
```nginx
server {
    listen 80;
    server_name clinic-001.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name clinic-001.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/clinic-001.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/clinic-001.yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://localhost:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## WhatsApp Widget (No Setup Needed)

The floating green WhatsApp button links to:
```
https://wa.me/91XXXXXXXXXX?text=Hello%2C%20I%20want%20to%20book%20an%20appointment
```

- **Mobile:** Opens WhatsApp app directly, pre-fills the message
- **Desktop:** Opens WhatsApp Web in a new tab
- **No server-side integration needed** — it's just a hyperlink

The clinic number is configured in the clinic DB config as `whatsapp_number`.
