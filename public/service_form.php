<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config.php';
require_login();

$SERVICE_TYPES = [
  'Fluids' => [
    'Engine oil change',
    'Gear oil change',
    'Brake fluid change',
    'Engine coolant check',
    'Engine coolant change',
    'Transmission fluid change',
    'Power steering fluid check',
    'Windshield washer fluid refill'
  ],
  'Brakes & Tires' => [
    'Brake pads check',
    'Brake discs/rotors check',
    'Tire check',
    'Tire rotation',
    'Wheel alignment',
    'Wheel balancing'
  ],
  'Filters' => [
    'Air filter change',
    'Cabin filter change',
    'Fuel filter change'
  ],
  'Engine & Electrical' => [
    'Spark plug replacement',
    'Battery check/replacement',
    'Timing belt/chain check',
    'Drive belt/serpentine belt check'
  ],
  'Suspension & Steering' => [
    'Complete suspension check',
    'Shock absorbers/struts check'
  ],
  'General' => [
    'AC system check',
    'Lights & electrical check',
    'Exhaust system check',
    'Other'
  ]
];

$user = current_user();

// Get user's vehicles for the dropdown
$vs = db()->prepare("SELECT id, reg_no FROM vehicles WHERE user_id=? ORDER BY id ASC");
$vs->execute([$user['id']]);
$vehicles = $vs->fetchAll();
if (!$vehicles) { header('Location: vehicle_form.php'); exit; }

$errors = [];
$service = [
  'vehicle_id'   => $vehicles[0]['id'],
  'service_date' => date('Y-m-d'),
  'type'         => '',
  'mileage'      => 0,
  'cost'         => '0.00',
  'notes'        => ''
];

// If a vehicle_id is provided in the URL, preselect it (only if it belongs to the user)
if (isset($_GET['vehicle_id'])) {
  $vid = (int)$_GET['vehicle_id'];
  foreach ($vehicles as $v) {
    if ((int)$v['id'] === $vid) {
      $service['vehicle_id'] = $vid;
      break;
    }
  }
}


$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($id) {
  // make sure the service belongs to this user
  $st = db()->prepare("SELECT s.* 
                       FROM services s 
                       JOIN vehicles v ON s.vehicle_id = v.id 
                       WHERE s.id=? AND v.user_id=?");
  $st->execute([$id, $user['id']]);
  $dbRow = $st->fetch();

  if ($dbRow) {
    $service = $dbRow; // overwrite defaults with DB values
  } else {
    // trying to edit someone else’s or non-existent → redirect back
    header('Location: services.php');
    exit;
  }
}


// Validate selected service type against grouped options
function is_valid_service_type(array $GROUPED, string $value): bool {
    foreach ($GROUPED as $group => $opts) {
        if (in_array($value, $opts, true)) return true;
    }
    return false;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $service['vehicle_id']   = (int)($_POST['vehicle_id'] ?? 0);
  $service['service_date'] = $_POST['service_date'] ?? '';
  $service['type']         = trim($_POST['type'] ?? '');
  $service['mileage']      = (int)($_POST['mileage'] ?? 0);
  $service['cost']         = (string)($_POST['cost'] ?? '0');
  $service['notes']        = trim($_POST['notes'] ?? '');

  // ensure the selected vehicle belongs to this user
  $own = db()->prepare("SELECT id FROM vehicles WHERE id=? AND user_id=?");
  $own->execute([$service['vehicle_id'], $user['id']]);
  if (!$own->fetch()) $errors[] = 'Invalid vehicle selection.';

  // --- mileage rules: use previous if 0, and never allow decrease ---

// 1) find the previous mileage for this vehicle:
//    - max mileage from existing services (excluding current row if editing)
//    - if none, fall back to vehicle's current_mileage
if (isset($id) && $id) {
    $prevStmt = db()->prepare("SELECT MAX(mileage) AS m FROM services WHERE vehicle_id=? AND id<>?");
    $prevStmt->execute([$service['vehicle_id'], $id]);
} else {
    $prevStmt = db()->prepare("SELECT MAX(mileage) AS m FROM services WHERE vehicle_id=?");
    $prevStmt->execute([$service['vehicle_id']]);
}
$prevServiceMileage = (int)($prevStmt->fetch()['m'] ?? 0);

$vehStmt = db()->prepare("SELECT current_mileage FROM vehicles WHERE id=? AND user_id=?");
$vehStmt->execute([$service['vehicle_id'], $user['id']]);
$vehRow = $vehStmt->fetch();
$vehCurrent = $vehRow ? (int)$vehRow['current_mileage'] : 0;

// previous baseline = max(previous service mileage, vehicle's stored mileage)
$previousMileage = max($prevServiceMileage, $vehCurrent);

// 2) if user entered 0, reuse previous mileage (if any)
if ((int)$service['mileage'] === 0 && $previousMileage > 0) {
    $service['mileage'] = $previousMileage;
}

// 3) never allow a decrease vs previous mileage
if ($previousMileage > 0 && (int)$service['mileage'] < $previousMileage) {
    $errors[] = "Mileage cannot be less than the previous recorded mileage ($previousMileage). "
              . "Enter a value ≥ $previousMileage, or enter 0 to reuse $previousMileage.";
}


  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $service['service_date'])) $errors[] = 'Invalid date format.';
  if (!is_valid_service_type($SERVICE_TYPES, $service['type'])) {
    $errors[] = 'Please select a valid service type.';
}
if ($service['type'] === 'Other' && $service['notes'] === '') {
    $errors[] = 'Please describe the service in Notes when choosing “Other”.';
}

  if ($service['mileage'] < 0) $errors[] = 'Mileage cannot be negative.';
  if (!is_numeric($service['cost']) || (float)$service['cost'] < 0) $errors[] = 'Cost must be a positive number.';

  if (!$errors) {
  if ($id) {
    $sql = "UPDATE services 
            SET vehicle_id=?, service_date=?, type=?, mileage=?, cost=?, notes=? 
            WHERE id=?";
    db()->prepare($sql)->execute([
      $service['vehicle_id'],
      $service['service_date'],
      $service['type'],
      $service['mileage'],
      $service['cost'],
      $service['notes'],
      $id
    ]);
  } else {
    $sql = "INSERT INTO services (vehicle_id, service_date, type, mileage, cost, notes)
            VALUES (?,?,?,?,?,?)";
    db()->prepare($sql)->execute([
      $service['vehicle_id'],
      $service['service_date'],
      $service['type'],
      $service['mileage'],
      $service['cost'],
      $service['notes']
    ]);
  }

  // bump vehicle mileage if this service mileage is higher
db()->prepare("
  UPDATE vehicles
  SET current_mileage = GREATEST(current_mileage, ?)
  WHERE id = ? AND user_id = ?
")->execute([(int)$service['mileage'], (int)$service['vehicle_id'], (int)$user['id']]);

  header('Location: services.php?vehicle_id=' . $service['vehicle_id']);
  exit;
}
}

include __DIR__ . '/../templates/header.php';
?>
<h2><?= $id ? 'Edit Service' : 'Add Service' ?></h2>
<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?></div>
<?php endif; ?>

<form method="post" class="row g-3">
  <div class="col-md-4">
    <label class="form-label">Vehicle</label>
    <select class="form-select" name="vehicle_id" required>
      <?php foreach ($vehicles as $v): ?>
        <option value="<?=$v['id']?>" <?=($service['vehicle_id']==$v['id']?'selected':'')?>>
          <?=htmlspecialchars($v['reg_no'])?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Date</label>
    <input type="date" class="form-control" name="service_date" value="<?=htmlspecialchars($service['service_date'])?>" required>
  </div>
  <div class="col-md-4">
  <label class="form-label">Type</label>
  <select class="form-select" name="type" required>
    <?php foreach ($SERVICE_TYPES as $group => $opts): ?>
      <optgroup label="<?=$group?>">
        <?php foreach ($opts as $opt): ?>
          <option value="<?=$opt?>" <?=($service['type']===$opt ? 'selected' : '')?>><?=$opt?></option>
        <?php endforeach; ?>
      </optgroup>
    <?php endforeach; ?>
  </select>
  <div class="form-text">If you choose “Other”, describe it in Notes.</div>
</div>


  <div class="col-md-4">
    <label class="form-label">Mileage</label>
    <input type="number" class="form-control" name="mileage" value="<?=htmlspecialchars($service['mileage'])?>" required>
  </div>
  <div class="col-md-4">
    <label class="form-label">Cost</label>
    <input type="number" step="0.01" class="form-control" name="cost" value="<?=htmlspecialchars($service['cost'])?>" required>
  </div>
  <div class="col-md-8">
    <label class="form-label">Notes (optional)</label>
    <textarea class="form-control" name="notes" rows="2"><?=htmlspecialchars($service['notes'])?></textarea>
  </div>
  <div class="col-12">
    <button class="btn btn-primary">Save</button>
    <a href="services.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const typeSelect = document.querySelector('select[name="type"]');
  const notes = document.querySelector('textarea[name="notes"]');

  function toggleNotesRequirement() {
    if (typeSelect.value === "Other") {
      notes.setAttribute("required", "required");
      notes.focus();
    } else {
      notes.removeAttribute("required");
    }
  }

  typeSelect.addEventListener("change", toggleNotesRequirement);
  toggleNotesRequirement(); // run once on page load
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
