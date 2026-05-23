<!-- Pro_letter/form_Memo/form_consent_research_presentation หนังสือยินยอมให้นำเสนอผลงานวิจัย -->
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
$presenterName = $valueMap[14] ?? "";
$researchTitle = $valueMap[13] ?? "";
$conferenceLevel = $valueMap[15] ?? "";
$conferenceName = $valueMap[5] ?? "";
$conferencePlace = $valueMap[7] ?? "";
$presentationDate = $valueMap[16] ?? "";
$signatureAffiliation = $valueMap[17] ?? "";

function dashText($text, $bold = true)
{
  $text = (string) $text;

  if ($text === '') {
    return '';
  }

  $class = $bold ? 'dash-piece' : 'dash-piece dash-piece-normal';

  // แยกคำ แต่ไม่เอาช่องว่างไปทำเส้นประ
  // เพราะช่องว่างที่เป็น span จะโดน text-align: justify ถ่างจน layout พัง
  $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

  $html = '';

  foreach ($parts as $part) {
    if ($part === '') {
      continue;
    }

    if (preg_match('/^\s+$/u', $part)) {
      // ช่องว่างให้เป็นช่องว่างปกติ ห้ามมี border-bottom
      $html .= ' ';
    } else {
      $html .= '<span class="' . $class . '">' . htmlspecialchars($part, ENT_QUOTES, 'UTF-8') . '</span>';
    }
  }

  return $html;
}
/* --------------------------------------------------
   Mapping joinType → purposeCode (รหัส)
-------------------------------------------------- */
$purposeCode = 'other';

switch (trim($joinType)) {
  // เอกสารหนังสือยินยอมฯ ต้องส่งกลับ update_memo.php ด้วย purpose เดิม
  // ถ้าปล่อยเป็น other จะทำให้ field_id 4 ถูกอัปเดตเป็น "อื่นๆ"
  case 'หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ':
  case 'หนังสือยินยอมให้นำเสนอผลงานวิจัย':
  case 'consent_research_presentation':
    $purposeCode = 'consent_research_presentation';
    break;

  case 'นำเสนอผลงานทางวิชาการ':
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
    padding: 70px 70px 50px 100px;
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
    /* ลดจาก 2.5cm เพื่อให้คำว่า "ข้าพเจ้า" ขยับไปทางซ้ายอีกนิด */
    text-indent: 2.15cm;
    margin-top: 0.5em;
    line-height: 1.3;
  }

  .inline-dash {
    display: inline;
    font-weight: bold;
    padding: 0 1px;
    background: none !important;
    border-bottom: none !important;
    vertical-align: baseline;

    text-decoration-line: underline;
    text-decoration-style: dashed;
    text-decoration-color: #111;
    text-decoration-thickness: 0.45px;
    text-decoration-skip-ink: none;

    /* ตัวนี้คุมระยะเส้นประของเนื้อหา */
    text-underline-offset: 1px;
  }

  .inline-dash-normal {
    font-weight: normal !important;
  }

  /* ตัวเส้นประจริง */
  .dash-piece {
    display: inline-block;
    font-weight: bold;
    line-height: 0.45;
    padding: 0 1px;
    margin: 0;
    border-bottom: 0.45px dashed #111;
    vertical-align: baseline;
    box-sizing: border-box;
    white-space: nowrap;
  }

  /* ใช้กับคำว่า "ในระหว่างวันที่" ให้มีเส้นประแต่ไม่ตัวหนา */
  .dash-piece-normal {
    font-weight: normal !important;
  }

  /* ช่องว่างระหว่างคำ ให้ยังมีเส้นประต่อเนื่อง */
  .dash-space {
    min-width: 0.25em;
    padding-left: 0;
    padding-right: 0;
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
    justify-content: flex-end;
    margin-top: 3cm;
    padding-right: -0.25cm;
  }

  .signature-block {
    display: inline-block;
    width: max-content;
    min-width: 8.5cm;
    text-align: center;
    font-family: 'TH SarabunPSK';
    font-size: 16pt;
    line-height: 1.2;
    font-weight: bold;
  }

  .sig-name,
  .sig-position,
  .sig-affiliation {
    display: block;
    white-space: nowrap;
    text-align: center;
  }

  .sig-dash {
    font-weight: bold;
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

  .content-block,
  .chip {
    font-family: "TH SarabunPSK";
    font-size: 16pt !important;
    /* ← เทียบเท่า 16pt จริงใน Word */
    font-weight: 400 !important;
  }

  /* เส้นประในเนื้อหา */
  .inline-dash {
    display: inline;
    font-weight: bold !important;
    padding: 0 1px;
    background: none !important;
    border-bottom: none !important;

    text-decoration-line: underline;
    text-decoration-style: dashed;
    text-decoration-color: #111;
    text-decoration-thickness: 0.45px;
    text-decoration-skip-ink: none;
    text-underline-offset: 0px;
  }

  .inline-dash-normal {
    font-weight: normal !important;
  }

  /* ลายเซ็นหน้าเว็บปกติ */
  .sig-name {
    display: block;
    text-align: center;
    white-space: nowrap;
    font-weight: bold;
  }

  .sig-affiliation {
    display: block;
    text-align: center;
    white-space: nowrap;
    font-weight: bold;
  }

  .sig-dash-normal {
    display: inline-block;
    font-weight: bold;
    padding: 0 2px;
    line-height: 0.45;
    border-bottom: 0.45px dashed #111;
    vertical-align: baseline;
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
    if (printBtn) printBtn.innerText = "พิมพ์/ดูตัวอย่าง (โหมดอ่านอย่างเดียว)";

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


      <input type="hidden" name="range_date" id="hidden_joinDates" value="<?= h($joinDates) ?>">
      <input type="hidden" name="place" id="hidden_location" value="<?= h($location) ?>">
      <input type="hidden" name="amount" id="hidden_amountStr" value="<?= h($amountStr) ?>">
      <input type="hidden" name="car_plate" id="hidden_vehicle" value="<?= h($vehicle) ?>">
      <input type="hidden" name="faculty" id="hidden_faculty" value="<?= h($faculty) ?>">
      <input type="hidden" name="department" id="hidden_department" value="<?= h($department) ?>">

      <!-- ตัวเลือกช่วงวันที่: ใช้ range เป็นค่า default ตาม UI ปัจจุบัน -->
      <input type="hidden" name="date_option" id="hidden_dateOption" value="range">
      <input type="hidden" name="single_date" id="hidden_singleDate" value="">



      <!-- เรื่อง -->
      <div style="text-align:center; font-size:18pt; font-weight:bold; margin-bottom:2em;">
        หนังสือยินยอมให้นำเสนอผลงานวิจัย
      </div>


      <div class="content-block paragraph">
        ข้าพเจ้า <span class="inline-dash"><?= h($ownerName) ?></span> ได้ยอมให้
        <span class="inline-dash"><?= h($presenterName) ?></span>
        อาจารย์สังกัดภาควิชาเทคโนโลยีสารสนเทศ
        คณะเทคโนโลยีและการจัดการอุตสาหกรรม
        มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ
        วิทยาเขตปราจีนบุรี
        ได้นำเสนอผลงานวิจัย เรื่อง
        <span class="inline-dash"><?= h($researchTitle) ?></span>
        ในงานการประชุมวิชาการ<span><?= h($conferenceLevel) ?></span>
        <span class="inline-dash"><?= h($conferenceName) ?></span>
        โดยงานการประชุมจัดขึ้นที่
        <span class="inline-dash"><?= h($conferencePlace) ?></span>
        <span class="inline-dash inline-dash-normal">ในระหว่างวันที่</span>
        <span class="inline-dash"><?= h($presentationDate) ?></span>
      </div>



      <!-- ลายเซ็น -->
      <div class="signature-wrapper">
        <div id="signatureBlock" class="signature-block">
          <span class="sig-name">(<span class="sig-dash-normal"><?= h($ownerName) ?></span>)</span>
          <span class="sig-affiliation"><?= h($signatureAffiliation) ?></span>
        </div>
      </div>



      <div class="footer-actions">

        <!-- 🔵 ปุ่มดาวน์โหลด PDF -->
        <button type="button" onclick="downloadPdf()"
          class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          ดาวน์โหลด PDF
        </button>

        <!-- 🟩 USER: ปุ่มแก้ไขเอกสาร -->
        <?php if ($roleId === 3): ?>
        <a href="/Pro_letter/documents/infor_present.php?id=<?= $docId ?>" id="editBtn"
          data-can-edit="<?= $canEdit ? '1' : '0' ?>" class="px-6 py-2 rounded-md text-xl font-bold
  <?= $canEdit
    ? 'bg-teal-500 hover:bg-teal-600 text-white'
    : 'bg-gray-300 text-gray-600 cursor-not-allowed' ?>">
          แก้ไขเอกสาร
        </a>
        <?php endif; ?>

        <!-- 🟦 OFFICER & ADMIN -->
        <?php if ($isAdmin || $isOfficer): ?>

        <!-- ปุ่มอนุมัติ -->
        <button type="button" onclick="updateStatus('approved')"
          class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          ยืนยันการแก้ไข
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
    const editBtn = document.getElementById("editBtn");

    if (editBtn) {
      editBtn.addEventListener("click", function(e) {
        const canEdit = this.dataset.canEdit === "1";

        if (!canEdit) {
          e.preventDefault();

          Swal.fire({
            title: "ไม่สามารถแก้ไขได้",
            text: "คุณไม่มีสิทธิ์แก้ไขเอกสารนี้",
            icon: "warning",
            confirmButtonText: "ตกลง"
          });
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
  // ไม่ต้องคำนวณความกว้างลายเซ็นด้วย JS แล้ว
  // เพราะ .signature-block ใช้ min-width + text-align:center เพื่อให้ชื่ออยู่กลางหน่วยงานเสมอ
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

        clone.classList.add("pdf-export-page");

        clone.style.width = "794px";
        clone.style.minHeight = "1123px";
        clone.style.height = "1123px";
        clone.style.margin = "0";
        clone.style.boxShadow = "none";
        clone.style.background = "#ffffff";
        clone.style.border = "2px solid #ffffff";
        clone.style.boxSizing = "border-box";
        const pdfStyle = document.createElement("style");
        pdfStyle.textContent = `
          .pdf-export-page .inline-dash {
            text-decoration: none !important;
            background: none !important;
            background-image: none !important;
            border-bottom: none !important;
            padding: 0 1px !important;
            font-weight: bold !important;
            line-height: 1 !important;
            vertical-align: baseline !important;
          }

          .pdf-export-page .inline-dash-normal {
            font-weight: normal !important;
          }

          .pdf-export-page .pdf-dash-overlay {
            position: absolute !important;
            height: 0 !important;
            border-bottom: 0.45px dashed #111 !important;
            pointer-events: none !important;
            z-index: 99 !important;
          }
        `;
        clone.prepend(pdfStyle);

        const cloneActions = clone.querySelectorAll(".footer-actions");
        cloneActions.forEach(el => el.remove());

        clone.querySelectorAll("[contenteditable]").forEach(el => {
          el.removeAttribute("contenteditable");
          el.removeAttribute("tabindex");
          el.style.outline = "none";
          el.style.caretColor = "transparent";
        });

        clone.querySelectorAll("input[type='hidden']").forEach(el => el.remove());
        // ===== แก้เส้นประเฉพาะตอน Export PDF เท่านั้น =====

        // 1) เส้นประในเนื้อหาเฉพาะตอน Export PDF
        // ห้ามใช้ border-bottom กับ inline ตอน html2canvas เพราะบางครั้งเส้นจะหาย
        // ใช้ background-image แทน และห้ามใส่ background = "none"
        clone.querySelectorAll(".inline-dash").forEach(el => {
          const isNormal = el.classList.contains("inline-dash-normal");

          el.style.setProperty("text-decoration", "none", "important");
          el.style.setProperty("background", "none", "important");
          el.style.setProperty("background-image", "none", "important");
          el.style.setProperty("border-bottom", "none", "important");
          el.style.setProperty("font-weight", isNormal ? "normal" : "bold", "important");
          el.style.setProperty("padding", "0 1px", "important");
          el.style.setProperty("line-height", "1", "important");
          el.style.setProperty("vertical-align", "baseline", "important");
        });

        // 2) ลายเซ็นตอน PDF ให้เส้นประเป็นเส้นเดียว
        // ไม่กระทบหน้าปกติ เพราะทำกับ clone เท่านั้น
        const sigName = clone.querySelector(".sig-name");

        if (sigName) {
          const rawName = sigName.textContent
            .replace("(", "")
            .replace(")", "")
            .trim();

          sigName.innerHTML = `(<span class="pdf-signature-dash">${rawName}</span>)`;

          const dash = sigName.querySelector(".pdf-signature-dash");

          if (dash) {
            dash.style.display = "inline-block";
            dash.style.fontWeight = "bold";

            // ตอนโหลด PDF ห้ามใช้ lineHeight ต่ำแบบหน้าจอ เพราะ html2canvas จะทำให้เส้นลอย
            dash.style.lineHeight = "1.55";
            dash.style.padding = "0 2px 1px 2px";

            dash.style.borderBottom = "0.45px dashed #111";
            dash.style.verticalAlign = "baseline";
            dash.style.textDecoration = "none";
            dash.style.background = "none";
          }
        }
        const wrapper = document.createElement("div");
        wrapper.style.position = "fixed";
        wrapper.style.left = "-9999px";
        wrapper.style.top = "0";
        wrapper.style.width = "794px";
        wrapper.style.background = "#ffffff";
        wrapper.style.zIndex = "-1";

        wrapper.appendChild(clone);
        document.body.appendChild(wrapper);

        // วาดเส้นประใต้ข้อความที่เป็น .inline-dash เท่านั้น
        // แก้ปัญหา border-bottom ของ inline span ลากไปโดนข้อความปกติ
        const DASH_Y_OFFSET = 2; // เลขมากขึ้น = เส้นลงต่ำลง

        const pageRect = clone.getBoundingClientRect();

        clone.querySelectorAll(".inline-dash").forEach(el => {
          const range = document.createRange();
          range.selectNodeContents(el);

          const rects = Array.from(range.getClientRects()).filter(rect => {
            return rect.width > 1 && rect.height > 1;
          });

          rects.forEach(rect => {
            const dash = document.createElement("span");
            dash.className = "pdf-dash-overlay";

            dash.style.left = `${rect.left - pageRect.left}px`;
            dash.style.top = `${rect.bottom - pageRect.top + DASH_Y_OFFSET}px`;
            dash.style.width = `${rect.width}px`;

            clone.appendChild(dash);
          });

          range.detach();
        });

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

        document.body.removeChild(wrapper);

        const imgData = canvas.toDataURL("image/png");

        if (i > 0) {
          pdf.addPage();
        }

        pdf.addImage(imgData, "PNG", 0, 0, 210, 297);
      }

      pdf.save("consent_research_presentation_<?= $docId ?>.pdf");

    } catch (error) {
      console.error(error);
      alert("สร้าง PDF ไม่สำเร็จ กรุณากด F12 ดู Console");
    }
  }
  </script>
</body>


</html>