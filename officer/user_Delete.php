<?php
session_start();
<<<<<<< HEAD

=======
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

<<<<<<< HEAD
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
=======
// อนุญาตให้เข้าได้เฉพาะ admin หรือผู้ที่มีสิทธิ์กำหนดสิทธิ์ (perm_id = 3)
$sessionPerms = array_map('intval', $_SESSION['permissions'] ?? []);
$hasManageUserPermission = ((int)($_SESSION['role_id'] ?? 0) === 1) || in_array(3, $sessionPerms, true);

if (!$hasManageUserPermission) {
    $permCheck = $pdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = ? AND perm_id = 3 LIMIT 1");
    $permCheck->execute([(int)$_SESSION['user_id']]);
    $hasManageUserPermission = (bool)$permCheck->fetchColumn();

    if ($hasManageUserPermission) {
        $_SESSION['permissions'] = array_values(array_unique(array_merge($sessionPerms, [3])));
    }
}

if (!$hasManageUserPermission) {
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
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

<<<<<<< HEAD
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
=======
    // ลบสิทธิ์ก่อน เพื่อไม่ให้ติด foreign key ใน user_permissions
    $permStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    $permStmt->execute([$id]);

    // ลบผู้ใช้
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    if (function_exists('addLog') && $currentUserId) {
        addLog($currentUserId, "ผู้ใช้ {$currentUser} จัดการลบผู้ใช้: {$user['username']}");
    }

<<<<<<< HEAD
    header("Location: user_Managerment.php?success=delete");
=======
    header("Location: user_Managerment.php?success=1");
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

<<<<<<< HEAD
    header("Location: user_Managerment.php?error=delete_failed");
    exit;
}
?>
=======
    // ถ้าลบจริงไม่ได้ เพราะมีเอกสารหรือ log ผูกอยู่ ให้ปิดการใช้งานแทน
    $soft = $pdo->prepare("
        UPDATE users 
        SET is_active = 0 
        WHERE user_id = ?
    ");
    $soft->execute([$id]);

    if (function_exists('addLog') && $currentUserId) {
        addLog($currentUserId, "ผู้ใช้ {$currentUser} ปิดการใช้งานผู้ใช้: {$user['username']} เนื่องจากมีข้อมูลผูกอยู่ในระบบ");
    }

    header("Location: user_Managerment.php?success=deactivated");
    exit;
}
?>
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
