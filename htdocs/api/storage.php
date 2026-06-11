<?php

/**
 * API-модуль: автоматическое сохранение заявок в таблицу на сервере.
 * Вызывается только из контроллера lead.php.
 *
 * Заявки дописываются в storage/leads.csv (UTF-8 с BOM — открывается
 * в Excel/Numbers/Google Sheets без кракозябр). Папка storage лежит
 * ВЫШЕ htdocs, поэтому снаружи недоступна и не затирается билдом.
 * Просмотр в браузере: /api/leads.php?key=<admin_key из config.php>
 */

declare(strict_types=1);

const LEADS_CSV_COLUMNS = ['Дата', 'Имя', 'WhatsApp', 'Цель', 'Очки в игре', 'Скидка %', 'Промокод', 'IP'];

function leads_csv_path(): string
{
    return dirname(__DIR__, 2) . '/storage/leads.csv';
}

/**
 * @param array $lead ['name','whatsapp','goal','score','discount','promoCode']
 */
function save_lead_to_storage(array $lead): array
{
    $file = leads_csv_path();
    $dir  = dirname($file);

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['success' => false, 'error' => "Cannot create storage directory: {$dir}"];
    }

    $isNew = !is_file($file);

    $fh = fopen($file, 'a');
    if ($fh === false) {
        return ['success' => false, 'error' => "Cannot open {$file} for writing (check permissions)"];
    }

    $row = [
        (new DateTime('now', new DateTimeZone('Asia/Almaty')))->format('d.m.Y H:i:s'),
        $lead['name'],
        $lead['whatsapp'],
        $lead['goal'],
        (int)($lead['score'] ?? 0),
        (int)($lead['discount'] ?? 0),
        (string)($lead['promoCode'] ?? ''),
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    flock($fh, LOCK_EX);
    if ($isNew) {
        fwrite($fh, "\xEF\xBB\xBF"); // BOM для Excel
        fputcsv($fh, LEADS_CSV_COLUMNS, ';', '"', '');
    }
    $written = fputcsv($fh, $row, ';', '"', '');
    flock($fh, LOCK_UN);
    fclose($fh);

    if ($written === false) {
        return ['success' => false, 'error' => 'Failed to write lead row'];
    }

    return ['success' => true, 'file' => basename($file)];
}
