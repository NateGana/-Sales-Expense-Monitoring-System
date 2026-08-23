# Sales and Expense Monitoring System for Small Businesses
ITP104 - Information Management 2 | University of Cabuyao

## What's in this package
- `schema.sql` - the complete database (tables, constraints, seed data, stored procedures)
- `app/` - the full PHP/MySQL web application

## Setup (XAMPP)
1. Start Apache and MySQL in the XAMPP Control Panel.
2. Copy the `app` folder into `C:\xampp\htdocs\` and rename it to `sales_expense_system`
   (or any name you like - just update `BASE_URL` in `app/config/db.php` to match).
3. Open phpMyAdmin (`http://localhost/phpmyadmin`), click **Import**, and import `schema.sql`.
   This creates the `sales_expense_db` database with sample data and all stored procedures.
4. Open `app/config/db.php` and confirm `DB_USER` / `DB_PASS` match your MySQL root account
   (XAMPP defaults are `root` with an empty password - already set).
5. Visit `http://localhost/sales_expense_system/` in your browser.

## Demo accounts (from the seed data)
| Username | Password  | Role  |
|----------|-----------|-------|
| admin    | Admin@123 | admin (Business Owner) |
| jstaff   | Staff@123 | staff |

**Change these passwords (or add your own accounts via User Accounts) before your defense.**

## What's already built and tested
- Login/logout, session-based RBAC (admin vs staff), user account management
- Sales: full CRUD + a real ACID transaction (open sale -> add items -> finalize total ->
  commit, or rollback the whole thing on any failure) via `sp_open_sale`, `sp_add_sale_item`,
  `sp_finalize_sale`
- Expenses: full CRUD, category management (uses `sp_add_expense_category`, an IN/OUT
  stored procedure)
- Dashboard with real SQL-driven metrics via `sp_get_daily_summary` (uses `CASE` to classify
  the day as Healthy / Low margin / Loss / No sales yet)
- Search & filtering on Sales and Expenses (date range, payment method, category, keyword)
- Four printable reports: Daily Sales Summary, Monthly Financial Performance, Category-wise
  Expense Breakdown (`GROUP BY ... WITH ROLLUP`), Profit Estimation Statement
- Input validation (client + server), friendly error handling (no raw SQL errors shown),
  CSRF protection on every form, prepared statements everywhere, bcrypt password hashing

This was tested end-to-end on a live MySQL/PHP server before delivery: every form
submission, every report, and role restrictions were exercised with real HTTP requests -
not just read over for syntax.

## Still to do together (documentation phase)
The Documentation Requirements doc needs: Introduction & Background, Problem Statement,
Objectives, System Flowchart, screenshots of the actual running system, Data Dictionary,
and Test Cases (TC-01 onward). Once you've got this running locally and clicked through it,
send me a few screenshots and we'll build the full documentation around what you actually see.
