<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $docStmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE template_id = ?");
    $docStmt->execute([$id]);
    $usedCount = (int)$docStmt->fetchColumn();

    if ($usedCount > 0) {
        header("Location: form_Templates.php?status=delete_blocked");
        exit;
    }

    $fieldStmt = $pdo->prepare("DELETE FROM template_fields WHERE template_id = ?");
    $fieldStmt->execute([$id]);

    $stmt = $pdo->prepare("DELETE FROM templates WHERE template_id = ?");
    $stmt->execute([$id]);

    header("Location: form_Templates.php?status=deleted");
    exit;
}

header("Location: form_Templates.php");
exit;
