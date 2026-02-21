#!/bin/bash
set -e
cd /var/www

# Jei nėra vendor/ – paleisti composer install (gyvam mount reikia)
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
  echo "Running composer install..."
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction
fi

# Symfony var/ katalogas turi būti rašomas
if [ -d var ]; then
  chown -R www-data:www-data var
  chmod -R 775 var
fi

exec apache2-foreground
