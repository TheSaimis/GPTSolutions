FROM php:8.4-apache

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libxslt1-dev \
    default-mysql-client \
    libreoffice \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo pdo_mysql mysqli zip intl mbstring xml xsl gd opcache

RUN a2enmod rewrite headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Symfony / Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV HOME=/tmp

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN printf '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>\n' > /etc/apache2/conf-available/symfony.conf \
    && a2enconf symfony

# Writable dirs for Symfony + LibreOffice
RUN mkdir -p /tmp/libreoffice-profile \
    /tmp/.cache \
    /var/www/html/var \
    /var/www/html/var/cache \
    /var/www/html/var/log \
    /var/www/html/var/pdf \
    && chmod -R 777 /tmp /var/www/html/var

CMD sh -c '\
    if [ ! -f /var/www/html/config/jwt/private.pem ]; then \
    echo "Generating JWT keys..."; \
    php bin/console lexik:jwt:generate-keypair --skip-if-exists; \
    fi && \
    exec apache2-foreground'