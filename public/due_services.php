<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();

// === Rules (same as dashboard/cron) ===
$RULES = [
  'Gear oil change'            => ['km' => 40000, 'days' => 720],
  'Brake fluid change'         => ['km' => 40000, 'days' => 730],
  'Engine coolant change'      => ['km' => 40000, 'days' => 730],
  'Engine coolant check'       => ['km' => 5000,  'days' => 90],
  'Brake pads check'           => ['km' => 10000, 'days' => 180],
  'Brake discs/rotors check'   => ['km' => 20000, 'days' => 365],
  'Tire rotation'              => ['km' => 8000,  'days' => 180],
  'Wheel alignment'            => ['km' => 10000, 'days' => 365],
  'Wheel balancing'            => ['km' => 10000, 'days' => 365],
  'Air filter change'          => ['km' => 15000, 'days' => 365],
  'Cabin filter change'        => ['km' => 15000, 'days' => 365],
  'Fuel filter change'         => ['km' => 30000, 'days' => 730],
  'Spark plug replacement'     => ['km' => 30000, 'days' => 730],
  'Battery check/replacement'  => ['km' => 20000, 'days' => 365],
  'Timing belt/chain check'    => ['km' => 50000, 'days' => 730],
  'Drive belt/serpentine belt check' => ['km' => 30000, 'days' => 365],
  'Complete suspension check'  => ['km' => 20000, 'days' => 365],
  'Shock absorbers/struts check' => ['km' => 30000, 'days' => 365],
  'AC system check'            => ['km' => 20000, 'days' => 365],
  'Lights & electrical check'  => ['km' => 0,     'days' => 180],
  'Exhaust system check'       => ['km' => 20000, 'days' => 365],
  'Windshield washer fluid refill' => ['km' => 0, 'days' => 90],
  'Power steering fluid check' => ['km' => 20000, 'days' => 365],
  'Tire check'                 => ['km' => 5000,  'days' => 90],
];

$OIL_RULES = [
  'Mineral'         => ['km' => 2500, 'days' => 90],
  'Semi synthetic'  => ['km' => 3500, 'days' => 180],
  'Full synthetic'  => ['km' => 5000, 'days' => 180],
  '_default'        => ['km' => 5000, 'days' => 180],
];

function km_or_days_due(?int $sinceKm, ?int $sinceDays, int $limitKm, int $limitDays): bool {
  $kmDue   = ($limitKm  > 0 && $sinceKm  !== null && $sinceKm  >= $limitKm);
  $timeDue = ($limitDays> 0 && $sinceDays!== null && $sinceDays>= $limitDays);
  return $kmDue || $timeDue;
}

$vehStmt = db()->prepare("SELECT id, reg_no, current_mileage FROM vehicles WHERE user_id=? ORDER BY id ASC");
$vehStmt->execute([$user['id']]);
$userVehicles = $vehStmt->fetchAll();

$alerts = [];
$today = new DateTime();

foreach ($userVehicles as $v) {
  $vehicleId = (int)$v['id'];
  $reg       = $v['reg_no'];
  $vehOdo    = (int)$v['current_mileage'];

  // Engine oil check
  $lastOil = db()->prepare("
    SELECT service_date, mileage, oil_type
    FROM services
    WHERE vehicle_id=? AND type='Engine oil change'
    ORDER BY service_date DESC, id DESC
    LIMIT 1
  ");
  $lastOil->execute([$vehicleId]);
  if ($oil = $lastOil->fetch()) {
    $oilType   = $oil['oil_type'] ?? null;
    $ruleOil   = $OIL_RULES[$oilType] ?? $OIL_RULES['_default'];
    $lastKm    = (int)$oil['mileage'];
    $lastDate  = new DateTime($oil['service_date']);
    $sinceKm   = max(0, $vehOdo - $lastKm);
    $sinceDays = $today->diff($lastDate)->days;

    if (km_or_days_due($sinceKm, $sinceDays, $ruleOil['km'], $ruleOil['days'])) {
      $alerts[] = [
        'vehicle_id' => $vehicleId,
        'reg_no'     => $reg,
        'type'       => 'Engine oil change' . ($oilType ? " ({$oilType})" : ''),
        'reason'     => "{$sinceKm} km • {$sinceDays} days since last",
      ];
    }
  }

  // All other services
  foreach ($RULES as $type => $rule) {
    $st = db()->prepare("
      SELECT service_date, mileage
      FROM services
      WHERE vehicle_id=? AND type=?
      ORDER BY service_date DESC, id DESC
      LIMIT 1
    ");
    $st->execute([$vehicleId, $type]);
    $row = $st->fetch();
    if (!$row) continue;

    $lastKm    = (int)$row['mileage'];
    $lastDate  = new DateTime($row['service_date']);
    $sinceKm   = max(0, $vehOdo - $lastKm);
    $sinceDays = $today->diff($lastDate)->days;

    if (km_or_days_due($sinceKm, $sinceDays, $rule['km'], $rule['days'])) {
      $alerts[] = [
        'vehicle_id' => $vehicleId,
        'reg_no'     => $reg,
        'type'       => $type,
        'reason'     => "{$sinceKm} km • {$sinceDays} days since last",
      ];
    }
  }
}

include __DIR__ . '/../templates/header.php';
?>

<h2>Due Maintenance</h2>

<?php if (!$alerts): ?>
  <div class="alert alert-success">No maintenance due right now. ✅</div>
<?php else: ?>
  <table class="table table-striped mt-3">
    <thead>
      <tr>
        <th>Vehicle</th>
        <th>Type</th>
        <th>Reason</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($alerts as $a): ?>
      <tr>
        <td><?= htmlspecialchars($a['reg_no']) ?></td>
        <td><?= htmlspecialchars($a['type']) ?></td>
        <td><?= htmlspecialchars($a['reason']) ?></td>
        <td>
          <a class="btn btn-sm btn-outline-primary" href="service_form.php?vehicle_id=<?= $a['vehicle_id'] ?>">Log Service</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
