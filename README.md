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
htdocs/                  <- ГОТОВЫЙ К ДЕПЛОЮ корень сайта (пребилд, коммитится в git)
├── .htaccess            <- SPA fallback, защита config.php, кэш/сжатие
├── index.html           <- собранное React-приложение
├── assets/              <- JS/CSS с хэшами
└── api/
    ├── lead.php         <- единый контроллер: принимает POST-заявку, дёргает модули
    ├── telegram.php     <- API-модуль: отправка в Telegram-бот
    ├── sheets.php       <- API-модуль: запись в Google Sheets (Apps Script)
    └── config.example.php  <- шаблон конфига (сам config.php в git не попадает)

public/                  <- исходники статики: .htaccess и api/*.php (копируются в htdocs при билде)
src/                     <- исходники React-приложения (entry-server.tsx — entry для пререндера)
scripts/prerender.mjs    <- скрипт пререндера: инжектит SSR-разметку в htdocs/index.html
google-apps-script/Code.gs  <- код макроса для Google Таблицы
```

## Деплой на хостинг

1. Залейте **содержимое** папки `htdocs/` в корень сайта (`htdocs`, `public_html`, `www` — зависит от хостинга).
2. На сервере скопируйте `api/config.example.php` -> `api/config.php` и заполните его (см. ниже).
3. Готово. Форма на сайте шлёт POST на `/api/lead.php` (также доступен алиас `/api/lead`).

Требования к хостингу: PHP 7.4+ с расширением cURL, Apache с `mod_rewrite` (есть почти везде).

## Настройка Telegram-бота

1. Напишите [@BotFather](https://t.me/BotFather) -> `/newbot` -> получите **токен**.
2. Узнайте **chat_id**, куда слать заявки:
   - личка: напишите [@userinfobot](https://t.me/userinfobot) — он покажет ваш ID;
   - группа: добавьте бота в группу, отправьте любое сообщение и откройте
     `https://api.telegram.org/bot<ТОКЕН>/getUpdates` — возьмите `chat.id` (у групп он отрицательный).
3. **Важно:** отправьте боту `/start` (или добавьте его в группу), иначе Telegram не даст ему писать.
4. Впишите токен и chat_id в `api/config.php` на сервере.

## Настройка Google Sheets (опционально)

1. Создайте Google Таблицу.
2. Меню «Расширения -> Apps Script», вставьте код из `google-apps-script/Code.gs`.
3. «Развернуть -> Новое развёртывание -> Веб-приложение»:
   запуск **от моего имени**, доступ — **все** (Anyone).
4. Скопируйте URL развёртывания (`https://script.google.com/macros/s/.../exec`)
   в `api/config.php` -> `sheets_webapp_url`.

Если `sheets_webapp_url` пустой — модуль просто пропускается, заявки идут только в Telegram.

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
