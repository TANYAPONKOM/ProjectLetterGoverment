<?php //หนังสือเรียนเชิญวิทยากร  Pro_letter/form_memo/form_memo_invite_speaker.php
session_start();
require_once __DIR__ . '/../functions.php';

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
   เงื่อนไขแก้ไขเอกสารจากสถานะเท่านั้น
-------------------------------------------------- */
$roleId = (int)($_SESSION['role_id'] ?? $_SESSION['role'] ?? 0);
$role = strtolower(trim((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? 'user')));

$isAdmin = ($roleId === 1 || in_array($role, ['admin', 'administrator', 'ผู้ดูแลระบบ'], true));
$isOfficer = ($roleId === 2 || in_array($role, ['officer', 'เจ้าหน้าที่'], true));

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
} elseif ($isSubmittedStatus) {
  $editDisabledReason = 'submitted';
  $editAlertTitle = 'เอกสารอยู่ระหว่างรอตรวจสอบ';
  $editAlertText = 'เอกสารนี้ถูกส่งเข้าสู่การตรวจสอบแล้ว จึงไม่สามารถแก้ไขได้ในขณะนี้';
  $editAlertIcon = 'info';
}

/*
  เงื่อนไขปุ่มแก้ไข
  - รอตรวจสอบ: แก้ไม่ได้
  - ผ่านการตรวจสอบแล้ว: แก้ไม่ได้
  - สถานะอื่น: แก้ไขได้
*/
if ($isCheckedStatus) {
  $canEdit = false;
} elseif ($isSubmittedStatus) {
  $canEdit = ($isAdmin || $isOfficer);
} else {
  $canEdit = true;
}


$readonly = !$canEdit;

/* --------------------------------------------------
   กล่องความคิดเห็นผู้ตรวจเอกสาร
-------------------------------------------------- */
$reviewStatuses = ['rejected', 'รอแก้เอกสาร', 'รอแก้ไข', 'ไม่ผ่านการตรวจสอบ'];

$canWriteReviewComment = ($isAdmin || $isOfficer);
$canReadReviewComment = $canWriteReviewComment || ($isOwner && in_array($docStatus, $reviewStatuses, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_review_comment') {
  if (!$canWriteReviewComment) {
    http_response_code(403);
    exit('Forbidden');
  }

  if ($userId <= 0) {
    http_response_code(403);
    exit('ไม่พบข้อมูลผู้ใช้งาน');
  }

  $reviewComment = trim((string)($_POST['review_comment'] ?? ''));
  $reviewComment = preg_replace('/\r\n|\r|\n/u', "\n", $reviewComment);
  $reviewComment = preg_replace('/[ \t]+/u', ' ', $reviewComment);

  if ($reviewComment === '') {
    header("Location: form_memo_invite_speaker.php?id=" . (int)$docId . "&comment_err=empty");
    exit;
  }

  if (mb_strlen($reviewComment, 'UTF-8') > 1000) {
    $reviewComment = mb_substr($reviewComment, 0, 1000, 'UTF-8');
  }

  $commentLog = $pdo->prepare("
    INSERT INTO audit_logs (user_id, document_id, action, detail)
    VALUES (:user_id, :document_id, 'REVIEW_COMMENT', :detail)
  ");
  $commentLog->execute([
    ':user_id' => $userId,
    ':document_id' => $docId,
    ':detail' => $reviewComment
  ]);

  header("Location: form_memo_invite_speaker.php?id=" . (int)$docId . "&comment_saved=1");
  exit;
}

$lastReviewComment = '';
if ($canReadReviewComment) {
  $lastCommentStmt = $pdo->prepare("
    SELECT detail
    FROM audit_logs
    WHERE document_id = :document_id
      AND action = 'REVIEW_COMMENT'
      AND detail IS NOT NULL
      AND TRIM(detail) <> ''
    ORDER BY created_at DESC, log_id DESC
    LIMIT 1
  ");
  $lastCommentStmt->execute([':document_id' => $docId]);
  $lastReviewComment = trim((string)($lastCommentStmt->fetchColumn() ?: ''));
}

$reviewCommentTextareaValue = $lastReviewComment;
if ($canWriteReviewComment && $isSubmittedStatus) {
  $reviewCommentTextareaValue = '';
}


/* --------------------------------------------------
   ดึงค่า field จาก document_values
-------------------------------------------------- */
$q = $pdo->prepare("
  SELECT 
    dv.field_id,
    dv.value_text,
    tf.field_key
  FROM document_values dv
  LEFT JOIN template_fields tf ON tf.field_id = dv.field_id
  WHERE dv.document_id = :id
");
$q->execute([':id' => $docId]);

$valueMap = [];
$valueMapByKey = [];

foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $fid = (int) ($row['field_id'] ?? 0);
  $val = (string) ($row['value_text'] ?? '');

  if ($fid > 0) {
    $valueMap[$fid] = $val;
  }

  $key = trim((string) ($row['field_key'] ?? ''));
  if ($key !== '') {
    $valueMapByKey[$key] = $val;
  }
}

/* --------------------------------------------------
   ฟังก์ชัน helper
-------------------------------------------------- */
// function h($s)
// {
//   return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
// }

function thai_digit($text)
{
  return strtr((string) $text, [
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

function arabic_digit($text)
{
  return strtr((string) $text, [
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

function h_thai($text)
{
  return h(thai_digit($text));
}

function thai_months()
{
  return [
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
    12 => "ธันวาคม",
  ];
}

function thai_date_from_parts($year, $month, $day, $withWeekday = false)
{
  $year = (int) $year;
  $month = (int) $month;
  $day = (int) $day;

  if ($year > 2400) {
    $christYear = $year - 543;
    $thaiYear = $year;
  } else {
    $christYear = $year;
    $thaiYear = $year + 543;
  }

  if (!checkdate($month, $day, $christYear)) {
    return "";
  }

  $months = thai_months();
  $dateText = thai_digit($day) . " " . $months[$month] . " " . thai_digit($thaiYear);

  if ($withWeekday) {
    $weekdays = [
      "อาทิตย์",
      "จันทร์",
      "อังคาร",
      "พุธ",
      "พฤหัสบดี",
      "ศุกร์",
      "เสาร์",
    ];
    $w = (int) date('w', strtotime(sprintf('%04d-%02d-%02d', $christYear, $month, $day)));
    return "วัน" . $weekdays[$w] . "ที่ " . $dateText;
  }

  return $dateText;
}

function thai_date($rawDate)
{
  return thai_date_any($rawDate, false);
}

function thai_date_with_weekday($rawDate)
{
  return thai_date_any($rawDate, true);
}

function thai_date_any($rawDate, $withWeekday = false)
{
  $rawDate = trim((string) $rawDate);
  if ($rawDate === '') {
    return "";
  }

  if ($withWeekday && preg_match('/^วัน/u', $rawDate)) {
    return thai_digit($rawDate);
  }

  $date = arabic_digit($rawDate);

  if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
    return thai_date_from_parts($m[1], $m[2], $m[3], $withWeekday);
  }

  if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) {
    return thai_date_from_parts($m[3], $m[2], $m[1], $withWeekday);
  }

  $months = thai_months();
  $monthRegex = implode('|', array_map('preg_quote', $months));

  if (preg_match('/(\d{1,2})\s+(' . $monthRegex . ')\s+(\d{4})/u', $date, $m)) {
    $monthNumber = array_search($m[2], $months, true);
    if ($monthNumber !== false) {
      return thai_date_from_parts($m[3], $monthNumber, $m[1], $withWeekday);
    }
  }

  return thai_digit($rawDate);
}

function format_thai_time_range($timeText)
{
  $timeText = trim((string) $timeText);
  if ($timeText === '') {
    return "";
  }

  $timeText = str_replace(':', '.', $timeText);
  $timeText = preg_replace('/\s*-\s*/', ' - ', $timeText);

  return thai_digit($timeText);
}

/* --------------------------------------------------
   Mapping ตัวแปรหลักจาก document_values
-------------------------------------------------- */
$hasSavedDocDateField = array_key_exists(1, $valueMap) || array_key_exists('doc_date', $valueMapByKey);
$docDate = $hasSavedDocDateField ? trim((string)($valueMapByKey['doc_date'] ?? ($valueMap[1] ?? ''))) : trim($document['doc_date'] ?? '');
$ownerName = $valueMap[2] ?? "";
$position = $valueMap[3] ?? "";
$joinType = $valueMap[4] ?? "";
$courseName = $valueMapByKey['course_name'] ?? ($valueMap[5] ?? "");
$projectTitle = $valueMapByKey['project_title'] ?? ($valueMapByKey['thesis_title'] ?? ($valueMap[5] ?? ""));
$joinDates = $valueMapByKey['intern_period'] ?? ($valueMapByKey['event_date'] ?? ($valueMap[6] ?? ""));
$eventDate = $valueMapByKey['event_date'] ?? ($valueMapByKey['intern_period'] ?? ($valueMap[6] ?? ($valueMap[16] ?? "")));
$location = $valueMapByKey['location_input'] ?? ($valueMapByKey['place'] ?? ($valueMap[7] ?? ""));
$amountStr = $valueMap[8] ?? "";
$vehicle = $valueMap[9] ?? "";
$faculty = $valueMapByKey['faculty'] ?? ($valueMap[10] ?? "");
$department = $valueMapByKey['department'] ?? ($valueMap[11] ?? "");
$eventPlace = $valueMap[13] ?? "";

$docSubject = $valueMapByKey['subject'] ?? ($valueMap[14] ?? ($document['subject'] ?? ""));
$objective = $valueMapByKey['objective'] ?? ($valueMap[25] ?? "");
$toPerson = $valueMapByKey['to_person'] ?? ($valueMap[26] ?? "");
$inviteStatement = $valueMapByKey['invite_statement'] ?? "";
$eventTime = $valueMapByKey['event_time'] ?? "";

/* --------------------------------------------------
   Mapping joinType → purposeCode (รหัส)
-------------------------------------------------- */
$purposeCode = 'other';

switch (trim($joinType)) {
  case 'นำเสนอผลงานทางวิชาการ':
    $purposeCode = 'academic';
    break;
  case 'เข้าร่วมประชุมวิชาการในงาน':
    $purposeCode = 'meeting';
    break;
  case 'เข้ารับการฝึกอบรมหลักสูตร':
    $purposeCode = 'training';
    break;
}

/* --------------------------------------------------
   ⭐⭐⭐ สำคัญที่สุด — แก้ให้ส่วนหัวขึ้น ⭐⭐⭐
-------------------------------------------------- */

$header_text = $document["header_text"] ?? "";
$doc_no = $document["doc_no"] ?? "";
$subject = $docSubject ?: ($document["subject"] ?? "");

/* ===== ชื่อไฟล์ดาวน์โหลดภาษาไทย (PDF / Word) ===== */
$downloadSubject = trim((string) $subject);
if ($downloadSubject === '') {
  $downloadSubject = 'หนังสือเรียนเชิญวิทยากร';
}
$downloadSubject = preg_replace('/[\\\\\/\:\*\?\"\<\>\|\r\n\t]+/u', ' ', $downloadSubject);
$downloadSubject = preg_replace('/\s+/u', ' ', $downloadSubject);
$downloadSubject = trim($downloadSubject);

if (function_exists('mb_strlen') && mb_strlen($downloadSubject, 'UTF-8') > 80) {
  $downloadSubject = mb_substr($downloadSubject, 0, 80, 'UTF-8');
}

$downloadBaseName = 'หนังสือเรียนเชิญวิทยากร_' . $downloadSubject . '_เลขที่_' . (int) $docId;
$pdfDownloadName = $downloadBaseName . '.pdf';
$wordDownloadName = $downloadBaseName . '.docx';

$referer = $_SERVER['HTTP_REFERER'] ?? '';


/* --------------------------------------------------
   คำนวณวันที่ไทย, งบประมาณ
-------------------------------------------------- */
$thaiDocDate = thai_date($docDate);
$thaiEventDate = thai_date_with_weekday($eventDate);
$thaiEventTime = format_thai_time_range($eventTime);
$prettyAmount = $amountStr !== "" ? number_format((float) $amountStr, 2) : "";

$displayFaculty = trim($faculty) !== '' ? trim($faculty) : "คณะเทคโนโลยีและการจัดการอุตสาหกรรม";
$displayDepartment = trim($department) !== '' ? trim($department) : "เทคโนโลยีสารสนเทศ";
$displayDepartmentFull = "ภาควิชา" . $displayDepartment;
$displayFacultyDean = "คณบดี" . $displayFaculty;
$deanName = "";
$deanPosition = "";
$deanFacultyName = $displayFaculty;

/* --------------------------------------------------
   ข้อมูลคณบดีตามคณะในบรรทัดส่วนราชการ
-------------------------------------------------- */
try {
  if (trim((string)$displayFaculty) !== "") {
    $deanStmt = $pdo->prepare("
      SELECT dean_name, dean_position, faculty_name
      FROM faculties
      WHERE faculty_name = :faculty
         OR faculty_name = CONCAT('คณะ', :faculty)
      LIMIT 1
    ");
    $deanStmt->execute([':faculty' => trim((string)$displayFaculty)]);
    $deanRow = $deanStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $deanName = trim((string)($deanRow['dean_name'] ?? ""));
    $deanPosition = trim((string)($deanRow['dean_position'] ?? ""));
    $deanFacultyName = trim((string)($deanRow['faculty_name'] ?? $deanFacultyName));
  }
} catch (Throwable $e) {
  $deanName = "";
  $deanPosition = "";
}

if ($deanName === "") {
  $deanName = "................................";
}
if ($deanPosition === "") {
  $deanPosition = "คณบดี" . ($deanFacultyName !== "" ? $deanFacultyName : $displayFaculty);
}
$displayFacultyDean = $deanPosition;
$displaySubject = $subject ?: "ขอเรียนเชิญเป็นวิทยากรบรรยาย";
$displayToPerson = $toPerson ?: "คุณ................................................";
$displayProjectTitle = $projectTitle ?: "................................................";
$displayInviteStatement = $inviteStatement ?: "เห็นว่าท่านเป็นผู้มีความเชี่ยวชาญและมีประสบการณ์สูง ในสาขาวิชาชีพดังกล่าว ซึ่งจะเป็นประโยชน์แก่นักศึกษาผู้เข้าร่วมโครงการเป็นอย่างดี";
$displayObjective = $objective ?: "................................................";
$displayEventDate = $thaiEventDate ?: "วันที่................................................";
$displayEventTime = $thaiEventTime ?: "................";
$displayLocation = $location ?: "................................................";

/* --------------------------------------------------
   สร้างข้อความส่วนหัวที่ใช้ในเนื้อหา
-------------------------------------------------- */
$hdr_agency = trim(
  ($faculty ?: "คณะ..................................") . " " .
  ($department ? "ภาควิชา" . $department : "ภาควิชา........................")
);

$hdr_subject = $joinType ?: "เข้ารับการฝึกอบรมหลักสูตร";
$hdr_to = "คณบดี" . ($faculty ?: "คณะ..................................");

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

  @page {
    size: A4;
    margin: 0;
  }

  .page {
    width: 794px;
    height: 1123px;
    min-height: 1123px;

    margin: 40px auto;

    padding: 55px 85px 45px 85px;

    background: #fff;

    box-shadow: 0 0 5px rgba(0, 0, 0, .1);

    position: relative;

    border: 2px solid #fff;

    box-sizing: border-box;

    overflow: visible;
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
    line-height: 1.25;
    margin: 0;
    text-align: justify;
  }

  .content-block.paragraph {
    text-indent: 2.5cm;
    margin-top: 8px;
    line-height: 1.25;
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
    padding: 0 1px;
    margin: 0;
    border: 1px solid #000;
    background: #fff;
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    line-height: 1em;
    white-space: nowrap;
    vertical-align: baseline;
  }

  .keep {
    white-space: nowrap;
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

  .footer-actions {
    margin-top: -6px;
    padding-top: 16px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid #e5e7eb;
  }

  /* เพิ่มพื้นที่ด้านล่างเฉพาะหน้าจอ เพื่อไม่ให้ปุ่มหลุดขอบเมื่อเนื้อหายาว
     ไม่กระทบ PDF เพราะตอน export จะ clone เอกสารแล้วลบ .footer-actions ออก
     และไม่กระทบ Word เพราะ Word ใช้ไฟล์ดาวน์โหลด Word แยกต่างหาก */
  @media screen {

    /* เฉพาะหน้าจอปกติ: ให้กระดาษยืดลงได้เมื่อเนื้อหายาวและปุ่มอยู่ท้ายหน้า
       ไม่กระทบ PDF เพราะ downloadPdf() clone .page แล้วบังคับ height = 1123px และลบ .footer-actions ออกก่อน export
       ไม่กระทบ Word เพราะ Word ใช้ไฟล์ดาวน์โหลดแยก */
    .page {
      height: auto !important;
      min-height: 1123px !important;
      padding-bottom: 115px !important;
      margin-bottom: 80px !important;
    }

    .footer-actions {
      margin-top: 18px !important;
      padding-bottom: 0 !important;
    }
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
    /* ⭐ กดลงมาอีกนิดเพื่อให้ชิดเส้นมากที่สุด */
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
      width: 794px !important;
      height: 1123px !important;
      min-height: 1123px !important;

      margin: 0 auto !important;

      padding: 55px 85px 45px 85px !important;

      box-shadow: none !important;
      border: 2px solid #fff !important;
      box-sizing: border-box !important;
      overflow: visible !important;
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

    .header-address {
      width: 400px !important;
      letter-spacing: -0.05px !important;
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
    /* เดิม 10px → เพิ่มออกมาอีก */
    margin-right: 6px !important;
    /* ขยับปลายด้านหลังให้สวยขึ้น */
    padding-left: 6px !important;
    padding-right: 6px !important;
    padding-top: 2px !important;
    padding-bottom: 2px !important;
    display: inline-flex !important;
    align-items: flex-end !important;
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
  }.document-review-layout {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  gap: 18px;
  width: 100%;
}

.review-comment-panel {
  position: fixed;
  right: 18px;
  bottom: 70px;
  width: 300px;
  background: #ffffff;
  border: 1px solid #99f6e4;
  border-radius: 14px;
  padding: 12px;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.10);
  z-index: 40;
}

.review-comment-title {
  font-family: 'TH SarabunPSK', sans-serif !important;
  font-size: 20pt;
  font-weight: bold;
  color: #0f766e;
  line-height: 1;
  margin-bottom: 8px;
}

.review-comment-note {
  margin-top: 4px;
  margin-bottom: 10px;
  color: #dc2626;
  font-size: 14px;
  font-weight: 500;
}

.review-comment-textarea {
  width: 100%;
  min-height: 118px;
  resize: vertical;
  border: 1px solid #99f6e4;
  border-radius: 10px;
  padding: 8px 10px;
  font-family: 'TH SarabunPSK', sans-serif !important;
  font-size: 18pt;
  line-height: 1.15;
  outline: none;
  color: #111827;
  background: #ffffff;
}

.review-comment-textarea:focus {
  border-color: #14b8a6;
  box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.16);
}

.review-comment-readonly {
  min-height: 96px;
  border: 1px solid #ccfbf1;
  border-radius: 8px;
  padding: 10px 12px;
  background: #f0fdfa;
  color: #134e4a;
  font-family: 'TH SarabunPSK', sans-serif !important;
  font-size: 18pt;
  line-height: 1.22;
  white-space: pre-wrap;
  word-break: break-word;
}

.review-comment-hint {
  margin-top: 8px;
  color: #64748b;
  font-family: 'TH SarabunPSK', sans-serif !important;
  font-size: 15pt;
  line-height: 1.1;
}

.review-comment-footer {
  display: flex;
  justify-content: flex-end;
  margin-top: 10px;
}

.review-comment-save-btn {
  border: 0;
  border-radius: 10px;
  padding: 7px 16px;
  cursor: pointer;
  background: #14b8a6;
  color: #ffffff;
  font-family: 'TH SarabunPSK', sans-serif !important;
  font-size: 18pt;
  font-weight: bold;
  line-height: 1;
}

.review-comment-save-btn:hover {
  background: #0f766e;
}

@media print {
  .review-comment-panel {
    display: none !important;
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
  <?php if ($readonly): ?>
  <script>
  document.addEventListener("DOMContentLoaded", () => {

    // ปิด contenteditable ทั้งหมด
    document.querySelectorAll("[contenteditable]").forEach(e => {
      e.setAttribute("contenteditable", "false");
      e.style.background = "#f0f0f0";
      e.style.cursor = "not-allowed";
    });

    // ปิด input / select / textarea
    document.querySelectorAll("input:not([type=hidden]), textarea, select").forEach(e => {
      e.disabled = true;
      e.style.background = "#f0f0f0";
      e.style.cursor = "not-allowed";
    });

    // ซ่อนปุ่ม submit
    const submitBtn = document.querySelector("button[type=submit]");
    if (submitBtn) submitBtn.style.display = "none";

    // เปลี่ยนข้อความของปุ่มพิมพ์ให้อยู่ในโหมดตัวอย่าง
    const printBtn = document.querySelector("button[onclick='window.print()']");
    if (printBtn) printBtn.innerText = "พิมพ์/ดูตัวอย่าง";

    // แจ้งเตือนแสดง read-only
    Swal.fire({
      title: <?= json_encode($editAlertTitle ?: "ไม่สามารถแก้ไขเอกสารได้", JSON_UNESCAPED_UNICODE) ?>,
      html: <?= json_encode(($editAlertText ?: "เอกสารนี้ไม่สามารถแก้ไขได้") . "<br><br>ระบบจะแสดงเอกสารในโหมดดูตัวอย่างเท่านั้น", JSON_UNESCAPED_UNICODE) ?>,
      icon: <?= json_encode($editAlertIcon ?: "info", JSON_UNESCAPED_UNICODE) ?>,
      confirmButtonText: "ตกลง",
      confirmButtonColor: "#14b8a6"
    });
  });
  </script>
  <?php endif; ?>

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

  <div class="document-review-layout">
  <main class="page">
    <form id="updateForm" action="update_memo.php" method="post">
      <input type="hidden" name="header_text" id="hidden_header_text" value="<?= h($header_text) ?>">
      <input type="hidden" name="doc_no" id="hidden_doc_no" value="<?= h($doc_no) ?>">

      <!-- hidden input ครบทุก field_id -->
      <input type="hidden" name="redirect_back" value="<?= h($referer) ?>">

      <input type="hidden" name="document_id" value="<?= h($document['document_id']) ?>">

      <!-- สำคัญ: ให้ doc_date เป็นรูปแบบเดิม (YYYY-MM-DD) ที่ดึงมาจาก DB -->
      <input type="hidden" name="doc_date" id="hidden_doc_date" value="<?= h($docDate) ?>">

      <input type="hidden" name="fullname" id="hidden_ownerName" value="<?= h($ownerName) ?>">
      <input type="hidden" name="position" id="hidden_position" value="<?= h($position) ?>">

      <!-- ส่ง purpose เป็นรหัส ไม่ใช่ข้อความไทย -->
      <input type="hidden" name="purpose" id="hidden_joinType" value="<?= h($purposeCode) ?>">

      <input type="hidden" name="event_title" id="hidden_courseName" value="<?= h($courseName) ?>">


      <input type="hidden" name="range_date" id="hidden_joinDates" value="<?= h($joinDates) ?>">
      <input type="hidden" name="place" id="hidden_location" value="<?= h($location) ?>">
      <input type="hidden" name="amount" id="hidden_amountStr" value="<?= h($amountStr) ?>">
      <input type="hidden" name="car_plate" id="hidden_vehicle" value="<?= h($vehicle) ?>">
      <input type="hidden" name="faculty" id="hidden_faculty" value="<?= h($faculty) ?>">
      <input type="hidden" name="department" id="hidden_department" value="<?= h($department) ?>">

      <!-- ตัวเลือกช่วงวันที่: ใช้ range เป็นค่า default ตาม UI ปัจจุบัน -->
      <input type="hidden" name="date_option" id="hidden_dateOption" value="range">
      <input type="hidden" name="single_date" id="hidden_singleDate" value="">


      <!-- หัวหนังสือราชการภายนอก -->
      <div style="
  display:grid;
  grid-template-columns: 31% 22% 47%;
  align-items:start;
  margin-top:18px;
">

        <!-- เลขที่ -->
        <div style="
    font-size:16pt;
    padding-top:50px;
    white-space:nowrap;
  ">
          ที่
        </div>

        <!-- ครุฑ -->
        <div style="text-align:center; position:relative; left:32px; top:6px;">
          <img src="/Pro_letter/assets/img/garuda.jpg" style="
      width:123px;
      height:auto;
      opacity:0.83;
      filter: grayscale(100%) contrast(65%) brightness(126%);
      image-rendering:auto;
      border:none;
      outline:none;
      box-shadow:none;
      background:transparent;
      transform:scale(1.01);
    ">
        </div>

        <!-- ที่อยู่ -->
        <div style="
  font-size:15.5pt;
  line-height:1.28;

  padding-top:58px;

  padding-left:44px;

  width:380px;

  text-align:left;
">

          <div style="
      position:relative;
      top:-5px;
  ">
            <?= h_thai($displayFaculty) ?>
          </div>

          <div style="
      position:relative;
      top:-2px;
  ">
            มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ
          </div>

          ๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐

        </div>

      </div>

      <!-- วันที่ -->
      <div style="
  font-size:16pt;

  text-align:center;

  margin-top:17px;

  margin-bottom:16px;

  position:relative;

  left:38px;
">
        <?= h_thai($thaiDocDate ?: '') ?>
      </div>

      <div style="
    font-family:'TH SarabunPSK';
    font-size:16pt;
    line-height:1.15;
    color:#111;
">

        <!-- เรื่อง -->
        <div style="
    display:grid;
    grid-template-columns: 1.2cm 1fr;
    column-gap:0;
    margin-bottom:2px;
    line-height:1.38;
">

          <div style="white-space:nowrap;">
            เรื่อง
          </div>

          <div style="
        text-align:left;
        line-height:1.38;
    ">
            <?= h_thai($displaySubject) ?>
          </div>

        </div>

        <!-- เรียน -->
        <div style="
    display:grid;
    grid-template-columns: 1.2cm 1fr;
    column-gap:0;
    margin-bottom:2px;
    line-height:1.38;
">

          <div style="white-space:nowrap;">
            เรียน
          </div>

          <div style="
        text-align:left;
        line-height:1.38;
    ">
            <?= nl2br(h_thai($displayToPerson)) ?>
          </div>

        </div>

        <div style="margin-bottom:8px;">

          <div>
            <span style="display:inline-block; width:2.2cm;">
              สิ่งที่ส่งมาด้วย
            </span>

            <span style="
        display:inline-block;
        width:8.3cm;
        padding-left:0.25cm;
    ">
              ๑. รายละเอียดโครงการ
            </span>

            <span>จำนวน ๑ ชุด</span>
          </div>

          <div>
            <span style="display:inline-block; width:2.2cm;"></span>

            <span style="
        display:inline-block;
        width:8.3cm;
        padding-left:0.25cm;
    ">
              ๒. แบบตอบรับการเป็นวิทยากร
            </span>

            <span>จำนวน ๑ ฉบับ</span>
          </div>
        </div>

        <!-- ย่อหน้า 1 -->
        <p style="
        text-indent:2.5cm;
        margin:0 0 10px 0;
        text-align:justify;
        line-height:1.38;
        letter-spacing:-0.1px;
        word-spacing:-1px;
    ">
          ด้วย<?= h_thai($displayDepartmentFull) ?> <?= h_thai($displayFaculty) ?>
          มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
          ได้ดำเนินการจัด<?= h_thai($displayProjectTitle) ?> ใน<?= h_thai($displayEventDate) ?> เวลา
          <?= h_thai($displayEventTime) ?>
          น.
          ณ <?= h_thai($displayLocation) ?> โดยมีวัตถุประสงค์<?= nl2br(h_thai($displayObjective)) ?>
          รายละเอียดโครงการตามสิ่งที่ส่งมาด้วย ๑
        </p>

        <!-- ย่อหน้า 2 -->
        <p style="
        text-indent:2.5cm;
        margin:0 0 10px 0;
        text-align:justify;
        line-height:1.38;
        letter-spacing:-0.1px;
        word-spacing:-1px;
    ">
          <?= h_thai($displayDepartmentFull) ?> <?= nl2br(h_thai($displayInviteStatement)) ?>
          จึงขอเรียนเชิญท่านเป็นวิทยากรบรรยายเรื่องดังกล่าว ตามวัน เวลา และสถานที่ข้างต้น
        </p>

        <!-- ย่อหน้า 3 -->
        <p style="
        text-indent:2.5cm;
        margin:0 0 10px 0;
        line-height:1.38;
    ">
          จึงเรียนมาเพื่อโปรดพิจารณาให้ความอนุเคราะห์ จะขอบคุณยิ่ง
        </p>

        <!-- ขอแสดงความนับถือ -->
        <div style="
    text-align:center;
    margin-top:22px;
    width:100%;
">
          ขอแสดงความนับถือ
        </div>

        <!-- ลายเซ็น -->
        <div style="
    text-align:center;
    margin-top:52px;
    width:100%;
    line-height:1.15;
    white-space:nowrap;
">

          <div>
            (<?= h_thai($deanName) ?>)
          </div>

          <div>
            <?= h_thai($deanPosition) ?>
          </div>

        </div>

        <!-- footer -->
        <div style="
    margin-top:30px;
    margin-left:0.2cm;

    font-size:16pt;

    line-height:1.32;

    letter-spacing:-0.05px;

    color:#111;
">

          <?= h_thai($displayDepartmentFull) ?><br>

          โทรศัพท์ ๐-๓๗๒๑-๗๓๔๐-๓ ต่อ ๗๐๖๕-๖<br>


          ไปรษณีย์อิเล็กทรอนิกส์ :
          <a href="mailto:it@itm.kmutnb.ac.th" style="
          color:#111;
          text-decoration:none;
        ">
            it@itm.kmutnb.ac.th
          </a>

        </div>

      </div>

      <!-- <div style="font-family:'TH SarabunPSK'; font-size:16pt; line-height:1.2;"> เรียน <?= h($hdr_to) ?> </div>
            <div class="content-block single align-to-dean"> เพื่อโปรดพิจารณาอนุมัติ </div>
            <div class="content-block single align-to-dean" style="margin-top:50px;;"> (ผู้ช่วยศาสตราจารย์ ดร. ขนิษฐา
                นามี)<br /> หัวหน้า<?= h($displayDepartmentFull) ?> </div> -->
      <div class="footer-actions">

        <!-- ปุ่มดาวน์โหลด PDF -->
        <button type="button" onclick="downloadPdf()"
          class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
          ดาวน์โหลด PDF
        </button>

        <!-- ปุ่มดาวน์โหลด Word -->
        <a href="/Pro_letter/documents/download_word_invite_speaker.php?id=<?= (int)$docId ?>"
          download="<?= h($wordDownloadName) ?>" data-word-download="1" data-word-filename="<?= h($wordDownloadName) ?>"
          onclick="return downloadWord(this);"
          class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
          ดาวน์โหลด Word
        </a>

        <!-- ปุ่มแก้ไขเอกสาร -->
        <?php if ($canEdit): ?>
        <a href="/Pro_letter/documents/infor_invite.php?id=<?= (int)$docId ?>&edit=1"
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

    <?php if (isset($_GET['comment_saved']) && $_GET['comment_saved'] == '1'): ?>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
      title: "บันทึกความคิดเห็นแล้ว",
      icon: "success",
      confirmButtonText: "ตกลง",
      confirmButtonColor: "#14b8a6"
    });
  });
  </script>
  <?php elseif (isset($_GET['comment_err']) && $_GET['comment_err'] === 'empty'): ?>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
      title: "กรุณากรอกความคิดเห็นก่อนบันทึก",
      icon: "warning",
      confirmButtonText: "ตกลง",
      confirmButtonColor: "#14b8a6"
    });
  });
  </script>
  <?php endif; ?>

  <?php if ($canWriteReviewComment): ?>
  <form method="post" class="review-comment-panel">
  <input type="hidden" name="action" value="save_review_comment">
  <div class="review-comment-title">ความคิดเห็นผู้ตรวจเอกสาร</div>
  <div class="review-comment-note">
    หมายเหตุ: กรุณากดบันทึกความคิดเห็นก่อนกดตรวจสอบว่าผ่านหรือไม่ผ่าน
  </div>
  <textarea name="review_comment" class="review-comment-textarea" maxlength="1000"
    placeholder="พิมพ์ความคิดเห็นสำหรับเอกสารนี้..." required><?= h($reviewCommentTextareaValue) ?></textarea>

    <div class="review-comment-footer">
      <button type="submit" class="review-comment-save-btn">บันทึก</button>
    </div>
  </form>
  <?php elseif ($canReadReviewComment): ?>
  <aside class="review-comment-panel" aria-label="ความคิดเห็นผู้ตรวจเอกสาร">
    <div class="review-comment-title">ความคิดเห็นผู้ตรวจเอกสาร</div>

    <div class="review-comment-readonly">
      <?= h($lastReviewComment !== '' ? $lastReviewComment : 'ยังไม่มีความคิดเห็นจากผู้ตรวจเอกสาร') ?>
    </div>

    <div class="review-comment-hint">
      อ่านความคิดเห็นนี้ แล้วกดแก้ไขเอกสารเพื่อปรับข้อมูลตามคำแนะนำ
    </div>
  </aside>
  <?php endif; ?>
</div>

  <?php if ($readonly): ?>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[contenteditable]").forEach(e => {
      e.setAttribute("contenteditable", "false");
      e.style.background = "#f0f0f0";
    });
    document.querySelectorAll("input, textarea, select").forEach(e => {
      e.disabled = true;
      e.style.background = "#f0f0f0";
    });
    const submitBtn = document.querySelector("button[type=submit]");
    if (submitBtn) submitBtn.style.display = "none";
  });
  </script>
  <?php endif; ?>

  <script>
  const alertBox = document.getElementById('alertBox');
  if (alertBox) {
    setTimeout(() => {
      alertBox.style.transition = "opacity 0.5s ease";
      alertBox.style.opacity = 0;
      setTimeout(() => alertBox.remove(), 500);
    }, 3000); // ซ่อนหลัง 3 วินาที
  }

  function parseThaiDate(str) {
    const monthMap = {
      "มกราคม": "01",
      "กุมภาพันธ์": "02",
      "มีนาคม": "03",
      "เมษายน": "04",
      "พฤษภาคม": "05",
      "มิถุนายน": "06",
      "กรกฎาคม": "07",
      "สิงหาคม": "08",
      "กันยายน": "09",
      "ตุลาคม": "10",
      "พฤศจิกายน": "11",
      "ธันวาคม": "12"
    };
    const parts = str.trim().split(" ");
    if (parts.length !== 3) return null;

    const d = parts[0].replace(/\D/g, ""); // เลขวัน
    const m = monthMap[parts[1]] || "01"; // เดือน
    const y = parseInt(parts[2], 10) - 543; // ปี พ.ศ. → ค.ศ.

    if (!d || !m || isNaN(y)) return null;
    return `${y}-${m}-${d.padStart(2, "0")}`; // YYYY-MM-DD
  }
  document.getElementById("updateForm").addEventListener("submit", function() {
    document.querySelectorAll("[contenteditable][data-target]").forEach(el => {
      const target = el.dataset.target;
      const hidden = document.getElementById("hidden_" + target);
      if (hidden) {
        let text = el.innerText.trim();

        if (target === "doc_date_display") {
          const isoDate = parseThaiDate(text);
          if (isoDate) {
            document.getElementById("hidden_doc_date").value = isoDate; // ✅ อัปเดตจริง
          }
        }

        hidden.value = text;
      }
    });
  });

  function getQuery(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }

  document.addEventListener("DOMContentLoaded", () => {
    const errType = getQuery("err");


    if (["submitted", "checked"].includes(errType)) {
    const alertMap = {
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
  </script>

  <script>
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

    const fileName = link.dataset.wordFilename ||
      <?= json_encode($wordDownloadName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    fetch(downloadUrl.toString(), {
        method: "GET",
        credentials: "same-origin"
      })
      .then(async response => {
        if (!response.ok) {
          const errorText = await response.text().catch(() => "");
          console.error("Word download response:", response.status, errorText);
          throw new Error("Word download failed");
        }
        return response.blob();
      })
      .then(blob => {
        const objectUrl = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = objectUrl;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(objectUrl);
      })
      .catch(error => {
        console.error(error);
        alert("ดาวน์โหลด Word ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง");
      })
      .finally(resetWordDownloadUI);

    return false;
  }

  async function downloadPdf() {
    const loadingOverlay = document.getElementById("pdfLoadingOverlay");
    const loadingTitle = document.getElementById("downloadLoadingTitle");
    const loadingSubtitle = document.getElementById("downloadLoadingSubtitle");
    const downloadButtons = document.querySelectorAll("button[onclick='downloadPdf()']");

    if (loadingTitle) loadingTitle.innerText = "กำลังสร้าง PDF...";
    if (loadingSubtitle) loadingSubtitle.innerText = "กรุณารอสักครู่ ระบบกำลังเตรียมเอกสาร";

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

        clone.style.position = "fixed";
        clone.style.left = "-9999px";
        clone.style.top = "0";
        clone.style.width = "794px";
        clone.style.minHeight = "1123px";
        clone.style.height = "1123px";
        clone.style.boxSizing = "border-box";
        clone.style.background = "#ffffff";
        clone.style.boxShadow = "none";
        clone.style.margin = "0";
        clone.style.overflow = "hidden";

        clone.querySelectorAll(".footer-actions").forEach(el => el.remove());

        clone.querySelectorAll("[contenteditable]").forEach(el => {
          el.setAttribute("contenteditable", "false");
        });

        const garuda = clone.querySelector('img[src*="g_photo1"], img[src*="garuda"]');
        if (garuda) {
          garuda.style.opacity = "0.58";
          garuda.style.filter = "grayscale(100%) contrast(35%) brightness(165%)";
        }

        document.body.appendChild(clone);

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