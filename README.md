# CaféCore

Tech Stack:
- HTML
- CSS
- JS
- PHP
- MYSQL

Folder Structure:
- MVC

## Run locally

PHP 8.1+ with PDO MySQL, PDO SQLite (needed only for the one-time import), and MySQL/MariaDB. Create/import the `cafecorev2` database using `cafecore.sql`.

```bash
php -S localhost:8080 router.php
```

Then open http://localhost:8080

The application connects to MySQL, not SQLite. Connection defaults are `127.0.0.1:3306`, database `cafecorev2`, user `root`, and an empty password. Override them with `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables.

Checkout writes an order, its order items, and a corresponding payment row in one transaction. Counter payments are recorded as `pending`; `payment_date` remains empty until a payment is collected and confirmed.

## Staff workspace

Sign in with an account whose `users.role` is `staff` (administrators can also use the staff workspace). To grant staff access to an existing customer account, run this once in MySQL, replacing the email address:

```sql
UPDATE users SET role = 'staff' WHERE email = 'staff@example.com';
```

The staff workspace is available at `/staff/orders`. Staff can create orders for registered customers, search and filter recent orders, update order and payment statuses, and edit menu products. Payment methods are recorded for in-person processing; marking a payment as paid records its payment date. Administrators can create and delete staff accounts at `/admin/staff`; deleting an account with customer order history is blocked so existing orders are preserved. Admins and staff use the same profile editor as customers. The admin order-history page is available at `/admin/history`.

## Reports and Excel export

Admins and staff can open `/reports` to review orders by date range, order status, and payment status. The report includes daily sales totals and matching order details. Use **Export to Excel** to download an `.xlsx` workbook with an overview sheet and a filterable order-details sheet.

## Structure

- `index.php` — front controller
- `app/core` — router, base controller, database
- `app/controllers` — auth, home, cart, orders, profile, admin, staff
- `app/models` — User, MenuItem, Order, Cart
- `app/views` — screens + layouts
- `public/css` — all the designnof the project


## Forgot password email (OTP) setup

Password recovery sends a 6-digit OTP to the email address registered to the account. The code expires after 10 minutes, is stored as a password hash, and is limited to 5 attempts. The app needs SMTP credentials to send real email; it does not send email just by running PHP locally.

Configure these environment variables before starting PHP:

- `SMTP_HOST` — for Gmail, `smtp.gmail.com`
- `SMTP_PORT` — `587` for TLS, or `465` for SSL
- `SMTP_ENCRYPTION` — `tls` for port 587, or `ssl` for port 465
- `SMTP_USERNAME` — the sender's full email address
- `SMTP_PASSWORD` — an SMTP/app password (for Gmail, create an App Password; do not use your normal account password)
- `SMTP_FROM_EMAIL` — sender email address (usually same as `SMTP_USERNAME`)
- `SMTP_FROM_NAME` — optional sender display name, defaults to `CaféCore`

Example for Windows PowerShell (replace the values with your own credentials):

```powershell
$env:SMTP_HOST="smtp.gmail.com"
$env:SMTP_PORT="587"
$env:SMTP_ENCRYPTION="tls"
$env:SMTP_USERNAME="your-email@gmail.com"
$env:SMTP_PASSWORD="your-16-character-app-password"
$env:SMTP_FROM_EMAIL="your-email@gmail.com"
$env:SMTP_FROM_NAME="CaféCore"
php -S localhost:8080 router.php
```
