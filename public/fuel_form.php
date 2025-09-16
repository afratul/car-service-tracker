<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();

// Get user's vehicles (need current_mileage + fuel_type for hints/defaults)
$vehStmt = db()->prepare("SELECT id, reg_no, current_mileage, fuel_type FROM vehicles WHERE user_id=? ORDER BY id ASC");
$vehStmt->execute([$user['id']]);
$vehicles = $vehStmt->fetchAll();

$success = null;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $odoRaw    = isset($_POST['odometer']) ? trim((string)$_POST['odometer']) : '';
    $liters    = isset($_POST['liters']) ? (float)$_POST['liters'] : 0.0;
    $cost      = isset($_POST['cost']) ? (float)$_POST['cost'] : 0.0;

    // Ensure selected vehicle belongs to this user and capture its row
    $veh = null;
    foreach ($vehicles as $v) {
        if ((int)$v['id'] === $vehicleId) { $veh = $v; break; }
    }

    if (!$vehicleId || !$veh) {
        $errors[] = "Please select a valid vehicle.";
    }

    // Odometer: optional — if blank, default to vehicle's current mileage
    if ($veh) {
        if ($odoRaw === '' || $odoRaw === null) {
            $odometer = (int)$veh['current_mileage'];
        } else {
            if (!is_numeric($odoRaw) || (int)$odoRaw < 0) {
                $errors[] = "Odometer must be a non-negative number.";
            }
            $odometer = (int)$odoRaw;
        }
    } else {
        $odometer = 0; // placeholder; errors already added above
    }

    // Required fields
    if ($liters <= 0) $errors[] = "Liters is required and must be positive.";
    if ($cost   <= 0) $errors[] = "Total cost is required and must be positive.";

    if (!$errors) {
        try {
            db()->beginTransaction();

            // Insert fuel log (always pass an integer odometer value)
            $stmt = db()->prepare("
                INSERT INTO fuel_logs (vehicle_id, odometer, liters, cost, filled_at)
                VALUES (?,?,?,?,NOW())
            ");
            $stmt->execute([$vehicleId, $odometer, $liters, $cost]);

            // Update vehicle's current mileage only if this entry is newer
            if ($odometer > (int)$veh['current_mileage']) {
                $upd = db()->prepare("UPDATE vehicles SET current_mileage=? WHERE id=? AND user_id=?");
                $upd->execute([$odometer, $vehicleId, $user['id']]);
            }

            db()->commit();
            $success = "Fuel entry added successfully!";
        } catch (Throwable $e) {
            db()->rollBack();
            // Optional: log $e->getMessage() to a file if you have logging set up
            $errors[] = "Failed to save entry. Please try again.";
        }
    }
}

include __DIR__ . '/../templates/header.php';
?>

<h2>Add Fuel Entry</h2>

<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e): ?>
      <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$vehicles): ?>
  <div class="alert alert-warning">Please add a vehicle first.</div>
<?php else: ?>
<form method="post" class="row g-3">
  <div class="col-md-4">
    <label class="form-label">Vehicle</label>
    <select name="vehicle_id" id="vehicle_id" class="form-select" required>
      <option value="">-- select vehicle --</option>
      <?php foreach ($vehicles as $v): ?>
        <option value="<?= (int)$v['id'] ?>" data-fuel-type="<?= htmlspecialchars($v['fuel_type'] ?? 'Unknown') ?>">
          <?= htmlspecialchars($v['reg_no']) ?> (<?= number_format((int)$v['current_mileage']) ?> km)
        </option>
      <?php endforeach; ?>
    </select>
    <div class="form-text" id="fuelTypeHint">Fuel type: —</div>
  </div>

  <div class="col-md-4">
    <label class="form-label">Odometer (km)</label>
    <input type="number" name="odometer" class="form-control" min="0" placeholder="Leave blank to use current">
    <div class="form-text">If left empty, we’ll use the car’s current odometer.</div>
  </div>

  <div class="col-md-4">
    <label class="form-label">Liters</label>
    <input type="number" step="0.01" min="0.01" name="liters" class="form-control" required>
  </div>

  <div class="col-md-4">
    <label class="form-label">Total Cost</label>
    <input type="number" step="0.01" min="0.01" name="cost" class="form-control" required>
    <div class="form-text">Enter the total paid for this refuel (based on the car’s fuel type).</div>
  </div>

  <div class="col-12">
    <button class="btn btn-primary">Save</button>
    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
<?php endif; ?>

<script>
  const sel = document.getElementById('vehicle_id');
  const hint = document.getElementById('fuelTypeHint');
  function updateFuelHint() {
    const opt = sel?.options[sel.selectedIndex];
    const ft = opt ? opt.getAttribute('data-fuel-type') : '—';
    if (hint) hint.textContent = 'Fuel type: ' + (ft || '—');
  }
  sel?.addEventListener('change', updateFuelHint);
  updateFuelHint();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
