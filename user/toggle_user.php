<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

$permissions = array_map('intval', $_SESSION['permissions'] ?? []);
$isAdmin = ((int)($_SESSION['role_id'] ?? 0) === 1);
$canManageUsers = in_array(3, $permissions, true);

if (!$isAdmin && !$canManageUsers) {
    $roleId = (int)($_SESSION['role_id'] ?? 0);

    if ($roleId === 2) {
        header('Location: ../officer/home.php');
    } elseif ($roleId === 3) {
        header('Location: ../user/home.php');
    } else {
        header('Location: ../login.html');
    }
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? 1; // ค่าใหม่ที่จะตั้ง

$stmt = $pdo->prepare("UPDATE users SET is_active=? WHERE user_id=?");
$stmt->execute([$status, $id]);

header("Location: user_Managerment.php");
exit;