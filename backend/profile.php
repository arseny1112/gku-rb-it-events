<?php
// backend/profile.php

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
foreach ($_ENV as $k => $v) putenv("$k=$v");

// 🔥 CORS заголовки
header("Access-Control-Allow-Origin: " . ($_ENV['CORS_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
require_once 'helpers.php';

$user = get_user_from_token($pdo);
if (!$user) {
    respond(['error' => 'Unauthorized'], 401);
}

if (!empty($user['avatar']) && strpos($user['avatar'], 'http') === false) {
    try {
        $accessKey = $_ENV['CLOUD_RU_ACCESS_KEY'] ?? null;
        $secretKey = $_ENV['CLOUD_RU_SECRET_KEY'] ?? null;
        
        if ($accessKey && $secretKey) {
            $s3Client = new Aws\S3\S3Client([
                'version' => 'latest',
                'region'  => $_ENV['CLOUD_RU_REGION'] ?? 'ru-1',
                'endpoint' => $_ENV['CLOUD_RU_ENDPOINT'],
                'credentials' => [
                    'key'    => $accessKey,
                    'secret' => $secretKey,
                ],
                'use_path_style_endpoint' => true,
            ]);

            $bucket = $_ENV['CLOUD_RU_BUCKET'];

            $cmd = $s3Client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key'    => $user['avatar'],
            ]);
            
            $request = $s3Client->createPresignedRequest($cmd, '+1 hour');
            $user['avatar'] = (string) $request->getUri();
        } else {
            error_log("S3 Keys missing in profile.php");
        }
        
    } catch (Exception $e) {
        error_log("Profile S3 Error: " . $e->getMessage());
    }
}

respond([
    'id'         => $user['id'],
    'name'       => $user['name'],
    'avatar'     => $user['avatar'], 
    'email'      => $user['email'],
    'role'       => $user['role'] ?? 'user',
    'department' => $user['department'] ?? '',
    'last_login' => $user['last_login'] ?? date('Y-m-d H:i:s'),
]);
?>