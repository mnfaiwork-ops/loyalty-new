# Image server demo Loyalty New (Render). Satu image untuk dua brand: APP_BRAND
# saat runtime memilih app yang dilayani. Build dari root repo:
#   docker build -f demo/Dockerfile -t loyalty-demo .
FROM php:8.5-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl bcmath zip opcache \
    && a2enmod rewrite headers \
    && rm /etc/apache2/sites-enabled/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY packages/core packages/core
COPY apps/amura apps/amura
COPY apps/reglow apps/reglow
COPY demo demo

RUN for b in amura reglow; do \
        (cd apps/$b && composer install --no-dev --optimize-autoloader --no-interaction --no-progress) || exit 1; \
        chown -R www-data:www-data apps/$b/storage apps/$b/bootstrap/cache; \
    done \
    && cp demo/apache.conf /etc/apache2/ports.conf \
    && chmod +x demo/docker-entrypoint.sh

ENV PORT=10000
EXPOSE 10000
ENTRYPOINT ["/app/demo/docker-entrypoint.sh"]
