<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Solo CLI';
    exit(1);
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

const LOTE = 5000;
const PRUEBA = 100;
const ID_PRUEBA_DESDE = 900000000;

$reiniciar = in_array('--reiniciar', $argv ?? [], true);
$continuar = in_array('--continuar', $argv ?? [], true);
$soloPrueba = in_array('--solo-prueba', $argv ?? [], true);

if ($reiniciar && $continuar) {
    fwrite(STDERR, "Use solo una opción: --reiniciar o --continuar." . PHP_EOL);
    exit(1);
}

try {
    $clickhouse = configuracionClickHouse();
    $totalPostgres = totalLineasPostgres();
    validarPagos();

    $reales = contarClickHouse(
        $clickhouse,
        'SELECT count() FROM fact_venta_linea WHERE id_factura_detalle < ' . ID_PRUEBA_DESDE
    );

    if ($reiniciar) {
        clickHouse($clickhouse, 'TRUNCATE TABLE fact_venta_linea', '', true);
        echo "Se vació fact_venta_linea por --reiniciar." . PHP_EOL;
        $reales = 0;
    } elseif ($reales === $totalPostgres && $totalPostgres > 0) {
        echo "El histórico ya está cargado ({$reales} líneas). No se insertó nada." . PHP_EOL;
        echo "Para repetir la carga: php tools/clickhouse/cargarHistoricoVentas.php --reiniciar" . PHP_EOL;
        exit(0);
    } elseif ($reales > $totalPostgres) {
        fwrite(STDERR, "ClickHouse tiene {$reales} líneas reales y PostgreSQL {$totalPostgres}. No se insertó nada." . PHP_EOL);
        exit(1);
    } elseif ($reales > 0 && !$continuar) {
        fwrite(STDERR, "Hay una carga parcial ({$reales} / {$totalPostgres})." . PHP_EOL);
        fwrite(STDERR, "Para reanudar: php tools/clickhouse/cargarHistoricoVentas.php --continuar" . PHP_EOL);
        fwrite(STDERR, "Para empezar de nuevo: php tools/clickhouse/cargarHistoricoVentas.php --reiniciar" . PHP_EOL);
        exit(1);
    }

    $pruebas = filasPrueba($clickhouse);
    if ($pruebas !== []) {
        $ids = array_map(static fn (array $fila): int => $fila['id'], $pruebas);
        $lista = implode(',', $ids);
        clickHouse(
            $clickhouse,
            'DELETE FROM fact_venta_linea WHERE id_factura_detalle IN (' . $lista . ') SETTINGS mutations_sync = 1',
            '',
            true
        );
        echo 'Filas de prueba eliminadas de ClickHouse: ' . implode(', ', $ids) . PHP_EOL;
    }

    $ultimoId = $reales > 0 ? ultimoIdReal($clickhouse) : 0;
    $insertadas = $reales;
    $lote = 0;
    $validacionInicial = $reales > 0;
    $inicio = microtime(true);
    $insertadasEnEstaEjecucion = 0;

    echo "Líneas en PostgreSQL: {$totalPostgres}" . PHP_EOL;

    while (true) {
        $limite = $validacionInicial ? LOTE : PRUEBA;
        $filas = leerLineas($ultimoId, $limite);

        if ($filas === []) {
            break;
        }

        $lote++;
        $ultimoAnterior = $ultimoId;

        try {
            insertarLote($clickhouse, $filas);
        } catch (Throwable $e) {
            fwrite(STDERR, "Error en el lote {$lote}." . PHP_EOL);
            fwrite(STDERR, "Último id procesado: {$ultimoAnterior}" . PHP_EOL);
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }

        $ultimoId = (int) $filas[array_key_last($filas)]['id_factura_detalle'];
        $insertadas += count($filas);
        $insertadasEnEstaEjecucion += count($filas);
        echo "{$insertadas} / {$totalPostgres}" . PHP_EOL;

        if (!$validacionInicial) {
            try {
                validarMuestra($clickhouse, $filas);
            } catch (Throwable $e) {
                $ids = array_map(static fn (array $fila): int => (int) $fila['id_factura_detalle'], $filas);
                clickHouse(
                    $clickhouse,
                    'DELETE FROM fact_venta_linea WHERE id_factura_detalle IN (' . implode(',', $ids) . ') SETTINGS mutations_sync = 1',
                    '',
                    true
                );
                fwrite(STDERR, "La prueba de " . count($filas) . " líneas no coincide con PostgreSQL." . PHP_EOL);
                fwrite(STDERR, "Último id procesado: {$ultimoAnterior}" . PHP_EOL);
                fwrite(STDERR, $e->getMessage() . PHP_EOL);
                exit(1);
            }

            echo 'Prueba de ' . count($filas) . ' líneas: tipos, UUID, fechas, decimales y valores coinciden con PostgreSQL.' . PHP_EOL;
            $validacionInicial = true;

            if ($soloPrueba) {
                echo "Prueba detenida antes de la carga completa. Faltan " . ($totalPostgres - $insertadas) . " líneas." . PHP_EOL;
                exit(0);
            }
        }
    }

    $segundos = microtime(true) - $inicio;
    $enClickHouse = contarClickHouse($clickhouse, 'SELECT count() FROM fact_venta_linea');

    if ($enClickHouse !== $totalPostgres) {
        fwrite(STDERR, "La carga no coincide. PostgreSQL: {$totalPostgres}. ClickHouse: {$enClickHouse}." . PHP_EOL);
        fwrite(STDERR, "Último id procesado: {$ultimoId}" . PHP_EOL);
        exit(1);
    }

    $metricas = compararMetricas($clickhouse);
    if ($metricas !== []) {
        fwrite(STDERR, "Las métricas no coinciden." . PHP_EOL);
        foreach ($metricas as $diferencia) {
            fwrite(STDERR, $diferencia . PHP_EOL);
        }
        exit(1);
    }

    $muestras = compararMuestraFacturas($clickhouse);
    if ($muestras !== []) {
        fwrite(STDERR, "La muestra de facturas no coincide." . PHP_EOL);
        foreach ($muestras as $diferencia) {
            fwrite(STDERR, $diferencia . PHP_EOL);
        }
        exit(1);
    }

    $porSegundo = $segundos > 0 ? (int) round($insertadasEnEstaEjecucion / $segundos) : $insertadasEnEstaEjecucion;
    $resumen = resumenClickHouse($clickhouse);

    echo "Carga completada." . PHP_EOL;
    echo "Filas insertadas en esta ejecución: {$insertadasEnEstaEjecucion}" . PHP_EOL;
    echo 'Tiempo: ' . number_format($segundos, 1, '.', '') . ' s' . PHP_EOL;
    echo "Filas por segundo: {$porSegundo}" . PHP_EOL;
    echo "Facturas distintas: {$resumen['facturas']}" . PHP_EOL;
    echo "Suma total_linea: {$resumen['suma_total']}" . PHP_EOL;
    echo "Suma cantidad: {$resumen['suma_cantidad']}" . PHP_EOL;
    echo "Fecha mínima: {$resumen['min_fecha']}" . PHP_EOL;
    echo "Fecha máxima: {$resumen['max_fecha']}" . PHP_EOL;
    echo "Tamaño: {$resumen['tamano']}" . PHP_EOL;
    echo "Particiones: {$resumen['particiones']}" . PHP_EOL;
    echo "Muestra de 5 facturas: coincide con PostgreSQL." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

function configuracionClickHouse(): array
{
    $base = trim((string) ($_ENV['CLICKHOUSE_DATABASE'] ?? $_ENV['CLICKHOUSE_DB'] ?? ''));
    $usuario = trim((string) ($_ENV['CLICKHOUSE_USER'] ?? ''));
    $password = (string) ($_ENV['CLICKHOUSE_PASSWORD'] ?? '');
    $host = trim((string) ($_ENV['CLICKHOUSE_HOST'] ?? '127.0.0.1'));
    $puerto = trim((string) ($_ENV['CLICKHOUSE_HTTP_PORT'] ?? '8123'));

    if ($base === '' || $usuario === '' || $password === '' || $host === '' || $puerto === '') {
        throw new RuntimeException('ClickHouse no está configurado.');
    }

    return [
        'base' => $base,
        'usuario' => $usuario,
        'password' => $password,
        'host' => $host,
        'puerto' => $puerto,
    ];
}

function validarPagos(): void
{
    $varios = Capsule::select(
        'SELECT COUNT(*) AS total
         FROM (
            SELECT id_factura
            FROM pago
            GROUP BY id_factura
            HAVING COUNT(*) > 1
         ) pagos'
    );
    $sinPago = Capsule::select(
        'SELECT COUNT(*) AS total
         FROM factura f
         WHERE NOT EXISTS (
            SELECT 1 FROM pago p WHERE p.id_factura = f.id_factura
         )'
    );

    $conVarios = (int) $varios[0]->total;
    $sin = (int) $sinPago[0]->total;

    if ($conVarios > 0 || $sin > 0) {
        throw new RuntimeException(
            "La carga se detuvo: {$conVarios} facturas tienen varios pagos y {$sin} no tienen pago."
        );
    }
}

function totalLineasPostgres(): int
{
    $filas = Capsule::select('SELECT COUNT(*) AS total FROM factura_detalle');

    return (int) $filas[0]->total;
}

function leerLineas(int $ultimoId, int $limite): array
{
    $limite = max(1, $limite);
    $filas = Capsule::select(
        'SELECT
            fd.id_factura_detalle,
            fd.id_factura,
            f.id_operacion::text AS id_operacion,
            to_char(f.fecha_emision AT TIME ZONE \'UTC\', \'YYYY-MM-DD HH24:MI:SS.MS\') AS fecha_emision,
            f.numero_comprobante,
            f.id_sucursal,
            f.nombre_sucursal,
            f.id_punto_emision,
            f.codigo_punto_emision,
            f.id_cliente,
            f.tipo_identificacion_cliente,
            f.identificacion_cliente,
            f.razon_social_cliente,
            fd.id_producto,
            fd.codigo_producto,
            fd.nombre_producto,
            fd.nombre_categoria,
            fd.cantidad,
            fd.precio_unitario::text AS precio_unitario,
            fd.costo_unitario::text AS costo_unitario,
            fd.descuento::text AS descuento,
            fd.porcentaje_impuesto::text AS porcentaje_impuesto,
            fd.subtotal::text AS subtotal,
            fd.valor_impuesto::text AS valor_impuesto,
            fd.total_linea::text AS total_linea,
            p.id_metodo_pago,
            mp.nombre AS nombre_metodo_pago
         FROM factura_detalle fd
         INNER JOIN factura f ON f.id_factura = fd.id_factura
         INNER JOIN pago p ON p.id_factura = f.id_factura
         INNER JOIN metodo_pago mp ON mp.id_metodo_pago = p.id_metodo_pago
         WHERE fd.id_factura_detalle > ?
         ORDER BY fd.id_factura_detalle
         LIMIT ' . $limite,
        [$ultimoId]
    );

    $salida = [];

    foreach ($filas as $fila) {
        $salida[] = normalizarFila((array) $fila);
    }

    return $salida;
}

function normalizarFila(array $fila): array
{
    $enteros = [
        'id_factura_detalle',
        'id_factura',
        'id_sucursal',
        'id_punto_emision',
        'id_cliente',
        'id_producto',
        'cantidad',
        'id_metodo_pago',
    ];
    $decimales = [
        'precio_unitario',
        'costo_unitario',
        'descuento',
        'porcentaje_impuesto',
        'subtotal',
        'valor_impuesto',
        'total_linea',
    ];
    $textos = [
        'id_operacion',
        'fecha_emision',
        'numero_comprobante',
        'nombre_sucursal',
        'codigo_punto_emision',
        'tipo_identificacion_cliente',
        'identificacion_cliente',
        'razon_social_cliente',
        'codigo_producto',
        'nombre_producto',
        'nombre_categoria',
        'nombre_metodo_pago',
    ];

    foreach (array_merge($enteros, $decimales, $textos) as $campo) {
        if (!array_key_exists($campo, $fila) || $fila[$campo] === null) {
            throw new RuntimeException('Una línea histórica tiene un dato vacío.');
        }
    }

    $normal = [];

    foreach ($enteros as $campo) {
        $normal[$campo] = (int) $fila[$campo];
    }

    $normal['id_operacion'] = strtolower((string) $fila['id_operacion']);
    $normal['fecha_emision'] = (string) $fila['fecha_emision'];

    foreach ($textos as $campo) {
        if ($campo === 'id_operacion' || $campo === 'fecha_emision') {
            continue;
        }
        $normal[$campo] = (string) $fila[$campo];
    }

    foreach ($decimales as $campo) {
        $normal[$campo] = decimal((string) $fila[$campo]);
    }

    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $normal['id_operacion']) !== 1) {
        throw new RuntimeException('Una línea histórica tiene un id_operacion inválido.');
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/', $normal['fecha_emision']) !== 1) {
        throw new RuntimeException('Una línea histórica tiene una fecha inválida.');
    }

    return $normal;
}

function decimal(string $valor): string
{
    if (!is_numeric($valor)) {
        throw new RuntimeException('Una línea histórica tiene un decimal inválido.');
    }

    return bcadd($valor, '0', 2);
}

function insertarLote(array $clickhouse, array $filas): void
{
    $lineas = [];

    foreach ($filas as $fila) {
        $lineas[] = json_encode($fila, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    clickHouse(
        $clickhouse,
        'INSERT INTO fact_venta_linea FORMAT JSONEachRow',
        implode("\n", $lineas) . "\n",
        true
    );
}

function validarMuestra(array $clickhouse, array $filas): void
{
    $ids = array_map(static fn (array $fila): int => $fila['id_factura_detalle'], $filas);
    $consulta = 'SELECT * FROM fact_venta_linea WHERE id_factura_detalle IN ('
        . implode(',', $ids)
        . ') ORDER BY id_factura_detalle FORMAT JSONEachRow';
    $cuerpo = clickHouse($clickhouse, $consulta, '', false);
    $recibidas = [];

    foreach (preg_split("/\r\n|\n|\r/", trim($cuerpo)) as $linea) {
        if ($linea === '') {
            continue;
        }
        $fila = json_decode($linea, true, 512, JSON_THROW_ON_ERROR);
        $recibidas[(int) $fila['id_factura_detalle']] = $fila;
    }

    if (count($recibidas) !== count($filas)) {
        throw new RuntimeException('ClickHouse devolvió ' . count($recibidas) . ' filas de ' . count($filas) . '.');
    }

    foreach ($filas as $esperada) {
        $id = $esperada['id_factura_detalle'];
        $real = $recibidas[$id] ?? null;

        if (!is_array($real)) {
            throw new RuntimeException("Falta la línea {$id} en ClickHouse.");
        }

        foreach ($esperada as $campo => $valor) {
            $obtenido = $real[$campo] ?? null;

            if (in_array($campo, ['precio_unitario', 'costo_unitario', 'descuento', 'porcentaje_impuesto', 'subtotal', 'valor_impuesto', 'total_linea'], true)) {
                $obtenido = decimal((string) $obtenido);
            } elseif (is_int($valor)) {
                $obtenido = (int) $obtenido;
            } else {
                $obtenido = (string) $obtenido;
            }

            if ($obtenido !== $valor) {
                throw new RuntimeException("La línea {$id} no coincide en {$campo}.");
            }
        }
    }
}

function filasPrueba(array $clickhouse): array
{
    $cuerpo = clickHouse(
        $clickhouse,
        'SELECT id_factura_detalle AS id FROM fact_venta_linea WHERE id_factura_detalle >= '
        . ID_PRUEBA_DESDE
        . ' ORDER BY id_factura_detalle FORMAT JSONEachRow',
        '',
        false
    );
    $filas = [];

    foreach (preg_split("/\r\n|\n|\r/", trim($cuerpo)) as $linea) {
        if ($linea === '') {
            continue;
        }
        $filas[] = json_decode($linea, true, 512, JSON_THROW_ON_ERROR);
    }

    return $filas;
}

function ultimoIdReal(array $clickhouse): int
{
    return contarClickHouse(
        $clickhouse,
        'SELECT max(id_factura_detalle) FROM fact_venta_linea WHERE id_factura_detalle < ' . ID_PRUEBA_DESDE
    );
}

function contarClickHouse(array $clickhouse, string $consulta): int
{
    return (int) trim(clickHouse($clickhouse, $consulta, '', false));
}

function compararMetricas(array $clickhouse): array
{
    $postgres = Capsule::select(
        'SELECT
            COUNT(*) AS lineas,
            COUNT(DISTINCT fd.id_factura) AS facturas,
            SUM(fd.total_linea)::text AS suma_total,
            SUM(fd.cantidad)::text AS suma_cantidad,
            to_char(MIN(f.fecha_emision) AT TIME ZONE \'UTC\', \'YYYY-MM-DD HH24:MI:SS.MS\') AS min_fecha,
            to_char(MAX(f.fecha_emision) AT TIME ZONE \'UTC\', \'YYYY-MM-DD HH24:MI:SS.MS\') AS max_fecha
         FROM factura_detalle fd
         INNER JOIN factura f ON f.id_factura = fd.id_factura'
    )[0];

    $click = json_decode(clickHouse(
        $clickhouse,
        'SELECT
            toString(count()) AS lineas,
            toString(uniqExact(id_factura)) AS facturas,
            toString(sum(total_linea)) AS suma_total,
            toString(sum(cantidad)) AS suma_cantidad,
            toString(min(fecha_emision)) AS min_fecha,
            toString(max(fecha_emision)) AS max_fecha
         FROM fact_venta_linea
         FORMAT JSONEachRow',
        '',
        false
    ), true, 512, JSON_THROW_ON_ERROR);

    $pares = [
        'líneas' => [(string) $postgres->lineas, (string) $click['lineas']],
        'facturas' => [(string) $postgres->facturas, (string) $click['facturas']],
        'suma total_linea' => [decimal((string) $postgres->suma_total), decimal((string) $click['suma_total'])],
        'suma cantidad' => [(string) (int) $postgres->suma_cantidad, (string) (int) $click['suma_cantidad']],
        'fecha mínima' => [(string) $postgres->min_fecha, (string) $click['min_fecha']],
        'fecha máxima' => [(string) $postgres->max_fecha, (string) $click['max_fecha']],
    ];
    $diferencias = [];

    foreach ($pares as $nombre => [$esperado, $obtenido]) {
        if ($esperado !== $obtenido) {
            $diferencias[] = "{$nombre}: PostgreSQL {$esperado}; ClickHouse {$obtenido}";
        }
    }

    return $diferencias;
}

function compararMuestraFacturas(array $clickhouse): array
{
    $facturas = Capsule::select(
        'SELECT id_factura
         FROM (
            SELECT id_factura,
                   row_number() OVER (ORDER BY id_factura) AS rn,
                   COUNT(*) OVER () AS total
            FROM factura
         ) muestra
         WHERE rn IN (1, total / 5, total / 2, (total * 4) / 5, total)
         ORDER BY id_factura'
    );
    $diferencias = [];

    foreach ($facturas as $factura) {
        $id = (int) $factura->id_factura;
        $postgres = leerLineasFactura($id);
        $click = lineasClickHouse($clickhouse, $id);

        if (count($postgres) !== count($click)) {
            $diferencias[] = "Factura {$id}: PostgreSQL " . count($postgres) . " líneas; ClickHouse " . count($click) . '.';
            continue;
        }

        foreach ($postgres as $indice => $esperada) {
            $real = $click[$indice];
            foreach (['numero_comprobante', 'razon_social_cliente', 'nombre_sucursal', 'nombre_producto', 'cantidad', 'total_linea'] as $campo) {
                $valorReal = $campo === 'total_linea' ? decimal((string) $real[$campo]) : (string) $real[$campo];
                $valorEsperado = (string) $esperada[$campo];

                if ($valorReal !== $valorEsperado) {
                    $diferencias[] = "Factura {$id}, línea {$esperada['id_factura_detalle']}, {$campo} no coincide.";
                }
            }
        }
    }

    return $diferencias;
}

function leerLineasFactura(int $idFactura): array
{
    $filas = Capsule::select(
        'SELECT
            fd.id_factura_detalle,
            f.numero_comprobante,
            f.razon_social_cliente,
            f.nombre_sucursal,
            fd.nombre_producto,
            fd.cantidad::text AS cantidad,
            fd.total_linea::text AS total_linea
         FROM factura_detalle fd
         INNER JOIN factura f ON f.id_factura = fd.id_factura
         WHERE fd.id_factura = ?
         ORDER BY fd.id_factura_detalle',
        [$idFactura]
    );

    return array_map(static function (object $fila): array {
        $fila = (array) $fila;
        $fila['total_linea'] = decimal((string) $fila['total_linea']);

        return $fila;
    }, $filas);
}

function lineasClickHouse(array $clickhouse, int $idFactura): array
{
    $cuerpo = clickHouse(
        $clickhouse,
        'SELECT id_factura_detalle, numero_comprobante, razon_social_cliente, nombre_sucursal, nombre_producto, cantidad, total_linea
         FROM fact_venta_linea
         WHERE id_factura = ' . $idFactura . '
         ORDER BY id_factura_detalle
         FORMAT JSONEachRow',
        '',
        false
    );
    $filas = [];

    foreach (preg_split("/\r\n|\n|\r/", trim($cuerpo)) as $linea) {
        if ($linea === '') {
            continue;
        }
        $filas[] = json_decode($linea, true, 512, JSON_THROW_ON_ERROR);
    }

    return $filas;
}

function resumenClickHouse(array $clickhouse): array
{
    $datos = json_decode(clickHouse(
        $clickhouse,
        'SELECT
            toString(uniqExact(id_factura)) AS facturas,
            toString(sum(total_linea)) AS suma_total,
            toString(sum(cantidad)) AS suma_cantidad,
            toString(min(fecha_emision)) AS min_fecha,
            toString(max(fecha_emision)) AS max_fecha
         FROM fact_venta_linea
         FORMAT JSONEachRow',
        '',
        false
    ), true, 512, JSON_THROW_ON_ERROR);
    $tamano = trim(clickHouse(
        $clickhouse,
        'SELECT formatReadableSize(sum(bytes_on_disk)) FROM system.parts WHERE active AND database = currentDatabase() AND table = \'fact_venta_linea\'',
        '',
        false
    ));
    $particiones = trim(clickHouse(
        $clickhouse,
        'SELECT arrayStringConcat(groupArray(partition), \', \') FROM (SELECT DISTINCT partition FROM system.parts WHERE active AND database = currentDatabase() AND table = \'fact_venta_linea\' ORDER BY partition)',
        '',
        false
    ));

    return [
        'facturas' => $datos['facturas'],
        'suma_total' => decimal((string) $datos['suma_total']),
        'suma_cantidad' => (string) (int) $datos['suma_cantidad'],
        'min_fecha' => $datos['min_fecha'],
        'max_fecha' => $datos['max_fecha'],
        'tamano' => $tamano,
        'particiones' => $particiones,
    ];
}

function clickHouse(array $clickhouse, string $consulta, string $cuerpo, bool $escritura): string
{
    $url = sprintf(
        'http://%s:%s/?database=%s&query=%s',
        $clickhouse['host'],
        $clickhouse['puerto'],
        rawurlencode($clickhouse['base']),
        rawurlencode($consulta)
    );
    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('No se pudo conectar con ClickHouse.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => $escritura,
        CURLOPT_POSTFIELDS => $escritura ? $cuerpo : null,
        CURLOPT_USERPWD => $clickhouse['usuario'] . ':' . $clickhouse['password'],
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 180,
    ]);

    $respuesta = curl_exec($curl);
    $codigo = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($respuesta === false || $codigo >= 400) {
        $texto = is_string($respuesta) ? $respuesta : $error;
        $texto = str_replace($clickhouse['password'], '***', $texto);
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');

        throw new RuntimeException($texto !== '' ? $texto : 'ClickHouse rechazó la operación.');
    }

    return is_string($respuesta) ? $respuesta : '';
}
