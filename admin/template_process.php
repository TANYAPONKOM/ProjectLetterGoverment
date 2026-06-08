<?php
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

// แสดง error (ช่วย debug)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $field_key   = $_POST['field_key'] ?? '';
        $field_label = $_POST['field_label'] ?? '';
        $field_type  = $_POST['field_type'] ?? 'text';
        $is_required = $_POST['is_required'] ?? 0;
        $sort_order  = $_POST['sort_order'] ?? 0;

        $stmt = $pdo->prepare("INSERT INTO template_fields 
            (template_id, field_key, field_label, field_type, is_required, sort_order) 
            VALUES (1, ?, ?, ?, ?, ?)");
        $stmt->execute([$field_key, $field_label, $field_type, $is_required, $sort_order]);

        header("Location: form_Templates.php");
        exit;
    }

    if ($action === 'edit') {
        $id          = $_POST['field_id'] ?? 0;
        $field_key   = $_POST['field_key'] ?? '';
        $field_label = $_POST['field_label'] ?? '';
        $field_type  = $_POST['field_type'] ?? 'text';
        $is_required = $_POST['is_required'] ?? 0;
        $sort_order  = $_POST['sort_order'] ?? 0;

        $stmt = $pdo->prepare("UPDATE template_fields 
            SET field_key=?, field_label=?, field_type=?, is_required=?, sort_order=? 
            WHERE field_id=?");
        $stmt->execute([$field_key, $field_label, $field_type, $is_required, $sort_order, $id]);

        header("Location: form_Templates.php");
        exit;
    }

    // กรณี action ไม่ถูกต้อง
    echo "❌ Unknown action!";
    exit;
} else {
    echo "❌ Invalid request!";
    exit;
}