# Makahupay Reflex & Massage System

A PHP 8.1+ / MySQL 8+ business management application for a small massage and reflexology business. The project uses a front controller, reusable PHP modules, PDO prepared statements, server-side role checks, Bootstrap 5, and a relational MySQL schema.

## Project structure

```text
makahupay-reflex/
├── .env.example
├── .gitignore
├── composer.json
├── README.md
├── config/
│   └── bootstrap.php
├── database/
│   └── schema.sql
├── bin/
│   └── create-admin.php
├── app/
│   ├── Controllers/
│   │   └── Actions.php
│   └── Core/
│       ├── Activity.php
│       ├── Auth.php
│       ├── Database.php
│       ├── Permissions.php
│       ├── helpers.php
│       └── view_helpers.php
├── includes/
│   ├── customer_modal.php
│   ├── footer.php
│   ├── header.php
│   └── sidebar.php
├── pages/
│   ├── appointments.php
│   ├── customers.php
│   ├── dashboard.php
│   ├── expenses.php
│   ├── forgot.php
│   ├── inventory.php
│   ├── login.php
│   ├── logs.php
│   ├── pos.php
│   ├── profile.php
│   ├── receipt.php
│   ├── reports.php
│   ├── reset.php
│   ├── services.php
│   ├── session.php
│   ├── settings.php
│   ├── therapists.php
│   ├── transactions.php
│   ├── users.php
│   └── walkins.php
└── public/
    ├── .htaccess
    ├── index.php
    ├── assets/
    │   ├── css/app.css
    │   └── js/app.js
    └── uploads/
        └── .htaccess
```

## Included modules

- Role-based authentication for Administrator, Staff/Receptionist, and Therapist accounts.
- Dashboard with daily totals, monthly revenue, expenses/net income, schedule, recent transactions, and popular services.
- Customer directory, customer profiles, appointment/payment/session history, archive/restore, and search.
- Service catalog with duration and PHP pricing; starter services are inserted by the schema.
- Therapist profiles, availability status, optional profile images, and assigned schedules.
- Daily, weekly, and monthly appointment views; automatic end-time calculation; therapist overlap checks with a row lock; status updates and session notes.
- Walk-in check-in queue.
- POS checkout with quantity, discount, payment method, amount received, insufficient-payment validation, change, transaction line items, and printable receipt.
- Transactions, inventory movements and low-stock warnings, expenses, administrator analytics/CSV exports, staff daily sales reports, activity logs, settings, logo upload, contextual notification counts on page load, database backup, and administrator-only restore.

## Requirements

- PHP 8.1 or later (PHP 8.2+ recommended)
- MySQL 8.0+ or compatible MariaDB
- PHP extensions: `pdo_mysql`, `mbstring`, and `fileinfo`
- Web server with HTTPS support (Apache or Nginx)

## Local installation

1. Create a MySQL database and a database user with privileges on that database. Note the exact database name; you will use it for `DB_NAME` in step 3.
2. Select that database in phpMyAdmin and import `database/schema.sql`, or run `mysql -u DB_USER -p DB_NAME < database/schema.sql`. The schema creates the tables, roles, initial business settings, and starter services.
3. Copy `.env.example` to `.env` in the project root. Set `DB_HOST`, `DB_PORT`, `DB_NAME` (the exact database name from step 1), `DB_USER`, `DB_PASS`, `APP_URL`, and `APP_TIMEZONE`. Never commit `.env` to source control.
4. Make `public/` the web document root. Do not expose the project root, `.env`, `config/`, `database/`, `app/`, `bin/`, or `includes/` through the web server.
5. Ensure `public/uploads/` is writable by the PHP process (for example, owner-write permissions). It only accepts JPG, PNG, and WebP images, limited to 2 MB.
6. From the project root, create the first administrator using:

   ```bash
   php bin/create-admin.php
   ```

7. Visit your configured `APP_URL` and sign in with that administrator account. Add therapist profiles, connect Therapist login accounts as needed, and update business settings.

For a quick local-only run, configure the web server document root to `public/` and use PHP's development server from the project root:

```bash
php -S 127.0.0.1:8000 -t public
```

The development server is not a production hosting solution.

## Production deployment

1. **Create the production database.** Create a MySQL/MariaDB database and a dedicated user. Grant the user access only to this application's database.
2. **Import the schema.** Import `database/schema.sql` into the newly created database. Confirm the role rows and starter services exist.
3. **Configure environment variables.** Copy `.env.example` to `.env` outside the public web root. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `APP_TIMEZONE=Asia/Manila`, and the production database credentials. Do not place credentials in frontend code.
4. **Upload the application.** Upload the project so that only the `public/` directory is exposed by the web server. Keep `app/`, `config/`, `database/`, `bin/`, `includes/`, `.env`, and `README.md` outside the document root. Enable HTTPS and redirect HTTP to HTTPS at the web-server or hosting-panel level.
5. **Configure the web server.** For Apache, enable the provided `public/.htaccess` and `mod_headers`. For Nginx, set the document root to the `public/` folder, route requests to `public/index.php`, deny access to hidden files, and disable PHP execution within `public/uploads/`.
6. **Create the administrator.** Run `php bin/create-admin.php` from the project root using the production environment. If shell access is unavailable, create the first account through a secure one-time CLI/deployment process; do not insert a plain-text password into the database.
7. **Test before launch.** Verify login/logout, role restrictions, customer registration, service pricing, overlapping appointment rejection, walk-in check-in, POS cash/GCash checkout, insufficient-payment rejection, printable receipt, reports/CSV, inventory movement, expenses, backup download, and restore in a non-production copy first.
8. **Set up backups.** Use the in-app administrator SQL export plus your host's scheduled database backup. Store backup files securely and test restore procedures on a staging database.

## Password reset email

Password-reset links are generated with random single-use tokens, stored as hashes, and expire after 30 minutes. The application currently sends the message using PHP `mail()`. Configure a reliable mail transport on your hosting server and set `MAIL_FROM` in `.env`. If your hosting provider does not support PHP `mail()`, connect a transactional mail transport before relying on password resets.

## Database backup and restore

The administrator-only backup download exports the schema and records as a SQL file. Restore accepts a SQL backup produced by this application, checks the allowed SQL statement types and known table names, and asks the administrator to type `RESTORE` before replacing tables. Restore is destructive; make a second backup first and test using a staging database. The restore upload is limited to 30 MB; larger backup files can be restored using the hosting database administration tool or MySQL command line.

## Security notes

- Passwords use `password_hash()` / `password_verify()` with `PASSWORD_DEFAULT`.
- Database operations use PDO prepared statements for user-provided values.
- POST actions require session-bound CSRF tokens; PHP sessions use strict mode, HttpOnly cookies, SameSite=Lax, and Secure cookies when HTTPS is detected.
- Access permissions are checked on the server for each module and action.
- User-facing output is HTML-escaped; uploaded images are MIME-checked and limited to safe raster formats.
- Appointment creation serializes checks per therapist to reduce concurrent double-booking.
- Do not use customer notes as a medical record. Record only information needed for massage/reflexology service delivery.
- Use HTTPS, keep PHP/MySQL patched, set file permissions conservatively, monitor logs, and protect backup files containing personal and financial data.

## Operational notes / current scope

- Receipt PDF output uses the browser's **Print → Save as PDF** workflow; it does not require a separate PDF library.
- Charts are rendered with lightweight HTML/CSS bars to avoid a required JavaScript chart dependency.
- Business hours and booking notice are configurable. Minimum booking notice and appointment time intervals are enforced. Appointment schedules are displayed in daily, weekly, and monthly table views (not a drag-and-drop calendar grid); staff shift planning and automatic SMS/GCash gateway integration are not included.
- The GCash and bank-transfer options record the payment method and cashier-confirmed amount; this is not a direct payment gateway integration.
- Email reset delivery depends on the configured server mail transport.

## Default service catalog

The schema seeds Full Body Massage, Swedish Massage, Deep Tissue Massage, Hilot, Foot Reflexology, Back Massage, Head and Shoulder Massage, and Combination Massage. Review prices and durations in the Services module before opening the business system for live use.
