#!/bin/sh
# update the project on the server

# get latest version
git pull origin master # || git reset --hard origin/master

# dependency update
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
deno install # --prod AFTER deno 2.8

# migrations
php artisan migrate --force

# finish
make build
