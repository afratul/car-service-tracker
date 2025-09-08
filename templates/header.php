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

<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

<nav class="navbar navbar-expand-lg bg-light border-bottom">
  <div class="container">
    <a class="navbar-brand fw-bold" href="dashboard.php">CST</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php $loggedIn = !empty($_SESSION['user']); ?>
        <?php if ($loggedIn): ?>
          <li class="nav-item">
  <a class="nav-link <?=($currentPage=='dashboard.php'?'active':'')?>" href="dashboard.php">Dashboard</a>
</li>
<li class="nav-item">
  <a class="nav-link <?=($currentPage=='vehicles.php'?'active':'')?>" href="vehicles.php">My Vehicles</a>
</li>
<li class="nav-item">
  <a class="nav-link <?=($currentPage=='services.php'?'active':'')?>" href="services.php">Service Records</a>
</li>
<li class="nav-item">
  <a class="nav-link <?=($currentPage=='vehicle_form.php'?'active':'')?>" href="vehicle_form.php">Add Vehicle</a>
</li>
<li class="nav-item">
  <a class="nav-link <?=($currentPage=='service_form.php'?'active':'')?>" href="service_form.php">Add Service</a>

        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
        <?php endif; ?>
      </ul>

      <div class="d-flex">
  <?php if ($loggedIn): ?>
    <a class="btn btn-outline-primary btn-sm me-2 <?=($currentPage=='profile.php'?'active':'')?>" href="profile.php">Profile</a>
    <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
  <?php else: ?>
    <a class="btn btn-primary btn-sm" href="login.php">Login</a>
  <?php endif; ?>
</div>

    </div>
  </div>
</nav>

<div class="container">
