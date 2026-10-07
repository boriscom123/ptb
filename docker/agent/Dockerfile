# Изолированный контейнер AI-агента: Claude Code + всё, что нужно для проверок (PHP, Composer, Node).
FROM php:8.5-cli-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip bcmath pcntl

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache git nodejs npm ripgrep bash \
    && npm install -g @anthropic-ai/claude-code@2.1.293 \
    && npm cache clean --force

RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/zz-agent.ini

ENV USE_BUILTIN_RIPGREP=0 \
    DISABLE_AUTOUPDATER=1 \
    IS_SANDBOX=1

COPY runner.mjs /opt/runner/runner.mjs

WORKDIR /workspace
EXPOSE 8080
CMD ["node", "/opt/runner/runner.mjs"]
