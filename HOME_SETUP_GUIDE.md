# Migration & Windows Home Setup Guide
This guide helps you move your development workspace (code and database) from your office Ubuntu system to your Windows home laptop (where Apache and PHP are already running).

---

## Step 1: Export Database (Office Ubuntu)
Before leaving the office, export your database to an SQL file:
```bash
mysqldump -u yogi -p dental_clinic > ~/Desktop/dental_clinic_backup.sql
```
*(Enter password `Yogen@1234` when prompted)*

Copy `dental_clinic_backup.sql` to a USB drive or upload it to Google Drive.

---

## Step 2: Package the Code (Office Ubuntu)
Zip both frontend and backend directories:
```bash
# Zip backend
cd /var/www/html
zip -r ~/Desktop/dental-website-backend.zip dental-website-backend/

# Zip frontend
cd /home/abc
zip -r ~/Desktop/dental-website-frontend.zip dental-website-frontend/
```
Copy both ZIP files to your USB/Google Drive.

---

## Step 3: Extract Code on Windows Home Laptop
1. Move the ZIP files to your Windows laptop.
2. Extract the backend folder into your local Apache web root directory (e.g., `C:\xampp\htdocs\dental-website-backend` or `C:\wamp64\www\dental-website-backend`).
3. Extract the frontend folder into the sibling folder inside the same web root (e.g., `C:\xampp\htdocs\frontend`). 
   *Note: This matches the structure where CodeIgniter index.php checks `../frontend` to serve static files.*

---

## Step 4: Import Database on Windows Laptop
1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or your MySQL client on Windows.
2. Create a new database named `dental_clinic`.
3. (Optional) Create a MySQL user named `yogi` with password `Yogen@1234` and grant permissions. 
4. Select the `dental_clinic` database and click **Import**, select `dental_clinic_backup.sql`, and click **Go**.

---

## Step 5: Update Database Configuration
If your home MySQL configuration is different (e.g., you are using `root` with no password):
1. Open `/application/config/database.php` in the backend.
2. Edit lines 9-11 with your Windows database credentials:
   ```php
   'hostname' => 'localhost',
   'username' => 'root',        // Change if not using root
   'password' => '',            // Change to your MySQL password
   'database' => 'dental_clinic',
   ```

---

## Step 6: Verify and Run
1. Ensure your Apache and MySQL servers are running on your Windows laptop.
2. Ensure `mod_rewrite` is enabled in your Windows Apache configuration (`httpd.conf` has `LoadModule rewrite_module modules/mod_rewrite.so` uncommented).
3. Access the project:
   - Frontend via backend server: `http://localhost/dental-website-backend/index.php` (if using backend's static file routing).
   - Frontend via static server: If you start a python/static server on Windows on port 8002, access `http://localhost:8002/`.
