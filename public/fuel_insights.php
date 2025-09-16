<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();

// Vehicles for this user
$vehStmt = db()->prepare("SELECT id, reg_no FROM vehicles WHERE user_id=? ORDER BY id ASC");
$vehStmt->execute([$user['id']]);
$vehicles = $vehStmt->fetchAll();

if (!$vehicles) {
    include __DIR__ . '/../templates/header.php';
    echo "<div class='alert alert-warning'>Please add a vehicle first.</div>";
    include __DIR__ . '/../templates/footer.php';
    exit;
}

// Vehicle selection (default first)
$vehicleId = (int)($_GET['vehicle_id'] ?? $vehicles[0]['id']);

// Make sure selected vehicle belongs to user
$veh = null;
foreach ($vehicles as $v) { if ((int)$v['id'] === $vehicleId) { $veh = $v; break; } }
if (!$veh) { $vehicleId = (int)$vehicles[0]['id']; $veh = $vehicles[0]; }

// Queries
$db = db();

// Last fuel entry
$lastStmt = $db->prepare("
  SELECT liters, cost, odometer, filled_at
  FROM fuel_logs
  WHERE vehicle_id=?
  ORDER BY filled_at DESC
  LIMIT 1
");
$lastStmt->execute([$vehicleId]);
$last = $lastStmt->fetch();

// Monthly totals (last 12 months)
$monthlyStmt = $db->prepare("
  SELECT DATE_FORMAT(filled_at,'%Y-%m') AS ym,
         SUM(liters) AS total_liters,
         SUM(cost)   AS total_cost,
         MIN(filled_at) AS first_fill_in_month,
         MAX(filled_at) AS last_fill_in_month
  FROM fuel_logs
  WHERE vehicle_id=?
  GROUP BY ym
  ORDER BY ym DESC
  LIMIT 12
");
$monthlyStmt->execute([$vehicleId]);
$monthly = $monthlyStmt->fetchAll();

// This month window
$startOfMonth = (new DateTime('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
$startOfNext  = (new DateTime('first day of next month 00:00:00'))->format('Y-m-d H:i:s');

// Totals for this month
$monthTotalsStmt = $db->prepare("
  SELECT COALESCE(SUM(liters),0) AS liters, COALESCE(SUM(cost),0) AS cost
  FROM fuel_logs
  WHERE vehicle_id=? AND filled_at >= ? AND filled_at < ?
");
$monthTotalsStmt->execute([$vehicleId, $startOfMonth, $startOfNext]);
$monthTotals = $monthTotalsStmt->fetch();

// Km driven this month (based on min/max odometer seen in fuel logs for the month)
$odoStmt = $db->prepare("
  SELECT MIN(odometer) AS min_odo, MAX(odometer) AS max_odo
  FROM fuel_logs
  WHERE vehicle_id=? AND filled_at >= ? AND filled_at < ?
");
$odoStmt->execute([$vehicleId, $startOfMonth, $startOfNext]);
$odoRange = $odoStmt->fetch();
$kmThisMonth = 0;
if ($odoRange && $odoRange['min_odo'] !== null && $odoRange['max_odo'] !== null) {
    $kmThisMonth = max(0, (int)$odoRange['max_odo'] - (int)$odoRange['min_odo']);
}

// Recent refuels (last 10)
$listStmt = $db->prepare("
  SELECT liters, cost, odometer, filled_at
  FROM fuel_logs
  WHERE vehicle_id=?
  ORDER BY filled_at DESC
  LIMIT 10
");
$listStmt->execute([$vehicleId]);
$recent = $listStmt->fetchAll();

include __DIR__ . '/../templates/header.php';
?>

<h2>Fuel Insights</h2>

<form method="get" class="row g-3 mb-3">
  <div class="col-md-4">
    <label class="form-label">Vehicle</label>
    <select name="vehicle_id" class="form-select" onchange="this.form.submit()">
      <?php foreach ($vehicles as $v): ?>
        <option value="<?= (int)$v['id'] ?>" <?= ((int)$v['id']===$vehicleId?'selected':'') ?>>
          <?= htmlspecialchars($v['reg_no']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title mb-2">Last Refuel</h5>
        <?php if ($last): ?>
          <div><strong>Date:</strong> <?= htmlspecialchars((new DateTime($last['filled_at']))->format('M d, Y H:i')) ?></div>
          <div><strong>Liters:</strong> <?= number_format((float)$last['liters'], 2) ?></div>
          <div><strong>Cost:</strong> <?= number_format((float)$last['cost'], 2) ?></div>
          <div><strong>Odometer:</strong> <?= number_format((int)$last['odometer']) ?> km</div>
        <?php else: ?>
          <div class="text-muted">No fuel records yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title mb-2">This Month</h5>
        <div class="row">
          <div class="col-md-4"><strong>Total Liters:</strong><br><?= number_format((float)$monthTotals['liters'], 2) ?></div>
          <div class="col-md-4"><strong>Total Cost:</strong><br><?= number_format((float)$monthTotals['cost'], 2) ?></div>
          <div class="col-md-4"><strong>Km Driven:</strong><br><?= number_format((int)$kmThisMonth) ?> km</div>
        </div>
        <div class="form-text mt-2">
          *Km driven is calculated from the min/max odometer values recorded in this month's fuel logs.
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-body">
    <h5 class="card-title mb-3">Monthly Totals (Last 12 Months)</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Month</th>
            <th>Total Liters</th>
            <th>Total Cost</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($monthly): ?>
            <?php foreach ($monthly as $m): ?>
              <tr>
                <td><?= htmlspecialchars($m['ym']) ?></td>
                <td><?= number_format((float)$m['total_liters'], 2) ?></td>
                <td><?= number_format((float)$m['total_cost'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="3" class="text-muted">No data yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mt-3 mb-4">
  <div class="card-body">
    <h5 class="card-title mb-3">Recent Refuels</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Date</th>
            <th>Liters</th>
            <th>Cost</th>
            <th>Odometer (km)</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent): ?>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td><?= htmlspecialchars((new DateTime($r['filled_at']))->format('Y-m-d H:i')) ?></td>
                <td><?= number_format((float)$r['liters'], 2) ?></td>
                <td><?= number_format((float)$r['cost'], 2) ?></td>
                <td><?= number_format((int)$r['odometer']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4" class="text-muted">No records yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
