<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle = 'Edit user';
$id = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$editUser = $stmt->get_result()->fetch_assoc();

if (!$editUser) {
    flash('error', 'That user could not be found.');
    header('Location: list.php');
    exit;
}

$errors = [];
$fullName = $editUser['full_name'];
$role = $editUser['role'];
$status = $editUser['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $role     = trim($_POST['role'] ?? '');
    $status   = trim($_POST['status'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        $errors[] = 'Please select a valid role.';
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Please select a valid status.';
    }
    if ($id === (int) currentUser()['user_id'] && ($role !== 'admin' || $status !== 'active')) {
        $errors[] = 'You cannot remove your own admin access or deactivate your own account.';
    }
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if (!$errors) {
        try {
            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $conn->prepare('UPDATE users SET full_name=?, role=?, status=?, password_hash=? WHERE user_id=?');
                $stmt->bind_param('ssssi', $fullName, $role, $status, $hash, $id);
            } else {
                $stmt = $conn->prepare('UPDATE users SET full_name=?, role=?, status=? WHERE user_id=?');
                $stmt->bind_param('sssi', $fullName, $role, $status, $id);
            }
            $stmt->execute();

            log_activity($conn, (int) currentUser()['user_id'], 'USER_UPDATED', "Updated user account #$id");
            flash('success', 'User account updated.');
            header('Location: list.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors[] = friendly_db_error($e);
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3">Edit user</h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<div class="card p-4" style="max-width: 520px;">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= $id ?>">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" class="form-control" value="<?= htmlspecialchars($editUser['username']) ?>" disabled>
    </div>
    <div class="mb-3">
      <label class="form-label">Full name *</label>
      <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($fullName) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Role *</label>
      <select name="role" class="form-select" required>
        <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Status *</label>
      <select name="status" class="form-select" required>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Reset password</label>
      <input type="password" name="new_password" class="form-control" minlength="8" placeholder="Leave blank to keep current password">
    </div>
    <button type="submit" class="btn btn-brand">Save changes</button>
    <a href="list.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
