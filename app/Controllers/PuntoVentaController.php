<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';
use App\Services\VentaRechazada;
use App\Services\VentaService;

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
$idUsuario = 0;

if (isset($_COOKIE[session_name()])) {
    awr_iniciar_sesion();
    $rol = $_SESSION['rol'] ?? null;
    $id = $_SESSION['id_usuario'] ?? null;

    if ($rol === 'Operador' && is_numeric($id) && (int) $id > 0) {
        $idUsuario = (int) $id;
    }
}

if ($idUsuario < 1) {
    responder(403, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

unset($entrada['id_sucursal']);

try {
    switch ($entrada['op'] ?? null) {
        case 'obtenerDatosPos':
            responder(200, VentaService::obtenerDatosPos($idUsuario));
            break;
        case 'buscarProductos':
            $texto = $entrada['texto'] ?? '';
            responder(200, VentaService::buscarProductos($idUsuario, is_string($texto) ? $texto : ''));
            break;
        case 'buscarClientes':
            $texto = $entrada['texto'] ?? '';
            responder(200, VentaService::buscarClientes($idUsuario, is_string($texto) ? $texto : ''));
            break;
        case 'cotizarVenta':
            responder(200, VentaService::cotizarVenta($idUsuario, is_array($entrada['productos'] ?? null) ? $entrada['productos'] : []));
            break;
        case 'confirmarVenta':
            responder(200, VentaService::confirmarVenta($idUsuario, $entrada));
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
} catch (VentaRechazada $e) {
    responder(200, [
        'exito' => false,
        'mensaje' => $e->getMessage(),
    ]);
} catch (Throwable $e) {
    error_log('AWR PuntoVentaController: ' . $e->getMessage());
    responder(500, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
