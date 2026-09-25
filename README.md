# SHU Food V3 — Final Upgraded Restaurant Ordering System

A complete PHP + MySQL restaurant ordering project upgraded from the 2024 version.

## Main customer features

- Premium responsive homepage and navigation
- Categories, menu, search, category filter, price range filter, sorting and pagination
- Food detail page with live stock and ratings
- Customer registration/login with secure password hashing
- Editable username, full name, email, phone, address and profile image
- Multiple saved delivery addresses and default address
- User-specific cart with quantity/stock validation
- Wishlist
- Verified-purchase rating and review system
- Coupon codes with minimum order, expiry, usage limit and one-use-per-customer rules
- Secure checkout with server-side price/stock validation
- Free-delivery threshold
- Cash on Delivery
- Optional manual bKash/Nagad payment submission with transaction ID and admin verification
- Optional SSLCOMMERZ hosted online gateway for card/bank/mobile payments when merchant credentials are configured
- Order status tracking
- Customer cancellation for eligible unpaid orders, with stock and coupon restoration
- Order details and printable invoice

## Main admin features

- Separate secure admin authentication
- Dashboard KPIs: revenue, orders, customers, pending work and low-stock items
- Top-selling food analytics and order-status summary
- Administrator management
- Category management
- Food management with stock/inventory
- Customer search + block/unblock
- Coupon management
- Review moderation
- Order status and payment verification management

## Security upgrades

- PDO prepared statements
- `password_hash()` / `password_verify()`
- Legacy MD5 password upgrade on successful login only
- CSRF tokens for state-changing forms
- Role-separated customer/admin sessions
- Session ID regeneration after login/logout
- User-specific cart/order/address/wishlist access
- Output escaping with `htmlspecialchars()`
- Image MIME/type/size checks and random upload filenames
- Server-side price, coupon and inventory validation
- Database transactions for checkout/cancellation
- `.env` based configuration

## Requirements

- PHP 8.1+ recommended
- MySQL 5.7+/MySQL 8 or compatible MariaDB
- PHP PDO MySQL extension
- PHP Fileinfo extension
- PHP cURL extension only if SSLCOMMERZ is enabled
- XAMPP/WAMP/LAMP or another PHP web server

## Fresh installation

1. Copy the `rest_food_v3` folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL.
3. Open phpMyAdmin and create a database named `rest_foodv3`.
4. Import `database/schema.sql` into that database.
5. Check `.env`. The defaults are ready for a standard XAMPP setup.
6. Open `http://localhost/rest_food_v3/`.
7. Admin: `http://localhost/rest_food_v3/admin/login.php`.

### Default administrator

- Username: `admin`
- Password: `Admin@123`

Change this password after first login.

## Payment configuration

### Cash on Delivery

Works immediately.

### bKash / Nagad manual verification

These options stay hidden until a valid merchant number is configured in `.env`:

```
BKASH_NUMBER=01XXXXXXXXX
NAGAD_NUMBER=01XXXXXXXXX
```

Replace the placeholders with your own merchant numbers. The customer submits sender phone + transaction ID. The payment is stored as `Submitted`; an administrator must verify it before changing it to `Paid`.

### SSLCOMMERZ hosted online payment

The project includes an optional hosted-gateway integration and validation callback. It is disabled by default. Configure:

```
SSLCOMMERZ_ENABLED=true
SSLCOMMERZ_SANDBOX=true
SSLCOMMERZ_STORE_ID=your_store_id
SSLCOMMERZ_STORE_PASSWORD=your_store_password
```

For real gateway callbacks/IPN, `APP_URL` must point to a publicly reachable web server. A localhost URL cannot receive external gateway callbacks. Test in sandbox before production.

## Coupon samples

Fresh database installs include:

- `WELCOME10`
- `SAVE50`

They can be edited/disabled from Admin → Coupons.

## Important database note

`database/schema.sql` is a fresh-install schema and recreates the project tables. Back up an existing production database before importing it. For this upgraded student/portfolio version, a fresh `rest_foodv3` database is recommended.

## Project structure

- `app/` — shared helper/security/payment functions
- `config/` — environment and PDO connection
- `database/` — complete SQL schema + seed data
- `partials-font/` — customer layout
- `admin/` — admin dashboard and management pages
- `css/v3.css` — customer design system
- `css/admin-v3.css` — admin design
- `assets/js/app.js` — navigation/payment UI interactions
- `images/` — project and uploaded images

## Production checklist

Before public deployment: set `APP_ENV=production`, use HTTPS, use a strong database password, change the default admin password, configure real merchant numbers/credentials, restrict upload directory execution at web-server level, and keep `.env` out of version control.
