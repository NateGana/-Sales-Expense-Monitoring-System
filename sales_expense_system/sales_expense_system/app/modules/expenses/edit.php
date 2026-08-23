<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Edit expense';
$id = (int) ($_GET['id'] ?? $_POST['expense_id'] ?? 0);

$stmt = $conn->prepare('SELECT * FROM expenses WHERE expense_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$expense = $stmt->get_result()->fetch_assoc();

if (!$expense) {
    flash('error', 'That expense record could not be found.');
    header('Location: list.php');
    exit;
}

$errors = [];
$expenseDate = $expense['expense_date'];
$categoryId  = $expense['category_id'];
$description = $expense['description'];
$amount      = $expense['amount'];

$categories = $conn->query('SELECT category_id, category_name FROM expense_categories ORDER BY category_name');

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
            $amountF = (float) $amount;
            $stmt = $conn->prepare(
                'UPDATE expenses SET expense_date=?, category_id=?, description=?, amount=? WHERE expense_id=?'
            );
            $stmt->bind_param('sisdi', $expenseDate, $categoryId, $description, $amountF, $id);
            $stmt->execute();

            log_activity($conn, (int) currentUser()['user_id'], 'EXPENSE_UPDATED', "Updated expense #$id");
            flash('success', 'Expense updated.');
            header('Location: list.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors[] = friendly_db_error($e);
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3">Edit expense</h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<div class="card p-4" style="max-width: 560px;">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="expense_id" value="<?= $id ?>">
    <div class="mb-3">
      <label class="form-label">Expense date *</label>
      <input type="date" name="expense_date" class="form-control" required max="<?= date('Y-m-d') ?>"
             value="<?= htmlspecialchars($expenseDate) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Category *</label>
      <select name="category_id" class="form-select" required>
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
             value="<?= htmlspecialchars($description) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Amount (₱) *</label>
      <input type="number" name="amount" class="form-control" required min="0.01" step="0.01"
             value="<?= htmlspecialchars($amount) ?>">
    </div>
    <button type="submit" class="btn btn-brand">Save changes</button>
    <a href="list.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
