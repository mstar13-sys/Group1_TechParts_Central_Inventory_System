<?php
// /includes/header.php
$user = currentUser();
$page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="/css/style.css">
  <!-- <link rel="preconnect" href="https://fonts.googleapis.com"> -->
  <!-- <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"> -->
  <script>
    // These must load before any inline page scripts that call them
    function openModal(id) {
      document.getElementById(id)?.classList.add('open');
    }

    function closeModal(id) {
      document.getElementById(id)?.classList.remove('open');
    }

    function confirmDelete(msg, form) {
      if (confirm(msg || 'Are you sure?')) form.submit();
    }
    // Close modal when clicking the backdrop
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open');
    });
  </script>
</head>

<body>
  <nav class="sidebar">
    <div class="sidebar-brand">
      <span class="brand-icon">⚙</span>
      <span class="brand-name">TechParts</span>
    </div>

    <div class="sidebar-role">
      <span class="role-badge role-<?= strtolower($user['role']) ?>"><?= $user['role'] ?></span>
      <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
    </div>

    <ul class="nav-links">
      <li class="nav-section">OVERVIEW</li>
      <li class="<?= $page === 'dashboard' ? 'active' : '' ?>">
        <a href="/dashboard.php"><i class="ico">▦</i> Dashboard</a>
      </li>

      <?php if (in_array($user['role'], ['Admin', 'Cashier', 'User'])): ?>
        <li class="nav-section">SALES</li>
        <?php if (in_array($user['role'], ['Admin', 'Cashier'])): ?>
          <li class="<?= $page === 'cashier' ? 'active' : '' ?>">
            <a href="/pages/cashier.php"><i class="ico">🛒</i> POS / Cashier</a>
          </li>
        <?php endif; ?>
        <li class="<?= $page === 'transactions' ? 'active' : '' ?>">
          <a href="/pages/transactions.php"><i class="ico">📋</i> Transactions</a>
        </li>
      <?php endif; ?>

      <li class="nav-section">INVENTORY</li>
      <li class="<?= $page === 'products' ? 'active' : '' ?>">
        <a href="/pages/products.php"><i class="ico">📦</i> Products</a>
      </li>
      <li class="<?= $page === 'categories' ? 'active' : '' ?>">
        <a href="/pages/categories.php"><i class="ico">🗂</i> Categories</a>
      </li>
      <li class="<?= $page === 'stock' ? 'active' : '' ?>">
        <a href="/pages/stock.php"><i class="ico">📊</i> Stock</a>
      </li>

      <li class="nav-section">PROCUREMENT</li>
      <li class="<?= $page === 'suppliers' ? 'active' : '' ?>">
        <a href="/pages/suppliers.php"><i class="ico">🏭</i> Suppliers</a>
      </li>
      <li class="<?= $page === 'purchase_orders' ? 'active' : '' ?>">
        <a href="/pages/purchase_orders.php"><i class="ico">📑</i> Purchase Orders</a>
      </li>

      <?php if ($user['role'] === 'Admin'): ?>
        <li class="nav-section">ADMIN</li>
        <li class="<?= $page === 'users' ? 'active' : '' ?>">
          <a href="/pages/users.php"><i class="ico">👥</i> Users</a>
        </li>
        <li class="<?= $page === 'reports' ? 'active' : '' ?>">
          <a href="/pages/reports.php"><i class="ico">📈</i> Reports</a>
        </li>
      <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
      <a href="/logout.php" class="logout-btn">⏏ Logout</a>
    </div>
  </nav>

  <main class="main-content">
    <header class="top-bar">
      <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
      <div class="top-bar-right">
        <span class="date-display" id="live-clock"></span>
      </div>
    </header>
    <div class="page-body">