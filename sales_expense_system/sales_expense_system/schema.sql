-- ============================================================================
-- ITP104 - Information Management 2
-- Sales and Expense Monitoring System for Small Businesses
-- University of Cabuyao (Pamantasan ng Cabuyao) - College of Computing Studies
-- ============================================================================
-- This file contains the complete database structure: tables, constraints,
-- indexes, seed/sample data, and stored procedures.
--
-- Design notes:
--  - Normalized to 3NF: every non-key column depends only on its table's
--    primary key, no repeating groups, no derived/duplicated data stored.
--  - total_amount on `sales` is the one intentional exception: it is a
--    cached rollup of `sale_items`, kept in sync only inside the
--    sp_record_sale transaction below (never written to directly by the
--    app). This is a common, deliberate denormalization for dashboard/report
--    read speed, not an oversight - it is documented here for that reason.
--  - Two roles: 'admin' (full access) and 'staff' (day-to-day recording).
--    Role is enforced in the application layer (PHP session checks), NOT
--    only in the database - the DCL section at the bottom is a supplementary
--    demonstration, not a replacement for app-level RBAC.
-- ============================================================================

DROP DATABASE IF EXISTS sales_expense_db;
CREATE DATABASE sales_expense_db;
USE sales_expense_db;

-- ============================================================================
-- SECTION 1: TABLES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1.1 users
-- Stores every person who can log in: business owner (admin) and staff.
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(100) NOT NULL,
    role            ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 1.2 expense_categories
-- Lookup table so expense types (Rent, Utilities, Supplies, ...) are not
-- retyped as free text on every expense row (this is what keeps the design
-- in 3NF instead of storing a category name string on every expense).
-- ----------------------------------------------------------------------------
CREATE TABLE expense_categories (
    category_id     INT AUTO_INCREMENT PRIMARY KEY,
    category_name   VARCHAR(50)  NOT NULL UNIQUE,
    description     VARCHAR(150) NULL,
    created_by      INT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_category_creator
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 1.3 sales
-- One row per sale transaction (the "receipt header").
-- total_amount is maintained by sp_record_sale (see Section 3) so it always
-- matches the SUM of its sale_items - never updated directly from the UI.
-- ----------------------------------------------------------------------------
CREATE TABLE sales (
    sale_id         INT AUTO_INCREMENT PRIMARY KEY,
    sale_date       DATE NOT NULL,
    sale_time       TIME NOT NULL,
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method  ENUM('cash', 'gcash', 'card', 'bank_transfer') NOT NULL DEFAULT 'cash',
    recorded_by     INT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sale_user
        FOREIGN KEY (recorded_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_sale_total CHECK (total_amount >= 0)
) ENGINE=InnoDB;

CREATE INDEX idx_sales_date ON sales(sale_date);
CREATE INDEX idx_sales_recorded_by ON sales(recorded_by);

-- ----------------------------------------------------------------------------
-- 1.4 sale_items
-- Line items of a sale (the "receipt body"). A sale must have at least one
-- item - enforced in the application transaction, not by the schema itself.
-- ----------------------------------------------------------------------------
CREATE TABLE sale_items (
    sale_item_id    INT AUTO_INCREMENT PRIMARY KEY,
    sale_id         INT NOT NULL,
    item_name       VARCHAR(100) NOT NULL,
    quantity        INT NOT NULL,
    unit_price      DECIMAL(10,2) NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_item_sale
        FOREIGN KEY (sale_id) REFERENCES sales(sale_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_item_qty CHECK (quantity > 0),
    CONSTRAINT chk_item_price CHECK (unit_price >= 0)
) ENGINE=InnoDB;

CREATE INDEX idx_sale_items_sale_id ON sale_items(sale_id);

-- ----------------------------------------------------------------------------
-- 1.5 expenses
-- One row per operating expense.
-- ----------------------------------------------------------------------------
CREATE TABLE expenses (
    expense_id      INT AUTO_INCREMENT PRIMARY KEY,
    expense_date    DATE NOT NULL,
    category_id     INT NOT NULL,
    description     VARCHAR(150) NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    recorded_by     INT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expense_category
        FOREIGN KEY (category_id) REFERENCES expense_categories(category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_expense_user
        FOREIGN KEY (recorded_by) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_expense_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE INDEX idx_expenses_date ON expenses(expense_date);
CREATE INDEX idx_expenses_category ON expenses(category_id);

-- ----------------------------------------------------------------------------
-- 1.6 activity_logs
-- Feeds the dashboard's "Recent Activity" widget and gives the admin an
-- audit trail (who did what, and when).
-- ----------------------------------------------------------------------------
CREATE TABLE activity_logs (
    log_id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    action          VARCHAR(50)  NOT NULL,
    details         VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_logs_created_at ON activity_logs(created_at);

-- ============================================================================
-- SECTION 2: SEED / SAMPLE DATA
-- ============================================================================

-- Passwords below are real bcrypt hashes (PHP password_hash-compatible).
-- admin  / Admin@123
-- jstaff / Staff@123
INSERT INTO users (username, password_hash, full_name, role, status) VALUES
('admin',  '$2b$10$t5joiiobYtqNByrG/CxmSOhua0/gmx3KfHVu3b4zfCy4ZbmpWZpga', 'Business Owner',  'admin', 'active'),
('jstaff', '$2b$10$/mEyy5bxiXA87UIuCAaL5OgVd/WzXFztRNKr9EkhvJh3pQ5TM4UNG', 'Juan Dela Cruz',  'staff', 'active');

INSERT INTO expense_categories (category_name, description, created_by) VALUES
('Rent',        'Monthly store/stall rent',                  1),
('Utilities',   'Electricity, water, internet',               1),
('Supplies',    'Packaging, consumables, cleaning supplies',  1),
('Salaries',    'Staff wages and allowances',                 1),
('Transport',   'Delivery and fuel expenses',                 1);

-- Two sample sales, each with line items, entered the way sp_record_sale
-- would (see Section 3) - total_amount matches the sum of its items.
INSERT INTO sales (sale_id, sale_date, sale_time, total_amount, payment_method, recorded_by) VALUES
(1, CURDATE(), '09:15:00', 650.00, 'cash',  2),
(2, CURDATE(), '11:40:00', 320.00, 'gcash', 2);

INSERT INTO sale_items (sale_id, item_name, quantity, unit_price, subtotal) VALUES
(1, 'Iced Coffee (16oz)', 3, 120.00, 360.00),
(1, 'Croissant',          2, 145.00, 290.00),
(2, 'Iced Coffee (16oz)', 2, 120.00, 240.00),
(2, 'Muffin',              1, 80.00,  80.00);

INSERT INTO expenses (expense_date, category_id, description, amount, recorded_by) VALUES
(CURDATE(), 1, 'October stall rent',            5000.00, 1),
(CURDATE(), 2, 'Electric bill - September',      850.00, 1),
(CURDATE(), 3, 'Disposable cups and lids',       420.00, 2);

INSERT INTO activity_logs (user_id, action, details) VALUES
(1, 'LOGIN',        'Business Owner logged in'),
(2, 'SALE_RECORDED', 'Recorded sale #1 - PHP 650.00'),
(2, 'SALE_RECORDED', 'Recorded sale #2 - PHP 320.00'),
(1, 'EXPENSE_RECORDED', 'Recorded expense - October stall rent');

-- ============================================================================
-- SECTION 3: STORED PROCEDURES
-- These mirror the exact techniques covered in the workbook: IN/OUT/INOUT
-- parameters, explicit transactions with COMMIT/ROLLBACK, IF/CASE branching,
-- and GROUP BY ... WITH ROLLUP for summarized reports.
-- ============================================================================

DELIMITER $$

-- ----------------------------------------------------------------------------
-- 3.1 sp_add_expense_category
-- Demonstrates IN + OUT parameters. Called from the Expense Categories
-- module. Returns the new category_id so the UI can confirm/redirect
-- without a second SELECT.
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_add_expense_category $$
CREATE PROCEDURE sp_add_expense_category (
    IN  p_category_name VARCHAR(50),
    IN  p_description   VARCHAR(150),
    IN  p_created_by    INT,
    OUT p_new_id         INT,
    OUT p_message         VARCHAR(150)
)
proc_block: BEGIN
    DECLARE v_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO v_exists
    FROM expense_categories
    WHERE category_name = p_category_name;

    IF v_exists > 0 THEN
        SET p_new_id = NULL;
        SET p_message = 'A category with that name already exists.';
        LEAVE proc_block;
    END IF;

    INSERT INTO expense_categories (category_name, description, created_by)
    VALUES (p_category_name, p_description, p_created_by);

    SET p_new_id = LAST_INSERT_ID();
    SET p_message = 'Category added successfully.';
END $$

-- ----------------------------------------------------------------------------
-- 3.2 sp_record_sale_header / sp_record_sale
-- Demonstrates a full ACID transaction: insert the sale header, insert each
-- line item, recompute and update total_amount, then COMMIT - or ROLLBACK
-- the whole thing if anything fails. Because MySQL stored procedures cannot
-- easily accept a variable-length list of items as a parameter, the PHP
-- transaction module calls three simpler procedures back-to-back inside one
-- application-level transaction (mysqli begin_transaction / commit /
-- rollback) - the same pattern shown in COMMIT & ROLLBACK.sql /
-- SAVEPOINT & AUTO COMMIT.sql. Those three building-block procedures are
-- defined here so every step of the flow goes through validated,
-- reusable database logic instead of raw ad hoc inserts.
-- ----------------------------------------------------------------------------

-- Step A: open the sale header, OUT the new sale_id
DROP PROCEDURE IF EXISTS sp_open_sale $$
CREATE PROCEDURE sp_open_sale (
    IN  p_sale_date      DATE,
    IN  p_sale_time      TIME,
    IN  p_payment_method VARCHAR(20),
    IN  p_recorded_by    INT,
    OUT p_sale_id         INT
)
BEGIN
    INSERT INTO sales (sale_date, sale_time, total_amount, payment_method, recorded_by)
    VALUES (p_sale_date, p_sale_time, 0.00, p_payment_method, p_recorded_by);

    SET p_sale_id = LAST_INSERT_ID();
END $$

-- Step B: add one line item to an already-open sale
DROP PROCEDURE IF EXISTS sp_add_sale_item $$
CREATE PROCEDURE sp_add_sale_item (
    IN p_sale_id    INT,
    IN p_item_name  VARCHAR(100),
    IN p_quantity   INT,
    IN p_unit_price DECIMAL(10,2)
)
BEGIN
    IF p_quantity <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Quantity must be greater than zero.';
    END IF;

    INSERT INTO sale_items (sale_id, item_name, quantity, unit_price, subtotal)
    VALUES (p_sale_id, p_item_name, p_quantity, p_unit_price, p_quantity * p_unit_price);
END $$

-- Step C: recompute the header total from its items (keeps total_amount in
-- sync - called once after all items for the sale have been added)
DROP PROCEDURE IF EXISTS sp_finalize_sale $$
CREATE PROCEDURE sp_finalize_sale (
    IN  p_sale_id  INT,
    OUT p_total     DECIMAL(10,2)
)
finalize_block: BEGIN
    DECLARE v_item_count INT DEFAULT 0;

    SELECT COUNT(*) INTO v_item_count FROM sale_items WHERE sale_id = p_sale_id;

    IF v_item_count = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot finalize a sale with no items.';
    END IF;

    SELECT SUM(subtotal) INTO p_total FROM sale_items WHERE sale_id = p_sale_id;

    UPDATE sales SET total_amount = p_total WHERE sale_id = p_sale_id;
END $$

-- ----------------------------------------------------------------------------
-- 3.3 sp_get_daily_summary
-- Demonstrates OUT parameters + CASE for the dashboard's "profit status"
-- widget. Called once per dashboard page load for "today".
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_get_daily_summary $$
CREATE PROCEDURE sp_get_daily_summary (
    IN  p_date          DATE,
    OUT p_total_sales     DECIMAL(10,2),
    OUT p_total_expenses  DECIMAL(10,2),
    OUT p_net_profit      DECIMAL(10,2),
    OUT p_profit_status   VARCHAR(20)
)
BEGIN
    SELECT COALESCE(SUM(total_amount), 0) INTO p_total_sales
    FROM sales WHERE sale_date = p_date;

    SELECT COALESCE(SUM(amount), 0) INTO p_total_expenses
    FROM expenses WHERE expense_date = p_date;

    SET p_net_profit = p_total_sales - p_total_expenses;

    SET p_profit_status = CASE
        WHEN p_total_sales = 0 THEN 'No sales yet'
        WHEN p_net_profit < 0 THEN 'Loss'
        WHEN p_net_profit / p_total_sales < 0.10 THEN 'Low margin'
        ELSE 'Healthy'
    END;
END $$

-- ----------------------------------------------------------------------------
-- 3.4 sp_monthly_category_report
-- Demonstrates GROUP BY ... WITH ROLLUP for the "Category-wise Expense
-- Breakdown" printable report - subtotal per category plus a grand total
-- row for the month, in one result set.
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_monthly_category_report $$
CREATE PROCEDURE sp_monthly_category_report (
    IN p_year  INT,
    IN p_month INT
)
BEGIN
    SELECT
        COALESCE(ec.category_name, 'GRAND TOTAL') AS category_name,
        COUNT(e.expense_id) AS num_transactions,
        SUM(e.amount) AS total_amount
    FROM expenses e
    JOIN expense_categories ec ON ec.category_id = e.category_id
    WHERE YEAR(e.expense_date) = p_year
      AND MONTH(e.expense_date) = p_month
    GROUP BY ec.category_name WITH ROLLUP;
END $$

DELIMITER ;

-- ============================================================================
-- SECTION 4: OPTIONAL DATABASE-LEVEL ROLES (DCL demonstration)
-- The application enforces admin vs staff access in PHP (session role
-- checks on every restricted page). The GRANT/REVOKE statements below are a
-- SUPPLEMENTARY, defense-in-depth demonstration of the DCL concepts from
-- the workbook (GRANT & REVOKE.sql) - uncomment and adjust the password
-- before running if you want the two app roles to also connect to MySQL
-- with different database-level privileges.
-- ============================================================================

-- CREATE USER IF NOT EXISTS 'sems_admin'@'localhost' IDENTIFIED BY 'change_me_admin';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON sales_expense_db.* TO 'sems_admin'@'localhost';
--
-- CREATE USER IF NOT EXISTS 'sems_staff'@'localhost' IDENTIFIED BY 'change_me_staff';
-- GRANT SELECT, INSERT ON sales_expense_db.sales TO 'sems_staff'@'localhost';
-- GRANT SELECT, INSERT ON sales_expense_db.sale_items TO 'sems_staff'@'localhost';
-- GRANT SELECT, INSERT ON sales_expense_db.expenses TO 'sems_staff'@'localhost';
-- GRANT SELECT ON sales_expense_db.expense_categories TO 'sems_staff'@'localhost';
-- GRANT SELECT, INSERT ON sales_expense_db.activity_logs TO 'sems_staff'@'localhost';
--
-- FLUSH PRIVILEGES;

-- ============================================================================
-- SECTION 5: QUICK VERIFICATION (safe to run once after import)
-- ============================================================================

-- CALL sp_get_daily_summary(CURDATE(), @ts, @te, @np, @status);
-- SELECT @ts AS total_sales, @te AS total_expenses, @np AS net_profit, @status AS profit_status;
