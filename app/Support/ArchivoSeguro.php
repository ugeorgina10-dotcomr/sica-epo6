<?php

namespace App\Support;

/**
 * Revisión de archivos subidos: devuelve el texto del problema o null si el archivo se ve limpio.
 * Solo mira el CONTENIDO real (no se fía de la extensión ni del tipo que declara el navegador).
 *
 * Bloquea: programas/scripts disfrazados, nombres con doble extensión peligrosa, Word con macros, objetos incrustados,
 * plantillas remotas o ActiveX, zips "bomba" (pequeños que al abrirse pesan gigas), y PDF con JavaScript, acciones de lanzar
 * programas, archivos adjuntos o contenido activo (RichMedia/XFA).
 */
class ArchivoSeguro
{
    /** Extensiones que nunca deben aparecer en el nombre ni dentro de un Word (ni siquiera como "doble extensión"). */
    private const PELIGROSAS = [
        'php', 'phtml', 'phar', 'exe', 'dll', 'bat', 'cmd', 'com', 'scr', 'msi', 'js', 'jse', 'vbs', 'vbe', 'wsf', 'ps1',
        'sh', 'jar', 'py', 'pl', 'cgi', 'asp', 'aspx', 'jsp', 'htm', 'html', 'svg', 'hta', 'lnk', 'reg', 'docm', 'xlsm', 'xlsb', 'pptm',
    ];

    private const MAX_ENTRADAS_ZIP    = 3000;
    private const MAX_DESCOMPRIMIDO   = 300 * 1024 * 1024;   // 300 MB ya abierto
    private const MAX_RAZON_ENTRADA   = 200;                 // una entrada que se infla más de 200 veces es sospechosa

    /** Revisa el nombre original del archivo. */
    public static function problemaDeNombre(string $nombre): ?string
    {
        if ($nombre === '' || preg_match('/[\x00-\x1F\x7F]/', $nombre) || str_contains($nombre, '..') || str_contains($nombre, '/') || str_contains($nombre, '\\')) {
            return 'El nombre del archivo trae caracteres no permitidos. Renómbralo solo con letras, números, espacios o guiones.';
        }
        // "lista.php.pdf", "lista.exe.xlsx": cualquier parte intermedia peligrosa descarta el archivo
        $partes = array_map('strtolower', explode('.', $nombre));
        array_shift($partes);   // lo que va antes del primer punto es el nombre, no una extensión
        foreach ($partes as $p) {
            if (in_array(trim($p), self::PELIGROSAS, true)) {
                return 'El nombre del archivo tiene una doble extensión sospechosa (".' . trim($p) . '"). No se puede subir.';
            }
        }

        return null;
    }

    /** Revisa el contenido de un PDF (ya leído en memoria). */
    public static function problemaDePdf(string $contenido): ?string
    {
        // Se miran solo las partes de texto del PDF: dentro de las imágenes/streams comprimidos cualquier combinación de bytes puede salir por azar
        $contenido = self::sinStreams($contenido);
        // Contenido activo: JavaScript, lanzar programas, adjuntos, multimedia/Flash y formularios XFA dinámicos
        if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|EmbeddedFiles|RichMedia|XFA|SubmitForm|ImportData)(?![A-Za-z0-9])#', $contenido, $m)) {
            return 'El PDF trae contenido activo no permitido (/' . $m[1] . '): scripts, adjuntos o acciones automáticas. Expórtalo de nuevo a PDF desde Word ("Guardar como PDF") para que quede limpio.';
        }

        return null;
    }

    /** Quita lo que hay entre "stream" y "endstream" (datos binarios) para quedarse con los diccionarios del PDF. */
    private static function sinStreams(string $pdf): string
    {
        $salida = '';
        $pos    = 0;
        $n      = strlen($pdf);
        while ($pos < $n) {
            $ini = strpos($pdf, 'stream', $pos);
            if ($ini === false) {
                $salida .= substr($pdf, $pos);
                break;
            }
            $salida .= substr($pdf, $pos, $ini - $pos);
            $fin = strpos($pdf, 'endstream', $ini + 6);
            if ($fin === false) {
                break;
            }
            $pos = $fin + 9;
        }

        return $salida;
    }

    /** Revisa un Word/Excel (.docx/.xlsx), que por dentro es un zip. $ruta = archivo en disco. */
    public static function problemaDeOffice(string $ruta): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            return null;   // sin la extensión zip no se puede mirar adentro; la validación de formato ya se hizo antes
        }
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) {
            return 'El archivo está dañado y no se pudo abrir por dentro.';
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRADAS_ZIP) {
                return 'El archivo trae demasiadas partes internas y parece una bomba de compresión. No se puede subir.';
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $e = $zip->statIndex($i);
                if ($e === false) {
                    return 'El archivo está dañado por dentro.';
                }
                $nombre = str_replace('\\', '/', (string) $e['name']);
                $min    = strtolower($nombre);

                if (str_starts_with($nombre, '/') || str_contains($nombre, '../')) {
                    return 'El archivo trae rutas internas peligrosas. No se puede subir.';
                }
                if (str_contains($min, 'vbaproject') || str_contains($min, 'macrosheets/') || str_contains($min, 'activex/') || str_contains($min, '/embeddings/') || str_contains($min, 'oleobject') || str_contains($min, 'vbadata')) {
                    return 'El archivo trae macros, ActiveX u objetos incrustados. Guárdalo sin macros (.docx / .xlsx normal) y vuelve a subirlo.';
                }
                $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
                if ($ext !== 'svg' && in_array($ext, self::PELIGROSAS, true)) {   // los .svg son imágenes normales dentro de un Word moderno
                    return 'El archivo trae adentro un archivo ejecutable o script (.' . $ext . '). No se puede subir.';
                }
                $total += (int) $e['size'];
                if ($total > self::MAX_DESCOMPRIMIDO) {
                    return 'El archivo, ya abierto, pesa demasiado (posible bomba de compresión). No se puede subir.';
                }
                if ((int) $e['comp_size'] > 0 && (int) $e['size'] > 5 * 1024 * 1024 && $e['size'] / $e['comp_size'] > self::MAX_RAZON_ENTRADA) {
                    return 'Una parte del archivo está comprimida de forma sospechosa (bomba de compresión). No se puede subir.';
                }
            }

            $tipos = $zip->getFromName('[Content_Types].xml');
            if (is_string($tipos) && stripos($tipos, 'macroEnabled') !== false) {
                return 'El archivo está guardado con macros activadas (docm/xlsm). Guárdalo como .docx o .xlsx normal.';
            }

            // Relaciones hacia fuera: plantilla remota, objetos OLE o subdocumentos cargados de internet
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombre = (string) $zip->getNameIndex($i);
                if (!str_ends_with(strtolower($nombre), '.rels')) {
                    continue;
                }
                $xml = (string) $zip->getFromIndex($i, 2 * 1024 * 1024);
                if (preg_match('#TargetMode="External"#i', $xml) && preg_match('#relationships/(attachedTemplate|oleObject|subDocument|frame|externalLink|externalLinkPath|package)#i', $xml)) {
                    return 'El archivo carga contenido externo (plantilla remota u objeto vinculado). No se puede subir.';
                }
            }
        } finally {
            $zip->close();
        }

        return null;
    }
}
