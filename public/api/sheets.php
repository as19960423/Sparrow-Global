<?php

/**
 * API-модуль: запись заявки в Google Sheets через веб-приложение Apps Script.
 * Вызывается только из контроллера lead.php.
 * Код самого макроса — в google-apps-script/Code.gs (см. README).
 */

declare(strict_types=1);

/**
 * @param array $lead   ['name','whatsapp','goal','score','discount','promoCode']
 * @param array $config конфиг из config.php
 */
function send_to_google_sheets(array $lead, array $config): array
{
    $url = trim($config['sheets_webapp_url'] ?? '');

    if ($url === '') {
        return ['success' => false, 'skipped' => true, 'error' => 'Google Sheets is not configured (sheets_webapp_url).'];
    }

    $payload = json_encode([
        'name'      => $lead['name'],
        'whatsapp'  => $lead['whatsapp'],
        'goal'      => $lead['goal'],
        'score'     => (int)($lead['score'] ?? 0),
        'discount'  => (int)($lead['discount'] ?? 0),
        'promoCode' => (string)($lead['promoCode'] ?? ''),
        'timestamp' => (new DateTime('now', new DateTimeZone('Asia/Almaty')))->format('d.m.Y H:i:s'),
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        // Apps Script отвечает редиректом на script.googleusercontent.com — его нужно пройти
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => "cURL error: {$curlErr}"];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return ['success' => false, 'error' => "Apps Script returned HTTP {$httpCode}"];
    }

    return ['success' => true];
}
