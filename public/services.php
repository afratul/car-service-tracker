<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();

// Read selected vehicle from querystring (0 = all)
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

<style>
  /* clamp the notes preview to 1 line, very short */
  .note-preview{
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    max-width: 200px;  /* was 400px before, now smaller */
  }
</style>


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

<div class="mt-2">
  <a class="btn btn-outline-secondary btn-sm"
     href="export_services.php<?= $vehicleId ? ('?vehicle_id='.(int)$vehicleId) : '' ?>">
    Export CSV
  </a>
</div>


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
      <td>
        <?=htmlspecialchars($r['type'])?>
        <?php if ($r['type'] === 'Engine oil change' && !empty($r['oil_type'])): ?>
          <span class="badge bg-secondary ms-1"><?=htmlspecialchars($r['oil_type'])?></span>
        <?php endif; ?>
      </td>
      <td><?=number_format($r['mileage'])?></td>
      <td><?=number_format((float)$r['cost'], 2)?></td>
      <td>
        <?php
          $note = $r['notes'] ?? '';
          $isLong = (mb_strlen($note, 'UTF-8') > 100); // decide when to show "View"
        ?>
        <div class="note-preview"><?= nl2br(htmlspecialchars($note)) ?></div>
        <?php if ($isLong): ?>
          <button type="button"
                  class="btn btn-sm btn-outline-primary ms-2"
                  data-bs-toggle="modal"
                  data-bs-target="#noteModal-<?= (int)$r['id'] ?>">
            View
          </button>
        <?php endif; ?>
      </td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-secondary me-2" href="service_form.php?id=<?=$r['id']?>">Edit</a>
<form method="post" class="d-inline" onsubmit="return confirm('Delete this record?');">
  <input type="hidden" name="delete_id" value="<?=$r['id']?>">
  <button class="btn btn-sm btn-danger">Delete</button>

</form>

      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if ($rows): ?>
  <?php foreach ($rows as $r): ?>
    <?php
      $note = $r['notes'] ?? '';
      if ($note === '') continue;
    ?>
    <div class="modal fade" id="noteModal-<?= (int)$r['id'] ?>" tabindex="-1" aria-labelledby="noteModalLabel-<?= (int)$r['id'] ?>" aria-hidden="true">
      <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="noteModalLabel-<?= (int)$r['id'] ?>">
              Note — <?= htmlspecialchars($r['reg_no']) ?> (<?= htmlspecialchars($r['service_date']) ?>)
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="white-space:pre-wrap;">
            <?= nl2br(htmlspecialchars($note)) ?>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <a class="btn btn-primary" href="service_form.php?id=<?= (int)$r['id'] ?>">Edit service</a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
