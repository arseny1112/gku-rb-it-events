<?php
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'error' => 'Fatal Error: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

try {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/helpers.php';
    require_once __DIR__ . '/config.php';

    $user = get_user_from_token($pdo);
    if (!$user) {
        respond(['error' => 'Unauthorized'], 401);
    }

    $userId = $user['id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['error' => 'Method not allowed'], 405);
    }

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        respond(['error' => 'Файл не загружен'], 422);
    }

    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

    if (!in_array($file['type'], $allowedTypes)) {
        respond(['error' => 'Только JPEG, PNG или WEBP'], 422);
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        respond(['error' => 'Файл не более 5MB'], 422);
    }

    $accessKey = defined('CLOUD_RU_ACCESS_KEY') ? CLOUD_RU_ACCESS_KEY : ($_ENV['CLOUD_RU_ACCESS_KEY'] ?? null);
    $secretKey = defined('CLOUD_RU_SECRET_KEY') ? CLOUD_RU_SECRET_KEY : ($_ENV['CLOUD_RU_SECRET_KEY'] ?? null);
    
    $useCloud = !empty($accessKey) && !empty($secretKey);

    if ($useCloud) {
        try {
            $region = defined('CLOUD_RU_REGION') ? CLOUD_RU_REGION : ($_ENV['CLOUD_RU_REGION'] ?? 'ru-1');
            $endpoint = defined('CLOUD_RU_ENDPOINT') ? CLOUD_RU_ENDPOINT : ($_ENV['CLOUD_RU_ENDPOINT']);
            $bucket = defined('CLOUD_RU_BUCKET') ? CLOUD_RU_BUCKET : ($_ENV['CLOUD_RU_BUCKET']);

            $s3Client = new S3Client([
                'version' => 'latest',
                'region'  => $region,
                'endpoint' => $endpoint,
                'credentials' => [
                    'key'    => $accessKey,
                    'secret' => $secretKey,
                ],
                'use_path_style_endpoint' => true,
            ]);
            
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $key = 'avatars/user_' . $userId . '/avatar_' . $userId . '_' . time() . '.' . $extension;
            
            $s3Client->putObject([
                'Bucket'      => $bucket,
                'Key'         => $key,
                'Body'        => fopen($file['tmp_name'], 'r'),
                'ACL'         => 'public-read',
                'ContentType' => $file['type']
            ]);
            
            $cmd = $s3Client->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);
            
            $request = $s3Client->createPresignedRequest($cmd, '+7 days');
            $fileUrl = (string) $request->getUri();
            
            $stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $oldAvatar = $stmt->fetchColumn();
            
            if ($oldAvatar && strpos($oldAvatar, 's3.cloud.ru') !== false) {
                try {
                    $parsed = parse_url($oldAvatar);
                    $path = ltrim($parsed['path'] ?? '', '/');
                    $oldKey = preg_replace('/^' . preg_quote($bucket, '/') . '\//', '', $path);
                    
                    if (!empty($oldKey)) {
                        $s3Client->deleteObject([
                            'Bucket' => $bucket,
                            'Key'    => $oldKey
                        ]);
                    }
                } catch (Exception $e) {
                    error_log("Ошибка удаления старого аватара: " . $e->getMessage());
                }
            }
            
            $stmt = $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
            $stmt->execute([$fileUrl, $userId]);
            
            respond([
                'success'    => true,
                'avatar_url' => $fileUrl,
                'storage'    => 'cloud'
            ]);
            
        } catch (AwsException $e) {
            error_log("S3 Upload Error: " . $e->getMessage());
            respond(['error' => 'Ошибка загрузки в облако: ' . $e->getMessage()], 500);
        }
    } else {
        $uploadDir = __DIR__ . '/uploads/avatars/user_' . $userId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            respond(['error' => 'Ошибка сохранения файла'], 500);
        }
        
        chmod($filePath, 0644);
        
        $avatarPath = '/uploads/avatars/user_' . $userId . '/' . $filename;
        $fullUrl = 'http://localhost/event_organizer/backend' . $avatarPath;
        
        $stmt = $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
        $stmt->execute([$fullUrl, $userId]);
        
        respond([
            'success'    => true,
            'avatar_url' => $fullUrl,
            'storage'    => 'local'
        ]);
    }

} catch (Throwable $e) {
    error_log("Upload Fatal: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>