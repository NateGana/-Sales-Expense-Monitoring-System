<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$id = (int) ($_GET['id'] ?? 0);

try {
    $stmt = $conn->prepare('DELETE FROM expenses WHERE expense_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        log_activity($conn, (int) currentUser()['user_id'], 'EXPENSE_DELETED', "Deleted expense #$id");
        flash('success', 'Expense deleted.');
    } else {
        flash('error', 'That expense record could not be found.');
    }
} catch (mysqli_sql_exception $e) {
    flash('error', friendly_db_error($e));
}

header('Location: list.php');
exit;
