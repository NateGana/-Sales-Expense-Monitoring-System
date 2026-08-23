<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Daily sales summary';
$date = trim($_GET['date'] ?? date('Y-m-d'));
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    $date = date('Y-m-d');
}

$totalSales = $totalExpenses = $netProfit = 0.0;
$profitStatus = 'No sales yet';

try {
    $stmt = $conn->prepare('CALL sp_get_daily_summary(?, @total_sales, @total_expenses, @net_profit, @profit_status)');
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $stmt->close();
    drain_multi_results($conn);
    $row = $conn->query('SELECT @total_sales ts, @total_expenses te, @net_profit np, @profit_status ps')->fetch_assoc();
    $totalSales = (float) $row['ts'];
    $totalExpenses = (float) $row['te'];
    $netProfit = (float) $row['np'];
    $profitStatus = $row['ps'];
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
}

$stmt = $conn->prepare(
    'SELECT s.sale_id, s.sale_time, s.payment_method, s.total_amount, u.full_name
     FROM sales s JOIN users u ON u.user_id = s.recorded_by
     WHERE s.sale_date = ? ORDER BY s.sale_time'
);
$stmt->bind_param('s', $date);
$stmt->execute();
$sales = $stmt->get_result();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <h1 class="h4 mb-0">Daily sales summary</h1>
  <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">Print report</button>
</div>

<form class="row g-2 mb-3 no-print" method="get">
  <div class="col-auto">
    <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($date) ?>">
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">View date</button></div>
</form>

<div class="card p-4" style="max-width: 760px;">
  <h2 class="h5 mb-1">Daily Sales Summary</h2>
  <p class="text-muted mb-4"><?= date('l, F j, Y', strtotime($date)) ?></p>

  <div class="row g-3 mb-4">
    <div class="col-4"><div class="stat-card accent"><div class="stat-label">Total sales</div><div class="stat-value"><?= peso($totalSales) ?></div></div></div>
    <div class="col-4"><div class="stat-card warn"><div class="stat-label">Total expenses</div><div class="stat-value"><?= peso($totalExpenses) ?></div></div></div>
    <div class="col-4"><div class="stat-card <?= $netProfit < 0 ? 'danger' : 'accent' ?>"><div class="stat-label">Net profit (<?= htmlspecialchars($profitStatus) ?>)</div><div class="stat-value"><?= peso($netProfit) ?></div></div></div>
  </div>

  <h3 class="h6">Sales transactions</h3>
  <table class="table">
    <thead><tr><th>Sale #</th><th>Time</th><th>Payment</th><th>Recorded by</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
      <?php if ($sales->num_rows === 0): ?>
        <tr><td colspan="5" class="text-muted text-center py-3">No sales recorded on this date.</td></tr>
      <?php endif; ?>
      <?php while ($s = $sales->fetch_assoc()): ?>
      <tr>
        <td>#<?= $s['sale_id'] ?></td>
        <td><?= date('g:i A', strtotime($s['sale_time'])) ?></td>
        <td><?= ucfirst(str_replace('_',' ',$s['payment_method'])) ?></td>
        <td><?= htmlspecialchars($s['full_name']) ?></td>
        <td class="text-end"><?= peso($s['total_amount']) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
    <tfoot>
      <tr class="fw-semibold"><td colspan="4" class="text-end">Total</td><td class="text-end"><?= peso($totalSales) ?></td></tr>
    </tfoot>
  </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
