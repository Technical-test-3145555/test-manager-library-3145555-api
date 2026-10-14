FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip git \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/app/covers storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 80

# The schema is owned by the migrations (ADR-001): they run on start, against the empty engine.
# The initial admin comes from the environment (D-10). PHP_CLI_SERVER_WORKERS lets the server answer
# several requests at once, which the last-copy concurrency check needs (RNF-2).
ENV PHP_CLI_SERVER_WORKERS=8
CMD ["sh", "-c", "php artisan migrate --force && php artisan app:bootstrap-admin && php artisan serve --host=0.0.0.0 --port=80"]
