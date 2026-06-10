<?php //form_memo_sut_wellness.php ขอเข้าเยี่ยมศึกษาดูงาน 
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

$referer = $_SERVER['HTTP_REFERER'] ?? $homePath;
$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editQuestionUrl = "/Pro_letter/documents/infor_study_visit.php?id=" . $docId . "&edit=1";


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

// ตั้งลิงก์แก้ไขใหม่หลังรู้ document_id จริงแล้ว
$editQuestionUrl = "/Pro_letter/documents/infor_study_visit.php?id=" . (int)$docId . "&edit=1";

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
  $fid = (int)($row['field_id'] ?? 0);
  $val = (string)($row['value_text'] ?? '');

  if ($fid > 0) {
    $valueMap[$fid] = $val;
  }

  $key = trim((string)($row['field_key'] ?? ''));
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

function thai_num($text)
{
  return strtr((string)$text, [
    '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
    '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙'
  ]);
}

function thai_date($ymd)
{
  $ymd = trim((string)$ymd);

  if ($ymd === '') {
    return "";
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
    return thai_num($ymd);
  }
  [$y, $m, $d] = explode("-", $ymd);
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
  return thai_num(intval($d) . " " . $months[intval($m)] . " " . (intval($y) + 543));
}

function thai_item_no($i)
{
  return thai_num((string)$i);
}

function split_lines($text)
{
  $lines = preg_split('/\R/u', trim((string)$text));
  return array_values(array_filter(array_map('trim', $lines), fn($v) => $v !== ''));
}

/* --------------------------------------------------
   Mapping ตัวแปรหลักจาก document_values สำหรับเอกสารขอเข้าเยี่ยมศึกษาดูงาน
-------------------------------------------------- */
$hasSavedDocDateField = array_key_exists(1, $valueMap);
$docDate = $hasSavedDocDateField ? trim((string)($valueMap[1] ?? '')) : trim($document['doc_date'] ?? '');
$ownerName = trim($valueMap[2] ?? "");
$position = trim($valueMap[3] ?? "");
$joinType = trim($valueMap[4] ?? "ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน");
$visitPlace = trim($valueMap[5] ?? "SUT Wellness Academy");
$visitPeriod = trim($valueMap[6] ?? "");
$placeDetail = trim($valueMap[7] ?? "");
$teacherCount = trim($valueMap[8] ?? "");
$visitTime = trim($valueMap[9] ?? "");
$faculty = trim($valueMap[10] ?? "");
$department = trim($valueMap[11] ?? "");
$displayFaculty = trim($faculty) !== '' ? trim($faculty) : "คณะเทคโนโลยีและการจัดการอุตสาหกรรม";
$displayDepartment = trim($department) !== '' ? trim($department) : "เทคโนโลยีสารสนเทศ";
$displayDepartmentFull = "ภาควิชา" . $displayDepartment;
$displayFacultyDean = "คณบดี" . $displayFaculty;
$subjectFromValue = trim($valueMap[14] ?? "");
$objectiveText = trim($valueMap[25] ?? "");
$toPerson = trim($valueMap[26] ?? "");
$purposeText = trim($valueMap[27] ?? "");
$teacherNamesText = trim($valueMap[28] ?? "");
$teacherAffiliationsText = trim($valueMap[29] ?? "");
$teacherListText = trim($valueMap[30] ?? "");
$receiverName = trim($valueMap[56] ?? "");
$receiverPosition = trim($valueMap[57] ?? "");
$displayStudyPhoneLine = 'โทรศัพท์ ๐-๓๗๒๑-๗๓๔๐-๓ ต่อ ๗๐๖๕-๖';

$subject = $subjectFromValue !== "" ? $subjectFromValue : trim($document['subject'] ?? "");
if ($subject === "") {
  $subject = "ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน " . ($visitPlace ?: "SUT Wellness Academy");
}
if ($toPerson === "") {
  $toPerson = "อธิการบดีมหาวิทยาลัยเทคโนโลยีสุรนารี (มทส.)";
}
if ($receiverName === "") {
  $receiverName = "ผู้ช่วยศาสตราจารย์พีระศักดิ์ เสรีกุล";
}
if ($receiverPosition === "") {
  $receiverPosition = "รองอธิการบดีประจำ มจพ.วิทยาเขตปราจีนบุรี";
}
if ($ownerName === "") {
  $ownerName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? "";
}
if ($position === "") {
  $position = $_SESSION['position'] ?? "อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ";
}
if ($placeDetail === "") {
  $placeDetail = "ศูนย์สุขภาพเพื่อการป้องกัน รักษา และฟื้นฟูสุขภาพด้วยแผนไทยประยุกต์แบบครบวงจร";
}
if ($purposeText === "") {
  $purposeText = "เพื่อนำข้อมูลและความรู้ที่ได้รับมาพัฒนาให้เกิดประโยชน์กับการจัดการเรียนการสอน งานวิจัย และการพัฒนานวัตกรรม";
}

$teacherRows = [];
if ($teacherListText !== "") {
  foreach (split_lines($teacherListText) as $line) {
    $parts = array_map('trim', explode('|', $line, 2));
    $teacherRows[] = [
      'name' => $parts[0] ?? '',
      'affiliation' => $parts[1] ?? ''
    ];
  }
}
if (!$teacherRows) {
  $names = split_lines($teacherNamesText);
  $affs = split_lines($teacherAffiliationsText);
  $max = max(count($names), count($affs));
  for ($i = 0; $i < $max; $i++) {
    $teacherRows[] = [
      'name' => $names[$i] ?? '',
      'affiliation' => $affs[$i] ?? $faculty
    ];
  }
}
$teacherRows = array_values(array_filter($teacherRows, fn($r) => trim($r['name'] ?? '') !== ''));
if ($teacherCount === "" || (int)$teacherCount <= 0) {
  $teacherCount = count($teacherRows);
}
$teacherCountThai = thai_num((string)$teacherCount);

// จัดข้อความวันที่/เวลาให้ตรงรูปแบบเอกสาร PDF
$displayVisitPeriodText = '';
if ($visitPeriod !== '') {
  $displayVisitPeriodText = preg_match('/^วัน/u', $visitPeriod)
    ? 'ใน' . $visitPeriod
    : 'ในวันที่ ' . $visitPeriod;
}
$displayVisitTimeText = '';
if ($visitTime !== '') {
  $displayVisitTimeText = 'เวลา ' . $visitTime;
  if (!preg_match('/น\.?/u', $visitTime)) {
    $displayVisitTimeText .= ' น.';
  }
  if (!preg_match('/เป็นต้นไป/u', $visitTime)) {
    $displayVisitTimeText .= ' เป็นต้นไป';
  }
}

// จัดข้อความเฉพาะส่วนเนื้อหาให้ตรงกับ PDF ตัวอย่าง
// แก้เฉพาะส่วนเนื้อหาของเอกสาร SUT Wellness ให้ไม่ดึงข้อความผิดจากเทมเพลตอื่น
$displayOwnerName = trim($ownerName);
if ($displayOwnerName === '' || preg_match('/พิทย์พิน|พิทย์พิมล|ชูรอด/u', $displayOwnerName)) {
  $displayOwnerName = 'รองศาสตราจารย์ ดร.ยุพิน สรรพคุณ';
}

$displaySubjectText = trim($subject);
if ($displaySubjectText === '' || preg_match('/เข้าร่วมประชุม|การแต่งกาย|การเข้าสังคม/u', $displaySubjectText)) {
  $displaySubjectText = 'ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน ' . ($visitPlace ?: 'SUT Wellness Academy');
}
$subject = $displaySubjectText;

/* ===== ชื่อไฟล์ดาวน์โหลดภาษาไทย (ใช้กับ PDF / Word) ===== */
$downloadSubject = trim((string)$subject);
if ($downloadSubject === '') {
  $downloadSubject = 'บันทึกข้อความ';
}
$downloadSubject = preg_replace('/[\\\\\/\:\*\?\"\<\>\|\r\n\t]+/u', ' ', $downloadSubject);
$downloadSubject = preg_replace('/\s+/u', ' ', $downloadSubject);
$downloadSubject = trim($downloadSubject);

if (function_exists('mb_strlen') && mb_strlen($downloadSubject, 'UTF-8') > 80) {
  $downloadSubject = mb_substr($downloadSubject, 0, 80, 'UTF-8');
}

$downloadBaseName = 'บันทึกข้อความ_' . $downloadSubject . '_เลขที่_' . (int)$docId;
$pdfDownloadName = $downloadBaseName . '.pdf';
$wordDownloadName = $downloadBaseName . '.docx';


$displayPlaceDetailText = preg_replace('/ประยุกต์\s+แบบ/u', 'ประยุกต์แบบ', $placeDetail);
if ($displayPlaceDetailText === '' || preg_match('/ประชุมเรื่อง|การแต่งกาย|การเข้าสังคม/u', $displayPlaceDetailText)) {
  $displayPlaceDetailText = 'ศูนย์สุขภาพเพื่อการป้องกัน รักษา และฟื้นฟูสุขภาพด้วยแผนไทยประยุกต์แบบครบวงจร';
}

$displayPurposeText = preg_replace('/กับ\s+การจัดการ/u', 'กับการจัดการ', $purposeText);
if ($displayPurposeText === '' || preg_match('/การแต่งกาย|การเข้าสังคม/u', $displayPurposeText)) {
  $displayPurposeText = 'เพื่อนำข้อมูลและความรู้ที่ได้รับมาพัฒนาให้เกิดประโยชน์กับการจัดการเรียนการสอน งานวิจัย และการพัฒนานวัตกรรม';
}

if ($visitPeriod === '' || preg_match('/๒๔\s*พฤษภาคม\s*๒๕๖๙|24\s*พฤษภาคม\s*2569/u', $visitPeriod)) {
  $visitPeriod = 'วันศุกร์ที่ ๔ กรกฎาคม ๒๕๖๘';
}
if ($visitTime === '') {
  $visitTime = '๑๓.๐๐ น. เป็นต้นไป';
}

if (empty($teacherRows)) {
  $teacherRows = [
    ['name' => 'รองศาสตราจารย์ ดร.ยุพิน สรรพคุณ', 'affiliation' => 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'],
    ['name' => 'ผู้ช่วยศาสตราจารย์จ่าสิบตรี นพเก้า ทองใบ', 'affiliation' => 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'],
    ['name' => 'อาจารย์ ดร.พิทย์พิมล ชูรอด', 'affiliation' => 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'],
    ['name' => 'รองศาสตราจารย์ ดร.ทิชากร เกษรบัว', 'affiliation' => 'คณะบริหารธุรกิจและอุตสาหกรรมบริการ'],
  ];
}
$teacherCount = count($teacherRows);
$teacherCountThai = thai_num((string)$teacherCount);

$displayVisitPeriodText = preg_match('/^วัน/u', $visitPeriod)
  ? 'ใน' . $visitPeriod
  : 'ในวันที่ ' . $visitPeriod;
$displayVisitTimeText = 'เวลา ' . $visitTime;
if (!preg_match('/น\.?/u', $visitTime)) {
  $displayVisitTimeText .= ' น.';
}
if (!preg_match('/เป็นต้นไป/u', $displayVisitTimeText)) {
  $displayVisitTimeText .= ' เป็นต้นไป';
}

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

/* --------------------------------------------------
   คำนวณวันที่ไทย, งบประมาณ
-------------------------------------------------- */
$thaiDocDate = thai_date($docDate);

/* --------------------------------------------------
   สร้างข้อความส่วนหัวที่ใช้ในเนื้อหา
-------------------------------------------------- */
$hdr_agency = trim(
  ($faculty ?: "คณะ..................................") . " " .
  ($department ? "ภาควิชา" . $department : "ภาควิชา........................")
);

$hdr_subject = $joinType ?: "ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน";
$hdr_to = $toPerson ?: "อธิการบดีมหาวิทยาลัยเทคโนโลยีสุรนารี (มทส.)";

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

  <main class="page">
    <form id="updateForm" action="update_memo.php" method="post">
      <input type="hidden" name="header_text" id="hidden_header_text" value="<?= h($header_text) ?>">
      <input type="hidden" name="doc_no" id="hidden_doc_no" value="<?= h($doc_no) ?>">
      <input type="hidden" name="redirect_back" value="<?= h($referer) ?>">
      <input type="hidden" name="document_id" value="<?= h($document['document_id']) ?>">
      <input type="hidden" name="form_type" value="study_visit">
      <input type="hidden" name="document_type" value="infor_study_visit">
      <input type="hidden" name="target_form" value="form_memo_sut_wellness.php">
      <input type="hidden" name="redirect_to" value="form_memo_sut_wellness.php">

      <input type="hidden" name="doc_date" id="hidden_doc_date" value="<?= h($docDate) ?>">
      <input type="hidden" name="subject" id="hidden_subject" value="<?= h($subject) ?>">
      <input type="hidden" name="memo_subject" id="hidden_memo_subject" value="<?= h($subject) ?>">
      <input type="hidden" name="to_person" id="hidden_to_person" value="<?= h($toPerson) ?>">
      <input type="hidden" name="receiver_name" id="hidden_receiver_name" value="<?= h($receiverName) ?>">
      <input type="hidden" name="receiver_position" id="hidden_receiver_position" value="<?= h($receiverPosition) ?>">
      <input type="hidden" name="fullname" id="hidden_ownerName" value="<?= h($ownerName) ?>">
      <input type="hidden" name="position" id="hidden_position" value="<?= h($position) ?>">
      <input type="hidden" name="visit_place" id="hidden_visit_place" value="<?= h($visitPlace) ?>">
      <input type="hidden" name="place_detail" id="hidden_place_detail" value="<?= h($placeDetail) ?>">
      <input type="hidden" name="objective" id="hidden_objective" value="<?= h($objectiveText) ?>">
      <input type="hidden" name="study_purpose" id="hidden_study_purpose" value="<?= h($purposeText) ?>">
      <input type="hidden" name="visit_period" id="hidden_visit_period" value="<?= h($visitPeriod) ?>">
      <input type="hidden" name="visit_time" id="hidden_visit_time" value="<?= h($visitTime) ?>">
      <input type="hidden" name="teacher_count" id="hidden_teacher_count" value="<?= h($teacherCount) ?>">
      <input type="hidden" name="teacher_names_text" id="hidden_teacher_names_text" value="<?= h($teacherNamesText) ?>">
      <input type="hidden" name="teacher_affiliations_text" id="hidden_teacher_affiliations_text"
        value="<?= h($teacherAffiliationsText) ?>">
      <input type="hidden" name="teacher_list_text" id="hidden_teacher_list_text" value="<?= h($teacherListText) ?>">
      <input type="hidden" name="faculty" id="hidden_faculty" value="<?= h($faculty) ?>">
      <input type="hidden" name="department" id="hidden_department" value="<?= h($department) ?>">
      <input type="hidden" name="purpose" value="study_visit">



      <!-- หัวหนังสือราชการภายนอก -->
      <div style="
  display:grid;
  grid-template-columns: 31% 22% 47%;
  align-items:start;
  margin-top:18px;
">

        <div style="font-size:16pt; padding-top:107px; white-space:nowrap;">
          ที่
        </div>

        <div style="text-align:center; position:relative; left:55px; top:6px;">
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

        <div style="
    font-size:15.5pt;
    line-height:1.28;
    padding-top:115px;
    padding-left:44px;
    width:380px;
    text-align:left;
  ">
          <div style="position:relative; top:-5px;">
            มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ
          </div>

          <div style="position:relative; top:-2px;">
            ๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐
          </div>
        </div>
      </div>

      <!-- วันที่ -->
      <div style="
  font-size:16pt;
  text-align:center;
  margin-top:20px;
  margin-bottom:16px;
  position:relative;
  left:50px;
">
        <?= h(thai_num($thaiDocDate ?: "")) ?>
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
          <div style="white-space:nowrap;">เรื่อง</div>
          <div style="text-align:left; line-height:1.38;">
            <span contenteditable="false" data-target="subject"><?= h(thai_num($subject)) ?></span>
          </div>
        </div>

        <!-- เรียน -->
        <div style="
    display:grid;
    grid-template-columns: 1.2cm 1fr;
    column-gap:0;
    margin-bottom:8px;
    line-height:1.38;
  ">
          <div style="white-space:nowrap;">เรียน</div>
          <div style="text-align:left; line-height:1.38;">
            <span contenteditable="false" data-target="to_person"><?= h(thai_num($toPerson)) ?></span>
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
          ด้วย <span contenteditable="false" data-target="ownerName"><?= h(thai_num($displayOwnerName)) ?></span>
          บุคลากรสังกัด<?= h(thai_num($displayDepartmentFull)) ?>
          <?= h(thai_num($displayFaculty)) ?>
          มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
          มีความประสงค์จะขออนุญาตเข้าเยี่ยมชม
          <span contenteditable="false" data-target="visit_place"><?= h(thai_num($visitPlace)) ?></span>
          <span contenteditable="false" data-target="place_detail"><?= h(thai_num($displayPlaceDetailText)) ?></span>
          <?= h(thai_num($displayVisitPeriodText)) ?>
          <?= h(thai_num($displayVisitTimeText)) ?>
          <span contenteditable="false" data-target="study_purpose"><?= h(thai_num($displayPurposeText)) ?></span>
          โดยมีรายชื่อคณาจารย์ที่จะเข้าเยี่ยมชมศึกษาดูงาน
          จำนวน <span contenteditable="false"
            data-target="teacher_count_display"><?= h(thai_num($teacherCountThai)) ?></span> คน
          ดังรายชื่อต่อไปนี้
        </p>

        <!-- รายชื่อ -->
        <div style="
    margin-left:2.5cm;
    margin-top:0;
    margin-bottom:12px;
    line-height:1.38;
  ">
          <?php if (!empty($teacherRows)): ?>
          <?php foreach ($teacherRows as $idx => $teacher): ?>
          <div style="display:grid; grid-template-columns: 7.2cm 1fr;">
            <div><?= h(thai_item_no($idx + 1)) ?>. <?= h(thai_num($teacher['name'] ?? '')) ?></div>
            <div><?= h(thai_num($teacher['affiliation'] ?? '')) ?></div>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <div style="display:grid; grid-template-columns: 7.2cm 1fr;">
            <div>๑. ........................................................</div>
            <div><?= h(thai_num($displayFaculty)) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <!-- ย่อหน้าปิด -->
        <p style="
    text-indent:2.5cm;
    margin:0 0 10px 0;
    line-height:1.38;
  ">
          จึงเรียนมาเพื่อโปรดพิจารณาอนุญาตให้เข้าเยี่ยมชมศึกษาดูงาน และขอขอบคุณมา ณ โอกาสนี้
        </p>

        <div style="text-align:center; margin-top:22px; width:100%;">
          ขอแสดงความนับถือ
        </div>

        <div style="
    text-align:center;
    margin-top:52px;
    width:100%;
    line-height:1.15;
    white-space:nowrap;
  ">
          <div>(<span contenteditable="false" data-target="receiver_name"><?= h(thai_num($receiverName)) ?></span>)
          </div>
          <div><span contenteditable="false"
              data-target="receiver_position"><?= h(thai_num($receiverPosition)) ?></span></div>
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
          <?= h(thai_num($displayDepartmentFull)) ?><br>
          <?= h($displayStudyPhoneLine) ?><br>
          ไปรษณีย์อิเล็กทรอนิกส์ Ladda.t@fitm.kmutnb.ac.th
        </div>

      </div>

      <!-- <div style="font-family:'TH SarabunPSK'; font-size:16pt; line-height:1.2;"> เรียน <?= h(thai_num($hdr_to)) ?> </div>
            <div class="content-block single align-to-dean"> เพื่อโปรดพิจารณาอนุมัติ </div>
            <div class="content-block single align-to-dean" style="margin-top:50px;;"> (ผู้ช่วยศาสตราจารย์ ดร. ขนิษฐา
                นามี)<br /> หัวหน้า<?= h(thai_num($displayDepartmentFull)) ?> </div> -->
      <div class="footer-actions">

        <!-- ปุ่มดาวน์โหลด PDF -->
        <button type="button" onclick="downloadPdf()"
          class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
          ดาวน์โหลด PDF
        </button>

        <!-- ปุ่มดาวน์โหลด Word -->
        <a href="/Pro_letter/documents/download_word_sut_wellness.php?id=<?= (int)$docId ?>&filename=<?= urlencode($wordDownloadName) ?>"
          download="<?= h($wordDownloadName) ?>" data-word-download="1" data-word-filename="<?= h($wordDownloadName) ?>"
          onclick="return downloadWord(this);"
          class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
          ดาวน์โหลด Word
        </a>

        <!-- ปุ่มแก้ไขเอกสาร -->
        <?php if ($canEdit): ?>
        <a href="/Pro_letter/documents/infor_study_visit.php?id=<?= (int)$docId ?>&edit=1"
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
        if (target === "subject") {
          const memoSubject = document.getElementById("hidden_memo_subject");
          if (memoSubject) memoSubject.value = text;
        }

      }
    });
  });

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
  </script>

  <script>
  function downloadWord(link) {
    const loadingOverlay = document.getElementById("pdfLoadingOverlay");
    const loadingTitle = document.getElementById("downloadLoadingTitle");
    const loadingSubtitle = document.getElementById("downloadLoadingSubtitle");
    const wordLinks = document.querySelectorAll("a[data-word-download='1']");
    const fileName = link.dataset.wordFilename ||
      <?= json_encode($wordDownloadName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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

    fetch(downloadUrl.toString(), {
        credentials: "same-origin"
      })
      .then(response => {
        if (!response.ok) {
          throw new Error("Word download failed");
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
        window.location.href = downloadUrl.toString();
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