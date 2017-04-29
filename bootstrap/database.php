<?php

$capsule = new \Illuminate\Database\Capsule\Manager;

$capsule->addConnection([
    'driver' => getenv('DB_DRIVER'),
    'host' => getenv('DB_HOST'),
    'database' => getenv('DB_DATABASE'),
    'username' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
    'charset' => 'utf8',
    'port' => getenv('DB_PORT'),
    'collation' => 'utf8_unicode_ci',
    'prefix' => ''
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

$pdo = new PDO(
    'mysql:host=' . getenv("DB_HOST") . ';dbname=' . getenv('DB_DATABASE'),
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD')
);
