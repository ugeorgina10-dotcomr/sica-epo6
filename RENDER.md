# Publicar SICA en Render

Render guarda tanto el sistema como la base de datos. No se necesita ningún otro servicio.

## Paso 1. Crear la base de datos
1. En Render pulsa **New +** y elige **Postgres**.
2. Ponle un nombre (por ejemplo `sica-epo6`) y elige la misma región que usará el servidor web.
3. Cuando esté lista, copia la **Internal Database URL** (la dirección de la base de datos).

## Paso 2. Crear el servidor web
1. Pulsa **New +** y elige **Web Service**.
2. Conecta este repositorio y en "Language" elige **Docker** (Render usa el archivo `Dockerfile`).

## Paso 3. Datos de configuración (Environment)
Agrega estas variables en el servidor web:

| Variable | Qué poner |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Una clave secreta (ver Paso 4) |
| `APP_URL` | La dirección de tu sitio, por ejemplo `https://sica-epo6.onrender.com` |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | La **Internal Database URL** del Paso 1 |
| `SESSION_SECURE_COOKIE` | `true` |

## Paso 4. Crear la clave secreta (APP_KEY)
En tu computadora, dentro de la carpeta del proyecto, escribe:

    php artisan key:generate --show

Copia el resultado (empieza con `base64:`) y pégalo en `APP_KEY`.

## Paso 5. Listo
Al encender, el sistema crea solo las tablas y las cuentas que falten. No tienes que hacer nada más.
Entra a tu dirección de Render con la cuenta de Control Escolar (contraseña inicial `prepa06`) y cámbiala de inmediato.

## Cosas que conviene saber
- **Plan gratis:** el sitio se "duerme" si nadie lo usa y tarda cerca de un minuto en despertar. Ábrelo un poco antes de la entrada.
- **Base de datos gratis:** se borra a los 30 días si no pasas a un plan de pago. Para uso real de la escuela, usa un plan de pago.
- **Plan de pago:** el servidor de $7 al mes se mantiene siempre encendido. Si se pone lento, sube a uno mayor.
- **Celulares sin señal:** el escaneo se guarda en el teléfono y se envía solo cuando regresa la conexión.
- **Si sale "500 Server Error":** revisa los registros (Logs) en el panel de Render.
