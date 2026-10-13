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
        case 'listarCatalogoPrecios':
            responder(200, listarCatalogoPrecios($idUsuario, $entrada));
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
} catch (Throwable $e) {
    error_log('AWR CatalogoPreciosController: ' . $e->getMessage());
    responder(500, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function listarCatalogoPrecios(int $idUsuario, array $entrada): array
{
    $idSucursal = sucursalAutorizada($idUsuario);
    $busqueda = textoOpcional($entrada, 'busqueda', 80);
    $idCategoria = categoriaSolicitada($entrada);
    $estadoStock = estadoStock($entrada);
    $pagina = paginaSolicitada($entrada);
    $porPagina = 20;
    $consulta = consultaCatalogo($idSucursal, $busqueda, $idCategoria, $estadoStock);
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
            'p.codigo_barras',
            'c.nombre as categoria',
            'p.precio',
            'i.nombre as impuesto_nombre',
            'i.porcentaje as impuesto_porcentaje',
            'inv.stock_actual',
            'inv.stock_minimo',
        ])
        ->orderBy('p.nombre')
        ->orderBy('p.id_producto')
        ->offset($offset)
        ->limit($porPagina)
        ->get();
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
            'codigo_barras' => $fila->codigo_barras === null || trim((string) $fila->codigo_barras) === ''
                ? null
                : (string) $fila->codigo_barras,
            'categoria' => (string) $fila->categoria,
            'precio' => dinero($fila->precio),
            'impuesto_nombre' => $fila->impuesto_nombre === null ? null : (string) $fila->impuesto_nombre,
            'impuesto_porcentaje' => $fila->impuesto_porcentaje === null ? null : dinero($fila->impuesto_porcentaje),
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
    ];
}

function consultaCatalogo(int $idSucursal, string $busqueda, ?int $idCategoria, string $estadoStock): Builder
{
    $consulta = DB::table('producto as p')
        ->join('inventario_sucursal as inv', 'inv.id_producto', '=', 'p.id_producto')
        ->join('categoria as c', 'c.id_categoria', '=', 'p.id_categoria')
        ->leftJoin('impuesto as i', 'i.id_impuesto', '=', 'p.id_impuesto')
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

function estadoStock(array $entrada): string
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
