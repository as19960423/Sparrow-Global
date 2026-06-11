<?php

/**
 * Конфигурация API Global Sparrow.
 *
 * 1. Скопируйте этот файл в config.php (рядом, в той же папке api/).
 * 2. Заполните значения ниже.
 *
 * config.php добавлен в .gitignore и закрыт от внешнего доступа через .htaccess —
 * секреты не попадут ни в git, ни наружу.
 */

return [
    // Токен бота. Получить: написать @BotFather в Telegram -> /newbot
    'telegram_bot_token' => '',

    // ID чата, куда слать заявки (личка, группа или канал).
    // Узнать свой ID: написать боту @userinfobot.
    // Для группы: добавьте бота в группу и посмотрите chat.id в
    // https://api.telegram.org/bot<ТОКЕН>/getUpdates (у групп ID отрицательный).
    'telegram_chat_id' => '',

    // URL веб-приложения Google Apps Script (опционально).
    // Оставьте пустым, чтобы отключить запись в Google Sheets.
    // Инструкция по настройке — в README.md, код макроса — в google-apps-script/Code.gs
    'sheets_webapp_url' => '',
];
