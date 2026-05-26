<!-- ขออนุมัติตัวบุคคลเพื่อไปนำเสนอผลงานวิจัยในงานประชุมวิชาการระดับนานาชาติACIE 2025 
 /Pro_letter/form_Memo/form_memo_academic_1.php -->
<?php
session_start();
require_once dirname(__DIR__) . '/functions.php';

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

$editQuestionUrl = "/Pro_letter/documents/infor_academic_presentation.php?id=" . (int)$docId . "&edit=1";

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
$q = $pdo->prepare("SELECT field_id, value_text FROM document_values WHERE document_id = :id");
$q->execute([':id' => $docId]);

$valueMap = [];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $valueMap[(int) $row['field_id']] = $row['value_text'];
}
function cleanSectionSubject($text)
{
  $text = trim(preg_replace('/\s+/u', ' ', (string)$text));

  $removePrefixList = [
    'ขออนุมัติตัวบุคคลเพื่อไป',
    'ขออนุมัติตัวบุคคลเข้าร่วม',
    'ขออนุมัติตัวบุคคลเพื่อเข้าร่วม',
  ];

  foreach ($removePrefixList as $prefix) {
    if (mb_strpos($text, $prefix, 0, 'UTF-8') === 0) {
      return trim(mb_substr($text, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8'));
    }
  }

  return $text;
}

function splitSubjectLines($text, $limit = 82)
{
  $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
  $lines = [];

  while (mb_strlen($text, 'UTF-8') > $limit) {
    $cut = mb_substr($text, 0, $limit, 'UTF-8');
    $spacePos = mb_strrpos($cut, ' ', 0, 'UTF-8');

    if ($spacePos !== false && $spacePos > 25) {
      $lines[] = trim(mb_substr($text, 0, $spacePos, 'UTF-8'));
      $text = trim(mb_substr($text, $spacePos + 1, null, 'UTF-8'));
    } else {
      $lines[] = trim($cut);
      $text = trim(mb_substr($text, $limit, null, 'UTF-8'));
    }
  }

  if ($text !== '') {
    $lines[] = $text;
  }

  return $lines;
}
function thaiBahtText($amount)
{
  $amount = number_format((float)$amount, 2, '.', '');
  [$number, $satang] = explode('.', $amount);

  $txtNumArr = ['ศูนย์','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า'];
  $txtDigitArr = ['','สิบ','ร้อย','พัน','หมื่น','แสน','ล้าน'];

  $convert = function($num) use (&$convert, $txtNumArr, $txtDigitArr) {
    $num = (string)((int)$num);
    $len = strlen($num);
    $result = '';

    for ($i = 0; $i < $len; $i++) {
      $n = (int)$num[$i];
      $pos = $len - $i - 1;
      if ($n === 0) continue;

      if ($pos === 0 && $n === 1 && $len > 1) {
        $result .= 'เอ็ด';
      } elseif ($pos === 1 && $n === 2) {
        $result .= 'ยี่';
      } elseif ($pos === 1 && $n === 1) {
        $result .= '';
      } else {
        $result .= $txtNumArr[$n];
      }

      $result .= $txtDigitArr[$pos];
    }

    return $result;
  };

  $bahtText = ((int)$number === 0) ? 'ศูนย์บาท' : $convert($number) . 'บาท';

  return ((int)$satang === 0)
    ? $bahtText . 'ถ้วน'
    : $bahtText . $convert($satang) . 'สตางค์';
}

function thai_date($ymd)
{
  if (!$ymd || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd))
    return "";
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
  return intval($d) . " " . $months[intval($m)] . " " . (intval($y) + 543);
}

/* --------------------------------------------------
   Mapping ตัวแปรหลักจาก document_values
-------------------------------------------------- */
$docDate = $valueMap[1] ?? $document['doc_date'];
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
$displayFaculty = trim($faculty) !== '' ? trim($faculty) : "คณะเทคโนโลยีและการจัดการอุตสาหกรรม";
$displayDepartment = trim($department) !== '' ? trim($department) : "เทคโนโลยีสารสนเทศ";
$displayDepartmentFull = "ภาควิชา" . $displayDepartment;
$displayFacultyDean = "คณบดี" . $displayFaculty;
$academicTopic = $valueMap[13] ?? "";
$memoSubject   = $valueMap[14] ?? ($document['subject'] ?? "");
$sectionSubject = cleanSectionSubject($memoSubject ?: $subject ?: 'ขออนุมัติ...');


$academicLevel = $valueMap[15] ?? "";
$eventDate     = $valueMap[16] ?? "";
$noCost = (($valueMap[12] ?? '0') === '1');

$budgetStmt = $pdo->prepare("
  SELECT item_type, description, amount
  FROM budget_items
  WHERE document_id = :id
  ORDER BY item_id ASC
");
$budgetStmt->execute([':id' => $docId]);
$budgetItems = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

$budgetTotal = 0;
foreach ($budgetItems as $item) {
  $budgetTotal += (float) $item['amount'];
}

$hasExpense = (!$noCost && !empty($budgetItems) && $budgetTotal > 0);
$hasCar = trim($vehicle) !== '';

$displayAmount = $budgetTotal > 0 ? $budgetTotal : (float) str_replace(',', '', $amountStr);
$displayAmountNumber = number_format($displayAmount, 2);
$displayAmountThai = thaiBahtText($displayAmount);
/* --------------------------------------------------
   Mapping joinType → purposeCode (รหัส)
-------------------------------------------------- */
$purposeCode = 'other';

switch (trim($joinType)) {
  case 'นำเสนอผลงานวิจัย':
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
/* ===== ชื่อไฟล์ดาวน์โหลดภาษาไทย (ใช้กับ PDF และ Word) ===== */
$downloadSubject = trim((string) $subject);
if ($downloadSubject === '') {
  $downloadSubject = 'บันทึกข้อความ';
}
$downloadSubject = preg_replace('/[\\\\\/\:\*\?\"\<\>\|\r\n\t]+/u', ' ', $downloadSubject);
$downloadSubject = preg_replace('/\s+/u', ' ', $downloadSubject);
$downloadSubject = trim($downloadSubject);

if (function_exists('mb_strlen') && mb_strlen($downloadSubject, 'UTF-8') > 80) {
  $downloadSubject = mb_substr($downloadSubject, 0, 80, 'UTF-8');
}

$downloadBaseName = 'บันทึกข้อความ_' . $downloadSubject . '_เลขที่_' . (int) $docId;
$pdfDownloadName = $downloadBaseName . '.pdf';
$wordDownloadName = $downloadBaseName . '.docx';


/* --------------------------------------------------
   คำนวณวันที่ไทย, งบประมาณ
-------------------------------------------------- */
$thaiDocDate = thai_date($docDate);
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
    padding: 60px 70px 50px 100px;
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

  .memo-title-row {
    position: relative;
    height: 1.65cm;
    margin-bottom: 0.35em;
  }

  .memo-title-row .garuda-img {
    position: absolute;
    left: 0;
    top: 0;
    height: 1.6cm;
    width: auto;
  }

  .memo-title-row .doc-title {
    position: absolute;
    left: 0;
    right: 0;
    top: 0.50cm;
    margin: 0 !important;
    padding: 0 !important;
    font-family: "TH SarabunPSK";
    font-size: 30pt;
    font-weight: bold;
    line-height: 1 !important;
    text-align: center;
    transform: translateX(-0.3cm);
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
    margin-left: 0;
    line-height: 1;
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

  .view-document .chip {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
    display: inline !important;
    white-space: normal !important;
  }

  body:not(.view-document) .chip {
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
    /* ⭐ กดลงมาอีกนิดเพื่อให้ชิดเส้นมากที่สุด */
  }



  .expense-info-block {
    text-align: left !important;
    width: 720px;
    max-width: 100%;
    margin: 0 auto;
  }

  .expense-info-block .flex {
    display: flex;
    align-items: flex-start;
    text-align: left !important;
  }

  .expense-info-block .w-\[180px\] {
    width: 180px;
    flex: 0 0 180px;
    text-align: left !important;
  }

  .expense-info-block .flex-1 {
    flex: 1;
    text-align: left !important;
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
      padding: 0.5cm 1cm 2cm 2.2cm !important;
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

  .expense-title-section {
    margin-top: 0;
    margin-bottom: 0.8cm;
  }

  .expense-main-title {
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    font-weight: bold;
    text-align: center;
    margin: 0 0 0cm 0;
  }

  .subject-wrap {
    flex: 1;
  }

  .subject-line {
    min-height: 22px;
    line-height: 1.05;
    border-bottom: 2px dotted #000;
    padding-left: 4px;
    padding-top: 4px;
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    font-weight: 300;
    white-space: normal;
    word-break: break-word;
  }

  .subject-text {
    display: inline-block;
    position: relative;
    top: 4px;

    /* ขยับเฉพาะตัวอักษรชื่อเรื่อง เส้นประไม่ขยับ */
    margin-left: 0.35cm;
  }

  /* ปรับเฉพาะข้อความบนเส้นประส่วนหัวเอกสาร ไม่กระทบส่วน "เรื่อง" */
  .doc-row:not(.subject-row) .dot-line > .chip {
    display: inline-block;
    position: relative;
    top: -2px;
    line-height: 1;
  }

  .subject-inline {
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
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

<body class="view-document">
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
    const printBtn = document.querySelector("button[onclick='downloadPdf()']");
    if (printBtn) printBtn.innerText = "โหลด PDF (โหมดอ่านอย่างเดียว)";

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
    <form id="updateForm" action="update_memo.php" method="post">
      <input type="hidden" name="header_text" id="hidden_header_text" value="<?= h($header_text) ?>">
      <input type="hidden" name="doc_no" id="hidden_doc_no" value="<?= h($doc_no) ?>">

      <!-- hidden input ครบทุก field_id -->
      <input type="hidden" name="redirect_back" value="<?= htmlspecialchars($referer) ?>">

      <input type="hidden" name="document_id" value="<?= h($document['document_id']) ?>">

      <!-- สำคัญ: ให้ doc_date เป็นรูปแบบเดิม (YYYY-MM-DD) ที่ดึงมาจาก DB -->
      <input type="hidden" name="doc_date" id="hidden_doc_date" value="<?= h($docDate) ?>">

      <input type="hidden" name="fullname" id="hidden_ownerName" value="<?= h($ownerName) ?>">
      <input type="hidden" name="position" id="hidden_position" value="<?= h($position) ?>">

      <!-- ส่ง purpose เป็นรหัส ไม่ใช่ข้อความไทย -->
      <input type="hidden" name="purpose" id="hidden_joinType" value="<?= h($purposeCode) ?>">

      <input type="hidden" name="event_title" id="hidden_courseName" value="<?= h($courseName) ?>">
      <input type="hidden" name="memo_subject" id="hidden_subject" value="<?= h($memoSubject) ?>">
      <input type="hidden" name="academic_topic" id="hidden_academicTopic" value="<?= h($academicTopic) ?>">
      <input type="hidden" name="academic_level" id="hidden_academicLevel" value="<?= h($academicLevel) ?>">
      <input type="hidden" name="event_date" id="hidden_eventDate" value="<?= h($eventDate) ?>">

      <input type="hidden" name="range_date" id="hidden_joinDates" value="<?= h($joinDates) ?>">
      <input type="hidden" name="place" id="hidden_location" value="<?= h($location) ?>">
      <input type="hidden" name="amount" id="hidden_amountStr" value="<?= h($amountStr) ?>">
      <input type="hidden" name="car_plate" id="hidden_vehicle" value="<?= h($vehicle) ?>">
      <input type="hidden" name="faculty" id="hidden_faculty" value="<?= h($faculty) ?>">
      <input type="hidden" name="department" id="hidden_department" value="<?= h($department) ?>">

      <!-- ตัวเลือกช่วงวันที่: ใช้ range เป็นค่า default ตาม UI ปัจจุบัน -->
      <input type="hidden" name="date_option" id="hidden_dateOption" value="range">
      <input type="hidden" name="single_date" id="hidden_singleDate" value="">


      <!-- หัวบันทึก -->
      <div class="memo-title-row">
        <img src="/Pro_letter/assets/img/garuda.jpg" class="garuda-img" />
        <h1 class="doc-title">บันทึกข้อความ</h1>
      </div>

      <!-- ส่วนราชการ -->
      <div class="doc-row">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;">ส่วนราชการ</div>
        <div class="dot-line">
          <span class="chip" contenteditable="true" data-target="header_text">
            <?= h($header_text ?: 'คณะ... ภาค... โทร...') ?>
          </span>
        </div>
      </div>

      <div class="doc-row row-ty-date">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;">ที่</div>

        <div class="dot-line ty-left">
          <span class="chip" contenteditable="true" data-target="doc_no">
            <?= h($doc_no ?: 'ทส.486/2568') ?>
          </span>
        </div>

        <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;">วันที่</div>

        <div class="dot-line ty-right">
          <span class="chip" contenteditable="true" data-target="doc_date_display">
            <?= h($thaiDocDate ?: '') ?>
          </span>
        </div>
      </div>


      <!-- เรื่อง -->
      <?php
  $mainSubjectText = $memoSubject ?: $subject ?: 'ขออนุมัติ...';
  $mainSubjectLines = splitSubjectLines($mainSubjectText, 82);
?>
      <div class="doc-row" style="align-items:flex-start;">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>

        <div class="subject-wrap">
          <?php foreach ($mainSubjectLines as $line): ?>
          <div class="subject-line">
            <span class="subject-text"><?= h($line) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>


      <!-- บรรทัด “เรียน ...” -->
      <div class="content-block single" style="
      display:flex;
      align-items:baseline;
      font-size:16pt;
      font-weight:400;
      line-height:1.05;
    ">
        <span style="
        display:inline-block;
        font-size:16pt;
        font-weight:400;
        width:1.05cm;
        flex:0 0 1.05cm;
        line-height:1.05;
      ">เรียน</span>

        <span style="
        display:inline-block;
        margin-left:0.35cm;
        font-size:16pt;
        font-weight:400;
        line-height:1.05;
      "><?= h($displayFacultyDean) ?></span>
      </div>


      <!-- ย่อหน้า 1 -->
      <div class="content-block paragraph">
        ตามที่ ข้าพเจ้า
        <span class="chip" contenteditable="true" data-target="ownerName">
          <?= h($ownerName ?: '................................') ?>
        </span>
        พนักงานมหาวิทยาลัย สังกัด<?= h($displayDepartmentFull) ?>
        <?= h($displayFaculty) ?> มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ
        วิทยาเขตปราจีนบุรี ได้รับอนุมัติตัวบุคคลให้เข้าร่วมนำเสนอผลงานวิจัย
        <span class="chip" contenteditable="true" data-target="courseName">
          <?= h($courseName ?: 'ในงานประชุมวิชาการระดับนานาชาติ The 5th Asia Conference on Information Engineering (ACIE 2025)') ?>
        </span>
        ในหัวข้อ “<span class="chip" contenteditable="true"
          data-target="academicTopic"><?= h($academicTopic ?: 'API-Based Personal Healthcare Application: Securing Data and Ensuring Patient Privacy') ?></span>”
        ซึ่งจัดขึ้นที่
        <span class="chip" contenteditable="true" data-target="location">
          <?= h($location ?: 'โรงแรม Beyond Kata จังหวัดภูเก็ต') ?>
        </span>
        ในระหว่างวันที่
        <span class="chip" contenteditable="true" data-target="eventDate">
          <?= h($eventDate ?: '10 – 12 มกราคม 2568') ?>
        </span>
        โดยเอกสารงานประชุมวิชาการจะถูกตีพิมพ์อยู่ในฐานข้อมูล Scopus นั้น
      </div>


      <!-- ย่อหน้า 2 -->
      <div class="content-block paragraph">
        การนี้ ข้าพเจ้า จึงมีความประสงค์ขออนุมัติเดินทางเพื่อไปนำเสนอผลงานวิจัย
        ในงานประชุม
        <span class="chip" contenteditable="true" data-target="academicLevel">
          <?= h($academicLevel ?: 'วิชาการระดับนานาชาติ ACIE 2025') ?>
        </span>
        ในระหว่างวันที่
        <span class="chip" contenteditable="true" data-target="duration">
          <?= h($joinDates ?: '9 – 12 มกราคม 2568') ?>
        </span>
        (รวมเวลาเดินทาง) ตามวัน เวลา และสถานที่ดังกล่าว
        โดยการนำเสนอผลงานวิจัยในครั้งนี้เป็นประโยชน์ต่อการพัฒนาการเรียนการสอนงานวิจัย
        และสร้างชื่อเสียงให้กับมหาวิทยาลัย โดยขอใช้งบจัดสรรให้หน่วยงาน
        ประจำปีงบประมาณ พ.ศ.
        <span class="chip" contenteditable="true" data-target="fiscal_year_display">
          <?= h($thaiYear ?: date('Y') + 543) ?>
        </span>
        ในส่วนของ<?= h($displayDepartmentFull) ?> แผนงานจัดการศึกษาระดับอุดมศึกษา
        หมวดค่าใช้สอย (รายละเอียดตามเอกสารแนบ)
      </div>


      <!-- ย่อหน้า 3 -->
      <div class="content-block paragraph">
        จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ
      </div>


      <div class="signature-wrapper">
        <div class="signature-block" id="signatureBlock">
          <div class="sig-name">(ผู้ช่วยศาสตราจารย์ ดร.ขนิษฐา นามี)</div>
          <div class="sig-position">หัวหน้า<?= h($displayDepartmentFull) ?></div>
        </div>
      </div>


      <?php if (!$hasExpense): ?>
      <div class="footer-actions">

        <!-- ปุ่มดาวน์โหลด PDF -->
        <button type="button" onclick="downloadPdf()"
          class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
          ดาวน์โหลด PDF
        </button>

        <!-- ปุ่มดาวน์โหลด Word -->
        <a href="/Pro_letter/documents/download_word_academic_1.php?id=<?= (int)$docId ?>"
          download="<?= h($wordDownloadName) ?>"
          data-word-download="1"
          data-word-filename="<?= h($wordDownloadName) ?>"
          onclick="return downloadWord(this);"
          class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
          ดาวน์โหลด Word
        </a>

        <!-- USER: ปุ่มแก้ไขเอกสาร -->
        <?php if ($canEdit || $roleId === 3 || $isAdmin || $isOfficer): ?>
        <a href="/Pro_letter/documents/infor_academic_presentation.php?id=<?= (int)$docId ?>&edit=1"
          class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
          แก้ไขเอกสาร
        </a>
        <?php endif; ?>


        <!-- ปุ่มกลับหน้าหลัก -->
        <a href="<?= $homePath ?>"
          class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          กลับหน้าหลัก
        </a>

      </div>
      <?php endif; ?>
    </form>
  </main>

  <!-- section ค่าใช้จ่าย -->
  <?php if ($hasExpense): ?>
  <section class="page">
    <div class="memo-title-row">
      <img src="/Pro_letter/assets/img/garuda.jpg" class="garuda-img" />
      <h1 class="doc-title">บันทึกข้อความ</h1>
    </div>

    <div class="doc-row">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ส่วนราชการ</div>
      <div class="dot-line">
        <span class="chip"><?= h($header_text ?: 'คณะ... ภาค... โทร...') ?></span>
      </div>
    </div>

    <div class="doc-row row-ty-date">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ที่</div>
      <div class="dot-line ty-left">
        <span class="chip"><?= h($doc_no ?: 'ทส.486/2568') ?></span>
      </div>

      <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;">วันที่</div>
      <div class="dot-line ty-right">
        <span class="chip"><?= h($thaiDocDate ?: '') ?></span>
      </div>
    </div>

    <?php
      $expenseSubjectText = 'ขออนุมัติค่าใช้จ่ายในการเข้าร่วม' . $sectionSubject;
      $expenseSubjectLines = splitSubjectLines($expenseSubjectText, 82);
    ?>
    <div class="doc-row" style="align-items:flex-start;">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>
      <div class="subject-wrap">
        <?php foreach ($expenseSubjectLines as $line): ?>
        <div class="subject-line"><span class="subject-text"><?= h($line) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="content-block single">
      เรียน <?= h($displayFacultyDean) ?>
    </div>

    <div class="content-block paragraph">
      การนี้ ข้าพเจ้า
      <span class="chip"><?= h($ownerName ?: 'ชื่อ-นามสกุล') ?></span>
      <span class="chip"><?= h($position ?: '') ?></span>
      สังกัดภาควิชา<span class="chip"><?= h($department ?: '...') ?></span>
      <span class="chip"><?= h($faculty ?: '...') ?></span>
      มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
      จึงมีความประสงค์ขออนุมัติค่าใช้จ่ายในการเข้าร่วม
      <span class="chip subject-inline"><?= h($memoSubject ?: $subject ?: 'ขออนุมัติ...') ?></span>
      ระหว่างวันที่ <span class="chip"><?= h($joinDates ?: '') ?></span>
      ณ <span class="chip"><?= h($location ?: '') ?></span>
      วงเงินทั้งสิ้น <span class="chip"><?= h($displayAmountNumber) ?></span> บาท
      (<span class="chip"><?= h($displayAmountThai) ?></span>)
      โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ
      <span class="chip"><?= h($thaiYear ? 'พ.ศ. ' . $thaiYear : 'พ.ศ. ....') ?></span>
      ในส่วนของ<?= h($displayDepartmentFull) ?> แผนงานจัดการศึกษาระดับอุดมศึกษา
      กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย
      <span class="keep">(รายละเอียดตามเอกสารแนบ)</span>
    </div>

    <div class="content-block paragraph">
      จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ
    </div>

    <div class="signature-wrapper">
      <div class="signature-block">
        <div class="sig-name">(<?= h($ownerName ?: '') ?>)</div>
        <div class="sig-position"><?= h($position ?: '') ?></div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- section รถยนต์ -->
  <?php if ($hasCar): ?>
  <section class="page">
    <div class="memo-title-row">
      <img src="/Pro_letter/assets/img/garuda.jpg" class="garuda-img" />
      <h1 class="doc-title">บันทึกข้อความ</h1>
    </div>

    <div class="doc-row">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ส่วนราชการ</div>
      <div class="dot-line">
        <span class="chip"><?= h($header_text ?: 'คณะ... ภาค... โทร...') ?></span>
      </div>
    </div>

    <div class="doc-row row-ty-date">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ที่</div>
      <div class="dot-line ty-left">
        <span class="chip"><?= h($doc_no ?: 'ทส.486/2568') ?></span>
      </div>

      <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;">วันที่</div>
      <div class="dot-line ty-right">
        <span class="chip"><?= h($thaiDocDate ?: '') ?></span>
      </div>
    </div>

    <?php
      $carSubjectText = 'ขออนุมัติใช้รถยนต์ส่วนบุคคลในการเดินทางไปเข้าร่วม' . $sectionSubject;
      $carSubjectLines = splitSubjectLines($carSubjectText, 82);
    ?>
    <div class="doc-row" style="align-items:flex-start;">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>
      <div class="subject-wrap">
        <?php foreach ($carSubjectLines as $line): ?>
        <div class="subject-line"><span class="subject-text"><?= h($line) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="content-block single">
      เรียน <?= h($displayFacultyDean) ?>
    </div>

    <div class="content-block paragraph">
      ตามที่ ข้าพเจ้า
      <span class="chip"><?= h($ownerName ?: 'ชื่อ-นามสกุล') ?></span>
      <span class="chip"><?= h($position ?: '') ?></span>
      สังกัดภาควิชา<span class="chip"><?= h($department ?: '...') ?></span>
      <span class="chip"><?= h($faculty ?: '...') ?></span>
      มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
      จึงมีความประสงค์ที่จะขออนุมัติ
      <span class="chip subject-inline"><?= h($memoSubject ?: $subject ?: 'ชื่อหลักสูตร') ?></span>
      ระหว่างวันที่ <span class="chip"><?= h($joinDates ?: '') ?></span>
      ณ <span class="chip"><?= h($location ?: '') ?></span> นั้น
    </div>

    <div class="content-block paragraph">
      ในการนี้ ข้าพเจ้าจึงขออนุมัติใช้รถยนต์ส่วนบุคคล หมายเลขทะเบียน
      <span class="chip"><?= h($vehicle ?: '...') ?></span>
      ในการเดินทางไป <span class="chip subject-inline"><?= h($memoSubject ?: $subject ?: 'ชื่อหลักสูตร') ?></span>
      ตามวัน เวลา และสถานที่ดังกล่าว ทั้งนี้ โดยให้เป็นไปตามหลักเกณฑ์และวิธีการของมหาวิทยาลัย
    </div>

    <div class="content-block paragraph">
      จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ
    </div>

    <div class="signature-wrapper">
      <div class="signature-block">
        <div class="sig-name">(<?= h($ownerName ?: '') ?>)</div>
        <div class="sig-position"><?= h($position ?: '') ?></div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($hasExpense): ?>
  <div class="page">
    <div class="expense-title-section">
      <h2 class="expense-main-title">
        ประมาณการค่าใช้จ่าย<br>
        การนำเสนอผลงานวิจัยในการประชุมวิชาการ
      </h2>

      <div class="expense-info-block text-[16pt] leading-[1.15]">
        <div class="flex mb-1">
          <div class="w-[180px]">ชื่อ–สกุล</div>
          <div class="flex-1"><?= h($ownerName ?: '-') ?></div>
        </div>

        <div class="flex mb-1">
          <div class="w-[180px]">มหาวิทยาลัยต้นสังกัด</div>
          <div class="flex-1">
            ภาควิชา<?= h($department ?: '-') ?> <?= h($faculty ?: '-') ?><br>
            มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
          </div>
        </div>

        <div class="flex mb-1">
          <div class="w-[180px]">ชื่อการประชุมวิชาการ</div>
          <div class="flex-1"><?= h($courseName ?: '-') ?></div>
        </div>

        <div class="flex mb-1">
          <div class="w-[180px]">วันที่</div>
          <div class="flex-1"><?= h($joinDates ?: '-') ?></div>
        </div>

        <div class="flex mb-1">
          <div class="w-[180px]">สถานที่</div>
          <div class="flex-1"><?= h($location ?: '-') ?></div>
        </div>

        <div class="flex mb-1">
          <div class="w-[180px]">ชื่อผลงานวิจัย</div>
          <div class="flex-1"><?= h($academicTopic ?: '-') ?></div>
        </div>
      </div>
    </div>

    <h2 class="text-[16pt] font-bold mt-4 mb-3 text-left">
      ตารางสรุปค่าใช้จ่ายในการไปนำเสนอผลงานวิจัย
    </h2>

    <table id="expenseTable" style="width:100%; border-collapse:collapse; font-family:'TH SarabunPSK';
    font-size:16pt; line-height:1.15; table-layout:fixed;">
      <tr style="height:28px;">
        <th style="width:75px; border:0.6px solid #000; padding:3px 4px; text-align:center;">ลำดับที่</th>
        <th style="width:65%; border:0.6px solid #000; padding:3px 6px; text-align:center;">รายการ</th>
        <th style="width:120px; border:0.6px solid #000; padding:3px 4px; text-align:center;">จำนวนเงิน (บาท)</th>
      </tr>

      <?php foreach ($budgetItems as $index => $item): ?>
      <tr>
        <td style="border:0.6px solid #000; padding:3px 4px; text-align:center;">
          <?= $index + 1 ?>
        </td>
        <td style="border:0.6px solid #000; padding:3px 8px; text-align:left;">
          <?= nl2br(h($item['description'] ?: $item['item_type'])) ?>
        </td>
        <td style="border:0.6px solid #000; padding:3px 4px; text-align:right;">
          <?= number_format((float) $item['amount'], 2) ?>
        </td>
      </tr>
      <?php endforeach; ?>

      <tr>
        <th style="border:0.6px solid #000;"></th>
        <th style="border:0.6px solid #000; padding:3px 6px; text-align:left;">รวมเป็นเงิน</th>
        <th style="border:0.6px solid #000; padding:3px 4px; text-align:right;">
          <?= number_format($budgetTotal, 2) ?>
        </th>
      </tr>
    </table>

    <div class="footer-actions">

      <!-- ปุ่มดาวน์โหลด PDF -->
      <button type="button" onclick="downloadPdf()"
        class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
        ดาวน์โหลด PDF
      </button>

      <!-- ปุ่มดาวน์โหลด Word -->
      <a href="/Pro_letter/documents/download_word_academic_1.php?id=<?= (int)$docId ?>"
        download="<?= h($wordDownloadName) ?>"
        data-word-download="1"
        data-word-filename="<?= h($wordDownloadName) ?>"
        onclick="return downloadWord(this);"
        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
        ดาวน์โหลด Word
      </a>

      <!-- USER: ปุ่มแก้ไขเอกสาร -->
      <?php if ($roleId === 3 && $canEdit): ?>
      <a href="/Pro_letter/documents/infor_academic_presentation.php?id=<?= (int)$docId ?>&edit=1"
        class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
        แก้ไขเอกสาร
      </a>
      <?php endif; ?>

      <!-- OFFICER & ADMIN -->
      <?php if ($isAdmin || $isOfficer): ?>

      <a href="/Pro_letter/documents/infor_academic_presentation.php?id=<?= (int)$docId ?>&edit=1"
        class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
        แก้ไขเอกสาร
      </a>

      <button type="button" onclick="updateStatus('approved')"
        class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold">
        ผ่านการตรวจสอบ
      </button>

      <button type="button" onclick="updateStatus('rejected')"
        class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-md text-xl font-bold">
        ไม่ผ่านการตรวจสอบ
      </button>

      <?php endif; ?>

      <!-- ปุ่มกลับหน้าหลัก -->
      <a href="<?= $homePath ?>"
        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
        กลับหน้าหลัก
      </a>

    </div>
  </div>
  <?php endif; ?>
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

    const fileName = link.dataset.wordFilename || <?= json_encode($wordDownloadName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    fetch(downloadUrl.toString(), {
        method: "GET",
        credentials: "same-origin"
      })
      .then(response => {
        if (!response.ok) {
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
        clone.classList.add("print-mode");

        clone.style.position = "fixed";
        clone.style.left = "-9999px";
        clone.style.top = "0";
        clone.style.width = "794px";
        clone.style.minHeight = "1123px";
        clone.style.boxSizing = "border-box";
        clone.style.background = "#ffffff";

        const cloneActions = clone.querySelectorAll(".footer-actions");
        cloneActions.forEach(el => el.remove());

        const cloneGaruda = clone.querySelector(".garuda-img");
        if (cloneGaruda) {
          cloneGaruda.style.transform = "translateY(-0.65cm)";
        }

        const cloneTitle = clone.querySelector(".doc-title");
        if (cloneTitle) {
          cloneTitle.style.top = "-0.85cm";
        }

        const cloneTitleRow = clone.querySelector(".memo-title-row");
        if (cloneTitleRow) {
          cloneTitleRow.style.height = "0.8cm";
          cloneTitleRow.style.marginBottom = "0.1cm";
        }

        clone.querySelectorAll(".dot-line").forEach(line => {
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
          chip.style.display = "inline";
          chip.style.background = "transparent";
          chip.style.paddingLeft = "0";
          chip.style.paddingRight = "0";
        });
        // แก้ไขตำแหน่งเส้นประในส่วน "เรื่อง" (ค้นหาบรรทัดประมาณที่ 835)
        clone.querySelectorAll(".subject-line").forEach((line, index) => {
          line.querySelectorAll(".pdf-subject-dot-line").forEach(el => el.remove());

          line.style.position = "relative";
          line.style.height = "auto";
          line.style.minHeight = "20px"; // เพิ่มความสูงขั้นต่ำเพื่อให้มีพื้นที่ดึงเส้นลงมา
          line.style.lineHeight = "1.2"; // ปรับระยะบรรทัดให้โปร่งขึ้นเล็กน้อย
          line.style.paddingLeft = "4px";
          line.style.paddingTop = "0";

          // --- จุดสำคัญ: ปรับค่า padding-bottom เพื่อดึงเส้นประลงมา ---
          // ยิ่งเลขเยอะ เส้นประจะยิ่งอยู่ต่ำลง (ลองปรับจาก 10px เป็น 12px หรือ 14px ตามความพอใจ)
          line.style.paddingBottom = "16px";

          line.style.margin = "0";

          // ปรับเฉพาะ PDF: ถ้าเป็นเส้นเรื่องบรรทัดที่ 2 ขึ้นไป ให้ขยับขึ้น
          if (index > 0) {
            line.style.marginTop = "-10px";
          }

          line.style.borderBottom = "2px dotted #000";
          line.style.overflow = "visible";
          line.style.fontSize = "16pt";
          line.style.fontFamily = "TH SarabunPSK";
          line.style.backgroundImage = "none";

          line.querySelectorAll(".subject-text").forEach(text => {
            text.style.display = "inline-block";
            text.style.position = "relative";

            // --- จุดสำคัญ: ปรับค่า top เพื่อให้ตัวอักษรขยับลงมาวางบนเส้นพอดี ---
            // ถ้าตัวอักษรลอยจากเส้นประมากไป ให้เพิ่มเลขนี้ (เช่น 8px, 9px)
            // ถ้าตัวอักษรจมเส้นประ ให้ลดเลขนี้ลง (เช่น 6px, 5px)
            text.style.top = "4px";

            text.style.zIndex = "3";
            text.style.background = "transparent";
          });
        });
        const expenseTable = clone.querySelector("#expenseTable");

        if (expenseTable) {
          expenseTable.style.borderCollapse = "separate";
          expenseTable.style.borderSpacing = "0";
          expenseTable.style.width = "100%";
          expenseTable.style.tableLayout = "fixed";
          expenseTable.style.background = "#ffffff";

          expenseTable.style.setProperty("border", "none", "important");
          expenseTable.style.setProperty("border-top", "0.5px solid #414141", "important");
          expenseTable.style.setProperty("border-left", "0.5px solid #414141", "important");

          expenseTable.querySelectorAll("th").forEach(th => {
            th.style.setProperty("border", "none", "important");
            th.style.setProperty("border-bottom", "0.5px solid #414141", "important");
            th.style.setProperty("border-right", "0.5px solid #414141", "important");
            th.style.setProperty("background", "#ffffff", "important");
            th.style.setProperty("color", "#000", "important");
            th.style.setProperty("font-weight", "bold", "important");
            th.style.setProperty("vertical-align", "middle", "important");
            th.style.setProperty("text-align", "center", "important");
            th.style.setProperty("line-height", "1.0", "important");
            th.style.setProperty("padding-top", "2px", "important");
            th.style.setProperty("padding-bottom", "12px", "important");
            th.style.setProperty("padding-left", "4px", "important");
            th.style.setProperty("padding-right", "4px", "important");
          });

          expenseTable.querySelectorAll("td").forEach(td => {
            td.style.setProperty("border", "none", "important");
            td.style.setProperty("border-bottom", "0.5px solid #414141", "important");
            td.style.setProperty("border-right", "0.5px solid #414141", "important");
            td.style.setProperty("background", "#ffffff", "important");
            td.style.setProperty("color", "#000", "important");
            td.style.setProperty("vertical-align", "middle", "important");
            td.style.setProperty("line-height", "1.0", "important");
            td.style.setProperty("padding-top", "2px", "important");
            td.style.setProperty("padding-bottom", "12px", "important");
            td.style.setProperty("padding-left", "8px", "important");
            td.style.setProperty("padding-right", "8px", "important");
          });

          const totalRow = expenseTable.querySelector("tr:last-child");
          if (totalRow) {
            totalRow.querySelectorAll("th, td").forEach(cell => {
              cell.style.setProperty("border", "none", "important");
              cell.style.setProperty("border-bottom", "0.5px solid #414141", "important");
              cell.style.setProperty("border-right", "0.5px solid #414141", "important");
              cell.style.setProperty("background", "#ffffff", "important");
              cell.style.setProperty("color", "#000", "important");
              cell.style.setProperty("font-weight", "bold", "important");
              cell.style.setProperty("vertical-align", "middle", "important");
              cell.style.setProperty("line-height", "1.0", "important");
              cell.style.setProperty("padding-top", "2px", "important");
              cell.style.setProperty("padding-bottom", "12px", "important");
            });

            const totalCells = totalRow.querySelectorAll("th, td");
            if (totalCells.length >= 3) {
              totalCells[1].style.setProperty("text-align", "left", "important");
              totalCells[1].style.setProperty("padding-left", "14px", "important");
              totalCells[2].style.setProperty("text-align", "right", "important");
              totalCells[2].style.setProperty("padding-right", "14px", "important");
            }
          }
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
      const text = (e.clipboardData || window.clipboardData)
        .getData('text')
        .replace(/\r?\n/g, ' ');
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
</body>


</html>