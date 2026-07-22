FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev \
    && docker-php-ext-install pdo_mysql intl opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# opcache for the built-in server: without it every request re-parses all of vendor/
# (validate_timestamps stays on, so code changes on the bind mount are picked up)
RUN { \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=256'; \
    } > /usr/local/etc/php/conf.d/opcache-cli.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
