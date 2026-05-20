<?php 
// ต้องวางตรงนี้! บรรทัดแรกของไฟล์
$CURRENT_MAIN = "external";
$CURRENT_SUB  = "หนังสือเรียนเชิญวิทยากร (ของนักศึกษา)";
           // ถ้าไม่มีหมวดย่อย ให้เว้นว่าง
?>
<!--หนังสือเรียนเชิญวิทยากร Pro_letter/documents/infor_invite.php  -->
<?php
session_start();
require_once __DIR__ . '/../functions.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

/* =========================================================
   โหลดข้อมูลเดิมตอนกลับมาแก้ไข
   ใช้เมื่อ URL มี ?id=DOCUMENT_ID
   ========================================================= */
$documentId = (int)($_GET['id'] ?? $_POST['document_id'] ?? 0);
$isEdit = $documentId > 0;

$formData = [];
$formDataByKey = [];
$docRow = [];

$defaultFaculty = 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
$defaultDepartment = 'เทคโนโลยีสารสนเทศ';

if ($isEdit) {
    try {
        $pdo = db();

        $stmtDoc = $pdo->prepare("SELECT * FROM documents WHERE document_id = :id LIMIT 1");
        $stmtDoc->execute([':id' => $documentId]);
        $docRow = $stmtDoc->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmtVals = $pdo->prepare("
            SELECT 
                dv.field_id,
                dv.value_text,
                tf.field_key
            FROM document_values dv
            LEFT JOIN template_fields tf ON tf.field_id = dv.field_id
            WHERE dv.document_id = :id
        ");
        $stmtVals->execute([':id' => $documentId]);

        foreach ($stmtVals->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fid = (int)($row['field_id'] ?? 0);
            $val = (string)($row['value_text'] ?? '');

            if ($fid > 0) {
                $formData[$fid] = $val;
            }

            $key = trim((string)($row['field_key'] ?? ''));
            if ($key !== '') {
                $formDataByKey[$key] = $val;
            }
        }
    } catch (Throwable $e) {
        // ถ้าโหลดข้อมูลเดิมไม่ได้ ให้เปิดฟอร์มต่อได้ แต่ไม่ให้หน้าเว็บพัง
        $formData = [];
        $formDataByKey = [];
        $docRow = [];
    }
}

$subjectValue = $formData[14] ?? ($docRow['subject'] ?? '');
$toPersonValue = $formData[26] ?? '';
$docDateValue = $formData[1] ?? ($docRow['doc_date'] ?? '');
$projectTitleValue = $formData[5] ?? '';
$inviteStatementValue = $formDataByKey['invite_statement'] ?? '';
$objectiveValue = $formData[25] ?? '';
$eventDateValue = $formData[6] ?? ($formData[16] ?? '');
$locationValue = $formData[7] ?? '';
$facultyValue = $formData[10] ?? $defaultFaculty;
$departmentValue = $formData[11] ?? $defaultDepartment;
$eventTimeValue = $formDataByKey['event_time'] ?? '';

$timeStartValue = '';
$timeEndValue = '';
if ($eventTimeValue !== '') {
    $timeParts = preg_split('/\s*(?:-|ถึง)\s*/u', $eventTimeValue);
    $timeStartValue = trim($timeParts[0] ?? '');
    $timeEndValue = trim($timeParts[1] ?? '');
}

$formAction = $isEdit ? '/Pro_letter/update_memo.php' : '/Pro_letter/documents/save_memo.php';
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>แบบฟอร์มบันทึกข้อความ</title>

  <!-- ✅ เพิ่มส่วนนี้ -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css" />
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
  <!-- ✅ จบส่วนที่เพิ่ม -->

  <script src="https://cdn.tailwindcss.com"></script>

  <style>
  @import url("https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap");

  html,
  :root {
    --base-fs: 16px;
  }

  body,
  label,
  input,
  textarea,
  select,
  option,
  button,
  span,
  div {
    font-size: var(--base-fs);
  }

  select,
  input,
  textarea {
    line-height: 1.4;
  }

  select option {
    font-size: var(--base-fs);
  }

  #requestListContainer {
    flex: 1;
    overflow-y: auto;
  }

  .custom-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background: white;
    border: 2px solid #11c2b9;
    border-radius: 1rem;
    padding: 0.5rem 2.5rem 0.5rem 0.75rem;
    background-image: url('data:image/svg+xml;utf8,<svg fill="%23000000" height="16" viewBox="0 0 20 20" width="16" xmlns="http://www.w3.org/2000/svg"><path d="M5.516 7.548l4.486 4.448 4.486-4.448L15.56 9l-5.558 5.5L4.444 9z"/></svg>');
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
  }

  .custom-select:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(17, 194, 185, 0.35);
  }

  /* error styles */
  .error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15);
  }

  .lbl.asterisk::after {
    content: " *";
    color: #ef4444;
    font-weight: 700;
    margin-left: 4px;
  }

  /* floating hint bubble */
  .hint {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fee2e2;
    border: 1px solid #ef4444;
    color: #991b1b;
    padding: 4px 8px;
    border-radius: 8px;
    margin-top: 6px;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.03);
  }

  .hint svg {
    min-width: 16px;
    min-height: 16px;
  }

  .hint:before {
    content: "";
    position: absolute;
    top: -6px;
    left: 16px;
    border-width: 6px;
    border-style: solid;
    border-color: transparent transparent #ef4444 transparent;
  }

  .hint:after {
    content: "";
    position: absolute;
    top: -5px;
    left: 16px;
    border-width: 5px;
    border-style: solid;
    border-color: transparent transparent #fee2e2 transparent;
  }

  .shake {
    animation: shake 0.2s linear 0s 2;
  }

  .spell-error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
    background-color: #fffafa;
  }

  .spell-ok {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    background-color: #f0fdf4;
  }

  .spell-box {
    margin-top: 8px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #fff7ed;
    border: 1px solid #fdba74;
    color: #9a3412;
    font-size: 14px;
    line-height: 1.6;
  }

  .spell-box.hidden,
  .spell-loading.hidden {
    display: none !important;
  }

  .spell-result-box {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .spell-warning {
    font-weight: 600;
    color: #991b1b;
  }

  .spell-help-text {
    font-size: 13px;
    color: #9a3412;
    font-weight: 500;
  }

  .spell-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
  }

  .spell-suggestion-btn {
    border: 1px solid #fdba74;
    background: #ffffff;
    color: #9a3412;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
  }

  .spell-ignore-btn {
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
  }

  .spell-loading {
    margin-top: 8px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #eff6ff;
    border: 1px solid #93c5fd;
    color: #1d4ed8;
    font-size: 14px;
  }

  .spell-loading-row {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .spell-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #bfdbfe;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
  }

  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }

  @keyframes shake {

    0%,
    100% {
      transform: translateX(0);
    }

    25% {
      transform: translateX(-3px);
    }

    75% {
      transform: translateX(3px);
    }
  }
  </style>
</head>

<body class="bg-gray-100">
  <header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md"
    style="font-family: Arial, Helvetica, sans-serif">
    <div class="flex items-center space-x-3">
      <div class="w-[56px] h-[56px] flex items-center justify-center relative overflow-visible">
        <svg xmlns="http://www.w3.org/2000/svg" class="absolute scale-[1.4] text-white"
          style="width: 60px; height: 60px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m0 0a2 2 0 00-2-2H5a2 2 0 00-2 2m18 0v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8" />
        </svg>
      </div>
      <div class="leading-tight">
        <div class="text-[16px] font-bold">Smart</div>
        <div class="text-[16px] font-bold -mt-[2px]">Government</div>
        <div class="text-[13px] mt-[0px]">Letter Assistant System</div>
      </div>
    </div>
    <div class="flex items-center space-x-4">
      <a href="/Pro_letter/user/home.php">
        <div class="px-4 py-2 rounded-[11px] font-bold transition text-white">
          หน้าหลัก
        </div>
      </a>

      <?php 
                if (isset($_SESSION['permissions']) && in_array(3, $_SESSION['permissions'])) {
                    renderAdminExtraMenus(); 
                }
            ?>

      <a href="/Pro_letter/form_Memo/Request/infor_invite.php">
        <div class="px-4 py-2 rounded-[11px] font-bold transition bg-white text-teal-500 shadow">
          แบบฟอร์มบันทึกข้อความ
        </div>
      </a>

      <div class="relative">
        <!-- ปุ่ม Profile -->
        <button id="profileBtn"
          class="bg-white text-teal-500 px-4 py-2 rounded-[11px] shadow flex items-center space-x-2 hover:bg-gray-100">
          <div class="text-right leading-tight">
            <div class="font-bold text-[14px]">
              <?= htmlspecialchars($_SESSION['fullname'] ?? 'Guest') ?>
            </div>
            <div class="text-[12px]">
              <?= htmlspecialchars($_SESSION['role_name'] ?? '') ?>
            </div>

          </div>
          <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
              stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M5.121 17.804A13.937 13.937 0 0112 15c2.33 0 4.487.577 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
        </button>

        <!-- เมนู Dropdown -->
        <div id="profileMenu" class="hidden absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-50">
          <a href="../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
          <button onclick="closeMenu()"
            class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">อยู่ต่อ</button>
        </div>
      </div>
    </div>
  </header>

  <form method="post" action="<?= h($formAction) ?>" id="memoForm">
    <?php if ($isEdit): ?>
    <input type="hidden" name="document_id" value="<?= (int)$documentId ?>">
    <?php endif; ?>
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="purpose" value="invite_speaker_student">
    <input type="hidden" name="form_type" value="invite">
    <input type="hidden" name="document_type" value="infor_invite">
    <input type="hidden" name="target_form" value="infor_invite.php">
    <input type="hidden" name="redirect_to" value="infor_invite.php">
    <!-- กล่องเนื้อหา -->
    <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
      <h1 class="text-center font-bold mb-6 text-black">
        แบบฟอร์มบันทึกข้อความ
      </h1>

      <!-- หมวดหมู่ -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-6 rounded-[25px] border-2" style="
            background-color: #e3f9f8;
            border-color: #11c2b9;
            min-height: 170px;
          ">
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 w-28 text-right">หมวดหลัก:</label>
          <div class="relative w-full">
            <select name="main_category" class="custom-select w-full" id="mainCategory">
              <option value="">-- เลือกหมวดหลัก --</option>
              <option value="train" <?= ($CURRENT_MAIN=="train"?"selected":"") ?>>ฝึกอบรม</option>
              <option value="academic" <?= ($CURRENT_MAIN=="academic"?"selected":"") ?>>
                ประชุมวิชาการ/ศึกษาดูงาน/สัมมนาวิชาการ</option>
              <option value="external" <?= ($CURRENT_MAIN=="external"?"selected":"") ?>>ภายนอก</option>
              <option value="internal" <?= ($CURRENT_MAIN=="internal"?"selected":"") ?>>
                ภายใน(บันทึกข้อความ)</option>
            </select>

          </div>
        </div>

        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 w-28 text-right">หมวดย่อย:</label>
          <div class="relative w-full">
            <select name="sub_category" class="custom-select w-full" id="subCategory"
              data-current="<?= h($CURRENT_SUB ?? '') ?>" disabled>
              <option value="">-- เลือกหมวดย่อย --</option>
            </select>

          </div>
        </div>

        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 w-28 text-right">คณะ:</label>
          <div class="relative w-full">
            <select name="faculty" class="custom-select w-full" id="faculty">
              <option value="<?= h($facultyValue) ?>" selected><?= h($facultyValue ?: $defaultFaculty) ?></option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 w-28 text-right">ภาควิชา:</label>
          <div class="relative w-full">
            <select name="department" class="custom-select w-full" id="dept">
              <option value="<?= h($departmentValue) ?>" selected><?= h($departmentValue ?: $defaultDepartment) ?>
              </option>
            </select>
          </div>
        </div>
      </div>


      <!-- 1. เรื่อง -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-22 pt-2">
          1. เรื่อง :
        </label>
        <div class="w-full">
          <input type="text" name="subject" id="subjectInput" data-spell-field="subject"
            class="w-full border rounded-md p-2" placeholder="ขอเรียนเชิญเป็นวิทยากรบรรยาย"
            value="<?= h($subjectValue) ?>">

          <div id="subjectInputSpellBox" class="spell-box hidden"></div>
          <div id="subjectInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 2. เรียน -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-22 pt-2">
          2. เรียน :
        </label>
        <div class="w-full">
          <textarea name="to_person" id="toPerson" data-spell-field="to_person" rows="3"
            class="w-full border rounded-md p-2"
            placeholder="คุณภาคิน วรากุล Astro Media Hub ตำแหน่ง บรรณาธิการข่าววิทยาศาสตร์อวกาศ แอสโทร มีเดีย ฮับ รายงานข่าวสาร ปรากฏการณ์ดาราศาสตร์ พร้อมดูแลสื่อ Pakin Space"><?= h($toPersonValue) ?></textarea>

          <div id="toPersonSpellBox" class="spell-box hidden"></div>
          <div id="toPersonSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 3. วัน เดือน ปี -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 items-end">
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="docDateDisplay">
            3. วัน เดือน ปี :
          </label>
          <div class="relative">
            <input type="text" id="docDateDisplay" value="<?= h($docDateValue) ?>"
              class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly />
            <input type="hidden" name="doc_date" id="docDate" value="<?= h($docDateValue) ?>" />
            <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
              xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>
          <label class="lbl text-gray-800 whitespace-nowrap">ที่ต้องการให้ปรากฏบนบันทึกข้อความ</label>
        </div>
      </div>


      <!-- 4. ชื่อโครงการ / ชื่ออบรม : -->
      <div class="mb-4 flex items-start gap-10">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          4. ชื่อโครงการ / ชื่ออบรม :
        </label>
        <div class="w-full">
          <textarea name="thesis_title" id="projectTitle" data-spell-field="project_title" rows="2"
            class="w-full border rounded-md p-2"
            placeholder="โครงการอบรมเชิงสัมมนา เรื่อง อวกาศกับปัญญาประดิษฐ์: อนาคตและความท้าทาย"><?= h($projectTitleValue) ?></textarea>

          <div id="projectTitleSpellBox" class="spell-box hidden"></div>

          <div id="projectTitleSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 5. คำกล่าวเชิญ -->
      <div class="mb-4 flex items-start gap-10">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          5. คำกล่าวเชิญ :
        </label>
        <div class="w-full">
          <textarea name="invite_statement" id="inviteStatement" data-spell-field="invite_statement" rows="4"
            class="w-full border rounded-md p-2"
            placeholder="เห็นว่าท่านเป็นผู้มีความเชี่ยวชาญและมีประสบการณ์สูง ในสาขาวิชาชีพดังกล่าว ซึ่งจะเป็นประโยชน์แก่นักศึกษาผู้เข้าร่วมโครงการเป็นอย่างดี"><?= h($inviteStatementValue) ?></textarea>

          <div id="inviteStatementSpellBox" class="spell-box hidden"></div>

          <div id="inviteStatementSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <div class="mb-4 flex items-start gap-16">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          6. วัตถุประสงค์ :
        </label>
        <div class="w-full">
          <textarea name="objective" id="objectiveInput" data-spell-field="objective" rows="2"
            class="w-full border rounded-md p-2"
            placeholder="เพื่อให้นักศึกษาภาควิชาเทคโนโลยีสารสนเทศ ได้รับฟังบรรยายจากผู้เชี่ยวชาญในวงการอวกาศและปัญญาประดิษฐ์ ในการเสริมสร้างแรงบันดาลใจและความคิดสร้างสรรค์ เพื่อเป็นแนวทางในการศึกษาและการประกอบอาชีพของนักศึกษาต่อไปในอนาคต"><?= h($objectiveValue) ?></textarea>

          <div id="objectiveInputSpellBox" class="spell-box hidden"></div>

          <div id="objectiveInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 7. วันที่จัดกิจกรรม -->
      <div class="mb-6 flex flex-col gap-3">

        <!-- 🔹 บรรทัดที่ 1 -->
        <div class="flex items-start gap-4">
          <label class="lbl whitespace-nowrap w-48 pt-2">
            7. วันที่จัดกิจกรรม :
          </label>

          <div class="flex items-center gap-4">
            <!-- วันที่จัดกิจกรรม เลือกวันเดียว -->
            <div class="relative">
              <input type="text" id="eventDateDisplay" class="border rounded-md p-2 w-44 pr-10 cursor-pointer"
                placeholder="เลือกวันที่" value="<?= h($eventDateValue) ?>" readonly>
              <input type="hidden" name="intern_period" id="internPeriod" value="<?= h($eventDateValue) ?>">
              <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
        </div>

        <!-- 🔹 บรรทัดที่ 2 (เวลา) -->
        <div class="flex items-start gap-4">
          <div class="w-48"></div>

        </div>
        <div class="flex items-center  gap-4 " style="margin-left: 15px;">
          <label class="lbl whitespace-nowrap w-44">
            เวลา :
          </label>

          <input type="time" id="timeStart" class="border rounded-md p-2 w-40" value="<?= h($timeStartValue) ?>">

          <span>ถึง</span>

          <input type="time" id="timeEnd" class="border rounded-md p-2 w-40" value="<?= h($timeEndValue) ?>">

          <input type="hidden" name="event_time" id="eventTime" value="<?= h($eventTimeValue) ?>">
        </div>

      </div>

      <div class="mb-4 flex items-start gap-16">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          8. สถานที่จัดกิจกรรม :
        </label>
        <div class="w-full">
          <textarea name="location_input" rows="2" id="locationInput" data-spell-field="location_input"
            class="w-full border rounded-md p-2"
            placeholder="ณ ห้องประชุม ๒๑๖ อาคารบริหาร มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี"><?= h($locationValue) ?></textarea>

          <div id="locationInputSpellBox" class="spell-box hidden"></div>

          <div id="locationInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>



      <!-- ปุ่ม -->
      <div class="relative mt-20">
        <div class="absolute right-0 bottom-0">
          <button type="submit" id="submitBtn"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md flex items-center justify-center transition">
            ดำเนินการ
          </button>
        </div>

      </div>
    </div>
  </form>

  <script>
  const byId = (id) => document.getElementById(id);
  const form = byId("memoForm");
  window.memoInviteForm = form;

  const subjectInput = byId("subjectInput");
  const toPerson = byId("toPerson");
  const projectTitle = byId("projectTitle");
  const inviteStatement = byId("inviteStatement");
  const objectiveInput = byId("objectiveInput");
  const locationInput = byId("locationInput");

  const spellFields = [
    subjectInput,
    toPerson,
    projectTitle,
    inviteStatement,
    objectiveInput,
    locationInput
  ].filter(Boolean);

  const spellState = {
    subject: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    to_person: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    project_title: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    invite_statement: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    objective: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    location_input: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    }
  };

  const spellCache = {};
  const approvedWords = new Set();
  const approvedTexts = {};
  const correctedTexts = {};

  const SPELL_API_URL = "http://127.0.0.1:8001/api/spell-check";

  function getFieldName(el) {
    return el?.dataset?.spellField || "";
  }

  function getSpellBoxByField(el) {
    if (!el) return null;

    const map = {
      subjectInput: "subjectInputSpellBox",
      toPerson: "toPersonSpellBox",
      projectTitle: "projectTitleSpellBox",
      inviteStatement: "inviteStatementSpellBox",
      objectiveInput: "objectiveInputSpellBox",
      locationInput: "locationInputSpellBox"
    };

    return byId(map[el.id]);
  }

  function getSpellLoadingByField(el) {
    if (!el) return null;

    const map = {
      subjectInput: "subjectInputSpellLoading",
      toPerson: "toPersonSpellLoading",
      projectTitle: "projectTitleSpellLoading",
      inviteStatement: "inviteStatementSpellLoading",
      objectiveInput: "objectiveInputSpellLoading",
      locationInput: "locationInputSpellLoading"
    };

    return byId(map[el.id]);
  }

  function showSpellLoading(el) {
    const box = getSpellLoadingByField(el);
    if (box) box.classList.remove("hidden");
  }

  function hideSpellLoading(el) {
    const box = getSpellLoadingByField(el);
    if (box) box.classList.add("hidden");
  }

  function clearSpellResult(el) {
    if (!el) return;

    el.classList.remove("spell-error", "spell-ok");

    const box = getSpellBoxByField(el);
    if (box) {
      box.innerHTML = "";
      box.classList.add("hidden");
    }
  }

  function showSpellOk(el) {
    clearSpellResult(el);

    const text = (el.value || "").trim();
    if (text !== "") {
      el.classList.add("spell-ok");
    }
  }

  function showSpellApiError(el, message = "ไม่สามารถเชื่อมต่อระบบตรวจคำผิดได้ กรุณาตรวจสอบว่า API เปิดอยู่") {
    clearSpellResult(el);
    el.classList.add("spell-error");

    const box = getSpellBoxByField(el);
    if (!box) return;

    box.innerHTML = `
      <div class="spell-result-box">
        <div class="spell-warning">${escapeHtml(message)}</div>
      </div>
    `;
    box.classList.remove("hidden");
  }

  function escapeHtml(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function escapeRegExp(str) {
    return String(str).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function replaceWholeWordOnce(text, wrongWord, newWord) {
    if (!text || !wrongWord || !newWord) return text;
    return text.replace(new RegExp(escapeRegExp(wrongWord)), newWord);
  }

  function normalizeErrorItem(item) {
    if (!item) return null;

    const wrongWord = String(
      item.wrongWord ??
      item.wrong_word ??
      item.word ??
      item.token ??
      item.error ??
      item.text ??
      ""
    ).trim();

    if (!wrongWord) return null;

    let suggestions = item.suggestions ?? item.suggestion ?? item.correct ?? item.candidates ?? [];

    if (typeof suggestions === "string") {
      suggestions = [suggestions];
    }

    if (!Array.isArray(suggestions)) {
      suggestions = [];
    }

    suggestions = suggestions
      .map(s => String(s || "").trim())
      .filter(Boolean)
      .filter(s => s !== wrongWord)
      .filter((s, i, arr) => arr.indexOf(s) === i)
      .slice(0, 5);

    return {
      wrongWord,
      suggestions
    };
  }

  function normalizeErrors(errors = [], originalText = "") {
    if (!Array.isArray(errors)) return [];

    const seen = new Set();
    const normalized = [];

    for (const item of errors) {
      const data = normalizeErrorItem(item);
      if (!data) continue;

      const wrongWord = data.wrongWord;
      if (originalText && !originalText.includes(wrongWord)) continue;
      if (seen.has(wrongWord)) continue;

      seen.add(wrongWord);
      normalized.push(data);
    }

    return normalized;
  }

  function extractThaiWords(text = "") {
    return String(text)
      .split(/[^\u0E00-\u0E7Fa-zA-Z0-9]+/g)
      .map(w => w.trim())
      .filter(Boolean);
  }

  function rememberApprovedText(fieldName, text) {
    const cleanText = String(text || "").trim();
    if (!fieldName || !cleanText) return;

    approvedTexts[fieldName] = cleanText;

    extractThaiWords(cleanText).forEach(word => {
      approvedWords.add(word);
    });
  }

  function isApprovedText(fieldName, text) {
    const cleanText = String(text || "").trim();
    return !!(fieldName && cleanText && approvedTexts[fieldName] === cleanText);
  }

  function filterApprovedErrors(errors = []) {
    return errors.filter(item => {
      const wrongWord = String(item?.wrongWord || "").trim();
      if (!wrongWord) return false;
      return !approvedWords.has(wrongWord);
    });
  }

  function setSpellPassed(el, fieldName, text, remember = false) {
    if (remember) {
      rememberApprovedText(fieldName, text);
    }

    spellState[fieldName] = {
      checked: true,
      hasError: false,
      ignored: remember,
      apiError: false,
      errors: [],
      lastText: text
    };

    showSpellOk(el);
  }

  function shouldCheckSpell(el) {
    if (!el) return false;
    if (el.disabled || el.readOnly) return false;
    return true;
  }

  function showSpellError(el, errors = []) {
    clearSpellResult(el);
    el.classList.add("spell-error");

    const box = getSpellBoxByField(el);
    if (!box) return;

    errors = normalizeErrors(errors, el.value || "");

    if (!errors.length) {
      showSpellOk(el);
      return;
    }

    let html = `<div class="spell-result-box">`;
    html += `<div class="spell-warning">พบคำแนะนำ ${errors.length} จุด</div>`;

    errors.forEach((item, index) => {
      html += `<div class="mt-2">`;
      html += `<div class="spell-help-text">คำที่ ${index + 1}: <b>${escapeHtml(item.wrongWord)}</b></div>`;

      if (item.suggestions.length > 0) {
        html += `<div class="spell-suggestions">`;

        item.suggestions.forEach(word => {
          html += `
            <button type="button"
              class="spell-suggestion-btn"
              data-target="${el.id}"
              data-word="${escapeHtml(word)}"
              data-wrong-word="${escapeHtml(item.wrongWord)}">
              ${escapeHtml(word)}
            </button>
          `;
        });

        html += `</div>`;
      } else {
        html += `<div class="spell-help-text">ไม่มีคำแนะนำอัตโนมัติ</div>`;
      }

      html += `</div>`;
    });

    html += `
      <div class="spell-suggestions">
        <button type="button" class="spell-ignore-btn" data-target="${el.id}">
          ใช้ข้อความเดิม
        </button>
      </div>
    `;

    html += `</div>`;

    box.innerHTML = html;
    box.classList.remove("hidden");
  }

  async function checkSpellField(el) {
    if (!el) return true;

    clearSpellResult(el);

    if (!shouldCheckSpell(el)) return true;

    const text = (el.value || "").trim();
    const fieldName = getFieldName(el);

    if (!text) {
      spellState[fieldName] = {
        checked: false,
        hasError: false,
        ignored: false,
        apiError: false,
        errors: [],
        lastText: ""
      };
      return true;
    }

    const cacheKey = `${fieldName}::${text}`;

    if (isApprovedText(fieldName, text) || correctedTexts[fieldName] === text) {
      setSpellPassed(el, fieldName, text, false);
      return true;
    }

    if (spellCache[cacheKey]) {
      const cached = spellCache[cacheKey];
      const normalizedErrors = filterApprovedErrors(normalizeErrors(cached.errors || [], text));
      const hasError = !!cached.hasError || normalizedErrors.length > 0;

      if (hasError && normalizedErrors.length > 0) {
        spellState[fieldName] = {
          checked: true,
          hasError: true,
          ignored: false,
          apiError: false,
          errors: normalizedErrors,
          lastText: text
        };
        showSpellError(el, normalizedErrors);
        return false;
      }

      setSpellPassed(el, fieldName, text, false);
      return true;
    }

    el.classList.add("opacity-50");
    showSpellLoading(el);

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000);

    try {
      const response = await fetch(SPELL_API_URL, {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          field: fieldName,
          text: text
        }),
        signal: controller.signal
      });

      clearTimeout(timeoutId);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();
      spellCache[cacheKey] = result;

      const normalizedErrors = filterApprovedErrors(normalizeErrors(result.errors || [], text));
      const hasError = !!result.hasError || normalizedErrors.length > 0;

      if (hasError && normalizedErrors.length > 0) {
        spellState[fieldName] = {
          checked: true,
          hasError: true,
          ignored: false,
          apiError: false,
          errors: normalizedErrors,
          lastText: text
        };
        showSpellError(el, normalizedErrors);
        return false;
      }

      setSpellPassed(el, fieldName, text, false);
      return true;

    } catch (error) {
      clearTimeout(timeoutId);
      console.error("Spell check API error:", error);

      spellState[fieldName] = {
        checked: false,
        hasError: true,
        ignored: false,
        apiError: true,
        errors: [],
        lastText: text
      };

      showSpellApiError(el);
      return false;

    } finally {
      el.classList.remove("opacity-50");
      hideSpellLoading(el);
    }
  }

  async function checkAllSpellFields() {
    let allPassed = true;

    for (const el of spellFields) {
      if (!el || !shouldCheckSpell(el)) continue;

      const text = (el.value || "").trim();
      if (!text) continue;

      const fieldName = getFieldName(el);
      const state = spellState[fieldName];

      if (
        state &&
        state.checked &&
        !state.hasError &&
        state.lastText === text
      ) {
        continue;
      }

      if (isApprovedText(fieldName, text) || correctedTexts[fieldName] === text) {
        setSpellPassed(el, fieldName, text, false);
        continue;
      }

      const passed = await checkSpellField(el);
      if (!passed) {
        allPassed = false;
      }
    }

    for (const key in spellState) {
      const state = spellState[key];
      const remainingErrors = filterApprovedErrors(state.errors || []);

      if (state.apiError) {
        if (err.name === "AbortError") {
          console.warn("Spell check timeout: API response too slow", err);

          showSpellBox(fieldName, `
    <div class="spell-warning">
      ระบบตรวจคำผิดใช้เวลานานเกินไป กรุณาลองตรวจอีกครั้ง
    </div>
  `);

          return {
            ok: false,
            timeout: true,
            errors: []
          };
        }

        console.error("Spell check API error:", err);

        showSpellBox(fieldName, `
  <div class="spell-warning">
    ระบบตรวจคำผิดเชื่อมต่อไม่ได้ กรุณาตรวจสอบ API อีกครั้ง
  </div>
`);

        return {
          ok: false,
          apiError: true,
          errors: []
        };
        return false;
      }

      if (state.checked && state.hasError && remainingErrors.length > 0) {
        alert("กรุณาเลือกคำแนะนำ หรือกดใช้ข้อความเดิมก่อนดำเนินการ");
        return false;
      }
    }

    return allPassed;
  }

  document.addEventListener("click", (e) => {
    const ignoreBtn = e.target.closest(".spell-ignore-btn");
    if (!ignoreBtn) return;

    const target = byId(ignoreBtn.dataset.target);
    if (!target) return;

    const fieldName = getFieldName(target);
    const currentText = (target.value || "").trim();

    setSpellPassed(target, fieldName, currentText, true);
  });

  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".spell-suggestion-btn");
    if (!btn) return;

    const target = byId(btn.dataset.target);
    const word = btn.dataset.word;
    const wrongWord = btn.dataset.wrongWord;

    if (!target || !word || !wrongWord) return;

    const beforeText = target.value || "";
    const afterText = replaceWholeWordOnce(beforeText, wrongWord, word);
    target.value = afterText;

    const fieldName = getFieldName(target);
    const currentText = (target.value || "").trim();

    correctedTexts[fieldName] = currentText;
    approvedWords.add(word);

    setSpellPassed(target, fieldName, currentText, false);
  });

  spellFields.forEach((el) => {
    el.addEventListener("blur", () => {
      checkSpellField(el);
    });

    el.addEventListener("input", () => {
      const fieldName = getFieldName(el);
      const state = spellState[fieldName];

      if (state) {
        state.checked = false;
        state.hasError = false;
        state.apiError = false;
        state.errors = [];
        state.lastText = "";
      }

      clearSpellResult(el);
    });
  });

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;

    HTMLFormElement.prototype.submit.call(form);
  });
  </script>


  <script>
  flatpickr.localize(flatpickr.l10ns.th);

  const monthsTH = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน",
    "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม",
    "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
  ];

  function formatThaiSingleDate(date) {
    const day = date.getDate();
    const month = monthsTH[date.getMonth()];
    const year = date.getFullYear() + 543;

    return `${day} ${month} ${year}`;
  }

  flatpickr("#docDateDisplay", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    allowInput: false,
    onChange: function(selectedDates, dateStr) {
      document.getElementById("docDate").value = dateStr;
    }
  });

  flatpickr("#eventDateDisplay", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    allowInput: false,
    onChange: function(selectedDates) {
      const selectedDate = selectedDates[0];
      if (!selectedDate) return;

      document.getElementById("internPeriod").value = formatThaiSingleDate(selectedDate);
    }
  });
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const main = document.getElementById("mainCategory");
    const sub = document.getElementById("subCategory");
    if (!main || !sub) return;

    const SUB_OPTIONS = {
      external: [
        "ระบบขอความอนุเคราะห์หนังสือฝึกงาน (ของนักศึกษา)",
        "ส่งตัวหนังสือขอออกฝึกงาน(ของนักศึกษา)",
        "หนังสือเรียนเชิญวิทยากร (ของนักศึกษา)",
        "หนังสือขอบคุณ (ของนักศึกษา)",
        "หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์ (ของนักศึกษา)",
        "หนังสือเรียนเชิญปริญญา(ของนักศึกษา)",
      ],
      internal: [
        "ขอเปลี่ยนแปลงตารางสอน (ของอาจารย์)",
        "ขอเปลี่ยนแปลงตารางสอบ (ของอาจารย์)",
        "ขอสอบนอกตาราง (ของอาจารย์)",
        "ขอใช้อาคารวันหยุดราชการ (ของอาจารย์)",
        "ขอสอนชดเชย (ของอาจารย์)",
        "ขอห้องพักรับรอง (ของอาจารย์)",
        "ขออนุมัติตัวบุคคลเป็นวิทยากร (ของอาจารย์)",
        "ขออนุมัติไม่เข้าร่วมโครงการ (ของอาจารย์)",
        "การเผยแพร่งานวิจัยและเบิกค่าตอบแทนการตีพิมพ์ (ของอาจารย์)",
        "ขออนุมัติจัดทำโครงการ (ของอาจารย์)",
        "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ (ของอาจารย์)",
        "ขอแจ้งเรียนการเป็นผู้ร่วมวิจัย (ของอาจารย์)",
      ],
    };

    const ROUTE_MAIN = {
      train: "/Pro_letter/documents/form_Memo.php",
      academic: "/Pro_letter/form_Memo/Request/infor_approve_pro.php",
    };

    const ROUTE_SUB = {
      "ระบบขอความอนุเคราะห์หนังสือฝึกงาน (ของนักศึกษา)": "/Pro_letter/form_Memo/Request/infor_intership.php",
      "หนังสือเรียนเชิญวิทยากร (ของนักศึกษา)": "/Pro_letter/form_Memo/Request/infor_invite.php",
      "ส่งตัวหนังสือขอออกฝึกงาน(ของนักศึกษา)": "#",
      "หนังสือขอบคุณ (ของนักศึกษา)": "/Pro_letter/form_Memo/Request/infor_thankyou.php",
      "หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์ (ของนักศึกษา)": "/Pro_letter/form_Memo/Request/infor_research_data.php",
      "หนังสือเรียนเชิญปริญญา(ของนักศึกษา)": "#",

      "ขอเปลี่ยนแปลงตารางสอน (ของอาจารย์)": "#",
      "ขอเปลี่ยนแปลงตารางสอบ (ของอาจารย์)": "/Pro_letter/form_Memo/Request/infor_change_exam.php",
      "ขอสอบนอกตาราง (ของอาจารย์)": "/Pro_letter/form_Memo/Request/infor_extra_exam.php",
      "ขอใช้อาคารวันหยุดราชการ (ของอาจารย์)": "/Pro_letter/user/Request_2.php",
      "ขอสอนชดเชย (ของอาจารย์)": "#",
      "ขอห้องพักรับรอง (ของอาจารย์)": "/Pro_letter/user/Request_3.php",
      "ขออนุมัติตัวบุคคลเป็นวิทยากร (ของอาจารย์)": "/Pro_letter/user/Request_4.php",
      "ขออนุมัติไม่เข้าร่วมโครงการ (ของอาจารย์)": "/Pro_letter/user/Request_5.php",
      "การเผยแพร่งานวิจัยและเบิกค่าตอบแทนการตีพิมพ์ (ของอาจารย์)": "#",
      "ขออนุมัติจัดทำโครงการ (ของอาจารย์)": "#",
      "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ (ของอาจารย์)": "#",
      "ขอแจ้งเรียนการเป็นผู้ร่วมวิจัย (ของอาจารย์)": "/Pro_letter/user/Request_7.php",
    };

    function renderSubOptions(list, selectedValue = "") {
      sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';
      list.forEach(text => {
        const opt = document.createElement("option");
        opt.value = text;
        opt.textContent = text;
        if (text === selectedValue) opt.selected = true;
        sub.appendChild(opt);
      });
    }

    function syncUI() {
      const mainVal = (main.value || "").trim();
      const currentSub = (sub.dataset.current || "").trim();

      if (mainVal === "train" || mainVal === "academic" || mainVal === "") {
        sub.disabled = true;
        sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';
        return;
      }

      sub.disabled = false;
      renderSubOptions(SUB_OPTIONS[mainVal] || [], currentSub);
    }

    function goMain() {
      const mainVal = (main.value || "").trim();
      const target = ROUTE_MAIN[mainVal];
      if (target && target !== "#") window.location.href = target;
    }

    function goSub() {
      const subVal = (sub.value || "").trim();
      sub.dataset.current = subVal; // ✅ เก็บไว้ให้พรีเซเลคได้
      const target = ROUTE_SUB[subVal];
      if (!target || target === "#") return;
      window.location.href = target;
    }

    main.addEventListener("change", () => {
      sub.dataset.current = "";
      syncUI();
      goMain();
    });

    sub.addEventListener("change", goSub);

    syncUI();
  });
  </script>
  <script>
  const timeStart = document.getElementById("timeStart");
  const timeEnd = document.getElementById("timeEnd");
  const eventTime = document.getElementById("eventTime");

  function updateEventTime() {
    if (!eventTime) return;

    const start = timeStart ? timeStart.value.trim() : "";
    const end = timeEnd ? timeEnd.value.trim() : "";

    if (start && end) {
      eventTime.value = start + " - " + end;
    } else if (start) {
      eventTime.value = start;
    } else if (end) {
      eventTime.value = end;
    } else {
      eventTime.value = "";
    }
  }

  if (timeStart) {
    timeStart.addEventListener("change", updateEventTime);
    timeStart.addEventListener("input", updateEventTime);
  }

  if (timeEnd) {
    timeEnd.addEventListener("change", updateEventTime);
    timeEnd.addEventListener("input", updateEventTime);
  }

  const memoInviteFormForTime = document.getElementById("memoForm");
  if (memoInviteFormForTime) {
    memoInviteFormForTime.addEventListener("submit", updateEventTime);
  }
  </script>
</body>

</html>