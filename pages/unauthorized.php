<?php
// pages/unauthorized.php
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = 'Access Denied';
include __DIR__ . '/../includes/header.php';
?>
<div class="empty-state" style="padding:100px 20px">
  <div class="empty-icon">🔒</div>
  <h2 style="font-family:'Syne',sans-serif;font-size:22px;margin-bottom:8px">Access Denied</h2>
  <p style="margin-bottom:20px">You don't have permission to view this page.</p>
  <a href="/pages/dashboard.php" class="btn btn-primary">Back to Dashboard</a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
