<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/predict.php';
require_login();

$user = current_user();

// Get predictions for all vehicles
$data = cst_predict_for_user((int)$user['id']);

// Helper to format date
function fmt_date(?DateTime $d): string {
  return $d ? $d->format('Y-m-d') : '—';
}

// Decide status badge
function status_badge(?int $kmRem, ?int $daysRem, bool $isDue): string {
  if ($isDue) return '<span class="badge text-bg-danger">Overdue</span>';

  // “Due soon” if within 500 km or 14 days
  $soonKm   = ($kmRem !== null && $kmRem <= 500);
  $soonTime = ($daysRem !== null && $daysRem <= 14);
  if ($soonKm || $soonTime) return '<span class="badge text-bg-warning text-dark">Due soon</span>';

  return '<span class="badge text-bg-success">OK</span>';
}

include __DIR__ . '/../templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center">
  <h2>Upcoming Service Predictions</h2>
  <a class="btn btn-outline-secondary btn-sm" href="dashboard.php">Back to Dashboard</a>
</div>

<?php if (!$data): ?>
  <div class="alert alert-info mt-3">No vehicles found. Add a vehicle first.</div>
<?php else: ?>
  <?php foreach ($data as $block): ?>
    <?php
      $preds = $block['predictions'] ?? [];
      if (!$preds) continue;
    ?>
    <div class="card shadow-sm mt-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="m-0">Vehicle: <?= htmlspecialchars($block['reg_no']) ?></h5>
          <a class="btn btn-sm btn-outline-primary" href="service_form.php?vehicle_id=<?= (int)$block['vehicle_id'] ?>">Log Service</a>
        </div>

 <div class="table-responsive">
  <table class="table table-striped align-middle m-0 table-fixed">
    <thead>
      <tr>
        <th style="width:200px;">Service Type</th>
        <th class="num" style="width:120px;">Next Due (Mileage)</th>
        <th class="num" style="width:120px;">Km Remaining</th>
        <th class="num" style="width:140px;">Next Due (Date)</th>
        <th class="num" style="width:120px;">Days Remaining</th>
        <th style="width:100px;">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($preds as $p): ?>
        <?php
          $isDue   = !empty($p['is_due']);
          $nextMil = ($p['next_mileage'] !== null) ? number_format((int)$p['next_mileage']) : '—';

          if ($isDue) {
            $kmRemOut   = '0';
            $daysRemOut = '0';
            $nextDateOut = '—';   // 🔹 hide next due date
          } else {
            $kmRemOut   = ($p['km_remaining']   !== null) ? number_format((int)max(0, (int)$p['km_remaining'])) : '—';
            $daysRemOut = ($p['days_remaining'] !== null) ? (int)max(0, (int)$p['days_remaining']) : '—';
            $nextDateOut = fmt_date($p['next_date'] ?? null);
          }
        ?>
        <tr>
          <td class="text-truncate"><?= htmlspecialchars($p['type']) ?></td>
          <td class="num"><?= $nextMil ?></td>
          <td class="num"><?= $kmRemOut ?></td>
          <td class="num"><?= $nextDateOut ?></td> 
          <td class="num"><?= $daysRemOut ?></td>
          <td><?= status_badge($p['km_remaining'], $p['days_remaining'], $isDue) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<style>
.table-fixed {
  table-layout: fixed;
  width: 100%;
}
.num {
  text-align: center;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>



      </div>
    </div>
  <?php endforeach; ?>

  <?php
    // If none of the vehicles produced predictions (no history yet)
    $hasAny = false;
    foreach ($data as $b) { if (!empty($b['predictions'])) { $hasAny = true; break; } }
    if (!$hasAny):
  ?>
    <div class="alert alert-info mt-3">
      No predictions yet — log at least one service for a vehicle so we can calculate the next due.
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
