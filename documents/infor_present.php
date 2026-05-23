<?php
// Pro_letter/documents/infor_present.php
session_start();

$CURRENT_MAIN = $_GET['main'] ?? 'external';
$CURRENT_SUB  = $_GET['sub']  ?? 'หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ';

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'external';
}
require_once __DIR__ . '/../functions.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}
$docId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $docId > 0;
$formData = [];

if ($isEdit) {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT document_id, owner_id, status
        FROM documents
        WHERE document_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) exit("ไม่พบเอกสาร");
   $roleId = (int)($_SESSION['role_id'] ?? 0);
    $isAdmin   = ($roleId === 1);
    $isOfficer = ($roleId === 2);
    if (!$isAdmin && !$isOfficer) {
        if ($doc['owner_id'] != $_SESSION['user_id']) {
            header("Location: view_memo.php?id={$docId}&err=no_permission");
            exit;

        }
        if (!in_array($doc['status'], ['draft','rejected'])) {
           header("Location: view_memo.php?id={$docId}&err=no_permission");
          exit;

        }
    }
    $q = $pdo->prepare("
        SELECT field_id, value_text
        FROM document_values
        WHERE document_id = :id
    ");
    $q->execute([':id' => $docId]);

    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $formData[(int)$row['field_id']] = $row['value_text'];
    }
}

$docDate     = $formData[1]  ?? '';
$ownerName   = $formData[2]  ?? '';
$position    = $formData[3]  ?? '';
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
      <a href="home.php">
        <div class="px-4 py-2 rounded-[11px] font-bold transition text-white">
          หน้าหลัก
        </div>
      </a>

      <?php 
                if (isset($_SESSION['permissions']) && in_array(3, $_SESSION['permissions'])) {
                    renderAdminExtraMenus(); 
                }
            ?>

      <a href="form_Memo.php">
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

  <form method="post" action="save_memo.php" id="memoForm">
    <input type="hidden" name="mode" value="<?= $isEdit ? 'update' : 'create' ?>">
    <input type="hidden" name="document_id" value="<?= (int)$docId ?>">
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="department_id" value="1">

    <input type="hidden" name="purpose" value="consent_research_presentation">
    <input type="hidden" name="doc_date" value="<?= h($docDate ?: date('Y-m-d')) ?>">
    <input type="hidden" name="fullname" id="fullnameHidden"
      value="<?= htmlspecialchars($ownerName ?: ($_SESSION['fullname'] ?? '')) ?>">
    <input type="hidden" name="position" value="<?= htmlspecialchars($position ?? '') ?>">

    <input type="hidden" name="event_title" id="eventTitleHidden">
    <input type="hidden" name="join_date" id="joinDateHidden">
    <input type="hidden" name="place" id="placeHidden">
    <input type="hidden" name="academic_topic" id="academicTopicHidden">
    <input type="hidden" name="academic_level" id="academicLevelHidden">
    <input type="hidden" name="presenter_name_hidden" id="presenterNameHidden">
    <input type="hidden" name="signature_affiliation" id="signatureAffiliationHidden">
    <input type="hidden" name="no_cost" value="1">
    <input type="hidden" name="is_online" value="0">
    <input type="hidden" name="amount" value="0">

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
              <option value="external" <?= ($CURRENT_MAIN=="external"?"selected":"") ?>>ภายนอก</option>
              <option value="internal" <?= ($CURRENT_MAIN=="internal"?"selected":"") ?>>ภายใน</option>
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
              <option>คณะเทคโนโลยีและการจัดการอุตสาหกรรม</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 w-28 text-right">ภาควิชา:</label>
          <div class="relative w-full">
            <select name="department" class="custom-select w-full" id="dept">
              <option>เทคโนโลยีสารสนเทศ</option>
            </select>
          </div>
        </div>
      </div>


      <!-- 1. ชื่อ–นามสกุลผู้ยื่นขอ -->
      <div class="mb-4 flex items-center gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-56">1. ชื่อ–นามสกุลผู้ยื่นขอ:</label>
        <input type="text" id="fullName" name="full_name" class="flex border rounded-md p-2"
          value="<?= htmlspecialchars($formData[2] ?? $ownerName ?: ($_SESSION['fullname'] ?? '')) ?>" />

      </div>


      <!-- 2. ชื่อ–นามสกุลผู้เสนอผลงาน -->
      <div class="mb-4 flex items-start gap-4">
        <div class="flex items-center h-[42px] w-56">
          <label class="lbl whitespace-nowrap">
            2. ชื่อ–นามสกุลผู้เสนอผลงาน :
          </label>
        </div>

        <div class="flex flex-col">
          <input type="text" name="presenter_name" id="presenterName" data-spell-field="presenter_name"
            class="border rounded-md p-2 w-[330px]" placeholder="เช่น นางสาวสมหญิง ตั้งใจ"
            value="<?= htmlspecialchars($formData[14] ?? '') ?>">

          <div id="presenterNameSpellBox" class="spell-box hidden"></div>

          <div id="presenterNameSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 3. ชื่อผลงานวิจัย -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          3. ชื่อผลงานวิจัย :
        </label>

        <div class="flex-1">
          <textarea name="research_title" id="researchTitle" data-spell-field="research_title"
            class="w-full border rounded-md p-2" rows="2"
            placeholder="ระบุชื่อผลงานวิจัย"><?= htmlspecialchars($formData[13] ?? '') ?></textarea>

          <div id="researchTitleSpellBox" class="spell-box hidden"></div>

          <div id="researchTitleSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. ชื่องานประชุมวิชาการ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          4. ชื่องานประชุมวิชาการ :
        </label>

        <div class="flex-1">
          <textarea name="conference_name" id="conferenceName" data-spell-field="conference_name"
            class="w-full border rounded-md p-2" rows="2"
            placeholder="เช่น International Conference on Information Technology"><?= htmlspecialchars($formData[5] ?? '') ?></textarea>

          <div id="conferenceNameSpellBox" class="spell-box hidden"></div>
          <div id="conferenceNameSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. ระดับการประชุมวิชาการ -->
      <div class="mb-4 flex items-center gap-4">
        <label class="lbl whitespace-nowrap w-56">
          5. ระดับการประชุมวิชาการ :
        </label>

        <select name="conference_level" id="conferenceLevel" class="border rounded-md p-2 flex-1">
          <option value="">-- เลือกระดับการประชุม --</option>
          <option value="ระดับชาติ" <?= (($formData[15] ?? '') === 'ระดับชาติ') ? 'selected' : '' ?>>ระดับชาติ</option>
          <option value="ระดับนานาชาติ" <?= (($formData[15] ?? '') === 'ระดับนานาชาติ') ? 'selected' : '' ?>>
            ระดับนานาชาติ</option>
        </select>
      </div>

      <!-- 6. สถานที่จัดงาน -->
      <div class="mb-6 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          6. สถานที่จัดงาน :
        </label>

        <div class="flex-1">
          <textarea name="conference_place" id="conferencePlace" data-spell-field="conference_place"
            class="w-full border rounded-md p-2" rows="2"
            placeholder="เช่น โรงแรม... กรุงเทพมหานคร ประเทศไทย / ประเทศญี่ปุ่น / รูปแบบออนไลน์"><?= htmlspecialchars($formData[7] ?? '') ?></textarea>

          <div id="conferencePlaceSpellBox" class="spell-box hidden"></div>
          <div id="conferencePlaceSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>



      <!-- 7. วันที่นำเสนอ -->
      <div class="mb-6 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          7. วันที่นำเสนอ :
        </label>

        <div class="flex items-center gap-3">
          <!-- วันที่เริ่ม -->
          <div class="relative">
            <input type="text" id="internStart" class="border rounded-md p-2 w-44 pr-10 cursor-pointer"
              placeholder="เริ่มต้น" readonly>
            <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <span>ถึง</span>

          <!-- วันที่สิ้นสุด -->
          <div class="relative">
            <input type="text" id="internEnd" class="border rounded-md p-2 w-44 pr-10 cursor-pointer"
              placeholder="สิ้นสุด" readonly>
            <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <!-- ค่าที่ส่งจริง -->
          <input type="hidden" name="intern_period" id="internPeriod" value="<?= h($formData[6] ?? '') ?>">
        </div>
      </div>
      <!-- 8. หน่วยงาน/มหาวิทยาลัยใต้ลายเซ็น -->
      <div class="mb-6 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          8. หน่วยงานใต้ลายเซ็น :
        </label>

        <div class="flex-1">
          <input type="text" name="signature_affiliation_input" id="signatureAffiliation"
            class="w-full border rounded-md p-2" placeholder="เช่น University in Hagen, Germany"
            value="<?= htmlspecialchars($formData[17] ?? '') ?>">
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
  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));
  const byId = (id) => document.getElementById(id);

  const form = byId("memoForm");

  const spellState = {
    research_title: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    conference_name: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    conference_place: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    }
  };

  const spellCache = {};
  const approvedWords = new Set();
  const approvedTexts = {};
  const correctedTexts = {};

  function setErr(el, on = true) {
    if (!el) return;
    el.classList.toggle("error", on);
    if (on) {
      el.classList.add("shake");
      setTimeout(() => el.classList.remove("shake"), 250);
    }
  }

  function getSpellBoxByField(el) {
    if (!el) return null;
    if (el.id === "researchTitle") return document.getElementById("researchTitleSpellBox");
    if (el.id === "presenterName") return document.getElementById("presenterNameSpellBox");
    if (el.id === "conferenceName") return document.getElementById("conferenceNameSpellBox");
    if (el.id === "conferencePlace") return document.getElementById("conferencePlaceSpellBox");
    return null;
  }

  function getSpellLoadingByField(el) {
    if (!el) return null;
    if (el.id === "researchTitle") return document.getElementById("researchTitleSpellLoading");
    if (el.id === "presenterName") return document.getElementById("presenterNameSpellLoading");
    if (el.id === "conferenceName") return document.getElementById("conferenceNameSpellLoading");
    if (el.id === "conferencePlace") return document.getElementById("conferencePlaceSpellLoading");
    return null;
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
    if ((el.value || "").trim() !== "") el.classList.add("spell-ok");
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

  function normalizeErrors(errors = [], originalText = "") {
    if (!Array.isArray(errors)) return [];
    const seen = new Set();
    const normalized = [];

    for (const item of errors) {
      const wrongWord = String(item?.wrongWord || "").trim();
      if (!wrongWord) continue;
      if (originalText && !originalText.includes(wrongWord)) continue;
      if (seen.has(wrongWord)) continue;
      seen.add(wrongWord);

      const suggestions = Array.isArray(item?.suggestions) ?
        item.suggestions
        .map(s => String(s || "").trim())
        .filter(Boolean)
        .filter(s => s !== wrongWord)
        .filter((s, i, arr) => arr.indexOf(s) === i)
        .slice(0, 5) : [];

      normalized.push({
        wrongWord,
        suggestions
      });
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

    return !!(
      fieldName &&
      cleanText &&
      approvedTexts[fieldName] === cleanText
    );
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
      errors: [],
      lastText: text
    };

    clearSpellResult(el);

    if ((text || "").trim() !== "") {
      el.classList.add("spell-ok");
    }
  }

  function isIgnoredForSameText(fieldName, text) {
    const state = spellState[fieldName];
    return !!(state && state.ignored && state.lastText === text);
  }

  function shouldCheckSpell(el) {
    if (!el) return false;
    if (el.disabled || el.readOnly) return false;

    if (el.id === "otherTypeInput") return !!otherTypeRadio?.checked;
    if (el.id === "reasonOtherInput") return !!reasonOtherRadio?.checked;

    return true;
  }

  function showSpellError(el, errors = []) {
    clearSpellResult(el);
    el.classList.add("spell-error");

    const box = getSpellBoxByField(el);
    if (!box) return;

    errors = filterApprovedErrors(
      normalizeErrors(errors, el.value || "")
    );

    if (!errors.length) {
      showSpellOk(el);
      return;
    }

    let html = `<div class="spell-result-box">`;
    html += `<div class="spell-warning">พบคำแนะนำ ${errors.length} จุด</div>`;

    errors.forEach((item, index) => {
      html += `<div class="mt-2">`;
      html += `<div class="spell-help-text">คำที่ ${index + 1}: <b>${escapeHtml(item.wrongWord)}</b></div>`;
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

      html += `</div></div>`;
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
    if (!el) return;
    clearSpellResult(el);

    if (!shouldCheckSpell(el)) return;

    const text = (el.value || "").trim();
    if (!text) return;

    const fieldName = el.dataset.spellField || "";
    const cacheKey = `${fieldName}::${text}`;

    if (
      isApprovedText(fieldName, text) ||
      correctedTexts[fieldName] === text
    ) {
      setSpellPassed(el, fieldName, text, false);
      return;
    }

    if (spellCache[cacheKey]) {
      const cached = spellCache[cacheKey];
      const normalizedErrors = filterApprovedErrors(
        normalizeErrors(cached.errors || [], text)
      );

      if (cached.hasError && normalizedErrors.length > 0) {
        spellState[fieldName] = {
          checked: true,
          hasError: true,
          ignored: false,
          errors: normalizedErrors,
          lastText: text
        };
        showSpellError(el, normalizedErrors);
      } else {
        spellState[fieldName] = {
          checked: true,
          hasError: false,
          ignored: false,
          errors: [],
          lastText: text
        };
        showSpellOk(el);
      }
      return;
    }

    el.classList.add("opacity-50");
    showSpellLoading(el);

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000);

    try {
      const response = await fetch("http://127.0.0.1:8001/api/spell-check", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          field: fieldName,
          text
        }),
        signal: controller.signal
      });

      clearTimeout(timeoutId);

      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const result = await response.json();
      spellCache[cacheKey] = result;

      const normalizedErrors = filterApprovedErrors(
        normalizeErrors(result.errors || [], text)
      );

      if (result.hasError && normalizedErrors.length > 0) {
        spellState[fieldName] = {
          checked: true,
          hasError: true,
          ignored: false,
          errors: normalizedErrors,
          lastText: text
        };
        showSpellError(el, normalizedErrors);
      } else {
        spellState[fieldName] = {
          checked: true,
          hasError: false,
          ignored: false,
          errors: [],
          lastText: text
        };
        showSpellOk(el);
      }
    } catch (error) {
      clearTimeout(timeoutId);
      console.error("Spell check API error:", error);
    } finally {
      el.classList.remove("opacity-50");
      hideSpellLoading(el);
    }
  }

  async function checkAllSpellFields() {
    const fields = [
      document.getElementById("researchTitle"),
      document.getElementById("presenterName"),
      document.getElementById("conferenceName"),
      document.getElementById("conferencePlace")
    ];

    for (const el of fields) {
      if (!el || !shouldCheckSpell(el)) continue;

      const fieldName = el.dataset.spellField || "";
      const text = (el.value || "").trim();

      if (!text) continue;

      const state = spellState[fieldName];

      if (
        state &&
        state.checked &&
        !state.hasError &&
        state.lastText === text
      ) {
        continue;
      }

      if (
        isApprovedText(fieldName, text) ||
        correctedTexts[fieldName] === text
      ) {
        setSpellPassed(el, fieldName, text, false);
        continue;
      }

      await checkSpellField(el);
    }

    for (const key in spellState) {
      const state = spellState[key];
      const remainingErrors = filterApprovedErrors(state.errors || []);

      if (
        state.checked &&
        state.hasError &&
        remainingErrors.length > 0
      ) {
        alert("กรุณาเลือกคำแนะนำ หรือกดใช้ข้อความเดิมก่อนดำเนินการ");
        return false;
      }
    }

    return true;
  }

  document.addEventListener("click", (e) => {
    const ignoreBtn = e.target.closest(".spell-ignore-btn");
    if (!ignoreBtn) return;

    const target = byId(ignoreBtn.dataset.target);
    if (!target) return;

    const fieldName = target.dataset.spellField || "";
    const currentText = (target.value || "").trim();

    setSpellPassed(target, fieldName, currentText, true);

    clearSpellResult(target);
    target.classList.add("spell-ok");
  });

  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".spell-suggestion-btn");
    if (!btn) return;

    const target = byId(btn.dataset.target);
    const word = btn.dataset.word;
    const wrongWord = btn.dataset.wrongWord;

    if (!target || !word || !wrongWord) return;

    target.value = replaceWholeWordOnce(target.value || "", wrongWord, word);

    const fieldName = target.dataset.spellField || "";
    const currentText = (target.value || "").trim();

    correctedTexts[fieldName] = currentText;
    approvedWords.add(word);

    setSpellPassed(target, fieldName, currentText, false);
  });





  function scrollFocus(el) {
    if (!el) return;
    el.scrollIntoView({
      behavior: "smooth",
      block: "center"
    });
    setTimeout(() => el.focus?.(), 200);
  }

  function validate() {
    let firstInvalid = null;

    const fullName = byId("fullName");
    const presenterName = byId("presenterName");
    const researchTitle = byId("researchTitle");
    const conferenceName = byId("conferenceName");
    const conferenceLevel = byId("conferenceLevel");
    const conferencePlace = byId("conferencePlace");
    const internPeriod = byId("internPeriod");

    [
      fullName,
      presenterName,
      researchTitle,
      conferenceName,
      conferenceLevel,
      conferencePlace
    ].forEach(el => setErr(el, false));

    if (!fullName?.value.trim()) {
      setErr(fullName, true);
      firstInvalid = firstInvalid || fullName;
    }

    if (!presenterName?.value.trim()) {
      setErr(presenterName, true);
      firstInvalid = firstInvalid || presenterName;
    }

    if (!researchTitle?.value.trim()) {
      setErr(researchTitle, true);
      firstInvalid = firstInvalid || researchTitle;
    }

    if (!conferenceName?.value.trim()) {
      setErr(conferenceName, true);
      firstInvalid = firstInvalid || conferenceName;
    }

    if (!conferenceLevel?.value.trim()) {
      setErr(conferenceLevel, true);
      firstInvalid = firstInvalid || conferenceLevel;
    }

    if (!conferencePlace?.value.trim()) {
      setErr(conferencePlace, true);
      firstInvalid = firstInvalid || conferencePlace;
    }
    if (!internPeriod?.value.trim()) {
      alert("กรุณาเลือกวันที่นำเสนอ");
      firstInvalid = firstInvalid || byId("internStart");
    }

    if (firstInvalid) {
      scrollFocus(firstInvalid);
      return false;
    }

    return true;
  }

  function syncPresentFormToSaveMemo() {
    const fullName = document.getElementById("fullName")?.value.trim() || "";
    const presenterName = document.getElementById("presenterName")?.value.trim() || "";
    const researchTitle = document.getElementById("researchTitle")?.value.trim() || "";
    const conferenceName = document.getElementById("conferenceName")?.value.trim() || "";
    const conferenceLevel = document.getElementById("conferenceLevel")?.value.trim() || "";
    const conferencePlace = document.getElementById("conferencePlace")?.value.trim() || "";
    const internPeriod = document.getElementById("internPeriod")?.value.trim() || "";

    document.getElementById("hidden_fullname").value = fullName;
    document.getElementById("hidden_presenter_name").value = presenterName;
    document.getElementById("hidden_research_title").value = researchTitle;
    document.getElementById("hidden_intern_period").value = internPeriod;
    document.getElementById("hidden_conference_place").value = conferencePlace;

    document.getElementById("hidden_conference_level").value = conferenceLevel;
    document.getElementById("hidden_conference_name").value = conferenceName;
    document.getElementById("hidden_event_date").value = internPeriod;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!validate()) return;

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;


    byId("eventTitleHidden").value = byId("conferenceName")?.value.trim() || "";
    byId("joinDateHidden").value = byId("internPeriod")?.value.trim() || "";
    byId("placeHidden").value = byId("conferencePlace")?.value.trim() || "";
    byId("academicTopicHidden").value = byId("researchTitle")?.value.trim() || "";
    byId("academicLevelHidden").value = byId("conferenceLevel")?.value.trim() || "";
    byId("presenterNameHidden").value = byId("presenterName")?.value.trim() || "";
    byId("signatureAffiliationHidden").value = byId("signatureAffiliation")?.value.trim() || "";
    byId("fullnameHidden").value = document.querySelector('input[name="full_name"]')?.value.trim() || "";

    form.submit();
  });
  </script>


  <script>
  flatpickr.localize(flatpickr.l10ns.th);

  const monthsTH = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน",
    "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม",
    "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
  ];

  let startPicker;
  let endPicker;

  function formatThaiRange(start, end) {
    const startDay = start.getDate();
    const endDay = end.getDate();
    const startMonth = monthsTH[start.getMonth()];
    const endMonth = monthsTH[end.getMonth()];
    const startYear = start.getFullYear() + 543;
    const endYear = end.getFullYear() + 543;

    if (
      start.getMonth() === end.getMonth() &&
      start.getFullYear() === end.getFullYear()
    ) {
      return `${startDay} - ${endDay} ${endMonth} ${endYear}`;
    }

    return `${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
  }

  function updateInternRange() {
    const start = startPicker.selectedDates[0];
    const end = endPicker.selectedDates[0];

    if (!start && !end) return;

    let text = "";

    if (start && end) {
      text = formatThaiRange(start, end);
    } else if (start) {
      const d = start.getDate();
      const m = monthsTH[start.getMonth()];
      const y = start.getFullYear() + 543;
      text = `${d} ${m} ${y}`;
    }

    document.getElementById("internPeriod").value = text;

    const rangeDisplay = document.getElementById("internRangeDisplay");
    if (rangeDisplay) {
      rangeDisplay.value = text;
    }
  }

  startPicker = flatpickr("#internStart", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: updateInternRange
  });

  endPicker = flatpickr("#internEnd", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: updateInternRange
  });

  function parseThaiDateForIntern(raw) {
    raw = String(raw || "").trim();
    if (!raw) return null;

    const thaiDigits = "๐๑๒๓๔๕๖๗๘๙";
    raw = raw.replace(/[๐-๙]/g, ch => {
      const index = thaiDigits.indexOf(ch);
      return index >= 0 ? String(index) : ch;
    });

    const m = raw.match(/(\d{1,2})\s+([^\s]+)\s+(\d{4})/);
    if (!m) return null;

    const day = parseInt(m[1], 10);
    const monthIndex = monthsTH.indexOf(m[2].trim());
    let year = parseInt(m[3], 10);

    if (monthIndex === -1) return null;
    if (year > 2400) year -= 543;

    return new Date(year, monthIndex, day);
  }

  function parseThaiRangeForIntern(raw) {
    raw = String(raw || "")
      .trim()
      .replace(/[–—]/g, "-")
      .replace(/\s*ถึง\s*/g, " - ");

    if (!raw) return null;

    // แบบเต็ม: 10 กรกฎาคม 2568 - 12 สิงหาคม 2568
    let m = raw.match(/(\d{1,2})\s+([^\s]+)\s+(\d{4})\s*-\s*(\d{1,2})\s+([^\s]+)\s+(\d{4})/);
    if (m) {
      const start = parseThaiDateForIntern(`${m[1]} ${m[2]} ${m[3]}`);
      const end = parseThaiDateForIntern(`${m[4]} ${m[5]} ${m[6]}`);
      return start && end ? [start, end] : null;
    }

    // แบบย่อ: 10 - 12 กรกฎาคม 2568
    m = raw.match(/(\d{1,2})\s*-\s*(\d{1,2})\s+([^\s]+)\s+(\d{4})/);
    if (m) {
      const start = parseThaiDateForIntern(`${m[1]} ${m[3]} ${m[4]}`);
      const end = parseThaiDateForIntern(`${m[2]} ${m[3]} ${m[4]}`);
      return start && end ? [start, end] : null;
    }

    const single = parseThaiDateForIntern(raw);
    return single ? [single, single] : null;
  }

  // โหลดวันที่นำเสนอเดิมกลับมาโชว์ตอนแก้ไขเอกสาร
  const oldInternPeriod = (document.getElementById("internPeriod")?.value || "").trim();
  if (oldInternPeriod) {
    const parsedRange = parseThaiRangeForIntern(oldInternPeriod);
    if (parsedRange) {
      startPicker.setDate(parsedRange[0], false);
      endPicker.setDate(parsedRange[1], false);
      document.getElementById("internPeriod").value = oldInternPeriod;
    }
  }
  </script>
  <script>
  // ✅ ระบบเปิด/ปิดเมนูโปรไฟล์
  const profileBtn = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");

  if (profileBtn && profileMenu) {
    profileBtn.addEventListener("click", (e) => {
      e.stopPropagation(); // ป้องกันการคลิกซ้ำซ้อน
      profileMenu.classList.toggle("hidden");
    });

    // ปิดเมนูเมื่อคลิกนอกกรอบ
    window.addEventListener("click", (e) => {
      if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
        profileMenu.classList.add("hidden");
      }
    });
  }

  // ✅ ปุ่ม "อยู่ต่อ" ให้ปิดเมนู dropdown
  function closeMenu() {
    profileMenu.classList.add("hidden");
  }

  const main = document.getElementById("mainCategory");
  const sub = document.getElementById("subCategory");

  const SUB_OPTIONS = {
    external: [
      "ฝึกอบรม",
      "ขออนุมัติตัวบุคคลไปนำเสนอผลงานวิจัย",
      "ขออนุมัติตัวบุคคลเป็นวิทยากร",
      "ขอห้องพักรับรอง",
      "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ",
    ],
    internal: [
      "หนังสือเรียนเชิญวิทยากร",
      "หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์",
      "ขอเข้าเยี่ยมศึกษาดูงาน",
      "ขอเข้าไปจัดกิจกรรมโครงการ",
      "ขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ)",
    ],
  };

  const ROUTE_SUB = {
    "ฝึกอบรม": "/Pro_letter/documents/form_Memo.php",
    "ขออนุมัติตัวบุคคลไปนำเสนอผลงานวิจัย": "/Pro_letter/documents/infor_academic_presentation.php",
    "ขออนุมัติตัวบุคคลเป็นวิทยากร": "/Pro_letter/documents/infor_speaker_workshop.php",
    "ขอห้องพักรับรอง": "/Pro_letter/documents/infor_room_request.php",
    "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ": "/Pro_letter/documents/infor_present.php",

    "หนังสือเรียนเชิญวิทยากร": "/Pro_letter/documents/infor_invite.php",
    "หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์": "/Pro_letter/documents/infor_research_data.php",
    "ขอเข้าเยี่ยมศึกษาดูงาน": "/Pro_letter/documents/infor_study_visit.php",
    "ขอเข้าไปจัดกิจกรรมโครงการ": "/Pro_letter/documents/infor_project_activity.php",
    "ขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ)": "/Pro_letter/documents/infor_coop_evaluation.php",
  };

  function withSelection(url, mainVal, subVal = "") {
    if (!url || url === "#") return "#";

    const finalUrl = new URL(url, window.location.origin);
    if (mainVal) finalUrl.searchParams.set("main", mainVal);
    if (subVal) finalUrl.searchParams.set("sub", subVal);

    return finalUrl.toString();
  }

  function renderSubOptions(list, selectedValue = "") {
    sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';

    list.forEach(text => {
      const opt = document.createElement("option");
      opt.value = text;
      opt.textContent = text;

      if (text.trim() === String(selectedValue || "").trim()) {
        opt.selected = true;
      }

      sub.appendChild(opt);
    });
  }

  function syncUI() {
    const mainVal = (main.value || "").trim();
    const currentSub = (sub.dataset.current || "").trim();

    if (mainVal === "external" || mainVal === "internal") {
      sub.disabled = false;
      renderSubOptions(SUB_OPTIONS[mainVal] || [], currentSub);
    } else {
      sub.disabled = true;
      sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';
    }
  }

  function resetSubToPlaceholder() {
    const mainVal = (main.value || "").trim();

    sub.dataset.current = "";

    if (mainVal === "external" || mainVal === "internal") {
      sub.disabled = false;
      renderSubOptions(SUB_OPTIONS[mainVal] || [], "");
      sub.value = "";
    } else {
      sub.disabled = true;
      sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';
    }
  }

  function goSub() {
    const mainVal = (main.value || "").trim();
    const subVal = (sub.value || "").trim();

    sub.dataset.current = subVal;

    const target = ROUTE_SUB[subVal];
    if (!target || target === "#") return;

    window.location.href = withSelection(target, mainVal, subVal);
  }

  main.addEventListener("change", resetSubToPlaceholder);

  sub.addEventListener("change", goSub);

  syncUI();
  </script>

</body>

</html>