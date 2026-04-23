<?php
// index.php — Dashboard
require_once 'config.php';
$page_title  = 'Dashboard';
$active_nav  = 'dashboard';
require_once 'includes/header.php';

// Stat queries
$total_skus   = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_stock    = $conn->query("SELECT COUNT(*) FROM products WHERE qty > 0 AND qty <= reorder_lvl")->fetchColumn();
$out_of_stock = $conn->query("SELECT COUNT(*) FROM products WHERE qty = 0")->fetchColumn();

// Today's sales = sum of qty_change (negative) for 'sale' type today
$today_sales_row = $conn->query(
    "SELECT SUM(ABS(t.qty_change) * p.price) AS total
     FROM transactions t
     JOIN products p ON t.product_id = p.id
     WHERE t.type = 'sale'
       AND DATE(t.created_at) = CURDATE()"
)->fetch(PDO::FETCH_ASSOC);
$today_sales = $today_sales_row['total'] ?? 0;

// Pending orders (restock needed items)
$pending_orders = $conn->query(
    "SELECT COUNT(*) FROM products WHERE qty <= reorder_lvl"
)->fetchColumn();

// Alert data 
$out_items = $conn->query("SELECT sku, name FROM products WHERE qty = 0");
$low_items = $conn->query("SELECT sku, name FROM products WHERE qty > 0 AND qty <= reorder_lvl");

// Inventory table preview 
$preview = $conn->query(
    "SELECT sku, name, category, qty, reorder_lvl
     FROM products ORDER BY qty ASC LIMIT 5"
);

// Recent transactions
$recent_tx = $conn->query(
    "SELECT t.type, t.qty_change, t.note, t.created_at, p.name
     FROM transactions t
     JOIN products p ON t.product_id = p.id
     ORDER BY t.created_at DESC LIMIT 8"
);

// Category breakdown
$cat_rows  = $conn->query(
    "SELECT category, COUNT(*) AS cnt FROM products GROUP BY category ORDER BY cnt DESC"
)->fetchAll(PDO::FETCH_ASSOC);
$cat_total = array_sum(array_column($cat_rows, 'cnt')) ?: 1;
?>

<!-- FLASH -->
<div><?php show_flash(); ?></div>

<!-- STAT CARDS -->
<div class="cards">
    <div class="card">
        <p class="card-label">Total SKUs</p>
        <p class="card-value" style="color:var(--cyan)"><?= $total_skus ?></p>
    </div>
    <div class="card">
        <p class="card-label">Low Stock</p>
        <p class="card-value" style="color:var(--amber)"><?= $low_stock ?></p>
    </div>
    <div class="card">
        <p class="card-label">Out of Stock</p>
        <p class="card-value" style="color:var(--red)"><?= $out_of_stock ?></p>
    </div>
    <div class="card">
        <p class="card-label">Today's Sales</p>
        <p class="card-value" style="color:var(--green)">₱<?= number_format($today_sales, 2) ?></p>
    </div>
    <div class="card">
        <p class="card-label">Reorder Needed</p>
        <p class="card-value"><?= $pending_orders ?></p>
    </div>
</div>

<!-- ALERTS -->
<div class="alerts">
    <h2>⚠ System Alerts</h2>
    <?php while ($row = $out_items->fetch(PDO::FETCH_ASSOC)): ?>
        <div class="alert critical">
            🔴 <strong><?= e($row['sku']) ?></strong> — <?= e($row['name']) ?> is <strong>OUT OF STOCK</strong>
        </div>
    <?php endwhile; ?>
    <?php while ($row = $low_items->fetch(PDO::FETCH_ASSOC)): ?>
        <div class="alert warning">
            🟡 <strong><?= e($row['sku']) ?></strong> — <?= e($row['name']) ?> is below reorder level
        </div>
    <?php endwhile; ?>
    <div class="alert info">ℹ All other items are within normal stock levels.</div>
</div>

<!-- INVENTORY PREVIEW TABLE -->
<div class="table-wrap">
    <div class="table-header">
        <h2>📦 Inventory Snapshot (Low → High)</h2>
        <a href="inventory.php" class="btn btn-primary">View All</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $preview->fetch(PDO::FETCH_ASSOC)):
                if ($row['qty'] == 0) {
                    $status_class = 'out';
                    $status_label = 'Out of Stock';
                } elseif ($row['qty'] <= $row['reorder_lvl']) {
                    $status_class = 'low';
                    $status_label = 'Reorder Soon';
                } else {
                    $status_class = 'instock';
                    $status_label = 'In Stock';
                }
            ?>
                <tr>
                    <td class="sku"><?= e($row['sku']) ?></td>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['category']) ?></td>
                    <td><?= $row['qty'] ?></td>
                    <td><span class="badge <?= $status_class ?>"><?= $status_label ?></span></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- BOTTOM GRID -->
<div class="bottom-grid">

    <!-- RECENT TRANSACTIONS -->
    <div class="log-box">
        <h2>🧾 Recent Transactions</h2>
        <?php while ($tx = $recent_tx->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="log-entry">
                <div>
                    <span class="log-type <?= e($tx['type']) ?>">
                        <?= $tx['type'] === 'sale' ? '↓ Sale' : ($tx['type'] === 'restock' ? '↑ Restock' : '~ Adjust') ?>
                    </span>
                    — <?= e($tx['name']) ?>
                    <?php if ($tx['note']): ?>
                        <span style="color:var(--muted)"> · <?= e($tx['note']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="log-time"><?= date('M j, g:ia', strtotime($tx['created_at'])) ?></span>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- CATEGORY BREAKDOWN -->
    <div class="category-box">
        <h2>📂 Category Breakdown</h2>
        <?php foreach ($cat_rows as $cat):
            $pct = round(($cat['cnt'] / $cat_total) * 100);
        ?>
            <div class="cat-row">
                <span class="cat-label"><?= e($cat['category']) ?></span>
                <div class="cat-bar-wrap">
                    <div class="cat-bar" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="cat-pct"><?= $pct ?>%</span>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>