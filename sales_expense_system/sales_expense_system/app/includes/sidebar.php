<?php
$here = $_SERVER['SCRIPT_NAME'];
function navActive(string $needle, string $here): string
{
    return strpos($here, $needle) !== false ? 'active' : '';
}
?>
<aside class="app-sidebar" style="width:230px;">
  <nav class="nav flex-column p-2">
    <a class="nav-link <?= navActive('/dashboard.php', $here) ?>" href="<?= base_url('dashboard.php') ?>">Dashboard</a>

    <div class="nav-section">Transactions</div>
    <a class="nav-link <?= navActive('/modules/sales/', $here) ?>" href="<?= base_url('modules/sales/list.php') ?>">Sales</a>
    <a class="nav-link <?= navActive('/modules/expenses/', $here) ?>" href="<?= base_url('modules/expenses/list.php') ?>">Expenses</a>

    <?php if (isAdmin()): ?>
    <div class="nav-section">Setup</div>
    <a class="nav-link <?= navActive('/modules/categories/', $here) ?>" href="<?= base_url('modules/categories/list.php') ?>">Expense categories</a>
    <a class="nav-link <?= navActive('/modules/users/', $here) ?>" href="<?= base_url('modules/users/list.php') ?>">User accounts</a>
    <?php endif; ?>

    <div class="nav-section">Reports</div>
    <a class="nav-link <?= navActive('daily_sales.php', $here) ?>" href="<?= base_url('modules/reports/daily_sales.php') ?>">Daily sales summary</a>
    <a class="nav-link <?= navActive('monthly_performance.php', $here) ?>" href="<?= base_url('modules/reports/monthly_performance.php') ?>">Monthly performance</a>
    <a class="nav-link <?= navActive('category_breakdown.php', $here) ?>" href="<?= base_url('modules/reports/category_breakdown.php') ?>">Category breakdown</a>
    <a class="nav-link <?= navActive('profit_statement.php', $here) ?>" href="<?= base_url('modules/reports/profit_statement.php') ?>">Profit estimation</a>
  </nav>
</aside>
