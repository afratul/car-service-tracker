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

// --- Engine oil change alerts (oil-type-specific rules) ---
$OIL_RULES = [
  'Mineral'         => ['km' => 2500, 'days' => 90],   // 3 months or 2500 km
  'Semi synthetic'  => ['km' => 3500, 'days' => 180],  // 6 months or 3500 km
  'Full synthetic'  => ['km' => 5000, 'days' => 180],  // 6 months or 5000 km
  '_default'        => ['km' => 5000, 'days' => 180],  // fallback if oil_type is NULL/unknown
];

// Load user's vehicles with their current mileage
$vehStmt = db()->prepare("SELECT id, reg_no, current_mileage FROM vehicles WHERE user_id=? ORDER BY id ASC");
$vehStmt->execute([$user['id']]);
$userVehicles = $vehStmt->fetchAll();

$alerts = [];
$today = new DateTime();

foreach ($userVehicles as $v) {
    // most recent engine oil change for this vehicle (now also fetching oil_type)
    $lastOil = db()->prepare("
        SELECT service_date, mileage, oil_type
        FROM services
        WHERE vehicle_id=? AND type='Engine oil change'
        ORDER BY service_date DESC, id DESC
        LIMIT 1
    ");
    $lastOil->execute([$v['id']]);
    $row = $lastOil->fetch();

    $oilType   = $row['oil_type'] ?? null;
    $rule      = $OIL_RULES[$oilType] ?? $OIL_RULES['_default'];

    $lastMileage = $row ? (int)$row['mileage'] : 0;
    $lastDate    = $row && !empty($row['service_date']) ? new DateTime($row['service_date']) : null;

    $sinceMileage = max(0, (int)$v['current_mileage'] - $lastMileage);
    $sinceDays    = $lastDate ? $today->diff($lastDate)->days : null;

    $dueByMileage = ($sinceMileage >= $rule['km']);
    $dueByTime    = ($sinceDays !== null && $sinceDays >= $rule['days']);

    if ($dueByMileage || $dueByTime) {
        $reason = [];
        // include oil type in the reason if we have it
        if ($oilType) {
            $reason[] = "Oil: $oilType";
        }
        if ($dueByMileage) $reason[] = "{$sinceMileage} km since last oil change (limit {$rule['km']} km)";
        if ($dueByTime)    $reason[] = "{$sinceDays} days since last oil change (limit {$rule['days']} days)";
        $alerts[] = [
            'reg_no'     => $v['reg_no'],
            'reason'     => implode(' • ', $reason),
            'vehicle_id' => $v['id'],
        ];
    }
}



include __DIR__ . '/../templates/header.php';
?>
<h2 class="mb-4">Dashboard</h2>

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
      <h5 class="m-0">Maintenance Alerts</h5>
    </div>

    <?php if (!$alerts): ?>
      <div class="text-muted">No alerts right now.</div>
    <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($alerts as $a): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($a['reg_no']) ?></strong>
              <div class="small text-muted"><?= htmlspecialchars($a['reason']) ?></div>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="service_form.php?vehicle_id=<?= $a['vehicle_id'] ?>">Log service</a>
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
