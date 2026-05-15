<?php
// pages/users.php
require_once __DIR__ . '/../includes/config.php';
requireLogin(['Admin']);
$pageTitle = 'User Management';
$db = getDB();

$msg = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrf();
  $action = $_POST['action'] ?? '';
  if ($action === 'save') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'Cashier';
    if (!in_array($role, ['Admin', 'Cashier'], true)) {
      $role = 'Cashier';
    }
    $active   = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '') {
      $msg = 'Name and email are required.';
      $msgType = 'danger';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
      $msg = 'Name must be 2 to 100 characters.';
      $msgType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $msg = 'Enter a valid email address.';
      $msgType = 'danger';
    } elseif ($password !== '' && strlen($password) < 8) {
      $msg = 'Password must be at least 8 characters.';
      $msgType = 'danger';
    } elseif (($id === 0 || $password !== '') && $password !== $confirmPassword) {
      $msg = 'Password and confirm password do not match.';
      $msgType = 'danger';
    } else {
      try {
        $emailCheck = $db->prepare('SELECT COUNT(*) FROM User WHERE Email = ? AND ID <> ?');
        $emailCheck->execute([$email, $id]);
        if ((int)$emailCheck->fetchColumn() > 0) {
          $msg = 'Email address is already used by another account.';
          $msgType = 'danger';
        } elseif ($id > 0) {
          if ($password) {
            $db->prepare('UPDATE User SET Name=?,Email=?,Role=?,IsActive=?,Password=? WHERE ID=?')
              ->execute([$name, $email, $role, $active, password_hash($password, PASSWORD_BCRYPT), $id]);
          } else {
            $db->prepare('UPDATE User SET Name=?,Email=?,Role=?,IsActive=? WHERE ID=?')
              ->execute([$name, $email, $role, $active, $id]);
          }
          $msg = 'User updated.';
          $msgType = 'success';
        } else {
          if (!$password) {
            $msg = 'Password required for new user.';
            $msgType = 'danger';
          } else {
            $db->prepare('INSERT INTO User (Name,Email,Password,Role,IsActive) VALUES (?,?,?,?,?)')
              ->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $role, $active]);
            $msg = 'User created.';
            $msgType = 'success';
          }
        }
      } catch (PDOException $e) {
        $msg = 'Error: ' . $e->getMessage();
        $msgType = 'danger';
      }
    }
  } elseif ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare('UPDATE User SET IsActive = NOT IsActive WHERE ID=?')->execute([$id]);
    $msg = 'User status toggled.';
    $msgType = 'info';
  }
}

$users = $db->query("
    SELECT u.*, COUNT(t.ID) AS TxnCount
    FROM User u
    LEFT JOIN Transaction t ON t.Cashier_ID = u.ID
    GROUP BY u.ID
    ORDER BY u.Role, u.Name
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/css/users.css">
<?php
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="toolbar">
  <span style="color:var(--text-muted)"><?= count($users) ?> users</span>
  <button class="btn btn-primary" onclick="openAddUser()">+ Add User</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Transactions</th>
          <th>Created</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td style="color:var(--text-muted)"><?= $u['ID'] ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($u['Name']) ?></td>
            <td style="color:var(--text-muted)"><?= htmlspecialchars($u['Email']) ?></td>
            <td>
              <?php $rc = match ($u['Role']) {
                'Admin' => 'orange',
                'Cashier' => 'blue',
                default => 'gray'
              }; ?>
              <span class="badge badge-<?= $rc ?>"><?= $u['Role'] ?></span>
            </td>
            <td><?= $u['TxnCount'] ?> txns</td>
            <td style="color:var(--text-muted);font-size:12px"><?= date('M d, Y', strtotime($u['CreatedAt'])) ?></td>
            <td><span class="badge badge-<?= $u['IsActive'] ? 'green' : 'red' ?>"><?= $u['IsActive'] ? 'Active' : 'Inactive' ?></span></td>
            <td style="display:flex;gap:6px">
              <button class="btn btn-ghost btn-sm" onclick='editUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_TAG) ?>)'>Edit</button>
              <?php if ($u['ID'] != $_SESSION['user_id']): ?>
                <form method="POST">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $u['ID'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm"><?= $u['IsActive'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="user-modal">
  <div class="modal" style="width:480px">
    <div class="modal-header">
      <span class="modal-title" id="user-modal-title">Add User</span>
      <button class="modal-close" onclick="closeModal('user-modal')">✕</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="u-id" value="0">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="name" id="u-name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email" id="u-email" class="form-control" required>
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Role *</label>
            <select name="role" id="u-role" class="form-control">
              <option value="Admin">Admin</option>
              <option value="Cashier">Cashier</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="is_active" id="u-active" class="form-control">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" id="u-pass-label">Password *</label>
          <input type="password" name="password" id="u-password" class="form-control" minlength="8" placeholder="Leave blank to keep unchanged (edit)">
        </div>
        <div class="form-group">
          <label class="form-label" id="u-confirm-label">Confirm Password *</label>
          <input type="password" name="confirm_password" id="u-confirm-password" class="form-control" minlength="8" placeholder="Retype password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('user-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save User</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openAddUser() {
    document.getElementById('user-modal-title').textContent = 'Add User';
    document.getElementById('u-pass-label').textContent = 'Password *';
    document.getElementById('u-confirm-label').textContent = 'Confirm Password *';
    document.getElementById('u-id').value = 0;
    ['u-name', 'u-email', 'u-password', 'u-confirm-password'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('u-password').required = true;
    document.getElementById('u-confirm-password').required = true;
    document.getElementById('u-role').value = 'Cashier';
    document.getElementById('u-active').value = '1';
    openModal('user-modal');
  }

  function editUser(u) {
    document.getElementById('user-modal-title').textContent = 'Edit User';
    document.getElementById('u-pass-label').textContent = 'New Password (leave blank to keep)';
    document.getElementById('u-confirm-label').textContent = 'Confirm New Password';
    document.getElementById('u-id').value = u.ID;
    document.getElementById('u-name').value = u.Name;
    document.getElementById('u-email').value = u.Email;
    document.getElementById('u-role').value = u.Role;
    document.getElementById('u-active').value = u.IsActive;
    document.getElementById('u-password').value = '';
    document.getElementById('u-confirm-password').value = '';
    document.getElementById('u-password').required = false;
    document.getElementById('u-confirm-password').required = false;
    openModal('user-modal');
  }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
