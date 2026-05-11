<?php
// api/po_detail.php
// ─────────────────────────────────────────────────────────────────
// Called by fetch('/api/po_detail.php?id=N') from purchase_orders.php.
// Returns a self-contained HTML fragment (no <html>/<body> wrapper)
// that is injected into the #view-po-body modal div.
// ─────────────────────────────────────────────────────────────────

require_once '../includes/config.php';

// ── Auth: same session check as every other page ─────────────────
requireLogin();

// ── Only respond to GET requests ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo '<p class="api-error">Method not allowed.</p>';
    exit;
}

// ── Validate the ?id= parameter ──────────────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$id) {
    http_response_code(400);
    echo '<p class="api-error">Invalid or missing Purchase Order ID.</p>';
    exit;
}

// ── Fetch PO header ───────────────────────────────────────────────
$db = getDB();

$poStmt = $db->prepare("
    SELECT po.*,
           s.Name    AS SupplierName,
           s.Email   AS SupplierEmail,
           s.Phone   AS SupplierPhone,
           s.Address AS SupplierAddress,
           u.Name    AS CreatedBy
    FROM PurchaseOrder po
    JOIN Supplier s ON po.Supplier_ID = s.ID
    JOIN User     u ON po.User_ID     = u.ID
    WHERE po.ID = ?
");
$poStmt->execute([$id]);
$po = $poStmt->fetch();

if (!$po) {
    http_response_code(404);
    echo '<p class="api-error">Purchase Order not found.</p>';
    exit;
}

// ── Fetch PO line items ───────────────────────────────────────────
$itemsStmt = $db->prepare("
    SELECT poi.QuantityOrdered,
           poi.UnitCost,
           (poi.QuantityOrdered * poi.UnitCost) AS LineTotal,
           p.Name  AS ProductName,
           p.Brand AS ProductBrand
    FROM PurchaseOrderItem poi
    JOIN Product p ON poi.Product_ID = p.ID
    WHERE poi.PurchaseOrder_ID = ?
    ORDER BY p.Name
");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

// ── Computed totals ───────────────────────────────────────────────
$grandTotal = array_sum(array_column($items, 'LineTotal'));
$totalQty   = array_sum(array_column($items, 'QuantityOrdered'));

// ── Status → badge colour map ─────────────────────────────────────
$statusColor = match($po['Status']) {
    'Received'  => 'green',
    'Approved'  => 'blue',
    'Ordered'   => 'orange',
    'Pending'   => 'yellow',
    'Cancelled' => 'red',
    default     => 'gray',
};

// ── Helpers ───────────────────────────────────────────────────────
$h  = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$dt = fn(?string $v): string => $v ? date('M d, Y', strtotime($v)) : '—';

// ── Output HTML fragment ──────────────────────────────────────────
// No DOCTYPE / html / body — this is injected directly into a modal div.
?>
<div class="po-detail">

  <!-- ── Header summary ──────────────────────────────────────── -->
  <div class="po-detail-header">
    <div class="po-detail-meta">

      <div class="po-meta-row">
        <span class="po-meta-label">Status</span>
        <span class="badge badge-<?= $h($statusColor) ?>">
          <?= $h($po['Status']) ?>
        </span>
      </div>

      <div class="po-meta-row">
        <span class="po-meta-label">Order Date</span>
        <span><?= $h(date('M d, Y  H:i', strtotime($po['OrderDate']))) ?></span>
      </div>

      <div class="po-meta-row">
        <span class="po-meta-label">Expected Arrival</span>
        <span><?= $dt($po['ArrivalDate']) ?></span>
      </div>

      <div class="po-meta-row">
        <span class="po-meta-label">Created By</span>
        <span><?= $h($po['CreatedBy']) ?></span>
      </div>

      <?php if ($po['Notes']): ?>
      <div class="po-meta-row">
        <span class="po-meta-label">Notes</span>
        <span style="color:var(--text-muted)"><?= $h($po['Notes']) ?></span>
      </div>
      <?php endif; ?>

    </div>

    <!-- Supplier card -->
    <div class="po-supplier-card">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;
                  color:var(--text-muted);margin-bottom:6px">Supplier</div>
      <div style="font-weight:700;font-size:15px;margin-bottom:4px">
        <?= $h($po['SupplierName']) ?>
      </div>
      <div class="supplier-contact"><?= $h($po['SupplierPhone']) ?></div>
      <div class="supplier-contact"><?= $h($po['SupplierEmail']) ?></div>
      <div class="supplier-contact"><?= $h($po['SupplierAddress']) ?></div>
    </div>
  </div>

  <!-- ── Line items table ─────────────────────────────────────── -->
  <div style="margin-top:18px">
    <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;
                color:var(--text-muted);margin-bottom:8px">
      Order Items
    </div>

    <?php if (empty($items)): ?>
      <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:16px 0">
        No items on this order.
      </p>
    <?php else: ?>
    <div class="table-wrap">
      <table class="po-items-table">
        <thead>
          <tr>
            <th style="text-align:left">Product</th>
            <th style="text-align:left">Brand</th>
            <th style="text-align:right">Qty</th>
            <th style="text-align:right">Unit Cost</th>
            <th style="text-align:right">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
          <tr>
            <td><?= $h($item['ProductName']) ?></td>
            <td style="color:var(--text-muted)"><?= $h($item['ProductBrand']) ?></td>
            <td style="text-align:right"><?= (int)$item['QuantityOrdered'] ?></td>
            <td style="text-align:right">₱<?= number_format((float)$item['UnitCost'], 2) ?></td>
            <td style="text-align:right;font-weight:600;color:var(--accent3)">
              ₱<?= number_format((float)$item['LineTotal'], 2) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="border-top:2px solid var(--border)">
            <td colspan="2" style="color:var(--text-muted);font-size:12px">
              <?= count($items) ?> product<?= count($items) !== 1 ? 's' : '' ?>
            </td>
            <td style="text-align:right;font-weight:600"><?= (int)$totalQty ?></td>
            <td style="text-align:right;color:var(--text-muted);font-size:12px">Grand Total</td>
            <td style="text-align:right;font-weight:700;font-size:15px;color:var(--accent3)">
              ₱<?= number_format($grandTotal, 2) ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /.po-detail -->
