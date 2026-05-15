<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  exit('Invalid purchase order.');
}

$poStmt = $db->prepare("
  SELECT po.*, s.Name AS SupplierName, u.Name AS CreatedBy
  FROM PurchaseOrder po
  JOIN Supplier s ON po.Supplier_ID = s.ID
  JOIN User u ON po.User_ID = u.ID
  WHERE po.ID = ?
");
$poStmt->execute([$id]);
$po = $poStmt->fetch();

if (!$po) {
  http_response_code(404);
  exit('Purchase order not found.');
}

$itemsStmt = $db->prepare("
  SELECT poi.*, p.Name, p.Brand
  FROM PurchaseOrderItem poi
  JOIN Product p ON poi.Product_ID = p.ID
  WHERE poi.PurchaseOrder_ID = ?
  ORDER BY poi.ID
");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();
$total = array_sum(array_map(fn($i) => $i['QuantityOrdered'] * $i['UnitCost'], $items));
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;font-size:13px">
  <div><span style="color:var(--text-muted)">Supplier:</span> <?= htmlspecialchars($po['SupplierName']) ?></div>
  <div><span style="color:var(--text-muted)">Created by:</span> <?= htmlspecialchars($po['CreatedBy']) ?></div>
  <div><span style="color:var(--text-muted)">Order date:</span> <?= date('M d, Y', strtotime($po['OrderDate'])) ?></div>
  <div><span style="color:var(--text-muted)">Arrival:</span> <?= $po['ArrivalDate'] ? date('M d, Y', strtotime($po['ArrivalDate'])) : '-' ?></div>
  <div><span style="color:var(--text-muted)">Status:</span> <?= htmlspecialchars($po['Status']) ?></div>
</div>

<?php if (!empty($po['Notes'])): ?>
  <div class="alert alert-info"><?= htmlspecialchars($po['Notes']) ?></div>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Product</th>
        <th>Brand</th>
        <th>Qty</th>
        <th>Unit Cost</th>
        <th>Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <?php $lineTotal = $item['QuantityOrdered'] * $item['UnitCost']; ?>
        <tr>
          <td><?= htmlspecialchars($item['Name']) ?></td>
          <td style="color:var(--text-muted)"><?= htmlspecialchars($item['Brand']) ?></td>
          <td><?= (int)$item['QuantityOrdered'] ?></td>
          <td>PHP <?= number_format($item['UnitCost'], 2) ?></td>
          <td style="font-weight:600">PHP <?= number_format($lineTotal, 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div style="margin-top:14px;text-align:right;font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:var(--accent)">
  Total: PHP <?= number_format($total, 2) ?>
</div>