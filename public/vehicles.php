<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $stmt = db()->prepare("DELETE FROM vehicles WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_POST['delete_id'], $user['id']]);
    header('Location: vehicles.php');
    exit;
}

// Fetch vehicles
$stmt = db()->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at ASC");
$stmt->execute([$user['id']]);
$vehicles = $stmt->fetchAll();

include __DIR__ . '/../templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center">
  <h2>My Vehicles</h2>
  <a class="btn btn-primary" href="vehicle_form.php">Add Vehicle</a>
</div>
<table class="table table-striped mt-3">
  <thead>
    <tr>
      <th>Reg No</th><th>Brand</th><th>Model</th><th>Year</th><th>Mileage</th><th>Fuel</th><th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($vehicles as $v): ?>
    <tr>
      <td><?=htmlspecialchars($v['reg_no'])?></td>
      <td><?=htmlspecialchars($v['make'])?></td>
      <td><?=htmlspecialchars($v['model'])?></td>
      <td><?=htmlspecialchars($v['year'])?></td>
      <td><?=htmlspecialchars($v['current_mileage'])?></td>
      <td><?=htmlspecialchars($v['fuel_type'] ?? '')?></td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-secondary" href="vehicle_form.php?id=<?=$v['id']?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this vehicle?');">
          <input type="hidden" name="delete_id" value="<?=$v['id']?>">
          <button class="btn btn-sm btn-outline-danger">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/../templates/footer.php'; ?>
