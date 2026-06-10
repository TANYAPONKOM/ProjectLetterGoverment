<!-- Pro_letter/form_Memo/form_memo_room_request_1.php ขออนุมัติใช้ห้องพักรับรอง -->
<?php
session_start();
require_once __DIR__ . '/../functions.php';

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$referer = trim(str_replace(["\r", "\n"], "", $referer));

/* --------------------------------------------------
   ตรวจ session
-------------------------------------------------- */
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  exit("Unauthorized");
}

$userId = (int) $_SESSION['user_id'];
$role = strtolower($_SESSION['role_name'] ?? 'user');

/* --------------------------------------------------
   ตั้ง homePath ตาม role
-------------------------------------------------- */
$roleId = $_SESSION['role_id'] ?? 0;
$roleId = (int) ($_SESSION['role_id'] ?? 0);
$isAdmin = ($roleId === 1);
$isOfficer = ($roleId === 2);


if ($roleId == 1) {
  $homePath = "/Pro_letter/admin/home.php";
} elseif ($roleId == 2) {
  $homePath = "/Pro_letter/officer/home.php";
} else {
  $homePath = "/Pro_letter/user/home.php";
}


/* --------------------------------------------------
   รับ document_id
-------------------------------------------------- */
$pdo = db();
$docId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($docId <= 0) {
  $q = $pdo->prepare("
        SELECT document_id 
        FROM documents 
        WHERE owner_id = :uid
        ORDER BY document_id DESC
        LIMIT 1
    ");
  $q->execute([':uid' => $userId]);
  $docId = (int) ($q->fetchColumn() ?: 0);

  if ($docId <= 0)
    exit("ยังไม่มีเอกสารของคุณ");
}

$editFormPath = "/Pro_letter/documents/infor_room_request.php?id=" . $docId;

/* --------------------------------------------------
   โหลดข้อมูลเอกสาร
-------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT document_id, template_id, owner_id, department_id, 
           doc_no, doc_date, subject, header_text, status
    FROM documents 
    WHERE document_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $docId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document)
  exit("ไม่พบเอกสาร");

/* --------------------------------------------------
   สิทธิ์ดูเอกสาร
-------------------------------------------------- */
// Officer: role_id = 2
// Admin:   role_id = 1
$roleId = (int) ($_SESSION['role_id'] ?? 0);

// officer & admin ดูได้ทุกอัน
if ($roleId !== 1 && $roleId !== 2) {
  // user: ดูเฉพาะของตัวเอง
  if ($document['owner_id'] != $userId) {
    header("Location: {$homePath}?err=no_view");
    exit;
  }
}

/* --------------------------------------------------
   สิทธิ์แก้ไขเอกสาร
-------------------------------------------------- */
$roleId = (int)($_SESSION['role_id'] ?? 0);
$isAdmin = ($roleId === 1);
$isOfficer = ($roleId === 2);
$isOwner = ((int)($document['owner_id'] ?? 0) === $userId);
$docStatus = trim((string)($document['status'] ?? ''));

/*
  เช็กสิทธิ์แก้ไขจาก permission จริง
  perm_id = 1 คือ แก้ไขได้
  หรือ perm_code = document.edit
*/
$hasDocumentEditPermission = false;
try {
  $permStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM (
      SELECT up.perm_id
      FROM user_permissions up
      LEFT JOIN permissions p ON p.perm_id = up.perm_id
      WHERE up.user_id = :uid
        AND (up.perm_id = 1 OR p.perm_code = 'document.edit')

      UNION

      SELECT rp.perm_id
      FROM role_permissions rp
      LEFT JOIN permissions p ON p.perm_id = rp.perm_id
      WHERE rp.role_id = :rid
        AND (rp.perm_id = 1 OR p.perm_code = 'document.edit')
    ) AS edit_perms
  ");
  $permStmt->execute([
    ':uid' => $userId,
    ':rid' => $roleId
  ]);
  $hasDocumentEditPermission = ((int)$permStmt->fetchColumn() > 0);
} catch (Throwable $permError) {
  $hasDocumentEditPermission = false;
}

$userEditableStatuses = ['draft', 'รอยืนยันการส่ง', 'rejected', 'รอแก้เอกสาร', 'รอแก้ไข'];
$submittedStatuses = ['submitted', 'รอตรวจ', 'รอตรวจสอบ', 'รอการตรวจสอบ'];
$checkedStatuses = ['ผ่านการตรวจสอบ', 'ผ่านการตรวจสอบแล้ว', 'ได้รับการตรวจสอบ', 'ได้รับการตรวจสอบแล้ว', 'ตรวจสอบแล้ว', 'approved', 'checked', 'reviewed'];

$isCheckedStatus = in_array($docStatus, $checkedStatuses, true);
$isSubmittedStatus = in_array($docStatus, $submittedStatuses, true);
$isUserEditableStatus = in_array($docStatus, $userEditableStatuses, true);

/*
  เหตุผลที่แก้ไม่ได้ มี 3 กรณีตามที่กำหนด
*/
$editDisabledReason = '';
$editAlertTitle = '';
$editAlertText = '';
$editAlertIcon = 'info';

if ($isCheckedStatus) {
  $editDisabledReason = 'checked';
  $editAlertTitle = 'เอกสารผ่านการตรวจสอบแล้ว';
  $editAlertText = 'เอกสารนี้ผ่านการตรวจสอบแล้ว จึงไม่สามารถแก้ไขได้';
  $editAlertIcon = 'success';
} elseif (!$isAdmin && !$isOfficer && $isSubmittedStatus) {
  $editDisabledReason = 'submitted';
  $editAlertTitle = 'เอกสารอยู่ระหว่างรอตรวจสอบ';
  $editAlertText = 'เอกสารนี้ถูกส่งเข้าสู่การตรวจสอบแล้ว จึงไม่สามารถแก้ไขได้ในขณะนี้';
  $editAlertIcon = 'info';
} elseif (!$hasDocumentEditPermission) {
  $editDisabledReason = 'no_permission';
  $editAlertTitle = 'ไม่มีสิทธิ์แก้ไขเอกสาร';
  $editAlertText = 'คุณไม่มีสิทธิ์แก้ไขเอกสารนี้ กรุณาติดต่อผู้ดูแลระบบหากต้องการแก้ไข';
  $editAlertIcon = 'warning';
}

/*
  เงื่อนไขปุ่มแก้ไข
  - ผ่านการตรวจสอบแล้ว: ทุก role แก้ไม่ได้
  - User ที่รอตรวจสอบ: แก้ไม่ได้
  - ไม่มีสิทธิ์แก้ไข: ทุก role แก้ไม่ได้
  - User ต้องเป็นเจ้าของเอกสารและสถานะอยู่ในกลุ่มที่แก้ได้
  - Admin/Officer ที่มีสิทธิ์แก้ไข แก้ได้ถ้าเอกสารยังไม่ผ่านตรวจ
*/
if ($isCheckedStatus) {
  $canEdit = false;
} elseif (!$hasDocumentEditPermission) {
  $canEdit = false;
} elseif (!$isAdmin && !$isOfficer && $isSubmittedStatus) {
  $canEdit = false;
} elseif ($isAdmin || $isOfficer) {
  $canEdit = true;
} else {
  $canEdit = ($isOwner && $isUserEditableStatus);
}

$readonly = !$canEdit;


/* --------------------------------------------------
   ดึงค่า field จาก document_values
-------------------------------------------------- */
$q = $pdo->prepare("SELECT field_id, value_text FROM document_values WHERE document_id = :id");
$q->execute([':id' => $docId]);

$valueMap = [];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $valueMap[(int) $row['field_id']] = $row['value_text'];
}

/* --------------------------------------------------
   ฟังก์ชัน helper
-------------------------------------------------- */
// function h($s)
// {
//   return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
// }

function thai_date($ymd)
{
  if (!$ymd || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd))
    return "";

  [$y, $m, $d] = explode("-", $ymd);

  if ((int)$y <= 0 || (int)$m < 1 || (int)$m > 12 || (int)$d < 1 || (int)$d > 31) {
    return "";
  }

  $months = [
    1 => "มกราคม",
    2 => "กุมภาพันธ์",
    3 => "มีนาคม",
    4 => "เมษายน",
    5 => "พฤษภาคม",
    6 => "มิถุนายน",
    7 => "กรกฎาคม",
    8 => "สิงหาคม",
    9 => "กันยายน",
    10 => "ตุลาคม",
    11 => "พฤศจิกายน",
    12 => "ธันวาคม"
  ];
  return intval($d) . " " . $months[intval($m)] . " " . (intval($y) + 543);
}


function thai_date_flexible($dateText)
{
  $dateText = trim((string)$dateText);
  if ($dateText === "") {
    return "";
  }

  // ถ้าเป็นวันที่ไทยอยู่แล้ว ให้คืนค่าเดิม
  if (preg_match('/[ก-๙]+/u', $dateText)) {
    return $dateText;
  }

  // รูปแบบ 2026-09-06
  if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $dateText, $m)) {
    return thai_date(sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]));
  }

  // รูปแบบ 06/09/2026 หรือ 06-09-2026 จาก datepicker
  // ตีความเป็น วัน/เดือน/ปี
  if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateText, $m)) {
    return thai_date(sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]));
  }

  return $dateText;
}

function thai_date_range_flexible($dateText)
{
  $dateText = trim((string)$dateText);
  if ($dateText === "") {
    return "";
  }

  // รองรับช่วงวันที่ เช่น 06/09/2026 ถึง 08/09/2026
  if (preg_match('/\s*(?:ถึง|-|–|—|to)\s*/u', $dateText)) {
    $parts = preg_split('/\s*(?:ถึง|-|–|—|to)\s*/u', $dateText);
    if (count($parts) >= 2) {
      $start = thai_date_flexible($parts[0]);
      $end = thai_date_flexible($parts[1]);
      if ($start !== "" && $end !== "") {
        return $start . " ถึง " . $end;
      }
    }
  }

  return thai_date_flexible($dateText);
}


function thai_digits($text)
{
  return strtr((string)$text, [
    '0' => '๐',
    '1' => '๑',
    '2' => '๒',
    '3' => '๓',
    '4' => '๔',
    '5' => '๕',
    '6' => '๖',
    '7' => '๗',
    '8' => '๘',
    '9' => '๙',
  ]);
}

function arabic_digits($text)
{
  return strtr((string)$text, [
    '๐' => '0',
    '๑' => '1',
    '๒' => '2',
    '๓' => '3',
    '๔' => '4',
    '๕' => '5',
    '๖' => '6',
    '๗' => '7',
    '๘' => '8',
    '๙' => '9',
  ]);
}

function h_thai_digits($text)
{
  return h(arabic_digits($text));
}

// บังคับเลขที่ดึงมาจากฐานข้อมูลให้เป็นเลขอารบิกทุกช่อง ก่อนนำไปแสดงผล
foreach ($valueMap as $k => $v) {
  $valueMap[$k] = arabic_digits($v);
}
foreach (['doc_no', 'subject', 'header_text'] as $k) {
  if (isset($document[$k])) {
    $document[$k] = arabic_digits($document[$k]);
  }
}

function room_header_subject_lines($text, $limit = 74)
{
  $text = trim(preg_replace('/\s+/u', ' ', arabic_digits((string)$text)));
  if ($text === '') {
    return [''];
  }

  $lines = [];
  while (mb_strlen($text, 'UTF-8') > $limit) {
    $cut = mb_substr($text, 0, $limit, 'UTF-8');
    $spacePos = mb_strrpos($cut, ' ', 0, 'UTF-8');

    if ($spacePos !== false && $spacePos > 28) {
      $lines[] = trim(mb_substr($text, 0, $spacePos, 'UTF-8'));
      $text = trim(mb_substr($text, $spacePos + 1, null, 'UTF-8'));
    } else {
      $softCut = mb_strrpos($cut, '‌', 0, 'UTF-8');
      if ($softCut !== false && $softCut > 28) {
        $lines[] = trim(mb_substr($text, 0, $softCut, 'UTF-8'));
        $text = trim(mb_substr($text, $softCut, null, 'UTF-8'));
      } else {
        $lines[] = trim($cut);
        $text = trim(mb_substr($text, $limit, null, 'UTF-8'));
      }
    }
  }

  if ($text !== '') {
    $lines[] = $text;
  }
  return $lines;
}

/* --------------------------------------------------
   Mapping ตัวแปรหลักจาก document_values
   ใช้ field_id จาก Request_3.php / save_memo.php
-------------------------------------------------- */
$hasSavedDocDateField = array_key_exists(1, $valueMap);
$docDate = $hasSavedDocDateField ? trim((string)($valueMap[1] ?? '')) : trim((string)($document['doc_date'] ?? ""));
$ownerName = $valueMap[2] ?? "";
$position = $valueMap[3] ?? "";

/* --------------------------------------------------
   ลายเซ็นท้ายเอกสาร
   ใช้ชื่อและตำแหน่งของผู้จัดทำเอกสารจากข้อมูลที่บันทึกไว้
-------------------------------------------------- */
$signatureName = trim((string)$ownerName);
$signaturePosition = trim((string)$position);

if ($signatureName === "") {
  $signatureName = "................................";
}

if ($signaturePosition === "") {
  $signaturePosition = "................................";
}


$faculty = $valueMap[10] ?? "";
$department = $valueMap[11] ?? "";
$displayFaculty = trim($faculty) !== '' ? trim($faculty) : "คณะเทคโนโลยีและการจัดการอุตสาหกรรม";
$displayDepartment = trim($department) !== '' ? trim($department) : "เทคโนโลยีสารสนเทศ";
$displayDepartmentFull = "ภาควิชา" . $displayDepartment;
$displayFacultyDean = "คณบดี" . $displayFaculty;

$deanName = "";
$deanFacultyName = $displayFaculty;

/* --------------------------------------------------
   ข้อมูลคณบดีตามคณะในบรรทัดส่วนราชการ
-------------------------------------------------- */
try {
  if (trim((string)$displayFaculty) !== "") {
    $deanStmt = $pdo->prepare("
      SELECT faculty_name, dean_name
      FROM faculties
      WHERE faculty_name = :faculty
      LIMIT 1
    ");
    $deanStmt->execute([':faculty' => trim((string)$displayFaculty)]);
    $deanRow = $deanStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $deanFacultyName = trim((string)($deanRow['faculty_name'] ?? $deanFacultyName));
    $deanName = trim((string)($deanRow['dean_name'] ?? ""));
  }
} catch (Throwable $e) {
  $deanName = "";
}

$deanToText = "คณบดี" . ($deanFacultyName ?: ($displayFaculty ?: "คณะ.................................."));
if ($deanName === "") {
  $deanName = "................................";
}

$deanPosition = $deanToText;
$displayFacultyDean = $deanToText;

$departmentPhone = "";
$toPerson = $valueMap[26] ?? "ประธานคณะกรรมการบ้านพัก มจพ. วิทยาเขตปราจีนบุรี";
$roomRequest = $valueMap[27] ?? "";
$roomRequestOther = $valueMap[28] ?? "";
$guestFullname = $valueMap[29] ?? "";
$personType = $valueMap[30] ?? "";
$personTypeOther = $valueMap[31] ?? "";
$reason = $valueMap[32] ?? "";
$reasonOther = $valueMap[33] ?? "";
$dateOption = $valueMap[34] ?? "single";
$singleDate = $valueMap[35] ?? "";
$rangeDate = $valueMap[36] ?? "";
$roomType = $valueMap[37] ?? "";

$roomRequestText = (trim($roomRequest) === "อื่น ๆ" && trim($roomRequestOther) !== "") ? $roomRequestOther : $roomRequest;
$personTypeText = (trim($personType) === "อื่น ๆ" && trim($personTypeOther) !== "") ? $personTypeOther : $personType;
$reasonText = (trim($reason) === "อื่น ๆ" && trim($reasonOther) !== "") ? $reasonOther : $reason;
$rawStayDateText = (trim($dateOption) === "range" && trim($rangeDate) !== "") ? $rangeDate : $singleDate;
$stayDateText = thai_date_range_flexible($rawStayDateText);

/* --------------------------------------------------
   ⭐⭐⭐ สำคัญที่สุด — แก้ให้ส่วนหัวขึ้น ⭐⭐⭐
-------------------------------------------------- */

$header_text = $document["header_text"] ?? "";
$doc_no = $document["doc_no"] ?? "";
$subject = $document["subject"] ?? "";

/* ===== ชื่อไฟล์ดาวน์โหลดภาษาไทย (ใช้ชื่อเดียวกับบรรทัด "เรื่อง") ===== */
$downloadSubject = 'ขออนุมัติใช้ห้องพักรับรอง' . (trim($roomRequestText) !== "" ? "สำหรับ" . trim($roomRequestText) : "");
$downloadSubject = preg_replace('/[\\\\\/\:\*\?\"\<\>\|\r\n\t]+/u', ' ', $downloadSubject);
$downloadSubject = preg_replace('/\s+/u', ' ', $downloadSubject);
$downloadSubject = trim($downloadSubject);

if ($downloadSubject === '') {
  $downloadSubject = 'ขออนุมัติใช้ห้องพักรับรอง';
}

if (function_exists('mb_strlen') && mb_strlen($downloadSubject, 'UTF-8') > 80) {
  $downloadSubject = mb_substr($downloadSubject, 0, 80, 'UTF-8');
}

$downloadBaseName = $downloadSubject . '_เลขที่_' . (int) $docId;
$pdfDownloadName = $downloadBaseName . '.pdf';
$wordDownloadName = $downloadBaseName . '.docx';



/* --------------------------------------------------
   คำนวณวันที่ไทย, งบประมาณ
-------------------------------------------------- */
$thaiDocDate = thai_date($docDate);
$prettyAmount = "";

/* --------------------------------------------------
   สร้างข้อความส่วนหัวที่ใช้ในเนื้อหา
-------------------------------------------------- */
$rawHeaderText = trim((string)$header_text);

if ($rawHeaderText !== "" && preg_match('/โทร\.?\s*([0-9๐-๙\-\s]+)/u', $rawHeaderText, $mPhone)) {
  $departmentPhone = trim(arabic_digits($mPhone[1]));
}

$headerWithoutPhone = trim(preg_replace('/\s*โทร\.?\s*[0-9๐-๙\-\s]+$/u', '', $rawHeaderText));

// ถ้าเอกสารเก่าบันทึก header_text มาโดยไม่มีเบอร์ ให้เติมเบอร์คณะกลับเข้าไป
// ไม่ยุ่งกับ layout อื่น และยังคงเว้นวรรค "คณะ ... ภาควิชา ... โทร. ..." ตามเดิม
if ($departmentPhone === "") {
  $departmentPhone = "7064";
}

if ($headerWithoutPhone !== "") {
  $hdr_agency = trim($headerWithoutPhone . " โทร. " . $departmentPhone);
} else {
  $hdr_agency = trim($displayFaculty . " " . $displayDepartmentFull . " โทร. " . $departmentPhone);
}

$hdr_agency = arabic_digits($hdr_agency);

$hdr_subject = "ขออนุมัติใช้ห้องพักรับรอง";
$hdr_to = $toPerson ?: "ประธานคณะกรรมการบ้านพัก มจพ. วิทยาเขตปราจีนบุรี";

/* --------------------------------------------------
   ปีไทย
-------------------------------------------------- */
$thaiYear = "";
if ($docDate && preg_match('/^\d{4}/', $docDate)) {
  $thaiYear = ((int) substr($docDate, 0, 4) + 543);
}

/* --------------------------------------------------
   ความกว้างของช่อง “เรื่อง”
-------------------------------------------------- */
$len = mb_strlen($subject, "UTF-8");
$len = max(20, $len);

?>



<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>บันทึกข้อความ #<?= h($document['document_id']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>


  <style>
  @import url("https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap");

  html,
  body {
    margin: 0;
    background: #f3f4f6;
    font-family: "TH SarabunPSK", sans-serif;
  }

  .page {
    width: 794px;
    min-height: 1123px;
    margin: 40px auto;
    /* ให้ขนาด A4 รวม padding แล้ว ไม่บาน/ไม่เพี้ยนตอน html2canvas ทำ PDF */
    box-sizing: border-box;
    padding: 60px 70px 50px 115px;
    background: #fff;
    box-shadow: 0 0 5px rgba(0, 0, 0, .1);
    position: relative;
    border: 2px solid #fff;
  }

  h1 {
    font-family: "TH SarabunPSK";
    font-size: 29pt;
    font-weight: bold;
    text-align: center;
    line-height: 1.2;
    margin-bottom: 1.5em;
  }

  .doc-title {
    margin-left: -30px;
  }

  .doc-row {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
    flex-wrap: nowrap;
  }

  .doc-label {
    margin-right: 2px;
  }

  /* .dot-line { flex: 1; display: flex; align-items: flex-end; height: 22px; margin: 0; position: relative; } .doc-line { display: flex; align-items: center; } */
  .doc-spacer {
    display: inline-block;
    width: 2.5cm;
    /* ← ขนาดช่องว่าง ปรับตรงนี้ */
  }

  /* .dot-line::after { content: ""; position: absolute; left: 0; right: 0; bottom: 2px; height: 2px; background-image: radial-gradient(circle, #000 1px, transparent 1px); background-size: 6px 2px; background-repeat: repeat-x; } */
  .dot-input {
    border: none;
    background: transparent;
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    line-height: 1.0;
    padding: 0 1px;
    margin: 0;
    min-width: 30px;
    max-width: 100%;
    box-sizing: border-box;
    position: relative;
    z-index: 1;
  }

  .dot-input.box {
    border: 1px solid #000;
    background: #fff;
    padding: 0 4px;
    height: 24px;
    margin: 0;
  }

  .dot-input.box.full {
    width: 100%;
    box-sizing: border-box;
  }

  .content-block {
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    line-height: 1.0;
    margin: 0;
    text-align: justify;
    text-justify: inter-word;
  }

  .content-block.paragraph {
    text-indent: 2.5cm;
    margin-top: 0.5em;
    line-height: 1.3;
  }

  .content-block.single {
    line-height: 1.0;
  }

  .content-block.indent-first {
    text-indent: 2.5cm;
    display: block;
  }

  /* SweetAlert */
  .swal2-popup {
    font-size: 1rem !important;
    font-family: 'Arial', sans-serif !important;
  }

  .swal2-title {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
  }

  .swal2-html-container {
    font-size: 1rem !important;
  }

  .indent-block {
    margin-left: 2.5cm;
    text-align: left;
    font-family: 'TH SarabunPSK';
    font-size: 16pt;
    line-height: 1.2;
  }

  .chip {
    display: inline;
    padding: 0;
    margin: 0;
    border: none !important;
    outline: none !important;
    background: transparent !important;
    box-shadow: none !important;
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    line-height: 1em;
    white-space: nowrap;
    vertical-align: baseline;
  }

  .keep {
    white-space: nowrap;
  }


  .memo-title-row {
    position: relative;
    height: 1.65cm;
    margin-bottom: 0.35em;
  }

  .memo-title-row .garuda-img {
    position: absolute;
    left: 0;
    top: 0.10cm;
    height: 1.6cm;
    width: auto;
  }

  .memo-title-row .doc-title {
    position: absolute;
    left: 0;
    right: 0;
    top: 0.42cm;
    margin: 0 !important;
    padding: 0 !important;
    font-family: "TH SarabunPSK";
    font-size: 30pt;
    font-weight: bold;
    line-height: 1 !important;
    text-align: center;
    transform: translateX(-0.3cm);
  }

  .signature-wrapper {
    display: flex;
    justify-content: center;
    margin-top: 2em;
  }

  .signature-block {
    margin-top: 50px;
    margin-left: 187px;
    text-align: center;
    font-family: 'TH SarabunPSK';
    font-size: 16pt;
    line-height: 1.2;
  }

  .sig-name {
    display: block;
    white-space: nowrap;
  }

  .sig-position {
    display: block;
    white-space: nowrap;
  }


  /* ===== บล็อกเสนอคณบดี: ใช้รูปแบบเดียวกับเอกสารบันทึกข้อความ ===== */
  .dean-approval-block {
    display: flex;
    align-items: flex-start;
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    font-weight: 400;
    line-height: 1.34 !important;
    margin-top: -0.15cm !important;
    margin-bottom: 6px !important;
    text-indent: 0 !important;
    text-align: left !important;
    text-align-last: left !important;
    word-spacing: normal !important;
    letter-spacing: normal !important;
    white-space: normal;
  }

  .dean-approval-label {
    width: 1.15cm;
    flex: 0 0 1.15cm;
  }

  .dean-approval-text {
    padding-left: 14px;
  }

  .dean-signature-wrapper {
    margin-top: 1.45cm;
    margin-bottom: 1em;
    margin-left: calc(1.15cm + 14px);
    text-align: left !important;
    font-family: "TH SarabunPSK", sans-serif !important;
    font-size: 16pt !important;
    font-weight: 400 !important;
    line-height: 1.15 !important;
  }

  .dean-signature-block {
    display: inline-block;
    width: max-content;
    text-align: center !important;
    margin: 0 !important;
    padding: 0 !important;
    transform: none !important;
    font-family: "TH SarabunPSK", sans-serif !important;
    font-size: 16pt !important;
    font-weight: 400 !important;
    line-height: 1.15 !important;
  }

  .dean-sig-name,
  .dean-sig-position {
    display: block;
    text-align: center !important;
    white-space: nowrap;
    margin: 0 !important;
    padding: 0 !important;
    font-family: "TH SarabunPSK", sans-serif !important;
    font-size: 16pt !important;
    font-weight: 400 !important;
    line-height: 1.15 !important;
  }

  .footer-actions {
    margin-top: 24px;
    padding-top: 16px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid #e5e7eb;
  }

  .dot-line {
    flex: 1;
    position: relative;
    height: 28px;
    display: flex;
    align-items: flex-end !important;
  }

  .dot-line::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: 4px;
    height: 2px;
    background-image: radial-gradient(circle, #000 1px, transparent 1px);
    background-size: 6px 2px;
    background-repeat: repeat-x;
  }

  /* ระยะว่างหน้าคำ + หลังคำ ตามรูป */
  .dot-line .chip {
    line-height: 0.9 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;

    margin-left: 14px !important;
    margin-right: 6px !important;

    display: inline-flex !important;
    align-items: flex-end !important;
    /* ดึงข้อความให้แตะเส้น */
    position: relative;
    top: 3px;
    /* ตำแหน่งพื้นฐานของข้อความบนเส้นประ */
  }

  /* ===== FIX: ส่วนราชการยาว ไม่ให้ตกบรรทัด และให้ข้อความอยู่บนเส้นประ ===== */
  .doc-row.gov-row {
    flex-wrap: nowrap !important;
    align-items: center !important;
  }

  .doc-row.gov-row>.doc-label {
    flex: 0 0 auto !important;
    white-space: nowrap !important;
    margin-right: 0 !important;
  }

  .doc-row.gov-row>.dot-line {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    overflow: visible !important;
  }

  .doc-row.gov-row>.dot-line>.chip.gov-text {
    display: inline-block !important;
    white-space: nowrap !important;
    margin-left: 14px !important;
    margin-right: 6px !important;
    padding-left: 6px !important;
    padding-right: 6px !important;
    transform: none !important;
    line-height: 0.9 !important;
    top: -1px !important;
  }

  .page.gov-header-compact .doc-row.gov-row>.dot-line>.chip.gov-text {
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
  }

  .page.gov-header-tight-margin {
    padding-left: 2.80cm !important;
    padding-right: 1.80cm !important;
  }

  .page.gov-header-tight-margin .doc-row.gov-row>.dot-line {
    padding-right: 0 !important;
  }

  .doc-row.subject-row {
    align-items: center !important;
    margin-bottom: 3px !important;
  }

  .doc-row.subject-row .dot-line .chip {
    white-space: nowrap !important;
    overflow-wrap: normal !important;
    word-break: keep-all !important;
  }

  .content-block.single .chip {
    margin-left: 22px;
  }

  /* สำหรับ print */
  @media print {

    header,
    .footer-actions {
      display: none !important;
    }

    body {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    .page {
      margin: 0;
      box-shadow: none;
      padding: 0.5cm 1cm 2cm 2.45cm !important;
      width: 21cm;
      min-height: 29.7cm;
      border: 2px solid #fff !important;
    }

    .dot-line::after {
      content: "";
      position: absolute;
      left: 0;
      right: 0;
      bottom: 2px;
      height: 2px;
      background-image: radial-gradient(circle, #000 0.6px, transparent 0.6px);
      background-size: 4px 2px;
      background-repeat: repeat-x;
    }

    .dot-input {
      border: none !important;
      background: transparent !important;
      outline: none !important;
      font-size: 16pt !important;
      line-height: 1.2 !important;
      padding: 0 !important;
      margin: 0 !important;
      height: auto !important;
      position: relative;
      top: 3px !important;
    }

    .chip {
      border: none !important;
      background: transparent !important;
      box-shadow: none !important;
    }
  }

  /* ฟอนต์ Sarabun */
  @font-face {
    font-family: 'TH SarabunPSK';
    src: url('../fonts/THSarabun.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
  }

  @font-face {
    font-family: 'TH SarabunPSK';
    src: url('../fonts/THSarabun-Bold.ttf') format('truetype');
    font-weight: bold;
    font-style: normal;
  }

  html,
  body,
  .page,
  .content-block,
  .chip,
  .dot-input,
  .subject-line,
  .signature-block {
    font-family: 'TH SarabunPSK', sans-serif !important;
  }

  /* ⭐⭐⭐ อันที่คุณย้ำว่าห้ามหาย — ใส่ให้อยู่ท้ายเหมือนเดิม ⭐⭐⭐ */
  .doc-header .doc-row {
    margin-bottom: 12px !important;
    /* เดิม 6px → เพิ่มเป็น 12px */
    line-height: 0.5 !important;
    /* เพิ่มความสูงบรรทัด */
  }

  /* ให้กล่อง (chip) ขยับออกจากคำ โดยเส้นยังติดกับคำ */
  /* ⭐ ขยับกล่องออกจากคำอีกนิด */
  .doc-row .dot-line .chip {
    margin-left: 14px !important;
    margin-right: 6px !important;
    padding-left: 6px !important;
    padding-right: 6px !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    position: relative;
    top: -3px;
  }


  .doc-row .doc-label {
    line-height: 1.0 !important;
    height: 32px !important;
    display: flex;
    align-items: flex-end;
  }

  /* ★ สำหรับบรรทัด "ที่ – วันที่" ให้เส้นประต่อกันสนิท */
  .row-ty-date .ty-left::after {
    margin-right: -13px !important;
    /* ดึงเส้นให้ต่อกับคำว่า “วันที่” */
  }

  .row-ty-date .ty-right::after {
    margin-left: -6px !important;
    /* ดึงเส้นให้ต่อจากเส้นฝั่งซ้าย */
  }

  /* ลดช่องว่างหลังกล่อง เพื่อไม่ให้เกิดรูเล็กๆ */
  .row-ty-date .chip {
    margin-right: 0px !important;
    margin-left: 12px !important;
    /* เว้นหลังคำว่า “ที่” พอดี */
  }

  /* เอาช่องว่างเล็กๆ หลังเลขเอกสารออก */
  .row-ty-date .ty-left .chip {
    margin-right: 0 !important;
  }

  /* ⭐ ขยับ "วันที่" ไปทางซ้าย */
  .row-ty-date .doc-label[style*="margin-left"] {
    margin-left: 0.2cm !important;
    /* ← จาก 1cm ลดเหลือ 0.6cm (ขยับซ้าย) */
  }

  .font-regular {
    font-family: 'Sarabun', sans-serif !important;
    font-weight: 20 !important;
  }

  .content-block,
  .chip {
    font-family: "TH SarabunPSK";
    font-size: 16pt !important;
    /* ← เทียบเท่า 16pt จริงใน Word */
    font-weight: 400 !important;
  }

  /* ===== PDF Loading Overlay ===== */
  .pdf-loading-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(255, 255, 255, 0.72);
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
  }

  .pdf-loading-box {
    min-width: 260px;
    padding: 28px 34px;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
    text-align: center;
    font-family: "TH SarabunPSK", sans-serif;
  }

  .pdf-spinner {
    width: 58px;
    height: 58px;
    margin: 0 auto 14px auto;
    border: 6px solid rgba(20, 184, 166, 0.18);
    border-top-color: #14b8a6;
    border-right-color: #14b8a6;
    border-radius: 50%;
    animation: pdfSpin 0.85s linear infinite;
  }

  .pdf-loading-title {
    color: #0f766e;
    font-size: 22pt;
    font-weight: bold;
    line-height: 1.1;
  }

  .pdf-loading-subtitle {
    margin-top: 4px;
    color: #475569;
    font-size: 16pt;
    line-height: 1.1;
  }

  @keyframes pdfSpin {
    from {
      transform: rotate(0deg);
    }

    to {
      transform: rotate(360deg);
    }
  }
  </style>
</head>

<body>
  <div id="pdfLoadingOverlay" class="pdf-loading-overlay">
    <div class="pdf-loading-box">
      <div class="pdf-spinner"></div>
      <div id="downloadLoadingTitle" class="pdf-loading-title">กำลังสร้าง PDF...</div>
      <div id="downloadLoadingSubtitle" class="pdf-loading-subtitle">กรุณารอสักครู่ ระบบกำลังเตรียมเอกสาร</div>
    </div>
  </div>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[contenteditable]").forEach(e => {
      e.setAttribute("contenteditable", "false");
      e.removeAttribute("data-target");
      e.style.background = "transparent";
      e.style.cursor = "default";
    });
  });
  </script>

  <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
  <div id="alertBox" class="bg-green-500 text-white px-4 py-2 rounded-md text-center mb-4 shadow-md">
    ✅ บันทึกสำเร็จ
  </div>
  <?php elseif (isset($_GET['err']) && $_GET['err'] == 'validate'): ?>
  <div id="alertBox" class="bg-red-500 text-white px-4 py-2 rounded-md text-center mb-4 shadow-md">
    ❌ กรุณากรอกข้อมูลให้ครบถ้วน
  </div>
  <?php elseif (isset($_GET['err']) && $_GET['err'] == 'server'): ?>
  <div id="alertBox" class="bg-red-600 text-white px-4 py-2 rounded-md text-center mb-4 shadow-md">
    ⚠️ เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง
  </div>
  <?php endif; ?>

  <main class="page">
    <form id="updateForm" action="update_memo.php" method="post">

      <input type="hidden" name="doc_no" id="hidden_doc_no" value="<?= h($doc_no) ?>">

      <!-- หน้านี้เป็นหน้าแสดงผลเท่านั้น ถ้าต้องการแก้ไขให้กลับไปหน้าแบบฟอร์ม Request_3.php -->
      <input type="hidden" name="redirect_back" value="<?= htmlspecialchars($referer) ?>">
      <input type="hidden" name="document_id" value="<?= h($document['document_id']) ?>">

      <!-- หัวบันทึก -->
      <div class="memo-title-row">
        <img src="/Pro_letter/assets/img/garuda.jpg" class="garuda-img" />
        <h1 class="doc-title">บันทึกข้อความ</h1>
      </div>

      <!-- ส่วนราชการ -->
      <div class="doc-row gov-row">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;">ส่วนราชการ</div>
        <div class="dot-line">
          <span class="chip gov-text" contenteditable="false" data-target="header_text">
            <?= h($hdr_agency ?: $header_text ?: 'คณะ... ภาควิชา... โทร...') ?>
          </span>
        </div>
      </div>

      <div class="doc-row row-ty-date">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;">ที่</div>

        <div class="dot-line ty-left">
          <span class="chip" contenteditable="false" data-target="doc_no">

          </span>
        </div>

        <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;">วันที่</div>

        <div class="dot-line ty-right">
          <span class="chip" contenteditable="false" data-target="doc_date_display">
            <?= h_thai_digits($thaiDocDate ?: '') ?>
          </span>
        </div>
      </div>


      <!-- เรื่อง -->
      <?php
        $roomHeaderSubjectText = 'ขออนุมัติใช้ห้องพักรับรอง' . (trim($roomRequestText) !== "" ? "สำหรับ" . arabic_digits($roomRequestText) : "");
        $roomHeaderSubjectLines = room_header_subject_lines($roomHeaderSubjectText, 74);
      ?>
      <?php foreach ($roomHeaderSubjectLines as $idx => $roomHeaderSubjectLine): ?>
      <div class="doc-row subject-row">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;<?= $idx === 0 ? '' : 'visibility:hidden;' ?>">
          เรื่อง</div>
        <div class="dot-line">
          <span class="chip" contenteditable="false">
            <?= h($roomHeaderSubjectLine) ?>
          </span>
        </div>
      </div>
      <?php endforeach; ?>


      <!-- บรรทัด “เรียน ...” -->
      <div class="content-block single">
        เรียน
        <span class="chip" contenteditable="false">
          <?= h_thai_digits($displayFacultyDean) ?>
        </span>
      </div>

      <!-- ย่อหน้า 1 -->
      <div class="content-block paragraph">
        ตามที่ <?= h_thai_digits($displayDepartmentFull) ?> <?= h_thai_digits($displayFaculty) ?>
        มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
        มีความประสงค์ขออนุมัติใช้ห้องพักรับรองสำหรับ
        <?= h_thai_digits($roomRequestText ?: "................................") ?>
        ให้แก่
        <?= h_thai_digits($guestFullname ?: "................................") ?>
        ซึ่งเป็น
        <?= h_thai_digits($personTypeText ?: "................................") ?>
        ในระหว่างวันที่
        <?= h_thai_digits($stayDateText ?: "................................") ?>
        นั้น
      </div>

      <!-- ย่อหน้า 2 -->
      <div class="content-block paragraph">
        ในการนี้ <?= h_thai_digits($displayDepartmentFull) ?>
        จึงมีความประสงค์ขออนุมัติใช้ห้องพักรับรอง
        ณ
        <?= h_thai_digits($roomType ?: "................................") ?>
        ให้แก่
        <?= h_thai_digits($guestFullname ?: "................................") ?>
        ทั้งนี้เพื่อ
        <?= h_thai_digits($reasonText ?: "................................") ?>
        รายละเอียดตามเอกสารแนบท้าย
      </div>

      <!-- ย่อหน้า 3 -->
      <div class="content-block paragraph">
        จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ
      </div>



      <div class="signature-wrapper">
        <div class="signature-block" id="signatureBlock">
          <div class="sig-name">(<?= h_thai_digits($signatureName) ?>)</div>
          <div class="sig-position"><?= h_thai_digits($signaturePosition) ?></div>
        </div>
      </div>

      <div class="dean-approval-block">
        <div class="dean-approval-label">เรียน</div>
        <div class="dean-approval-text">
          <?= h_thai_digits($deanToText) ?><br>
          เพื่อโปรดพิจารณาอนุมัติ
        </div>
      </div>

      <div class="dean-signature-wrapper">
        <div class="dean-signature-block">
          <div class="dean-sig-name">(<?= h_thai_digits($deanName) ?>)</div>
          <div class="dean-sig-position"><?= h_thai_digits($deanPosition) ?></div>
        </div>
      </div>


      <div class="footer-actions">

        <!-- ปุ่มดาวน์โหลด PDF -->
        <button type="button" onclick="downloadPdf()"
          class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
          ดาวน์โหลด PDF
        </button>

        <!-- ปุ่มดาวน์โหลด Word -->
        <a href="/Pro_letter/documents/download_word_room_request.php?id=<?= (int)$docId ?>" data-word-download="1"
          data-word-filename="<?= h($wordDownloadName) ?>" download="<?= h($wordDownloadName) ?>"
          onclick="return downloadWord(this);"
          class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
          ดาวน์โหลด Word
        </a>

        <!-- ปุ่มแก้ไขเอกสาร -->
        <?php if ($canEdit): ?>
        <a href="/Pro_letter/documents/infor_room_request.php?id=<?= (int)$docId ?>&edit=1"
          class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
          แก้ไขเอกสาร
        </a>
        <?php else: ?>
        <button type="button"
          class="bg-gray-300 text-gray-600 cursor-not-allowed px-6 py-2 rounded-md text-xl font-bold inline-block opacity-80"
          title="<?= h($editAlertText ?: 'ไม่สามารถแก้ไขเอกสารนี้ได้') ?>" disabled>
          แก้ไขเอกสาร
        </button>
        <?php endif; ?>



        <!-- ปุ่มกลับหน้าหลัก -->
        <a href="<?= $homePath ?>"
          class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          กลับหน้าหลัก
        </a>

      </div>
    </form>
  </main>


  <script>
  const alertBox = document.getElementById('alertBox');
  if (alertBox) {
    setTimeout(() => {
      alertBox.style.transition = "opacity 0.5s ease";
      alertBox.style.opacity = 0;
      setTimeout(() => alertBox.remove(), 500);
    }, 3000); // ซ่อนหลัง 3 วินาที
  }

  function getQuery(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }

  document.addEventListener("DOMContentLoaded", () => {
    const errType = getQuery("err");


    if (["no_permission", "submitted", "checked"].includes(errType)) {
      const alertMap = {
        no_permission: {
          title: "ไม่มีสิทธิ์แก้ไขเอกสาร",
          html: `<div style="font-size: 1.15rem; line-height: 1.6;">
        คุณไม่มีสิทธิ์แก้ไขเอกสารนี้<br>
        กรุณาติดต่อผู้ดูแลระบบหากต้องการแก้ไข
      </div>`,
          icon: "warning"
        },
        submitted: {
          title: "เอกสารอยู่ระหว่างรอตรวจสอบ",
          html: `<div style="font-size: 1.15rem; line-height: 1.6;">
        เอกสารนี้ถูกส่งเข้าสู่การตรวจสอบแล้ว<br>
        จึงไม่สามารถแก้ไขได้ในขณะนี้
      </div>`,
          icon: "info"
        },
        checked: {
          title: "เอกสารผ่านการตรวจสอบแล้ว",
          html: `<div style="font-size: 1.15rem; line-height: 1.6;">
        เอกสารนี้ผ่านการตรวจสอบแล้ว<br>
        จึงไม่สามารถแก้ไขได้
      </div>`,
          icon: "success"
        }
      };

      const alertInfo = alertMap[errType];

      Swal.fire({
        title: alertInfo.title,
        html: alertInfo.html,
        icon: alertInfo.icon,
        confirmButtonText: "ตกลง",
        confirmButtonColor: "#14b8a6"
      });
    }
  });


  document.addEventListener("DOMContentLoaded", () => {
    if (getQuery("saved") === "1" && getQuery("from") === "update") {
      Swal.fire({
        title: "บันทึกสำเร็จ",
        text: "คุณต้องการกลับไปที่หน้าหลักหรือไม่?",
        icon: "success",
        showCancelButton: true,
        confirmButtonText: "กลับหน้าหลัก",
        cancelButtonText: "อยู่หน้านี้ต่อ",
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#aaa",
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = "<?= $homePath ?>";

        }
      });
    }
  });

  document.querySelectorAll('.editable[contenteditable], .chip[contenteditable]').forEach(el => {
    el.addEventListener('keydown', e => {
      if (e.key === 'Enter') e.preventDefault();
    });
    el.addEventListener('paste', e => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\r?\n/g,
        ' ');
      document.execCommand('insertText', false, text);
    });
  });
  (function() {
    const box = document.getElementById('signatureBlock');
    if (!box) return;
    const nameEl = box.querySelector('.sig-name');
    // กำหนดความกว้างกล่อง = ความกว้างบรรทัดชื่อ -> ตำแหน่งจะกึ่งกลางใต้ชื่อพอดี
    box.style.width = nameEl.offsetWidth + 'px';
  })();


  function downloadWord(link) {
    const loadingOverlay = document.getElementById("pdfLoadingOverlay");
    const loadingTitle = document.getElementById("downloadLoadingTitle");
    const loadingSubtitle = document.getElementById("downloadLoadingSubtitle");
    const wordLinks = document.querySelectorAll("a[data-word-download='1']");

    if (loadingTitle) loadingTitle.innerText = "กำลังดาวน์โหลด Word...";
    if (loadingSubtitle) loadingSubtitle.innerText = "กรุณารอสักครู่ ระบบกำลังเตรียมเอกสาร";

    if (loadingOverlay) {
      loadingOverlay.style.display = "flex";
    }

    wordLinks.forEach(btn => {
      if (!btn.dataset.oldText) {
        btn.dataset.oldText = btn.innerText;
      }

      btn.innerText = "กำลังดาวน์โหลด Word...";
      btn.style.opacity = "0.65";
      btn.style.cursor = "wait";
    });

    const resetWordDownloadUI = () => {
      if (loadingOverlay) {
        loadingOverlay.style.display = "none";
      }

      wordLinks.forEach(btn => {
        btn.innerText = btn.dataset.oldText || "ดาวน์โหลด Word";
        btn.style.opacity = "1";
        btn.style.removeProperty("cursor");
      });
    };

    const downloadUrl = new URL(link.href, window.location.href);
    downloadUrl.searchParams.set("_download_time", Date.now().toString());
    const fileName = link.dataset.wordFilename || link.getAttribute("download") || "เอกสาร.docx";

    fetch(downloadUrl.toString(), {
        method: "GET",
        credentials: "same-origin"
      })
      .then(response => {
        if (!response.ok) {
          throw new Error("download failed");
        }
        return response.blob();
      })
      .then(blob => {
        const blobUrl = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = blobUrl;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(blobUrl);
      })
      .catch(error => {
        console.error(error);
        alert("ดาวน์โหลด Word ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง");
      })
      .finally(resetWordDownloadUI);

    return false;
  }

  function normalizeRoomGovHeaderText(text) {
    return String(text || "")
      .replace(/[๐๑๒๓๔๕๖๗๘๙]/g, (d) => ({
        "๐": "0",
        "๑": "1",
        "๒": "2",
        "๓": "3",
        "๔": "4",
        "๕": "5",
        "๖": "6",
        "๗": "7",
        "๘": "8",
        "๙": "9"
      } [d] || d));
  }

  function removeRoomGovHeaderSpaces(text) {
    return String(text || "").replace(/\s+/g, "");
  }

  function applyRoomGovHeaderFit(root = document) {
    const page = root.querySelector?.(".page") || root;
    const govChip = root.querySelector?.(".gov-text");
    const govLine = govChip?.closest(".dot-line");
    if (!govChip || !govLine) return;

    if (!govChip.dataset.originalGovText) {
      govChip.dataset.originalGovText = normalizeRoomGovHeaderText(govChip.textContent).trim();
    }

    const originalText = govChip.dataset.originalGovText;
    const compactText = removeRoomGovHeaderSpaces(originalText);

    const fits = () => {
      const textWidth = Math.ceil(govChip.getBoundingClientRect().width || govChip.scrollWidth);
      const lineWidth = Math.floor(govLine.getBoundingClientRect().width || govLine.clientWidth);
      return lineWidth && textWidth <= lineWidth + 1;
    };

    // ค่าเริ่มต้น: ถ้าไม่เกินเส้น ห้ามลดฟอนต์ ห้ามลบช่องว่าง
    govChip.textContent = originalText;
    govChip.style.setProperty("font-size", "16pt", "important");
    govChip.style.removeProperty("letter-spacing");
    page?.classList?.remove("gov-header-tight-margin");
    page?.classList?.remove("gov-header-compact");

    // ขั้นที่ 1: ลดฟอนต์ทีละระดับโดยยังคงช่องว่างเดิมไว้ก่อน
    for (const size of [16, 15, 14]) {
      govChip.style.setProperty("font-size", size + "pt", "important");
      if (fits()) return;
    }

    // ขั้นที่ 2: ถ้า 14pt แล้วยังเกินเส้นจริง ๆ ค่อยลบช่องว่าง
    page?.classList?.add("gov-header-compact");
    govChip.textContent = compactText;
    govChip.style.setProperty("font-size", "14pt", "important");
    if (fits()) return;

    // ขั้นที่ 3: ถ้ายังเกินจริง ๆ ค่อยขยับขอบเฉพาะตอนเกินเส้น
    page?.classList?.add("gov-header-tight-margin");
    void page?.offsetWidth;
    if (fits()) return;

    // กันเคสยาวมาก โดยไม่ลดฟอนต์ต่ำกว่า 14pt
    govChip.style.setProperty("letter-spacing", "-0.25px", "important");
  }


  window.addEventListener("load", () => applyRoomGovHeaderFit(document));

  async function downloadPdf() {
    const loadingOverlay = document.getElementById("pdfLoadingOverlay");
    const downloadButtons = document.querySelectorAll("button[onclick='downloadPdf()']");

    if (loadingOverlay) {
      loadingOverlay.style.display = "flex";
    }

    downloadButtons.forEach(btn => {
      btn.disabled = true;
      btn.dataset.oldText = btn.innerText;
      btn.innerText = "กำลังสร้าง PDF...";
      btn.style.opacity = "0.65";
      btn.style.cursor = "not-allowed";
    });

    try {
      const {
        jsPDF
      } = window.jspdf;
      const pages = document.querySelectorAll(".page");

      if (!pages.length) {
        alert("ไม่พบหน้าเอกสาร .page");
        return;
      }

      const pdf = new jsPDF({
        orientation: "portrait",
        unit: "mm",
        format: "a4"
      });

      for (let i = 0; i < pages.length; i++) {
        const clone = pages[i].cloneNode(true);
        clone.classList.add("print-mode");

        clone.style.position = "fixed";
        clone.style.left = "-9999px";
        clone.style.top = "0";
        clone.style.width = "794px";
        clone.style.minHeight = "1123px";
        clone.style.boxSizing = "border-box";
        clone.style.background = "#ffffff";
        clone.style.boxShadow = "none";
        clone.style.margin = "0";

        clone.querySelectorAll(".footer-actions").forEach(el => el.remove());
        clone.querySelectorAll("input[type='hidden']").forEach(el => el.remove());

        // ห้ามขยับหัวเอกสารตอน export PDF เพราะจะทำให้ตำแหน่ง
        // "บันทึกข้อความ" ไม่ตรงกับหน้าปกติ
        const cloneGaruda = clone.querySelector(".garuda-img");
        if (cloneGaruda) {
          cloneGaruda.style.transform = "none";
          cloneGaruda.style.top = "0.10cm";
        }

        const cloneTitle = clone.querySelector(".doc-title");
        if (cloneTitle) {
          cloneTitle.style.top = "0.42cm";
          cloneTitle.style.transform = "translateX(-0.3cm)";
        }

        const cloneTitleRow = clone.querySelector(".memo-title-row");
        if (cloneTitleRow) {
          cloneTitleRow.style.height = "1.65cm";
          cloneTitleRow.style.marginBottom = "0.35em";
        }

        clone.querySelectorAll(".dot-line").forEach(line => {
          line.querySelectorAll(".pdf-real-dot-line").forEach(el => el.remove());

          line.style.position = "relative";
          line.style.overflow = "visible";
          line.style.backgroundImage = "none";

          const dot = document.createElement("div");
          dot.className = "pdf-real-dot-line";
          dot.style.position = "absolute";
          dot.style.left = "0";
          dot.style.right = "0";
          dot.style.bottom = "-12px";
          dot.style.height = "0";
          dot.style.zIndex = "1";
          dot.style.pointerEvents = "none";
          dot.style.borderTop = "2px dotted #000";

          line.prepend(dot);
        });

        clone.querySelectorAll(".dot-line .chip").forEach(chip => {
          chip.style.position = "relative";
          chip.style.zIndex = "3";
          chip.style.background = "transparent";
          // ขยับข้อความขึ้นจากเส้นประเล็กน้อย เส้นประยังอยู่ตำแหน่งเดิม
          chip.style.top = "0px";
        });

        document.body.appendChild(clone);
        applyRoomGovHeaderFit(clone);

        const PDF_SCALE = 2.2; // จุดสมดุล: เร็วขึ้น แต่ยังไม่ฟุ้งแบบ JPEG

        // รอให้ฟอนต์โหลดก่อน ช่วยลดอาการตัวหนังสือฟุ้ง/เพี้ยน
        if (document.fonts && document.fonts.ready) {
          await document.fonts.ready;
        }

        const canvas = await html2canvas(clone, {
          scale: PDF_SCALE,
          useCORS: true,
          allowTaint: true,
          backgroundColor: "#ffffff",
          windowWidth: 794,
          windowHeight: 1123,
          scrollX: 0,
          scrollY: 0,
          logging: false,
          imageTimeout: 10000,
          removeContainer: true
        });

        document.body.removeChild(clone);

        // ใช้ PNG เพราะเอกสารราชการมีตัวหนังสือ/เส้นประเยอะ ถ้าใช้ JPEG จะฟุ้ง
        const imgData = canvas.toDataURL("image/png");

        if (i > 0) {
          pdf.addPage();
        }

        // FAST เร็วกว่า MEDIUM/SLOW และความคมของภาพยังคงดีกว่า JPEG
        pdf.addImage(imgData, "PNG", 0, 0, 210, 297, undefined, "FAST");
      }

      const pdfFileName = <?= json_encode($pdfDownloadName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      pdf.save(pdfFileName);
    } catch (error) {
      console.error(error);
      alert("สร้าง PDF ไม่สำเร็จ กรุณากด F12 ดู Console");
    } finally {
      if (loadingOverlay) {
        loadingOverlay.style.display = "none";
      }

      downloadButtons.forEach(btn => {
        btn.disabled = false;
        btn.innerText = btn.dataset.oldText || "ดาวน์โหลด PDF";
        btn.style.opacity = "1";
        btn.style.cursor = "pointer";
      });
    }
  }
  </script>
</body>


</html>