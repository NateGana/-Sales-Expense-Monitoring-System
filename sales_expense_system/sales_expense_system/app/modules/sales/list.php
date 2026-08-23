<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Sales';

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$payment  = trim($_GET['payment_method'] ?? '');

$sql = "SELECT s.sale_id, s.sale_date, s.sale_time, s.total_amount, s.payment_method, u.full_name AS recorded_by,
               (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.sale_id) AS item_count
        FROM sales s JOIN users u ON u.user_id = s.recorded_by
        WHERE 1=1";
$params = []; $types = '';

if ($dateFrom !== '') { $sql .= " AND s.sale_date >= ?"; $params[] = $dateFrom; $types .= 's'; }
if ($dateTo !== '')   { $sql .= " AND s.sale_date <= ?"; $params[] = $dateTo;   $types .= 's'; }
if ($payment !== '')  { $sql .= " AND s.payment_method = ?"; $params[] = $payment; $types .= 's'; }

$sql .= " ORDER BY s.sale_date DESC, s.sale_time DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$sales = $stmt->get_result();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Sales</h1>
  <a href="add.php" class="btn btn-brand btn-sm">Record a sale</a>
</div>

<form class="row g-2 mb-3 align-items-end" method="get">
  <div class="col-auto">
    <label class="form-label small mb-0">From</label>
    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">To</label>
    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">Payment method</label>
    <select name="payment_method" class="form-select form-select-sm">
      <option value="">All methods</option>
      <?php foreach (['cash','gcash','card','bank_transfer'] as $m): ?>
        <option value="<?= $m ?>" <?= $payment === $m ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option>
      <?php endforeach; ?>
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
      <tr><th>Sale #</th><th>Date/time</th><th>Items</th><th>Payment</th><th>Total</th><th>Recorded by</th><th></th></tr>
    </thead>
    <tbody>
      <?php if ($sales->num_rows === 0): ?>
        <tr><td colspan="7" class="text-muted text-center py-4">No sales found for these filters.</td></tr>
      <?php endif; ?>
      <?php while ($s = $sales->fetch_assoc()): ?>
        <tr>
          <td>#<?= $s['sale_id'] ?></td>
          <td><?= date('M j, Y g:i A', strtotime($s['sale_date'] . ' ' . $s['sale_time'])) ?></td>
          <td><?= (int) $s['item_count'] ?> item(s)</td>
          <td><span class="badge text-bg-light border"><?= ucfirst(str_replace('_',' ',$s['payment_method'])) ?></span></td>
          <td class="fw-semibold"><?= peso($s['total_amount']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($s['recorded_by']) ?></td>
          <td class="text-end">
            <a href="view.php?id=<?= $s['sale_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
            <?php if (isAdmin()): ?>
            <a href="delete.php?id=<?= $s['sale_id'] ?>" class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Delete this sale and all its items?');">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
