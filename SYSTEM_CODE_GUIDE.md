# CaféCore System Code Guide

This guide explains the current source tree in plain language. It focuses on what each file is responsible for and how a normal request moves through the application. It does not repeat third-party Font Awesome code or explain every CSS declaration one by one; those files are identified by purpose.

## 1. What the application does

CaféCore is a PHP and MySQL café ordering system. Customers browse menu items, configure eligible drinks, add items to a session cart, check out, and review their order history. Staff manage orders and payments. Admins manage staff accounts, menu items, order history, and reports.

The application follows a small Model–View–Controller pattern:

- **Routes** map a URL and HTTP method to a controller method.
- **Controllers** handle permissions, validate submitted data, call models, and select a page to render.
- **Models** perform database work or manipulate session data.
- **Views** contain HTML with PHP snippets for values, loops, and conditional sections.
- **Layouts** wrap views in the customer, admin, or authentication page frame.

The project does not use a full framework or Composer packages for its core features. It uses PHP PDO for MySQL access and includes a small Excel workbook writer in the source.

## 2. How a request travels

For example, when a customer opens `/checkout`:

1. The web server starts at `index.php` (or passes through `router.php` when using PHP's built-in server).
2. `index.php` loads configuration and starts the application bootstrap.
3. `app/bootstrap.php` starts the session, loads core classes, and enables class autoloading.
4. `app/core/App.php` looks up `GET /checkout` in its route list and calls `CartController::index()`.
5. The controller checks sign-in, gets the cart and profile data, and calls `Controller::view()`.
6. The view renders `app/views/checkout/index.html`; the layout adds the shared page header and scripts.
7. On checkout submission, the route calls `CartController::place()`, which validates the submission and asks `Order` to write the order.

The database and browser session have different jobs:

- The cart is held in `$_SESSION` until checkout.
- Users, products, orders, order lines, payments, and reset tokens are held in MySQL.
- The browser handles presentation tasks such as filters, pagination, showing a map, and updating the displayed checkout total.

## 3. Root files and startup

- **`index.php`** — Application entry point. It loads `config/config.php`, loads the bootstrap, and creates `App`.
- **`router.php`** — Intended for the command shown in the README, `php -S localhost:8080 router.php`. It should let the built-in PHP server serve real files directly and send other paths to `index.php`. The current source spells the server variable `REQUES_URI`; PHP normally provides `REQUEST_URI`. This can prevent the static-file pass-through from working as intended when using that development-server command.
- **`.htaccess`** — Apache rewrite rules for routing requests through the front controller and denying access to private project paths. Its behavior applies to Apache, such as a normal XAMPP setup, rather than to every server.
- **`README.md`** — Setup notes, database configuration, role instructions, staff workspace information, report/export information, and password-reset email configuration.
- **`GROUP 3 BSIT2-04 Milestone Checking 2`** and **`GROUP 3 BSIT2-04 Milestone Checking 2 - Corrected Documentation.docx`** — Project documents, not executable application code.

## 4. Configuration and shared helpers

### `config/config.php`

Defines the application name and filesystem paths, then provides helper functions used throughout controllers and views:

- `url()` builds an application-relative URL.
- `asset()` builds a URL under `public/`.
- `redirect()` sends a `Location` header and stops the current request.
- `e()` HTML-escapes values before placing them in page markup.
- `csrf_token()` and `csrf_field()` create a session-bound form token; `verify_csrf()` checks it on POST requests.
- `old()` retrieves escaped values saved for repopulating a form.
- `flash()` stores a one-time success or error message in the session.
- `auth()` returns the signed-in user stored in the session.
- `require_auth()`, `require_admin()`, and `require_staff()` enforce access rules and redirect when the user is not allowed.
- `money()` formats amounts as Philippine pesos.
- `history_date_filter()` validates the selected date and defaults it to the database's current date.

`require_staff()` reloads the account from MySQL. This allows an archived or changed account to lose access even if its browser session still contains old details.

### `app/bootstrap.php`

Starts the PHP session, loads `Database`, `Controller`, and `App`, and registers an autoloader. The autoloader looks for a requested class in the controller, model, and core folders, so classes generally do not need individual `require_once` calls.

### `app/core/App.php`

The route table is the application's URL map. It has separate GET and POST lists. `dispatch()` reads the request method and path, removes the configured base path, finds the handler, and calls the controller method. An unknown path produces a 404 page. Paths under `public/` are skipped because static assets should be served directly by the web server.

### `app/core/Controller.php`

- `view()` extracts the supplied data into variables, renders the selected `.html` PHP template into a buffer, then inserts that output into a layout.
- `json()` writes a JSON response and exits, used by status-refresh endpoints.

### `app/core/Database.php`

`connect()` creates one reusable PDO connection using `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`, with development defaults. PDO is set to throw errors, return associative rows, and use native prepared statements.

When connecting, it also makes compatibility changes for older databases: it adds missing archive and delivery columns, creates the reset-token and payments tables if needed, and backfills missing payment rows for existing orders. These checks mean some schema work happens automatically at application startup.

## 5. Routes at a glance

`app/core/App.php` contains the definitive route map. The main route groups are:

- **Public/customer pages:** `/`, `/home`, `/menu/item`, `/login`, `/signup`, `/forgot`, `/logout`, `/checkout`, `/history`, and `/profile`.
- **Cart actions:** POST `/cart/add`, `/cart/update`, and `/cart/remove`.
- **Checkout and customer history:** POST `/checkout`, GET `/history/statuses`, and POST `/history/archive` (the last route remains in code, though the current customer history screen no longer shows archive controls).
- **Admin pages/actions:** `/admin`, `/admin/history`, `/admin/staff`, `/admin/menu`, menu editing, and their POST save/archive/toggle actions.
- **Staff pages/actions:** `/staff/orders`, order creation, status/payment changes, and combined order updates.
- **Reports:** `/reports` and `/reports/export`.

GET normally displays pages or returns data. POST normally changes something and then redirects to avoid repeating the submission if the page is refreshed.

## 6. Controllers

Controllers coordinate requests. They are where the app checks the user's role, reads GET/POST values, validates them, calls models, and chooses a view.

### `app/controllers/HomeController.php`

- `index()` loads featured items, available products, and categories for the storefront.
- `detail()` requires sign-in, validates the requested menu item, and loads the product page with its allowed size/sugar options.

### `app/controllers/AuthController.php`

- `login()` shows the login view or redirects an already signed-in user to the correct workspace for their role.
- `loginPost()` checks CSRF, looks up the email, rejects archived accounts, verifies the password hash, creates the session user, and redirects by role.
- `signup()` and `signupPost()` display and process registration. The POST action validates required fields, email, password length and confirmation, and duplicate email before calling `User::create()`.
- `forgot()` shows the OTP reset form.
- `forgotPost()` handles two actions. `send_otp` validates the email, rate-limits requests in the current session, creates a six-digit code, stores only a hash and expiry in MySQL, and emails it. `reset_password` checks the session-bound email/user, six-digit format, expiry, attempt limit, and new password before changing the password.
- `logout()` clears the session and reports sign-out.

### `app/controllers/HomeController.php`

Described above; it supplies storefront and product-detail data to the matching views.

### `app/controllers/CartController.php`

- `index()` requires sign-in and displays the current cart, subtotal, and the saved profile address as the initial checkout address.
- `add()` validates that the product exists and is available, normalizes size and sugar selections, then adds it to the session cart.
- `update()` changes the quantity for one cart line.
- `remove()` deletes one cart line.
- `place()` validates the payment method, fulfillment type, non-empty delivery address, and non-empty cart. It calls `Order::create()`, clears the cart after success, and redirects to history. The checkout map writes into the checkout address field; saving an order does not update the profile record.

### `app/controllers/OrderController.php`

- `history()` loads the signed-in user's orders for one date. Customers get Current orders and Completed tabs; staff/admin users are sent to the admin layout.
- `archiveCustomerOrders()` is the legacy POST handler for customer archiving. It still exists in the route/controller/model, but the current customer history view no longer renders the bulk archive form or row checkboxes.
- `statuses()` returns a no-cache JSON list used to refresh order status badges in the customer history page.

### `app/controllers/ProfileController.php`

- `index()` reloads the latest user row and shows the profile form, selecting the main or admin layout by role.
- `update()` validates name, phone, and address; saves them; refreshes the session user; and redirects.
- `currentUser()` ensures the account still exists in the database before profile work.

### `app/controllers/AdminController.php`

- `dashboard()` requires an admin and loads sales stats, customer count, menu count, and recent orders.
- `history()` and `archiveOrders()` display and process admin order history, including archive/restore.
- `staff()` and `archiveStaff()` list/archive/restore staff accounts.
- `createStaff()` validates and creates a staff login.
- `deleteStaff()` deletes a staff record only if it has no orders; otherwise the order history is preserved.
- `menu()` and `archiveMenu()` display and archive/restore menu items.
- `edit()` loads a product editor for either a new or existing item.
- `save()` validates menu fields and uploaded image MIME type/size, stores uploads under `resources/menu-images`, saves the database row, and removes the old managed image when it was replaced.
- `toggle()` changes a product's available/featured flag.
- `delete()` permanently deletes a menu item.

Several menu operations use `require_staff()`, which permits both staff and admins. Dashboard, staff-account administration, and admin order history use `require_admin()`.

### `app/controllers/StaffController.php`

- `orders()` validates status/search filters and loads the order queue.
- `createOrder()` shows the staff order form.
- `createOrderPost()` validates a staff-created order through `Order::createForCustomer()` and reports validation/database errors.
- `updateStatus()`, `updatePayment()`, and `updateOrder()` validate a CSRF-protected POST and apply an order or payment change.
- `ordersUrl()` and `statusReturnUrl()` reconstruct safe redirect paths after form submission.
- `postedString()` and `postedInt()` read POST values with type checks.

**Current workspace note:** `StaffController::createOrder()` refers to `app/views/staff/create-order.html`, but that file is absent from the current tree. If it has not intentionally been removed, `/staff/orders/create` will show the controller's “View not found” response.

### `app/controllers/ReportController.php`

- `index()` requires staff/admin access, builds valid date-period filters, fetches the report, and renders the report page.
- `export()` builds styled worksheet rows for sales, categories, menu items, and orders, then delegates workbook creation to `ExcelExport`.
- `headerRow()` styles column headings.
- `mergedSectionRow()` makes a section heading row that spans columns.
- `salesBreakdown()` places matching orders into daily, weekly, monthly, or yearly buckets.
- `filters()` and `validDate()` normalize requested periods and reject invalid dates.
- `periodLabel()` provides a display label.

## 7. Models

Models isolate data operations from page rendering. Database values are usually accessed with PDO prepared statements.

### `app/models/User.php`

Finds users, creates/updates accounts, counts customers, creates staff, archives staff, and handles password-reset records. Passwords and OTP codes are stored as hashes. `deleteStaff()` uses a transaction and refuses to delete an account with related orders. `toSession()` limits the session user to the fields needed by the rest of the app.

### `app/models/MenuItem.php`

Queries menu items and categories, saves/updates products, toggles availability/featured state, permanently deletes, counts active items, and archives/restores items. `customizationOptions()` detects beverage categories so drinks can have size/sugar options while food items do not.

### `app/models/Cart.php`

The cart is a PHP session array. Each key is built from product ID, size, and sugar level, which lets the same drink appear as separate customized lines. Quantities are capped. `detailed()` reloads product data, recalculates prices and extras, and returns line items plus subtotal. It currently sets its service fee to zero; delivery fee logic is handled elsewhere.

### `app/models/Order.php`

This is the largest model because it supports most of the business logic:

- `create()` creates the order, item snapshots, and payment row in one database transaction.
- `createForCustomer()` validates a staff-entered customer and products, consolidates duplicate cart lines, calculates customizations, and writes the order transactionally.
- `forStaff()` searches/filters the staff queue and joins customer, payment, and line-summary data.
- `staffSummary()` calculates order counts for the staff dashboard.
- `updateStaffStatus()`, `updateStaffPayment()`, and `updateStaffOrder()` validate changes and maintain `payment_date`; a completed paid order is archived from the active queue.
- `forUser()` loads one user's order history by date and optional status.
- `archiveForUser()` is the data operation behind the legacy customer archive route.
- `statusesForUser()` provides the short order-ID/status list for browser refreshes.
- `allRecent()` and `allForAdmin()` serve dashboard/admin-history views.
- `reportDateDefaults()` reads the current date from MySQL.
- `archiveMany()` archives/restores selected admin orders.
- `report()` applies report filters and calculates order/revenue/collected totals and time buckets.
- `reportCategorySales()` aggregates sold quantities and order-line value by menu category/item.
- `reportScope()` limits reports to the date range, completed orders, and paid payments.
- `stats()` builds dashboard totals, recent daily/weekly/monthly/yearly earnings, status counts, and category sales.

### `app/core/ExcelExport.php`

Creates `.xlsx` files without a spreadsheet package. An Excel workbook is a ZIP file containing XML documents. This class writes content-types metadata, workbook/sheet relationships, styles, worksheet cells, optional filters/frozen rows/column widths/merged cells, escapes XML, and packages the files using PHP zlib.

### `app/core/Mailer.php`

Connects to an SMTP server over a socket to send the password-reset OTP. It handles SMTP replies, optional TLS setup, login, message headers, and dot-stuffing. It reads SMTP environment variables, but the current source also contains fallback credential values. Those should be removed from source and rotated if they are real credentials; this guide intentionally does not reproduce them.

## 8. Views and layouts

Files ending in `.html` are PHP templates, not static HTML. `<?= ... ?>` prints a value; `<?php if (...) ?>` and `foreach` create conditional/repeated markup. User-provided values should use `e()` before being printed.

### Layouts

- **`app/views/layouts/main.html`** — Customer-facing header, navigation, flash messages, page container, shared JS, and Leaflet assets for profile/checkout maps.
- **`app/views/layouts/admin.html`** — Admin/staff sidebar and top bar, role-aware navigation, flash messages, mobile nav toggle, and admin scripts.
- **`app/views/layouts/auth.html`** — Split-screen sign-in/registration/password-reset layout.

### Customer and authentication views

- **`app/views/home/index.html`** — Featured cards, category filters, product cards, and pagination target for the storefront.
- **`app/views/home/detail.html`** — One product's details and add-to-cart form. Size and sugar controls are rendered only when allowed by its category. Small JavaScript recalculates the displayed size price.
- **`app/views/checkout/index.html`** — Cart lines, quantity/remove forms, order type, delivery address, payment selection, and total display. JavaScript updates the display total and shows/hides the delivery address. The location picker writes into the checkout textarea only.
- **`app/views/history/index.html`** — Customer order history or staff history, depending on the supplied layout flag. Customers see Current orders and Completed; staff get history pagination. Customer order status badges refresh from the status JSON endpoint.
- **`app/views/profile/index.html`** — Profile fields and map dialog. Choosing a map point reverse-geocodes it; the profile form saves the resulting address to the user's record.
- **`app/views/auth/login.html`** — Login form and show-password toggle.
- **`app/views/auth/signup.html`** — Registration form and show-password toggle.
- **`app/views/auth/forgot.html`** — Two-state OTP page: request an email code, then enter code and new password.

### Admin and staff views

- **`app/views/admin/dashboard.html`** — Revenue summary, earnings chart, category pie/legend, and recent orders. JavaScript switches the chart among daily, weekly, monthly, and yearly data already embedded in the page.
- **`app/views/admin/history.html`** — Date-filtered order history with archive/restore selection and 10-card pagination.
- **`app/views/admin/menu.html`** — Current/archived products, category filters, bulk archive controls, availability switches, edit links, and pagination.
- **`app/views/admin/edit.html`** — Menu item create/edit form, image preview, category/price/description fields, and available/featured switches.
- **`app/views/admin/staff.html`** — Create/search/archive staff accounts and paginate account cards.
- **`app/views/staff/orders.html`** — Active/archive views, order search/status filters, summary links, order/payment controls, and staff order cards.
- **`app/views/reports/index.html`** — Report filters, summary cards, daily/period sales table, order detail table, and client-side pagination.
- **`app/views/staff/create-order.html`** — Not present in the current working tree, although the staff controller expects it.

## 9. Browser code

- **`public/js/app.js`** — Filters the storefront by category, paginates product cards, and makes cards open by click or keyboard.
- **`public/js/flash.js`** — Fades success/error notices after a short delay.
- **`public/js/bulk-archive.js`** — Connects select-all and row checkboxes, updates the selected count, and disables archive/restore until an item is selected. Used in admin pages.
- **`public/js/location-picker.js`** — Creates a Leaflet/OpenStreetMap picker. It lets users click the map or request their current device location, places a marker, reverse-geocodes coordinates with Nominatim, and writes the selected address only into the page's `data-location-picker-input` field. On checkout that field is the order's delivery textarea; on profile it is the saved home-address field.

Inline scripts in views handle small page-specific features such as pagination, price totals, report pagination, image previews, and the admin navigation toggle.

## 10. Styles and static assets

- **`public/css/style.css`** — Global type, colors, buttons, form defaults, basic layout, and shared primitives.
- **`public/css/main.css`** — Customer pages: storefront, item detail, cart/checkout, history, profile, modal/map, and shared controls.
- **`public/css/admin.css`** — Admin/staff dashboard, sidebar, tables, menu/staff management, reports, and staff workspace.
- **`public/css/auth.css`** — Login/signup/forgot-password split layout.
- **`public/css/style1.css`** — Present in the tree but not linked by the current layouts; it appears to be an alternate/older stylesheet.
- **`public/images/cafecore-mark.svg`** — Brand mark used by page headers.
- **`public/vendor/fontawesome/`** — Locally hosted third-party Font Awesome CSS, fonts, and license.
- **`resources/background/`** — Authentication background images.
- **`resources/menu-images/`** — Product photos referenced by menu records.

CSS class names are the bridge between templates and styling. For example, `checkout-layout` defines the checkout grid, `archive-toolbar` defines archive controls, and `location-modal` controls the map dialog.

## 11. Database structure

`database/cafecore.sql` creates the MySQL database and these tables:

- **`users`** — Account identity, password hash, role (`customer`, `staff`, or `admin`), phone/address, create time, and optional archive timestamp.
- **`categories`** — Unique category names.
- **`menu_items`** — Product name/description/price/category/image, availability/featured flags, and optional archive timestamp.
- **`orders`** — Customer, total, status, fulfillment type/address, create time, and optional archive timestamp.
- **`order_items`** — Snapshot of product name/quantity/price at order time. The menu-item link can become null if the original product is deleted, preserving the order line.
- **`payments`** — One payment row per order, including method, status, amount, and paid date.
- **`password_reset_tokens`** — Hashed OTP, expiry, attempt count, and owning user.

Relationships: one user can have many orders; a category can have many products; an order can have many item rows; and each order has one payment row. The ERD is in `database/cafecore-erd.svg` and `.png`.

## 12. Important request flows

### Adding a drink and checking out

1. Storefront links to `menu/item?id=...`.
2. `HomeController::detail()` loads the item and customization rules.
3. The product form posts its item ID, size, sugar, and quantity to `/cart/add`.
4. `CartController::add()` validates the product, then `Cart::add()` stores a session line.
5. `/checkout` calls `Cart::detailed()` to recalculate current product prices and totals.
6. The checkout POST calls `Order::create()`, which writes the order, order-item snapshots, and pending payment inside a transaction.
7. The cart clears only after the transaction succeeds.

### Changing an address with the map

The location icon opens a map. The browser may ask for location permission; otherwise the user can click/tap a point. The script sends coordinates to OpenStreetMap's Nominatim reverse-geocoding endpoint to get an address. “Use this location” fills the page's current address field. The profile and checkout use separate fields: the checkout picker changes the order form field, not the stored profile row.

### Staff updating an order

The staff page loads orders through `Order::forStaff()`. Staff POST a status/payment change with a CSRF token. `Order` validates allowed values and updates the relevant row. Marking a payment paid records `payment_date`; a completed and paid order is moved out of the active queue by setting `archived_at`.

### Generating reports

`ReportController` normalizes date filters. `Order::reportScope()` restricts report data to completed, paid orders in the date range. The view renders summary tables. Export builds four sheets and `ExcelExport` writes them as a downloadable `.xlsx` ZIP package.

## 13. Security and correctness notes in the current code

- POST forms generally include a CSRF field and controllers call `verify_csrf()`.
- Passwords and OTPs use PHP password hashing; authentication uses `password_verify()`.
- SQL values generally use PDO prepared statements.
- Templates should HTML-escape dynamic values with `e()` to reduce cross-site scripting risk.
- Role checks are in controller entry points and shared `require_*()` helpers.
- `Mailer.php` has source-code fallback SMTP credentials in addition to environment-variable configuration. Remove the fallback and rotate any real exposed SMTP credential; do not put secrets in documentation or commits.
- Checkout's browser script currently displays a 10% delivery charge, while `Order::create()` calculates a 2.5% delivery charge on the server. Those amounts do not match, so the shown checkout total can differ from the saved order total.
- `router.php` currently uses a misspelled request-URI server key, as noted above.
- The staff create-order controller currently points to a missing view file.

## 14. A good reading order

If you are learning how the code works, read it in this order:

1. `index.php`, `app/bootstrap.php`, `app/core/App.php`
2. `config/config.php`, `app/core/Controller.php`, `app/core/Database.php`
3. `app/controllers/HomeController.php` and `app/models/MenuItem.php`
4. `app/models/Cart.php`, `app/controllers/CartController.php`, and `app/models/Order.php`
5. `app/views/home/`, `app/views/checkout/`, and `app/views/history/`
6. `AuthController.php` and `User.php`
7. `StaffController.php`, `AdminController.php`, and the `admin/`/`staff/` views
8. `ReportController.php`, `Order::report*()`, `ExcelExport.php`, and `reports/index.html`
9. CSS and browser JavaScript once the request/data flow is clear

## 15. Detailed method-by-method walkthrough

This section follows the methods in the source and describes what each one does internally. PHP templates do not define classes; their PHP blocks call the shared helpers below and loop over data provided by controllers.

### Shared helper functions in `config/config.php`

- **`url($path)`** — Removes a leading slash from the route, prepends the application's configured base path, and returns a local URL.
- **`asset($path)`** — Uses `url()` to point a path into `public/`, where CSS, JavaScript, fonts, and images live.
- **`redirect($path)`** — Sends the browser to a local route using a `Location` response header, then exits so the old request cannot continue rendering.
- **`e($value)`** — Converts a value to a string and HTML-escapes it with quotes and UTF-8 handling. This is used when inserting data into HTML text or attributes.
- **`csrf_token()`** — Reuses the session's CSRF token if one exists; otherwise it creates a cryptographically random token and saves it in the session.
- **`csrf_field()`** — Produces a hidden form input containing the escaped CSRF token.
- **`verify_csrf()`** — Reads the posted token, compares it with the session token using `hash_equals()`, and stops with HTTP 419 if they do not match.
- **`old($key, $default)`** — Reads a previous form value from `$_SESSION['_old']`; it escapes the result before returning it for HTML.
- **`flash($key, $value)`** — With a non-null value, saves a one-time message in the session. With no value, reads and removes the saved message. Layouts call the read form when showing notices.
- **`auth()`** — Returns the user array currently stored in the session, or `null` for a visitor.
- **`is_admin()`** — Checks whether the session's role string is `admin`.
- **`require_auth()`** — Returns the session user. If nobody is signed in, it may save the requested GET route for return after login, stores an error message, and redirects to login.
- **`require_admin()`** — Calls `require_auth()`, then sends non-admin users away with an access error.
- **`require_staff()`** — Calls `require_auth()`, reloads the user record, invalidates a missing or archived account, refreshes the session from the current record, and allows only `staff` or `admin` roles.
- **`money($amount)`** — Formats a number to two decimal places with the peso sign.
- **`history_date_filter()`** — Validates a `YYYY-MM-DD` query value by parsing and round-tripping it. Invalid or absent dates fall back to the current date returned by `Order::reportDateDefaults()`.

### Core class methods

#### `App`

- **`__construct()`** — Opens the database connection, then immediately dispatches the current request.
- **`dispatch()`** — Reads the HTTP method and URL path; strips the configured base URL; ignores `public/` paths intended for static-file serving; finds the route handler; creates its controller; and calls the named method. If no method/path pair exists, it sends a 404.
- **`notFound()`** — Sets HTTP 404 and renders a simple “Page not found” message in the authentication layout.

#### `Controller`

- **`view($view, $data, $layout)`** — Makes array values available as local template variables, checks that the view file exists, buffers the view's HTML, then requires the chosen layout with `$content` set. Missing views return HTTP 500 and a short message.
- **`json($payload, $code)`** — Sets the HTTP status and JSON content type, encodes the payload, outputs it, and exits.

#### `Database`

- **`connect()`** — Returns the existing PDO connection if already made. Otherwise it reads DB environment variables, creates a MySQL connection with UTF-8, exception mode, associative results, and native prepares, then runs schema compatibility helpers.
- **`ensureArchiveColumns()`** — Checks `orders`, `menu_items`, and `users` for `archived_at`; adds the nullable timestamp column when missing.
- **`ensureOrderFulfillmentColumns()`** — Checks `orders` for `fulfillment_type` and `delivery_address`, adding them with defaults if the current database predates delivery support.
- **`ensurePasswordResetTable()`** — Creates the OTP/reset table and its foreign key to `users` if it does not yet exist.
- **`ensurePaymentsTable()`** — Creates the one-payment-per-order table if missing, then inserts a pending counter payment for any existing order without a payment row.

#### `Mailer`

- **`sendPasswordOtp($to, $otp)`** — Reads SMTP settings, opens a socket, validates the server greeting, sends SMTP `EHLO`, optionally negotiates TLS, authenticates, sets sender/recipient, sends an OTP email, then quits and closes the socket. Any unexpected server response throws an exception to the controller.
- **`command($socket, $command, $expected)`** — Writes one SMTP command followed by CRLF, then delegates response validation to `expect()`.
- **`expect($socket, $expected)`** — Reads complete SMTP response lines, extracts the numeric response code, and throws unless it is one of the expected codes.
- **`encodeHeader($value)`** — Encodes UTF-8 header text as MIME Base64 so accented or non-ASCII sender/subject text can be transmitted safely.

#### `ExcelExport`

- **`styled($value, $style)`** — Packages a cell value and its style index into an array that the sheet writer recognizes.
- **`download($filename, $sheets)`** — Checks that PHP zlib is available; builds workbook XML parts; writes one worksheet per supplied sheet; ZIP-compresses all parts; sets download headers; outputs the bytes; and exits.
- **`contentTypes($sheetCount)`** — Writes the OOXML content-type manifest and one worksheet entry per sheet.
- **`rootRelationships()`** — Connects the root package to `xl/workbook.xml`.
- **`workbookXml($sheetNames)`** — Writes the workbook document with escaped sheet names and relationship IDs.
- **`workbookRelationships($sheetCount)`** — Connects workbook relationship IDs to each worksheet and the shared styles document.
- **`stylesXml()`** — Defines the workbook's fonts, fills, borders, number formats, and cell style indexes, including peso and percentage formats.
- **`worksheetXml($rows, $options)`** — Walks rows and cells, writes numeric cells as numeric XML and text cells as escaped inline strings, then adds configured widths, auto-filters, frozen rows, merged cells, row heights, and worksheet dimensions.
- **`columnName($number)`** — Converts a 1-based integer to Excel letters (`1 → A`, `27 → AA`).
- **`columnNumber($name)`** — Converts Excel letters back to a 1-based integer; used when validating merged ranges and dimensions.
- **`xml($value)`** — Removes invalid XML control bytes and XML-escapes text.
- **`zip($files)`** — Compresses each in-memory workbook part and writes ZIP local headers, central-directory entries, checksums, offsets, and the end record.

### Controller methods

#### `HomeController`

- **`index()`** — Fetches featured items, currently available menu items, categories, and (only for signed-in users) the cart count. It passes those values to the home view.
- **`detail()`** — Requires sign-in; parses the item ID; looks up the item; rejects missing/unavailable items with a flash message; gets category-based customization rules; and renders the detail view.

#### `AuthController`

- **`login()`** — Redirects signed-in users based on role; otherwise renders the login view with the auth layout.
- **`loginPost()`** — Checks CSRF; reads email/password; saves email for error recovery; finds the user; rejects missing, archived, or wrong-password accounts; writes the minimal session user; clears old values; sets a welcome message; consumes the saved after-login route; and redirects to the appropriate workspace.
- **`signup()`** — Sends an already signed-in user to home; otherwise displays the registration form.
- **`signupPost()`** — Checks CSRF; reads values and saves name/email for recovery; validates required values, email format, password length, confirmation, and uniqueness; hashes and creates the customer; clears old values; then redirects to login with success feedback.
- **`forgot()`** — Renders the OTP page using the reset email and OTP-sent state held in the session.
- **`forgotPost()`** — First checks CSRF, then branches by hidden `action`. `send_otp` validates the email, finds the account, applies a 60-second session rate limit, creates a six-digit random code and ten-minute expiry, stores a hash, and sends email. If SMTP fails, it removes the fresh token and shows an error. `reset_password` verifies the session/email match, OTP shape, password rules, OTP expiry/attempt cap, and OTP hash. A valid code changes the password and clears reset state.
- **`logout()`** — Clears and destroys the current session, starts a fresh session for a success flash, and redirects home.

#### `CartController`

- **`index()`** — Requires a user, calculates the detailed cart, passes the user's saved address as the initial checkout text, and renders Checkout.
- **`add()`** — Requires sign-in and CSRF; parses product ID and fetches the product; rejects unavailable products; asks `MenuItem` whether size/sugar apply; validates or normalizes size, sugar, and quantity; adds the cart line; flashes success; and redirects to the supplied return path or home.
- **`update()`** — Requires sign-in and CSRF, passes the posted cart key and quantity to `Cart::set()`, then redirects to checkout.
- **`remove()`** — Requires sign-in and CSRF, removes the posted cart key, and redirects to checkout.
- **`place()`** — Requires sign-in and CSRF; accepts only the supported payment and fulfillment values; requires delivery address and non-empty cart; builds a catalog keyed by cart key; calls `Order::create()` inside its own transaction; clears the session cart only on success; and redirects to history. Exceptions are logged only by their generic user-facing handling; the customer receives an error flash and is sent back to checkout.

#### `OrderController`

- **`history()`** — Requires sign-in; routes staff/admin users through the staff guard and admin layout; reads the date and `completed=1`; requests either the user's current non-completed records or completed records from `Order::forUser()`; and renders the shared history view.
- **`archiveCustomerOrders()`** — Requires sign-in and customer role; checks CSRF; reads selected IDs and archive/restore operation; calls the model; flashes the number changed; preserves the date; and redirects to the old archived/current query. It remains available as a backend route even though the current customer page no longer offers an archive control.
- **`statuses()`** — Requires sign-in; sets no-cache headers; returns the user's order status rows as JSON.

#### `ProfileController`

- **`index()`** — Resolves a fresh current user, fetches profile data, gets cart count, and chooses the admin layout for staff/admin roles.
- **`update()`** — Resolves the user and checks CSRF; normalizes name/phone/address; validates name length and field bounds; saves the profile; refreshes the session; logs out if the account disappeared; then flashes success and redirects.
- **`currentUser()`** — Requires sign-in, reloads the DB row, clears invalid sessions, and synchronizes the session from the latest user row.

#### `AdminController`

- **`dashboard()`** — Requires admin; fetches aggregate order stats, customer count, menu count, and five recent orders; renders the admin dashboard.
- **`history()`** — Requires admin; validates the date, reads the archived tab flag, fetches up to 500 matching orders, and renders admin history.
- **`archiveOrders()`** — Requires admin and CSRF; reads IDs/operation; archives or restores rows through `Order::archiveMany()`; flashes the changed count; preserves date and archive-tab state in the redirect.
- **`staff()`** — Requires admin, reads archived state, gets staff account rows, and renders the staff manager.
- **`archiveStaff()`** — Requires admin/CSRF; reads IDs/operation; archives/restores them through `User`; reports the count and redirects to the matching tab.
- **`createStaff()`** — Requires admin/CSRF; validates name length, email, password length/confirmation, and uniqueness; calls `User::createStaff()`; converts duplicate-key database errors to a friendly message; then redirects with a result.
- **`deleteStaff()`** — Requires admin/CSRF; validates positive account ID; asks the model to delete transactionally; distinguishes not-found, has-orders, and deleted results in the flash message.
- **`menu()`** — Requires staff/admin, reads the archive tab, fetches menu items, and renders menu management.
- **`archiveMenu()`** — Requires staff/admin and CSRF; archives/restores selected IDs; shows a count and redirects to the proper menu tab.
- **`edit()`** — Requires staff/admin; reads an optional item ID; loads its data if present plus categories; renders the same form for create and edit.
- **`save()`** — Requires staff/admin and CSRF; normalizes form fields; validates required name, price, and category; preserves the old image on edit; validates upload error/size/MIME; creates the upload directory if needed; generates a safe lowercase filename; removes older extension variants for that name; moves the upload; saves the DB row; deletes the previous managed image only after the DB save; and redirects.
- **`toggle()`** — Requires staff/admin and CSRF; passes ID and field name to the model, which allow-lists toggleable columns; redirects to menu.
- **`delete()`** — Requires staff/admin and CSRF; permanently removes an item by ID, flashes success, and redirects.

#### `StaffController`

- **`orders()`** — Requires staff/admin; validates the status filter against allowed values; normalizes search; enables the today-only flag only for completed orders; reads archived state; fetches filtered orders and summary; renders the staff page.
- **`createOrder()`** — Requires staff/admin; loads available items and renders the staff create-order view.
- **`createOrderPost()`** — Requires staff/admin/CSRF; passes email/items/payment method to the model; returns validation errors to the form; logs unexpected errors; and on success shows the order number and redirects.
- **`updateStatus()`** — Requires staff/admin/CSRF; validates the ID and status through model return value; flashes the result and redirects to a safe return path.
- **`updatePayment()`** — Requires staff/admin/CSRF; passes order ID, payment status, and method to the model; reports success/failure and redirects to the filtered order list.
- **`updateOrder()`** — Requires staff/admin/CSRF; applies both status and payment changes together; reports the result and restores the relevant list view.
- **`ordersUrl()`** — Revalidates return status/search/today fields, drops empty query values, and builds a staff orders URL.
- **`statusReturnUrl()`** — Allows only the explicit history return paths; any other value falls back to `ordersUrl()`.
- **`postedString($key)`** — Returns a POST field only if PHP received it as a string; otherwise returns empty text.
- **`postedInt($key)`** — Returns a validated integer POST value, or zero when invalid.

#### `ReportController`

- **`index()`** — Requires staff/admin; prevents caching; normalizes requested report filters; calls `Order::report()`; and renders the report page.
- **`export()`** — Requires staff/admin; builds the same filtered order report plus category data; creates typed/styled rows for sales, category, menu, and order worksheets; calculates worksheet layout settings; then starts the `.xlsx` download.
- **`headerRow($labels)`** — Maps each header string to an Excel styled-cell object.
- **`mergedSectionRow($label, $columns)`** — Creates a blank formatted row and places the section title in its first cell; the export options merge the cells across the row.
- **`salesBreakdown($orders, $filters)`** — Creates date buckets for the selected period, maps every date in the range to its bucket, adds matching order count/value/paid amount to those buckets, and returns them in chronological order.
- **`filters()`** — Validates the period option, validates the anchor date, derives the daily/weekly/monthly/yearly start and end dates, and returns all four values.
- **`validDate($value, $fallback)`** — Accepts only a valid string in `YYYY-MM-DD` format; otherwise uses the fallback date.
- **`periodLabel($period)`** — Capitalizes the period name for the report workbook.

### Model methods

#### `Cart`

- **`items()`** — Returns the session cart or an empty array.
- **`count()`** — Adds up line quantities (not distinct products).
- **`makeKey($id, $size, $sugar)`** — Normalizes size/sugar to supported values and returns a stable `id|size|sugar` key.
- **`add($id, $qty, $size, $sugar)`** — Initializes the session cart if necessary, makes the normalized key, and adds a clamped quantity.
- **`set($key, $qty)`** — Removes the line for quantities below one; otherwise updates an existing line, capped at 99.
- **`remove($key)`** — Unsets one cart entry.
- **`clear()`** — Replaces the session cart with an empty array.
- **`detailed()`** — Splits each line key, reloads the current menu item, skips missing items, rechecks its customization rules, normalizes old/stale options, computes size extras and line amounts, accumulates subtotal, and returns structured lines and totals.

#### `MenuItem`

- **`customizationOptions($category)`** — Normalizes category text; detects food-like terms and beverage-like terms; returns true for size/sugar customization only when the category appears to be a beverage and not food.
- **`all($category, $availableOnly, $archived)`** — Selects items joined to categories, filters by archived status, optionally filters category and availability, then sorts featured first and name second.
- **`featured()`** — Returns up to four featured, available, non-archived products.
- **`find($id)`** — Fetches a single non-archived product plus category; returns null if none exists.
- **`categories()`** — Lists categories alphabetically.
- **`categoryExists($categoryId)`** — Uses a prepared existence query for form validation.
- **`save($data, $id)`** — Updates a supplied ID or inserts a new product; returns the retained/new ID.
- **`toggle($id, $field)`** — Does nothing unless the field is `available` or `featured`; otherwise flips its 0/1 value.
- **`delete($id)`** — Permanently deletes the row; order lines preserve their copied name and set menu reference null through the foreign key.
- **`archiveMany($ids, $restore)`** — Validates, deduplicates, and filters positive integer IDs; updates `archived_at` to now or null; returns affected row count.
- **`count()`** — Counts non-archived menu products.

#### `User`

- **`findByEmail($email)`** — Looks up one account by unique email and returns null if absent.
- **`find($id)`** — Looks up one account by ID.
- **`create($data)`** — Inserts a user with a password hash, default customer role, optional phone/address, and returns its ID.
- **`update($id, $data)`** — Updates the profile name, phone, and address fields.
- **`allStaff($archived)`** — Lists only staff-role records on the selected current/archive side.
- **`archiveStaff($ids, $restore)`** — Sanitizes selected IDs, then archives or restores only staff accounts; returns the number changed.
- **`createStaff($name, $email, $password)`** — Inserts a staff-role account with a hashed password.
- **`deleteStaff($id)`** — Starts a transaction, locks a matching staff row, checks whether the user owns any orders, refuses deletion if they do, otherwise deletes and commits. Any exception rolls back.
- **`count()`** — Counts customer-role accounts.
- **`createPasswordReset($userId, $otpHash, $expiresAt)`** — Deletes old reset codes for that user, then inserts the new hashed OTP and expiry with zero attempts.
- **`getPasswordReset($userId)`** — Returns the newest reset-token row, or null.
- **`incrementPasswordResetAttempts($id)`** — Adds one to the token's failed-attempt counter.
- **`updatePassword($userId, $password)`** — Stores a new password hash and deletes reset tokens for that user.
- **`toSession($user)`** — Extracts/casts only the session-safe user fields used by the app.

#### `Order`

- **`create($userId, $cart, $catalog, $paymentMethod, $fulfillmentType, $deliveryAddress)`** — Begins a transaction; rebuilds valid lines from the catalog; calculates item totals and option display names; rejects an empty order; adds the current server-side delivery charge; inserts the order; inserts each item snapshot; creates one pending payment; commits; and rolls back on any exception.
- **`createForCustomer($email, $requestedItems, $paymentMethod)`** — Validates email, payment method, and nonempty item input; begins a transaction; finds a customer account; locks/loads each product; validates quantity and drink options; combines duplicate customized keys; recalculates prices from the database; writes order/items/payment; commits; and rolls back on failure.
- **`forStaff($status, $search, $todayOnly, $archived)`** — Builds a query joining orders, customers, payments, and a grouped item summary; adds only allow-listed status, optional today range, optional name/email/order-ID search, archive condition, newest-first ordering, and a 100-row cap.
- **`staffSummary()`** — Counts all non-archived orders and totals preparing, completed-today, and cancelled counts with SQL aggregate expressions.
- **`updateStaffStatus($orderId, $status)`** — Rejects invalid ID/status; updates status; calls the settled-order archive helper; then verifies the stored status matches the requested value.
- **`updateStaffPayment($orderId, $paymentStatus, $paymentMethod)`** — Rejects invalid payment values or non-counter method; updates payment status/method; records the first paid timestamp or clears it when set back to pending; archives a completed-and-paid order; and confirms a payment row exists.
- **`archiveStaffOrderIfSettled($pdo, $orderId)`** — Sets the order's archive timestamp only when the order is completed and its payment is paid. `COALESCE` preserves an existing timestamp.
- **`updateStaffOrder($orderId, $status, $paymentStatus, $paymentMethod)`** — Validates both sets of values; begins a transaction; updates order and payment; verifies both rows exist; archives completed-and-paid orders; commits, or rolls back on failure.
- **`forUser($userId, $from, $to, $archived, $status, $excludeCompleted)`** — Selects one user's orders; if a status was supplied, filters by that status (used for Completed and intentionally includes archived rows); otherwise filters by archived state and can exclude completed records from Current orders. It applies date bounds, sorts newest first, then loads item and payment rows for every order.
- **`archiveForUser($userId, $ids, $restore)`** — Sanitizes IDs; for restore, clears archive timestamps for that user's rows; for archive, only marks eligible terminal statuses; returns affected count.
- **`statusesForUser($userId)`** — Returns only order IDs and statuses for the small live-refresh response.
- **`allRecent($limit)`** — Lists current/non-archived orders with customer names, newest first, optionally applying a bound limit.
- **`reportDateDefaults()`** — Reads January 1 and the current date from MySQL and returns them as `from` and `to`.
- **`allForAdmin($from, $to, $archived)`** — Joins orders with customers, payments, and grouped item names; applies archive/date filters; sorts newest first; and caps results at 500.
- **`archiveMany($ids, $restore)`** — Sanitizes selected IDs; clears archive timestamps for restore or sets them for eligible completed/cancelled/refunded orders; returns affected count.
- **`report($filters)`** — Gets safe date/status/payment conditions; fetches order rows with customer/payment details; fetches item names separately to avoid `GROUP_CONCAT` truncation; creates per-order summaries; totals order value and collected amount; groups daily/weekly/monthly/yearly buckets; and returns order, summary, and series arrays.
- **`reportCategorySales($filters)`** — Joins item lines to orders/products/categories, groups by category and item, and totals quantities and line revenue.
- **`reportScope($filters)`** — Produces the SQL conditions and bound dates used by report queries. The current report is limited to completed orders with paid payments.
- **`stats()`** — Calculates dashboard revenue/count/today totals; fills daily, weekly, monthly, and yearly series with zero-value periods; counts statuses; aggregates category units sold; packages all chart views; and returns one associative array.

### Main JavaScript functions

#### `public/js/app.js`

- **`matches()`** — Returns storefront cards matching the selected category or all cards.
- **`render()`** — Calculates page count, hides all cards, reveals the current filtered slice, clears/rebuilds pager buttons, and handles the no-pagination case.
- **`addButton()`** (inside the pager) — Creates a previous/next/page-number button, marks the current page for assistive tech, disables boundary buttons, and wires a click to update the page and rerender.
- **`openCard(event)`** — Navigates to the card URL unless the click originated inside an interactive link/button/form/input.
- The card keyboard listener opens the card on Enter or Space while preventing Space from scrolling the page.

#### `public/js/flash.js`

- Each toast gets a timeout; after 3.5 seconds the script adds the fade class and removes the element after the transition, with a backup timeout in case the transition event does not fire.

#### `public/js/bulk-archive.js`

- **`refresh()`** — Counts checked row boxes, enables/disables the submit button, updates the count label, and sets select-all checked/indeterminate state.
- Each row `change` event calls `refresh()`; select-all changes every row checkbox and then refreshes.

#### `public/js/location-picker.js`

- **`setStatus(message)`** — Writes a user-facing message into the live region.
- **`reverseGeocode(latlng)`** — Disables “Use this location,” calls Nominatim's reverse endpoint, stores its display address or coordinate fallback, updates status, and reenables the action.
- **`selectPoint(latlng)`** — Saves the chosen coordinates, moves or creates the map marker, and starts reverse geocoding.
- **`locateCurrentPosition()`** — Checks browser geolocation support; disables its button while requesting coordinates; on success zooms the map and calls `selectPoint()`; on failure shows a permission/device/timeout message and reenables the button.
- **`initMap()`** — Reuses an existing map after invalidating its size, or creates a Leaflet map with a Dagupan-area starting view, OpenStreetMap tiles, and click-to-select behavior.
- **Open-button handler** — Shows the modal, updates accessibility/body state, initializes the map, then asks for current position if the address is blank and no marker is already chosen.
- **`closeModal()`** — Hides the dialog and clears the body scroll lock.
- **Use-location handler** — Copies only the selected address into the current page's address field, dispatches an input event, then closes the dialog.
- Escape and close buttons are wired to `closeModal()`.

#### Page-inline JavaScript

- **Login/signup `togglePassword()`** — Switches password input(s) between masked and visible text based on the checkbox.
- **Product-detail `update()`** — Reads the selected size extra and updates the displayed product price; it runs after size changes and on initial load.
- **Checkout `formatMoney()`** — Formats browser-side amounts as PHP pesos.
- **Checkout `updateTotal()`** — Reads the selected fulfillment type, calculates the displayed delivery charge and total, toggles the address field, and toggles its required status.
- **History `renderPages()` / pager `addButton()`** — Hide non-current staff history cards and rebuild a compact pager.
- **History `refreshStatuses()`** — Fetches current statuses without using cache, maps results by order ID, and updates allowed status badges while keeping the page visible if the network request fails.
- **Admin menu `filteredRows()`** — Returns rows for the active category.
- **Admin menu `render()` / `addButton()`** — Paginates the filtered rows and creates boundary/current page buttons. Category button handlers update the selected category and reset to page one.
- **Admin history pager `render()` / `addButton()`** — Shows ten admin order cards at a time and rebuilds page controls.
- **Reports pager `render()` / `addButton()`** — Paginates report table rows for each report table independently.
- **Dashboard `renderEarnings(period)`** — Reads the embedded chart data, rebuilds the bars and labels for the selected period, updates the caption/accessible name, and toggles active period buttons.
- **Admin layout nav handler** — Opens/closes the mobile sidebar, updates `aria-expanded` and button icon/label, and closes the sidebar after a link is selected.

## 16. What “method-by-method” means here

The walkthrough explains each application method's purpose and the important steps/branches it performs. It does not annotate every punctuation mark, HTML attribute, CSS property, or bundled third-party Font Awesome rule. If you want to learn one file in literal line order, the most useful next step is to pick that file and ask for a line-by-line walkthrough of it.
