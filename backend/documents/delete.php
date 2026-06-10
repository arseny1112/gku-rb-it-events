<?php
// backend/documents/delete.php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: " . ($_ENV['CORS_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use Aws\S3\S3Client;
use Aws\Exception\AwsException;


$accessKey =  $_ENV['CLOUD_RU_ACCESS_KEY'];
$secretKey = $_ENV['CLOUD_RU_SECRET_KEY'];
$region = $_ENV['CLOUD_RU_REGION'];
$endpoint = $_ENV['CLOUD_RU_ENDPOINT'];
$bucket = $_ENV['CLOUD_RU_BUCKET'];

// Проверяем авторизацию
$user = get_user_from_token($pdo);
if (!$user) {
    respond(['error' => 'Unauthorized'], 401);
}

$userId = $user['id'];

// Получаем ID документа
$docId = $_GET['id'] ?? null;

if (!$docId) {
    respond(['error' => 'Document ID required'], 400);
}

try {
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ? AND user_id = ?');
    $stmt->execute([$docId, $userId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc) {
        respond(['error' => 'Document not found or access denied'], 404);
    }
    
    $deletedFromCloud = false;
    $deletedFromLocal = false;
    
    if (!empty($doc['file_url']) && strpos($doc['file_url'], 's3.cloud.ru') !== false) {
        try {
            $client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'endpoint' => $endpoint,
                'credentials' => [
                    'key' => $accessKey,
                    'secret' => $secretKey,
                ],
                'use_path_style_endpoint' => true,
            ]);
            
            $key = "documents/{$userId}/{$doc['filename']}";
            
            $client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $key,
            ]);
            
            $deletedFromCloud = true;
            
        } catch (AwsException $e) {
            error_log("Cloud delete failed: " . $e->getMessage());
        }
    }
    
    $localPath = __DIR__ . '/../uploads/documents/user_' . $userId . '/' . $doc['filename'];
    if (file_exists($localPath)) {
        unlink($localPath);
        $deletedFromLocal = true;
    }
    
    $oldPath = __DIR__ . '/../uploads/documents/' . $doc['filename'];
    if (file_exists($oldPath)) {
        unlink($oldPath);
        $deletedFromLocal = true;
    }
    
    $stmt = $pdo->prepare('DELETE FROM documents WHERE id = ? AND user_id = ?');
    $stmt->execute([$docId, $userId]);
    
    respond([
        'success' => true,
        'message' => 'Document deleted successfully',
        'deleted_from_cloud' => $deletedFromCloud,
        'deleted_from_local' => $deletedFromLocal
    ]);
    
} catch (PDOException $e) {
    respond(['error' => 'Database error: ' . $e->getMessage()], 500);
}