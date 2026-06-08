<?php
session_start();
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $log_id = $_POST['log_id'];

    $stmt = $pdo->prepare("DELETE FROM logs WHERE id = ?");
    $stmt->execute([$log_id]);

    // บันทึกว่าใครลบ log ด้วย
    addLog($_SESSION['user_id'], "ลบประวัติการดำเนินการ ID: {$log_id}");

    header("Location: home.php?log_deleted=1");
    exit;
}