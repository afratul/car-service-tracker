<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();

// Pagination
$perPage = 10;                                   // rows per page
$page    = max(1, (int)($_GET['page'] ?? 1));    // current page
$offset  = ($page - 1) * $perPage;


// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $stmt = db()->prepare("DELETE FROM vehicles WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$_POST['delete_id'], $user['id']]);
    header('Location: vehicles.php');
    exit;
}

// Count total for pagination
$c = db()->prepare("SELECT COUNT(*) FROM vehicles WHERE user_id = ?");
$c->execute([$user['id']]);
$totalRows = (int)$c->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Correct page if out of range
if ($page > $totalPages) {
  $page = $totalPages;
  $offset = ($page - 1) * $perPage;
}

// Fetch current page
$sql = "SELECT *
        FROM vehicles
        WHERE user_id = ?
        ORDER BY created_at ASC, id ASC
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = db()->prepare($sql);
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
      <td>
      <a href="services.php?vehicle_id=<?=$v['id']?>" class="text-decoration-none"><?=htmlspecialchars($v['reg_no'])?></a> </td>
      <td><a href="services.php?vehicle_id=<?=$v['id']?>" class="text-decoration-none"> <?=htmlspecialchars($v['make'])?></a></td>
      <td><a href="services.php?vehicle_id=<?=$v['id']?>" class="text-decoration-none"><?=htmlspecialchars($v['model'])?></a></td>
      <td><a href="services.php?vehicle_id=<?=$v['id']?>" class="text-decoration-none"><?=htmlspecialchars($v['year'])?></a></td>
      <td><a href="services.php?vehicle_id=<?=$v['id']?>" class="text-decoration-none"><?=htmlspecialchars($v['current_mileage'])?></a></td>
      <td><a href="services.php?vehicle_id=<?=$v['id']?>" class="text-decoration-none"><?=htmlspecialchars($v['fuel_type'] ?? '')?></a></td>
      <td class="text-end">
  <a class="btn btn-sm btn-outline-primary" href="service_form.php?vehicle_id=<?=$v['id']?>">Add Service</a>
  <a class="btn btn-sm btn-outline-secondary" href="vehicle_form.php?id=<?=$v['id']?>">Edit</a>
  <form method="post" class="d-inline" onsubmit="return confirm('Delete this vehicle?');">
    <input type="hidden" name="delete_id" value="<?=$v['id']?>">
   <button class="btn btn-sm btn-danger">Delete</button>

  </form>
</td>

    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php
  // helper to build URL with page param (no other filters on vehicles page)
  function vehPageUrl($p) {
    return 'vehicles.php?' . http_build_query(['page' => $p]);
  }
?>

<nav aria-label="Vehicles pagination">
  <ul class="pagination justify-content-center">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $page <= 1 ? '#' : vehPageUrl($page-1) ?>">Previous</a>
    </li>

    <?php
      // show up to 7 page links around current page
      $start = max(1, $page - 3);
      $end   = min($totalPages, $page + 3);
      if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="'.vehPageUrl(1).'">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
      for ($p = $start; $p <= $end; $p++) {
        $active = ($p === $page) ? ' active' : '';
        echo '<li class="page-item'.$active.'"><a class="page-link" href="'.vehPageUrl($p).'">'.$p.'</a></li>';
      }
      if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        echo '<li class="page-item"><a class="page-link" href="'.vehPageUrl($totalPages).'">'.$totalPages.'</a></li>';
      }
    ?>

    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $page >= $totalPages ? '#' : vehPageUrl($page+1) ?>">Next</a>
    </li>
  </ul>
</nav>

<?php include __DIR__ . '/../templates/footer.php'; ?>
