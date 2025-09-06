<?php require_once __DIR__ . '/../app/config.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Car Service Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bg-light border-bottom mb-4">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php">CST</a>
    <div>
      <?php if (!empty($_SESSION['user'])): ?>
        <span class="me-3">Hi, <?=htmlspecialchars($_SESSION['user']['name'])?></span>
        <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
      <?php else: ?>
        <a class="btn btn-primary btn-sm" href="login.php">Login</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container">
