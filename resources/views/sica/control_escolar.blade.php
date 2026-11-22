<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control Escolar - SICA-EPO6</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js para las gráficas estadísticas comparativas -->
    <script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
</head>
<body class="bg-slate-50 min-h-screen">
    <header class="bg-[#800020] text-white py-3 px-6 shadow-md flex justify-between items-center">
        <div>
            <h2 class="text-xs font-light">C.C.T. 15EBH0008G • C.C.T. 15EBH0199N</h2>
            <h1 class="text-lg font-bold">ESCUELA PREPARATORIA OFICIAL NÚM. 6</h1>
        </div>
        <div>
            <a href="{{ route('login') }}" class="text-xs bg-white/10 hover:bg-white/20 py-2 px-4 rounded-lg transition">Cerrar Sesión</a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6 space-y-6">
        <!-- Tarjetas de Estadísticas Principales -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 text-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Alumnos</p>
                <h4 class="text-3xl font-black text-gray-800 mt-2">640</h4>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 text-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Asistencias Hoy</p>
                <h4 class="text-3xl font-black text-emerald-600 mt-2">612</h4>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 text-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Salidas Registradas</p>
                <h4 class="text-3xl font-black text-blue-600 mt-2">485</h4>
            </div>
        </div>

        <!-- Panel de Gestión y Carga de Archivos (Exclusivo Control Escolar) -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
            <h3 class="text-sm font-black text-gray-800 uppercase tracking-wide mb-4">Gestión y Carga de Listas (Excel) y Plantillas</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Subir Lista Excel -->
                <div class="border-2 border-dashed border-gray-200 p-6 rounded-xl text-center">
                    <p class="text-xs font-bold text-gray-700 mb-2">Subir Lista de Alumnos por Grupo (.xlsx / .csv)</p>
                    <input type="file" class="text-xs text-gray-500 mb-4 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#800020] file:text-white hover:file:bg-[#600018]">
                    <button class="w-full py-3 bg-slate-800 text-white text-base font-bold rounded-lg hover:bg-slate-950 transition">Importar y Generar QR Automáticos</button>
                </div>

                <!-- Subir Plantilla Credencial -->
                <div class="border-2 border-dashed border-gray-200 p-6 rounded-xl text-center">
                    <p class="text-xs font-bold text-gray-700 mb-2">Subir Plantilla Base para Credenciales</p>
                    <input type="file" class="text-xs text-gray-500 mb-4 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#800020] file:text-white hover:file:bg-[#600018]">
                    <button class="w-full py-3 bg-slate-800 text-white text-base font-bold rounded-lg hover:bg-slate-950 transition">Guardar Plantilla Oficial</button>
                </div>
            </div>
        </div>

        <!-- Sección de Gráficas Comparativas -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
            <h3 class="text-sm font-black text-gray-800 uppercase tracking-wide mb-4">Estadísticas Comparativas de Asistencia, Retardos y Permanencia por Grupo</h3>
            <div class="w-full h-72 flex justify-center">
                <canvas id="graficaAsistencias"></canvas>
            </div>
        </div>
    </main>

    <script>
        // Configuración de Gráfica Comparativa con Chart.js
        const ctx = document.getElementById('graficaAsistencias').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Grupo 1.1', 'Grupo 1.2', 'Grupo 1.3', 'Grupo 1.4', 'Grupo 2.1', 'Grupo 2.2'],
                datasets: [
                    { label: 'Asistencias', data: [95, 90, 92, 88, 94, 91], backgroundColor: '#10b981' },
                    { label: 'Retardos', data: [4, 8, 5, 9, 3, 6], backgroundColor: '#f59e0b' },
                    { label: 'Permanencia', data: [1, 2, 3, 3, 3, 3], backgroundColor: '#ef4444' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html>