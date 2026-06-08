<?php
session_start();
require_once __DIR__ . '/functions.php';

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
function h($s)
{
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
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


            <!-- หัวบันทึก -->
            <div style="display:flex; align-items:flex-end; justify-content:flex-start; gap:20px; margin-bottom:0.5em;">
                <img src="https://i.pinimg.com/474x/bd/55/cc/bd55ccc4416012910a723da8f810658b.jpg"
                    style="height:1.6cm; width:auto; margin-top:0;" />

                <h1 class="doc-title" style="font-size:30pt;font-weight:bold;font-family:'TH SarabunPSK';
      line-height:1.0;margin-bottom:-10px;text-align:center;flex:1;
      transform: translateX(-0.3cm);">
                    บันทึกข้อความ
                </h1>
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
            <div class="doc-row">
                <div class="doc-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>
                <div class="dot-line">
                    <span class="chip" contenteditable="true" data-target="subject">
                        <?= h($subject ?: 'ขออนุมัติ...') ?>
                    </span>
                </div>
            </div>


            <!-- บรรทัด “เรียน ...” -->
            <div class="content-block single">
                เรียน คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม
            </div>

            <!-- ย่อหน้า 1 -->
            <div class="content-block paragraph">
                ตามที่ ข้าพเจ้า
                <span class="chip" contenteditable="true" data-target="ownerName">
                    <?= h($ownerName ?: 'ผู้ช่วยศาสตราจารย์ ดร. ขนิษฐา นามี') ?>
                </span>
                พนักงานมหาวิทยาลัย สังกัดภาควิชาเทคโนโลยีสารสนเทศ
                คณะเทคโนโลยีและการจัดการอุตสาหกรรม มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ
                วิทยาเขตปราจีนบุรี ได้รับการตอบรับให้เข้าร่วม นำเสนอผลงานวิจัยในการประชุมวิชาการระดับนานาชาติ
                The 5<sup>th</sup> Asia Conference on Information Engineering (ACIE 2025)
                ในหัวข้อ “API-Based Personal Healthcare Application: Securing Data and Ensuring Patient Privacy”
                ซึ่งจัดขึ้นที่โรงแรม Beyond Kata จังหวัดภูเก็ต ในระหว่างวันที่
                <span class="chip" contenteditable="true" data-target="joinDates">
                    <?= h($joinDates ?: '10 – 12 มกราคม 2568') ?>
                </span>
                โดยเอกสารงานประชุมวิชาการจะถูกตีพิมพ์อยู่ในฐานข้อมูล Scopus นั้น
            </div>

            <!-- ย่อหน้า 2 -->
            <div class="content-block paragraph">
                การนี้ ข้าพเจ้า จึงมีความประสงค์ขออนุมัติเดินทางเพื่อไปนำเสนอผลงานวิจัย
                ในงานประชุมวิชาการระดับนานาชาติ ACIE 2025 ในระหว่างวันที่
                <span class="chip" contenteditable="true" data-target="duration">
                    <?= h($valueMap['duration'] ?? '9 – 12 มกราคม 2568') ?>
                </span>
                (รวมเวลาเดินทาง) ตามวัน เวลา และสถานที่ดังกล่าว
                โดยการนำเสนอผลงานวิจัยในครั้งนี้เป็นประโยชน์ต่อการพัฒนาการเรียนการสอนงานวิจัย
                และสร้างชื่อเสียงให้กับมหาวิทยาลัย โดยขอใช้งบจัดสรรให้หน่วยงาน
                ประจำปีงบประมาณ พ.ศ.
                <span class="chip" contenteditable="true" data-target="fiscal_year_display">
                    <?= h($thaiYear ?: '2568') ?>
                </span>
                ในส่วนของสาขาเทคโนโลยีสารสนเทศ แผนงานจัดการศึกษาระดับอุดมศึกษา
                หมวดค่าใช้สอย (รายละเอียดตามเอกสารแนบ)
            </div>

            <!-- ย่อหน้า 3 -->
            <div class="content-block paragraph">
                จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ
            </div>


            <div class="signature-wrapper">
                <div class="signature-block" id="signatureBlock">
                    <div class="sig-name">(<?= h($ownerName ?: '') ?>)</div>
                    <div class="sig-position"><?= h($position ?: '') ?></div>
                </div>
            </div>

            <!-- <div style="font-family:'TH SarabunPSK'; font-size:16pt; line-height:1.2;"> เรียน <?= h($hdr_to) ?> </div>
            <div class="content-block single align-to-dean"> เพื่อโปรดพิจารณาอนุมัติ </div>
            <div class="content-block single align-to-dean" style="margin-top:50px;;"> (ผู้ช่วยศาสตราจารย์ ดร. ขนิษฐา
                นามี)<br /> หัวหน้าภาควิชาเทคโนโลยีสารสนเทศ </div> -->
            <div class="footer-actions">

                <!-- 🔵 ปุ่มแรก: พิมพ์/ดูตัวอย่าง (ทุก role ต้องมี และอยู่ลำดับแรก) -->
                <button type="button" onclick="window.print()"
                    class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold">
                    พิมพ์/ดูตัวอย่าง
                </button>

                <!-- 🟩 USER: ปุ่มยืนยัน -->
                <?php if ($roleId === 3): ?>
                <button type="submit"
                    class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold">
                    ยืนยันการแก้ไข
                </button>
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
</body>


</html>