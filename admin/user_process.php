<?php
session_start();

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/google_user_notify.php';
$pdo = getPDO();

/*
|--------------------------------------------------------------------------
| ฟังก์ชัน redirect กลับหน้า User Management
|--------------------------------------------------------------------------
*/
function goUserManagement($params = '')
{
    $url = '../admin/user_Managerment.php';

    if ($params !== '') {
        $url .= '?' . $params;
    }

    header("Location: {$url}");
    exit;
}

/*
|--------------------------------------------------------------------------
| ตรวจว่าเป็น POST เท่านั้น
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    goUserManagement();
}

$action = $_POST['action'] ?? '';

$currentUser = $_SESSION['username'] ?? 'Unknown';
$currentUserId = $_SESSION['user_id'] ?? null;

try {

    /*
    |--------------------------------------------------------------------------
    | เพิ่มผู้ใช้
    |--------------------------------------------------------------------------
    */
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $passwordRaw = $_POST['password'] ?? '';
        $fullname = trim($_POST['fullname'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role_id = (int)($_POST['role_id'] ?? 0);
        $position = trim($_POST['position'] ?? '');
        $department_id = (int)($_POST['department_id'] ?? 0);

        if (
            $username === '' ||
            $passwordRaw === '' ||
            $fullname === '' ||
            $email === '' ||
            $role_id <= 0 ||
            $department_id <= 0
        ) {
            goUserManagement('error=missing');
        }

        // เช็ก username ซ้ำก่อนเพิ่ม
        $checkUsername = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE username = ?
        ");
        $checkUsername->execute([$username]);

        if ((int)$checkUsername->fetchColumn() > 0) {
            goUserManagement('error=duplicate_username');
        }

        // เช็ก email ซ้ำก่อนเพิ่ม
        $checkEmail = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE email = ?
        ");
        $checkEmail->execute([$email]);

        if ((int)$checkEmail->fetchColumn() > 0) {
            goUserManagement('error=duplicate_email');
        }

        $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users 
                (
                    username, 
                    password, 
                    fullname, 
                    email, 
                    role_id, 
                    position, 
                    department_id, 
                    is_active, 
                    created_at
                ) 
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        $stmt->execute([
            $username,
            $password,
            $fullname,
            $email,
            $role_id,
            $position,
            $department_id
        ]);

        if (function_exists('addLog') && $currentUserId) {
            addLog($currentUserId, "ผู้ใช้ {$currentUser} จัดการเพิ่มผู้ใช้: {$username}");
        }

        goUserManagement('success=add');
    }

    /*
    |--------------------------------------------------------------------------
    | แก้ไขผู้ใช้
    |--------------------------------------------------------------------------
    */
    if ($action === 'edit') {
       $user_id = (int)($_POST['user_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$fullname = trim($_POST['fullname'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$auth_provider = trim($_POST['auth_provider'] ?? 'local');
$role_id = (int)($_POST['role_id'] ?? 0);
$position = trim($_POST['position'] ?? '');
$department_id = (int)($_POST['department_id'] ?? 0);
$is_active = (int)($_POST['is_active'] ?? 1);
$permissions = $_POST['permissions'] ?? [];

if ($username === '' && $auth_provider === 'google') {
    $username = $email;
}

        if (
    $user_id <= 0 ||
    $fullname === '' ||
    $email === '' ||
    $role_id <= 0 ||
    $department_id <= 0 ||
    $position === ''
) {
    goUserManagement('error=missing');
}

// ดึงสถานะเดิมก่อนแก้ไข เพื่อเช็กว่าจากรอ Admin กลายเป็นใช้งานได้หรือไม่
$oldStmt = $pdo->prepare("
    SELECT user_id, auth_provider, profile_completed, email, password
    FROM users
    WHERE user_id = ?
    LIMIT 1
");
$oldStmt->execute([$user_id]);
$oldUser = $oldStmt->fetch(PDO::FETCH_ASSOC);

$oldProfileCompleted = isset($oldUser['profile_completed']) ? (int)$oldUser['profile_completed'] : 0;
$oldAuthProvider = $oldUser['auth_provider'] ?? 'local';
$currentPasswordHash = $oldUser['password'] ?? '';

$profile_completed = (
    $fullname !== '' &&
    $email !== '' &&
    $role_id > 0 &&
    $department_id > 0 &&
    $position !== '' &&
    $is_active === 1
) ? 1 : 0;
        // เช็ก username ซ้ำ แต่ยกเว้น user ตัวเอง
        $checkUsername = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE username = ? 
              AND user_id <> ?
        ");
        $checkUsername->execute([$username, $user_id]);

        if ((int)$checkUsername->fetchColumn() > 0) {
            goUserManagement('error=duplicate_username');
        }

        // เช็ก email ซ้ำ แต่ยกเว้น user ตัวเอง
        $checkEmail = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE email = ? 
              AND user_id <> ?
        ");
        $checkEmail->execute([$email, $user_id]);

        if ((int)$checkEmail->fetchColumn() > 0) {
            goUserManagement('error=duplicate_email');
        }

        $pdo->beginTransaction();

        // ถ้ามีการกรอกรหัสผ่านใหม่ ให้ update password ด้วย
        // แต่ถ้าค่าที่ส่งมาเป็น hash เดิมจากฐานข้อมูล ห้าม hash ซ้ำ เพราะจะทำให้รหัสผ่านเปลี่ยนเอง
        $passwordRaw = trim($_POST['password'] ?? '');
        $shouldUpdatePassword = ($passwordRaw !== '' && $passwordRaw !== $currentPasswordHash);

        if ($shouldUpdatePassword) {
            $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE users 
SET 
    username = ?,
    password = ?,
    fullname = ?,
    email = ?,
    role_id = ?,
    position = ?,
    department_id = ?,
    is_active = ?,
    profile_completed = ?
WHERE user_id = ?
            ");

            $stmt->execute([
    $username,
    $password,
    $fullname,
    $email,
    $role_id,
    $position,
    $department_id,
    $is_active,
    $profile_completed,
    $user_id
]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
SET 
    username = ?,
    fullname = ?,
    email = ?,
    role_id = ?,
    position = ?,
    department_id = ?,
    is_active = ?,
    profile_completed = ?
WHERE user_id = ?
            ");

            $stmt->execute([
    $username,
    $fullname,
    $email,
    $role_id,
    $position,
    $department_id,
    $is_active,
    $profile_completed,
    $user_id
]);
        }

        // ลบสิทธิ์เก่าก่อน
        $deletePerm = $pdo->prepare("
            DELETE FROM user_permissions 
            WHERE user_id = ?
        ");
        $deletePerm->execute([$user_id]);

        // เพิ่มสิทธิ์ใหม่จาก checkbox
        if (!empty($permissions) && is_array($permissions)) {
            $insertPerm = $pdo->prepare("
                INSERT INTO user_permissions 
                    (user_id, perm_id) 
                VALUES 
                    (?, ?)
            ");

            foreach ($permissions as $perm_id) {
                $perm_id = (int)$perm_id;

                if ($perm_id > 0) {
                    $insertPerm->execute([$user_id, $perm_id]);
                }
            }
        }

        $pdo->commit();
        // ถ้าเป็น Google user และเดิมยังรอ Admin แต่ตอนนี้ข้อมูลครบแล้ว ให้ส่งอีเมลแจ้งผู้ใช้
if (
    $oldAuthProvider === 'google' &&
    $oldProfileCompleted === 0 &&
    (int)$profile_completed === 1
) {
    try {
        notifyGoogleUserProfileApproved($pdo, $user_id);
    } catch (Throwable $e) {
        error_log('Google User Approved Notify Error: ' . $e->getMessage());
    }
}

        if (function_exists('addLog') && $currentUserId) {
            addLog($currentUserId, "แก้ไขข้อมูลผู้ใช้ {$username} และอัปเดตสิทธิ์การเข้าถึง");
        }

        goUserManagement('success=edit');
    }

    /*
    |--------------------------------------------------------------------------
    | ลบผู้ใช้
    |--------------------------------------------------------------------------
    */
    if ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($user_id <= 0) {
            goUserManagement('error=invalid_user');
        }

        $stmt = $pdo->prepare("
            SELECT username 
            FROM users 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            goUserManagement('error=user_not_found');
        }

        $pdo->beginTransaction();

        // ลบสิทธิ์ก่อน กันติด foreign key
        $deletePerm = $pdo->prepare("
            DELETE FROM user_permissions 
            WHERE user_id = ?
        ");
        $deletePerm->execute([$user_id]);

        // ลบผู้ใช้
        $deleteUser = $pdo->prepare("
            DELETE FROM users 
            WHERE user_id = ?
        ");
        $deleteUser->execute([$user_id]);

        $pdo->commit();

        if (function_exists('addLog') && $currentUserId) {
            addLog($currentUserId, "ผู้ใช้ {$currentUser} จัดการลบผู้ใช้: {$user['username']}");
        }

        goUserManagement('success=delete');
    }

    /*
    |--------------------------------------------------------------------------
    | action ไม่ถูกต้อง
    |--------------------------------------------------------------------------
    */
    goUserManagement('error=invalid_action');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | กัน error duplicate จากฐานข้อมูลอีกชั้น
    |--------------------------------------------------------------------------
    */
    if ($e->getCode() === '23000') {
        $msg = $e->getMessage();

        if (stripos($msg, 'username') !== false) {
            goUserManagement('error=duplicate_username');
        }

        if (stripos($msg, 'email') !== false) {
            goUserManagement('error=duplicate_email');
        }

        goUserManagement('error=duplicate');
    }

    goUserManagement('error=database');
}