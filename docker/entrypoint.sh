#!/bin/sh
# Prépare l'application au démarrage du conteneur, puis lance Apache.
set -e
cd /var/www/html
DATA=database/data

[ -f .env ] || cp .env.example .env

# Hébergeurs qui imposent un port (Render, Fly…) : Apache écoute sur $PORT.
if [ -n "${PORT:-}" ] && [ "$PORT" != "80" ]; then
    sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi
# Adresse publique fournie par Render si APP_URL n'est pas défini.
if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

# Clés générées une seule fois et conservées dans le volume de données.
[ -f "$DATA/app.key" ] || php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$DATA/app.key"
[ -f "$DATA/suppression.key" ] || php -r 'echo bin2hex(random_bytes(32));' > "$DATA/suppression.key"
export APP_KEY="$(cat "$DATA/app.key")"
export PM_SUPPRESSION_KEY="$(cat "$DATA/suppression.key")"

if [ "$DB_CONNECTION" = "sqlite" ]; then
    [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
fi
chown -R www-data:www-data "$DATA" storage

php artisan migrate --force
if [ "${PM_DEMO:-false}" = "true" ]; then
    php artisan pm:demo
fi
php artisan optimize

# File d'attente (imports) et planificateur en arrière-plan.
su -s /bin/sh www-data -c "php artisan queue:work --queue=emails,automations,imports,default --sleep=2 --tries=3 >> storage/logs/worker.log 2>&1 &"
su -s /bin/sh www-data -c "php artisan schedule:work >> storage/logs/scheduler.log 2>&1 &"

exec "$@"
