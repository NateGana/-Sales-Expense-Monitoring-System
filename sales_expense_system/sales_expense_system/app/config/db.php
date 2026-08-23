<?php
/**
 * Database connection.
 * Update DB_HOST / DB_USER / DB_PASS / DB_NAME for your XAMPP/MySQL setup.
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sales_expense_db');

/**
 * BASE_URL - the folder this app is served from, relative to your web root.
 * Example: if you place this project in htdocs/sales_expense_system,
 * keep it as '/sales_expense_system/'. Change it if you rename the folder.
 */
define('BASE_URL', '/sales_expense_system/');

// Make mysqli throw exceptions on error instead of silently returning false.
// This is what lets add.php / edit.php catch DB problems and show a friendly
// message instead of a raw SQL error (System Security & Error Handling requirement).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die('The system is temporarily unavailable. Please try again later.');
}

/**
 * Clears any pending result sets left behind by a CALL to a stored
 * procedure. MySQL always appends an extra "OK packet" result after a
 * CALL, even for procedures with no SELECT inside them - if it is not
 * drained, the next query on the same connection fails with
 * "Commands out of sync". Call this right after every stmt->execute()
 * that runs a CALL statement.
 */
function drain_multi_results(mysqli $conn): void
{
    while ($conn->more_results() && $conn->next_result()) {
        // discard
    }
}
