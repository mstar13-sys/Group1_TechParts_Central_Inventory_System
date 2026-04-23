<?php
// inventory.php — Full CRUD with PHP validation
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];
$form   = []; // repopulate form on error

// ── Validation helper ─────────────────────────────────────
function validate_product(array $d): array
{
    $errs = [];
    if (empty(trim($d['sku'])))      $errs['sku']      = 'SKU is required.';
    elseif (!preg_match('/^[A-Z0-9\-]{2,20}$/i', $d['sku']))
        $errs['sku']      = 'SKU: 2–20 alphanumeric/dash chars only.';
    if (empty(trim($d['name'])))     $errs['name']     = 'Product name is required.';
    elseif (strlen($d['name']) > 150) $errs['name']     = 'Name too long (max 150 chars).';
    if (empty(trim($d['category']))) $errs['category'] = 'Category is required.';
    if (!is_numeric($d['qty']) || (int)$d['qty'] < 0)
        $errs['qty']      = 'Quantity must be 0 or more.';
    if (!is_numeric($d['price']) || (float)$d['price'] < 0)
        $errs['price']    = 'Price must be 0 or more.';
    if (!is_numeric($d['reorder_lvl']) || (int)$d['reorder_lvl'] < 0)
        $errs['reorder_lvl'] = 'Reorder level must be 0 or more.';
    return $errs;
}

// ── Handle POST: ADD ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $form   = $_POST;
    $errors = validate_product($form);

    if (empty($errors)) {
        $sku   = strtoupper(trim($form['sku']));
        $name  = trim($form['name']);
        $cat   = trim($form['category']);
        $qty   = (int)$form['qty'];
        $price = (float)$form['price'];
        $rl    = (int)$form['reorder_lvl'];

        // Check duplicate SKU
        $chk = $conn->prepare("SELECT id FROM products WHERE sku = ?");
        $chk->execute([$sku]);
        if ($chk->rowCount() > 0) {
            $errors['sku'] = 'SKU already exists.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO products (sku, name, category, qty, price, reorder_lvl) VALUES (?,?,?,?,?,?)"
            );
            $stmt->execute([$sku, $name, $cat, $qty, $price, $rl]);

            // Log transaction
            $pid = $conn->lastInsertId();
            if ($qty > 0) {
                $log = $conn->prepare(
                    "INSERT INTO transactions (product_id, type, qty_change, note) VALUES (?, 'restock', ?, ?)"
                );
                $log->execute([$pid, $qty, 'Initial stock entry']);
            }

            flash("Product $sku added successfully.", 'success');
            header('Location: inventory.php');
            exit;
        }
    }
}

// EDIT 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $form    = $_POST;
    $errors  = validate_product($form);
    $edit_id = (int)($form['id'] ?? 0);

    if ($edit_id < 1) $errors['id'] = 'Invalid product.';

    if (empty($errors)) {
        $sku   = strtoupper(trim($form['sku']));
        $name  = trim($form['name']);
        $cat   = trim($form['category']);
        $qty   = (int)$form['qty'];
        $price = (float)$form['price'];
        $rl    = (int)$form['reorder_lvl'];

        // Duplicate SKU check (exclude self)
        $chk = $conn->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $chk->execute([$sku, $edit_id]);
        if ($chk->rowCount() > 0) {
            $errors['sku'] = 'SKU already used by another product.';
        } else {
            // Detect qty change for transaction log
            $old      = $conn->prepare("SELECT qty FROM products WHERE id = ?");
            $old->execute([$edit_id]);
            $old_row  = $old->fetch(PDO::FETCH_ASSOC);
            $qty_diff = $qty - (int)($old_row['qty'] ?? 0);

            $stmt = $conn->prepare(
                "UPDATE products SET sku=?, name=?, category=?, qty=?, price=?, reorder_lvl=? WHERE id=?"
            );
            $stmt->execute([$sku, $name, $cat, $qty, $price, $rl, $edit_id]);

            // Log adjustment if qty changed
            if ($qty_diff !== 0) {
                $type = $qty_diff > 0 ? 'restock' : 'sale';
                $log  = $conn->prepare(
                    "INSERT INTO transactions (product_id, type, qty_change, note) VALUES (?, ?, ?, ?)"
                );
                $log->execute([$edit_id, $type, $qty_diff, 'Manual adjustment via inventory edit']);
            }

            flash("Product $sku updated.", 'success');
            header('Location: inventory.php');
            exit;
        }
    }
}

//DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = (int)($_POST['id'] ?? 0);
    if ($del_id > 0) {
        $row = $conn->prepare("SELECT sku FROM products WHERE id = ?");
        $row->execute([$del_id]);
        $del_row = $row->fetch(PDO::FETCH_ASSOC);

        $conn->prepare("DELETE FROM products WHERE id = ?")->execute([$del_id]);
        flash('Product ' . ($del_row['sku'] ?? '') . ' deleted.', 'warning');
    }
    header('Location: inventory.php');
    exit;
}

// Filter / Searc
$search   = trim($_GET['search'] ?? '');
$cat_filt = trim($_GET['category'] ?? '');

$where  = '1=1';
$params = [];

if ($search !== '') {
    $where    .= " AND (sku LIKE ? OR name LIKE ?)";
    $like      = "%$search%";
    $params[]  = $like;
    $params[]  = $like;
}
if ($cat_filt !== '') {
    $where   .= " AND category = ?";
    $params[] = $cat_filt;
}

$stmt = $conn->prepare("SELECT * FROM products WHERE $where ORDER BY name ASC");
$stmt->execute($params);
$products = $stmt;

// For edit: load product data if ?edit=id
$edit_product = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $ep = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $ep->execute([$edit_id]);
    $edit_product = $ep->fetch(PDO::FETCH_ASSOC);
    if (!empty($form)) $edit_product = array_merge($edit_product, $form); // repopulate on error
}

// Categories for filter dropdown
$categories = $conn->query("SELECT DISTINCT category FROM products ORDER BY category")
    ->fetchAll(PDO::FETCH_ASSOC);

// Page output 
$page_title = 'Inventory';
$active_nav = 'inventory';
require_once 'includes/header.php';
?>

<!-- FLASH -->
<div><?php show_flash(); ?></div>

<!-- TOOLBAR -->
<div class="table-wrap" style="margin-bottom:16px">
    <div class="table-header">
        <form class="filter-bar" method="GET">
            <input type="text" name="search" placeholder="Search SKU or name…"
                value="<?= e($search) ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c['category']) ?>"
                        <?= $cat_filt === $c['category'] ? 'selected' : '' ?>>
                        <?= e($c['category']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="inventory.php" class="btn" style="background:var(--surface2);color:var(--text)">Reset</a>
        </form>
        <button class="btn btn-primary" onclick="openModal('addModal')">＋ Add Product</button>
    </div>
</div>

<!-- ERRORS (shown when form fails) -->
<?php if (!empty($errors)): ?>
    <div class="alerts" style="margin-bottom:16px">
        <?php foreach ($errors as $err): ?>
            <div class="alert critical"><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- INVENTORY TABLE -->
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Reorder Lvl</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $products->fetch(PDO::FETCH_ASSOC)):
                if ($row['qty'] == 0) {
                    $sc = 'out';
                    $sl = 'Out of Stock';
                } elseif ($row['qty'] <= $row['reorder_lvl']) {
                    $sc = 'low';
                    $sl = 'Reorder Soon';
                } else {
                    $sc = 'instock';
                    $sl = 'In Stock';
                }
            ?>
                <tr>
                    <td class="sku"><?= e($row['sku']) ?></td>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['category']) ?></td>
                    <td><?= $row['qty'] ?></td>
                    <td>₱<?= number_format($row['price'], 2) ?></td>
                    <td><?= $row['reorder_lvl'] ?></td>
                    <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
                    <td>
                        <a href="inventory.php?edit=<?= $row['id'] ?>"
                            class="btn btn-primary btn-sm">Edit</a>
                        <form method="POST" style="display:inline"
                            onsubmit="return confirm('Delete <?= e($row['sku']) ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- ═══════════════════════════════════════════════════════
     ADD MODAL
═══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="addModal"
    <?= (!empty($errors) && ($_POST['action'] ?? '') === 'add') ? 'style="display:flex"' : '' ?>>
    <div class="modal">
        <h2>＋ Add New Product</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-row">
                <div class="form-group">
                    <label>SKU *</label>
                    <input type="text" name="sku" value="<?= e($form['sku'] ?? '') ?>"
                        placeholder="e.g. CPU-001">
                    <?php if (!empty($errors['sku'])): ?>
                        <p class="form-error"><?= e($errors['sku']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <input type="text" name="category" value="<?= e($form['category'] ?? '') ?>"
                        placeholder="e.g. CPU">
                    <?php if (!empty($errors['category'])): ?>
                        <p class="form-error"><?= e($errors['category']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" value="<?= e($form['name'] ?? '') ?>"
                    placeholder="e.g. Ryzen 5 5600X Processor">
                <?php if (!empty($errors['name'])): ?>
                    <p class="form-error"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="qty" min="0" value="<?= e($form['qty'] ?? '0') ?>">
                    <?php if (!empty($errors['qty'])): ?>
                        <p class="form-error"><?= e($errors['qty']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Price (₱) *</label>
                    <input type="number" name="price" min="0" step="0.01"
                        value="<?= e($form['price'] ?? '0.00') ?>">
                    <?php if (!empty($errors['price'])): ?>
                        <p class="form-error"><?= e($errors['price']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Reorder Level *</label>
                <input type="number" name="reorder_lvl" min="0"
                    value="<?= e($form['reorder_lvl'] ?? '10') ?>">
                <?php if (!empty($errors['reorder_lvl'])): ?>
                    <p class="form-error"><?= e($errors['reorder_lvl']) ?></p>
                <?php endif; ?>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn" style="background:var(--surface2);color:var(--text)"
                    onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT MODAL (pre-filled via ?edit=id)
═══════════════════════════════════════════════════════ -->
<?php if ($edit_product): ?>
    <div class="modal-overlay open" id="editModal">
        <div class="modal">
            <h2>✏ Edit Product</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $edit_product['id'] ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>SKU *</label>
                        <input type="text" name="sku" value="<?= e($edit_product['sku']) ?>">
                        <?php if (!empty($errors['sku'])): ?>
                            <p class="form-error"><?= e($errors['sku']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <input type="text" name="category" value="<?= e($edit_product['category']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" value="<?= e($edit_product['name']) ?>">
                    <?php if (!empty($errors['name'])): ?>
                        <p class="form-error"><?= e($errors['name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input type="number" name="qty" min="0" value="<?= e($edit_product['qty']) ?>">
                        <?php if (!empty($errors['qty'])): ?>
                            <p class="form-error"><?= e($errors['qty']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Price (₱) *</label>
                        <input type="number" name="price" min="0" step="0.01"
                            value="<?= e($edit_product['price']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Reorder Level *</label>
                    <input type="number" name="reorder_lvl" min="0"
                        value="<?= e($edit_product['reorder_lvl']) ?>">
                </div>

                <div class="modal-actions">
                    <a href="inventory.php" class="btn" style="background:var(--surface2);color:var(--text)">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('open');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    // Close overlay on backdrop click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>