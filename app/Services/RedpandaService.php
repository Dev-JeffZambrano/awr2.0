<?php

namespace App\Services;

use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;
use RuntimeException;
use Throwable;

class RedpandaService
{
    public function publicar(string $topic, array $mensaje, ?string $key = null): void
    {
        $topic = trim($topic);

        if ($topic === '') {
            throw new RuntimeException('No se pudo publicar el mensaje.');
        }

        $brokers = trim((string) ($_ENV['REDPANDA_BROKERS'] ?? ''));

        if ($brokers === '' || preg_match('/^[A-Za-z0-9._:-]+(?:,[A-Za-z0-9._:-]+)*$/', $brokers) !== 1) {
            throw new RuntimeException('Redpanda no está disponible');
        }

        try {
            $json = json_encode(
                $mensaje,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo publicar el mensaje.');
        }

        if (!is_string($json)) {
            throw new RuntimeException('No se pudo publicar el mensaje.');
        }

        $productor = null;
        set_error_handler(static function (): bool {
            throw new RuntimeException('Redpanda no está disponible');
        });

        try {
            $config = new ProducerConfig();
            $config->setBootstrapServer($brokers);
            $config->setUpdateBrokers(true);
            $config->setAcks(-1);
            $config->setAutoCreateTopic(false);
            $config->setConnectTimeout(2.0);
            $config->setSendTimeout(2.0);
            $config->setRecvTimeout(2.0);
            $config->setProduceRetry(0);
            $config->setMaxWriteAttempts(1);
            $config->setClientId('awr');

            $productor = new Producer($config);
            $productor->send($topic, $json, $key);
        } catch (Throwable $e) {
            throw new RuntimeException('Redpanda no está disponible');
        } finally {
            restore_error_handler();

            if ($productor instanceof Producer) {
                try {
                    $productor->close();
                } catch (Throwable $e) {
                }
            }
        }
    }
}
