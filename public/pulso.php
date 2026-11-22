<?php
/*
 * Aviso instantáneo de cambios (ver app/Support/Pulso.php).
 * No arranca Laravel, no usa sesión ni base de datos: solo lee un contador de un archivo pequeño.
 * No entrega ningún dato del sistema, únicamente "algo cambió" (números que suben con cada cambio).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$d = @file_get_contents(__DIR__ . '/../storage/framework/pulso.json');
$j = ($d !== false && $d !== '') ? json_decode($d, true) : null;

echo json_encode([
    'e' => (int) ($j['e'] ?? 0),
    'c' => (int) ($j['c'] ?? 0),
    'p' => (int) ($j['p'] ?? 0),
]);
