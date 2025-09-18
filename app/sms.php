<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function normalize_bd_number(string $phone): string {
    $d = preg_replace('/\D+/', '', $phone);
    if (strpos($d, '8801') === 0) return $d;
    if (strpos($d, '01') === 0) return '88'.$d;
    if (strpos($phone, '+8801') === 0) return '88'.substr($d, 1);
    if (strpos($d, '88') === 0) return $d;
    return '88'.$d;
}

function sms_already_sent_recently(int $userId, ?int $vehicleId, ?string $serviceName): bool {
    $stmt = db()->prepare("
        SELECT 1 FROM sms_logs
        WHERE user_id=? AND (vehicle_id <=> ?) AND (service_name <=> ?)
          AND created_at >= (NOW() - INTERVAL 24 HOUR)
          AND status='sent'
        LIMIT 1
    ");
    $stmt->execute([$userId, $vehicleId, $serviceName]);
    return (bool)$stmt->fetchColumn();
}

function send_sms_bulksmsbd(string $phone, string $message): array {
    if (!BULKSMSBD_API_KEY || !BULKSMSBD_SENDER_ID) {
        return [false, json_encode(['error' => 'BulkSMSBD credentials missing'])];
    }

    $number = normalize_bd_number($phone);

    $payload = http_build_query([
        'api_key'  => BULKSMSBD_API_KEY,
        'type'     => 'text',
        'number'   => $number,
        'senderid' => BULKSMSBD_SENDER_ID,
        'message'  => $message,
    ]);

    $ch = curl_init(BULKSMSBD_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err) return [false, json_encode(['curl_error' => $err])];

    // Success = HTTP 200 + contains "202" (per docs)
    $ok = ($code === 200 && strpos($resp, '202') !== false);

    return [$ok, $resp ?: ''];
}

function log_sms(int $userId, ?int $vehicleId, ?string $serviceName, string $message, string $status, string $providerResponse): void {
    $message = mb_substr($message, 0, 480);
    db()->prepare("
        INSERT INTO sms_logs (user_id, vehicle_id, service_name, message, status, provider, provider_response)
        VALUES (?,?,?,?,?, 'bulksmsbd', ?)
    ")->execute([$userId, $vehicleId, $serviceName, $message, $status, $providerResponse]);
}

function send_overdue_sms_if_allowed(array $user, ?int $vehicleId, ?string $serviceName, string $message): bool {
    if (empty($user['sms_opt_in']) || empty($user['phone_number'])) return false;
    if (sms_already_sent_recently((int)$user['id'], $vehicleId, $serviceName)) return false;

    [$ok, $resp] = send_sms_bulksmsbd($user['phone_number'], $message);
    log_sms((int)$user['id'], $vehicleId, $serviceName, $message, $ok ? 'sent' : 'failed', $resp);
    return $ok;
}
