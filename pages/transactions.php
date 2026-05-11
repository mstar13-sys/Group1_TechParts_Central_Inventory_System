<?php
// pages/transactions.php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Transactions';
$db = getDB();

// Void action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['void_id'])) {
  requireLogin(['Admin', 'Cashier']);
  $stmt = $db->prepare("UPDATE Transaction SET Status='Voided' WHERE ID=? AND Status='Completed'");
  $stmt->execute([$_POST['void_id']]);
  $msg = 'Transaction voided.';
  $msgType = 'warning';
}

// Filters
$search   = trim($_GET['q'] ?? '');
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to']   ?? '';
$status   = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];
if ($search) {
  $where[] = '(t.CustomerName LIKE ? OR t.CustomerPhone LIKE ?)';
  $params[] = "%$search%";
  $params[] = "%$search%";
}
if ($dateFrom) {
  $where[] = 'DATE(t.TransactionDate)>=?';
  $params[] = $dateFrom;
}
if ($dateTo) {
  $where[] = 'DATE(t.TransactionDate)<=?';
  $params[] = $dateTo;
}
if ($status) {
  $where[] = 't.Status=?';
  $params[] = $status;
}
$whereStr = implode(' AND ', $where);

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$total  = $db->prepare("SELECT COUNT(*) FROM Transaction t WHERE $whereStr");
$total->execute($params);
$totalRows = $total->fetchColumn();

$stmt = $db->prepare("SELECT t.*, u.Name AS CashierName FROM Transaction t JOIN User u ON t.Cashier_ID=u.ID WHERE $whereStr ORDER BY t.TransactionDate DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$txns = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($msg)): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
  <div class="card-title">Filter Transactions</div>
  <form method="GET">
    <div class="form-grid" style="grid-template-columns:2fr 1fr 1fr 1fr auto">
      <div class="form-group" style="margin:0">
        <input type="text" name="q" class="form-control" placeholder="Customer name or phone…" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="form-group" style="margin:0">
        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div class="form-group" style="margin:0">
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div class="form-group" style="margin:0">
        <select name="status" class="form-control">
          <option value="">All Status</option>
          <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
          <option value="Voided" <?= $status === 'Voided' ? 'selected' : '' ?>>Voided</option>
          <option value="Refunded" <?= $status === 'Refunded' ? 'selected' : '' ?>>Refunded</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="height:40px;align-self:start">Filter</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-title">Transactions (<?= $totalRows ?> records)</div>
  <div class="table-wrap">
    <table>
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
        <?php if (empty($txns)): ?>
          <tr>
            <td colspan="10" style="text-align:center;color:var(--text-muted);padding:30px">No transactions found.</td>
          </tr>
        <?php endif; ?>
        <?php foreach ($txns as $t): ?>
          <tr>
            <td style="font-family:'Syne',sans-serif;font-weight:700;color:var(--accent)">TXN-<?= str_pad($t['ID'], 5, '0', STR_PAD_LEFT) ?></td>
            <td><?= htmlspecialchars($t['CustomerName']) ?></td>
            <td style="color:var(--text-muted)"><?= htmlspecialchars($t['CustomerPhone'] ?? '—') ?></td>
            <td><?= htmlspecialchars($t['CashierName']) ?></td>
            <td><?= $t['PaymentMethod'] ?></td>
            <td><?= $t['Discount'] > 0 ? $t['Discount'] . '%' : '—' ?></td>
            <td style="font-weight:700;color:var(--accent3)">₱<?= number_format($t['TotalAmount'], 2) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M d, Y g:i a', strtotime($t['TransactionDate'])) ?></td>
            <td>
              <?php $c = match ($t['Status']) {
                'Completed' => 'green',
                'Voided' => 'red',
                'Refunded' => 'yellow',
                default => 'gray'
              }; ?>
              <span class="badge badge-<?= $c ?>"><?= $t['Status'] ?></span>
            </td>
            <td style="display:flex;gap:6px">
              <button class="btn btn-ghost btn-sm" onclick="openModal('txn-<?= $t['ID'] ?>')">View</button>
              <?php if ($t['Status'] === 'Completed' && in_array($_SESSION['role'], ['Admin', 'Cashier'])): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="void_id" value="<?= $t['ID'] ?>">
                  <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('Void this transaction?',this.closest('form'))">Void</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>

          <!-- Transaction Detail Modal -->
          <div class="modal-overlay" id="txn-<?= $t['ID'] ?>">
            <div class="modal">
              <div class="modal-header">
                <span class="modal-title">TXN-<?= str_pad($t['ID'], 5, '0', STR_PAD_LEFT) ?> Details</span>
                <button class="modal-close" onclick="closeModal('txn-<?= $t['ID'] ?>')">✕</button>
              </div>
              <div class="modal-body">
                <?php
                $items = $db->prepare('SELECT si.*, p.Name, p.Brand FROM SaleItem si JOIN Product p ON si.Product_ID=p.ID WHERE si.Transaction_ID=?');
                $items->execute([$t['ID']]);
                $saleItems = $items->fetchAll();
                ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;font-size:13px">
                  <div><span style="color:var(--text-muted)">Customer:</span> <?= htmlspecialchars($t['CustomerName']) ?></div>
                  <div><span style="color:var(--text-muted)">Phone:</span> <?= htmlspecialchars($t['CustomerPhone'] ?? '—') ?></div>
                  <div><span style="color:var(--text-muted)">Payment:</span> <?= $t['PaymentMethod'] ?></div>
                  <div><span style="color:var(--text-muted)">Cashier:</span> <?= htmlspecialchars($t['CashierName']) ?></div>
                  <div><span style="color:var(--text-muted)">Date:</span> <?= date('M d, Y g:i a', strtotime($t['TransactionDate'])) ?></div>
                  <div><span style="color:var(--text-muted)">Status:</span> <?= $t['Status'] ?></div>
                </div>
                <table style="width:100%">
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
                        <td style="color:var(--text-muted)"><?= htmlspecialchars($si['Brand']) ?></td>
                        <td><?= $si['Quantity'] ?></td>
                        <td>₱<?= number_format($si['UnitPrice'], 2) ?></td>
                        <td style="font-weight:600">₱<?= number_format($si['Subtotal'], 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <div style="margin-top:14px;text-align:right;font-size:13px">
                  <div style="color:var(--text-muted)">Subtotal: ₱<?= number_format(array_sum(array_column($saleItems, 'Subtotal')), 2) ?></div>
                  <?php if ($t['Discount'] > 0): ?><div style="color:var(--text-muted)">Discount: <?= $t['Discount'] ?>%</div><?php endif; ?>
                  <div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:var(--accent);margin-top:4px">
                    TOTAL: ₱<?= number_format($t['TotalAmount'], 2) ?>
                  </div>
                  <?php if ($t['AmountTendered'] > 0): ?>
                    <div style="color:var(--accent3)">Tendered: ₱<?= number_format($t['AmountTendered'], 2) ?> | Change: ₱<?= number_format(max(0, $t['AmountTendered'] - $t['TotalAmount']), 2) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <?php $pages = ceil($totalRows / $limit);
  if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>