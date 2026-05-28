<?php
session_start();
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

// อนุญาตให้เข้าได้เฉพาะ admin หรือผู้ที่มีสิทธิ์กำหนดสิทธิ์ (perm_id = 3)
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentUser = $_SESSION['username'] ?? 'Unknown';
$currentUserId = $_SESSION['user_id'] ?? null;

if ($id <= 0) {
    header("Location: user_Managerment.php?error=invalid_user");
    exit;
}

$stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: user_Managerment.php?error=user_not_found");
    exit;
}

try {
    $pdo->beginTransaction();

    // ลบสิทธิ์ก่อน เพื่อไม่ให้ติด foreign key ใน user_permissions
    $permStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    $permStmt->execute([$id]);

    // ลบผู้ใช้
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    if (function_exists('addLog') && $currentUserId) {
        addLog($currentUserId, "ผู้ใช้ {$currentUser} จัดการลบผู้ใช้: {$user['username']}");
    }

    header("Location: user_Managerment.php?success=1");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // ถ้าลบจริงไม่ได้ เพราะมีเอกสารหรือ log ผูกอยู่ ให้ปิดการใช้งานแทน
    $soft = $pdo->prepare("
        UPDATE users 
        SET is_active = 0 
        WHERE user_id = ?
    ");
    $soft->execute([$id]);

    if (function_exists('addLog') && $currentUserId) {
        addLog($currentUserId, "ผู้ใช้ {$currentUser} ปิดการใช้งานผู้ใช้: {$user['username']} เนื่องจากมีข้อมูลผูกอยู่ในระบบ");
    }

    header("Location: user_Managerment.php?success=deactivated");
    exit;
}
?>