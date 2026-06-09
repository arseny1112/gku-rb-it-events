<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: " . ($_ENV['CORS_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once '../config.php';
require_once '../db.php';
require_once '../helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$user = get_user_from_token($pdo);
if (!$user) respond(['error' => 'Unauthorized'], 401);

$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';
$codeVerifier = $input['code_verifier'] ?? '';

if (!$code) respond(['error' => 'No code provided'], 400);

$params = [
    'grant_type' => 'authorization_code',
    'client_id' => env('VK_CLIENT_ID', $_ENV['VK_CLIENT_ID']),
    'client_secret' => env('VK_CLIENT_SECRET', $_ENV['SECRET_KEY']),
    'code' => $code,
    'redirect_uri' => env('VK_REDIRECT_URI', $_ENV['REDIRECT_URL']),
];
if ($codeVerifier) $params['code_verifier'] = $codeVerifier;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://id.vk.com/oauth2/auth?' . http_build_query($params),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (!isset($data['user_id'])) respond(['error' => 'VK Error', 'details' => $data], 500);

$vkUserId = $data['user_id'];

try {
    $stmt = $pdo->prepare('SELECT id FROM settings WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    
    if ($stmt->fetch()) {
        $pdo->prepare('UPDATE settings SET vk_id = ?, vk_notify = 1 WHERE user_id = ?')
            ->execute([$vkUserId, $user['id']]);
    } else {
        $pdo->prepare('INSERT INTO settings (user_id, vk_id, vk_notify, notify_day_before, notify_hour_before, email_notify) VALUES (?, ?, 1, 1, 1, 0)')
            ->execute([$user['id'], $vkUserId]);
    }
    respond(['success' => true, 'vk_id' => $vkUserId]);
} catch (PDOException $e) {
    respond(['error' => 'DB Error', 'details' => $e->getMessage()], 500);
}