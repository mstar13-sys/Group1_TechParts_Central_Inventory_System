<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
$active_nav = $active_nav ?? '';
$page_title  = $page_title  ?? 'TechParts';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — TechParts</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">⬡</span>
            <span class="brand-name">TechParts</span>
        </div>

        <p class="nav-group-label">Main</p>
        <ul>
            <li class="<?= $active_nav === 'dashboard' ? 'active' : '' ?>">
                <a href="index.php">📊 Dashboard</a>
            </li>
            <li class="<?= $active_nav === 'inventory' ? 'active' : '' ?>">
                <a href="inventory.php">📦 Inventory</a>
            </li>
            <li class="<?= $active_nav === 'transactions' ? 'active' : '' ?>">
                <a href="transactions.php">🧾 Transactions</a>
            </li>
            <li class="<?= $active_nav === 'suppliers' ? 'active' : '' ?>">
                <a href="suppliers.php">🏢 Suppliers</a>
            </li>
        </ul>

        <p class="nav-group-label">Admin</p>
        <ul>
            <li><a href="#">👤 User Management</a></li>
            <li><a href="#">⚙️ Settings</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </nav>

    <!-- MAIN WRAPPER -->
    <div class="main">
        <div class="topbar">
            <h1 class="page-title"><?= e($page_title) ?></h1>
            <span class="topbar-date"><?= date('l, F j, Y') ?></span>
        </div>