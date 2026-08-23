<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle = 'Add expense category';
$errors = [];
$name = $description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = 'Category name is required.';
    } elseif (mb_strlen($name) > 50) {
        $errors[] = 'Category name must be 50 characters or fewer.';
    }

    if (!$errors) {
        try {
            $userId = (int) currentUser()['user_id'];
            $stmt = $conn->prepare('CALL sp_add_expense_category(?, ?, ?, @new_id, @message)');
            $stmt->bind_param('ssi', $name, $description, $userId);
            $stmt->execute();
            $stmt->close();
            drain_multi_results($conn);

            $res = $conn->query('SELECT @new_id AS new_id, @message AS message')->fetch_assoc();

            if ($res['new_id'] === null) {
                // Procedure itself detected a duplicate name and refused to insert.
                $errors[] = $res['message'];
            } else {
                log_activity($conn, $userId, 'CATEGORY_ADDED', "Added expense category \"$name\"");
                flash('success', $res['message']);
                header('Location: list.php');
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $errors[] = friendly_db_error($e);
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3">Add expense category</h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<div class="card p-4" style="max-width: 520px;">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Category name *</label>
      <input type="text" name="category_name" class="form-control" required maxlength="50"
             value="<?= htmlspecialchars($name) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="3" maxlength="150"><?= htmlspecialchars($description) ?></textarea>
    </div>
    <button type="submit" class="btn btn-brand">Save category</button>
    <a href="list.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
