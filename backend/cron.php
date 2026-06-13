<?php
// backend/cron.php
date_default_timezone_set('Asia/Yekaterinburg');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email.php'; 
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pdo->exec("SET time_zone = '+05:00'");

$lockFile = __DIR__ . '/cron.lock';

if (file_exists($lockFile)) {
    $pid = (int)file_get_contents($lockFile);
    if ($pid > 0 && posix_kill($pid, 0)) {
        $psOutput = shell_exec("ps -p $pid -o command= 2>/dev/null");
        if ($psOutput && strpos($psOutput, 'cron.php') !== false) {
            echo "⚠️ Крон уже запущен (PID: $pid). Выход.\n";
            exit;
        }
    }
    unlink($lockFile); 
}

file_put_contents($lockFile, getmypid());

register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

$vkToken   = $_ENV['VK_TOKEN'];
$vkGroupId = $_ENV['VK_GROUP_ID'];
error_log("VK_TOKEN loaded: " . ($_ENV['VK_TOKEN'] ? 'yes' : 'no'));
error_log("VK_GROUP_ID loaded: " . ($_ENV['VK_GROUP_ID'] ? 'yes' : 'no'));
$deleteDayStmt = $pdo->prepare("
    DELETE n FROM notifications n
    JOIN events e ON n.event_id = e.id
    WHERE n.type = 'day_before' 
      AND n.sent = 0
      AND TIMESTAMPDIFF(HOUR, NOW(), e.start_datetime) < 12
");
$deleteDayStmt->execute();
echo "🗑️ Удалены неактуальные day_before уведомления\n";

$deleteHourStmt = $pdo->prepare("
    DELETE n FROM notifications n
    JOIN events e ON n.event_id = e.id
    WHERE n.type = 'hour_before' 
      AND n.sent = 0
      AND e.start_datetime > NOW()
      AND TIMESTAMPDIFF(HOUR, NOW(), e.start_datetime) > 2
");
$deleteHourStmt->execute();
echo "🗑️ Удалены неактуальные hour_before уведомления (осталось >2 часов)\n";

$stmt = $pdo->prepare(
    'SELECT n.*, 
            e.title, e.start_datetime, e.location,
            u.name as user_name, u.vk_id, u.email,
            s.vk_notify, s.email_notify
     FROM notifications n
     JOIN events e  ON n.event_id = e.id
     JOIN users u   ON n.user_id  = u.id
     LEFT JOIN settings s ON s.user_id = u.id
     WHERE n.sent = 0
       AND n.notify_at <= NOW()'
);
$stmt->execute();
$notifications = $stmt->fetchAll();

echo "📋 Найдено уведомлений для отправки: " . count($notifications) . "\n";

foreach ($notifications as $notif) {
    $type    = $notif['type'] === 'day_before' ? 'за 1 день' : 'за 1 час';
    $date    = date('d.m.Y H:i', strtotime($notif['start_datetime']));
    $location = $notif['location'] ? "\n📍 {$notif['location']}" : '';

    $message = "🔔 Напоминание {$type} до мероприятия!\n\n"
             . "📋 {$notif['title']}\n"
             . "🕐 {$date}"
             . $location;
    
    $emailSubject = "⏰ Напоминание: {$notif['title']}";

    if ($notif['email_notify'] && !empty($notif['email'])) {
        if (sendEmail($notif['email'], $emailSubject, $message)) {
            echo "✅ Email отправлен пользователю {$notif['user_name']} ({$notif['email']})\n";
        } else {
            echo "❌ Ошибка Email для {$notif['email']}\n";
        }
    }

    if ($notif['vk_notify'] && $notif['vk_id']) {
        $params = [
            'user_id'   => $notif['vk_id'],
            'message'   => $message,
            'random_id' => rand(0, 1000000000),
            'access_token' => $vkToken,
            'v'         => '5.199',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.vk.com/method/messages.send',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        
        if (isset($result['response'])) {
            echo "✅ VK отправлено пользователю {$notif['user_name']} ({$notif['vk_id']})\n";
        } else {
            echo "❌ Ошибка VK для {$notif['user_name']}: " . json_encode($result['error'] ?? $result) . "\n";
        }
    }

    $pdo->prepare('UPDATE notifications SET sent = 1 WHERE id = ?')
        ->execute([$notif['id']]);
}

echo "✅ Готово. Обработано: " . count($notifications) . " уведомлений.\n";