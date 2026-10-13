<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

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
        case 'listarStockAgencia':
            responder(200, listarStockAgencia($idUsuario, $entrada));
            break;
        case 'obtenerMovimientosProducto':
            $historial = obtenerMovimientosProducto($idUsuario, $entrada);

            if ($historial === null) {
                responder(200, [
                    'exito' => false,
                    'mensaje' => 'No disponible',
                ]);
            }

            responder(200, $historial);
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
} catch (Throwable $e) {
    error_log('AWR StockAgenciaController: ' . $e->getMessage());
    responder(500, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function listarStockAgencia(int $idUsuario, array $entrada): array
{
    $idSucursal = sucursalAutorizada($idUsuario);
    $busqueda = textoOpcional($entrada, 'busqueda', 80);
    $idCategoria = categoriaSolicitada($entrada);
    $estadoStock = estadoStockSolicitado($entrada);
    $pagina = paginaSolicitada($entrada);
    $porPagina = 20;
    $consulta = consultaStock($idSucursal, $busqueda, $idCategoria, $estadoStock);
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
            'p.id_producto',
            'p.nombre',
            'p.codigo',
            'c.nombre as categoria',
            'inv.stock_actual',
            'inv.stock_minimo',
        ])
        ->orderByRaw('CASE WHEN inv.stock_actual <= inv.stock_minimo THEN 0 ELSE 1 END ASC')
        ->orderByRaw('(inv.stock_minimo - inv.stock_actual) DESC')
        ->orderBy('inv.stock_actual')
        ->orderBy('p.nombre')
        ->orderBy('p.id_producto')
        ->offset($offset)
        ->limit($porPagina)
        ->get();

    $kpis = DB::table('inventario_sucursal as inv')
        ->join('producto as p', 'p.id_producto', '=', 'inv.id_producto')
        ->where('inv.id_sucursal', $idSucursal)
        ->whereRaw('inv.estado IS TRUE')
        ->whereRaw('p.estado IS TRUE')
        ->selectRaw('COUNT(*)::int AS productos_inventariados')
        ->selectRaw('COUNT(*) FILTER (WHERE inv.stock_actual <= inv.stock_minimo)::int AS productos_criticos')
        ->selectRaw('COALESCE(SUM(inv.stock_actual), 0)::bigint AS unidades_disponibles')
        ->selectRaw('COUNT(*) FILTER (WHERE inv.stock_actual = 0)::int AS productos_sin_stock')
        ->first();

    $categorias = DB::table('categoria')
        ->whereRaw('estado IS TRUE')
        ->orderBy('nombre')
        ->orderBy('id_categoria')
        ->get(['id_categoria', 'nombre']);
    $lista = [];

    foreach ($filas as $fila) {
        $stockActual = (int) $fila->stock_actual;
        $stockMinimo = (int) $fila->stock_minimo;
        $lista[] = [
            'id_producto' => (int) $fila->id_producto,
            'nombre' => (string) $fila->nombre,
            'codigo' => (string) $fila->codigo,
            'categoria' => (string) $fila->categoria,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
            'estado_stock' => $stockActual <= $stockMinimo ? 'Crítico' : 'Normal',
        ];
    }

    $listaCategorias = [];

    foreach ($categorias as $categoria) {
        $listaCategorias[] = [
            'id_categoria' => (int) $categoria->id_categoria,
            'nombre' => (string) $categoria->nombre,
        ];
    }

    return [
        'exito' => true,
        'pagina_actual' => $pagina,
        'por_pagina' => $porPagina,
        'total_registros' => $total,
        'total_paginas' => $total === 0 ? 0 : (int) ceil($total / $porPagina),
        'filas' => $lista,
        'categorias' => $listaCategorias,
        'kpis' => [
            'productos_inventariados' => (int) ($kpis->productos_inventariados ?? 0),
            'productos_criticos' => (int) ($kpis->productos_criticos ?? 0),
            'unidades_disponibles' => (int) ($kpis->unidades_disponibles ?? 0),
            'productos_sin_stock' => (int) ($kpis->productos_sin_stock ?? 0),
        ],
    ];
}

function obtenerMovimientosProducto(int $idUsuario, array $entrada): ?array
{
    $idSucursal = sucursalAutorizada($idUsuario);
    $idProducto = enteroPositivo($entrada['id_producto'] ?? null);

    if ($idProducto === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $producto = DB::table('producto as p')
        ->join('inventario_sucursal as inv', 'inv.id_producto', '=', 'p.id_producto')
        ->where('p.id_producto', $idProducto)
        ->where('inv.id_sucursal', $idSucursal)
        ->whereRaw('p.estado IS TRUE')
        ->whereRaw('inv.estado IS TRUE')
        ->select(['p.nombre', 'p.codigo'])
        ->first();

    if ($producto === null) {
        return null;
    }

    $movimientos = DB::table('movimiento_inventario as m')
        ->leftJoin('factura as f', function ($join) use ($idSucursal): void {
            $join->on('f.id_factura', '=', 'm.id_factura')
                ->where('f.id_sucursal', '=', $idSucursal);
        })
        ->leftJoin('usuario as u', 'u.id_usuario', '=', 'm.id_usuario')
        ->where('m.id_sucursal', $idSucursal)
        ->where('m.id_producto', $idProducto)
        ->orderByDesc('m.fecha_movimiento')
        ->orderByDesc('m.id_movimiento')
        ->limit(20)
        ->get([
            'm.fecha_movimiento',
            'm.tipo_movimiento',
            'm.cantidad',
            'm.stock_anterior',
            'm.stock_nuevo',
            'm.observacion',
            'f.numero_comprobante',
            'u.nombres',
            'u.apellidos',
        ]);
    $lista = [];

    foreach ($movimientos as $movimiento) {
        $fila = [
            'fecha_movimiento' => fechaLocal((string) $movimiento->fecha_movimiento),
            'tipo_movimiento' => (string) $movimiento->tipo_movimiento,
            'cantidad' => (int) $movimiento->cantidad,
            'stock_anterior' => (int) $movimiento->stock_anterior,
            'stock_nuevo' => (int) $movimiento->stock_nuevo,
        ];
        agregarSiTieneValor($fila, 'numero_comprobante', $movimiento->numero_comprobante);
        agregarSiTieneValor($fila, 'nombres', $movimiento->nombres);
        agregarSiTieneValor($fila, 'apellidos', $movimiento->apellidos);
        agregarSiTieneValor($fila, 'observacion', $movimiento->observacion);
        $lista[] = $fila;
    }

    return [
        'exito' => true,
        'producto' => (string) $producto->nombre,
        'codigo' => (string) $producto->codigo,
        'movimientos' => $lista,
    ];
}

function consultaStock(int $idSucursal, string $busqueda, ?int $idCategoria, string $estadoStock): Builder
{
    $consulta = DB::table('producto as p')
        ->join('inventario_sucursal as inv', 'inv.id_producto', '=', 'p.id_producto')
        ->join('categoria as c', 'c.id_categoria', '=', 'p.id_categoria')
        ->where('inv.id_sucursal', $idSucursal)
        ->whereRaw('inv.estado IS TRUE')
        ->whereRaw('p.estado IS TRUE');

    if ($busqueda !== '') {
        $como = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $busqueda) . '%';
        $consulta->where(function ($grupo) use ($como): void {
            $grupo->whereRaw("p.nombre ILIKE ? ESCAPE '|'", [$como])
                ->orWhereRaw("p.codigo ILIKE ? ESCAPE '|'", [$como])
                ->orWhereRaw("p.codigo_barras ILIKE ? ESCAPE '|'", [$como]);
        });
    }

    if ($idCategoria !== null) {
        $consulta->where('p.id_categoria', $idCategoria);
    }

    if ($estadoStock === 'CRITICO') {
        $consulta->whereColumn('inv.stock_actual', '<=', 'inv.stock_minimo');
    } elseif ($estadoStock === 'NORMAL') {
        $consulta->whereColumn('inv.stock_actual', '>', 'inv.stock_minimo');
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

    session_start();
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

function categoriaSolicitada(array $entrada): ?int
{
    if (!array_key_exists('id_categoria', $entrada) || $entrada['id_categoria'] === null || $entrada['id_categoria'] === '') {
        return null;
    }

    $idCategoria = enteroPositivo($entrada['id_categoria']);

    if ($idCategoria === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Categoría no válida.',
        ]);
    }

    $activa = DB::table('categoria')
        ->where('id_categoria', $idCategoria)
        ->whereRaw('estado IS TRUE')
        ->exists();

    if (!$activa) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Categoría no válida.',
        ]);
    }

    return $idCategoria;
}

function estadoStockSolicitado(array $entrada): string
{
    if (!array_key_exists('estado_stock', $entrada) || $entrada['estado_stock'] === null || $entrada['estado_stock'] === '' || $entrada['estado_stock'] === 'TODAS') {
        return '';
    }

    if ($entrada['estado_stock'] === 'CRITICO' || $entrada['estado_stock'] === 'NORMAL') {
        return $entrada['estado_stock'];
    }

    responder(400, [
        'exito' => false,
        'mensaje' => 'Estado de stock no válido.',
    ]);
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

function responder(int $codigo, array $cuerpo): never
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
