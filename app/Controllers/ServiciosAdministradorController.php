<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';
use App\Services\EstadoSistemaService;

if (PHP_SAPI !== 'cli') {
    atenderSolicitudServicios();
}

function atenderSolicitudServicios(): void
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


awr_exigir_csrf(is_array($entrada) ? $entrada : []);
    $op = $entrada['op'] ?? null;

    switch ($op) {
        case 'listarServicios':
            listarServicios();
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
}

function listarServicios(): void
{
    $sesionValida = false;

    if (isset($_COOKIE[session_name()])) {
        awr_iniciar_sesion();
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
        responder(200, proyectarServicios($estado));
    } catch (Throwable $e) {
        error_log('AWR listarServicios: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function proyectarServicios(array $estado): array
{
    $fecha = new DateTimeImmutable('now', new DateTimeZone('America/Guayaquil'));

    return [
        'exito' => true,
        'timestamp_consulta' => $fecha->format(DateTimeInterface::ATOM),
        'servicios' => [
            servicioAplicacion(is_array($estado['aplicacion'] ?? null) ? $estado['aplicacion'] : []),
            servicioPostgresql(
                is_array($estado['postgresql'] ?? null) ? $estado['postgresql'] : [],
                is_array($estado['enlace'] ?? null) ? $estado['enlace'] : []
            ),
            servicioToxiproxy(is_array($estado['toxiproxy'] ?? null) ? $estado['toxiproxy'] : []),
            servicioRedpanda(is_array($estado['redpanda'] ?? null) ? $estado['redpanda'] : []),
            servicioClickHouse(is_array($estado['clickhouse'] ?? null) ? $estado['clickhouse'] : []),
        ],
    ];
}

function servicioAplicacion(array $aplicacion): array
{
    $version = is_string($aplicacion['version_php'] ?? null) ? trim($aplicacion['version_php']) : '';
    $servicio = [
        'nombre' => 'AWR / PHP',
        'estado' => textoEstado($aplicacion['estado'] ?? null, 'No disponible'),
        'detalle' => $version !== '' ? 'PHP ' . $version : 'No disponible',
    ];

    if (array_key_exists('memoria_usada_peticion', $aplicacion)) {
        $servicio['memoria_usada_peticion'] = $aplicacion['memoria_usada_peticion'];
    }

    return $servicio;
}

function servicioPostgresql(array $postgresql, array $enlace): array
{
    $version = is_string($postgresql['version'] ?? null) ? trim($postgresql['version']) : '';
    $base = is_string($postgresql['base'] ?? null) ? trim($postgresql['base']) : '';
    $partes = array_values(array_filter([$version, $base], static fn (string $parte): bool => $parte !== ''));
    $servicio = [
        'nombre' => 'PostgreSQL',
        'estado' => textoEstado($postgresql['estado'] ?? null, 'No disponible'),
        'detalle' => $partes === [] ? 'No disponible' : implode(' · ', $partes),
    ];

    if (array_key_exists('tiempo_respuesta_ms', $postgresql)) {
        $servicio['tiempo_respuesta_ms'] = $postgresql['tiempo_respuesta_ms'];
    }

    if (array_key_exists('latencia_red_ms', $enlace)) {
        $servicio['latencia_red_ms'] = $enlace['latencia_red_ms'];
    }

    return $servicio;
}

function servicioToxiproxy(array $toxiproxy): array
{
    $proxy = is_string($toxiproxy['proxy'] ?? null) ? trim($toxiproxy['proxy']) : '';
    $servicio = [
        'nombre' => 'Toxiproxy',
        'estado' => textoEstado($toxiproxy['estado'] ?? null, 'No disponible'),
        'detalle' => $proxy !== '' ? $proxy : 'No disponible',
    ];

    if (array_key_exists('latencia_configurada_ms', $toxiproxy)) {
        $servicio['latencia_artificial_ms'] = $toxiproxy['latencia_configurada_ms'];
    }

    return $servicio;
}

function servicioRedpanda(array $redpanda): array
{
    $broker = is_string($redpanda['broker'] ?? null) ? trim($redpanda['broker']) : '';
    $kafka = $redpanda['puerto_kafka'] ?? null;
    $admin = $redpanda['puerto_admin'] ?? null;
    $detalle = 'No disponible';

    if ($broker !== '' && is_int($kafka)) {
        $detalle = $broker . ':' . $kafka;
    }

    $servicio = [
        'nombre' => 'Redpanda',
        'estado' => textoEstado($redpanda['estado'] ?? null, 'No disponible'),
        'detalle' => $detalle,
    ];

    if (is_int($admin)) {
        $servicio['puerto_admin'] = $admin;
    }

    return $servicio;
}

function servicioClickHouse(array $clickhouse): array
{
    $version = is_string($clickhouse['version'] ?? null) ? trim($clickhouse['version']) : '';
    $base = is_string($clickhouse['base'] ?? null) ? trim($clickhouse['base']) : '';
    $partes = array_values(array_filter([$version, $base], static fn (string $parte): bool => $parte !== ''));
    $servicio = [
        'nombre' => 'ClickHouse',
        'estado' => textoEstado($clickhouse['estado'] ?? null, 'No disponible'),
        'detalle' => $partes === [] ? 'No disponible' : implode(' · ', $partes),
    ];

    if (is_int($clickhouse['tablas'] ?? null)) {
        $servicio['tablas'] = $clickhouse['tablas'];
    }

    if (is_int($clickhouse['tiempo_respuesta_ms'] ?? null)) {
        $servicio['respuesta_ms'] = $clickhouse['tiempo_respuesta_ms'];
    }

    return $servicio;
}

function textoEstado(mixed $estado, string $predeterminado): string
{
    if (!is_string($estado)) {
        return $predeterminado;
    }

    $limpio = trim($estado);

    return $limpio === '' ? $predeterminado : $limpio;
}

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
