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
            <a href="{{ route('login') }}" class="inline-flex items-center min-h-[68px] text-xl font-semibold bg-white/10 hover:bg-white/20 py-2 px-5 rounded-md transition">Regresar al Panel</a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto p-3 sm:p-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 text-center">
            <h3 class="text-base font-black text-gray-800 uppercase tracking-wide">Lector Fijo - Control de Entradas y Salidas</h3>
            <p class="text-sm text-gray-700 mt-1 mb-6">El sistema detecta el grupo del alumno y, según el horario del grupo, registra solo si es Entrada o Salida. Escanea con la cámara frontal.</p>

            <!-- Botones de Modo -->
            <div class="flex flex-wrap justify-center gap-4 mb-6" role="group" aria-label="Modo de registro">
                <button id="btnEntrada" type="button" aria-pressed="false" onclick="setModo('Entrada')" class="px-8 py-3 min-h-[68px] min-w-[160px] bg-gray-100 text-gray-800 text-xl font-semibold rounded-md border border-gray-300 hover:bg-gray-200 transition">Entrada</button>
                <button id="btnSalida" type="button" aria-pressed="false" onclick="setModo('Salida')" class="px-8 py-3 min-h-[68px] min-w-[160px] bg-gray-100 text-gray-800 text-xl font-semibold rounded-md border border-gray-300 hover:bg-gray-200 transition">Salida</button>
            </div>

            <p id="txtModo" role="status" aria-live="polite" class="text-sm font-bold text-green-800 -mt-3 mb-5">Modo AUTOMÁTICO: el sistema detecta el grupo del alumno y registra solo si es entrada o salida según su horario.</p>

            <!-- Contenedor de la Cámara -->
            <div class="max-w-md mx-auto bg-black rounded-2xl overflow-hidden shadow-inner relative mb-4">
                <div id="reader" class="w-full"></div>
            </div>

            <!-- Controles de Cámara -->
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" onclick="iniciarCamara()" class="px-7 py-3 min-h-[68px] bg-[#800020] hover:bg-[#600018] text-white text-xl font-semibold rounded-md shadow transition">Activar Cámara Frontal</button>
                <button type="button" onclick="detenerCamara()" class="px-7 py-3 min-h-[68px] bg-gray-200 hover:bg-gray-300 text-gray-900 text-xl font-semibold rounded-md border border-gray-400 transition">Detener</button>
            </div>

            <!-- Resultado del Escaneo: una tarjeta por lectura (varias credenciales seguidas, la más reciente arriba) -->
            <div id="resultado" role="status" aria-live="polite" class="mt-6 hidden text-left max-w-md mx-auto">
                <p class="text-sm font-bold text-gray-800 mb-2">Últimos escaneos:</p>
                <div id="listaAlumnos" class="space-y-2"></div>
            </div>
        </div>
    </main>

    <script>
        let html5QrCode;
        let modoActual = 'Auto';   // Auto = el servidor decide Entrada o Salida por el horario del grupo del alumno
        const ACTIVO = "px-8 py-3 min-h-[68px] min-w-[160px] bg-[#800020] text-white text-xl font-semibold rounded-md border border-[#800020] shadow transition";
        const INACTIVO = "px-8 py-3 min-h-[68px] min-w-[160px] bg-gray-100 text-gray-800 text-xl font-semibold rounded-md border border-gray-300 hover:bg-gray-200 transition";

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

        // Cada credencial se vigila por separado: se pueden escanear varias seguidas (A, B, C...) y ninguna se pisa
        const ultimosQr = new Map();
        const MAX_TARJETAS = 6;
        function nuevaTarjeta(texto) {
            document.getElementById('resultado').classList.remove('hidden');
            const lista = document.getElementById('listaAlumnos'), it = document.createElement('div');
            it.className = 'p-3 rounded-md border-l-8 border-gray-400 bg-slate-100 text-base text-gray-800 font-semibold break-words';
            it.textContent = texto;
            lista.insertBefore(it, lista.firstChild);
            while (lista.children.length > MAX_TARJETAS) lista.removeChild(lista.lastChild);
            return it;
        }
        function pintarTarjeta(it, ok, conRetardo, texto) {
            it.textContent = texto;
            it.className = 'p-3 rounded-md border-l-8 text-lg font-semibold break-words ' +
                (!ok ? 'border-red-700 bg-red-50 text-red-700' : conRetardo ? 'border-red-700 bg-red-50 text-red-700' : 'border-green-700 bg-green-50 text-green-800');
        }
        function registrar(decodedText) {
            const t = Date.now(), previo = ultimosQr.get(decodedText);
            if (previo && t - previo < 4000) return;   // lectura repetida de la misma credencial
            ultimosQr.set(decodedText, t);
            if (ultimosQr.size > 200) ultimosQr.forEach((v, k) => { if (t - v > 4000) ultimosQr.delete(k); });
            const it = nuevaTarjeta('Buscando alumno…');
            fetch('/api/sica/sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ accion: 'registrar_asistencia', datos: Object.assign(datosDeQr(decodedText), { modo: modoActual }) })
            }).then(r => r.json().catch(() => ({})).then(d => {
                if (r.ok && d.ok) {
                    const al = d.alumno || {};
                    // El retardo se avisa SOLO al final (cuando ya hay entrada y salida): con la entrada no sale nada;
                    // con la salida salen el retardo de entrada y el de salida (los que haya) en el mismo mensaje.
                    const partes = [];
                    if (d.retardo_entrada && d.retardo_entrada.minutos > 0) partes.push('Retardo de entrada: ' + duracion(d.retardo_entrada.minutos));
                    if (d.retardo && d.retardo.minutos > 0) partes.push('Retardo de salida: ' + duracion(d.retardo.minutos));
                    pintarTarjeta(it, true, partes.length > 0,
                        (d.modo === 'Salida' ? '¡Salida' : '¡Entrada') + ' registrada correctamente! ' + al.nombre + ' - ' + al.grado + '° ' + al.grupo + ' (' + al.turno + ') · ' + d.hora +
                        (partes.length ? ' (' + partes.join(' · ') + ')' : ''));
                } else {
                    pintarTarjeta(it, false, false, (d && d.mensaje) || ('No se pudo registrar (error ' + r.status + ').'));
                }
            })).catch(() => pintarTarjeta(it, false, false, 'Sin conexión con el servidor. Intenta de nuevo.'));
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