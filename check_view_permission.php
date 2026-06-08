<?php
session_start();
require_once __DIR__ . '/functions.php';

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["allowed" => false, "reason" => "not_login"]);
    exit;
}

$pdo = db();

$userId = (int)$_SESSION['user_id'];
$docId  = (int)($_GET['id'] ?? 0);

if ($docId <= 0) {
    echo json_encode(["allowed" => false, "reason" => "invalid_document"]);
    exit;
}

// เช็คว่าผู้ใช้มี permission ดูเอกสารหรือไม่ (perm_id = 2)
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM user_permissions 
    WHERE user_id = :uid 
      AND perm_id = 2
");
$stmt->execute([":uid" => $userId]);

$canView = (int)$stmt->fetchColumn();

if ($canView > 0) {
    echo json_encode(["allowed" => true]);
} else {
    echo json_encode(["allowed" => false, "reason" => "no_permission"]);
}

exit;