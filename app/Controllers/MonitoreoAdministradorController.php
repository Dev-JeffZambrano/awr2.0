<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\EstadoSistemaService;

if (PHP_SAPI !== 'cli') {
    atenderSolicitudMonitoreo();
}

function atenderSolicitudMonitoreo(): void
{
    header('Content-Type: application/json; charset=utf-8');

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $directorio = str_replace('\\', '/', dirname($script, 3));
    $rutaCookie = rtrim($directorio, '/');
    $rutaCookie = ($rutaCookie === '' || $rutaCookie === '.') ? '/' : $rutaCookie . '/';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $rutaCookie,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        responder(405, [
            'exito' => false,
            'mensaje' => 'Método no permitido.',
        ]);
    }

    $longitud = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($longitud < 0 || $longitud > 4096) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $crudo = file_get_contents('php://input');
    $entrada = json_decode(is_string($crudo) ? $crudo : '', true);

    if (!is_array($entrada)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $op = $entrada['op'] ?? null;

    switch ($op) {
        case 'obtenerEstadoMonitoreo':
            obtenerEstadoMonitoreo();
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
}

function obtenerEstadoMonitoreo(): void
{
    $sesionValida = false;

    if (isset($_COOKIE[session_name()])) {
        session_start();
        $sesionValida = isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
            && $_SESSION['rol'] === 'Administrador';
    }

    if (!$sesionValida) {
        responder(403, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    try {
        $estado = EstadoSistemaService::obtenerEstadoActual();
        responder(200, proyectarMonitoreo($estado));
    } catch (Throwable $e) {
        error_log('AWR obtenerEstadoMonitoreo: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function proyectarMonitoreo(array $estado): array
{
    $aplicacion = is_array($estado['aplicacion'] ?? null) ? $estado['aplicacion'] : [];
    $postgresql = is_array($estado['postgresql'] ?? null) ? $estado['postgresql'] : [];
    $toxiproxy = is_array($estado['toxiproxy'] ?? null) ? $estado['toxiproxy'] : [];
    $fecha = new DateTimeImmutable('now', new DateTimeZone('America/Guayaquil'));

    return [
        'exito' => true,
        'timestamp_consulta' => $fecha->format(DateTimeInterface::ATOM),
        'estado_general' => resolverEstadoGeneral($estado),
        'aplicacion' => [
            'estado' => $aplicacion['estado'] ?? null,
            'version_php' => $aplicacion['version_php'] ?? null,
            'memoria_usada_peticion' => $aplicacion['memoria_usada_peticion'] ?? null,
        ],
        'postgresql' => [
            'estado' => $postgresql['estado'] ?? null,
            'version' => $postgresql['version'] ?? null,
            'base' => $postgresql['base'] ?? null,
            'tablas' => $postgresql['tablas'] ?? null,
            'tamano_bytes' => $postgresql['tamano_bytes'] ?? null,
            'conexiones' => $postgresql['conexiones'] ?? null,
            'tiempo_respuesta_ms' => $postgresql['tiempo_respuesta_ms'] ?? null,
            'puerto' => $postgresql['puerto'] ?? null,
        ],
        'toxiproxy' => [
            'estado' => $toxiproxy['estado'] ?? null,
            'proxy' => $toxiproxy['proxy'] ?? null,
            'enabled' => $toxiproxy['habilitado'] ?? null,
            'latencia_configurada_ms' => $toxiproxy['latencia_configurada_ms'] ?? null,
        ],
        'latencias' => [
            'latencia_red_ms' => $estado['enlace']['latencia_red_ms'] ?? null,
            'latencia_artificial_ms' => $toxiproxy['latencia_configurada_ms'] ?? null,
            'tiempo_respuesta_postgresql_ms' => $postgresql['tiempo_respuesta_ms'] ?? null,
        ],
        'redpanda' => is_array($estado['redpanda'] ?? null) ? $estado['redpanda'] : ['estado' => 'No disponible'],
        'clickhouse' => is_array($estado['clickhouse'] ?? null) ? $estado['clickhouse'] : ['estado' => 'No disponible'],
    ];
}

function resolverEstadoGeneral(array $estado): string
{
    $aplicacion = (string) ($estado['aplicacion']['estado'] ?? '');
    $postgresql = (string) ($estado['postgresql']['estado'] ?? '');
    $toxiproxy = (string) ($estado['toxiproxy']['estado'] ?? '');

    if ($aplicacion !== 'Operativo' || $postgresql !== 'Conectado') {
        return 'No disponible';
    }

    if ($toxiproxy !== 'Operativo') {
        return 'Degradado';
    }

    foreach (['redpanda', 'clickhouse'] as $componente) {
        $valor = (string) ($estado[$componente]['estado'] ?? '');

        if (!in_array($valor, ['No integrado', 'Operativo', 'Conectado'], true)) {
            return 'Degradado';
        }
    }

    return 'Operativo';
}

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
