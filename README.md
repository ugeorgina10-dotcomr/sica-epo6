# SICA-EPO6

Sistema de Identificacion y Control de Acceso (Laravel 12 + MySQL).

## Instalacion en XAMPP (Windows)
1. Copia esta carpeta a `C:\xampp\htdocs\sica-epo6`.
2. Abre el Panel de XAMPP y da **Start** en **MySQL**.
3. Haz doble clic en `instalar.bat` (crea el .env, la clave, la base de datos, las tablas y las cuentas).
4. Haz doble clic en `iniciar.bat` y se abre el sistema en http://127.0.0.1:8000
   (deja abierta la ventana negra mientras uses el sistema).

Requisitos: XAMPP con PHP 8.2 o mayor y Composer (https://getcomposer.org/download/) solo si falta la carpeta `vendor`.
En `C:\xampp\php\php.ini` deben estar activas (sin `;`): `extension=zip`, `extension=gd`, `extension=mbstring`, `extension=intl`, `extension=fileinfo`.

Cuentas iniciales: ver `database/seeders/UsuarioSeeder.php` (contrasena inicial `prepa06`; cambiala desde Control Escolar).
Si aparece "500 Server Error": pon `APP_DEBUG=true` en `.env` o revisa `storage/logs/laravel.log`.

## Plantillas de credencial (Word o PDF)
El diseño de la credencial es un archivo de Word (.docx) o un PDF: `FrenteMatutino.docx`, `FrenteVespertino.docx` y `Reverso.docx`
(carpeta `public/plantillas`). El sistema los lee y escribe encima nombre, CURP, grado, grupo, turno y el QR de cada alumno,
todo con la misma letra y el mismo tamaño.

**Cómo cambiar el diseño (paso a paso):**
1. Haz el diseño en Word y guárdalo como `.docx`, o expórtalo como PDF.
2. Entra a **Portal del Alumno > Plantilla Oficial de Credenciales** (solo Control Escolar) y elige el turno (Matutino / Vespertino).
3. En "Frente" o "Reverso" pulsa **Elegir archivo** y luego **Actualizar**. Queda guardado y vale para todos los equipos.

También puedes reemplazar los archivos de `public/plantillas`; esos se usan mientras no se suba uno nuevo.

**Reglas del frente:** debe tener un cuadro con el texto `QR` y las etiquetas `NOMBRE DEL ESTUDIANTE:`, `CURP:`, `GRADO:`, `GRUPO:`, `TURNO:`
(opcional: un cuadro con el texto `FOTOGRAFÍA`). Deja libre el espacio junto a las etiquetas: ahí escribe el sistema.
- **Si es PDF:** el texto debe poder seleccionarse (no una foto) y se usa solo la primera hoja.
- Si el nombre es muy largo, baja la letra de todos los datos juntos para que siempre queden del mismo tamaño.
- El nombre va debajo de su etiqueta; CURP, grado, grupo y turno van al frente (a la derecha de su etiqueta). Para que la letra salga lo más grande posible, deja espacio libre entre "GRADO:" y "GRUPO:" y no pongas el cuadro del QR junto a la CURP.
- El PDF de credenciales se descarga en tamaño Carta u Oficio, con las credenciales a tamaño real.
- Si el diseño usa elementos muy especiales (efectos, WordArt, formas rotadas) puede que no se dibujen igual: revisa la vista previa.
