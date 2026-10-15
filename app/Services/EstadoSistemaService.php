<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Throwable;

class EstadoSistemaService
{
    private const API_TOXIPROXY = 'http://localhost:8474';
    private const PROXY_TOXIPROXY = 'awr_postgres_proxy';
    private const TOXIC_LATENCIA = 'awr_latency';
    private const HOST_POSTGRES_DIRECTO = '127.0.0.1';
    private const PUERTO_POSTGRES_DIRECTO = 5433;
    private const REDPANDA_ADMIN = 'http://127.0.0.1:9644/v1/status/ready';
    private const REDPANDA_BROKER = '127.0.0.1';
    private const REDPANDA_PUERTO_KAFKA = 19092;
    private const REDPANDA_PUERTO_ADMIN = 9644;

    public static function obtenerEstadoActual(): array
    {
        return [
            'aplicacion' => self::aplicacion(),
            'postgresql' => self::postgresql(),
            'toxiproxy' => self::toxiproxy(self::API_TOXIPROXY),
            'enlace' => self::enlace(),
            'redpanda' => self::redpanda(),
            'clickhouse' => self::clickhouse(),
        ];
    }

    private static function aplicacion(): array
    {
        return [
            'estado' => 'Operativo',
            'version_php' => PHP_VERSION,
            'memoria_usada_peticion' => memory_get_usage(true),
        ];
    }

    private static function postgresql(): array
    {
        $noDisponible = [
            'estado' => 'No disponible',
            'version' => null,
            'base' => null,
            'tablas' => null,
            'tamano_bytes' => null,
            'conexiones' => null,
            'tiempo_respuesta_ms' => null,
            'puerto' => null,
        ];

        try {
            $fila = DB::selectOne(
                "SELECT current_setting('server_version') AS version,
                        current_database() AS base,
                        pg_database_size(current_database()) AS tamano_bytes,
                        inet_server_port() AS puerto,
                        (SELECT COUNT(*)::int
                           FROM information_schema.tables
                          WHERE table_schema = 'public'
                            AND table_type = 'BASE TABLE') AS tablas,
                        (SELECT COUNT(*)::int FROM pg_stat_activity) AS conexiones"
            );
        } catch (Throwable $e) {
            error_log('AWR EstadoSistemaService postgresql: ' . $e->getMessage());

            return $noDisponible;
        }

        if ($fila === null) {
            return $noDisponible;
        }

        return [
            'estado' => 'Conectado',
            'version' => self::versionPostgres($fila->version ?? null),
            'base' => (string) $fila->base,
            'tablas' => (int) $fila->tablas,
            'tamano_bytes' => (int) $fila->tamano_bytes,
            'conexiones' => (int) $fila->conexiones,
            'tiempo_respuesta_ms' => self::medirSelect(),
            'puerto' => (int) $fila->puerto,
        ];
    }

    /**
     * Latencia base del enlace AWR ↔ PostgreSQL.
     * No es la latencia de Internet del usuario ni el retardo artificial de Toxiproxy.
     */
    private static function enlace(): array
    {
        return [
            'significado' => 'Latencia base del enlace AWR ↔ PostgreSQL',
            'latencia_red_ms' => self::medirLatenciaRed(),
        ];
    }

    private static function medirLatenciaRed(): ?float
    {
        try {
            return self::medirConexionTcp(self::HOST_POSTGRES_DIRECTO, self::PUERTO_POSTGRES_DIRECTO);
        } catch (Throwable $e) {
            error_log('AWR EstadoSistemaService latencia red: medicion no disponible');

            return null;
        }
    }

    private static function medirConexionTcp(string $host, int $puerto): ?float
    {
        $errno = 0;
        $error = '';
        $inicio = hrtime(true);
        $conexion = @stream_socket_client(
            'tcp://' . $host . ':' . $puerto,
            $errno,
            $error,
            2.0,
            STREAM_CLIENT_CONNECT
        );
        $transcurrido = hrtime(true) - $inicio;

        if (!is_resource($conexion)) {
            error_log('AWR EstadoSistemaService latencia red: medicion no disponible');

            return null;
        }

        fclose($conexion);

        if ($transcurrido < 0) {
            return null;
        }

        $milisegundos = $transcurrido / 1000000;
        $redondeado = round($milisegundos, 2);

        if ($redondeado <= 0 && $milisegundos > 0) {
            return round($milisegundos, 3);
        }

        return $redondeado;
    }

    private static function medirSelect(): ?int
    {
        try {
            $pdo = DB::connection()->getPdo();
            $inicio = hrtime(true);
            $resultado = $pdo->query('SELECT 1');

            if ($resultado !== false) {
                $resultado->fetchAll();
            }

            $milisegundos = (int) round((hrtime(true) - $inicio) / 1000000);

            return $milisegundos >= 0 ? $milisegundos : null;
        } catch (Throwable $e) {
            error_log('AWR EstadoSistemaService medirSelect: ' . $e->getMessage());

            return null;
        }
    }

    private static function redpanda(): array
    {
        $estado = [
            'estado' => 'No disponible',
            'broker' => self::REDPANDA_BROKER,
            'puerto_kafka' => self::REDPANDA_PUERTO_KAFKA,
            'puerto_admin' => self::REDPANDA_PUERTO_ADMIN,
        ];
        $cuerpo = self::obtenerHttp(self::REDPANDA_ADMIN);

        if ($cuerpo === null) {
            return $estado;
        }

        $datos = json_decode($cuerpo, true);

        if (is_array($datos) && ($datos['status'] ?? null) === 'ready') {
            $estado['estado'] = 'Operativo';
        }

        return $estado;
    }

    private static function clickhouse(): array
    {
        $noDisponible = [
            'estado' => 'No disponible',
            'version' => null,
            'base' => null,
            'tablas' => null,
            'tiempo_respuesta_ms' => null,
        ];
        $base = trim((string) ($_ENV['CLICKHOUSE_DATABASE'] ?? $_ENV['CLICKHOUSE_DB'] ?? ''));
        $usuario = trim((string) ($_ENV['CLICKHOUSE_USER'] ?? ''));
        $password = (string) ($_ENV['CLICKHOUSE_PASSWORD'] ?? '');
        $host = trim((string) ($_ENV['CLICKHOUSE_HOST'] ?? '127.0.0.1'));
        $puerto = trim((string) ($_ENV['CLICKHOUSE_HTTP_PORT'] ?? '8123'));

        if (
            preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $base) !== 1
            || $usuario === ''
            || $password === ''
            || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1
            || preg_match('/^[1-9][0-9]{0,4}$/', $puerto) !== 1
        ) {
            return $noDisponible;
        }

        $ping = self::obtenerHttp('http://' . $host . ':' . $puerto . '/ping', $usuario, $password);

        if ($ping === null || !in_array(trim($ping), ['Ok', 'Ok.'], true)) {
            return $noDisponible;
        }

        $version = self::consultaClickHouse($host, $puerto, $base, $usuario, $password, 'SELECT version()');

        if ($version === null || preg_match('/^\d+(?:\.\d+)+$/', $version) !== 1) {
            return $noDisponible;
        }

        $inicio = hrtime(true);
        $uno = self::consultaClickHouse($host, $puerto, $base, $usuario, $password, 'SELECT 1');
        $milisegundos = (int) round((hrtime(true) - $inicio) / 1000000);

        if ($uno !== '1' || $milisegundos < 0) {
            return $noDisponible;
        }

        $tablas = self::consultaClickHouse(
            $host,
            $puerto,
            $base,
            $usuario,
            $password,
            "SELECT count() FROM system.tables WHERE database = '" . $base . "'"
        );

        if ($tablas === null || preg_match('/^\d+$/', $tablas) !== 1) {
            return $noDisponible;
        }

        $tamano = self::consultaClickHouse(
            $host,
            $puerto,
            $base,
            $usuario,
            $password,
            "SELECT toString(ifNull(sum(bytes_on_disk), 0)) FROM system.parts WHERE active AND database = '" . $base . "'"
        );
        $tamanoBytes = null;

        if (is_string($tamano) && preg_match('/^\d+$/', $tamano) === 1) {
            $tamanoBytes = (int) $tamano;
        }

        return [
            'estado' => 'Conectado',
            'version' => $version,
            'base' => $base,
            'tablas' => (int) $tablas,
            'tamano_bytes' => $tamanoBytes,
            'tiempo_respuesta_ms' => $milisegundos,
            'puerto' => (int) $puerto,
        ];
    }

    private static function consultaClickHouse(
        string $host,
        string $puerto,
        string $base,
        string $usuario,
        string $password,
        string $consulta
    ): ?string {
        $url = 'http://' . $host . ':' . $puerto
            . '/?database=' . rawurlencode($base)
            . '&query=' . rawurlencode($consulta);
        $cuerpo = self::obtenerHttp($url, $usuario, $password);

        if ($cuerpo === null) {
            return null;
        }

        $texto = trim($cuerpo);

        return $texto === '' ? null : $texto;
    }

    private static function obtenerHttp(string $url, ?string $usuario = null, ?string $password = null): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init($url);

        if ($curl === false) {
            return null;
        }

        $opciones = [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 2,
        ];

        if ($usuario !== null && $password !== null) {
            $opciones[CURLOPT_USERPWD] = $usuario . ':' . $password;
            $opciones[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        }

        curl_setopt_array($curl, $opciones);
        $cuerpo = curl_exec($curl);
        $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($cuerpo) || $codigo !== 200) {
            return null;
        }

        return $cuerpo;
    }

    private static function toxiproxy(string $api): array
    {
        $noDisponible = [
            'estado' => 'No disponible',
            'api_disponible' => false,
            'proxy' => self::PROXY_TOXIPROXY,
            'habilitado' => null,
            'latencia_configurada_ms' => null,
        ];
        $proxy = self::solicitud($api . '/proxies/' . self::PROXY_TOXIPROXY);

        if ($proxy === null || ($proxy['name'] ?? null) !== self::PROXY_TOXIPROXY) {
            return $noDisponible;
        }

        $toxicos = self::solicitud($api . '/proxies/' . self::PROXY_TOXIPROXY . '/toxics');

        if ($toxicos === null || !array_is_list($toxicos)) {
            return [
                'estado' => 'No disponible',
                'api_disponible' => true,
                'proxy' => self::PROXY_TOXIPROXY,
                'habilitado' => self::esVerdadero($proxy['enabled'] ?? null),
                'latencia_configurada_ms' => null,
            ];
        }

        $habilitado = self::esVerdadero($proxy['enabled'] ?? null);

        return [
            'estado' => $habilitado ? 'Operativo' : 'No disponible',
            'api_disponible' => true,
            'proxy' => self::PROXY_TOXIPROXY,
            'habilitado' => $habilitado,
            'latencia_configurada_ms' => self::latenciaConfigurada($toxicos),
        ];
    }

    private static function latenciaConfigurada(array $toxicos): ?int
    {
        foreach ($toxicos as $toxic) {
            if (!is_array($toxic)) {
                continue;
            }

            if (($toxic['name'] ?? null) !== self::TOXIC_LATENCIA || ($toxic['type'] ?? null) !== 'latency') {
                continue;
            }

            $latencia = $toxic['attributes']['latency'] ?? null;

            if (is_int($latencia) || (is_string($latencia) && preg_match('/^\d+$/', $latencia) === 1)) {
                return (int) $latencia;
            }

            if (is_float($latencia) && floor($latencia) === $latencia) {
                return (int) $latencia;
            }

            return null;
        }

        return 0;
    }

    private static function solicitud(string $url): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init($url);

        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: toxiproxy-cli',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 2,
        ]);

        $crudo = curl_exec($curl);
        $codigo = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($crudo) || $codigo !== 200) {
            return null;
        }

        $datos = json_decode($crudo, true);

        return is_array($datos) ? $datos : null;
    }

    private static function versionPostgres(mixed $cruda): ?string
    {
        $corta = strtok(trim((string) $cruda), ' ');

        if (!is_string($corta) || preg_match('/^\d+(?:\.\d+)*$/', $corta) !== 1) {
            return null;
        }

        return $corta;
    }

    private static function esVerdadero(mixed $valor): bool
    {
        return $valor === true || $valor === 1 || $valor === '1' || $valor === 't' || $valor === 'true';
    }
}
