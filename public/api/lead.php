<?php

/**
 * Единый контроллер приёма заявок: POST /api/lead.php
 *
 * Принимает JSON с фронтенда, валидирует и раздаёт по API-модулям:
 *   - telegram.php -> уведомление в Telegram-бот
 *   - sheets.php   -> запись строки в Google Sheets (если настроено)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
    exit;
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server is not configured: api/config.php is missing (copy it from api/config.example.php).']);
    exit;
}
$config = require $configFile;

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body.']);
    exit;
}

$clean = static fn($v): string => trim(strip_tags((string)($v ?? '')));

$lead = [
    'name'      => mb_substr($clean($input['name'] ?? ''), 0, 100),
    'whatsapp'  => mb_substr($clean($input['whatsapp'] ?? ''), 0, 30),
    'goal'      => mb_substr($clean($input['goal'] ?? ''), 0, 200) ?: 'Консультация',
    'score'     => max(0, (int)($input['score'] ?? 0)),
    'discount'  => max(0, (int)($input['discount'] ?? 0)),
    'promoCode' => mb_substr($clean($input['promoCode'] ?? ''), 0, 50),
];

if ($lead['name'] === '' || $lead['whatsapp'] === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Name and WhatsApp are required fields.']);
    exit;
}

require __DIR__ . '/telegram.php';
require __DIR__ . '/sheets.php';

$telegramResult = send_to_telegram($lead, $config);
$sheetsResult   = send_to_google_sheets($lead, $config);

// Заявка считается доставленной, если сработал хотя бы один канал
$delivered = !empty($telegramResult['success']) || !empty($sheetsResult['success']);

http_response_code($delivered ? 200 : 502);
echo json_encode([
    'success'  => $delivered,
    'message'  => $delivered ? 'Lead processed successfully' : 'Failed to deliver lead to any channel',
    'telegram' => $telegramResult,
    'sheets'   => $sheetsResult,
], JSON_UNESCAPED_UNICODE);
