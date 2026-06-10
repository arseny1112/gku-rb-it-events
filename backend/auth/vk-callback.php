<?php
session_start();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . "://" . $host;

if ($host === 'localhost' || $host === '127.0.0.1') {
    $baseUrl = "http://localhost:5173";
}

if (isset($_GET['error'])) {
    header('Location: ' . $baseUrl . '/auth?error=vk_cancelled');
    exit;
}

$code     = $_GET['code']      ?? '';
$state    = $_GET['state']     ?? '';
$deviceId = $_GET['device_id'] ?? '';

if (!$code) die('No code. Params: ' . print_r($_GET, true));

error_log('VK Callback - device_id: ' . $deviceId);
error_log('VK Callback - base URL: ' . $baseUrl);

$_SESSION['vk_device_id'] = $deviceId;
$_SESSION['vk_code']      = $code;

$url = $baseUrl . "/auth#vk_code=" . urlencode($code)
     . "&state=" . urlencode($state);

header('Location: ' . $url);
exit;