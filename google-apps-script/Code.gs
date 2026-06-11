/**
 * Global Sparrow — приём заявок в Google Sheets.
 *
 * Установка (один раз):
 * 1. Создайте Google Таблицу для заявок.
 * 2. Меню: Расширения -> Apps Script. Вставьте этот код вместо содержимого Code.gs.
 * 3. Нажмите "Развернуть" -> "Новое развёртывание" -> тип "Веб-приложение":
 *      - Запуск от имени: "От моего имени"
 *      - Доступ: "Все" (Anyone)
 * 4. Скопируйте URL веб-приложения (вида https://script.google.com/macros/s/.../exec)
 *    и вставьте его в api/config.php -> 'sheets_webapp_url'.
 *
 * При изменении кода нужно создавать НОВОЕ развёртывание (или обновлять версию).
 */

var SHEET_NAME = 'Заявки';

function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.tryLock(10000);

  try {
    var data = JSON.parse(e.postData.contents);

    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName(SHEET_NAME);

    // Создаём лист с шапкой при первом запуске
    if (!sheet) {
      sheet = ss.insertSheet(SHEET_NAME);
      sheet.appendRow(['Дата', 'Имя', 'WhatsApp', 'Цель', 'Очки в игре', 'Скидка %', 'Промокод']);
      sheet.getRange(1, 1, 1, 7).setFontWeight('bold');
      sheet.setFrozenRows(1);
    }

    sheet.appendRow([
      data.timestamp || new Date(),
      data.name || '',
      "'" + (data.whatsapp || ''), // апостроф, чтобы номер не превратился в формулу/число
      data.goal || '',
      data.score || 0,
      data.discount || 0,
      data.promoCode || ''
    ]);

    return ContentService
      .createTextOutput(JSON.stringify({ success: true }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ success: false, error: String(err) }))
      .setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}
