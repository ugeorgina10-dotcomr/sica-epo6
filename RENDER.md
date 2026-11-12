# Publicar SICA en Render (con Postgres)

1. Base de datos: New + > Postgres (misma región que el servidor web). Copia la **Internal Database URL**.
2. Servidor web: New + > Web Service > este repositorio, entorno **Docker** (usa el `Dockerfile`).
3. Variables de entorno del servidor web:
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `APP_KEY` (genera una con `php artisan key:generate --show`)
   - `APP_URL=https://tu-servicio.onrender.com`
   - `DB_CONNECTION=pgsql`
   - `DB_URL=<Internal Database URL>`
   - `SESSION_SECURE_COOKIE=true`
4. Al arrancar, el contenedor migra la base y crea las cuentas solo (ver `CMD` del Dockerfile).
5. Plan del servidor: el de $7 (siempre activo) para empezar; sube a uno mayor si se pone lento.
