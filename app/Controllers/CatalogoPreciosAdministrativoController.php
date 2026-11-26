<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
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

if ($longitud < 0 || $longitud > 32768) {
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
        case 'obtenerFormularioProducto':
            responder(200, obtenerFormularioProducto());
            break;
        case 'crearProducto':
            responder(201, crearProducto($entrada));
            break;
        case 'actualizarProducto':
            responder(200, actualizarProducto($entrada));
            break;
        case 'cambiarEstadoProducto':
            responder(200, cambiarEstadoProducto($entrada));
            break;
        case 'registrarProductoEnSucursales':
            responder(200, registrarProductoEnSucursales($entrada));
            break;
        case 'retirarProductoDeSucursal':
            responder(200, retirarProductoDeSucursal($entrada));
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
        'impuestos' => impuestosFormulario(),
        'sucursales' => filasCatalogo('sucursal', 'id_sucursal', ['id_sucursal', 'nombre']),
    ];
}

function obtenerFormularioProducto(): array
{
    return [
        'exito' => true,
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
        'sucursales' => filasCatalogo('sucursal', 'id_sucursal', ['id_sucursal', 'nombre']),
        'unidades_sugeridas' => unidadesSugeridas(),
        'impuestos' => impuestosFormulario(),
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
            'i.codigo as impuesto_codigo',
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
        $iva = marcaIva($fila->impuesto_codigo ?? null, $fila->impuesto_porcentaje ?? null);
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
            'aplica_iva' => $iva['aplica_iva'],
            'etiqueta_iva' => $iva['etiqueta_iva'],
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

    $producto = productoDetallePorId($idProducto);

    if ($producto === null) {
        return null;
    }

    $sucursales = DB::select(
        'SELECT s.id_sucursal,
                s.nombre,
                inv.stock_actual,
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
        'producto' => filaProductoDetalle($producto),
        'sucursales' => array_map(static function ($fila): array {
            $registrada = $fila->estado_disponibilidad !== 'No registrado';

            return [
                'id_sucursal' => (int) $fila->id_sucursal,
                'nombre' => (string) $fila->nombre,
                'estado_disponibilidad' => (string) $fila->estado_disponibilidad,
                'registrada' => $registrada,
                'stock_actual' => $registrada ? (int) $fila->stock_actual : null,
            ];
        }, $sucursales),
    ];
}

function crearProducto(array $entrada): array
{
    $datos = datosProductoValidados($entrada, null);
    $asignaciones = null;

    if (array_key_exists('sucursales', $entrada)) {
        $lista = $entrada['sucursales'];

        if (!is_array($lista)) {
            responder(400, [
                'exito' => false,
                'mensaje' => 'Datos incompletos.',
            ]);
        }

        if ($lista !== []) {
            $asignaciones = validarAsignacionesSucursal($lista, null);
        }
    }

    try {
        $idProducto = (int) DB::transaction(static function () use ($datos, $asignaciones): int {
            $id = (int) DB::table('producto')->insertGetId([
                'id_categoria' => $datos['id_categoria'],
                'codigo' => $datos['codigo'],
                'codigo_barras' => $datos['codigo_barras'],
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
                'precio' => $datos['precio'],
                'costo' => $datos['costo'],
                'unidad_medida' => $datos['unidad_medida'],
                'estado' => true,
                'id_impuesto' => $datos['id_impuesto'],
                'id_proveedor' => $datos['id_proveedor'],
            ], 'id_producto');

            if ($asignaciones !== null) {
                foreach ($asignaciones as $fila) {
                    DB::table('inventario_sucursal')->insert([
                        'id_sucursal' => $fila['id_sucursal'],
                        'id_producto' => $id,
                        'stock_actual' => $fila['stock_actual'],
                        'stock_minimo' => $fila['stock_minimo'],
                        'stock_maximo' => $fila['stock_maximo'],
                        'estado' => true,
                    ]);
                }
            }

            return $id;
        });
    } catch (QueryException $e) {
        $estado = (string) ($e->errorInfo[0] ?? '');
        $detalle = mb_strtolower($e->getMessage(), 'UTF-8');

        if (
            $estado === '23505'
            && $asignaciones !== null
            && !str_contains($detalle, 'producto_codigo')
        ) {
            responder(200, [
                'exito' => false,
                'mensaje' => 'No se pudo registrar el inventario en sucursales. El producto no se creó.',
            ]);
        }

        responderColisionProducto($e);
    }

    $detalle = productoDetallePorId($idProducto);

    if ($detalle === null) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $respuesta = [
        'exito' => true,
        'producto' => filaProductoDetalle($detalle),
        'mensaje' => 'Producto creado correctamente.',
    ];

    if ($asignaciones !== null) {
        $cantidad = count($asignaciones);
        $respuesta['registrados'] = $cantidad;
        $respuesta['mensaje'] = $cantidad === 1
            ? 'Producto creado y registrado en 1 sucursal.'
            : 'Producto creado y registrado en ' . $cantidad . ' sucursales.';
    }

    return $respuesta;
}

function actualizarProducto(array $entrada): array
{
    $idProducto = enteroPositivo($entrada['id_producto'] ?? null);

    if ($idProducto === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    if (!DB::table('producto')->where('id_producto', $idProducto)->exists()) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $datos = datosProductoValidados($entrada, $idProducto);

    try {
        DB::table('producto')
            ->where('id_producto', $idProducto)
            ->update([
                'id_categoria' => $datos['id_categoria'],
                'codigo' => $datos['codigo'],
                'codigo_barras' => $datos['codigo_barras'],
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'],
                'precio' => $datos['precio'],
                'costo' => $datos['costo'],
                'unidad_medida' => $datos['unidad_medida'],
                'id_impuesto' => $datos['id_impuesto'],
                'id_proveedor' => $datos['id_proveedor'],
                'fecha_actualizacion' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    } catch (QueryException $e) {
        responderColisionProducto($e);
    }

    $detalle = productoDetallePorId($idProducto);

    if ($detalle === null) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    return [
        'exito' => true,
        'producto' => filaProductoDetalle($detalle),
    ];
}

function cambiarEstadoProducto(array $entrada): array
{
    $idProducto = enteroPositivo($entrada['id_producto'] ?? null);
    $nuevoEstado = estadoObligatorio($entrada['nuevo_estado'] ?? null);

    if ($idProducto === null || $nuevoEstado === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    if (!DB::table('producto')->where('id_producto', $idProducto)->exists()) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    DB::table('producto')
        ->where('id_producto', $idProducto)
        ->update([
            'estado' => $nuevoEstado,
            'fecha_actualizacion' => DB::raw('CURRENT_TIMESTAMP'),
        ]);

    $detalle = productoDetallePorId($idProducto);

    if ($detalle === null) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    return [
        'exito' => true,
        'producto' => filaProductoDetalle($detalle),
    ];
}

function registrarProductoEnSucursales(array $entrada): array
{
    $idProducto = enteroPositivo($entrada['id_producto'] ?? null);
    $lista = $entrada['sucursales'] ?? null;

    if ($idProducto === null || !is_array($lista) || $lista === []) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    if (!DB::table('producto')->where('id_producto', $idProducto)->exists()) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $pendientes = validarAsignacionesSucursal($lista, $idProducto);

    try {
        DB::transaction(static function () use ($pendientes): void {
            foreach ($pendientes as $fila) {
                if (!empty($fila['reactivar'])) {
                    DB::table('inventario_sucursal')
                        ->where('id_sucursal', $fila['id_sucursal'])
                        ->where('id_producto', $fila['id_producto'])
                        ->whereRaw('estado IS NOT TRUE')
                        ->update([
                            'stock_actual' => $fila['stock_actual'],
                            'stock_minimo' => $fila['stock_minimo'],
                            'stock_maximo' => $fila['stock_maximo'],
                            'estado' => true,
                            'fecha_actualizacion' => DB::raw('CURRENT_TIMESTAMP'),
                        ]);
                    continue;
                }

                DB::table('inventario_sucursal')->insert([
                    'id_sucursal' => $fila['id_sucursal'],
                    'id_producto' => $fila['id_producto'],
                    'stock_actual' => $fila['stock_actual'],
                    'stock_minimo' => $fila['stock_minimo'],
                    'stock_maximo' => $fila['stock_maximo'],
                    'estado' => true,
                ]);
            }
        });
    } catch (QueryException $e) {
        $estado = (string) ($e->errorInfo[0] ?? '');

        if ($estado === '23505') {
            responder(200, [
                'exito' => false,
                'mensaje' => 'Ya registrado en esa sucursal.',
            ]);
        }

        throw $e;
    }

    return [
        'exito' => true,
        'registrados' => count($pendientes),
        'mensaje' => count($pendientes) === 1
            ? 'Producto registrado en la sucursal.'
            : 'Producto registrado en ' . count($pendientes) . ' sucursales.',
    ];
}

function retirarProductoDeSucursal(array $entrada): array
{
    $idProducto = enteroPositivo($entrada['id_producto'] ?? null);
    $idSucursal = enteroPositivo($entrada['id_sucursal'] ?? null);

    if ($idProducto === null || $idSucursal === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }

    if (!DB::table('producto')->where('id_producto', $idProducto)->exists()) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $sucursalActiva = DB::table('sucursal')
        ->where('id_sucursal', $idSucursal)
        ->whereRaw('estado IS TRUE')
        ->first(['id_sucursal', 'nombre']);

    if ($sucursalActiva === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Sucursal no válida.',
        ]);
    }

    $afectadas = DB::table('inventario_sucursal')
        ->where('id_producto', $idProducto)
        ->where('id_sucursal', $idSucursal)
        ->whereRaw('estado IS TRUE')
        ->update([
            'estado' => false,
            'fecha_actualizacion' => DB::raw('CURRENT_TIMESTAMP'),
        ]);

    if ($afectadas < 1) {
        return [
            'exito' => false,
            'mensaje' => 'El producto no está asignado de forma activa a esa sucursal.',
        ];
    }

    $nombre = (string) $sucursalActiva->nombre;

    return [
        'exito' => true,
        'mensaje' => 'Producto retirado de ' . $nombre . '.',
    ];
}

/**
 * @param list<mixed> $lista
 * @return list<array{id_sucursal: int, id_producto: int|null, stock_actual: int, stock_minimo: int, stock_maximo: int, reactivar?: bool}>
 */
function validarAsignacionesSucursal(array $lista, ?int $idProducto): array
{
    if (count($lista) > 50) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Demasiadas sucursales en una sola solicitud.',
        ]);
    }

    $pendientes = [];
    $vistos = [];

    foreach ($lista as $item) {
        if (!is_array($item)) {
            responder(400, [
                'exito' => false,
                'mensaje' => 'Datos incompletos.',
            ]);
        }

        $idSucursal = enteroPositivo($item['id_sucursal'] ?? null);
        $stockActual = enteroNoNegativo($item['stock_actual'] ?? null);
        $stockMinimo = enteroNoNegativo($item['stock_minimo'] ?? null);
        $stockMaximo = enteroNoNegativo($item['stock_maximo'] ?? null);

        if ($idSucursal === null || $stockActual === null || $stockMinimo === null || $stockMaximo === null) {
            responder(400, [
                'exito' => false,
                'mensaje' => 'Stock o sucursal no válidos.',
            ]);
        }

        if ($stockMaximo < $stockMinimo || $stockMaximo < $stockActual) {
            responder(400, [
                'exito' => false,
                'mensaje' => 'El stock máximo debe ser mayor o igual al mínimo y al stock actual.',
            ]);
        }

        if (isset($vistos[$idSucursal])) {
            responder(400, [
                'exito' => false,
                'mensaje' => 'Hay sucursales duplicadas en la solicitud.',
            ]);
        }

        $vistos[$idSucursal] = true;

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

        $reactivar = false;

        if ($idProducto !== null) {
            $inventario = DB::table('inventario_sucursal')
                ->where('id_sucursal', $idSucursal)
                ->where('id_producto', $idProducto)
                ->first(['estado']);

            if ($inventario !== null) {
                if (esVerdadero($inventario->estado)) {
                    $nombre = (string) (DB::table('sucursal')
                        ->where('id_sucursal', $idSucursal)
                        ->value('nombre') ?? 'seleccionada');

                    responder(200, [
                        'exito' => false,
                        'mensaje' => 'Ya registrado en esa sucursal (' . $nombre . ').',
                    ]);
                }

                $reactivar = true;
            }
        }

        $pendiente = [
            'id_sucursal' => $idSucursal,
            'id_producto' => $idProducto,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
            'stock_maximo' => $stockMaximo,
        ];

        if ($reactivar) {
            $pendiente['reactivar'] = true;
        }

        $pendientes[] = $pendiente;
    }

    return $pendientes;
}

function datosProductoValidados(array $entrada, ?int $idProductoExcluir): array
{
    $codigo = cadenaObligatoria($entrada['codigo'] ?? null, 50, 'Código no válido.');
    $nombre = cadenaObligatoria($entrada['nombre'] ?? null, 200, 'Nombre no válido.');
    $unidad = cadenaObligatoria($entrada['unidad_medida'] ?? null, 50, 'Unidad de medida no válida.');
    $descripcion = cadenaOpcional($entrada['descripcion'] ?? null, 500, 'Descripción no válida.');
    $codigoBarras = cadenaOpcional($entrada['codigo_barras'] ?? null, 50, 'Código de barras no válido.');
    $idCategoria = filtroEntero($entrada, 'id_categoria', 'categoria', 'id_categoria', true, 'Categoría no válida.');
    $idProveedor = filtroEntero($entrada, 'id_proveedor', 'proveedor', 'id_proveedor', true, 'Proveedor no válido.');
    $precio = montoNoNegativo($entrada['precio'] ?? null, 'Precio no válido.');
    $costo = montoNoNegativo($entrada['costo'] ?? null, 'Costo no válido.');
    $aplicaIva = booleanoObligatorio($entrada['aplica_iva'] ?? null, 'Opción de IVA no válida.');

    if ($idCategoria === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Categoría no válida.',
        ]);
    }

    if ($idProveedor === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Proveedor no válido.',
        ]);
    }

    $duplicadoCodigo = DB::table('producto')->where('codigo', $codigo);

    if ($idProductoExcluir !== null) {
        $duplicadoCodigo->where('id_producto', '<>', $idProductoExcluir);
    }

    if ($duplicadoCodigo->exists()) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'El código ya se encuentra registrado.',
        ]);
    }

    if ($codigoBarras !== null) {
        $duplicadoBarras = DB::table('producto')->where('codigo_barras', $codigoBarras);

        if ($idProductoExcluir !== null) {
            $duplicadoBarras->where('id_producto', '<>', $idProductoExcluir);
        }

        if ($duplicadoBarras->exists()) {
            responder(200, [
                'exito' => false,
                'mensaje' => 'El código de barras ya se encuentra registrado.',
            ]);
        }
    }

    return [
        'codigo' => $codigo,
        'nombre' => $nombre,
        'unidad_medida' => $unidad,
        'descripcion' => $descripcion,
        'codigo_barras' => $codigoBarras,
        'id_categoria' => $idCategoria,
        'id_proveedor' => $idProveedor,
        'precio' => $precio,
        'costo' => $costo,
        'id_impuesto' => idImpuestoPorAplicaIva($aplicaIva),
    ];
}

function productoDetallePorId(int $idProducto): ?object
{
    return DB::table('producto as p')
        ->join('categoria as c', 'c.id_categoria', '=', 'p.id_categoria')
        ->join('proveedor as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
        ->leftJoin('impuesto as i', 'i.id_impuesto', '=', 'p.id_impuesto')
        ->where('p.id_producto', $idProducto)
        ->first([
            'p.id_producto',
            'p.id_categoria',
            'p.id_proveedor',
            'p.id_impuesto',
            'p.nombre',
            'p.descripcion',
            'p.codigo',
            'p.codigo_barras',
            'p.unidad_medida',
            'c.nombre as categoria',
            'pr.nombre_comercial as proveedor',
            'p.precio',
            'p.costo',
            'i.codigo as impuesto_codigo',
            'i.nombre as impuesto_nombre',
            'i.porcentaje as impuesto_porcentaje',
            'p.estado',
            'p.fecha_creacion',
            'p.fecha_actualizacion',
        ]);
}

function filaProductoDetalle(object $producto): array
{
    $iva = marcaIva($producto->impuesto_codigo ?? null, $producto->impuesto_porcentaje ?? null);

    return [
            'id_producto' => (int) $producto->id_producto,
        'id_categoria' => (int) $producto->id_categoria,
        'id_proveedor' => (int) $producto->id_proveedor,
        'id_impuesto' => $producto->id_impuesto === null ? null : (int) $producto->id_impuesto,
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
        'aplica_iva' => $iva['aplica_iva'],
        'etiqueta_iva' => $iva['etiqueta_iva'],
            'estado' => esVerdadero($producto->estado),
        'fecha_creacion' => textoNullable($producto->fecha_creacion ?? null),
        'fecha_actualizacion' => textoNullable($producto->fecha_actualizacion ?? null),
    ];
}

function impuestosFormulario(): array
{
    return array_map(static function ($fila): array {
        $iva = marcaIva($fila->codigo ?? null, $fila->porcentaje ?? null);

        return [
            'id_impuesto' => (int) $fila->id_impuesto,
            'codigo' => (string) $fila->codigo,
            'nombre' => (string) $fila->nombre,
            'porcentaje' => dinero($fila->porcentaje),
            'aplica_iva' => $iva['aplica_iva'],
            'etiqueta_iva' => $iva['etiqueta_iva'],
        ];
    }, DB::table('impuesto')
        ->whereRaw('estado IS TRUE')
        ->orderBy('nombre')
        ->orderBy('id_impuesto')
        ->get(['id_impuesto', 'codigo', 'nombre', 'porcentaje'])
        ->all());
}

function unidadesSugeridas(): array
{
    return array_values(array_map(
        static fn ($fila): string => (string) $fila->unidad_medida,
        DB::table('producto')
            ->whereNotNull('unidad_medida')
            ->where('unidad_medida', '<>', '')
            ->groupBy('unidad_medida')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('unidad_medida')
            ->limit(20)
            ->get(['unidad_medida'])
            ->all()
    ));
}

function idImpuestoPorAplicaIva(bool $aplicaIva): int
{
    if ($aplicaIva) {
        $fila = DB::table('impuesto')
            ->whereRaw('estado IS TRUE')
            ->where('porcentaje', '>', 0)
            ->orderByRaw("CASE WHEN codigo = 'IVA15' THEN 0 ELSE 1 END")
            ->orderByDesc('porcentaje')
            ->orderBy('id_impuesto')
            ->first(['id_impuesto']);
    } else {
        $fila = DB::table('impuesto')
            ->whereRaw('estado IS TRUE')
            ->where(function ($consulta): void {
                $consulta->where('codigo', 'IVA0')
                    ->orWhere('porcentaje', '=', 0);
            })
            ->orderByRaw("CASE WHEN codigo = 'IVA0' THEN 0 ELSE 1 END")
            ->orderBy('porcentaje')
            ->orderBy('id_impuesto')
            ->first(['id_impuesto']);
    }

    if ($fila === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Impuesto no disponible.',
        ]);
    }

    return (int) $fila->id_impuesto;
}

function marcaIva(mixed $codigo, mixed $porcentaje): array
{
    $codigoTexto = $codigo === null ? '' : mb_strtoupper(trim((string) $codigo), 'UTF-8');
    $porcentajeNumero = $porcentaje === null || $porcentaje === '' ? null : (float) $porcentaje;
    $aplica = $codigoTexto !== 'IVA0' && $porcentajeNumero !== null && $porcentajeNumero > 0;

    return [
        'aplica_iva' => $aplica,
        'etiqueta_iva' => $aplica ? 'Con IVA' : 'Sin IVA',
    ];
}

function responderColisionProducto(QueryException $e): never
{
    $estado = (string) ($e->errorInfo[0] ?? '');
    $detalle = mb_strtolower($e->getMessage(), 'UTF-8');

    if ($estado === '23505' && str_contains($detalle, 'producto_codigo_barras_unique')) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'El código de barras ya se encuentra registrado.',
        ]);
    }

    if ($estado === '23505' && str_contains($detalle, 'producto_codigo_unique')) {
        responder(200, [
            'exito' => false,
            'mensaje' => 'El código ya se encuentra registrado.',
        ]);
    }

    throw $e;
}

function cadenaObligatoria(mixed $valor, int $maximo, string $mensaje): string
{
    if (!is_string($valor)) {
        responder(400, [
            'exito' => false,
            'mensaje' => $mensaje,
        ]);
    }

    $texto = trim($valor);

    if ($texto === '' || mb_strlen($texto) > $maximo) {
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

function montoNoNegativo(mixed $valor, string $mensaje): string
{
    if (is_int($valor) || is_float($valor)) {
        if (!is_finite((float) $valor) || $valor < 0) {
            responder(400, [
                'exito' => false,
                'mensaje' => $mensaje,
            ]);
        }

        return dinero($valor);
    }

    if (!is_string($valor)) {
        responder(400, [
            'exito' => false,
            'mensaje' => $mensaje,
        ]);
    }

    $texto = trim($valor);

    if ($texto === '' || preg_match('/^\d+(\.\d{1,4})?$/', $texto) !== 1) {
        responder(400, [
            'exito' => false,
            'mensaje' => $mensaje,
        ]);
    }

    return dinero((float) $texto);
}

function booleanoObligatorio(mixed $valor, string $mensaje): bool
{
    if ($valor === true || $valor === 1 || $valor === '1' || $valor === 'true') {
        return true;
    }

    if ($valor === false || $valor === 0 || $valor === '0' || $valor === 'false') {
        return false;
    }

    responder(400, [
        'exito' => false,
        'mensaje' => $mensaje,
    ]);
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

function enteroNoNegativo(mixed $valor): ?int
{
    if (is_int($valor) && $valor >= 0) {
        return $valor;
    }

    if (is_float($valor) || is_bool($valor)) {
        return null;
    }

    if (is_string($valor) && preg_match('/^(0|[1-9][0-9]*)$/', $valor) === 1) {
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
