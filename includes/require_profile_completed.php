<?php
// includes/require_profile_completed.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../functions.php';

// ถ้ายังไม่ได้ Login จริง ห้ามเข้าแบบฟอร์มเด็ดขาด
if (empty($_SESSION['user_id'])) {
    header('Location: /Pro_letter/login.html?error=login');
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        SELECT 
            user_id,
            fullname,
            email,
            role_id,
            is_active,
            profile_completed
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ถ้า session มีแต่ user ไม่มีจริงใน DB ให้ล้าง session
    if (!$user) {
        session_unset();
        session_destroy();
        header('Location: /Pro_letter/login.html?error=user');
        exit;
    }

    // ถ้าบัญชีถูกปิด
    if ((int)$user['is_active'] !== 1) {
        session_unset();
        session_destroy();
        header('Location: /Pro_letter/login.html?error=inactive');
        exit;
    }

    // อัปเดต session ล่าสุด
    $_SESSION['fullname'] = $user['fullname'] ?? '';
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['role_id'] = (int)$user['role_id'];
    $_SESSION['profile_completed'] = (int)$user['profile_completed'];

    // ถ้า Admin ยังไม่เพิ่มข้อมูลครบ ห้ามเข้าแบบฟอร์ม
    if ((int)$user['profile_completed'] !== 1) {
        header('Location: /Pro_letter/pending_profile.php');
        exit;
    }

} catch (PDOException $e) {
    header('Location: /Pro_letter/login.html?error=db');
    exit;
}