<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use RuntimeException;
use Throwable;

class VentaRechazada extends RuntimeException
{
}

class VentaDegradada extends RuntimeException
{
}

class VentaService
{
    private const TIPO_FACTURA = '01';
    private const TIPO_OPERACION = 'FACTURA';
    private const TIPO_MOVIMIENTO = 'SALIDA_FACTURA';
    private const TOPIC_FACTURAS_CONFIRMADAS = 'awr.facturas.confirmadas';
    private const TOPIC_FACTURAS_PENDIENTES = 'awr.facturas.pendientes';
    private const TIMEOUT_VENTA_MS = 6000;
    private const CONSUMIDOR_FINAL = 'CONSUMIDOR_FINAL';
    private const LIMITE_BUSQUEDA = 15;
    private const LIMITE_LINEAS = 40;
    private const CONEXION_FACTURACION = 'facturacion';

    public static function obtenerDatosPos(int $idUsuario): array
    {
        $sucursal = self::sucursalAutorizada($idUsuario);
        $empresa = self::empresaDeSucursal((int) $sucursal->id_empresa);
        $puntos = self::puntosActivos((int) $sucursal->id_sucursal);
        $consumidor = self::consumidorFinal();
        $metodos = DB::select(
            'SELECT id_metodo_pago, codigo, nombre
             FROM metodo_pago
             WHERE estado IS TRUE
               AND codigo <> \'OTRO\'
             ORDER BY id_metodo_pago'
        );
        $tipo = self::tipoFactura();

        return [
            'exito' => true,
            'sucursal' => [
                'nombre' => (string) $sucursal->nombre,
                'ciudad' => (string) $sucursal->ciudad,
                'codigoEstablecimiento' => (string) $sucursal->codigo_establecimiento,
            ],
            'empresa' => [
                'nombre' => (string) $empresa->nombre,
                'razonSocial' => (string) $empresa->razon_social,
                'identificacion' => (string) $empresa->identificacion,
                'nombreComercial' => (string) ($empresa->nombre_comercial ?? ''),
            ],
            'puntosEmision' => array_map(static function ($punto): array {
                return [
                    'id' => (int) $punto->id_punto_emision,
                    'codigo' => (string) $punto->codigo,
                    'descripcion' => (string) $punto->descripcion,
                ];
            }, $puntos),
            'puntoSeleccionado' => count($puntos) === 1 ? (int) $puntos[0]->id_punto_emision : null,
            'consumidorFinal' => [
                'id' => (int) $consumidor->id_cliente,
                'tipoIdentificacion' => (string) $consumidor->tipo_identificacion,
                'identificacion' => (string) $consumidor->identificacion,
                'razonSocial' => (string) $consumidor->razon_social,
            ],
            'metodosPago' => array_map(static function ($metodo): array {
                return [
                    'id' => (int) $metodo->id_metodo_pago,
                    'codigo' => (string) $metodo->codigo,
                    'nombre' => (string) $metodo->nombre,
                ];
            }, $metodos),
            'tipoComprobante' => [
                'id' => (int) $tipo->id_tipo_comprobante,
                'codigo' => (string) $tipo->codigo,
                'nombre' => (string) $tipo->nombre,
            ],
        ];
    }

    public static function buscarProductos(int $idUsuario, string $texto): array
    {
        $sucursal = self::sucursalAutorizada($idUsuario);
        $texto = trim($texto);

        if ($texto === '' || mb_strlen($texto) > 80) {
            return ['exito' => true, 'productos' => []];
        }

        $como = self::patron($texto);
        $filas = DB::select(
            'SELECT p.id_producto, p.codigo, p.nombre, p.precio,
                    i.codigo AS codigo_impuesto, i.nombre AS nombre_impuesto, i.porcentaje,
                    inv.stock_actual
             FROM producto p
             JOIN inventario_sucursal inv
               ON inv.id_producto = p.id_producto
              AND inv.id_sucursal = ?
              AND inv.estado IS TRUE
             JOIN impuesto i ON i.id_impuesto = p.id_impuesto AND i.estado IS TRUE
             WHERE p.estado IS TRUE
               AND (
                    p.nombre ILIKE ? ESCAPE \'|\'
                    OR p.codigo ILIKE ? ESCAPE \'|\'
                    OR p.codigo_barras ILIKE ? ESCAPE \'|\'
               )
             ORDER BY p.nombre
             LIMIT ' . self::LIMITE_BUSQUEDA,
            [(int) $sucursal->id_sucursal, $como, $como, $como]
        );

        return [
            'exito' => true,
            'productos' => array_map(static function ($fila): array {
                return [
                    'idProducto' => (int) $fila->id_producto,
                    'codigo' => (string) $fila->codigo,
                    'nombre' => (string) $fila->nombre,
                    'precio' => self::redondear((string) $fila->precio),
                    'codigoImpuesto' => (string) $fila->codigo_impuesto,
                    'nombreImpuesto' => (string) $fila->nombre_impuesto,
                    'porcentajeImpuesto' => self::redondear((string) $fila->porcentaje),
                    'stockActual' => (int) $fila->stock_actual,
                ];
            }, $filas),
        ];
    }

    public static function buscarClientes(int $idUsuario, string $texto): array
    {
        self::sucursalAutorizada($idUsuario);
        $texto = trim($texto);

        if ($texto === '' || mb_strlen($texto) > 80) {
            return ['exito' => true, 'clientes' => []];
        }

        $como = self::patron($texto);
        $filas = DB::select(
            'SELECT id_cliente, tipo_identificacion, identificacion, razon_social
             FROM cliente
             WHERE estado IS TRUE
               AND (
                    identificacion ILIKE ? ESCAPE \'|\'
                    OR razon_social ILIKE ? ESCAPE \'|\'
               )
             ORDER BY razon_social
             LIMIT ' . self::LIMITE_BUSQUEDA,
            [$como, $como]
        );

        return [
            'exito' => true,
            'clientes' => array_map(static function ($fila): array {
                return [
                    'id' => (int) $fila->id_cliente,
                    'tipoIdentificacion' => (string) $fila->tipo_identificacion,
                    'identificacion' => (string) $fila->identificacion,
                    'razonSocial' => (string) $fila->razon_social,
                ];
            }, $filas),
        ];
    }

    public static function cotizarVenta(int $idUsuario, array $productos, mixed $porcentajeDescuento = 0): array
    {
        $sucursal = self::sucursalAutorizada($idUsuario);
        $cantidades = self::consolidarProductos($productos);
        $lineas = self::armarLineas((int) $sucursal->id_sucursal, $cantidades, false);
        $cotizacion = self::sumarLineas($lineas, self::porcentajeDescuento($porcentajeDescuento));
        $cotizacion['exito'] = true;

        return $cotizacion;
    }

    public static function confirmarVenta(
        int $idUsuario,
        array $entrada,
        bool $permitirCaminoResiliente = true
    ): array {
        $uuid = self::uuid($entrada['id_operacion'] ?? null);
        $idCliente = self::entero($entrada['id_cliente'] ?? null, 'Cliente no disponible');
        $idMetodo = self::entero($entrada['id_metodo_pago'] ?? null, 'Método de pago no disponible');
        $idPunto = self::entero($entrada['id_punto_emision'] ?? null, 'Punto de emisión no disponible');
        $porcentajeDescuento = self::porcentajeDescuento($entrada['porcentaje_descuento'] ?? 0);
        $referenciaPago = self::referenciaPago($entrada);
        $cantidades = self::consolidarProductos($entrada['productos'] ?? null);
        $productosPendiente = self::productosParaPendiente($cantidades);
        $entradaNormalizada = [
            'id_operacion' => $uuid,
            'id_cliente' => $idCliente,
            'id_metodo_pago' => $idMetodo,
            'id_punto_emision' => $idPunto,
            'porcentaje_descuento' => $porcentajeDescuento,
            'productos' => $productosPendiente,
            'referencia_pago' => $referenciaPago,
        ];

        if (getenv('AWR_VENTA_DIRECTA') !== '1') {
            try {
                return self::confirmarVentaConLimite(
                    $idUsuario,
                    $entradaNormalizada,
                    $permitirCaminoResiliente,
                    $productosPendiente
                );
            } catch (VentaDegradada $e) {
                return self::responderCaminoResiliente(
                    $e,
                    $permitirCaminoResiliente,
                    $uuid,
                    $idUsuario,
                    $idCliente,
                    $idPunto,
                    $idMetodo,
                    $productosPendiente,
                    $porcentajeDescuento,
                    $referenciaPago
                );
            }
        }

        $db = self::dbFacturacion();

        try {
            self::aplicarTimeoutVenta();
            $db->beginTransaction();

            try {
                $sucursal = self::sucursalAutorizada($idUsuario, self::CONEXION_FACTURACION);
                $idSucursal = (int) $sucursal->id_sucursal;
                $punto = self::puntoAutorizado($idPunto, $idSucursal);
                $tipo = self::tipoFactura(self::CONEXION_FACTURACION);
                $cliente = self::clienteActivo($idCliente);
                $metodo = self::metodoActivo($idMetodo);
                $existente = self::facturaDeOperacion($uuid);

                if ($existente !== null) {
                    self::exigirMismaOperacion($existente, $idUsuario, $idSucursal);
                    $db->commit();
                    self::restablecerTimeoutVenta();
                    self::publicarFacturaConfirmadaEnAnalitica((int) $existente->id_factura);

                    return self::respuestaFactura($existente, true);
                }

                $secuencial = $db->selectOne(
                    'SELECT id_secuencial, ultimo_numero
                     FROM secuencial_comprobante
                     WHERE id_punto_emision = ?
                       AND id_tipo_comprobante = ?
                       AND estado IS TRUE
                     FOR UPDATE',
                    [(int) $punto->id_punto_emision, (int) $tipo->id_tipo_comprobante]
                );

                if ($secuencial === null) {
                    throw new VentaRechazada('No disponible');
                }

                $numero = (int) $secuencial->ultimo_numero + 1;

                if ($numero < 1 || $numero > 999999999) {
                    throw new VentaRechazada('No disponible');
                }

                $lineas = self::armarLineas($idSucursal, $cantidades, true, self::CONEXION_FACTURACION);
                self::exigirStock($lineas);
                $totales = self::sumarLineas($lineas, $porcentajeDescuento);
                $empresa = self::empresaDeSucursal((int) $sucursal->id_empresa, self::CONEXION_FACTURACION);
                $configuracion = self::configuracionEmpresa((int) $sucursal->id_empresa);
                $comprobante = sprintf(
                    '%s-%s-%09d',
                    $sucursal->codigo_establecimiento,
                    $punto->codigo,
                    $numero
                );

                $db->table('operacion_idempotente')->insert([
                    'id_operacion' => $uuid,
                    'tipo_operacion' => self::TIPO_OPERACION,
                    'id_usuario' => $idUsuario,
                    'id_sucursal' => $idSucursal,
                ]);

                $idFactura = $db->table('factura')->insertGetId([
                    'id_operacion' => $uuid,
                    'id_empresa' => (int) $empresa->id_empresa,
                    'id_sucursal' => $idSucursal,
                    'id_punto_emision' => (int) $punto->id_punto_emision,
                    'id_tipo_comprobante' => (int) $tipo->id_tipo_comprobante,
                    'id_cliente' => (int) $cliente->id_cliente,
                    'id_usuario' => $idUsuario,
                    'secuencial' => $numero,
                    'numero_comprobante' => $comprobante,
                    'fecha_emision' => DB::raw('NOW()'),
                    'estado' => 'EMITIDA',
                    'subtotal' => $totales['subtotal'],
                    'descuento' => $totales['descuento'],
                    'valor_impuesto' => $totales['valorImpuesto'],
                    'total' => $totales['total'],
                    'ruc_empresa' => (string) $empresa->identificacion,
                    'razon_social_empresa' => (string) ($empresa->razon_social !== null && $empresa->razon_social !== ''
                        ? $empresa->razon_social
                        : $empresa->nombre),
                    'nombre_comercial_empresa' => $empresa->nombre_comercial,
                    'direccion_matriz' => $empresa->direccion,
                    'codigo_establecimiento' => (string) $sucursal->codigo_establecimiento,
                    'nombre_sucursal' => (string) $sucursal->nombre,
                    'direccion_sucursal' => $sucursal->direccion,
                    'codigo_punto_emision' => (string) $punto->codigo,
                    'codigo_tipo_comprobante' => (string) $tipo->codigo,
                    'moneda' => (string) $configuracion->moneda,
                    'ambiente' => (string) $configuracion->ambiente,
                    'tipo_emision' => (string) $configuracion->tipo_emision,
                    'tipo_identificacion_cliente' => (string) $cliente->tipo_identificacion,
                    'identificacion_cliente' => (string) $cliente->identificacion,
                    'razon_social_cliente' => (string) $cliente->razon_social,
                    'direccion_cliente' => $cliente->direccion,
                ], 'id_factura');

                foreach ($lineas as $linea) {
                    $db->table('factura_detalle')->insert([
                        'id_factura' => $idFactura,
                        'id_producto' => $linea['idProducto'],
                        'codigo_producto' => $linea['codigo'],
                        'nombre_producto' => $linea['nombre'],
                        'nombre_categoria' => $linea['nombreCategoria'],
                        'cantidad' => $linea['cantidad'],
                        'precio_unitario' => $linea['precioUnitario'],
                        'costo_unitario' => $linea['costoUnitario'],
                        'descuento' => '0.00',
                        'id_impuesto' => $linea['idImpuesto'],
                        'codigo_impuesto' => $linea['codigoImpuesto'],
                        'nombre_impuesto' => $linea['nombreImpuesto'],
                        'porcentaje_impuesto' => $linea['porcentajeImpuesto'],
                        'subtotal' => $linea['subtotal'],
                        'valor_impuesto' => $linea['valorImpuesto'],
                        'total_linea' => $linea['totalLinea'],
                    ]);
                }

                $db->table('pago')->insert([
                    'id_factura' => $idFactura,
                    'id_metodo_pago' => (int) $metodo->id_metodo_pago,
                    'monto' => $totales['total'],
                    'referencia' => $referenciaPago,
                ]);

                foreach ($lineas as $linea) {
                    $stockNuevo = $linea['stockActual'] - $linea['cantidad'];
                    $actualizadas = $db->update(
                        'UPDATE inventario_sucursal
                         SET stock_actual = ?, fecha_actualizacion = NOW()
                         WHERE id_inventario = ? AND stock_actual = ?',
                        [$stockNuevo, $linea['idInventario'], $linea['stockActual']]
                    );

                    if ($actualizadas !== 1) {
                        throw new VentaRechazada('Stock insuficiente.');
                    }

                    $db->table('movimiento_inventario')->insert([
                        'id_operacion' => $uuid,
                        'id_sucursal' => $idSucursal,
                        'id_producto' => $linea['idProducto'],
                        'id_usuario' => $idUsuario,
                        'id_factura' => $idFactura,
                        'tipo_movimiento' => self::TIPO_MOVIMIENTO,
                        'cantidad' => $linea['cantidad'],
                        'stock_anterior' => $linea['stockActual'],
                        'stock_nuevo' => $stockNuevo,
                    ]);
                }

                $avanzado = $db->update(
                    'UPDATE secuencial_comprobante
                     SET ultimo_numero = ?, fecha_actualizacion = NOW()
                     WHERE id_secuencial = ? AND ultimo_numero = ?',
                    [$numero, (int) $secuencial->id_secuencial, (int) $secuencial->ultimo_numero]
                );

                if ($avanzado !== 1) {
                    throw new VentaRechazada('No disponible');
                }

                $db->commit();
            } catch (VentaRechazada $e) {
                self::revertir();
                self::restablecerTimeoutVenta();
                throw $e;
            } catch (QueryException $e) {
                self::revertir();

                if (self::esDuplicadoOperacion($e)) {
                    try {
                        $existente = self::facturaDeOperacion($uuid);

                        if ($existente !== null) {
                            $sucursal = self::sucursalAutorizada($idUsuario, self::CONEXION_FACTURACION);
                            self::exigirMismaOperacion($existente, $idUsuario, (int) $sucursal->id_sucursal);
                            self::restablecerTimeoutVenta();
                            self::publicarFacturaConfirmadaEnAnalitica((int) $existente->id_factura);

                            return self::respuestaFactura($existente, true);
                        }
                    } catch (Throwable $recuperacion) {
                        if (self::esDegradacionInfraestructura($recuperacion)) {
                            throw new VentaDegradada($recuperacion->getMessage(), 0, $recuperacion);
                        }
                    }
                }

                if (self::esDegradacionInfraestructura($e)) {
                    throw new VentaDegradada($e->getMessage(), 0, $e);
                }

                self::restablecerTimeoutVenta();
                error_log('AWR confirmarVenta: ' . $e->getMessage());
                throw new VentaRechazada('No disponible');
            } catch (Throwable $e) {
                self::revertir();

                if (self::esDegradacionInfraestructura($e)) {
                    throw new VentaDegradada($e->getMessage(), 0, $e);
                }

                self::restablecerTimeoutVenta();
                error_log('AWR confirmarVenta: ' . $e->getMessage());
                throw new VentaRechazada('No disponible');
            }
        } catch (VentaDegradada $e) {
            return self::responderCaminoResiliente(
                $e,
                $permitirCaminoResiliente,
                $uuid,
                $idUsuario,
                $idCliente,
                $idPunto,
                $idMetodo,
                $productosPendiente,
                $porcentajeDescuento,
                $referenciaPago
            );
        } catch (Throwable $e) {
            if (!self::esDegradacionInfraestructura($e)) {
                throw $e instanceof VentaRechazada ? $e : new VentaRechazada('No disponible');
            }

            return self::responderCaminoResiliente(
                $e instanceof VentaDegradada ? $e : new VentaDegradada($e->getMessage(), 0, $e),
                $permitirCaminoResiliente,
                $uuid,
                $idUsuario,
                $idCliente,
                $idPunto,
                $idMetodo,
                $productosPendiente,
                $porcentajeDescuento,
                $referenciaPago
            );
        }

        self::restablecerTimeoutVenta();

        $creada = self::facturaDeOperacion($uuid);

        if ($creada === null) {
            throw new VentaRechazada('No disponible');
        }

        self::publicarFacturaConfirmadaEnAnalitica((int) $creada->id_factura);

        return self::respuestaFactura($creada, false);
    }

    private static function responderCaminoResiliente(
        VentaDegradada $e,
        bool $permitirCaminoResiliente,
        string $uuid,
        int $idUsuario,
        int $idCliente,
        int $idPunto,
        int $idMetodo,
        array $productosPendiente,
        float $porcentajeDescuento = 0.0,
        ?string $referenciaPago = null
    ): array {
        self::abortarConexionVenta();
        error_log('AWR confirmarVenta degradada: ' . $e->getMessage());

        if (!$permitirCaminoResiliente) {
            throw $e;
        }

        return self::registrarVentaPendiente(
            $uuid,
            $idUsuario,
            $idCliente,
            $idPunto,
            $idMetodo,
            $productosPendiente,
            $porcentajeDescuento,
            $referenciaPago
        );
    }

    private static function confirmarVentaConLimite(
        int $idUsuario,
        array $entradaNormalizada,
        bool $permitirCaminoResiliente,
        array $productosPendiente
    ): array {
        $uuid = (string) $entradaNormalizada['id_operacion'];
        $script = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools'
            . DIRECTORY_SEPARATOR . 'venta' . DIRECTORY_SEPARATOR . 'confirmarAislado.php';
        $php = self::binarioPhpCli();

        if (!is_file($script) || $php === '') {
            throw new VentaRechazada('No disponible');
        }

        $descriptores = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proceso = @proc_open(
            [$php, $script],
            $descriptores,
            $pipes,
            dirname(__DIR__, 2),
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($proceso)) {
            $comando = '"' . $php . '" "' . $script . '"';
            $proceso = @proc_open($comando, $descriptores, $pipes, dirname(__DIR__, 2));
        }

        if (!is_resource($proceso)) {
            throw new VentaDegradada('No se pudo iniciar la confirmación aislada');
        }

        fwrite($pipes[0], json_encode([
            'id_usuario' => $idUsuario,
            'entrada' => $entradaNormalizada,
        ], JSON_UNESCAPED_UNICODE));
        fclose($pipes[0]);

        $limite = microtime(true) + (self::TIMEOUT_VENTA_MS / 1000);
        $agotado = false;

        while (true) {
            $estado = proc_get_status($proceso);

            if (!$estado['running']) {
                break;
            }

            if (microtime(true) >= $limite) {
                $agotado = true;
                self::terminarProcesoVenta($proceso, $estado['pid'] ?? null);
                break;
            }

            usleep(50000);
        }

        if ($agotado) {
            try {
                @fclose($pipes[1]);
                @fclose($pipes[2]);
            } catch (Throwable $e) {
            }
            try {
                self::cerrarProcesoVenta($proceso);
            } catch (Throwable $e) {
            }

            try {
                return self::responderCaminoResiliente(
                    new VentaDegradada('Tiempo de confirmación agotado'),
                    $permitirCaminoResiliente,
                    $uuid,
                    $idUsuario,
                    (int) $entradaNormalizada['id_cliente'],
                    (int) $entradaNormalizada['id_punto_emision'],
                    (int) $entradaNormalizada['id_metodo_pago'],
                    $productosPendiente,
                    (float) ($entradaNormalizada['porcentaje_descuento'] ?? 0),
                    self::referenciaOpcional($entradaNormalizada['referencia_pago'] ?? null)
                );
            } catch (VentaDegradada | VentaRechazada $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new VentaRechazada(
                    'No se pudo registrar la operación. Intente nuevamente cuando el servicio se encuentre disponible.'
                );
            }
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = (string) @stream_get_contents($pipes[1]);
        $stderr = (string) @stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo = self::cerrarProcesoVenta($proceso);

        if ($stderr !== '') {
            error_log('AWR confirmarVenta aislada: ' . trim($stderr));
        }

        $respuesta = json_decode(trim($stdout), true);

        if (!is_array($respuesta) || !isset($respuesta['tipo'])) {
            throw new VentaDegradada('Confirmación aislada sin respuesta');
        }

        if ($respuesta['tipo'] === 'ok' && is_array($respuesta['datos'] ?? null)) {
            return $respuesta['datos'];
        }

        if ($respuesta['tipo'] === 'rechazada') {
            throw new VentaRechazada((string) ($respuesta['mensaje'] ?? 'No disponible'));
        }

        if ($respuesta['tipo'] === 'degradada' || $codigo === 2) {
            return self::responderCaminoResiliente(
                new VentaDegradada((string) ($respuesta['mensaje'] ?? 'PostgreSQL no disponible')),
                $permitirCaminoResiliente,
                $uuid,
                $idUsuario,
                (int) $entradaNormalizada['id_cliente'],
                (int) $entradaNormalizada['id_punto_emision'],
                (int) $entradaNormalizada['id_metodo_pago'],
                $productosPendiente,
                (float) ($entradaNormalizada['porcentaje_descuento'] ?? 0),
                self::referenciaOpcional($entradaNormalizada['referencia_pago'] ?? null)
            );
        }

        throw new VentaRechazada('No disponible');
    }

    private static function terminarProcesoVenta($proceso, mixed $pid): void
    {
        if (is_int($pid) || (is_string($pid) && preg_match('/^[1-9][0-9]*$/', $pid) === 1)) {
            if (stripos(PHP_OS, 'WIN') === 0) {
                exec('taskkill /F /T /PID ' . (int) $pid . ' 2>NUL');
            } else {
                exec('kill -9 ' . (int) $pid . ' 2>/dev/null');
            }
        }

        @proc_terminate($proceso, 9);
    }

    private static function cerrarProcesoVenta($proceso): int
    {
        for ($i = 0; $i < 20; $i++) {
            $estado = @proc_get_status($proceso);

            if (!is_array($estado)) {
                return -1;
            }

            if ($estado['running'] !== true) {
                $codigo = @proc_close($proceso);

                return is_int($codigo) ? $codigo : -1;
            }

            if ($i === 0 || $i === 5 || $i === 10) {
                self::terminarProcesoVenta($proceso, $estado['pid'] ?? null);
            }

            usleep(50000);
        }

        $estado = @proc_get_status($proceso);
        if (is_array($estado) && ($estado['running'] ?? false) === true) {
            self::terminarProcesoVenta($proceso, $estado['pid'] ?? null);
        }

        return -1;
    }

    private static function binarioPhpCli(): string
    {
        $binario = (string) PHP_BINARY;

        if ($binario !== '' && preg_match('/php\.exe$/i', $binario) === 1 && is_file($binario)) {
            return $binario;
        }

        $desdeEnv = trim((string) ($_ENV['AWR_PHP_CLI'] ?? getenv('AWR_PHP_CLI') ?: ''));

        if ($desdeEnv !== '' && is_file($desdeEnv)) {
            return $desdeEnv;
        }

        $preferido = 'C:\\wamp64\\bin\\php\\php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION . '\\php.exe';

        if (is_file($preferido)) {
            return $preferido;
        }

        $candidatos = glob('C:\\wamp64\\bin\\php\\php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.*\\php.exe') ?: [];
        rsort($candidatos, SORT_NATURAL);

        foreach ($candidatos as $candidato) {
            if (is_file($candidato)) {
                return $candidato;
            }
        }

        return 'php';
    }

    private static function dbFacturacion(): Connection
    {
        return DB::connection(self::CONEXION_FACTURACION);
    }

    private static function sucursalAutorizada(int $idUsuario, string $conexion = 'default'): object
    {
        $filas = DB::connection($conexion)->select(
            'SELECT s.id_sucursal, s.id_empresa, s.nombre, s.ciudad, s.direccion, s.codigo_establecimiento
             FROM usuario_sucursal us
             JOIN sucursal s ON s.id_sucursal = us.id_sucursal AND s.estado IS TRUE
             WHERE us.id_usuario = ? AND us.estado IS TRUE',
            [$idUsuario]
        );

        if (count($filas) !== 1) {
            throw new VentaRechazada('No disponible');
        }

        return $filas[0];
    }

    private static function empresaDeSucursal(int $idEmpresa, string $conexion = 'default'): object
    {
        $empresa = DB::connection($conexion)->selectOne(
            'SELECT id_empresa, nombre, razon_social, identificacion, nombre_comercial, direccion
             FROM empresa
             WHERE id_empresa = ? AND estado IS TRUE',
            [$idEmpresa]
        );

        if ($empresa === null || $empresa->identificacion === null || $empresa->identificacion === '') {
            throw new VentaRechazada('No disponible');
        }

        return $empresa;
    }

    private static function configuracionEmpresa(int $idEmpresa): object
    {
        $configuracion = self::dbFacturacion()->selectOne(
            'SELECT moneda, ambiente, tipo_emision
             FROM configuracion_facturacion
             WHERE id_empresa = ? AND estado IS TRUE',
            [$idEmpresa]
        );

        if ($configuracion === null) {
            throw new VentaRechazada('No disponible');
        }

        return $configuracion;
    }

    private static function puntosActivos(int $idSucursal): array
    {
        return DB::select(
            'SELECT id_punto_emision, codigo, descripcion
             FROM punto_emision
             WHERE id_sucursal = ? AND estado IS TRUE
             ORDER BY codigo, id_punto_emision',
            [$idSucursal]
        );
    }

    private static function puntoAutorizado(int $idPunto, int $idSucursal): object
    {
        $punto = self::dbFacturacion()->selectOne(
            'SELECT id_punto_emision, codigo, descripcion
             FROM punto_emision
             WHERE id_punto_emision = ? AND id_sucursal = ? AND estado IS TRUE',
            [$idPunto, $idSucursal]
        );

        if ($punto === null) {
            throw new VentaRechazada('Punto de emisión no disponible');
        }

        return $punto;
    }

    private static function tipoFactura(string $conexion = 'default'): object
    {
        $tipo = DB::connection($conexion)->selectOne(
            'SELECT id_tipo_comprobante, codigo, nombre
             FROM tipo_comprobante
             WHERE codigo = ? AND estado IS TRUE',
            [self::TIPO_FACTURA]
        );

        if ($tipo === null) {
            throw new VentaRechazada('No disponible');
        }

        return $tipo;
    }

    private static function consumidorFinal(): object
    {
        $filas = DB::select(
            'SELECT id_cliente, tipo_identificacion, identificacion, razon_social
             FROM cliente
             WHERE tipo_identificacion = ? AND estado IS TRUE
             ORDER BY id_cliente',
            [self::CONSUMIDOR_FINAL]
        );

        if (count($filas) !== 1) {
            throw new VentaRechazada('No disponible');
        }

        return $filas[0];
    }

    private static function clienteActivo(int $idCliente): object
    {
        $cliente = self::dbFacturacion()->selectOne(
            'SELECT id_cliente, tipo_identificacion, identificacion, razon_social, direccion
             FROM cliente
             WHERE id_cliente = ? AND estado IS TRUE',
            [$idCliente]
        );

        if ($cliente === null) {
            throw new VentaRechazada('Cliente no disponible');
        }

        return $cliente;
    }

    private static function metodoActivo(int $idMetodo): object
    {
        $metodo = self::dbFacturacion()->selectOne(
            'SELECT id_metodo_pago, codigo, nombre
             FROM metodo_pago
             WHERE id_metodo_pago = ? AND estado IS TRUE',
            [$idMetodo]
        );

        if ($metodo === null) {
            throw new VentaRechazada('Método de pago no disponible');
        }

        return $metodo;
    }

    private static function consolidarProductos(mixed $productos): array
    {
        if (!is_array($productos) || $productos === []) {
            throw new VentaRechazada('Datos incompletos.');
        }

        $cantidades = [];

        foreach ($productos as $producto) {
            if (!is_array($producto)) {
                throw new VentaRechazada('Datos incompletos.');
            }

            $id = self::entero($producto['id_producto'] ?? null, 'Producto no disponible');
            $cantidad = self::entero($producto['cantidad'] ?? null, 'Datos incompletos.');

            if ($cantidad < 1 || $cantidad > 9999) {
                throw new VentaRechazada('Datos incompletos.');
            }

            $cantidades[$id] = ($cantidades[$id] ?? 0) + $cantidad;

            if ($cantidades[$id] > 9999) {
                throw new VentaRechazada('Datos incompletos.');
            }
        }

        if (count($cantidades) > self::LIMITE_LINEAS) {
            throw new VentaRechazada('Datos incompletos.');
        }

        ksort($cantidades);

        return $cantidades;
    }

    private static function armarLineas(
        int $idSucursal,
        array $cantidades,
        bool $bloquear,
        string $conexion = 'default'
    ): array {
        $ids = array_keys($cantidades);
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT inv.id_inventario, inv.id_producto, inv.stock_actual,
                       p.codigo, p.nombre, p.precio, p.costo, p.estado AS producto_activo,
                       c.nombre AS nombre_categoria,
                       i.id_impuesto, i.codigo AS codigo_impuesto, i.nombre AS nombre_impuesto,
                       i.porcentaje, i.estado AS impuesto_activo
                FROM inventario_sucursal inv
                JOIN producto p ON p.id_producto = inv.id_producto
                JOIN categoria c ON c.id_categoria = p.id_categoria
                JOIN impuesto i ON i.id_impuesto = p.id_impuesto
                WHERE inv.id_sucursal = ?
                  AND inv.estado IS TRUE
                  AND inv.id_producto IN (' . $marcas . ')
                ORDER BY inv.id_producto';

        if ($bloquear) {
            $sql .= ' FOR UPDATE OF inv';
        }

        $filas = DB::connection($conexion)->select($sql, array_merge([$idSucursal], $ids));
        $porId = [];

        foreach ($filas as $fila) {
            $porId[(int) $fila->id_producto] = $fila;
        }

        $lineas = [];

        foreach ($cantidades as $idProducto => $cantidad) {
            $fila = $porId[$idProducto] ?? null;

            if (
                $fila === null
                || !self::esVerdadero($fila->producto_activo)
                || !self::esVerdadero($fila->impuesto_activo)
            ) {
                throw new VentaRechazada('Producto no disponible');
            }

            $precio = self::redondear((string) $fila->precio);
            $costo = self::redondear((string) $fila->costo);
            $porcentaje = self::redondear((string) $fila->porcentaje);
            $subtotal = self::redondear((float) $precio * $cantidad);
            $impuesto = self::redondear((float) $subtotal * (float) $porcentaje / 100);
            $total = self::redondear((float) $subtotal + (float) $impuesto);
            $stock = (int) $fila->stock_actual;

            $lineas[] = [
                'idInventario' => (int) $fila->id_inventario,
                'idProducto' => (int) $fila->id_producto,
                'codigo' => (string) $fila->codigo,
                'nombre' => (string) $fila->nombre,
                'nombreCategoria' => (string) $fila->nombre_categoria,
                'cantidad' => $cantidad,
                'precioUnitario' => $precio,
                'costoUnitario' => $costo,
                'idImpuesto' => (int) $fila->id_impuesto,
                'codigoImpuesto' => (string) $fila->codigo_impuesto,
                'nombreImpuesto' => (string) $fila->nombre_impuesto,
                'porcentajeImpuesto' => $porcentaje,
                'subtotal' => $subtotal,
                'valorImpuesto' => $impuesto,
                'totalLinea' => $total,
                'stockActual' => $stock,
                'stockSuficiente' => $stock >= $cantidad,
            ];
        }

        return $lineas;
    }

    private static function exigirStock(array $lineas): void
    {
        foreach ($lineas as $linea) {
            if ($linea['stockSuficiente'] !== true) {
                throw new VentaRechazada('Stock insuficiente.');
            }
        }
    }

    private static function sumarLineas(array $lineas, float $porcentajeDescuento = 0.0): array
    {
        $subtotal = 0.0;
        $impuesto = 0.0;
        $stockSuficiente = true;
        $visibles = [];

        foreach ($lineas as $linea) {
            $subtotal += (float) $linea['subtotal'];
            $impuesto += (float) $linea['valorImpuesto'];
            $stockSuficiente = $stockSuficiente && $linea['stockSuficiente'];
            $visibles[] = [
                'idProducto' => $linea['idProducto'],
                'codigo' => $linea['codigo'],
                'nombre' => $linea['nombre'],
                'cantidad' => $linea['cantidad'],
                'precioUnitario' => $linea['precioUnitario'],
                'nombreImpuesto' => $linea['nombreImpuesto'],
                'porcentajeImpuesto' => $linea['porcentajeImpuesto'],
                'subtotal' => $linea['subtotal'],
                'valorImpuesto' => $linea['valorImpuesto'],
                'totalLinea' => $linea['totalLinea'],
                'stockActual' => $linea['stockActual'],
                'stockSuficiente' => $linea['stockSuficiente'],
            ];
        }

        $subtotalRedondeado = self::redondear($subtotal);
        $impuestoOriginal = self::redondear($impuesto);
        $descuento = self::redondear((float) $subtotalRedondeado * $porcentajeDescuento / 100);
        $base = self::redondear((float) $subtotalRedondeado - (float) $descuento);
        // IVA proporcional: equivale a repartir el % de descuento sobre cada
        // subtotal de línea y recalcular impuesto = subtotal_linea * %IVA.
        $valorImpuesto = (float) $subtotalRedondeado > 0
            ? self::redondear((float) $impuestoOriginal * ((float) $base / (float) $subtotalRedondeado))
            : '0.00';
        $total = self::redondear((float) $base + (float) $valorImpuesto);

        return [
            'lineas' => $visibles,
            'subtotal' => $subtotalRedondeado,
            'descuento' => $descuento,
            'valorImpuesto' => $valorImpuesto,
            'total' => $total,
            'porcentajeDescuento' => self::redondear($porcentajeDescuento),
            'stockSuficiente' => $stockSuficiente,
        ];
    }

    private static function publicarFacturaConfirmadaEnAnalitica(int $idFactura): void
    {
        try {
            $db = self::dbFacturacion();
            $pagos = $db->select(
                'SELECT p.id_metodo_pago, mp.nombre AS nombre_metodo_pago
                 FROM pago p
                 JOIN metodo_pago mp ON mp.id_metodo_pago = p.id_metodo_pago
                 WHERE p.id_factura = ?',
                [$idFactura]
            );
            $lineas = $db->select(
                'SELECT d.id_factura_detalle, f.id_factura, f.id_operacion, f.fecha_emision,
                        f.numero_comprobante, f.id_sucursal, f.nombre_sucursal,
                        f.id_punto_emision, f.codigo_punto_emision, f.id_cliente,
                        f.tipo_identificacion_cliente, f.identificacion_cliente,
                        f.razon_social_cliente, d.id_producto, d.codigo_producto,
                        d.nombre_producto, d.nombre_categoria, d.cantidad,
                        d.precio_unitario, d.costo_unitario, d.descuento,
                        d.porcentaje_impuesto, d.subtotal, d.valor_impuesto, d.total_linea
                 FROM factura f
                 JOIN factura_detalle d ON d.id_factura = f.id_factura
                 WHERE f.id_factura = ?
                 ORDER BY d.id_factura_detalle',
                [$idFactura]
            );
        } catch (Throwable $e) {
            error_log('AWR confirmarVenta analitica: publicacion fallida');

            return;
        }

        if (count($pagos) !== 1 || $lineas === []) {
            error_log('AWR confirmarVenta analitica: publicacion fallida');

            return;
        }

        $pago = $pagos[0];
        $redpanda = new RedpandaService();

        foreach ($lineas as $linea) {
            try {
                $redpanda->publicar(
                    self::TOPIC_FACTURAS_CONFIRMADAS,
                    self::eventoLineaConfirmada($linea, $pago),
                    (string) $linea->id_factura_detalle
                );
            } catch (Throwable $e) {
                error_log('AWR confirmarVenta analitica: publicacion fallida');
            }
        }
    }

    private static function eventoLineaConfirmada(object $linea, object $pago): array
    {
        return [
            'id_factura_detalle' => (int) $linea->id_factura_detalle,
            'id_factura' => (int) $linea->id_factura,
            'id_operacion' => (string) $linea->id_operacion,
            'fecha_emision' => self::fechaAnalitica($linea->fecha_emision),
            'numero_comprobante' => (string) $linea->numero_comprobante,
            'id_sucursal' => (int) $linea->id_sucursal,
            'nombre_sucursal' => (string) $linea->nombre_sucursal,
            'id_punto_emision' => (int) $linea->id_punto_emision,
            'codigo_punto_emision' => (string) $linea->codigo_punto_emision,
            'id_cliente' => (int) $linea->id_cliente,
            'tipo_identificacion_cliente' => (string) $linea->tipo_identificacion_cliente,
            'identificacion_cliente' => (string) $linea->identificacion_cliente,
            'razon_social_cliente' => (string) $linea->razon_social_cliente,
            'id_producto' => (int) $linea->id_producto,
            'codigo_producto' => (string) $linea->codigo_producto,
            'nombre_producto' => (string) $linea->nombre_producto,
            'nombre_categoria' => (string) $linea->nombre_categoria,
            'cantidad' => (int) $linea->cantidad,
            'precio_unitario' => self::redondear((string) $linea->precio_unitario),
            'costo_unitario' => self::redondear((string) $linea->costo_unitario),
            'descuento' => self::redondear((string) $linea->descuento),
            'porcentaje_impuesto' => self::redondear((string) $linea->porcentaje_impuesto),
            'subtotal' => self::redondear((string) $linea->subtotal),
            'valor_impuesto' => self::redondear((string) $linea->valor_impuesto),
            'total_linea' => self::redondear((string) $linea->total_linea),
            'id_metodo_pago' => (int) $pago->id_metodo_pago,
            'nombre_metodo_pago' => (string) $pago->nombre_metodo_pago,
        ];
    }

    private static function fechaAnalitica(mixed $valor): string
    {
        $fecha = $valor instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($valor)
            : new \DateTimeImmutable((string) $valor);

        return $fecha->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private static function facturaDeOperacion(string $uuid): ?object
    {
        return self::dbFacturacion()->selectOne(
            'SELECT o.id_operacion, o.id_usuario, o.id_sucursal,
                    f.id_factura, f.numero_comprobante, f.secuencial, f.estado,
                    f.subtotal, f.descuento, f.valor_impuesto, f.total,
                    f.razon_social_cliente, f.codigo_punto_emision,
                    mp.nombre AS metodo_pago
             FROM operacion_idempotente o
             LEFT JOIN factura f ON f.id_operacion = o.id_operacion
             LEFT JOIN pago p ON p.id_factura = f.id_factura
             LEFT JOIN metodo_pago mp ON mp.id_metodo_pago = p.id_metodo_pago
             WHERE o.id_operacion = ?',
            [$uuid]
        );
    }

    private static function exigirMismaOperacion(object $operacion, int $idUsuario, int $idSucursal): void
    {
        if ((int) $operacion->id_usuario !== $idUsuario || (int) $operacion->id_sucursal !== $idSucursal) {
            throw new VentaRechazada('No disponible');
        }

        if ($operacion->id_factura === null) {
            throw new VentaRechazada('No disponible');
        }
    }

    private static function respuestaFactura(object $factura, bool $idempotente): array
    {
        return [
            'exito' => true,
            'idempotente' => $idempotente,
            'factura' => [
                'idFactura' => (int) $factura->id_factura,
                'numeroComprobante' => (string) $factura->numero_comprobante,
                'secuencial' => (int) $factura->secuencial,
                'estado' => (string) $factura->estado,
                'subtotal' => self::redondear((string) $factura->subtotal),
                'descuento' => self::redondear((string) $factura->descuento),
                'valorImpuesto' => self::redondear((string) $factura->valor_impuesto),
                'total' => self::redondear((string) $factura->total),
                'cliente' => (string) $factura->razon_social_cliente,
                'metodoPago' => (string) ($factura->metodo_pago ?? ''),
                'codigoPuntoEmision' => (string) $factura->codigo_punto_emision,
            ],
        ];
    }

    private static function uuid(mixed $valor): string
    {
        if (!is_string($valor)) {
            throw new VentaRechazada('Identificador de operación no válido');
        }

        $uuid = strtolower(trim($valor));

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid) !== 1) {
            throw new VentaRechazada('Identificador de operación no válido');
        }

        return $uuid;
    }

    private static function entero(mixed $valor, string $mensaje): int
    {
        if (is_int($valor)) {
            return $valor > 0 ? $valor : throw new VentaRechazada($mensaje);
        }

        if (is_string($valor) && preg_match('/^[1-9][0-9]*$/', $valor) === 1) {
            return (int) $valor;
        }

        throw new VentaRechazada($mensaje);
    }

    private static function porcentajeDescuento(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        if (is_int($valor) || is_float($valor)) {
            $numero = (float) $valor;
        } elseif (is_string($valor) && preg_match('/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/', $valor) === 1) {
            $numero = (float) $valor;
        } else {
            throw new VentaRechazada('Descuento no válido.');
        }

        if ($numero < 0 || $numero > 100) {
            throw new VentaRechazada('Descuento no válido.');
        }

        return round($numero, 2, PHP_ROUND_HALF_UP);
    }

    private static function patron(string $texto): string
    {
        return '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $texto) . '%';
    }

    private static function esVerdadero(mixed $valor): bool
    {
        return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true';
    }

    private static function redondear(float|string $valor): string
    {
        return number_format(round((float) $valor, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }

    private static function esDuplicadoOperacion(QueryException $e): bool
    {
        $estado = (string) ($e->errorInfo[0] ?? '');

        if ($estado !== '23505') {
            return false;
        }

        $detalle = $e->getMessage();

        return str_contains($detalle, 'id_operacion') || str_contains($detalle, 'operacion_idempotente');
    }

    private static function revertir(): void
    {
        $db = self::dbFacturacion();

        if ($db->transactionLevel() > 0) {
            $db->rollBack();
        }
    }

    private static function productosParaPendiente(array $cantidades): array
    {
        $productos = [];

        foreach ($cantidades as $idProducto => $cantidad) {
            $productos[] = [
                'id_producto' => (int) $idProducto,
                'cantidad' => (int) $cantidad,
            ];
        }

        return $productos;
    }

    private static function referenciaOpcional(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }

        $texto = trim($valor);

        if ($texto === '') {
            return null;
        }

        return mb_substr($texto, 0, 100);
    }

    /**
     * @param array<string,mixed> $entrada
     */
    private static function referenciaPago(array $entrada): ?string
    {
        $directa = self::referenciaOpcional($entrada['referencia_pago'] ?? null);

        if ($directa !== null) {
            return $directa;
        }

        $transferencia = $entrada['transferencia'] ?? null;

        if (!is_array($transferencia)) {
            return null;
        }

        $banco = trim((string) ($transferencia['banco'] ?? ''));
        $numero = trim((string) ($transferencia['numero_cuenta'] ?? $transferencia['numero'] ?? ''));
        $comprobante = trim((string) ($transferencia['comprobante'] ?? ''));

        if ($banco === '' && $numero === '' && $comprobante === '') {
            return null;
        }

        $bancoCorto = preg_replace('/^banco\s+(del\s+)?/iu', '', $banco) ?? $banco;
        $bancoCorto = trim((string) $bancoCorto);

        if ($bancoCorto === '') {
            $bancoCorto = $banco;
        }

        $partesCompletas = array_values(array_filter([$bancoCorto, $numero, $comprobante], static function (string $parte): bool {
            return $parte !== '';
        }));
        $completa = implode(' | ', $partesCompletas);

        if (mb_strlen($completa) <= 100) {
            return $completa === '' ? null : $completa;
        }

        $prioridad = array_values(array_filter([$comprobante, $bancoCorto, $numero], static function (string $parte): bool {
            return $parte !== '';
        }));
        $recortada = implode(' | ', $prioridad);

        return $recortada === '' ? null : mb_substr($recortada, 0, 100);
    }

    private static function registrarVentaPendiente(
        string $uuid,
        int $idUsuario,
        int $idCliente,
        int $idPunto,
        int $idMetodo,
        array $productos,
        float $porcentajeDescuento = 0.0,
        ?string $referenciaPago = null
    ): array {
        $mensaje = [
            'id_operacion' => $uuid,
            'tipo_operacion' => self::TIPO_OPERACION,
            'id_usuario' => $idUsuario,
            'id_cliente' => $idCliente,
            'id_punto_emision' => $idPunto,
            'id_metodo_pago' => $idMetodo,
            'porcentaje_descuento' => $porcentajeDescuento,
            'productos' => $productos,
            'referencia_pago' => $referenciaPago,
            'fecha_solicitud' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format('Y-m-d\TH:i:s.v\Z'),
        ];

        try {
            (new RedpandaService())->publicar(self::TOPIC_FACTURAS_PENDIENTES, $mensaje, $uuid);
        } catch (Throwable $e) {
            error_log('AWR confirmarVenta pendiente: ' . $e->getMessage());
            throw new VentaRechazada(
                'No se pudo registrar la operación. Intente nuevamente cuando el servicio se encuentre disponible.'
            );
        }

        return [
            'exito' => true,
            'estado' => 'EN_PROCESO',
            'mensaje' => 'La operación fue recibida y será procesada automáticamente cuando el servicio se encuentre disponible.',
            'id_operacion' => $uuid,
        ];
    }

    private static function aplicarTimeoutVenta(): void
    {
        self::dbFacturacion()->statement('SET statement_timeout TO ' . self::TIMEOUT_VENTA_MS);
    }

    private static function restablecerTimeoutVenta(): void
    {
        try {
            self::dbFacturacion()->statement('SET statement_timeout TO 0');
        } catch (Throwable $e) {
            self::abortarConexionVenta();
        }
    }

    private static function abortarConexionVenta(): void
    {
        try {
            self::dbFacturacion()->disconnect();
        } catch (Throwable $e) {
        }
    }

    private static function esDegradacionInfraestructura(Throwable $e): bool
    {
        $estado = '';

        if ($e instanceof QueryException) {
            $estado = (string) ($e->errorInfo[0] ?? '');
        } elseif (isset($e->errorInfo[0]) && is_string($e->errorInfo[0])) {
            $estado = $e->errorInfo[0];
        }

        if (in_array($estado, [
            '57014',
            '08000',
            '08001',
            '08003',
            '08004',
            '08006',
            '08007',
            '57P01',
            '57P02',
            '57P03',
            'HY000',
        ], true)) {
            return true;
        }

        $detalle = strtolower($e->getMessage());

        foreach ([
            'statement timeout',
            'query_canceled',
            'canceling statement',
            'connection refused',
            'could not connect',
            'server closed the connection',
            'broken pipe',
            'timeout expired',
            'connection timed out',
            'no connection to the server',
            'ssl connection has been closed',
            'terminating connection',
        ] as $fragmento) {
            if (str_contains($detalle, $fragmento)) {
                return true;
            }
        }

        return $e instanceof VentaDegradada;
    }
}
