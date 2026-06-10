<?php
session_start();

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

// อนุญาตให้ประมวลผลได้เฉพาะ admin หรือผู้ที่มีสิทธิ์กำหนดสิทธิ์ (perm_id = 3)
$sessionPerms = array_map('intval', $_SESSION['permissions'] ?? []);
$hasManageUserPermission = ((int)($_SESSION['role_id'] ?? 0) === 1) || in_array(3, $sessionPerms, true);

if (!$hasManageUserPermission) {
    $permCheck = $pdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = ? AND perm_id = 3 LIMIT 1");
    $permCheck->execute([(int)$_SESSION['user_id']]);
    $hasManageUserPermission = (bool)$permCheck->fetchColumn();

    if ($hasManageUserPermission) {
        $_SESSION['permissions'] = array_values(array_unique(array_merge($sessionPerms, [3])));
    }
}

if (!$hasManageUserPermission) {
    header('Location: home.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| ฟังก์ชัน redirect กลับหน้า User Management
|--------------------------------------------------------------------------
*/
function goUserManagement($params = '')
{
    $url = 'user_Managerment.php';

    if ($params !== '') {
        $url .= '?' . $params;
    }

    header("Location: {$url}");
    exit;
}
function alertBack($message)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        history.back();
    </script>";
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
        $permissions = $_POST['permissions'] ?? [];

        if (
            $username === '' ||
            $passwordRaw === '' ||
            $fullname === '' ||
            $email === '' ||
            $role_id <= 0 ||
            $department_id <= 0
        ) {
            alertBack('กรุณากรอกข้อมูลให้ครบถ้วน');
        }

        // เช็ก username ซ้ำก่อนเพิ่ม
        $checkUsername = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE username = ?
        ");
        $checkUsername->execute([$username]);

        if ((int)$checkUsername->fetchColumn() > 0) {
            alertBack('ชื่อผู้ใช้นี้มีอยู่แล้ว กรุณาใช้ชื่อผู้ใช้อื่น');
        }

        // เช็ก email ซ้ำก่อนเพิ่ม
        $checkEmail = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE email = ?
        ");
        $checkEmail->execute([$email]);

        if ((int)$checkEmail->fetchColumn() > 0) {
            alertBack('อีเมลนี้มีอยู่แล้ว กรุณาใช้อีเมลอื่น');
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
        $newUserId = (int)$pdo->lastInsertId();

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
            $insertPerm->execute([$newUserId, $perm_id]);
        }
    }
}

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
        $role_id = (int)($_POST['role_id'] ?? 0);
        $position = trim($_POST['position'] ?? '');
        $department_id = (int)($_POST['department_id'] ?? 0);
        $is_active = (int)($_POST['is_active'] ?? 1);
        $permissions = $_POST['permissions'] ?? [];

        if (
            $user_id <= 0 ||
            $username === '' ||
            $fullname === '' ||
            $email === '' ||
            $role_id <= 0 ||
            $department_id <= 0
        ) {
            alertBack('กรุณากรอกข้อมูลให้ครบถ้วน');
        }

        // เช็ก username ซ้ำ แต่ยกเว้น user ตัวเอง
        $checkUsername = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users 
            WHERE username = ? 
              AND user_id <> ?
        ");
        $checkUsername->execute([$username, $user_id]);

        if ((int)$checkUsername->fetchColumn() > 0) {
            alertBack('ชื่อผู้ใช้นี้มีอยู่แล้ว กรุณาใช้ชื่อผู้ใช้อื่น');
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
            alertBack('อีเมลนี้มีอยู่แล้ว กรุณาใช้อีเมลอื่น');
        }

        $pdo->beginTransaction();

        // ถ้ามีการกรอกรหัสผ่านใหม่ ให้ update password ด้วย
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

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
                    is_active = ?
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
                    is_active = ?
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
            alertBack('ชื่อผู้ใช้นี้มีอยู่แล้ว กรุณาใช้ชื่อผู้ใช้อื่น');
        }

        if (stripos($msg, 'email') !== false) {
            alertBack('อีเมลนี้มีอยู่แล้ว กรุณาใช้อีเมลอื่น');
        }

        goUserManagement('error=duplicate');
    }

    goUserManagement('error=database');
}