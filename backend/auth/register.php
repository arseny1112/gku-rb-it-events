<?php
// CORS заголовки
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../db.php';
require_once '../helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['error' => 'Method not allowed'], 405);
    }

    $body = get_body();
    
    $name  = trim($body['name'] ?? '');
    $email = trim($body['email'] ?? '');
    $pass  = $body['password'] ?? '';

    if (!$name || !$email || !$pass) {
        respond(['error' => 'Заполните все поля'], 422);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['error' => 'Неверный формат email'], 422);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        respond(['error' => 'Email уже занят'], 409);
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $columns = $pdo->query('DESCRIBE users')->fetchAll(PDO::FETCH_COLUMN);
    
    $fields = [
        'name' => $name,
        'email' => $email,
        'password_hash' => $hash
    ];
    
    if (in_array('role', $columns)) {
        $fields['role'] = 'user';
    }
    
    if (in_array('vk_token', $columns)) {
        $vk_token = trim($body['vk_token'] ?? '');
        $fields['vk_token'] = $vk_token ?: null;
    }

    $columns_list = implode(', ', array_keys($fields));
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    
    $stmt = $pdo->prepare("INSERT INTO users ($columns_list) VALUES ($placeholders)");
    $stmt->execute(array_values($fields));
    
    $token = bin2hex(random_bytes(32));
    
    $user_id = $pdo->lastInsertId();
    $update = $pdo->prepare('UPDATE users SET auth_token = ? WHERE id = ?');
    $update->execute([$token, $user_id]);
    
    respond([
        'success' => true,
        'token' => $token,
        'name' => $name,
        'email' => $email,
        'role' => 'user'
    ], 201);

} catch (PDOException $e) {
    error_log('Register PDO Error: ' . $e->getMessage());
    respond([
        'error' => 'Database error',
        'details' => $e->getMessage()
    ], 500);
} catch (Exception $e) {
    error_log('Register Error: ' . $e->getMessage());
    respond([
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ], 500);
}