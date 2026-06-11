<?php

/**
 * API-модуль: запись заявки в Google Sheets через Google Forms.
 * Вызывается только из контроллера lead.php.
 *
 * Как это работает: создаётся Google Форма, привязанная к таблице
 * («Ответы» -> значок Sheets). PHP отправляет POST прямо в форму —
 * ответ автоматически появляется строкой в таблице. Без Apps Script и API-ключей.
 */

declare(strict_types=1);

/**
 * @param array $lead   ['name','whatsapp','goal','score','discount','promoCode']
 * @param array $config конфиг из config.php
 */
function send_to_google_sheets(array $lead, array $config): array
{
    $formUrl = trim($config['google_form_url'] ?? '');
    $fields  = $config['google_form_fields'] ?? [];

    if ($formUrl === '' || !is_array($fields) || $fields === []) {
        return ['success' => false, 'skipped' => true, 'error' => 'Google Forms is not configured (google_form_url / google_form_fields).'];
    }

    // Приводим ссылку к виду .../formResponse (принимает и viewform-ссылку)
    $formUrl = preg_replace('#/(viewform|prefill).*$#', '/formResponse', $formUrl);
    if (!str_ends_with($formUrl, '/formResponse')) {
        $formUrl = rtrim($formUrl, '/') . '/formResponse';
    }

    $values = [
        'name'      => $lead['name'],
        'whatsapp'  => $lead['whatsapp'],
        'goal'      => $lead['goal'],
        'score'     => (string)(int)($lead['score'] ?? 0),
        'discount'  => (string)(int)($lead['discount'] ?? 0),
        'promoCode' => (string)($lead['promoCode'] ?? ''),
        'timestamp' => (new DateTime('now', new DateTimeZone('Asia/Almaty')))->format('d.m.Y H:i:s'),
    ];

    $payload = [];
    foreach ($fields as $key => $entryId) {
        if ($entryId !== '' && isset($values[$key])) {
            $payload[$entryId] = $values[$key];
        }
    }

    if ($payload === []) {
        return ['success' => false, 'error' => 'google_form_fields has no valid entry IDs.'];
    }

    $ch = curl_init($formUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
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

    // Успешная отправка отдаёт 200 со страницей "Ответ записан".
    // 400/422 обычно значит неверные entry-ID или обязательное поле не заполнено.
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['success' => false, 'error' => "Google Forms returned HTTP {$httpCode} (check entry IDs and required fields)"];
    }

    return ['success' => true];
}
