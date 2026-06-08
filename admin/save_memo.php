<?php
session_start();
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$template_id = $_POST['template_id'] ?? 0;
if (!$template_id) {
    die("ไม่พบ template_id");
}

// ดึงฟิลด์ของ template นี้
$stmt = $pdo->prepare("SELECT * FROM template_fields WHERE template_id=? ORDER BY sort_order ASC");
$stmt->execute([$template_id]);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

// loop เก็บข้อมูลลง memo_data
foreach ($fields as $f) {
    $key = $f['field_key'];
    $val = $_POST[$key] ?? null;

    $stmt2 = $pdo->prepare("
        INSERT INTO memo_data (template_id, field_id, field_key, value, created_at) 
        VALUES (?,?,?,?,NOW())
    ");
    $stmt2->execute([$template_id, $f['field_id'], $key, $val]);
}

header("Location: form_Templates.php?success=1");
exit;