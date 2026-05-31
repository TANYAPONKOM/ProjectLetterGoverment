<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$id = $_GET['id'] ?? 0;

if ($id) {
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE department_id=?");
    $checkStmt->execute([$id]);
    $documentCount = (int)$checkStmt->fetchColumn();

    if ($documentCount > 0) {
        echo "<script>alert('ไม่สามารถลบภาควิชานี้ได้ เนื่องจากมีเอกสารที่ใช้งานภาควิชานี้อยู่'); window.location.href='department_Managerment.php';</script>";
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id=?");
    $stmt->execute([$id]);
}

header("Location: department_Managerment.php?success=delete");
exit;
?>
