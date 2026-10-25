<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;
use longlang\phpkafka\Broker;
use longlang\phpkafka\Consumer\ConsumeMessage;
use longlang\phpkafka\Consumer\Consumer;
use longlang\phpkafka\Consumer\ConsumerConfig;
use longlang\phpkafka\Protocol\DescribeGroups\DescribeGroupsRequest;
use longlang\phpkafka\Protocol\FindCoordinator\FindCoordinatorRequest;
use longlang\phpkafka\Protocol\ListOffset\ListOffsetPartition;
use longlang\phpkafka\Protocol\ListOffset\ListOffsetRequest;
use longlang\phpkafka\Protocol\ListOffset\ListOffsetTopic;
use longlang\phpkafka\Protocol\OffsetCommit\OffsetCommitRequest;
use longlang\phpkafka\Protocol\OffsetCommit\OffsetCommitRequestPartition;
use longlang\phpkafka\Protocol\OffsetCommit\OffsetCommitRequestTopic;
use longlang\phpkafka\Protocol\OffsetFetch\OffsetFetchRequest;
use longlang\phpkafka\Protocol\OffsetFetch\OffsetFetchRequestTopic;
use Throwable;

class EstadoResilienciaService
{
    private const TOPIC_PENDIENTES = 'awr.facturas.pendientes';
    private const GRUPO_CONSUMIDOR = 'awr_consumidor_facturas_pendientes';
    private const GRUPO_LECTURA = 'awr_admin_lectura_facturas_pendientes';
    private const PARTICION = 0;
    private const MAX_MENSAJES = 50;

    public static function obtenerResumen(): array
    {
        $broker = null;

        try {
            $broker = self::abrirBroker('awr-estado-resiliencia');
            $offsets = self::consultarOffsets($broker);
            $consumidor = self::consultarEstadoConsumidor($broker);

            return [
                'disponible' => true,
                'topic' => self::TOPIC_PENDIENTES,
                'grupo' => self::GRUPO_CONSUMIDOR,
                'facturas_pendientes' => $offsets['lag'],
                'high_watermark' => $offsets['high_watermark'],
                'committed_offset' => $offsets['committed_offset'],
                'sin_offset_confirmado' => $offsets['sin_offset_confirmado'],
                'redpanda' => [
                    'estado' => 'Operativo',
                ],
                'consumidor' => $consumidor,
                'nota' => $offsets['lag'] === 0
                    ? 'Sin operaciones pendientes'
                    : 'Operaciones esperando reproceso',
            ];
        } catch (Throwable $e) {
            error_log('AWR EstadoResilienciaService obtenerResumen: ' . $e->getMessage());

            return self::resumenNoDisponible();
        } finally {
            self::cerrarBroker($broker);
        }
    }

    public static function obtenerOperacionesPendientes(): array
    {
        $broker = null;

        try {
            $broker = self::abrirBroker('awr-estado-resiliencia');
            $offsets = self::consultarOffsets($broker);
            $lag = $offsets['lag'];

            if ($lag === 0) {
                return [
                    'disponible' => true,
                    'facturas_pendientes' => 0,
                    'operaciones' => [],
                    'nota' => 'Sin operaciones pendientes',
                ];
            }

            $inicio = $offsets['inicio_pendiente'];
            $limite = min($lag, self::MAX_MENSAJES);
            $mensajes = self::leerMensajesPendientes($inicio, $limite);
            $crudos = [];

            foreach ($mensajes as $mensaje) {
                $crudos[] = $mensaje['value'];
            }

            $operaciones = self::proyectarOperaciones($crudos);
            $operaciones = self::filtrarSinFactura($operaciones);
            self::enriquecerDesdePostgres($operaciones);
            $visibles = count($operaciones);

            return [
                'disponible' => true,
                'facturas_pendientes' => $lag,
                'operaciones' => $operaciones,
                'nota' => $visibles === 0
                    ? 'Sin operaciones pendientes'
                    : ($visibles === 1
                        ? '1 operación esperando reproceso'
                        : $visibles . ' operaciones esperando reproceso'),
            ];
        } catch (Throwable $e) {
            error_log('AWR EstadoResilienciaService obtenerOperacionesPendientes: ' . $e->getMessage());

            return [
                'disponible' => false,
                'facturas_pendientes' => null,
                'operaciones' => [],
                'nota' => 'No disponible',
            ];
        } finally {
            self::cerrarBroker($broker);
        }
    }

    /**
     * @return array{
     *   id_operacion:string,
     *   estado:string,
     *   mensaje:string,
     *   id_factura:?int,
     *   numero_comprobante:?string
     * }
     */
    public static function procesarFacturaPendiente(string $idOperacion): array
    {
        $idOperacion = strtolower(trim($idOperacion));

        if (!self::uuidValido($idOperacion)) {
            return self::resultadoProceso($idOperacion, 'Error', 'Operación no válida', null, null);
        }

        try {
            $localizada = self::localizarOperacionPendiente($idOperacion);

            if ($localizada === null) {
                $factura = self::facturaPorOperacion($idOperacion);

                if ($factura !== null) {
                    self::avanzarPrefijoResuelto();

                    return self::resultadoProceso(
                        $idOperacion,
                        'Procesada',
                        'La operación ya estaba confirmada.',
                        (int) $factura->id_factura,
                        (string) $factura->numero_comprobante
                    );
                }

                return self::resultadoProceso(
                    $idOperacion,
                    'Error',
                    'La operación no está pendiente en Redpanda.',
                    null,
                    null
                );
            }

            $facturaExistente = self::facturaPorOperacion($idOperacion);

            if ($facturaExistente !== null) {
                self::avanzarPrefijoResuelto();

                return self::resultadoProceso(
                    $idOperacion,
                    'Procesada',
                    'La operación ya estaba confirmada.',
                    (int) $facturaExistente->id_factura,
                    (string) $facturaExistente->numero_comprobante
                );
            }

            $datos = $localizada['datos'];
            $idUsuario = self::enteroOpcional($datos['id_usuario'] ?? null);

            if ($idUsuario === null) {
                return self::resultadoProceso($idOperacion, 'Error', 'Usuario no válido en el mensaje.', null, null);
            }

            $entrada = [
                'id_operacion' => $idOperacion,
                'id_cliente' => $datos['id_cliente'] ?? null,
                'id_punto_emision' => $datos['id_punto_emision'] ?? null,
                'id_metodo_pago' => $datos['id_metodo_pago'] ?? null,
                'porcentaje_descuento' => $datos['porcentaje_descuento'] ?? 0,
                'productos' => $datos['productos'] ?? null,
                'referencia_pago' => $datos['referencia_pago'] ?? null,
                'transferencia' => $datos['transferencia'] ?? null,
            ];

            try {
                $respuesta = VentaService::confirmarVenta($idUsuario, $entrada, false);
            } catch (VentaDegradada $e) {
                return self::resultadoProceso(
                    $idOperacion,
                    'Sigue pendiente',
                    'PostgreSQL no confirmó la operación.',
                    null,
                    null
                );
            } catch (VentaRechazada $e) {
                return self::resultadoProceso(
                    $idOperacion,
                    'Error',
                    $e->getMessage() !== '' ? $e->getMessage() : 'La operación fue rechazada.',
                    null,
                    null
                );
            }

            if (($respuesta['exito'] ?? false) === true && isset($respuesta['factura']['idFactura'])) {
                self::avanzarPrefijoResuelto();

                return self::resultadoProceso(
                    $idOperacion,
                    'Procesada',
                    'Operación confirmada.',
                    (int) $respuesta['factura']['idFactura'],
                    is_string($respuesta['factura']['numeroComprobante'] ?? null)
                        ? $respuesta['factura']['numeroComprobante']
                        : null
                );
            }

            return self::resultadoProceso(
                $idOperacion,
                'Sigue pendiente',
                'PostgreSQL no confirmó la operación.',
                null,
                null
            );
        } catch (Throwable $e) {
            error_log('AWR EstadoResilienciaService procesarFacturaPendiente: ' . $e->getMessage());

            return self::resultadoProceso($idOperacion, 'Error', 'No se pudo procesar la operación.', null, null);
        }
    }

    /**
     * @param list<string> $idsOperacion
     * @return list<array{id_operacion:string,estado:string,mensaje:string,id_factura:?int,numero_comprobante:?string}>
     */
    public static function procesarFacturasPendientes(array $idsOperacion): array
    {
        $resultados = [];
        $vistos = [];

        foreach ($idsOperacion as $id) {
            if (!is_string($id)) {
                continue;
            }

            $normalizado = strtolower(trim($id));

            if ($normalizado === '' || isset($vistos[$normalizado])) {
                continue;
            }

            $vistos[$normalizado] = true;
            $resultados[] = self::procesarFacturaPendiente($normalizado);
        }

        return $resultados;
    }

    /**
     * Cantidad de operaciones pendientes del topic filtradas por sucursal
     * (vía id_punto_emision → punto_emision.id_sucursal).
     *
     * @return array{disponible:bool,cantidad:int|null,valor:string}
     */
    public static function obtenerCantidadPendientesSucursal(int $idSucursal): array
    {
        if ($idSucursal < 1) {
            return [
                'disponible' => false,
                'cantidad' => null,
                'valor' => 'No disponible',
            ];
        }

        $broker = null;

        try {
            $broker = self::abrirBroker('awr-estado-resiliencia-sucursal');
            $offsets = self::consultarOffsets($broker);
            $lag = $offsets['lag'];

            if ($lag === 0) {
                return [
                    'disponible' => true,
                    'cantidad' => 0,
                    'valor' => '0',
                ];
            }

            $inicio = $offsets['inicio_pendiente'];
            $limite = min($lag, self::MAX_MENSAJES);
            $mensajes = self::leerMensajesPendientes($inicio, $limite);
            $puntosSucursal = self::idsPuntoEmisionSucursal($idSucursal);
            $cantidad = 0;

            foreach ($mensajes as $mensaje) {
                try {
                    $datos = json_decode($mensaje['value'], true, 32, JSON_THROW_ON_ERROR);
                } catch (Throwable $e) {
                    continue;
                }

                if (!is_array($datos)) {
                    continue;
                }

                $idOperacion = is_string($datos['id_operacion'] ?? null)
                    ? strtolower(trim($datos['id_operacion']))
                    : '';

                if ($idOperacion !== '' && self::facturaPorOperacion($idOperacion) !== null) {
                    continue;
                }

                $idPunto = self::enteroOpcional($datos['id_punto_emision'] ?? null);

                if ($idPunto !== null && isset($puntosSucursal[$idPunto])) {
                    $cantidad++;
                }
            }

            return [
                'disponible' => true,
                'cantidad' => $cantidad,
                'valor' => (string) $cantidad,
            ];
        } catch (Throwable $e) {
            error_log('AWR EstadoResilienciaService obtenerCantidadPendientesSucursal: ' . $e->getMessage());

            return [
                'disponible' => false,
                'cantidad' => null,
                'valor' => 'No disponible',
            ];
        } finally {
            self::cerrarBroker($broker);
        }
    }

    /**
     * @return array<int,true>
     */
    private static function idsPuntoEmisionSucursal(int $idSucursal): array
    {
        $filas = DB::table('punto_emision')
            ->where('id_sucursal', $idSucursal)
            ->get(['id_punto_emision']);

        $mapa = [];

        foreach ($filas as $fila) {
            $mapa[(int) $fila->id_punto_emision] = true;
        }

        return $mapa;
    }

    private static function resumenNoDisponible(): array
    {
        return [
            'disponible' => false,
            'topic' => self::TOPIC_PENDIENTES,
            'grupo' => self::GRUPO_CONSUMIDOR,
            'facturas_pendientes' => null,
            'high_watermark' => null,
            'committed_offset' => null,
            'sin_offset_confirmado' => null,
            'redpanda' => [
                'estado' => 'No disponible',
            ],
            'consumidor' => [
                'estado' => 'No disponible',
                'miembros' => null,
            ],
            'nota' => 'No disponible',
        ];
    }

    private static function abrirBroker(string $clientId): Broker
    {
        $brokers = trim((string) ($_ENV['REDPANDA_BROKERS'] ?? ''));

        if ($brokers === '' || preg_match('/^[A-Za-z0-9._:-]+(?:,[A-Za-z0-9._:-]+)*$/', $brokers) !== 1) {
            throw new \RuntimeException('Redpanda no está disponible');
        }

        $config = new ConsumerConfig();
        $config->setBootstrapServer($brokers);
        $config->setUpdateBrokers(true);
        $config->setClientId($clientId);
        $config->setConnectTimeout(2.0);
        $config->setSendTimeout(2.0);
        $config->setRecvTimeout(2.0);

        set_error_handler(static function (): bool {
            throw new \RuntimeException('Redpanda no está disponible');
        });

        try {
            $broker = new Broker($config);
            $broker->updateBrokers();
        } finally {
            restore_error_handler();
        }

        return $broker;
    }

    private static function cerrarBroker(?Broker $broker): void
    {
        if (!$broker instanceof Broker) {
            return;
        }

        try {
            $broker->close();
        } catch (Throwable $e) {
        }
    }

    /**
     * @return array{
     *   lag:int,
     *   high_watermark:int,
     *   committed_offset:int|null,
     *   inicio_pendiente:int,
     *   sin_offset_confirmado:bool
     * }
     */
    private static function consultarOffsets(Broker $broker): array
    {
        $client = $broker->getClient();
        $high = self::listarOffset($client, -1);
        $earliest = self::listarOffset($client, -2);

        $fc = new FindCoordinatorRequest();
        $fc->setKey(self::GRUPO_CONSUMIDOR);
        $fc->setKeyType(0);
        $fcr = $client->sendRecv($fc);
        $coord = $broker->getClient((int) $fcr->getNodeId());

        $of = new OffsetFetchRequest();
        $of->setGroupId(self::GRUPO_CONSUMIDOR);
        $of->setTopics([
            (new OffsetFetchRequestTopic())
                ->setName(self::TOPIC_PENDIENTES)
                ->setPartitionIndexes([self::PARTICION]),
        ]);
        $ofr = $coord->sendRecv($of);

        $committed = null;
        $sinConfirmado = true;
        $topics = $ofr->getTopics();

        if ($topics !== []) {
            $partitions = $topics[0]->getPartitions();
            if ($partitions !== []) {
                $valor = (int) $partitions[0]->getCommittedOffset();
                if ($valor >= 0) {
                    $committed = $valor;
                    $sinConfirmado = false;
                }
            }
        }

        $inicio = $sinConfirmado ? max(0, $earliest) : (int) $committed;
        $lag = max(0, $high - $inicio);

        return [
            'lag' => $lag,
            'high_watermark' => $high,
            'committed_offset' => $committed,
            'inicio_pendiente' => $inicio,
            'sin_offset_confirmado' => $sinConfirmado,
        ];
    }

    private static function listarOffset($client, int $timestamp): int
    {
        $request = new ListOffsetRequest();
        $request->setReplicaId(-1);
        $request->setIsolationLevel(0);
        $request->setTopics([
            (new ListOffsetTopic())->setName(self::TOPIC_PENDIENTES)->setPartitions([
                (new ListOffsetPartition())
                    ->setPartitionIndex(self::PARTICION)
                    ->setTimestamp($timestamp)
                    ->setCurrentLeaderEpoch(-1),
            ]),
        ]);

        $response = $client->sendRecv($request);
        $topics = $response->getTopics();

        if ($topics === []) {
            throw new \RuntimeException('No se pudo consultar offsets');
        }

        $partitions = $topics[0]->getPartitions();

        if ($partitions === []) {
            throw new \RuntimeException('No se pudo consultar offsets');
        }

        return max(0, (int) $partitions[0]->getOffset());
    }

    /**
     * @return array{estado:string,miembros:int|null}
     */
    private static function consultarEstadoConsumidor(Broker $broker): array
    {
        try {
            $client = $broker->getClient();
            $fc = new FindCoordinatorRequest();
            $fc->setKey(self::GRUPO_CONSUMIDOR);
            $fc->setKeyType(0);
            $fcr = $client->sendRecv($fc);
            $coord = $broker->getClient((int) $fcr->getNodeId());

            $dg = new DescribeGroupsRequest();
            $dg->setGroups([self::GRUPO_CONSUMIDOR]);
            $dgr = $coord->sendRecv($dg);
            $grupos = $dgr->getGroups();

            if ($grupos === []) {
                return [
                    'estado' => 'Inactivo',
                    'miembros' => 0,
                ];
            }

            $grupo = $grupos[0];
            $miembros = count($grupo->getMembers());
            $estadoKafka = (string) $grupo->getGroupState();

            if ($miembros > 0 || strcasecmp($estadoKafka, 'Stable') === 0) {
                return [
                    'estado' => 'Activo',
                    'miembros' => $miembros,
                ];
            }

            return [
                'estado' => 'Inactivo',
                'miembros' => $miembros,
            ];
        } catch (Throwable $e) {
            error_log('AWR EstadoResilienciaService consumidor: ' . $e->getMessage());

            return [
                'estado' => 'No disponible',
                'miembros' => null,
            ];
        }
    }

    /**
     * @return array{offset:int,datos:array<string,mixed>}|null
     */
    private static function localizarOperacionPendiente(string $idOperacion): ?array
    {
        $broker = null;

        try {
            $broker = self::abrirBroker('awr-estado-resiliencia-localizar');
            $offsets = self::consultarOffsets($broker);
            $lag = $offsets['lag'];

            if ($lag === 0) {
                return null;
            }

            $mensajes = self::leerMensajesPendientes(
                $offsets['inicio_pendiente'],
                min($lag, self::MAX_MENSAJES)
            );

            foreach ($mensajes as $mensaje) {
                try {
                    $datos = json_decode($mensaje['value'], true, 32, JSON_THROW_ON_ERROR);
                } catch (Throwable $e) {
                    continue;
                }

                if (!is_array($datos)) {
                    continue;
                }

                $id = is_string($datos['id_operacion'] ?? null)
                    ? strtolower(trim($datos['id_operacion']))
                    : '';

                if ($id === $idOperacion) {
                    return [
                        'offset' => $mensaje['offset'],
                        'datos' => $datos,
                    ];
                }
            }

            return null;
        } finally {
            self::cerrarBroker($broker);
        }
    }

    private static function avanzarPrefijoResuelto(): void
    {
        $broker = null;

        try {
            $broker = self::abrirBroker('awr-estado-resiliencia-prefijo');
            $offsets = self::consultarOffsets($broker);
            $inicio = $offsets['inicio_pendiente'];
            $lag = $offsets['lag'];

            if ($lag === 0) {
                return;
            }

            $mensajes = self::leerMensajesPendientes($inicio, min($lag, self::MAX_MENSAJES));
            $nuevoCommitted = $inicio;

            foreach ($mensajes as $mensaje) {
                try {
                    $datos = json_decode($mensaje['value'], true, 32, JSON_THROW_ON_ERROR);
                } catch (Throwable $e) {
                    break;
                }

                if (!is_array($datos)) {
                    break;
                }

                $id = is_string($datos['id_operacion'] ?? null)
                    ? strtolower(trim($datos['id_operacion']))
                    : '';

                if ($id === '' || self::facturaPorOperacion($id) === null) {
                    break;
                }

                $nuevoCommitted = $mensaje['offset'] + 1;
            }

            if ($nuevoCommitted > $inicio) {
                self::confirmarOffsetGrupo($broker, $nuevoCommitted);
            }
        } catch (Throwable $e) {
            error_log('AWR EstadoResilienciaService avanzarPrefijoResuelto: ' . $e->getMessage());
        } finally {
            self::cerrarBroker($broker);
        }
    }

    private static function confirmarOffsetGrupo(Broker $broker, int $committedOffset): void
    {
        if ($committedOffset < 0) {
            return;
        }

        $client = $broker->getClient();
        $fc = new FindCoordinatorRequest();
        $fc->setKey(self::GRUPO_CONSUMIDOR);
        $fc->setKeyType(0);
        $fcr = $client->sendRecv($fc);
        $coord = $broker->getClient((int) $fcr->getNodeId());

        $request = new OffsetCommitRequest();
        $request->setGroupId(self::GRUPO_CONSUMIDOR);
        $request->setGenerationId(-1);
        $request->setMemberId('');
        $request->setGroupInstanceId(null);

        $topic = (new OffsetCommitRequestTopic())->setName(self::TOPIC_PENDIENTES);
        $topic->setPartitions([
            (new OffsetCommitRequestPartition())
                ->setPartitionIndex(self::PARTICION)
                ->setCommittedOffset($committedOffset)
                ->setCommittedLeaderEpoch(-1)
                ->setCommittedMetadata('awr-admin-prefijo'),
        ]);
        $request->setTopics([$topic]);

        $response = $coord->sendRecv($request);
        $topics = $response->getTopics();

        if ($topics === []) {
            throw new \RuntimeException('No se pudo confirmar el offset');
        }

        $partitions = $topics[0]->getPartitions();

        if ($partitions === [] || (int) $partitions[0]->getErrorCode() !== 0) {
            throw new \RuntimeException('No se pudo confirmar el offset');
        }
    }

    private static function facturaPorOperacion(string $idOperacion): ?object
    {
        return DB::table('factura')
            ->where('id_operacion', $idOperacion)
            ->first(['id_factura', 'numero_comprobante', 'id_operacion']);
    }

    /**
     * @param list<array<string,mixed>> $operaciones
     * @return list<array<string,mixed>>
     */
    private static function filtrarSinFactura(array $operaciones): array
    {
        if ($operaciones === []) {
            return [];
        }

        $ids = [];

        foreach ($operaciones as $op) {
            if (is_string($op['id_operacion'] ?? null) && $op['id_operacion'] !== '') {
                $ids[] = strtolower($op['id_operacion']);
            }
        }

        if ($ids === []) {
            return [];
        }

        $existentes = DB::table('factura')
            ->whereIn('id_operacion', $ids)
            ->pluck('id_operacion')
            ->all();

        $mapa = [];

        foreach ($existentes as $id) {
            $mapa[strtolower((string) $id)] = true;
        }

        $filtradas = [];

        foreach ($operaciones as $op) {
            $id = is_string($op['id_operacion'] ?? null) ? strtolower($op['id_operacion']) : '';

            if ($id !== '' && !isset($mapa[$id])) {
                $filtradas[] = $op;
            }
        }

        return $filtradas;
    }

    /**
     * @return array{
     *   id_operacion:string,
     *   estado:string,
     *   mensaje:string,
     *   id_factura:?int,
     *   numero_comprobante:?string
     * }
     */
    private static function resultadoProceso(
        string $idOperacion,
        string $estado,
        string $mensaje,
        ?int $idFactura,
        ?string $numeroComprobante
    ): array {
        return [
            'id_operacion' => $idOperacion,
            'estado' => $estado,
            'mensaje' => $mensaje,
            'id_factura' => $idFactura,
            'numero_comprobante' => $numeroComprobante,
        ];
    }

    private static function uuidValido(string $valor): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $valor
        ) === 1;
    }

    /**
     * @return list<array{offset:int,value:string}>
     */
    private static function leerMensajesPendientes(int $inicio, int $limite): array
    {
        $brokers = trim((string) ($_ENV['REDPANDA_BROKERS'] ?? ''));

        if ($brokers === '' || preg_match('/^[A-Za-z0-9._:-]+(?:,[A-Za-z0-9._:-]+)*$/', $brokers) !== 1) {
            throw new \RuntimeException('Redpanda no está disponible');
        }

        $config = new ConsumerConfig();
        $config->setBootstrapServer($brokers);
        $config->setUpdateBrokers(true);
        $config->setTopic(self::TOPIC_PENDIENTES);
        $config->setGroupId(self::GRUPO_LECTURA);
        $config->setClientId('awr-admin-lectura-facturas-pendientes');
        $config->setAutoCommit(false);
        $config->setAutoCreateTopic(false);
        $config->setInterval(0.2);
        $config->setMaxWait(1);
        $config->setConnectTimeout(2.0);
        $config->setSendTimeout(2.0);
        $config->setRecvTimeout(2.0);

        set_error_handler(static function (): bool {
            throw new \RuntimeException('Redpanda no está disponible');
        });

        try {
            $consumer = new Consumer($config);
        } finally {
            restore_error_handler();
        }

        try {
            $offsetManager = $consumer->getOffsetManager(self::TOPIC_PENDIENTES);
            $actual = $offsetManager->getFetchOffset(self::PARTICION);

            if ($actual < $inicio) {
                $offsetManager->addFetchOffset(self::PARTICION, $inicio - $actual);
            } elseif ($actual > $inicio) {
                $offsetManager->addFetchOffset(self::PARTICION, $inicio - $actual);
            }

            $valores = [];
            $inicioTiempo = microtime(true);

            while (count($valores) < $limite && (microtime(true) - $inicioTiempo) < 8.0) {
                $offsetActual = $offsetManager->getFetchOffset(self::PARTICION);
                $mensaje = $consumer->consume();

                if (!$mensaje instanceof ConsumeMessage) {
                    continue;
                }

                $valor = $mensaje->getValue();

                if (is_string($valor) && $valor !== '') {
                    $valores[] = [
                        'offset' => $offsetActual,
                        'value' => $valor,
                    ];
                }

                $offsetManager->addFetchOffset($mensaje->getPartition(), 1);
            }

            return $valores;
        } finally {
            try {
                $consumer->close();
            } catch (Throwable $e) {
            }
        }
    }

    /**
     * @param list<string> $crudos
     * @return list<array<string,mixed>>
     */
    private static function proyectarOperaciones(array $crudos): array
    {
        $operaciones = [];

        foreach ($crudos as $crudo) {
            try {
                $datos = json_decode($crudo, true, 32, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                continue;
            }

            if (!is_array($datos)) {
                continue;
            }

            $productos = is_array($datos['productos'] ?? null) ? $datos['productos'] : [];
            $productosProyectados = [];

            foreach ($productos as $producto) {
                if (!is_array($producto)) {
                    continue;
                }

                $idProducto = $producto['id_producto'] ?? null;
                $cantidad = $producto['cantidad'] ?? null;

                if (!is_int($idProducto) && !(is_string($idProducto) && preg_match('/^[1-9][0-9]*$/', $idProducto) === 1)) {
                    continue;
                }

                if (!is_int($cantidad) && !(is_string($cantidad) && preg_match('/^[1-9][0-9]*$/', $cantidad) === 1)) {
                    continue;
                }

                $productosProyectados[] = [
                    'id_producto' => (int) $idProducto,
                    'cantidad' => (int) $cantidad,
                    'nombre_producto' => null,
                ];
            }

            $operaciones[] = [
                'id_operacion' => is_string($datos['id_operacion'] ?? null) ? $datos['id_operacion'] : null,
                'fecha_solicitud' => is_string($datos['fecha_solicitud'] ?? null) ? $datos['fecha_solicitud'] : null,
                'id_cliente' => self::enteroOpcional($datos['id_cliente'] ?? null),
                'cliente' => null,
                'id_punto_emision' => self::enteroOpcional($datos['id_punto_emision'] ?? null),
                'punto_emision' => null,
                'id_metodo_pago' => self::enteroOpcional($datos['id_metodo_pago'] ?? null),
                'metodo_pago' => null,
                'cantidad_productos' => count($productosProyectados),
                'productos' => $productosProyectados,
                'estado' => 'Pendiente de reproceso',
            ];
        }

        return $operaciones;
    }

    /**
     * @param list<array<string,mixed>> $operaciones
     */
    private static function enriquecerDesdePostgres(array &$operaciones): void
    {
        if ($operaciones === []) {
            return;
        }

        $idsCliente = [];
        $idsPunto = [];
        $idsMetodo = [];
        $idsProducto = [];

        foreach ($operaciones as $op) {
            if (is_int($op['id_cliente'] ?? null)) {
                $idsCliente[$op['id_cliente']] = true;
            }
            if (is_int($op['id_punto_emision'] ?? null)) {
                $idsPunto[$op['id_punto_emision']] = true;
            }
            if (is_int($op['id_metodo_pago'] ?? null)) {
                $idsMetodo[$op['id_metodo_pago']] = true;
            }
            foreach ($op['productos'] as $producto) {
                if (is_int($producto['id_producto'] ?? null)) {
                    $idsProducto[$producto['id_producto']] = true;
                }
            }
        }

        try {
            $clientes = self::mapaConsulta(
                'cliente',
                'id_cliente',
                'razon_social',
                array_keys($idsCliente)
            );
            $puntos = self::mapaConsulta(
                'punto_emision',
                'id_punto_emision',
                'descripcion',
                array_keys($idsPunto)
            );
            $metodos = self::mapaConsulta(
                'metodo_pago',
                'id_metodo_pago',
                'nombre',
                array_keys($idsMetodo)
            );
            $productos = self::mapaConsulta(
                'producto',
                'id_producto',
                'nombre',
                array_keys($idsProducto)
            );
        } catch (Throwable $e) {
            error_log('AWR EstadoResilienciaService enriquecimiento: ' . $e->getMessage());

            return;
        }

        foreach ($operaciones as &$op) {
            $idCliente = $op['id_cliente'] ?? null;
            $idPunto = $op['id_punto_emision'] ?? null;
            $idMetodo = $op['id_metodo_pago'] ?? null;

            if (is_int($idCliente) && isset($clientes[$idCliente])) {
                $op['cliente'] = $clientes[$idCliente];
            }
            if (is_int($idPunto) && isset($puntos[$idPunto])) {
                $op['punto_emision'] = $puntos[$idPunto];
            }
            if (is_int($idMetodo) && isset($metodos[$idMetodo])) {
                $op['metodo_pago'] = $metodos[$idMetodo];
            }

            foreach ($op['productos'] as &$producto) {
                $idProducto = $producto['id_producto'] ?? null;
                if (is_int($idProducto) && isset($productos[$idProducto])) {
                    $producto['nombre_producto'] = $productos[$idProducto];
                }
            }
            unset($producto);
        }
        unset($op);
    }

    /**
     * @param list<int|string> $ids
     * @return array<int,string>
     */
    private static function mapaConsulta(string $tabla, string $columnaId, string $columnaNombre, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $permitidas = [
            'cliente' => ['id_cliente', 'razon_social'],
            'punto_emision' => ['id_punto_emision', 'descripcion'],
            'metodo_pago' => ['id_metodo_pago', 'nombre'],
            'producto' => ['id_producto', 'nombre'],
        ];

        if (!isset($permitidas[$tabla]) || $permitidas[$tabla] !== [$columnaId, $columnaNombre]) {
            return [];
        }

        $filas = DB::table($tabla)
            ->whereIn($columnaId, $ids)
            ->get([$columnaId, $columnaNombre]);

        $mapa = [];

        foreach ($filas as $fila) {
            $id = (int) $fila->{$columnaId};
            $nombre = trim((string) $fila->{$columnaNombre});
            if ($nombre !== '') {
                $mapa[$id] = $nombre;
            }
        }

        return $mapa;
    }

    private static function enteroOpcional(mixed $valor): ?int
    {
        if (is_int($valor) && $valor > 0) {
            return $valor;
        }

        if (is_string($valor) && preg_match('/^[1-9][0-9]*$/', $valor) === 1) {
            return (int) $valor;
        }

        return null;
    }
}
