<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Sale details';
$id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT s.*, u.full_name AS recorded_by
     FROM sales s JOIN users u ON u.user_id = s.recorded_by
     WHERE s.sale_id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    flash('error', 'That sale could not be found.');
    header('Location: list.php');
    exit;
}

$stmt = $conn->prepare('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY sale_item_id');
$stmt->bind_param('i', $id);
$stmt->execute();
$items = $stmt->get_result();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <h1 class="h4 mb-0">Sale #<?= $sale['sale_id'] ?></h1>
  <div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">Print receipt</button>
    <a href="list.php" class="btn btn-sm btn-link">Back to sales</a>
  </div>
</div>

<div class="card p-4" style="max-width: 640px;">
  <div class="d-flex justify-content-between mb-3">
    <div>
      <div class="text-muted small">Date &amp; time</div>
      <div><?= date('F j, Y g:i A', strtotime($sale['sale_date'] . ' ' . $sale['sale_time'])) ?></div>
    </div>
    <div>
      <div class="text-muted small">Payment method</div>
      <div><?= ucfirst(str_replace('_',' ',$sale['payment_method'])) ?></div>
    </div>
    <div>
      <div class="text-muted small">Recorded by</div>
      <div><?= htmlspecialchars($sale['recorded_by']) ?></div>
    </div>
  </div>

  <table class="table">
    <thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Subtotal</th></tr></thead>
    <tbody>
      <?php while ($it = $items->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($it['item_name']) ?></td>
        <td class="text-end"><?= (int) $it['quantity'] ?></td>
        <td class="text-end"><?= peso($it['unit_price']) ?></td>
        <td class="text-end"><?= peso($it['subtotal']) ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
    <tfoot>
      <tr class="fw-semibold"><td colspan="3" class="text-end">Total</td><td class="text-end"><?= peso($sale['total_amount']) ?></td></tr>
    </tfoot>
  </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
