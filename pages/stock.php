<?php
// pages/stock.php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Stock Management';
$db = getDB();
$isAdmin = in_array($_SESSION['role'], ['Admin', 'User']);

$msg = '';
$msgType = '';
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrf();
  $action = $_POST['action'] ?? '';
  if ($action === 'adjust') {
    $id  = (int)($_POST['stock_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);
    $min = (int)($_POST['min_stock'] ?? 5);
    if ($id && $qty >= 0) {
      $db->prepare('UPDATE Stock SET Quantity=?,MinStock=? WHERE ID=?')->execute([$qty, $min, $id]);
      $msg = 'Stock updated.';
      $msgType = 'success';
    }
  }
}

$search  = trim($_GET['q'] ?? '');
$filter  = $_GET['filter'] ?? '';

// Build WHERE — note: use COALESCE so products without a Stock row get Quantity=0
$where = ['1=1'];
$params = [];
if ($search) {
  $where[] = '(p.Name LIKE ? OR p.Brand LIKE ?)';
  $params[] = "%" . $search . "%";
  $params[] = "%" . $search . "%";
}
if ($filter === 'low') {
  $where[] = 's.ID IS NOT NULL AND s.Quantity<=s.MinStock AND s.Quantity>0';
}
if ($filter === 'empty') {
  $where[] = 's.ID IS NULL OR s.Quantity=0';
}
$whereStr = implode(' AND ', $where);

// Start from Product and LEFT JOIN Stock so ALL products appear even if they have no Stock row
$stock = $db->prepare("
    SELECT s.ID AS StockID, s.Quantity, s.MinStock, s.Supplier_ID, s.LastUpdated,
           p.ID AS ProductID, p.Name AS ProductName, p.Brand, p.Price,
           c.Name AS CategoryName,
           sup.Name AS SupplierName
    FROM Product p
    LEFT JOIN Stock s ON s.Product_ID = p.ID
    LEFT JOIN Category c ON p.Category_ID = c.ID
    LEFT JOIN Supplier sup ON s.Supplier_ID = sup.ID
    WHERE $whereStr
    ORDER BY COALESCE(s.Quantity, 0) ASC, p.Name
");
$stock->execute($params);
$stockItems = $stock->fetchAll();

$alerts = $db->query("
    SELECT
      SUM(stock_qty > 0 AND stock_qty <= min_stock) AS low_stock,
      SUM(stock_qty = 0) AS out_of_stock
    FROM (
      SELECT p.ID,
             COALESCE(SUM(s.Quantity), 0) AS stock_qty,
             COALESCE(MAX(s.MinStock), 5) AS min_stock
      FROM Product p
      LEFT JOIN Stock s ON s.Product_ID = p.ID
      GROUP BY p.ID
    ) product_stock
")->fetch();
$lowCount   = (int)$alerts['low_stock'];
$emptyCount = (int)$alerts['out_of_stock'];
$totalValue = $db->query('SELECT COALESCE(SUM(s.Quantity*p.Price),0) FROM Stock s JOIN Product p ON s.Product_ID=p.ID')->fetchColumn();

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/css/stock.css">

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Total Products</div>
    <div class="stat-value"><?= count($stockItems) ?></div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Low Stock</div>
    <div class="stat-value"><?= $lowCount ?></div>
    <div class="stat-sub">At or below reorder point</div>
  </div>
  <div class="stat-card red">
    <div class="stat-label">Out of Stock</div>
    <div class="stat-value"><?= $emptyCount ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-label">Inventory Value</div>
    <div class="stat-value">₱<?= number_format($totalValue, 0) ?></div>
    <div class="stat-sub">At selling price</div>
  </div>
</div>

<div class="toolbar">
  <form method="GET" style="display:flex;gap:10px;align-items:center">
    <div class="search-box">
      <span>🔍</span>
      <input type="text" name="q" placeholder="Search products…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <div style="display:flex;gap:6px">
      <a href="?filter=" class="btn btn-ghost btn-sm <?= $filter === '' ? 'btn-primary' : '' ?>">All</a>
      <a href="?filter=low" class="btn btn-ghost btn-sm <?= $filter === 'low' ? 'btn-primary' : '' ?>">⚠ Low (<?= $lowCount ?>)</a>
      <a href="?filter=empty" class="btn btn-ghost btn-sm <?= $filter === 'empty' ? 'btn-primary' : '' ?>">✕ Out (<?= $emptyCount ?>)</a>
    </div>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>Brand</th>
          <th>Category</th>
          <th>Supplier</th>
          <th>Qty</th>
          <th>Min Stock</th>
          <th>Unit Price</th>
          <th>Value</th>
          <th>Last Updated</th><?php if ($isAdmin): ?><th>Adjust</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stockItems as $s): ?>
          <?php $qty = $s['StockID'] ? (int)$s['Quantity'] : null; ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($s['ProductName']) ?></td>
            <td><?= htmlspecialchars($s['Brand']) ?></td>
            <td><span class="badge badge-blue"><?= htmlspecialchars($s['CategoryName']) ?></span></td>
            <td style="color:var(--text-muted);font-size:12px"><?= htmlspecialchars($s['SupplierName'] ?? '—') ?></td>
            <td>
              <?php if ($s['StockID'] === null): ?>
                <span class="stock-none">No record</span>
              <?php elseif ($qty == 0): ?>
                <span class="out-of-stock-text"><?= $qty ?></span>
              <?php elseif ($qty <= $s['MinStock']): ?>
                <span class="low-stock"><?= $qty ?> ⚠</span>
              <?php else: ?>
                <span style="color:var(--accent3);font-weight:600"><?= $qty ?></span>
              <?php endif; ?>
            </td>
            <td style="color:var(--text-muted)"><?= $s['StockID'] ? $s['MinStock'] : '—' ?></td>
            <td>₱<?= number_format($s['Price'], 2) ?></td>
            <td style="color:var(--accent3)">₱<?= number_format(($qty ?? 0) * $s['Price'], 2) ?></td>
            <td style="color:var(--text-muted);font-size:12px"><?= $s['StockID'] ? date('M d, Y', strtotime($s['LastUpdated'])) : '—' ?></td>
            <?php if ($isAdmin): ?>
              <td>
                <?php if ($s['StockID']): ?>
                  <button class="btn btn-ghost btn-sm" onclick='adjustStock(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_TAG) ?>)'>Adjust</button>
                <?php else: ?>
                  <span class="stock-none">No stock row</span>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Adjust Modal -->
<div class="modal-overlay" id="adjust-modal">
  <div class="modal" style="width:360px">
    <div class="modal-header">
      <span class="modal-title">Adjust Stock</span>
      <button class="modal-close" onclick="closeModal('adjust-modal')">✕</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="adjust">
      <input type="hidden" name="stock_id" id="adj-id">
      <div class="modal-body">
        <p id="adj-product" style="font-weight:600;margin-bottom:14px;color:var(--accent)"></p>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">New Quantity</label>
            <input type="number" name="quantity" id="adj-qty" class="form-control" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Min Stock (reorder)</label>
            <input type="number" name="min_stock" id="adj-min" class="form-control" min="0" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('adjust-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
<script>
  function adjustStock(s) {
    document.getElementById('adj-id').value = s.StockID;
    document.getElementById('adj-product').textContent = s.ProductName + ' — Current: ' + s.Quantity;
    document.getElementById('adj-qty').value = s.Quantity;
    document.getElementById('adj-min').value = s.MinStock;
    openModal('adjust-modal');
  }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>