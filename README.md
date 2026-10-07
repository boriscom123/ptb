# PTB

Telegram-бот с админкой в виде миниприложения Telegram, модерацией чатов и AI-агентом для разработки.

Архитектура и принятые решения: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Запуск

```sh
cp .env.example .env   # заполнить APP_KEY, DB_PASSWORD и остальные секреты
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan bot:setup   # вебхук, команды и описания бота в Telegram
```

Приложение: https://150-241-105-178.sslip.io, проверка API: `/api/health`.

## Полезные команды

```sh
docker compose ps                         # статус сервисов
docker compose logs -f app queue          # логи
docker compose exec app php artisan ...   # artisan
docker compose exec node npm ...          # npm во фронтенде
docker compose exec node npm test         # тесты фронтенда
docker compose exec app php artisan test  # тесты (отдельная БД ptb_test)
docker compose exec app vendor/bin/pint   # форматирование PHP-кода
docker compose logs -f agent              # лог AI-агента
```

Правила проекта для разработки (и для AI-агента) — [CLAUDE.md](CLAUDE.md).
