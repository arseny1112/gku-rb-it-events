<?php
session_start();



header("Access-Control-Allow-Origin: " . ($_ENV['CORS_ORIGIN'] ?? '*'));
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(204); 
    exit; 
}
mb_internal_encoding('UTF-8');

require_once '../db.php';
require_once '../helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

if (!function_exists('respond')) {
    function respond($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$input        = json_decode(file_get_contents('php://input'), true);
$code         = $input['code']          ?? '';
$codeVerifier = $input['code_verifier'] ?? '';

$deviceId = $_SESSION['vk_device_id'] ?? '';

if (!$code || !$codeVerifier || !$deviceId) {
    respond(['error' => 'Missing params'], 400);
}

$VKCLIENTID  = $_ENV['VK_CLIENT_ID'];
$REDIRECTURL = $_ENV['REDIRECT_URL'];

$tokenParams = [
    'grant_type'    => 'authorization_code',
    'client_id'     => $VKCLIENTID,
    'code'          => $code,
    'redirect_uri'  => $REDIRECTURL,
    'code_verifier' => $codeVerifier,
    'device_id'     => $deviceId,
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://id.vk.com/oauth2/auth',
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($tokenParams),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'],
]);
$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);

if (!isset($tokenData['access_token'])) {
    respond(['error' => 'VK token error', 'details' => $tokenData], 400);
}

$vkAccessToken = $tokenData['access_token'];
$vkUserId      = $tokenData['user_id'];
$email         = $tokenData['email'] ?? null;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://api.vk.com/method/users.get?user_ids=' . $vkUserId . '&fields=first_name,last_name,photo_100&access_token=' . $vkAccessToken . '&v=5.131&lang=ru',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Accept-Charset: utf-8'],
]);
$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userResponse, true);
$vkUser   = $userData['response'][0] ?? null;

if ($vkUser) {
    $firstName = $vkUser['first_name'] ?? '';
    $lastName  = $vkUser['last_name'] ?? '';
    
    $firstName = mb_convert_encoding($firstName, 'UTF-8', 'auto');
    $lastName  = mb_convert_encoding($lastName, 'UTF-8', 'auto');
    
    $name = trim($firstName . ' ' . $lastName);
} else {
    $name = 'Пользователь VK';
}

$photo = $vkUser['photo_100'] ?? null;
$user = null;
$userId = null;

$stmt = $pdo->prepare('SELECT * FROM users WHERE vk_id = ?');
$stmt->execute([$vkUserId]);
$user = $stmt->fetch();

if (!$user && $email) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && !$user['vk_id']) {
        $stmt = $pdo->prepare('UPDATE users SET vk_id = ?, vk_token = ? WHERE id = ?');
        $stmt->execute([$vkUserId, $vkAccessToken, $user['id']]);
    }
}

if (!$user) {
    $tempEmail = $email ?? "vk{$vkUserId}@temp.local";
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, vk_id, vk_token) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $tempEmail, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'user', $vkUserId, $vkAccessToken]);
    $userId = $pdo->lastInsertId();
} else {
    $userId = $user['id'];
}

$ourToken = bin2hex(random_bytes(32));
$pdo->prepare('UPDATE users SET vk_token = ?, last_login = NOW(), avatar = ? WHERE id = ?')
    ->execute([$ourToken, $photo, $userId]);

unset($_SESSION['vk_device_id'], $_SESSION['vk_code']);

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

respond([
    'token' => $ourToken,
    'name'  => $name,  
    'email' => $email ?? '',
    'role'  => $currentUser['role'] ?? 'user',
]);