<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle = 'Add user';
$errors = [];

$username = $fullName = '';
$role = 'staff';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role     = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($username === '' || !preg_match('/^[a-zA-Z0-9._]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters and contain only letters, numbers, dots, or underscores.';
    }
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        $errors[] = 'Please select a valid role.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }

    if (!$errors) {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare(
                'INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('ssss', $username, $hash, $fullName, $role);
            $stmt->execute();

            log_activity($conn, (int) currentUser()['user_id'], 'USER_ADDED', "Added user account \"$username\" ($role)");
            flash('success', 'User account created.');
            header('Location: list.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors[] = friendly_db_error($e);
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3">Add user</h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<div class="card p-4" style="max-width: 520px;">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Full name *</label>
      <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($fullName) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Username *</label>
      <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($username) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Role *</label>
      <select name="role" class="form-select" required>
        <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Password *</label>
      <input type="password" name="password" class="form-control" required minlength="8">
      <div class="form-text">At least 8 characters.</div>
    </div>
    <div class="mb-3">
      <label class="form-label">Confirm password *</label>
      <input type="password" name="confirm_password" class="form-control" required minlength="8">
    </div>
    <button type="submit" class="btn btn-brand">Create account</button>
    <a href="list.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
