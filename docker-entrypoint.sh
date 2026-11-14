#!/usr/bin/env sh
set -e

cd /app

mkdir -p storage/app/private storage/app/public storage/app/evidence \
         storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache \
         provisioning/registry provisioning/issued provisioning/imports provisioning/archive

if [ ! -f /app/database/database.sqlite ]; then
    if [ -f /app/database/cloud_seed.sqlite ]; then
        cp /app/database/cloud_seed.sqlite /app/database/database.sqlite
    else
        touch /app/database/database.sqlite
    fi
fi

php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=OfficialPussiberadSeeder --force
php artisan config:clear
php artisan view:clear

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
