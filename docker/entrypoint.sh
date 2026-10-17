#!/bin/sh
# Entrypoint container "app" (PHP-FPM) dan "scheduler".
# Menyiapkan aplikasi sebelum menjalankan perintah utama (default: php-fpm).
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] ERROR: APP_KEY belum diisi. Jalankan:"
    echo "    docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show"
    echo "lalu salin hasilnya ke APP_KEY di .env.docker"
    exit 1
fi

# Tunggu MySQL siap (maks ~60 detik).
if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    echo "[entrypoint] Menunggu database ${DB_HOST}:${DB_PORT:-3306}..."
    i=0
    until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "[entrypoint] ERROR: database tidak dapat dihubungi."
            exit 1
        fi
        sleep 2
    done
fi

# Migrasi & cache hanya dijalankan oleh container utama (RUN_MIGRATIONS=true).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] Menjalankan migrasi..."
    php artisan migrate --force

    # Seed data inti (user per peran, COA, periode, template) hanya bila tabel users masih kosong.
    if [ "${SEED_ON_FIRST_RUN:-true}" = "true" ]; then
        USERS=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -n 1)
        if [ "$USERS" = "0" ]; then
            echo "[entrypoint] Database kosong — menjalankan CoreSeeder..."
            php artisan db:seed --class=CoreSeeder --force
        fi
    fi
fi

php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
