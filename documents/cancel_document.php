<?php
session_start();
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$documentId = (int)($input['document_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($documentId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ไม่พบรหัสเอกสาร'
    ]);
    exit;
}

try {
    $pdo = db();

    $stmt = $pdo->prepare("
        DELETE FROM documents
        WHERE document_id = ?
          AND owner_id = ?
          AND status = 'draft'
    ");
    $stmt->execute([$documentId, $userId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'ยกเลิกได้เฉพาะคำขอที่ยังไม่ได้ส่งเท่านั้น'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบ'
    ]);
}