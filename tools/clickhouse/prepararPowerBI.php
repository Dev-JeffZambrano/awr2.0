<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Solo CLI';
    exit(1);
}

/**
 * Prepara ClickHouse para Power BI:
 * - VIEW vw_ventas_analiticas
 * - usuario de solo lectura powerbi_reader (XML montado en Docker)
 *
 * Uso:
 *   php tools/clickhouse/prepararPowerBI.php
 *   docker compose up -d clickhouse
 */

require dirname(__DIR__, 2) . '/bootstrap.php';

$host = trim((string) ($_ENV['CLICKHOUSE_HOST'] ?? '127.0.0.1'));
$puerto = trim((string) ($_ENV['CLICKHOUSE_HTTP_PORT'] ?? '8123'));
$base = trim((string) ($_ENV['CLICKHOUSE_DATABASE'] ?? $_ENV['CLICKHOUSE_DB'] ?? ''));
$usuarioAdmin = trim((string) ($_ENV['CLICKHOUSE_USER'] ?? ''));
$passwordAdmin = (string) ($_ENV['CLICKHOUSE_PASSWORD'] ?? '');

if (
    preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $base) !== 1
    || $usuarioAdmin === ''
    || $passwordAdmin === ''
    || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1
    || preg_match('/^[1-9][0-9]{0,4}$/', $puerto) !== 1
) {
    fwrite(STDERR, "ClickHouse no está configurado en .env" . PHP_EOL);
    exit(1);
}

$usuarioPowerBi = trim((string) ($_ENV['CLICKHOUSE_POWERBI_USER'] ?? 'powerbi_reader'));
$passwordPowerBi = (string) ($_ENV['CLICKHOUSE_POWERBI_PASSWORD'] ?? '');

if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $usuarioPowerBi) !== 1) {
    fwrite(STDERR, "CLICKHOUSE_POWERBI_USER no válido." . PHP_EOL);
    exit(1);
}

if ($passwordPowerBi === '') {
    $passwordPowerBi = generarPassword();
    appendEnv('CLICKHOUSE_POWERBI_USER', $usuarioPowerBi);
    appendEnv('CLICKHOUSE_POWERBI_PASSWORD', $passwordPowerBi);
    echo "Se agregaron CLICKHOUSE_POWERBI_USER y CLICKHOUSE_POWERBI_PASSWORD al .env" . PHP_EOL;
}

$vista = <<<SQL
CREATE OR REPLACE VIEW {$base}.vw_ventas_analiticas AS
SELECT
    id_factura_detalle,
    id_factura,
    id_operacion,
    fecha_emision,
    toYear(fecha_emision) AS anio,
    toMonth(fecha_emision) AS mes,
    toDayOfMonth(fecha_emision) AS dia,
    numero_comprobante,
    id_sucursal,
    nombre_sucursal,
    id_punto_emision,
    codigo_punto_emision,
    id_cliente,
    tipo_identificacion_cliente,
    identificacion_cliente,
    razon_social_cliente,
    id_producto,
    codigo_producto,
    nombre_producto,
    nombre_categoria,
    cantidad,
    precio_unitario,
    costo_unitario,
    descuento,
    porcentaje_impuesto,
    subtotal,
    valor_impuesto,
    total_linea,
    id_metodo_pago,
    nombre_metodo_pago,
    round(costo_unitario * cantidad, 2) AS costo_total,
    round(subtotal - (costo_unitario * cantidad), 2) AS margen,
    if(
        tipo_identificacion_cliente = 'CONSUMIDOR_FINAL'
        OR identificacion_cliente = '9999999999999',
        toUInt8(1),
        toUInt8(0)
    ) AS es_consumidor_final
FROM {$base}.fact_venta_linea
SQL;

try {
    clickhouse($host, $puerto, $base, $usuarioAdmin, $passwordAdmin, $vista);
    echo "VIEW lista: {$base}.vw_ventas_analiticas" . PHP_EOL;

    escribirXmlUsuario($usuarioPowerBi, $passwordPowerBi);
    echo "XML de usuario listo: tools/clickhouse/powerbi-reader.xml" . PHP_EOL;
    echo "Reinicia ClickHouse para cargar el usuario:" . PHP_EOL;
    echo "  docker compose up -d clickhouse" . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

function clickhouse(
    string $host,
    string $puerto,
    string $base,
    string $usuario,
    string $password,
    string $consulta
): string {
    $url = 'http://' . $host . ':' . $puerto
        . '/?database=' . rawurlencode($base);

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('No se pudo conectar con ClickHouse.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $consulta,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: text/plain; charset=UTF-8'],
        CURLOPT_USERPWD => $usuario . ':' . $password,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
    ]);

    $cuerpo = curl_exec($curl);
    $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($cuerpo) || $codigo !== 200) {
        $texto = is_string($cuerpo) ? trim($cuerpo) : '';
        $texto = str_replace($password, '***', $texto);
        throw new RuntimeException($texto !== '' ? $texto : 'ClickHouse rechazó la operación.');
    }

    return $cuerpo;
}

function escribirXmlUsuario(string $usuario, string $password): void
{
    $ruta = __DIR__ . DIRECTORY_SEPARATOR . 'powerbi-reader.xml';

    if (str_contains($password, ']]>')) {
        throw new RuntimeException('Password no válido para XML.');
    }

    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $usuario) !== 1) {
        throw new RuntimeException('Usuario Power BI no válido.');
    }

    $xml = <<<XML
<clickhouse>
  <users>
    <{$usuario}>
      <profile>default</profile>
      <networks>
        <ip>::/0</ip>
      </networks>
      <password><![CDATA[{$password}]]></password>
      <quota>default</quota>
      <access_management>0</access_management>
      <grants>
        <query>GRANT SELECT ON awr_analytics.vw_ventas_analiticas</query>
        <query>GRANT SELECT ON awr_analytics.fact_venta_linea</query>
        <query>GRANT SHOW TABLES ON awr_analytics.*</query>
      </grants>
      <settings>
        <readonly>2</readonly>
      </settings>
    </{$usuario}>
  </users>
</clickhouse>

XML;

    if (file_put_contents($ruta, $xml) === false) {
        throw new RuntimeException('No se pudo escribir powerbi-reader.xml');
    }
}

function generarPassword(): string
{
    return rtrim(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), '=');
}

function appendEnv(string $clave, string $valor): void
{
    $ruta = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($ruta)) {
        throw new RuntimeException('No existe el archivo .env');
    }

    $contenido = file_get_contents($ruta);

    if (!is_string($contenido)) {
        throw new RuntimeException('No se pudo leer .env');
    }

    if (preg_match('/^' . preg_quote($clave, '/') . '=/m', $contenido) === 1) {
        $nuevo = preg_replace(
            '/^' . preg_quote($clave, '/') . '=.*$/m',
            $clave . '=' . $valor,
            $contenido,
            1
        );
    } else {
        $nuevo = rtrim($contenido) . PHP_EOL . $clave . '=' . $valor . PHP_EOL;
    }

    if (!is_string($nuevo) || file_put_contents($ruta, $nuevo) === false) {
        throw new RuntimeException('No se pudo actualizar .env');
    }
}
