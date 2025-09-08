<?php
// public/export_services.php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

$user = current_user();
$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;

// Verify access to the vehicle if a filter is provided
if ($vehicleId) {
  $own = db()->prepare("SELECT id FROM vehicles WHERE id=? AND user_id=?");
  $own->execute([$vehicleId, $user['id']]);
  if (!$own->fetch()) $vehicleId = 0; // reset if not owned
}

// Build query
if ($vehicleId) {
  $q = db()->prepare("SELECT s.service_date, v.reg_no, s.type, s.oil_type, s.mileage, s.cost, s.notes
                      FROM services s
                      JOIN vehicles v ON v.id = s.vehicle_id
                      WHERE v.user_id=? AND v.id=?
                      ORDER BY s.service_date DESC, s.id DESC");
  $q->execute([$user['id'], $vehicleId]);
} else {
  $q = db()->prepare("SELECT s.service_date, v.reg_no, s.type, s.oil_type, s.mileage, s.cost, s.notes
                      FROM services s
                      JOIN vehicles v ON v.id = s.vehicle_id
                      WHERE v.user_id=?
                      ORDER BY s.service_date DESC, s.id DESC");
  $q->execute([$user['id']]);
}
$rows = $q->fetchAll();

// CSV headers
header('Content-Type: text/csv; charset=UTF-8');
$fname = 'service_history' . ($vehicleId ? "_veh{$vehicleId}" : '') . '.csv';
header('Content-Disposition: attachment; filename="'.$fname.'"');

// Output CSV (with UTF-8 BOM so Excel shows characters correctly)
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['Date','Vehicle','Type','Oil Type','Mileage','Cost','Notes']);

foreach ($rows as $r) {
  fputcsv($out, [
    $r['service_date'],
    $r['reg_no'],
    $r['type'],
    $r['oil_type'] ?? '',
    (int)$r['mileage'],
    number_format((float)$r['cost'], 2, '.', ''),
    // keep notes as-is (no HTML), limit linebreak weirdness
    str_replace(["\r","\n"], [' ',' '], (string)$r['notes'])
  ]);
}
fclose($out);
exit;
