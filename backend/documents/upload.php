<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$user = get_user_from_token($pdo);
if (!$user) {
    respond(['error' => 'Unauthorized'], 401);
}

$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    respond(['error' => 'File upload error'], 422);
}

$file = $_FILES['file'];
$eventId = $_POST['event_id'] ?? null;

$accessKey =  $_ENV['CLOUD_RU_ACCESS_KEY'];
$secretKey = $_ENV['CLOUD_RU_SECRET_KEY'];
$region = $_ENV['CLOUD_RU_REGION'];
$endpoint = $_ENV['CLOUD_RU_ENDPOINT'];
$bucket = $_ENV['CLOUD_RU_BUCKET'];

if (empty($accessKey) || empty($secretKey)) {
    $uploadDir = __DIR__ . '/../uploads/documents/user_' . $userId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'doc_' . $userId . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        respond(['error' => 'Failed to save file'], 500);
    }
    
    chmod($filePath, 0644);
    $fileUrl = (string) $request->getUri();
    
    try {
        $stmt = $pdo->prepare('INSERT INTO documents (event_id, user_id, filename, original_name, size, file_url, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$eventId, $userId, $filename, $file['name'], $file['size'], $fileUrl]);
        
        respond([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'filename' => $filename,
            'original_name' => $file['name'],
            'url' => $fileUrl,
            'storage' => 'local'
        ]);
    } catch (PDOException $e) {
        if (file_exists($filePath)) unlink($filePath);
        respond(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
    exit;
}

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
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'doc_' . $userId . '_' . time() . '_' . uniqid() . '.' . $extension;
    $key = "documents/{$userId}/{$filename}";
    
    $result = $client->putObject([
        'Bucket' => $bucket,
        'Key' => $key,
        'Body' => fopen($file['tmp_name'], 'r'),
        'ContentType' => $file['type'],
        'ACL' => 'public-read',
    ]);
    
    $fileUrl = $result['ObjectURL'];
    
    $stmt = $pdo->prepare('INSERT INTO documents (event_id, user_id, filename, original_name, size, file_url, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$eventId, $userId, $filename, $file['name'], $file['size'], $fileUrl]);
    
    respond([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'filename' => $filename,
        'original_name' => $file['name'],
        'url' => $fileUrl,
        'storage' => 'cloud'
    ]);
    
} catch (AwsException $e) {
    respond(['error' => 'Cloud upload failed: ' . $e->getMessage()], 500);
} catch (PDOException $e) {
    respond(['error' => 'Database error: ' . $e->getMessage()], 500);
}