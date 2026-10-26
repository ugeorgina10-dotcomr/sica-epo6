<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#800020">
<title>SICA-EPO6 | Sistema de Identificación y Control de Acceso</title>
<link rel="manifest" href="/manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="SICA-EPO6">
<link rel="apple-touch-icon" href="{{ asset('img/logo6.png') }}">
{{-- Librerías servidas desde public/vendor (sin CDN): así el sistema abre aunque no haya internet --}}
<link href="{{ asset('vendor/bootstrap.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
<style>
body{background:#f4f6f9;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;color:#333;margin:0}
/* Fondo epo6.png: public/img/epo6.png. Se ve a partir del login, con brillo y color originales. */
.bg-escuela{background:linear-gradient(rgba(0,0,0,.12),rgba(0,0,0,.12)),url("{{ asset('img/epo6.png') }}") center/cover no-repeat fixed !important;min-height:100vh}
#input-clave::-ms-reveal,#input-clave::-ms-clear{display:none}
.bg-login{background:#fff !important;min-height:100vh}
.main-card{background:rgba(255,255,255,.95);border-radius:20px;box-shadow:0 20px 40px rgba(0,0,0,.3);border:1px solid rgba(128,0,32,.25)}
.login-card{background:rgba(255,255,255,.96);border-radius:20px;box-shadow:0 15px 40px rgba(0,0,0,.35);border:1px solid #e2e8f0}
.btn-vinotinto{background:#800020;color:#fff;font-weight:600;transition:.12s}
.casilla{display:inline-block;width:16px;height:16px;margin-right:8px;vertical-align:-3px;border:2px solid #adb5bd;border-radius:4px;background:#fff;position:relative;transition:.12s}
#requisitos-clave li.cumple .casilla{background:#198754;border-color:#198754}
#requisitos-clave li.cumple .casilla::after{content:"";position:absolute;left:3px;top:0;width:4px;height:8px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
.btn-vinotinto:hover{background:#600018;color:#fff}
.institution-logo{width:130px;height:130px;object-fit:contain}
.small-logo{width:75px;height:75px;object-fit:contain}
.cct-text{font-size:.9rem;color:#444;letter-spacing:.5px;font-weight:500}
.profile-card{border:2px solid #dee2e6;border-radius:12px;cursor:pointer;transition:.12s;background:#fff}
.profile-card.active{border-color:#800020;background:#fdf8f8}
.camera-box-ref{width:100%;max-width:320px;min-height:200px;background:#111;border-radius:10px;margin:0 auto;position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-direction:column}
.camera-box-ref video{width:100%}
.form-control,.form-select{border-radius:10px}
.form-label{font-weight:600;color:#2c3e50}
.bloqueado-permiso{opacity:.55;cursor:not-allowed !important}
.res-scan{min-height:24px;font-weight:600;font-size:.85rem}
.tarjeta-grado{cursor:pointer;transition:.2s}
.tarjeta-grado:hover{box-shadow:0 6px 16px rgba(0,0,0,.12)}
.tarjeta-grado.seleccionada{border:2px solid #800020 !important;background:#fdf8f8 !important}
.tarjeta-grado.grado-desactivado{opacity:.4;pointer-events:none;cursor:not-allowed}
.preview-credencial{width:100%;max-width:340px;border:1px solid #dee2e6;border-radius:10px;background:#eee;display:block;margin:0 auto}
.preview-plantilla{height:240px;object-fit:contain;object-position:center;background:#fff}
/* Vista previa de la plantilla oficial: frente y reverso con el mismo alto y alineados arriba */
.slot-prev-plantilla{min-height:150px;display:flex;align-items:flex-start;justify-content:center}
.slot-prev-plantilla canvas.preview-credencial{max-width:100%;margin:0}
.badge-asignado{font-size:.7rem}
/* ===== Responsivo ===== */
@media (max-width:767.98px){
.form-control,.form-select{font-size:16px;min-height:46px} /* 16px evita el zoom automático de iPhone */
.btn{min-height:44px}
.main-card,.login-card{padding:1.5rem !important;border-radius:16px}
.institution-logo{width:90px;height:90px}
.small-logo{width:55px;height:55px}
h2.fw-bold{font-size:1.4rem}
h5.fw-bold{font-size:1.05rem}
#selector-pestanas-superior .profile-card{padding:.75rem !important}
#selector-pestanas-superior .profile-card i{font-size:1.1rem !important}
#selector-pestanas-superior .profile-card h6{font-size:.75rem}
.camera-box-ref{max-width:100%}
.table-responsive table{font-size:.75rem}
.d-flex.justify-content-between.align-items-center.mb-2.flex-wrap.gap-2 .badge{font-size:.75rem}
#stats-grados .grado-btn{font-size:.8rem}
}
@media (max-width:575.98px){
.btn-lg.rounded-pill{padding:.75rem 1.25rem !important;font-size:1rem !important}
.row.g-2.mb-4.justify-content-center .btn{font-size:.75rem}
#vista-sistema .d-flex.justify-content-between.align-items-center.mb-2.flex-wrap.gap-2{flex-direction:column;align-items:flex-start !important;gap:.75rem !important}
#vista-sistema .d-flex.align-items-center.gap-2{width:100%;justify-content:space-between}
.d-flex.justify-content-center.gap-3.mb-3.flex-wrap .btn{flex:1 1 45%;font-size:.8rem;white-space:normal}
.d-flex.gap-2.flex-wrap .btn{flex:1 1 100%}
#panel-control .row.text-center.mb-4.g-3 > div{margin-bottom:.5rem}
.card.main-card{padding:1rem !important}
}
@media (max-width:400px){
.institution-logo{width:70px;height:70px}
h2.fw-bold{font-size:1.15rem}
.btn{font-size:.8rem}
}
canvas#grafica-asistencias{max-width:100%;height:auto !important}
html{-webkit-text-size-adjust:100%}
body{padding-bottom:env(safe-area-inset-bottom)}
body{overflow-x:hidden}
/* ===== MODO TELÉFONO (orientador): solo escanear ===== */
body.modo-movil #selector-pestanas-superior,
body.modo-movil #panel-orientador > *:not(#bloque-camara-orientador):not(#bloque-mi-grupo-orientador):not(#bloque-buscador-orientador):not(#bloque-tabla-orientador),
body.modo-movil #btn-regresar-panel{display:none !important}
body.modo-movil #vista-sistema{padding:.75rem !important}
body.modo-movil .camera-box-ref{max-width:100%;min-height:260px}
body.modo-movil #res-cam-orient{font-size:1.1rem;min-height:40px}
/* En el teléfono el orientador también ve su grupo y la lista de alumnos (cámara arriba, lista abajo) */
body.modo-movil #bloque-camara-orientador{margin-bottom:1rem !important}
body.modo-movil #bloque-buscador-orientador .col-md-3,
body.modo-movil #bloque-tabla-orientador > div:first-child .d-flex.gap-2{display:none !important}
body.modo-movil #bloque-tabla-orientador th:nth-child(1),body.modo-movil #bloque-tabla-orientador td:nth-child(1),
body.modo-movil #bloque-tabla-orientador th:nth-child(3),body.modo-movil #bloque-tabla-orientador td:nth-child(3),
body.modo-movil #bloque-tabla-orientador th:nth-child(4),body.modo-movil #bloque-tabla-orientador td:nth-child(4),
body.modo-movil #bloque-tabla-orientador th:nth-child(7),body.modo-movil #bloque-tabla-orientador td:nth-child(7),
body.modo-movil #bloque-tabla-orientador th:nth-child(9),body.modo-movil #bloque-tabla-orientador td:nth-child(9){display:none}
body.modo-movil #bloque-camara-orientador .btn{width:100%;margin-bottom:.5rem;padding:.9rem}
/* ---- Orientadores: lista desplegable ---- */
.ori-desplegable{border:1.5px solid #d9c3c8;border-radius:.75rem;background:#fff;overflow:hidden}
.ori-desplegable>summary{cursor:pointer;padding:.55rem .9rem;font-weight:600;color:#5a1020;list-style:none}
.ori-desplegable>summary::-webkit-details-marker{display:none}
.ori-desplegable>summary::after{content:"\25BE";float:right}
.ori-desplegable[open]>summary::after{content:"\25B4"}
.ori-desplegable[open]>summary{border-bottom:1px solid #eee}
.ori-lista{max-height:260px;overflow-y:auto}
.ori-item{display:flex;justify-content:space-between;align-items:center;gap:.75rem;padding:.5rem .9rem;border-bottom:1px solid #f1e6e8}
.ori-item:last-child{border-bottom:0}
/* ---- Contraseñas: botones de cuenta y ventana central ---- */
.btn-cuenta{border:1.5px solid #d9c3c8;background:#fff;color:#5a1020;border-radius:999px;padding:.4rem .9rem;font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:.45rem;cursor:pointer;transition:all .15s}
.btn-cuenta:hover{border-color:#800020;background:#fdf3f5}
.btn-cuenta.activa{background:#800020;border-color:#800020;color:#fff}
.btn-cuenta.alerta:not(.activa){border-color:#dc3545}
.btn-cuenta .etq{font-size:.68rem;font-weight:600;border-radius:999px;padding:.1rem .5rem;background:#e9ecef;color:#495057}
.btn-cuenta .etq.mal{background:#dc3545;color:#fff}
.btn-cuenta .etq.bien{background:#d1e7dd;color:#0f5132}
.btn-cuenta.activa .etq{background:rgba(255,255,255,.22);color:#fff}
.modal-cuenta{position:fixed;inset:0;z-index:2000;background:rgba(30,0,8,.62);display:flex;align-items:center;justify-content:center;padding:1rem}
.modal-cuenta.d-none{display:none!important}
.modal-cuenta-caja{background:#fff;border-radius:1.1rem;max-width:440px;width:100%;padding:2rem 1.6rem 1.6rem;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4);border-top:6px solid #800020}
.modal-cuenta-icono{width:64px;height:64px;border-radius:50%;background:#fdf3f5;color:#800020;font-size:1.7rem;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem}
.modal-cuenta-caja h5{font-weight:700;color:#800020}
.modal-cuenta-caja p{color:#444;font-size:.95rem}
.dd-ori{position:relative;display:inline-block}
.dd-menu{position:absolute;top:calc(100% + 6px);left:0;min-width:290px;max-height:260px;overflow:auto;background:#fff;border:1px solid #d9c3c8;border-radius:.8rem;box-shadow:0 10px 30px rgba(0,0,0,.18);z-index:30;padding:.35rem}
.dd-menu.d-none{display:none!important}
.dd-item{display:flex;width:100%;justify-content:space-between;align-items:center;gap:.6rem;border:0;background:transparent;text-align:left;padding:.45rem .6rem;border-radius:.5rem;font-size:.88rem;color:#333;cursor:pointer}
.dd-item:hover{background:#fdf3f5}
.dd-item .etq{font-size:.68rem;font-weight:600;border-radius:999px;padding:.1rem .5rem;background:#e9ecef;color:#495057}
.dd-item .etq.mal{background:#dc3545;color:#fff}
.dd-item .etq.bien{background:#d1e7dd;color:#0f5132}
.burbujas-claves{position:fixed;top:14px;right:14px;z-index:1900;display:flex;flex-direction:column;gap:10px;max-width:340px;width:calc(100% - 28px)}
.burbuja{position:relative;background:#800020;color:#fff;border-radius:1.1rem 1.1rem .3rem 1.1rem;padding:.8rem 2rem .8rem 1rem;box-shadow:0 8px 24px rgba(0,0,0,.3);font-size:.9rem;animation:burbujaEntra .35s ease-out}
.burbuja-x{position:absolute;top:4px;right:10px;border:0;background:transparent;color:#fff;font-size:1.3rem;line-height:1;cursor:pointer;opacity:.8}
@keyframes burbujaEntra{from{opacity:0;transform:translateY(-12px) scale(.95)}to{opacity:1;transform:none}}
</style>
</head>
<body id="cuerpo-contenedor" class="bg-login">
<div class="container d-flex flex-column justify-content-center align-items-center min-vh-100 py-4">
<!-- ============ INICIO ============ -->
<div id="vista-inicio" class="card main-card p-4 p-md-5 text-center col-12 col-md-8 col-lg-6">
 <div class="d-flex justify-content-center mb-3">
 <img src="{{ asset('img/logo6.png') }}" alt="Logo EPO6" class="institution-logo">
 </div>
<h2 class="fw-bold mb-1">SICA - EPO6</h2>
<p class="text-muted fw-semibold mb-3">Sistema de Identificación y Control de Acceso</p>
<div class="mb-4 bg-light p-3 rounded-4 border">
 <h5 class="fw-bold text-uppercase mb-1" style="color:#800020">Escuela Preparatoria Oficial Núm. 6</h5>
 <p class="cct-text mb-0">C.C.T. 15EBH0008G (Matutino) &nbsp;|&nbsp; C.C.T. 15EBH0199N (Vespertino)</p>
</div>
 <button onclick="mostrarSeccion('vista-login')" class="btn btn-vinotinto btn-lg rounded-pill py-3 px-5 shadow fs-5">
 INGRESAR AL SISTEMA
</button>
</div>
<!-- ============ LOGIN ============ -->
<div id="vista-login" class="card login-card p-4 p-md-5 col-12 col-sm-10 col-md-7 col-lg-5 d-none">
<div class="text-center mb-2">
 <img src="{{ asset('img/logo6.png') }}" alt="Logo EPO6" class="small-logo mb-2">
 <h4 class="fw-bold mb-1">SICA - EPO6</h4>
 <p class="text-muted small mb-2">Sistema de Identificación y Control de Acceso</p>
 <hr class="w-25 mx-auto my-2" style="border-top:2px solid #800020">
 <h5 class="fw-bold m-0" style="color:#800020">Iniciar Sesión</h5>
</div>
<form id="form-login" onsubmit="return false">
 <div class="mb-3">
 <label class="form-label">Seleccionar Rol</label>
 <select id="rol-select" class="form-select shadow-sm" onchange="actualizarCredenciales()">
 <option value="orientador">Orientador</option>
 <option value="director">Director</option>
 <option value="subdirector">Subdirector</option>
 <option value="control">Encargado de Control Escolar</option>
 </select>
 </div>
 <!-- Solo aparece cuando el rol elegido es Orientador: selecciona su nombre de la lista que
 subió Control Escolar. El grupo se calcula solo, no lo elige el orientador. -->
 <div id="campo-orientador-login" class="mb-3 d-none">
 <label class="form-label">Selecciona tu nombre</label>
 <select id="select-orientador-login" class="form-select shadow-sm">
 <option value="" selected disabled>Selecciona tu nombre...</option>
 </select>
 <div id="aviso-sin-orientadores" class="form-text text-danger d-none">
 Aún no hay orientadores dados de alta. Control Escolar debe subir la lista de orientadores primero.
 </div>
 </div>
 <div class="mb-3">
 <label class="form-label">Correo Institucional</label>
 <input type="email" id="input-correo" class="form-control shadow-sm" autocomplete="username" readonly>
 </div>
 <div class="mb-4">
 <label class="form-label">Contraseña</label>
 <div class="position-relative shadow-sm rounded">
 <input type="password" id="input-clave" class="form-control pe-5" placeholder="Escribe tu contraseña" autocomplete="current-password" onkeydown="if(event.key==='Enter') intentarEntrar()">
 <button type="button" id="btn-ver-clave" onclick="verOcultarClave()" aria-label="Mostrar contraseña" aria-pressed="false" title="Mostrar contraseña"
 style="position:absolute;top:0;bottom:0;right:0;width:46px;border:0;background:transparent;color:#6c757d;display:flex;align-items:center;justify-content:center;cursor:pointer">
 <svg id="ojo-abierto" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
 <svg id="ojo-cerrado" class="d-none" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
 </button>
 </div>
 </div>
 <div class="text-end mb-3" style="margin-top:-1rem"><a href="#" class="small text-muted text-decoration-underline" onclick="olvideContrasena(); return false;">¿Olvidaste tu contraseña?</a></div>
 <button type="button" onclick="intentarEntrar()" class="btn btn-vinotinto w-100 py-3 rounded-3 shadow fs-5">Ingresar al Sistema</button>
</form>
</div>
<!-- ============ SISTEMA ============ -->
<div id="vista-sistema" class="card main-card p-3 p-md-4 col-12 col-lg-11 d-none text-start">
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
 <div class="d-flex align-items-center gap-3">
 <img src="{{ asset('img/logo6.png') }}" alt="Logo EPO6" class="small-logo">
 <div>
 <h5 class="fw-bold text-uppercase m-0" style="color:#800020">Escuela Preparatoria Oficial Núm. 6</h5>
 <p class="cct-text m-0 small">C.C.T. 15EBH0008G (Matutino) - C.C.T. 15EBH0199N (Vespertino)</p>
 </div>
 </div>
 <div class="d-flex align-items-center gap-2">
 <span id="badge-rol-activo" class="badge bg-dark px-3 py-2 fs-6">Rol: --</span>
 <button onclick="location.reload()" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm">
 Recargar Sistema
 </button>
 </div>
</div>
<hr class="mt-2 mb-3">
<div id="selector-pestanas-superior" class="mb-4">
 <label class="form-label mb-2 text-uppercase fs-6">Selecciona tu sección:</label>
 <div class="row g-2 text-center justify-content-center" id="fila-pestanas">
 @foreach([['alumno','fa-user-graduate','Portal del Alumno'],['lector','fa-qrcode','Lector Fijo (Entrada/Salida)'],['orientador','fa-users-rectangle','Panel de Orientadores'],['control','fa-user-gear','Control Escolar']] as $t)
 <div class="col-md-3 col-tab-pestana">
 <div id="tab-{{ $t[0] }}" onclick="seleccionarPerfil('{{ $t[0] }}')" class="profile-card p-3">
 <i class="fa-solid {{ $t[1] }} mb-1 text-secondary" style="font-size:1.4rem"></i>
 <h6 class="fw-bold m-0 text-dark small">{{ $t[2] }}</h6>
 </div>
 </div>
 @endforeach
 </div>
</div>
<!-- ===== 1. PORTAL DEL ALUMNO ===== -->
<div id="panel-alumno" class="panel-perfil d-none">
 <!-- ===== PLANTILLA OFICIAL DE CREDENCIALES (solo Control Escolar, dentro del Portal del Alumno) ===== -->
 <div id="bloque-plantilla-oficial" class="p-4 bg-white rounded-4 border shadow-sm mb-4 d-none">
 <h6 class="fw-bold mb-2" style="color:#800020">Plantilla Oficial de Credenciales <span id="turno-plantilla-actual" class="badge bg-secondary ms-2 align-middle">Matutino</span></h6>
 <p class="text-muted small mb-2">Aquí se suben los <b>diseños de la credencial hechos en Word (.docx) o en PDF</b>: uno de <b>frente</b> y uno de <b>reverso</b> por turno. Valen para <b>todos los grupos del turno elegido</b> (usa los botones Matutino / Vespertino de abajo para cambiar de turno; cada turno tiene su propio frente porque cambia el C.C.T.).</p>
 <p class="text-muted small mb-2">Cuando quieras cambiar el diseño, edítalo en Word y <b>guárdalo como .docx</b> (o expórtalo como <b>PDF</b>), luego elige el archivo aquí abajo y pulsa <b>Actualizar</b>: el sistema vuelve a acomodar solo el nombre, CURP, grado, grupo, turno y el código QR de cada alumno, todos con la <b>misma letra y el mismo tamaño</b>. En el frente deben existir el cuadro con el texto <b>QR</b> y las etiquetas <b>NOMBRE DEL ESTUDIANTE:, CURP:, GRADO:, GRUPO:, TURNO:</b>; no escribas nada a un lado de ellas, porque ahí escribe el sistema.</p>
 <p class="text-muted small mb-3">Si tu archivo es PDF, el texto debe poder seleccionarse (no una foto de la credencial) y se usa solo la <b>primera hoja</b>.</p>
 <div class="row g-3">
 <div class="col-md-6">
 <label class="form-label small">Frente (Word .docx o PDF)</label>
 <div class="input-group input-group-sm mb-2">
 <input type="file" class="form-control" id="input-plantilla-oficial-frente" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
 <button class="btn btn-vinotinto" type="button" onclick="subirPlantillaOficial('frente')">Actualizar</button>
 </div>
 <div class="slot-prev-plantilla">
 <canvas id="prev-plantilla-oficial-frente" class="preview-credencial" width="380" height="240" style="display:none"></canvas>
 </div>
 <div id="msg-prev-plantilla-frente" class="small text-muted text-center mt-1"></div>
 </div>
 <div class="col-md-6">
 <label class="form-label small">Reverso (Word .docx o PDF)</label>
 <div class="input-group input-group-sm mb-2">
 <input type="file" class="form-control" id="input-plantilla-oficial-reverso" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
 <button class="btn btn-vinotinto" type="button" onclick="subirPlantillaOficial('reverso')">Actualizar</button>
 </div>
 <div class="slot-prev-plantilla">
 <canvas id="prev-plantilla-oficial-reverso" class="preview-credencial" width="380" height="240" style="display:none"></canvas>
 </div>
 <div id="msg-prev-plantilla-reverso" class="small text-muted text-center mt-1"></div>
 <div class="form-check small mt-2"><input class="form-check-input" type="checkbox" id="plantilla-ambos-turnos" checked><label class="form-check-label" for="plantilla-ambos-turnos">Usar este reverso en los dos turnos</label></div>
 </div>
 </div>
 <div class="d-flex flex-wrap gap-2 mt-3">
 <button id="btn-restaurar-plantilla" class="btn btn-outline-dark btn-sm" onclick="restaurarPlantillaOficial()">
 Restaurar plantilla original
 </button>
 <button id="btn-eliminar-anterior" class="btn btn-outline-danger btn-sm" onclick="eliminarPlantillaAnterior()" title="Borra para siempre el archivo anterior (solo si ya subiste uno nuevo)">
 Eliminar plantilla anterior
 </button>
 </div>
 <div id="resumen-plantilla-oficial" class="mt-2 small fw-semibold"></div>
 </div>

 
 <div class="text-center p-4 bg-light rounded-4 border mb-3">
 <h5 class="fw-bold text-uppercase mb-2" style="color:#800020">Credencial digital con código QR</h5>
 <p class="text-muted small mb-3">Elige el turno, selecciona el grupo y administra las credenciales. Cada turno usa su plantilla oficial (frente y reverso); solo el Encargado de Control Escolar puede cambiarla (bloque "Plantilla Oficial de Credenciales" de arriba).</p>
 <div class="d-flex justify-content-center gap-3 mb-3 flex-wrap">
 <button onclick="setTurnoCred('Matutino')" id="btn-turno-cred-matutino" class="btn btn-vinotinto px-4 py-2 fw-semibold shadow-sm small">Matutino</button>
 <button onclick="setTurnoCred('Vespertino')" id="btn-turno-cred-vespertino" class="btn btn-outline-dark bg-white px-4 py-2 fw-semibold shadow-sm small">Vespertino</button>
 </div>
 <div class="row g-2 mb-4 justify-content-center">
 @foreach(['Primero','Segundo','Tercero'] as $i => $gr)
 @foreach([1,2,3,4] as $n)
 <div class="col-lg-2 col-md-3 col-sm-4">
 <button onclick="selCred({{ $i+1 }},{{ $n }})" class="btn btn-outline-dark w-100 py-2 bg-white fw-semibold border shadow-sm small">{{ $gr }} {{ $n }}</button>
 </div>
 @endforeach
 @endforeach
 </div>
 <div id="resultado-credencial-box" class="d-none p-4 bg-white rounded-3 border shadow-sm mb-3 col-md-10 mx-auto text-start">
 <div class="d-flex justify-content-between mb-3">
 <h6 class="fw-bold m-0 text-danger" id="titulo-grupo-credencial"></h6>
 <span id="badge-plantilla" class="badge bg-secondary">Plantilla oficial</span>
 </div>
 <div class="table-responsive bg-white rounded-3 border mb-3">
 <table class="table table-striped table-hover align-middle mb-0 small">
 <thead class="table-dark"><tr><th>Matrícula (CURP)</th><th>Nombre Completo</th><th class="text-center">Acción</th></tr></thead>
 <tbody id="tabla-alumnos-credencial"></tbody>
 </table>
 </div>
 <div class="d-flex align-items-center gap-2 mb-2 flex-wrap"><label class="small fw-semibold m-0" for="sel-hoja-pdf-grupo">Tamaño de hoja al imprimir:</label><select id="sel-hoja-pdf-grupo" class="form-select form-select-sm sel-hoja-pdf" style="max-width:250px" onchange="cambiarHoja(this.value)"><option value="carta">Carta (21.6 × 27.9 cm)</option><option value="oficio">Oficio (21.6 × 33.0 cm)</option></select></div>
 <button id="btn-pdf-grupo" onclick="descargarCredencialesPDF()" class="btn btn-vinotinto w-100 py-3 shadow-sm btn-accion-restringida">
 Descargar Credenciales Completas del Grupo (Frente y Reverso)
 </button>
 </div>
 <div id="vista-editar-credencial-individual" class="d-none p-4 bg-white rounded-3 border shadow-sm mb-3 col-md-10 mx-auto text-start">
 <div class="d-flex justify-content-between mb-3">
 <h6 class="fw-bold m-0 text-danger">Editar Credencial Individual</h6>
 <button onclick="regresarListaGrupo()" class="btn btn-outline-dark btn-sm">Regresar a Todos</button>
 </div>
 <div class="row">
 <div class="col-md-6">
 <div class="mb-3"><label class="form-label small">Matrícula (CURP)</label>
 <input type="text" id="edit-curp-alumno" class="form-control form-control-sm" oninput="actualizarVistaPreviaCredencial()"></div>
 <div class="mb-3"><label class="form-label small">Nombre Completo (Apellido paterno, Apellido materno y Nombre)</label>
 <input type="text" id="edit-nombre-alumno" class="form-control form-control-sm" oninput="actualizarVistaPreviaCredencial()"></div>
 <div class="d-flex align-items-center gap-2 mb-2 flex-wrap"><label class="small fw-semibold m-0" for="sel-hoja-pdf-individual">Tamaño de hoja al imprimir:</label><select id="sel-hoja-pdf-individual" class="form-select form-select-sm sel-hoja-pdf" style="max-width:250px" onchange="cambiarHoja(this.value)"><option value="carta">Carta (21.6 × 27.9 cm)</option><option value="oficio">Oficio (21.6 × 33.0 cm)</option></select></div>
  <div class="d-flex gap-2">
 <button onclick="guardarCambiosCredencial()" class="btn btn-success w-100 btn-accion-restringida">Guardar Cambios</button>
 <button id="btn-pdf-individual" onclick="descargarCredencialIndividual()" class="btn btn-vinotinto w-100 btn-accion-restringida">Descargar PDF</button>
 </div>
 </div>
 <div class="col-md-6">
 <label class="form-label small d-block text-center">Vista previa (se actualiza mientras editas)</label>
 <div class="row g-2">
 <div class="col-12 text-center">
 <span class="d-block small text-muted mb-1">Frente</span>
 <canvas id="preview-canvas-frente" width="380" height="240" class="preview-credencial" style="max-width:480px"></canvas>
 </div>
 <div class="col-12 text-center">
 <span class="d-block small text-muted mb-1">Reverso</span>
 <canvas id="preview-canvas-reverso" width="380" height="240" class="preview-credencial" style="max-width:480px"></canvas>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
</div>
<!-- ===== 2. LECTOR FIJO (no visible para Director/Subdirector) ===== -->
<div id="panel-lector" class="panel-perfil d-none">
 <div class="text-center p-4 bg-light rounded-4 border mb-3">
 <h5 class="fw-bold text-uppercase mb-1" style="color:#800020">Lector Fijo - Control de Entradas y Salidas</h5>
 <p class="text-muted small mb-3">Elige el tipo de escaneo, enciende la cámara y muestra el QR de la credencial:</p>
 <div class="d-flex justify-content-center gap-3 mb-3 flex-wrap">
 <button onclick="setModoEscaneo('Entrada')" id="btn-modo-entrada" class="btn btn-success px-4 py-2 fw-semibold shadow-sm">Registrar Entrada</button>
 <button onclick="setModoEscaneo('Salida')" id="btn-modo-salida" class="btn btn-outline-dark bg-white px-4 py-2 fw-semibold shadow-sm">Registrar Salida</button>
 </div>
 <div class="camera-box-ref mb-2 border border-danger shadow">
 <div class="cam-ph text-center text-warning"><span class="small d-block fw-semibold">[ <span id="lbl-modo">Entrada</span> ] - Cámara apagada</span></div>
 <div id="cam-fija" style="width:100%"></div>
 </div>
 <div id="res-cam-fija" class="res-scan mb-2"></div>
 <button onclick="toggleCamaraFija()" class="btn btn-vinotinto px-4">Encender / Apagar Cámara</button>
 </div>
</div>
<!-- ===== 3. PANEL DE ORIENTADORES ===== -->
<div id="panel-orientador" class="panel-perfil d-none">
 <div id="bloque-turno-panel" class="d-flex justify-content-center gap-3 mb-3 flex-wrap">
 <button onclick="setTurno('Matutino')" id="btn-turno-matutino" class="btn btn-vinotinto px-4 py-2 fw-semibold shadow-sm small">Turno Matutino</button>
 <button onclick="setTurno('Vespertino')" id="btn-turno-vespertino" class="btn btn-outline-dark bg-white px-4 py-2 fw-semibold shadow-sm small">Turno Vespertino</button>
 </div>
 <!-- VISUALIZAR GRUPO: para Control Escolar, Director y Subdirector (eligen cualquier grupo) -->
 <div id="bloque-visualizar-grupo" class="mb-3">
 <label class="form-label mb-2 text-uppercase fs-6">Visualizar Grupo:</label>
 <div class="row g-2">
 @foreach([1,2,3] as $g)
 <div class="col-md-4">
 <label class="form-label small mb-1">Grado {{ $g }}</label>
 <select id="sel-grado-{{ $g }}" class="form-select form-select-sm shadow-sm" onchange="elegirGrupo({{ $g }},this.value)">
 <option value="" selected disabled>Selecciona el grupo...</option>
 @foreach([1,2,3,4] as $n)<option value="{{ $n }}">Grupo {{ $n }}</option>@endforeach
 </select>
 </div>
 @endforeach
 </div>
 </div>
 <!-- MI GRUPO: solo para el rol Orientador. Su grupo lo asigna Control Escolar, aquí no lo elige. -->
 <div id="bloque-mi-grupo-orientador" class="mb-3 d-none">
 <label class="form-label mb-2 text-uppercase fs-6">Mi Grupo Asignado:</label>
 <div class="p-3 bg-light rounded-3 border d-flex align-items-center gap-3 flex-wrap">
 <div id="sin-grupo-orientador" class="text-danger fw-semibold small d-none">
 Aún no tienes un grupo asignado. Solicita a Control Escolar que te asigne uno.
 </div>
 <div id="con-grupo-orientador" class="d-none w-100">
 <div class="row g-2 align-items-end">
 <div class="col-md-8">
 <label class="form-label small mb-1">Grupo(s) que te corresponden</label>
 <select id="sel-mi-grupo" class="form-select form-select-sm shadow-sm" onchange="elegirMiGrupo(this.value)"></select>
 </div>
 <div class="col-md-4">
 <span class="badge bg-dark">Solo puedes ver tu(s) grupo(s) asignado(s)</span>
 </div>
 </div>
 </div>
 </div>
 </div>
  <!-- SOLO CONTROL ESCOLAR: dar de alta a los orientadores (con su grado, grupo y turno) -->
 <div id="bloque-orientadores-control" class="p-3 bg-light rounded-4 border shadow-sm mb-4 d-none">
 <h6 class="fw-bold mb-2" style="color:#800020">Subir Lista de Orientadores</h6>
 <p class="text-muted small mb-3">Excel, CSV o PDF (.xlsx/.xls/.csv/.pdf) con <b>una fila por orientador(a)</b> y estas columnas: <b>Nombre completo</b>, <b>Grado</b> (1, 2 o 3), <b>Grupo</b> (1 al 4) y <b>Turno</b> (Matutino, Vespertino o Ambos; es opcional, si no lo pones se usa el turno que tienes activo arriba). Al subir el archivo, cada orientador(a) <b>queda asignado a ese grado, grupo y turno</b>.</p>
 <div class="row g-3 align-items-end mb-2">
 <div class="col-md-8"><label class="form-label small">Archivo</label><input type="file" id="archivo-orientadores" class="form-control form-control-sm" accept=".xlsx,.xls,.csv,.pdf"></div>
 <div class="col-md-4"><button onclick="subirOrientadores()" class="btn btn-vinotinto btn-sm w-100 py-2 fw-semibold">Subir Orientadores</button></div>
 </div>
 <div id="resumen-orientadores" class="small text-muted"></div>
 <div id="estado-sync-orientadores" class="small mt-1"></div>
 <div id="lista-orientadores-actual" class="small mt-2"></div>
 </div>
 <!-- SOLO CONTROL ESCOLAR: subir lista del grupo elegido arriba -->
 <div id="bloque-listas-orientador" class="p-3 bg-light rounded-4 border shadow-sm mb-4 d-none">
 <h6 class="fw-bold mb-2" style="color:#800020">Subir Lista del Grupo</h6>
 <p class="text-muted small mb-3">Excel, CSV o PDF (.xlsx/.xls/.csv/.pdf) con el <b>CURP en la columna A</b> y el <b>nombre completo en la columna B</b> (o Apellido paterno, Apellido materno y Nombre(s) en las columnas B, C y D), uno por fila. Se asigna al grado, grupo y turno elegidos arriba, con su CURP y su credencial digital; el nombre se guarda como <b>Apellido paterno, Apellido materno y Nombre(s)</b>. En el <b>Portal del Alumno</b> solo se ve y se edita la credencial. Si el archivo no trae CURP, se cargan solo los nombres.</p>
 <div class="row g-3 align-items-end">
 <div class="col-md-4"><label class="form-label small">Grupo seleccionado</label><input type="text" id="lbl-grupo-lista" class="form-control form-control-sm" value="Selecciona grado y grupo arriba" readonly></div>
 <div class="col-md-5"><label class="form-label small">Archivo</label><input type="file" id="archivo-lista" class="form-control form-control-sm" accept=".xlsx,.xls,.csv,.pdf"></div>
 <div class="col-md-3"><button onclick="subirLista()" class="btn btn-vinotinto btn-sm w-100 py-2 fw-semibold btn-accion-restringida">Subir Lista</button></div>
 <div class="col-12">
 <label class="form-label small">Orden de los nombres en el archivo</label>
 <select id="orden-nombre-lista" class="form-select form-select-sm">
 <option value="reordenar" selected>Vienen como Nombre(s) + Apellidos (reordenar a Apellido paterno, materno y nombre)</option>
 <option value="tal-cual">Ya vienen como Apellido paterno, materno y nombre (no cambiar)</option>
 </select>
 </div>
 </div>
 </div>
 <!-- SOLO CONTROL ESCOLAR: horario de entrada/salida y orientador del grupo -->
 <div id="bloque-horario-control" class="p-3 bg-light rounded-4 border shadow-sm mb-4 d-none">
 <h6 class="fw-bold mb-2" style="color:#800020">Horario y Orientador(a) del Grupo</h6>
 <p class="text-muted small mb-3">Define la hora límite de entrada (para marcar retardo), la hora de cierre (para marcar permanencia) y el orientador(a) del grado, grupo y turno seleccionados arriba. El horario y el orientador son independientes en cada turno. Un orientador(a) puede tener varios grupos: basta elegirlo en cada uno. Para cambiar al orientador(a) de un grupo, elige otro aquí y guarda.</p>
 <div class="row g-3 align-items-end mb-3">
 <div class="col-md-3"><label class="form-label small">Hora límite de entrada</label><input type="time" id="horario-entrada-limite" class="form-control form-control-sm"></div>
 <div class="col-md-3"><label class="form-label small">Hora de cierre (salida)</label><input type="time" id="horario-salida-limite" class="form-control form-control-sm"></div>
 <div class="col-md-4"><label class="form-label small">Orientador(a) del grupo</label>
 <select id="orientador-grupo-select" class="form-select form-select-sm">
 <option value="" selected>Sin asignar</option>
 </select>
 </div>
 <div class="col-md-2"><button onclick="guardarHorarioGrupo()" class="btn btn-vinotinto btn-sm w-100 py-2 fw-semibold">Guardar</button></div>
 </div>
 <div class="row g-3 align-items-end">
 <div class="col-md-6"><label class="form-label small">Archivo de horarios (.xlsx/.xls/.csv): columnas Grado, Grupo, HoraEntrada, HoraSalida, NombreOrientador y Turno (opcional; si no se indica se usa el turno activo)</label><input type="file" id="archivo-horarios" class="form-control form-control-sm" accept=".xlsx,.xls,.csv"></div>
 <div class="col-md-3"><button onclick="subirHorarios()" class="btn btn-dark btn-sm w-100 py-2 fw-semibold">Subir Horarios</button></div>
 </div>
 <div id="resumen-horario-grupo" class="mt-2 small text-muted"></div>
 </div>
 <!-- Cámara (orientador / director / subdirector) -->
 <div id="bloque-camara-orientador" class="p-3 bg-light rounded-4 border mb-4 text-center">
 <h6 class="fw-bold mb-3 text-uppercase" style="color:#800020;font-size:.9rem">Cámara de Escaneo (Entradas y Salidas)</h6>
 <!-- CAMBIO: cualquier orientador puede registrar entrada o salida de cualquier alumno -->
 <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
 <button onclick="setModoOrient('Entrada')" id="btn-orient-entrada" class="btn btn-success px-3 py-2 fw-semibold shadow-sm small">Entrada</button>
 <button onclick="setModoOrient('Salida')" id="btn-orient-salida" class="btn btn-outline-dark bg-white px-3 py-2 fw-semibold shadow-sm small">Salida</button>
 </div>
 <div class="camera-box-ref mb-2 border shadow-sm">
 <div class="cam-ph text-center text-warning"><span class="d-block small">Escáner apagado</span></div>
 <div id="cam-orient" style="width:100%"></div>
 </div>
 <div id="res-cam-orient" class="res-scan mb-2"></div>
 <div id="aviso-camara-bloqueada" class="small text-danger fw-semibold mb-2 d-none">Necesitas autorización de Control Escolar para usar la cámara.</div>
 <button onclick="activarCamaraOrientador()" id="btn-activar-camara-orientador" class="btn btn-vinotinto px-4 py-2 rounded-pill shadow-sm small btn-accion-restringida">Activar Cámara</button>
 <button onclick="detener('cam-orient')" class="btn btn-dark py-2 px-3 rounded-pill small">Apagar</button>
 </div>
 <div id="bloque-buscador-orientador" class="row g-3 mb-4 bg-light p-3 rounded-3 border">
 <div class="col-md-5"><label class="form-label">Buscador de Alumno</label><input type="text" id="buscador-alumno" class="form-control shadow-sm" placeholder="Ej. Maria Jose..." oninput="renderTablaPronto()"></div>
 <div class="col-md-4"><label class="form-label">Calendario (fecha a consultar)</label><input type="date" id="calendario-orientador" class="form-control shadow-sm" onchange="renderTabla()"></div>
 <div class="col-md-3">
 <label class="form-label">Período del reporte</label>
 <select id="sel-periodo-reporte" class="form-select shadow-sm">
 <option value="semana" selected>Semana</option>
 <option value="mes">Mes</option>
 </select>
 </div>
 </div>
 <div id="bloque-tabla-orientador">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
 <h6 class="fw-bold m-0" id="titulo-tabla-orientador">Turno Matutino</h6>
 <div class="d-flex gap-2 flex-wrap">
 <button onclick="descargarReporte('Excel')" class="btn btn-success px-4 py-2 fw-semibold shadow-sm small btn-accion-restringida">Descargar Excel</button>
 <button onclick="descargarReporte('PDF')" class="btn btn-danger px-4 py-2 fw-semibold shadow-sm small btn-accion-restringida">Descargar PDF</button>
 </div>
 </div>
 <div class="table-responsive bg-white rounded-3 p-2 border shadow-sm mb-4">
 <table class="table table-striped table-hover align-middle mb-0 small">
 <thead class="table-dark"><tr><th>Matrícula / QR</th><th>Alumno</th><th>Grado</th><th>Grupo</th><th>Entrada</th><th>Salida</th><th>Permanencia</th><th>Estatus</th><th>Orientador(a)</th><th class="text-center">Acción</th></tr></thead>
 <tbody id="tabla-cuerpo-orientador"></tbody>
 </table>
 </div>
</div>
</div>
<!-- ===== 4. CONTROL ESCOLAR ===== -->
<div id="panel-control" class="panel-perfil d-none">
 <div class="alert alert-warning py-2 mb-3 small fw-semibold">Control Escolar: estadísticas y asignación de permisos. Las listas, los orientadores y los horarios se suben en el <b>Panel de Orientadores</b> (elige turno, grado y grupo).</div>
 <div class="row text-center mb-4 g-3">
 <div class="col-md-4"><div class="p-3 bg-white rounded-4 border shadow-sm"><span class="text-muted d-block fw-bold small mb-1">TOTAL ALUMNOS</span><h3 class="fw-bold mb-0" id="st-total">0</h3></div></div>
 <div class="col-md-4"><div class="p-3 bg-white rounded-4 border shadow-sm"><span class="text-muted d-block fw-bold small mb-1">ASISTENCIAS DE HOY</span><h3 class="fw-bold text-success mb-0" id="st-asis">0</h3></div></div>
 <div class="col-md-4"><div class="p-3 bg-white rounded-4 border shadow-sm"><span class="text-muted d-block fw-bold small mb-1">RETARDOS Y PERMANENCIAS</span><h3 class="fw-bold text-primary mb-0" id="st-ret">0</h3></div></div>
 </div>
 <div class="p-4 bg-white rounded-4 border shadow-sm mb-4">
 <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
 <h6 class="fw-bold m-0" style="color:#800020">Gráficas Comparativas y Estadísticas</h6>
 <div class="d-flex align-items-center gap-2 flex-wrap">
 <select id="sel-periodo-stats" class="form-select form-select-sm" style="width:auto" onchange="renderStats()">
 <option value="hoy" selected>Hoy</option>
 <option value="semana">Semana</option>
 <option value="mes">Mes</option>
 </select>
 <button onclick="descargarGraficas('Excel')" class="btn btn-success px-4 py-2 fw-semibold shadow-sm small btn-accion-restringida">Excel</button>
 <button onclick="descargarGraficas('PDF')" class="btn btn-danger px-4 py-2 fw-semibold shadow-sm small btn-accion-restringida">PDF</button>
 </div>
 </div>
 <p class="text-muted small mb-2" id="subtitulo-periodo-stats">Estadísticas de asistencia -Hoy</p>
 <p class="small mb-2"><span class="fw-semibold" style="color:#800020">Se descargará:</span> <span id="lbl-descarga-seleccion" class="fw-semibold">Estadísticas generales (todos los grados)</span></p>
 <div class="mb-3" style="max-width:640px;margin:0 auto"><canvas id="grafica-asistencias" height="220"></canvas></div>
 <div class="row text-center g-3" id="stats-grados"></div>
 </div>

<div id="bloque-permisos-roles" class="p-4 bg-white rounded-4 border shadow-sm mb-4 d-none">
 <h6 class="fw-bold mb-2" style="color:#800020">Permisos y Roles</h6>
 <p class="text-muted small mb-3">Define si el rol solo visualiza o también puede realizar acciones (descargas, marcar salidas, subir listas, editar credenciales, usar la cámara de escaneo).</p>
 <div class="row g-3 align-items-end">
 <div class="col-md-4"><label class="form-label small">Rol</label>
 <select id="select-rol-permiso" class="form-select form-select-sm">
 <option value="director">Director</option>
 <option value="subdirector">Subdirector</option>
 <option value="orientador">Orientador</option>
 </select>
 </div>
 <div class="col-md-5"><label class="form-label small">Tarea a realizar</label>
 <select id="select-tarea-permiso" class="form-select form-select-sm">
 <option value="ver">Solo visualizar</option>
 <option value="editar">Puede editar / realizar acciones (incluye cámara)</option>
 </select>
 </div>
 <div class="col-md-3"><button onclick="guardarPermisoRol()" class="btn btn-vinotinto btn-sm w-100 py-2 fw-semibold">Guardar</button></div>
 </div>
 <div id="resumen-permisos" class="mt-3 small text-muted"></div>
 </div>

<div id="bloque-claves" class="p-4 bg-white rounded-4 border shadow-sm mb-4 d-none">
 <h6 class="fw-bold mb-2" style="color:#800020">Contraseñas</h6>
 <p class="text-muted small mb-3">Control Escolar genera y entrega las contraseñas. Elige un rol; si es orientador con contraseña propia, elige su nombre. Después borra su contraseña, genera una nueva o quita el bloqueo.</p>
 <div class="fw-semibold small mb-2" style="color:#800020">Roles</div>
 <div id="botones-roles-clave" class="d-flex flex-wrap gap-2 mb-2"></div>
 <div id="resumen-bloqueadas" class="mt-3 small"></div>

 <div id="panel-cuenta-clave" class="mt-3 p-3 rounded-3 d-none" style="background:#fdf8f8;border:1px solid #eee">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
   <div><span class="fw-bold" id="panel-titulo"></span> <span id="panel-estado" class="ms-1"></span></div>
   <div class="d-flex flex-wrap gap-2">
    <button type="button" id="btn-borrar-clave" onclick="borrarClaveCuenta()" class="btn btn-outline-danger btn-sm">Borrar contraseña</button>
    <button type="button" id="btn-desbloquear-clave" onclick="desbloquearCuenta()" class="btn btn-outline-secondary btn-sm d-none">Desbloquear sin cambiar</button>
    <button type="button" id="btn-quitar-clave" onclick="quitarClaveOrientador()" class="btn btn-outline-secondary btn-sm d-none" title="El orientador vuelve a entrar con la contraseña general">Quitar propia</button>
   </div>
  </div>
  <div class="small text-muted mb-3" id="panel-ayuda"></div>
  <div class="row g-3 align-items-end">
   <div class="col-md-12 d-none" id="campo-clave-actual"><label class="form-label small">Contraseña actual</label>
    <input type="password" id="clave-actual" class="form-control form-control-sm" autocomplete="current-password">
   </div>
   <div class="col-md-4"><label class="form-label small">Contraseña nueva</label>
    <input type="password" id="clave-nueva" class="form-control form-control-sm" autocomplete="new-password" maxlength="72" oninput="revisarRequisitosClave()">
   </div>
   <div class="col-md-4"><label class="form-label small">Repite la contraseña nueva</label>
    <input type="password" id="clave-confirmar" class="form-control form-control-sm" autocomplete="new-password" maxlength="72" oninput="revisarRequisitosClave()">
   </div>
   <div class="col-md-4 d-flex gap-2">
    <button type="button" onclick="generarClave()" class="btn btn-outline-dark btn-sm py-2 text-nowrap" title="Crea una contraseña segura al azar">Generar</button>
    <button type="button" onclick="guardarClave()" class="btn btn-vinotinto btn-sm w-100 py-2 fw-semibold">Guardar</button>
   </div>
  </div>
  <div class="form-check mt-2 small"><input class="form-check-input" type="checkbox" id="ver-claves" onchange="verClavesNuevas()"><label class="form-check-label" for="ver-claves">Mostrar lo que escribo</label></div>
  <div class="mt-3 p-3 rounded-3 small bg-white" style="border:1px solid #eee">
   <div class="fw-semibold mb-1" style="color:#800020">Cómo hacer una contraseña segura</div>
   <ul class="list-unstyled mb-2" id="requisitos-clave">
    <li data-req="largo"><span class="casilla" aria-hidden="true"></span>Mínimo 8 caracteres (máximo 72).</li>
    <li data-req="mayus"><span class="casilla" aria-hidden="true"></span>Una letra mayúscula y una minúscula.</li>
    <li data-req="numero"><span class="casilla" aria-hidden="true"></span>Al menos un número.</li>
    <li data-req="espacios"><span class="casilla" aria-hidden="true"></span>Sin espacios.</li>
    <li data-req="especial"><span class="casilla" aria-hidden="true"></span>Solo letras y números: sin caracteres especiales (@ # $ % & * . - _ ! ?) ni acentos.</li>
    <li data-req="escuela"><span class="casilla" aria-hidden="true"></span>Sin el nombre de la escuela ni la clave inicial (prepa06, epo6).</li>
    <li data-req="igual"><span class="casilla" aria-hidden="true"></span>Las dos casillas deben coincidir.</li>
   </ul>
   <div class="text-muted">El botón <b>Generar</b> crea una que ya cumple todo. Evita nombres, CURP y fechas de nacimiento, y no repitas la misma contraseña en otro rol.</div>
  </div>
  <div id="entrega-clave" class="mt-3 p-3 rounded-3 d-none" style="background:#eef7f0;border:1px solid #b7dcc0">
   <div class="fw-semibold mb-1" style="color:#0f5132">Contraseña lista para entregar</div>
   <div class="small mb-2">Para: <b id="entrega-para"></b> · Correo: <span id="entrega-correo"></span></div>
   <div class="d-flex flex-wrap align-items-center gap-2">
    <code id="entrega-clave-texto" class="fs-5 px-3 py-1 bg-white rounded border" style="user-select:all"></code>
    <button type="button" onclick="copiarEntrega()" class="btn btn-outline-success btn-sm">Copiar</button>
    <button type="button" onclick="imprimirEntrega()" class="btn btn-outline-success btn-sm">Imprimir hoja de entrega</button>
    <button type="button" onclick="ocultarEntrega()" class="btn btn-success btn-sm">Ya la entregué</button>
   </div>
   <div class="small text-muted mt-2">Entrégala en persona. Esta es la única vez que se muestra: el sistema solo guarda su versión cifrada.</div>
  </div>
 </div>
 <div id="resumen-claves" class="mt-3 small text-muted"></div>
</div>
</div>
<div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
 <button onclick="regresarPanel()" id="btn-regresar-panel" class="btn btn-vinotinto px-4 rounded-pill fw-semibold shadow-sm d-none">Regresar</button>
 <button onclick="cerrarSesion()" class="btn btn-dark px-4 rounded-pill fw-semibold shadow-sm ms-auto">Cerrar Sesión</button>
</div>
</div>
</div>
<div id="burbujas-claves" class="burbujas-claves"></div>
<div id="modal-cuenta" class="modal-cuenta d-none" role="dialog" aria-modal="true" aria-labelledby="modal-cuenta-titulo">
 <div class="modal-cuenta-caja">
    <h5 id="modal-cuenta-titulo"></h5>
  <p id="modal-cuenta-texto"></p>
  <div id="modal-cuenta-botones" class="d-flex gap-2 justify-content-center flex-wrap"></div>
 </div>
</div>
<div id="canvas-temporal" style="position:absolute;left:-9999px;top:-9999px;"></div>
<script src="{{ asset('vendor/jquery.min.js') }}"></script>
<script>
/* Librerías pesadas: se cargan en segundo plano para que la pantalla aparezca de inmediato (el service worker las guarda,
   así que desde la segunda visita salen del equipo). Lo que tarda en estar listo se avisa con libsOk(). */
(function(){
 const base = "{{ asset('vendor') }}/";
 const grupos = [
  ['html5-qrcode.min.js', 'qrious.min.js', 'chart.umd.min.js', 'jszip.min.js', 'sica-docx.js'],   // escáner, QR, gráficas y lector de plantillas Word
  ['xlsx.full.min.js', 'jspdf.umd.min.js', 'pdf.min.js'],             // Excel, PDF y lector de PDF
  ['jspdf.plugin.autotable.min.js']                                   // depende de jsPDF
 ];
 const cargar = src => new Promise(ok => {
  const s = document.createElement('script');
  s.src = base + src + '?v=12'; s.onload = s.onerror = () => ok();
  document.head.appendChild(s);
 });
 window.SICA_LIBS_LISTAS = grupos.reduce((p, g) => p.then(() => Promise.all(g.map(cargar))), Promise.resolve()).then(() => {
  if (window.Chart) Chart.defaults.devicePixelRatio = Math.max(window.devicePixelRatio || 1, 2);
  if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.js';
 });
 window.libsOk = function (global) {
  if (typeof window[global] !== 'undefined') return true;
  alert('Todavía se está cargando una herramienta del sistema. Espera unos segundos e inténtalo de nuevo.');
  return false;
 };
})();
</script>
<script>
/* Lo incrusta el servidor al abrir la página: quién tiene sesión y la lista de nombres de orientadores.
   Así al abrir NO hace falta pedir /auth/me ni /api/sica/orientadores (arranque más rápido).
   OJO: esto solo sirve para mostrar la pantalla; los permisos reales los decide siempre el servidor. */
window.SICA_SESION = @json($sesion ?? null);
window.SICA_ORIENTADORES = @json($orientadoresLogin ?? []);
</script>
<script>
/* ============ DATOS (localStorage del navegador) ============ */
const LS = 'sica_epo6_v4';
/* Limpieza única de datos guardados en el navegador.
 Para volver a borrar en el futuro, cambia '_3' por '_4'.
 (Se subió a _3 porque cambió el formato de las claves de horario y de plantillas: ahora llevan turno.) */
const LIMPIEZA = 'sica_epo6_limpieza_3';
if (!localStorage.getItem(LIMPIEZA)) {
 localStorage.removeItem(LS);
 localStorage.removeItem('sica_epo6_plantilla_oficial');
 localStorage.setItem(LIMPIEZA, '1');
}
// Detecta si se abrió desde un teléfono (cámara trasera, modo "solo escanear" para el orientador)
const ES_MOVIL = /Android|iPhone|iPad|iPod|Mobile|Tablet|Silk|webOS|BlackBerry|Opera Mini/i.test(navigator.userAgent)
 || (/Macintosh/i.test(navigator.userAgent) && navigator.maxTouchPoints > 1) // iPad con iPadOS se identifica como Mac
 || (window.matchMedia && window.matchMedia('(pointer: coarse)').matches); // pantalla táctil (aunque use "sitio de escritorio")
const correosRoles = {orientador:'orientador1@epo6.edu.mx',director:'director@epo6.edu.mx',subdirector:'subdirector@epo6.edu.mx',control:'control.escolar@epo6.edu.mx'};
const nombresRoles = {orientador:'Orientador',director:'Director',subdirector:'Subdirector',control:'Encargado de Control Escolar'};
const ordenPaneles = ['orientador','lector','alumno'];
let db = JSON.parse(localStorage.getItem(LS) || 'null') || {
 permisos: {orientador:'editar', director:'ver', subdirector:'ver'},
 plantillas: {}, // claveGrupo -> {frente:{img,fmt}, reverso:{img,fmt}} (override opcional por grupo)
 horarios: {}, // claveGrupo ("Turno-grado-grupo") -> {entrada, salida, orientadorId}
 orientadores: [], // {id, nombre}
 alumnos: []
};
// Compatibilidad con datos guardados en versiones anteriores
db.plantillas = db.plantillas || {};
db.horarios = db.horarios || {};
db.orientadores = db.orientadores || [];
Object.keys(db.horarios).forEach(k => {
 const h = db.horarios[k];
 if (h && h.orientador && !h.orientadorId) {
 // Migración: si existía un nombre libre de orientador, se crea el registro correspondiente
 let o = db.orientadores.find(x => norm(x.nombre) === norm(h.orientador));
 if (!o) { o = {id: generarId(), nombre: h.orientador}; db.orientadores.push(o); }
 h.orientadorId = o.id;
 }
});
/* Guarda SOLO orientadores y grupos en el servidor (rápido, sin mandar alumnos ni plantillas) y avisa si quedó guardado.
   Es lo que hace que los orientadores aparezcan en la pantalla de inicio (lista de nombres) en cualquier equipo. */
function guardarOrientadoresServidor() {
 localStorage.setItem(LS, JSON.stringify(db));
 const caja = $('#estado-sync-orientadores');
 caja.removeClass('text-danger text-success').addClass('text-muted').text('Guardando en el servidor…');
 return fetchT('/api/sica/sync', {
  method: 'POST',
  headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''},
  body: JSON.stringify({accion: 'guardar_orientadores', datos: {orientadores: db.orientadores, horarios: db.horarios}})
 }, 30000).then(r => {
  if (r.status === 419) return refrescarCsrf().then(() => Promise.reject('csrf'));
  if (r.status === 401) { sesionExpirada(); return Promise.reject('sesion'); }
  if (!r.ok) return Promise.reject('error');
  caja.removeClass('text-muted text-danger').addClass('text-success').text('✔ Guardado en el servidor: ya aparecen en la pantalla de inicio para elegir su nombre y escanear.');
  return true;
 }).catch(e => {
  if (e !== 'sesion') caja.removeClass('text-muted text-success').addClass('text-danger').text('⚠ No se pudo guardar en el servidor (' + (e === 'csrf' ? 'la sesión se renovó, inténtalo otra vez' : 'revisa tu conexión') + '). Los cambios siguen en este equipo; vuelve a intentarlo.');
  return false;
 });
}
// El teléfono nunca sobrescribe toda la base en el servidor: solo envía cada escaneo (registrar_asistencia).
let _temporizadorSave = null;
const save = () => {
 try { localStorage.setItem(LS, JSON.stringify(db)); } catch (e) {}
 if (ES_MOVIL) return;
 // Varias acciones seguidas (subir lista, escanear varios QR...) se envían al servidor en UNA sola petición.
 // Mientras tanto queda la marca de pendiente: si se cierra la página antes, se envía al volver a abrir.
 try { localStorage.setItem(PEND_TODO, '1'); } catch (e) {}
 clearTimeout(_temporizadorSave);
 _temporizadorSave = setTimeout(() => { _temporizadorSave = null; sincronizarConServidor('guardar_todo', db); }, 600);
};
/* ============ SINCRONIZACIÓN CON EL SERVIDOR (MySQL vía Laravel) ============
 Envía {accion, datos} a /api/sica/sync (ControlEscolarController@sync).
 Si el backend no responde, falla en silencio y el sistema sigue con localStorage. */
let _ultimoAvisoPermiso = 0;
function avisoPermiso(mensaje) {
 if (Date.now() - _ultimoAvisoPermiso < 5000) return;   // un solo aviso cada 5 s
 _ultimoAvisoPermiso = Date.now();
 alert(mensaje || 'No tienes permiso para realizar esta acción.');
}
/* fetch con tiempo límite: con señal mala (o sin internet "a medias") una petición puede quedarse esperando
   para siempre y atorar la cola de escaneos. Pasados los segundos indicados se corta y cuenta como "sin conexión". */
function fetchT(url, opciones, ms) {
 if (typeof AbortController === 'undefined') return fetch(url, opciones);
 const ctl = new AbortController();
 const reloj = setTimeout(() => ctl.abort(), ms || 12000);
 return fetch(url, Object.assign({}, opciones, {signal: ctl.signal}))
  .then(r => { clearTimeout(reloj); return r; }, e => { clearTimeout(reloj); throw e; });
}
/* Pide al service worker guardar la página de inicio YA CON la sesión iniciada: así, al abrir el sistema sin
   internet, entra directo (sin esto aparecía "Sin conexión" si no se había recargado la página con internet). */
function guardarPaginaOffline() {
 try {
  if (!navigator.serviceWorker) return;
  navigator.serviceWorker.ready.then(reg => { if (reg.active) reg.active.postMessage('guardar-pagina'); }).catch(() => {});
 } catch (e) {}
}
const PEND_TODO = 'sica_epo6_pend_todo';   // marca: hay cambios guardados solo en este equipo (se hicieron sin internet)
function sincronizarConServidor(accion, datos, reintento) {
 try {
  fetchT('/api/sica/sync', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '' },
   body: JSON.stringify({ accion: accion, datos: datos })
  }, 30000).then(r => {
   // 419: el token CSRF venció -> se pide uno nuevo y se reintenta UNA vez
   if (r.status === 419 && !reintento) return refrescarCsrf().then(() => sincronizarConServidor(accion, datos, true));
   if (r.status === 401) { sesionExpirada(); return; }
   if (r.status === 403) return r.json().then(d => avisoPermiso(d && d.mensaje)).catch(() => avisoPermiso());
   if (accion === 'guardar_todo') {
    if (r.ok) { if (!_temporizadorSave) localStorage.removeItem(PEND_TODO); }
    else if (r.status >= 500) localStorage.setItem(PEND_TODO, '1');
   }
  }).catch(() => {
   /* sin conexión: localStorage sigue funcionando y se recuerda que falta enviar los cambios */
   if (accion === 'guardar_todo') localStorage.setItem(PEND_TODO, '1');
  });
 } catch (err) { /* entorno sin fetch: se ignora */ }
}
/* Mezcla el estado del servidor con el local sin perder entradas/salidas ya registradas.
 Los alumnos se emparejan por CURP (el id del servidor es distinto al id local). */
function fusionarServidor(s) {
 if (!s || !Array.isArray(s.alumnos)) return;
 const clv = a => a.curp ? 'c:' + a.curp : 'i:' + a.id;
 const locales = new Map(db.alumnos.map(a => [clv(a), a])), claves = new Set();
 const lista = s.alumnos.map(sa => {
  claves.add(clv(sa)); sa.reg = sa.reg || {}; sa.enServidor = true;
  const la = locales.get(clv(sa));
  if (la) {
   sa.id = la.id;
   if (la.reg) Object.keys(la.reg).forEach(f => {
    const l = la.reg[f], r = sa.reg[f] = sa.reg[f] || {};
    if (l.entrada && !r.entrada) r.entrada = l.entrada;
    if (l.salida && !r.salida) r.salida = l.salida;
   });
  }
  return sa;
 });
 // Se conservan los locales aún no enviados; los que ya estuvieron en el servidor y ya no están (eliminados) se quitan
 db.alumnos = lista.concat(db.alumnos.filter(a => !claves.has(clv(a)) && !a.enServidor));
 if (s.horarios) db.horarios = s.horarios;
 if (s.orientadores) db.orientadores = s.orientadores;
 if (s.permisos) db.permisos = s.permisos;
 localStorage.setItem(LS, JSON.stringify(db));
}
/* Control Escolar: lo que tenga este equipo y aún no esté en el servidor se sube SOLO (sin respaldos manuales). */
let _autoSubiendo = false;
function pintarEstadoDatos() {}   // (ya no hay recuadro de estado; la subida automática sigue funcionando)
function autoSubirPendientes() {
 if (rolActivo !== 'control' || ES_MOVIL) return;
 const faltan = db.alumnos.filter(a => !a.enServidor).length;
 if (!faltan) {
  pintarEstadoDatos('✔ Todo guardado en el servidor: ' + db.orientadores.length + ' orientador(es) y ' + db.alumnos.length + ' alumno(s).', true);
  return;
 }
 if (_autoSubiendo) return;
 _autoSubiendo = true;
 pintarEstadoDatos('Subiendo ' + faltan + ' alumno(s) al servidor…');
 fetchT('/api/sica/sync', {
  method: 'POST',
  headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''},
  body: JSON.stringify({accion: 'guardar_todo', datos: db})
 }, 120000).then(r => {
  if (r.status === 419) return refrescarCsrf();
  if (r.ok) localStorage.removeItem(PEND_TODO); else pintarEstadoDatos('⚠ No se pudo subir al servidor; se reintenta solo.', false);
 }).catch(() => pintarEstadoDatos('⚠ Sin conexión con el servidor; se reintenta solo.', false))
 .then(() => { _autoSubiendo = false; });
}
/* Pide un token CSRF nuevo (cuando la sesión venció, el anterior ya no sirve) */
function refrescarCsrf() {
 return fetchT('/auth/me', {headers:{'Accept':'application/json'}}, 8000)
 .then(r => r.json())
 .then(d => { if (d && d.csrf) $('meta[name="csrf-token"]').attr('content', d.csrf); })
 .catch(() => {});
}
/* Lista de nombres de orientadores para la pantalla de login (no incluye alumnos ni CURP) */
function cargarOrientadoresLogin() {
 return fetch('/api/sica/orientadores', {headers:{'Accept':'application/json'}})
 .then(r => r.ok ? r.json() : null)
 .then(lista => {
 if (!Array.isArray(lista)) return;
 db.orientadores = lista;
 localStorage.setItem(LS, JSON.stringify(db));
 if (!rolActivo && !$('#select-orientador-login').val()) actualizarCredenciales();
 })
 .catch(() => {});
}
/* El servidor respondió 401: no hay sesión (nunca se inició o ya venció) */
function sesionExpirada() {
 if (rolActivo) {
 alert('Tu sesión expiró. Vuelve a iniciar sesión.');
 cerrarSesion();
 } else {
 refrescarCsrf();
 cargarOrientadoresLogin();
 }
}
/* Descarga el estado del servidor (listas, CURPs, horarios, asistencias de todos los dispositivos) */
function cargarDesdeServidor() {
 // Si se hicieron cambios sin internet, primero se envían; así la descarga no los pisa con datos viejos del servidor
 if (!ES_MOVIL && rolActivo && localStorage.getItem(PEND_TODO) && !_temporizadorSave) {
  return fetchT('/api/sica/sync', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '' },
   body: JSON.stringify({ accion: 'guardar_todo', datos: db })
  }, 30000).then(r => { if (r.ok) localStorage.removeItem(PEND_TODO); else if (r.status === 401) sesionExpirada(); })
  .catch(() => {})
  .then(() => localStorage.getItem(PEND_TODO) ? null : descargarEstadoServidor());
 }
 return descargarEstadoServidor();
}
/* El navegador convierte solo los 304 en 200 y entregaba todo el estado cada 3 s: había que volver a leerlo,
   fusionarlo, guardarlo y redibujar la tabla aunque nada hubiera cambiado. Ahora se manda el ETag a mano
   (cache:'no-store') para recibir el 304 de verdad y no hacer nada cuando no hay cambios. */
let _etagEstado = null, _descargandoEstado = false;
function descargarEstadoServidor() {
 if (_descargandoEstado) return Promise.resolve();   // no se amontonan consultas si el servidor tarda
 _descargandoEstado = true;
 const cab = {'Accept':'application/json'};
 if (_etagEstado) cab['If-None-Match'] = _etagEstado;
 return fetchT('/api/sica/estado', {headers: cab, cache: 'no-store'}, 15000)
  .then(r => {
   if (r.status === 401) { sesionExpirada(); return null; }
   if (r.status === 304) { autoSubirPendientes(); return null; }   // sin cambios en el servidor
   if (!r.ok) return null;
   const et = r.headers.get('ETag');
   return r.json().then(d => { _etagEstado = et; return d; });
  })
  .then(s => {
   _descargandoEstado = false;
   if (!s) return;
   fusionarServidor(s);
   autoSubirPendientes();
   if (orientadorActivo) {
    orientadorActivo = db.orientadores.find(o => o.id === orientadorActivo.id) || orientadorActivo;
    const gruposAntes = JSON.stringify(gruposDelOrientador);
    gruposDelOrientador = calcularGruposOrientador(orientadorActivo.id);
    // Control Escolar asignó (o cambió) el grupo mientras este equipo ya estaba dentro: se actualiza la lista de grupos
    if (rolActivo === 'orientador' && JSON.stringify(gruposDelOrientador) !== gruposAntes) {
     const elegido = $('#sel-mi-grupo').val();
     configurarMiGrupoOrientador();
     if (elegido && $('#sel-mi-grupo option[value="' + elegido + '"]').length) { $('#sel-mi-grupo').val(elegido); elegirMiGrupo(elegido); }
    }
   }
   if (rolActivo) {
    renderTabla();
    // Portal del Alumno: si se está viendo la lista de un grupo, se actualiza (alumnos nuevos que subió otra computadora)
    if (credSel && !$('#resultado-credencial-box').hasClass('d-none') && !$('#panel-alumno').hasClass('d-none')) selCred(credSel.grado, credSel.grupo);
   }
   else if (!$('#vista-login').hasClass('d-none') && !$('#select-orientador-login').val()) actualizarCredenciales();
  })
  .catch(() => { _descargandoEstado = false; });
}
let rolActivo = null, turnoActivo = 'Matutino', indicePanelActual = 0, modoEscaneo = 'Entrada';
let filtro = null, credSel = null, idEditando = null, chartAsistencias = null, gradoSeleccionado = null;
let turnoCred = 'Matutino'; // turno elegido en el Portal del Alumno (credenciales)
let orientadorActivo = null; // {id, nombre} cuando el rol activo es 'orientador'
let gruposDelOrientador = []; // [{turno,grado,grupo}] calculados a partir de db.horarios
const scanners = {}; let ultimo = {t:'', ts:0};
// dataURL cacheado de la plantilla oficial de cada turno
const plantillasDefault = {Matutino:{frente:null, reverso:null}, Vespertino:{frente:null, reverso:null}};
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const norm = t => (t||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
const p2 = n => String(n).padStart(2,'0');
const fechaLocal = d => { d = d || new Date(); return d.getFullYear()+'-'+p2(d.getMonth()+1)+'-'+p2(d.getDate()); };
const hoy = () => fechaLocal();
const horaAhora = () => { const d = new Date(); return p2(d.getHours())+':'+p2(d.getMinutes()); };
const toMin = hm => { const [h,m] = hm.split(':').map(Number); return h*60+m; };
/* Clave de grupo: "Turno-grado-grupo" (ej. "Vespertino-1-2"). Matutino y Vespertino son grupos distintos. */
const claveGrupo = (grado, grupo, turno) => (turno || 'Matutino') + '-' + grado + '-' + grupo;
/* Lee una clave. Acepta también el formato viejo "grado-grupo" (se toma como Matutino). */
const parseClave = k => {
 const p = String(k).split('-');
 return p.length === 2
  ? {turno:'Matutino', grado:Number(p[0]), grupo:Number(p[1])}
  : {turno:p[0], grado:Number(p[1]), grupo:Number(p[2])};
};
const generarId = () => 'id_' + Date.now().toString(36) + Math.random().toString(36).slice(2,7);
const LIM = {Matutino:{retardo:430,fin:840}, Vespertino:{retardo:790,fin:1200}}; // 7:10-14:00 / 13:10-20:00 (por defecto)
const nomGrado = ['', 'Primero', 'Segundo', 'Tercero'];
const ROMANOS = ['', 'I', 'II', 'III', 'IV']; // grupo en número romano para la credencial
function limiteDe(a) {
 const h = db.horarios[claveGrupo(a.grado, a.grupo, a.turno)];
 if (h && h.entrada && h.salida) return {retardo: toMin(h.entrada), fin: toMin(h.salida)};
 return LIM[a.turno];
}
function estatusDe(a, r, fecha) {
 if (!r || !r.entrada) return 'Sin registro';
 const l = limiteDe(a);
 if (!r.salida && fecha === hoy() && toMin(horaAhora()) > l.fin) return 'Permanencia';
 return toMin(r.entrada) > l.retardo ? 'Retardo' : 'Asistencia';
}
function permDe(r) {
 if (!r || !r.entrada || !r.salida) return 'Pendiente';
 const d = toMin(r.salida) - toMin(r.entrada), h = Math.floor(d/60);
 return (h > 0 ? h+' h ' : '') + (d%60) + ' min';
}
const claseEst = {'Asistencia':'bg-success','Retardo':'bg-warning text-dark','Permanencia':'bg-danger','Sin registro':'bg-secondary'};
function diasDePeriodo(periodo, ref) {
 ref = ref || hoy();
 const d = new Date(ref + 'T12:00:00');
 if (periodo === 'mes') {
 const anio = d.getFullYear(), mes = d.getMonth(), ultimoDia = new Date(anio, mes+1, 0).getDate();
 return [...Array(ultimoDia)].map((_, i) => fechaLocal(new Date(anio, mes, i+1)));
 }
 if (periodo === 'semana') {
 const dd = new Date(d); dd.setDate(dd.getDate() - ((dd.getDay() + 6) % 7));
 return [...Array(7)].map((_, i) => { const x = new Date(dd); x.setDate(dd.getDate() + i); return fechaLocal(x); });
 }
 return [ref];
}
function tituloPeriodo(periodo, ref) {
 ref = ref || hoy();
 const d = new Date(ref + 'T12:00:00');
 if (periodo === 'mes') return 'Mes de ' + d.toLocaleDateString('es-MX', {month:'long', year:'numeric'});
 if (periodo === 'semana') { const dias = diasDePeriodo('semana', ref); return 'Semana del ' + dias[0] + ' al ' + dias[6]; }
 return 'Hoy (' + ref + ')';
}
/* ============ ORDEN DEL NOMBRE: APELLIDO PATERNO + APELLIDO MATERNO + NOMBRE(S) ============ */
const PARTICULAS = ['de','del','la','las','los','y','e','san','santa','van','von','mc','mac','da','das','dos','di'];
/* Convierte "Nombre(s) Ap. paterno Ap. materno" en "Ap. paterno Ap. materno Nombre(s)".
 Respeta partículas de apellidos compuestos (DE LA CRUZ, DEL RIO, etc.). Es una regla: revisa los casos raros. */
function reordenarNombrePrimero(txt) {
 const p = String(txt).trim().replace(/\s+/g, ' ').split(' ');
 if (p.length < 2) return p.join(' ');
 const tomarApellido = () => {
  const ap = [p.pop()];
  while (p.length > 1 && PARTICULAS.includes(norm(p[p.length - 1]))) ap.unshift(p.pop());
  return ap;
 };
 const ap2 = tomarApellido();
 if (p.length <= 1) return ap2.concat(p).join(' '); // solo un apellido
 const ap1 = tomarApellido();
 return ap1.concat(ap2, p).join(' ');
}
/* fila = renglón del archivo; desde = columna donde empieza el nombre.
 Si vienen 3 columnas (paterno, materno, nombre) las une en ese orden;
 si viene una sola, la reordena o la deja tal cual según el selector. */
function armarNombre(fila, desde, modo) {
 const c = [0,1,2].map(i => String(fila[desde + i] == null ? '' : fila[desde + i]).trim());
 if (c[1] && c[2]) return c.join(' ').replace(/\s+/g, ' ').trim();
 const t = c[0].replace(/\s+/g, ' ');
 return modo === 'reordenar' ? reordenarNombrePrimero(t) : t;
}
/* ============ LECTURA DE ARCHIVOS (Excel / CSV / PDF) ============
 Punto único para leer listas de nombres: si es .xlsx/.xls/.csv usa SheetJS (como antes);
 si es .pdf usa pdf.js y trata cada línea de texto del PDF como una fila (una sola columna).
 callback(filas) recibe un arreglo de arreglos, igual que sheet_to_json({header:1}). */
function leerFilasDesdeArchivo(file, callback, onError) {
 if (/\.pdf$/i.test(file.name)) {
 if (typeof pdfjsLib === 'undefined') { onError && onError('No se pudo cargar el lector de PDF. Revisa tu conexión a internet.'); return; }
 const rd = new FileReader();
 rd.onload = async e => {
 try {
 const pdf = await pdfjsLib.getDocument({data: e.target.result}).promise;
 const filas = [];
 for (let p = 1; p <= pdf.numPages; p++) {
 const page = await pdf.getPage(p);
 const contenido = await page.getTextContent();
 const lineasPorY = {};
 contenido.items.forEach(it => {
 const y = Math.round(it.transform[5]);
 (lineasPorY[y] = lineasPorY[y] || []).push(it.str);
 });
 Object.keys(lineasPorY).sort((a, b) => b - a).forEach(y => {
 const texto = lineasPorY[y].join(' ').replace(/\s+/g, ' ').trim();
 if (texto) filas.push([texto]);
 });
 }
 callback(filas);
 } catch (err) { onError && onError('No se pudo leer el PDF: ' + err); }
 };
 rd.readAsArrayBuffer(file);
 } else {
 if (!libsOk('XLSX')) return;
 const rd = new FileReader();
 rd.onload = e => {
 const wb = XLSX.read(e.target.result, {type:'array'});
 callback(XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], {header:1}));
 };
 rd.readAsArrayBuffer(file);
 }
}
/* ============ ORIENTADORES: alta y consulta ============ */
/* Número desde una celda: acepta 1, "1", "1°", "Primero"... Devuelve 0 si no hay número. */
function numeroDe(v) {
 const t = norm(v == null ? '' : v);
 if (/primer/.test(t)) return 1;
 if (/segund/.test(t)) return 2;
 if (/tercer/.test(t)) return 3;
 const m = t.match(/\d+/);
 return m ? Number(m[0]) : 0;
}
const turnoDe = v => /ambos|ambas|mixto/i.test(String(v)) ? 'Ambos' : /vesp/i.test(String(v)) ? 'Vespertino' : 'Matutino';
/* Una fila del archivo -> {nombre, grado, grupo, turno}.
   Excel/CSV: columnas Nombre | Grado | Grupo | Turno (opcional).
   PDF o una sola columna: "Nombre ... 1 2 Matutino" al final de la línea (el turno es opcional). */
function leerFilaOrientador(r) {
 let nombre = String(r[0] == null ? '' : r[0]).trim(), grado = 0, grupo = 0, turno = '';
 const hayMasColumnas = r.slice(1).some(x => x != null && String(x).trim() !== '');
 if (hayMasColumnas) {
  grado = numeroDe(r[1]); grupo = numeroDe(r[2]);
  if (r[3] != null && String(r[3]).trim()) turno = turnoDe(r[3]);
 } else {
  const m = nombre.replace(/\s+/g, ' ').match(/^(.*?)[\s,;|\-]+([1-9])[\s,;|\-°º]*([1-9])(?:[\s,;|\-]+(matutino|vespertino|ambos(?:\s+turnos)?))?\s*$/i);
  if (m) { nombre = m[1].trim(); grado = Number(m[2]); grupo = Number(m[3]); if (m[4]) turno = turnoDe(m[4]); }
 }
 return {nombre: nombre.replace(/\s+/g, ' ').trim(), grado: grado, grupo: grupo, turno: turno};
}
function subirOrientadores() {
 if (!permisoPermite()) return;
 const file = $('#archivo-orientadores')[0].files[0];
 if (!file) { alert('Selecciona un archivo con los orientadores (nombre, grado, grupo y turno).'); return; }
 if (!/\.(xlsx|xls|csv|pdf)$/i.test(file.name)) { alert('Usa un archivo Excel (.xlsx/.xls), .csv o .pdf.'); return; }
 leerFilasDesdeArchivo(file, rows => {
  rows = rows.filter(r => r[0] && String(r[0]).trim());
  if (rows.length && /nombre|orientador/i.test(rows[0][0])) rows.shift();
  let nuevos = 0, existentes = 0, asignados = 0, iguales = 0;
  const avisos = [];
  rows.forEach(r => {
   const f = leerFilaOrientador(r);
   if (!f.nombre) return;
   let o = db.orientadores.find(x => norm(x.nombre) === norm(f.nombre));
   if (o) existentes++; else { o = {id: generarId(), nombre: f.nombre}; db.orientadores.push(o); nuevos++; }
   if (!f.grado && !f.grupo) return;   // solo trae el nombre: queda sin grupo
   if (f.grado < 1 || f.grado > 3 || f.grupo < 1 || f.grupo > 4) { avisos.push(f.nombre + ': grado o grupo no válido (grado 1 a 3, grupo 1 a 4); quedó sin grupo.'); return; }
   const turnos = f.turno === 'Ambos' ? ['Matutino', 'Vespertino'] : [f.turno || turnoActivo];
   turnos.forEach(turno => {
   const clave = claveGrupo(f.grado, f.grupo, turno);
   const etiqueta = f.grado + '° grado, grupo ' + f.grupo + ' (' + turno + ')';
   const h = db.horarios[clave] || {entrada: '', salida: '', orientadorId: ''};
   if (h.orientadorId === o.id) { iguales++; return; }
   if (h.orientadorId) {   // el grupo ya tenía otro orientador: se cambia por este
    const otro = db.orientadores.find(x => x.id === h.orientadorId);
    avisos.push(etiqueta + ': cambió de ' + (otro ? otro.nombre : 'otro orientador(a)') + ' a ' + f.nombre + '.');
   }
   h.orientadorId = o.id;
   db.horarios[clave] = h;   // si el grupo ya tenía horario, se conserva
   asignados++;
   });
  });
  guardarOrientadoresServidor();
  poblarSelectOrientadorHorario();
  renderListaOrientadores();
  refrescarHorarioForm();
  renderTabla();
  let html = 'Orientadores nuevos: ' + nuevos + '. Ya existían: ' + existentes + '. Asignados a un grupo: ' + asignados + (iguales ? '. Ya estaban en ese grupo: ' + iguales : '') + '.';
  if (avisos.length) html += '<div class="text-danger mt-1"><b>Revisa:</b><br>' + avisos.map(esc).join('<br>') + '</div>';
  $('#resumen-orientadores').html(html);
  $('#archivo-orientadores').val('');
 }, msg => alert(msg));
}
/* Lista desplegable de orientadores dados de alta: ahí se ve a qué grupo está cada uno y se puede eliminar. */
function renderListaOrientadores() {
 const caja = $('#lista-orientadores-actual');
 const abierta = caja.find('details').prop('open');   // si estaba abierta, sigue abierta después de eliminar
 if (!db.orientadores.length) { caja.html('<span class="text-muted">Aún no hay orientadores dados de alta.</span>'); return; }
 const filas = db.orientadores.slice().sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')).map(o => {
  const chips = Object.entries(db.horarios).filter(([k, v]) => v.orientadorId === o.id).map(([k]) => { const c = parseClave(k);
   return '<span class="badge bg-light text-dark border me-1 mb-1">' + c.grado + '° grado, grupo ' + c.grupo + ' · ' + c.turno +
    ' <a href="#" class="text-danger text-decoration-none ms-1" title="Quitar de este grupo" data-o="' + esc(o.id) + '" data-k="' + esc(k) + '" onclick="quitarGrupoOrientador(this.dataset.o, this.dataset.k); return false;">&times;</a></span>'; });
  return '<div class="ori-item"><div><div class="fw-semibold">' + esc(o.nombre) + '</div>' +
   '<div class="small ' + (chips.length ? '' : 'text-danger') + '">' + (chips.length ? chips.join('') : 'Sin grupo asignado') + '</div></div>' +
   '<div class="d-flex gap-1 flex-shrink-0"><button type="button" data-o="' + esc(o.id) + '" onclick="editarOrientador(this.dataset.o)" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Cambiar grados y grupos">Editar</button>' +
   '<button type="button" data-o="' + esc(o.id) + '" onclick="eliminarOrientador(this.dataset.o)" class="btn btn-outline-danger btn-sm py-0 px-2" title="Eliminar orientador">Eliminar</button></div></div>' + (oriEditando && oriEditando.id === o.id ? htmlEditorOrientador(oriEditando) : '');
 }).join('');
 caja.html('<details class="ori-desplegable"' + (abierta ? ' open' : '') + '><summary>Ver orientadores dados de alta (' + db.orientadores.length + ')</summary><div class="ori-lista">' + filas + '</div></details>');
}
/* Quita a un orientador de UN grupo (conserva el horario del grupo y sus demás grupos). */
function quitarGrupoOrientador(id, clave) {
 if (rolActivo !== 'control') { alert('Solo el Encargado de Control Escolar puede cambiar las asignaciones.'); return; }
 const h = db.horarios[clave]; if (!h || h.orientadorId !== id) return;
 const c = parseClave(clave), o = db.orientadores.find(x => x.id === id);
 if (!confirm('¿Quitar a ' + (o ? o.nombre : 'este orientador(a)') + ' del ' + c.grado + '° grado, grupo ' + c.grupo + ' (' + c.turno + ')? El grupo quedará sin orientador.')) return;
 h.orientadorId = '';
 guardarOrientadoresServidor(); renderListaOrientadores(); poblarSelectOrientadorHorario(); refrescarHorarioForm(); renderTabla();
}
/* ---- Editar los grados y grupos de un orientador (puede tener varios) ---- */
let oriEditando = null;   // {id, filas:[{turno, grado, grupo}]}
function editarOrientador(id) {
 if (rolActivo !== 'control') { alert('Solo el Encargado de Control Escolar puede editar orientadores.'); return; }
 if (oriEditando && oriEditando.id === id) { oriEditando = null; renderListaOrientadores(); return; }
 const filas = Object.entries(db.horarios).filter(([k, v]) => v.orientadorId === id).map(([k]) => { const c = parseClave(k); return {turno: c.turno, grado: Number(c.grado), grupo: Number(c.grupo)}; });
 oriEditando = {id: id, filas: filas.length ? filas : [{turno: turnoActivo, grado: 1, grupo: 1}]};
 renderListaOrientadores();
}
function htmlEditorOrientador(e) {
 const opt = (vals, sel, txt) => vals.map(v => '<option value="' + v + '"' + (v === sel ? ' selected' : '') + '>' + (txt ? txt(v) : v) + '</option>').join('');
 const filas = e.filas.map((f, i) =>
  '<div class="d-flex gap-2 align-items-center mb-2">' +
  '<select class="form-select form-select-sm" style="max-width:140px" onchange="oriEditando.filas[' + i + '].turno=this.value">' + opt(['Matutino', 'Vespertino'], f.turno) + '</select>' +
  '<select class="form-select form-select-sm" style="max-width:120px" onchange="oriEditando.filas[' + i + '].grado=Number(this.value)">' + opt([1, 2, 3], f.grado, v => v + '° grado') + '</select>' +
  '<select class="form-select form-select-sm" style="max-width:120px" onchange="oriEditando.filas[' + i + '].grupo=Number(this.value)">' + opt([1, 2, 3, 4], f.grupo, v => 'Grupo ' + v) + '</select>' +
  '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" title="Quitar este grupo" onclick="oriEditando.filas.splice(' + i + ',1); renderListaOrientadores()">&times;</button></div>').join('');
 return '<div class="p-2 border-bottom" style="background:#fdf3f5"><div class="small fw-semibold mb-2">Grados y grupos de este orientador(a)</div>' +
  (filas || '<div class="small text-muted mb-2">Sin grupos: quedará sin grupo asignado.</div>') +
  '<div class="d-flex gap-2 mt-1"><button type="button" class="btn btn-outline-dark btn-sm py-0 px-2" onclick="oriEditando.filas.push({turno:turnoActivo,grado:1,grupo:1}); renderListaOrientadores()">+ Agregar grupo</button>' +
  '<button type="button" class="btn btn-vinotinto btn-sm py-0 px-3" onclick="guardarGruposOrientador()">Guardar</button>' +
  '<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="oriEditando=null; renderListaOrientadores()">Cancelar</button></div></div>';
}
function guardarGruposOrientador() {
 if (!oriEditando || rolActivo !== 'control') return;
 const id = oriEditando.id, o = db.orientadores.find(x => x.id === id); if (!o) { oriEditando = null; return; }
 const nuevas = [];
 for (const f of oriEditando.filas) { const k = claveGrupo(f.grado, f.grupo, f.turno); if (!nuevas.includes(k)) nuevas.push(k); }
 const quitan = [];
 nuevas.forEach(k => { const h = db.horarios[k]; if (h && h.orientadorId && h.orientadorId !== id) { const otro = db.orientadores.find(x => x.id === h.orientadorId), c = parseClave(k); quitan.push(c.grado + '° grado, grupo ' + c.grupo + ' (' + c.turno + ') — hoy de ' + (otro ? otro.nombre : 'otro orientador(a)')); } });
 if (quitan.length && !confirm('Estos grupos ya tienen otro orientador(a) y pasarán a ' + o.nombre + ':\n' + quitan.join('\n') + '\n¿Continuar?')) return;
 Object.keys(db.horarios).forEach(k => { if (db.horarios[k].orientadorId === id && !nuevas.includes(k)) db.horarios[k].orientadorId = ''; });
 nuevas.forEach(k => { const h = db.horarios[k] || {entrada: '', salida: '', orientadorId: ''}; h.orientadorId = id; db.horarios[k] = h; });
 oriEditando = null;
 guardarOrientadoresServidor(); renderListaOrientadores(); poblarSelectOrientadorHorario(); refrescarHorarioForm(); renderTabla();
}
/* Elimina un orientador del servidor y de este navegador. Sus grupos quedan "sin asignar". Solo Control Escolar. */
function eliminarOrientador(id) {
 if (rolActivo !== 'control') { alert('Solo el Encargado de Control Escolar puede eliminar orientadores.'); return; }
 const o = db.orientadores.find(x => x.id === id); if (!o) return;
 const grupos = Object.entries(db.horarios).filter(([k, v]) => v.orientadorId === id).map(([k]) => { const c = parseClave(k); return 'Grado ' + c.grado + ' Grupo ' + c.grupo + ' (' + c.turno + ')'; });
 if (!confirm('¿Eliminar a ' + o.nombre + '?' + (grupos.length ? '\nSe quitará de: ' + grupos.join(', ') + ' (quedará sin orientador).' : '') + '\nSe borrará de forma permanente de la base de datos, junto con su contraseña. Esta acción no se puede deshacer.')) return;
 fetch('/api/sica/sync', {
 method: 'POST',
 headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''},
 body: JSON.stringify({accion:'eliminar_orientador', datos:{id:o.id, nombre:o.nombre}})
 }).then(r => r.json().catch(() => ({})).then(d => { if (!r.ok || d.ok === false) return Promise.reject(); }))
 .then(() => {
 db.orientadores = db.orientadores.filter(x => x.id !== id);
 if (localStorage.getItem('sica_orientador') === id) localStorage.removeItem('sica_orientador');
 Object.values(db.horarios).forEach(h => { if (h.orientadorId === id) h.orientadorId = ''; });
 localStorage.setItem(LS, JSON.stringify(db));
 renderListaOrientadores(); poblarSelectOrientadorHorario(); renderTabla();
 }).catch(() => alert('No se pudo eliminar en el servidor. Revisa tu conexión e inténtalo de nuevo.'));
}
/* Grupos que le corresponden a un orientador, según lo que asignó Control Escolar */
function calcularGruposOrientador(orientadorId) {
 return Object.entries(db.horarios)
 .filter(([k, v]) => v.orientadorId === orientadorId)
 .map(([k]) => parseClave(k));
}
/* Grupos (todos) que ya tiene cada orientador, para mostrarlo en la lista. Un orientador puede tener varios grupos. */
function gruposDeOrientadores(claveActual) {
 const m = {};
 Object.entries(db.horarios).forEach(([k, v]) => { if (v.orientadorId && k !== claveActual) (m[v.orientadorId] = m[v.orientadorId] || []).push(k); });
 return m;
}
function poblarSelectOrientadorHorario() {
 const sel = $('#orientador-grupo-select');
 const claveAct = filtro ? claveGrupo(filtro.grado, filtro.grupo, turnoActivo) : '';
 const actualId = filtro ? (db.horarios[claveAct] || {}).orientadorId : '';
 const otros = filtro ? gruposDeOrientadores(claveAct) : {};
 let html = '<option value="">Sin asignar</option>';
 db.orientadores.slice().sort((x, y) => x.nombre.localeCompare(y.nombre, 'es')).forEach(o => {
 const extra = (otros[o.id] || []).map(k => { const c = parseClave(k); return c.grado + '°' + c.grupo + ' ' + c.turno.slice(0, 3) + '.'; });
 html += '<option value="' + esc(o.id) + '">' + esc(o.nombre) + (extra.length ? ' (también en: ' + extra.join(', ') + ')' : '') + '</option>';
 });
 sel.html(html);
 sel.val(actualId || '');
}
/* ============ NAVEGACIÓN / ROLES ============ */
function actualizarCredenciales() {
 const rol = $('#rol-select').val();
 $('#input-correo').val(correosRoles[rol] || '');
 const esOrientador = rol === 'orientador';
 $('#campo-orientador-login').toggleClass('d-none', !esOrientador);
 if (esOrientador) {
 const sel = $('#select-orientador-login');
 sel.html('<option value="" selected disabled>Selecciona tu nombre...</option>' +
 db.orientadores.map(o => '<option value="' + esc(o.id) + '">' + esc(o.nombre) + '</option>').join(''));
 $('#aviso-sin-orientadores').toggleClass('d-none', db.orientadores.length > 0);
 }
}
/* Muestra u oculta lo que se escribe en la contraseña (útil en teléfono y en equipos nuevos) */
function verOcultarClave() {
 const campo = document.getElementById('input-clave');
 const boton = document.getElementById('btn-ver-clave');
 const mostrar = campo.type === 'password';
 campo.type = mostrar ? 'text' : 'password';
 document.getElementById('ojo-abierto').classList.toggle('d-none', mostrar);
 document.getElementById('ojo-cerrado').classList.toggle('d-none', !mostrar);
 const txt = mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña';
 boton.setAttribute('aria-label', txt);
 boton.setAttribute('title', txt);
 boton.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
 campo.focus();
}
function intentarEntrar() {
 const rol = $('#rol-select').val();
 const clave = $('#input-clave').val();
 if (!clave) { alert('Escribe tu contraseña.'); return; }
 if (rol === 'orientador') {
 const id = $('#select-orientador-login').val();
 if (!id) { alert('Selecciona tu nombre de la lista para continuar.'); return; }
 orientadorActivo = db.orientadores.find(o => o.id === id) || null;
 if (!orientadorActivo) { alert('No se encontró ese orientador. Intenta de nuevo.'); return; }
 } else {
 orientadorActivo = null;
 }
 fetch('/auth/login', {
 method: 'POST',
 headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''},
 body: JSON.stringify({email: $('#input-correo').val(), password: clave, orientador: orientadorActivo ? orientadorActivo.nombre : null})
 })
 .then(r => r.json().then(d => ({status: r.status, ...d})))
 .then(d => {
 if (!d.ok) {
 orientadorActivo = null;
 if (d.bloqueada) { $('#input-clave').val(''); abrirModalCuenta('bloqueada', d.mensaje); return; }
 alert(d.mensaje || 'No se pudo iniciar sesión.');
 return;
 }
 if (d.csrf) $('meta[name="csrf-token"]').attr('content', d.csrf);
 // El rol real lo dice el SERVIDOR. Si no coincide con el elegido (alguien tocó la página con F12), se cierra la sesión.
 if (d.rol !== rol) {
 orientadorActivo = null;
 fetch('/auth/logout', {method:'POST', headers:{'Accept':'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''}})
 .then(r => r.json()).then(x => { if (x && x.csrf) $('meta[name="csrf-token"]').attr('content', x.csrf); }).catch(() => {});
 alert('Esa cuenta no corresponde al rol seleccionado.');
 return;
 }
 if (orientadorActivo) localStorage.setItem('sica_orientador', orientadorActivo.id);   // para recordar quién eres si recargas la página
 $('#input-clave').val('').attr('type','password');
 $('#ojo-abierto').removeClass('d-none'); $('#ojo-cerrado').addClass('d-none');
 cargarDesdeServidor().then(() => {
 if (orientadorActivo) gruposDelOrientador = calcularGruposOrientador(orientadorActivo.id);
 mostrarSeccion('vista-sistema');
 guardarPaginaOffline();   // guarda la página con la sesión para poder abrir el sistema sin internet
 });
 })
 .catch(() => {
 orientadorActivo = null;
 alert('No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.');
 });
}
function mostrarSeccion(id) {
 $('#vista-inicio,#vista-login,#vista-sistema').addClass('d-none');
 $('#cuerpo-contenedor').toggleClass('bg-escuela', id !== 'vista-inicio').toggleClass('bg-login', id === 'vista-inicio');
 $('#' + id).removeClass('d-none');
 if (id === 'vista-login') actualizarCredenciales();
 if (id === 'vista-sistema') {
 const rol = rolActivo = $('#rol-select').val();
 if (!plantillasPedidas && !(ES_MOVIL && rol === 'orientador')) { plantillasPedidas = true; cargarPlantillasDefault(); }   // las plantillas de Word solo se bajan al entrar
 $('#badge-rol-activo').text('Rol: ' + nombresRoles[rol] + (orientadorActivo ? ' — ' + orientadorActivo.nombre : ''));
 indicePanelActual = 0;
 if (rol === 'orientador') {
 gruposDelOrientador = calcularGruposOrientador(orientadorActivo.id);
 $('#selector-pestanas-superior').hide();
 $('#btn-regresar-panel').addClass('d-none');
 seleccionarPerfil('orientador');
 } else {
 $('#selector-pestanas-superior').show();
 $('#btn-regresar-panel').removeClass('d-none');
 const esDirSub = (rol === 'director' || rol === 'subdirector');
 $('#tab-lector').closest('.col-tab-pestana').toggleClass('d-none', esDirSub);
 $('#fila-pestanas .col-tab-pestana').not($('#tab-lector').closest('.col-tab-pestana'))
 .removeClass('col-md-3').addClass(esDirSub ? 'col-md-4' : 'col-md-3');
 if (!esDirSub) $('#fila-pestanas .col-tab-pestana').removeClass('col-md-4').addClass('col-md-3');
 seleccionarPerfil(rol === 'control' ? 'control' : 'orientador');
 }
 // En teléfono, el orientador solo ve la cámara de escaneo
 document.body.classList.toggle('modo-movil', ES_MOVIL && rol === 'orientador');
 aplicarPermisos(rol);
 // En teléfono, la cámara (trasera) se enciende sola
 if (ES_MOVIL && rol === 'orientador') setTimeout(activarCamaraOrientador, 400);
 }
}
function cerrarSesion() {
 Object.keys(scanners).forEach(detener);
 // El service worker guarda la página de inicio para abrir sin internet: al cerrar sesión se borra esa copia
 try { if (navigator.serviceWorker && navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage('limpiar-paginas'); } catch (e) {}
 localStorage.removeItem('sica_orientador');
 orientadorActivo = null;
 rolActivo = null;
 _etagEstado = null;
 document.body.classList.remove('modo-movil');
 fetch('/auth/logout', {
 method: 'POST',
 headers: {'Accept':'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''}
 }).then(r => r.json())
 .then(d => { if (d && d.csrf) $('meta[name="csrf-token"]').attr('content', d.csrf); })
 .catch(() => {})
 .then(() => cargarOrientadoresLogin());
 mostrarSeccion('vista-inicio');
}
function seleccionarPerfil(perfil) {
 Object.keys(scanners).forEach(detener);
 gradoSeleccionado = null;
 $('.profile-card').removeClass('active'); $('.panel-perfil').addClass('d-none');
 $('#tab-' + perfil).addClass('active'); $('#panel-' + perfil).removeClass('d-none');
 if (perfil === 'orientador') { configurarPanelOrientador(); renderTabla(); }
 if (perfil === 'control') renderStats();
 if (perfil === 'alumno') refrescarPreviewPlantillaOficial(); // vista previa de la plantilla oficial (solo la ve Control Escolar)
 const i = ordenPaneles.indexOf(perfil); if (i !== -1) indicePanelActual = i;
}
function regresarPanel() {
 do {
 indicePanelActual = (indicePanelActual - 1 + ordenPaneles.length) % ordenPaneles.length;
 } while (ordenPaneles[indicePanelActual] === 'lector' && (rolActivo === 'director' || rolActivo === 'subdirector'));
 seleccionarPerfil(ordenPaneles[indicePanelActual]);
}
/* ============ PERMISOS ============ */
function permisoPermite() {
 if (rolActivo === 'control') return true;
 if ((db.permisos[rolActivo] || 'ver') !== 'editar') { alert('No tienes permiso para realizar esta acción. Se requiere autorización del Encargado de Control Escolar.'); return false; }
 return true;
}
function aplicarPermisos(rol) {
 const ok = rol === 'control' || db.permisos[rol] === 'editar';
 $('.btn-accion-restringida').toggleClass('bloqueado-permiso', !ok).attr('title', ok ? null : 'Requiere autorización de Control Escolar');
 $('#aviso-camara-bloqueada').toggleClass('d-none', ok);
 $('#bloque-permisos-roles').toggleClass('d-none', rol !== 'control');
 $('#bloque-claves').toggleClass('d-none', rol !== 'control');
 if (rol === 'control') cargarClaves();
 $('#bloque-plantilla-oficial').toggleClass('d-none', rol !== 'control'); // solo Control Escolar ve el bloque
 configurarPanelOrientador();
}
function guardarPermisoRol() {
 const rol = $('#select-rol-permiso').val(), tarea = $('#select-tarea-permiso').val();
 db.permisos[rol] = tarea; save();
 $('#resumen-permisos').text('Permiso actualizado: ' + nombresRoles[rol] + ' → ' + (tarea === 'editar' ? 'Puede editar / realizar acciones (incluye cámara)' : 'Solo visualizar'));
 sincronizarConServidor('guardar_permisos', db.permisos);
}
/* ============ CONTRASEÑAS (solo Control Escolar) ============ */
let clavesOrientadores = [];   // [{id, nombre, propia}] que devuelve el servidor
function llamarClaves(url, metodo, cuerpo, reintento) {
 return fetch(url, {
  method: metodo,
  headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''},
  body: cuerpo ? JSON.stringify(cuerpo) : undefined
 }).then(r => {
  if (r.status === 419 && !reintento) return refrescarCsrf().then(() => llamarClaves(url, metodo, cuerpo, true));
  if (r.status === 401) { sesionExpirada(); return {ok:false, mensaje:'Tu sesión expiró.'}; }
  return r.json().catch(() => ({})).then(d => {
   // Laravel responde los errores de validación con {message, errors}
   if (!r.ok && !d.mensaje) d.mensaje = (d.errors && Object.values(d.errors)[0][0]) || d.message || 'No se pudo guardar.';
   return {...d, ok: r.ok && d.ok !== false};
  });
 }).catch(() => ({ok:false, mensaje:'Sin conexión con el servidor. Inténtalo de nuevo cuando haya internet.'}));   // sin internet: no se queda un error sin capturar
}
const NOMBRES_CUENTA = {director: 'Director', subdirector: 'Subdirector', orientador: 'Orientadores · contraseña general', control: 'Control Escolar (mi cuenta)'};
let clavesRoles = [];        // [{rol, borrada, bloqueada}]
let cuentaSel = '';          // 'rol:director' | 'rol:control' | 'ori:ID' | '' (ninguna)
let menuOriAbierto = false;  // lista desplegable de orientadores abierta
let claveEntrega = null;     // {para, correo, clave}: solo en memoria, se borra al ocultar la tarjeta
let temporizadorClaves = null;
function etiquetaCuenta(borrada, bloqueada) {
 return bloqueada ? '<span class="etq mal">Bloqueada</span>' : borrada ? '<span class="etq mal">Sin contraseña</span>' : '<span class="etq bien">Activa</span>';
}
function cargarClaves() {
 return llamarClaves('/api/sica/claves', 'GET').then(d => {
  if (!d.ok) return;
  clavesOrientadores = d.orientadores || [];
  clavesRoles = d.roles || [];
  renderClaves();
  if (!temporizadorClaves) temporizadorClaves = setInterval(() => { if (rolActivo === 'control' && !document.hidden) cargarClaves(); }, 10000);
 });
}
function datosCuenta() {
 if (cuentaSel.startsWith('ori:')) {
  const o = clavesOrientadores.find(x => x.id === cuentaSel.slice(4));
  return o ? {nombre: o.nombre, tipo: 'ori', borrada: o.estado === 'borrada', bloqueada: !!o.bloqueada, propia: o.estado === 'propia', correo: correosRoles.orientador || ''} : null;
 }
 if (cuentaSel.startsWith('rol:')) {
  const rol = cuentaSel.slice(4), r = clavesRoles.find(x => x.rol === rol) || {};
  return {nombre: NOMBRES_CUENTA[rol] || rol, tipo: 'rol', rol: rol, control: rol === 'control', borrada: !!r.borrada, bloqueada: !!r.bloqueada, correo: correosRoles[rol] || ''};
 }
 return null;
}
function renderClaves() {
 const alerta = (b, o) => (b || o) ? ' alerta' : '';
 const rolBtn = rol => {
  const r = clavesRoles.find(x => x.rol === rol) || {};
  return '<button type="button" class="btn-cuenta' + (cuentaSel === 'rol:' + rol ? ' activa' : '') + alerta(r.borrada, r.bloqueada) + '" onclick="elegirCuenta(\'rol:' + rol + '\')">' +
   esc(NOMBRES_CUENTA[rol]) + (rol === 'control' ? '' : ' ' + etiquetaCuenta(r.borrada, r.bloqueada)) + '</button>';
 };
 const oSel = cuentaSel.startsWith('ori:') ? clavesOrientadores.find(x => x.id === cuentaSel.slice(4)) : null;
 const pendientesOri = clavesOrientadores.filter(o => o.bloqueada || o.estado === 'borrada').length;
 const items = clavesOrientadores.length ? clavesOrientadores.map(o =>
  '<button type="button" class="dd-item" onclick="elegirCuenta(\'ori:' + esc(o.id) + '\')"><span>' + esc(o.nombre) + '</span>' +
  (o.bloqueada ? '<span class="etq mal">Bloqueada</span>' : o.estado === 'borrada' ? '<span class="etq mal">Sin contraseña</span>' : o.estado === 'propia' ? '<span class="etq bien">Propia</span>' : '<span class="etq">General</span>') + '</button>'
 ).join('') : '<div class="small text-muted p-2">Aún no hay orientadores dados de alta.</div>';
 const personal = '<div class="dd-ori"><button type="button" class="btn-cuenta' + ((menuOriAbierto || oSel) ? ' activa' : '') + alerta(pendientesOri && !oSel, false) + '" onclick="alternarMenuOrientadores()">' +
  'Orientadores · contraseña personal' + (oSel ? ': ' + esc(oSel.nombre) : (pendientesOri ? ' <span class="etq mal">' + pendientesOri + '</span>' : '')) +
  ' </button>' +
  '<div class="dd-menu' + (menuOriAbierto ? '' : ' d-none') + '">' + items + '</div></div>';
 $('#botones-roles-clave').html(rolBtn('director') + rolBtn('subdirector') + rolBtn('orientador') + personal + rolBtn('control'));
 // Resumen de cuentas que esperan a Control Escolar
 const esperan = [];
 clavesRoles.forEach(r => { if (r.bloqueada || r.borrada) esperan.push(NOMBRES_CUENTA[r.rol] || r.rol); });
 clavesOrientadores.forEach(o => { if (o.bloqueada || o.estado === 'borrada') esperan.push(o.nombre); });
 $('#resumen-bloqueadas').html(esperan.length ? '<span class="badge bg-danger me-1">' + esperan.length + '</span> Esperan una contraseña nueva: <b>' + esperan.map(esc).join(', ') + '</b>' : '<span class="text-success">Ninguna cuenta está esperando contraseña.</span>');
 renderPanelCuenta();
 sincronizarBurbujas();
}
function renderPanelCuenta() {
 const c = datosCuenta();
 $('#panel-cuenta-clave').toggleClass('d-none', !c);
 if (!c) return;
 $('#panel-titulo').text(c.nombre);
 $('#panel-estado').html(c.control ? '' : etiquetaCuenta(c.borrada, c.bloqueada));
 $('#campo-clave-actual').toggleClass('d-none', !c.control);
 $('#btn-borrar-clave').toggleClass('d-none', !!c.control || c.borrada);
 $('#btn-desbloquear-clave').toggleClass('d-none', !c.bloqueada);
 $('#btn-quitar-clave').toggleClass('d-none', !(c.tipo === 'ori' && c.propia));
 $('#panel-ayuda').text(c.control ? 'Para cambiar tu propia contraseña escribe primero la actual.'
  : c.bloqueada ? 'La persona marcó "¿Olvidaste tu contraseña?" y no puede entrar. Borra la anterior y genera una nueva para darle acceso otra vez.'
  : c.borrada ? 'No tiene contraseña: no puede entrar. Genera una nueva y entrégasela.'
  : c.tipo === 'ori' && !c.propia ? 'Usa la contraseña general del rol Orientador. Genera una propia para que no la comparta.'
  : 'Puedes borrar su contraseña o generarle una nueva directamente.');
}
/* Tocar un botón ya activo lo cierra: el panel y la lista se pliegan y todo lo de abajo sube */
function cerrarCuenta() {
 cuentaSel = ''; menuOriAbierto = false;
 limpiarCamposClave(); ocultarEntrega(); $('#resumen-claves').text('');
 renderClaves();
}
function elegirCuenta(v) {
 if (cuentaSel === v) { cerrarCuenta(); return; }
 if (cuentaSel) { limpiarCamposClave(); ocultarEntrega(); $('#resumen-claves').text(''); }
 cuentaSel = v; menuOriAbierto = false;
 renderClaves();
 setTimeout(() => { const el = document.getElementById('panel-cuenta-clave'); if (el && el.scrollIntoView) el.scrollIntoView({behavior: 'smooth', block: 'nearest'}); }, 0);
}
function alternarMenuOrientadores() {
 if (menuOriAbierto || cuentaSel.startsWith('ori:')) { cerrarCuenta(); return; }
 if (cuentaSel) { cuentaSel = ''; limpiarCamposClave(); ocultarEntrega(); $('#resumen-claves').text(''); }
 menuOriAbierto = true;
 renderClaves();
}
$(document).on('click', function (e) {
 if (menuOriAbierto && !$(e.target).closest('.dd-ori').length) { menuOriAbierto = false; renderClaves(); }
});
/* Burbujas para Control Escolar: aparecen cuando otra cuenta pulsa "¿Olvidaste tu contraseña?" */
let burbujasVistas = {};
function sincronizarBurbujas() {
 if (rolActivo !== 'control') { $('#burbujas-claves').empty(); return; }
 const pend = [];
 clavesRoles.forEach(r => { if (r.bloqueada) pend.push({cuenta: 'rol:' + r.rol, nombre: NOMBRES_CUENTA[r.rol] || r.rol, k: 'rol:' + r.rol + '|' + r.olvido_at}); });
 clavesOrientadores.forEach(o => { if (o.bloqueada) pend.push({cuenta: 'ori:' + o.id, nombre: 'Orientador ' + o.nombre, k: 'ori:' + o.id + '|' + o.olvido_at}); });
 const claves = pend.map(p => p.k);
 $('#burbujas-claves .burbuja').each(function () { if (claves.indexOf($(this).attr('data-k')) === -1) $(this).remove(); });
 pend.forEach(p => {
  if (burbujasVistas[p.k]) return;
  burbujasVistas[p.k] = true;
  $('#burbujas-claves').append(
   '<div class="burbuja" role="alert" data-k="' + esc(p.k) + '" data-cuenta="' + esc(p.cuenta) + '">' +
   '<button type="button" class="burbuja-x" aria-label="Cerrar" onclick="$(this).closest(\'.burbuja\').remove()">&times;</button>' +
   '<div class="fw-bold">' + esc(p.nombre) + ' solicita su nueva contraseña</div>' +
   '<div class="small mt-1">Marcó "¿Olvidaste tu contraseña?". Su cuenta quedó bloqueada y no podrá entrar hasta que le des acceso.</div>' +
   '<button type="button" class="btn btn-sm btn-light fw-semibold mt-2" onclick="abrirSolicitud(this)">Dar acceso</button></div>');
 });
}
function abrirSolicitud(btn) {
 const b = $(btn).closest('.burbuja');
 const v = b.attr('data-cuenta');
 b.remove();
 try { seleccionarPerfil('control'); } catch (e) {}   // abre el panel de Control Escolar aunque falle la gráfica
 cuentaSel = '';
 elegirCuenta(v);
 setTimeout(() => { const el = document.getElementById('bloque-claves'); if (el && el.scrollIntoView) el.scrollIntoView({behavior: 'smooth', block: 'start'}); }, 50);
}
/* Indicaciones en vivo: se marcan en verde las que ya cumple la contraseña escrita */
function revisarRequisitosClave() {
 const n = $('#clave-nueva').val() || '', c = $('#clave-confirmar').val() || '';
 const ok = {
  largo: n.length >= 8 && n.length <= 72,
  mayus: /\p{Lu}/u.test(n) && /\p{Ll}/u.test(n),
  numero: /\d/.test(n),
  espacios: n.length > 0 && !/\s/.test(n),
  especial: n.length > 0 && /^[A-Za-z0-9]+$/.test(n),
  escuela: n.length > 0 && !/prepa0?6|epo0?6/i.test(n),
  igual: n.length > 0 && n === c
 };
 $('#requisitos-clave li').each(function () {
  const cumple = ok[$(this).data('req')];
  $(this).toggleClass('cumple text-success fw-semibold', cumple);
 });
 return Object.values(ok).every(Boolean);
}
function verClavesNuevas() {
 const t = $('#ver-claves').is(':checked') ? 'text' : 'password';
 $('#clave-actual,#clave-nueva,#clave-confirmar').attr('type', t);
}
function limpiarCamposClave() {
 $('#clave-actual,#clave-nueva,#clave-confirmar').val('');
 $('#ver-claves').prop('checked', false); verClavesNuevas(); revisarRequisitosClave();
}
/* Genera una contraseña segura al azar con el generador criptográfico del navegador (sin letras ni números que se confunden) */
function generarClave() {
 const may = 'ABCDEFGHJKLMNPQRSTUVWXYZ', min = 'abcdefghijkmnpqrstuvwxyz', num = '23456789', todo = may + min + num;
 const al = n => { const b = new Uint32Array(1), lim = Math.floor(0x100000000 / n) * n; let x; do { crypto.getRandomValues(b); x = b[0]; } while (x >= lim); return x % n; };
 let c;
 do {
  const ch = [may[al(may.length)], min[al(min.length)], num[al(num.length)]];
  while (ch.length < 12) ch.push(todo[al(todo.length)]);
  for (let i = ch.length - 1; i > 0; i--) { const j = al(i + 1); [ch[i], ch[j]] = [ch[j], ch[i]]; }
  c = ch.join('');
 } while (/prepa0?6|epo0?6/i.test(c));
 $('#clave-nueva,#clave-confirmar').val(c);
 $('#ver-claves').prop('checked', true); verClavesNuevas(); revisarRequisitosClave();
}
function guardarClave() {
 const c = datosCuenta();
 if (!c) { alert('Primero elige una cuenta.'); return; }
 const nueva = $('#clave-nueva').val(), conf = $('#clave-confirmar').val();
 if (!revisarRequisitosClave()) { alert('La contraseña nueva aún no cumple las indicaciones que aparecen debajo de las casillas.'); return; }
 let url, cuerpo;
 if (c.tipo === 'ori') {
  url = '/api/sica/claves/orientador';
  cuerpo = {orientador_id: cuentaSel.slice(4), password: nueva, password_confirmation: conf};
 } else {
  url = '/api/sica/claves/rol';
  cuerpo = {rol: c.rol, password: nueva, password_confirmation: conf, password_actual: $('#clave-actual').val()};
 }
 llamarClaves(url, 'POST', cuerpo).then(d => {
  if (!d.ok) { $('#resumen-claves').text(''); alert(d.mensaje || 'No se pudo guardar la contraseña.'); return; }
  if (c.control) {
   $('#resumen-claves').text('Tu contraseña de Control Escolar se actualizó.');
  } else {
   claveEntrega = {para: c.nombre, correo: c.correo, clave: nueva};
   $('#entrega-para').text(c.nombre); $('#entrega-correo').text(c.correo); $('#entrega-clave-texto').text(nueva);
   $('#entrega-clave').removeClass('d-none');
   $('#resumen-claves').text('Contraseña nueva guardada para ' + c.nombre + '. Ya puede entrar con ella.');
  }
  limpiarCamposClave();
  cargarClaves();
 }).catch(() => alert('No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.'));
}
function ocultarEntrega() {
 claveEntrega = null;
 $('#entrega-clave-texto').text(''); $('#entrega-clave').addClass('d-none');
}
function copiarEntrega() {
 if (!claveEntrega) return;
 const t = claveEntrega.clave;
 const aviso = () => $('#resumen-claves').text('Contraseña copiada. Entrégala en persona.');
 if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(t).then(aviso).catch(() => {}); return; }
 const ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select();
 try { document.execCommand('copy'); aviso(); } catch (e) {}
 document.body.removeChild(ta);
}
function imprimirEntrega() {
 if (!claveEntrega) return;
 const w = window.open('', '_blank', 'width=640,height=720');
 if (!w) { alert('El navegador bloqueó la ventana de impresión. Permite ventanas emergentes e inténtalo otra vez.'); return; }
 const fecha = new Date().toLocaleDateString('es-MX', {day: '2-digit', month: 'long', year: 'numeric'});
 w.document.write('<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Entrega de contraseña</title>' +
  '<style>body{font-family:Arial,sans-serif;padding:40px;color:#222}h2{color:#800020;margin:0 0 4px}.c{border:2px dashed #800020;border-radius:10px;padding:18px;margin:22px 0}' +
  '.k{font:700 26px monospace;letter-spacing:2px}small{color:#555}</style></head><body>' +
  '<h2>Escuela Preparatoria Oficial Núm. 6</h2><div>SICA-EPO6 · Entrega de contraseña</div>' +
  '<div class="c"><div>Para: <b>' + esc(claveEntrega.para) + '</b></div><div>Correo: <b>' + esc(claveEntrega.correo) + '</b></div>' +
  '<div style="margin-top:14px">Contraseña:</div><div class="k">' + esc(claveEntrega.clave) + '</div></div>' +
  '<small>Fecha: ' + esc(fecha) + '. Entregada por Control Escolar. Guarda esta hoja en un lugar seguro o destrúyela después de memorizar la contraseña. No la compartas con nadie.</small>' +
  '</body></html>');
 w.document.close(); w.focus(); w.print();
}
/* Control Escolar borra la contraseña guardada de la cuenta elegida y después genera una nueva */
function borrarClaveCuenta() {
 const c = datosCuenta();
 if (!c || c.control) return;
 if (!confirm('Se borrará la contraseña guardada de: ' + c.nombre + '.\nNo podrá entrar hasta que generes una nueva aquí mismo. No se borra ningún otro dato. ¿Continuar?')) return;
 const url = c.tipo === 'ori' ? '/api/sica/claves/orientador/' + encodeURIComponent(cuentaSel.slice(4)) + '/borrar' : '/api/sica/claves/rol/' + encodeURIComponent(c.rol);
 llamarClaves(url, c.tipo === 'ori' ? 'POST' : 'DELETE').then(d => {
  if (!d.ok) { alert(d.mensaje || 'No se pudo borrar la contraseña.'); return; }
  ocultarEntrega();
  $('#resumen-claves').text('Se borró la contraseña anterior de ' + c.nombre + '. Pulsa Generar, luego Guardar, y entrégasela.');
  cargarClaves();
 }).catch(() => alert('No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.'));
}
/* Quita el bloqueo sin cambiar la contraseña (por ejemplo, si alguien lo marcó por error) */
function desbloquearCuenta() {
 const c = datosCuenta();
 if (!c || !c.bloqueada) return;
 if (!confirm('Se quitará el bloqueo de ' + c.nombre + ' sin cambiar su contraseña. ¿Continuar?')) return;
 const url = c.tipo === 'ori' ? '/api/sica/claves/orientador/' + encodeURIComponent(cuentaSel.slice(4)) + '/desbloquear' : '/api/sica/claves/rol/' + encodeURIComponent(c.rol) + '/desbloquear';
 llamarClaves(url, 'POST').then(d => {
  if (!d.ok) { alert(d.mensaje || 'No se pudo desbloquear.'); return; }
  $('#resumen-claves').text('Se quitó el bloqueo de ' + c.nombre + '.');
  cargarClaves();
 });
}
function quitarClaveOrientador() {
 if (!cuentaSel.startsWith('ori:')) return;
 if (!confirm('Este orientador volverá a entrar con la contraseña general del rol Orientador. ¿Continuar?')) return;
 llamarClaves('/api/sica/claves/orientador/' + encodeURIComponent(cuentaSel.slice(4)), 'DELETE').then(d => {
  if (!d.ok) { alert(d.mensaje || 'No se pudo quitar la contraseña propia.'); return; }
  $('#resumen-claves').text('Se quitó la contraseña propia. Ahora usa la general del rol Orientador.');
  cargarClaves();
 });
}
/* ---- Pantalla de inicio de sesión: ventana central de "¿Olvidaste tu contraseña?" ---- */
function abrirModalCuenta(modo, mensaje) {
 const rol = $('#rol-select').val();
 const nombre = rol === 'orientador' ? ($('#select-orientador-login option:selected').text() || 'Orientador') : (nombresRoles[rol] || rol);
 const btn = (txt, cls, fn) => '<button type="button" class="btn ' + cls + ' px-4" onclick="' + fn + '">' + txt + '</button>';
 let titulo, texto, botones;
 if (modo === 'confirmar') {
  titulo = '¿Olvidaste tu contraseña?';
  texto = 'Avisa a Control Escolar. Tu cuenta (<b>' + esc(nombre) + '</b>) quedará bloqueada automáticamente y no podrás entrar hasta que Control Escolar borre tu contraseña anterior, genere una nueva y te dé acceso otra vez.';
  botones = btn('Avisar a Control Escolar', 'btn-vinotinto', 'confirmarOlvido()');
 } else if (modo === 'info') {
  titulo = 'Control Escolar';
  texto = mensaje;
  botones = btn('Entendido', 'btn-vinotinto', 'cerrarModalCuenta()');
 } else {
  titulo = 'Cuenta bloqueada';
  texto = mensaje || 'Avisa a Control Escolar. Mientras tanto no podrás entrar.';
  botones = btn('Entendido', 'btn-vinotinto', 'cerrarModalCuenta()');
 }
 $('#modal-cuenta-titulo').text(titulo);
 $('#modal-cuenta-texto').html(modo === 'confirmar' ? texto : esc(texto));
 $('#modal-cuenta-botones').html(botones);
 $('#modal-cuenta').removeClass('d-none');
}
function cerrarModalCuenta() { $('#modal-cuenta').addClass('d-none'); }
function olvideContrasena() {
 const rol = $('#rol-select').val();
 if (rol === 'orientador' && !$('#select-orientador-login').val()) { alert('Primero selecciona tu nombre de la lista.'); return; }
 abrirModalCuenta('confirmar');
}
function confirmarOlvido(reintento) {
 const rol = $('#rol-select').val();
 const o = rol === 'orientador' ? db.orientadores.find(x => x.id === $('#select-orientador-login').val()) : null;
 // El correo se toma del ROL elegido (no del campo de texto): en computadora el navegador puede dejar ese campo vacío,
 // con otro rol o autocompletado con un correo guardado, y entonces el servidor rechazaba la petición sin avisar.
 const email = correosRoles[rol] || $('#input-correo').val();
 $('#input-correo').val(email);
 const btn = $('#modal-cuenta-botones button').prop('disabled', true);
 fetch('/auth/olvide', {
  method: 'POST',
  credentials: 'same-origin',
  headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''},
  body: JSON.stringify({email: email, orientador: o ? o.nombre : null})
 }).then(r => r.json().catch(() => ({})).then(d => ({status: r.status, ...d})))
 .then(d => {
  // 419: la página llevaba mucho tiempo abierta y su token venció (pasa en computadoras) -> se pide uno nuevo y se reintenta UNA vez
  if (d.status === 419 && !reintento) return refrescarCsrf().then(() => confirmarOlvido(true));
  btn.prop('disabled', false);
  $('#input-clave').val('');
  if (d.ok === false || !d.mensaje) {
   let msg = d.mensaje;
   if (!msg) {
    if (d.status === 429) msg = 'Demasiados intentos. Espera un minuto.';
    else if (d.status === 419) msg = 'La página estuvo abierta demasiado tiempo. Recárgala (F5) e inténtalo de nuevo.';
    else if (d.status === 422) msg = 'No se pudo identificar tu cuenta. Recarga la página (F5), elige tu rol y vuelve a intentarlo.';
    else if (d.status >= 500) {
     // Error interno del servidor. Con APP_DEBUG=true (modo local) Laravel manda la causa real en d.message; en producción solo dice "Server Error".
     msg = 'Error del servidor (' + d.status + ')' + (d.message && d.message !== 'Server Error' ? ': ' + String(d.message).slice(0, 300) : '. Revisa el registro del servidor (storage/logs/laravel.log).');
    }
    else msg = 'No se pudo completar (error ' + d.status + '). Inténtalo de nuevo.';
   }
   abrirModalCuenta('info', msg); return;
  }
  abrirModalCuenta(d.bloqueada ? 'bloqueada' : 'info', d.mensaje);
 })
 .catch(() => { btn.prop('disabled', false); abrirModalCuenta('info', 'No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.'); });
}
/* ============ COLA DE ESCANEOS (funciona sin internet) ============
 Cada escaneo se guarda primero en este equipo y se envía al servidor en cuanto hay conexión.
 Un escaneo solo sale de la cola cuando el servidor confirma que lo guardó: si no hay internet
 se queda esperando (aunque se cierre la página o el navegador) y se reintenta solo. */
const LS_COLA = 'sica_epo6_cola_v1';
let colaEscaneos = [];
try { colaEscaneos = JSON.parse(localStorage.getItem(LS_COLA) || '[]'); } catch (e) { colaEscaneos = []; }
if (!Array.isArray(colaEscaneos)) colaEscaneos = [];
let _vaciandoCola = false;
function guardarCola() {
 try { localStorage.setItem(LS_COLA, JSON.stringify(colaEscaneos)); } catch (e) {}
 pintarIndicadorCola();
}
function pintarIndicadorCola() {
 let el = document.getElementById('indicador-cola');
 if (!el) {
  if (!document.body) return;
  el = document.createElement('div'); el.id = 'indicador-cola';
  el.style.cssText = 'position:fixed;left:50%;bottom:12px;transform:translateX(-50%);z-index:2000;padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:600;color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.3);display:none;pointer-events:none';
  document.body.appendChild(el);
 }
 const n = colaEscaneos.length, sinRed = navigator.onLine === false;
 if (!n && !sinRed) { el.style.display = 'none'; return; }
 el.style.background = sinRed ? '#b45309' : '#800020';
 el.textContent = (sinRed ? 'Sin conexión' : 'Enviando') + (n ? ' · ' + n + (n === 1 ? ' escaneo pendiente' : ' escaneos pendientes') : '');
 el.style.display = 'block';
}
function encolarEscaneo(curp, modo, fecha, hora) {
 colaEscaneos.push({id: generarId(), curp: curp, modo: modo, fecha: fecha, hora: hora, ts: Date.now(), diferido: false});
 guardarCola();
 vaciarCola();
}
function vaciarCola() {
 if (_vaciandoCola || !colaEscaneos.length) return;
 _vaciandoCola = true;
 const terminar = huboExito => {
  _vaciandoCola = false;
  guardarCola();
  if (huboExito) cargarDesdeServidor();   // trae lo que escanearon los demás dispositivos
 };
 const enviar = (reintentoCsrf, huboExito) => {
  const it = colaEscaneos[0];
  if (!it) return terminar(huboExito);
  // Si el escaneo ya esperó (sin internet o en cola), el servidor usa la hora en que se escaneó de verdad
  const diferido = !!it.diferido || (Date.now() - (it.ts || 0) > 60000);
  fetchT('/api/sica/sync', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '' },
   body: JSON.stringify({ accion: 'registrar_asistencia', datos: {curp: it.curp, modo: it.modo, fecha: it.fecha, hora: it.hora, diferido: diferido} })
  }, 12000).then(r => {
   if (r.status === 419 && !reintentoCsrf) return refrescarCsrf().then(() => enviar(true, huboExito));
   if (r.status === 401) { terminar(huboExito); sesionExpirada(); return; }   // la cola se conserva hasta volver a iniciar sesión
   if (r.ok) { colaEscaneos.shift(); guardarCola(); return enviar(false, true); }
   if (r.status >= 500 || r.status === 419 || r.status === 429) { it.diferido = true; return terminar(huboExito); }
   // 403 / 404 / 422: el servidor lo rechazó de forma definitiva -> se descarta para no atorar la cola
   colaEscaneos.shift(); guardarCola();
   return r.json().catch(() => ({})).then(d => {
    avisoPermiso('Un escaneo pendiente (' + it.curp + ') fue rechazado por el servidor: ' + ((d && d.mensaje) || 'error ' + r.status));
    return enviar(false, huboExito);
   });
  }).catch(() => { it.diferido = true; terminar(huboExito); });   // sin conexión: se queda en la cola
 };
 enviar(false, false);
}
window.addEventListener('online', () => { pintarIndicadorCola(); if (rolActivo) vaciarCola(); });
window.addEventListener('offline', pintarIndicadorCola);
const etiquetaGrupo = x => (x && x.grado ? x.grado + '° grado, grupo ' + x.grupo + ' (' + x.turno + ')' : 'grupo sin asignar');
/* ============ ESCÁNER QR (cámara) ============ */
function iniciarScanner(el, modoFn) {
 if (scanners[el]) return;
 if (typeof Html5Qrcode === 'undefined') {
  window.SICA_LIBS_LISTAS.then(() => { if (typeof Html5Qrcode === 'undefined') alert('No se cargó la librería del escáner. Revisa tu conexión a internet.'); else iniciarScanner(el, modoFn); });
  return;
 }
 const s = new Html5Qrcode(el); scanners[el] = s;
 $('#' + el).siblings('.cam-ph').hide();
 // Teléfono: cámara trasera ('environment'). Computadora: la que haya ('user').
 const cfg = {fps:10, qrbox: w => { const m = Math.min(w.width, w.height); return {width:Math.floor(m*0.7), height:Math.floor(m*0.7)}; }};
 const alLeer = txt => procesarQR(txt.trim().toUpperCase(), modoFn(), el);
 const fallo = e => {
 delete scanners[el]; $('#' + el).siblings('.cam-ph').show();
 alert('No se pudo abrir la cámara (requiere permiso y https o localhost): ' + e);
 };
 const probar = (intentos, i) => s.start(intentos[i], cfg, alLeer).catch(e => (i + 1 < intentos.length) ? probar(intentos, i + 1) : fallo(e));
 if (!ES_MOVIL) { probar([{facingMode:'user'}], 0); return; } // Computadora: cámara frontal
 // Teléfono/tablet: SIEMPRE trasera. Se busca en la lista real de cámaras la que se llame back/rear/trasera...
 const reTrasera = /back|rear|trasera|posterior|traseira|environment|arri[eè]re/i, reExtra = /ultra|wide|tele|macro|depth|front|frontal|delantera/i;
 Html5Qrcode.getCameras().then(devs => {
 const traseras = devs.filter(d => reTrasera.test(d.label || ''));
 const elegida = traseras.find(d => !reExtra.test(d.label)) || traseras[0] || (devs.length > 1 ? devs[devs.length - 1] : null);
 return elegida ? [elegida.id] : [];
 }).catch(() => []).then(ids => {
 const intentos = ids.concat([{facingMode:{exact:'environment'}}, {facingMode:'environment'}]); // nunca se cae a la frontal
 return probar(intentos, 0);
 });
}
/* CAMBIO: el orientador elige si la cámara registra Entrada o Salida. Funciona con cualquier alumno,
 sin importar si es de su grupo (el registro busca por CURP en toda la base). */
let modoOrient = 'Entrada';
function setModoOrient(m) {
 modoOrient = m;
 $('#btn-orient-entrada').toggleClass('btn-success', m === 'Entrada').toggleClass('btn-outline-dark bg-white', m !== 'Entrada');
 $('#btn-orient-salida').toggleClass('btn-vinotinto', m === 'Salida').toggleClass('btn-outline-dark bg-white', m !== 'Salida');
}
function activarCamaraOrientador() { if (!permisoPermite()) return; iniciarScanner('cam-orient', () => modoOrient); }
function detener(el) {
 const s = scanners[el]; if (!s) return; delete scanners[el];
 s.stop().then(() => s.clear()).catch(() => {}); $('#' + el).siblings('.cam-ph').show();
}
function procesarQR(curp, modo, el) {
 if (ultimo.t === curp && Date.now() - ultimo.ts < 4000) return;
 ultimo = {t:curp, ts:Date.now()};
 const mostrar = r => {
  $('#res-' + el).removeClass('text-success text-danger').addClass(r.ok ? 'text-success' : 'text-danger').text(r.msg);
  if (navigator.vibrate) navigator.vibrate(r.ok ? 150 : [100,60,100]);
 };
 const r = registrarPorCurp(curp, modo);
 if (r && typeof r.then === 'function') {   // alumno que este equipo aún no tiene: lo busca el servidor
  $('#res-' + el).removeClass('text-success text-danger').text('Buscando alumno…');
  r.then(mostrar);
 } else mostrar(r);
}
function aplicarRegistro(a, modo) {
 if (!a) return {ok:false, msg:'Alumno no encontrado'};
 const f = hoy(), r = a.reg[f] = a.reg[f] || {};
 if (modo === 'Entrada') {
 if (r.entrada) return {ok:false, msg:a.nombre + ': la entrada ya estaba registrada (' + r.entrada + ')'};
 r.entrada = horaAhora();
 } else {
 if (!r.entrada) return {ok:false, msg:a.nombre + ': no tiene entrada registrada hoy'};
 if (r.salida) return {ok:false, msg:a.nombre + ': la salida ya estaba registrada (' + r.salida + ')'};
 r.salida = horaAhora();
 }
 // Cada escaneo entra a la cola: se envía al servidor ya (o en cuanto vuelva el internet) y aparece en todos los equipos
 if (a.curp) encolarEscaneo(a.curp, modo, f, horaAhora());
 save(); renderTabla();
 // El alumno se registra en SU grupo (el de la lista), sea o no del orientador que escanea
 return {ok:true, msg:modo + ' registrada: ' + a.nombre + ' — ' + etiquetaGrupo(a) + ' (' + horaAhora() + ')' +
  (navigator.onLine === false ? ' · guardada sin internet, se enviará al reconectar' : '')};
}
function registrarPorCurp(curp, modo) {
 const a = db.alumnos.find(x => x.curp && x.curp === curp);
 if (!a) return registrarDesconocido(curp, modo);
 return aplicarRegistro(a, modo);
}
/* El QR no está en la lista de este equipo (p. ej. Control Escolar lo dio de alta hace un momento):
   se le pregunta al servidor, que busca al alumno por CURP en TODOS los grupos y lo registra en el suyo. */
function registrarDesconocido(curp, modo) {
 if (navigator.onLine === false) return {ok:false, msg:'QR no reconocido: ' + curp + ' (sin internet: no está en la lista de este equipo)'};
 return fetch('/api/sica/sync', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '' },
  body: JSON.stringify({ accion: 'registrar_asistencia', datos: {curp: curp, modo: modo} })
 }).then(r => r.json().catch(() => ({})).then(d => {
  if (r.ok && d && d.ok) {
   cargarDesdeServidor();   // este equipo descarga al alumno nuevo
   const al = d.alumno || {};
   return {ok:true, msg:modo + ' registrada: ' + (al.nombre || curp) + ' — ' + etiquetaGrupo(al) + ' (' + (d.hora || horaAhora()) + ')'};
  }
  if (r.status === 404) return {ok:false, msg:'QR no reconocido: ' + curp};
  if (r.status === 401) { sesionExpirada(); return {ok:false, msg:'Tu sesión expiró. Inicia sesión de nuevo.'}; }
  if (r.status === 403) return {ok:false, msg:(d && d.mensaje) || 'No tienes permiso para registrar.'};
  if (r.status === 419) { refrescarCsrf(); return {ok:false, msg:'Escanea de nuevo (se renovó la sesión).'}; }
  return {ok:false, msg:'No se pudo registrar (error ' + r.status + '). Escanea de nuevo.'};
 })).catch(() => ({ok:false, msg:'QR no reconocido: ' + curp + ' (sin conexión con el servidor)'}));
}
function registrarPorId(id, modo) { const a = db.alumnos.find(x => x.id === id); return aplicarRegistro(a, modo); }
/* ============ LECTOR FIJO ============ */
function setModoEscaneo(m) {
 modoEscaneo = m;
 $('#btn-modo-entrada').toggleClass('btn-success', m === 'Entrada').toggleClass('btn-outline-dark bg-white', m !== 'Entrada');
 $('#btn-modo-salida').toggleClass('btn-vinotinto', m === 'Salida').toggleClass('btn-outline-dark bg-white', m !== 'Salida');
 $('#lbl-modo').text(m);
}
function toggleCamaraFija() { scanners['cam-fija'] ? detener('cam-fija') : iniciarScanner('cam-fija', () => modoEscaneo); }
/* ============ PANEL DE ORIENTADORES ============ */
function pintarBotonesTurno() {
 const t = turnoActivo;
 $('#btn-turno-matutino').toggleClass('btn-vinotinto', t === 'Matutino').toggleClass('btn-outline-dark bg-white', t !== 'Matutino');
 $('#btn-turno-vespertino').toggleClass('btn-vinotinto', t === 'Vespertino').toggleClass('btn-outline-dark bg-white', t !== 'Vespertino');
 $('#titulo-tabla-orientador').text('Turno ' + t);
}
/* Rellena horario, orientador y etiqueta del grupo según el grupo y el turno activos */
function refrescarHorarioForm() {
 if (!filtro) return;
 $('#lbl-grupo-lista').val(filtro.grado + '° grado - Grupo ' + filtro.grupo + ' (' + turnoActivo + ')');
 const h = db.horarios[claveGrupo(filtro.grado, filtro.grupo, turnoActivo)];
 $('#horario-entrada-limite').val(h ? h.entrada : '');
 $('#horario-salida-limite').val(h ? h.salida : '');
 poblarSelectOrientadorHorario();
}
function setTurno(t) {
 turnoActivo = t;
 pintarBotonesTurno();
 refrescarHorarioForm();
 renderTabla();
}
function configurarPanelOrientador() {
 const c = rolActivo === 'control';
 const o = rolActivo === 'orientador';
 $('#bloque-camara-orientador').toggleClass('d-none', c);
 $('#bloque-orientadores-control').toggleClass('d-none', !c);
 $('#bloque-listas-orientador').toggleClass('d-none', !c);
 $('#bloque-horario-control').toggleClass('d-none', !c);
 $('#bloque-visualizar-grupo').toggleClass('d-none', o);
 $('#bloque-mi-grupo-orientador').toggleClass('d-none', !o);
 $('#bloque-turno-panel').toggleClass('d-none', o); // el orientador ve solo el turno de su grupo
 if (c) { renderListaOrientadores(); poblarSelectOrientadorHorario(); }
 if (o) configurarMiGrupoOrientador();
}
function configurarMiGrupoOrientador() {
 const tieneGrupos = gruposDelOrientador.length > 0;
 $('#sin-grupo-orientador').toggleClass('d-none', tieneGrupos);
 $('#con-grupo-orientador').toggleClass('d-none', !tieneGrupos);
 if (!tieneGrupos) { filtro = null; return; }
 $('#sel-mi-grupo').html(gruposDelOrientador.map(g =>
 '<option value="' + claveGrupo(g.grado, g.grupo, g.turno) + '">' + nomGrado[g.grado] + ' grado - Grupo ' + g.grupo + ' (' + g.turno + ')</option>').join(''));
 const primero = claveGrupo(gruposDelOrientador[0].grado, gruposDelOrientador[0].grupo, gruposDelOrientador[0].turno);
 $('#sel-mi-grupo').val(primero);
 elegirMiGrupo(primero);
}
function elegirMiGrupo(valor) {
 const c = parseClave(valor);
 filtro = {grado:c.grado, grupo:c.grupo};
 turnoActivo = c.turno;
 pintarBotonesTurno();
 renderTabla();
}
function elegirGrupo(grado, grupo) {
 [1,2,3].filter(g => g !== grado).forEach(g => $('#sel-grado-' + g).val(''));
 filtro = {grado:grado, grupo:Number(grupo)};
 refrescarHorarioForm();
 renderTabla();
}
function filtrados() {
 return db.alumnos.filter(a => a.turno === turnoActivo && (!filtro || (a.grado === filtro.grado && a.grupo === filtro.grupo)));
}
/* Orientador(a) del grupo del alumno: sale solo de la asignación del grupo, así aparece apenas se sube la lista. */
function nombreOrientadorDe(a) {
 const h = db.horarios[claveGrupo(a.grado, a.grupo, a.turno)];
 const o = h && h.orientadorId ? db.orientadores.find(x => x.id === h.orientadorId) : null;
 return o ? esc(o.nombre) : '<span class="text-danger">Sin asignar</span>';
}
function renderTabla() {
 const f = $('#calendario-orientador').val() || hoy(), q = norm($('#buscador-alumno').val());
 const rows = filtrados().filter(a => norm(a.nombre).includes(q)).map(a => {
 const r = a.reg[f] || {}, e = estatusDe(a, r, f), dis = (!r.entrada || r.salida || f !== hoy()) ? 'disabled' : '';
 return `<tr><td>${a.curp ? '<code>'+esc(a.curp)+'</code>' : '<span class="badge bg-secondary">Sin CURP</span>'}</td><td>${esc(a.nombre)}</td><td>${a.grado}</td><td>${a.grupo}</td><td>${r.entrada || '—'}</td><td>${r.salida || 'Pendiente'}</td><td>${permDe(r)}</td><td><span class="badge ${claseEst[e]}">${e}</span></td><td>${nombreOrientadorDe(a)}</td><td class="text-center"><button data-c="${esc(a.id)}" onclick="marcarSalida(this.dataset.c)" class="btn btn-outline-dark btn-sm btn-accion-restringida" ${dis}>Marcar Salida</button></td></tr>`;
 }).join('') || '<tr><td colspan="10" class="text-center text-muted py-3">Sin alumnos. Control Escolar debe subir la lista de este grupo.</td></tr>';
 $('#tabla-cuerpo-orientador').html(rows);
 if (rolActivo) aplicarPermisosSolo();
 renderStats();
}
let _temporizadorTabla = null;
function renderTablaPronto() { clearTimeout(_temporizadorTabla); _temporizadorTabla = setTimeout(renderTabla, 150); }
function aplicarPermisosSolo() {
 const ok = rolActivo === 'control' || db.permisos[rolActivo] === 'editar';
 $('.btn-accion-restringida').toggleClass('bloqueado-permiso', !ok);
 $('#aviso-camara-bloqueada').toggleClass('d-none', ok);
}
function marcarSalida(id) { if (!permisoPermite()) return; const r = registrarPorId(id, 'Salida'); if (!r.ok) alert(r.msg); }
function subirLista() {
 if (!permisoPermite()) return;
 if (!filtro) { alert('Primero selecciona el grado y el grupo arriba (Visualizar Grupo).'); return; }
 const file = $('#archivo-lista')[0].files[0];
 if (!file) { alert('Selecciona un archivo para la lista.'); return; }
 if (!/\.(xlsx|xls|csv|pdf)$/i.test(file.name)) { alert('Para cargar los alumnos automáticamente usa un archivo Excel (.xlsx/.xls), .csv o .pdf.'); return; }
 const modo = $('#orden-nombre-lista').val();
 const RX_CURP = /^[A-Z]{4}\d{6}[HMX][A-Z]{5}[A-Z0-9]\d$/i;
 leerFilasDesdeArchivo(file, rows => {
 rows = rows.filter(r => r && r[0] != null && String(r[0]).trim());
 if (rows.length && /curp|matr|nombre|apellido/i.test(String(rows[0][0]))) rows.shift();
 let nuevos = 0, actualizados = 0;
 rows.forEach(r => {
 let curp = '', fila = r, desde = 0;
 const c0 = String(r[0]).trim();
 if (RX_CURP.test(c0)) { curp = c0.toUpperCase(); desde = 1; }   // Excel: CURP en columna A
 else { const m = c0.match(/^([A-Za-z]{4}\d{6}[HMXhmx][A-Za-z]{5}[A-Za-z0-9]\d)\s+(.+)$/); if (m) { curp = m[1].toUpperCase(); fila = [m[2]]; } }   // PDF: "CURP Nombre"
 const nombre = armarNombre(fila, desde, modo);
 if (!nombre) return;
 let a = curp ? db.alumnos.find(x => x.curp === curp) : null;
 if (!a) a = db.alumnos.find(x => x.grado === filtro.grado && x.grupo === filtro.grupo && x.turno === turnoActivo && (!curp ? true : !x.curp) && norm(x.nombre) === norm(nombre));
 if (a) { if (curp) a.curp = curp; a.nombre = nombre; a.grado = filtro.grado; a.grupo = filtro.grupo; a.turno = turnoActivo; actualizados++; }
 else { db.alumnos.push({id:generarId(), curp:curp, nombre:nombre, grado:filtro.grado, grupo:filtro.grupo, turno:turnoActivo, reg:{}}); nuevos++; }
 });
 save(); renderTabla(); if (credSel) selCred(credSel.grado, credSel.grupo);
 const hg = db.horarios[claveGrupo(filtro.grado, filtro.grupo, turnoActivo)], og = hg && hg.orientadorId ? db.orientadores.find(x => x.id === hg.orientadorId) : null;
 const sinCurp = db.alumnos.filter(x => x.grado === filtro.grado && x.grupo === filtro.grupo && x.turno === turnoActivo && !x.curp).length;
 alert('Lista cargada en ' + filtro.grado + '° grado, grupo ' + filtro.grupo + ' (' + turnoActivo + '): ' + nuevos + ' alumno(s) nuevo(s), ' + actualizados + ' actualizado(s).\nOrientador(a) del grupo: ' + (og ? og.nombre : 'sin asignar todavía') + '.' + (sinCurp ? '\nOjo: ' + sinCurp + ' alumno(s) quedaron sin CURP (el archivo no lo traía).' : '\nTodos tienen CURP: su credencial ya está lista en el Portal del Alumno.'));
 $('#archivo-lista').val('');
 }, msg => alert(msg));
}
function guardarHorarioGrupo() {
 if (!permisoPermite()) return;
 if (!filtro) { alert('Primero selecciona el grado y el grupo arriba (Visualizar Grupo).'); return; }
 const e = $('#horario-entrada-limite').val(), s = $('#horario-salida-limite').val(), oid = $('#orientador-grupo-select').val();
 if (!e || !s) { alert('Indica la hora límite de entrada y la hora de cierre.'); return; }
 db.horarios[claveGrupo(filtro.grado, filtro.grupo, turnoActivo)] = {entrada:e, salida:s, orientadorId: oid || ''};
 guardarOrientadoresServidor(); renderTabla(); poblarSelectOrientadorHorario(); renderListaOrientadores();
 const nombreOrientador = oid ? (db.orientadores.find(o => o.id === oid) || {}).nombre : 'Sin asignar';
 $('#resumen-horario-grupo').text('Datos guardados para ' + filtro.grado + '° grado, grupo ' + filtro.grupo + ' (' + turnoActivo + '): entrada límite ' + e + ', cierre ' + s + ', orientador(a): ' + nombreOrientador + '.');
}
function subirHorarios() {
 if (!permisoPermite()) return;
 const file = $('#archivo-horarios')[0].files[0];
 if (!file) { alert('Selecciona un archivo de horarios.'); return; }
 if (!libsOk('XLSX')) return;
 const rd = new FileReader();
 rd.onload = e => {
 const wb = XLSX.read(e.target.result, {type:'array'});
 const rows = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], {header:1}).filter(r => r[0] !== undefined && r[1] !== undefined && r[2] && r[3]);
 if (rows.length && /grado/i.test(rows[0][0])) rows.shift();
 rows.forEach(r => {
 const grado = Number(r[0]), grupo = Number(r[1]), entrada = String(r[2]).trim(), salida = String(r[3]).trim(), nombreOrientador = r[4] ? String(r[4]).trim() : '';
 const turno = r[5] ? (/vesp/i.test(String(r[5])) ? 'Vespertino' : 'Matutino') : turnoActivo; // columna 6 (opcional)
 if (!grado || !grupo) return;
 let orientadorId = '';
 if (nombreOrientador) {
 let o = db.orientadores.find(x => norm(x.nombre) === norm(nombreOrientador));
 if (!o) { o = {id: generarId(), nombre: nombreOrientador}; db.orientadores.push(o); }
 orientadorId = o.id;
 }
 db.horarios[claveGrupo(grado, grupo, turno)] = {entrada:entrada, salida:salida, orientadorId: orientadorId};
 });
 guardarOrientadoresServidor(); renderTabla(); poblarSelectOrientadorHorario(); renderListaOrientadores();
 refrescarHorarioForm();
 alert('Horarios cargados para ' + rows.length + ' grupo(s).');
 $('#archivo-horarios').val('');
 };
 rd.readAsArrayBuffer(file);
}
/* Descargas (Excel / PDF reales) */
function descargar(nombre, filas, formato, titulo) {
 if (!filas.length) { alert('No hay datos para descargar.'); return; }
 if (!libsOk(formato === 'Excel' ? 'XLSX' : 'jspdf')) return;
 if (formato === 'Excel') {
 const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(filas), 'Reporte'); XLSX.writeFile(wb, nombre + '.xlsx');
 } else {
 const d = new window.jspdf.jsPDF({orientation:'landscape'}), k = Object.keys(filas[0]);
 d.setFontSize(13); d.text(titulo, 14, 14);
 d.autoTable({head:[k], body:filas.map(o => k.map(x => o[x])), startY:20, styles:{fontSize:8}, headStyles:{fillColor:[128,0,32]}});
 d.save(nombre + '.pdf');
 }
}
function descargarReporte(formato) {
 if (!permisoPermite()) return;
 const periodo = $('#sel-periodo-reporte').val() || 'semana';
 const ref = $('#calendario-orientador').val() || hoy();
 const dias = diasDePeriodo(periodo, ref);
 const tp = tituloPeriodo(periodo, ref);
 const nombreArchivo = 'asistencia_' + (periodo === 'mes' ? ref.slice(0,7) : dias[0]);
 const filas = [];
 filtrados().forEach(a => dias.forEach(f => { const r = a.reg[f]; if (r && r.entrada) filas.push({Fecha:f, CURP:a.curp, Alumno:a.nombre, Grado:a.grado, Grupo:a.grupo, Turno:a.turno, Entrada:r.entrada, Salida:r.salida || 'Pendiente', Permanencia:permDe(r), Estatus:estatusDe(a, r, f)}); }));
 descargar(nombreArchivo, filas, formato, 'Reporte de asistencia - Turno ' + turnoActivo + ' - ' + tp);
}
/* ============ CONTROL ESCOLAR: ESTADÍSTICAS Y GRÁFICA ============ */
function statsGrados(periodo) {
 periodo = periodo || 'hoy';
 const dias = diasDePeriodo(periodo);
 return [1,2,3].map(g => {
 const l = db.alumnos.filter(a => a.grado === g);
 let as = 0, re = 0, pe = 0;
 l.forEach(a => dias.forEach(f => { const e = estatusDe(a, a.reg[f], f); if (e === 'Asistencia') as++; else if (e === 'Retardo') re++; else if (e === 'Permanencia') pe++; }));
 const base = l.length * dias.length;
 const p = n => base ? Math.round(n*100/base) : 0;
 return {Grado:g + '° grado', gradoNum:g, Alumnos:l.length, Asistencias:as, '% Asistencias':p(as), Retardos:re, '% Retardos':p(re), Permanencias:pe};
 });
}
/* Comparativa por grupo de un grado. Incluye los grupos de ambos turnos
 (los del vespertino solo aparecen si ya tienen alumnos). */
function statsPorGrupo(grado, periodo) {
 periodo = periodo || 'hoy';
 const dias = diasDePeriodo(periodo), res = [];
 ['Matutino','Vespertino'].forEach(turno => [1,2,3,4].forEach(grupo => {
 const l = db.alumnos.filter(a => a.grado === grado && a.grupo === grupo && a.turno === turno);
 if (!l.length && turno === 'Vespertino') return;
 let as = 0, re = 0, pe = 0;
 l.forEach(a => dias.forEach(f => { const e = estatusDe(a, a.reg[f], f); if (e === 'Asistencia') as++; else if (e === 'Retardo') re++; else if (e === 'Permanencia') pe++; }));
 const h = db.horarios[claveGrupo(grado, grupo, turno)] || {};
 const orientadorNombre = h.orientadorId ? ((db.orientadores.find(o => o.id === h.orientadorId) || {}).nombre || 'Sin asignar') : 'Sin asignar';
 res.push({turno:turno, grupo:grupo, asistencias:as, retardos:re, permanencias:pe, alumnos:l.length, orientador: orientadorNombre});
 }));
 return res;
}
function renderStats() {
 const periodo = $('#sel-periodo-stats').val() || 'hoy';
 $('#subtitulo-periodo-stats').text('Estadísticas de asistencia - ' + tituloPeriodo(periodo));
 const s = statsGrados(periodo), sum = k => s.reduce((t, x) => t + x[k], 0), tot = sum('Alumnos');
 $('#st-total').text(tot);
 $('#st-asis').text(sum('Asistencias') + ' (' + (tot ? Math.round(sum('Asistencias')*100/tot) : 0) + '%)');
 $('#st-ret').text((sum('Retardos') + sum('Permanencias')) + ' (' + (tot ? Math.round((sum('Retardos') + sum('Permanencias'))*100/tot) : 0) + '%)');
 $('#stats-grados').html(s.map(x => `<div class="col-md-4"><button id="btn-grado-${x.gradoNum}" onclick="seleccionarGrado(${x.gradoNum})" class="btn tarjeta-grado p-3 border rounded-3 bg-light w-100 text-center" title="Selecciona este grado y usa Excel o PDF (arriba) para descargar su comparativa">
 <span class="fw-bold small d-block text-secondary">${x.Grado}</span>
 <span class="text-success fw-bold d-block">Asistencias: ${x['% Asistencias']}% (${x.Asistencias} de ${x.Alumnos})</span>
 <span class="text-warning fw-bold d-block">Retardos: ${x['% Retardos']}% (${x.Retardos})</span>
 <span class="text-danger fw-bold d-block">Permanencia: ${x.Permanencias} alumnos</span>
 </button></div>`).join(''));
 actualizarBotonesGrado();
 dibujarGraficaAsistencias(s);
}
function seleccionarGrado(grado) { gradoSeleccionado = (gradoSeleccionado === grado) ? null : grado; actualizarBotonesGrado(); }
function actualizarBotonesGrado() {
 [1,2,3].forEach(g => {
 const sel = gradoSeleccionado === g;
 const desactivado = gradoSeleccionado !== null && !sel;
 $('#btn-grado-' + g).toggleClass('seleccionada', sel).toggleClass('grado-desactivado', desactivado);
 });
 $('#lbl-descarga-seleccion').text(gradoSeleccionado ? ('Comparativa de ' + nomGrado[gradoSeleccionado] + ' grado (por grupo y turno, con orientador)') : 'Estadísticas generales (todos los grados)');
}
function dibujarGraficaAsistencias(s) {
 const ctx = document.getElementById('grafica-asistencias');
 if (!ctx || typeof Chart === 'undefined') return;
 const data = { labels: s.map(x => x.Grado), datasets: [
 {label:'Asistencias', data: s.map(x => x.Asistencias), backgroundColor:'#198754'},
 {label:'Retardos', data: s.map(x => x.Retardos), backgroundColor:'#ffc107'},
 {label:'Permanencias', data: s.map(x => x.Permanencias), backgroundColor:'#dc3545'}
 ]};
 const titulo = 'Estadísticas de asistencia - ' + tituloPeriodo($('#sel-periodo-stats').val() || 'hoy');
 if (!libsOk('Chart')) return;
 if (chartAsistencias) { chartAsistencias.data = data; chartAsistencias.options.plugins.title.text = titulo; chartAsistencias.update(); return; }
 chartAsistencias = new Chart(ctx, { type: 'bar', data: data, options: {
 responsive:true, maintainAspectRatio:true,
 plugins:{ legend:{position:'bottom'}, title:{display:true, text:titulo, font:{size:13, weight:'bold'}, color:'#800020', padding:{bottom:10}} },
 scales:{y:{beginAtZero:true, ticks:{precision:0}}}
 }});
}
function descargarGraficas(formato) {
 if (!permisoPermite()) return;
 if (gradoSeleccionado) { if (formato === 'PDF') descargarComparativaGradoPDF(gradoSeleccionado); else descargarComparativaGradoExcel(gradoSeleccionado); return; }
 const periodo = $('#sel-periodo-stats').val() || 'hoy';
 const s = statsGrados(periodo);
 const tp = tituloPeriodo(periodo);
 if (formato === 'Excel') { descargar('estadisticas_' + hoy(), s.map(x => ({Grado:x.Grado, Alumnos:x.Alumnos, Asistencias:x.Asistencias, '% Asistencias':x['% Asistencias'], Retardos:x.Retardos, '% Retardos':x['% Retardos'], Permanencias:x.Permanencias})), 'Excel', 'Estadísticas de asistencia - ' + tp); return; }
 if (!libsOk('jspdf')) return;
 const d = new window.jspdf.jsPDF();
 d.setFontSize(14); d.text('Estadísticas de asistencia - ' + tp, 14, 15);
 let startY = 22;
 const canvas = document.getElementById('grafica-asistencias');
 if (canvas && chartAsistencias) {
 const img = canvas.toDataURL('image/png', 1.0);
 const ratio = canvas.height / canvas.width, imgW = 180, imgH = Math.round(imgW * ratio);
 d.addImage(img, 'PNG', 14, 22, imgW, imgH);
 startY = 22 + imgH + 8;
 }
 const k = ['Grado','Alumnos','Asistencias','% Asistencias','Retardos','% Retardos','Permanencias'];
 d.autoTable({head:[k], body: s.map(o => k.map(x => o[x])), startY: startY, styles:{fontSize:8}, headStyles:{fillColor:[128,0,32]}});
 d.save('estadisticas_' + hoy() + '.pdf');
}
function descargarComparativaGradoPDF(grado) {
 if (!libsOk('Chart')) return;
 const periodo = $('#sel-periodo-stats').val() || 'hoy';
 const datos = statsPorGrupo(grado, periodo);
 const tp = tituloPeriodo(periodo);
 if (!libsOk('jspdf')) return;
 const d = new window.jspdf.jsPDF();
 d.setFontSize(14); d.text('Comparativa de asistencia - ' + nomGrado[grado] + ' grado - ' + tp, 14, 15);
 let y = 24, contadorEnPagina = 0;
 const cont = document.getElementById('canvas-temporal');
 datos.forEach((g) => {
 if (contadorEnPagina === 2) { d.addPage(); y = 15; contadorEnPagina = 0; }
 const canvas = document.createElement('canvas'); canvas.width = 700; canvas.height = 380; cont.appendChild(canvas);
 const tempChart = new Chart(canvas.getContext('2d'), { type:'bar',
 data:{ labels:['Asistencias','Retardos','Permanencias'], datasets:[{ data:[g.asistencias,g.retardos,g.permanencias], backgroundColor:['#198754','#ffc107','#dc3545'] }] },
 options:{ responsive:false, animation:false, devicePixelRatio:2,
 plugins:{ legend:{display:false}, title:{display:true, text:nomGrado[grado]+' '+g.grupo+' ('+g.turno+') — Orientador(a): '+g.orientador, font:{size:15, weight:'bold'}, color:'#800020'} },
 scales:{ y:{beginAtZero:true, ticks:{precision:0}} } }});
 tempChart.update();
 const img = canvas.toDataURL('image/png', 1.0);
 const imgW = 180, imgH = Math.round(imgW * (canvas.height/canvas.width));
 d.addImage(img, 'PNG', 14, y, imgW, imgH);
 y += imgH + 8; contadorEnPagina++;
 tempChart.destroy(); cont.removeChild(canvas);
 });
 d.save('comparativa_' + nomGrado[grado] + '_grado_' + hoy() + '.pdf');
}
function descargarComparativaGradoExcel(grado) {
 const periodo = $('#sel-periodo-stats').val() || 'hoy';
 const tp = tituloPeriodo(periodo);
 const datos = statsPorGrupo(grado, periodo);
 const filas = datos.map(g => ({Grupo: nomGrado[grado] + ' ' + g.grupo, Turno: g.turno, 'Orientador(a)': g.orientador, Alumnos: g.alumnos, Asistencias: g.asistencias, Retardos: g.retardos, Permanencias: g.permanencias}));
 descargar('comparativa_' + nomGrado[grado] + '_grado_' + hoy(), filas, 'Excel', 'Comparativa - ' + nomGrado[grado] + ' grado - ' + tp);
}
/* ============ PLANTILLAS OFICIALES EN WORD (frente y reverso, por turno) ============
 Las plantillas son archivos de Word (.docx) o PDF. El sistema los lee (public/vendor/sica-docx.js), los acomoda
 y escribe encima nombre, CURP, grado, grupo, turno y el QR de cada alumno, todo con la misma letra y tamaño.
 Cada plantilla se busca en este orden:
   1) la que Control Escolar subió desde el bloque "Plantilla Oficial de Credenciales" (queda en la base de datos)
   2) el archivo original de public/plantillas (FrenteMatutino.docx, FrenteVespertino.docx, Reverso.docx) */
const ARCHIVOS_PLANTILLA = {
 Matutino:   {frente: 'FrenteMatutino.docx',   reverso: 'Reverso.docx'},
 Vespertino: {frente: 'FrenteVespertino.docx', reverso: 'Reverso.docx'}
};
const BASE_PLANTILLAS = "{{ asset('plantillas') }}/";
let plantillasPedidas = false;
const errorPlantilla = {};   // 'Matutino-frente' -> texto del problema, si lo hay
const origenPlantilla = {};  // 'Matutino-frente' -> 'servidor' (la que subió Control Escolar) | 'original' (el Word de public/plantillas)
const existeOriginal = {};   // 'FrenteMatutino.docx' -> true/false (false = ya se eliminó para siempre)
try { localStorage.removeItem('sica_epo6_plantilla_oficial'); } catch (e) {}   // ya no se guardan imágenes de plantilla en el navegador
function cargarPlantillasDefault() {
 revisarOriginales();
 const hecho = (turno, lado, modelo, error, origen) => {
  plantillasDefault[turno][lado] = modelo;
  if (modelo && origen) origenPlantilla[turno + '-' + lado] = origen; else delete origenPlantilla[turno + '-' + lado];
  if (error) errorPlantilla[turno + '-' + lado] = error; else delete errorPlantilla[turno + '-' + lado];
  refrescarPreviewPlantillaOficial();
  if (idEditando) actualizarVistaPreviaCredencial();
 };
 (window.SICA_LIBS_LISTAS || Promise.resolve()).then(() => {
  if (!window.SicaDocx) { Object.keys(plantillasDefault).forEach(t => ['frente','reverso'].forEach(l => hecho(t, l, null, 'No se pudo cargar el lector de plantillas. Revisa tu internet y recarga la página.'))); return; }
  Object.keys(ARCHIVOS_PLANTILLA).forEach(turno => ['frente','reverso'].forEach(lado => {
   const intentos = ['/plantilla-oficial/' + turno + '/' + lado, BASE_PLANTILLAS + ARCHIVOS_PLANTILLA[turno][lado]];
   const probar = (i, ultimoError) => {
    if (i >= intentos.length) return hecho(turno, lado, null, ultimoError || ('Todavía no hay plantilla para este turno. Elige tu archivo (Word o PDF) y pulsa "Actualizar".'));
    fetch(intentos[i] + '?v=' + Date.now(), {credentials: 'same-origin'})
     .then(r => r.ok ? r.arrayBuffer() : Promise.reject('no-esta'))
     .then(buf => SicaDocx.cargar(buf))
     .then(m => hecho(turno, lado, m, null, i === 0 ? 'servidor' : 'original'))
     .catch(e => probar(i + 1, e === 'no-esta' || !e ? ultimoError : 'No se pudo leer el archivo de la plantilla: ' + (e.message || e)));
   };
   probar(0, '');
  }));
 });
}
/* Devuelve la plantilla (ya leída) del turno y lado indicados, o null si aún no carga. */
function plantillaDe(turno, lado) {
 const t = plantillasDefault[turno || 'Matutino'];
 return (t && t[lado]) || null;
}
/* Falta algo en la plantilla del frente? (cuadro QR o etiquetas) -> lista de textos */
function problemasFrente(m) {
 const faltan = [];
 if (!m.qr) faltan.push('el cuadro con el texto "QR"');
 ['nombre','curp','grado','grupo','turno'].forEach(k => { if (!m.campos[k]) faltan.push('la etiqueta ' + (k === 'nombre' ? 'NOMBRE DEL ESTUDIANTE:' : k.toUpperCase() + ':')); });
 return faltan;
}
function refrescarPreviewPlantillaOficial() {
 const t = turnoCred || 'Matutino';
 $('#turno-plantilla-actual').text(t);
 ['frente','reverso'].forEach(l => {
  const cv = document.getElementById('prev-plantilla-oficial-' + l);
  if (!cv) return;
  const m = plantillasDefault[t] && plantillasDefault[t][l];
  const msg = $('#msg-prev-plantilla-' + l);
  if (m && window.SicaDocx) {
   SicaDocx.dibujar(cv, m, null, {ancho: 900});
   $(cv).show();
   const falta = l === 'frente' ? problemasFrente(m) : [];
   msg.toggleClass('text-danger', falta.length > 0).toggleClass('text-muted', !falta.length)
    .text(falta.length ? 'A esta plantilla le falta: ' + falta.join(', ') + '.' : '');
  } else {
   $(cv).hide();
   msg.removeClass('text-danger').addClass('text-muted').text(errorPlantilla[t + '-' + l] || 'Cargando plantilla…');
  }
 });
 igualarAlturaPreviews();
 actualizarBotonesPlantilla();
}
/* Frente y reverso se ven con EXACTAMENTE la misma altura (el reverso es un poco más ancho que el frente,
   por eso antes se veía más bajito). La altura se limita al ancho de la columna para que nunca se corte. */
function igualarAlturaPreviews() {
 const cvs = ['frente', 'reverso'].map(l => document.getElementById('prev-plantilla-oficial-' + l)).filter(c => c && c.style.display !== 'none' && c.width && c.height);
 if (!cvs.length) return;
 const maxAsp = Math.max.apply(null, cvs.map(c => c.width / c.height));
 const colW = Math.min.apply(null, cvs.map(c => c.parentElement.clientWidth));   // 0 si el panel aún está oculto
 const h = Math.floor(Math.min(250, colW > 0 ? (colW - 2) / maxAsp : 250));
 cvs.forEach(c => { c.style.height = h + 'px'; c.style.width = Math.round(h * c.width / c.height) + 'px'; });
}
window.addEventListener('resize', igualarAlturaPreviews);
/* ¿Siguen existiendo los Word originales de public/plantillas? (se pueden eliminar para siempre) */
function revisarOriginales() {
 return Promise.all(['FrenteMatutino.docx', 'FrenteVespertino.docx', 'Reverso.docx'].map(f =>
  fetch(BASE_PLANTILLAS + f + '?v=' + Date.now(), {method: 'HEAD', credentials: 'same-origin'})
   .then(r => { existeOriginal[f] = r.ok; })
   .catch(() => {})                         // sin internet: no se sabe, se deja como estaba
 )).then(actualizarBotonesPlantilla);
}
function actualizarBotonesPlantilla() {
 const t = turnoCred || 'Matutino', lados = ['frente', 'reverso'];
 const hayOriginal = lados.some(l => existeOriginal[ARCHIVOS_PLANTILLA[t][l]] !== false);
 const hayAnterior = lados.some(l => origenPlantilla[t + '-' + l] === 'servidor' && existeOriginal[ARCHIVOS_PLANTILLA[t][l]] !== false);
 $('#btn-restaurar-plantilla').prop('disabled', !hayOriginal)
  .attr('title', hayOriginal ? '' : 'La plantilla original ya fue eliminada, no se puede restaurar.');
 $('#btn-eliminar-anterior').prop('disabled', !hayAnterior)
  .attr('title', hayAnterior ? 'Borra para siempre el archivo anterior de este turno.' : 'Disponible cuando ya subiste una plantilla nueva y la anterior todavía existe.');
}
/* ============ PLANTILLA OFICIAL EDITABLE (solo Control Escolar) ============
 Sube un Word (.docx) o un PDF nuevo para el turno elegido: se lee aquí mismo (vista previa y PDF al instante)
 y se guarda en el servidor (ControlEscolarController@plantillaOficial) para todos los equipos. */
function subirPlantillaOficial(lado) {
 if (rolActivo !== 'control') { alert('Solo el Encargado de Control Escolar puede cambiar la plantilla oficial.'); return; }
 const turno = turnoCred || 'Matutino';
 const f = $('#input-plantilla-oficial-' + lado)[0].files[0];
 if (!f) { alert('Primero elige tu archivo (Word o PDF) y luego pulsa "Actualizar".'); return; }
 const esPDF = /\.pdf$/i.test(f.name), esDocx = /\.docx$/i.test(f.name);
 if (!esPDF && !esDocx) { alert('Ese archivo no sirve. Usa un Word guardado como .docx (Archivo > Guardar como > Documento de Word) o un PDF (Archivo > Exportar > PDF).'); return; }
 if (!libsOk('SicaDocx')) return;
 if (esPDF && !libsOk('pdfjsLib')) return;
 const caja = $('#resumen-plantilla-oficial');
 caja.removeClass('text-success text-danger').addClass('text-muted').text('Leyendo tu ' + (esPDF ? 'PDF' : 'Word') + '…');
 f.arrayBuffer().then(buf => SicaDocx.cargar(buf)).then(m => {
  if (lado === 'frente') {
   const falta = problemasFrente(m);
   if (falta.length && !confirm('En este archivo no encontré ' + falta.join(', ') + '. Sin eso, los datos del alumno no quedarían bien en la credencial.\n\n¿Subirlo de todos modos?')) { caja.text(''); return; }
  }
  const turnos = (lado === 'reverso' && $('#plantilla-ambos-turnos').is(':checked')) ? ['Matutino', 'Vespertino'] : [turno];
  turnos.forEach(t => { plantillasDefault[t][lado] = m; delete errorPlantilla[t + '-' + lado]; });   // se usa de inmediato
  refrescarPreviewPlantillaOficial();
  if (idEditando) actualizarVistaPreviaCredencial();
  caja.removeClass('text-success text-danger').addClass('text-muted').text('Guardando en el servidor…');
  return Promise.all(turnos.map(t => {
   const fd = new FormData();
   fd.append('archivo', f, f.name);
   fd.append('turno', t);
   return fetch('/api/sica/plantilla-oficial/' + lado, {
    method: 'POST',
    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''},
    body: fd
   }).then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(d => Promise.reject((d && (d.message || d.mensaje)) || ('error ' + r.status))));
  })).then(() => {
   turnos.forEach(t => { origenPlantilla[t + '-' + lado] = 'servidor'; });
   actualizarBotonesPlantilla();
   caja.removeClass('text-muted text-danger').addClass('text-success')
    .text('¡Listo! La plantilla ' + turnos.join(' y ') + ' (' + lado + ') ya se actualizó. Las credenciales de ' + (turnos.length > 1 ? 'los dos turnos' : 'ese turno') + ' se hacen con este diseño.');
  }, e => {
   caja.removeClass('text-muted text-success').addClass('text-danger')
    .text('La plantilla se cambió solo en este navegador; el servidor no la guardó (' + e + '). Intenta de nuevo.');
  });
 }).catch(e => {
  caja.removeClass('text-muted text-success').addClass('text-danger').text('No se pudo leer ese archivo: ' + (e && e.message ? e.message : e));
 }).then(() => $('#input-plantilla-oficial-' + lado).val(''));
}
function restaurarPlantillaOficial() {
 if (rolActivo !== 'control') return;
 const t = turnoCred || 'Matutino';
 // Solo se restaura lo que todavía tiene su Word original (si ya se eliminó para siempre, no hay a qué volver)
 const lados = ['frente', 'reverso'].filter(l => existeOriginal[ARCHIVOS_PLANTILLA[t][l]] !== false);
 if (!lados.length) { alert('La plantilla original del turno ' + t + ' ya fue eliminada, por eso no se puede restaurar.'); return; }
 if (!confirm('¿Volver a la plantilla original del turno ' + t + '?')) return;
 const cab = {'Accept':'application/json','X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''};
 Promise.all(lados.map(l => fetch('/api/sica/plantilla-oficial/' + t + '/' + l, {method:'DELETE', headers: cab}).catch(() => {})))
  .then(() => { plantillasDefault[t] = {frente: null, reverso: null}; cargarPlantillasDefault(); });
 $('#resumen-plantilla-oficial').removeClass('text-danger').addClass('text-success').text('Se restauró la plantilla original del turno ' + t + ' en el servidor.');
}
/* Elimina PARA SIEMPRE la plantilla anterior (el Word original del servidor) del turno elegido.
   Solo se puede cuando ya hay una plantilla nueva en uso; la nueva no se toca. */
function eliminarPlantillaAnterior() {
 if (rolActivo !== 'control') { alert('Solo el Encargado de Control Escolar puede eliminar la plantilla anterior.'); return; }
 const t = turnoCred || 'Matutino', caja = $('#resumen-plantilla-oficial');
 const lados = ['frente', 'reverso'].filter(l => origenPlantilla[t + '-' + l] === 'servidor' && existeOriginal[ARCHIVOS_PLANTILLA[t][l]] !== false);
 if (!lados.length) {
  const hayNueva = ['frente', 'reverso'].some(l => origenPlantilla[t + '-' + l] === 'servidor');
  alert(hayNueva ? 'La plantilla anterior del turno ' + t + ' ya fue eliminada.'
                 : 'Todavía no hay una plantilla nueva en el turno ' + t + '. Primero elige tu archivo nuevo (Word o PDF) y pulsa "Actualizar"; después podrás eliminar la anterior.');
  return;
 }
 const archivos = lados.map(l => ARCHIVOS_PLANTILLA[t][l]).filter((v, i, a) => a.indexOf(v) === i).join(', ');
 if (!confirm('¿Eliminar PERMANENTEMENTE la plantilla anterior del turno ' + t + ' (' + archivos + ')?\n\nLa plantilla nueva que subiste sigue en uso, pero ya no se podrá "Restaurar plantilla original". Esta acción no se puede deshacer.')) return;
 const cab = {'Accept':'application/json','X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''};
 caja.removeClass('text-success text-danger').addClass('text-muted').text('Eliminando la plantilla anterior…');
 Promise.all(lados.map(l => fetch('/api/sica/plantilla-oficial/' + t + '/' + l + '/anterior', {method: 'DELETE', headers: cab})
  .then(r => r.json().catch(() => ({})).then(d => ({ok: r.ok && d.ok === true, mensaje: d.mensaje || d.message || ('error ' + r.status)})))
  .catch(() => ({ok: false, mensaje: 'No hubo conexión con el servidor.'}))
 )).then(res => {
  const mal = res.filter(r => !r.ok);
  revisarOriginales();
  if (mal.length) caja.removeClass('text-muted text-success').addClass('text-danger').text(mal.map(r => r.mensaje).filter((v, i, a) => a.indexOf(v) === i).join(' '));
  else caja.removeClass('text-muted text-danger').addClass('text-success').text('Listo: la plantilla anterior del turno ' + t + ' se eliminó para siempre. Sigue en uso la plantilla nueva.');
 });
}
/* ============ PORTAL DEL ALUMNO / CREDENCIALES ============ */
const qrImg = curp => (typeof QRious === 'undefined') ? '' : new QRious({value:curp, size:220}).toDataURL();
const alumnosCred = () => db.alumnos.filter(a => a.grado === credSel.grado && a.grupo === credSel.grupo && a.turno === turnoCred);
/* CAMBIO: al elegir Matutino/Vespertino abajo, la etiqueta del turno y la
 vista previa (frente y reverso) de arriba cambian al mismo turno. */
function setTurnoCred(t) {
 turnoCred = t;
 $('#btn-turno-cred-matutino').toggleClass('btn-vinotinto', t === 'Matutino').toggleClass('btn-outline-dark bg-white', t !== 'Matutino');
 $('#btn-turno-cred-vespertino').toggleClass('btn-vinotinto', t === 'Vespertino').toggleClass('btn-outline-dark bg-white', t !== 'Vespertino');
 refrescarPreviewPlantillaOficial();    // cambia la vista previa de frente y reverso
 if (credSel) selCred(credSel.grado, credSel.grupo);
}
function selCred(grado, grupo) {
 credSel = {grado:grado, grupo:grupo};
 $('#titulo-grupo-credencial').text('Credenciales Digitales - ' + nomGrado[grado] + ' ' + grupo + ' (' + turnoCred + ')');
 $('#badge-plantilla').text('Plantilla ' + turnoCred);
 $('#vista-editar-credencial-individual').addClass('d-none'); $('#resultado-credencial-box').removeClass('d-none');
 $('#tabla-alumnos-credencial').html(alumnosCred().map(a => `<tr><td>${a.curp ? '<code>'+esc(a.curp)+'</code>' : '<span class="badge bg-secondary">Sin CURP</span>'}</td><td>${esc(a.nombre)}</td><td class="text-center"><button data-c="${esc(a.id)}" onclick="verCredencial(this.dataset.c)" class="btn btn-vinotinto btn-sm py-1 px-3">Ver Credencial</button> <button data-c="${esc(a.id)}" onclick="eliminarAlumno(this.dataset.c)" class="btn btn-outline-danger btn-sm py-1 px-3" title="Eliminar alumno">Eliminar</button></td></tr>`).join('') || '<tr><td colspan="3" class="text-center text-muted py-3">Este grupo aún no tiene alumnos en este turno. Control Escolar debe subir la lista.</td></tr>');
}
function verCredencial(id) {
 const a = db.alumnos.find(x => x.id === id); idEditando = id;
 $('#edit-curp-alumno').val(a.curp); $('#edit-nombre-alumno').val(a.nombre);
 $('#resultado-credencial-box').addClass('d-none'); $('#vista-editar-credencial-individual').removeClass('d-none');
 actualizarVistaPreviaCredencial();
}
function regresarListaGrupo() { selCred(credSel.grado, credSel.grupo); }
/* Elimina un alumno (y sus asistencias) del servidor y de este navegador. Solo Control Escolar. */
function eliminarAlumno(id) {
 if (rolActivo !== 'control') { alert('Solo el Encargado de Control Escolar puede eliminar alumnos.'); return; }
 const a = db.alumnos.find(x => x.id === id); if (!a) return;
 if (!confirm('¿Eliminar a ' + a.nombre + (a.curp ? ' (' + a.curp + ')' : '') + '? Se borrarán también sus registros de asistencia. Esta acción no se puede deshacer.')) return;
 const quitarLocal = () => {
 db.alumnos = db.alumnos.filter(x => x.id !== id);
 localStorage.setItem(LS, JSON.stringify(db));
 selCred(credSel.grado, credSel.grupo); renderTabla();
 };
 fetch('/api/sica/sync', {
 method: 'POST',
 headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || ''},
 body: JSON.stringify({accion:'eliminar_alumno', datos:{curp:a.curp || '', id:a.id}})
 }).then(r => r.ok ? quitarLocal() : Promise.reject())
 .catch(() => alert('No se pudo eliminar en el servidor. Revisa tu conexión e inténtalo de nuevo.'));
}
function guardarCambiosCredencial() {
 if (!permisoPermite()) return;
 const a = db.alumnos.find(x => x.id === idEditando), curp = $('#edit-curp-alumno').val().trim().toUpperCase(), nombre = $('#edit-nombre-alumno').val().trim();
 if (!curp || !nombre) { alert('CURP y nombre son obligatorios.'); return; }
 a.curp = curp; a.nombre = nombre; save();
 actualizarVistaPreviaCredencial();
 alert('Cambios guardados en la credencial.');
}
/* Dibuja la vista previa en vivo (canvas) de frente y reverso mientras se edita al alumno,
 usando siempre los datos que hay en los inputs (aunque no se hayan guardado todavía). */
function actualizarVistaPreviaCredencial() {
 const a = db.alumnos.find(x => x.id === idEditando);
 if (!a) return;
 const datosPreview = {
 nombre: $('#edit-nombre-alumno').val().trim() || a.nombre,
 curp: $('#edit-curp-alumno').val().trim().toUpperCase() || a.curp,
 grado: a.grado, grupo: a.grupo, turno: a.turno
 };
 pintarCredencialCanvas(document.getElementById('preview-canvas-frente'), datosPreview, 'frente');
 pintarCredencialCanvas(document.getElementById('preview-canvas-reverso'), datosPreview, 'reverso');
}
/* Datos que se escriben sobre la plantilla (Word o PDF). El nombre va como se guardó:
   Apellido paterno, Apellido materno y Nombre(s). */
function datosCredencial(a) {
 return {
  nombre: a.nombre || '(SIN NOMBRE)', curp: a.curp || '(SIN CURP)', conQR: !!a.curp,
  grado: nomGrado[a.grado] || '',
  grupo: ROMANOS[a.grupo] || String(a.grupo || ''), turno: a.turno || ''
 };
}
/* Dibuja una credencial (frente o reverso) en un <canvas> sobre la plantilla (Word o PDF) del turno del alumno.
   Es la misma función de la vista previa y del PDF, así lo que se ve es lo que se imprime. */
function pintarCredencialCanvas(canvas, a, lado, onListo, ancho) {
 if (!canvas) return;
 const m = plantillaDe(a.turno, lado);
 if (!m || !window.SicaDocx) {
  canvas.width = 380; canvas.height = 240;
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#f4f6f9'; ctx.fillRect(0, 0, 380, 240);
  ctx.fillStyle = '#666'; ctx.font = '14px Segoe UI, Arial'; ctx.textAlign = 'center';
  ctx.fillText(errorPlantilla[(a.turno || 'Matutino') + '-' + lado] || 'Cargando plantilla…', 190, 124);
  ctx.textAlign = 'start';
  if (onListo) onListo(null);
  return;
 }
 SicaDocx.dibujar(canvas, m, datosCredencial(a), {ancho: ancho || 760});
 if (onListo) onListo(m);
}
/* ---------- PDF: Carta u Oficio, credenciales a tamaño real ---------- */
const HOJAS = {carta: {w: 215.9, h: 279.4}, oficio: {w: 215.9, h: 330.2}};   // mm
const PT_A_MM = 25.4 / 72;
try { const v = localStorage.getItem('sica_hoja_pdf'); if (HOJAS[v]) $(function () { $('.sel-hoja-pdf').val(v); }); } catch (e) {}
function cambiarHoja(v) {
 $('.sel-hoja-pdf').val(v);
 try { localStorage.setItem('sica_hoja_pdf', v); } catch (e) {}
}
const hojaElegida = () => HOJAS[$('.sel-hoja-pdf').first().val()] || HOJAS.carta;
/* Imágenes (frente y reverso) de un alumno para el PDF, con su tamaño real en mm */
function imagenesCredencial(a) {
 const mF = plantillaDe(a.turno, 'frente'), mR = plantillaDe(a.turno, 'reverso');
 if (!mF || !mR) return null;
 const cF = document.createElement('canvas'), cR = document.createElement('canvas');
 pintarCredencialCanvas(cF, a, 'frente', null, 1100);
 pintarCredencialCanvas(cR, a, 'reverso', null, 1100);
 return {
  f: {img: cF.toDataURL('image/jpeg', 0.92), w: mF.ancho * PT_A_MM, h: mF.alto * PT_A_MM},
  r: {img: cR.toDataURL('image/jpeg', 0.92), w: mR.ancho * PT_A_MM, h: mR.alto * PT_A_MM}
 };
}
/* Cómo acomodar las credenciales en la hoja para que quepan las que más se pueda:
   frente arriba y reverso abajo, o frente y reverso lado a lado (se elige lo que más aproveche la hoja). */
function planHoja(hoja, f, r) {
 const margen = 8, sep = 5, junto = 3;
 const W = hoja.w - 2 * margen, H = hoja.h - 2 * margen;
 const opciones = [
  {modo: 'apilado', bw: Math.max(f.w, r.w), bh: f.h + junto + r.h},
  {modo: 'lado', bw: f.w + junto + r.w, bh: Math.max(f.h, r.h)}
 ].map(o => {
  o.cols = Math.max(0, Math.floor((W + sep) / (o.bw + sep)));
  o.rows = Math.max(0, Math.floor((H + sep) / (o.bh + sep)));
  o.n = o.cols * o.rows;
  return o;
 });
 const mejor = opciones[1].n > opciones[0].n ? opciones[1] : opciones[0];
 if (mejor.n < 1) { mejor.cols = 1; mejor.rows = 1; mejor.n = 1; }
 return Object.assign({margen: margen, sep: sep, junto: junto}, mejor);
}
function generarPDFCredenciales(alumnos, nombreArchivo, boton, textoBoton, onFin) {
 if (!libsOk('jspdf') || !libsOk('SicaDocx')) return;
 const t0 = alumnos[0].turno || 'Matutino';
 if (!plantillaDe(t0, 'frente') || !plantillaDe(t0, 'reverso')) {
  alert('La plantilla de este turno todavía no está lista. ' + (errorPlantilla[t0 + '-frente'] || errorPlantilla[t0 + '-reverso'] || 'Espera unos segundos e inténtalo de nuevo.'));
  return;
 }
 const $b = boton ? $(boton) : null;
 const liberar = () => { if ($b) $b.prop('disabled', false).text(textoBoton); };
 let d, plan, hoja, primera, x0;
 try {
  hoja = hojaElegida();
  primera = imagenesCredencial(alumnos[0]);
  plan = planHoja(hoja, primera.f, primera.r);
  d = new window.jspdf.jsPDF({unit: 'mm', format: [hoja.w, hoja.h], orientation: 'portrait'});
  x0 = Math.max((hoja.w - (plan.cols * plan.bw + (plan.cols - 1) * plan.sep)) / 2, 0);
 } catch (e) {
  alert('No se pudo preparar el PDF: ' + (e && e.message ? e.message : e));
  return;
 }
 if ($b) $b.prop('disabled', true);
 let i = 0;
 const poner = () => {
  const im = i === 0 ? primera : imagenesCredencial(alumnos[i]);
  const pos = i % plan.n;
  if (i > 0 && pos === 0) d.addPage([hoja.w, hoja.h], 'portrait');
  const x = x0 + (pos % plan.cols) * (plan.bw + plan.sep), y = plan.margen + Math.floor(pos / plan.cols) * (plan.bh + plan.sep);
  d.addImage(im.f.img, 'JPEG', x, y, im.f.w, im.f.h);
  if (plan.modo === 'apilado') d.addImage(im.r.img, 'JPEG', x, y + im.f.h + plan.junto, im.r.w, im.r.h);
  else d.addImage(im.r.img, 'JPEG', x + im.f.w + plan.junto, y, im.r.w, im.r.h);
  i++;
 };
 const terminar = () => { d.save(nombreArchivo); liberar(); if (onFin) onFin(); };
 const falla = e => { liberar(); alert('No se pudo generar el PDF: ' + (e && e.message ? e.message : e)); };
 try {
  if (alumnos.length <= 15) {            // pocos alumnos: se descarga de inmediato, sin esperas
   while (i < alumnos.length) poner();
   terminar();
   return;
  }
  const paso = () => {
   try {
    if (i >= alumnos.length) return terminar();
    poner();
    if ($b) $b.text('Generando PDF… ' + i + ' de ' + alumnos.length);
    setTimeout(paso, 0);                 // deja respirar a la pantalla entre alumno y alumno
   } catch (e) { falla(e); }
  };
  paso();
 } catch (e) { falla(e); }
}
function descargarCredencialesPDF() {
 if (!permisoPermite()) return;
 const l = alumnosCred().filter(a => a.curp);
 const sinCurp = alumnosCred().length - l.length;
 if (!l.length) { alert('No hay alumnos con CURP asignado en este grupo. Sube primero la lista con CURP y nombre completo.'); return; }
 generarPDFCredenciales(l, 'credenciales_' + nomGrado[credSel.grado] + '_' + credSel.grupo + '_' + turnoCred + '.pdf',
  document.getElementById('btn-pdf-grupo'), 'Descargar Credenciales Completas del Grupo (Frente y Reverso)',
  () => { if (sinCurp > 0) alert('Se descargaron ' + l.length + ' credenciales (frente y reverso). ' + sinCurp + ' alumno(s) no tienen CURP asignado todavía y no se incluyeron.'); });
}
function descargarCredencialIndividual() {
 if (!permisoPermite()) return;
 const a = db.alumnos.find(x => x.id === idEditando);
 if (!a.curp) { alert('Este alumno aún no tiene CURP asignado. Sube su CURP desde "Subir lista de alumnos" antes de descargar su credencial.'); return; }
 const datos = Object.assign({}, a, {nombre: $('#edit-nombre-alumno').val().trim() || a.nombre, curp: $('#edit-curp-alumno').val().trim().toUpperCase() || a.curp});
 generarPDFCredenciales([datos], 'credencial_' + datos.curp + '.pdf', document.getElementById('btn-pdf-individual'), 'Descargar PDF');
}
/* Si al abrir la página ya hay sesión en el servidor, entra directo al sistema (sin volver a pedir contraseña al recargar) */
function restaurarSesion(S) {
 $('#rol-select').val(S.rol);
 if (S.rol === 'orientador') {
 const id = localStorage.getItem('sica_orientador');
 orientadorActivo = db.orientadores.find(o => o.id === id) || null;
 if (!orientadorActivo) { mostrarSeccion('vista-login'); return; }   // sesión abierta pero sin nombre elegido: que lo elija
 }
 cargarDesdeServidor().then(() => {
 if (orientadorActivo) gruposDelOrientador = calcularGruposOrientador(orientadorActivo.id);
 if (!rolActivo) mostrarSeccion('vista-sistema');
 guardarPaginaOffline();
 vaciarCola();   // si quedaron escaneos pendientes de otra vez, se envían ahora
 });
}
// Computadoras: al volver con "atrás" o recargar, el navegador restaura el rol elegido SIN avisar a la página -> se vuelve a sincronizar el correo
window.addEventListener('pageshow', () => { if (!rolActivo) actualizarCredenciales(); });
$(function () {
 if (Array.isArray(window.SICA_ORIENTADORES)) db.orientadores = window.SICA_ORIENTADORES;   // ya vienen en la página
 actualizarCredenciales();
 $('#calendario-orientador').val(hoy());
 if (window.SICA_SESION) restaurarSesion(window.SICA_SESION);   // solo pide datos al servidor si hay sesión (cero errores 401 al abrir)
 // Todos los equipos consultan al servidor (computadoras cada 3 s, teléfonos cada 5 s) para ver los mismos datos;
 // se pausa si la pestaña está oculta para no gastar datos ni servidor
 setInterval(() => { if (rolActivo && !document.hidden) cargarDesdeServidor(); }, ES_MOVIL ? 5000 : 3000);
 // Reintenta enviar los escaneos que quedaron esperando por falta de internet
 setInterval(() => { if (rolActivo && colaEscaneos.length) vaciarCola(); }, 15000);
 pintarIndicadorCola();
 // Service worker: permite abrir el sistema sin internet (solo funciona en https o localhost)
 if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
 document.addEventListener('visibilitychange', () => { if (!document.hidden && rolActivo) cargarDesdeServidor(); });
});
</script>
</body>
</html>