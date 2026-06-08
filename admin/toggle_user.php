<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
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