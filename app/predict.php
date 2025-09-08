<?php
// app/predict.php
// Helper functions to compute next-due mileage/date for each service type

require_once __DIR__ . '/db.php';

// ---- 1) Rules (keep in sync with dashboard/cron) ----
function cst_rules(): array {
  return [
    'Gear oil change'            => ['km' => 40000, 'days' => 720],
    'Brake fluid change'         => ['km' => 40000, 'days' => 730],
    'Engine coolant change'      => ['km' => 40000, 'days' => 730],
    'Engine coolant check'       => ['km' => 5000,  'days' => 90],
    'Brake pads check'           => ['km' => 10000, 'days' => 180],
    'Brake discs/rotors check'   => ['km' => 20000, 'days' => 365],
    'Tire rotation'              => ['km' => 8000,  'days' => 180],
    'Wheel alignment'            => ['km' => 10000, 'days' => 365],
    'Wheel balancing'            => ['km' => 10000, 'days' => 365],
    'Air filter change'          => ['km' => 15000, 'days' => 365],
    'Cabin filter change'        => ['km' => 15000, 'days' => 365],
    'Fuel filter change'         => ['km' => 30000, 'days' => 730],
    'Spark plug replacement'     => ['km' => 30000, 'days' => 730],
    'Battery check/replacement'  => ['km' => 20000, 'days' => 365],
    'Timing belt/chain check'    => ['km' => 50000, 'days' => 730],
    'Drive belt/serpentine belt check' => ['km' => 30000, 'days' => 365],
    'Complete suspension check'  => ['km' => 20000, 'days' => 365],
    'Shock absorbers/struts check' => ['km' => 30000, 'days' => 365],
    'AC system check'            => ['km' => 20000, 'days' => 365],
    'Lights & electrical check'  => ['km' => 0,     'days' => 180], // time-only
    'Exhaust system check'       => ['km' => 20000, 'days' => 365],
    'Windshield washer fluid refill' => ['km' => 0, 'days' => 90],
    'Power steering fluid check' => ['km' => 20000, 'days' => 365],
    'Tire check'                 => ['km' => 5000,  'days' => 90],
  ];
}

// Engine oil depends on oil type selected on the service form
function cst_oil_rules(): array {
  return [
    'Mineral'         => ['km' => 2500, 'days' => 90],
    'Semi synthetic'  => ['km' => 3500, 'days' => 180],
    'Full synthetic'  => ['km' => 5000, 'days' => 180],
    '_default'        => ['km' => 5000, 'days' => 180],
  ];
}

// ---- 2) Core calculator ----
function cst_next_due_from_last(int $lastMileage, ?DateTime $lastDate, int $vehOdo, int $intervalKm, int $intervalDays): array {
  // Next due mileage (if km-based rule exists)
  $nextMileage = null;
  $kmRemaining = null;
  if ($intervalKm > 0) {
    $nextMileage = $lastMileage + $intervalKm;
    $kmRemaining = max(0, $nextMileage - $vehOdo);
  }

  // Next due date (if time-based rule exists)
  $nextDate = null;
  $daysRemaining = null;
  if ($intervalDays > 0 && $lastDate) {
    $nextDate = (clone $lastDate)->modify('+' . $intervalDays . ' days');
    $today = new DateTime('today');
    $daysRemaining = (int)$today->diff($nextDate)->format('%r%a'); // negative if overdue
  }

  // Overdue flags (either condition)
  $overKm   = ($kmRemaining !== null && $kmRemaining <= 0);
  $overTime = ($daysRemaining !== null && $daysRemaining <= 0);
  $isDue    = $overKm || $overTime;

  return [
    'next_mileage'   => $nextMileage,     // int|null
    'km_remaining'   => $kmRemaining,     // int|null
    'next_date'      => $nextDate,        // DateTime|null
    'days_remaining' => $daysRemaining,   // int|null (negative means overdue)
    'is_due'         => $isDue,
    'due_by'         => $overKm && $overTime ? 'km+time' : ($overKm ? 'km' : ($overTime ? 'time' : null)),
  ];
}

// ---- 3) Fetch helpers ----
function cst_last_service_of_type(int $vehicleId, string $type): ?array {
  $st = db()->prepare("
    SELECT service_date, mileage
    FROM services
    WHERE vehicle_id=? AND type=?
    ORDER BY service_date DESC, id DESC
    LIMIT 1
  ");
  $st->execute([$vehicleId, $type]);
  $row = $st->fetch();
  if (!$row) return null;

  return [
    'mileage' => (int)$row['mileage'],
    'date'    => new DateTime($row['service_date']),
  ];
}

function cst_last_engine_oil(int $vehicleId): ?array {
  $st = db()->prepare("
    SELECT service_date, mileage, oil_type
    FROM services
    WHERE vehicle_id=? AND type='Engine oil change'
    ORDER BY service_date DESC, id DESC
    LIMIT 1
  ");
  $st->execute([$vehicleId]);
  $row = $st->fetch();
  if (!$row) return null;

  return [
    'mileage'  => (int)$row['mileage'],
    'date'     => new DateTime($row['service_date']),
    'oil_type' => $row['oil_type'] ?? null,
  ];
}

// ---- 4) Public API: predictions for one vehicle ----
// Returns an array of rows like:
// [
//   'type' => 'Air filter change',
//   'next_mileage' => 25000|null,
//   'km_remaining' => 1200|null,
//   'next_date'    => DateTime|null,
//   'days_remaining'=> 45|null,
//   'is_due'       => bool,
//   'due_by'       => 'km'|'time'|'km+time'|null,
//   'note'         => 'based on last ...' (optional string)
// ]
function cst_predict_for_vehicle(int $vehicleId): array {
  // Load current odometer & reg for context (optional)
  $vs = db()->prepare("SELECT reg_no, current_mileage FROM vehicles WHERE id=?");
  $vs->execute([$vehicleId]);
  $veh = $vs->fetch();
  if (!$veh) return [];

  $vehOdo = (int)$veh['current_mileage'];

  $rules     = cst_rules();
  $oilRules  = cst_oil_rules();
  $result    = [];

  // 4A) Engine oil (special handling by oil_type)
  if ($oil = cst_last_engine_oil($vehicleId)) {
    $oilType = $oil['oil_type'] ?? null;
    $rule    = $oilRules[$oilType] ?? $oilRules['_default'];
    $calc    = cst_next_due_from_last($oil['mileage'], $oil['date'], $vehOdo, $rule['km'], $rule['days']);
    $result[] = array_merge(
      ['type' => 'Engine oil change' . ($oilType ? " ({$oilType})" : '')],
      $calc
    );
  }

  // 4B) All other rule-based services
  foreach ($rules as $type => $rule) {
    $last = cst_last_service_of_type($vehicleId, $type);
    if (!$last) continue; // no history yet → we cannot predict
    $calc = cst_next_due_from_last($last['mileage'], $last['date'], $vehOdo, $rule['km'], $rule['days']);
    $result[] = array_merge(['type' => $type], $calc);
  }

  return $result;
}

// ---- 5) Public API: predictions for all vehicles of a user ----
function cst_predict_for_user(int $userId): array {
  $vstmt = db()->prepare("SELECT id, reg_no FROM vehicles WHERE user_id=? ORDER BY id ASC");
  $vstmt->execute([$userId]);
  $vehicles = $vstmt->fetchAll();

  $out = [];
  foreach ($vehicles as $v) {
    $vehId = (int)$v['id'];
    $out[] = [
      'vehicle_id' => $vehId,
      'reg_no'     => $v['reg_no'],
      'predictions'=> cst_predict_for_vehicle($vehId),
    ];
  }
  return $out;
}
