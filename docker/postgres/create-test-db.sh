#!/bin/sh
# Отдельная БД для тестов, чтобы RefreshDatabase не затирал рабочие данные
set -e
psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "CREATE DATABASE ${POSTGRES_DB}_test OWNER $POSTGRES_USER"
