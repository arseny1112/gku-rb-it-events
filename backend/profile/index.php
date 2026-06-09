<?php
require_once '../db.php';
require_once '../helpers.php';

header("Access-Control-Allow-Origin: " . ($_ENV['CORS_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Origin: https://event-org.ru'); 
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

$user = get_user_from_token($pdo);
$method = $_SERVER['REQUEST_METHOD'];

if (!$user) {
    respond(['error' => 'Unauthorized'], 401);
}

if ($method === 'GET') {
    $now = date('Y-m-d H:i:s');
    
    $total  = $pdo->prepare('SELECT COUNT(*) FROM events WHERE user_id=?');
    $total->execute([$user['id']]);
    
    $future = $pdo->prepare('SELECT COUNT(*) FROM events WHERE user_id=? AND start_datetime > ?');
    $future->execute([$user['id'], $now]);
    
    $past   = $pdo->prepare('SELECT COUNT(*) FROM events WHERE user_id=? AND start_datetime <= ?');
    $past->execute([$user['id'], $now]);

    respond([
        'name'       => $user['name'],
        'email'      => $user['email'],
        'avatar'     => $user['avatar'],
        'department' => $user['department'],
        'last_login' => $user['last_login'],
        'role'       => $user['role'],
        'stats'      => [
            'total'  => (int)$total->fetchColumn(),
            'future' => (int)$future->fetchColumn(),
            'past'   => (int)$past->fetchColumn(),
        ]
    ]);
}

if ($method === 'PUT') {
    $b = get_body();
    
    $name       = trim($b['name'] ?? '');
    $email      = trim($b['email'] ?? '');
    $department = trim($b['department'] ?? '');
    
    if (!$name || !$email) {
        respond(['error' => 'Заполните ФИО и Email'], 422);
    }
    
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $user['id']]);
    if ($stmt->fetch()) { 
        respond(['error' => 'Email уже занят'], 409);
    }
    
    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, department = ? WHERE id = ?');
    $stmt->execute([$name, $email, $department, $user['id']]);
    
    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
    
    respond(['message' => 'Профиль обновлен']);
}

respond(['error' => 'Method not allowed'], 405);
?>