<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

define('DB_HOST', $_ENV['DB_HOST_PROD'] ?? $_ENV['DB_HOST']);
define('DB_PORT', $_ENV['DB_PORT_PROD'] ?? $_ENV['DB_PORT']);
define('DB_NAME', $_ENV['DB_NAME_PROD'] ?? $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER_PROD'] ?? $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS_PROD'] ?? '');

// Cloud.ru - берем из .env, не добавляем бакет в endpoint
define('CLOUD_RU_ACCESS_KEY', $_ENV['CLOUD_RU_ACCESS_KEY'] ?? '');
define('CLOUD_RU_SECRET_KEY', $_ENV['CLOUD_RU_SECRET_KEY'] ?? '');
define('CLOUD_RU_BUCKET', $_ENV['CLOUD_RU_BUCKET'] ?? '');
define('CLOUD_RU_REGION', $_ENV['CLOUD_RU_REGION']);
define('CLOUD_RU_ENDPOINT', $_ENV['CLOUD_RU_ENDPOINT']);

// CORS
$corsOrigin = $_ENV['CORS_ORIGIN'] ?? 'http://localhost:5173';
header('Access-Control-Allow-Origin: ' . $corsOrigin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');