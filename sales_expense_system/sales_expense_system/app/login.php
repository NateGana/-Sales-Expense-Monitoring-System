<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Please enter both your username and password.';
    } else {
        try {
            $stmt = $conn->prepare(
                'SELECT user_id, username, password_hash, full_name, role, status
                 FROM users WHERE username = ? LIMIT 1'
            );
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            // Same generic message whether the username or the password is wrong -
            // this avoids confirming to an attacker which usernames exist.
            if (!$row || !password_verify($password, $row['password_hash'])) {
                $errors[] = 'Invalid username or password.';
            } elseif ($row['status'] !== 'active') {
                $errors[] = 'This account has been deactivated. Please contact the business owner.';
            } else {
                $_SESSION['user_id']   = (int) $row['user_id'];
                $_SESSION['username']  = $row['username'];
                $_SESSION['full_name'] = $row['full_name'];
                $_SESSION['role']      = $row['role'];
                session_regenerate_id(true);

                log_activity($conn, $row['user_id'], 'LOGIN', $row['full_name'] . ' logged in');
                header('Location: ' . base_url('dashboard.php'));
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            error_log('Login DB error: ' . $e->getMessage());
            $errors[] = 'Unable to log in right now. Please try again in a moment.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in - Sales & Expense Monitoring System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
  <div class="card shadow-sm p-4" style="width: 380px;">
    <h1 class="h4 mb-1 fw-semibold">Sales &amp; Expense Monitoring</h1>
    <p class="text-muted small mb-4">Sign in to continue.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-brand w-100">Log in</button>
    </form>

    <p class="text-muted small mt-3 mb-0">
      Demo accounts: <code>admin</code> / <code>Admin@123</code> (owner),
      <code>jstaff</code> / <code>Staff@123</code> (staff)
    </p>
  </div>
</body>
</html>
