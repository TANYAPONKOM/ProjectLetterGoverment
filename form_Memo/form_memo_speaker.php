<!-- /Pro_letter/form_Memo/form_memo_speaker.php ขออนุมัติคัตตัวบุคคลเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ -->
<?php
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

$editQuestionUrl = "/Pro_letter/documents/infor_speaker_workshop.php?id=" . (int)$docId . "&edit=1";

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

function thai_digits($value)
{
  return strtr((string) $value, [
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

function splitSubjectLines($text, $limit = 78)
{
  $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
  if ($text === '') {
    return [];
  }

  $lines = [];
  $minCut = 48;

  // จุดที่ควรตัดบรรทัดเมื่อหัวเรื่องยาวจริง ๆ
  // ไม่ใส่คำว่า "เพื่อ" แล้ว เพราะจะทำให้บรรทัดแรกสั้นเกินและเหลือพื้นที่เยอะ
  $beforeBreakWords = [
    'การจัดการ', 'สำนักงาน', 'ยุคใหม่', 'และ', 'พร้อม', 'โดย', 'สำหรับ',
    'ในการ', 'ตาม', 'เรื่อง', 'หลักสูตร', 'เกี่ยวกับ', 'แก่', 'ให้แก่', 'ภายใต้'
  ];

  while (mb_strlen($text, 'UTF-8') > $limit) {
    $cutPos = 0;
    $segment = mb_substr($text, 0, $limit, 'UTF-8');

    // 1) ถ้ามีช่องว่าง ให้ตัดที่ช่องว่างก่อน โดยเลือกตำแหน่งที่ใกล้ limit ที่สุด
    $spacePos = mb_strrpos($segment, ' ', 0, 'UTF-8');
    if ($spacePos !== false && $spacePos >= $minCut) {
      $cutPos = $spacePos;
    }

    // 2) ภาษาไทยไม่มีช่องว่าง ให้หาคำเชื่อมที่อยู่ใกล้ปลายบรรทัดที่สุด
    foreach ($beforeBreakWords as $word) {
      $pos = mb_strrpos($segment, $word, 0, 'UTF-8');
      if ($pos !== false && $pos >= $minCut && $pos > $cutPos) {
        $cutPos = $pos;
      }
    }

    // 3) ถ้ายังหาไม่ได้ ค่อยตัดตามจำนวนตัวอักษร แต่พยายามไม่ตัดโดนสระ/วรรณยุกต์
    if ($cutPos <= 0) {
      $cutPos = $limit;
      while ($cutPos > $minCut) {
        $char = mb_substr($text, $cutPos, 1, 'UTF-8');
        if (!preg_match('/[\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}]/u', $char)) {
          break;
        }
        $cutPos--;
      }
    }

    $line = trim(mb_substr($text, 0, $cutPos, 'UTF-8'));
    if ($line !== '') {
      $lines[] = $line;
    }

    $text = trim(mb_substr($text, $cutPos, null, 'UTF-8'));
  }

  if ($text !== '') {
    $lines[] = $text;
  }

  return $lines;
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
  return thai_digits(intval($d) . " " . $months[intval($m)] . " " . (intval($y) + 543));
}

/* --------------------------------------------------
   Mapping ตัวแปรหลักจาก document_values
-------------------------------------------------- */
$hasSavedDocDateField = array_key_exists(1, $valueMap);
$docDate = $hasSavedDocDateField ? trim((string)($valueMap[1] ?? '')) : trim($document['doc_date'] ?? '');
$ownerName = $valueMap[2] ?? "";
$position = $valueMap[3] ?? "";
$joinType = $valueMap[4] ?? "";

// ฟอร์มวิทยากร
$projectTitle  = $valueMap[5] ?? "";     // 5. ชื่อโครงการอบรม
$joinDates     = $valueMap[6] ?? "";     // 8. วันที่เริ่ม - วันที่สิ้นสุดโครงการ
$location      = $valueMap[7] ?? "";     // 7. สถานที่จัดงาน
$amountStr     = $valueMap[8] ?? "";
$travelPeriod  = $valueMap[9] ?? "";     // 9. วันที่เดินทางไป - วันที่เดินทางกลับ
$faculty       = $valueMap[10] ?? "";
$department    = $valueMap[11] ?? "";
$displayFaculty = trim($faculty) !== '' ? trim($faculty) : "คณะเทคโนโลยีและการจัดการอุตสาหกรรม";
$displayDepartment = trim($department) !== '' ? trim($department) : "เทคโนโลยีสารสนเทศ";
$displayDepartmentFull = "ภาควิชา" . $displayDepartment;
$displayFacultyDean = "คณบดี" . $displayFaculty;

$referenceOrg  = $valueMap[18] ?? "";    // 3. หน่วยงานผู้ออกหนังสืออ้างอิง
$referenceNo   = $valueMap[19] ?? "";    // เลขที่หนังสืออ้างอิง
$refDate       = $valueMap[21] ?? $docDate; // วันที่หนังสืออ้างอิง
$eventRange    = $joinDates;
$eventPlace    = $location;
$courseName    = $valueMap[23] ?? "";    // 6. ชื่อหลักสูตร
$travelPeriod  = $valueMap[24] ?? $travelPeriod;
$intentionText = $valueMap[25] ?? "ขออนุมัติเดินทางไปร่วมเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ";

$vehicle = "";
$referenceDateText = thai_date($refDate) ?: $refDate;

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
$mainSubjectText = trim($subject) !== ''
  ? trim($subject)
  : 'ขออนุมัติตัวบุคคลเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ';
$mainSubjectLines = splitSubjectLines($mainSubjectText, 78);

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
   เบอร์โทรภาควิชา สำหรับแถวส่วนราชการ
-------------------------------------------------- */
$departmentPhone = "";
try {
  $deptIdForPhone = (int)($document['department_id'] ?? 0);
  if ($deptIdForPhone > 0) {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM departments");
    $deptCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $phoneCandidates = ['phone', 'department_phone', 'tel', 'telephone', 'department_tel'];
    $phoneCol = '';
    foreach ($phoneCandidates as $candidate) {
      if (in_array($candidate, $deptCols, true)) {
        $phoneCol = $candidate;
        break;
      }
    }

    if ($phoneCol !== '') {
      $phoneStmt = $pdo->prepare("SELECT `$phoneCol` AS phone_value FROM departments WHERE department_id = :department_id LIMIT 1");
      $phoneStmt->execute([':department_id' => $deptIdForPhone]);
      $departmentPhone = trim((string)($phoneStmt->fetchColumn() ?: ''));
    }
  }
} catch (Throwable $e) {
  $departmentPhone = "";
}

/* --------------------------------------------------
   คำนวณวันที่ไทย, งบประมาณ
-------------------------------------------------- */
$thaiDocDate = thai_date($docDate);

// กันกรณีวันที่ถูกบันทึกมาเป็นข้อความไทย หรือรูปแบบอื่น
if ($thaiDocDate === '' && trim($docDate) !== '') {
  $thaiDocDate = trim($docDate);
}
$prettyAmount = $amountStr !== "" ? number_format((float) $amountStr, 2) : "";

/* --------------------------------------------------
   สร้างข้อความส่วนหัวที่ใช้ในเนื้อหา
-------------------------------------------------- */
$hdr_agency = trim(
  ($faculty ?: "คณะ..................................") . " " .
  ($department ? "ภาควิชา" . $department : "ภาควิชา........................") .
  ($departmentPhone !== "" ? " โทร." . $departmentPhone : "")
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



  /* ===== หัวบันทึก + ตราครุฑ: ทำให้เหมือน view_memo.php ===== */
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

  /* ตอน clone เพื่อทำ PDF ต้องปิด pseudo-line เดิม ไม่งั้น html2canvas จะวาดเส้นซ้อน/ลอย */
  .print-mode .dot-line::after {
    content: none !important;
    display: none !important;
    background-image: none !important;
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
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
    outline: none !important;
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
    padding-top: 8px;
  }

  .dot-line::after {
    content: "";

    position: absolute;
    left: 0;
    right: 0;

    bottom: -2px;

    height: 2px;

    background-image:
      radial-gradient(circle, #000 0.9px, transparent 1px);

    background-size: 6px 2px;
    background-repeat: repeat-x;
  }

  /* ระยะว่างหน้าคำ + หลังคำ ตามรูป */
  .dot-line .chip {
    line-height: 1 !important;
    padding: 0 6px !important;
    margin-left: 10px !important;
    margin-right: 6px !important;
    display: inline-flex !important;
    align-items: flex-end !important;
    position: relative;
    top: 5px !important;
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
      bottom: 0;
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
      background: #fff !important;
      box-shadow: none !important;

      position: relative;
      top: 2px;

      padding: 0 2px;
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
    line-height: 1 !important;
    height: 28px !important;
    display: flex;
    align-items: flex-end !important;
    padding-bottom: 3px !important;
    font-weight: bold !important;
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

  /* ===== แก้เฉพาะแถวส่วนราชการ: กันข้อความยาวตกบรรทัดและยกข้อความขึ้นบนเส้นประ ===== */
  .doc-row.gov-row {
    flex-wrap: nowrap !important;
    align-items: flex-end !important;
  }

  .doc-row.gov-row>.doc-label {
    flex: 0 0 auto !important;
    white-space: nowrap !important;
    margin-right: 0 !important;
  }

  .doc-row.gov-row>.dot-line {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    height: 28px !important;
    padding-top: 0 !important;
    display: flex !important;
    align-items: flex-end !important;
    overflow: visible !important;
  }

  .doc-row.gov-row>.dot-line::after {
    bottom: 0 !important;
  }

  .doc-row.gov-row>.dot-line>.chip.gov-text {
    display: inline-block !important;
    white-space: nowrap !important;
    margin-left: 0 !important;
    margin-right: 4px !important;
    padding: 0 4px 0 2px !important;
    position: relative !important;
    top: 3px !important;
    line-height: 1.1 !important;
    max-width: none !important;
  }

  /* ===== แก้เฉพาะแถว "เรื่อง" ให้แยกเป็นบรรทัดจริงแบบไฟล์ตัวอย่าง ===== */
  .subject-row .subject-label {
    width: 1.15cm !important;
    flex: 0 0 1.15cm !important;
  }

  .subject-wrap {
    flex: 1;
    padding-left: 0;
    margin-left: -8px;
  }

  .subject-line {
    min-height: 22px;
    line-height: 1.05;
    border-bottom: 2px dotted #000;
    padding-left: 19px;
    padding-top: 4px;
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    font-weight: 300;
    white-space: normal;
    word-break: normal;
    overflow-wrap: normal;
    line-break: auto;
  }

  .subject-text {
    display: inline-block;
    position: relative;
    top: 4px;
    white-space: normal;
    word-break: normal;
    overflow-wrap: normal;
    line-break: auto;
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

    // เปลี่ยนข้อความของปุ่มดาวน์โหลด PDF ให้อยู่ในโหมดอ่านอย่างเดียว
    const pdfBtn = document.querySelector("button[onclick='downloadPdf()']");
    if (pdfBtn) pdfBtn.innerText = "ดาวน์โหลด PDF (โหมดอ่านอย่างเดียว)";

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

      <!-- ส่งให้ update_memo.php รู้ว่าเป็นฟอร์มวิทยากร -->
      <input type="hidden" name="purpose" id="hidden_joinType" value="speaker_workshop">
      <input type="hidden" name="target_form" value="form_memo_speaker.php">
      <input type="hidden" name="redirect_back"
        value="/Pro_letter/form_Memo/form_memo_speaker.php?id=<?= h($document['document_id']) ?>">

      <input type="hidden" name="subject" id="hidden_subject" value="<?= h($subject) ?>">
      <input type="hidden" name="project_title" id="hidden_projectTitle" value="<?= h($projectTitle) ?>">
      <input type="hidden" name="course_name" id="hidden_courseName" value="<?= h($courseName) ?>">

      <input type="hidden" name="reference_org" id="hidden_referenceOrg" value="<?= h($referenceOrg) ?>">
      <input type="hidden" name="reference_no" id="hidden_referenceNo" value="<?= h($referenceNo) ?>">
      <input type="hidden" name="reference_date" id="hidden_referenceDate" value="<?= h($refDate) ?>">

      <input type="hidden" name="intern_period" id="hidden_joinDates" value="<?= h($eventRange) ?>">
      <input type="hidden" name="location" id="hidden_location" value="<?= h($eventPlace) ?>">
      <input type="hidden" name="travel_period" id="hidden_travelPeriod" value="<?= h($travelPeriod) ?>">
      <input type="hidden" name="intention_text" id="hidden_intentionText" value="<?= h($intentionText) ?>">

      <input type="hidden" name="amount" id="hidden_amountStr" value="<?= h($amountStr) ?>">
      <input type="hidden" name="faculty" id="hidden_faculty" value="<?= h($faculty) ?>">
      <input type="hidden" name="department" id="hidden_department" value="<?= h($department) ?>">

      <!-- ตัวเลือกช่วงวันที่: ใช้ range เป็นค่า default ตาม UI ปัจจุบัน -->
      <input type="hidden" name="date_option" id="hidden_dateOption" value="range">
      <input type="hidden" name="single_date" id="hidden_singleDate" value="">


      <!-- หัวบันทึก: ใช้โครงสร้างเดียวกับ view_memo.php เพื่อให้ html2canvas ดึงตราครุฑติด PDF -->
      <div class="memo-title-row">
        <img src="/Pro_letter/assets/img/garuda.jpg" class="garuda-img" />
        <h1 class="doc-title">บันทึกข้อความ</h1>
      </div>

      <!-- ส่วนราชการ -->
      <div class="doc-row gov-row">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;position:relative;top:9px;">
          ส่วนราชการ
        </div>
        <div class="dot-line">
          <span class="chip gov-text" contenteditable="true" data-target="header_text">
            <?= h(thai_digits($hdr_agency ?: ($header_text ?: 'คณะ... ภาควิชา... โทร...'))) ?>
          </span>
        </div>
      </div>

      <div class="doc-row row-ty-date">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;position:relative;top:9px;">
          ที่
        </div>

        <div class="dot-line ty-left">
          <span class="chip" contenteditable="true" data-target="doc_no" style="
  display:inline-block;
  transform:translateX(26px);
">
            
          </span>
        </div>

        <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;position:relative;top:9px;">
          วันที่
        </div>

        <div class="dot-line ty-right">
          <span class="chip" contenteditable="true" data-target="doc_date_display">
            <?= h(thai_digits($thaiDocDate ?: '')) ?>
          </span>
        </div>
      </div>

      <!-- เรื่อง -->
      <div class="doc-row subject-row" style="align-items:flex-start;">
        <div class="doc-label subject-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>
        <div class="subject-wrap">
          <?php foreach ($mainSubjectLines as $subjectLine): ?>
          <div class="subject-line"><span class="subject-text" lang="th"><?= h(thai_digits($subjectLine)) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- บรรทัด “เรียน” -->
      <div class="doc-row" style="
        margin-top:4px;
        margin-bottom:18px;
        display:flex;
        align-items:baseline;
     ">

        <div class="doc-label" style="
            font-size:16pt !important;
            font-weight:400 !important;
            line-height:1.2;
            padding-top:1px;
         ">
          เรียน
        </div>

        <div style="
            flex:1;
            padding-left:28px;
            font-size:16pt;
            line-height:1.2;
         ">
          <?= h(thai_digits($displayFacultyDean)) ?>
        </div>

      </div>

      <!-- ย่อหน้า 1 -->
      <div class="content-block paragraph">
        อ้างถึง หนังสือจาก
        <span class="chip" contenteditable="true"
          data-target="referenceOrg"><?= h(thai_digits($referenceOrg ?: '................................')) ?></span>
        เลขที่
        <span class="chip" contenteditable="true"
          data-target="referenceNo"><?= h(thai_digits($referenceNo ?: '................................')) ?></span>
        ลงวันที่
        <span class="chip" contenteditable="true"
          data-target="referenceDate"><?= h(thai_digits($referenceDateText ?: '................................')) ?></span>
        เรื่อง
        <span class="chip" contenteditable="true"
          data-target="projectTitle"><?= h(thai_digits($projectTitle ?: '................................')) ?></span>
        หลักสูตร
        <span class="chip" contenteditable="true"
          data-target="courseName"><?= h(thai_digits($courseName ?: '................................')) ?></span>
        ในระหว่างวันที่
        <span class="chip" contenteditable="true"
          data-target="joinDates"><?= h(thai_digits($eventRange ?: '................................')) ?></span>
        ณ
        <span class="chip" contenteditable="true"
          data-target="location"><?= h(thai_digits($eventPlace ?: '................................')) ?></span>
        นั้น
      </div>

      <!-- ย่อหน้า 2 -->
      <div class="content-block paragraph">
        ในการนี้ ข้าพเจ้า
        <span class="chip" contenteditable="true"
          data-target="ownerName"><?= h(thai_digits($ownerName ?: '................................')) ?></span>
        สังกัด<?= h(thai_digits($displayDepartmentFull)) ?>
        คณะ<?= h(thai_digits($faculty ?: 'เทคโนโลยีและการจัดการอุตสาหกรรม')) ?>
        มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
        ข้าพเจ้ามีความประสงค์
        <span class="chip" contenteditable="true"
          data-target="intentionText"><?= h(thai_digits($intentionText ?: 'ขออนุมัติเดินทางไปร่วมเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ')) ?></span>
        หลักสูตร
        <span class="chip" contenteditable="true"
          data-target="courseName"><?= h(thai_digits($courseName ?: '................................')) ?></span>
        ระหว่างวันที่
        <span class="chip" contenteditable="true"
          data-target="travelPeriod"><?= h(thai_digits($travelPeriod ?: '................................')) ?></span>
        (รวมระยะเวลาในการเดินทาง) รายละเอียดตามเอกสารแนบ
      </div>

      <!-- ย่อหน้า 3 -->
      <div class="content-block paragraph">
        จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ
      </div>

      <div class="signature-wrapper">
        <div class="signature-block" id="signatureBlock">
          <div class="sig-name">(<?= h(thai_digits($ownerName ?: '')) ?>)</div>
          <div class="sig-position"><?= h(thai_digits($position ?: '')) ?></div>
        </div>
      </div>


      <div class="footer-actions">

        <!-- ปุ่มดาวน์โหลด PDF -->
        <button type="button" onclick="downloadPdf()"
          class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
          ดาวน์โหลด PDF
        </button>

        <!-- ปุ่มดาวน์โหลด Word -->
        <a href="/Pro_letter/documents/download_word_speaker.php?id=<?= (int)$docId ?>" data-word-download="1"
          data-word-filename="<?= h($wordDownloadName) ?>" onclick="return downloadWord(this);"
          class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
          ดาวน์โหลด Word
        </a>

        <!-- USER: ปุ่มแก้ไขเอกสาร -->
        <?php if ($canEdit || $roleId === 3 || $isAdmin || $isOfficer): ?>
        <a href="<?= h($editQuestionUrl) ?>"
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
            document.getElementById("hidden_doc_date").value = isoDate;
          }
          return;
        }

        if (target === "referenceDate") {
          const isoDate = parseThaiDate(text);
          if (isoDate) {
            document.getElementById("hidden_referenceDate").value = isoDate;
          } else {
            document.getElementById("hidden_referenceDate").value = text;
          }
          return;
        }

        hidden.value = text;

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

    const fileName = link.dataset.wordFilename || "บันทึกข้อความ.docx";

    fetch(downloadUrl.toString(), {
        method: "GET",
        credentials: "same-origin"
      })
      .then(response => {
        if (!response.ok) {
          throw new Error("Download failed");
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

        // ไม่เอาปุ่มต่าง ๆ ติดไปใน PDF
        const cloneActions = clone.querySelectorAll(".footer-actions");
        cloneActions.forEach(el => el.remove());

        // ล็อกข้อความแก้ไขได้ให้เป็นข้อความนิ่งตอนแปลง PDF
        clone.querySelectorAll("[contenteditable]").forEach(el => {
          el.setAttribute("contenteditable", "false");
        });

        // ใส่ style ลงใน clone เพื่อปิด .dot-line::after เดิมของหน้า form_memo_speaker
        // ถ้าไม่ปิด ตัว pseudo-line จะยังถูก html2canvas วาด ทำให้เส้นประลอย/ซ้อนผิดตำแหน่ง
        const pdfStyle = document.createElement("style");
        pdfStyle.textContent = `
          .print-mode .dot-line::after {
            content: none !important;
            display: none !important;
            background-image: none !important;
          }
        `;
        clone.prepend(pdfStyle);

        // ปรับตราครุฑและหัวเรื่องให้เหมือน view_memo.php ตอนแปลง PDF
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

        // ทำเส้นประใหม่เฉพาะตอน clone แบบเดียวกับ view_memo.php
        clone.querySelectorAll(".dot-line").forEach(line => {
          line.querySelectorAll(".pdf-real-dot-line").forEach(el => el.remove());

          line.style.position = "relative";
          line.style.overflow = "visible";
          line.style.backgroundImage = "none";
          line.style.borderBottom = "none";

          line.querySelectorAll(".chip").forEach(chip => {
            chip.style.setProperty("transform", "translateY(4.5px)", "important");
            chip.style.setProperty("line-height", "1", "important");
            chip.style.setProperty("display", "inline-block", "important");
            chip.style.setProperty("vertical-align", "bottom", "important");
          });

          const dot = document.createElement("div");
          dot.className = "pdf-real-dot-line";
          dot.style.position = "absolute";
          dot.style.left = "0";
          dot.style.right = "0";
          dot.style.bottom = "-18px";
          dot.style.height = "0";
          dot.style.zIndex = "1";
          dot.style.pointerEvents = "none";
          dot.style.borderTop = "2px dotted #000";

          line.prepend(dot);
        });

        // แก้เฉพาะเส้นประของแถว "เรื่อง" ให้เป็นหลายบรรทัดจริง ไม่ใช้ background ทับตัวหนังสือ
        clone.querySelectorAll(".subject-line").forEach((line, index) => {
          line.querySelectorAll(".pdf-subject-dot-line").forEach(el => el.remove());

          line.style.position = "relative";
          line.style.height = "auto";
          line.style.minHeight = "20px";
          line.style.lineHeight = "1.2";
          line.style.paddingLeft = "18px";
          line.style.paddingTop = "0";
          line.style.paddingBottom = "16px";
          line.style.margin = "0";

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
            text.style.top = "4px";
            text.style.zIndex = "3";
            text.style.background = "transparent";
            text.style.whiteSpace = "normal";
            text.style.wordBreak = "normal";
            text.style.overflowWrap = "normal";
            text.style.lineBreak = "auto";
          });
        });

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