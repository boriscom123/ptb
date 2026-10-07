# PTB — правила проекта

Telegram-бот: админка-миниприложение, модерация чатов, AI-агент для разработки. Архитектура и принятые решения — `docs/ARCHITECTURE.md`, читай её перед крупными изменениями.

## Структура

- `backend/` — Laravel 13 (PHP 8.5): API миниприложения, вебхук бота (Nutgram), модерация, задачи агента.
  - `routes/telegram.php` — обработчики бота, `app/Telegram/` — их классы.
  - `app/Moderation/` — движок правил модерации (новое правило: класс-наследник `Rules\Rule` + строка в `config/moderation.php` + переводы на фронтенде).
  - `app/Agent/` — задачи AI-агента на стороне Laravel.
  - `lang/ru`, `lang/en` — тексты бота и API.
- `frontend/` — Vue 3 + Vite + Pinia + vue-i18n, миниприложение (админка). `src/locales/{ru,en}.json` — тексты.
- `docker/` — Dockerfile'ы и конфигурация (Caddy, PostgreSQL, агент). `docker/agent/runner.mjs` — исполнитель задач агента.
- `compose.yaml` — все сервисы.

## Соглашения

- Пиши код в стиле окружающего: те же паттерны, именование, плотность комментариев. Комментарии — на русском, кратко и только там, где неочевидно «почему».
- Все тексты для пользователя — через переводы, **сразу на двух языках** (ru и en), ключи в ru и en должны совпадать.
- vue-i18n: символы `@ { } |` в строках перевода экранируй (`{'@'}`), иначе ломается вся страница.
- Оформление фронтенда — только переменные темы Telegram из `src/style.css` (`--bg`, `--text`, `--hint`, `--button` …), без UI-библиотек.
- Новые API-эндпоинты админки — в группе `auth` + `admin` в `routes/api.php`, ответы через API Resources.
- Изменения схемы БД — только новыми миграциями (существующие не редактировать). PostgreSQL.
- На каждое изменение поведения — тесты (PHPUnit в `backend/tests`, Vitest в `frontend/tests`). Для бота — `FakeNutgram` (см. `tests/Feature/Telegram`, `tests/Feature/Moderation`).
- Не трогай `.env`, секреты, `docker/agent/`, `compose.yaml` и `docker/caddy/` без явной просьбы в задаче.
- Не добавляй зависимости без необходимости; если добавляешь — через `composer require` / `npm install`, чтобы обновились lock-файлы.

## Проверки

Все должны проходить перед завершением работы.

В контейнере агента (рабочий каталог — корень репозитория):

```sh
cd backend && php artisan test --compact && vendor/bin/pint --test   # vendor/bin/pint — автоисправление стиля
cd frontend && npm test && npx vite build --outDir /tmp/agent-build --emptyOutDir
```

На сервере (вне контейнера):

```sh
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint
docker compose exec node npm test
```

## Git

- Рабочая ветка — `dev`. В `main` напрямую не коммитить.
- Агент не делает commit/push/reset/checkout сам — коммит создаёт исполнитель после проверок.
