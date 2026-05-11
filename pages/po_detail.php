<?php
// pages/po_detail.php — returns HTML fragment for the view PO modal
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$db = getDB();
$id = (int)($_GET['id']??0);
if (!$id) { echo '<p style="color:var(--danger)">Invalid PO ID.</p>'; exit; }

$po = $db->prepare("SELECT po.*,s.Name AS SupplierName,s.Phone AS SupplierPhone,s.Email AS SupplierEmail,u.Name AS CreatedBy FROM PurchaseOrder po JOIN Supplier s ON po.Supplier_ID=s.ID JOIN User u ON po.User_ID=u.ID WHERE po.ID=?");
$po->execute([$id]);
$po = $po->fetch();
if (!$po) { echo '<p style="color:var(--danger)">PO not found.</p>'; exit; }

$items = $db->prepare("SELECT poi.*,p.Name AS ProductName,p.Brand FROM PurchaseOrderItem poi JOIN Product p ON poi.Product_ID=p.ID WHERE poi.PurchaseOrder_ID=?");
$items->execute([$id]);
$items = $items->fetchAll();
$total = array_sum(array_map(fn($i)=>$i['QuantityOrdered']*$i['UnitCost'],$items));
$c = match($po['Status']){'Received'=>'green','Approved'=>'blue','Ordered'=>'orange','Pending'=>'yellow','Cancelled'=>'red',default=>'gray'};
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;font-size:13px">
  <div><span style="color:var(--text-muted)">Supplier:</span> <strong><?=htmlspecialchars($po['SupplierName'])?></strong></div>
  <div><span style="color:var(--text-muted)">Phone:</span> <?=htmlspecialchars($po['SupplierPhone'])?></div>
  <div><span style="color:var(--text-muted)">Email:</span> <?=htmlspecialchars($po['SupplierEmail'])?></div>
  <div><span style="color:var(--text-muted)">Created by:</span> <?=htmlspecialchars($po['CreatedBy'])?></div>
  <div><span style="color:var(--text-muted)">Order Date:</span> <?=date('M d, Y g:i a',strtotime($po['OrderDate']))?></div>
  <div><span style="color:var(--text-muted)">Arrival:</span> <?=$po['ArrivalDate']?date('M d, Y',strtotime($po['ArrivalDate'])):'TBD'?></div>
  <div><span style="color:var(--text-muted)">Status:</span> <span class="badge badge-<?=$c?>"><?=$po['Status']?></span></div>
  <?php if($po['Notes']):?><div style="grid-column:span 2"><span style="color:var(--text-muted)">Notes:</span> <?=htmlspecialchars($po['Notes'])?></div><?php endif;?>
</div>

<table style="width:100%">
  <thead><tr><th>Product</th><th>Brand</th><th>Qty Ordered</th><th>Unit Cost</th><th>Subtotal</th></tr></thead>
  <tbody>
  <?php foreach($items as $item):?>
  <tr>
    <td><?=htmlspecialchars($item['ProductName'])?></td>
    <td style="color:var(--text-muted)"><?=htmlspecialchars($item['Brand'])?></td>
    <td><?=$item['QuantityOrdered']?></td>
    <td>₱<?=number_format($item['UnitCost'],2)?></td>
    <td style="font-weight:600">₱<?=number_format($item['QuantityOrdered']*$item['UnitCost'],2)?></td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
<div style="text-align:right;margin-top:12px">
  <div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:var(--accent2)">
    TOTAL: ₱<?=number_format($total,2)?>
  </div>
</div>
