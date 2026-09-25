# 🍽️ SHU Food — Restaurant Food Ordering & Management System

A responsive full-stack restaurant food ordering and management system built with **PHP, MySQL, HTML, CSS, and JavaScript**.

SHU Food provides a complete customer ordering experience together with a dedicated admin panel for managing foods, categories, customers, coupons, reviews, inventory, orders, and payments.

---

## ✨ Features

### 👤 Customer
- Secure registration and login
- Responsive customer homepage
- Browse foods and categories
- Search, filter, sort, and paginate foods
- Food details page
- Add to cart and update quantities
- Wishlist system
- Coupon support
- Secure checkout
- Cash on Delivery
- bKash / Nagad manual payment workflow
- Optional SSLCommerz integration
- Order history and order details
- Order cancellation where applicable
- Printable invoice
- Rating and review system
- Customer profile dashboard
- Edit profile information
- Profile photo upload
- Change password
- Multiple saved delivery addresses
- Default delivery address selection

### 🛠️ Admin
- Separate secure admin authentication
- Dashboard statistics
- Revenue and order overview
- Manage foods
- Manage categories
- Manage inventory / stock
- Manage customers
- Block / unblock customers
- Manage coupons
- Moderate reviews
- Manage orders
- Update order status
- Verify manual payments
- Low-stock monitoring
- Basic sales analytics

### 🔐 Security
- PDO prepared statements
- Password hashing with `password_hash()`
- CSRF protection
- Role-based authentication
- Secure customer/admin session separation
- Server-side price verification
- Server-side coupon validation
- Output escaping
- Secure image upload validation
- Environment-based configuration
- `.env` excluded from Git

---

## 🧰 Technologies Used

- PHP
- MySQL / MariaDB
- HTML5
- CSS3
- JavaScript
- PDO
- Apache
- XAMPP

---

## 📁 Project Structure

```text
rest_food_v3/
├── admin/
├── app/
├── assets/
├── config/
├── css/
├── database/
│   └── schema.sql
├── images/
├── partials-font/
├── .env.example
├── .gitignore
├── .htaccess
├── index.php
├── login.php
├── register.php
├── cart.php
├── order.php
├── profile.php
├── wishlist.php
├── README.md
└── ...
```

---

## 🚀 Installation

### 1. Clone the repository

```bash
git clone https://github.com/woazkuruni/rest_food_v3.git
```

Move the project into your XAMPP `htdocs` directory:

```text
C:\xampp\htdocs\rest_food_v3
```

### 2. Start XAMPP

Start:
- Apache
- MySQL

### 3. Create the database

Open:

```text
http://localhost/phpmyadmin
```

Create a database named:

```text
rest_foodv3
```

Then import:

```text
database/schema.sql
```

### 4. Configure environment variables

Copy:

```text
.env.example
```

to:

```text
.env
```

Example configuration:

```env
APP_NAME=SHU Food
APP_URL=http://localhost/rest_food_v3/
APP_ENV=local

DB_HOST=localhost
DB_PORT=3306
DB_NAME=rest_foodv3
DB_USER=root
DB_PASSWORD=

BKASH_NUMBER=01XXXXXXXXX
NAGAD_NUMBER=01XXXXXXXXX

SSLCOMMERZ_ENABLED=false
SSLCOMMERZ_SANDBOX=true
SSLCOMMERZ_STORE_ID=
SSLCOMMERZ_STORE_PASSWORD=
```

> Never commit your real `.env` file or live payment credentials.

### 5. Run the application

Customer website:

```text
http://localhost/rest_food_v3/
```

Admin panel:

```text
http://localhost/rest_food_v3/admin/login.php
```

---

## 🔑 Default Local Admin

For the seeded local development database:

```text
Username: admin
Password: Admin@123
```

> Change the default password before using the project outside local development.

---

## 💳 Payment Configuration

### Cash on Delivery
Works without additional configuration.

### bKash / Nagad
Set your merchant numbers in `.env`:

```env
BKASH_NUMBER=01XXXXXXXXX
NAGAD_NUMBER=01XXXXXXXXX
```

The project supports a manual transaction-reference verification workflow.

### SSLCommerz
To enable SSLCommerz:

```env
SSLCOMMERZ_ENABLED=true
SSLCOMMERZ_SANDBOX=true
SSLCOMMERZ_STORE_ID=your_store_id
SSLCOMMERZ_STORE_PASSWORD=your_store_password
```

Use your own sandbox or production credentials.

---

## 📸 Screenshots

Add screenshots to:

```text
docs/screenshots/
```

Recommended screenshots:
- Homepage
- Food menu
- Food details
- Cart
- Checkout
- Customer profile
- Order history
- Admin dashboard
- Food management
- Order management

Example:

```md
![Homepage](docs/screenshots/homepage.png)
```

---

## 🧪 Suggested Test Flow

1. Register a customer account
2. Login
3. Add foods to cart
4. Update quantity
5. Apply a coupon
6. Complete checkout
7. View order history
8. Open invoice
9. Update profile
10. Add a wishlist item
11. Login as admin
12. Update the order status
13. Verify payment where applicable

---

## 🔒 Git Ignore

The project intentionally excludes local secrets and user-uploaded profile images:

```gitignore
.env

# User uploaded profile images
/images/users/*
!/images/users/default_profile.webp

# OS files
Thumbs.db
.DS_Store

# Log files
*.log

# VS Code local settings
.vscode/
```

`.env.example` remains in the repository so other developers can configure the application safely.

---

## 📌 Notes

- GitHub Pages cannot run this application because it requires PHP and MySQL.
- Run it locally through XAMPP or deploy it to a PHP/MySQL-compatible hosting service.
- Do not commit real API keys, merchant secrets, database passwords, or production credentials.

---

## 👨‍💻 Author

**woazkuruni**

GitHub: [github.com/woazkuruni](https://github.com/woazkuruni)

---

## 📄 License

No license has been added yet. If you want others to reuse or modify the project, consider adding an appropriate open-source license.
