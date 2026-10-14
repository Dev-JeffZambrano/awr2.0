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

if (!sesionAdministrativa()) {
    responder(403, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

try {
    switch ($entrada['op'] ?? null) {
        case 'obtenerResumenCatalogo':
            responder(200, obtenerResumenCatalogo());
            break;
        case 'listarCatalogoAdministrativo':
            responder(200, listarCatalogoAdministrativo($entrada));
            break;
        case 'obtenerDetalleProducto':
            $detalle = obtenerDetalleProducto($entrada);

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
    error_log('AWR CatalogoPreciosAdministrativoController: ' . $e->getMessage());
    responder(500, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function obtenerResumenCatalogo(): array
{
    $sucursalesActivas = (int) DB::table('sucursal')->whereRaw('estado IS TRUE')->count();
    $cobertura = DB::selectOne(
        'SELECT COUNT(*)::int AS productos_cobertura_parcial
         FROM producto p
         LEFT JOIN (
             SELECT inv.id_producto,
                    COUNT(DISTINCT inv.id_sucursal)::int AS sucursales_disponibles
             FROM inventario_sucursal inv
             JOIN sucursal s
               ON s.id_sucursal = inv.id_sucursal
              AND s.estado IS TRUE
             WHERE inv.estado IS TRUE
               AND inv.stock_actual > 0
             GROUP BY inv.id_producto
         ) cobertura ON cobertura.id_producto = p.id_producto
         WHERE p.estado IS TRUE
           AND COALESCE(cobertura.sucursales_disponibles, 0) < ?',
        [$sucursalesActivas]
    );

    return [
        'exito' => true,
        'kpis' => [
            'productos_activos' => (int) DB::table('producto')->whereRaw('estado IS TRUE')->count(),
            'categorias_activas' => (int) DB::table('categoria')->whereRaw('estado IS TRUE')->count(),
            'proveedores_activos' => (int) DB::table('proveedor')->whereRaw('estado IS TRUE')->count(),
            'productos_cobertura_parcial' => (int) $cobertura->productos_cobertura_parcial,
        ],
        'categorias' => filasCatalogo('categoria', 'id_categoria', ['id_categoria', 'nombre']),
        'proveedores' => array_map(static fn ($fila): array => [
            'id_proveedor' => (int) $fila->id_proveedor,
            'nombre' => (string) $fila->nombre_comercial,
        ], DB::table('proveedor')
            ->whereRaw('estado IS TRUE')
            ->orderBy('nombre_comercial')
            ->orderBy('id_proveedor')
            ->get(['id_proveedor', 'nombre_comercial'])
            ->all()),
        'impuestos' => array_map(static fn ($fila): array => [
            'id_impuesto' => (int) $fila->id_impuesto,
            'nombre' => (string) $fila->nombre,
            'porcentaje' => dinero($fila->porcentaje),
        ], DB::table('impuesto')
            ->whereRaw('estado IS TRUE')
            ->orderBy('nombre')
            ->orderBy('id_impuesto')
            ->get(['id_impuesto', 'nombre', 'porcentaje'])
            ->all()),
        'sucursales' => filasCatalogo('sucursal', 'id_sucursal', ['id_sucursal', 'nombre']),
    ];
}

function listarCatalogoAdministrativo(array $entrada): array
{
    $busqueda = textoOpcional($entrada, 'busqueda', 80);
    $idCategoria = filtroEntero($entrada, 'id_categoria', 'categoria', 'id_categoria', true, 'Categoría no válida.');
    $idProveedor = filtroEntero($entrada, 'id_proveedor', 'proveedor', 'id_proveedor', false, 'Proveedor no válido.');
    $idImpuesto = filtroEntero($entrada, 'id_impuesto', 'impuesto', 'id_impuesto', false, 'Impuesto no válido.');
    $estado = estadoProducto($entrada);
    $idSucursal = sucursalAnalitica($entrada);
    $pagina = paginaSolicitada($entrada);
    $porPagina = 20;
    $totalSucursales = (int) DB::table('sucursal')->whereRaw('estado IS TRUE')->count();
    $consulta = consultaCatalogo($busqueda, $idCategoria, $idProveedor, $idImpuesto, $estado, $idSucursal);
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
            'p.unidad_medida',
            'p.codigo',
            'p.codigo_barras',
            'c.nombre as categoria',
            'pr.nombre_comercial as proveedor',
            'p.precio',
            'i.nombre as impuesto_nombre',
            'i.porcentaje as impuesto_porcentaje',
            'p.estado',
        ])
        ->selectRaw('COALESCE(disponibilidad.sucursales_disponibles, 0)::int AS sucursales_disponibles')
        ->orderBy('p.nombre')
        ->orderBy('p.id_producto')
        ->offset($offset)
        ->limit($porPagina)
        ->get();
    $lista = [];

    foreach ($filas as $fila) {
        $lista[] = [
            'id_producto' => (int) $fila->id_producto,
            'nombre' => (string) $fila->nombre,
            'unidad_medida' => (string) $fila->unidad_medida,
            'codigo' => (string) $fila->codigo,
            'codigo_barras' => textoNullable($fila->codigo_barras),
            'categoria' => (string) $fila->categoria,
            'proveedor' => (string) $fila->proveedor,
            'precio' => dinero($fila->precio),
            'impuesto_nombre' => $fila->impuesto_nombre === null ? null : (string) $fila->impuesto_nombre,
            'impuesto_porcentaje' => $fila->impuesto_porcentaje === null ? null : dinero($fila->impuesto_porcentaje),
            'sucursales_disponibles' => (int) $fila->sucursales_disponibles,
            'total_sucursales_activas' => $totalSucursales,
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

function obtenerDetalleProducto(array $entrada): ?array
{
    $idProducto = enteroPositivo($entrada['id_producto'] ?? null);

    if ($idProducto === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    $producto = DB::table('producto as p')
        ->join('categoria as c', 'c.id_categoria', '=', 'p.id_categoria')
        ->join('proveedor as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
        ->leftJoin('impuesto as i', 'i.id_impuesto', '=', 'p.id_impuesto')
        ->where('p.id_producto', $idProducto)
        ->first([
            'p.id_producto',
            'p.nombre',
            'p.descripcion',
            'p.codigo',
            'p.codigo_barras',
            'p.unidad_medida',
            'c.nombre as categoria',
            'pr.nombre_comercial as proveedor',
            'p.precio',
            'p.costo',
            'i.nombre as impuesto_nombre',
            'i.porcentaje as impuesto_porcentaje',
            'p.estado',
        ]);

    if ($producto === null) {
        return null;
    }

    $sucursales = DB::select(
        'SELECT s.id_sucursal,
                s.nombre,
                CASE
                    WHEN inv.id_inventario IS NULL THEN \'No registrado\'
                    WHEN inv.stock_actual > 0 THEN \'Disponible\'
                    ELSE \'Sin stock\'
                END AS estado_disponibilidad
         FROM sucursal s
         LEFT JOIN inventario_sucursal inv
           ON inv.id_sucursal = s.id_sucursal
          AND inv.id_producto = ?
          AND inv.estado IS TRUE
         WHERE s.estado IS TRUE
         ORDER BY s.nombre, s.id_sucursal',
        [$idProducto]
    );

    return [
        'exito' => true,
        'producto' => [
            'id_producto' => (int) $producto->id_producto,
            'nombre' => (string) $producto->nombre,
            'descripcion' => textoNullable($producto->descripcion),
            'codigo' => (string) $producto->codigo,
            'codigo_barras' => textoNullable($producto->codigo_barras),
            'unidad_medida' => (string) $producto->unidad_medida,
            'categoria' => (string) $producto->categoria,
            'proveedor' => (string) $producto->proveedor,
            'precio' => dinero($producto->precio),
            'costo' => dinero($producto->costo),
            'margen_bruto_porcentaje' => margenBruto($producto->precio, $producto->costo),
            'impuesto_nombre' => $producto->impuesto_nombre === null ? null : (string) $producto->impuesto_nombre,
            'impuesto_porcentaje' => $producto->impuesto_porcentaje === null ? null : dinero($producto->impuesto_porcentaje),
            'estado' => esVerdadero($producto->estado),
        ],
        'sucursales' => array_map(static fn ($fila): array => [
            'id_sucursal' => (int) $fila->id_sucursal,
            'nombre' => (string) $fila->nombre,
            'estado_disponibilidad' => (string) $fila->estado_disponibilidad,
        ], $sucursales),
    ];
}

function consultaCatalogo(
    string $busqueda,
    ?int $idCategoria,
    ?int $idProveedor,
    ?int $idImpuesto,
    string $estado,
    ?int $idSucursal
): Builder {
    $disponibilidad = DB::table('inventario_sucursal as inv')
        ->join('sucursal as s', function ($join): void {
            $join->on('s.id_sucursal', '=', 'inv.id_sucursal')
                ->whereRaw('s.estado IS TRUE');
        })
        ->whereRaw('inv.estado IS TRUE')
        ->where('inv.stock_actual', '>', 0)
        ->groupBy('inv.id_producto')
        ->select('inv.id_producto')
        ->selectRaw('COUNT(DISTINCT inv.id_sucursal)::int AS sucursales_disponibles');
    $consulta = DB::table('producto as p')
        ->join('categoria as c', 'c.id_categoria', '=', 'p.id_categoria')
        ->join('proveedor as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
        ->leftJoin('impuesto as i', 'i.id_impuesto', '=', 'p.id_impuesto')
        ->leftJoinSub($disponibilidad, 'disponibilidad', 'disponibilidad.id_producto', '=', 'p.id_producto');

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

    if ($idProveedor !== null) {
        $consulta->where('p.id_proveedor', $idProveedor);
    }

    if ($idImpuesto !== null) {
        $consulta->where('p.id_impuesto', $idImpuesto);
    }

    if ($estado === 'ACTIVO') {
        $consulta->whereRaw('p.estado IS TRUE');
    } elseif ($estado === 'INACTIVO') {
        $consulta->whereRaw('p.estado IS NOT TRUE');
    }

    if ($idSucursal !== null) {
        $consulta->whereExists(function ($subconsulta) use ($idSucursal): void {
            $subconsulta->from('inventario_sucursal as invf')
                ->selectRaw('1')
                ->whereColumn('invf.id_producto', 'p.id_producto')
                ->where('invf.id_sucursal', $idSucursal)
                ->whereRaw('invf.estado IS TRUE')
                ->where('invf.stock_actual', '>', 0);
        });
    }

    return $consulta;
}

function filasCatalogo(string $tabla, string $clave, array $columnas): array
{
    $filas = DB::table($tabla)
        ->whereRaw('estado IS TRUE')
        ->orderBy('nombre')
        ->orderBy($clave)
        ->get($columnas);
    $lista = [];

    foreach ($filas as $fila) {
        $item = [];

        foreach ($columnas as $columna) {
            $item[$columna] = $columna === $clave ? (int) $fila->{$columna} : (string) $fila->{$columna};
        }

        $lista[] = $item;
    }

    return $lista;
}

function filtroEntero(
    array $entrada,
    string $clave,
    string $tabla,
    string $columna,
    bool $exigirActivo,
    string $mensaje
): ?int {
    if (!array_key_exists($clave, $entrada) || $entrada[$clave] === null || $entrada[$clave] === '') {
        return null;
    }

    $id = enteroPositivo($entrada[$clave]);

    if ($id === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => $mensaje,
        ]);
    }

    $consulta = DB::table($tabla)->where($columna, $id);

    if ($exigirActivo) {
        $consulta->whereRaw('estado IS TRUE');
    }

    if (!$consulta->exists()) {
        responder(400, [
            'exito' => false,
            'mensaje' => $mensaje,
        ]);
    }

    return $id;
}

function estadoProducto(array $entrada): string
{
    if (!array_key_exists('estado_producto', $entrada) || $entrada['estado_producto'] === null || $entrada['estado_producto'] === '') {
        return 'ACTIVO';
    }

    if (in_array($entrada['estado_producto'], ['ACTIVO', 'INACTIVO', 'TODOS'], true)) {
        return $entrada['estado_producto'];
    }

    responder(400, [
        'exito' => false,
        'mensaje' => 'Estado de producto no válido.',
    ]);
}

function sucursalAnalitica(array $entrada): ?int
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

    session_start();
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

function textoNullable(mixed $valor): ?string
{
    if ($valor === null) {
        return null;
    }

    $texto = trim((string) $valor);

    return $texto === '' ? null : $texto;
}

function dinero(mixed $valor): string
{
    return number_format(round((float) $valor, 2, PHP_ROUND_HALF_UP), 2, '.', '');
}

function margenBruto(mixed $precio, mixed $costo): ?string
{
    $precioNumero = (float) $precio;

    if ($precioNumero <= 0) {
        return null;
    }

    return number_format(
        round((($precioNumero - (float) $costo) / $precioNumero) * 100, 2, PHP_ROUND_HALF_UP),
        2,
        '.',
        ''
    );
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
