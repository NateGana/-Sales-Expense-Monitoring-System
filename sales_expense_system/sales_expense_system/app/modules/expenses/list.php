<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Expenses';

$search    = trim($_GET['q'] ?? '');
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);

$sql = "SELECT e.expense_id, e.expense_date, e.description, e.amount, c.category_name, u.full_name AS recorded_by
        FROM expenses e
        JOIN expense_categories c ON c.category_id = e.category_id
        JOIN users u ON u.user_id = e.recorded_by
        WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND e.description LIKE CONCAT('%', ?, '%')";
    $params[] = $search; $types .= 's';
}
if ($dateFrom !== '') {
    $sql .= " AND e.expense_date >= ?";
    $params[] = $dateFrom; $types .= 's';
}
if ($dateTo !== '') {
    $sql .= " AND e.expense_date <= ?";
    $params[] = $dateTo; $types .= 's';
}
if ($categoryId > 0) {
    $sql .= " AND e.category_id = ?";
    $params[] = $categoryId; $types .= 'i';
}
$sql .= " ORDER BY e.expense_date DESC, e.expense_id DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$expenses = $stmt->get_result();

$categories = $conn->query('SELECT category_id, category_name FROM expense_categories ORDER BY category_name');

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Expenses</h1>
  <a href="add.php" class="btn btn-brand btn-sm">Record expense</a>
</div>

<form class="row g-2 mb-3 align-items-end" method="get">
  <div class="col-auto">
    <label class="form-label small mb-0">Search</label>
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Description"
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">From</label>
    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">To</label>
    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">Category</label>
    <select name="category_id" class="form-select form-select-sm">
      <option value="0">All categories</option>
      <?php while ($c = $categories->fetch_assoc()): ?>
        <option value="<?= $c['category_id'] ?>" <?= $categoryId === (int)$c['category_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['category_name']) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>
  <div class="col-auto">
    <button class="btn btn-sm btn-outline-secondary">Filter</button>
    <a href="list.php" class="btn btn-sm btn-link">Reset</a>
  </div>
</form>

<div class="card p-0">
  <table class="table mb-0 align-middle">
    <thead>
      <tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Recorded by</th><th></th></tr>
    </thead>
    <tbody>
      <?php if ($expenses->num_rows === 0): ?>
        <tr><td colspan="6" class="text-muted text-center py-4">No expenses found for these filters.</td></tr>
      <?php endif; ?>
      <?php while ($e = $expenses->fetch_assoc()): ?>
        <tr>
          <td><?= date('M j, Y', strtotime($e['expense_date'])) ?></td>
          <td><span class="badge text-bg-light border"><?= htmlspecialchars($e['category_name']) ?></span></td>
          <td><?= htmlspecialchars($e['description']) ?></td>
          <td class="fw-semibold"><?= peso($e['amount']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($e['recorded_by']) ?></td>
          <td class="text-end">
            <a href="edit.php?id=<?= $e['expense_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
            <?php if (isAdmin()): ?>
            <a href="delete.php?id=<?= $e['expense_id'] ?>" class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Delete this expense record?');">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
