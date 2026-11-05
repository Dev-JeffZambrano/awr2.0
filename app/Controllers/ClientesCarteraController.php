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
if (!sesionAdministrativa()) {
    responder(403, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

try {
    switch ($entrada['op'] ?? null) {
        case 'obtenerResumenClientes':
            responder(200, obtenerResumenClientes($entrada));
            break;
        case 'listarClientesComerciales':
            responder(200, listarClientesComerciales($entrada));
            break;
        case 'obtenerDetalleCliente':
            $detalle = obtenerDetalleCliente($entrada);

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
    error_log('AWR ClientesCarteraController: ' . $e->getMessage());
    responder(500, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function obtenerResumenClientes(array $entrada): array
{
    $rango = resolverPeriodo($entrada);
    $idSucursal = resolverSucursal($entrada);
    $params = parametrosFacturas($rango, $idSucursal);
    $filtroSucursal = $idSucursal === null ? '' : ' AND f.id_sucursal = ?';
    $compras = DB::selectOne(
        "WITH compras AS (
            SELECT f.id_cliente,
                   COUNT(*)::int AS facturas,
                   SUM(f.total) AS total_comprado,
                   MAX(f.fecha_emision) AS ultima_compra
            FROM factura f
            JOIN cliente c ON c.id_cliente = f.id_cliente
            WHERE f.estado = 'EMITIDA'
              AND f.fecha_emision >= ?
              AND f.fecha_emision < ?
              {$filtroSucursal}
              AND c.tipo_identificacion <> 'CONSUMIDOR_FINAL'
            GROUP BY f.id_cliente
        )
        SELECT COUNT(*)::int AS clientes_con_compras,
               COUNT(*) FILTER (WHERE facturas >= 2)::int AS clientes_recurrentes,
               COALESCE(SUM(total_comprado), 0) AS ventas_identificadas
        FROM compras",
        $params
    );
    $top = DB::selectOne(
        "SELECT c.id_cliente, c.razon_social, c.identificacion,
                SUM(f.total) AS total_comprado
         FROM factura f
         JOIN cliente c ON c.id_cliente = f.id_cliente
         WHERE f.estado = 'EMITIDA'
           AND f.fecha_emision >= ?
           AND f.fecha_emision < ?
           {$filtroSucursal}
           AND c.tipo_identificacion <> 'CONSUMIDOR_FINAL'
         GROUP BY c.id_cliente, c.razon_social, c.identificacion
         ORDER BY total_comprado DESC, c.razon_social, c.id_cliente
         LIMIT 1",
        $params
    );
    $consumidorFinal = DB::selectOne(
        "SELECT COUNT(*) FILTER (
                    WHERE c.tipo_identificacion = 'CONSUMIDOR_FINAL'
                )::int AS facturas_consumidor_final,
                COALESCE(SUM(f.total) FILTER (
                    WHERE c.tipo_identificacion = 'CONSUMIDOR_FINAL'
                ), 0) AS ventas_consumidor_final,
                COALESCE(SUM(f.total), 0) AS ventas_totales
         FROM factura f
         JOIN cliente c ON c.id_cliente = f.id_cliente
         WHERE f.estado = 'EMITIDA'
           AND f.fecha_emision >= ?
           AND f.fecha_emision < ?
           {$filtroSucursal}",
        $params
    );
    $registrados = (int) DB::table('cliente')
        ->where('tipo_identificacion', '<>', 'CONSUMIDOR_FINAL')
        ->count();
    $compradores = (int) $compras->clientes_con_compras;
    $ventasIdentificadas = (float) $compras->ventas_identificadas;
    $ventasFinal = (float) $consumidorFinal->ventas_consumidor_final;
    $ventasTotales = (float) $consumidorFinal->ventas_totales;
    $sucursales = DB::table('sucursal')
        ->whereRaw('estado IS TRUE')
        ->orderBy('nombre')
        ->orderBy('id_sucursal')
        ->get(['id_sucursal', 'nombre']);

    return [
        'exito' => true,
        'periodo' => respuestaPeriodo($rango),
        'id_sucursal' => $idSucursal,
        'sucursales' => array_map(static fn ($fila): array => [
            'id_sucursal' => (int) $fila->id_sucursal,
            'nombre' => (string) $fila->nombre,
        ], $sucursales->all()),
        'kpis' => [
            'clientes_con_compras' => $compradores,
            'clientes_recurrentes' => (int) $compras->clientes_recurrentes,
            'venta_promedio_por_cliente' => monto(
                $compradores > 0 ? $ventasIdentificadas / $compradores : 0
            ),
            'cliente_mayor_facturacion' => $top === null ? null : [
                'id_cliente' => (int) $top->id_cliente,
                'razon_social' => (string) $top->razon_social,
                'identificacion' => (string) $top->identificacion,
                'total_comprado' => monto($top->total_comprado),
            ],
        ],
        'informacion_secundaria' => [
            'clientes_identificados_registrados' => $registrados,
            'clientes_sin_compras_periodo' => max(0, $registrados - $compradores),
        ],
        'consumidor_final' => [
            'facturas_consumidor_final' => (int) $consumidorFinal->facturas_consumidor_final,
            'ventas_consumidor_final' => monto($ventasFinal),
            'participacion_consumidor_final' => monto(
                $ventasTotales > 0 ? ($ventasFinal / $ventasTotales) * 100 : 0
            ),
        ],
    ];
}

function listarClientesComerciales(array $entrada): array
{
    $rango = resolverPeriodo($entrada);
    $idSucursal = resolverSucursal($entrada);
    $busqueda = textoOpcional($entrada, 'busqueda', 200);
    $pagina = paginaSolicitada($entrada);
    $porPagina = 20;
    $compras = consultaComprasAgrupadas($rango, $idSucursal);
    $consulta = DB::table('cliente as c')
        ->leftJoinSub($compras, 'compras', 'compras.id_cliente', '=', 'c.id_cliente')
        ->where('c.tipo_identificacion', '<>', 'CONSUMIDOR_FINAL');

    if ($busqueda !== '') {
        $como = patron($busqueda);
        $consulta->where(function ($grupo) use ($como): void {
            $grupo->whereRaw("c.identificacion ILIKE ? ESCAPE '|'", [$como])
                ->orWhereRaw("c.razon_social ILIKE ? ESCAPE '|'", [$como]);
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
        ->select([
            'c.id_cliente',
            'c.razon_social',
            'c.identificacion',
            'c.tipo_identificacion',
            'c.estado',
            'compras.facturas_periodo',
            'compras.total_comprado',
            'compras.ultima_compra',
        ])
        ->orderByRaw('COALESCE(compras.total_comprado, 0) DESC')
        ->orderByRaw('compras.ultima_compra DESC NULLS LAST')
        ->orderBy('c.razon_social')
        ->orderBy('c.id_cliente')
        ->offset($offset)
        ->limit($porPagina)
        ->get();
    $lista = [];

    foreach ($filas as $fila) {
        $facturas = (int) ($fila->facturas_periodo ?? 0);
        $totalComprado = (float) ($fila->total_comprado ?? 0);
        $lista[] = [
            'id_cliente' => (int) $fila->id_cliente,
            'razon_social' => (string) $fila->razon_social,
            'identificacion' => (string) $fila->identificacion,
            'tipo_identificacion' => (string) $fila->tipo_identificacion,
            'facturas_periodo' => $facturas,
            'total_comprado' => monto($totalComprado),
            'ticket_promedio' => monto($facturas > 0 ? $totalComprado / $facturas : 0),
            'ultima_compra' => $fila->ultima_compra === null
                ? null
                : fechaLocal((string) $fila->ultima_compra),
            'estado' => esVerdadero($fila->estado),
        ];
    }

    return [
        'exito' => true,
        'periodo' => respuestaPeriodo($rango),
        'id_sucursal' => $idSucursal,
        'pagina_actual' => $pagina,
        'por_pagina' => $porPagina,
        'total_registros' => $total,
        'total_paginas' => $total === 0 ? 0 : (int) ceil($total / $porPagina),
        'filas' => $lista,
    ];
}

function obtenerDetalleCliente(array $entrada): ?array
{
    $rango = resolverPeriodo($entrada);
    $idSucursal = resolverSucursal($entrada);
    $idCliente = enteroPositivo($entrada['id_cliente'] ?? null);

    if ($idCliente === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $cliente = DB::table('cliente')
        ->where('id_cliente', $idCliente)
        ->where('tipo_identificacion', '<>', 'CONSUMIDOR_FINAL')
        ->first([
            'id_cliente',
            'tipo_identificacion',
            'identificacion',
            'razon_social',
            'direccion',
            'telefono',
            'correo',
            'estado',
        ]);

    if ($cliente === null) {
        return null;
    }

    $resumenConsulta = consultaFacturasValidas($rango, $idSucursal)
        ->where('f.id_cliente', $idCliente);
    $resumen = (clone $resumenConsulta)
        ->selectRaw('COUNT(*)::int AS facturas_periodo')
        ->selectRaw('COALESCE(SUM(f.total), 0) AS total_comprado')
        ->selectRaw('MAX(f.fecha_emision) AS ultima_compra')
        ->first();
    $facturas = $resumenConsulta
        ->select([
            'f.id_factura',
            'f.numero_comprobante',
            'f.fecha_emision',
            'f.nombre_sucursal',
            'f.total',
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
        ->limit(10)
        ->get();
    $cantidadFacturas = (int) $resumen->facturas_periodo;
    $totalComprado = (float) $resumen->total_comprado;
    $listaFacturas = [];

    foreach ($facturas as $factura) {
        $listaFacturas[] = [
            'numero_comprobante' => (string) $factura->numero_comprobante,
            'fecha_emision' => fechaLocal((string) $factura->fecha_emision),
            'sucursal' => (string) $factura->nombre_sucursal,
            'total' => monto($factura->total),
            'metodo_pago' => (string) $factura->metodo_pago,
        ];
    }

    return [
        'exito' => true,
        'periodo' => respuestaPeriodo($rango),
        'id_sucursal' => $idSucursal,
        'cliente' => [
            'id_cliente' => (int) $cliente->id_cliente,
            'tipo_identificacion' => (string) $cliente->tipo_identificacion,
            'identificacion' => (string) $cliente->identificacion,
            'razon_social' => (string) $cliente->razon_social,
            'direccion' => $cliente->direccion === null ? null : (string) $cliente->direccion,
            'telefono' => $cliente->telefono === null ? null : (string) $cliente->telefono,
            'correo' => $cliente->correo === null ? null : (string) $cliente->correo,
            'estado' => esVerdadero($cliente->estado),
        ],
        'resumen' => [
            'facturas_periodo' => $cantidadFacturas,
            'total_comprado' => monto($totalComprado),
            'ticket_promedio' => monto(
                $cantidadFacturas > 0 ? $totalComprado / $cantidadFacturas : 0
            ),
            'ultima_compra' => $resumen->ultima_compra === null
                ? null
                : fechaLocal((string) $resumen->ultima_compra),
        ],
        'facturas' => $listaFacturas,
    ];
}

function consultaComprasAgrupadas(array $rango, ?int $idSucursal): Builder
{
    return consultaFacturasValidas($rango, $idSucursal)
        ->groupBy('f.id_cliente')
        ->select('f.id_cliente')
        ->selectRaw('COUNT(*)::int AS facturas_periodo')
        ->selectRaw('SUM(f.total) AS total_comprado')
        ->selectRaw('MAX(f.fecha_emision) AS ultima_compra');
}

function consultaFacturasValidas(array $rango, ?int $idSucursal): Builder
{
    $consulta = DB::table('factura as f')
        ->where('f.estado', 'EMITIDA')
        ->where('f.fecha_emision', '>=', marcaTiempo($rango['inicio']))
        ->where('f.fecha_emision', '<', marcaTiempo($rango['fin_exclusivo']));

    if ($idSucursal !== null) {
        $consulta->where('f.id_sucursal', $idSucursal);
    }

    return $consulta;
}

function parametrosFacturas(array $rango, ?int $idSucursal): array
{
    $params = [
        marcaTiempo($rango['inicio']),
        marcaTiempo($rango['fin_exclusivo']),
    ];

    if ($idSucursal !== null) {
        $params[] = $idSucursal;
    }

    return $params;
}

function resolverPeriodo(array $entrada): array
{
    $periodo = $entrada['periodo'] ?? 'ULTIMOS_30_DIAS';

    if (!is_string($periodo)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Período no válido.',
        ]);
    }

    $zona = new DateTimeZone('America/Guayaquil');
    $hoy = new DateTimeImmutable('today', $zona);

    switch ($periodo) {
        case 'ULTIMOS_7_DIAS':
            $inicio = $hoy->modify('-6 days');
            $finExclusivo = $hoy->modify('+1 day');
            break;
        case 'ULTIMOS_30_DIAS':
            $inicio = $hoy->modify('-29 days');
            $finExclusivo = $hoy->modify('+1 day');
            break;
        case 'MES_ACTUAL':
            $inicio = $hoy->modify('first day of this month');
            $finExclusivo = $inicio->modify('+1 month');
            break;
        case 'MES_ANTERIOR':
            $finExclusivo = $hoy->modify('first day of this month');
            $inicio = $finExclusivo->modify('-1 month');
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Período no válido.',
            ]);
    }

    return [
        'codigo' => $periodo,
        'inicio' => $inicio,
        'fin_exclusivo' => $finExclusivo,
    ];
}

function resolverSucursal(array $entrada): ?int
{
    if (!array_key_exists('id_sucursal', $entrada) || $entrada['id_sucursal'] === null || $entrada['id_sucursal'] === '') {
        return null;
    }

    $idSucursal = enteroPositivo($entrada['id_sucursal']);

    if ($idSucursal === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Sucursal no válida.',
        ]);
    }

    $activa = DB::table('sucursal')
        ->where('id_sucursal', $idSucursal)
        ->whereRaw('estado IS TRUE')
        ->exists();

    if (!$activa) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Sucursal no válida.',
        ]);
    }

    return $idSucursal;
}

function sesionAdministrativa(): bool
{
    if (!isset($_COOKIE[session_name()])) {
        return false;
    }

    awr_iniciar_sesion();
    $id = $_SESSION['id_usuario'] ?? null;

    return ($_SESSION['rol'] ?? null) === 'Administrativo'
        && is_numeric($id)
        && (int) $id > 0
        && !is_float($id);
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

function respuestaPeriodo(array $rango): array
{
    return [
        'codigo' => $rango['codigo'],
        'desde' => $rango['inicio']->format('Y-m-d'),
        'hasta' => $rango['fin_exclusivo']->modify('-1 day')->format('Y-m-d'),
    ];
}

function patron(string $texto): string
{
    return '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $texto) . '%';
}

function marcaTiempo(DateTimeImmutable $fecha): string
{
    return $fecha->format('Y-m-d H:i:sP');
}

function fechaLocal(string $valor): string
{
    return (new DateTimeImmutable($valor))
        ->setTimezone(new DateTimeZone('America/Guayaquil'))
        ->format('Y-m-d\TH:i:sP');
}

function monto(mixed $valor): float
{
    return round((float) $valor, 2, PHP_ROUND_HALF_UP);
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
