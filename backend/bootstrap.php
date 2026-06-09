<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
foreach ($_ENV as $key => $value) {
    putenv("$key=$value");
}