<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;

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

if ($longitud < 0 || $longitud > 8192) {
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

$idUsuario = operadorEnSesion();

if ($idUsuario < 1) {
    responder(403, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

unset($entrada['id_sucursal']);

try {
    switch ($entrada['op'] ?? null) {
        case 'listarClientes':
            responder(200, listarClientes($idUsuario, $entrada));
            break;
        case 'crearCliente':
            responder(201, crearCliente($idUsuario, $entrada));
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
} catch (Throwable $e) {
    error_log('AWR FicheroClientesController: ' . $e->getMessage());
    responder(500, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function listarClientes(int $idUsuario, array $entrada): array
{
    exigirSucursalActiva($idUsuario);
    $busqueda = textoOpcional($entrada, 'busqueda', 200);
    $pagina = paginaSolicitada($entrada);
    $porPagina = 20;
    $consulta = DB::table('cliente');

    if ($busqueda !== '') {
        $como = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $busqueda) . '%';
        $consulta->where(function ($grupo) use ($como): void {
            $grupo->whereRaw("identificacion ILIKE ? ESCAPE '|'", [$como])
                ->orWhereRaw("razon_social ILIKE ? ESCAPE '|'", [$como]);
        });
    }

    $total = (int) (clone $consulta)->count();
    $offset = ($pagina - 1) * $porPagina;

    if (!is_int($offset) || $offset < 0) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Página no válida.',
        ]);
    }

    $filas = $consulta
        ->orderBy('razon_social')
        ->orderBy('id_cliente')
        ->offset($offset)
        ->limit($porPagina)
        ->get([
            'id_cliente',
            'tipo_identificacion',
            'identificacion',
            'razon_social',
            'direccion',
            'telefono',
            'correo',
            'estado',
        ]);
    $lista = [];

    foreach ($filas as $fila) {
        $lista[] = [
            'id_cliente' => (int) $fila->id_cliente,
            'tipo_identificacion' => (string) $fila->tipo_identificacion,
            'identificacion' => (string) $fila->identificacion,
            'razon_social' => (string) $fila->razon_social,
            'direccion' => $fila->direccion === null ? null : (string) $fila->direccion,
            'telefono' => $fila->telefono === null ? null : (string) $fila->telefono,
            'correo' => $fila->correo === null ? null : (string) $fila->correo,
            'estado' => esVerdadero($fila->estado),
        ];
    }

    return [
        'exito' => true,
        'pagina_actual' => $pagina,
        'por_pagina' => $porPagina,
        'total_registros' => $total,
        'total_paginas' => $total === 0 ? 0 : (int) ceil($total / $porPagina),
        'filas' => $lista,
    ];
}

function crearCliente(int $idUsuario, array $entrada): array
{
    exigirSucursalActiva($idUsuario);
    $tipo = tipoIdentificacion($entrada['tipo_identificacion'] ?? null);
    $identificacion = cadenaObligatoria($entrada['identificacion'] ?? null, 'Identificación no válida.');
    $razonSocial = cadenaObligatoria($entrada['razon_social'] ?? null, 'Razón social no válida.');
    $direccion = cadenaOpcional($entrada['direccion'] ?? null, 255, 'Dirección no válida.');
    $telefono = cadenaOpcional($entrada['telefono'] ?? null, 30, 'Teléfono no válido.');
    $correo = cadenaOpcional($entrada['correo'] ?? null, 150, 'Correo no válido.');

    if ($identificacion === '9999999999999') {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Identificación no válida.',
        ]);
    }

    validarIdentificacion($tipo, $identificacion);

    if (mb_strlen($razonSocial) > 200) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Razón social no válida.',
        ]);
    }

    $duplicado = DB::table('cliente')
        ->where('tipo_identificacion', $tipo)
        ->where('identificacion', $identificacion)
        ->exists();

    if ($duplicado) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'El cliente ya está registrado',
        ]);
    }

    try {
        $idCliente = DB::table('cliente')->insertGetId([
            'tipo_identificacion' => $tipo,
            'identificacion' => $identificacion,
            'razon_social' => $razonSocial,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'correo' => $correo,
            'estado' => true,
        ], 'id_cliente');
    } catch (QueryException $e) {
        if (esColisionCliente($e)) {
            responder(200, [
                'exito' => false,
                'mensaje' => 'El cliente ya está registrado',
            ]);
        }

        throw $e;
    }

    return [
        'exito' => true,
        'cliente' => [
            'id_cliente' => (int) $idCliente,
            'tipo_identificacion' => $tipo,
            'identificacion' => $identificacion,
            'razon_social' => $razonSocial,
        ],
    ];
}

function exigirSucursalActiva(int $idUsuario): void
{
    $sucursales = DB::select(
        'SELECT s.id_sucursal
         FROM usuario_sucursal us
         JOIN sucursal s ON s.id_sucursal = us.id_sucursal AND s.estado IS TRUE
         WHERE us.id_usuario = ? AND us.estado IS TRUE',
        [$idUsuario]
    );

    if (count($sucursales) !== 1) {
        responder(403, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function operadorEnSesion(): int
{
    if (!isset($_COOKIE[session_name()])) {
        return 0;
    }

    session_start();
    $rol = $_SESSION['rol'] ?? null;
    $id = $_SESSION['id_usuario'] ?? null;

    if ($rol === 'Operador' && is_numeric($id) && (int) $id > 0 && !is_float($id)) {
        return (int) $id;
    }

    return 0;
}

function tipoIdentificacion(mixed $valor): string
{
    if (!is_string($valor)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Tipo de identificación no válido.',
        ]);
    }

    $tipo = mb_strtoupper(trim($valor), 'UTF-8');

    if (!in_array($tipo, ['CEDULA', 'RUC', 'PASAPORTE'], true)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Tipo de identificación no válido.',
        ]);
    }

    return $tipo;
}

function validarIdentificacion(string $tipo, string $identificacion): void
{
    $valida = match ($tipo) {
        'CEDULA' => preg_match('/^[0-9]{10}$/D', $identificacion) === 1,
        'RUC' => preg_match('/^[0-9]{13}$/D', $identificacion) === 1,
        'PASAPORTE' => mb_strlen($identificacion) >= 1 && mb_strlen($identificacion) <= 20,
        default => false,
    };

    if (!$valida) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Identificación no válida.',
        ]);
    }
}

function cadenaObligatoria(mixed $valor, string $mensaje): string
{
    if (!is_string($valor)) {
        responder(400, [
            'exito' => false,
            'mensaje' => $mensaje,
        ]);
    }

    $texto = trim($valor);

    if ($texto === '') {
        responder(400, [
            'exito' => false,
            'mensaje' => $mensaje,
        ]);
    }

    return $texto;
}

function cadenaOpcional(mixed $valor, int $maximo, string $mensaje): ?string
{
    if ($valor === null || $valor === '') {
        return null;
    }

    if (!is_string($valor)) {
        responder(400, [
            'exito' => false,
            'mensaje' => $mensaje,
        ]);
    }

    $texto = trim($valor);

    if ($texto === '') {
        return null;
    }

    if (mb_strlen($texto) > $maximo) {
        responder(400, [
            'exito' => false,
            'mensaje' => $mensaje,
        ]);
    }

    return $texto;
}

function textoOpcional(array $entrada, string $clave, int $maximo): string
{
    if (!array_key_exists($clave, $entrada) || $entrada[$clave] === null || $entrada[$clave] === '') {
        return '';
    }

    if (!is_string($entrada[$clave])) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $texto = trim($entrada[$clave]);

    if (mb_strlen($texto) > $maximo) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    return $texto;
}

function paginaSolicitada(array $entrada): int
{
    if (!array_key_exists('pagina', $entrada) || $entrada['pagina'] === null || $entrada['pagina'] === '') {
        return 1;
    }

    $pagina = enteroPositivo($entrada['pagina']);

    if ($pagina === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Página no válida.',
        ]);
    }

    return $pagina;
}

function enteroPositivo(mixed $valor): ?int
{
    if (is_int($valor) && $valor > 0) {
        return $valor;
    }

    if (is_string($valor) && preg_match('/^[1-9][0-9]*$/', $valor) === 1) {
        $numero = (int) $valor;

        return (string) $numero === $valor ? $numero : null;
    }

    return null;
}

function esColisionCliente(QueryException $e): bool
{
    $estado = (string) ($e->errorInfo[0] ?? '');
    $detalle = mb_strtolower($e->getMessage(), 'UTF-8');

    return $estado === '23505' && str_contains($detalle, 'uq_cliente_tipo_identificacion');
}

function esVerdadero(mixed $valor): bool
{
    return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true';
}

function responder(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
