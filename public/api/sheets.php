<?php

/**
 * API-модуль: запись заявки напрямую в Google Sheets (Sheets API v4).
 * Вызывается только из контроллера lead.php.
 *
 * Авторизация — сервисный аккаунт Google Cloud (JSON-ключ в api/google-credentials.json,
 * закрыт от веба и git). Таблица должна быть расшарена на email сервисного аккаунта
 * с правами «Редактор». Инструкция по настройке — в README.md.
 */

declare(strict_types=1);

/**
 * @param array $lead   ['name','whatsapp','goal','score','discount','promoCode']
 * @param array $config конфиг из config.php
 */
function send_to_google_sheets(array $lead, array $config): array
{
    $spreadsheetId = trim($config['sheets_spreadsheet_id'] ?? '');
    $sheetName     = trim($config['sheets_sheet_name'] ?? '') ?: 'Заявки';
    $credFile      = $config['sheets_credentials_file'] ?? (__DIR__ . '/google-credentials.json');

    if ($spreadsheetId === '') {
        return ['success' => false, 'skipped' => true, 'error' => 'Google Sheets is not configured (sheets_spreadsheet_id).'];
    }
    if (!is_file($credFile)) {
        return ['success' => false, 'error' => 'Service account key not found: api/google-credentials.json'];
    }

    $creds = json_decode((string)file_get_contents($credFile), true);
    if (empty($creds['client_email']) || empty($creds['private_key'])) {
        return ['success' => false, 'error' => 'Invalid service account JSON (client_email / private_key missing).'];
    }

    $auth = google_sheets_access_token($creds);
    if (isset($auth['error'])) {
        return ['success' => false, 'error' => 'Google auth failed: ' . $auth['error']];
    }
    $token = $auth['token'];

    $range = rawurlencode("'{$sheetName}'!A1");
    $base  = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}";

    // Если лист пустой — сначала добавляем строку-шапку
    $rows = [];
    $check = google_sheets_request('GET', $base, $token);
    if (isset($check['error'])) {
        return ['success' => false, 'error' => 'Google Sheets read failed: ' . $check['error']];
    }
    if (empty($check['body']['values'])) {
        $rows[] = ['Дата', 'Имя', 'WhatsApp', 'Цель', 'Очки в игре', 'Скидка %', 'Промокод'];
    }

    $rows[] = [
        (new DateTime('now', new DateTimeZone('Asia/Almaty')))->format('d.m.Y H:i:s'),
        $lead['name'],
        $lead['whatsapp'],
        $lead['goal'],
        (int)($lead['score'] ?? 0),
        (int)($lead['discount'] ?? 0),
        (string)($lead['promoCode'] ?? ''),
    ];

    $append = google_sheets_request(
        'POST',
        "{$base}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS",
        $token,
        ['values' => $rows]
    );
    if (isset($append['error'])) {
        return ['success' => false, 'error' => 'Google Sheets append failed: ' . $append['error']];
    }

    return ['success' => true, 'updatedRange' => $append['body']['updates']['updatedRange'] ?? null];
}

/**
 * OAuth2-токен сервисного аккаунта (JWT RS256 -> access_token), без сторонних библиотек.
 */
function google_sheets_access_token(array $creds): array
{
    $b64 = static fn(string $d): string => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

    $tokenUri = $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token';
    $now = time();

    $header = $b64((string)json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = $b64((string)json_encode([
        'iss'   => $creds['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud'   => $tokenUri,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    if (!openssl_sign("{$header}.{$claims}", $signature, $creds['private_key'], OPENSSL_ALGO_SHA256)) {
        return ['error' => 'openssl_sign failed (check private_key in credentials JSON)'];
    }
    $jwt = "{$header}.{$claims}." . $b64($signature);

    $ch = curl_init($tokenUri);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['error' => "cURL error: {$curlErr}"];
    }

    $body = json_decode($response, true);
    if (empty($body['access_token'])) {
        return ['error' => $body['error_description'] ?? $body['error'] ?? 'No access_token in response'];
    }

    return ['token' => $body['access_token']];
}

/**
 * Запрос к Sheets API. Возвращает ['body' => array] либо ['error' => string].
 */
function google_sheets_request(string $method, string $url, string $token, ?array $json = null): array
{
    $headers = ["Authorization: Bearer {$token}"];
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ];
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE);
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;

    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['error' => "cURL error: {$curlErr}"];
    }

    $body = json_decode($response, true) ?? [];
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['error' => $body['error']['message'] ?? "HTTP {$httpCode}"];
    }

    return ['body' => $body];
}
