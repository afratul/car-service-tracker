<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();

// quick stats
$vehCount = db()->prepare("SELECT COUNT(*) AS c FROM vehicles WHERE user_id=?");
$vehCount->execute([$user['id']]);
$totalVehicles = (int)$vehCount->fetch()['c'];

$svcCount = db()->prepare("
  SELECT COUNT(*) AS c
  FROM services s
  JOIN vehicles v ON s.vehicle_id = v.id
  WHERE v.user_id=?
");
$svcCount->execute([$user['id']]);
$totalServices = (int)$svcCount->fetch()['c'];

$lastSvc = db()->prepare("
  SELECT s.service_date, s.type, v.reg_no
  FROM services s
  JOIN vehicles v ON s.vehicle_id = v.id
  WHERE v.user_id=?
  ORDER BY s.service_date DESC, s.id DESC
  LIMIT 1
");
$lastSvc->execute([$user['id']]);
$latest = $lastSvc->fetch();

// recent 5 services
$recent = db()->prepare("
  SELECT s.id, s.service_date, s.type, s.mileage, s.cost, v.reg_no
  FROM services s
  JOIN vehicles v ON s.vehicle_id = v.id
  WHERE v.user_id=?
  ORDER BY s.service_date DESC, s.id DESC
  LIMIT 5
");
$recent->execute([$user['id']]);
$recentRows = $recent->fetchAll();

// --- Maintenance alerts (all types, same logic as cron) ---

// Core rules (km OR days)
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
  'Lights & electrical check'  => ['km' => 0,     'days' => 180], // time-only
  'Exhaust system check'       => ['km' => 20000, 'days' => 365],
  'Windshield washer fluid refill' => ['km' => 0, 'days' => 90], // time-only
  'Power steering fluid check' => ['km' => 20000, 'days' => 365],
  'Tire check'                 => ['km' => 5000,  'days' => 90],
];

// Engine oil depends on oil_type
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

  // 1) Engine oil (most recent record)
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
      $reason = [];
      if ($ruleOil['km'] > 0)   $reason[] = "{$sinceKm} km since last";
      if ($ruleOil['days'] > 0) $reason[] = "{$sinceDays} days since last";
      $alerts[] = [
        'vehicle_id' => $vehicleId,
        'reg_no'     => $reg,
        'type'       => 'Engine oil change' . ($oilType ? " ({$oilType})" : ''),
        'reason'     => implode(' • ', $reason),
      ];
    }
  }

  // 2) All other service types in $RULES
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
    if (!$row) continue; // no record yet → skip

    $lastKm    = (int)$row['mileage'];
    $lastDate  = new DateTime($row['service_date']);
    $sinceKm   = max(0, $vehOdo - $lastKm);
    $sinceDays = $today->diff($lastDate)->days;

    if (km_or_days_due($sinceKm, $sinceDays, $rule['km'], $rule['days'])) {
      $reason = [];
      if ($rule['km'] > 0)   $reason[] = "{$sinceKm} km since last";
      if ($rule['days'] > 0) $reason[] = "{$sinceDays} days since last";
      $alerts[] = [
        'vehicle_id' => $vehicleId,
        'reg_no'     => $reg,
        'type'       => $type,
        'reason'     => implode(' • ', $reason),
      ];
    }
  }
}

$dueCount = count($alerts);


// ---------- Nearby Predictions (show like Alerts) ----------
$PREDICT_KM_WINDOW   = 1000;
$PREDICT_DAYS_WINDOW = 30;
$predictionsSoon = [];

/* loop over $userVehicles and compute $predictionsSoon
   (the exact snippet you posted goes here unchanged) */
   foreach ($userVehicles as $v) {
    $vehicleId = (int)$v['id'];
    $reg       = $v['reg_no'];
    $vehOdo    = (int)$v['current_mileage'];

    // 1) Engine oil change (depends on oil_type)
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
        $rule      = $OIL_RULES[$oilType] ?? $OIL_RULES['_default'];
        $lastKm    = (int)$oil['mileage'];
        $lastDate  = new DateTime($oil['service_date']);
        $sinceKm   = max(0, $vehOdo - $lastKm);
        $sinceDays = $today->diff($lastDate)->days;

        $kmToDue   = ($rule['km']   > 0 ? max(0, $rule['km']   - $sinceKm)   : PHP_INT_MAX);
        $daysToDue = ($rule['days'] > 0 ? max(0, $rule['days'] - $sinceDays) : PHP_INT_MAX);

        // Only if within window and not already due (due items appear in Alerts)
        if (($kmToDue <= $PREDICT_KM_WINDOW) || ($daysToDue <= $PREDICT_DAYS_WINDOW)) {
            if (!km_or_days_due($sinceKm, $sinceDays, $rule['km'], $rule['days'])) {
                $reason = [];
                if ($kmToDue   !== PHP_INT_MAX) $reason[] = "{$kmToDue} km to due";
                if ($daysToDue !== PHP_INT_MAX) $reason[] = "{$daysToDue} days to due";

                $predictionsSoon[] = [
                    'vehicle_id' => $vehicleId,
                    'reg_no'     => $reg,
                    'type'       => 'Engine oil change',
                    'reason'     => implode(' • ', $reason),
                    'sort_key'   => min($kmToDue, $daysToDue),
                ];
            }
        }
    }

    // 2) All other rule-based services
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

        $kmToDue   = ($rule['km']   > 0 ? max(0, $rule['km']   - $sinceKm)   : PHP_INT_MAX);
        $daysToDue = ($rule['days'] > 0 ? max(0, $rule['days'] - $sinceDays) : PHP_INT_MAX);

        if (($kmToDue <= $PREDICT_KM_WINDOW) || ($daysToDue <= $PREDICT_DAYS_WINDOW)) {
            if (!km_or_days_due($sinceKm, $sinceDays, $rule['km'], $rule['days'])) {
                $reason = [];
                if ($kmToDue   !== PHP_INT_MAX) $reason[] = "{$kmToDue} km to due";
                if ($daysToDue !== PHP_INT_MAX) $reason[] = "{$daysToDue} days to due";

                $predictionsSoon[] = [
                    'vehicle_id' => $vehicleId,
                    'reg_no'     => $reg,
                    'type'       => $type,
                    'reason'     => implode(' • ', $reason),
                    'sort_key'   => min($kmToDue, $daysToDue),
                ];
            }
        }
    }
}

usort($predictionsSoon, fn($a,$b) => $a['sort_key'] <=> $b['sort_key']);
?>


<?php include __DIR__ . '/../templates/header.php'; ?>

<h2 class="mb-4">Dashboard</h2>
<p class="lead">Welcome, <?= htmlspecialchars($user['name']) ?> 👋</p>


<div class="row g-3">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fs-6 text-muted">Total Vehicles</div>
        <div class="fs-3 fw-bold"><?= $totalVehicles ?></div>
        <a href="vehicles.php" class="btn btn-sm btn-outline-primary mt-2">Manage vehicles</a>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fs-6 text-muted">Total Services</div>
        <div class="fs-3 fw-bold"><?= $totalServices ?></div>
        <a href="services.php" class="btn btn-sm btn-outline-primary mt-2">View services</a>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="fs-6 text-muted">Latest Service</div>
        <?php if ($latest): ?>
          <div class="fw-semibold"><?= htmlspecialchars($latest['type']) ?></div>
          <div class="small text-muted">
            <?= htmlspecialchars($latest['reg_no']) ?> •
            <?= htmlspecialchars($latest['service_date']) ?>
          </div>
        <?php else: ?>
          <div class="text-muted">No services yet</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- Maintenance Alerts -->
<div class="card shadow-sm mt-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="m-0">
  🚨 Maintenance Alerts
    <?php if ($dueCount): ?>
      <span class="badge text-bg-danger ms-2"><?= $dueCount ?></span>
    <?php else: ?>
      <span class="badge text-bg-secondary ms-2">0</span>
    <?php endif; ?>
  </h5>
  <a class="btn btn-sm btn-outline-secondary" href="due_services.php">View all</a>

</div>


    <?php if (!$alerts): ?>
      <div class="text-muted">No alerts right now.</div>
    <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($alerts as $a): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($a['type']) ?></strong>
            <div class="small text-muted">
              <?= htmlspecialchars($a['reg_no']) ?> • <?= htmlspecialchars($a['reason']) ?>
            </div>

            </div>
            <a class="btn btn-sm btn-outline-primary" href="service_form.php?vehicle_id=<?= $a['vehicle_id'] ?>">Log service</a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<!-- Nearby Predictions (like Alerts) -->
<div class="card shadow-sm mt-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="m-0">
  ⏳ Upcoming Predictions
  <span class="badge text-bg-info ms-2"><?= count($predictionsSoon) ?></span>
</h5>

      <a class="btn btn-sm btn-outline-secondary" href="predictions.php">View all</a>
    </div>

    <?php if (!$predictionsSoon): ?>
      <div class="text-muted">Nothing coming up soon.</div>
    <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($predictionsSoon as $p): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($p['type']) ?></strong>
              <div class="small text-muted">
                <?= htmlspecialchars($p['reg_no']) ?> • <?= htmlspecialchars($p['reason']) ?>
              </div>
            </div>
            <a class="btn btn-sm btn-outline-primary"
               href="service_form.php?vehicle_id=<?= (int)$p['vehicle_id'] ?>">Log service</a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>


<!-- Recent services -->
<div class="card shadow-sm mt-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="m-0">Recent Services</h5>
      <a class="btn btn-sm btn-outline-secondary" href="service_form.php">Add Service</a>
    </div>

    <?php if (!$recentRows): ?>
      <div class="text-muted">No service history yet.</div>
    <?php else: ?>
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Date</th>
            <th>Vehicle</th>
            <th>Type</th>
            <th>Mileage</th>
            <th>Cost</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recentRows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['service_date']) ?></td>
            <td><?= htmlspecialchars($r['reg_no']) ?></td>
            <td><?= htmlspecialchars($r['type']) ?></td>
            <td><?= number_format((int)$r['mileage']) ?></td>
            <td><?= number_format((float)$r['cost'], 2) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" href="service_form.php?id=<?= $r['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
