<?php
// backend/uploads/auth/vk-auth.php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$vkClientId = $_ENV['VK_CLIENT_ID'];
$redirectUri = $_ENV['REDIRECT_URL'];

$state = bin2hex(random_bytes(10));
$_SESSION['vk_state'] = $state;

$params = [
    'client_id'     => $vkClientId,
    'redirect_uri'  => $redirectUri,
    'response_type' => 'code',
    'scope'         => 'email',
    'state'         => $state,
    'v'             => '5.199',  
];

$authUrl = 'https://oauth.vk.com/authorize?' . http_build_query($params);

header('Location: ' . $authUrl);
exit;