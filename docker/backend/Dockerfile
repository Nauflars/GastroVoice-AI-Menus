FROM php:8.3-fpm-alpine AS base

# Build-time deps (compiler, headers) — needed for pecl + ext compile
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        linux-headers \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        util-linux-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        intl \
        opcache \
        zip \
        bcmath \
        sockets \
    && pecl install redis uuid \
    && docker-php-ext-enable redis uuid \
    && apk del .build-deps \
    # Runtime libs (keep these — needed at runtime)
    && apk add --no-cache \
        libpq \
        icu-libs \
        libzip \
        oniguruma \
        libuuid \
        zip \
        unzip \
        git \
        nginx \
        supervisor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# PHP configuration
COPY docker/backend/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/backend/nginx.conf /etc/nginx/nginx.conf
COPY docker/backend/supervisord.conf /etc/supervisord.conf

# --- Development stage ---
FROM base AS development
ENV APP_ENV=dev
COPY docker/backend/php-dev.ini /usr/local/etc/php/conf.d/app-dev.ini

# --- Production stage ---
FROM base AS production
ENV APP_ENV=prod
COPY backend/ /var/www/html/
RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN php bin/console cache:warmup

EXPOSE 8080
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
