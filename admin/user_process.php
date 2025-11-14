<?php
session_start();
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $currentUser = $_SESSION['username'] ?? 'Unknown';

    // ✅ เพิ่มผู้ใช้
    if ($action === 'add') {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $fullname = $_POST['fullname'];
        $email = strtolower(trim($_POST['email']));
        $role_id = $_POST['role_id'];
        $position = $_POST['position'];
        $department_id = $_POST['department_id'];

        $stmt = $pdo->prepare("INSERT INTO users 
            (username, password, fullname, email, role_id, position, department_id, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$username, $password, $fullname, $email, $role_id, $position, $department_id]);

        addLog($_SESSION['user_id'], "ผู้ใช้ {$currentUser} จัดการเพิ่มผู้ใช้: {$username}");
        header("Location: ../user_Managerment.php?success=1");
        exit;
    }

    // ✅ แก้ไขผู้ใช้ (รวมทั้งอัปเดตสิทธิ์)
    // ✅ แก้ไขผู้ใช้
if ($action === 'edit') {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $fullname = $_POST['fullname'];
    $email = strtolower(trim($_POST['email']));
    $role_id = $_POST['role_id'];
    $position = $_POST['position'];
    $department_id = $_POST['department_id'];
    $is_active = $_POST['is_active'] ?? 1;
    $permissions = $_POST['permissions'] ?? []; // รับค่าที่ติ๊ก checkbox

    // ✅ ถ้ามีรหัสผ่านใหม่
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users 
            SET username=?, password=?, fullname=?, email=?, role_id=?, position=?, department_id=?, is_active=? 
            WHERE user_id=?");
        $stmt->execute([$username, $password, $fullname, $email, $role_id, $position, $department_id, $is_active, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users 
            SET username=?, fullname=?, email=?, role_id=?, position=?, department_id=?, is_active=? 
            WHERE user_id=?");
        $stmt->execute([$username, $fullname, $email, $role_id, $position, $department_id, $is_active, $user_id]);
    }

    // ✅ ลบสิทธิ์เก่าก่อน
    $pdo->prepare("DELETE FROM user_permissions WHERE user_id=?")->execute([$user_id]);

    // ✅ เพิ่มสิทธิ์ใหม่จาก checkbox
    $insert = $pdo->prepare("INSERT INTO user_permissions (user_id, perm_id) VALUES (?, ?)");
    foreach ($permissions as $perm_id) {
        $insert->execute([$user_id, $perm_id]);
    }

    // ✅ บันทึก log
    addLog($_SESSION['user_id'], "แก้ไขข้อมูลผู้ใช้ {$username} และอัปเดตสิทธิ์การเข้าถึง");

    header("Location: ../user_Managerment.php?success=1");
    exit;
}


    // ✅ ลบผู้ใช้
    if ($action === 'delete') {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id=?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$user_id]);
            addLog($_SESSION['user_id'], "ผู้ใช้ {$currentUser} จัดการลบผู้ใช้: {$user['username']}");
        }

        header("Location: ../user_Managerment.php?success=1");
        exit;
    }
}
?>