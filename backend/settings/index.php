<?php
require_once '../db.php';
require_once '../helpers.php';

$user = get_user_from_token($pdo);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM settings WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $s = $stmt->fetch();

    if (!$s) {
        $pdo->prepare(
            'INSERT INTO settings (user_id, vk_notify, notify_day_before, notify_hour_before, email_notify, vk_id) 
             VALUES (?, 0, 1, 1, 0, NULL)'
        )->execute([$user['id']]);
        
        $stmt->execute([$user['id']]);
        $s = $stmt->fetch();
    }

    respond([
        'vk_notify'          => (bool)$s['vk_notify'],
        'notify_day_before'  => (bool)$s['notify_day_before'],
        'notify_hour_before' => (bool)$s['notify_hour_before'],
        'email_notify'       => (bool)$s['email_notify'],
        'vk_id'              => $s['vk_id'],
        'email'              => $user['email'] ?? ''
    ]);
}

if ($method === 'PUT') {
    $b = get_body();
    
    try {
        $stmt = $pdo->prepare(
            'UPDATE settings SET
             vk_notify = ?, 
             notify_day_before = ?, 
             notify_hour_before = ?,
             email_notify = ?, 
             vk_id = ?
             WHERE user_id = ?'
        );
        $stmt->execute([
            (int)($b['vk_notify']          ?? 0),
            (int)($b['notify_day_before']  ?? 0),
            (int)($b['notify_hour_before'] ?? 0),
            (int)($b['email_notify']       ?? 0),
            $b['vk_id'] ?? null,
            $user['id'],
        ]);
        
        if (!empty($b['email']) && $b['email'] !== $user['email']) {
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $checkStmt->execute([$b['email'], $user['id']]);
            
            if ($checkStmt->fetch()) {
                respond(['error' => 'Этот email уже используется другим пользователем'], 409);
            }
            
            $updateEmail = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
            $updateEmail->execute([$b['email'], $user['id']]);
        }
        
        respond(['message' => 'Настройки сохранены']);
        
    } catch (PDOException $e) {
        error_log('Settings PUT Error: ' . $e->getMessage());
        respond(['error' => 'Ошибка базы данных', 'details' => $e->getMessage()], 500);
    }
}

respond(['error' => 'Method not allowed'], 405);