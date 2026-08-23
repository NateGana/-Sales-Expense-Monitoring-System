<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Category breakdown';
$year  = (int) ($_GET['year']  ?? date('Y'));
$month = (int) ($_GET['month'] ?? date('n'));
if ($month < 1 || $month > 12) { $month = (int) date('n'); }

$rows = [];
try {
    $stmt = $conn->prepare('CALL sp_monthly_category_report(?, ?)');
    $stmt->bind_param('ii', $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    drain_multi_results($conn);
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    flash('error', 'Unable to load the category breakdown right now.');
}

$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <h1 class="h4 mb-0">Category-wise expense breakdown</h1>
  <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">Print report</button>
</div>

<form class="row g-2 mb-3 no-print" method="get">
  <div class="col-auto">
    <select name="month" class="form-select form-select-sm">
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-auto">
    <input type="number" name="year" class="form-control form-control-sm" style="width:100px" value="<?= $year ?>">
  </div>
  <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">View month</button></div>
</form>

<div class="card p-4" style="max-width: 700px;">
  <h2 class="h5 mb-1">Category-wise Expense Breakdown</h2>
  <p class="text-muted mb-4"><?= $monthName . ' ' . $year ?></p>

  <table class="table">
    <thead><tr><th>Category</th><th class="text-end">Transactions</th><th class="text-end">Total amount</th></tr></thead>
    <tbody>
      <?php if (count($rows) <= 1): // only the ROLLUP grand-total row, or nothing ?>
        <tr><td colspan="3" class="text-muted text-center py-3">No expenses recorded for this month.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): $isGrand = $r['category_name'] === 'GRAND TOTAL'; ?>
        <tr class="<?= $isGrand ? 'fw-semibold border-top' : '' ?>">
          <td><?= htmlspecialchars($r['category_name']) ?></td>
          <td class="text-end"><?= (int) $r['num_transactions'] ?></td>
          <td class="text-end"><?= peso($r['total_amount']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
