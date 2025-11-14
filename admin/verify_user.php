<?php
session_start();
require_once __DIR__ . '/../functions.php'; // เรียกใช้ฟังก์ชันกลาง เช่น getPDO()
$pdo = getPDO();

header('Content-Type: application/json; charset=utf-8');

// 📌 รับค่าจากฟอร์ม Login
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// 🔒 ตรวจสอบว่ากรอกครบไหม
if ($username === '' || $password === '') {
    echo json_encode(["success" => false, "error" => "missing_fields"]);
    exit;
}

// ✅ ดึงข้อมูลผู้ใช้จากตาราง users
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ❌ กรณีไม่พบบัญชี
if (!$user) {
    echo json_encode(["success" => false, "error" => "user_not_found"]);
    exit;
}

// ✅ ตรวจสอบรหัสผ่าน
$stored = $user['password'];
$passOK = false;

// ถ้าเป็น bcrypt / argon2 ให้ตรวจสอบด้วย password_verify()
if (preg_match('/^\$2[aby]\$|^\$argon2/i', $stored)) {
    $passOK = password_verify($password, $stored);
} else {
    // ถ้าเป็นรหัสธรรมดา (plain text)
    $passOK = ($stored === $password);
}

// ✅ หากรหัสผ่านถูกต้อง
if ($passOK) {
    $_SESSION['verified'] = true;
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['fullname'] = $user['fullname'];

    // ✅ เคลียร์ค่า permissions เก่าออกก่อน (กัน session เก่าค้าง)
    unset($_SESSION['permissions']);

    // ✅ โหลดสิทธิ์ใหม่ล่าสุดจากฐานข้อมูล
    $permStmt = $pdo->prepare("SELECT perm_id FROM user_permissions WHERE user_id = ?");
    $permStmt->execute([$user['user_id']]);
    $_SESSION['permissions'] = $permStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(["success" => true]);
    exit;
}

// ❌ ถ้ารหัสผ่านไม่ถูกต้อง
echo json_encode(["success" => false, "error" => "invalid_password"]);
exit;