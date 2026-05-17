<?php    //pro_letter/form_memo/internship_assessment_form.php  ขอเข้าเยี่ยมศึกษาดูงาน
session_start();
require_once __DIR__ . '/../functions.php';

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$referer = trim(str_replace(["\r","\n"],"", $referer));

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
$docStatus = $document['status'];





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

$hasEditPermission = $st->fetchColumn() > 0;   // สิทธิ์จาก permission
$allowEditByStatus = in_array($docStatus, ['draft', 'rejected']);

// User
if ($roleId === 3 || $roleId === 0) {
    $canEdit = $hasEditPermission && in_array($docStatus, ['draft', 'rejected']);
}

// Officer / Admin
elseif ($roleId === 1 || $roleId === 2) {
    $canEdit = true;
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

$name = $valueMap[1] ?? '';
$position = $valueMap[2] ?? '';
$faculty = $valueMap[3] ?? '';
$department = $valueMap[4] ?? '';
$conference = $valueMap[5] ?? '';
$country = $valueMap[6] ?? '';
$paper_title = $valueMap[7] ?? '';
$date_range = $valueMap[8] ?? '';
$table_data = $valueMap[9] ?? ''; // JSON ตารางค่าใช้จ่าย

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
  <meta name="color-scheme" content="light" />
  <title>บันทึกข้อความ #<?= h($document['document_id']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <link rel="stylesheet" href="/Pro_letter/documents/memo-styles.css">
  <style>
  @page {
    size: A4;
    margin: 0;
  }

  @media print {
    body {
      margin: 0;
      padding: 0;
    }

    .page {
      margin: 0;
      padding: 2.5cm 2cm 1cm 3cm !important;
      width: 100%;
      height: 100%;
      font-size: 16pt !important;
    }
  }
  </style>

</head>

<body class="view-document">


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

    <!-- ตราครุฑ ศูนย์กลาง -->
    <div style="display:flex; padding:0; padding-left:6.5cm; margin-bottom:-0.8cm;">
      <img src="https://i.pinimg.com/474x/bd/55/cc/bd55ccc4416012910a723da8f810658b.jpg"
        style="height:3cm; width:auto;" />
    </div>

    <!-- ส่วนหัวเอกสาร -->
    <table
      style="width:100%; border-collapse:collapse; margin-bottom:0.5cm; margin-top:-0.85cm; font-family:'TH SarabunPSK'; font-size:16pt;">
      <tr>
        <!-- ซ้าย: ที่ -->
        <td style="width:22%; vertical-align:top; padding:0; padding-right:0.3cm; white-space: nowrap;">
          <div>
            <span>ที่</span>&nbsp;&nbsp;
            <span><?= h($doc_no ?: 'อว ๗๑๒๐/๑๖๑') ?></span>
          </div>
        </td>

        <!-- ขวา: ข้อมูลส่วนราชการ (ปรับให้ข้อความสามารถเกินขอบได้) -->
        <td
          style="width:78%; vertical-align:top; padding:0; padding-left:5.8cm; text-align:left; white-space: nowrap; word-break: normal;">
          <div style="line-height:1.0;">
            <div>คณะเทคโนโลยีและการจัดการอุตสาหกรรม</div>
            <div>มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ</div>
            <div>๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐</div>
          </div>
        </td>
      </tr>
    </table>

    <!-- วันที่ (จัดตรงกลาง) -->
    <div style="margin-bottom:0.3cm; font-family:'TH SarabunPSK'; font-size:16pt; padding:0; padding-left: 7.9cm;">

      <span>๕ กุมภาพันธ์ ๒๕๖๘</span>
    </div>


    <!-- เรื่อง -->
    <div style="margin-bottom:0cm; font-family:'TH SarabunPSK'; font-size:16pt;">
      <span>เรื่อง</span>&nbsp;
      <span style="white-space: nowrap;">ขอความอนุเคราะห์ตอบแบบประเมินและแบบสำรวจนักศึกษาปฏิบัติงานสหกิจศึกษา</span>
    </div>

    <!-- เนื้อหา -->
    <div style="margin-top:-0.15cm; font-family:'TH SarabunPSK'; font-size:16pt;">
      เรียน &nbsp;เลขาธิการ สำนักงานคณะกรรมการการรักษาความมั่นคงปลอดภัยไซเบอร์แห่งชาติ (กสมช.)
    </div>

    <div class="content-block paragraph" style="margin-top:0.15cm;">
      ตามที่หน่วยงานของท่านได้ให้ความอนุเคราะห์รับนักศึกษาภาควิชาเทคโนโลยีสารสนเทศ
      <span style="white-space: nowrap; word-break: normal;">คณะเทคโนโลยีและการจัดการอุตสาหกรรม
        มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขต</span>
      ปราจีนบุรี ได้แก่
    </div>

    <div class=" content-block paragraph" style="margin-top:0.15cm;">
      นายปุณนที ปิ่นวิเศษ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; รหัสนักศึกษา &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ๖๕-๐๖๐๒๑๖-๓๐๐๓-๘
    </div>

    <div style="margin-top:-0.15cm; font-family:'TH SarabunPSK'; font-size:16pt;">
      เข้าปฏิบัติงานสหกิจศึกษาในหน่วยงานของท่าน&nbsp; ตั้งแต่วันที่ ๓ พฤศจิกายน ๒๕๖๘ ถึง ๒๗ กุมภาพันธ์ ๒๕๖๙
    </div>

    <div class="content-block paragraph" style="font-family:'TH SarabunPSK'; font-size:16pt;">
      <span style="white-space: nowrap; margin-top:-0.15cm;">ในการนี้ ภาควิชาเทคโนโลยีสารสนเทศ
        ขอความอนุเคราะห์ตอบแบบประเมินผลรายงาน</span>
      <span style="white-space: nowrap;margin-top:-0.15cm;">การปฏิบัติงานของนักศึกษาสหกิจศึกษา
        และแบบสำรวจคุณลักษณะของนักศึกษาปฏิบัติงานสหกิจศึกษาที่</span>
      <span style="white-space: nowrap; margin-top:-0.15cm;">พึงประสงค์ตามความต้องการของสถานประกอบการ(ในปีถัดไป)
        โดยภาควิชาขออนุญาตส่งแบบประเมินและ</span>
      <span style="white-space: nowrap; margin-top:-0.15cm;">แบบสำรวจดังกล่าวให้กับ “พนักงานที่ปรึกษา”
        ผ่านทางไปรษณีย์อิเล็กทรอนิกส์ it.kmutnb@itm.kmutnb.ac.th</span>
      <span style="white-space: nowrap; margin-top:-0.15cm;">ทั้งนี้ ข้อมูลที่ได้จากแบบประเมินและแบบสำรวจจะนำมารวบรวม
        วิเคราะห์ และสรุปผล ซึ่งภาควิชาจะข้อมูล</span>
      มาเป็นแนวทางสำหรับการดำเนินการครั้งต่อไป
    </div>
    <div class="content-block paragraph">
      สุดท้ายนี้ ภาควิชาเทคโนโลยีสารสนเทศ ขอขอบคุณในความอนุเคราะห์ของท่านเป็นอย่างยิ่ง
      และหวังว่าจะได้รับความอนุเคราะห์จากท่านอีกในโอกาสต่อไป
    </div>
    <div class="content-block paragraph" style="margin-bottom:-1cm;">
      จึงเรียนมาเพื่อโปรดอนุญาต และพิจารณาแจ้งผู้เกี่ยวข้องดำเนินการต่อไป
    </div>

    <div class="signature-wrapper" style="margin-top:0.3cm;margin-left: -2.5cm;">
      <div class="signature-block" id="signatureBlock">
        <div class="sig-name">ขอแสดงความนับถือ</div>
      </div>
    </div>

    <div class="signature-wrapper" style="margin-top: 0.1cm; margin-bottom: -1.5cm;margin-left: -2.4cm;">
      <div class="signature-block" id="signatureBlock">
        <div style="height:20px;"></div>
        <div class="sig-name" style="padding-top: 0.5px;">
          (ผู้ช่วยศาสตราจารย์ ดร.กฤษฎากร บุดดาจันทร์)</div>
        <div class="sig-position">คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม</div>
      </div>
    </div>
    <div style="margin-top:2cm; font-family:'TH SarabunPSK'; font-size:16pt;">
      <span style="display:block;margin-top:-0.15cm;">ภาควิชาเทคโนโลยีสารสนเทศ</span>
      <span style="display:block;margin-top:-0.15cm;">โทร. ๐ ๓๗๒๑ ๗๓๔๐ ต่อ ๗๐๖๕-๖</span>
      <span style="display:block;margin-top:-0.15cm;">ไปรษณีย์อิเล็กทรอนิกส์ : it.kmutnb@itm.kmutnb.ac.th</span>
    </div>

    <div class="footer-actions">

      <!-- พิมพ์ -->
      <button type="button" onclick="window.print()"
        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-md text-xl font-bold">
        พิมพ์/ดูตัวอย่าง
      </button>

      <!-- ปุ่มแก้ไข (ทุก role เห็นเหมือนกัน) -->
      <!-- ปุ่มแก้ไข -->
      <a href="/Pro_letter/documents/form_Memo.php?id=<?= $docId ?>" id="editBtn"
        data-can-edit="<?= $canEdit ? '1' : '0' ?>" class="px-6 py-2 rounded-md text-xl font-bold
   <?= $canEdit
      ? "bg-teal-500 hover:bg-teal-600 text-white"
      : "bg-gray-300 text-gray-600 cursor-not-allowed" ?>">
        แก้ไข
      </a>



      <!-- กลับ -->
      <a href="<?= $homePath ?>"
        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md text-xl font-bold">
        กลับหน้าหลัก
      </a>

    </div>


  </main>

  <script>
  /* ===============================
    GLOBAL HELPERS
    =============================== */
  function getQuery(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }

  /* ===============================
  DOM READY
  =============================== */
  document.addEventListener("DOMContentLoaded", () => {

    /* ---------------------------------
    1) LOCK VIEW DOCUMENT (chip)
    --------------------------------- */
    document.querySelectorAll(".view-document .chip").forEach(el => {
      el.removeAttribute("contenteditable");
      el.removeAttribute("tabindex");
      el.style.pointerEvents = "none";
      el.style.userSelect = "none";
      el.style.caretColor = "transparent";
      el.blur();
    });

    /* ---------------------------------
    2) READONLY MODE (PHP inject)
    ใช้ได้เฉพาะตอน $readonly = true
    --------------------------------- */
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

    /* ---------------------------------
    3) ALERT BOX AUTO HIDE
    --------------------------------- */
    const alertBox = document.getElementById("alertBox");
    if (alertBox) {
      setTimeout(() => {
        alertBox.style.transition = "opacity 0.5s ease";
        alertBox.style.opacity = 0;
        setTimeout(() => alertBox.remove(), 500);
      }, 3000);
    }

    /* ---------------------------------
    4) NO PERMISSION ALERT (?err=no_permission)
    --------------------------------- */
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

    /* ---------------------------------
    5) EDIT BUTTON PERMISSION CHECK
    --------------------------------- */
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

    /* ---------------------------------
    6) SAVED FROM UPDATE ALERT
    --------------------------------- */
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
  </script>




</body>


</html>