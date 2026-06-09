<?php
// pro_letter/documents/get_requests.php
session_start();
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$userId = $_SESSION['user_id'] ?? 0; 


$sql_owner = "
  SELECT 
    d.document_id,
    d.doc_date,
    d.status,
    d.subject,
    d.document_type_name,
    u.fullname AS owner_name,
    t.template_code,
    t.template_name,
    t.question_path,
    t.document_path,
    MAX(CASE WHEN f.field_key = 'join_type' THEN v.value_text END) AS join_type,
    MAX(CASE WHEN f.field_key = 'course_name' THEN v.value_text END) AS course_name,
    MAX(CASE WHEN f.field_key IN ('memo_subject', 'free_subject') THEN v.value_text END) AS memo_subject,
    MAX(CASE WHEN f.field_key = 'free_paragraph_1' THEN v.value_text END) AS free_paragraph_1
  FROM documents d
  LEFT JOIN templates t ON t.template_id = d.template_id
  LEFT JOIN users u ON u.user_id = d.owner_id
  LEFT JOIN document_values v ON d.document_id = v.document_id
  LEFT JOIN template_fields f ON v.field_id = f.field_id
  WHERE d.owner_id = :u
  GROUP BY
    d.document_id,
    d.doc_date,
    d.status,
    d.subject,
    d.document_type_name,
    u.fullname,
    t.template_code,
    t.template_name,
    t.question_path,
    t.document_path
  ORDER BY d.created_at DESC
";

// $sql_all = "
//   SELECT 
//     d.document_id,
//     d.doc_date,
//     d.status,
//     MAX(CASE WHEN f.field_key = 'join_type' THEN v.value_text END) AS join_type,
//     MAX(CASE WHEN f.field_key = 'course_name' THEN v.value_text END) AS course_name
//   FROM documents d
//   LEFT JOIN document_values v ON d.document_id = v.document_id
//   LEFT JOIN template_fields f ON v.field_id = f.field_id
//   GROUP BY d.document_id, d.doc_date, d.status
//   ORDER BY d.created_at DESC
// ";

$stmt = $pdo->prepare($sql_owner);
$stmt->execute([':u' => $userId]);


// $stmt = $pdo->query($sql_all);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows, JSON_UNESCAPED_UNICODE);