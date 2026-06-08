<?php
session_start();
require_once __DIR__ . '/../functions.php';

$pdo = getPDO();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "error" => "invalid_method",
        "message" => "Method ไม่ถูกต้อง"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| รับข้อมูลได้ทั้ง 2 แบบ
| 1) application/json
| 2) form-data / x-www-form-urlencoded
|--------------------------------------------------------------------------
*/
$rawInput = file_get_contents("php://input");
$jsonData = json_decode($rawInput, true);

if (is_array($jsonData)) {
    $username = trim($jsonData['username'] ?? '');
    $password = trim($jsonData['password'] ?? '');
} else {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
}

if ($username === '' || $password === '') {
    echo json_encode([
        "success" => false,
        "error" => "missing_fields",
        "message" => "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| ดึงข้อมูลผู้ใช้
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT user_id, username, password, fullname, role_id, is_active
    FROM users
    WHERE username = ?
    LIMIT 1
");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode([
        "success" => false,
        "error" => "user_not_found",
        "message" => "ไม่พบบัญชีผู้ใช้นี้"
    ]);
    exit;
}

if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
    echo json_encode([
        "success" => false,
        "error" => "inactive_user",
        "message" => "บัญชีนี้ถูกปิดการใช้งาน"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| ตรวจรหัสผ่าน
|--------------------------------------------------------------------------
*/
$stored = $user['password'];
$passOK = false;

if (preg_match('/^\$2[aby]\$|^\$argon2/i', $stored)) {
    $passOK = password_verify($password, $stored);
} else {
    $passOK = ($stored === $password);
}

if (!$passOK) {
    echo json_encode([
        "success" => false,
        "error" => "invalid_password",
        "message" => "รหัสผ่านไม่ถูกต้อง"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| ตรวจสิทธิ์
| อนุญาตเฉพาะ admin หรือคนที่มี permission id = 3
|--------------------------------------------------------------------------
*/
$permStmt = $pdo->prepare("SELECT perm_id FROM user_permissions WHERE user_id = ?");
$permStmt->execute([$user['user_id']]);
$permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);

$isAdmin = ((int)$user['role_id'] === 1);
$hasManageUserPermission = in_array(3, array_map('intval', $permissions), true);

if (!$isAdmin && !$hasManageUserPermission) {
    echo json_encode([
        "success" => false,
        "error" => "permission_denied",
        "message" => "บัญชีนี้ไม่มีสิทธิ์จัดการผู้ใช้งาน"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| สำคัญ:
| ไฟล์นี้ใช้แค่ยืนยันตัวตนก่อนกดเพิ่ม/แก้ไข/ลบ
| ไม่ควรเขียนทับ session login หลักทั้งหมด
|--------------------------------------------------------------------------
*/
$_SESSION['verified_user_action'] = true;
$_SESSION['verified_user_action_at'] = time();

echo json_encode([
    "success" => true,
    "message" => "ยืนยันตัวตนสำเร็จ"
]);
exit;