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

$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? 1; // ค่าใหม่ที่จะตั้ง

$stmt = $pdo->prepare("UPDATE users SET is_active=? WHERE user_id=?");
$stmt->execute([$status, $id]);

header("Location: user_Managerment.php");
exit;