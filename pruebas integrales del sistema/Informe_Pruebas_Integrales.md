# Informe de pruebas integrales — SICA-EPO6

**Fecha de ejecución:** 19 de noviembre de 2026  
**Entorno PHP:** PHP 8.3.36 de XAMPP, PHPUnit 11.5.56, Laravel 12.  
**Aislamiento:** las pruebas PHPUnit usan SQLite `:memory:` según `phpunit.xml`. La aceptación HTTP se hizo con una base SQLite temporal en `%TEMP%`; no se conectó a la base real.  
**Alcance:** pruebas funcionales, no funcionales, de integración, automatización/verificación, Feature y aceptación HTTP local. Las capturas visuales quedan pendientes de pegar por el usuario.

## Resumen de resultados

| Categoría | Ejecución | Resultado observado | Estado |
|---|---|---|---|
| Funcionales | Seguridad, claves, listas, precarga y avisos | 66 pruebas, 324 aserciones | Aprobadas |
| No funcionales | `SicaRapidezTest` | 5 pruebas, 26 aserciones; 2 consultas/24 grupos y 10 consultas/60 alumnos | Aprobadas para esos límites; no mide latencia ni concurrencia |
| Integración | Precarga, listas y avisos | 14 pruebas, 62 aserciones | Aprobadas |
| Automatización de verificación | `php -l` en `app`, `tests`, `routes`, `database` | 60 archivos, 0 errores de sintaxis | Aprobada |
| Feature | Suite Feature completa | 83 pruebas, 359 aserciones; 5 errores y 2 fallos | No aprobada |
| Aceptación de entorno web | HTTP local en SQLite temporal | Inicio 200; login 200; estado autenticado 200; logout 200; estado sin sesión 401; tres recursos estáticos 200 | Aprobación HTTP; aceptación visual interactiva pendiente |

Los grupos funcionales y de integración se solapan con la suite Feature; no sumarlos como pruebas únicas. El comando de Feature reportó 5 errores porque `ZipArchive` no está disponible en el PHP CLI y 2 fallos de aserción (se esperaba HTTP 422 y se recibió 200). Habilitar `extension=zip` es necesario para los casos DOCX, pero no garantiza resolver los dos fallos.

La comprobación interactiva con navegador integrado no se pudo completar: el navegador de esta sesión reportó `ERR_INVALID_HTTP_RESPONSE` al cargar recursos y `ReferenceError: $ is not defined`. Las solicitudes HTTP directas a la página y recursos sí respondieron 200. Por eso el flujo HTTP queda registrado, pero no se afirma que la aceptación visual del navegador haya pasado.

`npm run build` no se ejecutó: `node_modules` no está instalado. No se instalaron dependencias.

## Comandos reproducibles en PowerShell

Ejecutar desde la raíz del proyecto. Los archivos `.txt` de salida ya guardados en `evidencias` son resultados de esta ejecución; `Tee-Object` permite volver a generarlos y verlos en la terminal.

### Preparar rutas y herramientas

```powershell
$php = 'C:\xampp\php\php.exe'
$runner = 'vendor\phpunit\phpunit\phpunit'
$e = 'pruebas integrales del sistema\evidencias'
New-Item -ItemType Directory -Force $e | Out-Null
```

### Pruebas funcionales

```powershell
& $php $runner --do-not-cache-result tests\Feature\SicaSeguridadTest.php tests\Feature\SicaClavesTest.php tests\Feature\SicaListasTest.php tests\Feature\SicaPrecargaTest.php tests\Feature\SicaOlvidoAvisoTest.php 2>&1 | Tee-Object -FilePath "$e\01_funcionales.txt"
$LASTEXITCODE
```

Resultado de esta ejecución: `OK (66 tests, 324 assertions)`.

### Pruebas no funcionales: consultas/rendimiento

```powershell
& $php $runner --do-not-cache-result tests\Feature\SicaRapidezTest.php 2>&1 | Tee-Object -FilePath "$e\02_no_funcionales_rendimiento.txt"
$LASTEXITCODE
```

Resultado: `OK (5 tests, 26 assertions)`. La salida informa 2 consultas para 24 grupos sin cambios y 10 consultas para 60 alumnos. No es una prueba de carga ni mide segundos.

### Pruebas de integración

```powershell
& $php $runner --do-not-cache-result tests\Feature\SicaPrecargaTest.php tests\Feature\SicaListasTest.php tests\Feature\SicaOlvidoAvisoTest.php 2>&1 | Tee-Object -FilePath "$e\03_integracion.txt"
$LASTEXITCODE
```

Resultado: `OK (14 tests, 62 assertions)`.

### Automatización de verificación de sintaxis

```powershell
$files = Get-ChildItem app,tests,routes,database -Recurse -Filter *.php
$files | ForEach-Object { & $php -l $_.FullName } 2>&1 | Tee-Object -FilePath "$e\04_automatizacion_sintaxis.txt"
"Archivos=$($files.Count)"
```

Resultado: 60 archivos y 0 errores de sintaxis.

### Suite de pruebas Feature completa

```powershell
& $php $runner --do-not-cache-result --testsuite Feature 2>&1 | Tee-Object -FilePath "$e\05_feature.txt"
$LASTEXITCODE
```

Resultado de esta ejecución: 83 pruebas, 359 aserciones, 5 errores y 2 fallos. Conservar la salida completa; no recortar ni ocultar los errores.

## Aceptación de entorno web aislado

No ejecutar estas migraciones contra MySQL ni contra datos reales. Abrir una terminal PowerShell para preparar la base temporal, migrarla y levantar el servidor:

```powershell
$php = 'C:\xampp\php\php.exe'
$db = Join-Path $env:TEMP ('sica-aceptacion-' + [guid]::NewGuid().ToString('N') + '.sqlite')
New-Item -ItemType File -Path $db | Out-Null
$env:APP_KEY = & $php artisan key:generate --show
$env:APP_ENV = 'testing'
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8010'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $db
$env:DB_URL = ''
$env:SESSION_DRIVER = 'database'
$env:SESSION_SECURE_COOKIE = 'false'
$env:CACHE_STORE = 'array'
$env:QUEUE_CONNECTION = 'sync'
$env:MAIL_MAILER = 'array'
$env:LOG_CHANNEL = 'stderr'
$env:BCRYPT_ROUNDS = '4'
$php artisan migrate --force
$php artisan db:seed --class=UsuarioSeeder --force
$php artisan serve --host=127.0.0.1 --port=8010
```

Dejar esa terminal abierta. En otra terminal, abrir `http://127.0.0.1:8010`, comprobar la pantalla y realizar el flujo con una cuenta de prueba creada solo en la base temporal. Para reproducir las comprobaciones HTTP exitosas de esta ejecución, la secuencia fue: GET `/`, POST `/auth/login`, GET `/api/sica/estado` autenticado, POST `/auth/logout` y GET `/api/sica/estado` sin sesión. Resultados: 200, 200, 200, 200 y 401, respectivamente. Recursos `/vendor/jquery.min.js`, `/vendor/bootstrap.min.css` y `/img/logo6.png`: 200.

El resumen de esta ejecución está en `evidencias/06_aceptacion_http.txt`. No se guarda contraseña, cookie ni token en ese archivo.

Al terminar, detén el servidor con **Ctrl + C** en la primera terminal y elimina únicamente la base temporal generada por esa sesión:

```powershell
Remove-Item -LiteralPath $db
```

## Comandos para guardar salida y tomar captura

`Tee-Object` guarda la salida de la prueba en `.txt` y la deja visible en pantalla. Cuando aparezca el resumen final:

1. Pulsa **Win + Shift + S** o ejecuta `Start-Process "ms-screenclip:"`.
2. Selecciona solo la ventana/área de la terminal con el resumen final.
3. En el recorte, pulsa **Ctrl + C**; abre este Word, ubica el renglón con el mismo ID y pega con **Ctrl + V** dentro de la celda de evidencia.
4. Añade pie con ID, fecha, entorno y resultado. Si la ejecución falla, conserva la evidencia del fallo y no la reemplaces por una captura anterior.
5. Para evidencia web, captura la interfaz en navegador únicamente después de una prueba real; oculta datos personales, URL privada y cualquier sesión. No uses los errores del navegador integrado como si fueran una prueba de aceptación visual aprobada.

## Evidencia que debe pegarse en el Word

| ID | Evidencia/captura que se debe pegar en el Word | Espacio para evidencia | Resultado real y qué demuestra |
|---|---|---|---|
| EV-FUN-01 | Terminal de funcionales y, opcionalmente, fragmento de `SicaSeguridadTest.php`. | [PEGAR CAPTURA AQUÍ] | Log `01_funcionales.txt`: 66 pruebas/324 aserciones, aprobado. Distinguir código fuente del resultado ejecutado. |
| EV-NOF-01 | Terminal y fragmento de `SicaRapidezTest.php`. | [PEGAR CAPTURA AQUÍ] | Log `02_no_funcionales_rendimiento.txt`: 2 consultas/24 grupos y 10/60 alumnos; no mide latencia ni concurrencia. |
| EV-INT-01 | Terminal de integración y fragmento de uno de los Feature tests. | [PEGAR CAPTURA AQUÍ] | Log `03_integracion.txt`: 14 pruebas/62 aserciones, aprobado. |
| EV-AUT-01 | Terminal con `php -l` y total de archivos. | [PEGAR CAPTURA AQUÍ] | Log `04_automatizacion_sintaxis.txt`: 60 archivos, 0 errores de sintaxis. |
| EV-FEA-01 | Resumen final completo de Feature, incluyendo errores y fallos. | [PEGAR CAPTURA AQUÍ] | Log `05_feature.txt`: 83 pruebas/359 aserciones, 5 errores y 2 fallos; no aprobado. |
| EV-WEB-01 | Inicio/login y flujo web con rol sintético; captura adicional de terminal HTTP. | [PEGAR CAPTURA AQUÍ] | Log `06_aceptacion_http.txt`; captura interactiva de navegador pendiente. No incluir datos personales. |

**No se tomaron capturas visuales en esta ejecución.** Los logs `.txt` sí se guardaron en `evidencias`; reemplaza cada marcador del Word con una captura auténtica y conserva la fecha/resultado reales.
