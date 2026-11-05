<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';
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


awr_exigir_csrf(is_array($entrada) ? $entrada : []);
if (!administradorEnSesion()) {
    responder(403, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

unset($entrada['id_sucursal']);

try {
    switch ($entrada['op'] ?? null) {
        case 'listarClientes':
            responder(200, listarClientes($entrada));
            break;
        case 'obtenerDetalleCliente':
            responder(200, obtenerDetalleCliente($entrada));
            break;
        case 'crearCliente':
            responder(201, crearCliente($entrada));
            break;
        case 'actualizarCliente':
            responder(200, actualizarCliente($entrada));
            break;
        case 'cambiarEstadoCliente':
            responder(200, cambiarEstadoCliente($entrada));
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
} catch (Throwable $e) {
    error_log('AWR ClientesAdministradorController: ' . $e->getMessage());
    responder(500, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function listarClientes(array $entrada): array
{
    $busqueda = textoOpcional($entrada, 'busqueda', 200);
    $tipo = filtroTipo($entrada);
    $estado = filtroEstado($entrada);
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

    if ($tipo !== null) {
        $consulta->where('tipo_identificacion', $tipo);
    }

    if ($estado !== null) {
        $consulta->where('estado', $estado);
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
        $lista[] = filaListado($fila);
    }

    return [
        'exito' => true,
        'kpis' => kpisMaestro(),
        'pagina_actual' => $pagina,
        'por_pagina' => $porPagina,
        'total_registros' => $total,
        'total_paginas' => $total === 0 ? 0 : (int) ceil($total / $porPagina),
        'filas' => $lista,
    ];
}

function obtenerDetalleCliente(array $entrada): array
{
    $idCliente = idClienteSolicitado($entrada);
    $fila = clientePorId($idCliente);

    if ($fila === null) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'Cliente no disponible',
        ]);
    }

    return [
        'exito' => true,
        'cliente' => filaDetalle($fila),
    ];
}

function crearCliente(array $entrada): array
{
    $tipo = tipoCreacion($entrada['tipo_identificacion'] ?? null);
    $identificacion = cadenaObligatoria($entrada['identificacion'] ?? null, 'Identificación inválida');
    $razonSocial = cadenaObligatoria($entrada['razon_social'] ?? null, 'Razón social no válida.');
    $direccion = cadenaOpcional($entrada['direccion'] ?? null, 255, 'Dirección no válida.');
    $telefono = cadenaOpcional($entrada['telefono'] ?? null, 30, 'Teléfono no válido.');
    $correo = cadenaOpcional($entrada['correo'] ?? null, 150, 'Correo no válido.');

    if (esProtegido($tipo, $identificacion)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Consumidor Final es un registro protegido',
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
            'mensaje' => 'El cliente ya se encuentra registrado',
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
                'mensaje' => 'El cliente ya se encuentra registrado',
            ]);
        }

        throw $e;
    }

    $fila = clientePorId((int) $idCliente);

    return [
        'exito' => true,
        'cliente' => filaDetalle($fila),
    ];
}

function actualizarCliente(array $entrada): array
{
    $idCliente = idClienteSolicitado($entrada);
    $fila = clientePorId($idCliente);

    if ($fila === null) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'Cliente no disponible',
        ]);
    }

    if (esProtegido((string) $fila->tipo_identificacion, (string) $fila->identificacion)) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'Consumidor Final es un registro protegido',
        ]);
    }

    $razonSocial = cadenaObligatoria($entrada['razon_social'] ?? null, 'Razón social no válida.');
    $direccion = cadenaOpcional($entrada['direccion'] ?? null, 255, 'Dirección no válida.');
    $telefono = cadenaOpcional($entrada['telefono'] ?? null, 30, 'Teléfono no válido.');
    $correo = cadenaOpcional($entrada['correo'] ?? null, 150, 'Correo no válido.');

    if (mb_strlen($razonSocial) > 200) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Razón social no válida.',
        ]);
    }

    DB::table('cliente')
        ->where('id_cliente', $idCliente)
        ->update([
            'razon_social' => $razonSocial,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'correo' => $correo,
        ]);

    return [
        'exito' => true,
        'cliente' => filaDetalle(clientePorId($idCliente)),
    ];
}

function cambiarEstadoCliente(array $entrada): array
{
    $idCliente = idClienteSolicitado($entrada);
    $nuevoEstado = estadoObligatorio($entrada['nuevo_estado'] ?? null);

    if ($nuevoEstado === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación inválida.',
        ]);
    }

    $fila = clientePorId($idCliente);

    if ($fila === null) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'Cliente no disponible',
        ]);
    }

    if (esProtegido((string) $fila->tipo_identificacion, (string) $fila->identificacion)) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'Consumidor Final es un registro protegido',
        ]);
    }

    DB::table('cliente')
        ->where('id_cliente', $idCliente)
        ->update([
            'estado' => $nuevoEstado,
        ]);

    return [
        'exito' => true,
        'cliente' => filaDetalle(clientePorId($idCliente)),
    ];
}

function kpisMaestro(): array
{
    $fila = DB::table('cliente')
        ->where('tipo_identificacion', '<>', 'CONSUMIDOR_FINAL')
        ->where('identificacion', '<>', '9999999999999')
        ->selectRaw('COUNT(*) FILTER (WHERE estado IS TRUE)::int AS activos')
        ->selectRaw('COUNT(*) FILTER (WHERE estado IS FALSE)::int AS inactivos')
        ->selectRaw("COUNT(*) FILTER (WHERE tipo_identificacion = 'CEDULA')::int AS cedulas")
        ->selectRaw("COUNT(*) FILTER (WHERE tipo_identificacion = 'RUC')::int AS ruc")
        ->selectRaw("COUNT(*) FILTER (WHERE tipo_identificacion = 'PASAPORTE')::int AS pasaportes")
        ->first();

    return [
        'activos' => (int) ($fila->activos ?? 0),
        'inactivos' => (int) ($fila->inactivos ?? 0),
        'cedulas' => (int) ($fila->cedulas ?? 0),
        'ruc' => (int) ($fila->ruc ?? 0),
        'pasaportes' => (int) ($fila->pasaportes ?? 0),
    ];
}

function clientePorId(int $idCliente): ?object
{
    return DB::table('cliente')->where('id_cliente', $idCliente)->first([
        'id_cliente',
        'tipo_identificacion',
        'identificacion',
        'razon_social',
        'direccion',
        'telefono',
        'correo',
        'estado',
        'fecha_creacion',
        'fecha_actualizacion',
    ]);
}

function filaListado(object $fila): array
{
    $tipo = (string) $fila->tipo_identificacion;
    $identificacion = (string) $fila->identificacion;

    return [
        'id_cliente' => (int) $fila->id_cliente,
        'tipo_identificacion' => $tipo,
        'identificacion' => $identificacion,
        'razon_social' => (string) $fila->razon_social,
        'direccion' => textoONull($fila->direccion ?? null),
        'telefono' => textoONull($fila->telefono ?? null),
        'correo' => textoONull($fila->correo ?? null),
        'estado' => esVerdadero($fila->estado),
        'protegido' => esProtegido($tipo, $identificacion),
    ];
}

function filaDetalle(?object $fila): array
{
    if ($fila === null) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'Cliente no disponible',
        ]);
    }

    $listado = filaListado($fila);
    $listado['fecha_creacion'] = textoONull($fila->fecha_creacion ?? null);
    $listado['fecha_actualizacion'] = textoONull($fila->fecha_actualizacion ?? null);

    return $listado;
}

function esProtegido(string $tipo, string $identificacion): bool
{
    return $tipo === 'CONSUMIDOR_FINAL' || $identificacion === '9999999999999';
}

function administradorEnSesion(): bool
{
    if (!isset($_COOKIE[session_name()])) {
        return false;
    }

    awr_iniciar_sesion();

    return isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
        && $_SESSION['rol'] === 'Administrador';
}

function tipoCreacion(mixed $valor): string
{
    if (!is_string($valor)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Tipo de identificación no válido.',
        ]);
    }

    $tipo = mb_strtoupper(trim($valor), 'UTF-8');

    if ($tipo === 'CONSUMIDOR_FINAL') {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Consumidor Final es un registro protegido',
        ]);
    }

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
            'mensaje' => 'Identificación inválida',
        ]);
    }
}

function filtroTipo(array $entrada): ?string
{
    if (!array_key_exists('tipo_identificacion', $entrada) || $entrada['tipo_identificacion'] === null || $entrada['tipo_identificacion'] === '') {
        return null;
    }

    if (!is_string($entrada['tipo_identificacion'])) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Tipo de identificación no válido.',
        ]);
    }

    $tipo = mb_strtoupper(trim($entrada['tipo_identificacion']), 'UTF-8');

    if (!in_array($tipo, ['CEDULA', 'RUC', 'PASAPORTE', 'CONSUMIDOR_FINAL'], true)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Tipo de identificación no válido.',
        ]);
    }

    return $tipo;
}

function filtroEstado(array $entrada): ?bool
{
    if (!array_key_exists('estado', $entrada) || $entrada['estado'] === null || $entrada['estado'] === '') {
        return null;
    }

    $estado = estadoObligatorio($entrada['estado']);

    if ($estado === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación inválida.',
        ]);
    }

    return $estado;
}

function estadoObligatorio(mixed $valor): ?bool
{
    if ($valor === true || $valor === 1 || $valor === '1' || $valor === 'true') {
        return true;
    }

    if ($valor === false || $valor === 0 || $valor === '0' || $valor === 'false') {
        return false;
    }

    return null;
}

function idClienteSolicitado(array $entrada): int
{
    $id = enteroPositivo($entrada['id_cliente'] ?? null);

    if ($id === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación inválida.',
        ]);
    }

    return $id;
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

function textoONull(mixed $valor): ?string
{
    if ($valor === null) {
        return null;
    }

    return (string) $valor;
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
