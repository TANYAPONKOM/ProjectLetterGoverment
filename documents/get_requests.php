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
    MAX(CASE WHEN f.field_key = 'join_type' THEN v.value_text END) AS join_type,
    MAX(CASE WHEN f.field_key = 'course_name' THEN v.value_text END) AS course_name
  FROM documents d
  LEFT JOIN document_values v ON d.document_id = v.document_id
  LEFT JOIN template_fields f ON v.field_id = f.field_id
  WHERE d.owner_id = :u
  GROUP BY d.document_id, d.doc_date, d.status
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