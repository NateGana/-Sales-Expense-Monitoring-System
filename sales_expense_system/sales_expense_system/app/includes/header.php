<?php
// Expects $pageTitle to be set by the including page before this file is required.
$pageTitle = $pageTitle ?? 'Sales & Expense Monitoring System';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> - Sales & Expense Monitoring System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<nav class="app-topbar navbar navbar-dark px-3 py-2 d-flex justify-content-between">
  <span class="brand">Sales &amp; Expense Monitoring System</span>
  <div class="d-flex align-items-center gap-2">
    <span class="role-badge"><?= htmlspecialchars($user['role'] ?? '') ?></span>
    <span><?= htmlspecialchars($user['full_name'] ?? '') ?></span>
    <a href="<?= base_url('logout.php') ?>" class="btn btn-sm btn-outline-light ms-2">Log out</a>
  </div>
</nav>

<div class="d-flex">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="app-main flex-grow-1">
    <?php render_flash(); ?>
