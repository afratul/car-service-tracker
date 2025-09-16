<?php require_once __DIR__ . '/../app/config.php'; ?>

<?php
$currentPhoto = $_SESSION['user']['profile_photo'] ?? null;
if ($currentPhoto && preg_match('/^[a-z0-9._-]+$/i', $currentPhoto)) {
    $navPhotoUrl = rtrim(BASE_URL, '/') . '/public/uploads/profile/' . $currentPhoto;
} else {
    // Default avatar
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">
      <rect width="100%" height="100%" fill="#e9ecef"/>
      <text x="50%" y="55%" text-anchor="middle" font-size="18" fill="#6c757d" font-family="Arial,Helvetica,sans-serif">👤</text>
    </svg>';
    $navPhotoUrl = 'data:image/svg+xml;base64,' . base64_encode($svg);
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Car Service Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">


<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

<?php $loggedIn = !empty($_SESSION['user']); ?>


<nav class="navbar navbar-expand-lg bg-light border-bottom">
  <div class="container position-relative">

  <!-- Toggler on the left (mobile) -->
    <?php if ($loggedIn): ?>
  <!-- Drawer (offcanvas) toggler: visible only when logged in -->
  <button class="btn btn-outline-secondary me-3 d-lg-inline-flex" type="button"
        data-bs-toggle="offcanvas" data-bs-target="#appDrawer" aria-controls="appDrawer"
        title="Menu" aria-label="Open menu">
  <span class="navbar-toggler-icon"></span>
</button>

<?php endif; ?>

    <!-- Brand centered -->
   <?php if (!empty($_SESSION['user'])): ?>
  <!-- Logged in: show brand on the left -->
  <a class="navbar-brand fw-bold" href="dashboard.php">🚗 Car Service Tracker</a>
<?php else: ?>
  <!-- Logged out: center the brand -->
  <a class="navbar-brand fw-bold position-absolute start-50 translate-middle-x" href="login.php">
    🚗 Car Service Tracker
  </a>
<?php endif; ?>


  

    <!-- Collapsible content -->
    <div class="d-flex flex-grow-1">
      <!-- Left: app links (only when logged in) -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
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
            <a class="nav-link <?=($currentPage=='predictions.php'?'active':'')?>" href="predictions.php">Predictions</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?=($currentPage=='fuel_insights.php'?'active':'')?>" href="fuel_insights.php">Fuel Insights</a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- Right: auth area (buttons when logged out, profile/logout when logged in) -->
      <div class="ms-auto d-flex">
        <?php if ($loggedIn): ?>
          <a class="btn btn-outline-primary btn-sm me-2 <?=($currentPage=='profile.php'?'active':'')?>" href="profile.php">
            <img src="<?= htmlspecialchars($navPhotoUrl) ?>" alt="Profile" class="rounded-circle me-1" style="width:24px;height:24px;object-fit:cover;">
            Profile
          </a>
          <a class="btn btn-outline-secondary btn-sm" href="logout.php">Logout</a>
        <?php else: ?>
          <a class="btn btn-primary btn-sm me-2" href="login.php">Login</a>
          <a class="btn btn-outline-secondary btn-sm" href="register.php">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<?php if ($loggedIn): ?>
  <div class="offcanvas offcanvas-start" tabindex="-1" id="appDrawer" aria-labelledby="appDrawerLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="appDrawerLabel">🚗 Car Service Tracker</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <div class="list-group list-group-flush">
        <a href="dashboard.php"       class="list-group-item list-group-item-action <?= ($currentPage=='dashboard.php'?'active':'') ?>">🏠 Dashboard</a>
        <a href="vehicle_form.php"        class="list-group-item list-group-item-action <?= ($currentPage=='vehicle_form.php'?'active':'') ?>">🚙 Add Vehicle</a>
        <a href="service_form.php"        class="list-group-item list-group-item-action <?= ($currentPage=='service_form.php'?'active':'') ?>">🧰 Add Service Record</a>
        <a href="fuel_form.php"            class="list-group-item list-group-item-action <?= ($currentPage=='fuel_form.php'?'active':'') ?>">⛽ Add Fuel Record</a>
        <div class="mt-3 border-top pt-3"></div>
        <a href="profile.php"         class="list-group-item list-group-item-action <?= ($currentPage=='profile.php'?'active':'') ?>">👤 Profile</a>
        <a href="logout.php"          class="list-group-item list-group-item-action">🚪 Logout</a>
      </div>
    </div>
  </div>
<?php endif; ?>



<!-- Open the page content container (footer will close it) -->
<div class="container flex-fill">


