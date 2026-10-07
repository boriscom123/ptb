#!/bin/sh
# Тестовые БД (выполняется только при первой инициализации тома PostgreSQL):
#  - ${POSTGRES_DB}_test — для тестов в контейнере app;
#  - ptb_agent_test — для тестов AI-агента, под отдельным пользователем без доступа к рабочей БД.
set -e
psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" <<SQL
CREATE DATABASE ${POSTGRES_DB}_test OWNER $POSTGRES_USER;
CREATE ROLE ptb_agent LOGIN PASSWORD '$AGENT_DB_PASSWORD';
CREATE DATABASE ptb_agent_test OWNER ptb_agent;
REVOKE CONNECT ON DATABASE $POSTGRES_DB FROM PUBLIC;
REVOKE CONNECT ON DATABASE ${POSTGRES_DB}_test FROM PUBLIC;
SQL
