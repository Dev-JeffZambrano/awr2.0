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

return [
    'host' => $_ENV['DB_HOST'],
    'port' => $_ENV['DB_PORT'],
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
];
