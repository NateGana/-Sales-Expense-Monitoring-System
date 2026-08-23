<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$id = (int) ($_GET['id'] ?? 0);

if ($id === (int) currentUser()['user_id']) {
    flash('error', 'You cannot delete your own account while logged in.');
    header('Location: list.php');
    exit;
}

try {
    $stmt = $conn->prepare('SELECT username FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    if (!$target) {
        flash('error', 'That user could not be found.');
    } else {
        $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        log_activity($conn, (int) currentUser()['user_id'], 'USER_DELETED', "Deleted user account \"{$target['username']}\"");
        flash('success', 'User account deleted.');
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1451) {
        flash('error', 'This user cannot be deleted because they have existing sales or expense records. Set their status to Inactive instead.');
    } else {
        flash('error', friendly_db_error($e));
    }
}

header('Location: list.php');
exit;
