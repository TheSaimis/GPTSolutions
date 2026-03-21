#!/bin/sh
set -e

mkdir -p config/jwt

if [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
  echo "Generating JWT keys..."
  php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
fi

echo "Waiting for database..."
until php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done

php bin/console doctrine:migrations:migrate --no-interaction || true

exec apache2-foreground