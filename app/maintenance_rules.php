<?php
declare(strict_types=1);

/**
 * Centralized service rules + helpers.
 * Usage in pages: require_once __DIR__ . '/../app/maintenance_rules.php';
 */

function maintenance_rules(): array {
    return [
        'Gear oil change'                    => ['km' => 40000, 'days' => 720],
        'Brake fluid change'                 => ['km' => 40000, 'days' => 730],
        'Engine coolant change'              => ['km' => 40000, 'days' => 730],
        'Engine coolant check'               => ['km' => 5000,  'days' => 90],
        'Brake pads check'                   => ['km' => 10000, 'days' => 180],
        'Brake discs/rotors check'           => ['km' => 20000, 'days' => 365],
        'Tire rotation'                      => ['km' => 8000,  'days' => 180],
        'Wheel alignment'                    => ['km' => 10000, 'days' => 365],
        'Wheel balancing'                    => ['km' => 10000, 'days' => 365],
        'Air filter change'                  => ['km' => 15000, 'days' => 365],
        'Cabin filter change'                => ['km' => 15000, 'days' => 365],
        'Fuel filter change'                 => ['km' => 30000, 'days' => 730],
        'Spark plug replacement'             => ['km' => 30000, 'days' => 730],
        'Battery check/replacement'          => ['km' => 20000, 'days' => 365],
        'Timing belt/chain check'            => ['km' => 50000, 'days' => 730],
        'Drive belt/serpentine belt check'   => ['km' => 30000, 'days' => 365],
        'Complete suspension check'          => ['km' => 20000, 'days' => 365],
        'Shock absorbers/struts check'       => ['km' => 30000, 'days' => 365],
        'AC system check'                    => ['km' => 20000, 'days' => 365],
        'Lights & electrical check'          => ['km' => 0,     'days' => 180], // time-only
        'Exhaust system check'               => ['km' => 20000, 'days' => 365],
        'Windshield washer fluid refill'     => ['km' => 0,     'days' => 90],  // time-only
        'Power steering fluid check'         => ['km' => 20000, 'days' => 365],
        'Tire check'                         => ['km' => 5000,  'days' => 90],
    ];
}

function oil_rules(): array {
    return [
        'Mineral'        => ['km' => 2500, 'days' => 90],
        'Semi synthetic' => ['km' => 3500, 'days' => 180],
        'Full synthetic' => ['km' => 5000, 'days' => 180],
        '_default'       => ['km' => 5000, 'days' => 180],
    ];
}

/**
 * Returns true if due by KM or by days. Either threshold triggers a due state.
 */
function km_or_days_due(?int $sinceKm, ?int $sinceDays, int $limitKm, int $limitDays): bool {
    $kmDue   = ($limitKm  > 0 && $sinceKm   !== null && $sinceKm   >= $limitKm);
    $timeDue = ($limitDays> 0 && $sinceDays !== null && $sinceDays >= $limitDays);
    return $kmDue || $timeDue;
}

/**
 * Optional helper: compute how much is left until due (or 0 if past).
 * Returns ['km_to_due'=>int|PHP_INT_MAX, 'days_to_due'=>int|PHP_INT_MAX]
 */
function remaining_until_due(?int $sinceKm, ?int $sinceDays, int $limitKm, int $limitDays): array {
    $kmToDue   = ($limitKm  > 0 && $sinceKm   !== null) ? max(0, $limitKm  - $sinceKm)   : PHP_INT_MAX;
    $daysToDue = ($limitDays> 0 && $sinceDays !== null) ? max(0, $limitDays- $sinceDays) : PHP_INT_MAX;
    return ['km_to_due' => $kmToDue, 'days_to_due' => $daysToDue];
}
