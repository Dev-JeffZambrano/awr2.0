<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/config/seguridad.php';

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
    responderAnalitica(405, [
        'exito' => false,
        'mensaje' => 'Método no permitido.',
    ]);
}

$longitud = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

if ($longitud < 0 || $longitud > 4096) {
    responderAnalitica(400, [
        'exito' => false,
        'mensaje' => 'Datos incompletos.',
    ]);
}

$crudo = file_get_contents('php://input');
$entrada = json_decode(is_string($crudo) ? $crudo : '', true);

if (!is_array($entrada)) {
    responderAnalitica(400, [
        'exito' => false,
        'mensaje' => 'Datos incompletos.',
    ]);
}

awr_exigir_csrf($entrada);

if (!sesionAdministrativaAnalitica()) {
    responderAnalitica(403, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

try {
    switch ($entrada['op'] ?? null) {
        case 'obtenerEstadoAnalitica':
            responderAnalitica(200, obtenerEstadoAnalitica());
            break;
        case 'exportarCsvAnalitica':
            exportarCsvAnalitica();
            break;
        default:
            responderAnalitica(400, [
                'exito' => false,
                'mensaje' => 'Operación no reconocida.',
            ]);
    }
} catch (Throwable $e) {
    error_log('AWR AnaliticaAdministrativoController: ' . $e->getMessage());
    responderAnalitica(500, [
        'exito' => false,
        'mensaje' => 'No disponible',
    ]);
}

function obtenerEstadoAnalitica(): array
{
    $raiz = dirname(__DIR__, 2);
    $rutaPbix = $raiz . DIRECTORY_SEPARATOR . 'documentacion' . DIRECTORY_SEPARATOR . 'AWR_Analitica.pbix';
    $informeDisponible = is_file($rutaPbix);

    $galeria = [
        [
            'id' => 'resumen',
            'titulo' => 'Vista completa',
            'ruta' => 'documentacion/powerbi/resumen-ejecutivo.png',
            'disponible' => is_file($raiz . '/documentacion/powerbi/resumen-ejecutivo.png'),
        ],
        [
            'id' => 'kpis',
            'titulo' => 'Indicadores KPI',
            'ruta' => 'documentacion/powerbi/resumen-kpis.png',
            'disponible' => is_file($raiz . '/documentacion/powerbi/resumen-kpis.png'),
        ],
        [
            'id' => 'graficos',
            'titulo' => 'Gráficos del informe',
            'ruta' => 'documentacion/powerbi/resumen-graficos.png',
            'disponible' => is_file($raiz . '/documentacion/powerbi/resumen-graficos.png'),
        ],
    ];

    $kpis = kpisClickHouseAnalitica();

    return [
        'exito' => true,
        'informe' => [
            'disponible' => $informeDisponible,
            'ruta' => 'documentacion/AWR_Analitica.pbix',
            'tamano_bytes' => $informeDisponible ? filesize($rutaPbix) : null,
            'actualizado' => $informeDisponible
                ? date('Y-m-d H:i:s', (int) filemtime($rutaPbix))
                : null,
        ],
        'galeria' => $galeria,
        'vista_previa' => [
            'disponible' => $informeDisponible && ($galeria[0]['disponible'] ?? false),
        ],
        'kpis' => $kpis,
        'csv' => [
            'disponible' => ($kpis['disponible'] ?? false) === true,
        ],
    ];
}

function exportarCsvAnalitica(): void
{
    $clickhouse = configuracionClickHouseAnalitica();
    $consulta = <<<'SQL'
SELECT
    nombre_sucursal,
    anio,
    mes,
    round(sum(total_linea), 2) AS ventas_totales,
    round(sum(subtotal), 2) AS subtotal,
    round(sum(costo_total), 2) AS costo_total,
    round(sum(margen), 2) AS margen_bruto,
    uniqExact(id_factura) AS facturas,
    round(sum(cantidad), 2) AS unidades
FROM vw_ventas_analiticas
GROUP BY nombre_sucursal, anio, mes
ORDER BY anio, mes, nombre_sucursal
FORMAT CSVWithNames
SQL;

    $csv = consultarClickHouseAnalitica($clickhouse, $consulta);

    if ($csv === null || trim($csv) === '') {
        responderAnalitica(500, [
            'exito' => false,
            'mensaje' => 'No disponible',
        ]);
    }

    $nombre = 'awr_ventas_analiticas_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Cache-Control: no-store');
    echo $csv;
    exit;
}

/**
 * @return array{disponible: bool, ventas_totales: ?string, facturas: ?string, ticket_promedio: ?string, margen_bruto: ?string, porcentaje_margen: ?string, lineas: ?string}
 */
function kpisClickHouseAnalitica(): array
{
    $vacio = [
        'disponible' => false,
        'ventas_totales' => null,
        'facturas' => null,
        'ticket_promedio' => null,
        'margen_bruto' => null,
        'porcentaje_margen' => null,
        'lineas' => null,
    ];

    try {
        $clickhouse = configuracionClickHouseAnalitica();
    } catch (Throwable $e) {
        return $vacio;
    }

    $consulta = <<<'SQL'
SELECT
    toString(round(sum(total_linea), 2)),
    toString(uniqExact(id_factura)),
    toString(round(ifNull(sum(total_linea) / nullIf(uniqExact(id_factura), 0), 0), 2)),
    toString(round(sum(margen), 2)),
    toString(round(ifNull(100 * sum(margen) / nullIf(sum(subtotal), 0), 0), 2)),
    toString(count())
FROM vw_ventas_analiticas
FORMAT TabSeparated
SQL;

    $texto = consultarClickHouseAnalitica($clickhouse, $consulta);

    if ($texto === null) {
        return $vacio;
    }

    $partes = preg_split("/\t/", trim($texto));

    if (!is_array($partes) || count($partes) < 6) {
        return $vacio;
    }

    return [
        'disponible' => true,
        'ventas_totales' => formatearNumeroAnalitica($partes[0], true),
        'facturas' => formatearNumeroAnalitica($partes[1], false),
        'ticket_promedio' => formatearNumeroAnalitica($partes[2], true),
        'margen_bruto' => formatearNumeroAnalitica($partes[3], true),
        'porcentaje_margen' => formatearNumeroAnalitica($partes[4], false) . ' %',
        'lineas' => formatearNumeroAnalitica($partes[5], false),
    ];
}

/**
 * @return array{host: string, puerto: string, base: string, usuario: string, password: string}
 */
function configuracionClickHouseAnalitica(): array
{
    $base = trim((string) ($_ENV['CLICKHOUSE_DATABASE'] ?? $_ENV['CLICKHOUSE_DB'] ?? ''));
    $host = trim((string) ($_ENV['CLICKHOUSE_HOST'] ?? '127.0.0.1'));
    $puerto = trim((string) ($_ENV['CLICKHOUSE_HTTP_PORT'] ?? '8123'));
    $usuario = trim((string) ($_ENV['CLICKHOUSE_POWERBI_USER'] ?? $_ENV['CLICKHOUSE_USER'] ?? ''));
    $password = (string) ($_ENV['CLICKHOUSE_POWERBI_PASSWORD'] ?? $_ENV['CLICKHOUSE_PASSWORD'] ?? '');

    if (
        preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $base) !== 1
        || $usuario === ''
        || $password === ''
        || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1
        || preg_match('/^[1-9][0-9]{0,4}$/', $puerto) !== 1
    ) {
        throw new RuntimeException('ClickHouse analítica no configurado.');
    }

    return [
        'host' => $host,
        'puerto' => $puerto,
        'base' => $base,
        'usuario' => $usuario,
        'password' => $password,
    ];
}

/**
 * @param array{host: string, puerto: string, base: string, usuario: string, password: string} $clickhouse
 */
function consultarClickHouseAnalitica(array $clickhouse, string $consulta): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $url = 'http://' . $clickhouse['host'] . ':' . $clickhouse['puerto']
        . '/?database=' . rawurlencode($clickhouse['base']);

    $curl = curl_init($url);

    if ($curl === false) {
        return null;
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $consulta,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: text/plain; charset=UTF-8'],
        CURLOPT_USERPWD => $clickhouse['usuario'] . ':' . $clickhouse['password'],
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 60,
    ]);

    $cuerpo = curl_exec($curl);
    $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($cuerpo) || $codigo !== 200) {
        return null;
    }

    return $cuerpo;
}

function formatearNumeroAnalitica(string $valor, bool $dinero): string
{
    if (!is_numeric($valor)) {
        return $valor;
    }

    $numero = (float) $valor;

    if ($dinero) {
        return '$ ' . number_format($numero, 2, ',', '.');
    }

    return number_format($numero, 0, ',', '.');
}

function sesionAdministrativaAnalitica(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        awr_iniciar_sesion();
    }

    return isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
        && $_SESSION['rol'] === 'Administrativo';
}

function responderAnalitica(int $codigo, array $cuerpo): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    exit;
}
