<?php
// pages/transactions.php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Transactions';
$db = getDB();

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['void_id'])) {
  requireLogin(['Admin', 'Cashier']);
  verifyCsrf();

  $voidId = (int)$_POST['void_id'];
  $stmt = $db->prepare("UPDATE Transaction SET Status='Voided' WHERE ID=? AND Status='Completed'");
  $stmt->execute([$voidId]);

  $msg = $stmt->rowCount() ? 'Transaction voided.' : 'Transaction cannot be voided.';
  $msgType = $stmt->rowCount() ? 'warning' : 'danger';
}

$search   = trim($_GET['q'] ?? '');
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to'] ?? '';
$status   = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];

if ($search !== '') {
  $where[] = '(t.CustomerName LIKE ? OR t.CustomerPhone LIKE ? OR CAST(t.ID AS CHAR) LIKE ?)';
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
}
if ($dateFrom !== '') {
  $where[] = 'DATE(t.TransactionDate) >= ?';
  $params[] = $dateFrom;
}
if ($dateTo !== '') {
  $where[] = 'DATE(t.TransactionDate) <= ?';
  $params[] = $dateTo;
}
if (in_array($status, ['Completed', 'Voided', 'Refunded'], true)) {
  $where[] = 't.Status = ?';
  $params[] = $status;
}

$whereStr = implode(' AND ', $where);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$total = $db->prepare("SELECT COUNT(*) FROM Transaction t WHERE $whereStr");
$total->execute($params);
$totalRows = (int)$total->fetchColumn();

$stmt = $db->prepare("
  SELECT t.*, u.Name AS CashierName
  FROM Transaction t
  JOIN User u ON t.Cashier_ID = u.ID
  WHERE $whereStr
  ORDER BY t.TransactionDate DESC
  LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$txns = $stmt->fetchAll();

$itemsByTxn = [];
if ($txns) {
  $txnIds = array_map(fn($t) => (int)$t['ID'], $txns);
  $placeholders = implode(',', array_fill(0, count($txnIds), '?'));
  $items = $db->prepare("
    SELECT si.*, p.Name, p.Brand
    FROM SaleItem si
    JOIN Product p ON si.Product_ID = p.ID
    WHERE si.Transaction_ID IN ($placeholders)
    ORDER BY si.ID
  ");
  $items->execute($txnIds);
  foreach ($items->fetchAll() as $item) {
    $itemsByTxn[(int)$item['Transaction_ID']][] = $item;
  }
}

function txnNo(int $id): string
{
  return 'TXN-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

function statusColor(string $status): string
{
  return match ($status) {
    'Completed' => 'green',
    'Voided' => 'red',
    'Refunded' => 'yellow',
    default => 'gray',
  };
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/css/transactions.css">

<?php if ($msg): ?>
  <div class="alert alert-<?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card tx-filter-card">
  <div class="card-title">Filter Transactions</div>
  <form method="GET">
    <div class="tx-filter-grid">
      <div class="form-group">
        <input type="text" name="q" class="form-control" placeholder="Customer, phone, or TXN number" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="form-group">
        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div class="form-group">
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div class="form-group">
        <select name="status" class="form-control">
          <option value="">All Status</option>
          <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
          <option value="Voided" <?= $status === 'Voided' ? 'selected' : '' ?>>Voided</option>
          <option value="Refunded" <?= $status === 'Refunded' ? 'selected' : '' ?>>Refunded</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Filter</button>
    </div>
  </form>
</div>

<div class="card tx-card">
  <div class="card-title">Transactions (<?= number_format($totalRows) ?> records)</div>
  <div class="table-wrap tx-table-wrap">
    <table class="tx-table">
      <thead>
        <tr>
          <th>TXN #</th>
          <th>Customer</th>
          <th>Phone</th>
          <th>Cashier</th>
          <th>Method</th>
          <th>Discount</th>
          <th>Total</th>
          <th>Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$txns): ?>
          <tr>
            <td colspan="10" class="tx-empty">No transactions found.</td>
          </tr>
        <?php endif; ?>

        <?php foreach ($txns as $t): ?>
          <?php $id = (int)$t['ID']; ?>
          <tr>
            <td class="tx-number"><?= txnNo($id) ?></td>
            <td><?= htmlspecialchars($t['CustomerName']) ?></td>
            <td class="tx-muted"><?= htmlspecialchars($t['CustomerPhone'] ?? '-') ?></td>
            <td><?= htmlspecialchars($t['CashierName']) ?></td>
            <td><?= htmlspecialchars($t['PaymentMethod']) ?></td>
            <td><?= (float)$t['Discount'] > 0 ? htmlspecialchars($t['Discount']) . '%' : '-' ?></td>
            <td class="tx-amount">PHP <?= number_format($t['TotalAmount'], 2) ?></td>
            <td class="tx-date"><?= date('M d, Y g:i a', strtotime($t['TransactionDate'])) ?></td>
            <td><span class="badge badge-<?= statusColor($t['Status']) ?>"><?= htmlspecialchars($t['Status']) ?></span></td>
            <td>
              <div class="tx-actions">
                <button class="btn btn-ghost btn-sm" type="button" onclick="openModal('txn-<?= $id ?>')">View</button>
                <?php if ($t['Status'] === 'Completed' && in_array($_SESSION['role'], ['Admin', 'Cashier'], true)): ?>
                  <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="void_id" value="<?= $id ?>">
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('Void this transaction?', this.closest('form'))">Void</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php $pages = (int)ceil($totalRows / $limit); ?>
  <?php if ($pages > 1): ?>
    <div class="pagination tx-pagination">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))) ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php foreach ($txns as $t): ?>
  <?php
  $id = (int)$t['ID'];
  $saleItems = $itemsByTxn[$id] ?? [];
  $subtotal = array_sum(array_column($saleItems, 'Subtotal'));
  ?>
  <div class="modal-overlay" id="txn-<?= $id ?>">
    <div class="modal tx-modal">
      <div class="modal-header">
        <span class="modal-title"><?= txnNo($id) ?> Details</span>
        <button class="modal-close" onclick="closeModal('txn-<?= $id ?>')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="tx-detail-grid">
          <div><span>Customer</span><?= htmlspecialchars($t['CustomerName']) ?></div>
          <div><span>Phone</span><?= htmlspecialchars($t['CustomerPhone'] ?? '-') ?></div>
          <div><span>Payment</span><?= htmlspecialchars($t['PaymentMethod']) ?></div>
          <div><span>Cashier</span><?= htmlspecialchars($t['CashierName']) ?></div>
          <div><span>Date</span><?= date('M d, Y g:i a', strtotime($t['TransactionDate'])) ?></div>
          <div><span>Status</span><?= htmlspecialchars($t['Status']) ?></div>
        </div>

        <div class="table-wrap">
          <table class="tx-items-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Brand</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($saleItems as $si): ?>
                <tr>
                  <td><?= htmlspecialchars($si['Name']) ?></td>
                  <td class="tx-muted"><?= htmlspecialchars($si['Brand']) ?></td>
                  <td><?= (int)$si['Quantity'] ?></td>
                  <td>PHP <?= number_format($si['UnitPrice'], 2) ?></td>
                  <td><strong>PHP <?= number_format($si['Subtotal'], 2) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="tx-total-box">
          <div>Subtotal: PHP <?= number_format($subtotal, 2) ?></div>
          <?php if ((float)$t['Discount'] > 0): ?>
            <div>Discount: <?= htmlspecialchars($t['Discount']) ?>%</div>
          <?php endif; ?>
          <strong>Total: PHP <?= number_format($t['TotalAmount'], 2) ?></strong>
          <?php if ((float)$t['AmountTendered'] > 0): ?>
            <div class="tx-change">
              Tendered: PHP <?= number_format($t['AmountTendered'], 2) ?>
              | Change: PHP <?= number_format(max(0, $t['AmountTendered'] - $t['TotalAmount']), 2) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>