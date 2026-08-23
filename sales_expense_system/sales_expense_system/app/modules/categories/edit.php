<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle = 'Edit expense category';
$id = (int) ($_GET['id'] ?? $_POST['category_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM expense_categories WHERE category_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();

if (!$category) {
    flash('error', 'That category could not be found.');
    header('Location: list.php');
    exit;
}

$errors = [];
$name = $category['category_name'];
$description = $category['description'];

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
            $stmt = $conn->prepare('UPDATE expense_categories SET category_name = ?, description = ? WHERE category_id = ?');
            $stmt->bind_param('ssi', $name, $description, $id);
            $stmt->execute();

            log_activity($conn, (int) currentUser()['user_id'], 'CATEGORY_UPDATED', "Updated expense category \"$name\"");
            flash('success', 'Category updated.');
            header('Location: list.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors[] = friendly_db_error($e);
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3">Edit expense category</h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<div class="card p-4" style="max-width: 520px;">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="category_id" value="<?= $id ?>">
    <div class="mb-3">
      <label class="form-label">Category name *</label>
      <input type="text" name="category_name" class="form-control" required maxlength="50"
             value="<?= htmlspecialchars($name) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="3" maxlength="150"><?= htmlspecialchars($description) ?></textarea>
    </div>
    <button type="submit" class="btn btn-brand">Save changes</button>
    <a href="list.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
