<?php
// app/cron/send_reminders.php
// Run from CLI: php app/cron/send_reminders.php

require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/mail.php';

date_default_timezone_set('Asia/Dhaka');

// === Config (edit if you like) ===
$REMINDER_LOOKBACK_DAYS = 0; // re-send every run (no cooldown)

// Default rules for service "types" in your dropdown (km OR days)
$RULES = [
  'Gear oil change'            => ['km' => 40000, 'days' => 720], // 2 years
  'Brake fluid change'         => ['km' => 40000, 'days' => 730], // ~2 years
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
  'Lights & electrical check'  => ['km' => 0,     'days' => 180], // time-based
  'Exhaust system check'       => ['km' => 20000, 'days' => 365],
  'Windshield washer fluid refill' => ['km' => 0, 'days' => 90], // time-based
  'Power steering fluid check' => ['km' => 20000, 'days' => 365],
  'Tire check'                 => ['km' => 5000,  'days' => 90],
];

// Special case — Engine oil change depends on oil_type
$OIL_RULES = [
  'Mineral'         => ['km' => 2500, 'days' => 90],   // 3 months or 2,500 km
  'Semi synthetic'  => ['km' => 3500, 'days' => 180],  // 6 months or 3,500 km
  'Full synthetic'  => ['km' => 5000, 'days' => 180],  // 6 months or 5,000 km
  '_default'        => ['km' => 5000, 'days' => 180],  // fallback if oil_type missing
];

// ---- helpers ----
function already_sent_recently(int $userId, int $vehicleId, string $ruleKey, int $lookbackDays): bool {
  $q = db()->prepare("SELECT 1 FROM reminder_log
                      WHERE user_id=? AND vehicle_id=? AND rule_key=?
                        AND sent_at >= (NOW() - INTERVAL ? DAY)
                      LIMIT 1");
  $q->execute([$userId, $vehicleId, $ruleKey, $lookbackDays]);
  return (bool)$q->fetchColumn();
}

function log_sent(int $userId, int $vehicleId, string $ruleKey): void {
  db()->prepare("INSERT INTO reminder_log (user_id, vehicle_id, rule_key, sent_at)
                 VALUES (?,?,?, NOW())")->execute([$userId, $vehicleId, $ruleKey]);
}

function km_or_days_due(?int $sinceKm, ?int $sinceDays, int $limitKm, int $limitDays): bool {
  $kmDue   = ($limitKm  > 0 && $sinceKm  !== null && $sinceKm  >= $limitKm);
  $timeDue = ($limitDays> 0 && $sinceDays!== null && $sinceDays>= $limitDays);
  return $kmDue || $timeDue;
}

// ---- fetch verified users ----
$users = db()->query("SELECT id, name, email FROM users WHERE email_verified=1")->fetchAll();
if (!$users) {
  echo "[info] no verified users.\n";
  exit(0);
}

$today = new DateTime();
$totalEmails = 0;

foreach ($users as $u) {
  $userId = (int)$u['id'];

  // vehicles owned by user
  $vstmt = db()->prepare("SELECT id, reg_no, current_mileage FROM vehicles WHERE user_id=? ORDER BY id ASC");
  $vstmt->execute([$userId]);
  $vehicles = $vstmt->fetchAll();
  if (!$vehicles) continue;

  foreach ($vehicles as $v) {
    $vehicleId = (int)$v['id'];
    $reg = $v['reg_no'];
    $vehOdo = (int)$v['current_mileage'];

    // 1) Engine oil change — dynamic by oil_type (use most recent oil change)
    $lastOil = db()->prepare("
      SELECT service_date, mileage, oil_type
      FROM services
      WHERE vehicle_id=? AND type='Engine oil change'
      ORDER BY service_date DESC, id DESC
      LIMIT 1
    ");
    $lastOil->execute([$vehicleId]);
    $oil = $lastOil->fetch();

    if ($oil) {
      $oilType = $oil['oil_type'] ?? null;
      $rule = $OIL_RULES[$oilType] ?? $OIL_RULES['_default'];

      $lastMileage = (int)$oil['mileage'];
      $lastDate    = new DateTime($oil['service_date']);
      $sinceKm     = max(0, $vehOdo - $lastMileage);
      $sinceDays   = $today->diff($lastDate)->days;

      $ruleKey = 'engine_oil'; // for reminder_log

      if (km_or_days_due($sinceKm, $sinceDays, $rule['km'], $rule['days'])) {
        if (!already_sent_recently($userId, $vehicleId, $ruleKey, $REMINDER_LOOKBACK_DAYS)) {
          // SAFE subject line (ASCII hyphen)
          $subject = "Engine oil change due - {$reg}";
          $html = "<p>Hi ".htmlspecialchars($u['name']).",</p>
                   <p><strong>{$reg}</strong> is due for an <strong>Engine oil change</strong>.</p>
                   <ul>
                     <li>Oil type: <strong>".htmlspecialchars($oilType ?: 'Unknown')."</strong></li>
                     <li>Since last change: <strong>{$sinceKm} km</strong>, <strong>{$sinceDays} days</strong></li>
                     <li>Recommended: <strong>{$rule['km']} km or {$rule['days']} days</strong></li>
                   </ul>
                   <p>You can log the service here:
                     <a href=\"".rtrim(BASE_URL ?? '', '/')."/public/service_form.php?vehicle_id={$vehicleId}\">Add service</a>
                   </p>";
          $sent = send_mail($u['email'], $u['name'], $subject, $html);
          if ($sent === true) {
            log_sent($userId, $vehicleId, $ruleKey);
            $totalEmails++;
            echo "[sent] {$u['email']} {$reg} engine_oil\n";
          } else {
            echo "[mail error] {$u['email']} {$reg} engine_oil: {$sent}\n";
          }
        }
      }
    }
    // If no oil change ever recorded, we skip emailing to avoid noise.

    // 2) General rules for every other service type in $RULES
    foreach ($RULES as $type => $rule) {
      // fetch most recent service of this type
      $st = db()->prepare("
        SELECT service_date, mileage
        FROM services
        WHERE vehicle_id=? AND type=?
        ORDER BY service_date DESC, id DESC
        LIMIT 1
      ");
      $st->execute([$vehicleId, $type]);
      $row = $st->fetch();
      if (!$row) continue; // no record yet → don't email

      $lastMileage = (int)$row['mileage'];
      $lastDate    = new DateTime($row['service_date']);
      $sinceKm     = max(0, $vehOdo - $lastMileage);
      $sinceDays   = $today->diff($lastDate)->days;

      $ruleKey = strtolower(preg_replace('/[^a-z0-9]+/i','_', $type)); // e.g., 'air_filter_change'

      if (km_or_days_due($sinceKm, $sinceDays, $rule['km'], $rule['days'])) {
        if (!already_sent_recently($userId, $vehicleId, $ruleKey, $REMINDER_LOOKBACK_DAYS)) {
          // SAFE subject line (ASCII hyphen)
          $subject = "{$type} due - {$reg}";
          $html = "<p>Hi ".htmlspecialchars($u['name']).",</p>
                   <p><strong>{$reg}</strong> is due for <strong>".htmlspecialchars($type)."</strong>.</p>
                   <ul>
                     <li>Since last: <strong>{$sinceKm} km</strong>, <strong>{$sinceDays} days</strong></li>
                     <li>Recommended: <strong>{$rule['km']} km or {$rule['days']} days</strong></li>
                   </ul>
                   <p>Log it here:
                     <a href=\"".rtrim(BASE_URL ?? '', '/')."/public/service_form.php?vehicle_id={$vehicleId}\">Add service</a>
                   </p>";
          $sent = send_mail($u['email'], $u['name'], $subject, $html);
          if ($sent === true) {
            log_sent($userId, $vehicleId, $ruleKey);
            $totalEmails++;
            echo "[sent] {$u['email']} {$reg} {$ruleKey}\n";
          } else {
            echo "[mail error] {$u['email']} {$reg} {$ruleKey}: {$sent}\n";
          }
        }
      }
    }
  }
}

echo "[done] total emails sent: {$totalEmails}\n";
