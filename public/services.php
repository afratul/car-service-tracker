<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();

// Fetch user's services (joined with their vehicles)
$q = db()->prepare("SELECT s.*, v.reg_no 
                    FROM services s 
                    JOIN vehicles v ON s.vehicle_id = v.id 
                    WHERE v.user_id=? 
                    ORDER BY s.service_date DESC, s.id DESC");
$q->execute([$user['id']]);
$rows = $q->fetchAll();

include __DIR__ . '/../templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center">
  <h2>Service Records</h2>
  <a class="btn btn-primary" href="service_form.php">Add Service</a>
</div>

<table class="table table-striped mt-3">
  <thead>
    <tr>
      <th>Date</th><th>Vehicle</th><th>Type</th><th>Mileage</th><th>Cost</th><th></th>
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
