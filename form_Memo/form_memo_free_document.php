<?php
// pro_letter/form_Memo/form_memo_free_document.php
session_start();
require_once __DIR__ . '/../functions.php';

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  exit("Unauthorized");
}

function fd_h($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fd_arabic_digits($text) {
  return strtr((string)$text, [
    '๐'=>'0','๑'=>'1','๒'=>'2','๓'=>'3','๔'=>'4',
    '๕'=>'5','๖'=>'6','๗'=>'7','๘'=>'8','๙'=>'9'
  ]);
}

function fd_thai_date($date) {
  $date = trim((string)$date);
  if ($date === '' || $date === '0000-00-00') return '';
  $ts = strtotime($date);
  if (!$ts) return $date;
  $months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
  ];
  return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . ((int)date('Y', $ts) + 543);
}

function fd_split_subject_lines($text, $limit = 82) {
  $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
  if ($text === '') return [''];
  $lines = [];
  while (mb_strlen($text, 'UTF-8') > $limit) {
    $head = mb_substr($text, 0, $limit, 'UTF-8');
    $cut = mb_strrpos($head, ' ', 0, 'UTF-8');
    if ($cut === false || $cut < 25) $cut = $limit;
    $lines[] = trim(mb_substr($text, 0, $cut, 'UTF-8'));
    $text = trim(mb_substr($text, $cut, null, 'UTF-8'));
  }
  if ($text !== '') $lines[] = $text;
  return $lines;
}

$roleId = (int)($_SESSION['role_id'] ?? 0);
$role = strtolower(trim((string)($_SESSION['role_name'] ?? 'user')));
$isAdmin = ($roleId === 1 || in_array($role, ['admin', 'administrator', 'ผู้ดูแลระบบ'], true));
$isOfficer = ($roleId === 2 || in_array($role, ['officer', 'เจ้าหน้าที่'], true));

if ($roleId === 1) {
  $homePath = "/Pro_letter/admin/home.php";
} elseif ($roleId === 2) {
  $homePath = "/Pro_letter/officer/home.php";
} else {
  $homePath = "/Pro_letter/user/home.php";
}

$pdo = db();
$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($docId <= 0) {
  header("Location: {$homePath}?err=notfound");
  exit;
}

$stmt = $pdo->prepare("
  SELECT d.*, t.template_code, t.template_name
  FROM documents d
  LEFT JOIN templates t ON t.template_id = d.template_id
  WHERE d.document_id = :id
  LIMIT 1
");
$stmt->execute([':id' => $docId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
  header("Location: {$homePath}?err=notfound");
  exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$isOwner = ((int)($document['owner_id'] ?? 0) === $userId);

if (!$isAdmin && !$isOfficer && !$isOwner) {
  header("Location: {$homePath}?err=no_view");
  exit;
}

$valueStmt = $pdo->prepare("
  SELECT tf.field_id, tf.field_key, tf.field_label, dv.value_text
  FROM document_values dv
  LEFT JOIN template_fields tf ON tf.field_id = dv.field_id
  WHERE dv.document_id = :id
");
$valueStmt->execute([':id' => $docId]);

$valueMap = [];
$valueMapById = [];
$valueMapByLabel = [];
foreach ($valueStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $key = trim((string)($row['field_key'] ?? ''));
  if ($key !== '') {
    $valueMap[$key] = (string)($row['value_text'] ?? '');
  }

  $label = trim((string)($row['field_label'] ?? ''));
  if ($label !== '') {
    $valueMapByLabel[$label] = (string)($row['value_text'] ?? '');
  }

  $valueMapById[(int)($row['field_id'] ?? 0)] = (string)($row['value_text'] ?? '');
}

$getValue = function(array $keys, $fallback = '') use ($valueMap, $valueMapById) {
  foreach ($keys as $key) {
    if (is_int($key) && array_key_exists($key, $valueMapById) && trim((string)$valueMapById[$key]) !== '') {
      return $valueMapById[$key];
    }
    if (is_string($key) && array_key_exists($key, $valueMap) && trim((string)$valueMap[$key]) !== '') {
      return $valueMap[$key];
    }
  }
  return $fallback;
};

$docStatus = trim((string)($document['status'] ?? ''));
$checkedStatuses = ['ผ่านการตรวจสอบ', 'ผ่านการตรวจสอบแล้ว', 'ได้รับการตรวจสอบ', 'ได้รับการตรวจสอบแล้ว', 'ตรวจสอบแล้ว', 'approved', 'checked', 'reviewed'];
$canEdit = !in_array($docStatus, $checkedStatuses, true);

$docNo = trim((string)($document['doc_no'] ?? ''));
$docDate = $getValue(['free_doc_date', 'doc_date', 1], (string)($document['doc_date'] ?? ''));
$subject = $getValue(['free_subject', 'subject', 14], (string)($document['subject'] ?? ''));
$toPerson = $getValue(['free_to_person', 'to_person', 26], 'คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม');
$faculty = $getValue(['free_faculty', 'faculty', 10], '');
$department = $getValue(['free_department', 'department', 11], '');
$departmentPhone = $getValue(['free_department_phone', 'department_phone'], '');

$getSignatureValue = function(array $fieldKeys, array $fieldLabels, $fallback = '') use ($valueMap, $valueMapByLabel) {
  foreach ($fieldKeys as $fieldKey) {
    if (array_key_exists($fieldKey, $valueMap) && trim((string)$valueMap[$fieldKey]) !== '') {
      return $valueMap[$fieldKey];
    }
  }

  foreach ($fieldLabels as $fieldLabel) {
    if (array_key_exists($fieldLabel, $valueMapByLabel) && trim((string)$valueMapByLabel[$fieldLabel]) !== '') {
      return $valueMapByLabel[$fieldLabel];
    }
  }

  return $fallback;
};

$signerName = trim((string)$getSignatureValue(
  ['free_signer_name', 'signer_name'],
  ['ชื่อผู้ลงนาม', 'ผู้ลงนาม']
));
$signerPosition = trim((string)$getSignatureValue(
  ['free_signer_position', 'signer_position'],
  ['ตำแหน่งผู้ลงนาม', 'ตำแหน่ง']
));

$paragraphs = [
  trim((string)$getValue(['free_paragraph_1', 'paragraph_1'], '')),
  trim((string)$getValue(['free_paragraph_2', 'paragraph_2'], '')),
  trim((string)$getValue(['free_paragraph_3', 'paragraph_3'], '')),
];

$hdrAgency = trim(
  ($faculty ?: "คณะ..................................") . " " .
  ($department ? "ภาควิชา" . $department : "ภาควิชา........................") .
  ($departmentPhone ? " โทร. " . fd_arabic_digits($departmentPhone) : "")
);
$hdrAgency = fd_arabic_digits(preg_replace('/\s+/u', ' ', $hdrAgency));

$thaiDocDate = fd_thai_date($docDate);
$subjectLines = fd_split_subject_lines($subject ?: 'บันทึกข้อความทั่วไป', 82);

$downloadSubject = trim((string)$subject);
if ($downloadSubject === '') $downloadSubject = 'บันทึกข้อความทั่วไป';
$downloadSubject = preg_replace('/[\\\\\/\:\*\?\"\<\>\|\r\n\t]+/u', ' ', $downloadSubject);
$downloadSubject = preg_replace('/\s+/u', ' ', $downloadSubject);
$downloadSubject = trim($downloadSubject);
if (function_exists('mb_strlen') && mb_strlen($downloadSubject, 'UTF-8') > 80) {
  $downloadSubject = mb_substr($downloadSubject, 0, 80, 'UTF-8');
}
$pdfDownloadName = 'บันทึกข้อความ_' . $downloadSubject . '_เลขที่_' . (int)$docId . '.pdf';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>บันทึกข้อความ #<?= fd_h($docId) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <link rel="stylesheet" href="../documents/memo-styles.css">

  <style>
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

    .doc-row {
      display: flex;
      align-items: flex-end;
      width: 100%;
      margin-bottom: 0.04cm;
      font-size: 16pt;
      line-height: 1.05;
    }

    .doc-label {
      font-size: 20pt;
      font-weight: bold;
      white-space: nowrap;
      margin-right: 0.12cm;
      line-height: 1.05;
    }

    .dot-line {
      position: relative;
      flex: 1 1 auto;
      min-height: 0.55cm;
      line-height: 1.05;
    }

    .chip {
      display: inline-block;
      position: relative;
      top: -2px;
      line-height: 1;
      font-size: 16pt;
    }

    .row-ty-date .ty-left {
      flex: 0 0 6.4cm;
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
      word-break: normal;
      overflow-wrap: normal;
    }

    .subject-line.blank-label-line {
      margin-left: 0;
    }

    .subject-text {
      display: inline-block;
      position: relative;
      top: 4px;
    }

    .doc-row:not(.subject-row) .dot-line>.chip {
      display: inline-block;
      position: relative;
      top: -2px;
      line-height: 1;
    }

    .doc-row.gov-row>.doc-label {
      width: auto !important;
      min-width: 2.15cm !important;
      white-space: nowrap !important;
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
      font-size: 16pt;
      max-width: 100%;
    }

    .page.gov-agency-overflow-fit {
      padding-left: 2.80cm !important;
      padding-right: 1.80cm !important;
    }

    .memo-to-row {
      display: flex;
      align-items: flex-end;
      margin-bottom: 6px;
    }

    .memo-to-row .memo-to-label,
    .memo-to-label {
      font-family: "TH SarabunPSK";
      font-size: 16pt;
      width: 1.15cm;
      flex: 0 0 1.15cm;
      line-height: 2;
      font-weight: normal;
      margin-right: 0;
      white-space: nowrap;
    }

    .memo-to-row .memo-to-text,
    .memo-to-text {
      font-family: "TH SarabunPSK";
      font-size: 16pt;
      font-weight: 300;
      line-height: 2;
      padding-left: 14px;
    }

    .content-block.paragraph {
      font-family: "TH SarabunPSK";
      font-size: 16pt;
      font-weight: 400;
      line-height: 1.34 !important;
      margin-top: 0 !important;
      margin-bottom: 6px !important;
      text-indent: 2.5cm;
      text-align: justify !important;
      text-align-last: left !important;
      word-spacing: -1.2px !important;
      letter-spacing: -0.05px !important;
      white-space: pre-line;
      text-justify: inter-character;
      overflow-wrap: normal;
    }

    .free-document-signature {
      font-family: "TH SarabunPSK";
      font-size: 16pt;
      font-weight: 400;
      line-height: 1.35;
      text-align: center;
      width: 7.2cm;
      margin-left: auto;
      margin-right: 0.4cm;
      margin-top: 1.35cm;
    }

    .free-document-signature .signer-name {
      margin-bottom: 0.05cm;
    }

    .footer-actions {
      margin-top: 1.0cm;
      display: flex;
      justify-content: center;
      gap: 0.35rem;
      flex-wrap: wrap;
    }

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
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    @media print {
      body { background: #fff !important; }
      .footer-actions,
      .pdf-loading-overlay { display: none !important; }
      .page {
        margin: 0 !important;
        box-shadow: none !important;
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

  <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
  <div id="alertBox" class="bg-green-500 text-white px-4 py-2 rounded-md text-center mb-4 shadow-md">
    ✅ บันทึกสำเร็จ
  </div>
  <?php elseif (isset($_GET['err']) && $_GET['err'] == 'no_permission'): ?>
  <div id="alertBox" class="bg-red-500 text-white px-4 py-2 rounded-md text-center mb-4 shadow-md">
    ❌ ไม่สามารถแก้ไขเอกสารนี้ได้
  </div>
  <?php endif; ?>

  <main class="page" id="memoPage">
    <div class="memo-title-row">
      <img src="/Pro_letter/assets/img/garuda.jpg" class="garuda-img" />
      <h1 class="doc-title">บันทึกข้อความ</h1>
    </div>

    <div class="doc-row gov-row">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ส่วนราชการ</div>
      <div class="dot-line">
        <span class="chip gov-text">
          <?= fd_h($hdrAgency ?: 'คณะ... ภาควิชา... โทร...') ?>
        </span>
      </div>
    </div>

    <div class="doc-row row-ty-date">
      <div class="doc-label" style="font-size:20pt;font-weight:bold;">ที่</div>
      <div class="dot-line ty-left">
        <span class="chip"><?= fd_h($docNo ?: '') ?></span>
      </div>
      <div class="doc-label" style="font-size:20pt;font-weight:bold;margin-left:1cm;">วันที่</div>
      <div class="dot-line ty-right">
        <span class="chip"><?= fd_h($thaiDocDate ?: '') ?></span>
      </div>
    </div>

    <div class="doc-row subject-row">
      <div class="doc-label subject-label" style="font-size:20pt;font-weight:bold;">เรื่อง</div>
      <div class="subject-wrap">
        <?php foreach ($subjectLines as $line): ?>
          <div class="subject-line"><span class="subject-text"><?= fd_h($line) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="memo-to-row">
      <div class="memo-to-label">เรียน</div>
      <div class="memo-to-text"><?= fd_h($toPerson ?: 'คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม') ?></div>
    </div>

    <?php foreach ($paragraphs as $paragraph): ?>
      <?php if ($paragraph !== ''): ?>
        <div class="content-block paragraph"><?= fd_h($paragraph) ?></div>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if (trim(implode('', $paragraphs)) === ''): ?>
      <div class="content-block paragraph">................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................</div>
    <?php endif; ?>

    <?php if ($signerName !== '' || $signerPosition !== ''): ?>
      <div class="free-document-signature">
        <?php if ($signerName !== ''): ?>
          <div class="signer-name">(<?= fd_h($signerName) ?>)</div>
        <?php endif; ?>
        <?php if ($signerPosition !== ''): ?>
          <div class="signer-position"><?= fd_h($signerPosition) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="footer-actions">
      <button type="button" onclick="downloadPdf()"
        class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-md text-xl font-bold">
        ดาวน์โหลด PDF
      </button>

      <a href="/Pro_letter/documents/download_word_free_document.php?id=<?= (int)$docId ?>"
        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
        ดาวน์โหลด Word
      </a>

      <?php if ($canEdit): ?>
      <a href="/Pro_letter/documents/infor_free_document.php?id=<?= (int)$docId ?>&edit=1"
        class="bg-teal-500 hover:bg-teal-600 text-white px-6 py-2 rounded-md text-xl font-bold inline-block">
        แก้ไขเอกสาร
      </a>
      <?php else: ?>
      <span class="bg-gray-300 text-gray-600 cursor-not-allowed px-6 py-2 rounded-md text-xl font-bold inline-block">
        แก้ไขเอกสาร
      </span>
      <?php endif; ?>

      <a href="<?= fd_h($homePath) ?>"
        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
        กลับหน้าหลัก
      </a>
    </div>
  </main>

  <script>
    function fitGovAgencyText(root = document) {
      root.querySelectorAll(".doc-row.gov-row .chip.gov-text").forEach(el => {
        const line = el.closest(".dot-line");
        const page = el.closest(".page");
        if (!line) return;

        if (!el.dataset.govOriginalText) {
          el.dataset.govOriginalText = (el.textContent || "").trim();
        }

        const originalText = el.dataset.govOriginalText;
        const compactText = originalText.replace(/\s+/gu, "");

        if (page) {
          page.classList.remove("gov-agency-overflow-fit");
        }

        el.style.setProperty("display", "inline-block", "important");
        el.style.setProperty("white-space", "nowrap", "important");
        el.style.setProperty("max-width", "none", "important");
        el.style.setProperty("letter-spacing", "normal", "important");

        const fits = () => {
          const lineWidth = Math.floor(line.getBoundingClientRect().width || line.clientWidth);
          const textWidth = Math.ceil(el.getBoundingClientRect().width || el.scrollWidth);
          return !!lineWidth && textWidth <= lineWidth + 1;
        };

        el.textContent = originalText;
        for (const size of [16, 15, 14]) {
          el.style.setProperty("font-size", size + "pt", "important");
          if (fits()) return;
        }

        el.textContent = compactText;
        el.style.setProperty("font-size", "14pt", "important");
        if (fits()) return;

        if (page) {
          page.classList.add("gov-agency-overflow-fit");
          void page.offsetWidth;
        }
        if (!fits()) {
          el.style.setProperty("letter-spacing", "-0.2px", "important");
        }
      });
    }

    document.addEventListener("DOMContentLoaded", () => {
      fitGovAgencyText(document);
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(() => fitGovAgencyText(document));
      }
    });

    setTimeout(() => {
      const alertBox = document.getElementById('alertBox');
      if (alertBox) alertBox.style.display = 'none';
    }, 2500);

    function showPdfLoading() {
      const overlay = document.getElementById('pdfLoadingOverlay');
      if (overlay) overlay.style.display = 'flex';
    }

    function hidePdfLoading() {
      const overlay = document.getElementById('pdfLoadingOverlay');
      if (overlay) overlay.style.display = 'none';
    }

    async function downloadPdf() {
      const loadingOverlay = document.getElementById("pdfLoadingOverlay");
      const loadingTitle = document.getElementById("downloadLoadingTitle");
      const loadingSubtitle = document.getElementById("downloadLoadingSubtitle");
      const downloadButtons = document.querySelectorAll("button[onclick='downloadPdf()']");

      if (loadingTitle) loadingTitle.innerText = "กำลังสร้าง PDF...";
      if (loadingSubtitle) loadingSubtitle.innerText = "กรุณารอสักครู่ ระบบกำลังเตรียมเอกสาร";
      if (loadingOverlay) loadingOverlay.style.display = "flex";

      downloadButtons.forEach(btn => {
        btn.disabled = true;
        btn.dataset.oldText = btn.innerText;
        btn.innerText = "กำลังสร้าง PDF...";
        btn.style.opacity = "0.65";
        btn.style.cursor = "not-allowed";
      });

      try {
        const { jsPDF } = window.jspdf;
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

          clone.querySelectorAll(".footer-actions").forEach(el => el.remove());

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
            line.style.setProperty("border-bottom", "none", "important");

            line.querySelectorAll(".pdf-real-dot-line").forEach(el => el.remove());

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

            if (!line.closest(".subject-row")) {
              line.querySelectorAll(".chip").forEach(chip => {
                chip.style.setProperty("display", "inline-block", "important");
                chip.style.setProperty("position", "relative", "important");
                chip.style.setProperty("top", "-2px", "important");
                chip.style.setProperty("line-height", "1", "important");
                chip.style.setProperty("z-index", "3", "important");
                chip.style.setProperty("background", "transparent", "important");
              });
            }
          });

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
            line.style.borderBottom = "2px dotted #000";
            line.style.overflow = "visible";
            line.style.fontSize = "16pt";
            line.style.wordBreak = "normal";
            line.style.overflowWrap = "normal";
            line.style.fontFamily = "TH SarabunPSK";
            line.style.backgroundImage = "none";

            if (index > 0) {
              line.style.marginTop = "-10px";
            }

            line.querySelectorAll(".subject-text").forEach(text => {
              text.style.display = "inline-block";
              text.style.position = "relative";
              text.style.top = "4px";
              text.style.zIndex = "3";
              text.style.background = "transparent";
            });
          });

          document.body.appendChild(clone);

          if (typeof fitGovAgencyText === "function") {
            fitGovAgencyText(clone);
          }

          if (document.fonts && document.fonts.ready) {
            await document.fonts.ready;
          }

          const canvas = await html2canvas(clone, {
            scale: 2.2,
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

          const imgData = canvas.toDataURL("image/png");

          if (i > 0) {
            pdf.addPage();
          }

          pdf.addImage(imgData, "PNG", 0, 0, 210, 297, undefined, "FAST");
        }

        const pdfFileName = <?= json_encode($pdfDownloadName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        pdf.save(pdfFileName);
      } catch (error) {
        console.error(error);
        alert("สร้าง PDF ไม่สำเร็จ กรุณากด F12 ดู Console");
      } finally {
        if (loadingOverlay) loadingOverlay.style.display = "none";

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
