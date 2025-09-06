<?php
require_once __DIR__ . '/../app/auth.php';
require_login();
include __DIR__ . '/../templates/header.php';
?>
<h2>Dashboard</h2>
<p>Welcome to Car Service Tracker. Use the menu to manage your vehicles and services.</p>
<p><a class="btn btn-outline-primary" href="vehicles.php">My Vehicles</a>
   <a class="btn btn-outline-secondary" href="services.php">Service Records</a>
   <a class="btn btn-outline-success" href="appointments.php">Appointments</a></p>
<?php include __DIR__ . '/../templates/footer.php'; ?>
