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

// Search term (service type)
$search = trim($_GET['q'] ?? '');   // e.g., "oil", "coolant", etc.

// Pagination
$perPage = 10;                                      // rows per page
$page    = max(1, (int)($_GET['page'] ?? 1));       // current page
$offset  = ($page - 1) * $perPage;

// Sorting (whitelisted)
$allowedSorts = [
  'date'    => 's.service_date',
  'vehicle' => 'v.reg_no',
  'type'    => 's.type',
  'mileage' => 's.mileage',
  'cost'    => 's.cost',
];

$sort = $_GET['sort'] ?? 'date';
$dir  = strtolower($_GET['dir'] ?? 'desc');

if (!isset($allowedSorts[$sort])) $sort = 'date';
$dir = ($dir === 'asc') ? 'asc' : 'desc';

// final ORDER BY clause; make it stable by also ordering by id
$orderSql = $allowedSorts[$sort] . ' ' . strtoupper($dir) . ', s.id ' . strtoupper($dir);


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

// Fetch user's services (joined with their vehicles) + optional vehicle filter + optional search
// Build WHERE for both count and data queries
$where  = ["v.user_id=?"];
$params = [$user['id']];

if ($vehicleId) {
  $where[]  = "v.id=?";
  $params[] = $vehicleId;
}

if ($search !== '') {
  $where[]  = "s.type LIKE ?";
  $params[] = '%' . $search . '%';
}

// 1) Count total rows for pagination
$sqlCount = "SELECT COUNT(*) AS c
             FROM services s
             JOIN vehicles v ON s.vehicle_id = v.id
             WHERE " . implode(' AND ', $where);
$qc = db()->prepare($sqlCount);
$qc->execute($params);
$totalRows = (int)$qc->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Correct page if out of range
if ($page > $totalPages) {
  $page = $totalPages;
  $offset = ($page - 1) * $perPage;
}

// 2) Fetch current page of rows
$sql = "SELECT s.*, v.reg_no
        FROM services s
        JOIN vehicles v ON s.vehicle_id = v.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$orderSql}
        LIMIT {$perPage} OFFSET {$offset}";

$q = db()->prepare($sql);
$q->execute($params);
$rows = $q->fetchAll();


include __DIR__ . '/../templates/header.php';
?>

<style>
  /* Use fixed layout so widths are respected */
  .table-fixed { table-layout: fixed; width: 100%; }

  /* Number cells: keep compact and aligned */
  .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }

  /* Single-line truncation with ellipsis */
  .td-trunc {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Notes preview: keep very short */
  .note-preview{
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    max-width: 100%;
  }

  /* Give the actions column a little breathing room */
  td.actions .btn { vertical-align: middle; }
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

    <div class="col-md-4">
    <label class="form-label">Search by Type</label>
    <input type="text"
           class="form-control"
           name="q"
           value="<?= htmlspecialchars($search) ?>"
           placeholder="e.g., oil, coolant, brake">
  </div>

  <div class="col-md-4 d-flex align-items-end">
    <button class="btn btn-primary me-2">Search</button>
    <a class="btn btn-outline-secondary"
       href="services.php<?= $vehicleId ? ('?vehicle_id='.(int)$vehicleId) : '' ?>">
       Clear
    </a>
  </div>

</form>

<div class="mt-2">
  <a class="btn btn-outline-secondary btn-sm"
     href="export_services.php<?= $vehicleId ? ('?vehicle_id='.(int)$vehicleId) : '' ?><?= $search !== '' ? ($vehicleId ? '&' : '?') . 'q=' . urlencode($search) : '' ?>">
    Export CSV
  </a>
</div>

<?php
  // Build base query string for sort links (keep filters, drop page so it resets to 1)
  $qsSort = [];
  if ($vehicleId) $qsSort['vehicle_id'] = (int)$vehicleId;
  if ($search !== '') $qsSort['q'] = $search;

  // helper: make a sort link that toggles asc/desc for given key
  function sortUrl($key, $currentSort, $currentDir, $qs) {
    $dir = 'asc';
    if ($currentSort === $key) {
      $dir = ($currentDir === 'asc') ? 'desc' : 'asc';
    }
    $qs = array_merge($qs, ['sort' => $key, 'dir' => $dir]);
    return 'services.php?' . http_build_query($qs);
  }

  // helper: visual arrow next to active column
  function sortArrow($key, $currentSort, $currentDir) {
    if ($currentSort !== $key) return '';
    return $currentDir === 'asc' ? ' &uarr;' : ' &darr;';
  }
?>

<table class="table table-striped mt-3 table-fixed">
  <colgroup>
    <col style="width:90px;">  <!-- Date -->
    <col style="width:180px;">  <!-- Vehicle -->
    <col style="width:180px;">  <!-- Type -->
    <col style="width:80px;">  <!-- Mileage -->
    <col style="width:80px;">  <!-- Cost -->
    <col style="width:150px;">  <!-- Service Center -->
    <col style="width:50px;">  <!-- Notes -->
    <col style="width:120px;">  <!-- Actions -->
  </colgroup>
  <thead>
  <tr>
    <th>
      <a class="text-decoration-none" href="<?= sortUrl('date', $sort, $dir, $qsSort) ?>">
        Date<?= sortArrow('date', $sort, $dir) ?>
      </a>
    </th>
    <th>
      <a class="text-decoration-none" href="<?= sortUrl('vehicle', $sort, $dir, $qsSort) ?>">
        Vehicle<?= sortArrow('vehicle', $sort, $dir) ?>
      </a>
    </th>
    <th>
      <a class="text-decoration-none" href="<?= sortUrl('type', $sort, $dir, $qsSort) ?>">
        Type<?= sortArrow('type', $sort, $dir) ?>
      </a>
    </th>

    <th class="num">
  <a class="text-decoration-none" href="<?= sortUrl('mileage', $sort, $dir, $qsSort) ?>">
    Mileage<?= sortArrow('mileage', $sort, $dir) ?>
  </a>
</th>
<th class="num">
  <a class="text-decoration-none" href="<?= sortUrl('cost', $sort, $dir, $qsSort) ?>">
    Cost<?= sortArrow('cost', $sort, $dir) ?>
  </a>
</th>
  </th>
      <th class="text-center">
      Service Center
      </th>

  <th>
    Notes
  </th>
  <th></th>
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
      <td class="num"><?= number_format((int)$r['mileage']) ?></td>

<td class="num"><?= number_format((float)$r['cost'], 2) ?></td>

<td class="text-center" ><?= htmlspecialchars($r['service_center'] ?? '') ?></td>

     <td class="text-center">
  <?php
    $note = $r['notes'] ?? '';
    $isLong = (mb_strlen($note, 'UTF-8') > 0);
  ?>
  <?php if ($isLong): ?>
    <button type="button"
            class="btn btn-sm btn-outline-primary"
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

<?php
  // Build base query string preserving filters
  $qs = [];
  if ($vehicleId) $qs['vehicle_id'] = (int)$vehicleId;
  if ($search !== '') $qs['q'] = $search;

  // helper to build URL with page number
  function pageUrl($p, $qs) {
    $qs = array_merge($qs, ['page' => $p]);
    return 'services.php?' . http_build_query($qs);
  }
?>

<nav aria-label="Service pagination">
  <ul class="pagination justify-content-center">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $page <= 1 ? '#' : pageUrl($page-1, $qs) ?>">Previous</a>
    </li>

    <?php
      // simple window: show up to 7 pages around current
      $start = max(1, $page - 3);
      $end   = min($totalPages, $page + 3);
      if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="'.pageUrl(1,$qs).'">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
      for ($p = $start; $p <= $end; $p++) {
        $active = ($p === $page) ? ' active' : '';
        echo '<li class="page-item'.$active.'"><a class="page-link" href="'.pageUrl($p,$qs).'">'.$p.'</a></li>';
      }
      if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        echo '<li class="page-item"><a class="page-link" href="'.pageUrl($totalPages,$qs).'">'.$totalPages.'</a></li>';
      }
    ?>

    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $page >= $totalPages ? '#' : pageUrl($page+1, $qs) ?>">Next</a>
    </li>
  </ul>
</nav>


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
