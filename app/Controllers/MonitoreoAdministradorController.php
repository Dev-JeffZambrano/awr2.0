<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';
use App\Services\EstadoResilienciaService;
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

    if ($longitud < 0 || $longitud > 65536) {
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
        case 'obtenerEstadoMonitoreo':
            obtenerEstadoMonitoreo();
            break;
        case 'obtenerFacturasPendientes':
            obtenerFacturasPendientes();
            break;
        case 'procesarFacturaPendiente':
            procesarFacturaPendiente($entrada);
            break;
        case 'procesarFacturasPendientes':
            procesarFacturasPendientes($entrada);
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
    exigirAdministradorMonitoreo();

    try {
        $estado = EstadoSistemaService::obtenerEstadoActual();
        $resiliencia = EstadoResilienciaService::obtenerResumen();
        responder(200, proyectarMonitoreo($estado, $resiliencia));
    } catch (Throwable $e) {
        error_log('AWR obtenerEstadoMonitoreo: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function obtenerFacturasPendientes(): void
{
    exigirAdministradorMonitoreo();

    try {
        $detalle = EstadoResilienciaService::obtenerOperacionesPendientes();
        responder(200, [
            'exito' => true,
            'disponible' => ($detalle['disponible'] ?? false) === true,
            'facturas_pendientes' => $detalle['facturas_pendientes'] ?? null,
            'operaciones' => is_array($detalle['operaciones'] ?? null) ? $detalle['operaciones'] : [],
            'nota' => is_string($detalle['nota'] ?? null) ? $detalle['nota'] : 'No disponible',
        ]);
    } catch (Throwable $e) {
        error_log('AWR obtenerFacturasPendientes: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function procesarFacturaPendiente(array $entrada): void
{
    exigirAdministradorMonitoreo();

    $idOperacion = $entrada['id_operacion'] ?? null;

    if (!is_string($idOperacion) || trim($idOperacion) === '') {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    try {
        $resultado = EstadoResilienciaService::procesarFacturaPendiente($idOperacion);
        $resumen = EstadoResilienciaService::obtenerResumen();

        responder(200, [
            'exito' => true,
            'resultado' => $resultado,
            'resiliencia' => [
                'disponible' => ($resumen['disponible'] ?? false) === true,
                'facturas_pendientes' => $resumen['facturas_pendientes'] ?? null,
                'nota' => $resumen['nota'] ?? 'No disponible',
            ],
        ]);
    } catch (Throwable $e) {
        error_log('AWR procesarFacturaPendiente: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function procesarFacturasPendientes(array $entrada): void
{
    exigirAdministradorMonitoreo();

    $ids = $entrada['id_operacion'] ?? null;

    if (!is_array($ids) || $ids === []) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    if (count($ids) > 50) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    try {
        $resultados = EstadoResilienciaService::procesarFacturasPendientes($ids);
        $resumen = EstadoResilienciaService::obtenerResumen();

        responder(200, [
            'exito' => true,
            'resultados' => $resultados,
            'resiliencia' => [
                'disponible' => ($resumen['disponible'] ?? false) === true,
                'facturas_pendientes' => $resumen['facturas_pendientes'] ?? null,
                'nota' => $resumen['nota'] ?? 'No disponible',
            ],
        ]);
    } catch (Throwable $e) {
        error_log('AWR procesarFacturasPendientes: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function exigirAdministradorMonitoreo(): void
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
}

function proyectarMonitoreo(array $estado, array $resiliencia): array
{
    $aplicacion = is_array($estado['aplicacion'] ?? null) ? $estado['aplicacion'] : [];
    $postgresql = is_array($estado['postgresql'] ?? null) ? $estado['postgresql'] : [];
    $toxiproxy = is_array($estado['toxiproxy'] ?? null) ? $estado['toxiproxy'] : [];
    $fecha = new DateTimeImmutable('now', new DateTimeZone('America/Guayaquil'));
    $disponible = ($resiliencia['disponible'] ?? false) === true;
    $pendientes = $disponible && is_int($resiliencia['facturas_pendientes'] ?? null)
        ? $resiliencia['facturas_pendientes']
        : null;
    $consumidor = is_array($resiliencia['consumidor'] ?? null) ? $resiliencia['consumidor'] : [];

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
        'resiliencia' => [
            'disponible' => $disponible,
            'facturas_pendientes' => $pendientes,
            'nota' => $disponible
                ? ($pendientes === 0 ? 'Sin operaciones pendientes' : 'Operaciones esperando reproceso')
                : 'No disponible',
            'redpanda_estado' => $disponible
                ? 'Operativo'
                : (is_string($resiliencia['redpanda']['estado'] ?? null)
                    ? $resiliencia['redpanda']['estado']
                    : 'No disponible'),
            'consumidor_estado' => is_string($consumidor['estado'] ?? null)
                ? $consumidor['estado']
                : 'No disponible',
            'consumidor_miembros' => is_int($consumidor['miembros'] ?? null) ? $consumidor['miembros'] : null,
        ],
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
