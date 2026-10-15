<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\RedpandaService;

$probarError = in_array('--error', $argv ?? [], true);

if ($probarError) {
    $_ENV['REDPANDA_BROKERS'] = '127.0.0.1:1';
}

$topic = 'awr.facturas.confirmadas';
$id = 900000101;
$milisegundos = (int) round(microtime(true) * 1000);
$fecha = gmdate('Y-m-d H:i:s', intdiv($milisegundos, 1000))
    . sprintf('.%03d', $milisegundos % 1000);

$mensaje = [
    'id_factura_detalle' => $id,
    'id_factura' => $id,
    'id_operacion' => '90000000-0000-4000-8000-000000000101',
    'fecha_emision' => $fecha,
    'numero_comprobante' => 'PRUEBA-PHP-900000101',
    'id_sucursal' => 900001,
    'nombre_sucursal' => 'PRUEBA PHP REDPANDA',
    'id_punto_emision' => 900001,
    'codigo_punto_emision' => 'P99',
    'id_cliente' => 900001,
    'tipo_identificacion_cliente' => 'PRUEBA',
    'identificacion_cliente' => 'PHP-REDPANDA-01',
    'razon_social_cliente' => 'CLIENTE PRUEBA PHP',
    'id_producto' => 900001,
    'codigo_producto' => 'PHP-REDPANDA',
    'nombre_producto' => 'PRODUCTO PRUEBA PHP',
    'nombre_categoria' => 'PRUEBA',
    'cantidad' => 1,
    'precio_unitario' => '2.50',
    'costo_unitario' => '1.00',
    'descuento' => '0.00',
    'porcentaje_impuesto' => '15.00',
    'subtotal' => '2.50',
    'valor_impuesto' => '0.38',
    'total_linea' => '2.88',
    'id_metodo_pago' => 900001,
    'nombre_metodo_pago' => 'PRUEBA',
];

try {
    (new RedpandaService())->publicar($topic, $mensaje, (string) $id);
} catch (Throwable $e) {
    $texto = $e->getMessage();
    $controlado = in_array($texto, [
        'Redpanda no está disponible',
        'No se pudo publicar el mensaje.',
    ], true);

    fwrite(STDERR, ($controlado ? $texto : 'No se pudo publicar el mensaje.') . PHP_EOL);
    exit(1);
}

echo "Mensaje publicado correctamente." . PHP_EOL . PHP_EOL;
echo "Topic:" . PHP_EOL;
echo $topic . PHP_EOL . PHP_EOL;
echo "ID:" . PHP_EOL;
echo $id . PHP_EOL;
