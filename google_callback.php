<?php
// Pro_letter/google_callback.php

session_start();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/google_admin_notify.php';

// โหลด Google OAuth config
$configPath = __DIR__ . '/config/google_oauth.php';

if (!file_exists($configPath)) {
    header('Location: login.html?error=google');
    exit;
}

$googleConfig = require $configPath;

$clientId = $googleConfig['client_id'] ?? '';
$clientSecret = $googleConfig['client_secret'] ?? '';
$redirectUri = $googleConfig['redirect_uri'] ?? '';
$allowedDomains = $googleConfig['allowed_domains'] ?? [
    'email.kmutnb.ac.th',
    'itm.kmutnb.ac.th'
];

if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
    header('Location: login.html?error=google');
    exit;
}

// ถ้า Google ส่ง error กลับมา
if (isset($_GET['error'])) {
    header('Location: login.html?error=google');
    exit;
}

// ตรวจ code และ state
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if ($code === '' || $state === '') {
    header('Location: login.html?error=callback');
    exit;
}

// ตรวจ state เพื่อป้องกัน CSRF
if (
    !isset($_SESSION['google_oauth_state']) ||
    !hash_equals($_SESSION['google_oauth_state'], $state)
) {
    unset($_SESSION['google_oauth_state']);
    header('Location: login.html?error=callback');
    exit;
}

unset($_SESSION['google_oauth_state']);

/**
 * ส่ง HTTP POST แบบ x-www-form-urlencoded
 */
function googleHttpPost(string $url, array $data): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => $curlError
        ];
    }

    $json = json_decode($response, true);

    if (!is_array($json)) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => 'Invalid JSON response'
        ];
    }

    $json['ok'] = ($httpCode >= 200 && $httpCode < 300);

    return $json;
}

/**
 * ส่ง HTTP GET พร้อม Bearer token
 */
function googleHttpGet(string $url, string $accessToken): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => $curlError
        ];
    }

    $json = json_decode($response, true);

    if (!is_array($json)) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => 'Invalid JSON response'
        ];
    }

    $json['ok'] = ($httpCode >= 200 && $httpCode < 300);

    return $json;
}

/**
 * เช็กว่าอีเมลลงท้ายด้วยโดเมนที่อนุญาตหรือไม่
 */
function isAllowedGoogleEmail(string $email, array $allowedDomains): bool
{
    $email = strtolower(trim($email));

    if ($email === '' || strpos($email, '@') === false) {
        return false;
    }

    $parts = explode('@', $email);
    $domain = strtolower(end($parts));

    foreach ($allowedDomains as $allowedDomain) {
        if ($domain === strtolower(trim($allowedDomain))) {
            return true;
        }
    }

    return false;
}

/**
 * Redirect ตาม role
 */
function redirectByRole(int $roleId): void
{
    switch ($roleId) {
        case 1:
            header('Location: admin/home.php');
            break;

        case 2:
            header('Location: officer/home.php');
            break;

        case 3:
            header('Location: user/home.php');
            break;

        default:
            header('Location: login.html?error=role');
            break;
    }

    exit;
}

try {
    // 1) เอา code ไปแลก access token
    $tokenResponse = googleHttpPost('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ]);

    if (
        empty($tokenResponse['ok']) ||
        empty($tokenResponse['access_token'])
    ) {
        header('Location: login.html?error=google');
        exit;
    }

    $accessToken = $tokenResponse['access_token'];

    // 2) ดึงข้อมูลผู้ใช้จาก Google
    $userInfo = googleHttpGet('https://www.googleapis.com/oauth2/v3/userinfo', $accessToken);

    if (empty($userInfo['ok'])) {
        header('Location: login.html?error=google');
        exit;
    }

    $googleSub = trim($userInfo['sub'] ?? '');
    $email = strtolower(trim($userInfo['email'] ?? ''));
    $emailVerified = $userInfo['email_verified'] ?? false;
    $fullname = trim($userInfo['name'] ?? '');

    if ($googleSub === '' || $email === '') {
        header('Location: login.html?error=callback');
        exit;
    }

    // 3) ตรวจว่าอีเมล Google verified แล้ว
    if ($emailVerified !== true && $emailVerified !== 'true' && $emailVerified !== 1) {
        header('Location: login.html?error=unverified');
        exit;
    }

    // 4) ตรวจ domain
    if (!isAllowedGoogleEmail($email, $allowedDomains)) {
        header('Location: login.html?error=domain');
        exit;
    }

    if ($fullname === '') {
        $fullname = $email;
    }

    $pdo = getPDO();

    // 5) หา user จาก google_sub ก่อน ถ้าไม่เจอค่อยหา email
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.fullname,
            u.email,
            u.google_sub,
            u.role_id,
            u.department_id,
            u.position,
            u.is_active,
            u.profile_completed,
            r.role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE u.google_sub = :google_sub OR u.email = :email
        LIMIT 1
    ");
    $stmt->execute([
        'google_sub' => $googleSub,
        'email' => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 6) ถ้าไม่พบ user ให้สร้าง user ใหม่
    if (!$user) {
        $insert = $pdo->prepare("
            INSERT INTO users (
                username,
                password,
                fullname,
                email,
                google_sub,
                auth_provider,
                role_id,
                department_id,
                position,
                is_active,
                profile_completed,
                admin_notified_at
            ) VALUES (
                NULL,
                NULL,
                :fullname,
                :email,
                :google_sub,
                'google',
                3,
                NULL,
                NULL,
                1,
                0,
                NULL
            )
        ");

        $insert->execute([
            'fullname' => $fullname,
            'email' => $email,
            'google_sub' => $googleSub
        ]);

        $newUserId = (int)$pdo->lastInsertId();
        // แจ้ง Admin ว่ามีผู้ใช้ใหม่ Login ด้วย Google และรอเพิ่มข้อมูล
try {
    notifyAdminNewGoogleUser($pdo, $newUserId);
} catch (Throwable $e) {
    error_log('Google New User Notify Error: ' . $e->getMessage());
}

        $stmt = $pdo->prepare("
            SELECT 
                u.user_id,
                u.username,
                u.fullname,
                u.email,
                u.google_sub,
                u.role_id,
                u.department_id,
                u.position,
                u.is_active,
                u.profile_completed,
                r.role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$newUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // 7) ถ้าพบ user เดิม แต่ยังไม่มี google_sub ให้ผูกบัญชี Google เข้า user เดิม
        $update = $pdo->prepare("
            UPDATE users
            SET 
                google_sub = COALESCE(google_sub, :google_sub),
                auth_provider = 'google',
                fullname = CASE 
                    WHEN fullname IS NULL OR fullname = '' THEN :fullname 
                    ELSE fullname 
                END
            WHERE user_id = :user_id
        ");

        $update->execute([
            'google_sub' => $googleSub,
            'fullname' => $fullname,
            'user_id' => $user['user_id']
        ]);

        // ดึงข้อมูลใหม่อีกรอบ
        $stmt = $pdo->prepare("
            SELECT 
                u.user_id,
                u.username,
                u.fullname,
                u.email,
                u.google_sub,
                u.role_id,
                u.department_id,
                u.position,
                u.is_active,
                u.profile_completed,
                r.role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        header('Location: login.html?error=google');
        exit;
    }

    // 8) ตรวจบัญชีถูกปิดหรือไม่
    if ((int)$user['is_active'] !== 1) {
        header('Location: login.html?error=inactive');
        exit;
    }

    // 9) ดึง permissions
    $permStmt = $pdo->prepare("SELECT perm_id FROM user_permissions WHERE user_id = ?");
    $permStmt->execute([$user['user_id']]);
    $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);

    // 10) สร้าง session ให้เหมือนระบบเดิม
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['username'] = $user['email'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role_id'] = (int)$user['role_id'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['position'] = $user['position'] ?? '';
    $_SESSION['role_name'] = $user['role_name'] ?? '';
    $_SESSION['permissions'] = $permissions;
    $_SESSION['auth_provider'] = 'google';
    $_SESSION['profile_completed'] = (int)$user['profile_completed'];

    // 11) บันทึก log
    if (function_exists('addLog')) {
        addLog((int)$user['user_id'], 'เข้าสู่ระบบด้วย Google');
    }

    // 12) ถ้า admin ยังเพิ่มข้อมูลไม่ครบ ให้ไปหน้า pending
    if ((int)$user['profile_completed'] !== 1) {
        header('Location: pending_profile.php');
        exit;
    }

    // 13) ถ้าข้อมูลครบแล้ว ไปหน้าตาม role
    redirectByRole((int)$user['role_id']);

} catch (PDOException $e) {
    header('Location: login.html?error=db');
    exit;
} catch (Throwable $e) {
    header('Location: login.html?error=google');
    exit;
}