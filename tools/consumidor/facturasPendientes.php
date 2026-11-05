<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Solo CLI';
    exit(1);
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\VentaDegradada;
use App\Services\VentaRechazada;
use App\Services\VentaService;
use longlang\phpkafka\Consumer\Consumer;
use longlang\phpkafka\Consumer\ConsumerConfig;
use longlang\phpkafka\Consumer\ConsumeMessage;

const AWR_TOPIC_PENDIENTES = 'awr.facturas.pendientes';
const AWR_GRUPO_PENDIENTES = 'awr_consumidor_facturas_pendientes';
const AWR_ESPERA_REINTENTO_SEGUNDOS = 3;

$brokers = trim((string) ($_ENV['REDPANDA_BROKERS'] ?? ''));

if ($brokers === '' || preg_match('/^[A-Za-z0-9._:-]+(?:,[A-Za-z0-9._:-]+)*$/', $brokers) !== 1) {
    fwrite(STDERR, "Redpanda no está disponible" . PHP_EOL);
    exit(1);
}

$config = new ConsumerConfig();
$config->setBootstrapServer($brokers);
$config->setUpdateBrokers(true);
$config->setTopic(AWR_TOPIC_PENDIENTES);
$config->setGroupId(AWR_GRUPO_PENDIENTES);
$config->setClientId('awr-consumidor-facturas-pendientes');
$config->setGroupInstanceId('awr-consumidor-facturas-pendientes');
$config->setAutoCommit(false);
$config->setAutoCreateTopic(false);
$config->setInterval(1.0);
$config->setMaxWait(1.0);
$config->setConnectTimeout(2.0);
$config->setSendTimeout(2.0);
$config->setRecvTimeout(2.0);

try {
    $consumidor = new Consumer($config);
} catch (Throwable $e) {
    fwrite(STDERR, "Redpanda no está disponible" . PHP_EOL);
    exit(1);
}

echo "Consumidor activo: " . AWR_TOPIC_PENDIENTES . PHP_EOL;
echo "Grupo: " . AWR_GRUPO_PENDIENTES . PHP_EOL;

while (true) {
    try {
        $mensaje = $consumidor->consume();
    } catch (Throwable $e) {
        fwrite(STDERR, "AWR consumidor: lectura no disponible" . PHP_EOL);
        sleep(AWR_ESPERA_REINTENTO_SEGUNDOS);
        continue;
    }

    if (!$mensaje instanceof ConsumeMessage) {
        continue;
    }

    while (true) {
        $resultado = procesarMensajePendiente($mensaje->getValue());

        if ($resultado === 'ok') {
            try {
                $consumidor->ack($mensaje);
            } catch (Throwable $e) {
                fwrite(STDERR, "AWR consumidor: no se pudo confirmar el offset" . PHP_EOL);
                sleep(AWR_ESPERA_REINTENTO_SEGUNDOS);
                continue;
            }
            break;
        }

        if ($resultado === 'descartar') {
            try {
                $consumidor->ack($mensaje);
            } catch (Throwable $e) {
                fwrite(STDERR, "AWR consumidor: no se pudo descartar el mensaje" . PHP_EOL);
            }
            break;
        }

        echo "PostgreSQL no disponible. Reintento en "
            . AWR_ESPERA_REINTENTO_SEGUNDOS . " s..." . PHP_EOL;
        sleep(AWR_ESPERA_REINTENTO_SEGUNDOS);
    }
}

function procesarMensajePendiente(?string $crudo): string
{
    if (!is_string($crudo) || $crudo === '') {
        fwrite(STDERR, "AWR consumidor: mensaje vacío" . PHP_EOL);

        return 'descartar';
    }

    try {
        $datos = json_decode($crudo, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        fwrite(STDERR, "AWR consumidor: JSON no válido" . PHP_EOL);

        return 'descartar';
    }

    if (!is_array($datos)) {
        return 'descartar';
    }

    $idUsuario = $datos['id_usuario'] ?? null;

    if (!is_int($idUsuario) && !(is_string($idUsuario) && preg_match('/^[1-9][0-9]*$/', $idUsuario) === 1)) {
        fwrite(STDERR, "AWR consumidor: id_usuario no válido" . PHP_EOL);

        return 'descartar';
    }

    $entrada = [
        'id_operacion' => $datos['id_operacion'] ?? null,
        'id_cliente' => $datos['id_cliente'] ?? null,
        'id_punto_emision' => $datos['id_punto_emision'] ?? null,
        'id_metodo_pago' => $datos['id_metodo_pago'] ?? null,
        'porcentaje_descuento' => $datos['porcentaje_descuento'] ?? 0,
        'productos' => $datos['productos'] ?? null,
        'referencia_pago' => $datos['referencia_pago'] ?? null,
        'transferencia' => $datos['transferencia'] ?? null,
    ];

    try {
        $respuesta = VentaService::confirmarVenta((int) $idUsuario, $entrada, false);
    } catch (VentaDegradada $e) {
        return 'reintentar';
    } catch (VentaRechazada $e) {
        fwrite(STDERR, "AWR consumidor: " . $e->getMessage() . PHP_EOL);

        return 'descartar';
    } catch (Throwable $e) {
        fwrite(STDERR, "AWR consumidor: error no controlado" . PHP_EOL);
        error_log('AWR consumidor: ' . $e->getMessage());

        return 'reintentar';
    }

    if (($respuesta['exito'] ?? false) === true && isset($respuesta['factura']['idFactura'])) {
        echo "Factura procesada: " . $respuesta['factura']['idFactura']
            . " · operación " . ($entrada['id_operacion'] ?? '') . PHP_EOL;

        return 'ok';
    }

    return 'reintentar';
}
