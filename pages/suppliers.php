<?php
// pages/suppliers.php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Suppliers';
$db = getDB();
$isAdmin = in_array($_SESSION['role'], ['Admin','User']);

$msg = ''; $msgType = '';
if ($isAdmin && $_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $active  = (int)($_POST['is_active'] ?? 1);

        if (!$name || !$phone || !$email || !$address) {
            $msg = 'All fields are required.'; $msgType='danger';
        } else {
            try {
                if ($id > 0) {
                    $db->prepare('UPDATE Supplier SET Name=?,Phone=?,Email=?,Address=?,IsActive=? WHERE ID=?')
                       ->execute([$name,$phone,$email,$address,$active,$id]);
                    $msg = 'Supplier updated.'; $msgType='success';
                } else {
                    $db->prepare('INSERT INTO Supplier (Name,Phone,Email,Address,IsActive) VALUES (?,?,?,?,?)')
                       ->execute([$name,$phone,$email,$address,$active]);
                    $msg = 'Supplier added.'; $msgType='success';
                }
            } catch (PDOException $e) {
                error_log('[Supplier save] ' . $e->getMessage());
                $msg = 'Could not save supplier. Please try again.'; $msgType = 'danger';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $db->prepare('UPDATE Supplier SET IsActive=0 WHERE ID=?')->execute([$id]);
            $msg = 'Supplier deactivated.'; $msgType='warning';
        } catch (PDOException $e) {
            error_log('[Supplier deactivate] ' . $e->getMessage());
            $msg = 'Could not deactivate supplier. Please try again.'; $msgType = 'danger';
        }
    }
}

$search = trim($_GET['q'] ?? '');
$where  = $search ? 'WHERE (s.Name LIKE ? OR s.Email LIKE ? OR s.Phone LIKE ?)' : '';
$params = $search ? ["%$search%","%$search%","%$search%"] : [];

$suppliers = $db->prepare("
    SELECT s.*, COUNT(ps.Supplier_ID) AS ProductCount
    FROM Supplier s
    LEFT JOIN Product_has_Supplier ps ON ps.Supplier_ID = s.ID
    $where
    GROUP BY s.ID
    ORDER BY s.Name
");
$suppliers->execute($params);
$suppliers = $suppliers->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= APP_ROOT ?>/css/suppliers.css">
<?php
?>

<?php if($msg):?><div class="alert alert-<?=$msgType?>"><?=htmlspecialchars($msg)?></div><?php endif;?>

<div class="toolbar">
  <form method="GET" class="search-box">
    <span>🔍</span>
    <input type="text" name="q" placeholder="Search suppliers…" value="<?=htmlspecialchars($search)?>">
  </form>
  <?php if($isAdmin):?>
  <button class="btn btn-primary" onclick="openAddSupplier()">+ Add Supplier</button>
  <?php endif;?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Products</th><th>Status</th><?php if($isAdmin):?><th>Actions</th><?php endif;?></tr>
      </thead>
      <tbody>
      <?php if(empty($suppliers)):?>
        <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:30px">No suppliers found.</td></tr>
      <?php endif;?>
      <?php foreach($suppliers as $s): ?>
      <tr>
        <td style="color:var(--text-muted)"><?=$s['ID']?></td>
        <td style="font-weight:600"><?=htmlspecialchars($s['Name'])?></td>
        <td><?=htmlspecialchars($s['Phone'])?></td>
        <td><?=htmlspecialchars($s['Email'])?></td>
        <td style="color:var(--text-muted);font-size:12px"><?=htmlspecialchars($s['Address'])?></td>
        <td><span class="badge badge-blue"><?=$s['ProductCount']?> products</span></td>
        <td><span class="badge badge-<?=$s['IsActive']?'green':'red'?>"><?=$s['IsActive']?'Active':'Inactive'?></span></td>
        <?php if($isAdmin):?>
        <td style="display:flex;gap:6px">
          <button class="btn btn-ghost btn-sm" onclick='editSupplier(<?=json_encode($s, JSON_HEX_APOS|JSON_HEX_TAG)?>)'>Edit</button>
          <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$s['ID']?>">
            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('Deactivate this supplier?',this.closest('form'))">✕</button>
          </form>
        </td>
        <?php endif;?>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="supplier-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="supplier-modal-title">Add Supplier</span>
      <button class="modal-close" onclick="closeModal('supplier-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="s-id" value="0">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Company Name *</label>
          <input type="text" name="name" id="s-name" class="form-control" required>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Phone *</label>
            <input type="text" name="phone" id="s-phone" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email" id="s-email" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Address *</label>
          <input type="text" name="address" id="s-address" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="is_active" id="s-active" class="form-control">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('supplier-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Supplier</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddSupplier() {
  document.getElementById('supplier-modal-title').textContent = 'Add Supplier';
  document.getElementById('s-id').value      = '0';
  document.getElementById('s-name').value    = '';
  document.getElementById('s-phone').value   = '';
  document.getElementById('s-email').value   = '';
  document.getElementById('s-address').value = '';
  document.getElementById('s-active').value  = '1';
  openModal('supplier-modal');
}
function editSupplier(s) {
  document.getElementById('supplier-modal-title').textContent = 'Edit Supplier';
  document.getElementById('s-id').value      = s.ID;
  document.getElementById('s-name').value    = s.Name;
  document.getElementById('s-phone').value   = s.Phone;
  document.getElementById('s-email').value   = s.Email;
  document.getElementById('s-address').value = s.Address;
  document.getElementById('s-active').value  = s.IsActive;
  openModal('supplier-modal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
