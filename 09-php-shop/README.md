# Everyday Shop (PHP 8 / XAMPP)

A small PHP 8 and MySQL shop with a session cart, transactional checkout, admin order management, and customer order lookup.

## Requirements

- XAMPP with PHP 8, Apache, and MySQL/MariaDB
- PHP PDO MySQL extension enabled (`pdo_mysql`)

## Install

1. Put the project files in `C:\xampp\htdocs\swd\09-php-shop`.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin/`, select **Import**, and import `database.sql`. It creates the `swd_shop` database and its tables.
4. If your local MySQL credentials differ from XAMPP's default `root` account with an empty password, configure `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the Apache environment. `DB_NAME` defaults to `swd_shop`.
5. Seed the 15 sample products from PowerShell:

   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\swd\09-php-shop\seed.php
   ```

6. Generate an admin password hash with PHP CLI. Replace the example password with a unique, strong password:

   ```powershell
   C:\xampp\php\php.exe -r "echo password_hash('ReplaceWithALongUniquePassword', PASSWORD_DEFAULT), PHP_EOL;"
   ```

   Keep the generated hash private. Set it in Apache's environment (for example, in the XAMPP Apache configuration that applies to this site):

   ```apache
   SetEnv ADMIN_PASSWORD_HASH "$2y$10$paste_the_generated_hash_here"
   ```

   Add the complete hash as output, without changing its characters. Restart Apache after editing its configuration. Do not commit the hash or a plaintext password.

7. Visit the shop at `http://localhost/swd/09-php-shop/`. Admin sign-in is at `http://localhost/swd/09-php-shop/admin/login.php`.

## Features

- The session cart stores product IDs and quantities only. Prices and order totals are read and calculated from the database.
- Checkout locks product rows, verifies stock, creates the order and order items, and decrements stock within one transaction.
- Admin users can review and filter orders, change statuses, and cancel eligible orders. Cancelling restores stock transactionally and cannot be applied twice.
- Customers can look up an order at `http://localhost/swd/09-php-shop/order-lookup.php` using the order reference and checkout email.
- Today's admin sales include paid, shipped, and delivered orders; cancelled orders are excluded from sales and top-product totals.

## Database files

- `database.sql` is the import-ready schema export with the required database, tables, indexes, and initial categories.
- `schema.sql` is maintained alongside the export as the project schema.
- Product sample data is inserted separately by `seed.php`.

The admin password hash is configured outside the database using `ADMIN_PASSWORD_HASH`; no admin credentials are stored in either SQL file.
