<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Record expense';
$errors = [];

$expenseDate = date('Y-m-d');
$categoryId = '';
$description = '';
$amount = '';

$categories = $conn->query('SELECT category_id, category_name FROM expense_categories ORDER BY category_name');
$categoryCount = $categories->num_rows;
$categories->data_seek(0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $expenseDate = trim($_POST['expense_date'] ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $amount      = trim($_POST['amount'] ?? '');

    if ($expenseDate === '' || !DateTime::createFromFormat('Y-m-d', $expenseDate)) {
        $errors[] = 'Please provide a valid expense date.';
    } elseif ($expenseDate > date('Y-m-d')) {
        $errors[] = 'Expense date cannot be in the future.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Please select an expense category.';
    }
    if ($description === '') {
        $errors[] = 'Please describe what this expense was for.';
    } elseif (mb_strlen($description) > 150) {
        $errors[] = 'Description must be 150 characters or fewer.';
    }
    if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
        $errors[] = 'Amount must be a positive number.';
    }

    if (!$errors) {
        try {
            $userId = (int) currentUser()['user_id'];
            $amountF = (float) $amount;

            $stmt = $conn->prepare(
                'INSERT INTO expenses (expense_date, category_id, description, amount, recorded_by)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sisdi', $expenseDate, $categoryId, $description, $amountF, $userId);
            $stmt->execute();

            log_activity($conn, $userId, 'EXPENSE_RECORDED', "Recorded expense - $description (" . peso($amountF) . ')');
            flash('success', 'Expense recorded.');
            header('Location: list.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors[] = friendly_db_error($e);
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3">Record expense</h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<?php if ($categoryCount === 0): ?>
  <div class="alert alert-warning">
    No expense categories exist yet.
    <?php if (isAdmin()): ?><a href="../categories/add.php">Add one first</a>.<?php else: ?>Please ask the business owner to add one.<?php endif; ?>
  </div>
<?php endif; ?>

<div class="card p-4" style="max-width: 560px;">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Expense date *</label>
      <input type="date" name="expense_date" class="form-control" required max="<?= date('Y-m-d') ?>"
             value="<?= htmlspecialchars($expenseDate) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Category *</label>
      <select name="category_id" class="form-select" required>
        <option value="">Select a category</option>
        <?php while ($c = $categories->fetch_assoc()): ?>
          <option value="<?= $c['category_id'] ?>" <?= (int)$categoryId === (int)$c['category_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['category_name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Description *</label>
      <input type="text" name="description" class="form-control" required maxlength="150"
             value="<?= htmlspecialchars($description) ?>" placeholder="e.g. October stall rent">
    </div>
    <div class="mb-3">
      <label class="form-label">Amount (₱) *</label>
      <input type="number" name="amount" class="form-control" required min="0.01" step="0.01"
             value="<?= htmlspecialchars($amount) ?>">
    </div>
    <button type="submit" class="btn btn-brand" <?= $categoryCount === 0 ? 'disabled' : '' ?>>Save expense</button>
    <a href="list.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
