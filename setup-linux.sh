#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
command -v php >/dev/null || { echo "Install PHP 8.2+ first."; exit 1; }
command -v composer >/dev/null || { echo "Install Composer first."; exit 1; }
php tools/prepare.php
composer install --no-dev --prefer-dist --optimize-autoloader
php tools/key.php
php artisan config:clear
php artisan migrate --force
php artisan storage:link || echo "Create the public/storage symlink manually if logos are needed."
php artisan park:install
php artisan park:database-check
printf '\nStart a local test server with: php artisan serve --host=0.0.0.0 --port=8000\n'
