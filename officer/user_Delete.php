<?php
session_start();

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

$permissions = array_map('intval', $_SESSION['permissions'] ?? []);
$isAdmin = ((int)($_SESSION['role_id'] ?? 0) === 1);
$canManageUsers = in_array(3, $permissions, true);

if (!$isAdmin && !$canManageUsers) {
    $permCheck = $pdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = ? AND perm_id = 3 LIMIT 1");
    $permCheck->execute([(int)$_SESSION['user_id']]);
    $canManageUsers = (bool)$permCheck->fetchColumn();

    if ($canManageUsers) {
        $_SESSION['permissions'] = array_values(array_unique(array_merge($permissions, [3])));
    }
}

if (!$isAdmin && !$canManageUsers) {
    header('Location: home.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentUser = $_SESSION['username'] ?? 'Unknown';
$currentUserId = $_SESSION['user_id'] ?? null;

if ($id <= 0) {
    header("Location: user_Managerment.php?error=invalid_user");
    exit;
}

$stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: user_Managerment.php?error=user_not_found");
    exit;
}

try {
    $pdo->beginTransaction();

    // ดึงเอกสารที่ผูกกับผู้ใช้นี้ เพื่อให้ลบผู้ใช้ได้จริง
    $docStmt = $pdo->prepare("
        SELECT document_id
        FROM documents
        WHERE owner_id = ?
           OR approved_by = ?
    ");
    $docStmt->execute([$id, $id]);
    $documentIds = $docStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($documentIds)) {
        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));

        // ลบข้อมูลลูกที่อ้างอิง document_id ก่อน เพื่อไม่ให้ติด foreign key
        $fkDocStmt = $pdo->prepare("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME = 'documents'
              AND REFERENCED_COLUMN_NAME = 'document_id'
        ");
        $fkDocStmt->execute();
        $docRefs = $fkDocStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($docRefs as $ref) {
            $table = str_replace('`', '``', $ref['TABLE_NAME']);
            $column = str_replace('`', '``', $ref['COLUMN_NAME']);

            if ($table === 'documents') {
                continue;
            }

            $deleteChild = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$column}` IN ({$placeholders})");
            $deleteChild->execute($documentIds);
        }

        // ลบเอกสารเก่าที่ผูกกับผู้ใช้นี้
        $deleteDocs = $pdo->prepare("DELETE FROM documents WHERE document_id IN ({$placeholders})");
        $deleteDocs->execute($documentIds);
    }

    // ลบข้อมูลลูกที่อ้างอิง user_id ก่อน เพื่อไม่ให้ติด foreign key
    $fkUserStmt = $pdo->prepare("
        SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND REFERENCED_TABLE_NAME = 'users'
          AND REFERENCED_COLUMN_NAME = 'user_id'
    ");
    $fkUserStmt->execute();
    $userRefs = $fkUserStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($userRefs as $ref) {
        $table = str_replace('`', '``', $ref['TABLE_NAME']);
        $column = str_replace('`', '``', $ref['COLUMN_NAME']);

        if ($table === 'users') {
            continue;
        }

        $deleteRef = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?");
        $deleteRef->execute([$id]);
    }

    // ลบผู้ใช้จริง
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    if (function_exists('addLog') && $currentUserId) {
        addLog($currentUserId, "ผู้ใช้ {$currentUser} จัดการลบผู้ใช้: {$user['username']}");
    }

    header("Location: user_Managerment.php?success=delete");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header("Location: user_Managerment.php?error=delete_failed");
    exit;
}
?>
