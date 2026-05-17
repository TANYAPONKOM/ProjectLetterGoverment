<?php
// /update_memo.php
session_start();

/** DEV */
$DEV_AUTO_LOGIN = false;
$DEBUG_ERRORS = true;
if ($DEV_AUTO_LOGIN && empty($_SESSION['user_id'])) {
  $_SESSION['user_id'] = 1;
}

require_once __DIR__ . '/functions.php';

try {
  if (empty($_SESSION['user_id'])) {
    header('Location: /documents/form_Memo.html?err=unauthorized', true, 302);
    exit;
  }
  $userId = (int) $_SESSION['user_id'];

  $documentId = (int) ($_POST['document_id'] ?? 0);
  $templateId = (int) ($_POST['template_id'] ?? 1);

  if ($documentId <= 0) {
    header('Location: /documents/form_Memo.html?err=nodoc', true, 302);
    exit;
  }

  // ตรวจสิทธิ์เป็นเจ้าของเอกสาร
  $pdo = db();
  $chk = $pdo->prepare("SELECT owner_id FROM documents WHERE document_id = :id LIMIT 1");
  $chk->execute([':id' => $documentId]);
  $owner = (int) $chk->fetchColumn();
  if ($owner !== $userId) {
    header('Location: /documents/form_Memo.html?err=forbidden', true, 302);
    
    exit;
  }

  // รับค่า
  $docDate = trim($_POST['doc_date'] ?? '');
  $fullname = trim($_POST['fullname'] ?? '');
  $position = trim($_POST['position'] ?? '');
  $purpose = $_POST['purpose'] ?? '';
  $eventTitle = trim($_POST['event_title'] ?? '');
$researchTitle = trim($_POST['research_title'] ?? '');

  $dateOption = $_POST['date_option'] ?? '';
  $singleDate = trim($_POST['single_date'] ?? '');
  $rangeDate = trim($_POST['range_date'] ?? '');

  $isOnline = isset($_POST['is_online']) ? 1 : 0;
  $place = trim($_POST['place'] ?? '');

  $noCost = (($_POST['no_cost'] ?? '0') === '1') ? 1 : 0;

$amountRaw = str_replace(',', '', trim($_POST['amount'] ?? '0'));
$amount = $noCost ? 0.00 : (is_numeric($amountRaw) ? (float) $amountRaw : 0.00);

  $carUsed = isset($_POST['car_used']) ? 1 : 0;
  $carPlate = trim($_POST['car_plate'] ?? '');

  $faculty = trim($_POST['faculty'] ?? '');
  $department = trim($_POST['department'] ?? '');
  $headerText = trim($_POST['header_text'] ?? ''); // ✅ ใช้ค่าที่กรอกมาโดยตรง
  $docNo = trim($_POST['doc_no'] ?? '');
  $docDateDisp = trim($_POST['doc_date_display'] ?? '');

  // ตรวจขั้นต่ำ
  $errors = [];
  if ($docDate === '')
    $errors['doc_date'] = 'required';
  if ($purpose === '')
    $errors['purpose'] = 'required';
  if ($eventTitle === '')
    $errors['event_title'] = 'required';
  if ($purpose === 'academic' && $researchTitle === '')
    $errors['research_title'] = 'required';
  if ($dateOption === 'single' && $singleDate === '')
    $errors['single_date'] = 'required';
  if ($dateOption === 'range' && $rangeDate === '')
    $errors['range_date'] = 'required';
  if (!$isOnline && $place === '')
    $errors['place'] = 'required';
  if (!$noCost && !is_numeric($amountRaw))
    $errors['amount'] = 'number';
  if ($carUsed && $carPlate === '')
    $errors['car_plate'] = 'required';



  // ตรวจขั้นต่ำ
  if (!empty($errors)) {
    header('Location: /Pro_letter/documents/view_memo.php?id=' . $documentId . '&err=validate');
    exit;
  }

  // 🟢 ตรวจสอบสถานะเอกสารก่อนเริ่ม transaction
  $stmtStatus = $pdo->prepare("SELECT status FROM documents WHERE document_id = :id");
  $stmtStatus->execute([':id' => $documentId]);
  $currentStatus = $stmtStatus->fetchColumn();

  // ถ้าเอกสารเดิมเป็น “รอการแก้ไข” (rejected) → เปลี่ยนกลับเป็น “รอตรวจสอบ” (submitted)
  if ($currentStatus === 'rejected') {
    $pdo->prepare("UPDATE documents SET status = 'submitted', updated_at = NOW() WHERE document_id = :id")
      ->execute([':id' => $documentId]);
  }
error_log("DEBUG SESSION: user_id=" . $_SESSION['user_id'] . " role_id=" . ($_SESSION['role_id'] ?? 'NULL'));
error_log("DEBUG USER LOGIN => SESSION user_id=" . $_SESSION['user_id']);


// ---- DEBUG: ยืนยันว่าใช้ฐานข้อมูลไหนแน่ ----
$whichDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
error_log("DEBUG DB=" . $whichDb . " user_id=" . $userId);

// --- ตรวจสิทธิ์ document.edit ---
// 1) ห้ามแก้ถ้าไม่มีสิทธิ์ document.edit
$sql = "
  SELECT COUNT(*) 
  FROM user_permissions up
  JOIN permissions p ON p.perm_id = up.perm_id
  WHERE up.user_id = :uid AND p.perm_code = 'document.edit'
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid'=>$userId]);
$canEdit = (int)$stmt->fetchColumn() > 0;

if (!$canEdit) {
    header("Location: /Pro_letter/documents/view_memo.php?id={$documentId}&err=no_permission");
    exit;
}







  $pdo->beginTransaction();


  // อัปเดตหัวเอกสาร
  $joinType = match ($purpose) {
    'academic' => 'นำเสนอผลงานทางวิชาการ',
    'training' => 'เข้ารับการฝึกอบรมหลักสูตร',
    'meeting' => 'เข้าร่วมประชุมวิชาการในงาน',
    default => 'อื่นๆ',
  };
  $subject = trim($joinType . $eventTitle);

  $up = $pdo->prepare("
    UPDATE documents 
    SET doc_no = :doc_no,
        doc_date = :doc_date,
        subject = :subject,
        header_text = :header_text,
        updated_at = NOW() 
    WHERE document_id = :id
");
  $up->execute([
    ':doc_no' => $docNo,
    ':doc_date' => $docDate,
    ':subject' => $subject,
    ':header_text' => $headerText,
    ':id' => $documentId
  ]);

  // ค่า field อื่น ๆ
 $values = [
    1 => $docDate,
    2 => $fullname,
    3 => $position,
    4 => $joinType,
    5 => $eventTitle,
    6 => ($dateOption === 'single') ? $singleDate : $rangeDate,
    7 => $isOnline ? 'เข้าร่วมรูปแบบออนไลน์' : $place,
    8 => number_format($amount, 2, '.', ''),
    9 => $carUsed ? $carPlate : '',
    10 => $faculty,
    11 => $department,
    12 => (string)$noCost,
    13 => ($purpose === 'academic') ? $researchTitle : ''
];

  $q = $pdo->prepare("SELECT field_id FROM template_fields WHERE template_id = :tid");
  $q->execute([':tid' => $templateId]);
  $allowIds = array_flip($q->fetchAll(PDO::FETCH_COLUMN));

  $ins = $pdo->prepare("
        INSERT INTO document_values (document_id, field_id, value_text)
        VALUES (:document_id, :field_id, :value_text)
        ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)
  ");
  foreach ($values as $fieldId => $val) {
    if (!isset($allowIds[$fieldId]))
      continue;
    $ins->execute([
      ':document_id' => $documentId,
      ':field_id' => $fieldId,
      ':value_text' => $val
    ]);
  }

  if ($noCost) {
    $pdo->prepare("DELETE FROM budget_items WHERE document_id = :id")
      ->execute([':id' => $documentId]);
  }


$pdo->commit();

/* =======================================================
   🔄 ทำให้กลับไปหน้าเดิม (admin/officer/user)
   ======================================================= */
$redirectBack = $_POST['redirect_back'] ?? '';
$redirectBack = trim($redirectBack);

/* ถ้ามี referer ที่ส่งมา → กลับไปหน้านั้น */
if ($redirectBack !== '') {
    if (!$noCost) {
        header("Location: /Pro_letter/documents/form_Calcu.php?id={$documentId}&from=update");
        exit;
    }

    header("Location: /Pro_letter/documents/view_memo.php?id={$documentId}&saved=1&from=update");
    exit;
}


/* ถ้าไม่มี referer → fallback ไปหน้าตาม role */
$role = strtolower($_SESSION['role_name'] ?? '');

switch ($role) {
    case 'admin':
        $homePath = "/Pro_letter/admin/home.php";
        break;
    case 'officer':
        $homePath = "/Pro_letter/officer/home.php";
        break;
    default:
        $homePath = "/Pro_letter/user/home.php";
}

header("Location: {$homePath}?saved=1&from=update");
exit;



} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction())
    $pdo->rollBack();
  if ($DEBUG_ERRORS) {
    echo 'server error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  } else {
    header('Location: /Pro_letter/documents/view_memo.php?id=' . $documentId . '&err=server', true, 302);
  }
}