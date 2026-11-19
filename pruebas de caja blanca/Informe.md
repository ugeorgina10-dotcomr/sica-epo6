# Pruebas de caja blanca - SICA-EPO6

**Fecha:** 16 de noviembre de 2026  
**Alcance:** revisión de rutas internas de ejecución mediante las pruebas existentes, pruebas unitarias de lógica y validación de sintaxis. No se modificó el código ni la configuración del proyecto.

## Herramientas utilizadas

- **PHP 8.2.12 de XAMPP**, que tiene habilitado `pdo_sqlite`.
- **PHPUnit 11.5.56**, ya instalado en `vendor`; no se instaló software.
- **SQLite en memoria**, según `phpunit.xml`; las pruebas no usan la base de datos real del sistema.
- **`php -l`**, para verificar la sintaxis de archivos PHP.

## Ejecución y resultado

Comando de la suite:

```powershell
& 'C:\xampp\php\php.exe' vendor\phpunit\phpunit\phpunit --do-not-cache-result
```

**Resultado: aprobado — 78 pruebas, 372 aserciones, 0 fallos.**

También se validó la sintaxis de **57 archivos PHP** de `app`, `tests`, `routes` y `database`: todos correctos.

### Comprobaciones internas cubiertas

- **Lógica de asistencia** (`MovimientoAutomatico`): horarios matutino y vespertino, horarios predeterminados y no válidos, conversión de horas, jornada corta y decisiones de entrada/salida/completo. Resultado: **5 pruebas unitarias aprobadas**.
- **Autenticación, claves y roles**: hash de contraseñas, acceso válido e inválido, límites de intentos, validaciones, permisos por rol, bloqueo/desbloqueo, protección de datos sensibles y límites de solicitudes.
- **Sincronización y listas**: alumnos con y sin CURP, prevención de duplicados, eliminación y corrección de roles.
- **Precarga y caché HTTP**: datos incluidos en el inicio de sesión, diferencias por rol y respuestas `304`.
- **Rendimiento**: guardar sin cambios 24 grupos usó **2 consultas** (límite de la prueba: menos de 10); cargar 60 alumnos usó **10 consultas** (límite: menos de 25).
- **Avisos de olvido de contraseña**: aparición, actualización y desaparición de solicitudes.

La suite se ejecutó sobre las pruebas ya presentes en `tests/`; no se agregaron pruebas ejecutables nuevas ni se midió cobertura porcentual de líneas/rama.

## Tablas para pegar código y capturas técnicas

Pega el fragmento como texto seleccionable cuando sea posible. Para capturas, muestra nombre de archivo/líneas y la salida completa relevante; no incluyas secretos ni datos reales.

### Fragmentos de código

| ID | Archivo / prueba | Pegar fragmento aquí | Qué debe explicar |
|---|---|---|---|
| COD-WB-01 | `MovimientoAutomatico.php` / `MovimientoAutomaticoTest.php` | **[PEGAR FRAGMENTO DE CÓDIGO AQUÍ]** | Rama o condición probada y aserción correspondiente. |
| COD-WB-02 | `VerificarPermisoSica.php` / `SicaSeguridadTest.php` | **[PEGAR FRAGMENTO DE CÓDIGO AQUÍ]** | Permiso por rol, resultado esperado y prueba que lo verifica. |
| COD-WB-03 | `SicaRapidezTest.php` | **[PEGAR FRAGMENTO DE CÓDIGO AQUÍ]** | Instrumentación y límite de consultas; no es medición de latencia. |

### Capturas de ejecución/configuración

| ID | Evidencia | Pegar captura aquí | Qué demuestra |
|---|---|---|---|
| CAP-WB-01 | PHPUnit | **[PEGAR CAPTURA DE TERMINAL AQUÍ]** | Comando, versión y resultado real, incluidos errores/fallos si los hay. |
| CAP-WB-02 | Sintaxis y aislamiento | **[PEGAR CAPTURA AQUÍ]** | Resultado de `php -l` y/o `phpunit.xml` con SQLite en memoria; no capturar `.env`. |
| CAP-WB-03 | Código en IDE | **[PEGAR CAPTURA DE EDITOR AQUÍ]** | Archivo, líneas y prueba asociada legibles; el código no sustituye la salida de PHPUnit. |
| CAP-WB-04 | Interfaz con rol sintético | **[PEGAR CAPTURA DE INTERFAZ AQUÍ]** | Contexto visual del escenario; agregar solo después de una prueba manual. No sustituye la evidencia interna. |

## Incidencia del primer intento

El primer intento utilizó el PHP 8.5.12 disponible en el PATH, que no tenía el controlador PDO para SQLite: **72 pruebas reportaron error de entorno** (`could not find driver`). No se cambió `php.ini` ni se instaló una extensión. Se repitió la suite con el PHP 8.2.12 de XAMPP, que ya incluía `pdo_sqlite`, y las 78 pruebas pasaron.

## Archivos y datos

Esta carpeta contiene únicamente este informe. Las pruebas se configuraron para usar SQLite en memoria; no se ejecutaron migraciones contra MySQL ni se alteraron datos reales. El código de la aplicación y sus archivos de configuración permanecieron sin cambios.
