FROM php:8.3-fpm-bookworm

ARG HOST_UID=1000
ARG HOST_GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libsqlite3-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        opcache \
        pdo_sqlite \
        zip \
        pcntl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid ${HOST_GID} app \
    && useradd --uid ${HOST_UID} --gid app --shell /bin/bash --create-home app \
    && mkdir -p /app/var/cache /app/var/log /app/var/data /app/vendor \
    && chown -R app:app /app

COPY docker/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=0 \
    COMPOSER_HOME=/home/app/.composer \
    PATH="/app/vendor/bin:${PATH}"

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
