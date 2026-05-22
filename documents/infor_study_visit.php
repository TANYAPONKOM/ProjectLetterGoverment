<?php
// pro_letter/documents/infor_study_visit.php
$CURRENT_MAIN = $_GET['main'] ?? 'internal';
$CURRENT_SUB  = $_GET['sub']  ?? 'ขอเข้าเยี่ยมศึกษาดูงาน';

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'internal';
}
?>
<?php
session_start();
require_once __DIR__ . '/../functions.php';
$formData = $formData ?? [];
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}
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

  /* input error */
  .spell-error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
    background-color: #fffafa;
  }

  /* กล่องผลลัพธ์ */
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

  /* ซ่อน */
  .spell-box.hidden {
    display: none !important;
  }

  /* กล่องผลลัพธ์ภายใน */
  .spell-result-box {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  /* ข้อความแจ้งเตือน */
  .spell-warning {
    font-weight: 600;
    color: #991b1b;
  }

  /* ข้อความช่วย */
  .spell-help-text {
    font-size: 13px;
    color: #9a3412;
    font-weight: 500;
  }

  /* container ของคำแนะนำ */
  .spell-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
  }

  /* ปุ่มคำแนะนำ */
  .spell-suggestion-btn {
    border: 1px solid #fdba74;
    background: #ffffff;
    color: #9a3412;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
  }

  /* hover */
  .spell-suggestion-btn:hover {
    background: #ffedd5;
    border-color: #fb923c;
  }

  /* กด */
  .spell-suggestion-btn:active {
    transform: scale(0.96);
  }

  /* focus */
  .spell-suggestion-btn:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.2);
  }

  .spell-ok {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    background-color: #f0fdf4;
  }

  .spell-loading {
    margin-top: 8px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #eff6ff;
    border: 1px solid #93c5fd;
    color: #1d4ed8;
    font-size: 14px;
    line-height: 1.6;
  }

  .spell-loading.hidden {
    display: none !important;
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

  .spell-ignore-btn {
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
  }

  .spell-ignore-btn:hover {
    background: #e2e8f0;
  }

  ;

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

      <a href="/Pro_letter/form_Memo/Request/infor_research_data.php">
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


      <!-- 1. วัน เดือน ปี ที่ต้องการให้ปรากฎบนบันทึกข้อความ -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 items-end">
        <div class="flex items-center gap-3 md:col-span-2">
          <label class="lbl text-gray-800 whitespace-nowrap w-48" for="docDateDisplay">1. วัน เดือน ปี :</label>
          <div class="relative">
            <input type="text" id="docDateDisplay" value="<?= h($formData[1] ?? '') ?>"
              class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly />
            <input type="hidden" name="doc_date" id="docDate" value="<?= h($formData[1] ?? '') ?>" />
            <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
              xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>
          <label class="lbl text-gray-800 whitespace-nowrap">ที่ต้องการให้ปรากฎบนบันทึกข้อความ</label>
        </div>
      </div>

      <!-- 2. เรื่อง -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          2. เรื่อง :
        </label>

        <div class="flex-1">
          <input type="text" name="subject" id="subjectInput" data-spell-field="subject"
            class="w-full border rounded-md p-2" placeholder="เช่น ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน SUT Wellness Academy">

          <div id="subjectInputSpellBox" class="spell-box hidden"></div>
          <div id="subjectInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. เรียนถึง -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          3. เรียนถึง :
        </label>

        <div class="flex-1">
          <input type="text" name="to_person" id="toPerson" data-spell-field="to_person"
            class="w-full border rounded-md p-2" placeholder="เช่น อธิการบดีมหาวิทยาลัยเทคโนโลยีสุรนารี (มทส.)">

          <div id="toPersonSpellBox" class="spell-box hidden"></div>
          <div id="toPersonSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. ชื่อผู้ลงนามท้ายเอกสาร -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-48 pt-2" for="receiverNameInput">
          4. ชื่อผู้ลงนามท้ายเอกสาร :
        </label>

        <div class="flex-1">
          <input type="text" name="receiver_name" id="receiverNameInput" class="w-full border rounded-md p-2"
            value="<?= h($formData[31] ?? '') ?>" placeholder="เช่น ผู้ช่วยศาสตราจารย์พีระศักดิ์ เสรีกุล">
          <p class="text-sm text-gray-500 mt-1">ชื่อนี้จะใช้แสดงบริเวณลายเซ็นท้ายเอกสาร เช่น
            (ผู้ช่วยศาสตราจารย์พีระศักดิ์ เสรีกุล)</p>
        </div>
      </div>

      <!-- 5. ตำแหน่งผู้ลงนามท้ายเอกสาร -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-48 pt-2" for="receiverPositionInput">
          5. ตำแหน่งผู้ลงนามท้ายเอกสาร :
        </label>

        <div class="flex-1">
          <input type="text" name="receiver_position" id="receiverPositionInput" data-spell-field="receiver_position"
            class="w-full border rounded-md p-2" value="<?= h($formData[32] ?? '') ?>"
            placeholder="เช่น รองอธิการบดีประจำ มจพ.วิทยาเขตปราจีนบุรี">

          <div id="receiverPositionInputSpellBox" class="spell-box hidden"></div>
          <div id="receiverPositionInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 6. ชื่อ - นามสกุล -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-48 pt-2" for="fullname">6. ชื่อ - นามสกุล :</label>
        <input type="text" name="fullname" class="flex-1 border rounded-md p-2" id="fullname"
          value="<?= htmlspecialchars($_SESSION['fullname'] ?? '') ?>" />
      </div>

      <!-- 7. ตำแหน่ง / ตำแหน่งทางวิชาการ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-48 pt-2" for="positionInput">
          7. ตำแหน่ง :
        </label>

        <div class="flex-1">
          <input type="text" name="position" id="positionInput" data-spell-field="position"
            class="w-full border rounded-md p-2"
            value="<?= h($formData[5] ?? ($_SESSION['position'] ?? 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ')) ?>"
            placeholder="เช่น รองศาสตราจารย์ ดร. / ผู้ช่วยศาสตราจารย์ / อาจารย์ / เจ้าหน้าที่">

          <div id="positionInputSpellBox" class="spell-box hidden"></div>
          <div id="positionInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 8. ชื่อสถานที่/หน่วยงานที่จะเข้าเยี่ยม -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-48 pt-2" for="visitPlaceInput">
          8. สถานที่เข้าเยี่ยม :
        </label>

        <div class="flex-1">
          <input type="text" name="visit_place" id="visitPlaceInput" data-spell-field="visit_place"
            class="w-full border rounded-md p-2" placeholder="เช่น SUT Wellness Academy">

          <div id="visitPlaceInputSpellBox" class="spell-box hidden"></div>
          <div id="visitPlaceInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 9. รายละเอียดสถานที่/ลักษณะหน่วยงาน -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-48 pt-2" for="placeDetailInput">
          9. รายละเอียดสถานที่ :
        </label>

        <div class="flex-1">
          <textarea name="place_detail" id="placeDetailInput" data-spell-field="place_detail" rows="3"
            class="w-full border rounded-md p-2 shadow-sm"
            placeholder="เช่น ศูนย์สุขภาพเพื่อการป้องกัน รักษา และฟื้นฟูสุขภาพด้วยแผนไทยประยุกต์แบบครบวงจร"></textarea>

          <div id="placeDetailInputSpellBox" class="spell-box hidden"></div>
          <div id="placeDetailInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 10. วัตถุประสงค์ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-48 pt-2">
          10. วัตถุประสงค์ :
        </label>

        <div class="flex-1">
          <textarea name="objective" id="objectiveInput" data-spell-field="objective" rows="3"
            class="w-full border rounded-md p-2 shadow-sm"
            placeholder="เช่น มีความประสงค์จะขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน เพื่อศึกษาแนวทางการบริหารจัดการ..."></textarea>

          <div id="objectiveInputSpellBox" class="spell-box hidden"></div>
          <div id="objectiveInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 11. เพื่อนำไปใช้ประโยชน์ด้านใด -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          11. เพื่ออะไร :
        </label>

        <div class="flex-1">
          <textarea name="purpose" id="purposeInput" data-spell-field="purpose" rows="3"
            class="w-full border rounded-md p-2"
            placeholder="เช่น เพื่อนำข้อมูลและความรู้ที่ได้รับมาพัฒนาให้เกิดประโยชน์กับการจัดการเรียนการสอน งานวิจัย และการบริการวิชาการ"></textarea>

          <div id="purposeInputSpellBox" class="spell-box hidden"></div>
          <div id="purposeInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 12. วันที่เข้าเยี่ยมศึกษาดูงาน -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          12. วันที่เข้าเยี่ยมศึกษาดูงาน :
        </label>

        <div class="flex items-center gap-4">
          <div class="relative">
            <input type="text" id="visitStart" class="border rounded-md p-2 w-44 pr-10 cursor-pointer"
              placeholder="วันที่เข้าเยี่ยม" readonly>

            <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <input type="hidden" name="visit_period" id="visitPeriod">
        </div>
      </div>

      <!-- 13. เวลา -->
      <div class="mb-4 flex items-center gap-4">
        <label class="lbl whitespace-nowrap w-48">
          13. เวลา :
        </label>

        <div class="flex items-center gap-4">
          <input type="time" id="timeStart" class="border rounded-md p-2 w-40">
          <input type="hidden" name="visit_time" id="visitTime">
        </div>
      </div>

      <!-- 14. จำนวนรายชื่อคณาจารย์ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          14. จำนวนรายชื่อคณาจารย์ :
        </label>

        <div class="flex-1">
          <input type="number" name="teacher_count" id="teacherCount" class="w-48 border rounded-md p-2" min="1"
            max="50" placeholder="กรอกจำนวนคณาจารย์">

          <div id="teacherNameContainer" class="mt-3 space-y-3"></div>

          <input type="hidden" name="teacher_names_text" id="teacherNamesText">
          <input type="hidden" name="teacher_affiliations_text" id="teacherAffiliationsText">
          <input type="hidden" name="teacher_list_text" id="teacherListText">
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

  const docDateDisplay = byId("docDateDisplay");
  const docDate = byId("docDate");

  const subjectInput = byId("subjectInput");
  const toPerson = byId("toPerson");
  const receiverNameInput = byId("receiverNameInput");
  const receiverPositionInput = byId("receiverPositionInput");
  const fullname = byId("fullname");
  const positionInput = byId("positionInput");
  const visitPlaceInput = byId("visitPlaceInput");
  const placeDetailInput = byId("placeDetailInput");
  const objectiveInput = byId("objectiveInput");
  const purposeInput = byId("purposeInput");

  const visitStart = byId("visitStart");
  const visitPeriod = byId("visitPeriod");

  const timeStart = byId("timeStart");
  const visitTime = byId("visitTime");

  const teacherCount = byId("teacherCount");
  const teacherNameContainer = byId("teacherNameContainer");
  const teacherNamesText = byId("teacherNamesText");
  const teacherAffiliationsText = byId("teacherAffiliationsText");
  const teacherListText = byId("teacherListText");

  const spellState = {
    subject: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    to_person: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    receiver_position: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    position: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    visit_place: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    place_detail: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    objective: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    purpose: {
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

  function scrollFocus(el) {
    if (!el) return;
    el.scrollIntoView({
      behavior: "smooth",
      block: "center"
    });
    setTimeout(() => el.focus?.(), 200);
  }

  function toThaiNumber(value) {
    const map = ["๐", "๑", "๒", "๓", "๔", "๕", "๖", "๗", "๘", "๙"];
    return String(value).replace(/[0-9]/g, d => map[d]);
  }

  function formatTimeThai(timeStr) {
    if (!timeStr) return "";
    const [h, m] = timeStr.split(":");
    return `${toThaiNumber(h)}.${toThaiNumber(m)}`;
  }

  function updateVisitTime() {
    if (!timeStart.value) {
      visitTime.value = "";
      return;
    }

    visitTime.value = `${formatTimeThai(timeStart.value)} น.`;
  }
  timeStart?.addEventListener("change", updateVisitTime);

  const monthsTH = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน",
    "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม",
    "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
  ];

  let docDatePicker;
  let visitStartPicker;

  function formatThaiDate(date) {
    const day = date.getDate();
    const month = monthsTH[date.getMonth()];
    const year = date.getFullYear() + 543;

    return `${day} ${month} ${year}`;
  }

  function updateDocDate() {
    const selected = docDatePicker?.selectedDates?. [0];
    if (!selected) {
      docDate.value = "";
      return;
    }

    const thaiDate = formatThaiDate(selected);
    docDate.value = thaiDate;
    docDateDisplay.value = thaiDate;
  }

  function updateVisitPeriod() {
    const start = visitStartPicker?.selectedDates?. [0];

    if (!start) {
      visitPeriod.value = "";
      return;
    }

    visitPeriod.value = formatThaiDate(start);
  }



  if (window.flatpickr) {
    flatpickr.localize(flatpickr.l10ns.th);

    if (docDateDisplay) {
      docDatePicker = flatpickr("#docDateDisplay", {
        dateFormat: "d/m/Y",
        disableMobile: true,
        allowInput: false,
        onChange: updateDocDate
      });

      docDateDisplay.addEventListener("click", () => {
        docDatePicker?.open();
      });
    }

    if (visitStart) {
      visitStartPicker = flatpickr("#visitStart", {
        dateFormat: "d/m/Y",
        disableMobile: true,
        allowInput: false,
        onChange: updateVisitPeriod
      });
      visitStart.addEventListener("click", () => {
        visitStartPicker?.open();
      });
    }
  }

  function rebuildTeacherInputs() {
    const oldNames = Array.from(document.querySelectorAll(".teacher-name-input"))
      .map(input => input.value.trim());
    const oldAffiliations = Array.from(document.querySelectorAll(".teacher-affiliation-input"))
      .map(input => input.value.trim());

    const count = parseInt(teacherCount.value, 10);

    teacherNameContainer.innerHTML = "";
    teacherNamesText.value = "";

    if (!count || count < 1) return;

    for (let i = 1; i <= count; i++) {
      const row = document.createElement("div");
      row.className = "teacher-name-row";

      row.innerHTML = `
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block mb-1 text-gray-700">ชื่อคณาจารย์คนที่ ${i} :</label>
          <input type="text"
            id="teacherNameInput${i}"
            name="teacher_names[]"
            class="teacher-name-input w-full border rounded-md p-2"
            placeholder="เช่น รองศาสตราจารย์ ดร.สมชาย ใจดี">
        </div>
        <div>
          <label class="block mb-1 text-gray-700">คณะ/สังกัดของคนที่ ${i} :</label>
          <input type="text"
            id="teacherAffiliationInput${i}"
            name="teacher_affiliations[]"
            data-spell-field="teacher_affiliation_${i}"
            data-spell-box-id="teacherAffiliationSpellBox${i}"
            class="teacher-affiliation-input w-full border rounded-md p-2"
            placeholder="เช่น คณะเทคโนโลยีและการจัดการอุตสาหกรรม">
          <div id="teacherAffiliationSpellBox${i}" class="spell-box hidden"></div>
        </div>
      </div>
    `;

      teacherNameContainer.appendChild(row);

      const input = row.querySelector(".teacher-name-input");
      const affiliationInput = row.querySelector(".teacher-affiliation-input");
      if (oldNames[i - 1]) {
        input.value = oldNames[i - 1];
      }
      if (oldAffiliations[i - 1]) {
        affiliationInput.value = oldAffiliations[i - 1];
      }
    }

    updateTeacherNamesText();
  }

  function updateTeacherNamesText() {
    const nameInputs = Array.from(document.querySelectorAll(".teacher-name-input"));
    const affiliationInputs = Array.from(document.querySelectorAll(".teacher-affiliation-input"));

    const names = nameInputs.map(input => input.value.trim()).filter(Boolean);
    const affiliations = affiliationInputs.map(input => input.value.trim()).filter(Boolean);

    teacherNamesText.value = names.join("\n");
    teacherAffiliationsText.value = affiliations.join("\n");

    teacherListText.value = nameInputs.map((input, index) => {
      const name = input.value.trim();
      const affiliation = affiliationInputs[index]?.value.trim() || "";
      if (!name && !affiliation) return "";
      return `${name}|${affiliation}`;
    }).filter(Boolean).join("\n");
  }

  teacherCount?.addEventListener("input", rebuildTeacherInputs);
  teacherNameContainer?.addEventListener("input", updateTeacherNamesText);

  function getSpellBoxByField(el) {
    if (!el) return null;
    if (el.dataset.spellBoxId) return byId(el.dataset.spellBoxId);
    if (el.id === "subjectInput") return byId("subjectInputSpellBox");
    if (el.id === "toPerson") return byId("toPersonSpellBox");
    if (el.id === "receiverPositionInput") return byId("receiverPositionInputSpellBox");
    if (el.id === "positionInput") return byId("positionInputSpellBox");
    if (el.id === "visitPlaceInput") return byId("visitPlaceInputSpellBox");
    if (el.id === "placeDetailInput") return byId("placeDetailInputSpellBox");
    if (el.id === "objectiveInput") return byId("objectiveInputSpellBox");
    if (el.id === "purposeInput") return byId("purposeInputSpellBox");
    return null;
  }

  function getSpellLoadingByField(el) {
    if (!el) return null;
    if (el.id === "subjectInput") return byId("subjectInputSpellLoading");
    if (el.id === "toPerson") return byId("toPersonSpellLoading");
    if (el.id === "receiverPositionInput") return byId("receiverPositionInputSpellLoading");
    if (el.id === "positionInput") return byId("positionInputSpellLoading");
    if (el.id === "visitPlaceInput") return byId("visitPlaceInputSpellLoading");
    if (el.id === "placeDetailInput") return byId("placeDetailInputSpellLoading");
    if (el.id === "objectiveInput") return byId("objectiveInputSpellLoading");
    if (el.id === "purposeInput") return byId("purposeInputSpellLoading");
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
    if ((el.value || "").trim() !== "") {
      el.classList.add("spell-ok");
    }
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
    extractThaiWords(cleanText).forEach(word => approvedWords.add(word));
  }

  function isApprovedText(fieldName, text) {
    const cleanText = String(text || "").trim();
    return !!(fieldName && cleanText && approvedTexts[fieldName] === cleanText);
  }

  function filterApprovedErrors(errors = []) {
    return errors.filter(item => {
      const wrongWord = String(item?.wrongWord || "").trim();
      return wrongWord && !approvedWords.has(wrongWord);
    });
  }

  function setSpellPassed(el, fieldName, text, remember = false) {
    if (remember) rememberApprovedText(fieldName, text);

    spellState[fieldName] = {
      checked: true,
      hasError: false,
      ignored: remember,
      errors: [],
      lastText: text
    };

    clearSpellResult(el);
    if ((text || "").trim() !== "") el.classList.add("spell-ok");
  }

  function isIgnoredForSameText(fieldName, text) {
    const state = spellState[fieldName];
    return !!(state && state.ignored && state.lastText === text);
  }

  function showSpellError(el, errors = []) {
    clearSpellResult(el);
    el.classList.add("spell-error");

    const box = getSpellBoxByField(el);
    if (!box) return;

    errors = filterApprovedErrors(normalizeErrors(errors, el.value || ""));

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
    if (!el) return;

    clearSpellResult(el);

    const text = (el.value || "").trim();
    if (!text) return;

    const fieldName = el.dataset.spellField || "";
    if (fieldName && !spellState[fieldName]) {
      spellState[fieldName] = {
        checked: false,
        hasError: false,
        ignored: false,
        errors: [],
        lastText: ""
      };
    }
    const cacheKey = `${fieldName}::${text}`;

    if (isApprovedText(fieldName, text) || correctedTexts[fieldName] === text) {
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
    const fields = Array.from(document.querySelectorAll("[data-spell-field]"));

    for (const el of fields) {
      if (!el) continue;

      const fieldName = el.dataset.spellField || "";
      if (fieldName && !spellState[fieldName]) {
        spellState[fieldName] = {
          checked: false,
          hasError: false,
          ignored: false,
          errors: [],
          lastText: ""
        };
      }
      const text = (el.value || "").trim();

      if (!text) continue;

      const state = spellState[fieldName];

      if (state && state.checked && !state.hasError && state.lastText === text) {
        continue;
      }

      if (isApprovedText(fieldName, text) || correctedTexts[fieldName] === text) {
        setSpellPassed(el, fieldName, text, false);
        continue;
      }

      await checkSpellField(el);
    }

    for (const key in spellState) {
      const state = spellState[key];
      const remainingErrors = filterApprovedErrors(state.errors || []);

      if (state.checked && state.hasError && remainingErrors.length > 0) {
        alert("กรุณาเลือกคำแนะนำ หรือกดใช้ข้อความเดิมก่อนดำเนินการ");
        return false;
      }
    }

    return true;
  }


  function validateForm() {
    let firstInvalid = null;

    const teacherInputs = Array.from(document.querySelectorAll(".teacher-name-input"));

    [
      docDateDisplay,
      subjectInput,
      toPerson,
      receiverNameInput,
      receiverPositionInput,
      fullname,
      positionInput,
      visitPlaceInput,
      placeDetailInput,
      objectiveInput,
      purposeInput,
      visitStart,
      timeStart,
      teacherCount,
      ...teacherInputs,
      ...Array.from(document.querySelectorAll(".teacher-affiliation-input"))
    ].forEach(el => setErr(el, false));

    const requiredFields = [
      docDateDisplay,
      subjectInput,
      toPerson,
      receiverNameInput,
      receiverPositionInput,
      fullname,
      positionInput,
      visitPlaceInput,
      placeDetailInput,
      objectiveInput,
      purposeInput,
      visitStart,
      timeStart,
      teacherCount
    ];

    requiredFields.forEach(el => {
      if (!el?.value.trim()) {
        setErr(el, true);
        firstInvalid = firstInvalid || el;
      }
    });

    teacherInputs.forEach(el => {
      if (!el.value.trim()) {
        setErr(el, true);
        firstInvalid = firstInvalid || el;
      }
    });

    document.querySelectorAll(".teacher-affiliation-input").forEach(el => {
      if (!el.value.trim()) {
        setErr(el, true);
        firstInvalid = firstInvalid || el;
      }
    });

    if (firstInvalid) {
      alert("กรุณากรอกข้อมูลให้ครบถ้วน");
      scrollFocus(firstInvalid);
      return false;
    }

    updateDocDate();
    updateVisitPeriod();
    updateVisitTime();
    updateTeacherNamesText();

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

    setSpellPassed(target, fieldName, currentText, false)
  });



  document.addEventListener("input", (e) => {
    const el = e.target.closest("[data-spell-field]");
    if (!el) return;

    const fieldName = el.dataset.spellField || "";
    if (!fieldName) return;

    spellState[fieldName] = {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: (el.value || "").trim()
    };

    clearSpellResult(el);
  });

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();


    updateTeacherNamesText();

    if (!validateForm()) return;

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;

    form.submit();
  });
  </script>
  <script>
  // ระบบเปิด/ปิดเมนูโปรไฟล์
  const profileBtn = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");

  if (profileBtn && profileMenu) {
    profileBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      profileMenu.classList.toggle("hidden");
    });

    window.addEventListener("click", (e) => {
      if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
        profileMenu.classList.add("hidden");
      }
    });
  }

  function closeMenu() {
    profileMenu?.classList.add("hidden");
  }
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const main = document.getElementById("mainCategory");
    const sub = document.getElementById("subCategory");
    if (!main || !sub) return;

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
      const target = new URL(url, window.location.origin);
      if (mainVal) target.searchParams.set("main", mainVal);
      if (subVal) target.searchParams.set("sub", subVal);
      return target.toString();
    }

    function renderSubOptions(list, selectedValue = "") {
      const selected = String(selectedValue || "").trim();
      sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';

      list.forEach(text => {
        const opt = document.createElement("option");
        opt.value = text;
        opt.textContent = text;
        if (text === selected) opt.selected = true;
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

    function goMain() {
      const mainVal = (main.value || "").trim();
      const firstSub = (SUB_OPTIONS[mainVal] || [""])[0] || "";
      const target = ROUTE_SUB[firstSub];

      if (target && target !== "#") {
        window.location.href = withSelection(target, mainVal, firstSub);
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

    main.addEventListener("change", () => {
      sub.dataset.current = "";
      syncUI();
      goMain();
    });

    sub.addEventListener("change", goSub);
    syncUI();
  });
  </script>
</body>

</html>