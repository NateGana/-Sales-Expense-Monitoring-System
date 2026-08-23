<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Record a sale';
$errors = [];

$saleDate = date('Y-m-d');
$paymentMethod = 'cash';
$items = [['item_name' => '', 'quantity' => '', 'unit_price' => '']];

$validPaymentMethods = ['cash', 'gcash', 'card', 'bank_transfer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $saleDate = trim($_POST['sale_date'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $itemNames  = $_POST['item_name']   ?? [];
    $itemQtys   = $_POST['quantity']    ?? [];
    $itemPrices = $_POST['unit_price']  ?? [];

    // ---- STEP 1: INPUT VALIDATION (client already checks required/min via HTML,
    // this is the authoritative server-side check) ----
    if ($saleDate === '' || !DateTime::createFromFormat('Y-m-d', $saleDate)) {
        $errors[] = 'Please provide a valid sale date.';
    } elseif ($saleDate > date('Y-m-d')) {
        $errors[] = 'Sale date cannot be in the future.';
    }
    if (!in_array($paymentMethod, $validPaymentMethods, true)) {
        $errors[] = 'Please select a valid payment method.';
    }

    $items = [];
    $rowCount = max(count($itemNames), count($itemQtys), count($itemPrices));
    for ($i = 0; $i < $rowCount; $i++) {
        $name = trim($itemNames[$i] ?? '');
        $qty  = trim($itemQtys[$i] ?? '');
        $price = trim($itemPrices[$i] ?? '');

        // Skip fully blank rows (the person may have added an extra row and not filled it).
        if ($name === '' && $qty === '' && $price === '') {
            continue;
        }

        $items[] = ['item_name' => $name, 'quantity' => $qty, 'unit_price' => $price];

        if ($name === '') {
            $errors[] = 'Item #' . ($i + 1) . ': item name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Item #' . ($i + 1) . ': item name must be 100 characters or fewer.';
        }
        if ($qty === '' || !ctype_digit((string) $qty) || (int) $qty <= 0) {
            $errors[] = 'Item #' . ($i + 1) . ': quantity must be a whole number greater than zero.';
        }
        if ($price === '' || !is_numeric($price) || (float) $price < 0) {
            $errors[] = 'Item #' . ($i + 1) . ': unit price must be zero or a positive number.';
        }
    }

    if (empty($items)) {
        $errors[] = 'Add at least one item to this sale.';
    }

    // ---- STEP 2 & 3: PROCESSING + DATABASE UPDATE (ACID transaction) ----
    if (!$errors) {
        $userId = (int) currentUser()['user_id'];
        $saleTime = date('H:i:s');

        $conn->begin_transaction();
        try {
            // Open the sale header, get the new sale_id back via an OUT parameter.
            $stmt = $conn->prepare('CALL sp_open_sale(?, ?, ?, ?, @new_sale_id)');
            $stmt->bind_param('sssi', $saleDate, $saleTime, $paymentMethod, $userId);
            $stmt->execute();
            $stmt->close();
            drain_multi_results($conn);

            $saleId = (int) $conn->query('SELECT @new_sale_id AS id')->fetch_assoc()['id'];

            // Insert every line item inside the same transaction.
            foreach ($items as $item) {
                $stmt = $conn->prepare('CALL sp_add_sale_item(?, ?, ?, ?)');
                $qty = (int) $item['quantity'];
                $price = (float) $item['unit_price'];
                $stmt->bind_param('isid', $saleId, $item['item_name'], $qty, $price);
                $stmt->execute();
                $stmt->close();
                drain_multi_results($conn);
            }

            // Recompute and store the header total from its items.
            $stmt = $conn->prepare('CALL sp_finalize_sale(?, @final_total)');
            $stmt->bind_param('i', $saleId);
            $stmt->execute();
            $stmt->close();
            drain_multi_results($conn);
            $finalTotal = (float) $conn->query('SELECT @final_total AS t')->fetch_assoc()['t'];

            $conn->commit();

            // ---- STEP 4 & 5: CONFIRMATION + TRANSACTION HISTORY ----
            log_activity($conn, $userId, 'SALE_RECORDED', "Recorded sale #$saleId - " . peso($finalTotal));
            flash('success', "Sale #$saleId recorded successfully - total " . peso($finalTotal) . '.');
            header('Location: view.php?id=' . $saleId);
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            error_log('Sale transaction failed: ' . $e->getMessage());
            $errors[] = 'Unable to record this sale. Nothing was saved - please check the items and try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3">Record a sale</h1>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<div class="card p-4">
  <form method="post" id="saleForm" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label class="form-label">Sale date *</label>
        <input type="date" name="sale_date" class="form-control" required max="<?= date('Y-m-d') ?>"
               value="<?= htmlspecialchars($saleDate) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Payment method *</label>
        <select name="payment_method" class="form-select" required>
          <?php foreach ($validPaymentMethods as $m): ?>
            <option value="<?= $m ?>" <?= $paymentMethod === $m ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h2 class="h6 mt-4 mb-2">Items</h2>
    <table class="table" id="itemsTable">
      <thead>
        <tr><th>Item name</th><th style="width:120px;">Quantity</th><th style="width:160px;">Unit price (₱)</th><th style="width:140px;">Subtotal</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $item): ?>
        <tr>
          <td><input type="text" name="item_name[]" class="form-control" maxlength="100"
                     value="<?= htmlspecialchars($item['item_name']) ?>"></td>
          <td><input type="number" name="quantity[]" class="form-control row-qty" min="1" step="1"
                     value="<?= htmlspecialchars($item['quantity']) ?>"></td>
          <td><input type="number" name="unit_price[]" class="form-control row-price" min="0" step="0.01"
                     value="<?= htmlspecialchars($item['unit_price']) ?>"></td>
          <td class="row-subtotal align-middle">₱0.00</td>
          <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button type="button" id="addRow" class="btn btn-sm btn-outline-secondary mb-3">+ Add item</button>

    <div class="text-end h5">Total: <span id="grandTotal">₱0.00</span></div>

    <button type="submit" class="btn btn-brand">Save sale</button>
    <a href="list.php" class="btn btn-link">Cancel</a>
  </form>
</div>

<script>
const tbody = document.querySelector('#itemsTable tbody');
const rowTemplate = () => {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="text" name="item_name[]" class="form-control" maxlength="100"></td>
    <td><input type="number" name="quantity[]" class="form-control row-qty" min="1" step="1"></td>
    <td><input type="number" name="unit_price[]" class="form-control row-price" min="0" step="0.01"></td>
    <td class="row-subtotal align-middle">₱0.00</td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>`;
  return tr;
};

document.getElementById('addRow').addEventListener('click', () => {
  tbody.appendChild(rowTemplate());
  attachRowEvents();
  recalc();
});

function attachRowEvents() {
  tbody.querySelectorAll('.remove-row').forEach(btn => {
    btn.onclick = () => { btn.closest('tr').remove(); recalc(); };
  });
  tbody.querySelectorAll('.row-qty, .row-price').forEach(input => {
    input.oninput = recalc;
  });
}

function recalc() {
  let grand = 0;
  tbody.querySelectorAll('tr').forEach(tr => {
    const qty = parseFloat(tr.querySelector('.row-qty').value) || 0;
    const price = parseFloat(tr.querySelector('.row-price').value) || 0;
    const subtotal = qty * price;
    tr.querySelector('.row-subtotal').textContent = '₱' + subtotal.toFixed(2);
    grand += subtotal;
  });
  document.getElementById('grandTotal').textContent = '₱' + grand.toFixed(2);
}

attachRowEvents();
recalc();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
