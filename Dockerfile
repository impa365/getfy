# Estágio 1: compila extensões PHP (toolchain pesada); não vai para a imagem final.
FROM php:8.2-fpm-alpine AS php_extensions_builder

RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libzip-dev libpng-dev freetype-dev libjpeg-turbo-dev \
    oniguruma-dev icu-dev libxml2-dev postgresql-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql pdo_pgsql zip exif intl opcache pcntl bcmath \
    && mkdir -p /export-inis \
    && cp /usr/local/etc/php/conf.d/docker-php-ext-*.ini /export-inis/ \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

# Estágio 2: runtime base — nginx/supervisor + libs (sem gcc).
FROM php:8.2-fpm-alpine AS php_runtime

COPY --from=php_extensions_builder \
    /usr/local/lib/php/extensions/no-debug-non-zts-20220829/ \
    /usr/local/lib/php/extensions/no-debug-non-zts-20220829/

COPY --from=php_extensions_builder /export-inis/ /usr/local/etc/php/conf.d/

RUN apk add --no-cache \
    nginx supervisor curl \
    git unzip mysql-client postgresql-client \
    libzip libpng freetype libjpeg-turbo \
    oniguruma icu-libs icu-data-en libxml2 libpq

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Estágio 3: assets Vite
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# Estágio 4: dependências PHP (sem scripts — discover no entrypoint)
FROM php_runtime AS vendor

COPY composer.json composer.lock ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY plugins ./plugins
COPY routes ./routes
COPY artisan ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    && composer dump-autoload --optimize --no-dev

# Estágio 5: imagem final de release
FROM php_runtime AS app

COPY docker/php/conf.d/99-getfy-uploads.ini /usr/local/etc/php/conf.d/99-getfy-uploads.ini
COPY docker/php-fpm.d/zz-getfy.conf /usr/local/etc/php-fpm.d/zz-getfy.conf
COPY docker/nginx/getfy.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/getfy-entrypoint

RUN chmod +x /usr/local/bin/getfy-entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache .docker .docker/plugins-installed \
    && mkdir -p /run/nginx \
    && chmod -R 777 storage bootstrap/cache .docker

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/getfy-entrypoint"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]
