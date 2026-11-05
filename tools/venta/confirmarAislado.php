<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Solo CLI';
    exit(1);
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\VentaDegradada;
use App\Services\VentaRechazada;
use App\Services\VentaService;

putenv('AWR_VENTA_DIRECTA=1');
$_ENV['AWR_VENTA_DIRECTA'] = '1';

$crudo = stream_get_contents(STDIN);
$payload = json_decode(is_string($crudo) ? $crudo : '', true);

if (!is_array($payload) || !is_array($payload['entrada'] ?? null)) {
    echo json_encode(['tipo' => 'rechazada', 'mensaje' => 'Datos incompletos.'], JSON_UNESCAPED_UNICODE);
    exit(3);
}

$idUsuario = $payload['id_usuario'] ?? null;

if (!is_int($idUsuario) && !(is_string($idUsuario) && preg_match('/^[1-9][0-9]*$/', (string) $idUsuario) === 1)) {
    echo json_encode(['tipo' => 'rechazada', 'mensaje' => 'No disponible'], JSON_UNESCAPED_UNICODE);
    exit(3);
}

try {
    $datos = VentaService::confirmarVenta((int) $idUsuario, $payload['entrada'], false);
    echo json_encode(['tipo' => 'ok', 'datos' => $datos], JSON_UNESCAPED_UNICODE);
    exit(0);
} catch (VentaDegradada $e) {
    echo json_encode(['tipo' => 'degradada', 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(2);
} catch (VentaRechazada $e) {
    echo json_encode(['tipo' => 'rechazada', 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(3);
} catch (Throwable $e) {
    error_log('AWR confirmarAislado: ' . $e->getMessage());
    echo json_encode(['tipo' => 'degradada', 'mensaje' => 'PostgreSQL no disponible'], JSON_UNESCAPED_UNICODE);
    exit(2);
}
