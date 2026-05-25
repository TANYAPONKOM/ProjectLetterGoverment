<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    /*
        ถ้ามีข้อมูลสิทธิ์ของผู้ใช้อยู่ใน user_permissions
        ให้ลบก่อน เพื่อไม่ให้ติด foreign key หรือข้อมูลค้าง
    */
    $permStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    $permStmt->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$id]);
}

// ไฟล์นี้อยู่ในโฟลเดอร์ admin อยู่แล้ว จึงไม่ต้องใช้ ../
header("Location: user_Managerment.php");
exit;