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

switch ($entrada['op'] ?? null) {
    case 'obtenerResumenOperador':
        obtenerResumenOperador();
        break;
    default:
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación no reconocida.',
        ]);
}

function obtenerResumenOperador(): void
{
    $idUsuario = 0;

    if (isset($_COOKIE[session_name()])) {
        session_start();
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

    try {
        $sucursales = DB::select(
            'SELECT s.id_sucursal, s.nombre, s.ciudad
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

        $sucursal = $sucursales[0];
        $idSucursal = (int) $sucursal->id_sucursal;
        $zona = new DateTimeZone('America/Guayaquil');
        $inicio = new DateTimeImmutable('today', $zona);
        $fin = $inicio->modify('+1 day');
        $params = [$idSucursal, marcaTiempo($inicio), marcaTiempo($fin)];
        $donde = "f.estado = 'EMITIDA'
            AND f.fecha_anulacion IS NULL
            AND f.id_sucursal = ?
            AND f.fecha_emision >= ?
            AND f.fecha_emision < ?";

        $factura = DB::selectOne(
            'SELECT COALESCE(SUM(f.total), 0) AS ventas, COUNT(*)::int AS facturas
             FROM factura f
             WHERE ' . $donde,
            $params
        );
        $detalle = DB::selectOne(
            'SELECT COALESCE(SUM(d.cantidad), 0) AS unidades
             FROM factura_detalle d
             JOIN factura f ON f.id_factura = d.id_factura
             WHERE ' . $donde,
            $params
        );
        $cobros = DB::selectOne(
            "SELECT COALESCE(SUM(p.monto), 0) AS cobrado,
                    COALESCE(SUM(p.monto) FILTER (WHERE mp.codigo = 'EFECTIVO'), 0) AS efectivo
             FROM pago p
             JOIN metodo_pago mp ON mp.id_metodo_pago = p.id_metodo_pago
             JOIN factura f ON f.id_factura = p.id_factura
             WHERE " . $donde,
            $params
        );
        $metodoEfectivo = DB::select(
            "SELECT nombre FROM metodo_pago WHERE codigo = 'EFECTIVO' AND estado IS TRUE"
        );
        $horas = DB::select(
            "SELECT EXTRACT(HOUR FROM f.fecha_emision AT TIME ZONE 'America/Guayaquil')::int AS hora,
                    COALESCE(SUM(f.total), 0) AS total,
                    COUNT(*)::int AS facturas
             FROM factura f
             WHERE " . $donde . '
             GROUP BY 1
             ORDER BY 1',
            $params
        );
        $metodos = DB::select(
            'SELECT mp.nombre, COALESCE(SUM(p.monto), 0) AS total
             FROM pago p
             JOIN metodo_pago mp ON mp.id_metodo_pago = p.id_metodo_pago
             JOIN factura f ON f.id_factura = p.id_factura
             WHERE ' . $donde . '
             GROUP BY mp.id_metodo_pago, mp.nombre
             HAVING COALESCE(SUM(p.monto), 0) > 0
             ORDER BY total DESC, mp.nombre',
            $params
        );
        $recientes = DB::select(
            'SELECT f.numero_comprobante,
                    f.fecha_emision,
                    f.razon_social_cliente,
                    f.total,
                    f.estado,
                    COUNT(DISTINCT p.id_metodo_pago)::int AS metodos,
                    MIN(mp.nombre) AS metodo
             FROM factura f
             LEFT JOIN pago p ON p.id_factura = f.id_factura
             LEFT JOIN metodo_pago mp ON mp.id_metodo_pago = p.id_metodo_pago
             WHERE ' . $donde . '
             GROUP BY f.id_factura, f.numero_comprobante, f.fecha_emision, f.razon_social_cliente, f.total, f.estado
             ORDER BY f.fecha_emision DESC
             LIMIT 5',
            $params
        );
        $criticos = (int) DB::table('inventario_sucursal')
            ->where('id_sucursal', $idSucursal)
            ->where('estado', true)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->count();
        $stock = DB::select(
            'SELECT p.nombre AS producto, p.codigo, i.stock_actual, i.stock_minimo
             FROM inventario_sucursal i
             JOIN producto p ON p.id_producto = i.id_producto
             WHERE i.estado IS TRUE
               AND i.stock_actual <= i.stock_minimo
               AND i.id_sucursal = ?
             ORDER BY (i.stock_minimo - i.stock_actual) DESC, i.stock_actual ASC, p.nombre
             LIMIT 5',
            [$idSucursal]
        );
        $puntos = DB::select(
            'SELECT codigo, descripcion
             FROM punto_emision
             WHERE id_sucursal = ? AND estado IS TRUE
             ORDER BY id_punto_emision',
            [$idSucursal]
        );

        $ventas = (float) $factura->ventas;
        $cantidadFacturas = (int) $factura->facturas;
        $cobrado = (float) $cobros->cobrado;
        $efectivo = (float) $cobros->efectivo;
        $serie = [];
        $pico = null;
        $sumaHoras = 0.0;

        foreach ($horas as $hora) {
            $totalHora = round((float) $hora->total, 2);
            if ($totalHora <= 0) {
                continue;
            }
            $numero = (int) $hora->hora;
            $punto = [
                'hora' => $numero,
                'etiqueta' => sprintf('%02dh', $numero),
                'total' => $totalHora,
                'facturas' => (int) $hora->facturas,
            ];
            $serie[] = $punto;
            $sumaHoras += $totalHora;
            if ($pico === null || $totalHora > $pico['total']) {
                $pico = $punto;
            }
        }

        $facturasRecientes = [];

        foreach ($recientes as $fila) {
            $metodosFactura = (int) $fila->metodos;
            if ($metodosFactura > 1) {
                $pago = 'Múltiple';
            } elseif ($metodosFactura === 1) {
                $pago = (string) $fila->metodo;
            } else {
                $pago = 'Pendiente';
            }
            $emision = new DateTimeImmutable((string) $fila->fecha_emision);
            $facturasRecientes[] = [
                'comprobante' => (string) $fila->numero_comprobante,
                'hora' => $emision->setTimezone($zona)->format('H:i'),
                'cliente' => (string) $fila->razon_social_cliente,
                'pago' => $pago,
                'total' => dinero((float) $fila->total),
                'estado' => (string) $fila->estado,
            ];
        }

        $itemsStock = [];

        foreach ($stock as $fila) {
            $itemsStock[] = [
                'producto' => (string) $fila->producto,
                'codigo' => (string) $fila->codigo,
                'stock' => (int) $fila->stock_actual,
                'minimo' => (int) $fila->stock_minimo,
            ];
        }

        $punto = count($puntos) === 1
            ? (string) $puntos[0]->codigo . ' · ' . (string) $puntos[0]->descripcion
            : 'Pendiente';

        responder(200, [
            'exito' => true,
            'sucursal' => [
                'nombre' => (string) $sucursal->nombre,
                'ciudad' => (string) $sucursal->ciudad,
            ],
            'puntoEmision' => $punto,
            'ventasHoy' => dinero($ventas),
            'facturas' => [
                'total' => number_format($cantidadFacturas, 0, ',', '.'),
                'ticket' => 'Ticket promedio: ' . dinero($cantidadFacturas > 0 ? $ventas / $cantidadFacturas : 0),
            ],
            'unidades' => [
                'total' => number_format((int) $detalle->unidades, 0, ',', '.'),
                'nota' => 'En ' . number_format($cantidadFacturas, 0, ',', '.') . ' facturas',
            ],
            'efectivo' => [
                'nombre' => count($metodoEfectivo) === 1 ? (string) $metodoEfectivo[0]->nombre : 'Pendiente',
                'total' => count($metodoEfectivo) === 1 ? dinero($efectivo) : 'Pendiente',
                'porcentaje' => count($metodoEfectivo) === 1 && $cobrado > 0
                    ? number_format(($efectivo / $cobrado) * 100, 1, ',', '.') . '% del cobrado'
                    : 'Pendiente',
            ],
            'stockCritico' => number_format($criticos, 0, ',', '.'),
            'serie' => $serie,
            'pico' => $pico === null
                ? 'Sin ventas registradas hoy'
                : sprintf('%02d:00 - %02d:59', $pico['hora'], $pico['hora']),
            'picoMonto' => $pico === null ? '' : dinero($pico['total']),
            'promedioHorario' => count($serie) > 0
                ? dinero($sumaHoras / count($serie)) . ' / hora'
                : 'Sin ventas registradas hoy',
            'tickets' => number_format($cantidadFacturas, 0, ',', '.') . ' facturas',
            'pagos' => participar($metodos),
            'totalCobrado' => dinero($cobrado),
            'facturasRecientes' => $facturasRecientes,
            'stock' => $itemsStock,
            'sincronizacion' => 'Pendiente',
            'modoResiliente' => 'Pendiente de integración',
            'operacionesPendientes' => 'Pendiente',
        ]);
    } catch (Throwable $e) {
        error_log('AWR obtenerResumenOperador: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function participar(array $filas): array
{
    $suma = 0.0;

    foreach ($filas as $fila) {
        $suma += (float) $fila->total;
    }

    $lista = [];

    foreach ($filas as $fila) {
        $total = (float) $fila->total;
        $lista[] = [
            'nombre' => (string) $fila->nombre,
            'total' => dinero($total),
            'participacion' => $suma > 0
                ? number_format(($total / $suma) * 100, 1, ',', '.') . '%'
                : 'Pendiente',
        ];
    }

    return $lista;
}

function dinero(float $valor): string
{
    return '$ ' . number_format($valor, 2, ',', '.');
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
