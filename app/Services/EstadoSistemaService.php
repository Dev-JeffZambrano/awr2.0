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

    public static function obtenerEstadoActual(): array
    {
        return [
            'aplicacion' => self::aplicacion(),
            'postgresql' => self::postgresql(),
            'toxiproxy' => self::toxiproxy(self::API_TOXIPROXY),
            'enlace' => self::enlace(),
            'redpanda' => [
                'estado' => 'No integrado',
            ],
            'clickhouse' => [
                'estado' => 'No integrado',
            ],
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
