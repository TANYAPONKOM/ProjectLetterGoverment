<?php
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$username = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($username === '' && $password === '') { header('Location: login.html?user=required&pass=required'); exit; }
if ($username === '') { header('Location: login.html?user=required'); exit; }
if ($password === '') { header('Location: login.html?pass=required'); exit; }

$res = login($username, $password);

// ✅ ต้องเช็คก่อนทำอย่างอื่น
if (!$res['ok']) {
    $err = $res['error'] ?? 'unknown';

    // แยกให้ชัดเจนว่า Username ผิด หรือ Password ผิด
    if (!in_array($err, ['user', 'pass', 'inactive', 'db'], true)) {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $err = 'user';
            } elseif (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
                $err = 'inactive';
            } else {
                $storedPassword = (string)($user['password'] ?? '');

                if ($storedPassword !== '' && !password_verify($password, $storedPassword)) {
                    $err = 'pass';
                }
            }
        } catch (PDOException $e) {
            $err = 'db';
        }
    }

    header('Location: login.html?error=' . urlencode($err));
    exit;
}

// ✅ เก็บ session
$_SESSION['user_id']   = $res['user_id'];
$_SESSION['username']  = $res['username'];
$_SESSION['role_id']   = $res['role_id'];
$_SESSION['fullname']  = $res['fullname'];
$_SESSION['position']  = $res['position'];
$_SESSION['role_name'] = $res['role_name'];

// ✅ ดึง permissions หลัง login ผ่านเท่านั้น
$pdo = getPDO(); // หรือ db()
$stmt = $pdo->prepare("SELECT perm_id FROM user_permissions WHERE user_id = ?");
$stmt->execute([$res['user_id']]);
$_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Redirect ตาม role_id
switch ((int)$res['role_id']) {
    case 1: header('Location: admin/home.php'); break;
    case 2: header('Location: officer/home.php'); break;
    case 3: header('Location: user/home.php'); break;
    default: header('Location: login.html?error=role');
}
exit;