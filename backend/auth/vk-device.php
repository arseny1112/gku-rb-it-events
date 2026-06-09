<?php
header("Access-Control-Allow-Origin: " . ($_ENV['CORS_ORIGIN'] ?? '*'));
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://id.vk.com/oauth2/auth',
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type' => 'initiate',
        'client_id'  => $_ENV['VK_CLIENT_ID'],
        'scope'      => 'email',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

error_log("VK device_id response ($httpCode): $response");
echo $response;