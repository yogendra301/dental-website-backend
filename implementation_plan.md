# Add Admin Portal Visibility Settings

The goal is to allow super admins to enable or disable specific sections of the Admin Portal, similar to how website sections can be toggled. This will fulfill the requirement to hide "Booking Management" (Dashboard) while keeping "Patient History", "Records", and "Prescriptions" (History) intact for clinics that do not want booking features.

## Proposed Changes

### 1. Database & Backend API
- No schema changes are required. The new settings will be stored in the existing JSON column `visibility_settings` within the `clinics` table.
- The backend `Admin.php` controller already merges and returns `visibility_settings`, so no API endpoint changes are strictly necessary for saving or retrieving, as it accepts any valid JSON key within `visibility_settings`.

### 2. Frontend Settings UI (`admin.html`)
- Inside the **Clinic Config -> Visibility** tab (`id="cfg-tab-visibility"`), add a new subsection for **Admin Portal Visibility**.
- Add toggle switches for each section:
  - Dashboard (Appointments & Booking)
  - History (Patient Records & Prescriptions)
  - Follow-ups
  - Leads
  - Reports
  - Gallery
  - Settings
- Ensure these new inputs are properly formatted with the existing UI styling.

### 3. Frontend Logic (`js/admin.js`)
- **Saving Settings**: Update the `saveClinicConfig` or equivalent function to read the states of the new Admin Portal toggles and include them in the `visibility_settings` payload sent to the backend. Example keys: `admin_show_dashboard`, `admin_show_history`, etc.
- **Applying Settings**: When the admin portal loads, read these settings from the fetched clinic data:
  - If a section is disabled, completely hide its tab button from the top navigation menu using `.style.display = 'none'` or adding the `.hidden` class.
  - If the default `dashboard` tab is disabled, automatically set the active tab to the first available/enabled tab (e.g., `history`).
  - Restrict this logic to the main application (`admin.html`), leaving the demo version intact.

## Verification Plan
### Automated Tests
- No automated tests to run.
### Manual Verification
- Log in to `admin.html` as a super admin.
- Navigate to Clinic Config -> Visibility.
- Toggle off "Admin Portal Dashboard" and toggle on "History".
- Save and reload the portal.
- Verify the "Dashboard" tab disappears and the app lands on the "History" tab instead.
- Verify that toggles successfully persist in the database (by checking the DB or reloading).
