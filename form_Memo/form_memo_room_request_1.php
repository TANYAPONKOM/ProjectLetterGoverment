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
$sql = "
    SELECT COUNT(*) 
    FROM user_permissions up
    JOIN permissions p ON p.perm_id = up.perm_id
    WHERE up.user_id = :uid
    AND p.perm_code = 'document.edit'
";
$st = $pdo->prepare($sql);
$st->execute([':uid' => $userId]);

$hasEditPermission = $st->fetchColumn() > 0;
$docStatus = $document['status'] ?? '';

if ($roleId === 1 || $roleId === 2) {
  $canEdit = true;
} else {
  $canEdit = $hasEditPermission && in_array($docStatus, ['draft', 'rejected'], true);
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
   ใช้ field_id จาก Request_3.php / save_memo.php
-------------------------------------------------- */
$docDate = $valueMap[1] ?? ($document['doc_date'] ?? "");
$ownerName = $valueMap[2] ?? "";
$position = $valueMap[3] ?? "";

$faculty = $valueMap[10] ?? "";
$department = $valueMap[11] ?? "";
$displayFaculty = trim($faculty) !== '' ? trim($faculty) : "คณะเทคโนโลยีและการจัดการอุตสาหกรรม";
$displayDepartment = trim($department) !== '' ? trim($department) : "เทคโนโลยีสารสนเทศ";
$displayDepartmentFull = "ภาควิชา" . $displayDepartment;
$displayFacultyDean = "คณบดี" . $displayFaculty;

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
$stayDateText = (trim($dateOption) === "range" && trim($rangeDate) !== "") ? $rangeDate : $singleDate;

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
$prettyAmount = "";

/* --------------------------------------------------
   สร้างข้อความส่วนหัวที่ใช้ในเนื้อหา
-------------------------------------------------- */
$hdr_agency = trim($displayFaculty . " " . $displayDepartmentFull);

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
      <div class="pdf-loading-title">กำลังสร้าง PDF...</div>
      <div class="pdf-loading-subtitle">กรุณารอสักครู่ ระบบกำลังเตรียมเอกสาร</div>
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
      <div class="doc-row">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;">ส่วนราชการ</div>
        <div class="dot-line">
          <span class="chip" contenteditable="false" data-target="header_text">
            <?= h($header_text ?: 'คณะ... ภาค... โทร...') ?>
          </span>
        </div>
      </div>

      <div class="doc-row row-ty-date">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;">ที่</div>

        <div class="dot-line ty-left">
          <span class="chip" contenteditable="false" data-target="doc_no">
            <?= h($doc_no ?: 'ทส.486/2568') ?>
          </span>
        </div>

        <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;">วันที่</div>

        <div class="dot-line ty-right">
          <span class="chip" contenteditable="false" data-target="doc_date_display">
            <?= h($thaiDocDate ?: '') ?>
          </span>
        </div>
      </div>


      <!-- เรื่อง -->
      <div class="doc-row">
        <div class="doc-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>
        <div class="dot-line">
          <span class="chip" contenteditable="false">
            ขออนุมัติใช้ห้องพักรับรอง<?= trim($roomRequestText) !== "" ? "สำหรับ" . h($roomRequestText) : "" ?>
          </span>
        </div>
      </div>


      <!-- บรรทัด “เรียน ...” -->
      <div class="content-block single">
        เรียน
        <span class="chip" contenteditable="false">
          <?= h($toPerson ?: "ประธานคณะกรรมการบ้านพัก มจพ. วิทยาเขตปราจีนบุรี") ?>
        </span>
      </div>

      <!-- ย่อหน้า 1 -->
      <div class="content-block paragraph">
        ตามที่ <?= h($displayDepartmentFull) ?> <?= h($displayFaculty) ?>
        มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
        มีความประสงค์ขออนุมัติใช้ห้องพักรับรองสำหรับ
        <?= h($roomRequestText ?: "................................") ?>
        ให้แก่
        <?= h($guestFullname ?: "................................") ?>
        ซึ่งเป็น
        <?= h($personTypeText ?: "................................") ?>
        ในระหว่างวันที่
        <?= h($stayDateText ?: "................................") ?>
        นั้น
      </div>

      <!-- ย่อหน้า 2 -->
      <div class="content-block paragraph">
        ในการนี้ <?= h($displayDepartmentFull) ?>
        จึงมีความประสงค์ขออนุมัติใช้ห้องพักรับรอง
        ณ
        <?= h($roomType ?: "................................") ?>
        ให้แก่
        <?= h($guestFullname ?: "................................") ?>
        ทั้งนี้เพื่อ
        <?= h($reasonText ?: "................................") ?>
        รายละเอียดตามเอกสารแนบท้าย
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


      <div class="footer-actions">

        <button type="button" onclick="downloadPdf()"
          class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          ดาวน์โหลด PDF
        </button>

        <a href="<?= h($editFormPath) ?>" id="editBtn" data-can-edit="<?= $canEdit ? '1' : '0' ?>"
          class="px-6 py-2 rounded-md text-xl font-bold <?= $canEdit ? 'bg-teal-500 hover:bg-teal-600 text-white' : 'bg-gray-300 text-gray-600 cursor-not-allowed' ?>">
          แก้ไขเอกสาร
        </a>

        <?php if ($isAdmin || $isOfficer): ?>
        <button type="button" onclick="updateStatus('approved')"
          class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          ผ่านการตรวจสอบ
        </button>

        <button type="button" onclick="updateStatus('rejected')"
          class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          ไม่ผ่าน
        </button>
        <?php endif; ?>

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

    const editBtn = document.getElementById("editBtn");
    if (editBtn) {
      editBtn.addEventListener("click", function(e) {
        const canEdit = this.dataset.canEdit === "1";
        if (!canEdit) {
          e.preventDefault();
          Swal.fire({
            title: "ไม่สามารถแก้ไขได้",
            text: "คุณไม่มีสิทธิ์แก้ไขเอกสารนี้ หรือสถานะเอกสารไม่อนุญาตให้แก้ไข",
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
  (function() {
    const box = document.getElementById('signatureBlock');
    if (!box) return;
    const nameEl = box.querySelector('.sig-name');
    // กำหนดความกว้างกล่อง = ความกว้างบรรทัดชื่อ -> ตำแหน่งจะกึ่งกลางใต้ชื่อพอดี
    box.style.width = nameEl.offsetWidth + 'px';
  })();


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

      pdf.save("room_request_<?= $docId ?>.pdf");
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