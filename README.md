# PTB

Telegram-бот с админкой в виде миниприложения Telegram, модерацией чатов и AI-агентом для разработки.

Архитектура и принятые решения: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Запуск

```sh
cp .env.example .env   # заполнить APP_KEY, DB_PASSWORD и остальные секреты
docker compose up -d --build
docker compose exec app php artisan migrate
```

Приложение: https://150-241-105-178.sslip.io, проверка API: `/api/health`.

## Полезные команды

```sh
docker compose ps                         # статус сервисов
docker compose logs -f app queue          # логи
docker compose exec app php artisan ...   # artisan
docker compose exec node npm ...          # npm во фронтенде
```
