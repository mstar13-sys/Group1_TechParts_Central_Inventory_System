<?php
// /includes/logo.php
$logoClass = $logoClass ?? 'app-logo';
$logoAlt = $logoAlt ?? 'TechParts logo';
?>
<img src="/assets/images/logo.png" alt="<?= htmlspecialchars($logoAlt, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($logoClass, ENT_QUOTES, 'UTF-8') ?>">
