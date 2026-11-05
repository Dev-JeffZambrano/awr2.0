<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';
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


awr_exigir_csrf(is_array($entrada) ? $entrada : []);
$op = $entrada['op'] ?? null;

switch ($op) {
    case 'iniciarSesion':
        iniciarSesion($entrada);
        break;
    case 'cerrarSesion':
        cerrarSesion();
        break;
    default:
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación no reconocida.',
        ]);
}

function iniciarSesion(array $entrada): void
{
    $username = $entrada['username'] ?? null;
    $password = $entrada['password'] ?? null;

    if (!is_string($username) || !is_string($password)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $username = trim($username);

    if ($username === '' || mb_strlen($username) > 100 || $password === '' || mb_strlen($password) > 255) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    if (!awr_login_permitido($username)) {
        responder(429, [
            'exito' => false,
            'mensaje' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.',
        ]);
    }

    try {
        $usuario = Usuario::query()->where('username', $username)->first();

        $hash = $usuario === null
            ? '$2y$12$XwHBIHQJckKjQd52ZKsnYuxRdLrYWbBtQR/XfayehUUyM6eY3lXsq'
            : (string) ($usuario->getAttributes()['password_hash'] ?? '');

        $claveCorrecta = password_verify($password, $hash);

        if ($usuario === null || $usuario->estado !== true || $claveCorrecta !== true) {
            awr_login_registrar_fallo($username);
            responder(200, [
                'exito' => false,
                'mensaje' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        $roles = $usuario->roles()
            ->where('rol.estado', true)
            ->wherePivot('estado', true)
            ->get();

        if ($roles->count() !== 1) {
            responder(200, [
                'exito' => false,
                'mensaje' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        $rol = $roles->first();
        $nombreRol = (string) $rol->nombre;
        $destinos = [
            'Administrador' => 'dashboardAdministrador.php',
            'Administrativo' => 'dashboardAdministrativo.php',
            'Operador' => 'dashboardOperador.php',
        ];

        if (!isset($destinos[$nombreRol])) {
            responder(200, [
                'exito' => false,
                'mensaje' => 'Usuario o contraseña incorrectos.',
            ]);
        }

        $sucursal = null;

        if ($nombreRol === 'Operador') {
            $sucursales = DB::select(
                'SELECT s.id_sucursal, s.nombre, s.ciudad
                 FROM usuario_sucursal us
                 JOIN sucursal s ON s.id_sucursal = us.id_sucursal AND s.estado IS TRUE
                 WHERE us.id_usuario = ? AND us.estado IS TRUE',
                [(int) $usuario->id_usuario]
            );

            if (count($sucursales) !== 1) {
                responder(200, [
                    'exito' => false,
                    'mensaje' => 'No disponible',
                ]);
            }

            $sucursal = $sucursales[0];
        }

        awr_iniciar_sesion();
        session_regenerate_id(true);

        $_SESSION = [
            'id_usuario' => (int) $usuario->id_usuario,
            'username' => (string) $usuario->username,
            'nombres' => (string) $usuario->nombres,
            'apellidos' => (string) $usuario->apellidos,
            'id_rol' => (int) $rol->id_rol,
            'rol' => $nombreRol,
            '_csrf' => bin2hex(random_bytes(32)),
        ];

        if ($sucursal !== null) {
            $_SESSION['id_sucursal'] = (int) $sucursal->id_sucursal;
            $_SESSION['sucursal'] = (string) $sucursal->nombre;
            $_SESSION['ciudad'] = (string) $sucursal->ciudad;
        }

        awr_login_limpiar($username);

        responder(200, [
            'exito' => true,
            'redireccion' => $destinos[$nombreRol],
        ]);
    } catch (Throwable) {
        responder(500, [
            'exito' => false,
            'mensaje' => 'No se pudo iniciar sesión.',
        ]);
    }
}

function cerrarSesion(): void
{
    if (isset($_COOKIE[session_name()])) {
        awr_iniciar_sesion();
        $_SESSION = [];

        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);

        session_destroy();
    }

    responder(200, [
        'exito' => true,
        'redireccion' => 'login.php',
    ]);
}

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
