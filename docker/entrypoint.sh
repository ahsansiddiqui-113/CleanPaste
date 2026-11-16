#!/bin/sh
set -e

# Hosts like Railway/Render tell the container which port to listen on via $PORT.
PORT="${PORT:-8080}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Optional: create tables on first boot (safe to repeat; skips if they exist).
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php /var/www/html/tools/migrate.php || echo "migration failed - check DB settings"
fi

exec apache2-foreground
