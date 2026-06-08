<?php
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

header('Content-Type: application/json; charset=utf-8'); // บังคับ output เป็น JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faculty_name = trim($_POST['faculty_name'] ?? '');

    if ($faculty_name !== '') {
        $stmt = $pdo->prepare("INSERT INTO faculties (faculty_name) VALUES (?)");
        $stmt->execute([$faculty_name]);

        echo json_encode([
            "success" => true,
            "faculty_id" => $pdo->lastInsertId(),
            "faculty_name" => $faculty_name
        ]);
        exit;
    }
}

// ถ้ามี error
echo json_encode(["success" => false, "message" => "ชื่อคณะว่าง หรือบันทึกไม่สำเร็จ"]);
exit;