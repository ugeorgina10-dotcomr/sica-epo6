<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Escuela Preparatoria Oficial Núm. 6</title>
    <link rel="icon" href="{{ asset('img/logo6.png') }}">
    <style>
        html, body { height: 100%; margin: 0; }
        body { display: flex; align-items: center; justify-content: center; background: #800020; color: #fff;
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; text-align: center; }
        main { padding: 24px; max-width: 420px; }
        img { width: 150px; max-width: 55vw; height: auto; background: #fff; border-radius: 50%; padding: 10px; }
        h1 { font-size: 1.15rem; letter-spacing: .04em; margin: 20px 0 0; text-transform: uppercase; line-height: 1.4; }
        a { color: #fff; }
        p { font-size: .85rem; margin: 14px 0 0; line-height: 1.7; opacity: .92; }
    </style>
</head>
<body>
    <main>
        <img src="{{ asset('img/logo6.png') }}" alt="Logo de la Escuela Preparatoria Oficial Núm. 6">
        <h1>Escuela Preparatoria<br>Oficial Núm. 6</h1>
        <p>C.C.T. 15EBH0008G (Matutino)<br>C.C.T. 15EBH0199N (Vespertino)</p>
        @if (!empty($ubicacion))
            <p class="ubic">{{ $ubicacion }}<br>
                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode('Escuela Preparatoria Oficial Núm. 6 ' . $ubicacion) }}" rel="noopener">Ver en el mapa</a></p>
        @endif
    </main>
</body>
</html>
