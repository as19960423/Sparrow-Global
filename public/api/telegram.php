<?php

/**
 * API-модуль: отправка заявки в Telegram-бот.
 * Вызывается только из контроллера lead.php.
 */

declare(strict_types=1);

/**
 * @param array $lead   ['name','whatsapp','goal','score','discount','promoCode']
 * @param array $config конфиг из config.php
 */
function send_to_telegram(array $lead, array $config): array
{
    $token  = trim($config['telegram_bot_token'] ?? '');
    $chatId = trim((string)($config['telegram_chat_id'] ?? ''));

    if ($token === '' || $chatId === '') {
        return ['success' => false, 'error' => 'Telegram is not configured (telegram_bot_token / telegram_chat_id).'];
    }

    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    $waDigits = preg_replace('/\D+/', '', $lead['whatsapp']);
    $hasScore = !empty($lead['score']) && (int)$lead['score'] > 0;

    $lines = [
        '🔥 <b>Новая заявка Global Sparrow!</b>',
        '',
        '👤 Имя: <b>' . $esc($lead['name']) . '</b>',
        '📱 WhatsApp: <a href="https://wa.me/' . $esc($waDigits) . '">' . $esc($lead['whatsapp']) . '</a>',
        '🎯 Цель: ' . $esc($lead['goal']),
    ];

    if ($hasScore) {
        $lines[] = '🎮 Результат в игре: <b>' . (int)$lead['score'] . ' очков</b>';
        $lines[] = '🎁 Скидка за игру: <b>' . (int)$lead['discount'] . '%</b>';
        $lines[] = '🎟️ Промокод: <code>' . $esc((string)$lead['promoCode']) . '</code>';
    }

    $date = new DateTime('now', new DateTimeZone('Asia/Almaty'));
    $lines[] = '';
    $lines[] = '📅 ' . $date->format('d.m.Y H:i');

    $payload = [
        'chat_id'                  => $chatId,
        'text'                     => implode("\n", $lines),
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ];

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => "cURL error: {$curlErr}"];
    }

    $body = json_decode($response, true);
    if ($httpCode !== 200 || empty($body['ok'])) {
        return [
            'success' => false,
            'error'   => $body['description'] ?? "Telegram API returned HTTP {$httpCode}",
        ];
    }

    return ['success' => true, 'message_id' => $body['result']['message_id'] ?? null];
}
