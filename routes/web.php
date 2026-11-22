<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClavesController;
use App\Http\Controllers\ControlEscolarController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// ===== Públicas (no necesitan iniciar sesión) =====

// Pantalla de inicio y de inicio de sesión del sistema SICA
Route::get('/', function () {
    $u = auth()->user();

    return view('sica.index', [
        // Se incluye dentro de la página para que al abrirla no haga falta pedir esos datos por separado
        'sesion' => $u ? ['rol' => $u->rol, 'nombre' => $u->name, 'email' => $u->email] : null,
        'orientadoresLogin' => ControlEscolarController::orientadoresLogin(),
        // Si ya inició sesión, todos los datos van dentro de la página: al recargar entra sin esperar
        'estado' => $u ? app(ControlEscolarController::class)->paraPagina() : null,
        // Control Escolar: el estado de las contraseñas también viene en la página (el panel abre ya con todo listo)
        'claves' => ($u && $u->rol === 'control')
            ? ['etag' => ClavesController::etag($c = ClavesController::datos()), 'datos' => $c]
            : null,
    ]);
});

Route::get('/login', function () {
    return view('sica.dashboard');
})->name('login');

// Inicio y cierre de sesión
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('auth.login');
// "Olvidé mi contraseña": no necesita haber iniciado sesión (cualquiera podría usarla),
// por eso tiene un límite de 5 veces por minuto. Al no usar sesión, el sistema trabaja menos en cada petición.
Route::post('/auth/olvide', [AuthController::class, 'olvide'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class])
    ->middleware(['throttle:5,1,olvide', 'pulso:c'])   // pulso:c = el aviso le llega a Control Escolar al instante
    ->name('auth.olvide');
// Aviso para Control Escolar en la pantalla de inicio: quién pidió una contraseña nueva (burbuja y sonido). No necesita sesión, igual que /auth/olvide.
Route::get('/api/sica/olvidos', [AuthController::class, 'olvidos'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class])
    ->middleware('throttle:600,1,olvidos')
    ->name('sica.olvidos');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

// Solo nombres de orientadores, para llenar la lista del inicio de sesión.
// No incluye alumnos, CURP ni asistencias.
Route::get('/api/sica/orientadores', function () {
    return ControlEscolarController::orientadoresLogin();
})->middleware('throttle:30,1')->name('sica.orientadores');

// Plantilla oficial guardada en el sistema (como imagen, igual que las de public/img)
Route::get('/plantilla-oficial/{turno}/{lado}', [ControlEscolarController::class, 'verPlantillaOficial'])
    ->name('sica.plantilla.ver');

// ===== Requieren haber iniciado sesión =====
Route::middleware('auth')->group(function () {

    Route::get('/lector-fijo', function () {
        return view('sica.lector');
    })->name('lector');

    Route::get('/control-escolar', function () {
        return view('sica.control_escolar');
    })->name('control.escolar');

    // Todos los datos del sistema (los descargan computadoras y teléfonos)
    Route::get('/api/sica/estado', [ControlEscolarController::class, 'estado'])->name('sica.estado');

    // Guardado general de datos (del navegador al servidor) y escaneos del teléfono.
    // El sistema revisa el rol y el permiso según lo que se quiera hacer.
    Route::post('/api/sica/sync', [ControlEscolarController::class, 'sync'])
        ->middleware(['sica:sync', 'pulso:sync'])
        ->name('sica.sync');

    // Plantilla oficial de credenciales (frente y reverso): solo Control Escolar
    // (pulso:p = al subir o borrar, las demás computadoras y celulares ven la plantilla nueva al instante)
    Route::post('/api/sica/plantilla-oficial/{lado}', [ControlEscolarController::class, 'plantillaOficial'])
        ->whereIn('lado', ['frente', 'reverso'])
        ->middleware(['sica:control', 'pulso:p'])
        ->name('sica.plantilla');

    Route::delete('/api/sica/plantilla-oficial/{turno}/{lado}', [ControlEscolarController::class, 'borrarPlantillaOficial'])
        ->middleware(['sica:control', 'pulso:p'])
        ->name('sica.plantilla.borrar');

    // Borra PARA SIEMPRE el archivo de Word anterior (el original de public/plantillas) cuando ya se usa uno nuevo
    Route::delete('/api/sica/plantilla-oficial/{turno}/{lado}/anterior', [ControlEscolarController::class, 'eliminarPlantillaAnterior'])
        ->middleware(['sica:control', 'pulso:p'])
        ->name('sica.plantilla.anterior');

    // Contraseñas: solo Control Escolar (cambiar la de cada rol y poner una propia a cada orientador).
    // La pantalla revisa el estado cada ~2.5 segundos: antes compartía el límite de 20 por minuto con las demás acciones,
    // se agotaba en medio minuto y entonces desbloquear o guardar fallaban o tardaban. Ahora cada cosa tiene su propio límite.
    Route::middleware('sica:control')->prefix('/api/sica/claves')->group(function () {
        Route::get('/', [ClavesController::class, 'index'])->middleware('throttle:300,1,claves-leer')->name('sica.claves');

        Route::middleware(['throttle:90,1,claves', 'pulso:c'])->group(function () {
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
