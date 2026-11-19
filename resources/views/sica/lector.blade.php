<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lector Fijo - SICA-EPO6</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Librería para lectura de códigos QR desde la cámara -->
    <script src="{{ asset('vendor/html5-qrcode.min.js') }}" type="text/javascript"></script>
</head>
<body class="bg-slate-50 min-h-screen">
    <header class="bg-[#800020] text-white py-3 px-6 shadow-md flex justify-between items-center">
        <div>
            <h2 class="text-xs font-light">C.C.T. 15EBH0008G • C.C.T. 15EBH0199N</h2>
            <h1 class="text-lg font-bold">ESCUELA PREPARATORIA OFICIAL NÚM. 6</h1>
        </div>
        <div>
            <a href="{{ route('login') }}" class="text-xs bg-white/10 hover:bg-white/20 py-2 px-4 rounded-lg transition">Regresar al Panel</a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto p-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 text-center">
            <h3 class="text-sm font-black text-gray-800 uppercase tracking-wide">Lector Fijo - Control de Entradas y Salidas</h3>
            <p class="text-xs text-gray-500 mt-1 mb-6">El sistema detecta el grupo del alumno y, según el horario del grupo, registra solo si es Entrada o Salida. Escanea con la cámara frontal.</p>

            <!-- Botones de Modo -->
            <div class="flex justify-center gap-4 mb-6">
                <button id="btnEntrada" onclick="setModo('Entrada')" class="px-6 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-200 transition">Entrada</button>
                <button id="btnSalida" onclick="setModo('Salida')" class="px-6 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-200 transition">Salida</button>
            </div>

            <p id="txtModo" class="text-xs font-bold text-green-700 -mt-3 mb-5">Modo AUTOMÁTICO: el sistema detecta el grupo del alumno y registra solo si es entrada o salida según su horario.</p>

            <!-- Contenedor de la Cámara -->
            <div class="max-w-md mx-auto bg-black rounded-2xl overflow-hidden shadow-inner relative mb-4">
                <div id="reader" class="w-full"></div>
            </div>

            <!-- Controles de Cámara -->
            <div class="flex justify-center gap-3">
                <button onclick="iniciarCamara()" class="px-5 py-2 bg-[#800020] hover:bg-[#600018] text-white text-xs font-bold rounded-xl shadow transition">Activar Cámara Frontal</button>
                <button onclick="detenerCamara()" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold rounded-xl transition">Detener</button>
            </div>

            <!-- Resultado del Escaneo -->
            <div id="resultado" class="mt-6 p-4 bg-slate-100 rounded-xl hidden text-left max-w-md mx-auto">
                <p class="text-xs font-bold text-gray-700">Alumno detectado:</p>
                <p id="infoAlumno" class="text-sm text-gray-900 font-semibold mt-1"></p>
            </div>
        </div>
    </main>

    <script>
        let html5QrCode;
        let modoActual = 'Auto';   // Auto = el servidor decide Entrada o Salida por el horario del grupo del alumno
        const ACTIVO = "px-6 py-2.5 bg-[#800020] text-white text-xs font-bold rounded-xl shadow transition";
        const INACTIVO = "px-6 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-200 transition";

        function setModo(tipo) {
            modoActual = (modoActual === tipo) ? 'Auto' : tipo;   // pulsar otra vez el mismo botón = volver a Automático
            document.getElementById('btnEntrada').className = modoActual === 'Entrada' ? ACTIVO : INACTIVO;
            document.getElementById('btnSalida').className = modoActual === 'Salida' ? ACTIVO : INACTIVO;
            const t = document.getElementById('txtModo');
            if (modoActual === 'Auto') {
                t.className = 'text-xs font-bold text-green-700 -mt-3 mb-5';
                t.innerText = 'Modo AUTOMÁTICO: el sistema detecta el grupo del alumno y registra solo si es entrada o salida según su horario.';
            } else {
                t.className = 'text-xs font-bold text-red-600 -mt-3 mb-5';
                t.innerText = 'Modo MANUAL: todo escaneo se registra como ' + modoActual.toUpperCase() + '. Vuelve a pulsar «' + modoActual + '» para regresar al modo automático.';
            }
        }

        // El QR de la credencial muestra solo la escuela y las C.C.T.; el código del alumno va al final con caracteres invisibles
        // (16 caracteres hexadecimales = 22 invisibles, 3 bits cada uno). También se aceptan los QR anteriores: "EPO6 SICA<código>" y la CURP sola.
        const QR_ZW = String.fromCharCode(0x200B, 0x200C, 0x200D, 0x2060, 0x2061, 0x2062, 0x2063, 0x2064);   // 8 invisibles (ancho cero) = 3 bits cada uno
        function qrCodigoOculto(txt) {
            const leer = t => {
                let d = ''; for (const ch of t) { const i = QR_ZW.indexOf(ch); if (i >= 0) d += i; }
                let bits = '';
                if (d.length === 22) for (const x of d) bits += (+x).toString(2).padStart(3, '0');
                else if (d.length === 32 && !/[4-7]/.test(d)) for (const x of d) bits += (+x).toString(2).padStart(2, '0');   // versión anterior
                else return '';
                let h = ''; for (let i = 0; i < 64; i += 4) h += parseInt(bits.substr(i, 4), 2).toString(16);
                return h;
            };
            txt = String(txt); let h = leer(txt);
            if (!h && /â[\u0080-¿]/.test(txt)) { try { h = leer(decodeURIComponent(escape(txt))); } catch (e) {} }   // escáner que lee en Latin-1
            return h;
        }
        function datosDeQr(texto) {
            const oculto = qrCodigoOculto(texto);
            if (oculto) return { qr: oculto };
            const limpio = String(texto).toUpperCase().replace(/[^A-Z0-9]/g, '');
            const m = limpio.match(/SICA([0-9A-F]{16,20})$/);
            if (m) return { qr: m[1].slice(0, 16).toLowerCase() };
            if (/PREPARATORIA/.test(limpio) && /15EBH0008G/.test(limpio) && /15EBH0199N/.test(limpio)) return null;   // QR solo con datos de la escuela
            return { curp: String(texto).trim().toUpperCase() };
        }

        let ultimoQr = { t: '', ts: 0 };
        function registrar(decodedText) {
            if (ultimoQr.t === decodedText && Date.now() - ultimoQr.ts < 4000) return;   // lectura repetida
            ultimoQr = { t: decodedText, ts: Date.now() };
            const caja = document.getElementById('resultado'), info = document.getElementById('infoAlumno');
            caja.classList.remove('hidden');
            const datos = datosDeQr(decodedText);
            if (!datos) { info.innerText = 'Este QR solo trae los datos de la escuela, no el código de un alumno.'; return; }
            info.innerText = 'Buscando alumno…';
            fetch('/api/sica/sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ accion: 'registrar_asistencia', datos: Object.assign(datos, { modo: modoActual }) })
            }).then(r => r.json().catch(() => ({})).then(d => {
                if (r.ok && d.ok) {
                    const al = d.alumno || {};
                    info.innerText = (d.modo === 'Salida' ? '¡Salida' : '¡Entrada') + ' registrada correctamente! ' + al.nombre + ' - ' + al.grado + '° ' + al.grupo + ' (' + al.turno + ') · ' + d.hora + (d.estatus === 'Retardo' ? ' · Retardo' : '');
                } else {
                    info.innerText = (d && d.mensaje) || ('No se pudo registrar (error ' + r.status + ').');
                }
            })).catch(() => { info.innerText = 'Sin conexión con el servidor. Intenta de nuevo.'; });
        }

        function iniciarCamara() {
            html5QrCode = new Html5Qrcode("reader");
            // Configuración estricta para forzar la cámara frontal (user) en el lector fijo de entrada
            html5QrCode.start(
                { facingMode: "user" }, 
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    // Éxito al leer el QR del alumno
                    registrar(decodedText);
                },
                (errorMessage) => {
                    // Errores de escaneo en tiempo real se pueden ignorar
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