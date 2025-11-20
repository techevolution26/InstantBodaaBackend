#!/bin/sh
set -e

# Railway usually injects PORT (HTTP). Default to 9000 if not provided.
: "${PORT:=9000}"
# internal php-fpm port (avoid Railway's HTTP port)
: "${PHP_FPM_PORT:=9001}"

# Export so envsubst (which reads from environment) can see them
export PORT
export PHP_FPM_PORT

echo ">>> STARTUP: PORT=${PORT}, PHP_FPM_PORT=${PHP_FPM_PORT}"

# Robustly update php-fpm listen address in pool config(s)
for f in /usr/local/etc/php-fpm.d/*.conf /etc/php/*/fpm/pool.d/*.conf; do
  if [ -f "$f" ]; then
    sed -ri "s#listen\s*=.*#listen = 127.0.0.1:${PHP_FPM_PORT}#g" "$f" || true
    echo ">>> patched $f listen to 127.0.0.1:${PHP_FPM_PORT}"
  fi
done

# IMPORTANT: only substitute PORT and PHP_FPM_PORT — do NOT substitute nginx vars like $document_root
envsubst '$PORT $PHP_FPM_PORT' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf

echo ">>> generated /etc/nginx/conf.d/default.conf:"
cat /etc/nginx/conf.d/default.conf

# Show listeners and processes BEFORE starting (useful in logs)
echo ">>> current listeners (before start):"
ss -ltn || true
echo ">>> current processes (before start):"
ps aux | head -n 30

# ensure ownership/permissions
chown -R www-data:www-data /var/www/html || true

# Start php-fpm in background (daemonize)
php-fpm -D
sleep 0.5

# Show listeners AFTER php-fpm started (we should see php-fpm bound)
echo ">>> listeners after php-fpm start:"
ss -ltnp | head -n 50 || ss -ltn | head -n 50

# Start nginx in foreground
echo ">>> starting nginx..."
nginx -g 'daemon off;'
