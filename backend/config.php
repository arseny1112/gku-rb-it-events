<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$hostname = gethostname() ?: 'unknown';
$httpHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

$isProduction = (
    (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') ||
    stripos($hostname, 'fvh3') !== false ||
    stripos($hostname, 'sweb') !== false ||
    stripos($httpHost, 'event-org.ru') !== false
);

$isLocal = (
    (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'local') ||
    stripos($httpHost, 'localhost') !== false ||
    stripos($httpHost, '127.0.0.1') !== false
);

if ($isLocal) {
    $isProduction = false;
}

if ($isProduction) {
    define('DB_HOST', $_ENV['DB_HOST_PROD'] ?? '127.0.0.1');
    define('DB_PORT', $_ENV['DB_PORT_PROD'] ?? '3306');
    define('DB_NAME', $_ENV['DB_NAME_PROD'] ?? 'arsenijsto');
    define('DB_USER', $_ENV['DB_USER_PROD'] ?? 'arsenijsto');
    define('DB_PASS', $_ENV['DB_PASS_PROD'] ?? '');
} else {
    define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
    define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'event_organizer');
    define('DB_USER', $_ENV['DB_USER'] ?? 'root');
    define('DB_PASS', $_ENV['DB_PASS'] ?? '');
}

// Cloud.ru
define('CLOUD_RU_ACCESS_KEY', $_ENV['CLOUD_RU_ACCESS_KEY'] ?? '');
define('CLOUD_RU_SECRET_KEY', $_ENV['CLOUD_RU_SECRET_KEY'] ?? '');
define('CLOUD_RU_BUCKET', $_ENV['CLOUD_RU_BUCKET'] ?? '');
define('CLOUD_RU_REGION', $_ENV['CLOUD_RU_REGION'] ?? 'ru-central-1');
define('CLOUD_RU_ENDPOINT', $_ENV['CLOUD_RU_ENDPOINT'] ?? 'https://s3.cloud.ru');

// CORS
if ($isProduction) {
    $corsOrigin = $_ENV['CORS_ORIGIN'] ?? 'https://event-org.ru';
} else {
    $corsOrigin = $_ENV['CORS_ORIGIN'] ?? 'http://localhost:8000';
}

header('Access-Control-Allow-Origin: ' . $corsOrigin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');