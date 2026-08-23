<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$id = (int) ($_GET['id'] ?? 0);

try {
    $stmt = $conn->prepare('SELECT category_name FROM expense_categories WHERE category_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();

    if (!$category) {
        flash('error', 'That category could not be found.');
    } else {
        $stmt = $conn->prepare('DELETE FROM expense_categories WHERE category_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        log_activity($conn, (int) currentUser()['user_id'], 'CATEGORY_DELETED', "Deleted expense category \"{$category['category_name']}\"");
        flash('success', 'Category deleted.');
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1451) {
        // FK RESTRICT: expenses still reference this category.
        flash('error', 'This category cannot be deleted because it is used by existing expenses. Edit or delete those expenses first.');
    } else {
        flash('error', friendly_db_error($e));
    }
}

header('Location: list.php');
exit;
