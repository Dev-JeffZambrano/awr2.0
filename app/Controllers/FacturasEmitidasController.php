<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;

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
        case 'listarFacturasEmitidas':
            responder(200, listarFacturasEmitidas($idUsuario, $entrada));
            break;
        case 'obtenerDetalleFacturaEmitida':
            $detalle = obtenerDetalleFacturaEmitida($idUsuario, $entrada);

            if ($detalle === null) {
                responder(200, [
                    'exito' => false,
                    'mensaje' => 'No disponible',
                ]);
            }

            responder(200, $detalle);
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
} catch (Throwable $e) {
    error_log('AWR FacturasEmitidasController: ' . $e->getMessage());
    responder(500, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function listarFacturasEmitidas(int $idUsuario, array $entrada): array
{
    $idSucursal = sucursalAutorizada($idUsuario);
    $busqueda = textoOpcional($entrada, 'busqueda', 80);
    $estado = estadoFactura($entrada);
    $fechas = rangoFechas($entrada);
    $pagina = paginaSolicitada($entrada);
    $porPagina = 20;
    $consulta = consultaFacturas($idSucursal, $busqueda, $estado, $fechas);
    $total = (int) (clone $consulta)->count();
    $offset = ($pagina - 1) * $porPagina;

    if (!is_int($offset) || $offset < 0) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Página no válida.',
        ]);
    }

    $filas = $consulta
        ->select([
            'f.id_factura',
            'f.numero_comprobante',
            'f.fecha_emision',
            'f.identificacion_cliente',
            'f.razon_social_cliente',
            'f.total',
            'f.moneda',
            'f.estado',
        ])
        ->selectRaw(
            "COALESCE((
                SELECT CASE
                    WHEN COUNT(DISTINCT p.id_metodo_pago) > 1 THEN 'Múltiple'
                    WHEN COUNT(DISTINCT p.id_metodo_pago) = 1 THEN MIN(mp.nombre)
                    ELSE NULL
                END
                FROM pago p
                LEFT JOIN metodo_pago mp ON mp.id_metodo_pago = p.id_metodo_pago
                WHERE p.id_factura = f.id_factura
            ), 'Pendiente') AS metodo_pago"
        )
        ->orderByDesc('f.fecha_emision')
        ->orderByDesc('f.id_factura')
        ->offset($offset)
        ->limit($porPagina)
        ->get();

    $lista = [];

    foreach ($filas as $fila) {
        $lista[] = [
            'id_factura' => (int) $fila->id_factura,
            'numero_comprobante' => (string) $fila->numero_comprobante,
            'fecha_emision' => fechaLocal((string) $fila->fecha_emision),
            'identificacion_cliente' => (string) $fila->identificacion_cliente,
            'razon_social_cliente' => (string) $fila->razon_social_cliente,
            'total' => dinero($fila->total),
            'moneda' => (string) $fila->moneda,
            'estado' => (string) $fila->estado,
            'metodo_pago' => (string) $fila->metodo_pago,
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

function obtenerDetalleFacturaEmitida(int $idUsuario, array $entrada): ?array
{
    $idSucursal = sucursalAutorizada($idUsuario);
    $idFactura = enteroPositivo($entrada['id_factura'] ?? null);

    if ($idFactura === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $factura = DB::table('factura as f')
        ->leftJoin('usuario as u', 'u.id_usuario', '=', 'f.id_usuario')
        ->leftJoin('punto_emision as pe', 'pe.id_punto_emision', '=', 'f.id_punto_emision')
        ->where('f.id_factura', $idFactura)
        ->where('f.id_sucursal', $idSucursal)
        ->select([
            'f.numero_comprobante',
            'f.fecha_emision',
            'f.estado',
            'f.tipo_identificacion_cliente',
            'f.identificacion_cliente',
            'f.razon_social_cliente',
            'f.direccion_cliente',
            'f.razon_social_empresa',
            'f.nombre_comercial_empresa',
            'f.nombre_sucursal',
            'f.codigo_establecimiento',
            'f.direccion_sucursal',
            'f.codigo_punto_emision',
            'f.subtotal',
            'f.descuento',
            'f.valor_impuesto',
            'f.total',
            'f.moneda',
            'f.observacion',
            'u.nombres',
            'u.apellidos',
            'pe.descripcion as descripcion_punto_emision',
        ])
        ->first();

    if ($factura === null) {
        return null;
    }

    $lineas = DB::table('factura_detalle')
        ->where('id_factura', $idFactura)
        ->orderBy('id_factura_detalle')
        ->get([
            'codigo_producto',
            'nombre_producto',
            'cantidad',
            'precio_unitario',
            'nombre_impuesto',
            'porcentaje_impuesto',
            'valor_impuesto',
            'subtotal',
            'total_linea',
            'descuento',
        ]);
    $pagos = DB::table('pago as p')
        ->leftJoin('metodo_pago as mp', 'mp.id_metodo_pago', '=', 'p.id_metodo_pago')
        ->where('p.id_factura', $idFactura)
        ->orderBy('p.id_pago')
        ->get(['mp.nombre', 'p.monto', 'p.referencia']);

    $cabecera = [
        'numero_comprobante' => (string) $factura->numero_comprobante,
        'fecha_emision' => fechaLocal((string) $factura->fecha_emision),
        'estado' => (string) $factura->estado,
        'tipo_identificacion_cliente' => (string) $factura->tipo_identificacion_cliente,
        'identificacion_cliente' => (string) $factura->identificacion_cliente,
        'razon_social_cliente' => (string) $factura->razon_social_cliente,
        'razon_social_empresa' => (string) $factura->razon_social_empresa,
        'nombre_sucursal' => (string) $factura->nombre_sucursal,
        'codigo_establecimiento' => (string) $factura->codigo_establecimiento,
        'codigo_punto_emision' => (string) $factura->codigo_punto_emision,
        'subtotal' => dinero($factura->subtotal),
        'descuento' => dinero($factura->descuento),
        'valor_impuesto' => dinero($factura->valor_impuesto),
        'total' => dinero($factura->total),
        'moneda' => (string) $factura->moneda,
    ];
    agregarSiTieneValor($cabecera, 'direccion_cliente', $factura->direccion_cliente);
    agregarSiTieneValor($cabecera, 'nombre_comercial_empresa', $factura->nombre_comercial_empresa);
    agregarSiTieneValor($cabecera, 'direccion_sucursal', $factura->direccion_sucursal);
    agregarSiTieneValor($cabecera, 'observacion', $factura->observacion);
    // La descripción sale del catálogo actual. El código oficial es el snapshot de la factura.
    agregarSiTieneValor($cabecera, 'descripcion_punto_emision', $factura->descripcion_punto_emision);

    $detalleLineas = [];

    foreach ($lineas as $linea) {
        $detalleLineas[] = [
            'codigo_producto' => (string) $linea->codigo_producto,
            'nombre_producto' => (string) $linea->nombre_producto,
            'cantidad' => (int) $linea->cantidad,
            'precio_unitario' => dinero($linea->precio_unitario),
            'nombre_impuesto' => (string) $linea->nombre_impuesto,
            'porcentaje_impuesto' => dinero($linea->porcentaje_impuesto),
            'valor_impuesto' => dinero($linea->valor_impuesto),
            'subtotal' => dinero($linea->subtotal),
            'total_linea' => dinero($linea->total_linea),
            'descuento' => dinero($linea->descuento),
        ];
    }

    $detallePagos = [];

    foreach ($pagos as $pago) {
        $fila = [
            'nombre' => $pago->nombre === null ? '' : (string) $pago->nombre,
            'monto' => dinero($pago->monto),
        ];
        agregarSiTieneValor($fila, 'referencia', $pago->referencia);
        $detallePagos[] = $fila;
    }

    return [
        'exito' => true,
        'factura' => $cabecera,
        // nombres y apellidos son el nombre actual del usuario, no un snapshot histórico.
        'emisor' => [
            'nombres' => $factura->nombres === null ? '' : (string) $factura->nombres,
            'apellidos' => $factura->apellidos === null ? '' : (string) $factura->apellidos,
        ],
        'lineas' => $detalleLineas,
        'pagos' => $detallePagos,
    ];
}

function consultaFacturas(int $idSucursal, string $busqueda, string $estado, ?array $fechas): Builder
{
    $consulta = DB::table('factura as f')->where('f.id_sucursal', $idSucursal);

    if ($busqueda !== '') {
        $como = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $busqueda) . '%';
        $consulta->where(function ($grupo) use ($como): void {
            $grupo->whereRaw("f.numero_comprobante ILIKE ? ESCAPE '|'", [$como])
                ->orWhereRaw("f.identificacion_cliente ILIKE ? ESCAPE '|'", [$como])
                ->orWhereRaw("f.razon_social_cliente ILIKE ? ESCAPE '|'", [$como]);
        });
    }

    if ($estado !== '') {
        $consulta->where('f.estado', $estado);
    }

    if ($fechas !== null) {
        if ($fechas['desde'] !== null) {
            $consulta->where('f.fecha_emision', '>=', $fechas['desde']);
        }

        if ($fechas['hasta'] !== null) {
            $consulta->where('f.fecha_emision', '<', $fechas['hasta']);
        }
    }

    return $consulta;
}

function sucursalAutorizada(int $idUsuario): int
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

    return (int) $sucursales[0]->id_sucursal;
}

function operadorEnSesion(): int
{
    if (!isset($_COOKIE[session_name()])) {
        return 0;
    }

    awr_iniciar_sesion();
    $rol = $_SESSION['rol'] ?? null;
    $id = $_SESSION['id_usuario'] ?? null;

    if ($rol === 'Operador' && is_numeric($id) && (int) $id > 0 && !is_float($id)) {
        return (int) $id;
    }

    return 0;
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

function estadoFactura(array $entrada): string
{
    if (!array_key_exists('estado', $entrada) || $entrada['estado'] === null || $entrada['estado'] === '' || $entrada['estado'] === 'TODAS') {
        return '';
    }

    if ($entrada['estado'] === 'EMITIDA' || $entrada['estado'] === 'ANULADA') {
        return $entrada['estado'];
    }

    responder(400, [
        'exito' => false,
        'mensaje' => 'Estado no válido.',
    ]);
}

function rangoFechas(array $entrada): ?array
{
    $desde = textoOpcional($entrada, 'fecha_desde', 10);
    $hasta = textoOpcional($entrada, 'fecha_hasta', 10);

    if ($desde === '' && $hasta === '') {
        return null;
    }

    $zona = new DateTimeZone('America/Guayaquil');
    $inicio = $desde === '' ? null : fechaCalendario($desde, $zona);
    $fin = $hasta === '' ? null : fechaCalendario($hasta, $zona);

    if ($inicio !== null && $fin !== null && $inicio > $fin) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'El rango de fechas no es válido.',
        ]);
    }

    return [
        'desde' => $inicio?->format('Y-m-d H:i:sP'),
        'hasta' => $fin?->modify('+1 day')->format('Y-m-d H:i:sP'),
    ];
}

function fechaCalendario(string $texto, DateTimeZone $zona): DateTimeImmutable
{
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto, $zona);
    $errores = DateTimeImmutable::getLastErrors();

    if (!is_array($errores)) {
        $errores = ['warning_count' => 0, 'error_count' => 0];
    }

    if (!$fecha || $errores['warning_count'] > 0 || $errores['error_count'] > 0) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Fecha no válida.',
        ]);
    }

    return $fecha;
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

function agregarSiTieneValor(array &$destino, string $clave, mixed $valor): void
{
    if ($valor === null) {
        return;
    }

    $texto = trim((string) $valor);

    if ($texto !== '') {
        $destino[$clave] = $texto;
    }
}

function fechaLocal(string $valor): string
{
    $fecha = new DateTimeImmutable($valor);

    return $fecha->setTimezone(new DateTimeZone('America/Guayaquil'))->format('Y-m-d\TH:i:sP');
}

function dinero(mixed $valor): string
{
    return number_format(round((float) $valor, 2, PHP_ROUND_HALF_UP), 2, '.', '');
}

function responder(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
