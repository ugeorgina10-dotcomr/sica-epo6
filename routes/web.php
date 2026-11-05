<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClavesController;
use App\Http\Controllers\ControlEscolarController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// ===== Públicas =====

// Pantalla de inicio / login del sistema SICA
Route::get('/', function () {
    $u = auth()->user();

    return view('sica.index', [
        // Se incrusta en la página: así al abrir no hace falta pedir /auth/me ni /api/sica/orientadores
        'sesion' => $u ? ['rol' => $u->rol, 'nombre' => $u->name, 'email' => $u->email] : null,
        'orientadoresLogin' => ControlEscolarController::orientadoresLogin(),
        // Con sesión, el estado completo va dentro de la página: al recargar entra sin esperar otra petición
        'estado' => $u ? app(ControlEscolarController::class)->paraPagina() : null,
        // Control Escolar: el estado de las contraseñas también viene dentro de la página (el panel abre ya con todo dibujado)
        'claves' => ($u && $u->rol === 'control')
            ? ['etag' => ClavesController::etag($c = ClavesController::datos()), 'datos' => $c]
            : null,
    ]);
});

Route::get('/login', function () {
    return view('sica.dashboard');
})->name('login');

// Autenticación
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('auth.login');
// "Olvidé mi contraseña": no usa sesión ni token CSRF (se llama sin haber iniciado sesión y cualquiera podría llamarla igual;
// el límite de 5 por minuto la protege). Sin sesión se ahorra leer y escribir la tabla sessions en cada petición.
Route::post('/auth/olvide', [AuthController::class, 'olvide'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class])
    ->middleware('throttle:5,1,olvide')
    ->name('auth.olvide');
// Aviso para Control Escolar en la pantalla de inicio: quién pidió contraseña nueva (burbuja + sonido). Sin sesión, igual que /auth/olvide.
Route::get('/api/sica/olvidos', [AuthController::class, 'olvidos'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class])
    ->middleware('throttle:600,1,olvidos')
    ->name('sica.olvidos');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

// Solo nombres de orientadores, para llenar la lista de la pantalla de login.
// No incluye alumnos, CURP ni asistencias.
Route::get('/api/sica/orientadores', function () {
    return ControlEscolarController::orientadoresLogin();
})->middleware('throttle:30,1')->name('sica.orientadores');

// Plantilla oficial guardada en la base de datos (imagen, igual que las de public/img)
Route::get('/plantilla-oficial/{turno}/{lado}', [ControlEscolarController::class, 'verPlantillaOficial'])
    ->name('sica.plantilla.ver');

// ===== Requieren sesión iniciada =====
Route::middleware('auth')->group(function () {

    Route::get('/lector-fijo', function () {
        return view('sica.lector');
    })->name('lector');

    Route::get('/control-escolar', function () {
        return view('sica.control_escolar');
    })->name('control.escolar');

    // Estado completo del servidor (lo descargan computadoras y teléfonos)
    Route::get('/api/sica/estado', [ControlEscolarController::class, 'estado'])->name('sica.estado');

    // Sincronización general (localStorage -> MySQL) y escaneos del teléfono.
    // El guardia revisa el rol y el permiso según la acción.
    Route::post('/api/sica/sync', [ControlEscolarController::class, 'sync'])
        ->middleware('sica:sync')
        ->name('sica.sync');

    // Plantilla oficial de credenciales (frente / reverso): solo Control Escolar
    Route::post('/api/sica/plantilla-oficial/{lado}', [ControlEscolarController::class, 'plantillaOficial'])
        ->whereIn('lado', ['frente', 'reverso'])
        ->middleware('sica:control')
        ->name('sica.plantilla');

    Route::delete('/api/sica/plantilla-oficial/{turno}/{lado}', [ControlEscolarController::class, 'borrarPlantillaOficial'])
        ->middleware('sica:control')
        ->name('sica.plantilla.borrar');

    // Borra PARA SIEMPRE el archivo de Word anterior (el original de public/plantillas) cuando ya hay uno nuevo en uso
    Route::delete('/api/sica/plantilla-oficial/{turno}/{lado}/anterior', [ControlEscolarController::class, 'eliminarPlantillaAnterior'])
        ->middleware('sica:control')
        ->name('sica.plantilla.anterior');

    // Contraseñas: solo Control Escolar (cambiar la de cada rol y poner una propia a cada orientador).
    // La pantalla revisa el estado cada ~2.5 s (GET): antes compartía el límite de 20 por minuto con las acciones,
    // se lo acababa en medio minuto y entonces desbloquear / guardar fallaban o tardaban. Ahora cada cosa tiene su propio límite.
    Route::middleware('sica:control')->prefix('/api/sica/claves')->group(function () {
        Route::get('/', [ClavesController::class, 'index'])->middleware('throttle:300,1,claves-leer')->name('sica.claves');

        Route::middleware('throttle:90,1,claves')->group(function () {
            Route::post('/rol', [ClavesController::class, 'rol'])->name('sica.claves.rol');
            Route::post('/orientador', [ClavesController::class, 'orientador'])->name('sica.claves.orientador');
            Route::delete('/rol/{rol}', [ClavesController::class, 'borrarRol'])->name('sica.claves.borrar.rol');
            Route::post('/orientador/{orientador}/borrar', [ClavesController::class, 'borrarOrientador'])->name('sica.claves.borrar.orientador');
            Route::post('/rol/{rol}/desbloquear', [ClavesController::class, 'desbloquearRol'])->name('sica.claves.desbloquear.rol');
            Route::post('/orientador/{orientador}/desbloquear', [ClavesController::class, 'desbloquearOrientador'])->name('sica.claves.desbloquear.orientador');
            Route::delete('/orientador/{orientador}', [ClavesController::class, 'quitarOrientador'])->name('sica.claves.quitar');
        });
    });
});
