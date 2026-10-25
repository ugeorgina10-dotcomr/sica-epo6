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

## Plantillas de credencial (Word)
El diseño de la credencial es un archivo de Word (.docx): `FrenteMatutino.docx`, `FrenteVespertino.docx` y `Reverso.docx`
(carpeta `public/plantillas`). El sistema los lee y escribe encima nombre, CURP, grado, grupo, turno y el QR de cada alumno.
- Para cambiar el diseño: edita el Word, guárdalo como .docx y súbelo en **Portal del Alumno > Plantilla Oficial de Credenciales**
  (solo Control Escolar). Queda guardado en la base de datos y vale para todos los equipos.
  También puedes reemplazar los archivos de `public/plantillas`; esos se usan mientras no se suba uno nuevo.
- El frente debe tener un cuadro con el texto `QR` y las etiquetas `NOMBRE DEL ESTUDIANTE:`, `CURP:`, `GRADO:`, `GRUPO:`, `TURNO:`.
- El PDF se descarga en tamaño Carta u Oficio, con las credenciales a tamaño real.
- Si el Word usa elementos muy especiales (efectos, WordArt, formas rotadas) puede que no se dibujen igual: revisa la vista previa.
