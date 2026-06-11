<?php

/**
 * Конфигурация API Global Sparrow.
 *
 * 1. Скопируйте этот файл в config.php (рядом, в той же папке api/).
 * 2. Заполните значения ниже.
 *
 * config.php и google-credentials.json добавлены в .gitignore и закрыты
 * от внешнего доступа через .htaccess — секреты не попадут ни в git, ни наружу.
 */

return [
    // --- Telegram ---

    // Токен бота. Получить: написать @BotFather в Telegram -> /newbot
    'telegram_bot_token' => '',

    // ID чата, куда слать заявки (личка, группа или канал).
    // Узнать: напишите боту /start, затем откройте
    // https://api.telegram.org/bot<ТОКЕН>/getUpdates и возьмите chat.id
    // (у групп ID отрицательный).
    'telegram_chat_id' => '',

    // --- Google Sheets (запись напрямую через Sheets API) ---
    // Настройка сервисного аккаунта — см. README.md.
    // Пусто = модуль выключен, заявки идут в Telegram и локальный CSV.

    // ID таблицы — кусок между /d/ и /edit в её URL:
    // https://docs.google.com/spreadsheets/d/<ВОТ_ЭТО>/edit
    'sheets_spreadsheet_id' => '',

    // Название листа, куда писать заявки (создайте лист с таким именем)
    'sheets_sheet_name' => 'Заявки',

    // JSON-ключ сервисного аккаунта (положите файл рядом, в папку api/)
    'sheets_credentials_file' => __DIR__ . '/google-credentials.json',

    // --- Резервная таблица на сервере ---

    // Секретный ключ для просмотра заявок: /api/leads.php?key=<admin_key>
    // Пусто = страница просмотра отключена (CSV всё равно пишется).
    'admin_key' => '',
];
