<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\EstadoSistemaService;
use Illuminate\Database\Capsule\Manager as DB;

const AWR_API_TOXIPROXY = 'http://localhost:8474';
const AWR_PROXY_TOXIPROXY = 'awr_postgres_proxy';
const AWR_TOXIC_LATENCIA = 'awr_latency';

if (PHP_SAPI !== 'cli') {
    atenderSolicitudConfiguracion();
}

function atenderSolicitudConfiguracion(): void
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

    if (!administradorEnSesion()) {
        responder(403, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $op = $entrada['op'] ?? null;

    try {
        switch ($op) {
            case 'obtenerConfiguracion':
                obtenerConfiguracion();
                break;
            case 'actualizarEmpresa':
                actualizarEmpresa($entrada);
                break;
            case 'actualizarFacturacion':
                actualizarFacturacion($entrada);
                break;
            case 'actualizarLatenciaToxiproxy':
                actualizarLatenciaToxiproxy($entrada);
                break;
            default:
                responder(400, [
                    'exito' => false,
                    'mensaje' => 'Operación no reconocida.',
                ]);
        }
    } catch (Throwable $e) {
        error_log('AWR configuracion: ' . $e->getMessage());
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }
}

function obtenerConfiguracion(): void
{
    $empresa = empresaActiva();
    $configuracion = configuracionEmpresa((int) $empresa->id_empresa);
    $estado = EstadoSistemaService::obtenerEstadoActual();

    responder(200, [
        'exito' => true,
        'empresa' => proyectarEmpresa($empresa),
        'configuracion_facturacion' => proyectarConfiguracion($configuracion),
        'referencias_facturacion' => referenciasFacturacion(),
        'integraciones' => proyectarIntegraciones($estado),
    ]);
}

function actualizarEmpresa(array $entrada): void
{
    $empresa = empresaActiva();
    $idEmpresa = (int) $empresa->id_empresa;
    $datos = [
        'nombre' => textoObligatorio($entrada, 'nombre', 150, 'Nombre no válido.'),
        'razon_social' => textoOpcional($entrada, 'razon_social', 200, 'Razón social no válida.'),
        'nombre_comercial' => textoOpcional($entrada, 'nombre_comercial', 200, 'Nombre comercial no válido.'),
        'identificacion' => textoObligatorio($entrada, 'identificacion', 20, 'Identificación no válida.'),
        'direccion' => textoOpcional($entrada, 'direccion', 255, 'Dirección no válida.'),
        'telefono' => textoOpcional($entrada, 'telefono', 30, 'Teléfono no válido.'),
        'correo' => textoOpcional($entrada, 'correo', 150, 'Correo no válido.'),
    ];

    $afectadas = DB::table('empresa')
        ->where('id_empresa', $idEmpresa)
        ->where('estado', true)
        ->update($datos);

    if ($afectadas !== 1) {
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $actual = DB::table('empresa')->where('id_empresa', $idEmpresa)->first();

    if ($actual === null) {
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    responder(200, [
        'exito' => true,
        'empresa' => proyectarEmpresa($actual),
    ]);
}

function actualizarFacturacion(array $entrada): void
{
    $empresa = empresaActiva();
    $configuracion = configuracionEmpresa((int) $empresa->id_empresa);
    exigirPresente($entrada, 'ambiente');

    if (!is_string($entrada['ambiente'])) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Ambiente no válido.',
        ]);
    }

    $ambiente = strtoupper(trim($entrada['ambiente']));

    if (!in_array($ambiente, ['PRUEBAS', 'PRODUCCION'], true)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Ambiente no válido.',
        ]);
    }

    $datos = [
        'ambiente' => $ambiente,
        'leyenda' => textoOpcional($entrada, 'leyenda', 255, 'Leyenda no válida.'),
    ];

    $afectadas = DB::table('configuracion_facturacion')
        ->where('id_configuracion', (int) $configuracion->id_configuracion)
        ->where('id_empresa', (int) $empresa->id_empresa)
        ->update($datos);

    if ($afectadas !== 1) {
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $actual = DB::table('configuracion_facturacion')
        ->where('id_configuracion', (int) $configuracion->id_configuracion)
        ->first();

    if ($actual === null) {
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    responder(200, [
        'exito' => true,
        'configuracion_facturacion' => proyectarConfiguracion($actual),
    ]);
}

function actualizarLatenciaToxiproxy(array $entrada): void
{
    if (!array_key_exists('latencia_ms', $entrada)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Latencia no válida.',
        ]);
    }

    $latencia = latenciaEntera($entrada['latencia_ms']);

    if ($latencia === null) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Latencia no válida.',
        ]);
    }

    $resultado = aplicarLatenciaToxiproxy($latencia);

    if ($resultado['exito'] === true) {
        responder(200, [
            'exito' => true,
            'latencia_configurada_ms' => $resultado['latencia_configurada_ms'],
        ]);
    }

    responder(200, [
        'exito' => false,
        'mensaje' => $resultado['mensaje'],
    ]);
}

function empresaActiva(): object
{
    $filas = DB::table('empresa')->where('estado', true)->get();

    if ($filas->count() !== 1) {
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    return $filas->first();
}

function configuracionEmpresa(int $idEmpresa): object
{
    $filas = DB::table('configuracion_facturacion')->where('id_empresa', $idEmpresa)->get();

    if ($filas->count() !== 1) {
        responder(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    return $filas->first();
}

function proyectarEmpresa(object $empresa): array
{
    return [
        'id_empresa' => (int) $empresa->id_empresa,
        'nombre' => (string) $empresa->nombre,
        'razon_social' => $empresa->razon_social !== null ? (string) $empresa->razon_social : null,
        'nombre_comercial' => $empresa->nombre_comercial !== null ? (string) $empresa->nombre_comercial : null,
        'identificacion' => $empresa->identificacion !== null ? (string) $empresa->identificacion : null,
        'direccion' => $empresa->direccion !== null ? (string) $empresa->direccion : null,
        'telefono' => $empresa->telefono !== null ? (string) $empresa->telefono : null,
        'correo' => $empresa->correo !== null ? (string) $empresa->correo : null,
        'estado' => esVerdadero($empresa->estado),
        'fecha_creacion' => $empresa->fecha_creacion !== null ? (string) $empresa->fecha_creacion : null,
        'fecha_actualizacion' => $empresa->fecha_actualizacion !== null ? (string) $empresa->fecha_actualizacion : null,
    ];
}

function proyectarConfiguracion(object $configuracion): array
{
    return [
        'id_configuracion' => (int) $configuracion->id_configuracion,
        'id_empresa' => (int) $configuracion->id_empresa,
        'moneda' => (string) $configuracion->moneda,
        'ambiente' => (string) $configuracion->ambiente,
        'tipo_emision' => (string) $configuracion->tipo_emision,
        'leyenda' => $configuracion->leyenda !== null ? (string) $configuracion->leyenda : null,
        'estado' => esVerdadero($configuracion->estado),
        'fecha_creacion' => $configuracion->fecha_creacion !== null ? (string) $configuracion->fecha_creacion : null,
        'fecha_actualizacion' => $configuracion->fecha_actualizacion !== null ? (string) $configuracion->fecha_actualizacion : null,
    ];
}

function referenciasFacturacion(): array
{
    $tipos = DB::table('tipo_comprobante')
        ->orderBy('codigo')
        ->orderBy('id_tipo_comprobante')
        ->get();
    $impuestos = DB::table('impuesto')
        ->orderBy('codigo')
        ->orderBy('id_impuesto')
        ->get();
    $puntos = DB::table('punto_emision as pe')
        ->leftJoin('sucursal as s', 's.id_sucursal', '=', 'pe.id_sucursal')
        ->orderBy('s.nombre')
        ->orderBy('pe.codigo')
        ->orderBy('pe.id_punto_emision')
        ->get([
            'pe.id_punto_emision',
            'pe.codigo',
            'pe.descripcion',
            'pe.estado',
            's.nombre as sucursal',
        ]);
    $secuenciales = DB::table('secuencial_comprobante as sc')
        ->join('punto_emision as pe', 'pe.id_punto_emision', '=', 'sc.id_punto_emision')
        ->join('tipo_comprobante as tc', 'tc.id_tipo_comprobante', '=', 'sc.id_tipo_comprobante')
        ->leftJoin('sucursal as s', 's.id_sucursal', '=', 'pe.id_sucursal')
        ->orderBy('s.nombre')
        ->orderBy('pe.codigo')
        ->orderBy('tc.codigo')
        ->get([
            'pe.codigo as punto',
            's.nombre as sucursal',
            'tc.codigo as tipo',
            'sc.ultimo_numero',
        ]);

    return [
        'tipo_comprobante' => array_map(static function (object $fila): array {
            return [
                'id_tipo_comprobante' => (int) $fila->id_tipo_comprobante,
                'codigo' => (string) $fila->codigo,
                'nombre' => (string) $fila->nombre,
                'estado' => esVerdadero($fila->estado),
            ];
        }, $tipos->all()),
        'impuesto' => array_map(static function (object $fila): array {
            return [
                'id_impuesto' => (int) $fila->id_impuesto,
                'codigo' => (string) $fila->codigo,
                'nombre' => (string) $fila->nombre,
                'porcentaje' => $fila->porcentaje !== null ? (string) $fila->porcentaje : null,
                'estado' => esVerdadero($fila->estado),
            ];
        }, $impuestos->all()),
        'punto_emision' => array_map(static function (object $fila): array {
            return [
                'id_punto_emision' => (int) $fila->id_punto_emision,
                'codigo' => (string) $fila->codigo,
                'descripcion' => $fila->descripcion !== null ? (string) $fila->descripcion : null,
                'sucursal' => $fila->sucursal !== null ? (string) $fila->sucursal : null,
                'estado' => esVerdadero($fila->estado),
            ];
        }, $puntos->all()),
        'secuencial_comprobante' => array_map(static function (object $fila): array {
            return [
                'punto' => (string) $fila->punto,
                'sucursal' => $fila->sucursal !== null ? (string) $fila->sucursal : null,
                'tipo' => (string) $fila->tipo,
                'ultimo_numero' => (int) $fila->ultimo_numero,
            ];
        }, $secuenciales->all()),
    ];
}

function proyectarIntegraciones(array $estado): array
{
    $toxiproxy = is_array($estado['toxiproxy'] ?? null) ? $estado['toxiproxy'] : [];
    $redpanda = is_array($estado['redpanda'] ?? null) ? $estado['redpanda'] : [];
    $clickhouse = is_array($estado['clickhouse'] ?? null) ? $estado['clickhouse'] : [];

    return [
        'toxiproxy' => [
            'estado' => is_string($toxiproxy['estado'] ?? null) ? $toxiproxy['estado'] : 'No disponible',
            'proxy' => is_string($toxiproxy['proxy'] ?? null) ? $toxiproxy['proxy'] : null,
            'latencia_configurada_ms' => array_key_exists('latencia_configurada_ms', $toxiproxy)
                ? $toxiproxy['latencia_configurada_ms']
                : null,
        ],
        'redpanda' => [
            'estado' => is_string($redpanda['estado'] ?? null) && $redpanda['estado'] !== ''
                ? $redpanda['estado']
                : 'No integrado',
        ],
        'clickhouse' => [
            'estado' => is_string($clickhouse['estado'] ?? null) && $clickhouse['estado'] !== ''
                ? $clickhouse['estado']
                : 'No integrado',
        ],
    ];
}

function exigirPresente(array $entrada, string $clave): void
{
    if (!array_key_exists($clave, $entrada)) {
        responder(400, [
            'exito' => false,
            'mensaje' => 'Datos incompletos.',
        ]);
    }
}

function textoObligatorio(array $entrada, string $clave, int $maximo, string $mensaje): string
{
    exigirPresente($entrada, $clave);
    $valor = $entrada[$clave];

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

function textoOpcional(array $entrada, string $clave, int $maximo, string $mensaje): ?string
{
    exigirPresente($entrada, $clave);
    $valor = $entrada[$clave];

    if ($valor === null) {
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

function latenciaEntera(mixed $valor): ?int
{
    if (!is_int($valor) || $valor < 0 || $valor > 3000) {
        return null;
    }

    return $valor;
}

function aplicarLatenciaToxiproxy(
    int $latenciaMs,
    string $api = AWR_API_TOXIPROXY,
    string $proxy = AWR_PROXY_TOXIPROXY
): array {
    if ($latenciaMs < 0 || $latenciaMs > 3000) {
        return [
            'exito' => false,
            'mensaje' => 'Latencia no válida.',
        ];
    }

    $proxyDisponible = verificarProxyToxiproxy($api, $proxy);

    if ($proxyDisponible['ok'] !== true) {
        return falloToxiproxy($proxyDisponible['motivo'] ?? 'api');
    }

    $lista = listarToxicsToxiproxy($api, $proxy);

    if ($lista['ok'] !== true) {
        return falloToxiproxy($lista['motivo'] ?? 'api');
    }

    $existe = toxicLatenciaExiste($lista['toxicos']);

    if ($latenciaMs === 0) {
        if ($existe) {
            $borrado = eliminarToxicToxiproxy($api, $proxy);

            if ($borrado['ok'] !== true) {
                return falloToxiproxy($borrado['motivo'] ?? 'escritura');
            }
        }
    } elseif ($existe) {
        $actualizado = actualizarToxicToxiproxy($api, $proxy, $latenciaMs);

        if ($actualizado['ok'] !== true) {
            return falloToxiproxy($actualizado['motivo'] ?? 'escritura');
        }
    } else {
        $creado = crearToxicToxiproxy($api, $proxy, $latenciaMs);

        if ($creado['ok'] !== true) {
            return falloToxiproxy($creado['motivo'] ?? 'escritura');
        }
    }

    $confirmacion = confirmarLatenciaToxiproxy($api, $proxy);

    if ($confirmacion['ok'] !== true) {
        return falloToxiproxy($confirmacion['motivo'] ?? 'confirmacion');
    }

    $confirmada = $confirmacion['latencia'];
    $presente = $confirmacion['presente'] === true;

    if ($latenciaMs === 0 && !$presente && $confirmada === 0) {
        return [
            'exito' => true,
            'latencia_configurada_ms' => 0,
        ];
    }

    if ($latenciaMs > 0 && $presente && $confirmada === $latenciaMs) {
        return [
            'exito' => true,
            'latencia_configurada_ms' => $confirmada,
        ];
    }

    return falloToxiproxy('confirmacion');
}

function solicitarApiToxiproxy(string $api, string $metodo, string $ruta, ?array $cuerpo = null): array
{
    $vacia = [
        'conexion' => false,
        'codigo' => 0,
        'datos' => null,
    ];

    if (!function_exists('curl_init')) {
        return $vacia;
    }

    $curl = curl_init($api . $ruta);

    if ($curl === false) {
        return $vacia;
    }

    $cabeceras = [
        'Accept: application/json',
        'User-Agent: toxiproxy-cli',
    ];

    if ($cuerpo !== null) {
        $cabeceras[] = 'Content-Type: application/json';
    }

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => $cabeceras,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POSTFIELDS => $cuerpo === null ? null : json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
    ]);

    $crudo = curl_exec($curl);
    $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($crudo === false || $codigo === 0) {
        return $vacia;
    }

    $datos = json_decode(is_string($crudo) ? $crudo : '', true);

    return [
        'conexion' => true,
        'codigo' => $codigo,
        'datos' => is_array($datos) ? $datos : null,
    ];
}

function verificarProxyToxiproxy(string $api, string $proxy): array
{
    $respuesta = solicitarApiToxiproxy($api, 'GET', '/proxies/' . rawurlencode($proxy));

    if ($respuesta['conexion'] !== true) {
        return ['ok' => false, 'motivo' => 'api'];
    }

    if ($respuesta['codigo'] !== 200 || !is_array($respuesta['datos'])) {
        return ['ok' => false, 'motivo' => 'proxy'];
    }

    if (($respuesta['datos']['name'] ?? null) !== $proxy) {
        return ['ok' => false, 'motivo' => 'proxy'];
    }

    return ['ok' => true];
}

function listarToxicsToxiproxy(string $api, string $proxy): array
{
    $respuesta = solicitarApiToxiproxy($api, 'GET', '/proxies/' . rawurlencode($proxy) . '/toxics');

    if ($respuesta['conexion'] !== true) {
        return ['ok' => false, 'motivo' => 'api'];
    }

    if ($respuesta['codigo'] === 404) {
        return ['ok' => false, 'motivo' => 'proxy'];
    }

    if ($respuesta['codigo'] !== 200 || !is_array($respuesta['datos']) || !array_is_list($respuesta['datos'])) {
        return ['ok' => false, 'motivo' => 'api'];
    }

    return [
        'ok' => true,
        'toxicos' => $respuesta['datos'],
    ];
}

function crearToxicToxiproxy(string $api, string $proxy, int $latenciaMs): array
{
    $respuesta = solicitarApiToxiproxy($api, 'POST', '/proxies/' . rawurlencode($proxy) . '/toxics', [
        'name' => AWR_TOXIC_LATENCIA,
        'type' => 'latency',
        'stream' => 'downstream',
        'toxicity' => 1,
        'attributes' => [
            'latency' => $latenciaMs,
            'jitter' => 0,
        ],
    ]);

    return resultadoEscrituraToxiproxy($respuesta);
}

function actualizarToxicToxiproxy(string $api, string $proxy, int $latenciaMs): array
{
    $respuesta = solicitarApiToxiproxy(
        $api,
        'POST',
        '/proxies/' . rawurlencode($proxy) . '/toxics/' . rawurlencode(AWR_TOXIC_LATENCIA),
        [
            'attributes' => [
                'latency' => $latenciaMs,
                'jitter' => 0,
            ],
        ]
    );

    return resultadoEscrituraToxiproxy($respuesta);
}

function eliminarToxicToxiproxy(string $api, string $proxy): array
{
    $respuesta = solicitarApiToxiproxy(
        $api,
        'DELETE',
        '/proxies/' . rawurlencode($proxy) . '/toxics/' . rawurlencode(AWR_TOXIC_LATENCIA)
    );

    if ($respuesta['conexion'] !== true) {
        return ['ok' => false, 'motivo' => 'api'];
    }

    if (in_array($respuesta['codigo'], [200, 204, 404], true)) {
        return ['ok' => true];
    }

    return ['ok' => false, 'motivo' => 'escritura'];
}

function confirmarLatenciaToxiproxy(string $api, string $proxy): array
{
    $lista = listarToxicsToxiproxy($api, $proxy);

    if ($lista['ok'] !== true) {
        return [
            'ok' => false,
            'motivo' => $lista['motivo'] ?? 'api',
        ];
    }

    foreach ($lista['toxicos'] as $toxic) {
        if (!is_array($toxic) || ($toxic['name'] ?? null) !== AWR_TOXIC_LATENCIA) {
            continue;
        }

        if (($toxic['type'] ?? null) !== 'latency') {
            return ['ok' => false, 'motivo' => 'confirmacion'];
        }

        $latencia = latenciaLeidaApi($toxic['attributes']['latency'] ?? null);

        if ($latencia === null) {
            return ['ok' => false, 'motivo' => 'confirmacion'];
        }

        return [
            'ok' => true,
            'presente' => true,
            'latencia' => $latencia,
        ];
    }

    return [
        'ok' => true,
        'presente' => false,
        'latencia' => 0,
    ];
}

function resultadoEscrituraToxiproxy(array $respuesta): array
{
    if ($respuesta['conexion'] !== true) {
        return ['ok' => false, 'motivo' => 'api'];
    }

    if ($respuesta['codigo'] === 404) {
        return ['ok' => false, 'motivo' => 'proxy'];
    }

    if (!in_array($respuesta['codigo'], [200, 201], true)) {
        return ['ok' => false, 'motivo' => 'escritura'];
    }

    return ['ok' => true];
}

function toxicLatenciaExiste(array $toxicos): bool
{
    foreach ($toxicos as $toxic) {
        if (is_array($toxic) && ($toxic['name'] ?? null) === AWR_TOXIC_LATENCIA) {
            return true;
        }
    }

    return false;
}

function latenciaLeidaApi(mixed $valor): ?int
{
    if (is_int($valor) || (is_string($valor) && preg_match('/^\d+$/', $valor) === 1)) {
        return (int) $valor;
    }

    if (is_float($valor) && floor($valor) === $valor) {
        return (int) $valor;
    }

    return null;
}

function falloToxiproxy(string $motivo): array
{
    if ($motivo === 'proxy') {
        error_log('AWR actualizarLatenciaToxiproxy: proxy no disponible');

        return [
            'exito' => false,
            'mensaje' => 'El proxy no está disponible',
        ];
    }

    if ($motivo === 'api') {
        error_log('AWR actualizarLatenciaToxiproxy: API no disponible');

        return [
            'exito' => false,
            'mensaje' => 'Toxiproxy no está disponible',
        ];
    }

    error_log('AWR actualizarLatenciaToxiproxy: no se pudo confirmar la latencia');

    return [
        'exito' => false,
        'mensaje' => 'No se pudo confirmar la latencia',
    ];
}

function administradorEnSesion(): bool
{
    if (!isset($_COOKIE[session_name()])) {
        return false;
    }

    session_start();

    return isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
        && $_SESSION['rol'] === 'Administrador';
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
