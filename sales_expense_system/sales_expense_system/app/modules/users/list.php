<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle = 'User accounts';

$search = trim($_GET['q'] ?? '');
$sql = "SELECT user_id, username, full_name, role, status, created_at FROM users WHERE 1=1";
$params = []; $types = '';
if ($search !== '') {
    $sql .= " AND (username LIKE CONCAT('%',?,'%') OR full_name LIKE CONCAT('%',?,'%'))";
    $params[] = $search; $params[] = $search; $types .= 'ss';
}
$sql .= " ORDER BY full_name";

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$users = $stmt->get_result();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">User accounts</h1>
  <a href="add.php" class="btn btn-brand btn-sm">Add user</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-auto">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or username"
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <div class="col-auto">
    <button class="btn btn-sm btn-outline-secondary">Search</button>
    <?php if ($search !== ''): ?><a href="list.php" class="btn btn-sm btn-link">Clear</a><?php endif; ?>
  </div>
</form>

<div class="card p-0">
  <table class="table mb-0 align-middle">
    <thead><tr><th>Full name</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
      <?php if ($users->num_rows === 0): ?>
        <tr><td colspan="6" class="text-muted text-center py-4">No users found.</td></tr>
      <?php endif; ?>
      <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($u['full_name']) ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><span class="badge text-bg-light border"><?= ucfirst($u['role']) ?></span></td>
          <td>
            <?php if ($u['status'] === 'active'): ?>
              <span class="badge badge-status-healthy">Active</span>
            <?php else: ?>
              <span class="badge badge-status-loss">Inactive</span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td class="text-end">
            <a href="edit.php?id=<?= $u['user_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
            <?php if ((int)$u['user_id'] !== (int) currentUser()['user_id']): ?>
            <a href="delete.php?id=<?= $u['user_id'] ?>" class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Delete this user account?');">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
