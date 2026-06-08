<?php
session_start();
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$id = $_GET['id'] ?? 0;
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM logs WHERE log_id = ?");
    $stmt->execute([$id]);

    // log การลบด้วย (optional)
    addLog($_SESSION['user_id'], "ลบประวัติ log_id: " . $id);
}

header("Location: home.php?deleted=1");
exit;