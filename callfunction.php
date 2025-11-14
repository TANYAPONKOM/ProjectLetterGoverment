<?php
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$username = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');

// ตรวจสอบว่ากรอกครบ
if ($username === '' && $password === '') { 
    header('Location: login.html?user=required&pass=required'); exit;
}
if ($username === '') { 
    header('Location: login.html?user=required'); exit;
}
if ($password === '') { 
    header('Location: login.html?pass=required'); exit;
}

// ตรวจสอบล็อกอิน
$res = login($username, $password);

// 🧠 ทดสอบดูว่า login() ส่งค่ามาเป็นชื่อใคร
// echo "<pre>";
// print_r($res);
// echo "</pre>";
// exit;

// ✅ ดึงสิทธิ์ทั้งหมดของผู้ใช้ (ตาม user_id)
$pdo = getPDO();
$permStmt = $pdo->prepare("SELECT perm_id FROM user_permissions WHERE user_id = ?");
$permStmt->execute([$res['user_id']]);
$_SESSION['permissions'] = $permStmt->fetchAll(PDO::FETCH_COLUMN);


if (!$res['ok']) {
    if ($res['error'] === 'db') {
        header('Location: login.html?error=db'); exit;
    }
    if ($res['error'] === 'user') {
        header('Location: login.html?error=user'); exit;
    }
    if ($res['error'] === 'pass') {
        header('Location: login.html?error=pass'); exit;
    }
    if ($res['error'] === 'inactive') {
        header('Location: login.html?error=inactive'); exit;
    }
}

// ✅ เก็บ session เดิม
$_SESSION['user_id']   = $res['user_id'];
$_SESSION['username']  = $res['username'];
$_SESSION['role_id']   = $res['role_id'];
$_SESSION['fullname']  = $res['fullname'];
$_SESSION['position']  = $res['position'];
$_SESSION['role_name'] = $res['role_name'];
$_SESSION['perm_id']   = $res['perm_id'];

// ✅ ดึงสิทธิ์ทั้งหมดของผู้ใช้
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT perm_id FROM user_permissions WHERE user_id = ?");
$stmt->execute([$res['user_id']]);
$_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);


// Redirect ตาม role_id
switch ((int)$res['role_id']) {
    case 1:
        header('Location: admin/home.php');
        break;
    case 2:
        header('Location: officer/home.php');
        break;
    case 3:
        header('Location: user/home.php'); 
        break;
    default:
        header('Location: login.html?error=role');
}
exit;