<?php
// pages/products.php
require_once '../includes/config.php';
requireLogin();
$pageTitle = 'Products';
$db = getDB();
$isAdmin = in_array($_SESSION['role'], ['Admin', 'User']);

$msg = '';
$msgType = '';
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrf();
  $action = $_POST['action'] ?? '';
  if ($action === 'save') {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $catId = (int)($_POST['category_id'] ?? 0);
    if (!$name || !$price || !$brand || !$catId) {
      $msg = 'Fill all required fields.';
      $msgType = 'danger';
    } else {
      try {
        if ($id > 0) {
          $db->prepare('UPDATE Product SET Name=?,Description=?,Price=?,Brand=?,Category_ID=? WHERE ID=?')
            ->execute([$name, $desc, $price, $brand, $catId, $id]);
          $msg = 'Product updated.';
          $msgType = 'success';
        } else {
          // Insert the product first
          $db->prepare('INSERT INTO Product (Name,Description,Price,Brand,Category_ID) VALUES (?,?,?,?,?)')
            ->execute([$name, $desc, $price, $brand, $catId]);
          $newProductId = (int)$db->lastInsertId();
          $msg = 'Product added.';
          $msgType = 'success';
        }
      } catch (PDOException $e) {
        $msg = 'Error: ' . $e->getMessage();
        $msgType = 'danger';
      }
    }
  } elseif ($action === 'delete') {
    try {
      $db->prepare('DELETE FROM Product WHERE ID=?')->execute([$_POST['id']]);
      $msg = 'Product deleted.';
      $msgType = 'warning';
    } catch (PDOException $e) {
      $msg = 'Cannot delete: ' . $e->getMessage();
      $msgType = 'danger';
    }
  }
}

$categories = $db->query('SELECT * FROM Category WHERE Status="Active" ORDER BY Name')->fetchAll();
$search  = trim($_GET['q'] ?? '');
$catFilt = (int)($_GET['cat'] ?? 0);

$where = ['1=1'];
$params = [];
if ($search) {
  $where[] = '(p.Name LIKE ? OR p.Brand LIKE ?)';
  $params[] = "%" . $search . "%";
  $params[] = "%" . $search . "%";
}
if ($catFilt) {
  $where[] = 'p.Category_ID=?';
  $params[] = $catFilt;
}
$whereStr = implode(' AND ', $where);

$products = $db->prepare("
    SELECT p.*, c.Name AS CategoryName, COALESCE(SUM(s.Quantity),0) AS StockQty
    FROM Product p
    LEFT JOIN Category c ON p.Category_ID=c.ID
    LEFT JOIN Stock s ON s.Product_ID=p.ID
    WHERE $whereStr GROUP BY p.ID ORDER BY c.Name, p.Name
");
$products->execute($params);
$products = $products->fetchAll();

include '../includes/header.php';
?>
<link rel="stylesheet" href="../css/products.css">

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="toolbar">
  <form method="GET" style="display:flex;gap:10px;align-items:center">
    <div class="search-box">
      <span>🔍</span>
      <input type="text" name="q" placeholder="Search products…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="cat" class="form-control" style="width:180px" onchange="this.form.submit()">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['ID'] ?>" <?= $catFilt == $c['ID'] ? 'selected' : '' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($isAdmin): ?>
    <button class="btn btn-primary" onclick="openAddModal()">+ Add Product</button>
  <?php endif; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Brand</th>
          <th>Category</th>
          <th>Price</th>
          <th>Stock</th><?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr>
            <td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">No products found.</td>
          </tr>
        <?php endif; ?>
        <?php foreach ($products as $p): ?>
          <tr>
            <td style="color:var(--text-muted)"><?= $p['ID'] ?></td>
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($p['Name']) ?></div>
              <?php if ($p['Description']): ?><div class="product-desc-cell"><?= htmlspecialchars($p['Description']) ?></div><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['Brand']) ?></td>
            <td><span class="badge badge-blue"><?= htmlspecialchars($p['CategoryName']) ?></span></td>
            <td style="font-family:'Syne',sans-serif;font-weight:700;color:var(--accent)">₱<?= number_format($p['Price'], 2) ?></td>
            <td>
              <?php if ($p['StockQty'] == 0): ?>
                <span class="badge badge-red">Out of Stock</span>
              <?php elseif ($p['StockQty'] <= 5): ?>
                <span class="badge badge-yellow"><?= $p['StockQty'] ?> (Low)</span>
              <?php else: ?>
                <span class="badge badge-green"><?= $p['StockQty'] ?></span>
              <?php endif; ?>
            </td>
            <?php if ($isAdmin): ?>
              <td style="display:flex;gap:6px">
                <button class="btn btn-ghost btn-sm" onclick='editProduct(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_TAG) ?>)'>Edit</button>
                <form method="POST">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['ID'] ?>">
                  <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('Delete this product? This cannot be undone.',this.closest('form'))">✕</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="product-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="product-modal-title">Add Product</span>
      <button class="modal-close" onclick="closeModal('product-modal')">✕</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="p-id" value="0">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Product Name *</label>
          <input type="text" name="name" id="p-name" class="form-control" required>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Brand *</label>
            <input type="text" name="brand" id="p-brand" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Price (₱) *</label>
            <input type="number" name="price" id="p-price" class="form-control" step="0.01" min="0.01" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Category *</label>
          <select name="category_id" id="p-cat" class="form-control" required>
            <option value="">Select category…</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['ID'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="p-desc" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('product-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Product</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openAddModal() {
    document.getElementById('product-modal-title').textContent = 'Add Product';
    document.getElementById('p-id').value = 0;
    document.getElementById('p-name').value = '';
    document.getElementById('p-brand').value = '';
    document.getElementById('p-price').value = '';
    document.getElementById('p-cat').value = '';
    document.getElementById('p-desc').value = '';
    openModal('product-modal');
  }

  function editProduct(p) {
    document.getElementById('product-modal-title').textContent = 'Edit Product';
    document.getElementById('p-id').value = p.ID;
    document.getElementById('p-name').value = p.Name;
    document.getElementById('p-brand').value = p.Brand;
    document.getElementById('p-price').value = p.Price;
    document.getElementById('p-cat').value = p.Category_ID;
    document.getElementById('p-desc').value = p.Description || '';
    openModal('product-modal');
  }
</script>
<?php include '../includes/footer.php'; ?>