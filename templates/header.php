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

<nav class="navbar navbar-expand-lg bg-light border-bottom">
  <div class="container position-relative">

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


    <!-- Toggler on the left (mobile) -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Collapsible content -->
    <div class="collapse navbar-collapse" id="mainNav">
      <!-- Left: app links (only when logged in) -->
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
            <a class="nav-link <?=($currentPage=='predictions.php'?'active':'')?>" href="predictions.php">Predictions</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?=($currentPage=='vehicle_form.php'?'active':'')?>" href="vehicle_form.php">Add Vehicle</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?=($currentPage=='service_form.php'?'active':'')?>" href="service_form.php">Add Service</a>
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


<!-- Open the page content container (footer will close it) -->
<div class="container flex-fill">


