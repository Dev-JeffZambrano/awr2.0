<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';
use App\Models\Usuario;
use App\Services\EstadoResilienciaService;
use App\Services\EstadoSistemaService;
use Illuminate\Database\Capsule\Manager as DB;

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
    case 'obtenerResumenAdministrador':
        obtenerResumenAdministrador();
        break;
    case 'obtenerSenales':
        obtenerSenales();
        break;
    default:
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación no reconocida.',
        ]);
}

function obtenerSenales(): void
{
    exigirAdministrador();

    try {
        $estado = EstadoSistemaService::obtenerEstadoActual();
        $postgres = is_array($estado['postgresql'] ?? null) ? $estado['postgresql'] : [];
        $toxiproxy = is_array($estado['toxiproxy'] ?? null) ? $estado['toxiproxy'] : [];
        $milisegundos = $toxiproxy['latencia_configurada_ms'] ?? null;
        $latencia = 'No disponible';

        if (is_int($milisegundos) || (is_string($milisegundos) && preg_match('/^\d+$/', $milisegundos) === 1)) {
            $latencia = ((int) $milisegundos) . ' ms';
        }

        $conectado = ($postgres['estado'] ?? null) === 'Conectado';
        $sincronizacion = 'No disponible';

        if ($conectado) {
            $pendientes = DB::table('operacion_idempotente as o')
                ->where('o.tipo_operacion', 'FACTURA')
                ->whereNotExists(function ($consulta) {
                    $consulta->selectRaw('1')
                        ->from('factura as f')
                        ->whereColumn('f.id_operacion', 'o.id_operacion');
                })
                ->count();
            $sincronizacion = $pendientes === 0
                ? 'Al día'
                : $pendientes . ($pendientes === 1 ? ' pendiente' : ' pendientes');
        }

        responder(200, [
            'exito' => true,
            'disponibilidad' => $conectado ? 'Operativo' : 'No disponible',
            'latencia' => $latencia,
            'sincronizacion' => $sincronizacion,
        ]);
    } catch (Throwable $e) {
        error_log('AWR obtenerSenales: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function obtenerResumenAdministrador(): void
{
    exigirAdministrador();

    try {
        $inicioApi = hrtime(true);
        $total = (int) Usuario::query()->count();
        $habilitados = (int) Usuario::query()->where('estado', true)->count();
        $inhabilitados = (int) Usuario::query()->where('estado', false)->count();

        $porRol = [
            'Administrador' => 0,
            'Administrativo' => 0,
            'Operador' => 0,
        ];

        $filasRol = DB::table('usuario_rol')
            ->join('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
            ->where('usuario_rol.estado', true)
            ->where('rol.estado', true)
            ->whereIn('rol.nombre', array_keys($porRol))
            ->groupBy('rol.nombre')
            ->select('rol.nombre', DB::raw('COUNT(DISTINCT usuario_rol.id_usuario) AS total'))
            ->get();

        foreach ($filasRol as $fila) {
            $nombre = (string) $fila->nombre;
            if (array_key_exists($nombre, $porRol)) {
                $porRol[$nombre] = (int) $fila->total;
            }
        }

        $intentosFallidos = (int) DB::table('auditoria_login')
            ->where('resultado', 'FALLIDO')
            ->count();

        $roles = (int) DB::table('rol')->count();

        $estado = EstadoSistemaService::obtenerEstadoActual();
        $resiliencia = EstadoResilienciaService::obtenerResumen();
        $motor = $estado['postgresql'];
        $aplicacion = is_array($estado['aplicacion'] ?? null) ? $estado['aplicacion'] : [];
        $versionPhp = is_string($aplicacion['version_php'] ?? null) ? $aplicacion['version_php'] : '';
        $conectado = ($motor['estado'] ?? null) === 'Conectado';
        $version = is_string($motor['version'] ?? null) ? $motor['version'] : 'No disponible';
        $tablas = is_int($motor['tablas'] ?? null) ? $motor['tablas'] : 0;
        $puerto = is_int($motor['puerto'] ?? null) ? $motor['puerto'] : 0;

        responder(200, [
            'exito' => true,
            'usuarios' => [
                'total' => $total,
                'habilitados' => $habilitados,
                'inhabilitados' => $inhabilitados,
                'administrador' => $porRol['Administrador'],
                'administrativo' => $porRol['Administrativo'],
                'operador' => $porRol['Operador'],
                'insignia' => $habilitados . ' habilitados',
                'nota' => $porRol['Operador'] . ' operadores · '
                    . $porRol['Administrativo'] . ' administrativos · '
                    . $porRol['Administrador'] . ' administrador',
                'rolesTexto' => $porRol['Administrador'] . ' Admin · '
                    . $porRol['Administrativo'] . ' Adm. · '
                    . $porRol['Operador'] . ' Op.',
            ],
            'intentosFallidos' => $intentosFallidos,
            'roles' => $roles,
            'configuracion' => 'Pendiente',
            'aplicacion' => [
                'estado' => ($aplicacion['estado'] ?? null) === 'Operativo' ? 'Operativo' : 'No disponible',
                'servicio' => $versionPhp !== '' ? 'Aplicación PHP · ' . $versionPhp : 'Aplicación PHP',
            ],
            'postgresql' => [
                'conectado' => $conectado,
                'version' => $conectado ? $version : 'No disponible',
                'tablas' => $motor['tablas'] ?? null,
                'puerto' => $motor['puerto'] ?? null,
                'detalle' => $conectado
                    ? 'PostgreSQL ' . $version . ' · ' . $tablas . ' tablas'
                    : 'No disponible',
                'nota' => $conectado ? 'Conectado' : 'No disponible',
                'servicio' => $conectado && $puerto > 0 ? 'PostgreSQL · ' . $puerto : 'PostgreSQL',
            ],
            'latenciaRedMs' => $estado['toxiproxy']['latencia_configurada_ms'] ?? null,
            'latencia_red_ms' => $estado['enlace']['latencia_red_ms'] ?? null,
            'tiempoPostgresMs' => $motor['tiempo_respuesta_ms'] ?? null,
            'tiempoApiMs' => (int) round((hrtime(true) - $inicioApi) / 1000000),
            'redpanda' => proyectarRedpanda(is_array($estado['redpanda'] ?? null) ? $estado['redpanda'] : []),
            'clickhouse' => proyectarClickHouse(is_array($estado['clickhouse'] ?? null) ? $estado['clickhouse'] : []),
            'facturasPendientes' => proyectarFacturasPendientes($resiliencia),
            'estado' => $estado,
        ]);
    } catch (Throwable $e) {
        error_log('AWR obtenerResumenAdministrador: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function proyectarRedpanda(array $redpanda): array
{
    $operativo = ($redpanda['estado'] ?? null) === 'Operativo';

    return [
        'estado' => $operativo ? 'Operativo' : 'No disponible',
        'broker' => $operativo && is_string($redpanda['broker'] ?? null) ? $redpanda['broker'] : null,
        'puerto_kafka' => $operativo && is_int($redpanda['puerto_kafka'] ?? null) ? $redpanda['puerto_kafka'] : null,
        'puerto_admin' => $operativo && is_int($redpanda['puerto_admin'] ?? null) ? $redpanda['puerto_admin'] : null,
    ];
}

function proyectarFacturasPendientes(array $resiliencia): array
{
    if (($resiliencia['disponible'] ?? false) !== true) {
        return [
            'disponible' => false,
            'cantidad' => null,
            'valor' => 'No disponible',
            'nota' => 'No disponible',
        ];
    }

    $cantidad = is_int($resiliencia['facturas_pendientes'] ?? null)
        ? $resiliencia['facturas_pendientes']
        : null;

    if ($cantidad === null) {
        return [
            'disponible' => false,
            'cantidad' => null,
            'valor' => 'No disponible',
            'nota' => 'No disponible',
        ];
    }

    return [
        'disponible' => true,
        'cantidad' => $cantidad,
        'valor' => (string) $cantidad,
        'nota' => $cantidad === 0
            ? 'Sin operaciones pendientes'
            : 'Operaciones esperando reproceso',
    ];
}

function proyectarClickHouse(array $clickhouse): array
{
    $conectado = ($clickhouse['estado'] ?? null) === 'Conectado';

    return [
        'estado' => $conectado ? 'Conectado' : 'No disponible',
        'version' => $conectado && is_string($clickhouse['version'] ?? null) ? $clickhouse['version'] : null,
        'base' => $conectado && is_string($clickhouse['base'] ?? null) ? $clickhouse['base'] : null,
        'tablas' => $conectado && is_int($clickhouse['tablas'] ?? null) ? $clickhouse['tablas'] : null,
        'tiempo_respuesta_ms' => $conectado && is_int($clickhouse['tiempo_respuesta_ms'] ?? null)
            ? $clickhouse['tiempo_respuesta_ms']
            : null,
    ];
}

function exigirAdministrador(): void
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

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
