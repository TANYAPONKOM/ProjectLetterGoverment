<?php
session_start();
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit("Unauthorized");
}

$docId = $_GET['id'] ?? null;
$approverId = $_SESSION['user_id'];

if ($docId) {
    $stmt = $pdo->prepare("UPDATE documents SET status = 'rejected', updated_at = NOW() WHERE document_id = ?");
    $stmt->execute([$docId]);

    $stmt = $pdo->prepare("INSERT INTO approvals (document_id, approver_id, action, action_at) 
                           VALUES (?, ?, 'rejected', NOW())
                           ON DUPLICATE KEY UPDATE action='rejected', action_at=NOW()");
    $stmt->execute([$docId, $approverId]);

    header("Location: request_list.php?msg=rejected");
    exit;
} else {
    echo "ไม่พบรหัสเอกสาร";
}