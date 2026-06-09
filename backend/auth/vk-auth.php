<?php
// backend/uploads/auth/vk-auth.php
session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$vkClientId = $_ENV['vkClientId'];
$redirectUri = $_ENV['redirectUri'];

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