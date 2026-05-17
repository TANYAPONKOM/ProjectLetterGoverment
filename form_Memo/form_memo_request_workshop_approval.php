<?php
// pro_letter/form_memo/form_memo_request_workshop_approval.php

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
$subject = $formData[1] ?? '';
$recipient = $formData[2] ?? '';
$attachments = $formData[3] ?? '';
$projectName = $formData[4] ?? '';
$projectObjective = $formData[5] ?? '';
$numberOfParticipants = $formData[6] ?? '';
$projectDate = $formData[7] ?? '';
$venueLocation = $formData[8] ?? '';
$lecturerName = $formData[9] ?? '';
$faculty = $formData[10] ?? '';
$department  = $formData[11] ?? '';


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

  /* Custom input width for form fields */
  .form-input-medium {
    width: 550px;
  }

  @media (max-width: 768px) {
    .form-input-medium {
      width: 100%;
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
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2" for="subject">1. เรื่อง :</label>
          <div>
            <input type="text" name="subject" class="max-w-lg border rounded-md p-2" id="subject"
              placeholder="เช่น ขออนุญาตจัดอบรม" value="<?= h($formData[1] ?? '') ?>" />
          </div>
        </div>

        <!-- ข้อ 2 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2" for="recipient">2. เรียน :</label>
          <div>
            <input type="text" name="recipient" class="form-input-medium border rounded-md p-2" id="recipient"
              placeholder="เช่น ผู้อำนวยการสถาบัน" value="<?= h($formData[2] ?? '') ?>" />
          </div>
        </div>

        <!-- ข้อ 3 และ ข้อ 3.1 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl text-gray-800 whitespace-nowrap pt-2">3. สิ่งที่ส่งมาด้วย :</label>
          <div style="width: 550px;">
            <div id="attachmentsContainer" class="space-y-2 mb-4">
              <div class="attachment-item p-3 border rounded-md bg-gray-50 flex items-center gap-3">
                <input type="text" name="attachment_name[]" class="form-input-medium border rounded-md p-2"
                  placeholder="เช่น สำเนาใบอนุญาต"
                  value="<?= isset($attachmentsList[0]) ? h($attachmentsList[0]['name'] ?? '') : h($formData[3] ?? '') ?>" />
                <input type="number" name="attachment_quantity[]" class="w-20 border rounded-md p-2" placeholder="จำนวน"
                  value="1" min="1" />
                <button type="button"
                  class="removeAttachmentBtn hidden text-red-600 hover:text-red-800 font-semibold px-2 py-1">
                  ✕
                </button>
              </div>
            </div>
            <button type="button" id="addAttachmentBtn"
              class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-semibold py-2 px-4 rounded-md transition">
              + เพิ่มสิ่งที่ส่งมาด้วย
            </button>
          </div>
        </div>

        <!-- ข้อ 4 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2" for="projectName">4. ชื่อโครงการ :</label>
          <div class="w-full">
            <input type="text" name="project_name" class="w-full border rounded-md p-2" id="projectName"
              placeholder="เช่น โครงการอบรมทักษะดิจิทัลสำหรับข้าราชการ" value="<?= h($formData[4] ?? '') ?>" />
          </div>
        </div>

        <!-- ข้อ 5 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2" for="projectObjective">5.
            วัตถุประสงค์ของโครงการ :</label>
          <div class="w-full">
            <textarea name="project_objective" class="w-full border rounded-md p-2 shadow-sm" id="projectObjective"
              rows="3" placeholder="อธิบายวัตถุประสงค์ของโครงการ..."><?= h($formData[5] ?? '') ?></textarea>
          </div>
        </div>

        <!-- ข้อ 6 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2" for="numberOfParticipants">6.
            จำนวนผู้เข้าร่วมโครงการ :</label>
          <div class="max-w-xs">
            <input type="number" name="number_of_participants" class="w-full border rounded-md p-2"
              id="numberOfParticipants" placeholder="เช่น 50" value="<?= h($formData[6] ?? '') ?>" min="1" />
          </div>
        </div>

        <!-- ข้อ 7 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2" for="projectDate">
            7. วันที่จัดโครงการ :
          </label>
          <div class="max-w-xs">
            <div class="relative">
              <input type="text" id="projectDate" name="project_date"
                class="w-full border rounded-md p-2 shadow-sm pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly
                value="<?= h($formData[7] ?? '') ?>" />
              <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
        </div>

        <!-- ข้อ 8 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2" for="venueLocation">8. สถานที่จัดโครงการ
            :</label>
          <div class="w-full">
            <input type="text" name="venue_location" class="w-full border rounded-md p-2" id="venueLocation"
              placeholder="เช่น หอประชุมมหาวิทยาลัย" value="<?= h($formData[8] ?? '') ?>" />
          </div>
        </div>

        <!-- ข้อ 9 -->
        <div class="mb-6 flex items-start gap-4">
          <label class="lbl asterisk text-gray-800 whitespace-nowrap pt-2" for="lecturerName">9. ชื่อวิทยากร :</label>
          <div class="max-w-lg">
            <input type="text" name="lecturer_name" class="w-full border rounded-md p-2" id="lecturerName"
              placeholder="เช่น ผู้ช่วยศาสตราจารย์ สมชาย ใจดี" value="<?= h($formData[9] ?? '') ?>" />
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

    // ข้อ 1-9 (แบบฟอร์มโครงการ/อบรม)
    const subject = document.getElementById("subject");
    const recipient = document.getElementById("recipient");
    const projectName = document.getElementById("projectName");
    const projectObjective = document.getElementById("projectObjective");
    const numberOfParticipants = document.getElementById("numberOfParticipants");
    const projectDate = document.getElementById("projectDate");
    const venueLocation = document.getElementById("venueLocation");
    const lecturerName = document.getElementById("lecturerName");

    // ====== ATTACHMENTS MANAGEMENT ======
    const attachmentsContainer = document.getElementById("attachmentsContainer");
    const addAttachmentBtn = document.getElementById("addAttachmentBtn");

    function updateAttachmentButtons() {
      const items = document.querySelectorAll(".attachment-item");
      items.forEach((item, index) => {
        const removeBtn = item.querySelector(".removeAttachmentBtn");
        if (removeBtn) {
          if (items.length > 1) {
            removeBtn.classList.remove("hidden");
          } else {
            removeBtn.classList.add("hidden");
          }
        }
      });
    }

    function addAttachmentRow(value = "") {
      const newItem = document.createElement("div");
      newItem.className = "attachment-item p-3 border rounded-md bg-gray-50 flex items-center gap-3";
      newItem.innerHTML = `
        <input type="text" name="attachment_name[]" class="form-input-medium border rounded-md p-2"
          placeholder="เช่น สำเนาใบอนุญาต" value="${value}" />
        <input type="number" name="attachment_quantity[]" class="w-20 border rounded-md p-2"
          placeholder="จำนวน" value="1" min="1" />
        <button type="button" class="removeAttachmentBtn text-red-600 hover:text-red-800 font-semibold px-2 py-1">
          ✕
        </button>
      `;

      const removeBtn = newItem.querySelector(".removeAttachmentBtn");
      removeBtn.addEventListener("click", (e) => {
        e.preventDefault();
        newItem.remove();
        updateAttachmentButtons();
      });

      attachmentsContainer.appendChild(newItem);
      updateAttachmentButtons();
    }

    addAttachmentBtn?.addEventListener("click", (e) => {
      e.preventDefault();
      addAttachmentRow();
    });

    // Initialize remove buttons for existing items
    document.querySelectorAll(".removeAttachmentBtn").forEach(btn => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        btn.closest(".attachment-item").remove();
        updateAttachmentButtons();
      });
    });

    updateAttachmentButtons();

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

    // ====== VALIDATION ON SUBMIT ======
    memoForm?.addEventListener("submit", (event) => {
      [
        mainCategory,
        subject, recipient, projectName, projectObjective,
        numberOfParticipants, projectDate, venueLocation, lecturerName
      ].forEach(clearError);

      let firstError = null;

      if (!mainCategory?.value?.trim()) {
        firstError = firstError || mainCategory;
        setError(mainCategory, "กรุณาเลือกหมวดหลัก");
      }

      if (!subject?.value?.trim()) {
        firstError = firstError || subject;
        setError(subject, "กรุณากรอกเรื่อง");
      }

      if (!recipient?.value?.trim()) {
        firstError = firstError || recipient;
        setError(recipient, "กรุณากรอกผู้รับ");
      }

      if (!projectName?.value?.trim()) {
        firstError = firstError || projectName;
        setError(projectName, "กรุณากรอกชื่อโครงการ");
      }

      if (!projectObjective?.value?.trim()) {
        firstError = firstError || projectObjective;
        setError(projectObjective, "กรุณากรอกวัตถุประสงค์ของโครงการ");
      }

      if (!numberOfParticipants?.value?.trim()) {
        firstError = firstError || numberOfParticipants;
        setError(numberOfParticipants, "กรุณากรอกจำนวนผู้เข้าร่วม");
      }

      if (!projectDate?.value?.trim()) {
        firstError = firstError || projectDate;
        setError(projectDate, "กรุณาเลือกวันที่จัดโครงการ");
      }

      if (!venueLocation?.value?.trim()) {
        firstError = firstError || venueLocation;
        setError(venueLocation, "กรุณากรอกสถานที่จัดโครงการ");
      }

      if (!lecturerName?.value?.trim()) {
        firstError = firstError || lecturerName;
        setError(lecturerName, "กรุณากรอกชื่อวิทยากร");
      }

      if (firstError) {
        event.preventDefault();
        scrollToFirstError(firstError);
        return;
      }
    });

    // ====== FLATPICKR DATE PICKER (Thai localized) ======
    if (window.flatpickr) {
      flatpickr("#projectDate", {
        locale: "th",
        dateFormat: "d/m/Y",
        altInput: false,
        minDate: new Date(),
        onChange: function(selectedDates, dateStr, instance) {
          clearError(projectDate);
        }
      });
    }
  });
  </script>
</body>

</html>