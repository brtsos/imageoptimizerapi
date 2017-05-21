<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo __DIR__ . '/vendor/autoload.php';

include __DIR__ . '/vendor/autoload.php';

echo 'mysql:host=' . getenv("DB_HOST") . ';dbname=' . getenv('DB_DATABASE');

$pdo = new \PDO(
    'mysql:host=' . getenv("DB_HOST") . ';dbname=' . getenv('DB_DATABASE'),
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD')
);

/*
$stmt = $pdo->prepare("
          insert into users (name, password, email, activ, activ_code) 
          values (:name, :password, :email, :activ, :activ_code)"
);

$name = 'testname';
$password = password_hash('testPassword',PASSWORD_DEFAULT);
$email = 'test@testunit.com';
$activCode = '12345';

$stmt->bindParam(':name', $name);
$stmt->bindParam(':password', $password);
$stmt->bindParam(':email', $email);
$stmt->bindParam(':activ', $active);
$stmt->bindParam(':activ_code', $activCode);

$stmt->execute();
*/
