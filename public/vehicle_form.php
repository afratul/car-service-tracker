<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$vehicle = [
  'make'=>'','model'=>'','year'=>'','reg_no'=>'','vin'=>'',
  'current_mileage'=>0,'fuel_type'=>''
];

if ($id) {
  $stmt = db()->prepare("SELECT * FROM vehicles WHERE id = ? AND user_id = ?");
  $stmt->execute([$id, $user['id']]);
  $vehicle = $stmt->fetch() ?: $vehicle;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = [
    'make' => trim($_POST['make'] ?? ''),
    'model' => trim($_POST['model'] ?? ''),
    'year' => (int)($_POST['year'] ?? 0),
    'reg_no' => strtoupper(trim($_POST['reg_no'] ?? '')),
    'vin' => strtoupper(trim($_POST['vin'] ?? '')),
    'current_mileage' => (int)($_POST['current_mileage'] ?? 0),
    'fuel_type' => trim($_POST['fuel_type'] ?? '')
  ];

  if ($data['make']==='' || $data['model']==='') $errors[] = 'Brand and Model are required';
  if ($data['year'] < 1970 || $data['year'] > (int)date('Y')+1) $errors[] = 'Year looks invalid';
  if ($data['reg_no']==='') $errors[] = 'Registration number is required';
  if ($data['current_mileage'] < 0) $errors[] = 'Mileage cannot be negative';

  if (!$errors) {
    if ($id) {
      $sql = "UPDATE vehicles SET make=?, model=?, year=?, reg_no=?, vin=?, current_mileage=?, fuel_type=? WHERE id=? AND user_id=?";
      db()->prepare($sql)->execute([$data['make'],$data['model'],$data['year'],$data['reg_no'],$data['vin'],$data['current_mileage'],$data['fuel_type'],$id,$user['id']]);
    } else {
      $sql = "INSERT INTO vehicles (user_id,make,model,year,reg_no,vin,current_mileage,fuel_type) VALUES (?,?,?,?,?,?,?,?)";
      db()->prepare($sql)->execute([$user['id'],$data['make'],$data['model'],$data['year'],$data['reg_no'],$data['vin'],$data['current_mileage'],$data['fuel_type']]);
    }
    header('Location: vehicles.php');
    exit;
  } else {
    $vehicle = array_merge($vehicle, $data);
  }
}

include __DIR__ . '/../templates/header.php';
?>
<h2><?= $id ? 'Edit Vehicle' : 'Add Vehicle' ?></h2>
<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?></div>
<?php endif; ?>
<form method="post" class="row g-3">
  <div class="col-md-4"><label class="form-label">Brand (Make)</label><input class="form-control" name="make" value="<?=htmlspecialchars($vehicle['make'])?>" required></div>
  <div class="col-md-4"><label class="form-label">Model</label><input class="form-control" name="model" value="<?=htmlspecialchars($vehicle['model'])?>" required></div>
  <div class="col-md-4"><label class="form-label">Year</label><input type="number" class="form-control" name="year" value="<?=htmlspecialchars($vehicle['year'])?>" required></div>
  <div class="col-md-4"><label class="form-label">Reg No</label><input class="form-control" name="reg_no" value="<?=htmlspecialchars($vehicle['reg_no'])?>" required></div>
  <div class="col-md-4"><label class="form-label">VIN (optional)</label><input class="form-control" name="vin" value="<?=htmlspecialchars($vehicle['vin'])?>"></div>
  <div class="col-md-4"><label class="form-label">Current Mileage</label><input type="number" class="form-control" name="current_mileage" value="<?=htmlspecialchars($vehicle['current_mileage'])?>" required></div>
  <div class="col-md-4"><label class="form-label">Fuel Type</label>
    <select class="form-select" name="fuel_type">
      <option value="">-- Select --</option>
      <option value="Petrol" <?=($vehicle['fuel_type']=='Petrol'?'selected':'')?>>Petrol</option>
      <option value="Diesel" <?=($vehicle['fuel_type']=='Diesel'?'selected':'')?>>Diesel</option>
      <option value="CNG/LPG" <?=($vehicle['fuel_type']=='CNG/LPG'?'selected':'')?>>CNG/LPG</option>
      <option value="Hybrid" <?=($vehicle['fuel_type']=='Hybrid'?'selected':'')?>>Hybrid</option>
      <option value="Electric" <?=($vehicle['fuel_type']=='Electric'?'selected':'')?>>Electric</option>
    </select>
  </div>
  <div class="col-12"><button class="btn btn-primary"><?= $id ? 'Update' : 'Save' ?></button>
  <a href="vehicles.php" class="btn btn-secondary">Cancel</a></div>
</form>
<?php include __DIR__ . '/../templates/footer.php'; ?>
