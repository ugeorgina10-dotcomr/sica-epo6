# Pruebas de caja negra - SICA-EPO6

**Fecha:** 16 de noviembre de 2026  
**Objetivo:** comprobar el comportamiento observable del sistema mediante solicitudes HTTP y resultados, sin cambiar el código. Las pruebas automatizadas se ejecutaron con el cliente de pruebas de Laravel y una base SQLite en memoria; no se probaron contra una instalación con datos reales.

## Herramientas utilizadas

- **PHP 8.2.12 de XAMPP** y **PHPUnit 11.5.56** (ya instalados en el proyecto).
- Cliente HTTP de pruebas de **Laravel** para simular peticiones a las rutas de la aplicación.
- **SQLite en memoria** para aislar los datos de las pruebas.

## Casos funcionales ejecutados

Todos los casos siguientes forman parte de la suite existente. **Resultado general: aprobados** (78 pruebas y 372 aserciones en toda la suite).

| ID | Función / escenario | Resultado observable esperado | Resultado |
|---|---|---|---|
| CN-01 | Abrir la página de inicio | La página responde correctamente. | Aprobado |
| CN-02 | Iniciar sesión con credenciales correctas | Respuesta satisfactoria e identificación del rol correspondiente. | Aprobado |
| CN-03 | Iniciar sesión con contraseña incorrecta | Acceso rechazado; después de 5 intentos fallidos se limita otro intento. | Aprobado |
| CN-04 | Consultar el estado sin iniciar sesión | Acceso no autorizado (`401`). | Aprobado |
| CN-05 | Consultar como director y tratar de guardar datos | Puede consultar; no puede guardar (prohibido). | Aprobado |
| CN-06 | Intentar acciones de Control Escolar como orientador | No puede eliminar alumnos ni cambiar permisos; datos protegidos. | Aprobado |
| CN-07 | Guardar una lista de alumnos sin CURP dos veces | Se conserva un alumno, sin duplicados. | Aprobado |
| CN-08 | Asignar CURP a un alumno previamente guardado sin CURP | Se actualiza el mismo alumno. | Aprobado |
| CN-09 | Guardar y borrar la lista de un grupo | Se eliminan los alumnos, asistencias y grupo vacío asociados. | Aprobado |
| CN-10 | Registrar una asistencia enviando una hora diferente | El registro usa la hora del servidor. | Aprobado |
| CN-11 | Solicitar recuperación de contraseña | Se muestra el aviso correspondiente; al resolverlo desaparece. | Aprobado |
| CN-12 | Solicitar datos sin cambios usando ETag | El servidor responde `304` cuando el estado coincide. | Aprobado |

## Verificaciones manuales de interfaz pendientes

No se abrió el sistema en un navegador ni se usaron cámara o dispositivos físicos. Estas comprobaciones requieren una instalación de prueba accesible, credenciales de prueba y, para el lector, un dispositivo compatible. No se deben hacer sobre cuentas o datos reales sin autorización.

| ID | Prueba manual sugerida | Criterio de aceptación | Estado |
|---|---|---|---|
| MN-01 | Recorrer inicio de sesión y panel con cada rol en navegador | Se muestran las pantallas y acciones que corresponden al rol. | Pendiente |
| MN-02 | Registrar entrada y salida escaneando un QR de prueba | La asistencia aparece en pantalla con fecha y hora esperadas. | Pendiente |
| MN-03 | Probar el lector en móvil y modo sin conexión | El comportamiento y la sincronización al reconectar son correctos. | Pendiente |
| MN-04 | Cargar y descargar plantillas de credencial de prueba | Se valida el archivo y la vista/descarga presenta los datos esperados. | Pendiente |

## Límites y seguridad de la ejecución

- Las solicitudes fueron simuladas en el entorno aislado de PHPUnit: esto comprueba rutas y respuestas, pero **no sustituye** una prueba visual manual del navegador ni una prueba de cámara/QR físico.
- No se inició el servidor con la base MySQL configurada para la instalación, no se cargaron datos reales, no se instalaron dependencias y no se cambió código ni configuración.
- Las pruebas y su salida completa se describen en `pruebas de caja blanca/Informe.md`.

## Tablas para pegar capturas de interfaz y fragmentos de código

Las celdas son espacios de inserción. En Word, pega la captura o el código en la celda indicada y conserva el pie. Una captura de código solo da contexto: no sustituye la evidencia del comportamiento observado.

### Interfaces

| ID | Pantalla / caso | Pegar captura aquí | Qué debe mostrar y pie |
|---|---|---|---|
| UI-CN-01 | Inicio de sesión y panel por rol | **[PEGAR CAPTURA DE INTERFAZ AQUÍ]** | Cuenta ficticia y acciones visibles para el rol. Pie: navegador, rol sintético, fecha y resultado. |
| UI-CN-02 | Lector QR | **[PEGAR CAPTURA DE INTERFAZ AQUÍ]** | Antes/después de leer un QR de prueba y resultado de asistencia; no usar QR real. |
| UI-CN-03 | Carga de plantilla | **[PEGAR CAPTURA DE INTERFAZ AQUÍ]** | Archivo de prueba y mensaje de aceptación/rechazo. |
| UI-CN-04 | Respuesta HTTP | **[PEGAR CAPTURA DE INTERFAZ AQUÍ]** | Estado 401/403/304 en DevTools; ocultar cookies, tokens, datos y URL privada. |

### Código relacionado con los casos

| ID | Archivo / caso asociado | Pegar fragmento de código aquí | Qué explica |
|---|---|---|---|
| COD-CN-01 | `routes/web.php` y CN-02 | **[PEGAR FRAGMENTO AQUÍ]** | Ruta de login y respuesta observable; eliminar datos sensibles. |
| COD-CN-02 | `VerificarPermisoSica.php` y CN-05/CN-06 | **[PEGAR FRAGMENTO AQUÍ]** | Regla por rol que se contrasta con la respuesta HTTP. |
| COD-CN-03 | Prueba Feature que respalda el caso elegido | **[PEGAR FRAGMENTO AQUÍ]** | Preparación, petición y aserción. No presentar el código como captura de una ejecución manual. |
