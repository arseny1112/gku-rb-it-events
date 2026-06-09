<?php
// backend/events/vk-webhook.php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

$log_file = __DIR__ . '/vk_debug.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - REQUEST START\n", FILE_APPEND);
file_put_contents($log_file, "Input: " . json_encode($input) . "\n", FILE_APPEND);

if (!$input) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$type = $input['type'] ?? '';
$secret_key = $_ENV['SECRET_KEY'];

if ($type === 'confirmation') {
    header('Content-Type: text/plain');
    echo $_ENV['STRING_RETURN'];
    exit;
}

if (!isset($input['secret']) || $input['secret'] !== $secret_key) {
    file_put_contents($log_file, "Wrong secret\n", FILE_APPEND);
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

require_once __DIR__ . '/../db.php';

$object = $input['object'] ?? [];
$user_id = $object['from_id'] ?? $object['user_id'] ?? 0;
$text = trim(strtolower($object['text'] ?? $object['body'] ?? ''));

file_put_contents($log_file, "Message from $user_id: $text\n", FILE_APPEND);

if ($type === 'message_new' || $type === 'message_reply') {
    if (strpos($text, 'привязать') !== false || strpos($text, 'уведом') !== false) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE vk_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                $stmt = $pdo->prepare("UPDATE settings SET vk_notify = 1 WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                file_put_contents($log_file, "✅ User {$user['id']} bound VK notifications\n", FILE_APPEND);
                sendVkMessage($user_id, "✅ Отлично! Уведомления привязаны. Теперь вы будете получать напоминания о мероприятиях здесь.");
            } else {
                file_put_contents($log_file, "❌ User with vk_id $user_id not found\n", FILE_APPEND);
                sendVkMessage($user_id, "❌ Ваш аккаунт VK не привязан к сайту. Зайдите в настройки сайта и привяжите аккаунт.");
            }
        } catch (PDOException $e) {
            file_put_contents($log_file, "❌ DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
    
    echo 'ok';
    exit;
}

echo 'ok';

function sendVkMessage(int $userId, string $text): void {
    $vkToken = $_ENV['VK_TOKEN'];
    $vkGroupId = $_ENV['VK_GROUP_ID'];
    
    $params = [
        'user_id' => $userId,
        'message' => $text,
        'random_id' => rand(0, 1000000000),
        'access_token' => $vkToken,
        'v' => '5.199'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.vk.com/method/messages.send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if (isset($result['error'])) {
        file_put_contents(__DIR__ . '/vk_debug.log', "Send error: " . json_encode($result['error']) . "\n", FILE_APPEND);
    }
}