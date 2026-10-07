FROM php:8.5-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql redis intl zip bcmath pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/backend

# Dev-режим: файлы репозитория принадлежат root на хосте, поэтому FPM работает от root (-R).
CMD ["php-fpm", "-R"]
