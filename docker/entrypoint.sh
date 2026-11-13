#!/bin/sh
set -e

# Render inyecta el puerto en $PORT (por defecto 10000)
PORT="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migraciones (con reintentos por si la BD aun esta arrancando)
i=0
until php artisan migrate --force; do
  i=$((i+1))
  [ "$i" -ge 10 ] && echo "No se pudo migrar" && exit 1
  echo "Esperando a la base de datos... ($i/10)"; sleep 5
done

# Roles, categorias y admin (los seeders usan firstOrCreate: son repetibles)
php artisan db:seed --force

# Tarea programada (confirmacion automatica de pedidos), en segundo plano
php artisan schedule:work >/dev/null 2>&1 &

chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
