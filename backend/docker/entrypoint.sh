#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
  cp .env.example .env
fi

mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

fresh_database=0
if [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite
  fresh_database=1
elif [ ! -s database/database.sqlite ]; then
  fresh_database=1
fi

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if grep -q '^APP_KEY=$' .env; then
  php artisan key:generate --force
fi

php artisan migrate --force

if [ "$fresh_database" -eq 1 ]; then
  php artisan db:seed --force
fi

exec php artisan serve --host=0.0.0.0 --port=8000