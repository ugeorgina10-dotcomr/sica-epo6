<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lector Fijo - SICA-EPO6</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Accesibilidad: foco bien visible con el teclado y sin animaciones si la persona lo pidió */
        :focus-visible { outline: 4px solid #1d4ed8; outline-offset: 3px; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
    <!-- Librería para lectura de códigos QR desde la cámara -->
    <script src="{{ asset('vendor/html5-qrcode.min.js') }}" type="text/javascript"></script>
</head>
<body class="bg-slate-50 min-h-screen">
    <header class="bg-[#800020] text-white py-3 px-4 sm:px-6 shadow-md flex flex-wrap gap-3 justify-between items-center">
        <div>
            <h2 class="text-sm font-light">C.C.T. 15EBH0008G • C.C.T. 15EBH0199N</h2>
            <h1 class="text-lg font-bold">ESCUELA PREPARATORIA OFICIAL NÚM. 6</h1>
        </div>
        <div>
            <a href="{{ route('login') }}" class="inline-flex items-center min-h-[48px] text-base font-semibold bg-white/10 hover:bg-white/20 py-2 px-5 rounded-lg transition">Regresar al Panel</a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto p-3 sm:p-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 text-center">
            <h3 class="text-base font-black text-gray-800 uppercase tracking-wide">Lector Fijo - Control de Entradas y Salidas</h3>
            <p class="text-sm text-gray-700 mt-1 mb-6">El sistema detecta el grupo del alumno y, según el horario del grupo, registra solo si es Entrada o Salida. Escanea con la cámara frontal.</p>

            <!-- Botones de Modo -->
            <div class="flex flex-wrap justify-center gap-4 mb-6" role="group" aria-label="Modo de registro">
                <button id="btnEntrada" type="button" aria-pressed="false" onclick="setModo('Entrada')" class="px-8 py-3 min-h-[60px] min-w-[150px] bg-gray-100 text-gray-800 text-lg font-bold rounded-xl border border-gray-300 hover:bg-gray-200 transition">Entrada</button>
                <button id="btnSalida" type="button" aria-pressed="false" onclick="setModo('Salida')" class="px-8 py-3 min-h-[60px] min-w-[150px] bg-gray-100 text-gray-800 text-lg font-bold rounded-xl border border-gray-300 hover:bg-gray-200 transition">Salida</button>
            </div>

            <p id="txtModo" role="status" aria-live="polite" class="text-sm font-bold text-green-800 -mt-3 mb-5">Modo AUTOMÁTICO: el sistema detecta el grupo del alumno y registra solo si es entrada o salida según su horario.</p>

            <!-- Contenedor de la Cámara -->
            <div class="max-w-md mx-auto bg-black rounded-2xl overflow-hidden shadow-inner relative mb-4">
                <div id="reader" class="w-full"></div>
            </div>

            <!-- Controles de Cámara -->
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" onclick="iniciarCamara()" class="px-7 py-3 min-h-[60px] bg-[#800020] hover:bg-[#600018] text-white text-lg font-bold rounded-xl shadow transition">Activar Cámara Frontal</button>
                <button type="button" onclick="detenerCamara()" class="px-7 py-3 min-h-[60px] bg-gray-200 hover:bg-gray-300 text-gray-900 text-lg font-bold rounded-xl border border-gray-400 transition">Detener</button>
            </div>

            <!-- Resultado del Escaneo -->
            <div id="resultado" role="status" aria-live="polite" class="mt-6 p-4 bg-slate-100 rounded-xl hidden text-left max-w-md mx-auto break-words">
                <p class="text-sm font-bold text-gray-800">Alumno detectado:</p>
                <p id="infoAlumno" class="text-base text-gray-900 font-semibold mt-1"></p>
            </div>
        </div>
    </main>

    <script>
        let html5QrCode;
        let modoActual = 'Auto';   // Auto = el servidor decide Entrada o Salida por el horario del grupo del alumno
        const ACTIVO = "px-8 py-3 min-h-[60px] min-w-[150px] bg-[#800020] text-white text-lg font-bold rounded-xl border border-[#800020] shadow transition";
        const INACTIVO = "px-8 py-3 min-h-[60px] min-w-[150px] bg-gray-100 text-gray-800 text-lg font-bold rounded-xl border border-gray-300 hover:bg-gray-200 transition";

        function setModo(tipo) {
            modoActual = (modoActual === tipo) ? 'Auto' : tipo;   // pulsar otra vez el mismo botón = volver a Automático
            document.getElementById('btnEntrada').className = modoActual === 'Entrada' ? ACTIVO : INACTIVO;
            document.getElementById('btnSalida').className = modoActual === 'Salida' ? ACTIVO : INACTIVO;
            document.getElementById('btnEntrada').setAttribute('aria-pressed', modoActual === 'Entrada');
            document.getElementById('btnSalida').setAttribute('aria-pressed', modoActual === 'Salida');
            const t = document.getElementById('txtModo');
            if (modoActual === 'Auto') {
                t.className = 'text-sm font-bold text-green-800 -mt-3 mb-5';
                t.innerText = 'Modo AUTOMÁTICO: el sistema detecta el grupo del alumno y registra solo si es entrada o salida según su horario.';
            } else {
                t.className = 'text-sm font-bold text-red-700 -mt-3 mb-5';
                t.innerText = 'Modo MANUAL: todo escaneo se registra como ' + modoActual.toUpperCase() + '. Vuelve a pulsar «' + modoActual + '» para regresar al modo automático.';
            }
        }

        // Texto del QR de la credencial: termina en "SICA<código de 16 caracteres>"
        function datosDeQr(texto) {
            const limpio = String(texto).toUpperCase().replace(/[^A-Z0-9]/g, '');
            const m = limpio.match(/SICA([0-9A-F]{16,20})$/);
            return m ? { qr: m[1].slice(0, 16).toLowerCase() } : { curp: String(texto).trim().toUpperCase() };
        }

        // Duración: menos de una hora en minutos ("4 min"); de una hora en adelante horas y minutos ("1 hr 15 min")
        function duracion(n) {
            n = Math.max(0, Math.round(Number(n) || 0));
            if (n < 60) return n + ' min';
            const h = Math.floor(n / 60), m = n % 60;
            return h + (h === 1 ? ' hr' : ' hrs') + (m ? ' ' + m + ' min' : '');
        }

        let ultimoQr = { t: '', ts: 0 };
        function registrar(decodedText) {
            if (ultimoQr.t === decodedText && Date.now() - ultimoQr.ts < 4000) return;   // lectura repetida
            ultimoQr = { t: decodedText, ts: Date.now() };
            const caja = document.getElementById('resultado'), info = document.getElementById('infoAlumno');
            caja.classList.remove('hidden');
            info.className = 'text-base text-gray-900 font-semibold mt-1';
            info.innerText = 'Buscando alumno…';
            fetch('/api/sica/sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ accion: 'registrar_asistencia', datos: Object.assign(datosDeQr(decodedText), { modo: modoActual }) })
            }).then(r => r.json().catch(() => ({})).then(d => {
                if (r.ok && d.ok) {
                    const al = d.alumno || {};
                    // Con retardo TODO el texto sale en rojo; sin retardo, en verde
                    const conRetardo = !!(d.retardo && d.retardo.minutos > 0);
                    info.className = 'text-base font-semibold mt-1 break-words ' + (conRetardo ? 'text-red-700' : 'text-green-800');
                    info.innerText = (d.modo === 'Salida' ? '¡Salida' : '¡Entrada') + ' registrada correctamente! ' + al.nombre + ' - ' + al.grado + '° ' + al.grupo + ' (' + al.turno + ') · ' + d.hora;
                    // Si ya pasó la tolerancia del grupo: aviso en ROJO, "Retardo de entrada" o "Retardo de salida"
                    if (d.retardo && d.retardo.minutos > 0) {
                        const aviso = document.createElement('span');
                        aviso.className = 'block mt-2 text-lg font-bold text-red-700 bg-red-50 border-l-4 border-red-700 rounded p-2 break-words';
                        aviso.setAttribute('role', 'alert');
                        aviso.textContent = '⚠ Retardo de ' + d.retardo.tipo + ': ' + duracion(d.retardo.minutos) + ' tarde (ya pasada la tolerancia)';
                        info.appendChild(aviso);
                    }
                } else {
                    info.className = 'text-base text-red-700 font-semibold mt-1';
                    info.innerText = (d && d.mensaje) || ('No se pudo registrar (error ' + r.status + ').');
                }
            })).catch(() => { info.innerText = 'Sin conexión con el servidor. Intenta de nuevo.'; });
        }

        function iniciarCamara() {
            html5QrCode = new Html5Qrcode("reader");
            // Configuración para usar siempre la cámara frontal en el lector fijo de entrada
            html5QrCode.start(
                { facingMode: "user" }, 
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    // Se leyó bien el QR del alumno
                    registrar(decodedText);
                },
                (errorMessage) => {
                    // Los errores al escanear mientras la cámara busca el código se pueden ignorar
                }
            ).catch((err) => {
                alert("No se pudo acceder a la cámara frontal: " + err);
            });
        }

        function detenerCamara() {
            if(html5QrCode) {
                html5QrCode.stop().then(() => {
                    console.log("Cámara detenida.");
                }).catch(err => {
                    console.log("Error al detener la cámara.");
                });
            }
        }
    </script>
</body>
</html>