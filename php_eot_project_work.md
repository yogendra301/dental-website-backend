# Task: Implement Patients Dashboard Tab & Local Dev Clinic Switcher & Logo Circle Fit

## Task Details

Implemented three requested features/improvements:
1. **Local Dev Clinic Switcher**:
   - Added query parameter `?clinic=username` parsing to `resolve-config.js` to set the session's clinic username easily.
   - Injected a small floating dev badge switcher in `app.js` visible only on localhost/LAN hosts.
2. **Logo Circle Fit**:
   - Replaced `object-contain p-1` image sizing with `object-cover` and removed padding in `admin.html` and `index.html` headers so the clinic logo fills the circular space completely.
3. **Patients Dashboard Tab**:
   - Added a new database-driven Patients Overview dashboard tab (first tab position) that only appears for clinics with the `admin_manage_patients` permission enabled.
   - Shows today's visits count, calendar month visits/new patients stats, monthly revenue vs. pending totals, a top loyal patients table sorted by visits count, and a timeline of recent completed patient activity.

## Files Changed

- `backend/application/models/Admin_model.php`
- `backend/application/controllers/Admin.php`
- `backend/application/config/routes.php`
- `frontend/admin.html`
- `frontend/index.html`
- `frontend/js/admin.js`
- `frontend/js/app.js`
- `frontend/js/resolve-config.js`

## Functions Implemented/Changed

- `getPatientDashboard` (Admin_model.php): Built single queries combining stats, top patients, and recent activity.
- `patient_dashboard` (Admin.php): Implemented endpoint `/api/patients/dashboard` returning dashboard stats.
- `applyPackagePlanUI` (admin.js): Controlled visibility and default tab redirection for the `patients-overview` tab.
- `switchTab` (admin.js): Wired tab panel change and fetched data via `loadPatientsDashboard()`.
- `loadPatientsDashboard` (admin.js): Built JS data-binder and populates stat cards and tables.

---

# Task: Fix Clinic Config Update Bugs (Super Admin Panel + Settings Tab)

## Task Details

Deep audit of the full clinic config update flow — from super admin panel and regular settings tab — covering save, fetch, and website reflection. Found and fixed 5 real bugs:

- **A** `Google_review_link` (capital G) in `saveSettings` — PHP reads `google_review_link` (lowercase), so review link was silently never saved from the basic settings tab.
- **B** `package` missing from `$flatFields` in `update_clinic_full` — package plan changes from super admin were silently dropped.
- **C** `get_clinic_full` not returning `slug` field — `cfgPopulate` reads `d.slug` but response only had `username`; slug input was always blank.
- **D** `cfgCollectContact` sent `contact: { mapEmbedUrl }` only — on save, `array_replace` in backend would wipe any other keys (`phone`, `address`) already in `config.contact`. Fixed to spread existing contact first, then override only `mapEmbedUrl`.
- **E** `get_clinic_full` access_token masking — used `isset($clinic['config']['whatsapp']['access_token'])` without checking that config is an array first (it's already decoded by `_parseClinic`); added `is_array` guard.

## Files Changed

- `backend/application/controllers/Admin.php`
- `frontend/js/admin.js`

## Functions Implemented/Changed

- `update_clinic_full` (Admin.php): Added `package` to `$flatFields`
- `get_clinic_full` (Admin.php): Added `slug` alias, fixed access_token array guard
- `saveSettings` (admin.js): Fixed `google_review_link` key casing
- `cfgCollectContact` (admin.js): Preserved existing `config.contact` keys via spread before overriding `mapEmbedUrl`

---

# Task: Fix Status Undefined Key in getAppointmentsForSlots Query


## Task Details

Resolved a PHP warning (`Undefined array key "status"`) encountered in the slots availability API. The query in `getAppointmentsForSlots()` did not request the `status` column, causing a fatal warning output that broke JSON encoding whenever a clinic had valid active appointments (such as the newly inserted sample patients). Added `status` to the SELECT clause.

## Files Changed

- `backend/application/models/Admin_model.php`

## Functions Implemented/Changed

- `getAppointmentsForSlots` (in `backend/application/models/Admin_model.php`): Appended `status` to the SELECT column list.

---

# Task: Resolve Super Admin Impersonation Conflict

## Task Details

Resolved an auth routing issue where logging in as a super admin and choosing an impersonated clinic would redirect the user back to the login screen. This happened because old clinic credentials in `localStorage` took precedence over newly selected clinic credentials in `sessionStorage` during config resolution, leading to a mismatched authorization token (401 error). Wiped any lingering clinic tokens/usernames from storage at the start of `handleLogin()` and `loginAsClinic()` to prevent config namespace conflicts.

## Files Changed

- `frontend/js/admin.js`

## Functions Implemented/Changed

- `handleLogin`, `loginAsClinic` (in `frontend/js/admin.js`): Added defensive clear steps to reset clinic tokens from both local and session storage when initiating a login.

---

# Task: Enhance Document Print Routine

## Task Details

Optimized the print handler (`window._printDoc`) inside the patient documents modal:
- **Images**: Opens a transient printable window containing only the target image, waits for it to load, triggers the native browser print dialog, and automatically closes the temporary window upon completion or cancellation.
- **PDFs**: Dynamically injects a hidden iframe, targets the PDF URL, focuses it, and invokes `iframe.contentWindow.print()` for inline printing without tab redirection.

## Files Changed

- `frontend/js/admin.js`

## Functions Implemented/Changed

- `_printDoc` (in `frontend/js/admin.js`): Improved print logic flow for PDFs and images.

---

# Task: Implement Document Viewer Endpoint and Stream File Previews

## Task Details

Resolved a backend routing omission where requests to view uploaded patient documents (`/api/documents/view/...`) would result in a `404 override` rendering HTML instead of raw file streams (breaking images and previews). Added a dedicated controller action `view_document` in the backend and registered its GET route. This streams files directly using PHP's `readfile` and matches the correct `Content-Type` safely.

## Files Changed

- `backend/application/config/routes.php`
- `backend/application/controllers/Admin.php`

## Functions Implemented/Changed

- `view_document` (in `backend/application/controllers/Admin.php`): Streams files from the sanitised uploads directory with correct headers.

---

# Task: Support Patient Documents Management from Patient Cards

## Task Details

Added a dedicated "**Docs**" action button on patient cards in the list to manage and view patient-level documents. This opens a premium Documents Modal showing all uploaded files for the selected patient. The clinic can upload new files (supporting drag-and-drop or select), download existing documents, trigger browser printing, or delete records. This is integrated with the existing backend documents API and incorporates patient-level isolation. Any document attached during the completed visit workflow is automatically consolidated and visible here.

## Files Changed

- `frontend/admin.html`
- `frontend/js/admin.js`
- `frontend/js/appointment-card.js`

## Functions Implemented/Changed

- `renderAppointmentCard` (in `frontend/js/appointment-card.js`): Injects the Docs button on patient cards.
- `showPatientDocumentsModal`, `hidePatientDocumentsModal`, `setupPatientDocsModal`, `loadDocsModalList` (in `frontend/js/admin.js`): Implemented the documents modal controller, populate, print, delete, and file upload stream.

---

# Task: Add Complete Action and Remove Price Display from Service Dropdowns

## Task Details

Added support for marking records as complete or updating their treatment/payment details in patient record management mode. Handled displaying the "**Complete**" button both on the patient cards in the history list and inside the patient detail card when `admin_manage_patients` is enabled. Additionally, removed all price display/bracket values from the service required dropdown option labels across all add/edit modals in the admin panel.

## Files Changed

- `frontend/js/admin.js`
- `frontend/js/appointment-card.js`

## Functions Implemented/Changed

- `renderAppointmentCard` (in `frontend/js/appointment-card.js`): Injects the Complete action button in patient record management mode.
- `showDetailModal` (in `frontend/js/admin.js`): Bypasses primary actions hiding in patient record management mode to allow marking complete from the details card.
- `showAddBookingModal`, `showAddPatientRecordModal`, `showEditPatientRecordModal` (in `frontend/js/admin.js`): Stripped brackets and prices from populated service dropdown lists.

---

# Task: Clear Session Storage Completely on Logout

## Task Details

Resolved a session data leakage issue where session-bound values (specifically the dynamic clinic configuration cache `_cc` and other session parameters) persisted in `sessionStorage` after logging out. Refactored the `clearAuthToken()` routine to invoke `sessionStorage.clear()` upon user logout. This ensures that all clinic and super admin session values are wiped from the browser's memory, preventing stale configurations or credentials from leaking across sequential logins.

## Files Changed

- `frontend/js/admin.js`

## Functions Implemented/Changed

- `clearAuthToken` (in `frontend/js/admin.js`): Wipes `sessionStorage` completely and cleans authentication keys from `localStorage`.

---

# Task: Support Patient Record Management (CRUD) under History Tab

## Task Details

Implemented a complete CRUD workflow for managing patient records directly within the History panel, tailored specifically for clinics that do not wish to use the scheduling/booking flow. Added a new permission `admin_manage_patients` under the History tab visibility configuration. When enabled:
- The History panel title transitions to "Patient Records / History", and a new "Add Patient / Record" button is rendered.
- Creating a patient record utilizes the existing booking modal structure but automatically commits the record in `completed` status directly.
- The appointment cards rendered in the history list display "Edit" and "Delete" buttons for full CRUD capabilities.
- The DELETE API is refactored to perform a hard delete of the record (instead of a cancel status update) if the patient management permission is active.
- Added support in both local/live backend APIs and frontend mock interceptors to support the status payload overrides, patching, and hard deletes.

## Files Changed

- `application/controllers/Admin.php`
- `application/models/Admin_model.php`
- `frontend/js/admin.js`
- `frontend/js/appointment-card.js`
- `frontend/js/demo-mock.js`

## Functions Implemented/Changed

- `deleteAppointmentHard` (in `application/models/Admin_model.php`): Hard deletes an appointment record from the database.
- `delete_appointment` (in `application/controllers/Admin.php`): Checks if the `admin_manage_patients` permission is active and executes either a hard delete or a status cancellation.
- `create_admin_appointment` (in `application/controllers/Admin.php`): Support mapping `status` from payload input.
- `showAddPatientRecordModal` (in `frontend/js/admin.js`): Configures and displays the booking modal for record addition.
- `showEditPatientRecordModal` (in `frontend/js/admin.js`): Populates and displays the modal for editing a patient record.
- `submitPhoneBooking` (in `frontend/js/admin.js`): Dispatches POST/PATCH payload dynamically and handles completed status overrides.
- `loadHistoryData` (in `frontend/js/admin.js`): Updates header text and injects "Add Patient / Record" button.
- `renderAppointmentCard` (in `frontend/js/appointment-card.js`): Appends Edit and Delete buttons on cards.
- Mock interceptors (in `frontend/js/demo-mock.js`): Adapted POST and DELETE interceptors to handle the status overrides and hard deletes.

---

# Task: Resolve Multi-Tenant Config Resolution & Admin Visibility Restrictions

## Task Details

Fixed an issue where visibility settings saved for `clinic_003` were not respected when logged in. During local development (`localhost`), the `/api/clinics/resolve` API automatically fell back to resolving the default clinic `clinic_001`, ignoring the logged-in clinic's configuration. Furthermore, `showDashboard()` did not force a re-resolve of the dynamic configuration upon user session transitions, and the default routing logic switched to the hidden `'history'` tab on page load even when booking features were restricted by the clinic package. Updated the backend resolver to support an optional `slug` parameter to bypass local fallbacks, updated the frontend resolver to pass the logged-in session's clinic username, made `showDashboard()` clear the cache and re-resolve config before loading dashboard assets, and updated the default routing logic to dynamically default to the first visible nav tab. Additionally, updated package plan UI restrictions to bypass hiding tabs/elements (such as History and Patient Search) if they are explicitly enabled in the clinic's custom visibility settings.

## Files Changed

- `application/controllers/Admin.php`
- `frontend/js/resolve-config.js`
- `frontend/js/admin.js`

## Functions Implemented/Changed

- `resolve_clinic` (in `application/controllers/Admin.php`): Checks and resolves by `slug` parameter first if provided.
- `resolveClinicConfig` (in `frontend/js/resolve-config.js`): Appends `slug` query parameter using saved session clinic username.
- `showDashboard` (in `frontend/js/admin.js`): Refactored to be `async`, clears the session storage cache (`_cc`), and calls `resolveClinicConfig()` to ensure correct state load on user transition.
- `applyPackagePlanUI` (in `frontend/js/admin.js`): Updated default routing to find and switch to the first visible tab, and bypassed package-based tab and search bar hiding if explicitly allowed in settings.

---

# Task: Fix Dashboard Stats Loading Error

## Task Details

Resolved a stats loading error (`Failed to load stats`) on the admin dashboard load. The date input value was empty initially causing an empty date query parameter (`?start=&end=`) to be sent to the backend endpoint `/api/reports/summary`, resulting in a 400 Bad Request error. Fixed this by passing the resolved `date` variable directly and adding a fallback to `getLocalDateString()` in the `loadDashboardStats` function.

## Files Changed

- `frontend/js/admin.js`

## Functions Implemented/Changed

- `loadDailyData` (updated `loadDashboardStats` call)
- `loadDashboardStats` (updated signature and added date resolution fallback)

---

# Task: Fix Admin Portal Visibility Settings Not Persisting

## Task Details

Fixed an issue where "Admin Portal Visibility" settings would not reflect or persist in the admin dashboard. The backend `get_settings()` API response whitelisted fields, but completely omitted the `visibility_settings` column, causing the frontend settings panel to load empty/undefined values and overwrite configured visibility.

## Files Changed

- `application/controllers/Admin.php`: Added `visibility_settings` to the response array of the `get_settings` method.

## Functions Implemented/Changed

- `application/controllers/Admin.php`: `get_settings` (updated response whitelist)

---

# Task: Fix Frontend Loading & Network Connectivity Errors

## Task Details

Diagnosed and fixed the "Loading..." stuck state on the frontend by wrapping the `resolveClinicConfig` fetch call in a try-catch block. When a network error occurs, it now displays a structured diagnostic error page with the exact failing URL. Reverted the `apiBase` port changes so `client-config.js` correctly targets the backend running on Apache (port 80) without appending the frontend's development port (8002), fixing the 404 issue.

## Files Changed

- `frontend/js/resolve-config.js`: Wrapped `resolveClinicConfig` fetch in try-catch to display an error diagnostic UI.
- `frontend/js/client-config.js`: Validated and reverted `apiBase` back to `window.location.hostname` (port 80).

## Functions Implemented/Changed

- `frontend/js/resolve-config.js`: `resolveClinicConfig` (updated)

---

# Task: Implement Backend API and Admin Dashboard

## Task Details

Implement a multitenant database backend using Node/Express and MySQL for local dental clinics.
Create the administrator reception portal to manage bookings and block time slots.
Connect the dynamic public frontend booking widget to the backend API for availability query and booking submissions.

## Files Changed

- `backend/schema.sql`: Added database table structures (clinics, appointments, slots_blocked) and initial seed data.
- `backend/config/db.js`: Created database connection pool configuration.
- `backend/middleware/auth.js`: Auth middleware verification function.
- `backend/routes/auth.js`: Admin login endpoint `/api/auth/login`.
- `backend/routes/clinics.js`: Public clinic details endpoint `/api/clinics/:slug`.
- `backend/routes/slots.js`: Public availability check and protected slots block/unblock endpoints.
- `backend/routes/appointments.js`: Public booking endpoint, protected admin manual booking and cancellation endpoints.
- `backend/server.js`: Registered auth, clinics, slots, and appointments routes.
- `frontend/admin.html`: Created admin login and dashboard management UI.
- `frontend/js/admin.js`: Implemented admin view state transitions, login handlers, date changes, slots grid rendering, and booking/cancellation actions.
- `frontend/js/app.js`: Added backend integration for the booking widget stepper.

## Functions Implemented/Changed

- `backend/middleware/auth.js`: `module.exports` (verification middleware)
- `backend/routes/auth.js`: `POST /login`
- `backend/routes/clinics.js`: `GET /:slug`
- `backend/routes/slots.js`: `GET /availability`, `POST /block`, `DELETE /block`
- `backend/routes/appointments.js`: `GET /`, `POST /`, `POST /admin`, `DELETE /:id`, `sendWhatsAppNotification`
- `frontend/js/admin.js`: `initTheme`, `adjustColorBrightness`, `handleLogin`, `showLoginError`, `handleLogout`, `showLogin`, `showDashboard`, `renderWeekFastPick`, `loadDailyData`, `renderSlotsGrid`, `renderUpcomingPanel`, `handleSlotClick`, `setupModals`, `showAddBookingModal`, `hideAddBookingModal`, `submitPhoneBooking`, `showDetailModal`, `hideDetailModal`, `cancelAppointment`, `formatTime12`
- `frontend/js/app.js`: `initBookingWidget` (updated to fetch availability from `/api/slots/availability` and POST appointments), `formatTime12` (helper added inside `initBookingWidget`)

---

# Task: Frontend Animation & Polish Pass

## Task Details

Full animation/polish layer applied to the public-facing frontend. Covers hero animations, scroll-triggered reveals, mobile menu, nav scroll state, magnetic CTA effect, count-up stats, service card glow, and booking success animation.

## Files Changed

- `frontend/index.html`: Added comprehensive CSS animation system (hero blobs, scroll reveal, step transitions, checkmark anim, mobile menu CSS). Added mobile hamburger menu. Added `reveal`/`reveal-stagger` classes to all sections. Added hero animated blob mesh. Updated step-4 success icon with animation classes.
- `frontend/js/app.js`: Added `initNavScroll`, `initMobileMenu`, `initScrollReveal`, `animateCountUps`, `initMagneticButtons`. Updated `renderHero` with stagger animation classes, trust stats bar, floating glass badge, and magnetic CTA class. Updated `renderServices` with `service-card` glow class.

## Functions Implemented/Changed

- `frontend/js/app.js`: `initNavScroll`, `initMobileMenu`, `initScrollReveal`, `animateCountUps`, `initMagneticButtons`, `renderHero` (updated), `renderServices` (updated)

---

# Task: Admin Portal Polish & Animations

## Task Details

Added animation and UX polish to the admin portal focused on functional utility (fast, no decorative excess). Covers login card entrance, skeleton loading states, block-mode banner, slot hover glows, appointment card slide-in, modal transitions, custom toggle, and custom scrollbar.

## Files Changed

- `frontend/admin.html`: Added admin CSS system (loginFadeUp, modal scaleIn, slot-card hover glows, skeleton shimmer, apptCardIn, custom toggle, custom scrollbar, block-mode banner CSS). Added `#block-mode-banner` HTML element. Updated toggle to custom CSS toggle. Added `#upcoming-panel-title` ID to appointments panel h3.
- `frontend/js/admin.js`: Wired block-mode toggle to show/hide banner and re-render slot grid. Added skeleton loader placeholders before API fetch in `loadDailyData`. Added error state render. Updated `renderSlotsGrid` with `slot-card`, `slot-available/online/phone` classes and block-mode rose ring indicator. Updated `renderUpcomingPanel` to use `#upcoming-panel-title` ID and add `appt-card` + stagger `animationDelay` on cards.

## Functions Changed

- `frontend/js/admin.js`: `loadDailyData` (skeleton + error state), `renderSlotsGrid` (slot-card classes, block-mode ring), `renderUpcomingPanel` (appt-card, panel title fix)

---

# Task: Project Configuration & Architecture Hardening Pass

## Task Details

Moved hardcoded frontend strings and layout elements to `client-config.js` to align with the single-template multi-tenant architecture. Hardened backend routing, database schema, CORS restrictions, error response formatting, and input normalization.

## Files Changed

- `frontend/js/client-config.js`: Added `slug`, `hero.badgeText`, `hero.floatingBadge`, `hero.stats`, and `doctors[].credentials` properties.
- `frontend/js/app.js`: Updated `API_BASE` to dynamically use `window.location.origin`. Modified `renderHero()` to dynamically render trust stats, badge text, and floating glass badge from configuration. Modified `renderDoctor()` to loop and render doctor credentials dynamically. Updated booking submit and availability fetch requests to pass `clinicConfig.slug` instead of `clinicId`.
- `frontend/js/admin.js`: Updated `API_BASE` to use `window.location.origin`. Removed credentials autofill block from `showLogin()`.
- `backend/schema.sql`: Added unique key constraint `unique_slot` to `appointments` table and `custom_domain` column to `clinics` table.
- `backend/routes/appointments.js`: Handled `ER_DUP_ENTRY` errors in booking and admin booking routes with a 409 status code. Prepend/normalize `+91` prefix on `patient_phone` fields before saving to DB and sending WhatsApp mocks/notifications.
- `backend/routes/slots.js`: Fixed UTC day retrieval via `getUTCDay()` and added closed-day check for both fixed and custom modes.
- `backend/server.js`: Added Express static middleware to serve frontend files, configured CORS origin restrictions, and added startup guard for `JWT_SECRET`.

## Functions Implemented/Changed

- `frontend/js/app.js`: `renderHero` (updated), `renderDoctor` (updated)
- `backend/routes/appointments.js`: `POST /` (updated), `POST /admin` (updated)
- `backend/routes/slots.js`: `GET /availability` (updated)

---

# Task: Add Support for Hero Video Loop with Image Fallback

## Task Details

Added configuration option `heroVideo` to `client-config.js` to allow a video loop background. Modified the split layout renderer in `renderHero()` to check for `heroVideo` and dynamically render either a video player (with loop, autoplay, muted parameters and image fallback) or standard image fallback.

## Files Changed

- `frontend/js/client-config.js`: Added `heroVideo` parameter inside the `hero` section.
- `frontend/js/app.js`: Updated the split layout rendering in `renderHero()` to conditionally output a `<video>` tag or `<img>` tag depending on `clinicConfig.hero.heroVideo` value.

## Functions Implemented/Changed

- `frontend/js/app.js`: `renderHero` (updated)

---

# Task: Add Twinkling Sparkle Canvas and Fix Count-up Decimal Bug

## Task Details

Implemented a twinkling, floating canvas sparkle overlay (light-reflective star particles) on the Hero section. Fixed the stats animation count-up bug that was dropping decimal values (e.g., displaying "4" instead of "4.9") by updating parsing and formatting logic.

## Files Changed

- `frontend/index.html`: Added the `#sparkle-canvas` element inside `.hero-mesh` and configured its opacity styling.
- `frontend/js/app.js`: Added the canvas-based `initSparkles()` animation function and called it on `DOMContentLoaded`. Updated `animateCountUps()` to recognize and retain decimal formatting during the animation ticker.

## Functions Implemented/Changed

- `frontend/js/app.js`: `initSparkles` (new), `animateCountUps` (updated)

---

# Task: Implement Journey Layout option for Hero Section

## Task Details

Added support for a new "journey" layout option for the Hero section. Configured steps dynamically from config, added custom SVG line trail rendering that loops through steps sequentially with an active styling toggle, and parameterized `statsHTML()` to support both light and dark backgrounds depending on the layout.

## Files Changed

- `frontend/js/client-config.js`: Updated `heroLayout` to `"journey"`, corrected `heroVideo` path to `"/assets/hero-loop.mp4"`, and added `journeySteps` array.
- `frontend/index.html`: Added styling for `.hero-journey-video`, `.hero-journey-overlay`, active `.journey-card` styles, and trail SVG animation rules. Removed `relative` class from `#hero-container` to allow background videos and fullbg images to span edge-to-edge dynamically. Deleted broken CSS-based `journeyTrailFlow` animation keyframe with unitless custom property math.
- `frontend/js/app.js`: Parameterized `statsHTML` helper, added render branch for `journey` layout under `renderHero()`, and implemented the `initJourneyTrail()` function. Added explicit `vid.play()` for dynamically injected `<video>` tags to bypass browser `innerHTML` autoplay blocking. Added full-height styling configuration for the hero element in `journey` layout, double-requestAnimationFrame layout measuring guard, and dynamic style sheet keyframe injection for exact-pixel dash-offset animation values.

## Functions Implemented/Changed

- `frontend/js/app.js`: `renderHero` (updated), `initJourneyTrail` (updated)

# Task: Dental Clinic System - Phase 2 Backend & Public Booking Widget

## Task Details

Implemented the Phase 2 backend architecture and public site features:

- Modified appointments delete route to soft cancel status and check availability accordingly.
- Added duplicate date booking protection on backend and frontend (click-disable CTA).
- Integrated emergency booking check (with red styling cards + problem notes) and owner/patient WhatsApp notifications.
- Created public manage-booking portal (`manage-booking.html`) with reschedule/cancel public APIs.
- Added repeat patient recognition endpoints and blur input dynamic banner greeting on Step 3 of the booking widget.
- Created leads capture endpoints and integrated inquiry mini-form onto the main landing page.
- Created patient documents upload endpoint with auth-restricted serving of `/uploads/` folder path.

## Files Changed

- `backend/routes/appointments.js`: Modified duplicate check in public POST booking. Updated admin manual booking POST route for walk-ins. Updated delete route to soft cancel status. Added GET lookup, PATCH reschedule, PATCH cancel, PATCH complete, and POST request-review endpoints.
- `backend/routes/slots.js`: Excluded cancelled appointments from slot grid availability.
- `backend/routes/patients.js`: Added public repeat patient lookup and admin patient search queries.
- `backend/routes/reports.js`: Added report summary endpoint for dashboard statistics and analytics charts.
- `backend/routes/leads.js`: Created public POST leads capture and admin GET/PATCH pipeline routes.
- `backend/routes/documents.js`: Created document uploads and list retrieval endpoints using Multer.
- `backend/middleware/auth.js`: Added role property parsing and created requireRole wrapper function.
- `backend/server.js`: Imported authMiddleware and mounted new patients, reports, leads, documents routes and secure static uploads folder view.
- `backend/package.json`: Added `multer` dependency.
- `frontend/manage-booking.html`: Added public booking rescheduling and cancellation panel view.
- `frontend/index.html`: Injected emergency toggle checkbox, problem-note textarea, welcome back banner, and pricing callback request form.
- `frontend/js/app.js`: Added phone blur listener, custom payload parameters, reset states, and callback form event listener.
- `frontend/admin.html`: Added remember-me checkbox, forgot password modal overlay, complete visit form modal, and google review request modal.
- `frontend/js/admin.js`: Implemented centralized token storage helpers, remember-me retention logic, forgot password verification, rendering of status badges & quick action links, sorting of emergency cards to the top of the upcoming list, and complete visit submit workflow.

## Functions Implemented/Changed

- `backend/routes/appointments.js`: `POST /`, `POST /admin`, `DELETE /:id` (updated); `GET /lookup`, `PATCH /:id/reschedule`, `PATCH /:id/cancel`, `PATCH /:id/complete`, `POST /:id/request-review` (new)
- `backend/routes/slots.js`: `GET /availability` (updated)
- `backend/routes/patients.js`: `GET /lookup`, `GET /search` (new)
- `backend/routes/reports.js`: `GET /summary` (new)
- `backend/routes/leads.js`: `POST /`, `GET /`, `PATCH /:id` (new)
- `backend/routes/documents.js`: `POST /`, `GET /`, `DELETE /:id` (new)
- `backend/middleware/auth.js`: `module.exports` (updated), `requireRole` (new)
- `frontend/js/app.js`: `initBookingWidget` (updated), `initLeadForm` (new)
- `frontend/js/admin.js`: `getAuthToken`, `getClinicSlug`, `setAuthToken`, `clearAuthToken`, `initForgotModal`, `renderAppointmentCard`, `showCompleteModal`, `hideCompleteModal`, `submitCompleteVisit`, `showReviewRequestModal` (new); `showLogin`, `handleLogin`, `handleLogout`, `loadDailyData`, `handleSlotClick`, `showAddBookingModal`, `submitPhoneBooking`, `cancelAppointment` (updated)

---

# Task: Implement Remaining Phase 2 Features

## Task Details

Implemented the remaining features of Phase 2, which includes adding horizontal sub-tabs, global patient search, document upload drawer, dashboard stats grid, walk-in quick add booking, manual lead capturing form, and reports analysis charts to the admin dashboard. Created doctor portal login and read-only clinical dashboard views. Implemented language translation loader switch for English/Hindi. Created interactive before-after slider rendering on the landing page gallery.

## Files Changed

- `backend/routes/appointments.js`: Updated GET `/` API handler to support filtering by `phone`.
- `frontend/admin.html`: Restructured layout for navigation tabs (Dashboard, History, Follow-ups, Leads, Reports), added global search bar, language toggle button, dashboard stats cards, profile slide-out, walk-in quick booking modal, manual lead modal, and Chart.js library imports.
- `frontend/js/admin.js`: Implemented navigation tab togglers, stats fetch loaders, debounced global search dropdown, profile slide-out drawer rendering, document fetchers, upload triggers and document delete actions, quick-range history filters, followup listing, manual lead inputs, leads pipeline columns, Chart.js report integrations, and float walk-in bookers.
- `frontend/doctor.html`: Created doctor PIN login view with slug fields and click triggers.
- `frontend/doctor-dashboard.html`: Created read-only clinical panel layout displaying Today's visits, Upcoming, Pending, and Completed appointments.
- `frontend/i18n/en.json`: English language translation key mappings.
- `frontend/i18n/hi.json`: Hindi language translation key mappings.
- `frontend/js/i18n.js`: Created client-side translation switch scripting.
- `frontend/index.html`: Added language switch button next to public header booking links.
- `frontend/js/app.js`: Integrated translation switches and updated before-after dynamic gallery layout slider clipping handlers.
- `frontend/js/client-config.js`: Swapped gallery paths array with single/before-after object formats.

## Functions Implemented/Changed

- `backend/routes/appointments.js`: `GET /` (updated to support `phone` query param)
- `frontend/js/admin.js`: `initTabs`, `switchTab`, `loadDashboardStats`, `initGlobalSearch`, `showPatientProfile`, `loadPatientDocuments`, `setupDocumentUpload`, `initHistoryTab`, `loadHistoryData`, `loadFollowupsData`, `initManualLeadModal`, `loadLeadsData`, `initReportsTab`, `loadReportsData`, `renderServiceRevenueChart`, `renderPeakHoursChart`, `initWalkinModal` (new); `loadDailyData`, `renderAppointmentCard` (updated)
- `frontend/js/app.js`: `renderGallery` (updated to support before-after sliders); `DOMContentLoaded` hook (updated to call `initI18n`)
- `frontend/js/i18n.js`: `initI18n`, `loadTranslations`, `applyTranslations`, `setupLangToggles`, `updateToggleUI` (new)

---

# Task: Update Services Display to Static Pre-made Image Cards

## Task Details

Replaced the dynamically generated text-based service cards with pre-made static image cards (`service_1.png` to `service_4.png`). Updated the layout grid from 3 columns to 2 columns to properly display the 4 cards in a 2x2 grid layout. Kept click-to-book functionality by triggering the navigation booking button.

## Files Changed

- `frontend/index.html`: Updated `#services-grid` CSS classes to use a 2-column grid (`md:grid-cols-2`) and removed `lg:grid-cols-3`.
- `frontend/js/app.js`: Updated `renderServices` function to loop through indices 1 to 4 and render static `img` tags instead of dynamic configurations.

## Functions Implemented/Changed

- `frontend/js/app.js`: `renderServices` (updated)

---

# Task: Configure Hero Section Journey Step Card Images

## Task Details

Updated the identity configuration parameters in client-config.js to load new pre-made step icons (`hero_card1.png` to `hero_card4.png`) within the interactive hero journey layout.

## Files Changed

- `frontend/js/client-config.js`: Updated the `image` values inside `journeySteps` from `null` to the corresponding static image file paths `/assets/hero_card1.png` to `/assets/hero_card4.png`.

## Functions Implemented/Changed

- None (pure config data updates)

---

# Task: Adjust Journey Card CSS Sizes

## Task Details

Enlarged the size and aspect ratio of step cards inside the hero section journey grid. Modified card borders and paddings to yield a cleaner landscape rectangular layout, ensuring that pre-made text/photo details on the step card images are fully visible and readable.

## Files Changed

- `frontend/index.html`: Modified `.journey-card` and `.journey-card-icon` dimensions and properties inside the landing page styling declaration block.

## Functions Implemented/Changed

- None (pure CSS design adjustments)

---

# Task: Optimize Hero Section Vertical Spacing & Compactness

## Task Details

Removed large generic padding classes (`py-16`, `sm:py-24`, `lg:py-32`) from the `#hero` element and replaced them with compact padding constraints (`pt-20`, `pb-8`) for the journey layout. Decreased `#journey-track` internal layout spacing from `space-y-5` to `space-y-3` and adjusted `.journey-card` container styles. These changes shift the hero content upwards to prevent lower cards from spilling off-screen.

## Files Changed

- `frontend/index.html`: Reduced `.journey-card` paddings and `.journey-card-icon` bounding dimensions.
- `frontend/js/app.js`: Cleaned layout classes on `#hero` container and adjusted layout spacing on `#journey-track`.

## Functions Implemented/Changed

- None (pure UI layout tweaks)

---

# Task: Add Admin Login Link to Navigation Menu

## Task Details

Added "Admin Login" links directly to the landing page desktop and mobile header navigation menus, pointing users to `/admin.html`.

## Files Changed

- `frontend/index.html`: Appended `Admin Login` navigation links next to `Manage Booking` targets.

## Functions Implemented/Changed

- None (pure HTML updates)

---

# Task: Implement Multi-Tenant Foundation & Dynamic Config Resolution

## Task Details

Replaced hardcoded clinic configuration file references with a dynamic `/api/clinics/resolve` endpoint mapping DNS hostname to database-configured settings. Handled automatic configuration caching, auto-filled and locked slug fields on admin/doctor login portals, sanitized document upload target directory naming conventions, adjusted token expiration times, and implemented auto-resolution fallbacks for walk-in appointment conflicts.

## Files Changed

- `backend/routes/clinics.js`: Added `/resolve` API endpoint and `PATCH /settings` route.
- `backend/routes/auth.js`: Extended token expiration time to 12h.
- `backend/routes/appointments.js`: Added walk-in conflict slot check loops.
- `backend/routes/documents.js`: Digitized patient phone upload pathing configurations.
- `backend/schema.sql`: Appended table structure migrations details.
- `frontend/js/resolve-config.js`: Added configuration resolver script helper.
- `frontend/js/app.js`: Integrated async config loading and resolved settings on DOM ready.
- `frontend/js/admin.js`: Refactored configuration lookup and locked input slug fields.
- `frontend/manage-booking.html`: Refactored hardcoded slug configuration variables.
- `frontend/doctor.html`: Integrated configuration resolver and locked credentials.
- `frontend/doctor-dashboard.html`: Integrated async resolve utilities.

## Functions Implemented/Changed

- `backend/routes/clinics.js`: `sanitizeClinic`, `GET /resolve`, `PATCH /settings` (new)
- `backend/routes/appointments.js`: `POST /admin` (updated for walk-in slots fallback)
- `backend/js/resolve-config.js`: `resolveClinicConfig` (new)
- `frontend/js/app.js`: `DOMContentLoaded` callback (updated for async config initialization)
- `frontend/js/admin.js`: `DOMContentLoaded` callback, `showLogin` (updated)
- `frontend/doctor.html`: `DOMContentLoaded` callback (updated)
- `frontend/doctor-dashboard.html`: `DOMContentLoaded` callback (updated)

---

# Task: Implement Admin Portal Fixes & Security Hardening

## Task Details

Resolved top global search database query failures, styled date pickers timezone-safely using local offset formatting, corrected HTML tag mismatches in header layouts, secured availability endpoints to prevent public patient names leakage by verifying admin JWT contexts, and implemented custom walk-in service selections and contextual pre-filled WhatsApp links.

## Files Changed

- `backend/routes/slots.js`: Hardened availability endpoints and fallback queries.
- `backend/routes/patients.js`: Added timezone-safe and only_full_group_by compatibility groups.
- `frontend/admin.html`: Corrected nested anchor tag closures.
- `frontend/js/admin.js`: Added local offset time conversion helpers and updated all ISO time string references.

## Functions Implemented/Changed

- `backend/routes/slots.js`: `GET /availability` (updated for auth checks and fallback queries)
- `backend/routes/patients.js`: `GET /search` (updated GROUP BY constraints)
- `frontend/js/admin.js`: `getLocalDateString` (new), `initGlobalSearch`, `loadHistoryData`, `initReportsTab`, `initWalkinModal` (updated)

---

# Task: Extra Fixes (Batch A-G)

## Task Details

Enforced consistent role gating backend-wide, extracted formatTime12 and renderAppointmentCard into a shared frontend module `appointment-card.js` for admin and doctor dashboards, added `no_show` status endpoint and frontend action, simplified walk-in slots generation logic by removing avoidance loop, swapped completed/confirmed status colors as per spec, and wired dynamic `defaultLanguage` from clinic config.

## Files Changed

- `backend/routes/appointments.js`: Added role gating (`requireRole('admin')`) to GET /history, GET /followups, PATCH /:id/followup-done, and GET / (to `requireRole('admin', 'doctor')`). Added PATCH /:id/no-show route. Removed walk-in slots search/avoidance loop.
- `backend/routes/slots.js`: Enforced `requireRole('admin')` on POST /block, DELETE /block.
- `backend/routes/leads.js`: Enforced `requireRole('admin')` on GET /, PATCH /:id.
- `backend/routes/documents.js`: Enforced `requireRole('admin')` on POST /, GET /, DELETE /:id.
- `backend/routes/gallery.js`: Enforced `requireRole('admin')` on POST /, POST /before-after, DELETE /:id.
- `backend/routes/clinics.js`: Enforced `requireRole('admin')` on PATCH /settings. Added local network IP range matching (192.168.x.x, 10.x.x.x, 172.x.x.x, \*.local) to the `/resolve` endpoint for local dev fallback.
- `backend/routes/patients.js`: Enforced `requireRole('admin')` on GET /search.
- `frontend/js/appointment-card.js`: [NEW] Shared frontend rendering module for appointment cards.
- `frontend/js/app.js`: Passed resolved defaultLanguage to initI18n call on startup. Added dynamic fallback and error handling to `renderServices()`. Fixed `applyVisibilitySettings()` to default components to visible unless explicitly set to `false`.
- `frontend/js/admin.js`: Removed duplicate renderer/formatter functions, implemented window hooks, passed default language, wired action refresh handlers. Cleared dynamic config session cache on settings save.

## Functions Implemented/Changed

- `backend/routes/appointments.js`: `PATCH /:id/no-show` (new), `POST /admin` (updated walk-in logic)
- `backend/routes/clinics.js`: `GET /resolve` (updated dev fallback hosts matching logic)
- `frontend/js/appointment-card.js`: `formatTime12` (new shared), `renderAppointmentCard` (new shared)
- `frontend/js/i18n.js`: `initI18n` (updated signature and fallback order)
- `frontend/js/app.js`: `renderServices` (updated), `applyVisibilitySettings` (fixed defaults logic)
- `frontend/js/admin.js`: `saveSettings` (added cache clearance)

## SQL to Run Manually

```sql
-- Update config JSON to seed defaultLanguage:
UPDATE clinics SET config = JSON_SET(config, '$.theme.defaultLanguage', 'en') WHERE slug = 'clinic_001';

-- Update services config to include the fourth service (Cosmetic Dentistry):
UPDATE clinics SET services = '[{"id": "svc_1", "name": "General Checkup", "durationMin": 30, "priceDisplay": "₹500"}, {"id": "svc_2", "name": "Teeth Cleaning", "durationMin": 45, "priceDisplay": "₹1200"}, {"id": "svc_3", "name": "Root Canal", "durationMin": 60, "priceDisplay": "₹4000"}, {"id": "svc_4", "name": "Cosmetic Dentistry", "durationMin": 75, "priceDisplay": "₹6500"}]' WHERE slug = 'clinic_001';
```

---

# Task: Audit Fix Batch — Reports 1, 2, 3 (P0/P1/P2)

## Task Details

Fixed all critical crashes, security gaps, and pattern violations identified in the 3-report deep audit.
P0: 3 crash fixes. P1: role-based access control enforced + DB migration SQL provided. P2: pattern violations resolved.

## Files Changed

- `backend/routes/appointments.js`: Added requireRole('admin') to write routes; removed local sendWhatsAppNotification, now imports from shared util.
- `backend/routes/auth.js`: Added import for shared WA util; replaced 40-line inline https block with single sendWhatsAppNotification() call.
- `backend/utils/whatsapp.js`: [NEW] Shared WhatsApp notification utility extracted from appointments.js — single source of truth.
- `frontend/js/admin.js`: Guarded initReportsTab() with null checks; removed admin123 demo autofill from showLogin().
- `frontend/admin.html`: Added #modal-problem-note and #modal-is-emergency toggle to add-booking-modal; added full #complete-visit-modal and #review-request-modal HTML.
- `frontend/js/app.js`: Fixed renderServices() to use clinicConfig.services (removed hardcoded i=1..4 loop); added populateLeadServiceDropdown().
- `frontend/index.html`: Removed hardcoded static options from #lead-service select — now populated from config at runtime.
- `frontend/manage-booking.html`: Replaced inline fetch+resolve with shared resolveClinicConfig() from resolve-config.js; changed script tag to type="module".
- `frontend/doctor-dashboard.html`: Added flex-wrap to badge container in createDoctorCard() — same overflow fix as admin.js.

## Functions Implemented/Changed

- `backend/middleware/auth.js`: `requireRole` — now applied to 4 write routes (was defined but unused)
- `backend/utils/whatsapp.js`: `sendWhatsAppNotification` (new shared util)
- `backend/routes/appointments.js`: `POST /admin`, `DELETE /:id`, `PATCH /:id/complete`, `POST /:id/request-review` — all now require admin role
- `frontend/js/admin.js`: `initReportsTab` (null-guarded), `showLogin` (removed demo autofill)
- `frontend/js/app.js`: `renderServices` (config-driven), `populateLeadServiceDropdown` (new)

## SQL to Run Manually (P1-B — missing DB columns/table)

```sql
-- Run on dental_clinic database:
ALTER TABLE clinics
    ADD COLUMN IF NOT EXISTS config JSON DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS visibility_settings JSON DEFAULT NULL;

CREATE TABLE IF NOT EXISTS gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    type ENUM('single','before_after') NOT NULL DEFAULT 'single',
    image_url VARCHAR(500) NOT NULL,
    before_url VARCHAR(500) DEFAULT NULL,
    after_url VARCHAR(500) DEFAULT NULL,
    caption VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE
);
```

## .env Key to Add (P1-C)

```
DEV_CLINIC_SLUG=clinic_001
```

---

# Task: Login UI Controls and Walk-in Widget Animation Hotfix

## Task Details

Restored "Remember Me" checkbox and "Forgot Password?" button to the admin login card. Added the `#forgot-password-modal` OTP reset modal markup back to the admin HTML. Fixed the walk-in patient quick booking modal where clicking the float button "did nothing" by adding the dynamic CSS translation toggling (`translate-y-full` removal/addition) to slide the modal container into view.

## Files Changed

- `frontend/admin.html`: Restored "Remember Me" checkbox, "Forgot Password?" trigger, and full `#forgot-password-modal` OTP HTML template markup.
- `frontend/js/admin.js`: Added translate-y-full animations and timing delays to slide in/out the walk-in quick booking modal.

## Functions Implemented/Changed

- `frontend/js/admin.js`: `initWalkinModal` (updated with translation animations)

---

# Task: Vercel Static Demo Mode

## Task Details

Added a `demoMode` flag to `client-config.js`. When set to `true`, the frontend skips all backend API calls and runs fully in-browser using a `localStorage`-based mock database. A global `window.fetch` interceptor (`demo-mock.js`) handles all `/api/` endpoints, simulating the backend with pre-seeded data for appointments, slots, gallery, leads, documents, and clinic settings. A `vercel.json` config was added to deploy the `frontend/` directory as a pure static site.

## Files Changed

- `frontend/js/client-config.js`: Added `demoMode: false` flag.
- `frontend/js/resolve-config.js`: Added early-exit demo mode check — lazy-imports demo-mock.js and returns a locally-built config when `demoMode` is `true`.
- `frontend/js/demo-mock.js`: [NEW] Full `window.fetch` interceptor with `localStorage` mock DB covering all API routes.
- `vercel.json`: [NEW] Vercel static site deploy config pointing to `frontend/` directory.

## Functions Implemented/Changed

- `frontend/js/resolve-config.js`: `resolveClinicConfig` (added demoMode branch)
- `frontend/js/demo-mock.js`: `enableDemoMode`, `initLocalStorage` (new)

---

# Task: Fix Frontend Doctor Section Render Crash

## Task Details

Resolved a ReferenceError crash (`ReferenceError: doc is not defined`) in `renderDoctor()` inside `app.js` that was preventing the rendering of all sections below the Hero section (Services, Doctor, Gallery, Booking, Contact & Working Hours).

## Files Changed

- `frontend/js/app.js`: Added the missing `const doc = docs[0]` definition inside `renderDoctor()`.

## Functions Implemented/Changed

- `frontend/js/app.js`: `renderDoctor` (updated)

---

# Task: Restore Missing Admin Portal Layout, Modals, and Prioritize Gallery Before/After Images

## Task Details

Rebuilt the entire admin dashboard HTML structure to restore all missing sections expected by the JavaScript code (including the tab navigation bar, global search bar and dropdown list, patient profile slideout drawer, stats bar, manual lead modal, and all tab panels). Fixed walk-in time slot wrap calculations to prevent invalid formatting (e.g. `14:60`) and implemented dynamic collision checking for walk-in slots on both the backend and frontend mock interceptor to resolve database constraint issues. Prioritized Before/After gallery images in the API backend routes, mock data interceptor, and frontend rendering arrays.

## Files Changed

- `frontend/admin.html`: Restored the tab navigation header, global search inputs/container, stats cards, all tab panels, slideout patient profile drawer, Chart.js library script, and the manual lead modal. Changed `slideout-doc-upload` input ID to `slideout-doc-file`.
- `frontend/js/admin.js`: Fixed rounding logic of current time in `initWalkinModal` to handle minutes wrap-around correctly.
- `backend/routes/gallery.js`: Modified the GET `/` database query to sort and prioritize items where `type` is `before-after` or `before_after`.
- `frontend/js/demo-mock.js`: Updated the gallery GET interceptor to sort mock data, and implemented a slot collision checking loop for `/api/appointments/admin` POST endpoint.
- `frontend/js/app.js`: Updated `renderGallery` in both try and catch blocks to sort and prioritize `before-after` type items.

## Functions Implemented/Changed

- `frontend/js/admin.js`: `initWalkinModal` (updated)
- `backend/routes/gallery.js`: GET `/` (updated)
- `frontend/js/demo-mock.js`: GET `/api/gallery` interceptor (updated), POST `/api/appointments/admin` interceptor (updated)
- `frontend/js/app.js`: `renderGallery` (updated)

---

# Task: Secure WhatsApp Connection Routes for Multi-Tenant Data Isolation

## Task Details

Secured the WhatsApp integration credentials endpoints (`/whatsapp/connect` and `/whatsapp/disconnect`) to ensure that an authenticated admin from one clinic cannot alter or overwrite the credentials of another clinic. Added an authorization guard checking that the clinic `id` from the URL parameters matches the `clinicId` extracted from the JWT token.

## Files Changed

- `backend/routes/clinics.js`: Added tenant matches check between `req.params.id` and `req.clinicId` in `POST /:id/whatsapp/connect` and `POST /:id/whatsapp/disconnect`.

## Functions Implemented/Changed

- `backend/routes/clinics.js`: `POST /:id/whatsapp/connect` (updated), `POST /:id/whatsapp/disconnect` (updated)

---

# Task: Implement Google Maps Dynamic Embed URL Configurations

## Task Details

Added support for dynamically configurable Google Maps embed iframe sources inside the admin settings tab and public frontend landing page. Enforced domain checks on the backend `PATCH /settings` endpoint to only allow valid Google Maps embed URLs (`https://www.google.com/maps/embed` or `https://maps.google.com`) to mitigate XSS vulnerabilities. Also added full state support to the frontend local demo mock interceptor.

## Files Changed

- `backend/routes/clinics.js`: Processed `map_embed_url` input parameters, validated domains, merged it into `config.contact.mapEmbedUrl`, and mapped parameters to `contact_map_url` in the database.
- `frontend/admin.html`: Added Google Maps Embed URL input control element inside the settings tab template layout.
- `frontend/js/admin.js`: Pre-filled `#settings-map-embed` on tab load and compiled `map_embed_url` inside the update request body.
- `frontend/js/app.js`: Refactored public landing page map frame source assignment with fallback rendering elements.
- `frontend/js/demo-mock.js`: Updated seeded local settings and resolve/patch interceptor endpoint schemas with `map_embed_url` support.

## Functions Implemented/Changed

- `backend/routes/clinics.js`: `PATCH /settings` (updated)
- `frontend/js/admin.js`: `loadSettingsTab` (updated), `saveSettings` (updated)
- `frontend/js/app.js`: `renderContactAndHours` (updated)
- `frontend/js/demo-mock.js`: `initLocalStorage` (updated), GET `/api/clinics/resolve` interceptor (updated), PATCH `/api/clinics/settings` interceptor (updated)

---

# Task: Fix Local Network Access API URL

## Task Details

Updated frontend API base URL to dynamically resolve using current hostname instead of localhost. Fixes the blank page when accessing the site via local network IP.

## Files Changed

- `js/client-config.js`: Updated `apiBase` to use dynamic hostname.

## Functions Implemented/Changed

- None

---

# Task: Dynamic Browser Tab Favicon System

## Task Details

Set the browser tab icon (favicon) dynamically using the logo URL from the clinic config. This is applied on the main frontend, admin dashboard, manage booking portal, doctor login, and doctor dashboard pages.

## Files Changed

- `frontend/js/app.js`: Updated theme initialization to set the favicon dynamically.
- `frontend/js/admin.js`: Updated theme initialization to set the favicon dynamically.
- `frontend/manage-booking.html`: Added favicon setter in the page load function.
- `frontend/doctor.html`: Added favicon setter in the page load function.
- `frontend/doctor-dashboard.html`: Added favicon setter in the page load function.

## Functions Implemented/Changed

- `frontend/js/app.js`: `initTheme` (updated)
- `frontend/js/admin.js`: `initTheme` (updated)
- `frontend/manage-booking.html`: `init` (updated)
- `frontend/doctor.html`: `DOMContentLoaded` event handler (updated)
- `frontend/doctor-dashboard.html`: `DOMContentLoaded` event handler (updated)

---

# Task: Production Readiness & Deployment Hardening

## Task Details

Updated configuration handling for seamless Local and Production deployments avoiding hardcoded HTTP protocols and hostnames, which prevents Mixed Content errors on production HTTPS servers. The application continues to support dynamic host/port detection for local network testing.

## Files Changed

- `frontend/js/client-config.js`: Changed `apiBase` to use `window.location.protocol` and `window.location.hostname` to adapt to both local and production environments.
- `frontend/manage-booking.html`: Removed hardcoded `http://localhost` fallback and replaced it with dynamic origin fallback.
- `backend/application/config/config.php`: Emptied `$config['base_url']` to allow CodeIgniter to auto-detect base URL dynamically.

## Functions Implemented/Changed

- `frontend/manage-booking.html`: `init` (updated API_BASE fallback)

---

# Task: Prescription & Document Upload on Visit Completion

## Task Details

Added an optional prescription/document attachment input directly inside the Complete Visit modal. On mobile devices, this opens camera or lets user pick from gallery. Automatically saves the file to patient documents upon completion.

## Files Changed

- `frontend/admin.html`: Added prescription attachment input UI in complete-visit-modal.
- `frontend/js/admin.js`: Added file change display logic, modal state reset on open, and document upload call in `submitCompleteVisit`.

## Functions Implemented/Changed

- `frontend/js/admin.js`: `setupModalEvents` (updated), `openCompleteModal` (updated), `submitCompleteVisit` (updated)

---

# Task: Fix API Route Endpoint for No Show Action

## Task Details

Fixed a bug where marking an appointment as "No Show" failed because the fetch call was hitting the wrong host. Changed the hardcoded `API_BASE` inside the shared appointment card script to resolve the backend URL dynamically.

## Files Changed

- `frontend/js/appointment-card.js`: Replaced the hardcoded `API_BASE` with `getApiBase()` helper to resolve dynamic server location properly.

## Functions Implemented/Changed

- `frontend/js/appointment-card.js`: `getApiBase` (new helper), `renderAppointmentCard` (updated)

---

# Task: Add Complete and No Show Actions to Booking Detail Modal

## Task Details

Integrated Complete and No Show quick action buttons directly inside the booking details modal popup. Managed layout and styling dynamically depending on whether the booking is active, past, or a future event.

## Files Changed

- `frontend/admin.html`: Restructured the modal footer to support a multi-row grid layout for primary/secondary action buttons.
- `frontend/js/admin.js`: Wired the click events and added styling logic in `showDetailModal` to dynamically align, hide, and size buttons based on appointment date/status.

## Functions Implemented/Changed

- `frontend/js/admin.js`: `setupModalEvents` (updated), `showDetailModal` (updated)

---

# Task: Dashboard Mobile Navigation and Statistics Upgrades

## Task Details

Replaced the scrolling horizontal tab bar on mobile devices with a custom animated dropdown selector menu matching the main website style for navigating between dashboard panels. Removed the redundant Settings navigation tab. Renamed the "Visits Today" metric to "Appointments Today" and added a scroll-to functionality so users can jump straight to the upcoming visits list by clicking the statistic card.

## Files Changed

- `frontend/admin.html`: Hid horizontal tabs on mobile, added a custom toggleable dropdown menu (`#mobile-nav-dropdown`), renamed "Visits Today", and added hover effects to the stats card.
- `frontend/js/admin.js`: Synchronized the custom mobile dropdown state with tab changes, wired animation handlers, removed duplicate logic, and attached a scroll event to `#widget-today-visits`.

## Functions Implemented/Changed

- `frontend/js/admin.js`: `initTabs` (updated), `switchTab` (updated)

---

# Task: Walk-in Patient Custom Service Dropdown Component

## Task Details

Replaced the default OS HTML `<select>` element in the Walk-in Patient quick add modal with a custom, styled dropdown component matching the website design. Features a trigger button showing service name and price tag, an animated popover menu with hover effects, and automatic detection of custom service names.

## Files Changed

- `frontend/admin.html`: Replaced raw `<select id="walkin-service">` with a custom dropdown container (`#walkin-service-dropdown-btn`, `#walkin-service-dropdown-menu`, and hidden input `#walkin-service-selected`).
- `frontend/js/admin.js`: Updated `initWalkinModal` to dynamically render custom option buttons with price badges and handle dropdown toggling/outside-click dismissal.

## Functions Implemented/Changed

- `frontend/js/admin.js`: `initWalkinModal` (updated)

---

# Task: Fix Slot Unblock Failed Error

## Task Details

Fixed the issue where unblocking a time slot threw a "Failed to unblock slot" error. The backend `unblock_slot()` controller was expecting parameters in a JSON body payload, whereas HTTP `DELETE` requests from the frontend pass `date` and `time_slot` via URL query parameters. Updated the controller to support fallback to `$_GET` query string parameters, and enhanced frontend error handling to extract server error details.

## Files Changed

- `backend/application/controllers/Admin.php`: Updated `unblock_slot()` and `block_slot()` functions to read date and time_slot parameters from both JSON body (`$input`) and query params (`$_GET`).
- `frontend/js/admin.js`: Enhanced `handleSlotClick()` to catch backend JSON error messages when unblocking or blocking a slot fails.
- `backend/js/admin.js`: Aligned `handleSlotClick()` query string request format and error handling with frontend.

## Functions Implemented/Changed

- `backend/application/controllers/Admin.php`: `unblock_slot` (updated), `block_slot` (updated)
- `frontend/js/admin.js`: `handleSlotClick` (updated)
- `backend/js/admin.js`: `handleSlotClick` (updated)

---

# Task: Walk-in Patient Mobile Modal Expansion & Dropdown Auto-scroll

## Task Details

Enhanced the Walk-in patient popup UX on mobile screens. Centered the modal vertically (`flex items-center justify-center p-4`), removed fixed height `h-[88vh]` in favor of dynamic shrinkwrap height (`h-auto max-h-[90vh]`) to eliminate empty bottom space. Removed service price/amount badges from the Walk-in dropdown options and trigger label, and added automatic smooth scrolling (`scrollIntoView`) on service dropdown toggle.

## Files Changed

- `frontend/admin.html`: Updated `#walkin-booking-modal` backdrop alignment to `items-center justify-center p-4` and rounded corners to `rounded-2xl sm:rounded-theme`.
- `frontend/js/admin.js`: Removed price amounts from option list rendering and label formatting, and added smooth auto-scrolling when opening service dropdown in `initWalkinModal()`.
- `backend/js/admin.js`: Synchronized `initWalkinModal()` custom dropdown implementation and smooth scrolling with frontend.

## Functions Implemented/Changed

- `frontend/js/admin.js`: `initWalkinModal` (updated)
- `backend/js/admin.js`: `initWalkinModal` (updated)

---

# Task: Walk-in Upward Dropdown, Top Quick Actions & "Didn't Come" Renaming

## Task Details

- Configured Walk-in patient service dropdown to expand upwards (`bottom-full mb-1`) above the trigger button and increased max height (`max-h-80`).
- Removed `overflow-hidden` and `overflow-y-auto` clipping constraints from `#walkin-booking-modal` container to allow full upward dropdown expansion without clipping options.
- Prepended "Other / Not Listed" (Custom) service option at the top of the Walk-in dropdown list before clinic services.
- Positioned WhatsApp (`fa-brands fa-whatsapp`) and Direct Call (`fa-solid fa-phone`) quick action buttons directly in front of Patient Name in the Booking Details modal.
- Enlarged WhatsApp & Call quick action icons on appointment cards (`h-7 w-7`), removed desktop hidden restriction (`lg:hidden`), and styled them with vibrant brand colors (`bg-emerald-500` & `bg-sky-500`).
- Renamed "No Show" / "Didn't Show" to "Didn't Come" across modal action buttons, appointment cards, and status badge definitions.

## Files Changed

- `frontend/admin.html`: Removed container overflow clipping on `#walkin-booking-modal`, updated `#walkin-service-dropdown-menu` to `bottom-full mb-1 max-h-80`, moved WhatsApp/Call action buttons beside `#detail-patient-name`, and updated button text to `Didn't Come`.
- `frontend/js/admin.js`: Updated `showDetailModal()` to bind WhatsApp/Call links and prepended custom service option at top in `initWalkinModal()`.
- `backend/js/admin.js`: Synchronized `initWalkinModal()` top custom option logic with frontend.
- `frontend/js/appointment-card.js`: Enlarged & colored WhatsApp/Call quick action icons in `renderAppointmentCard`, updated `STATUS_LABELS.no_show` and card action button innerHTML to `Didn't Come`.
- `backend/js/appointment-card.js`: Synchronized quick action icons, `STATUS_LABELS.no_show`, and action button text with frontend.

## Functions Implemented/Changed

- `frontend/admin.html`: `#detail-booking-modal` (updated), `#walkin-service-dropdown-menu` (updated)
- `frontend/js/admin.js`: `showDetailModal` (updated), `initWalkinModal` (updated)
- `backend/js/admin.js`: `initWalkinModal` (updated)
- `frontend/js/appointment-card.js`: `renderAppointmentCard` (updated)
- `backend/js/appointment-card.js`: `renderAppointmentCard` (updated)

# Task: Service Image Sequence Correction

## Task Details

Corrected the mismatch in service images where services 5 through 16 were shifted due to the missing Root Canal image. Renamed the uploaded root canal image (`service_17.png`) to `service_5.png` and shifted all other images (`service_5.png` to `service_15.png`) back to their correct matching indices on disk under the backend uploads directory.

## Files Changed

- `uploads/assets/clinic_001/service/service_*.png` (physical image files shifted on disk)

## Functions Implemented/Changed

- None (Disk file rename operation)

---

# Task: Mobile Hero Subtext "Visit" Word Swap & Sync

## Task Details

Updated hero subtext on mobile screens (`< 640px`) to display "From the first visit" instead of "From the first consultation", split into 2 rows, and hide the second clause. Synced across frontend (`js/app.js`) and backend codebase (`/var/www/html/dental-website-backend/js/app.js`).

## Files Changed

- `js/app.js` (frontend)
- `/var/www/html/dental-website-backend/js/app.js` (backend)

## Functions Implemented/Changed

- `renderHero` (updated subtext template rendering logic for mobile screen responsiveness)

---

# Task: Mobile Hamburger Menu Call Us Button Removal

## Task Details

Removed the "Call Us" button from the mobile hamburger navigation menu to clean up mobile nav view.

## Files Changed

- `index.html` (frontend)

## Functions Implemented/Changed

- None (HTML structure updated)

---

# Task: Seed Today's Dummy Data

## Task Details

Added 3 realistic appointments for "today" to the demo database seed structure so today's slots have mock data. Bumped current demo version to `'v13'`.

## Files Changed

- `js/demo-mock.js` (frontend)

## Functions Implemented/Changed

- `initLocalStorage` (seeded Rajesh Kumar, Sunita Sharma, and Karan Nair for today's date)

---

# Task: Mobile Stats Layout 2-Row Wrap

## Task Details

Updated the CSS styling of the hero section trust stats bar on mobile screens (< 640px) to wrap the third stat ("100% Sterilized Clinic") to a second, centered row, keeping the value and label inline on the same line.

## Files Changed

- `index.html` (frontend)

## Functions Implemented/Changed

- CSS `.stats-bar` styles updated under `@media (max-width: 639px)`

---

# Task: Mobile Hero Layout Spacing Optimization & Video Zoom Fix

## Task Details
Optimized spacing below the "100% Sterilized Clinic" stat line by setting stats bar bottom padding to 0 and reducing the cards column top margin to `0.25rem` on mobile screens (< 640px). Fixed video zoom-in issue by setting `#hero` section and background media elements to a fixed height of `55vh`.

## Files Changed
- `index.html` (frontend)

## Functions Implemented/Changed
- CSS `.stats-bar`, `#hero`, `#journey-text-col`, and `.journey-card` styles updated under `@media (max-width: 639px)`

---

# Task: Verify and Sync Pick a date & time

## Task Details
Verified that the phrase "Choose your convenient date & time." has been fully replaced with "Pick a date & time" in the codebase (both demo and main/production configuration files, admin interface, and the SQL database seed). Provided SQL update query to manually sync any existing live database records.

## Files Changed
None (Already up-to-date in repository source files).

## Functions Implemented/Changed
None.

---

# Task: Optimize Mobile Journey Cards Height and Spacing

## Task Details
Fixed card height matching, vertical spacing, and grid alignment on mobile screens. Resolved the issue where the first card shifted upward relative to the rest of the grid by clearing Tailwind's sibling `space-y-3` margins on all grid items, and overrode the active card `translateX` transform to prevent layout offsets in the grid. Reduced `min-height` to `138px` to remove excessive bottom gap, and targeted the flex text container instead of every `span` element to remove double spacing between the title and description.

## Files Changed
- `index.html` (frontend)

## Functions Implemented/Changed
- Updated `@media (max-width: 1023px)` styles: added margin resets on `#journey-track > *`, active card override `.journey-card.active`, `.journey-card .flex-col` rule, and modified `.journey-card` and `.journey-card span` dimensions.

---

# Task: Style Service Cards with Soft Dental Glass and Aura Glow

## Task Details
Changed service cards background to clean white `#ffffff` to eliminate the heavy flat grey style. Replaced the hard dark bottom gradient on service card images with a soft dental glass/aura card look. Utilized `:nth-child(3n+1)`, `:nth-child(3n+2)`, and `:nth-child(3n)` selectors to alternate the glows subtly between teal, blue, and warm pink auras, matching a responsive shift on hover. Moved these radial gradients directly to the `.service-card` backgrounds, and added `mix-blend-mode: multiply` on the service images to allow the background glows to blend naturally underneath them while keeping the images 100% sharp and crisp.

## Files Changed
- `index.html` (frontend)
- `js/app.js` (frontend)

## Functions Implemented/Changed
- Updated CSS rules `.service-card`, `.service-card-photo`, `.service-card-photo-wrap::after` (and hover overrides), and added specific alternating rules for `:nth-child(3n+1)`, `:nth-child(3n+2)`, and `:nth-child(3n)` in `index.html`.

---

# Task: Reorder Services and Implement Custom Dropdown in Stepper

## Task Details
Updated service selection in booking widget to display three primary services ("Tooth Pain & Checkup", "Root Canal Treatment (RCT)", and "Cleaning & Polishing") and replaced the fourth option with a premium dropdown list containing all services. Synced both Vercel static demo mode seeds and backend SQL seed schema.

## Files Changed
- `js/client-config.js` (frontend)
- `js/admin.js` (frontend)
- `js/demo-mock.js` (frontend)
- `js/app.js` (frontend)
- `/var/www/html/dental-website-backend/js/app.js` (backend)
- `/var/www/html/dental-website-backend/js/demo-mock.js` (backend)
- `/var/www/html/dental-website-backend/dental_clinic.sql` (backend)

## Functions Implemented/Changed
- `initLocalStorage()`: Seed data updated with new names and durations.
- `app.js` service rendering block: Refactored to draw `svc_1`, `svc_5`, and `svc_7` in order and render the 4th slot as a custom dropdown menu card. Added select, click-away, and active highlight behavior listeners.

---

# Task: Implement Robust Token Lifecycle and Expiration Management

## Task Details
Resolved auth token expiration and credential issues:
1. Updated frontend login to forward the `rememberMe` checkbox state to the backend login API `/api/auth/login`, allowing correctly generated 30-day tokens instead of defaulting to 12 hours.
2. Implemented a global `window.fetch` interceptor in `admin.js` to catch any `401 Unauthorized` responses on API calls (excluding the login routes) and automatically clear credentials and redirect the user back to the login screen.
3. Prioritized `sessionStorage` over `localStorage` when retrieving tokens or clinic usernames to prevent cross-tab session pollution (e.g., leftover persistent token overriding active session token).
4. Guarded the global 401 interceptor with an active token presence check to prevent multiple parallel api calls from triggering redundant logouts and duplicate alerts.

## Files Changed
- `c:\xampp\htdocs\dental-website-frontend\js\admin.js`
- `c:\xampp\htdocs\dental-website-frontend\js\resolve-config.js`

## Functions Implemented/Changed
- `window.fetch` (overridden globally to intercept 401s, handle auto-attaching Bearer tokens, and prevent duplicate logout flows)
- `handleLogin` (updated to send `rememberMe` parameter in JSON request body)
- `getAuthToken` (prioritized sessionStorage)
- `getClinicUsername` (prioritized sessionStorage)
- `resolveClinicConfig` (prioritized sessionStorage)

---

# Task: Bulletproof System Security, Add Package 4, and Fix Clinic Cloning

## Task Details

Implemented comprehensive bulletproofing for system security, clinic creation, and packages:
1. **Clinic Creation/Cloning Fixes**:
   - Updated create_clinic() in Admin.php to correctly clone isibility_settings and contact_map_url from clinic_001, instead of using hardcoded defaults or leaving them empty.
   - Ensured the package setting is cloned from clinic_001 (or defaults to 3).
2. **Package 4 (Website Only)**:
   - Added Package 4 ('Website only, no booking system either in website or admin') to the super admin panel dropdown.
   - Updated frontend logic in dmin.js (pplyPackagePlanUI) so that Package 4 completely hides all booking-related tabs (Dashboard, Appointments, History, Follow-ups, Leads, Reports) even for super admins.
3. **Security Audits**:
   - Verified that both create_clinic and update_clinic_full are properly guarded by _requireSuperAdmin().
   - Verified that the update_settings endpoint for regular clinics strictly ignores privilege escalation attempts for package or super_admin_only fields.
4. **External Links**:
   - Updated desktop and mobile admin portal links in index.html to include 	arget=_blank` to open in a new tab as requested.

## Files Changed

- ackend/application/controllers/Admin.php
- rontend/admin.html
- rontend/index.html
- rontend/js/admin.js

## Functions Implemented/Changed

- create_clinic (Admin.php): Replaced hardcoded default visibility settings with dynamic clone logic for isibility_settings, contact_map_url, and package.
- pplyPackagePlanUI (admin.js): Updated hideBookingTabs logic to forcefully hide all booking features if pkg === 4.

