<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Profit estimation';
$dateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));
$dateTo   = trim($_GET['date_to']   ?? date('Y-m-d'));
if (!DateTime::createFromFormat('Y-m-d', $dateFrom)) { $dateFrom = date('Y-m-01'); }
if (!DateTime::createFromFormat('Y-m-d', $dateTo))   { $dateTo   = date('Y-m-d'); }

$stmt = $conn->prepare('SELECT COALESCE(SUM(total_amount),0) t FROM sales WHERE sale_date BETWEEN ? AND ?');
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$totalSales = (float) $stmt->get_result()->fetch_assoc()['t'];

$stmt = $conn->prepare('SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE expense_date BETWEEN ? AND ?');
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$totalExpenses = (float) $stmt->get_result()->fetch_assoc()['t'];

$netProfit = $totalSales - $totalExpenses;
$margin = $totalSales > 0 ? ($netProfit / $totalSales) * 100 : null;

// Same classification rule as sp_get_daily_summary's CASE, applied to the chosen range.
if ($totalSales == 0) {
    $status = 'No sales yet'; $statusClass = 'badge-status-none';
} elseif ($netProfit < 0) {
    $status = 'Loss'; $statusClass = 'badge-status-loss';
} elseif ($margin < 10) {
    $status = 'Low margin'; $statusClass = 'badge-status-low';
} else {
    $status = 'Healthy'; $statusClass = 'badge-status-healthy';
}

// Expense breakdown for the range, to explain where the money went.
$stmt = $conn->prepare(
    "SELECT c.category_name, SUM(e.amount) AS total
     FROM expenses e JOIN expense_categories c ON c.category_id = e.category_id
     WHERE e.expense_date BETWEEN ? AND ?
     GROUP BY c.category_name ORDER BY total DESC"
);
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$breakdown = $stmt->get_result();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <h1 class="h4 mb-0">Profit estimation statement</h1>
  <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">Print report</button>
</div>

<form class="row g-2 mb-3 no-print" method="get">
  <div class="col-auto">
    <label class="form-label small mb-0">From</label>
    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">To</label>
    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
  </div>
  <div class="col-auto align-self-end"><button class="btn btn-sm btn-outline-secondary">View range</button></div>
</form>

<div class="card p-4" style="max-width: 700px;">
  <h2 class="h5 mb-1">Profit Estimation Statement</h2>
  <p class="text-muted mb-4"><?= date('M j, Y', strtotime($dateFrom)) ?> &ndash; <?= date('M j, Y', strtotime($dateTo)) ?></p>

  <table class="table mb-4">
    <tbody>
      <tr><td>Total sales</td><td class="text-end"><?= peso($totalSales) ?></td></tr>
      <tr><td>Total expenses</td><td class="text-end">(<?= peso($totalExpenses) ?>)</td></tr>
      <tr class="fw-semibold border-top"><td>Estimated net profit</td><td class="text-end"><?= peso($netProfit) ?></td></tr>
      <tr>
        <td>Profit margin</td>
        <td class="text-end"><?= $margin === null ? '—' : number_format($margin, 1) . '%' ?></td>
      </tr>
      <tr>
        <td>Status</td>
        <td class="text-end"><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
      </tr>
    </tbody>
  </table>

  <h3 class="h6">Where expenses went</h3>
  <table class="table">
    <thead><tr><th>Category</th><th class="text-end">Amount</th><th class="text-end">% of expenses</th></tr></thead>
    <tbody>
      <?php if ($breakdown->num_rows === 0): ?>
        <tr><td colspan="3" class="text-muted text-center py-3">No expenses recorded for this range.</td></tr>
      <?php endif; ?>
      <?php while ($b = $breakdown->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($b['category_name']) ?></td>
          <td class="text-end"><?= peso($b['total']) ?></td>
          <td class="text-end"><?= $totalExpenses > 0 ? number_format(($b['total']/$totalExpenses)*100, 1) . '%' : '—' ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
