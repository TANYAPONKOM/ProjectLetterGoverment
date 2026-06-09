<?php
// Pro_letter/google_login.php

session_start();

// โหลด config Google OAuth
$configPath = __DIR__ . '/config/google_oauth.php';

if (!file_exists($configPath)) {
    die('Google OAuth config file not found.');
}

$googleConfig = require $configPath;

// ตรวจค่าที่จำเป็น
$clientId = $googleConfig['client_id'] ?? '';
$redirectUri = $googleConfig['redirect_uri'] ?? '';
$scopes = $googleConfig['scopes'] ?? ['openid', 'email', 'profile'];

if (empty($clientId) || empty($redirectUri)) {
    die('Google OAuth configuration is incomplete.');
}

// สร้าง state token เพื่อป้องกัน CSRF
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

// สร้าง URL ไปหน้า Google Login
$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => implode(' ', $scopes),
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account'
];

$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

// ส่งผู้ใช้ไปหน้า Login Google
header('Location: ' . $googleAuthUrl);
exit;