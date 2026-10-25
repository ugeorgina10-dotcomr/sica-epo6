<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClavesController;
use App\Http\Controllers\ControlEscolarController;
use Illuminate\Support\Facades\Route;

// ===== Públicas =====

// Pantalla de inicio / login del sistema SICA
Route::get('/', function () {
    $u = auth()->user();

    return view('sica.index', [
        // Se incrusta en la página: así al abrir no hace falta pedir /auth/me ni /api/sica/orientadores
        'sesion' => $u ? ['rol' => $u->rol, 'nombre' => $u->name, 'email' => $u->email] : null,
        'orientadoresLogin' => ControlEscolarController::orientadoresLogin(),
    ]);
});

Route::get('/login', function () {
    return view('sica.dashboard');
})->name('login');

// Autenticación
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('auth.login');
Route::post('/auth/olvide', [AuthController::class, 'olvide'])->middleware('throttle:5,1,olvide')->name('auth.olvide');
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

    // Contraseñas: solo Control Escolar (cambiar la de cada rol y poner una propia a cada orientador)
    Route::middleware(['sica:control', 'throttle:20,1,claves'])->prefix('/api/sica/claves')->group(function () {
        Route::get('/', [ClavesController::class, 'index'])->name('sica.claves');
        Route::post('/rol', [ClavesController::class, 'rol'])->name('sica.claves.rol');
        Route::post('/orientador', [ClavesController::class, 'orientador'])->name('sica.claves.orientador');
        Route::delete('/rol/{rol}', [ClavesController::class, 'borrarRol'])->name('sica.claves.borrar.rol');
        Route::post('/orientador/{orientador}/borrar', [ClavesController::class, 'borrarOrientador'])->name('sica.claves.borrar.orientador');
        Route::post('/rol/{rol}/desbloquear', [ClavesController::class, 'desbloquearRol'])->name('sica.claves.desbloquear.rol');
        Route::post('/orientador/{orientador}/desbloquear', [ClavesController::class, 'desbloquearOrientador'])->name('sica.claves.desbloquear.orientador');
        Route::delete('/orientador/{orientador}', [ClavesController::class, 'quitarOrientador'])->name('sica.claves.quitar');
    });
});
