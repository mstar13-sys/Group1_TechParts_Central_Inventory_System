<?php
// dashboard.php
require_once __DIR__ . '/includes/config.php';
requireLogin();
$pageTitle = 'Dashboard';
$db = getDB();

// Stats
$totalProducts  = $db->query('SELECT COUNT(*) FROM Product')->fetchColumn();
$totalStock     = $db->query('SELECT COALESCE(SUM(Quantity),0) FROM Stock')->fetchColumn();
$totalSuppliers = $db->query('SELECT COUNT(*) FROM Supplier WHERE IsActive=1')->fetchColumn();
$todaySales     = $db->query("SELECT COALESCE(SUM(TotalAmount),0) FROM Transaction WHERE DATE(TransactionDate)=CURDATE() AND Status='Completed'")->fetchColumn();
$monthSales     = $db->query("SELECT COALESCE(SUM(TotalAmount),0) FROM Transaction WHERE YEAR(TransactionDate)=YEAR(CURDATE()) AND MONTH(TransactionDate)=MONTH(CURDATE()) AND Status='Completed'")->fetchColumn();
$pendingPOs     = $db->query("SELECT COUNT(*) FROM PurchaseOrder WHERE Status IN ('Pending','Approved','Ordered')")->fetchColumn();
// Single query for stock alerts
$stockAlerts    = $db->query('SELECT SUM(Quantity<=MinStock AND Quantity>0) AS low_stock, SUM(Quantity=0) AS out_of_stock FROM Stock')->fetch();
$lowStock       = (int)$stockAlerts['low_stock'];
$outOfStock     = (int)$stockAlerts['out_of_stock'];

// Recent transactions
$recentTxn = $db->query("SELECT t.*, u.Name AS CashierName FROM Transaction t JOIN User u ON t.Cashier_ID=u.ID ORDER BY t.TransactionDate DESC LIMIT 6")->fetchAll();

// Recent purchase orders
$recentPOs = $db->query("SELECT po.*, s.Name AS SupplierName FROM PurchaseOrder po JOIN Supplier s ON po.Supplier_ID=s.ID ORDER BY po.OrderDate DESC LIMIT 5")->fetchAll();

// Top products by sales
$topProducts = $db->query("
    SELECT p.Name, p.Brand, SUM(si.Quantity) AS Sold, SUM(si.Subtotal) AS Revenue
    FROM SaleItem si JOIN Product p ON si.Product_ID=p.ID
    GROUP BY p.ID ORDER BY Sold DESC LIMIT 5")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="/css/dashboard.css">
<?php
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Today's Sales</div>
    <div class="stat-value">₱<?= number_format($todaySales, 0) ?></div>
    <div class="stat-sub">Completed transactions</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Month Sales</div>
    <div class="stat-value">₱<?= number_format($monthSales, 0) ?></div>
    <div class="stat-sub">This month</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Products</div>
    <div class="stat-value"><?= $totalProducts ?></div>
    <div class="stat-sub"><?= number_format($totalStock) ?> units in stock</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Suppliers</div>
    <div class="stat-value"><?= $totalSuppliers ?></div>
    <div class="stat-sub">Active suppliers</div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Stock Alerts</div>
    <div class="stat-value"><?= $lowStock + $outOfStock ?></div>
    <div class="stat-sub"><?= $lowStock ?> low &nbsp;|&nbsp; <?= $outOfStock ?> out</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Pending POs</div>
    <div class="stat-value"><?= $pendingPOs ?></div>
    <div class="stat-sub">Awaiting action</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <!-- Recent Transactions -->
  <div class="card">
    <div class="card-title">Recent Transactions
      <a href="/pages/transactions.php" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Customer</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTxn as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['CustomerName']) ?></td>
              <td style="font-weight:600">₱<?= number_format($t['TotalAmount'], 2) ?></td>
              <td><?= $t['PaymentMethod'] ?></td>
              <td style="color:var(--text-muted);font-size:12px"><?= date('M d, g:i a', strtotime($t['TransactionDate'])) ?></td>
              <td>
                <?php $sc = match ($t['Status']) {
                  'Completed' => 'green',
                  'Voided' => 'red',
                  'Refunded' => 'yellow',
                  default => 'gray'
                }; ?>
                <span class="badge badge-<?= $sc ?>"><?= $t['Status'] ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Products -->
  <div class="card">
    <div class="card-title">Top Selling Products</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Brand</th>
            <th>Sold</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topProducts as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['Name']) ?></td>
              <td style="color:var(--text-muted)"><?= htmlspecialchars($p['Brand']) ?></td>
              <td><?= $p['Sold'] ?> units</td>
              <td style="color:var(--accent3);font-weight:600">₱<?= number_format($p['Revenue'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Recent POs -->
<div class="card" style="margin-top:0">
  <div class="card-title">Recent Purchase Orders
    <a href="/pages/purchase_orders.php" class="btn btn-ghost btn-sm">View all</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Supplier</th>
          <th>Order Date</th>
          <th>Arrival</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentPOs as $po): ?>
          <tr>
            <td style="color:var(--text-muted)">PO-<?= str_pad($po['ID'], 4, '0', STR_PAD_LEFT) ?></td>
            <td><?= htmlspecialchars($po['SupplierName']) ?></td>
            <td><?= date('M d, Y', strtotime($po['OrderDate'])) ?></td>
            <td><?= $po['ArrivalDate'] ? date('M d, Y', strtotime($po['ArrivalDate'])) : '—' ?></td>
            <td>
              <?php $c = match ($po['Status']) {
                'Received' => 'green',
                'Approved' => 'blue',
                'Ordered' => 'orange',
                'Pending' => 'yellow',
                'Cancelled' => 'red',
                default => 'gray'
              }; ?>
              <span class="badge badge-<?= $c ?>"><?= $po['Status'] ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>