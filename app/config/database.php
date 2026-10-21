<?php

function cargarEnv(string $ruta): void
{
    if (!file_exists($ruta)) {
        throw new RuntimeException('No se encontró el archivo .env');
    }

    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }

        [$clave, $valor] = array_map('trim', explode('=', $linea, 2));

        $_ENV[$clave] = $valor;
    }
}

cargarEnv(dirname(__DIR__, 2) . '/.env');

foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_FACTURACION_HOST', 'DB_FACTURACION_PORT'] as $clave) {
    if (!isset($_ENV[$clave]) || $_ENV[$clave] === '') {
        throw new RuntimeException('Falta la variable de entorno ' . $clave);
    }
}

$credenciales = [
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
];

return [
    'default' => [
        'host' => $_ENV['DB_HOST'],
        'port' => $_ENV['DB_PORT'],
    ] + $credenciales,
    'facturacion' => [
        'host' => $_ENV['DB_FACTURACION_HOST'],
        'port' => $_ENV['DB_FACTURACION_PORT'],
    ] + $credenciales,
];
