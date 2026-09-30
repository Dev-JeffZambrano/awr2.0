<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

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
    case 'obtenerResumenAdministrativo':
        obtenerResumenAdministrativo($entrada);
        break;
    default:
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación no reconocida.',
        ]);
}

function obtenerResumenAdministrativo(array $entrada): void
{
    $sesionValida = false;

    if (isset($_COOKIE[session_name()])) {
        session_start();
        $sesionValida = isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
            && $_SESSION['rol'] === 'Administrativo';
    }

    if (!$sesionValida) {
        responder(403, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    try {
        $zona = new DateTimeZone('America/Guayaquil');
        $rango = resolverRango($entrada, $zona);
        $idSucursal = resolverSucursal($entrada);
        $anterior = rangoAnterior($rango['inicio'], $rango['finExclusivo']);

        $actual = resumirPeriodo($rango['inicio'], $rango['finExclusivo'], $idSucursal);
        $previo = resumirPeriodo($anterior['inicio'], $anterior['finExclusivo'], $idSucursal);
        $serie = serieDiaria($rango['inicio'], $rango['finExclusivo'], $idSucursal, $zona);
        $sucursalesVenta = ventasPorSucursal($rango['inicio'], $rango['finExclusivo'], $idSucursal);
        $categorias = ventasPorCategoria($rango['inicio'], $rango['finExclusivo'], $idSucursal);
        $pagos = ventasPorMetodo($rango['inicio'], $rango['finExclusivo'], $idSucursal);
        $productos = topProductos($rango['inicio'], $rango['finExclusivo'], $idSucursal);
        $stock = stockCritico($idSucursal);
        $sucursales = DB::select(
            'SELECT id_sucursal, nombre FROM sucursal WHERE estado IS TRUE ORDER BY nombre'
        );

        $dias = count($serie);
        $mejor = null;

        foreach ($serie as $punto) {
            if ($mejor === null || $punto['total'] > $mejor['total']) {
                $mejor = $punto;
            }
        }

        $hayVentas = $actual['facturas'] > 0;
        $base = $actual['base'];
        $margen = $base > 0
            ? number_format(($actual['ganancia'] / $base) * 100, 2, ',', '.') . '%'
            : 'Pendiente';
        $ticket = $actual['facturas'] > 0
            ? 'Ticket promedio ' . dinero($actual['ventas'] / $actual['facturas'])
            : 'Pendiente';
        $promedio = $dias > 0 ? dinero($actual['ventas'] / $dias) : 'Pendiente';

        responder(200, [
            'exito' => true,
            'periodo' => [
                'desde' => $rango['desde'],
                'hasta' => $rango['hasta'],
                'etiqueta' => etiquetaFecha($rango['inicio'], $zona) . ' – ' . etiquetaFecha($rango['finInclusivo'], $zona),
            ],
            'sucursales' => array_map(static function ($fila): array {
                return [
                    'id' => (int) $fila->id_sucursal,
                    'nombre' => (string) $fila->nombre,
                ];
            }, $sucursales),
            'ventas' => [
                'total' => dinero($actual['ventas']),
                'crecimiento' => crecimiento($actual['ventas'], $previo['ventas']),
                'ayuda' => $hayVentas ? 'Total de facturas emitidas' : 'Sin datos para el período seleccionado',
            ],
            'facturas' => [
                'total' => number_format($actual['facturas'], 0, ',', '.'),
                'crecimiento' => crecimiento((float) $actual['facturas'], (float) $previo['facturas']),
                'ayuda' => $hayVentas ? 'Emitidas en el período' : 'Sin datos para el período seleccionado',
            ],
            'ganancia' => [
                'total' => dinero($actual['ganancia']),
                'crecimiento' => crecimiento($actual['ganancia'], $previo['ganancia']),
                'ayuda' => $margen === 'Pendiente' ? 'Pendiente' : 'Margen bruto ' . $margen,
            ],
            'productosVendidos' => [
                'total' => number_format($actual['unidades'], 0, ',', '.'),
                'crecimiento' => crecimiento((float) $actual['unidades'], (float) $previo['unidades']),
                'ayuda' => $ticket,
            ],
            'serie' => $serie,
            'promedioDia' => $hayVentas ? $promedio : 'Pendiente',
            'diaMayor' => $hayVentas && $mejor !== null
                ? $mejor['etiqueta'] . ' · ' . number_format($mejor['facturas'], 0, ',', '.') . ' facturas'
                : 'Sin datos para el período seleccionado',
            'consistencia' => 'Pendiente',
            'marca' => $hayVentas && $mejor !== null ? dinero($mejor['total']) : '',
            'sucursalesVenta' => $sucursalesVenta,
            'categorias' => $categorias,
            'pagos' => $pagos,
            'topProductos' => $productos,
            'stock' => $stock,
            'exportacion' => 'Pendiente',
            'analitica' => 'Pendiente de integración',
        ]);
    } catch (Throwable $e) {
        error_log('AWR obtenerResumenAdministrativo: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function resolverRango(array $entrada, DateTimeZone $zona): array
{
    $hoy = new DateTimeImmutable('today', $zona);
    $desdeTexto = $entrada['fecha_desde'] ?? null;
    $hastaTexto = $entrada['fecha_hasta'] ?? null;

    if (is_string($desdeTexto) && is_string($hastaTexto) && $desdeTexto !== '' && $hastaTexto !== '') {
        $inicio = fechaValida($desdeTexto, $zona);
        $finInclusivo = fechaValida($hastaTexto, $zona);
    } else {
        $periodo = is_string($entrada['periodo'] ?? null) ? $entrada['periodo'] : '30';

        if ($periodo === '7') {
            $inicio = $hoy->modify('-6 days');
            $finInclusivo = $hoy;
        } elseif ($periodo === 'mes') {
            $inicio = $hoy->modify('first day of this month');
            $finInclusivo = $hoy;
        } elseif ($periodo === 'mes-anterior') {
            $inicio = $hoy->modify('first day of last month');
            $finInclusivo = $hoy->modify('last day of last month');
        } elseif ($periodo === '30') {
            $inicio = $hoy->modify('-29 days');
            $finInclusivo = $hoy;
        } else {
            responder(400, [
                'exito' => false,
                'mensaje' => 'Datos incompletos.',
            ]);
        }
    }

    if ($inicio > $finInclusivo) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $dias = (int) $inicio->diff($finInclusivo)->days + 1;

    if ($dias > 366) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    return [
        'desde' => $inicio->format('Y-m-d'),
        'hasta' => $finInclusivo->format('Y-m-d'),
        'inicio' => $inicio,
        'finInclusivo' => $finInclusivo,
        'finExclusivo' => $finInclusivo->modify('+1 day'),
    ];
}

function fechaValida(string $texto, DateTimeZone $zona): DateTimeImmutable
{
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto, $zona);
    $errores = DateTimeImmutable::getLastErrors();

    if (!is_array($errores)) {
        $errores = ['warning_count' => 0, 'error_count' => 0];
    }

    if (!$fecha || $errores['warning_count'] > 0 || $errores['error_count'] > 0) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    return $fecha;
}

function resolverSucursal(array $entrada): ?int
{
    if (!array_key_exists('id_sucursal', $entrada) || $entrada['id_sucursal'] === null || $entrada['id_sucursal'] === '') {
        return null;
    }

    if (is_bool($entrada['id_sucursal']) || !is_numeric($entrada['id_sucursal']) || (int) $entrada['id_sucursal'] < 1) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $id = (int) $entrada['id_sucursal'];
    $existe = DB::table('sucursal')->where('id_sucursal', $id)->where('estado', true)->exists();

    if (!$existe) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    return $id;
}

function rangoAnterior(DateTimeImmutable $inicio, DateTimeImmutable $finExclusivo): array
{
    $dias = (int) $inicio->diff($finExclusivo)->days;

    return [
        'inicio' => $inicio->modify('-' . $dias . ' days'),
        'finExclusivo' => $inicio,
    ];
}

function resumirPeriodo(DateTimeImmutable $inicio, DateTimeImmutable $finExclusivo, ?int $idSucursal): array
{
    $filtro = filtroFactura($idSucursal);
    $params = array_merge(
        [marcaTiempo($inicio), marcaTiempo($finExclusivo)],
        $filtro['params']
    );

    $factura = DB::selectOne(
        'SELECT COALESCE(SUM(f.total), 0) AS ventas, COUNT(*)::int AS facturas
         FROM factura f
         WHERE ' . $filtro['sql'],
        $params
    );

    $detalle = DB::selectOne(
        'SELECT COALESCE(SUM(d.cantidad), 0) AS unidades,
                COALESCE(SUM(d.subtotal - d.descuento), 0) AS base,
                COALESCE(SUM(d.subtotal - d.descuento - (d.cantidad * d.costo_unitario)), 0) AS ganancia
         FROM factura_detalle d
         JOIN factura f ON f.id_factura = d.id_factura
         WHERE ' . $filtro['sql'],
        $params
    );

    return [
        'ventas' => (float) $factura->ventas,
        'facturas' => (int) $factura->facturas,
        'unidades' => (int) $detalle->unidades,
        'base' => (float) $detalle->base,
        'ganancia' => (float) $detalle->ganancia,
    ];
}

function serieDiaria(DateTimeImmutable $inicio, DateTimeImmutable $finExclusivo, ?int $idSucursal, DateTimeZone $zona): array
{
    $filtro = filtroFactura($idSucursal);
    $params = array_merge(
        [marcaTiempo($inicio), marcaTiempo($finExclusivo)],
        $filtro['params']
    );
    $filas = DB::select(
        "SELECT (f.fecha_emision AT TIME ZONE 'America/Guayaquil')::date AS dia,
                COALESCE(SUM(f.total), 0) AS total,
                COUNT(*)::int AS facturas
         FROM factura f
         WHERE " . $filtro['sql'] . '
         GROUP BY 1',
        $params
    );
    $mapa = [];

    foreach ($filas as $fila) {
        $mapa[substr((string) $fila->dia, 0, 10)] = $fila;
    }

    $serie = [];
    $cursor = $inicio;

    while ($cursor < $finExclusivo) {
        $clave = $cursor->format('Y-m-d');
        $fila = $mapa[$clave] ?? null;
        $serie[] = [
            'fecha' => $clave,
            'etiqueta' => etiquetaFecha($cursor, $zona),
            'total' => round((float) ($fila->total ?? 0), 2),
            'facturas' => (int) ($fila->facturas ?? 0),
        ];
        $cursor = $cursor->modify('+1 day');
    }

    return $serie;
}

function ventasPorSucursal(DateTimeImmutable $inicio, DateTimeImmutable $finExclusivo, ?int $idSucursal): array
{
    $filtro = filtroFactura($idSucursal);
    $params = array_merge(
        [marcaTiempo($inicio), marcaTiempo($finExclusivo)],
        $filtro['params']
    );
    $filas = DB::select(
        'SELECT s.nombre, COALESCE(SUM(f.total), 0) AS total
         FROM factura f
         JOIN sucursal s ON s.id_sucursal = f.id_sucursal
         WHERE ' . $filtro['sql'] . '
         GROUP BY s.id_sucursal, s.nombre
         ORDER BY total DESC, s.nombre',
        $params
    );

    return participar($filas, 'nombre');
}

function ventasPorCategoria(DateTimeImmutable $inicio, DateTimeImmutable $finExclusivo, ?int $idSucursal): array
{
    $filtro = filtroFactura($idSucursal);
    $params = array_merge(
        [marcaTiempo($inicio), marcaTiempo($finExclusivo)],
        $filtro['params']
    );
    $filas = DB::select(
        'SELECT d.nombre_categoria AS nombre, COALESCE(SUM(d.total_linea), 0) AS total
         FROM factura_detalle d
         JOIN factura f ON f.id_factura = d.id_factura
         WHERE ' . $filtro['sql'] . '
         GROUP BY d.nombre_categoria
         HAVING COALESCE(SUM(d.total_linea), 0) > 0
         ORDER BY total DESC, d.nombre_categoria',
        $params
    );

    return participar($filas, 'nombre');
}

function ventasPorMetodo(DateTimeImmutable $inicio, DateTimeImmutable $finExclusivo, ?int $idSucursal): array
{
    $filtro = filtroFactura($idSucursal);
    $params = array_merge(
        [marcaTiempo($inicio), marcaTiempo($finExclusivo)],
        $filtro['params']
    );
    $filas = DB::select(
        'SELECT mp.nombre, COALESCE(SUM(p.monto), 0) AS total
         FROM pago p
         JOIN metodo_pago mp ON mp.id_metodo_pago = p.id_metodo_pago
         JOIN factura f ON f.id_factura = p.id_factura
         WHERE ' . $filtro['sql'] . '
         GROUP BY mp.id_metodo_pago, mp.nombre
         HAVING COALESCE(SUM(p.monto), 0) > 0
         ORDER BY total DESC, mp.nombre',
        $params
    );

    return participar($filas, 'nombre');
}

function topProductos(DateTimeImmutable $inicio, DateTimeImmutable $finExclusivo, ?int $idSucursal): array
{
    $filtro = filtroFactura($idSucursal);
    $params = array_merge(
        [marcaTiempo($inicio), marcaTiempo($finExclusivo)],
        $filtro['params']
    );
    $filas = DB::select(
        'SELECT d.nombre_producto AS producto,
                d.nombre_categoria AS categoria,
                COALESCE(SUM(d.cantidad), 0) AS cantidad,
                COALESCE(SUM(d.total_linea), 0) AS ventas,
                COALESCE(SUM(d.subtotal - d.descuento), 0) AS base,
                COALESCE(SUM(d.cantidad * d.costo_unitario), 0) AS costo
         FROM factura_detalle d
         JOIN factura f ON f.id_factura = d.id_factura
         WHERE ' . $filtro['sql'] . '
         GROUP BY d.nombre_producto, d.nombre_categoria
         ORDER BY cantidad DESC, ventas DESC, d.nombre_producto
         LIMIT 5',
        $params
    );
    $lista = [];

    foreach ($filas as $fila) {
        $base = (float) $fila->base;
        $margen = $base > 0
            ? number_format(((($base - (float) $fila->costo) / $base) * 100), 1, ',', '.') . '%'
            : 'Pendiente';
        $lista[] = [
            'producto' => (string) $fila->producto,
            'categoria' => (string) $fila->categoria,
            'cantidad' => number_format((int) $fila->cantidad, 0, ',', '.') . ' unid.',
            'margen' => $margen,
            'ventas' => dinero((float) $fila->ventas),
        ];
    }

    return $lista;
}

function stockCritico(?int $idSucursal): array
{
    $params = [];
    $sucursal = '';

    if ($idSucursal !== null) {
        $sucursal = ' AND i.id_sucursal = ?';
        $params[] = $idSucursal;
    }

    $filas = DB::select(
        'SELECT p.nombre AS producto, s.nombre AS sucursal, i.stock_actual
         FROM inventario_sucursal i
         JOIN producto p ON p.id_producto = i.id_producto
         JOIN sucursal s ON s.id_sucursal = i.id_sucursal
         WHERE i.estado IS TRUE
           AND i.stock_actual <= i.stock_minimo' . $sucursal . '
         ORDER BY (i.stock_minimo - i.stock_actual) DESC, i.stock_actual ASC, p.nombre
         LIMIT 5',
        $params
    );
    $lista = [];

    foreach ($filas as $fila) {
        $lista[] = [
            'producto' => (string) $fila->producto,
            'sucursal' => (string) $fila->sucursal,
            'stock' => number_format((int) $fila->stock_actual, 0, ',', '.') . ' unid.',
        ];
    }

    return $lista;
}

function participar(array $filas, string $campo): array
{
    $suma = 0.0;

    foreach ($filas as $fila) {
        $suma += (float) $fila->total;
    }

    $maximo = 0.0;

    foreach ($filas as $fila) {
        $maximo = max($maximo, (float) $fila->total);
    }

    $lista = [];

    foreach ($filas as $indice => $fila) {
        $total = (float) $fila->total;
        $lista[] = [
            'puesto' => $indice + 1,
            'nombre' => (string) $fila->{$campo},
            'total' => dinero($total),
            'participacion' => $suma > 0
                ? number_format(($total / $suma) * 100, 1, ',', '.') . '%'
                : 'Pendiente',
            'ancho' => $maximo > 0 ? round(($total / $maximo) * 100, 1) : 0,
        ];
    }

    return $lista;
}

function filtroFactura(?int $idSucursal): array
{
    $sql = 'f.estado = \'EMITIDA\' AND f.fecha_anulacion IS NULL AND f.fecha_emision >= ? AND f.fecha_emision < ?';
    $params = [];

    if ($idSucursal !== null) {
        $sql .= ' AND f.id_sucursal = ?';
        $params[] = $idSucursal;
    }

    return [
        'sql' => $sql,
        'params' => $params,
    ];
}

function crecimiento(float $actual, float $anterior): string
{
    if ($anterior <= 0) {
        return 'Pendiente';
    }

    $valor = (($actual - $anterior) / $anterior) * 100;
    $signo = $valor > 0 ? '+' : '';

    return $signo . number_format($valor, 1, ',', '.') . '%';
}

function dinero(float $valor): string
{
    return '$ ' . number_format($valor, 2, ',', '.');
}

function etiquetaFecha(DateTimeImmutable $fecha, DateTimeZone $zona): string
{
    $local = $fecha->setTimezone($zona);
    $meses = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
        7 => 'jul', 8 => 'ago', 9 => 'sept', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    return $local->format('j') . ' ' . $meses[(int) $local->format('n')];
}

function marcaTiempo(DateTimeImmutable $fecha): string
{
    return $fecha->format('Y-m-d H:i:sP');
}

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
