# Global Sparrow

Лендинг «Global Sparrow» (React + Vite + Tailwind) с приёмом заявок через PHP-бэкенд:
уведомления приходят в **Telegram-бот** и (опционально) пишутся в **Google Sheets**.

Репозиторий содержит **готовый пребилд** в папке `htdocs/` — её содержимое можно сразу
выкладывать в корень статичного PHP-хостинга (Apache).

Билд **пререндерен (SSG)**: `htdocs/index.html` содержит полностью отрендеренную
страницу (контент виден без JS и индексируется поисковиками), а React при загрузке
гидрирует разметку и оживляет интерактив.

## Структура

```
.htaccess                <- переадресатор: корень репозитория = httpdocs, сайт отдаётся из /htdocs
nginx.example.conf       <- директивы для nginx, если он раздаёт статику перед Apache (Plesk)
htdocs/                  <- корень САЙТА (пребилд, коммитится в git)
├── .htaccess            <- SPA fallback, защита config.php, кэш/сжатие
├── index.html           <- собранное React-приложение
├── assets/              <- JS/CSS с хэшами
└── api/
    ├── lead.php         <- единый контроллер: принимает POST-заявку, дёргает модули
    ├── telegram.php     <- API-модуль: отправка в Telegram-бот
    ├── sheets.php       <- API-модуль: запись в Google Sheets (через Google Forms)
    └── config.example.php  <- шаблон конфига (сам config.php в git не попадает)

public/                  <- исходники статики: .htaccess и api/*.php (копируются в htdocs при билде)
src/                     <- исходники React-приложения (entry-server.tsx — entry для пререндера)
scripts/prerender.mjs    <- скрипт пререндера: инжектит SSR-разметку в htdocs/index.html
```

## Деплой на хостинг

Репозиторий разворачивается **целиком в корень сайта** (httpdocs). Корневой `.htaccess`
прозрачно переписывает все запросы в подпапку `/htdocs` и закрывает исходники
(`src/`, `public/`, `package.json`, `.git` и т.д.) от внешнего доступа.

1. Клонируйте/залейте репозиторий в корень сайта (httpdocs).
2. На сервере скопируйте `htdocs/api/config.example.php` -> `htdocs/api/config.php` и заполните (см. ниже).
3. Готово. Форма шлёт POST на `/api/lead.php` (также доступен алиас `/api/lead`).

**Если перед Apache стоит nginx** с включённой раздачей статики (типичный Plesk):
добавьте директивы из `nginx.example.conf` в «Дополнительные директивы nginx»,
иначе nginx будет искать статику в корне и отдавать 404. Если nginx просто
проксирует всё в Apache — ничего делать не нужно.

Требования к хостингу: PHP 7.4+ с расширением cURL, Apache с `mod_rewrite` (есть почти везде).

## Настройка Telegram-бота

1. Напишите [@BotFather](https://t.me/BotFather) -> `/newbot` -> получите **токен**.
2. Узнайте **chat_id**, куда слать заявки:
   - личка: напишите [@userinfobot](https://t.me/userinfobot) — он покажет ваш ID;
   - группа: добавьте бота в группу, отправьте любое сообщение и откройте
     `https://api.telegram.org/bot<ТОКЕН>/getUpdates` — возьмите `chat.id` (у групп он отрицательный).
3. **Важно:** отправьте боту `/start` (или добавьте его в группу), иначе Telegram не даст ему писать.
4. Впишите токен и chat_id в `api/config.php` на сервере.

## Настройка Google Sheets

Заявки пишутся **напрямую в Google Таблицу** через Sheets API v4 от имени
сервисного аккаунта (без Apps Script и сторонних библиотек). Шапка листа
создаётся автоматически при первой заявке.

1. Зайдите в [Google Cloud Console](https://console.cloud.google.com/) -> создайте проект
   (или используйте существующий).
2. «APIs & Services -> Library» -> найдите **Google Sheets API** -> Enable.
3. «APIs & Services -> Credentials» -> «Create Credentials -> Service account».
   Имя любое (например `sparrow-leads`), роли не нужны.
4. Откройте созданный аккаунт -> вкладка «Keys» -> «Add key -> Create new key -> JSON».
   Скачанный файл переименуйте в `google-credentials.json` и положите в `api/`
   на сервере (он закрыт от веба и git).
5. Создайте Google Таблицу с листом **«Заявки»** и нажмите «Настройки доступа» ->
   добавьте email сервисного аккаунта (`...@...iam.gserviceaccount.com` из JSON)
   с правами **Редактор**.
6. В `api/config.php` впишите `sheets_spreadsheet_id` — кусок URL таблицы между
   `/d/` и `/edit`.

Если `sheets_spreadsheet_id` пустой — модуль пропускается.

## Резервная таблица на сервере

Каждая заявка дополнительно дублируется в `storage/leads.csv` (вне веб-корня) —
даже если Telegram и Google недоступны, лид не потеряется.

- Просмотр в браузере: `https://ваш-сайт/api/leads.php?key=<admin_key из config.php>`
- Выгрузка CSV: та же ссылка + `&format=csv`

## Локальная разработка

```bash
npm install
npm run dev        # vite dev-сервер (фронтенд) на :3000/:5173
npm run dev:php    # (в соседнем терминале) php -S localhost:8080 -t htdocs — для теста API
```

Запросы `/api/*` из dev-сервера проксируются на `localhost:8080`.
Для локального теста API положите `config.php` в `htdocs/api/` (он в .gitignore).

## Пересборка пребилда

После любых правок фронтенда или файлов в `public/`:

```bash
npm run build      # клиентский билд + SSR-билд + пререндер -> htdocs/
git add htdocs && git commit
```

## API

`POST /api/lead.php` — JSON:

```json
{
  "name": "Имя",            // обязательно
  "whatsapp": "+7 777 ...", // обязательно
  "goal": "Сдача IELTS",
  "score": 55,
  "discount": 20,
  "promoCode": "SPARROW-20-AB1CD"
}
```

Ответ: `200` если заявка доставлена хотя бы в один канал, `400` при невалидных данных,
`502` если ни один канал не сработал. В теле — детальный статус по `telegram` и `sheets`.
