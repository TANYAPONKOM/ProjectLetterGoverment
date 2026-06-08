<?php 
// ต้องวางตรงนี้! บรรทัดแรกของไฟล์
$CURRENT_MAIN = "academic";     
$CURRENT_SUB  = "ประชุมวิชาการ/ศึกษาดูงาน/สัมมนาวิชาการ";           // ถ้าไม่มีหมวดย่อย ให้เว้นว่าง
?>
<!--2.ประชุมวิชาการ/ศึกษาดูงาน/สัมมนาวิชาการ -->
<?php
session_start();
require_once __DIR__ . '/../functions.php';
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

  .spell-suggestion-btn,
  .spell-ignore-btn {
    border: 1px solid #fdba74;
    background: #ffffff;
    color: #9a3412;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
  }

  .spell-ignore-btn {
    border-color: #cbd5e1;
    color: #334155;
    background: #f8fafc;
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

      <a href="documents/form_Memo.php">
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
              <?= ($CURRENT_MAIN!="internal"?"disabled":"") ?>>
              <option value="">-- เลือกหมวดย่อย --</option>

              <?php if ($CURRENT_MAIN == "internal"): ?>
              <option value="ขอใช้อาคารวันหยุดราชการ" <?= ($CURRENT_SUB=="ขอใช้อาคารวันหยุดราชการ"?"selected":"") ?>>
                ขอใช้อาคารวันหยุดราชการ
              </option>

              <option value="ขอห้องพักรับรอง" <?= ($CURRENT_SUB=="ขอห้องพักรับรอง"?"selected":"") ?>>
                ขอห้องพักรับรอง
              </option>

              <option value="ขออนุมัติตัวบุคคลเป็นวิทยากร"
                <?= ($CURRENT_SUB=="ขออนุมัติตัวบุคคลเป็นวิทยากร"?"selected":"") ?>>
                ขออนุมัติตัวบุคคลเป็นวิทยากร
              </option>

              <option value="ขออนุมัติไม่เข้าร่วมโครงการ"
                <?= ($CURRENT_SUB=="ขออนุมัติไม่เข้าร่วมโครงการ"?"selected":"") ?>>
                ขออนุมัติไม่เข้าร่วมโครงการ
              </option>

              <option value="การเผยแพร่งานวิจัยและเบิกค่าตอบแทนการตีพิมพ์"
                <?= ($CURRENT_SUB=="การเผยแพร่งานวิจัยและเบิกค่าตอบแทนการตีพิมพ์"?"selected":"") ?>>
                การเผยแพร่งานวิจัยและเบิกค่าตอบแทนการตีพิมพ์
              </option>

              <option value="ขอแจ้งเรียนการเป็นผู้ร่วมวิจัย"
                <?= ($CURRENT_SUB=="ขอแจ้งเรียนการเป็นผู้ร่วมวิจัย"?"selected":"") ?>>
                ขอแจ้งเรียนการเป็นผู้ร่วมวิจัย
              </option>
              <?php endif; ?>
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


      <!-- ข้อ 1 -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 items-end">
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="docDateDisplay">1.วัน เดือน ปี :</label>

          <div class="relative">
            <input type="text" id="docDateDisplay" class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer"
              placeholder="เลือกวันที่" readonly />

            <input type="hidden" name="doc_date" id="docDate" />

            <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
              xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <label class="lbl text-gray-800 whitespace-nowrap">ที่ต้องการให้ปรากฎบนบันทึกข้อความ</label>
        </div>
      </div>
      <!-- ข้อ 2 -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 items-end">
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="fullname">2.ชื่อ - นามสกุล :</label>
          <select name="fullname" class="flex-1 border rounded-md p-2" id="fullname">
            <option>อาจารย์ ดร.พิทย์พิมล ชูรอด</option>
          </select>
        </div>
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="position">ตำแหน่ง :</label>
          <input type="text" name="position" class="flex-1 border rounded-md p-2" id="position"
            value="อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ" />
        </div>
      </div>

      <!-- ข้อ 3 -->
      <div class="mb-4">
        <div class="flex items-start gap-4">

          <label class="lbl text-gray-800 whitespace-nowrap pt-1" id="purposeLabel">
            3. ขออนุมัติไปเข้าร่วม :
          </label>

          <div class="text-gray-800 space-y-1 mt-2" id="purposeGroup" role="radiogroup" aria-labelledby="purposeLabel">

            <label class="flex items-center gap-2">
              <input type="radio" name="purpose" value="ประชุมวิชาการ" class="accent-black" />
              เข้าร่วมประชุมวิชาการ
            </label>

            <label class="flex items-center gap-2">
              <input type="radio" name="purpose" value="ศึกษาดูงาน" class="accent-black" />
              เข้าร่วมศึกษาดูงาน
            </label>

            <label class="flex items-center gap-2">
              <input type="radio" name="purpose" value="สัมมนาวิชาการ" class="accent-black" />
              เข้าร่วมสัมมนาวิชาการ
            </label>

            <div class="flex items-start gap-2">
              <div class="flex items-center gap-2 h-[42px]">
                <input type="radio" name="purpose" value="other" class="accent-black" id="purposeOtherRadio">
                <span class="whitespace-nowrap">อื่น ๆ</span>
              </div>

              <div class="flex flex-col ml-3">
                <input type="text" name="purpose_other_detail" id="purposeOtherInput"
                  data-spell-field="purpose_other_detail"
                  class="border rounded-md p-2 w-[330px] bg-gray-100 text-gray-400" placeholder="โปรดระบุ" disabled>

                <div id="purposeOtherSpellBox" class="spell-box hidden"></div>

                <div id="purposeOtherSpellLoading" class="spell-loading hidden">
                  <div class="spell-loading-row">
                    <div class="spell-spinner"></div>
                    <span>กำลังตรวจคำผิด...</span>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>



      <!-- ข้อ 4 -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap pt-2" for="eventTitle">
          4.ชื่อของงานประชุมวิชาการ /<br />ชื่อหลักสูตรอบรม :
        </label>
        <div class="w-full">
          <textarea name="event_title" rows="2" class="w-full border rounded-md p-2 shadow-sm" id="eventTitle"
            data-spell-field="event_title"></textarea>

          <div id="eventTitleSpellBox" class="spell-box hidden"></div>
          <div id="eventTitleSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ข้อ 5 -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap pt-2" for="researchTitle">
          5. ชื่อบทความวิจัยที่จะนำเสนอ :
        </label>
        <div class="w-full">
          <textarea name="research_title" rows="2" class="w-full border rounded-md p-2 shadow-sm" id="researchTitle"
            data-spell-field="research_title"></textarea>

          <div id="researchTitleSpellBox" class="spell-box hidden"></div>
          <div id="researchTitleSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ข้อ 6 -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap pt-1" for="presentationType">
          6. ประเภทการนำเสนอ :
        </label>

        <div class="w-full ml-1 space-y-1 mt-2" id="presentationType">

          <label class="flex items-center gap-2">
            <input type="radio" name="presentation_type" value="oral" class="accent-black option-radio">
            Oral
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="presentation_type" value="poster" class="accent-black option-radio">
            Poster
          </label>

          <!-- ลดช่องว่างของบรรทัดนี้ -->
          <div class="flex items-start gap-2">
            <!-- ฝั่ง radio + คำว่าอื่น ๆ ล็อกให้อยู่บรรทัดบน -->
            <div class="flex items-center gap-2 h-[42px]">
              <input type="radio" name="presentation_type" value="other" class="accent-black" id="otherTypeRadio">
              <span class="whitespace-nowrap">อื่น ๆ (ระบุ)</span>
            </div>

            <!-- ฝั่งกล่องข้อความ + คำแนะนำ -->
            <div class="flex flex-col ml-3">
              <input type="text" name="presentation_other_detail" id="otherTypeInput"
                data-spell-field="presentation_other_detail"
                class="border rounded-md p-2 w-[330px] bg-gray-100 text-gray-400" placeholder="โปรดระบุ" disabled>

              <div id="otherTypeSpellBox" class="spell-box hidden"></div>

              <div id="otherTypeSpellLoading" class="spell-loading hidden">
                <div class="spell-loading-row">
                  <div class="spell-spinner"></div>
                  <span>กำลังตรวจคำผิด...</span>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>




      <div class="mb-6">
        <label class="lbl text-gray-800 block mb-2" id="dateLabel">7. วันที่เข้าร่วม</label>

        <div class="space-y-4 ml-6 text-gray-800">
          <!-- 🔹 วันเดียว -->
          <div class="flex items-center gap-2">
            <input type="radio" name="date_option" value="single" id="optSingle" class="accent-[#11C2B9]" checked />
            <span>วันเดียว :</span>
            <div class="relative">
              <input type="text" name="single_date" id="singleDate"
                class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly />
              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>
          </div>

          <!-- 🔹 หลายวัน -->
          <div class="flex flex-wrap items-center gap-2">
            <input type="radio" name="date_option" value="range" id="optRange" class="accent-[#11C2B9]" />
            <span>หลายวัน :</span>

            <!-- วันที่เริ่มต้น -->
            <div class="relative">
              <input type="text" id="startDate" class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer"
                placeholder="เริ่มต้น" readonly />
              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>

            <span>ถึง</span>

            <!-- วันที่สิ้นสุด -->
            <div class="relative">
              <input type="text" id="endDate" class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer"
                placeholder="สิ้นสุด" readonly />
              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>

            <!-- 🔹 แสดงผลรูปแบบวันที่ -->
            <input type="text" id="rangeDisplay" class="border rounded-md p-2 shadow-sm w-64 bg-gray-50 text-gray-600"
              placeholder="10 - 11 กรกฎาคม 2568" readonly />

            <!-- ซ่อนค่ารวมเพื่อส่งข้อมูล -->
            <input type="hidden" name="range_date" id="rangeDate" value="" />
          </div>
        </div>
      </div>

      <!-- ข้อ 8 -->
      <div class="mb-4">
        <label class="lbl text-gray-800 block mb-2">
          8. ชื่อสถานที่จัดประชุมวิชาการ / สถานที่จัดอบรม / เข้าร่วมรูปแบบออนไลน์
        </label>

        <div class="ml-7 space-y-2">

          <label class="flex items-center gap-2">
            <input type="radio" id="onlineCheckbox" name="is_online" value="1" class="accent-black">
            เข้าร่วมรูปแบบออนไลน์
          </label>

          <label class="flex items-start gap-2">
            <input type="radio" id="onsiteCheckbox" name="is_online" value="0" class="accent-black mt-3">

            <span class="mt-2">ออนไซต์ ณ</span>

            <div class="flex flex-col">
              <input type="text" name="place" id="placeInput" data-spell-field="place"
                class="border rounded-md p-2 w-[350px] shadow-sm bg-gray-100 text-gray-400"
                placeholder="เช่น โรงแรม Best Western PLUS จังหวัดนนทบุรี" disabled>

              <div id="placeSpellBox" class="spell-box hidden"></div>

              <div id="placeSpellLoading" class="spell-loading hidden">
                <div class="spell-loading-row">
                  <div class="spell-spinner"></div>
                  <span>กำลังตรวจคำผิด...</span>
                </div>
              </div>
            </div>
          </label>

        </div>
      </div>


      <!-- ข้อ 9 -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap pt-2">
          9. ฐานข้อมูลที่ตีพิมพ์ (เช่น Scopus) :
        </label>

        <div class="w-full">
          <input type="text" name="database" id="databaseInput" data-spell-field="database"
            class="border rounded-md p-2 w-[350px] shadow-sm" placeholder="เช่น Scopus, TCI, IEEE">

          <div id="databaseSpellBox" class="spell-box hidden"></div>
          <div id="databaseSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- ข้อ 10 -->
      <div class="mb-6">
        <div class="flex items-center gap-2 mb-2">
          <label class="lbl text-gray-800" for="amountInput">10.รวมยอดประมาณการค่าใช้จ่าย :</label>
          <div class="flex flex-col">
            <div class="flex items-center gap-2">
              <input type="text" name="amount" class="border rounded-md p-2 w-36" id="amountInput" value="0.00" />
              <span>บาท</span>
            </div>
          </div>
        </div>
        <label class="flex items-center gap-2 ml-6 mt-2">
          <input type="checkbox" name="no_cost" value="1" class="accent-black" id="noCostCheckbox" />
          โดยไม่เบิกค่าใช้จ่ายใดๆทั้งสิ้น
        </label>
      </div>


      <!-- ข้อ 11 -->
      <div class="mb-6">
        <label class="lbl block text-gray-800 mb-2" id="carLabel">11.กรณีไปรถยนต์ส่วนตัว</label>
        <div class="flex items-center gap-2 ml-6">
          <input type="checkbox" name="car_used" value="1" class="accent-black" id="carCheckbox" />
          <label for="carPlateInput" class="lbl">ระบุหมายเลขทะเบียนรถยนต์ :</label>
          <div class="flex flex-col">
            <input type="text" name="car_plate" class="border rounded-md p-2 w-[250px]" id="carPlateInput"
              placeholder="เช่น กร 1906 พัทลุง" disabled />
          </div>
        </div>
      </div>

      <!-- ปุ่ม -->
      <div class="relative mt-20">
        <div class="absolute right-0 bottom-0">
          <button type="submit" id="submitBtn"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md">
            ดำเนินการ
          </button>
        </div>

      </div>
    </div>
  </form>
  <script>
  const spellState = {
    purpose_other_detail: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    event_title: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    research_title: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    presentation_other_detail: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    place: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    database: {
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

  function getSpellBoxByField(el) {
    if (!el) return null;
    if (el.id === "purposeOtherInput") return document.getElementById("purposeOtherSpellBox");
    if (el.id === "eventTitle") return document.getElementById("eventTitleSpellBox");
    if (el.id === "researchTitle") return document.getElementById("researchTitleSpellBox");
    if (el.id === "otherTypeInput") return document.getElementById("otherTypeSpellBox");
    if (el.id === "placeInput") return document.getElementById("placeSpellBox");
    if (el.id === "databaseInput") return document.getElementById("databaseSpellBox");
    return null;
  }

  function getSpellLoadingByField(el) {
    if (!el) return null;
    if (el.id === "purposeOtherInput") return document.getElementById("purposeOtherSpellLoading");
    if (el.id === "eventTitle") return document.getElementById("eventTitleSpellLoading");
    if (el.id === "researchTitle") return document.getElementById("researchTitleSpellLoading");
    if (el.id === "otherTypeInput") return document.getElementById("otherTypeSpellLoading");
    if (el.id === "placeInput") return document.getElementById("placeSpellLoading");
    if (el.id === "databaseInput") return document.getElementById("databaseSpellLoading");
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
      errors: [],
      lastText: text
    };

    clearSpellResult(el);

    if ((text || "").trim() !== "") {
      el.classList.add("spell-ok");
    }
  }

  function shouldCheckSpell(el) {
    if (!el) return false;
    if (el.disabled || el.readOnly) return false;

    if (el.id === "purposeOtherInput") {
      return !!document.getElementById("purposeOtherRadio")?.checked;
    }

    if (el.id === "otherTypeInput") {
      return !!document.getElementById("otherTypeRadio")?.checked;
    }

    if (el.id === "placeInput") {
      return !!document.getElementById("onsiteCheckbox")?.checked;
    }

    return true;
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
    if (!shouldCheckSpell(el)) return;

    const text = (el.value || "").trim();
    if (!text) return;

    const fieldName = el.dataset.spellField || "";
    const cacheKey = `${fieldName}::${text}`;

    if (isApprovedText(fieldName, text) || correctedTexts[fieldName] === text) {
      setSpellPassed(el, fieldName, text, false);
      return;
    }

    if (spellCache[cacheKey]) {
      const cached = spellCache[cacheKey];
      const normalizedErrors = filterApprovedErrors(normalizeErrors(cached.errors || [], text));

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
    const timeoutId = setTimeout(() => controller.abort(), 3000);

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

      const normalizedErrors = filterApprovedErrors(normalizeErrors(result.errors || [], text));

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
      document.getElementById("purposeOtherInput"),
      document.getElementById("eventTitle"),
      document.getElementById("researchTitle"),
      document.getElementById("otherTypeInput"),
      document.getElementById("placeInput"),
      document.getElementById("databaseInput")
    ];

    for (const el of fields) {
      if (!el || !shouldCheckSpell(el)) continue;

      const fieldName = el.dataset.spellField || "";
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

  document.addEventListener("click", (e) => {
    const ignoreBtn = e.target.closest(".spell-ignore-btn");
    if (!ignoreBtn) return;

    const target = document.getElementById(ignoreBtn.dataset.target);
    if (!target) return;

    const fieldName = target.dataset.spellField || "";
    const currentText = (target.value || "").trim();

    setSpellPassed(target, fieldName, currentText, true);
  });

  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".spell-suggestion-btn");
    if (!btn) return;

    const target = document.getElementById(btn.dataset.target);
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
  </script>
  <script>
  /* ====== Helpers ====== */
  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));
  const byId = (id) => document.getElementById(id);
  const labelFor = (id) => document.querySelector(`label[for="${id}"]`);
  const setErr = (el, on = true) => {
    if (!el) return;
    el.classList.toggle("error", on);
    if (on) {
      el.classList.add("shake");
      setTimeout(() => el.classList.remove("shake"), 250);
    }
    el.setAttribute("aria-invalid", on ? "true" : "false");
  };
  const setStar = (labelEl, on = true) => {
    if (labelEl) labelEl.classList.toggle("asterisk", on);
  };

  /* ====== Elements ====== */
  const form = byId("memoForm");
  const docDate = byId("docDate");
  const eventTitle = byId("eventTitle");

  const purposeRadios = $$('input[name="purpose"]');
  const purposeGroup = byId("purposeGroup");
  const purposeLabel = byId("purposeLabel");

  const purposeOtherInput = document.getElementById("purposeOtherInput");
  const purposeOtherRadio = document.getElementById("purposeOtherRadio");
  const otherTypeInput = byId("otherTypeInput");
  const otherTypeRadio = byId("otherTypeRadio");
  const presentationTypeRadios = $$('input[name="presentation_type"]');


  function syncPresentationTypeUI() {
    if (!otherTypeInput) return;

    if (otherTypeRadio?.checked) {
      otherTypeInput.disabled = false;
      otherTypeInput.classList.remove("bg-gray-100", "text-gray-400");
      otherTypeInput.focus();
    } else {
      otherTypeInput.value = "";
      otherTypeInput.disabled = true;
      otherTypeInput.classList.add("bg-gray-100", "text-gray-400");
      clearSpellResult(otherTypeInput);

      spellState.presentation_other_detail = {
        checked: false,
        hasError: false,
        ignored: false,
        errors: [],
        lastText: ""
      };
    }
  }

  presentationTypeRadios.forEach(radio => {
    radio.addEventListener("change", syncPresentationTypeUI);
  });


  purposeRadios.forEach(radio => {
    radio.addEventListener("change", () => {
      if (purposeOtherRadio?.checked) {
        purposeOtherInput.disabled = false;
        purposeOtherInput.classList.remove("bg-gray-100", "text-gray-400");
        purposeOtherInput.focus();
      } else {
        purposeOtherInput.value = "";
        purposeOtherInput.disabled = true;
        purposeOtherInput.classList.add("bg-gray-100", "text-gray-400");
        clearSpellResult(purposeOtherInput);

        spellState.purpose_other_detail = {
          checked: false,
          hasError: false,
          ignored: false,
          errors: [],
          lastText: ""
        };
      }
    });
  });


  const optSingle = byId("optSingle");
  const singleDate = byId("singleDate");
  const optRange = byId("optRange");
  const rangeDate = byId("rangeDate");
  const dateLabel = byId("dateLabel");

  const onlineCheckbox = byId("onlineCheckbox");
  const onsiteCheckbox = byId("onsiteCheckbox");
  const placeInput = byId("placeInput");

  const amountInput = byId("amountInput");
  const noCostCheckbox = byId("noCostCheckbox");

  const carCheckbox = byId("carCheckbox");
  const carPlateInput = byId("carPlateInput");

  /* ====== Sync UI (ไม่สร้าง/ลบ element) ====== */
  function syncDateOptionUI() {
    if (optSingle.checked) {
      singleDate.disabled = false;
      rangeDate.disabled = true;
      setErr(rangeDate, false);
    } else {
      singleDate.disabled = true;
      setErr(singleDate, false);
      rangeDate.disabled = false;
    }
  }

  function syncOnlineUI() {
    if (onlineCheckbox.checked) {
      placeInput.value = "";
      placeInput.disabled = true;
      setErr(placeInput, false);
    } else {
      placeInput.disabled = false;
    }
  }

  function syncCostUI() {
    if (noCostCheckbox.checked) {
      amountInput.value = "0.00";
      amountInput.disabled = true;
      setErr(amountInput, false);
    } else {
      amountInput.disabled = false;
    }
  }

  function syncCarUI() {
    if (carCheckbox.checked) {
      carPlateInput.disabled = false;
    } else {
      carPlateInput.value = "";
      carPlateInput.disabled = true;
      setErr(carPlateInput, false);
    }
  }

  optSingle.addEventListener("change", syncDateOptionUI);
  optRange.addEventListener("change", syncDateOptionUI);
  onlineCheckbox.addEventListener("change", syncOnlineUI);
  noCostCheckbox.addEventListener("change", syncCostUI);
  carCheckbox.addEventListener("change", syncCarUI);

  syncDateOptionUI();
  syncOnlineUI();
  syncCostUI();
  syncCarUI();

  /* เคลียร์ error เมื่อมีการแก้ไข */
  [
    docDate,
    eventTitle,
    singleDate,
    rangeDate,
    placeInput,
    amountInput,
    carPlateInput,
  ].forEach((el) => {
    el.addEventListener("input", () => setErr(el, false));
    el.addEventListener("change", () => setErr(el, false));
  });
  purposeRadios.forEach((r) => {
    r.addEventListener("change", () => {
      purposeGroup.classList.remove("ring-2", "ring-red-300");
      setStar(purposeLabel, false);
    });
  });

  /* ====== Validate (ใส่กรอบแดง + ดอกจันเท่านั้น) ====== */
  function scrollFocus(el) {
    if (!el) return;
    el.scrollIntoView({
      behavior: "smooth",
      block: "center"
    });
    setTimeout(() => el.focus?.(), 200);
  }

  function syncPlaceUI() {
    if (!placeInput) return;

    if (onlineCheckbox?.checked) {
      placeInput.value = "เข้าร่วมรูปแบบออนไลน์";
      placeInput.disabled = false;
      placeInput.readOnly = true;
      placeInput.classList.add("bg-gray-100", "text-gray-400");
      clearSpellResult(placeInput);
    } else if (onsiteCheckbox?.checked) {
      placeInput.readOnly = false;
      placeInput.disabled = false;
      placeInput.classList.remove("bg-gray-100", "text-gray-400");

      if (placeInput.value === "เข้าร่วมรูปแบบออนไลน์") {
        placeInput.value = "";
      }

      placeInput.focus();
    } else {
      placeInput.value = "";
      placeInput.disabled = true;
      placeInput.readOnly = false;
      placeInput.classList.add("bg-gray-100", "text-gray-400");
      clearSpellResult(placeInput);
    }
  }

  onlineCheckbox?.addEventListener("change", syncPlaceUI);
  onsiteCheckbox?.addEventListener("change", syncPlaceUI);

  function validate() {
    let firstInvalid = null;
    // ล้างดอกจันทั้งหมด
    $$(".lbl").forEach((l) => setStar(l, false));

    // 1) วันที่เอกสาร
    if (!docDate.value.trim()) {
      setErr(docDateDisplay, true);
      firstInvalid = firstInvalid || docDateDisplay;
    }

    // 3) วัตถุประสงค์
    const hasPurpose = purposeRadios.some((r) => r.checked);
    if (!hasPurpose) {
      purposeGroup.classList.add("shake", "ring-2", "ring-red-300");
      setTimeout(() => purposeGroup.classList.remove("shake"), 250);
      setStar(purposeLabel, true);
      firstInvalid = firstInvalid || purposeRadios[0];
    }

    // 4) ชื่องาน/หลักสูตร
    if (!eventTitle.value.trim()) {
      setErr(eventTitle, true);
      setStar(labelFor("eventTitle"), true);
      firstInvalid = firstInvalid || eventTitle;
    }

    // 5) วันที่เข้าร่วม
    if (optSingle.checked) {
      if (!singleDate.value.trim()) {
        setErr(singleDate, true);
        setStar(dateLabel, true);
        firstInvalid = firstInvalid || singleDate;
      }
    } else if (optRange.checked) {
      if (!rangeDate.value.trim()) {
        setErr(rangeDate, true);
        setStar(dateLabel, true);
        firstInvalid = firstInvalid || rangeDate;
      }
    } else {
      setStar(dateLabel, true);
      firstInvalid = firstInvalid || optRange;
    }

    // 6) สถานที่ (เฉพาะกรณีไม่ออนไลน์)
    if (!onlineCheckbox.checked && !onsiteCheckbox.checked) {
      setErr(onlineCheckbox, true);
      firstInvalid = firstInvalid || onlineCheckbox;
    } else if (onsiteCheckbox.checked && !placeInput.value.trim()) {
      setErr(placeInput, true);
      firstInvalid = firstInvalid || placeInput;
    }
    // 6) ประเภทการนำเสนอ - อื่น ๆ
    if (otherTypeRadio?.checked && !otherTypeInput?.value.trim()) {
      setErr(otherTypeInput, true);
      firstInvalid = firstInvalid || otherTypeInput;
    }

    // 7) จำนวนเงิน (ถ้าไม่ได้ติ๊กไม่เบิก)
    if (!noCostCheckbox.checked) {
      const raw = amountInput.value.replace(/,/g, "").trim();
      const val = Number(raw);
      if (raw === "" || isNaN(val)) {
        setErr(amountInput, true);
        setStar(labelFor("amountInput"), true);
        firstInvalid = firstInvalid || amountInput;
      }
    }

    // 8) ทะเบียนรถ (เมื่อเลือกใช้รถ)
    if (carCheckbox.checked && !carPlateInput.value.trim()) {
      setErr(carPlateInput, true);
      setStar(byId("carLabel"), true);
      firstInvalid = firstInvalid || carPlateInput;
    }

    if (firstInvalid) {
      scrollFocus(firstInvalid);
      return false;
    }
    return true;
  }

  /* ====== Submit แบบปกติ ====== */
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!validate()) return;

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;

    form.submit();
  });
  syncPresentationTypeUI();
  syncPlaceUI();
  </script>

  <script>
  flatpickr.localize(flatpickr.l10ns.th);
  const monthsTH = [
    "มกราคม",
    "กุมภาพันธ์",
    "มีนาคม",
    "เมษายน",
    "พฤษภาคม",
    "มิถุนายน",
    "กรกฎาคม",
    "สิงหาคม",
    "กันยายน",
    "ตุลาคม",
    "พฤศจิกายน",
    "ธันวาคม",
  ];

  function toThaiDisplay(dateObj) {
    const d = dateObj.getDate();
    const m = monthsTH[dateObj.getMonth()];
    const y = dateObj.getFullYear() + 543;
    return `${d} ${m} ${y}`;
  }

  const docDateDisplay = byId("docDateDisplay");
  const docDateHidden = byId("docDate");

  const docPicker = flatpickr(docDateDisplay, {
    disableMobile: true,
    allowInput: false,
    clickOpens: true,
    dateFormat: "Y-m-d",
    onChange: (selectedDates, dateStr, inst) => {
      const d = selectedDates[0];
      if (!d) return;

      docDateDisplay.value = toThaiDisplay(d);
      docDateHidden.value = inst.formatDate(d, "Y-m-d");
      setErr(docDateDisplay, false);
    }
  });

  docDateDisplay?.addEventListener("click", () => docPicker.open());


  // ✅ ปฏิทินวันเดียว
  flatpickr("#singleDate", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: function(selectedDates, dateStr, instance) {
      if (selectedDates.length > 0) {
        const date = selectedDates[0];
        const day = date.getDate();
        const month = monthsTH[date.getMonth()];
        const year = date.getFullYear() + 543;
        const formatted = `${day} ${month} ${year}`;

        // 🔹 แสดงผลรูปแบบไทยในช่อง input (แทนค่าเก่า)
        instance.input.value = formatted;
      }
    },
  });

  // ===== ปฏิทินช่วงวันที่ (เริ่มต้น / สิ้นสุด) =====
  const startPicker = flatpickr("#startDate", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: updateRangeDisplay,
  });

  const endPicker = flatpickr("#endDate", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: updateRangeDisplay,
  });

  // ===== ฟังก์ชันแปลงและแสดงผล =====
  function updateRangeDisplay() {
    const start = startPicker.selectedDates[0];
    const end = endPicker.selectedDates[0];
    if (start && end) {
      const months = [
        "มกราคม",
        "กุมภาพันธ์",
        "มีนาคม",
        "เมษายน",
        "พฤษภาคม",
        "มิถุนายน",
        "กรกฎาคม",
        "สิงหาคม",
        "กันยายน",
        "ตุลาคม",
        "พฤศจิกายน",
        "ธันวาคม",
      ];

      const startDay = start.getDate();
      const endDay = end.getDate();
      const startMonth = months[start.getMonth()];
      const endMonth = months[end.getMonth()];
      const startYear = start.getFullYear() + 543;
      const endYear = end.getFullYear() + 543;

      let displayText = "";

      // ✅ ถ้าเดือนเดียวกันและปีเดียวกัน
      if (
        start.getMonth() === end.getMonth() &&
        start.getFullYear() === end.getFullYear()
      ) {
        displayText = `${startDay} - ${endDay} ${endMonth} ${endYear}`;
      }
      // ✅ ถ้าเดือนหรือปีต่างกัน
      else {
        displayText = `${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
      }

      // ✅ แสดงผลในช่องรูปแบบและช่องซ่อน
      document.getElementById("rangeDisplay").value = displayText;
      document.getElementById("rangeDate").value = displayText;
    }
  }

  // ===== สลับสถานะช่องเมื่อเลือก radio =====
  document
    .getElementById("optSingle")
    .addEventListener("change", toggleDatePickers);
  document
    .getElementById("optRange")
    .addEventListener("change", toggleDatePickers);

  function toggleDatePickers() {
    const single = document.getElementById("singleDate");
    const start = document.getElementById("startDate");
    const end = document.getElementById("endDate");
    const display = document.getElementById("rangeDisplay");

    if (document.getElementById("optSingle").checked) {
      single.disabled = false;
      start.disabled = true;
      end.disabled = true;
      display.disabled = true;
    } else {
      single.disabled = true;
      start.disabled = false;
      end.disabled = false;
      display.disabled = false;
    }
  }
  // เรียกครั้งแรกให้ตรงตามค่า checked เริ่มต้น
  toggleDatePickers();
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

  // Mapping ไฟล์สำหรับ redirect
  const redirectMain = {
    train: "documents/form_Memo.php",
    academic: "Request_1.php",
    external: null, // ยังไม่มีฟอร์ม
    internal: null // ให้เลือกหมวดย่อยแทน
  };

  const redirectSub = {
    "ขอใช้อาคารวันหยุดราชการ": "Request_2.php",
    "ขอห้องพักรับรอง": "Request_3.php",
    "ขออนุมัติตัวบุคคลเป็นวิทยากร": "Request_4.php",
    "ขออนุมัติไม่เข้าร่วมโครงการ": "Request_5.php",
    "การเผยแพร่งานวิจัยและเบิกค่าตอบแทนการตีพิมพ์": "Request_6.php",
    "ขอแจ้งเรียนการเป็นผู้ร่วมวิจัย": "Request_7.php"
  };

  // หมวดย่อยของ "ภายใน"
  const subInternal = Object.keys(redirectSub);

  // เมื่อเลือก "หมวดหลัก"
  main.addEventListener("change", () => {
    const value = main.value;

    // เคลียร์หมวดย่อยก่อน
    sub.innerHTML = `<option value="">-- เลือกหมวดย่อย --</option>`;
    sub.disabled = true;

    // ถ้าเลือกหมวดที่มี redirect ทันที เช่น ฝึกอบรม, ประชุมฯ
    if (redirectMain[value]) {
      window.location.href = redirectMain[value];
      return;
    }

    // ถ้าเลือก "ภายนอก" → ไม่ redirect, ไม่เปิดหมวดย่อย
    if (value === "external") {
      return;
    }

    // ถ้าเลือก "ภายใน" → เปิดหมวดย่อย
    if (value === "internal") {
      sub.disabled = false;
      subInternal.forEach(text => {
        const opt = document.createElement("option");
        opt.value = text;
        opt.textContent = text;
        sub.appendChild(opt);
      });
    }
  });

  // เมื่อเลือกหมวดย่อยของภายใน → redirect
  sub.addEventListener("change", () => {
    const value = sub.value;
    if (redirectSub[value]) {
      window.location.href = redirectSub[value];
    }
  });
  </script>

</body>

</html>