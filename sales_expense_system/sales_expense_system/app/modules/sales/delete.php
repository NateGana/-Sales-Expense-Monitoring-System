<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$id = (int) ($_GET['id'] ?? 0);

try {
    // sale_items rows are removed automatically via ON DELETE CASCADE.
    $stmt = $conn->prepare('DELETE FROM sales WHERE sale_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        log_activity($conn, (int) currentUser()['user_id'], 'SALE_DELETED', "Deleted sale #$id");
        flash('success', "Sale #$id and its items were deleted.");
    } else {
        flash('error', 'That sale could not be found.');
    }
} catch (mysqli_sql_exception $e) {
    flash('error', friendly_db_error($e));
}

header('Location: list.php');
exit;
