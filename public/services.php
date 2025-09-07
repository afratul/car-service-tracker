<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();

// ▼ Add this block here
$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;

// If a specific vehicle is requested, make sure it belongs to this user
if ($vehicleId) {
    $own = db()->prepare("SELECT id FROM vehicles WHERE id=? AND user_id=?");
    $own->execute([$vehicleId, $user['id']]);
    if (!$own->fetch()) { $vehicleId = 0; } // reset if not owned
}

// Load vehicles for the dropdown
$vs = db()->prepare("SELECT id, reg_no FROM vehicles WHERE user_id=? ORDER BY id ASC");
$vs->execute([$user['id']]);
$vehicles = $vs->fetchAll();


// Handle delete securely (only your own records)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $sid = (int)$_POST['delete_id'];

    // confirm the service belongs to one of the user's vehicles
    $chk = db()->prepare("
        SELECT s.id
        FROM services s
        JOIN vehicles v ON s.vehicle_id = v.id
        WHERE s.id = ? AND v.user_id = ?
    ");
    $chk->execute([$sid, $user['id']]);
    if ($chk->fetch()) {
        db()->prepare("DELETE FROM services WHERE id = ?")->execute([$sid]);
    }

    // redirect to avoid resubmission on refresh
    // redirect to avoid resubmission AND keep current filter
$redir = 'services.php' . ($vehicleId ? ('?vehicle_id=' . $vehicleId) : '');
header('Location: ' . $redir);
exit;

}


// Fetch user's services (joined with their vehicles)
if ($vehicleId) {
    $q = db()->prepare("SELECT s.*, v.reg_no
                        FROM services s
                        JOIN vehicles v ON s.vehicle_id = v.id
                        WHERE v.user_id=? AND v.id=?
                        ORDER BY s.service_date DESC, s.id DESC");
    $q->execute([$user['id'], $vehicleId]);
} else {
    $q = db()->prepare("SELECT s.*, v.reg_no
                        FROM services s
                        JOIN vehicles v ON s.vehicle_id = v.id
                        WHERE v.user_id=?
                        ORDER BY s.service_date DESC, s.id DESC");
    $q->execute([$user['id']]);
}
$rows = $q->fetchAll();


include __DIR__ . '/../templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center">
  <h2>Service Records</h2>
  <a class="btn btn-primary" href="service_form.php">Add Service</a>
</div>

<form class="row g-3 mt-2" method="get">
  <div class="col-md-4">
    <label class="form-label">Filter by Vehicle</label>
    <select class="form-select" name="vehicle_id" onchange="this.form.submit()">
      <option value="0">All vehicles</option>
      <?php foreach ($vehicles as $v): ?>
        <option value="<?=$v['id']?>" <?=($vehicleId==$v['id'] ? 'selected' : '')?>>
          <?=htmlspecialchars($v['reg_no'])?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</form>


<table class="table table-striped mt-3">
  <thead>
  <tr>
    <th>Date</th><th>Vehicle</th><th>Type</th><th>Mileage</th><th>Cost</th><th>Notes</th><th></th>
  </tr>
</thead>

<tbody>
  <?php foreach($rows as $r): ?>
  <tr>
    <td><?=htmlspecialchars($r['service_date'])?></td>
    <td><?=htmlspecialchars($r['reg_no'])?></td>
    <td><?=htmlspecialchars($r['type'])?></td>
    <td><?=number_format($r['mileage'])?></td>
    <td><?=number_format((float)$r['cost'], 2)?></td>
    <td>
      <?php
        $note = $r['notes'] ?? '';
        $preview = mb_strimwidth($note, 0, 60, '…', 'UTF-8');
        echo htmlspecialchars($preview);
      ?>
    </td>
    <td class="text-end">
      <a class="btn btn-sm btn-outline-secondary" href="service_form.php?id=<?=$r['id']?>">Edit</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Delete this record?');">
        <input type="hidden" name="delete_id" value="<?=$r['id']?>">
        <button class="btn btn-sm btn-outline-danger">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</tbody>

</table>
<?php include __DIR__ . '/../templates/footer.php'; ?>
