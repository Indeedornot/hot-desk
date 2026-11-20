FROM node:22.19.0-bookworm-slim AS frontend
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM dunglas/frankenphp:php8.3
HEALTHCHECK NONE
RUN install-php-extensions pdo_pgsql intl mbstring zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-scripts --no-interaction --prefer-dist
COPY .env ./
COPY bin/ bin/
COPY config/ config/
COPY docs/openapi.yaml docs/openapi.yaml
COPY migrations/ migrations/
COPY public/index.php public/index.php
COPY src/ src/
COPY --from=frontend /app/public/app/ public/app/
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
