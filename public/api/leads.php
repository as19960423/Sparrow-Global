<?php

/**
 * Просмотр сохранённых заявок (таблица в браузере + выгрузка CSV).
 *
 * Доступ:  /api/leads.php?key=<admin_key из config.php>
 * Выгрузка: /api/leads.php?key=...&format=csv
 */

declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? require $configFile : [];

$adminKey = trim((string)($config['admin_key'] ?? ''));
if ($adminKey === '' || !hash_equals($adminKey, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

require __DIR__ . '/storage.php';
$file = leads_csv_path();

// Выгрузка сырого CSV (открывается в Excel / импортируется в Google Sheets)
if (($_GET['format'] ?? '') === 'csv') {
    if (!is_file($file)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No leads yet';
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sparrow-leads.csv"');
    readfile($file);
    exit;
}

// Читаем CSV в массив строк
$rows = [];
if (is_file($file) && ($fh = fopen($file, 'r')) !== false) {
    flock($fh, LOCK_SH);
    // пропускаем BOM
    if (fread($fh, 3) !== "\xEF\xBB\xBF") {
        rewind($fh);
    }
    while (($row = fgetcsv($fh, 0, ';', '"', '')) !== false) {
        if ($row !== [null]) {
            $rows[] = $row;
        }
    }
    flock($fh, LOCK_UN);
    fclose($fh);
}

$header = $rows ? array_shift($rows) : LEADS_CSV_COLUMNS;
$rows = array_reverse($rows); // свежие сверху
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Заявки — Global Sparrow</title>
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; margin: 0; background: #f6f7f9; color: #1e293b; }
    .wrap { max-width: 1200px; margin: 0 auto; padding: 32px 16px; }
    .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
    h1 { font-size: 20px; margin: 0; }
    .count { color: #64748b; font-size: 13px; }
    a.btn { background: #0d9488; color: #fff; text-decoration: none; padding: 9px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; }
    .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: auto; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
    table { border-collapse: collapse; width: 100%; font-size: 13px; min-width: 760px; }
    th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
    th { background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; position: sticky; top: 0; }
    tr:hover td { background: #fafcff; }
    td.empty { text-align: center; color: #94a3b8; padding: 48px 14px; }
    .promo { font-family: ui-monospace, monospace; color: #0d9488; font-weight: 600; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <h1>Заявки Global Sparrow <span class="count">(всего: <?= count($rows) ?>)</span></h1>
      <a class="btn" href="?key=<?= $e($_GET['key']) ?>&amp;format=csv">Скачать CSV</a>
    </div>
    <div class="card">
      <table>
        <thead>
          <tr><?php foreach ($header as $h): ?><th><?= $e($h) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td class="empty" colspan="<?= count($header) ?>">Заявок пока нет</td></tr>
          <?php else: foreach ($rows as $row): ?>
            <tr>
              <?php foreach ($row as $i => $cell): ?>
                <td<?= ($header[$i] ?? '') === 'Промокод' ? ' class="promo"' : '' ?>><?= $e($cell) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
