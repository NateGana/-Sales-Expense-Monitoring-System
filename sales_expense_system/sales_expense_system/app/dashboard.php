<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Dashboard';
$today = date('Y-m-d');

// --- Today's summary via sp_get_daily_summary (IN date, OUT x4) ---
$totalSales = $totalExpenses = $netProfit = 0.0;
$profitStatus = 'No sales yet';

try {
    $stmt = $conn->prepare(
        'CALL sp_get_daily_summary(?, @total_sales, @total_expenses, @net_profit, @profit_status)'
    );
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $stmt->close();
    drain_multi_results($conn);

    $res = $conn->query(
        'SELECT @total_sales AS total_sales, @total_expenses AS total_expenses,
                @net_profit AS net_profit, @profit_status AS profit_status'
    );
    $row = $res->fetch_assoc();
    $totalSales    = (float) $row['total_sales'];
    $totalExpenses = (float) $row['total_expenses'];
    $netProfit     = (float) $row['net_profit'];
    $profitStatus  = $row['profit_status'];
} catch (mysqli_sql_exception $e) {
    error_log('Dashboard summary error: ' . $e->getMessage());
}

$statusClass = [
    'Healthy'      => 'badge-status-healthy',
    'Low margin'   => 'badge-status-low',
    'Loss'         => 'badge-status-loss',
    'No sales yet' => 'badge-status-none',
][$profitStatus] ?? 'badge-status-none';

// --- Supporting counts for the "system overview" part of the dashboard ---
$salesTodayCount = (int) $conn->query("SELECT COUNT(*) c FROM sales WHERE sale_date = CURDATE()")->fetch_assoc()['c'];
$expensesTodayCount = (int) $conn->query("SELECT COUNT(*) c FROM expenses WHERE expense_date = CURDATE()")->fetch_assoc()['c'];
$monthSales = (float) $conn->query(
    "SELECT COALESCE(SUM(total_amount),0) t FROM sales WHERE YEAR(sale_date)=YEAR(CURDATE()) AND MONTH(sale_date)=MONTH(CURDATE())"
)->fetch_assoc()['t'];
$monthExpenses = (float) $conn->query(
    "SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())"
)->fetch_assoc()['t'];

// --- Recent activity feed ---
$activity = $conn->query(
    "SELECT a.action, a.details, a.created_at, u.full_name
     FROM activity_logs a JOIN users u ON u.user_id = a.user_id
     ORDER BY a.created_at DESC LIMIT 8"
);

include __DIR__ . '/includes/header.php';
?>

<h1 class="h4 mb-1">Good day, <?= htmlspecialchars(currentUser()['full_name']) ?></h1>
<p class="text-muted mb-4"><?= date('l, F j, Y') ?></p>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card accent">
      <div class="stat-label">Today's sales</div>
      <div class="stat-value"><?= peso($totalSales) ?></div>
      <div class="text-muted small"><?= $salesTodayCount ?> transaction(s)</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card warn">
      <div class="stat-label">Today's expenses</div>
      <div class="stat-value"><?= peso($totalExpenses) ?></div>
      <div class="text-muted small"><?= $expensesTodayCount ?> record(s)</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card <?= $netProfit < 0 ? 'danger' : 'accent' ?>">
      <div class="stat-label">Today's net profit</div>
      <div class="stat-value"><?= peso($netProfit) ?></div>
      <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($profitStatus) ?></span>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-label">This month so far</div>
      <div class="stat-value"><?= peso($monthSales - $monthExpenses) ?></div>
      <div class="text-muted small">Sales <?= peso($monthSales) ?> · Expenses <?= peso($monthExpenses) ?></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card p-3">
      <h2 class="h6 mb-3">Quick actions</h2>
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= base_url('modules/sales/add.php') ?>" class="btn btn-brand btn-sm">Record a sale</a>
        <a href="<?= base_url('modules/expenses/add.php') ?>" class="btn btn-outline-secondary btn-sm">Record an expense</a>
        <a href="<?= base_url('modules/reports/daily_sales.php') ?>" class="btn btn-outline-secondary btn-sm">View daily report</a>
        <?php if (isAdmin()): ?>
        <a href="<?= base_url('modules/categories/add.php') ?>" class="btn btn-outline-secondary btn-sm">Add expense category</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card p-3">
      <h2 class="h6 mb-3">Recent activity</h2>
      <ul class="list-unstyled small mb-0">
        <?php if ($activity->num_rows === 0): ?>
          <li class="text-muted">No activity yet.</li>
        <?php endif; ?>
        <?php while ($a = $activity->fetch_assoc()): ?>
          <li class="mb-2 pb-2 border-bottom">
            <strong><?= htmlspecialchars($a['full_name']) ?></strong> — <?= htmlspecialchars($a['details']) ?>
            <div class="text-muted"><?= date('M j, g:i A', strtotime($a['created_at'])) ?></div>
          </li>
        <?php endwhile; ?>
      </ul>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
