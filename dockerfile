# Use official PHP image with required extensions
FROM php:8.2-apache

# Install system dependencies (MySQL + PostgreSQL for DB options)
RUN apt-get update && apt-get install -y \
    git zip unzip libpng-dev libzip-dev \
    default-mysql-client libpq-dev \
    libreoffice-writer-nogui

RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql zip gd

RUN a2enmod rewrite

# Composer (reikalingas entrypoint gyvam mount)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN sed -i 's!/var/www/html!/var/www/public!g' \
    /etc/apache2/sites-available/000-default.conf
RUN echo 'SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1' >> /etc/apache2/apache2.conf

WORKDIR /var/www

# Production: COPY ir composer install; dev: volume mount perrides, entrypoint runs composer
COPY . /var/www
RUN chown -R www-data:www-data /var/www/var 2>/dev/null || true
RUN chmod -R 775 /var/www/var 2>/dev/null || true
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-scripts --no-autoloader 2>/dev/null || true

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]