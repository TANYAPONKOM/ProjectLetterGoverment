<?php //ขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ) Pro_letter/form_Memo/form_memo_coop_evaluation.php
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
   สิทธิ์แก้ไขเอกสาร
-------------------------------------------------- */
$sql = "
    SELECT COUNT(*) 
    FROM user_permissions up
    JOIN permissions p ON p.perm_id = up.perm_id
    WHERE up.user_id = :uid
    AND p.perm_code = 'document.edit'
";
$st = $pdo->prepare($sql);
$st->execute([':uid' => $userId]);

$canEdit = $st->fetchColumn() > 0;
$readonly = !$canEdit;



/* --------------------------------------------------
   ดึงค่า field จาก document_values
-------------------------------------------------- */
$q = $pdo->prepare("
  SELECT dv.field_id, dv.value_text, tf.field_key
  FROM document_values dv
  LEFT JOIN template_fields tf ON tf.field_id = dv.field_id
  WHERE dv.document_id = :id
");
$q->execute([':id' => $docId]);

$valueMap = [];
$valueKeyMap = [];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $valueMap[(int) $row['field_id']] = $row['value_text'];
  if (!empty($row['field_key'])) {
    $valueKeyMap[$row['field_key']] = $row['value_text'];
  }
}

/* --------------------------------------------------
   ฟังก์ชัน helper
-------------------------------------------------- */
// function h($s)
// {
//   return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
// }

function thai_digits($text) {
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

function arabic_digits($text) {
  return strtr((string)$text, [
    '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
    '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
  ]);
}

function thai_date($date)
{
  return thai_doc_date_format($date, 1);
}

function thai_doc_date_format($date, $spaceAfterDay = 2) {
  $date = trim((string)$date);

  if ($date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
    return '';
  }

  // แปลงเลขไทยเป็นเลขอารบิกก่อน parse
  $date = arabic_digits($date);

  // ตัดคำขึ้นต้น เช่น "วันที่", "วันพุธที่", "ในวันที่"
  $date = preg_replace('/^\s*ใน\s*/u', '', $date);
  $date = preg_replace('/^\s*วัน[\p{Thai}]+ที่\s*/u', '', $date);
  $date = preg_replace('/^\s*วันที่\s*/u', '', $date);
  $date = trim($date);

  $months = [
    1 => 'มกราคม',
    2 => 'กุมภาพันธ์',
    3 => 'มีนาคม',
    4 => 'เมษายน',
    5 => 'พฤษภาคม',
    6 => 'มิถุนายน',
    7 => 'กรกฎาคม',
    8 => 'สิงหาคม',
    9 => 'กันยายน',
    10 => 'ตุลาคม',
    11 => 'พฤศจิกายน',
    12 => 'ธันวาคม',
  ];

  // รองรับวันที่ไทย เช่น "5 กุมภาพันธ์ 2568" หรือ "๕ กุมภาพันธ์ ๒๕๖๘"
  // ห้ามใช้ [ก-ฮ]+ เพราะเดือนอย่าง "กุมภาพันธ์" มีสระ/วรรณยุกต์ ทำให้จับไม่ครบ
  if (preg_match('/(\d{1,2})\s+([\p{Thai}]+)\s+(\d{4})/u', $date, $mThai)) {
    $day = (int)$mThai[1];
    $monthName = trim($mThai[2]);
    $year = (int)$mThai[3];

    if ($day < 1 || $day > 31) {
      return '';
    }

    if ($year < 2400) {
      $year += 543;
    }

    $spaces = str_repeat(' ', max(1, (int)$spaceAfterDay));
    return thai_digits($day . $spaces . $monthName . ' ' . $year);
  }

  // รองรับ YYYY-MM-DD, YYYY/MM/DD, YYYY-MM-DD HH:mm:ss และปี พ.ศ. เช่น 2568-02-05
  if (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $date, $mDate)) {
    $y = (int)$mDate[1];
    $month = (int)$mDate[2];
    $day = (int)$mDate[3];

    if ($y > 2400) {
      $y -= 543;
    }

    if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || !checkdate($month, $day, $y)) {
      return '';
    }

    $spaces = str_repeat(' ', max(1, (int)$spaceAfterDay));
    return thai_digits($day . $spaces . $months[$month] . ' ' . ($y + 543));
  }

  return '';
}

function coop_student_rows($studentsJson, $studentListText) {
  $rows = [];
  $decoded = json_decode((string)$studentsJson, true);

  if (is_array($decoded)) {
    foreach ($decoded as $student) {
      if (!is_array($student)) continue;

      $name = trim((string)($student['name'] ?? $student['student_name'] ?? $student['fullname'] ?? ''));
      $id = trim((string)($student['student_id'] ?? $student['id'] ?? ''));

      if ($name === '' && $id === '') continue;
      $rows[] = ['name' => $name, 'id' => $id];
    }
  }

  if (empty($rows)) {
    $lines = preg_split('/\r\n|\r|\n/', (string)$studentListText);
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') continue;

      $parts = preg_split('/\s*รหัสนักศึกษา\s*/u', $line, 2);
      $name = trim($parts[0] ?? '');
      $id = trim($parts[1] ?? '');

      $rows[] = ['name' => $name, 'id' => $id];
    }
  }

  return $rows;
}

/* --------------------------------------------------
   Mapping ตัวแปรหลักจาก document_values
-------------------------------------------------- */
$docDate = trim((string)($valueMap[1] ?? '')) !== ''
  ? trim((string)$valueMap[1])
  : trim((string)($document['doc_date'] ?? ''));
$ownerName = $valueMap[2] ?? "";
$position = $valueMap[3] ?? "";
$joinType = $valueMap[4] ?? "";
$courseName = $valueMap[5] ?? "";
$joinDates = $valueMap[6] ?? "";
$location = $valueMap[7] ?? "";
$amountStr = $valueMap[8] ?? "";
$vehicle = $valueMap[9] ?? "";
$faculty = $valueMap[10] ?? "";
$department = $valueMap[11] ?? "";
$eventDate  = $valueMap[12] ?? "";
$eventPlace = $valueMap[13] ?? "";

/* --------------------------------------------------
   Mapping สำหรับแบบประเมินสถานประกอบการสหกิจศึกษา
-------------------------------------------------- */
$coopSubject = $valueKeyMap['coop_subject'] ?? ($valueMap[70] ?? ($valueMap[14] ?? ($document['subject'] ?? 'ขอความอนุเคราะห์ตอบแบบประเมินและแบบสำรวจนักศึกษาปฏิบัติงานสหกิจศึกษา')));
$coopToPerson = $valueKeyMap['coop_to_person'] ?? ($valueMap[71] ?? ($valueMap[26] ?? ''));
$coopOrganizationName = $valueKeyMap['coop_organization_name'] ?? ($valueMap[72] ?? 'หน่วยงานของท่าน');
$coopStudentCount = $valueKeyMap['coop_student_count'] ?? ($valueMap[73] ?? '');
$coopStudentsJson = $valueKeyMap['coop_students_json'] ?? ($valueMap[74] ?? '');
$coopStudentListText = $valueKeyMap['coop_student_list_text'] ?? ($valueMap[75] ?? '');
$coopPeriod = $valueKeyMap['coop_period'] ?? ($valueMap[76] ?? '');
$coopStartDate = $valueKeyMap['coop_start_date'] ?? ($valueMap[77] ?? '');
$coopEndDate = $valueKeyMap['coop_end_date'] ?? ($valueMap[78] ?? '');
$coopAdvisorName = $valueKeyMap['coop_advisor_name'] ?? ($valueMap[79] ?? 'พนักงานที่ปรึกษา');
$coopAdditionalDetail = $valueKeyMap['coop_additional_detail'] ?? ($valueMap[81] ?? '');
$coopReceiverName = $valueKeyMap['coop_receiver_name'] ?? ($valueMap[82] ?? 'ผู้ช่วยศาสตราจารย์ ดร.กฤษฎากร บุดดาจันทร์');
$coopReceiverPosition = $valueKeyMap['coop_receiver_position'] ?? ($valueMap[83] ?? 'คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม');

if ($coopStudentListText === '' && $coopStudentsJson !== '') {
  $decodedStudents = json_decode($coopStudentsJson, true);
  if (is_array($decodedStudents)) {
    $studentLines = [];
    foreach ($decodedStudents as $student) {
      $studentName = trim((string)($student['name'] ?? ''));
      $studentId = trim((string)($student['student_id'] ?? ($student['id'] ?? '')));
      if ($studentName === '' && $studentId === '') continue;
      $studentLines[] = trim($studentName . ($studentId !== '' ? ' รหัสนักศึกษา ' . $studentId : ''));
    }
    $coopStudentListText = implode("
", $studentLines);
  }
}

if ($coopPeriod === '' && ($coopStartDate !== '' || $coopEndDate !== '')) {
  $coopPeriod = trim($coopStartDate . ($coopEndDate !== '' ? ' ถึง ' . $coopEndDate : ''));
}

$coopStudentRows = coop_student_rows($coopStudentsJson, $coopStudentListText);

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
$subject = $document["subject"] ?? "";

/* --------------------------------------------------
   คำนวณวันที่ไทย, งบประมาณ
-------------------------------------------------- */
$dateCandidates = [
  $docDate ?? '',
  $valueMap[1] ?? '',
  $valueKeyMap['doc_date'] ?? '',
  $valueKeyMap['document_date'] ?? '',
  $valueKeyMap['memo_date'] ?? '',
  $valueKeyMap['date'] ?? '',
  $document['doc_date'] ?? '',
];

$displayDocDate = '';
foreach ($dateCandidates as $dateCandidate) {
  $displayDocDate = thai_doc_date_format($dateCandidate, 2);
  if ($displayDocDate !== '') {
    break;
  }
}

// กันกรณีเอกสารเก่าที่ไม่มีวันที่ใน document_values/documents.doc_date
// อย่างน้อยต้องมีวันที่แสดง ไม่ให้หน้าเอกสารว่าง
if ($displayDocDate === '') {
  $displayDocDate = thai_doc_date_format(date('Y-m-d'), 2);
}

$thaiDocDate = '';
foreach ($dateCandidates as $dateCandidate) {
  $thaiDocDate = thai_doc_date_format($dateCandidate, 1);
  if ($thaiDocDate !== '') {
    break;
  }
}

if ($thaiDocDate === '') {
  $thaiDocDate = thai_doc_date_format(date('Y-m-d'), 1);
}
$prettyAmount = $amountStr !== "" ? number_format((float) $amountStr, 2) : "";

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
  $thaiYear = thai_digits((int) substr($docDate, 0, 4) + 543);
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


  /* ✅ เพิ่มพื้นที่ท้ายกระดาษเฉพาะหน้าโชว์บนเว็บเท่านั้น
     ไม่ใช้ body.pdf-rendering แล้ว เพราะจะทำให้พื้นที่ท้ายกระดาษหายระหว่างโหลด PDF
     ตอนสร้าง PDF จะใส่ class pdf-page-clone ให้ clone แทน เพื่อไม่ให้ CSS นี้กระทบ PDF */
  .page:not(.pdf-page-clone) {
    height: auto !important;
    min-height: 1123px !important;
    padding-bottom: 130px !important;
    margin-bottom: 90px !important;
  }

  .page:not(.pdf-page-clone) .footer-actions {
    margin-top: 24px !important;
    margin-bottom: 20px !important;
    flex-wrap: wrap !important;
  }

  .pdf-page-clone {
    padding-bottom: 45px !important;
    margin-bottom: 0 !important;
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
    src: url('/fonts/THSarabunPSK.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
  }

  @font-face {
    font-family: 'TH SarabunPSK';
    src: url('/fonts/THSarabunPSK-Bold.ttf') format('truetype');
    font-weight: bold;
    font-style: normal;
  }

  @font-face {
    font-family: 'TH SarabunPSK';
    src: url('fonts/THSarabunPSK.ttf') format('truetype');
  }

  body {
    font-family: 'TH SarabunPSK', sans-serif;
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
  </style>
</head>

<body>
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
      title: "โหมดอ่านอย่างเดียว",
      text: "คุณไม่มีสิทธิ์แก้ไขเอกสารนี้",
      icon: "info",
      confirmButtonText: "ตกลง"
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
    <form id="updateForm" action="/Pro_letter/documents/update_memo.php" method="post">
      <input type="hidden" name="header_text" id="hidden_header_text" value="<?= h($header_text) ?>">
      <input type="hidden" name="doc_no" id="hidden_doc_no" value="<?= h($doc_no) ?>">

      <!-- hidden input ครบทุก field_id -->
      <input type="hidden" name="redirect_back" value="<?= htmlspecialchars($referer) ?>">

      <input type="hidden" name="document_id" value="<?= h($document['document_id']) ?>">

      <input type="hidden" name="document_type" value="infor_coop_evaluation">
      <input type="hidden" name="form_type" value="coop_evaluation">
      <input type="hidden" name="redirect_to" value="form_memo_coop_evaluation.php">
      <input type="hidden" name="target_form" value="infor_coop_evaluation.php">
      <input type="hidden" name="template_id" value="<?= h($document['template_id'] ?? 1) ?>">

      <!-- สำคัญ: ให้ doc_date เป็นรูปแบบเดิม (YYYY-MM-DD) ที่ดึงมาจาก DB -->
      <input type="hidden" name="doc_date" id="hidden_doc_date" value="<?= h($docDate) ?>">

      <input type="hidden" name="fullname" id="hidden_ownerName" value="<?= h($ownerName) ?>">
      <input type="hidden" name="position" id="hidden_position" value="<?= h($position) ?>">

      <!-- ส่ง purpose เป็นรหัส ไม่ใช่ข้อความไทย -->
      <input type="hidden" name="purpose" id="hidden_joinType" value="coop_evaluation">

      <input type="hidden" name="event_title" id="hidden_courseName" value="<?= h($courseName) ?>">


      <input type="hidden" name="range_date" id="hidden_joinDates" value="<?= h($joinDates) ?>">
      <input type="hidden" name="place" id="hidden_location" value="<?= h($location) ?>">
      <input type="hidden" name="amount" id="hidden_amountStr" value="<?= h($amountStr) ?>">
      <input type="hidden" name="car_plate" id="hidden_vehicle" value="<?= h($vehicle) ?>">
      <input type="hidden" name="faculty" id="hidden_faculty" value="<?= h($faculty) ?>">
      <input type="hidden" name="department" id="hidden_department" value="<?= h($department) ?>">

      <input type="hidden" name="subject" value="<?= h($coopSubject) ?>">
      <input type="hidden" name="to_person" value="<?= h($coopToPerson) ?>">
      <input type="hidden" name="organization_name" value="<?= h($coopOrganizationName) ?>">
      <input type="hidden" name="student_count" value="<?= h($coopStudentCount) ?>">
      <input type="hidden" name="student_list_json" value="<?= h($coopStudentsJson) ?>">
      <input type="hidden" name="student_list_text" value="<?= h($coopStudentListText) ?>">
      <input type="hidden" name="coop_period" value="<?= h($coopPeriod) ?>">
      <input type="hidden" name="coop_start_date" value="<?= h($coopStartDate) ?>">
      <input type="hidden" name="coop_end_date" value="<?= h($coopEndDate) ?>">
      <input type="hidden" name="advisor_name" value="<?= h($coopAdvisorName) ?>">
      <input type="hidden" name="evaluation_email" value="">
      <input type="hidden" name="additional_detail" value="<?= h($coopAdditionalDetail) ?>">
      <input type="hidden" name="receiver_name" value="<?= h($coopReceiverName) ?>">
      <input type="hidden" name="receiver_position" value="<?= h($coopReceiverPosition) ?>">

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
    padding-top:106px;
    white-space:nowrap;
  ">
          ที่ อว ๗๑๒๐/๗๑๖
        </div>

        <!-- ครุฑ -->
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


        <!-- ที่อยู่ -->
        <div style="
  font-size:15.5pt;
  line-height:1.28;

  padding-top:107px;

  padding-left:40px;

  width:380px;

  text-align:left;
">

          <div style="
      position:relative;
      top:-5px;
  ">
            คณะเทคโนโลยีและการจัดการอุตสาหกรรม
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

  margin-top:20px;

  margin-bottom:16px;

  position:relative;

  left:55px;
">
        <?php
          $dateToShow = trim((string)($displayDocDate ?? ''));
          if ($dateToShow === '') {
            $dateToShow = thai_doc_date_format($docDate ?: ($valueMap[1] ?? '') ?: ($document['doc_date'] ?? '') ?: date('Y-m-d'), 2);
          }
          echo h($dateToShow);
        ?>
      </div>

      <div style="
  font-family:'TH SarabunPSK';
  font-size:16pt;
  line-height:1.15;
  color:#111;
">

        <!-- เรื่อง -->
        <div style="
    display:flex;
    margin-bottom:4px;
    font-size:16pt;
    line-height:1.25;
">
          <div style="width:55px;">เรื่อง</div>

          <div>
            <?= h($coopSubject ?: 'ขอความอนุเคราะห์ตอบแบบประเมินและแบบสำรวจนักศึกษาปฏิบัติงานสหกิจศึกษา') ?>
          </div>
        </div>

        <!-- เรียน -->
        <div style="
    display:flex;
    margin-bottom:6px;
    font-size:16pt;
    line-height:1.25;
">
          <div style="width:55px;">เรียน</div>

          <div>
            <?= h($coopToPerson ?: 'เลขาธิการ สำนักงานคณะกรรมการการรักษาความมั่นคงปลอดภัยไซเบอร์แห่งชาติ (กสมช.)') ?>
          </div>
        </div>

        <!-- เนื้อหา -->
        <div style="
    font-size:16pt;
    line-height:1.28;
    text-align:justify;
">

          <p style="
        text-indent:2.5cm;
        margin-bottom:4px;
    ">
            ตามที่ <?= h($coopOrganizationName ?: 'หน่วยงานของท่าน') ?>
            ได้ให้ความอนุเคราะห์รับนักศึกษาภาควิชาเทคโนโลยีสารสนเทศ
            คณะเทคโนโลยีและการจัดการอุตสาหกรรม มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ
            วิทยาเขตปราจีนบุรี ได้แก่
          </p>

          <div style="
        margin-left:2.5cm;
        margin-bottom:4px;
        line-height:1.35;
    ">
            <?php if (!empty($coopStudentRows)): ?>
            <?php foreach ($coopStudentRows as $studentRow): ?>
            <div style="display:flex; align-items:baseline; white-space:nowrap;">
              <span style="display:inline-block; min-width:5cm;">
                <?= h(thai_digits($studentRow['name'] ?? '')) ?>
              </span>
              <span style="display:inline-block; min-width:2.35cm;">
                รหัสนักศึกษา
              </span>
              <span style="display:inline-block;">
                <?= h(thai_digits($studentRow['id'] ?? '')) ?>
              </span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div style="display:flex; align-items:baseline; white-space:nowrap;">
              <span style="display:inline-block; min-width:8.8cm;">
                นายปุณนที ปิ่นวิเศษ
              </span>
              <span style="display:inline-block; min-width:2.35cm;">
                รหัสนักศึกษา
              </span>
              <span style="display:inline-block;">
                ๖๕-๐๖๐๒๑๖-๓๐๐๓-๘
              </span>
            </div>
            <?php endif; ?>
          </div>

          <p style="
        text-indent:0.0cm;
        margin-bottom:4px;
    ">
            เข้าปฏิบัติงานสหกิจศึกษาในหน่วยงานของท่าน ตั้งแต่วันที่
            <?= h(thai_digits($coopPeriod ?: '๓ พฤศจิกายน ๒๕๖๘ ถึง ๒๗ กุมภาพันธ์ ๒๕๖๙')) ?>
          </p>

          <p style="
        text-indent:2.5cm;
        margin-bottom:4px;
    ">
            ในการนี้ ภาควิชาเทคโนโลยีสารสนเทศ ขอความอนุเคราะห์ตอบแบบประเมินผลรายงาน
            การปฏิบัติงานของนักศึกษาสหกิจศึกษา และแบบสำรวจคุณลักษณะของนักศึกษาปฏิบัติงาน
            สหกิจศึกษาที่พึงประสงค์ตามความต้องการของสถานประกอบการ (ในปีถัดไป)
            โดยภาควิชาขออนุญาตส่งแบบประเมินและแบบสำรวจดังกล่าวให้กับ “<?= h($coopAdvisorName ?: 'พนักงานที่ปรึกษา') ?>”
            ทั้งนี้ ข้อมูลที่ได้จากแบบประเมินและแบบสำรวจจะนำมารวบรวม วิเคราะห์ และสรุปผล
            ซึ่งภาควิชาจะนำข้อมูลมาเป็นแนวทางสำหรับการดำเนินการครั้งต่อไป
          </p>



          <p style="
        text-indent:2.5cm;
        margin-bottom:4px;
    ">
            สุดท้ายนี้ ภาควิชาเทคโนโลยีสารสนเทศ ขอขอบคุณในความอนุเคราะห์ของท่านเป็นอย่างยิ่ง
            และหวังว่าจะได้รับความอนุเคราะห์จากท่านอีกในโอกาสต่อไป
          </p>

          <p style="
        text-indent:2.5cm;
        margin-bottom:8px;
    ">
            จึงเรียนมาเพื่อโปรดอนุญาต และพิจารณาแจ้งผู้เกี่ยวข้องดำเนินการต่อไป
          </p>

        </div>

        <!-- ลงชื่อ -->
        <div style="
    width:100%;
    text-align:center;
    margin-top:14px;
    line-height:1.3;
    font-size:16pt;
">
          <div>ขอแสดงความนับถือ</div>

          <div style="margin-top:42px;">
            (<?= h($coopReceiverName ?: 'ผู้ช่วยศาสตราจารย์ ดร.กฤษฎากร บุดดาจันทร์') ?>)
          </div>

          <div>
            <?= h($coopReceiverPosition ?: 'คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม') ?>
          </div>
        </div>

        <!-- footer -->
        <div style="
    margin-top:20px;
    font-size:14pt;
    line-height:1.35;
">
          ภาควิชาเทคโนโลยีสารสนเทศ<br>
          โทร. ๐ ๓๗๒๑ ๗๓๔๐ ต่อ ๗๐๖๕-๖<br>
          ไปรษณีย์อิเล็กทรอนิกส์ : it@itm.kmutnb.ac.th<br>
          <br>
        </div>

      </div>

      <!-- <div style="font-family:'TH SarabunPSK'; font-size:16pt; line-height:1.2;"> เรียน <?= h($hdr_to) ?> </div>
            <div class="content-block single align-to-dean"> เพื่อโปรดพิจารณาอนุมัติ </div>
            <div class="content-block single align-to-dean" style="margin-top:50px;;"> (ผู้ช่วยศาสตราจารย์ ดร. ขนิษฐา
                นามี)<br /> หัวหน้าภาควิชาเทคโนโลยีสารสนเทศ </div> -->
      <div class="footer-actions">

        <!-- 🔵 ปุ่มแรก: พิมพ์/ดูตัวอย่าง (ทุก role ต้องมี และอยู่ลำดับแรก) -->
        <button type="button" onclick="downloadPdf()"
          class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          ดาวน์โหลด PDF
        </button>
        <!-- 🟩 USER: ปุ่มแก้ไขเอกสาร -->
        <?php if ($roleId === 3): ?>
        <a href="/Pro_letter/documents/infor_coop_evaluation.php?id=<?= urlencode((string)$document['document_id']) ?>&edit=1"
          class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          แก้ไขเอกสาร
        </a>
        <?php endif; ?>

        <!-- 🟦 OFFICER & ADMIN -->
        <?php if ($isAdmin || $isOfficer): ?>
        <!-- ปุ่มแก้ไขเอกสาร -->
        <a href="/Pro_letter/documents/infor_coop_evaluation.php?id=<?= urlencode((string)$document['document_id']) ?>&edit=1"
          class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          แก้ไขเอกสาร
        </a>

        <!-- ปุ่มผ่านการตรวจสอบ -->
        <button type="button" onclick="updateStatus('approved')"
          class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          ผ่านการตรวจสอบ
        </button>

        <!-- ปุ่มไม่ผ่าน -->
        <button type="button" onclick="updateStatus('rejected')"
          class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          ไม่ผ่าน
        </button>

        <?php endif; ?>

        <!-- ปุ่มกลับหน้าหลัก (ทุก role มี) -->
        <a href="<?= $homePath ?>"
          class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          กลับหน้าหลัก
        </a>

      </div>

    </form>
  </main>
  <?php if ($readonly && !($isAdmin || $isOfficer)): ?>
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

    if (errType === "no_permission") {
      Swal.fire({
        title: "ไม่มีสิทธิ์แก้ไขเอกสารนี้",
        html: `
        <div style="font-size: 1.15rem; line-height: 1.6;">
          คุณไม่มีสิทธิ์ในการแก้ไขเอกสารนี้<br>
          ต้องการกลับหน้าหลักหรืออยู่ต่อ?
        </div>
      `,
        icon: "error",
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
  async function downloadPdf() {
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
        clone.classList.add("pdf-page-clone");

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

        const canvas = await html2canvas(clone, {
          scale: 4,
          useCORS: true,
          allowTaint: true,
          backgroundColor: "#ffffff",
          windowWidth: 794,
          windowHeight: 1123,
          scrollX: 0,
          scrollY: 0
        });

        document.body.removeChild(clone);

        const imgData = canvas.toDataURL("image/png");

        if (i > 0) {
          pdf.addPage();
        }

        pdf.addImage(imgData, "PNG", 0, 0, 210, 297);
      }

      pdf.save("coop_evaluation_<?= (int)$docId ?>.pdf");

    } catch (error) {
      console.error(error);
      alert("สร้าง PDF ไม่สำเร็จ กรุณากด F12 ดู Console");
    } finally {
      // ไม่ต้องลบ class จาก body แล้ว เพราะไม่ได้ใส่ class ให้ body ระหว่างโหลด PDF
    }
  }
  </script>
</body>

</html>