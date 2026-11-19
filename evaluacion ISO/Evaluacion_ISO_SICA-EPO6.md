# Evaluación documental de alineación con estándares ISO — SICA-EPO6

**Fecha:** 18 de noviembre de 2026  
**Alcance:** revisión exploratoria del código, configuración, pruebas y documentación disponibles en el repositorio.  
**Importante:** este documento no es una auditoría de certificación. No se encontró en el repositorio un certificado, alcance de certificación ni evidencia de auditoría independiente; por ello no se afirma que la organización o el sistema estén certificados o cumplan íntegramente una norma ISO.

## Resumen

SICA-EPO6 contiene algunas prácticas técnicas que se relacionan con calidad de producto, pruebas de software y seguridad. La evidencia disponible permite hablar de **alineación parcial**, no de cumplimiento formal. Además, la última ejecución de la suite completa no pasó: 90 pruebas y 388 aserciones, con 5 errores y 2 fallos.

## Respuesta directa: qué se puede afirmar que sí cumple

Con la evidencia revisada **no se puede afirmar que el sistema cumpla íntegramente ninguna de estas normas ISO**. Las ISO no se otorgan por tener unas funciones o pasar unas pruebas: hay que evaluar el alcance completo y, en el caso de ISO/IEC 27001, también el sistema de gestión de la organización.

Lo que sí se puede respaldar son estos aspectos concretos:

| Norma | Aspecto respaldado por evidencia | Afirmación correcta |
|---|---|---|
| ISO/IEC 25010:2023 | Parte de la idoneidad funcional: escenarios automatizados de login, permisos, listas y asistencia. Parte de eficiencia: límites de consultas en 2 escenarios. | “Se verificaron estos escenarios de funcionalidad y consultas”; no significa que se cumpla todo el modelo de calidad. |
| ISO/IEC/IEEE 29119-2/-3/-4:2021 | Existen suites PHPUnit, casos unitarios y de integración, informes y resultados reproducibles con SQLite aislado. | “Se aplican prácticas documentadas de pruebas”; la suite total no está aprobada y no se verificó conformidad con toda la serie. |
| ISO/IEC 27001:2022 | Controles técnicos puntuales: roles/acciones permitidas, hash de contraseñas, límite de intentos y algunas cabeceras HTTP. | “Hay controles técnicos alineados con objetivos de seguridad”; no se puede afirmar cumplimiento de ISO/IEC 27001 ni certificación del SGSI. |

## Métricas de calidad disponibles

Datos de la repetición del **18 de noviembre de 2026**, usando PHP 8.3.36, PHPUnit 11.5.56 y SQLite en memoria:

| Métrica observada | Resultado | Qué significa / límite |
|---|---:|---|
| Suite completa | 90 pruebas, 388 aserciones; 5 errores y 2 fallos | No aprobada. A partir de 90 casos menos 7 errores/fallos, 83 casos no aparecen como fallidos (92.2%); es una tasa de casos de esta ejecución, no una calificación de calidad ni de confiabilidad del sistema. |
| Errores de entorno | 5 | `ZipArchive` no está disponible porque PHP `zip` no está cargado en el CLI de pruebas. |
| Fallos de aserción | 2 | Se esperaba HTTP 422 y se recibió 200; requieren investigación independiente del problema de `zip`. |
| Pruebas unitarias | 7 pruebas, 29 aserciones; aprobadas | Verifica reglas internas incluidas en la suite Unit. |
| Integración enfocada | 14 pruebas, 62 aserciones; aprobadas | Valida solicitudes y persistencia en escenarios seleccionados. |
| Autorización y claves | 52 pruebas, 262 aserciones; aprobadas | Pruebas funcionales acotadas; no son auditoría ni pentest. |
| Rendimiento por consultas | 5 pruebas, 26 aserciones; aprobadas | 24 grupos sin cambios: 2 consultas (límite <10). Carga de 60 alumnos: 10 consultas (límite <25). No mide segundos, usuarios concurrentes ni infraestructura de producción. |
| Sintaxis PHP | 60 de 60 archivos sin errores | Solo valida sintaxis; no mide mantenibilidad ni defectos funcionales. |
| Cabeceras de seguridad probadas | `X-Frame-Options: SAMEORIGIN` y `X-Content-Type-Options: nosniff` | Dos comprobaciones funcionales; no equivalen a una evaluación integral de seguridad. |

Los grupos focalizados se solapan con la suite completa; no se deben sumar como pruebas únicas. No hay medición disponible de cobertura, latencia, disponibilidad, recuperación ante fallas, accesibilidad, usabilidad, compatibilidad de navegadores ni carga concurrente. Xdebug/PCOV no se usó.

| Referencia | Para qué sirve | Evaluación basada en el repositorio |
|---|---|---|
| ISO/IEC 25010:2023 | Modelo para especificar y evaluar calidad de productos de software. | **Parcial.** Hay pruebas funcionales y mediciones acotadas; faltan criterios de aceptación y evidencia de usabilidad, accesibilidad, compatibilidad y carga real. |
| ISO/IEC/IEEE 29119-2:2021, 29119-3:2021 y 29119-4:2021 | Procesos, documentación y técnicas de pruebas de software. | **Parcial.** Existen suites, casos e informes de caja negra/blanca y complementarios; falta cerrar resultados pendientes y completar trazabilidad y evidencia de ejecución. |
| ISO/IEC 27001:2022 | Requisitos para un sistema de gestión de seguridad de la información (SGSI) de una organización. | **No verificable como cumplimiento.** El sistema implementa algunos controles técnicos, pero el repositorio no acredita un SGSI, gestión de riesgos ni auditorías organizacionales. |

## Evidencia por referencia

### ISO/IEC 25010:2023 — calidad del producto

**Evidencia favorable**

- Hay pruebas de autenticación, permisos por rol, listas, asistencia y claves en `tests/Feature`; también pruebas unitarias para reglas de asistencia en `tests/Unit/MovimientoAutomaticoTest.php`.
- `tests/Feature/SicaRapidezTest.php` verifica consultas en escenarios concretos: 2 consultas para 24 grupos sin cambios y 10 para cargar 60 alumnos. Esto es una medición acotada de consultas, no una prueba de carga.
- Las rutas y responsabilidades se separan en controladores, middleware, modelos y soporte de Laravel.

**Brechas**

- No hay resultados de pruebas de usabilidad, accesibilidad, compatibilidad entre navegadores/dispositivos ni rendimiento concurrente.
- En la repetición del 18 de noviembre, la suite completa reportó 5 errores por `ZipArchive` ausente y 2 fallos de aserción (se esperaba HTTP 422 y se recibió 200). Los grupos ejecutados por separado sí pasaron, pero eso no convierte la suite completa en aprobada.
- No se reportó cobertura porcentual de líneas o ramas.

**Conclusión:** existe evidencia de algunas características de calidad, pero no una evaluación completa contra el modelo ni criterios formales de aceptación para cada característica.

### ISO/IEC/IEEE 29119-2/-3/-4:2021 — pruebas de software

**Evidencia favorable**

- El proyecto cuenta con pruebas unitarias y de integración/funcionales en PHPUnit.
- Las carpetas `pruebas de caja negra`, `pruebas de caja blanca` y `pruebas complementarias` contienen casos, resultados, límites y una guía de evidencia.
- `phpunit.xml` configura SQLite en memoria para aislar las pruebas de la base de datos de la instalación.

**Brechas**

- El último resultado completo no está aprobado: 90 pruebas, 388 aserciones, 5 errores y 2 fallos. El PHP CLI usado tiene `pdo_sqlite`, pero no carga `zip`, requerido por los casos DOCX que usan `ZipArchive`.
- Las capturas visuales descritas en los informes son marcadores pendientes; no se ejecutaron pruebas manuales de navegador, cámara o móvil en esta repetición.
- No se encontró una matriz completa que vincule requisitos, riesgos, casos de prueba, ejecuciones, defectos y aprobación de responsables.
- La ausencia de Xdebug/PCOV significa que no hay reporte de cobertura.

**Conclusión:** los documentos y las suites son una base útil y se relacionan con documentación y ejecución de pruebas; no bastan para afirmar conformidad integral con la serie 29119.

### ISO/IEC 27001:2022 — seguridad de la información

**Controles técnicos observables**

- Autenticación basada en sesión; las contraseñas se guardan mediante el cast `hashed` de Laravel.
- Middleware con permisos por rol y lista permitida de acciones; hay límites de intentos en rutas de autenticación y otras rutas.
- El middleware `CabecerasSeguridad` agrega cabeceras como `X-Frame-Options`, `X-Content-Type-Options` y `Referrer-Policy`; agrega HSTS cuando la solicitud ya es HTTPS.
- La configuración de sesiones establece `HttpOnly` y `SameSite=Lax`; la guía de despliegue Render indica `APP_DEBUG=false` y `SESSION_SECURE_COOKIE=true`.
- Hay pruebas funcionales de roles, sesiones, límites, hashes y algunas cabeceras.

**Brechas que impiden declarar cumplimiento**

- ISO/IEC 27001 requiere un SGSI organizacional: alcance, evaluación y tratamiento de riesgos, responsables, políticas, objetivos, seguimiento, auditoría interna y mejora continua. Esos registros no están acreditados por el código.
- No se encontró evidencia suficiente de procedimientos aprobados de incidentes, respaldos y restauración probada, retención/eliminación de datos, revisión periódica de accesos o monitoreo y auditoría de operaciones.
- El sistema maneja datos personales escolares. La protección y el tratamiento de esos datos necesitan revisión institucional, legal y operativa; las pruebas técnicas aisladas no acreditan esa gestión.
- Las configuraciones de producción deben verificarse en el entorno desplegado. Los valores de ejemplo/locales no demuestran que HTTPS, cookies seguras y `APP_DEBUG=false` estén activos en producción.
- La suite completa presenta errores/fallos pendientes y los resultados de pruebas de carga, penetración y auditorías independientes no están disponibles en este repositorio.

**Conclusión:** hay controles técnicos alineados con algunos objetivos de seguridad, pero no hay evidencia para afirmar que SICA-EPO6 o su organización cumplan ISO/IEC 27001 ni para declarar una certificación.

## Acciones recomendadas

1. Habilitar `extension=zip` en el PHP CLI de pruebas y confirmar que `ZipArchive` carga; después repetir la suite completa.
2. Investigar los 2 fallos de aserción y documentar resultado, causa, corrección (si aplica) y nueva ejecución. No reportar la suite como aprobada hasta obtener un resultado verde.
3. Completar la matriz de trazabilidad requisito → riesgo → caso → resultado → evidencia, y sustituir cada marcador de captura solo con evidencia real anonimizada.
4. Aprobar criterios de calidad para accesibilidad/usabilidad, navegadores/dispositivos y rendimiento; medirlos en un entorno de prueba representativo.
5. Si se busca ISO/IEC 27001, definir con la institución el alcance del SGSI, responsables, inventario de activos, evaluación de riesgos, tratamiento/Declaración de Aplicabilidad, políticas, incidentes, continuidad, respaldos, auditoría y revisión directiva. Solicitar una evaluación independiente para cualquier declaración de certificación.
6. Revisar la configuración y los procedimientos de producción con responsables autorizados; no usar datos escolares reales para pruebas.

## Referencias

- ISO/IEC 25010:2023, modelo de calidad de producto: https://www.iso.org/standard/78176.html
- ISO/IEC 27001:2022, requisitos de SGSI: https://www.iso.org/standard/27001
- ISO/IEC/IEEE 29119-2:2021, procesos de prueba: https://www.iso.org/standard/79428.html
- ISO/IEC/IEEE 29119-3:2021, documentación de pruebas: https://www.iso.org/standard/79429.html
- ISO/IEC/IEEE 29119-4:2021, técnicas de prueba: https://www.iso.org/standard/79430.html
- Evidencia del repositorio: `phpunit.xml`, `routes/web.php`, `app/Http/Middleware/`, `app/Models/User.php`, `tests/` y las tres carpetas de informes de pruebas.
