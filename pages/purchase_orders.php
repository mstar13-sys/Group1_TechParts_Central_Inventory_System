<?php
// pages/purchase_orders.php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Purchase Orders';
$db = getDB();
$isAdmin = in_array($_SESSION['role'],['Admin','User']);

$msg=''; $msgType='';
if ($isAdmin && $_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';

    if ($action==='create_po') {
        $supplierId   = (int)($_POST['supplier_id']??0);
        $arrivalDate  = $_POST['arrival_date']??null;
        $notes        = trim($_POST['notes']??'');
        $productIds   = $_POST['product_ids']??[];
        $qtys         = $_POST['quantities']??[];
        $costs        = $_POST['unit_costs']??[];

        if (!$supplierId || empty($productIds)) {
            $msg='Select supplier and add at least one item.'; $msgType='danger';
        } else {
            try {
                $db->beginTransaction();
                $db->prepare('INSERT INTO PurchaseOrder (ArrivalDate,Notes,Supplier_ID,User_ID) VALUES (?,?,?,?)')
                   ->execute([$arrivalDate?:null,$notes,$supplierId,$_SESSION['user_id']]);
                $poId = $db->lastInsertId();
                $itemStmt = $db->prepare('INSERT INTO PurchaseOrderItem (QuantityOrdered,UnitCost,Product_ID,PurchaseOrder_ID) VALUES (?,?,?,?)');
                foreach ($productIds as $i=>$pid) {
                    if ($pid && $qtys[$i]>0 && $costs[$i]>0) {
                        $itemStmt->execute([$qtys[$i],$costs[$i],$pid,$poId]);
                    }
                }
                $db->commit();
                $msg='Purchase Order PO-'.str_pad($poId,4,'0',STR_PAD_LEFT).' created.'; $msgType='success';
            } catch(Exception $e){ $db->rollBack(); $msg='Error: '.$e->getMessage(); $msgType='danger'; }
        }
    } elseif ($action==='update_status') {
        $poId   = (int)($_POST['po_id']??0);
        $status = $_POST['status']??'';
        $allowed = ['Pending','Approved','Ordered','Received','Cancelled'];
        if ($poId && in_array($status,$allowed)) {
            $db->prepare('UPDATE PurchaseOrder SET Status=?,ArrivalDate=COALESCE(?,ArrivalDate) WHERE ID=?')
               ->execute([$status,$_POST['arrival_date']??null,$poId]);
            $msg='Status updated to '.$status.'.'; $msgType='success';
        }
    } elseif ($action==='delete_po') {
        try {
            $db->prepare('DELETE FROM PurchaseOrder WHERE ID=?')->execute([$_POST['po_id']]);
            $msg='Purchase Order deleted.'; $msgType='warning';
        } catch(PDOException $e){ $msg='Cannot delete: '.$e->getMessage(); $msgType='danger'; }
    }
}

$statusFilt = $_GET['status']??'';
$where = $statusFilt ? 'WHERE po.Status=?' : '';
$params = $statusFilt ? [$statusFilt] : [];

$pos = $db->prepare("
    SELECT po.*, s.Name AS SupplierName, u.Name AS CreatedBy,
           COUNT(poi.ID) AS ItemCount,
           COALESCE(SUM(poi.QuantityOrdered*poi.UnitCost),0) AS TotalCost
    FROM PurchaseOrder po
    JOIN Supplier s ON po.Supplier_ID=s.ID
    JOIN User u ON po.User_ID=u.ID
    LEFT JOIN PurchaseOrderItem poi ON poi.PurchaseOrder_ID=po.ID
    $where GROUP BY po.ID ORDER BY po.OrderDate DESC
");
$pos->execute($params);
$poList = $pos->fetchAll();

$suppliers = $db->query("SELECT * FROM Supplier WHERE IsActive=1 ORDER BY Name")->fetchAll();
$products  = $db->query("SELECT p.ID,p.Name,p.Brand,p.Price FROM Product p ORDER BY p.Name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if($msg):?><div class="alert alert-<?=$msgType?>"><?=htmlspecialchars($msg)?></div><?php endif;?>

<div class="toolbar">
  <div style="display:flex;gap:6px">
    <?php foreach([''=>'All','Pending'=>'Pending','Approved'=>'Approved','Ordered'=>'Ordered','Received'=>'Received','Cancelled'=>'Cancelled'] as $v=>$l):?>
    <a href="?status=<?=$v?>" class="btn btn-ghost btn-sm <?=$statusFilt===$v?'btn-primary':''?>"><?=$l?></a>
    <?php endforeach;?>
  </div>
  <?php if($isAdmin):?>
  <button class="btn btn-primary" onclick="openModal('po-modal')">+ New Purchase Order</button>
  <?php endif;?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>PO #</th><th>Supplier</th><th>Created By</th><th>Order Date</th><th>Arrival Date</th><th>Items</th><th>Total Cost</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if(empty($poList)):?>
        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:30px">No purchase orders found.</td></tr>
      <?php endif;?>
      <?php foreach($poList as $po):?>
      <tr>
        <td style="font-family:'Syne',sans-serif;font-weight:700;color:var(--accent2)">PO-<?=str_pad($po['ID'],4,'0',STR_PAD_LEFT)?></td>
        <td style="font-weight:600"><?=htmlspecialchars($po['SupplierName'])?></td>
        <td style="color:var(--text-muted)"><?=htmlspecialchars($po['CreatedBy'])?></td>
        <td style="color:var(--text-muted);font-size:12px"><?=date('M d, Y',strtotime($po['OrderDate']))?></td>
        <td style="color:var(--text-muted);font-size:12px"><?=$po['ArrivalDate']?date('M d, Y',strtotime($po['ArrivalDate'])):'—'?></td>
        <td><span class="badge badge-blue"><?=$po['ItemCount']?> items</span></td>
        <td style="color:var(--accent3);font-weight:600">₱<?=number_format($po['TotalCost'],2)?></td>
        <td>
          <?php $c=match($po['Status']){'Received'=>'green','Approved'=>'blue','Ordered'=>'orange','Pending'=>'yellow','Cancelled'=>'red',default=>'gray'};?>
          <span class="badge badge-<?=$c?>"><?=$po['Status']?></span>
        </td>
        <td style="display:flex;gap:6px">
          <button class="btn btn-ghost btn-sm" onclick="viewPO(<?=$po['ID']?>)">View</button>
          <?php if($isAdmin && !in_array($po['Status'],['Received','Cancelled'])):?>
          <button class="btn btn-blue btn-sm" onclick='openStatusModal(<?=json_encode($po)?>')'>Update</button>
          <?php endif;?>
          <?php if($isAdmin && $po['Status']==='Pending'):?>
          <form method="POST">
            <input type="hidden" name="action" value="delete_po">
            <input type="hidden" name="po_id" value="<?=$po['ID']?>">
            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('Delete this PO?',this.closest('form'))">✕</button>
          </form>
          <?php endif;?>
        </td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create PO Modal -->
<div class="modal-overlay" id="po-modal">
  <div class="modal" style="width:min(700px,95vw)">
    <div class="modal-header">
      <span class="modal-title">New Purchase Order</span>
      <button class="modal-close" onclick="closeModal('po-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_po">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Supplier *</label>
            <select name="supplier_id" class="form-control" required>
              <option value="">Select supplier…</option>
              <?php foreach($suppliers as $s):?>
              <option value="<?=$s['ID']?>"><?=htmlspecialchars($s['Name'])?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Expected Arrival Date</label>
            <input type="date" name="arrival_date" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <input type="text" name="notes" class="form-control" placeholder="Optional notes…">
        </div>
        <div class="card-title" style="margin-bottom:12px;margin-top:4px">Order Items</div>
        <div id="po-items">
          <div class="po-item-row" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:end">
            <div>
              <label class="form-label">Product</label>
              <select name="product_ids[]" class="form-control" required>
                <option value="">Select product…</option>
                <?php foreach($products as $p):?>
                <option value="<?=$p['ID']?>" data-price="<?=$p['Price']?>"><?=htmlspecialchars($p['Name'])?> (<?=$p['Brand']?>)</option>
                <?php endforeach;?>
              </select>
            </div>
            <div>
              <label class="form-label">Quantity</label>
              <input type="number" name="quantities[]" class="form-control" min="1" value="1" required>
            </div>
            <div>
              <label class="form-label">Unit Cost (₱)</label>
              <input type="number" name="unit_costs[]" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <button type="button" class="btn btn-danger btn-sm" style="margin-bottom:1px" onclick="this.closest('.po-item-row').remove()">✕</button>
          </div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addPORow()">+ Add Item</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('po-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Purchase Order</button>
      </div>
    </form>
  </div>
</div>

<!-- Update Status Modal -->
<div class="modal-overlay" id="status-modal">
  <div class="modal" style="width:400px">
    <div class="modal-header">
      <span class="modal-title" id="status-modal-title">Update PO Status</span>
      <button class="modal-close" onclick="closeModal('status-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="po_id" id="sm-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">New Status</label>
          <select name="status" id="sm-status" class="form-control">
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Ordered">Ordered</option>
            <option value="Received">Received</option>
            <option value="Cancelled">Cancelled</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Arrival Date (optional override)</label>
          <input type="date" name="arrival_date" class="form-control">
        </div>
        <div class="alert alert-info" style="font-size:12px">
          Setting status to <strong>Received</strong> will automatically add ordered quantities to Stock.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('status-modal')">Cancel</button>
        <button type="submit" class="btn btn-blue">Update Status</button>
      </div>
    </form>
  </div>
</div>

<!-- View PO Detail Modal (loaded via fetch) -->
<div class="modal-overlay" id="view-po-modal">
  <div class="modal" style="width:min(640px,95vw)">
    <div class="modal-header">
      <span class="modal-title" id="view-po-title">Purchase Order Details</span>
      <button class="modal-close" onclick="closeModal('view-po-modal')">✕</button>
    </div>
    <div class="modal-body" id="view-po-body">Loading…</div>
  </div>
</div>

<script>
const products = <?=json_encode($products)?>;

function addPORow() {
  const opts = products.map(p=>`<option value="${p.ID}" data-price="${p.Price}">${p.Name} (${p.Brand})</option>`).join('');
  const row = document.createElement('div');
  row.className = 'po-item-row';
  row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:end';
  row.innerHTML = `
    <div><select name="product_ids[]" class="form-control"><option value="">Select product…</option>${opts}</select></div>
    <div><input type="number" name="quantities[]" class="form-control" min="1" value="1"></div>
    <div><input type="number" name="unit_costs[]" class="form-control" step="0.01" min="0.01" placeholder="0.00"></div>
    <button type="button" class="btn btn-danger btn-sm" style="margin-bottom:1px" onclick="this.closest('.po-item-row').remove()">✕</button>`;
  document.getElementById('po-items').appendChild(row);
}

function openStatusModal(po) {
  document.getElementById('sm-id').value = po.ID;
  document.getElementById('sm-status').value = po.Status;
  document.getElementById('status-modal-title').textContent = 'Update PO-' + String(po.ID).padStart(4,'0');
  openModal('status-modal');
}

async function viewPO(id) {
  document.getElementById('view-po-title').textContent = 'PO-'+String(id).padStart(4,'0');
  document.getElementById('view-po-body').innerHTML = '<p style="text-align:center;padding:20px;color:var(--text-muted)">Loading…</p>';
  openModal('view-po-modal');
  const resp = await fetch('/pages/po_detail.php?id='+id);
  document.getElementById('view-po-body').innerHTML = await resp.text();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
