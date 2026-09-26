<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Models\Usuario;

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
    case 'iniciarSesion':
        iniciarSesion($entrada);
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

    try {
        $usuario = Usuario::query()->where('username', $username)->first();

        $hash = $usuario === null
            ? '$2y$12$XwHBIHQJckKjQd52ZKsnYuxRdLrYWbBtQR/XfayehUUyM6eY3lXsq'
            : (string) ($usuario->getAttributes()['password_hash'] ?? '');

        $claveCorrecta = password_verify($password, $hash);

        if ($usuario === null || $usuario->estado !== true || $claveCorrecta !== true) {
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

        session_start();
        session_regenerate_id(true);

        $_SESSION = [
            'id_usuario' => (int) $usuario->id_usuario,
            'username' => (string) $usuario->username,
            'id_rol' => (int) $rol->id_rol,
            'rol' => $nombreRol,
        ];

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

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
