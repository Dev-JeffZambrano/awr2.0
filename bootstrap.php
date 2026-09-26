<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/app/config/database.php';

$capsule = new Capsule();

$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => $config['host'],
    'port' => $config['port'],
    'database' => $config['database'],
    'username' => $config['username'],
    'password' => $config['password'],
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();