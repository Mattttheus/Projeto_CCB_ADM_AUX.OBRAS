FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite

COPY --from=dependencies /app/vendor /var/www/html/vendor
COPY . /var/www/html
COPY docker/apache-entrypoint.sh /usr/local/bin/apache-entrypoint

RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod +x /usr/local/bin/apache-entrypoint

EXPOSE 80

ENTRYPOINT ["apache-entrypoint"]