# SICA-EPO6: PC de la escuela + celulares en Render con la MISMA base de datos

Resultado: lo que subes en la PC (`php artisan serve`) se ve en Render y al revés.
Si se va la luz o el internet de la escuela, los orientadores escanean desde su celular
en https://sica-epo6.onrender.com y cualquier escaneo va al grupo del alumno.

## 1. Crear la base de datos gratis (Neon)  ~3 min
1. Entra a https://neon.tech y regístrate (con Google o GitHub).
2. Crea un proyecto: nombre `sica-epo6`, región la más cercana (US East está bien).
3. En el panel pulsa **Connect** y copia: host, base de datos, usuario y contraseña.
   (Apaga "Connection pooling" o usa el host SIN `-pooler`.)

## 2. Cambiar el Dockerfile en GitHub
Reemplaza el `Dockerfile` del repo por el de esta carpeta (el único cambio es que al arrancar
también corre `php artisan sica:crear-usuarios`, que crea las cuentas que falten).

## 3. Variables en Render
Abre `1-variables-RENDER.txt`, llena los datos de Neon y pégalas en
Render > sica-epo6 > Environment (puedes usar "Add from .env" y pegar todo el bloque).
Guarda; Render redespliega solo.

## 4. Generar el APP_KEY (una sola vez)
En la carpeta del proyecto en tu PC:
    php artisan key:generate --show
Copia el resultado (empieza con `base64:`). Úsalo en Render y en tu .env local: debe ser el MISMO.

## 5. Configurar tu PC
Abre `2-cambios-.env-LOCAL.txt` y aplica esas líneas en tu `.env`. Luego, en la carpeta del proyecto:
    php artisan config:clear
    php artisan migrate --force
    php artisan sica:crear-usuarios
    php artisan sica:verificar-claves
Después arranca como siempre (`php artisan serve` o `iniciar.bat`).
Nota: los datos que tenías en MySQL local no pasan solos; vuelve a subir tus listas una vez.

## 6. Probar
1. En la PC entra con control.escolar@epo6.edu.mx / prepa06 y sube una lista.
2. Entra a https://sica-epo6.onrender.com: la lista debe aparecer.
3. Con un celular escanea un alumno: debe verse en la PC y en Render.
4. Cambia las contraseñas iniciales (pestaña de contraseñas de Control Escolar).

## Consejos
- Render gratis se duerme: ábrelo antes de la entrada, o pon https://uptimerobot.com a visitarlo cada 5 min.
- Neon también "duerme" tras 5 min sin uso; despierta solo en ~1 s.
- Si la PC pierde internet no podrá leer la base; usen los celulares en Render con datos móviles.
- Si un celular se queda sin señal, el escaneo se guarda y se envía al recuperar conexión.
