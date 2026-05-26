<?php    //pro_letter/documents/view_memo.php
session_start();
require_once __DIR__ . '/../functions.php';

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$referer = trim(str_replace(["\r","\n"],"", $referer));

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  exit("Unauthorized");
}

$userId = (int) $_SESSION['user_id'];
$role = strtolower($_SESSION['role_name'] ?? 'user');

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

$stmt = $pdo->prepare("
    SELECT document_id, template_id, owner_id, department_id, 
           doc_no, doc_date, subject, header_text, status
    FROM documents 
    WHERE document_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $docId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);
$docStatus = $document['status'];




if (!$document)
  exit("ไม่พบเอกสาร");

$roleId = (int) ($_SESSION['role_id'] ?? 0);

// officer & admin ดูได้ทุกอัน
if ($roleId !== 1 && $roleId !== 2) {
  // user: ดูเฉพาะของตัวเอง
  if ($document['owner_id'] != $userId) {
    header("Location: {$homePath}?err=no_view");
    exit;
  }
}



$sql = "
    SELECT COUNT(*) 
    FROM user_permissions up
    JOIN permissions p ON p.perm_id = up.perm_id
    WHERE up.user_id = :uid
    AND p.perm_code = 'document.edit'
";
$st = $pdo->prepare($sql);
$st->execute([':uid' => $userId]);

$hasEditPermission = $st->fetchColumn() > 0;   // สิทธิ์จาก permission
$allowEditByStatus = in_array($docStatus, ['draft', 'rejected']);


if ($roleId === 3 || $roleId === 0) {
    $canEdit = $hasEditPermission && in_array($docStatus, ['draft', 'rejected']);
}

elseif ($roleId === 1 || $roleId === 2) {
    $canEdit = true;
}

$readonly = !$canEdit;

$q = $pdo->prepare("SELECT field_id, value_text FROM document_values WHERE document_id = :id");
$q->execute([':id' => $docId]);

$valueMap = [];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $valueMap[(int) $row['field_id']] = $row['value_text'];
}
function splitSubjectLines($text, $limit = 72)
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

  if ((int)$satang === 0) {
    return $bahtText . 'ถ้วน';
  }

  return $bahtText . $convert($satang) . 'สตางค์';
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
$researchTitle = $valueMap[13] ?? "";


$noCost = (($valueMap[12] ?? '0') === '1');
$faculty = $valueMap[10] ?? "";
$department = $valueMap[11] ?? "";

$departmentPhone = "";

$docDepartmentId = (int)($document['department_id'] ?? 0);

if ($docDepartmentId > 0) {
  $stmtDept = $pdo->prepare("
    SELECT phone
    FROM departments
    WHERE department_id = :department_id
    LIMIT 1
  ");
  $stmtDept->execute([
    ':department_id' => $docDepartmentId
  ]);

  $deptRow = $stmtDept->fetch(PDO::FETCH_ASSOC);

  if ($deptRow) {
    $departmentPhone = trim($deptRow['phone'] ?? "");
  }
}
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
  $budgetTotal += (float)$item['amount'];
}
$hasExpense = (!$noCost && !empty($budgetItems) && $budgetTotal > 0);
$hasCar = trim($vehicle) !== '';

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


$header_text = $document["header_text"] ?? "";
$doc_no = $document["doc_no"] ?? "";
$subject = $document["subject"] ?? "";

$thaiDocDate = thai_date($docDate);
$displayAmount = $budgetTotal > 0 ? $budgetTotal : (float)str_replace(',', '', $amountStr);
$displayAmountNumber = number_format($displayAmount, 2);
$displayAmountThai = thaiBahtText($displayAmount);

$hdr_agency = trim(
  ($faculty ?: "คณะ..................................") . " " .
  ($department ? "ภาควิชา" . $department : "ภาควิชา........................") .
  ($departmentPhone ? " โทร. " . $departmentPhone : "")
);

$hdr_subject = $joinType ?: "เข้ารับการฝึกอบรมหลักสูตร";
$hdr_to = "คณบดี" . ($faculty ?: "คณะ..................................");

$thaiYear = "";
if ($docDate && preg_match('/^\d{4}/', $docDate)) {
  $thaiYear = ((int) substr($docDate, 0, 4) + 543);
}

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
  <link rel="stylesheet" href="../documents/memo-styles.css">

  <style>
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

  .signature-area {
    margin-top: 50px;
    margin-left: 0;
    width: max-content;
  }

  .signature-name {
    text-align: left;
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    line-height: 1.1;
    white-space: nowrap;
  }

  .signature-position {
    text-align: center;
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    line-height: 1.1;
    margin-top: 2px;
    white-space: nowrap;
  }

  .approve-line {
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    line-height: 1.2;
    margin-left: 0.9cm;
  }

  .memo-to-row {
    display: flex;
    align-items: flex-end;
    margin-bottom: 6px;
  }

  .memo-to-row .memo-to-label {
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    width: 1.15cm;
    flex: 0 0 1.15cm;
    line-height: 2;
  }

  .memo-to-row .memo-to-text {
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    font-weight: 300;
    line-height: 2;
    padding-left: 14px;
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
    word-break: break-word;
  }

  .subject-line.blank-label-line {
    margin-left: 0;
  }


  .subject-text {
    display: inline-block;
    position: relative;
    top: 4px;
  }

  .subject-inline {
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
  }

  /* ===== เนื้อหาเอกสาร: กระจายเต็มบรรทัด แต่คำไม่ห่างเกิน ===== */
  .content-block.paragraph {
    font-family: "TH SarabunPSK";
    font-size: 16pt;
    font-weight: 400;

    line-height: 1.34 !important;
    margin-top: 0 !important;
    margin-bottom: 6px !important;

    text-indent: 2.5cm;

    /* ให้กระจายเต็มบรรทัด */
    text-align: justify !important;
    text-align-last: left !important;

    /* สำคัญ: ลดการยืดช่องไฟระหว่างคำ */
    word-spacing: -1.2px !important;
    letter-spacing: -0.05px !important;

    white-space: normal;
    text-justify: inter-character;
    overflow-wrap: normal;
  }

  .content-block.paragraph .chip,
  .content-block.paragraph .keep {
    display: inline !important;
    margin: 0 !important;
    padding: 0 !important;

    line-height: inherit !important;
    word-spacing: -1.2px !important;
    letter-spacing: -0.05px !important;
    background: transparent !important;

    /* กันข้อมูลที่ดึงมาแตกห่างจากคำรอบข้าง */
    white-space: normal !important;
  }

  /* บล็อก "จึงเรียนมา..." ไม่ต้องห่างจากย่อหน้าก่อนหน้าเกินไป */
  .content-block.paragraph+.content-block.paragraph {
    margin-top: 0 !important;
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

  /* ขยับเฉพาะข้อความ "คณะ...ภาค...โทร..." ในบรรทัดส่วนราชการ */
  .gov-agency-line .gov-agency-text {
    margin-left: -0.40cm !important;
    margin-right: 0 !important;
    padding-left: 0 !important;
    padding-right: 4px !important;
    position: relative !important;
    left: 0 !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
  }



  /* แถวส่วนราชการ: ไม่ให้คำว่า "ส่วนราชการ" แตก และขยับเฉพาะจุดเริ่มข้อความคณะ */
  .doc-row.gov-row>.doc-label {
    width: auto !important;
    min-width: 2.15cm !important;
    white-space: nowrap !important;
  }

  .doc-row.gov-row>.dot-line {
    margin-left: 0 !important;
    padding-left: 0 !important;
  }

  .doc-row.gov-row>.dot-line {
    margin-left: 0 !important;
    padding-left: 0 !important;
    padding-right: 0.25cm !important;
    width: calc(100% - 2.15cm - 0.25cm) !important;
    flex: 0 0 calc(100% - 2.15cm - 0.25cm) !important;
  }

  .doc-row.gov-row>.dot-line>.chip.gov-text {
    display: inline-block !important;
    margin-left: -0.05cm !important;
    transform: none !important;
    white-space: nowrap !important;
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
</head>

<body class="view-document">

  <div id="pdfLoadingOverlay" class="pdf-loading-overlay">
    <div class="pdf-loading-box">
      <div class="pdf-spinner"></div>
      <div id="downloadLoadingTitle" class="pdf-loading-title">กำลังสร้าง PDF...</div>
      <div id="downloadLoadingSubtitle" class="pdf-loading-subtitle">กรุณารอสักครู่ ระบบกำลังเตรียมเอกสาร</div>
    </div>
  </div>
  <?php if ($readonly && !($isAdmin || $isOfficer)): ?>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
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
    <div class="memo-title-row">
      <img src="/pro_letter/assets/img/garuda.jpg" class="garuda-img" />
      <h1 class="doc-title">บันทึกข้อความ</h1>
    </div>
    <div class="doc-row gov-row">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ส่วนราชการ</div>
      <div class="dot-line">
        <span class="chip gov-text">
          <?= h($hdr_agency ?: 'คณะ... ภาควิชา... โทร...') ?>
        </span>
      </div>
    </div>
    <div class="doc-row row-ty-date">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ที่</div>
      <div class="dot-line ty-left">
        <span class="chip">
          <?= h($doc_no ?: 'ทส.486/2568') ?>
        </span>
      </div>
      <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;">วันที่</div>
      <div class="dot-line ty-right">
        <span class="chip">
          <?= h($thaiDocDate ?: '') ?>
        </span>
      </div>
    </div>
    <?php
      $mainSubjectText = 'ขออนุมัติตัวบุคคลเข้าร่วม' . ($subject ?: 'ขออนุมัติ...');
      $mainSubjectLine1 = mb_substr($mainSubjectText, 0, 82, 'UTF-8');
      $mainSubjectLine2 = mb_substr($mainSubjectText, 82, null, 'UTF-8');
    ?>
    <div class="doc-row subject-row" style="align-items:flex-start;">
      <div class="doc-label subject-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>
      <div class="subject-wrap">
        <div class="subject-line"><span class="subject-text"><?= h($mainSubjectLine1) ?></span></div>
        <?php if (trim($mainSubjectLine2) !== ''): ?>
        <div class="subject-line"><span class="subject-text"><?= h($mainSubjectLine2) ?></span></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="memo-to-row">
      <div class="memo-to-label">เรียน</div>
      <div class="memo-to-text">คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม</div>
    </div>
    <div class="content-block paragraph">
      ตามที่ กำหนดจัดอบรมหลักสูตร
      <span class="chip"><?= h($courseName ?: 'ชื่อหลักสูตร') ?></span>
      ระหว่างวันที่
      <span class="chip keep"><?= h($joinDates ?: '...') ?></span>
      ณ <span class="chip"><?= h($location ?: '...') ?></span> นั้น
      ซึ่งหลักสูตรดังกล่าวเป็นประโยชน์ต่อการพัฒนาทั้งกระบวนการจัดการเรียนการสอน
    </div>
    <div class="content-block paragraph">
      การนี้ ข้าพเจ้า
      <span class="chip"><?= h($ownerName ?: 'ชื่อ-นามสกุล') ?></span>
      <span class="chip"><?= h($position ?: '') ?></span>
      สังกัดภาควิชา<span class="chip"><?= h($department ?: '...') ?></span>
      <span class="chip"><?= h($faculty ?: '...') ?></span>
      มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
      จึงมีความประสงค์ที่จะขออนุมัติ เข้ารับการอบรมหลักสูตร
      <span class="chip keep"><?= h($courseName ?: 'ชื่อหลักสูตร') ?></span>
      ระหว่างวันที่ <span class="chip"><?= h($joinDates ?: '') ?></span>
      ณ <span class="chip"><?= h($location ?: '') ?></span>
      <?php if ($hasExpense): ?>
      เป็นเงินจำนวน <span class="chip"><?= h($displayAmountNumber) ?></span> บาท
      (<span class="chip"><?= h($displayAmountThai) ?></span>)
      โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ
      <span class="chip">
        <?= h($thaiYear ? 'พ.ศ. ' . $thaiYear : 'พ.ศ. ....') ?>
      </span>
      แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย
      <span class="keep">(รายละเอียดตามเอกสารแนบ)</span>
      <?php else: ?>
      โดยไม่เบิกค่าใช้จ่ายใดๆ ทั้งสิ้น
      <?php endif; ?>
    </div>
    <div class="content-block paragraph">
      จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ
    </div>
    <div class="signature-wrapper">
      <div class="signature-block" id="signatureBlock">
        <div class="sig-name">(<?= h($ownerName ?: '') ?>)</div>
        <div class="sig-position"><?= h($position ?: '') ?></div>
      </div>
    </div>

    <?php if (!$hasExpense): ?>
    <div class="footer-actions">
      <button type="button" onclick="downloadPdf()"
        class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
        ดาวน์โหลด PDF
      </button>

      <a href="/Pro_letter/documents/download_word_memo.php?id=<?= (int)$docId ?>" data-word-download="1"
        onclick="return downloadWord(this);"
        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
        ดาวน์โหลด Word
      </a>

      <a href="/Pro_letter/documents/form_Memo.php?id=<?= (int)$docId ?>" id="editBtn"
        data-can-edit="<?= $canEdit ? '1' : '0' ?>" class="px-6 py-2 rounded-md text-xl font-bold
        <?= $canEdit
          ? "bg-teal-500 hover:bg-teal-600 text-white"
          : "bg-gray-300 text-gray-600 cursor-not-allowed" ?>">
        แก้ไขเอกสาร
      </a>

      <a href="<?= $homePath ?>"
        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
        กลับหน้าหลัก
      </a>
    </div>
    <?php endif; ?>
  </main>

  <!-- section ค่าใช้จ่าย -->
  <?php if ($hasExpense): ?>
  <section class="page">
    <div class="memo-title-row">
      <img src="/Pro_letter/assets/img/garuda.jpg" class="garuda-img" />
      <h1 class="doc-title">บันทึกข้อความ</h1>
    </div>
    <div class="doc-row gov-row">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ส่วนราชการ</div>
      <div class="dot-line">
        <span class="chip gov-text">
          <?= h($hdr_agency  ?: 'คณะ... ภาควิชา... โทร...') ?>
        </span>
      </div>
    </div>
    <div class="doc-row row-ty-date">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ที่</div>
      <div class="dot-line ty-left">
        <span class="chip">
          <?= h($doc_no ?: 'ทส.486/2568') ?>
        </span>
      </div>
      <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;">วันที่</div>
      <div class="dot-line ty-right">
        <span class="chip">
          <?= h($thaiDocDate ?: '') ?>
        </span>
      </div>
    </div>
    <?php
      $expenseSubjectText = 'ขออนุมัติค่าใช้จ่ายในการเข้าร่วม' . ($subject ?: 'ขออนุมัติ...');
      $expenseSubjectLine1 = mb_substr($expenseSubjectText, 0, 82, 'UTF-8');
      $expenseSubjectLine2 = mb_substr($expenseSubjectText, 82, null, 'UTF-8');
    ?>
    <div class="doc-row subject-row" style="align-items:flex-start;">
      <div class="doc-label subject-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>
      <div class="subject-wrap">
        <div class="subject-line"><span class="subject-text"><?= h($expenseSubjectLine1) ?></span></div>
        <?php if (trim($expenseSubjectLine2) !== ''): ?>
        <div class="subject-line"><span class="subject-text"><?= h($expenseSubjectLine2) ?></span></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="memo-to-row">
      <div class="memo-to-label">เรียน</div>
      <div class="memo-to-text">คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม</div>
    </div>

    <div class="content-block paragraph">
      การนี้ ข้าพเจ้า
      <span class="chip"><?= h($ownerName ?: 'ชื่อ-นามสกุล') ?></span>
      <span class="chip"><?= h($position ?: '') ?></span>
      สังกัดภาควิชา<span class="chip"><?= h($department ?: '...') ?></span>
      <span class="chip"><?= h($faculty ?: '...') ?></span>
      มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
      จึงมีความประสงค์ขออนุมัติค่าใช้จ่ายในการเข้าร่วม
      <span class="chip subject-inline"><?= h($subject ?: 'ขออนุมัติ...') ?></span>
      ระหว่างวันที่ <span class="chip"><?= h($joinDates ?: '') ?></span>
      ณ <span class="chip"><?= h($location ?: '') ?></span>
      <?php if ($hasExpense): ?>
      วงเงินทั้งสิ้น <span class="chip"><?= h($displayAmountNumber) ?></span> บาท
      (<span class="chip"><?= h($displayAmountThai) ?></span>)
      โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ
      <span class="chip">
        <?= h($thaiYear ? 'พ.ศ. ' . $thaiYear : 'พ.ศ. ....') ?>
      </span>
      ในส่วนของภาควิชา
      เทคโนโลยีสารสนเทศ แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย
      <span class="keep">(รายละเอียดตามเอกสารแนบ)</span>
      <?php else: ?>
      โดยไม่เบิกค่าใช้จ่ายใดๆ ทั้งสิ้น
      <?php endif; ?>
    </div>
    <div class="content-block paragraph">
      จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ
    </div>
    <div class="signature-wrapper">
      <div class="signature-block" id="signatureBlock">
        <div class="sig-name">(<?= h($ownerName ?: '') ?>)</div>
        <div class="sig-position"><?= h($position ?: '') ?></div>
      </div>
    </div>

    <?php if (!$hasExpense): ?>
    <div class="footer-actions">
      <button type="button" onclick="downloadPdf()"
        class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
        ดาวน์โหลด PDF
      </button>

      <a href="/Pro_letter/documents/download_word_memo.php?id=<?= (int)$docId ?>" data-word-download="1"
        onclick="return downloadWord(this);"
        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
        ดาวน์โหลด Word
      </a>

      <a href="/Pro_letter/documents/form_Memo.php?id=<?= (int)$docId ?>" id="editBtn"
        data-can-edit="<?= $canEdit ? '1' : '0' ?>" class="px-6 py-2 rounded-md text-xl font-bold
        <?= $canEdit
          ? "bg-teal-500 hover:bg-teal-600 text-white"
          : "bg-gray-300 text-gray-600 cursor-not-allowed" ?>">
        แก้ไขเอกสาร
      </a>

      <a href="<?= $homePath ?>"
        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
        กลับหน้าหลัก
      </a>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>


  <!-- section รถยนต์ -->
  <?php if ($hasCar): ?>
  <section class="page">
    <div class="memo-title-row">
      <img src="/Pro_letter/assets/img/garuda.jpg" class="garuda-img" />
      <h1 class="doc-title">บันทึกข้อความ</h1>
    </div>
    <div class="doc-row gov-row">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ส่วนราชการ</div>
      <div class="dot-line">
        <span class="chip gov-text">
          <?= h($hdr_agency ?: 'คณะ... ภาควิชา... โทร...') ?>
        </span>
      </div>
    </div>
    <div class="doc-row row-ty-date">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ที่</div>
      <div class="dot-line ty-left">
        <span class="chip">
          <?= h($doc_no ?: 'ทส.486/2568') ?>
        </span>
      </div>
      <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;">วันที่</div>
      <div class="dot-line ty-right">
        <span class="chip">
          <?= h($thaiDocDate ?: '') ?>
        </span>
      </div>
    </div>
    <?php
      $carSubjectText = 'ขออนุมัติใช้รถยนต์ส่วนบุคคลในการเดินทางไปเข้าร่วม' . ($subject ?: 'ขออนุมัติ...');
      $carSubjectLine1 = mb_substr($carSubjectText, 0, 82, 'UTF-8');
      $carSubjectLine2 = mb_substr($carSubjectText, 82, null, 'UTF-8');
    ?>
    <div class="doc-row subject-row" style="align-items:flex-start;">
      <div class="doc-label subject-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>
      <div class="subject-wrap">
        <div class="subject-line"><span class="subject-text"><?= h($carSubjectLine1) ?></span></div>
        <?php if (trim($carSubjectLine2) !== ''): ?>
        <div class="subject-line"><span class="subject-text"><?= h($carSubjectLine2) ?></span></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="memo-to-row">
      <div class="memo-to-label">เรียน</div>
      <div class="memo-to-text">คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม</div>
    </div>

    <div class="content-block paragraph">
      ตามที่ ข้าพเจ้า
      <span class="chip"><?= h($ownerName ?: 'ชื่อ-นามสกุล') ?></span>
      <span class="chip"><?= h($position ?: '') ?></span>
      สังกัดภาควิชา<span class="chip"><?= h($department ?: '...') ?></span>
      <span class="chip"><?= h($faculty ?: '...') ?></span>
      มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
      จึงมีความประสงค์ที่จะขออนุมัติ เข้ารับการอบรมหลักสูตร
      <span class="chip keep"><?= h($courseName ?: 'ชื่อหลักสูตร') ?></span>
      ระหว่างวันที่ <span class="chip"><?= h($joinDates ?: '') ?></span>
      ณ <span class="chip"><?= h($location ?: '') ?></span> นั้น


    </div>
    <div class="content-block paragraph">
      ในการนี้ ข้าพเจ้าจึงขออนุมัติใช้รถยนต์ส่วนบุคคล หมายเลขทะเบียน
      <span class="chip"><?= h($vehicle ?: '...') ?></span>
      ในการเดินทางไป<span class="chip subject-inline"><?= h($subject ?: 'ชื่อหลักสูตร') ?></span>
      ตามวัน เวลา และสถานที่ดังกล่าว ทั้งนี้ โดยให้เป็นไปตามหลักเกณฑ์และวิธีการของมหาวิทยาลัย


    </div>
    <div class="content-block paragraph">
      จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ
    </div>
    <div class="signature-wrapper">
      <div class="signature-block" id="signatureBlock">
        <div class="sig-name">(<?= h($ownerName ?: '') ?>)</div>
        <div class="sig-position"><?= h($position ?: '') ?></div>
      </div>
    </div>

    <?php if (!$hasExpense): ?>
    <div class="footer-actions">
      <button type="button" onclick="downloadPdf()"
        class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
        ดาวน์โหลด PDF
      </button>

      <a href="/Pro_letter/documents/download_word_memo.php?id=<?= (int)$docId ?>" data-word-download="1"
        onclick="return downloadWord(this);"
        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
        ดาวน์โหลด Word
      </a>

      <a href="/Pro_letter/documents/form_Memo.php?id=<?= (int)$docId ?>" id="editBtn"
        data-can-edit="<?= $canEdit ? '1' : '0' ?>" class="px-6 py-2 rounded-md text-xl font-bold
        <?= $canEdit
          ? "bg-teal-500 hover:bg-teal-600 text-white"
          : "bg-gray-300 text-gray-600 cursor-not-allowed" ?>">
        แก้ไขเอกสาร
      </a>

      <a href="<?= $homePath ?>"
        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
        กลับหน้าหลัก
      </a>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  <?php if ($hasExpense): ?>
  <div class="page">
    <form action="save_expense.php" method="post" id="expenseForm">
      <div class="expense-title-section">
        <?php if ($purposeCode === 'academic'): ?>
        <h2 class="expense-main-title">
          ประมาณการค่าใช้จ่าย<br>
          การนำเสนอผลงานวิจัยในการประชุมวิชาการ
        </h2>

        <div class="text-[16pt] leading-[1.15]">
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
            <div class="flex-1"><?= h($researchTitle ?: '-') ?></div>
          </div>
        </div>
        <?php else: ?>
        <h2 class="expense-main-title">
          ประมาณการค่าใช้จ่าย
        </h2>

        <div class="text-[16pt] font-bold text-center leading-[1.05]">
          <div class="mb-[2px]">
            ค่าใช้จ่ายในการ<?= h($joinType ?: 'เข้าร่วม') ?>
          </div>

          <div class="mb-[2px] font-bold">
            <?= ($purposeCode === 'training') ? 'หลักสูตร' : 'หัวข้อ/งาน' ?>
            “<?= h($courseName ?: '-') ?>”
          </div>

          <div class="mb-[2px] font-bold">
            ระหว่างวันที่ <?= h($joinDates ?: '-') ?>
          </div>

          <div class="mb-[2px] font-bold">
            สถานที่ <?= h($location ?: '-') ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <h2 class="text-[16pt] font-bold mt-4 mb-3 text-left">
        ตารางสรุปค่าใช้จ่ายในการไปนำเสนอผลงานวิจัย
      </h2>
      <table id="expenseTable" style="width:100%; border-collapse:collapse; font-family:'TH SarabunPSK';
              font-size:16pt; line-height:1.15; table-layout:fixed;">
        <tr style="height:28px;">
          <th style="
            width:75px;
            border:0.6px solid #000; 
            padding:3px 4px; 
            text-align:center; 
            font-weight:bold;">
            ลำดับที่
          </th>
          <th style="
    width:65%; 
    border:0.6px solid #000; 
    padding:3px 6px; 
    text-align:center; 
    font-weight:bold;
    vertical-align: top;
">
            รายการ
          </th>
          <th style="
    width:120px; 
    border:0.6px solid #000; 
    padding:3px 4px; 
    text-align:center; 
    font-weight:bold;
    vertical-align: top;
">
            จำนวนเงิน (บาท)
          </th>
        </tr>
        <?php if (!empty($budgetItems)): ?>
        <?php foreach ($budgetItems as $index => $item): ?>
        <tr>
          <td style="border:0.6px solid #000; padding:3px 4px; text-align:center; vertical-align: top;">
            <?= $index + 1 ?>
          </td>
          <td style="border:0.6px solid #000; padding:3px 8px; text-align:left; vertical-align: top;">
            <?= nl2br(h($item['description'] ?: $item['item_type'])) ?>
          </td>
          <td style="border:0.6px solid #000; padding:3px 4px; text-align:right; vertical-align: top;">
            <?= number_format((float)$item['amount'], 2) ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr>
          <td colspan="3" style="border:0.6px solid #000; padding:8px; text-align:center;">
            ไม่พบข้อมูลประมาณค่าใช้จ่าย
          </td>
        </tr>
        <?php endif; ?>

        <tr>
          <th style="border:0.6px solid #000; padding:3px 4px; background:#ffffff;"></th>
          <th style="border:0.6px solid #000; padding:3px 6px; text-align:left; font-weight:bold; background:#ffffff;">
            รวมเป็นเงิน
          </th>
          <th style="border:0.6px solid #000; padding:3px 4px; text-align:right; font-weight:bold; background:#ffffff;">
            <?= number_format($budgetTotal, 2) ?>
          </th>
        </tr>
      </table>

      <div style="
        margin-top:8px;
        text-align:left;
        font-family:'TH SarabunPSK';
        font-size:16pt;
        line-height:1.2;
      ">
        <strong>หมายเหตุ</strong> ขอถัวจ่ายทุกรายการ
      </div>

      <input type="hidden" name="doc_id" value="<?= $docId ?>">
      <input type="hidden" name="table_data" id="table_data">
      <div class="footer-actions">
        <button type="button" onclick="downloadPdf()"
          class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
          ดาวน์โหลด PDF
        </button>

        <a href="/Pro_letter/documents/download_word_memo.php?id=<?= (int)$docId ?>" data-word-download="1"
          onclick="return downloadWord(this);"
          class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
          ดาวน์โหลด Word
        </a>

        <a href="/Pro_letter/documents/form_Memo.php?id=<?= (int)$docId ?>" id="editBtn"
          data-can-edit="<?= $canEdit ? '1' : '0' ?>" class="px-6 py-2 rounded-md text-xl font-bold
   <?= $canEdit
      ? "bg-teal-500 hover:bg-teal-600 text-white"
      : "bg-gray-300 text-gray-600 cursor-not-allowed" ?>">
          แก้ไขเอกสาร
        </a>
        <a href="<?= $homePath ?>"
          class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
          กลับหน้าหลัก
        </a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <script>
  function getQuery(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }
  document.addEventListener("DOMContentLoaded", () => {

    // ===== จัดช่องว่างในเนื้อหาไม่ให้คำห่างเกินตอนใช้ justify =====
    document.querySelectorAll(".content-block.paragraph").forEach(block => {
      block.childNodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) {
          node.textContent = node.textContent.replace(/\s+/g, " ");
        }
      });
    });

    document.querySelectorAll(".view-document .chip").forEach(el => {
      el.removeAttribute("contenteditable");
      el.removeAttribute("tabindex");
      el.style.pointerEvents = "none";
      el.style.userSelect = "none";
      el.style.caretColor = "transparent";
      el.blur();
    });

    <?php if ($readonly && !($isAdmin || $isOfficer)): ?>
    document.querySelectorAll("input, textarea, select").forEach(el => {
      el.disabled = true;
      el.style.background = "#f0f0f0";
    });
    const submitBtn = document.querySelector("button[type=submit]");
    if (submitBtn) submitBtn.style.display = "none";

    Swal.fire({
      title: "โหมดอ่านอย่างเดียว",
      text: "คุณไม่มีสิทธิ์แก้ไขเอกสารนี้",
      icon: "info",
      confirmButtonText: "ตกลง"
    });
    <?php endif; ?>
    const alertBox = document.getElementById("alertBox");
    if (alertBox) {
      setTimeout(() => {
        alertBox.style.transition = "opacity 0.5s ease";
        alertBox.style.opacity = 0;
        setTimeout(() => alertBox.remove(), 500);
      }, 3000);
    }

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
            text: "คุณไม่มีสิทธิ์แก้ไขเอกสารนี้",
            icon: "warning",
            confirmButtonText: "ตกลง"
          });
        }
      });
    }

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

    const downloadUrl = new URL(link.href, window.location.href);
    downloadUrl.searchParams.set("_download_time", Date.now().toString());
    link.href = downloadUrl.toString();

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

    // ซ่อน popup ให้เร็วขึ้น เพราะการดาวน์โหลดแบบลิงก์ปกติไม่สามารถตรวจจับจังหวะที่ไฟล์ถูกบันทึกเสร็จได้โดยตรง
    // ค่านี้ใช้ให้ popup ขึ้นทันทีตอนกด แล้วหายหลัง browser เริ่มรับไฟล์ดาวน์โหลดแล้ว
    setTimeout(resetWordDownloadUI, 700);

    return true;
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

        // ===== จัดช่องว่างใน clone ก่อนแปลงเป็น PDF =====
        clone.querySelectorAll(".content-block.paragraph").forEach(block => {
          block.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE) {
              node.textContent = node.textContent.replace(/\s+/g, " ");
            }
          });

          block.style.setProperty("text-align", "justify", "important");
          block.style.setProperty("text-align-last", "left", "important");
          block.style.setProperty("text-justify", "inter-character", "important");
          block.style.setProperty("word-spacing", "-1.2px", "important");
          block.style.setProperty("letter-spacing", "-0.05px", "important");
          block.style.setProperty("line-height", "1.34", "important");
          block.style.setProperty("margin-bottom", "4px", "important");
        });

        clone.querySelectorAll(".content-block.paragraph .chip, .content-block.paragraph .keep").forEach(el => {
          el.style.setProperty("display", "inline", "important");
          el.style.setProperty("margin", "0", "important");
          el.style.setProperty("padding", "0", "important");
          el.style.setProperty("word-spacing", "-1.2px", "important");
          el.style.setProperty("letter-spacing", "-0.05px", "important");
          el.style.setProperty("background", "transparent", "important");
        });

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

        // แก้ไขตำแหน่งเส้นประในส่วน "เรื่อง" (ค้นหาบรรทัดประมาณที่ 835)
        clone.querySelectorAll(".subject-line").forEach((line, index) => {
          line.querySelectorAll(".pdf-subject-dot-line").forEach(el => el.remove());

          line.style.position = "relative";
          line.style.height = "auto";
          line.style.minHeight = "20px"; // เพิ่มความสูงขั้นต่ำเพื่อให้มีพื้นที่ดึงเส้นลงมา
          line.style.lineHeight = "1.2"; // ปรับระยะบรรทัดให้โปร่งขึ้นเล็กน้อย
          line.style.paddingLeft = "18px";
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
            text.style.top = "8px";

            text.style.zIndex = "3";
            text.style.background = "transparent";
          });
        });
        const expenseTable = clone.querySelector("#expenseTable");

        if (expenseTable) {
          // 1. เปลี่ยนเป็น separate และห่าง 0 เพื่อแก้บั๊ก html2canvas วาดเส้นซ้อนกันจนหนาเตอะ
          expenseTable.style.borderCollapse = "separate";
          expenseTable.style.borderSpacing = "0";
          expenseTable.style.width = "100%";
          expenseTable.style.tableLayout = "fixed";
          expenseTable.style.background = "#ffffff";

          // 2. กำหนดให้ตัวตารางหลักมีเส้นขอบแค่ ด้านบน และ ด้านซ้าย
          expenseTable.style.setProperty("border", "none", "important");
          expenseTable.style.setProperty("border-top", "0.5px solid #414141", "important");
          expenseTable.style.setProperty("border-left", "0.5px solid #414141", "important");

          expenseTable.querySelectorAll("th").forEach(th => {
            // 3. หัวตารางแต่ละช่อง ให้มีเส้นขอบแค่ ด้านล่าง และ ด้านขวา (เส้นจะไม่ซ้อนกัน)
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
            // 4. ช่องข้อมูลแต่ละช่อง ให้มีเส้นขอบแค ด้านล่าง และ ด้านขวา เช่นกัน
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
              // 5. ช่องแถวสรุปผลรวม ก็ใช้หลักการ ด้านล่าง และ ด้านขวา
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

      pdf.save("memo_<?= $docId ?>.pdf");

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