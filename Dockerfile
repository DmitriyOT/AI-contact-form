# syntax=docker/dockerfile:1

# ---- base: PHP + extensions, shared by all stages ----
FROM php:8.2-cli AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev unzip \
    && docker-php-ext-install pdo_mysql intl opcache mbstring \
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

# ---- dev: code and vendor come from the bind mount (see docker-compose.yml) ----
FROM base AS dev

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]

# ---- prod (default): self-contained image with code and production dependencies ----
# Deploy targets (Render, Railway, a VPS) build this stage.
FROM base AS prod

# prod dependencies from the lockfile; this layer is cached until composer files change
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

COPY . .

# rebuild the optimized autoloader now that src/ is in place
# (var/ is excluded via .dockerignore, so create it before chown)
RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p var \
    && chown -R www-data:www-data var

# production defaults; real env vars (DATABASE_URL, MAILER_DSN, AI_*, ...) override .env
ENV APP_ENV=prod \
    APP_DEBUG=0

USER www-data

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
