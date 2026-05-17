<?php
// pages/reports.php
require_once '../includes/config.php';
requireLogin(['Admin']);
$pageTitle = 'Reports';
$db = getDB();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Summary
$summary = $db->prepare("SELECT COUNT(*) AS TxnCount, COALESCE(SUM(TotalAmount),0) AS Revenue FROM Transaction WHERE DATE(TransactionDate) BETWEEN ? AND ? AND Status='Completed'");
$summary->execute([$from, $to]);
$summary = $summary->fetch();

$voids = $db->prepare("SELECT COUNT(*) FROM Transaction WHERE DATE(TransactionDate) BETWEEN ? AND ? AND Status='Voided'");
$voids->execute([$from, $to]);
$voidCount = $voids->fetchColumn();

// Sales by day
$byDay = $db->prepare("SELECT DATE(TransactionDate) AS Day, COUNT(*) AS TxnCount, SUM(TotalAmount) AS Revenue FROM Transaction WHERE DATE(TransactionDate) BETWEEN ? AND ? AND Status='Completed' GROUP BY Day ORDER BY Day");
$byDay->execute([$from, $to]);
$dailySales = $byDay->fetchAll();

// Top products in range
$topProds = $db->prepare("
    SELECT p.Name,p.Brand,c.Name AS CategoryName,SUM(si.Quantity) AS Sold,SUM(si.Subtotal) AS Revenue
    FROM SaleItem si
    JOIN Transaction t ON si.Transaction_ID=t.ID
    JOIN Product p ON si.Product_ID=p.ID
    LEFT JOIN Category c ON p.Category_ID=c.ID
    WHERE DATE(t.TransactionDate) BETWEEN ? AND ? AND t.Status='Completed'
    GROUP BY p.ID ORDER BY Sold DESC LIMIT 10
");
$topProds->execute([$from, $to]);
$topProducts = $topProds->fetchAll();

// Sales by payment method
$byMethod = $db->prepare("SELECT PaymentMethod, COUNT(*) AS Count, SUM(TotalAmount) AS Revenue FROM Transaction WHERE DATE(TransactionDate) BETWEEN ? AND ? AND Status='Completed' GROUP BY PaymentMethod ORDER BY Revenue DESC");
$byMethod->execute([$from, $to]);
$byPayment = $byMethod->fetchAll();

// Cashier performance
$cashierPerf = $db->prepare("SELECT u.Name, COUNT(*) AS TxnCount, SUM(t.TotalAmount) AS Revenue FROM Transaction t JOIN User u ON t.Cashier_ID=u.ID WHERE DATE(t.TransactionDate) BETWEEN ? AND ? AND t.Status='Completed' GROUP BY u.ID ORDER BY Revenue DESC");
$cashierPerf->execute([$from, $to]);
$cashiers = $cashierPerf->fetchAll();

include '../includes/header.php';
?>
<link rel="stylesheet" href="../css/reports.css">
<?php
?>

<!-- Date range filter -->
<div class="card">
  <form method="GET" style="display:flex;gap:12px;align-items:flex-end">
    <div class="form-group" style="margin:0">
      <label class="form-label">From</label>
      <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">To</label>
      <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Generate Report</button>
    <a href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-ghost">This Month</a>
    <a href="?from=<?= date('Y-m-d', strtotime('monday this week')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-ghost">This Week</a>
    <a href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-ghost">Today</a>
  </form>
</div>

<!-- Summary Stats -->
<div class="stat-grid">
  <div class="stat-card green">
    <div class="stat-label">Total Revenue</div>
    <div class="stat-value">₱<?= number_format($summary['Revenue'], 0) ?></div>
    <div class="stat-sub"><?= date('M d', strtotime($from)) ?> – <?= date('M d', strtotime($to)) ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Transactions</div>
    <div class="stat-value"><?= $summary['TxnCount'] ?></div>
    <div class="stat-sub">Completed</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Avg. Transaction</div>
    <div class="stat-value">₱<?= $summary['TxnCount'] > 0 ? number_format($summary['Revenue'] / $summary['TxnCount'], 0) : 0 ?></div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Voided</div>
    <div class="stat-value"><?= $voidCount ?></div>
    <div class="stat-sub">Voided transactions</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

  <!-- Daily Sales -->
  <div class="card">
    <div class="card-title">Daily Sales Breakdown</div>
    <?php if (empty($dailySales)): ?>
      <div class="empty-state">
        <div class="empty-icon">📊</div>
        <p>No sales data for this period.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Transactions</th>
              <th>Revenue</th>
              <th>Avg</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dailySales as $d): ?>
              <tr>
                <td style="font-weight:600"><?= date('D, M d, Y', strtotime($d['Day'])) ?></td>
                <td><?= $d['TxnCount'] ?></td>
                <td style="color:var(--accent3);font-weight:600">₱<?= number_format($d['Revenue'], 2) ?></td>
                <td style="color:var(--text-muted)">₱<?= number_format($d['Revenue'] / $d['TxnCount'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Payment Methods -->
  <div class="card">
    <div class="card-title">By Payment Method</div>
    <?php if (empty($byPayment)): ?>
      <div class="empty-state">
        <div class="empty-icon">💳</div>
        <p>No data.</p>
      </div>
    <?php else: ?>
      <?php $maxRev = max(array_column($byPayment, 'Revenue'));
      foreach ($byPayment as $pm): ?>
        <div style="margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
            <span style="font-weight:600"><?= $pm['PaymentMethod'] ?></span>
            <span style="color:var(--accent3)">₱<?= number_format($pm['Revenue'], 2) ?> (<?= $pm['Count'] ?> txns)</span>
          </div>
          <div style="height:6px;background:var(--surface2);border-radius:3px">
            <div style="height:6px;background:var(--accent);border-radius:3px;width:<?= ($maxRev > 0 ? ($pm['Revenue'] / $maxRev * 100) : 0) ?>%"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <!-- Top Products -->
  <div class="card">
    <div class="card-title">Top Products by Units Sold</div>
    <?php if (empty($topProducts)): ?>
      <div class="empty-state">
        <div class="empty-icon">📦</div>
        <p>No data.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Product</th>
              <th>Category</th>
              <th>Units</th>
              <th>Revenue</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topProducts as $tp): ?>
              <tr>
                <td>
                  <div style="font-weight:600;font-size:12.5px"><?= htmlspecialchars($tp['Name']) ?></div>
                  <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($tp['Brand']) ?></div>
                </td>
                <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($tp['CategoryName']) ?></span></td>
                <td><?= $tp['Sold'] ?></td>
                <td style="color:var(--accent3)">₱<?= number_format($tp['Revenue'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Cashier Performance -->
  <div class="card">
    <div class="card-title">Cashier Performance</div>
    <?php if (empty($cashiers)): ?>
      <div class="empty-state">
        <div class="empty-icon">👤</div>
        <p>No data.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Cashier</th>
              <th>Transactions</th>
              <th>Total Sales</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cashiers as $c): ?>
              <tr>
                <td style="font-weight:600"><?= htmlspecialchars($c['Name']) ?></td>
                <td><?= $c['TxnCount'] ?></td>
                <td style="color:var(--accent);font-weight:700">₱<?= number_format($c['Revenue'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php include '../includes/footer.php'; ?>