<?php
// pages/categories.php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Categories';
$db = getDB();
$isAdmin = in_array($_SESSION['role'],['Admin','User']);

$msg=''; $msgType='';
if ($isAdmin && $_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';
    if ($action==='save') {
        $id     = (int)($_POST['id']??0);
        $name   = trim($_POST['name']??'');
        $parts  = trim($_POST['parts']??'N/A');
        $status = $_POST['status']??'Inactive';
        $desc   = trim($_POST['description']??'');
        if (!$name){ $msg='Name is required.'; $msgType='danger';
        } else {
            try {
                if ($id>0) {
                    $db->prepare('UPDATE Category SET Name=?,Parts=?,Status=?,Description=? WHERE ID=?')
                       ->execute([$name,$parts,$status,$desc,$id]);
                    $msg='Category updated.'; $msgType='success';
                } else {
                    $db->prepare('INSERT INTO Category (Name,Parts,Status,Description) VALUES (?,?,?,?)')
                       ->execute([$name,$parts,$status,$desc]);
                    $msg='Category added.'; $msgType='success';
                }
            } catch(PDOException $e){ $msg='Error: '.$e->getMessage(); $msgType='danger'; }
        }
    } elseif ($action==='delete') {
        try {
            $db->prepare('DELETE FROM Category WHERE ID=?')->execute([$_POST['id']]);
            $msg='Category deleted.'; $msgType='warning';
        } catch(PDOException $e){ $msg='Cannot delete (products exist): '.$e->getMessage(); $msgType='danger'; }
    }
}

$categories = $db->query("
    SELECT c.*, COUNT(p.ID) AS ProductCount
    FROM Category c LEFT JOIN Product p ON p.Category_ID=c.ID
    GROUP BY c.ID ORDER BY c.Name
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/css/categories.css">
<?php
?>

<?php if($msg):?><div class="alert alert-<?=$msgType?>"><?=htmlspecialchars($msg)?></div><?php endif;?>

<div class="toolbar">
  <span style="color:var(--text-muted);font-size:13px"><?=count($categories)?> categories</span>
  <?php if($isAdmin):?>
  <button class="btn btn-primary" onclick="openAddCat()">+ Add Category</button>
  <?php endif;?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>Parts</th><th>Description</th><th>Products</th><th>Status</th><?php if($isAdmin):?><th>Actions</th><?php endif;?></tr>
      </thead>
      <tbody>
      <?php foreach($categories as $c):?>
      <tr>
        <td style="color:var(--text-muted)"><?=$c['ID']?></td>
        <td style="font-weight:600"><?=htmlspecialchars($c['Name'])?></td>
        <td style="color:var(--text-muted);font-size:12px"><?=htmlspecialchars($c['Parts'])?></td>
        <td style="color:var(--text-muted);font-size:12px;max-width:220px"><?=htmlspecialchars(substr($c['Description']??'',0,80))?><?=strlen($c['Description']??'')>80?'…':''?></td>
        <td><span class="badge badge-blue"><?=$c['ProductCount']?></span></td>
        <td>
          <?php $sc=match($c['Status']){'Active'=>'green','Inactive'=>'yellow','Archived'=>'gray',default=>'gray'};?>
          <span class="badge badge-<?=$sc?>"><?=$c['Status']?></span>
        </td>
        <?php if($isAdmin):?>
        <td style="display:flex;gap:6px">
          <button class="btn btn-ghost btn-sm" onclick='editCat(<?=json_encode($c, JSON_HEX_APOS|JSON_HEX_TAG)?>)'>Edit</button>
          <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?=$c['ID']?>">
            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('Delete this category? Products must be reassigned first.',this.closest('form'))">✕</button>
          </form>
        </td>
        <?php endif;?>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="cat-modal">
  <div class="modal" style="width:480px">
    <div class="modal-header">
      <span class="modal-title" id="cat-modal-title">Add Category</span>
      <button class="modal-close" onclick="closeModal('cat-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="c-id" value="0">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Category Name *</label>
          <input type="text" name="name" id="c-name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Parts / Sub-types</label>
          <input type="text" name="parts" id="c-parts" class="form-control" placeholder="e.g. ATX, mATX, ITX">
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" id="c-status" class="form-control">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
            <option value="Archived">Archived</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="c-desc" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('cat-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Category</button>
      </div>
    </form>
  </div>
</div>
<script>
function openAddCat() {
  document.getElementById('cat-modal-title').textContent='Add Category';
  ['c-id','c-name','c-parts','c-desc'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('c-id').value='0';
  document.getElementById('c-status').value='Active';
  openModal('cat-modal');
}
function editCat(c) {
  document.getElementById('cat-modal-title').textContent='Edit Category';
  document.getElementById('c-id').value     = c.ID;
  document.getElementById('c-name').value   = c.Name;
  document.getElementById('c-parts').value  = c.Parts||'';
  document.getElementById('c-status').value = c.Status;
  document.getElementById('c-desc').value   = c.Description||'';
  openModal('cat-modal');
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
