<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - SICA-EPO6</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Encabezado Institucional Superior -->
    <header class="bg-[#800020] text-white py-3 px-6 shadow-md flex justify-between items-center">
        <div>
            <h2 class="text-xs font-light">C.C.T. 15EBH0008G • C.C.T. 15EBH0199N</h2>
            <h1 class="text-lg font-bold">ESCUELA PREPARATORIA OFICIAL NÚM. 6</h1>
        </div>
        <div>
            <a href="{{ url('/') }}" class="text-xs bg-white/10 hover:bg-white/20 py-2 px-4 rounded-lg transition">Salida / Regresar al Inicio</a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6">
        <!-- Selector de Perfiles de Usuario (Guía Visual) -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-wrap gap-4 items-center justify-between">
            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Selecciona tu perfil de usuario:</span>
            <div class="flex flex-wrap gap-2">
                <button class="px-4 py-3 bg-[#800020] text-white text-base font-bold rounded-lg shadow">Alumno</button>
                <button class="px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-base font-semibold rounded-lg">Lector Fijo (Entrada/Salida)</button>
                <button class="px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-base font-semibold rounded-lg">Orientador (Grupos)</button>
                <button class="px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-base font-semibold rounded-lg">Encargado General</button>
            </div>
        </div>

        <!-- Portal del Alumno / Credencial Digital -->
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200">
            <h3 class="text-center text-sm font-black text-gray-800 uppercase tracking-wide mb-2">Portal del Alumno - Credencial Digital</h3>
            <p class="text-center text-xs text-gray-500 mb-6">Consulta tus datos, descarga y genera tu código QR único.</p>
            
            <div class="max-w-md mx-auto">
                <label class="block text-xs font-bold text-gray-700 mb-2 uppercase">Ingresa tu matrícula o CURP:</label>
                <input type="text" placeholder="Ej. 2026EPO01..." class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#800020] text-sm mb-4">
                <button class="w-full py-3 bg-[#800020] hover:bg-[#600018] text-white font-bold rounded-xl text-base uppercase tracking-wider shadow-md transition">
                    Generar Credencial y QR
                </button>
            </div>
        </div>
    </main>
</body>
</html>