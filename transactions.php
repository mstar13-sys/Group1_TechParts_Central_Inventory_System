<?php
// transactions.php — View & add transactions
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];
$form   = [];

// ── Handle POST: Log a transaction ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_tx') {
    $form = $_POST;
    $pid  = (int)($form['product_id'] ?? 0);
    $type = trim($form['type'] ?? '');
    $qty  = (int)($form['qty_change'] ?? 0);
    $note = trim($form['note'] ?? '');

    // Validate
    if ($pid < 1)                                             $errors['product_id'] = 'Select a product.';
    if (!in_array($type, ['sale', 'restock', 'adjustment'])) $errors['type']       = 'Invalid type.';
    if ($qty === 0)                                           $errors['qty_change'] = 'Quantity cannot be zero.';
    if (strlen($note) > 255)                                  $errors['note']       = 'Note too long (max 255 chars).';

    if (empty($errors)) {
        // For sales, qty_change should be negative
        $actual_change = ($type === 'sale') ? -abs($qty) : abs($qty);

        // Don't let stock go below 0
        $cur = $conn->prepare("SELECT qty FROM products WHERE id = ?");
        $cur->execute([$pid]);
        $cur_row = $cur->fetch(PDO::FETCH_ASSOC);
        $new_qty = (int)$cur_row['qty'] + $actual_change;

        if ($new_qty < 0) {
            $errors['qty_change'] = 'Not enough stock. Current qty: ' . $cur_row['qty'];
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO transactions (product_id, type, qty_change, note) VALUES (?,?,?,?)"
            );
            $stmt->execute([$pid, $type, $actual_change, $note]);

            // Update product qty
            $upd = $conn->prepare("UPDATE products SET qty = qty + ? WHERE id = ?");
            $upd->execute([$actual_change, $pid]);

            flash('Transaction logged successfully.', 'success');
            header('Location: transactions.php');
            exit;
        }
    }
}

// ── Filter ────────────────────────────────────────────────
$type_filt = trim($_GET['type'] ?? '');
$where     = '1=1';
$params    = [];

if ($type_filt && in_array($type_filt, ['sale', 'restock', 'adjustment'])) {
    $where   .= ' AND t.type = ?';
    $params[] = $type_filt;
}

$stmt = $conn->prepare(
    "SELECT t.*, p.name AS product_name, p.sku
     FROM transactions t
     JOIN products p ON t.product_id = p.id
     WHERE $where
     ORDER BY t.created_at DESC
     LIMIT 100"
);
$stmt->execute($params);
$txs = $stmt;

// Products for the form dropdown
$all_products = $conn->query("SELECT id, sku, name FROM products ORDER BY name");

$page_title = 'Transactions';
$active_nav = 'transactions';
require_once 'includes/header.php';
?>

<!-- FLASH -->
<div><?php show_flash(); ?></div>

<!-- TOOLBAR -->
<div class="table-wrap" style="margin-bottom:16px">
    <div class="table-header">
        <form class="filter-bar" method="GET">
            <select name="type">
                <option value="">All Types</option>
                <option value="sale"       <?= $type_filt === 'sale'       ? 'selected' : '' ?>>Sale</option>
                <option value="restock"    <?= $type_filt === 'restock'    ? 'selected' : '' ?>>Restock</option>
                <option value="adjustment" <?= $type_filt === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="transactions.php" class="btn" style="background:var(--surface2);color:var(--text)">Reset</a>
        </form>
        <button class="btn btn-primary" onclick="openModal('addTxModal')">＋ Log Transaction</button>
    </div>
</div>

<!-- ERRORS -->
<?php if (!empty($errors)): ?>
    <div class="alerts" style="margin-bottom:16px">
        <?php foreach ($errors as $err): ?>
            <div class="alert critical"><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- TRANSACTIONS TABLE -->
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date / Time</th>
                <th>SKU</th>
                <th>Product</th>
                <th>Type</th>
                <th>Qty Change</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($tx = $txs->fetch(PDO::FETCH_ASSOC)):
            $sign  = $tx['qty_change'] > 0 ? '+' : '';
            $color = $tx['qty_change'] > 0 ? 'var(--green)' : 'var(--red)';
        ?>
            <tr>
                <td style="color:var(--muted)"><?= $tx['id'] ?></td>
                <td style="color:var(--muted);font-size:12px">
                    <?= date('M j, Y g:ia', strtotime($tx['created_at'])) ?>
                </td>
                <td class="sku"><?= e($tx['sku']) ?></td>
                <td><?= e($tx['product_name']) ?></td>
                <td>
                    <span class="badge <?=
                        $tx['type'] === 'sale'     ? 'out' :
                        ($tx['type'] === 'restock' ? 'instock' : 'low') ?>">
                        <?= ucfirst(e($tx['type'])) ?>
                    </span>
                </td>
                <td style="color:<?= $color ?>;font-family:'IBM Plex Mono',monospace;font-weight:600">
                    <?= $sign . $tx['qty_change'] ?>
                </td>
                <td style="color:var(--muted)"><?= e($tx['note'] ?? '—') ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- ═══════════════════════════════════════════════════════
     ADD TRANSACTION MODAL
═══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="addTxModal"
     <?= (!empty($errors)) ? 'style="display:flex"' : '' ?>>
    <div class="modal">
        <h2>＋ Log Transaction</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_tx">

            <div class="form-group">
                <label>Product *</label>
                <select name="product_id">
                    <option value="">— Select product —</option>
                    <?php while ($p = $all_products->fetch(PDO::FETCH_ASSOC)): ?>
                        <option value="<?= $p['id'] ?>"
                            <?= ($form['product_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['sku']) ?> — <?= e($p['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <?php if (!empty($errors['product_id'])): ?>
                    <p class="form-error"><?= e($errors['product_id']) ?></p>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Type *</label>
                    <select name="type">
                        <option value="">— Select —</option>
                        <option value="sale"       <?= ($form['type'] ?? '') === 'sale'       ? 'selected' : '' ?>>Sale</option>
                        <option value="restock"    <?= ($form['type'] ?? '') === 'restock'    ? 'selected' : '' ?>>Restock</option>
                        <option value="adjustment" <?= ($form['type'] ?? '') === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                    </select>
                    <?php if (!empty($errors['type'])): ?>
                        <p class="form-error"><?= e($errors['type']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="qty_change" min="1"
                           value="<?= e($form['qty_change'] ?? '') ?>" placeholder="e.g. 5">
                    <?php if (!empty($errors['qty_change'])): ?>
                        <p class="form-error"><?= e($errors['qty_change']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Note (optional)</label>
                <input type="text" name="note" value="<?= e($form['note'] ?? '') ?>"
                       placeholder="e.g. Supplier delivery">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn" style="background:var(--surface2);color:var(--text)"
                        onclick="closeModal('addTxModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Log Transaction</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>

<?php require_once 'includes/footer.php'; ?>
