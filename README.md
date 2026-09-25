# CaféCore

PHP MVC café ordering app (HTML, CSS, PHP) matching the CaféCore wireframes.

## Run locally

PHP 8.1+ with MySQL/MariaDB and the `cafecore` database:

```bash
php -S localhost:8000 router.php
```

Then open http://localhost:8000

## Demo accounts

| Role | Email | Password |
| --- | --- | --- |
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
