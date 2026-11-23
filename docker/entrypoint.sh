#!/bin/sh
set -eu

APP_DIR="/app/backend"
cd "$APP_DIR"

# Asegurar estructura escribible en storage y bootstrap/cache
mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Crear enlace simbólico público de storage si aún no existe
if [ ! -L public/storage ] && [ ! -e public/storage ]; then
    php artisan storage:link --no-interaction >/dev/null 2>&1 || true
fi

# Generar clave efímera si el entorno aún no define APP_KEY (evita HTTP 500 inicial)
if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="$(php artisan key:generate --show --no-interaction)"
    export APP_KEY
    echo "[DAGAZ][WARN] APP_KEY no definida; se generó una clave efímera para este arranque. Configure APP_KEY fija en producción." >&2
fi

# Limpiar cachés previas para absorber variables de entorno actualizadas
php artisan config:clear --no-interaction >/dev/null 2>&1 || true
php artisan route:clear --no-interaction >/dev/null 2>&1 || true
php artisan view:clear --no-interaction >/dev/null 2>&1 || true

# Ejecutar migraciones con reintentos cortos (tolerante a cold-start de Neon / Postgres)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    intento=1
    max_intentos="${MIGRATION_MAX_ATTEMPTS:-6}"
    while [ "$intento" -le "$max_intentos" ]; do
        if php artisan migrate --force --no-interaction; then
            break
        fi
        if [ "$intento" -eq "$max_intentos" ]; then
            echo "[DAGAZ][ERROR] No fue posible ejecutar php artisan migrate --force tras ${max_intentos} intentos." >&2
            if [ "${FAIL_ON_MIGRATION_ERROR:-true}" = "true" ]; then
                exit 1
            fi
            break
        fi
        echo "[DAGAZ][INFO] Esperando base de datos para migrar (${intento}/${max_intentos})..." >&2
        sleep 2
        intento=$((intento + 1))
    done
fi

# Sembrado inicial opcional: true | auto (solo si tabla fuentes está vacía) | false
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force --no-interaction
elif [ "${RUN_SEEDERS:-false}" = "auto" ]; then
    total_fuentes="$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Models\Fuente::count();' 2>/dev/null || echo 1)"
    if [ "$total_fuentes" = "0" ]; then
        echo "[DAGAZ][INFO] Tabla fuentes vacía; ejecutando DatabaseSeeder..." >&2
        php artisan db:seed --force --no-interaction
    fi
fi

# Cachear configuración, rutas y vistas usando las variables reales de producción
if [ "${APP_ENV:-production}" = "production" ] && [ "${LARAVEL_CACHE_ON_BOOT:-true}" = "true" ]; then
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
fi

# Garantizar permisos para el usuario www-data de Nginx / PHP-FPM
chown -R www-data:www-data storage bootstrap/cache

# Validar e inyectar el puerto dinámico asignado por Railway / Render / Compose
PORT="${PORT:-8000}"
case "$PORT" in
    ''|*[!0-9]*)
        echo "[DAGAZ][WARN] PORT inválido ('$PORT'); usando 8000." >&2
        PORT="8000"
        ;;
esac

sed "s/__PORT__/${PORT}/g" /etc/nginx/templates/dagaz.conf.template > /etc/nginx/conf.d/default.conf
nginx -t

echo "[DAGAZ] Iniciando Nginx + PHP-FPM en 0.0.0.0:${PORT} (APP_ENV=${APP_ENV:-production})" >&2
exec "$@"
