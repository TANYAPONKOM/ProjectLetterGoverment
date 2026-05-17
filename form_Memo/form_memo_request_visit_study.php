<?php
// pro_letter/form_memo/form_memo_request_visit_study.php

$CURRENT_MAIN = "train";
$CURRENT_SUB  = "ฝึกอบรม";


session_start();
require_once __DIR__ . '/../functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}


$docId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $docId > 0;
$formData = [];

// ===============================
// LOAD DATA (เฉพาะโหมดแก้ไข)
// ===============================
if ($isEdit) {
    $pdo = db();

    // ตรวจเอกสาร
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

    // ✅ อนุญาต Admin / Officer แก้ไขได้
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


    // โหลดค่า document_values
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

// ===============================
// MAP VARIABLES (ต้องอยู่หลัง load DB เท่านั้น)
// ===============================
$requestPersonName = $formData[1]  ?? '';
$visitPlaceName = $formData[2]  ?? '';
$visitDateTime = $formData[3]  ?? '';
$numberOfFaculty = $formData[4]  ?? '';
$facultyList = $formData[5]  ?? '';
$faculty1Name = $formData[6] ?? '';
$faculty2Name = $formData[7] ?? '';
$faculty3Name = $formData[8] ?? '';
$faculty4Name = $formData[9] ?? '';
$visitObjective = $formData[10] ?? '';
$expectedBenefits = $formData[11] ?? '';
$faculty     = $formData[12] ?? '';
$department  = $formData[13] ?? '';


?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>แบบฟอร์มประเมินสหกิจศึกษา</title>

  <!-- Flatpickr Date Picker -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

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

  /* INPUT & TEXTAREA STYLING */
  input[type="text"],
  textarea {
    transition: all 0.2s ease-in-out;
  }

  input[type="text"]:focus,
  textarea:focus {
    box-shadow: 0 0 0 3px rgba(17, 194, 185, 0.25);
  }

  input[type="text"]::placeholder,
  textarea::placeholder {
    color: #b0b0b0;
    opacity: 0.8;
  }

  /* FLATPICKR CUSTOM STYLING */
  .flatpickr-calendar {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
    border-radius: 8px;
    border: none;
  }

  .flatpickr-monthDropdown-months,
  .flatpickr-monthDropdown-months .selected {
    background-color: #f3f4f6;
    color: #1f2937;
  }

  .flatpickr-innerContainer {
    border-bottom: 1px solid #e5e7eb;
  }

  .flatpickr-day.selected,
  .flatpickr-day.startRange,
  .flatpickr-day.endRange {
    background-color: #11c2b9;
    border-color: #11c2b9;
  }

  .flatpickr-day.inRange {
    background-color: rgba(17, 194, 185, 0.25);
    border-color: transparent;
  }

  .flatpickr-day:hover {
    background-color: rgba(17, 194, 185, 0.15);
  }

  .flatpickr-day.today {
    border-color: #11c2b9;
  }

  .flatpickr-prev,
  .flatpickr-next {
    color: #11c2b9;
  }

  .flatpickr-prev:hover,
  .flatpickr-next:hover {
    color: #0fa39c;
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
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="department_id" value="1">

    <?php if ($isEdit): ?>
    <input type="hidden" name="document_id" value="<?= (int)$docId ?>">
    <input type="hidden" name="mode" value="update">
    <?php else: ?>
    <input type="hidden" name="mode" value="create">
    <?php endif; ?>

    <div id="step1">
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 items-end">
          <div class="flex items-center gap-3">
            <label class="lbl asterisk text-gray-800 whitespace-nowrap" for="requestPersonName">1.
              ชื่อผู้ขออนุญาตเข้าเยี่ยมชม :</label>
            <input type="text" name="request_person_name" class="flex-1 border rounded-md p-2" id="requestPersonName"
              placeholder="เช่น รศ.ดร. สมชาย ใจดี" value="<?= h($formData[1] ?? '') ?>" />
          </div>
        </div>

        <!-- ข้อ 2 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2" for="visitPlaceName">2.
            ชื่อสถานที่ที่จะเข้าเยี่ยมชม :</label>
          <div class="w-full">
            <input type="text" name="visit_place_name" class="w-full border rounded-md p-2" id="visitPlaceName"
              placeholder="เช่น SUT Wellness Academy" value="<?= h($formData[2] ?? '') ?>" />
          </div>
        </div>

        <!-- ข้อ 3 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2">
            3. วันที่และเวลาเข้าเยี่ยมชม :<br />(วันเข้าเยี่ยมชม)
          </label>
          <div class="w-full">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-gray-600 text-sm mb-1 block">วันที่เข้าเยี่ยมชม</label>
                <div class="relative">
                  <input type="text" id="visitDate" name="visit_date"
                    class="w-full border rounded-md p-2 shadow-sm pr-10 cursor-pointer" placeholder="เลือกวันที่"
                    readonly value="<?= h($formData[3] ?? '') ?>" />
                  <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
              <div>
                <label class="text-gray-600 text-sm mb-1 block">เวลาเข้าเยี่ยมชม</label>
                <div class="flex items-center gap-2">
                  <input type="time" name="visit_time_start" id="visitTimeStart"
                    class="flex-1 border rounded-md p-2 shadow-sm" />
                  <span class="text-gray-600">ถึง</span>
                  <input type="time" name="visit_time_end" id="visitTimeEnd"
                    class="flex-1 border rounded-md p-2 shadow-sm" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ข้อ 4 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 items-end">
          <div class="flex items-center gap-3">
            <label class="lbl text-gray-800 whitespace-nowrap">4. จำนวนคณาจารย์ที่จะเข้าเยี่ยมชม :</label>
            <div class="flex-1 border rounded-md p-2 bg-gray-100 text-gray-800 font-semibold">
              <span id="facultyCount">0</span> คน
            </div>
            <input type="hidden" name="number_of_faculty" id="numberOfFacultyHidden" value="0" />
          </div>
        </div>

        <!-- ข้อ 5 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2">
            5. รายชื่อคณาจารย์ที่เข้าเยี่ยมชม :
          </label>
          <div class="w-full">
            <div id="facultyListContainer">
              <div class="mb-4 p-4 border rounded-md bg-gray-50" id="facultyRow1">
                <label class="text-gray-700 font-semibold block mb-2">ชื่อคณาจารย์ 1:</label>
                <input type="text" name="faculty_1" class="w-full border rounded-md p-2 shadow-sm" id="faculty1"
                  placeholder="เช่น รศ.ดร. สมชาย ใจดี" value="<?= h($formData[6] ?? '') ?>" />
              </div>

              <div class="mb-4 p-4 border rounded-md bg-gray-50 hidden" id="facultyRow2">
                <div class="flex items-center justify-between mb-2">
                  <label class="text-gray-700 font-semibold">ชื่อคณาจารย์ 2:</label>
                  <button type="button" class="removeFacultyBtn text-red-600 hover:text-red-800 font-semibold"
                    data-row="2">✕ ลบ</button>
                </div>
                <input type="text" name="faculty_2" class="w-full border rounded-md p-2 shadow-sm" id="faculty2"
                  placeholder="เช่น ผู้ช่วยศาสตราจารย์ สมศรี ใจดี" value="<?= h($formData[7] ?? '') ?>" />
              </div>

              <div class="mb-4 p-4 border rounded-md bg-gray-50 hidden" id="facultyRow3">
                <div class="flex items-center justify-between mb-2">
                  <label class="text-gray-700 font-semibold">ชื่อคณาจารย์ 3:</label>
                  <button type="button" class="removeFacultyBtn text-red-600 hover:text-red-800 font-semibold"
                    data-row="3">✕ ลบ</button>
                </div>
                <input type="text" name="faculty_3" class="w-full border rounded-md p-2 shadow-sm" id="faculty3"
                  placeholder="เช่น อ.ดร. สมหญิง ใจดี" value="<?= h($formData[8] ?? '') ?>" />
              </div>

              <div class="mb-4 p-4 border rounded-md bg-gray-50 hidden" id="facultyRow4">
                <div class="flex items-center justify-between mb-2">
                  <label class="text-gray-700 font-semibold">ชื่อคณาจารย์ 4:</label>
                  <button type="button" class="removeFacultyBtn text-red-600 hover:text-red-800 font-semibold"
                    data-row="4">✕ ลบ</button>
                </div>
                <input type="text" name="faculty_4" class="w-full border rounded-md p-2 shadow-sm" id="faculty4"
                  placeholder="เช่น ดร. สมบูรณ์ ใจดี" value="<?= h($formData[9] ?? '') ?>" />
              </div>

              <div id="additionalFacultyContainer">
                <!-- เพิ่มรายชื่อคณาจารย์เพิ่มเติมที่นี่ -->
              </div>
            </div>
            <button type="button" id="addFacultyBtn"
              class="mt-3 bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-semibold py-2 px-4 rounded-md transition">
              + เพิ่มชื่อคณาจารย์
            </button>
          </div>
        </div>

        <!-- ข้อ 6 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2">
            6. วัตถุประสงค์ในการเข้าเยี่ยมชม :
          </label>
          <div class="w-full">
            <textarea name="visit_objective" rows="3" class="w-full border rounded-md p-2 shadow-sm" id="visitObjective"
              placeholder="อธิบายวัตถุประสงค์ของการเข้าเยี่ยมชมสถานที่นี้..."><?= h($formData[10] ?? '') ?></textarea>
          </div>
        </div>

        <!-- ข้อ 7 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2">
            7. ประโยชน์ที่คาดว่าจะได้รับจากการเยี่ยมชม :
          </label>
          <div class="w-full">
            <textarea name="expected_benefits" rows="3" class="w-full border rounded-md p-2 shadow-sm"
              id="expectedBenefits"
              placeholder="อธิบายประโยชน์ที่คาดหวังจากการเยี่ยมชมสถานที่..."><?= h($formData[11] ?? '') ?></textarea>
          </div>
        </div>


        <!-- ===== ปุ่มส่งข้อมูล ===== -->
        <div class="relative mt-20">
          <div class="absolute right-0 bottom-0 flex gap-3">

            <button type="submit" id="submitBtn"
              class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md transition">
              ดำเนินการ
            </button>

          </div>
        </div>
      </div>
    </div>

  </form>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    // ====== ELEMENTS ======
    const memoForm = document.getElementById("memoForm");
    const mainCategory = document.getElementById("mainCategory");
    const subCategory = document.getElementById("subCategory");

    // ข้อ 1-7 (แบบฟอร์มใหม่)
    const requestPersonName = document.getElementById("requestPersonName");
    const visitPlaceName = document.getElementById("visitPlaceName");
    const visitDate = document.getElementById("visitDate");
    const faculty1 = document.getElementById("faculty1");
    const faculty2 = document.getElementById("faculty2");
    const faculty3 = document.getElementById("faculty3");
    const faculty4 = document.getElementById("faculty4");
    const visitObjective = document.getElementById("visitObjective");
    const expectedBenefits = document.getElementById("expectedBenefits");
    const addFacultyBtn = document.getElementById("addFacultyBtn");
    const additionalFacultyContainer = document.getElementById("additionalFacultyContainer");

    // ====== UI HELPERS ======
    function clearError(el) {
      if (!el) return;
      el.classList.remove("error", "shake");
      const old = el.parentElement?.querySelector(".hint");
      if (old) old.remove();
    }

    function setError(el, msg) {
      if (!el) return;
      clearError(el);
      el.classList.add("error", "shake");

      const hint = document.createElement("div");
      hint.className = "hint";
      hint.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24">
          <path d="M12 9v4m0 4h.01M10.29 3.86l-7.5 13A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.71-3.14l-7.5-13a2 2 0 0 0-3.42 0Z"
            stroke="#991b1b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>${msg}</span>
      `;
      (el.parentElement || el).appendChild(hint);
    }

    function scrollToFirstError(firstEl) {
      if (!firstEl) return;
      firstEl.scrollIntoView({
        behavior: "smooth",
        block: "center"
      });
      setTimeout(() => firstEl.focus?.(), 150);
    }

    // ====== ADD FACULTY BUTTON ======
    let facultyCount = 4;
    let nextHiddenFaculty = 2; // เริ่มจากคณาจารย์ที่ 2 (ซ่อนอยู่)

    // ====== UPDATE FACULTY COUNT ======
    function updateFacultyCount() {
      let count = 0;
      // นับจำนวนแถวที่แสดงอยู่ (ไม่ซ่อน)
      const allFacultyRows = document.querySelectorAll('div[id^="facultyRow"]');
      allFacultyRows.forEach(row => {
        if (!row.classList.contains("hidden")) {
          count++;
        }
      });

      document.getElementById("facultyCount").textContent = count;
      document.getElementById("numberOfFacultyHidden").value = count;
    }

    // ====== SETUP REMOVE BUTTONS FOR PREDEFINED FACULTY (2-4) ======
    document.querySelectorAll('button[data-row]').forEach(btn => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        const rowNum = btn.getAttribute("data-row");
        const rowElement = document.getElementById(`facultyRow${rowNum}`);
        if (rowElement) {
          rowElement.classList.add("hidden");
          // Reset input value when hiding
          const input = rowElement.querySelector('input[type="text"]');
          if (input) input.value = "";
          nextHiddenFaculty = Math.min(nextHiddenFaculty, rowNum);
          updateFacultyCount();
        }
      });
    });

    addFacultyBtn?.addEventListener("click", (e) => {
      e.preventDefault();

      // แสดงคณาจารย์ที่ซ่อนอยู่ก่อน
      if (nextHiddenFaculty <= 4) {
        const rowElement = document.getElementById(`facultyRow${nextHiddenFaculty}`);
        if (rowElement) {
          rowElement.classList.remove("hidden");
          nextHiddenFaculty++;
          updateFacultyCount();
          return;
        }
      }

      // ถ้าแสดงครบแล้ว ให้เพิ่มรายชื่อคณาจารย์เพิ่มเติม
      facultyCount++;
      const newFacultyDiv = document.createElement("div");
      newFacultyDiv.className = "mb-4 p-4 border rounded-md bg-gray-50";
      newFacultyDiv.innerHTML = `
        <div class="flex items-center justify-between mb-2">
          <label class="text-gray-700 font-semibold">ชื่อคณาจารย์ ${facultyCount}:</label>
          <button type="button" class="removeFacultyBtn text-red-600 hover:text-red-800 font-semibold">✕ ลบ</button>
        </div>
        <input type="text" name="faculty_${facultyCount}" class="w-full border rounded-md p-2 shadow-sm"
          placeholder="เช่น อ.ดร. สมชาย ใจดี" />
      `;

      // Add remove button listener
      newFacultyDiv.querySelector(".removeFacultyBtn")?.addEventListener("click", (e) => {
        e.preventDefault();
        newFacultyDiv.remove();
        updateFacultyCount();
      });

      additionalFacultyContainer.appendChild(newFacultyDiv);
      updateFacultyCount();
    });



    // ====== VALIDATION ON SUBMIT ======
    memoForm?.addEventListener("submit", (event) => {
      [
        mainCategory,
        requestPersonName, visitPlaceName, visitDate,
        faculty1, visitObjective, expectedBenefits
      ].forEach(clearError);

      let firstError = null;

      if (!mainCategory?.value?.trim()) {
        firstError = firstError || mainCategory;
        setError(mainCategory, "กรุณาเลือกหมวดหลัก");
      }

      if (!requestPersonName?.value?.trim()) {
        firstError = firstError || requestPersonName;
        setError(requestPersonName, "กรุณากรอกชื่อผู้ขออนุญาต");
      }

      if (!visitPlaceName?.value?.trim()) {
        firstError = firstError || visitPlaceName;
        setError(visitPlaceName, "กรุณากรอกชื่อสถานที่ที่จะเข้าเยี่ยมชม");
      }

      if (!visitDate?.value?.trim()) {
        firstError = firstError || visitDate;
        setError(visitDate, "กรุณาเลือกวันที่เข้าเยี่ยมชม");
      }

      if (!faculty1?.value?.trim()) {
        firstError = firstError || faculty1;
        setError(faculty1, "กรุณากรอกชื่อคณาจารย์ที่ 1");
      }

      if (!visitObjective?.value?.trim()) {
        firstError = firstError || visitObjective;
        setError(visitObjective, "กรุณากรอกวัตถุประสงค์ในการเข้าเยี่ยมชม");
      }

      if (!expectedBenefits?.value?.trim()) {
        firstError = firstError || expectedBenefits;
        setError(expectedBenefits, "กรุณากรอกประโยชน์ที่คาดว่าจะได้รับ");
      }

      if (firstError) {
        event.preventDefault();
        scrollToFirstError(firstError);
        return;
      }
    });

    // ====== FLATPICKR DATE PICKER (Thai localized) ======
    if (window.flatpickr) {
      flatpickr("#visitDate", {
        locale: "th",
        dateFormat: "d/m/Y",
        altInput: false,
        minDate: "2020-01-01",
        maxDate: new Date(),
        onChange: function(selectedDates, dateStr, instance) {
          clearError(visitDate);
        }
      });
    }

    // ====== INITIALIZE FACULTY COUNT ======
    updateFacultyCount();
  });
  </script>
</body>

</html>