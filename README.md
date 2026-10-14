# CaféCore

Tech Stack:
- HTML
- CSS 
- PHP
- MYSQL

## Run locally

PHP 8.1+ with MySQL/MariaDB and the `cafecore` database:

```bash
php -S localhost:8080 router.php
```

Then open http://localhost:8080

## Demo accounts

| Role | Email | Password |
| Customer | customer@cafecore.test | password |
| Admin | admin@cafecore.test | password |

Tables and demo data are created automatically on first visit. Database credentials are configured in `storage/database.php`.

## Structure

- `index.php` — front controller
- `app/core` — router, base controller, database
- `app/controllers` — auth, home, cart, orders, profile, admin
- `app/models` — User, MenuItem, Order, Cart
- `app/views` — screens + layouts
- `public/css/style.css` — Poppins + Font Awesome kit


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

Keep SMTP credentials private and never commit them to a public repository. The database creates the `password_reset_tokens` table automatically.
