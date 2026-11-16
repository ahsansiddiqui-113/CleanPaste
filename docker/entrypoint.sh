#!/bin/sh
set -e

# Apache allows exactly one MPM. Some hosts end up with several enabled ("AH00534"),
# so force prefork (required by mod_php) at every start.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
a2enmod mpm_prefork >/dev/null 2>&1 || true

# Diagnostics shown in the host's deploy log
echo "entrypoint: enabled MPM files: $(ls /etc/apache2/mods-enabled | grep -i mpm | tr '\n' ' ')"
echo "entrypoint: other mpm references: $(grep -rli 'mpm_' /etc/apache2/apache2.conf /etc/apache2/conf-enabled /etc/apache2/sites-enabled 2>/dev/null | tr '\n' ' ')"

# Hosts like Railway/Render tell the container which port to listen on via $PORT.
PORT="${PORT:-8080}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Optional: create tables on first boot (safe to repeat; skips if they exist).
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php /var/www/html/tools/migrate.php || echo "migration failed - check DB settings"
fi

exec apache2-foreground
