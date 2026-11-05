<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';
use App\Services\EstadoSistemaService;
use Illuminate\Database\Capsule\Manager as DB;

if (PHP_SAPI !== 'cli') {
    atenderSolicitudBasesDatos();
}

function atenderSolicitudBasesDatos(): void
{
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
    $op = $entrada['op'] ?? null;

    switch ($op) {
        case 'obtenerResumenMotores':
            obtenerResumenMotores();
            break;
        case 'obtenerModeloRelacional':
            obtenerModeloRelacional($entrada);
            break;
        case 'obtenerDetalleTabla':
            obtenerDetalleTabla($entrada);
            break;
        default:
            responder(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
}

function obtenerResumenMotores(): void
{
    if (!sesionAdministrador()) {
        responder(403, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    try {
        $estado = EstadoSistemaService::obtenerEstadoActual();
        responder(200, proyectarMotores($estado));
    } catch (Throwable $e) {
        error_log('AWR obtenerResumenMotores: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function obtenerModeloRelacional(array $entrada): void
{
    if (!sesionAdministrador()) {
        responder(403, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $motor = motorSolicitado($entrada);

    if ($motor === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación inválida.',
        ]);
    }

    if ($motor === 'clickhouse') {
        responder(200, [
            'exito' => true,
            'motor' => 'clickhouse',
            'disponible' => false,
            'estado' => 'No integrado',
            'dominios' => [],
            'relaciones' => [],
        ]);
    }

    try {
        responder(200, modeloPostgresql());
    } catch (Throwable $e) {
        error_log('AWR obtenerModeloRelacional: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function obtenerDetalleTabla(array $entrada): void
{
    if (!sesionAdministrador()) {
        responder(403, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $motor = motorSolicitado($entrada);
    $tabla = tablaSolicitada($entrada);

    if ($motor === null || $tabla === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Operación inválida.',
        ]);
    }

    if ($motor === 'clickhouse') {
        responder(200, [
            'exito' => true,
            'motor' => 'clickhouse',
            'tabla' => $tabla,
            'disponible' => false,
            'estado' => 'No integrado',
        ]);
    }

    try {
        $detalle = detallePostgresql($tabla);

        if ($detalle === null) {
            responder(200, [
                'exito' => false,
                'mensaje' => 'No disponible',
            ]);
        }

        responder(200, $detalle);
    } catch (Throwable $e) {
        error_log('AWR obtenerDetalleTabla: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function proyectarMotores(array $estado): array
{
    $postgresql = is_array($estado['postgresql'] ?? null) ? $estado['postgresql'] : [];
    $enlace = is_array($estado['enlace'] ?? null) ? $estado['enlace'] : [];
    $clickhouse = is_array($estado['clickhouse'] ?? null) ? $estado['clickhouse'] : [];
    $estadoClickhouse = texto($clickhouse['estado'] ?? null, 'No integrado');

    return [
        'exito' => true,
        'motores' => [
            [
                'id' => 'postgresql',
                'nombre' => 'PostgreSQL',
                'rol' => 'OLTP',
                'disponible' => ($postgresql['estado'] ?? null) === 'Conectado',
                'estado' => texto($postgresql['estado'] ?? null, 'No disponible'),
                'version' => $postgresql['version'] ?? null,
                'base' => $postgresql['base'] ?? null,
                'tablas' => $postgresql['tablas'] ?? null,
                'tamano_bytes' => $postgresql['tamano_bytes'] ?? null,
                'conexiones' => $postgresql['conexiones'] ?? null,
                'tiempo_respuesta_ms' => $postgresql['tiempo_respuesta_ms'] ?? null,
                'puerto' => $postgresql['puerto'] ?? null,
                'latencia_red_ms' => $enlace['latencia_red_ms'] ?? null,
            ],
            [
                'id' => 'clickhouse',
                'nombre' => 'ClickHouse',
                'rol' => 'OLAP',
                'disponible' => $estadoClickhouse === 'Conectado' || $estadoClickhouse === 'Operativo',
                'estado' => $estadoClickhouse,
                'version' => $clickhouse['version'] ?? null,
                'base' => $clickhouse['base'] ?? null,
                'tablas' => $clickhouse['tablas'] ?? null,
                'tamano_bytes' => $clickhouse['tamano_bytes'] ?? null,
                'tiempo_respuesta_ms' => $clickhouse['tiempo_respuesta_ms'] ?? null,
                'puerto' => $clickhouse['puerto'] ?? null,
            ],
        ],
        'arquitectura' => [
            'transaccional' => 'PostgreSQL',
            'analitico' => 'ClickHouse',
        ],
    ];
}

function modeloPostgresql(): array
{
    $tablas = tablasPublicas();
    $tecnicas = tablasTecnicas();
    $relaciones = [];

    foreach (relacionesPublicas() as $relacion) {
        if (isset($tecnicas[$relacion['tabla_origen']]) || isset($tecnicas[$relacion['tabla_destino']])) {
            continue;
        }

        $relaciones[] = $relacion;
    }

    return [
        'exito' => true,
        'motor' => 'postgresql',
        'disponible' => true,
        'dominios' => agruparDominios($tablas),
        'grupos' => agruparClases($tablas),
        'relaciones' => $relaciones,
    ];
}

function detallePostgresql(string $tabla): ?array
{
    $tablas = tablasPublicas();

    if (!in_array($tabla, $tablas, true)) {
        return null;
    }

    $primarias = clavesPrimarias($tabla);
    $foraneas = clavesForaneas($tabla);
    $comentarios = comentariosColumnas($tabla);
    $columnas = [];

    foreach (columnasTabla($tabla) as $fila) {
        $nombre = (string) $fila->column_name;
        $referencia = $foraneas[$nombre] ?? null;
        $columnas[] = [
            'nombre' => $nombre,
            'tipo' => tipoLegible($fila),
            'permite_nulo' => esSi($fila->is_nullable ?? null),
            'clave_primaria' => isset($primarias[$nombre]),
            'clave_foranea' => $referencia !== null,
            'referencia' => $referencia,
            'predeterminado' => predeterminado($fila->column_default ?? null),
            'comentario_postgresql' => $comentarios[$nombre] ?? null,
            'descripcion_funcional' => descripcionColumna($tabla, $nombre),
        ];
    }

    return [
        'exito' => true,
        'motor' => 'postgresql',
        'tabla' => $tabla,
        'tipo_tabla' => isset(tablasTecnicas()[$tabla]) ? 'tecnica' : 'negocio',
        'clase' => clasificarTabla($tabla),
        'clase_nombre' => nombreClase(clasificarTabla($tabla)),
        'comentario_postgresql' => comentarioTabla($tabla),
        'descripcion_funcional' => descripcionTabla($tabla),
        'columnas' => $columnas,
    ];
}

function tablasPublicas(): array
{
    $filas = DB::select(
        "SELECT table_name
           FROM information_schema.tables
          WHERE table_schema = 'public'
            AND table_type = 'BASE TABLE'
          ORDER BY table_name"
    );
    $tablas = [];

    foreach ($filas as $fila) {
        if (is_string($fila->table_name ?? null) && $fila->table_name !== '') {
            $tablas[] = $fila->table_name;
        }
    }

    return $tablas;
}

function columnasTabla(string $tabla): array
{
    return DB::select(
        "SELECT column_name,
                data_type,
                character_maximum_length,
                numeric_precision,
                numeric_scale,
                is_nullable,
                column_default
           FROM information_schema.columns
          WHERE table_schema = 'public'
            AND table_name = ?
          ORDER BY ordinal_position",
        [$tabla]
    );
}

function clavesPrimarias(string $tabla): array
{
    $filas = DB::select(
        "SELECT kcu.column_name
           FROM information_schema.table_constraints tc
           JOIN information_schema.key_column_usage kcu
             ON kcu.constraint_schema = tc.constraint_schema
            AND kcu.constraint_name = tc.constraint_name
            AND kcu.table_schema = tc.table_schema
            AND kcu.table_name = tc.table_name
          WHERE tc.table_schema = 'public'
            AND tc.table_name = ?
            AND tc.constraint_type = 'PRIMARY KEY'",
        [$tabla]
    );
    $claves = [];

    foreach ($filas as $fila) {
        if (is_string($fila->column_name ?? null) && $fila->column_name !== '') {
            $claves[$fila->column_name] = true;
        }
    }

    return $claves;
}

function clavesForaneas(string $tabla): array
{
    $filas = DB::select(
        "SELECT kcu.column_name AS columna_origen,
                kcu2.table_name AS tabla_destino,
                kcu2.column_name AS columna_destino
           FROM information_schema.referential_constraints rc
           JOIN information_schema.key_column_usage kcu
             ON kcu.constraint_schema = rc.constraint_schema
            AND kcu.constraint_name = rc.constraint_name
           JOIN information_schema.key_column_usage kcu2
             ON kcu2.constraint_schema = rc.unique_constraint_schema
            AND kcu2.constraint_name = rc.unique_constraint_name
            AND kcu2.ordinal_position = kcu.position_in_unique_constraint
          WHERE kcu.table_schema = 'public'
            AND kcu.table_name = ?
          ORDER BY rc.constraint_name, kcu.ordinal_position",
        [$tabla]
    );
    $claves = [];

    foreach ($filas as $fila) {
        $columna = is_string($fila->columna_origen ?? null) ? $fila->columna_origen : '';
        $destino = referencia($fila->tabla_destino ?? null, $fila->columna_destino ?? null);

        if ($columna === '' || $destino === null) {
            continue;
        }

        $claves[$columna] = isset($claves[$columna]) ? $claves[$columna] . ', ' . $destino : $destino;
    }

    return $claves;
}

function relacionesPublicas(): array
{
    $filas = DB::select(
        "SELECT rc.constraint_name,
                kcu.table_name AS tabla_origen,
                kcu.column_name AS columna_origen,
                kcu2.table_name AS tabla_destino,
                kcu2.column_name AS columna_destino
           FROM information_schema.referential_constraints rc
           JOIN information_schema.key_column_usage kcu
             ON kcu.constraint_schema = rc.constraint_schema
            AND kcu.constraint_name = rc.constraint_name
           JOIN information_schema.key_column_usage kcu2
             ON kcu2.constraint_schema = rc.unique_constraint_schema
            AND kcu2.constraint_name = rc.unique_constraint_name
            AND kcu2.ordinal_position = kcu.position_in_unique_constraint
          WHERE kcu.table_schema = 'public'
            AND kcu2.table_schema = 'public'
          ORDER BY kcu.table_name, kcu.column_name, kcu2.table_name, kcu2.column_name"
    );
    $relaciones = [];

    foreach ($filas as $fila) {
        $origen = referencia($fila->tabla_origen ?? null, $fila->columna_origen ?? null);
        $destino = referencia($fila->tabla_destino ?? null, $fila->columna_destino ?? null);

        if ($origen === null || $destino === null) {
            continue;
        }

        $relaciones[] = [
            'origen' => $origen,
            'destino' => $destino,
            'constraint' => is_string($fila->constraint_name ?? null) ? $fila->constraint_name : null,
            'tabla_origen' => (string) $fila->tabla_origen,
            'columna_origen' => (string) $fila->columna_origen,
            'tabla_destino' => (string) $fila->tabla_destino,
            'columna_destino' => (string) $fila->columna_destino,
        ];
    }

    return $relaciones;
}

function comentarioTabla(string $tabla): ?string
{
    $filas = DB::select(
        "SELECT d.description AS comentario
           FROM pg_catalog.pg_description d
           JOIN pg_catalog.pg_class c ON c.oid = d.objoid
           JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
          WHERE n.nspname = 'public'
            AND c.relname = ?
            AND c.relkind = 'r'
            AND d.objsubid = 0",
        [$tabla]
    );

    return textoComentario($filas[0]->comentario ?? null);
}

function comentariosColumnas(string $tabla): array
{
    $filas = DB::select(
        "SELECT a.attname AS columna,
                d.description AS comentario
           FROM pg_catalog.pg_description d
           JOIN pg_catalog.pg_class c ON c.oid = d.objoid
           JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
           JOIN pg_catalog.pg_attribute a
             ON a.attrelid = c.oid
            AND a.attnum = d.objsubid
          WHERE n.nspname = 'public'
            AND c.relname = ?
            AND c.relkind = 'r'
            AND d.objsubid > 0",
        [$tabla]
    );
    $comentarios = [];

    foreach ($filas as $fila) {
        if (!is_string($fila->columna ?? null) || $fila->columna === '') {
            continue;
        }

        $comentarios[$fila->columna] = textoComentario($fila->comentario ?? null);
    }

    return $comentarios;
}

function agruparDominios(array $tablas): array
{
    $existentes = array_fill_keys($tablas, true);
    $tecnicas = tablasTecnicas();
    $dominios = [];

    foreach (mapaDominios() as $id => $dominio) {
        $presentes = [];

        foreach ($dominio['tablas'] as $tabla) {
            if (isset($existentes[$tabla]) && !isset($tecnicas[$tabla])) {
                $presentes[] = $tabla;
            }
        }

        if ($presentes !== []) {
            $dominios[] = [
                'id' => $id,
                'nombre' => $dominio['nombre'],
                'tablas' => $presentes,
            ];
        }
    }

    $conocidas = [];

    foreach (mapaDominios() as $dominio) {
        foreach ($dominio['tablas'] as $tabla) {
            $conocidas[$tabla] = true;
        }
    }

    $otras = [];

    foreach ($tablas as $tabla) {
        if (!isset($conocidas[$tabla]) && !isset($tecnicas[$tabla])) {
            $otras[] = $tabla;
        }
    }

    if ($otras !== []) {
        $dominios[] = [
            'id' => 'otras',
            'nombre' => 'Otras',
            'tablas' => $otras,
        ];
    }

    return $dominios;
}

function mapaDominios(): array
{
    return [
        'seguridad' => [
            'nombre' => 'Seguridad',
            'tablas' => [
                'usuario',
                'rol',
                'permiso',
                'usuario_rol',
                'rol_permiso',
                'auditoria_login',
            ],
        ],
        'organizacion' => [
            'nombre' => 'Organización',
            'tablas' => [
                'empresa',
                'sucursal',
                'usuario_sucursal',
                'punto_emision',
            ],
        ],
        'clientes' => [
            'nombre' => 'Clientes',
            'tablas' => [
                'cliente',
            ],
        ],
        'catalogo' => [
            'nombre' => 'Catálogo',
            'tablas' => [
                'producto',
                'categoria',
                'impuesto',
                'proveedor',
            ],
        ],
        'facturacion' => [
            'nombre' => 'Facturación',
            'tablas' => [
                'factura',
                'factura_detalle',
                'pago',
                'metodo_pago',
                'tipo_comprobante',
                'secuencial_comprobante',
                'configuracion_facturacion',
            ],
        ],
        'inventario' => [
            'nombre' => 'Inventario',
            'tablas' => [
                'inventario_sucursal',
                'movimiento_inventario',
            ],
        ],
        'resiliencia' => [
            'nombre' => 'Resiliencia',
            'tablas' => [
                'operacion_idempotente',
            ],
        ],
    ];
}

function agruparClases(array $tablas): array
{
    $orden = [
        'maestra' => ['nombre' => 'Tablas maestras', 'familia' => 'maestras'],
        'relacion' => ['nombre' => 'Tablas de relación', 'familia' => 'demas'],
        'saldo' => ['nombre' => 'Tablas de saldo', 'familia' => 'demas'],
        'transaccional' => ['nombre' => 'Tablas transaccionales', 'familia' => 'demas'],
        'tecnica' => ['nombre' => 'Tablas técnicas', 'familia' => 'demas'],
        'sin_clasificar' => ['nombre' => 'Sin clasificar', 'familia' => 'demas'],
    ];
    $cubetas = [];

    foreach ($orden as $id => $grupo) {
        $cubetas[$id] = [];
    }

    foreach ($tablas as $tabla) {
        $clase = clasificarTabla($tabla);

        if (!isset($cubetas[$clase])) {
            $clase = 'sin_clasificar';
        }

        $cubetas[$clase][] = $tabla;
    }

    $grupos = [];

    foreach ($orden as $id => $grupo) {
        if ($cubetas[$id] === []) {
            continue;
        }

        sort($cubetas[$id]);
        $grupos[] = [
            'id' => $id,
            'nombre' => $grupo['nombre'],
            'familia' => $grupo['familia'],
            'tablas' => $cubetas[$id],
        ];
    }

    return $grupos;
}

function clasificarTabla(string $tabla): string
{
    $clases = mapaClases();

    if (isset($clases[$tabla])) {
        return $clases[$tabla];
    }

    return isset(tablasTecnicas()[$tabla]) ? 'tecnica' : 'sin_clasificar';
}

function nombreClase(string $clase): string
{
    $nombres = [
        'maestra' => 'Tabla maestra',
        'relacion' => 'Tabla de relación',
        'saldo' => 'Tabla de saldo',
        'transaccional' => 'Tabla transaccional',
        'tecnica' => 'Tabla técnica',
        'sin_clasificar' => 'Sin clasificar',
    ];

    return $nombres[$clase] ?? 'Sin clasificar';
}

function mapaClases(): array
{
    return [
        'empresa' => 'maestra',
        'sucursal' => 'maestra',
        'punto_emision' => 'maestra',
        'usuario' => 'maestra',
        'rol' => 'maestra',
        'permiso' => 'maestra',
        'cliente' => 'maestra',
        'categoria' => 'maestra',
        'producto' => 'maestra',
        'proveedor' => 'maestra',
        'impuesto' => 'maestra',
        'metodo_pago' => 'maestra',
        'tipo_comprobante' => 'maestra',
        'configuracion_facturacion' => 'maestra',
        'usuario_rol' => 'relacion',
        'rol_permiso' => 'relacion',
        'usuario_sucursal' => 'relacion',
        'inventario_sucursal' => 'saldo',
        'secuencial_comprobante' => 'saldo',
        'factura' => 'transaccional',
        'factura_detalle' => 'transaccional',
        'pago' => 'transaccional',
        'movimiento_inventario' => 'transaccional',
        'operacion_idempotente' => 'transaccional',
        'auditoria_login' => 'transaccional',
        'knex_migrations' => 'tecnica',
        'knex_migrations_lock' => 'tecnica',
    ];
}

function tablasTecnicas(): array
{
    return [
        'knex_migrations' => true,
        'knex_migrations_lock' => true,
    ];
}

function descripcionTabla(string $tabla): ?string
{
    $descripciones = [
        'factura' => 'Comprobante emitido por una sucursal.',
        'factura_detalle' => 'Líneas de productos que componen una factura.',
        'cliente' => 'Datos generales de clientes.',
        'producto' => 'Catálogo maestro de productos.',
        'inventario_sucursal' => 'Existencia actual de productos por sucursal.',
        'operacion_idempotente' => 'Control técnico de operaciones resilientes e idempotencia.',
    ];

    return $descripciones[$tabla] ?? null;
}

function descripcionColumna(string $tabla, string $columna): ?string
{
    $descripciones = [
        'usuario.username' => 'Identificador de acceso del usuario.',
        'usuario.password_hash' => 'Hash de la contraseña del usuario.',
        'usuario.estado' => 'Indica si el usuario está habilitado.',
        'factura.id_factura' => 'Identificador del comprobante.',
        'factura.id_cliente' => 'Cliente asociado a la factura.',
        'factura.id_sucursal' => 'Sucursal que emite la factura.',
        'factura.total' => 'Total del comprobante.',
        'factura.fecha_emision' => 'Fecha y hora de emisión del comprobante.',
        'factura_detalle.id_producto' => 'Producto incluido en la línea.',
        'factura_detalle.cantidad' => 'Cantidad facturada.',
        'factura_detalle.precio_unitario' => 'Precio unitario de la línea.',
        'pago.id_metodo_pago' => 'Método de pago utilizado.',
        'pago.monto' => 'Monto cobrado.',
        'producto.codigo' => 'Código del producto.',
        'producto.nombre' => 'Nombre del producto.',
        'producto.precio' => 'Precio de venta.',
        'producto.costo' => 'Costo del producto.',
        'inventario_sucursal.stock_actual' => 'Existencia actual del producto en la sucursal.',
        'operacion_idempotente.id_operacion' => 'Identificador de la operación idempotente.',
    ];

    return $descripciones[$tabla . '.' . $columna] ?? null;
}

function tipoLegible(object $fila): string
{
    $tipo = is_string($fila->data_type ?? null) ? $fila->data_type : '';
    $longitud = entero($fila->character_maximum_length ?? null);

    if ($tipo === '' ) {
        return 'No disponible';
    }

    if ($longitud !== null) {
        return $tipo . '(' . $longitud . ')';
    }

    if ($tipo === 'numeric' || $tipo === 'decimal') {
        $precision = entero($fila->numeric_precision ?? null);
        $escala = entero($fila->numeric_scale ?? null);

        if ($precision !== null && $escala !== null) {
            return $tipo . '(' . $precision . ',' . $escala . ')';
        }

        if ($precision !== null) {
            return $tipo . '(' . $precision . ')';
        }
    }

    return $tipo;
}

function referencia(mixed $tabla, mixed $columna): ?string
{
    if (!is_string($tabla) || !is_string($columna) || $tabla === '' || $columna === '') {
        return null;
    }

    return $tabla . '.' . $columna;
}

function predeterminado(mixed $valor): ?string
{
    if (!is_string($valor) || trim($valor) === '') {
        return null;
    }

    return $valor;
}

function textoComentario(mixed $valor): ?string
{
    if (!is_string($valor) || trim($valor) === '') {
        return null;
    }

    return $valor;
}

function texto(mixed $valor, string $predeterminado): string
{
    if (!is_string($valor) || trim($valor) === '') {
        return $predeterminado;
    }

    return trim($valor);
}

function entero(mixed $valor): ?int
{
    if (is_int($valor)) {
        return $valor;
    }

    if (is_string($valor) && preg_match('/^-?\d+$/', $valor) === 1) {
        return (int) $valor;
    }

    return null;
}

function esSi(mixed $valor): bool
{
    return $valor === 'YES' || $valor === true || $valor === 1 || $valor === '1' || $valor === 't';
}

function sesionAdministrador(): bool
{
    if (!isset($_COOKIE[session_name()])) {
        return false;
    }

    awr_iniciar_sesion();

    return isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
        && $_SESSION['rol'] === 'Administrador';
}

function motorSolicitado(array $entrada): ?string
{
    $motor = $entrada['motor'] ?? null;

    if ($motor === 'postgresql' || $motor === 'clickhouse') {
        return $motor;
    }

    return null;
}

function tablaSolicitada(array $entrada): ?string
{
    $tabla = $entrada['tabla'] ?? null;

    if (!is_string($tabla)) {
        return null;
    }

    $tabla = trim($tabla);

    if ($tabla === '' || strlen($tabla) > 63) {
        return null;
    }

    return $tabla;
}

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
