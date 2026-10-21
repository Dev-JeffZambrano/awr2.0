<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/app/config/database.php';

$capsule = new Capsule();

$conexionBase = [
    'driver' => 'pgsql',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
];

$capsule->addConnection($conexionBase + [
    'host' => $config['default']['host'],
    'port' => $config['default']['port'],
    'database' => $config['default']['database'],
    'username' => $config['default']['username'],
    'password' => $config['default']['password'],
], 'default');

$capsule->addConnection($conexionBase + [
    'host' => $config['facturacion']['host'],
    'port' => $config['facturacion']['port'],
    'database' => $config['facturacion']['database'],
    'username' => $config['facturacion']['username'],
    'password' => $config['facturacion']['password'],
], 'facturacion');

$capsule->setAsGlobal();
$capsule->bootEloquent();
