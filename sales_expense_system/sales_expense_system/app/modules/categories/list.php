<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle = 'Expense categories';

$search = trim($_GET['q'] ?? '');
$sql = "SELECT c.category_id, c.category_name, c.description, u.full_name AS created_by, c.created_at,
               (SELECT COUNT(*) FROM expenses e WHERE e.category_id = c.category_id) AS usage_count
        FROM expense_categories c
        JOIN users u ON u.user_id = c.created_by";
$params = [];
$types = '';
if ($search !== '') {
    $sql .= " WHERE c.category_name LIKE CONCAT('%', ?, '%')";
    $params[] = $search;
    $types .= 's';
}
$sql .= " ORDER BY c.category_name";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$categories = $stmt->get_result();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Expense categories</h1>
  <a href="add.php" class="btn btn-brand btn-sm">Add category</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-auto">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search category name"
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <div class="col-auto">
    <button class="btn btn-sm btn-outline-secondary">Search</button>
    <?php if ($search !== ''): ?><a href="list.php" class="btn btn-sm btn-link">Clear</a><?php endif; ?>
  </div>
</form>

<div class="card p-0">
  <table class="table mb-0 align-middle">
    <thead>
      <tr><th>Category</th><th>Description</th><th>Created by</th><th>Used in</th><th></th></tr>
    </thead>
    <tbody>
      <?php if ($categories->num_rows === 0): ?>
        <tr><td colspan="5" class="text-muted text-center py-4">No categories yet. Add your first one.</td></tr>
      <?php endif; ?>
      <?php while ($c = $categories->fetch_assoc()): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($c['category_name']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($c['description'] ?: '—') ?></td>
          <td><?= htmlspecialchars($c['created_by']) ?></td>
          <td><?= (int) $c['usage_count'] ?> expense(s)</td>
          <td class="text-end">
            <a href="edit.php?id=<?= $c['category_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
            <a href="delete.php?id=<?= $c['category_id'] ?>" class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Delete this category? This cannot be undone.');">Delete</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
