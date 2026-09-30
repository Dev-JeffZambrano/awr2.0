<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Models\Usuario;
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

$op = $entrada['op'] ?? null;

switch ($op) {
    case 'obtenerResumenAdministrador':
        obtenerResumenAdministrador();
        break;
    default:
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación no reconocida.',
        ]);
}

function obtenerResumenAdministrador(): void
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
        $total = (int) Usuario::query()->count();
        $habilitados = (int) Usuario::query()->where('estado', true)->count();
        $ultimoAcceso = Usuario::query()->max('ultimo_acceso');

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

        $sesionesActivas = (int) DB::table('sesion_usuario')
            ->where('estado', true)
            ->whereRaw('fecha_expiracion > NOW()')
            ->count();

        $intentosFallidos = (int) DB::table('auditoria_login')
            ->where('resultado', 'FALLIDO')
            ->count();

        $auditoria = (int) DB::table('auditoria_login')->count();
        $roles = (int) DB::table('rol')->count();

        $motor = DB::selectOne(
            "SELECT current_setting('server_version') AS version,
                    inet_server_port() AS puerto,
                    (SELECT COUNT(*)::int
                       FROM information_schema.tables
                      WHERE table_schema = 'public'
                        AND table_type = 'BASE TABLE') AS tablas"
        );

        $version = versionPostgres($motor->version ?? null);
        $tablas = (int) ($motor->tablas ?? 0);
        $puerto = (int) ($motor->puerto ?? 0);

        responder(200, [
            'exito' => true,
            'usuarios' => [
                'total' => $total,
                'habilitados' => $habilitados,
                'administrador' => $porRol['Administrador'],
                'administrativo' => $porRol['Administrativo'],
                'operador' => $porRol['Operador'],
                'sesionesActivas' => $sesionesActivas,
                'insignia' => $habilitados . ' habilitados',
                'nota' => $porRol['Operador'] . ' operadores · '
                    . $porRol['Administrativo'] . ' administrativos · '
                    . $porRol['Administrador'] . ' administrador · '
                    . $sesionesActivas . ' sesiones',
                'rolesTexto' => $porRol['Administrador'] . ' Admin · '
                    . $porRol['Administrativo'] . ' Adm. · '
                    . $porRol['Operador'] . ' Op.',
            ],
            'ultimoAcceso' => formatearAcceso(is_string($ultimoAcceso) ? $ultimoAcceso : null),
            'intentosFallidos' => $intentosFallidos,
            'roles' => $roles,
            'auditoria' => $auditoria,
            'configuracion' => 'Pendiente',
            'postgresql' => [
                'conectado' => true,
                'version' => $version,
                'tablas' => $tablas,
                'puerto' => $puerto,
                'detalle' => 'PostgreSQL ' . $version . ' · ' . $tablas . ' tablas',
                'nota' => 'Conectado',
                'servicio' => $puerto > 0 ? 'PostgreSQL · ' . $puerto : 'PostgreSQL',
            ],
            'clickhouse' => 'Pendiente de integración',
            'redpanda' => 'Pendiente de integración',
            'latencia' => 'Pendiente de monitoreo',
            'disponibilidad' => 'Pendiente de monitoreo',
            'seguridad' => 'Pendiente',
            'rendimiento' => 'Pendiente de monitoreo',
        ]);
    } catch (Throwable $e) {
        error_log('AWR obtenerResumenAdministrador: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function versionPostgres(mixed $cruda): string
{
    $texto = trim((string) $cruda);
    $corta = strtok($texto, ' ');

    if (!is_string($corta) || !preg_match('/^\d+(?:\.\d+)*$/', $corta)) {
        return 'Pendiente';
    }

    return $corta;
}

function formatearAcceso(?string $valor): string
{
    if ($valor === null || trim($valor) === '') {
        return 'Pendiente';
    }

    $fecha = new DateTimeImmutable($valor);
    $fecha = $fecha->setTimezone(new DateTimeZone('America/Guayaquil'));
    $meses = [
        1 => 'ene',
        2 => 'feb',
        3 => 'mar',
        4 => 'abr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'sept',
        10 => 'oct',
        11 => 'nov',
        12 => 'dic',
    ];
    $meridiano = $fecha->format('a') === 'am' ? 'a. m.' : 'p. m.';

    return $fecha->format('j') . ' ' . $meses[(int) $fecha->format('n')] . ' ' . $fecha->format('Y')
        . ', ' . $fecha->format('h:i') . ' ' . $meridiano;
}

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
