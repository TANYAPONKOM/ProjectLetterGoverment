<?php 
// ต้องวางตรงนี้! บรรทัดแรกของไฟล์
$CURRENT_MAIN = "academic";     
$CURRENT_SUB  = "ประชุมวิชาการ/ศึกษาดูงาน/สัมมนาวิชาการ";           // ถ้าไม่มีหมวดย่อย ให้เว้นว่าง
?>
<!--ขอความอนุเคราะห์ดำเนินการเพิ่มตารางภาคปฏิบัติ  -->
<?php
session_start();
require_once __DIR__ . '/../../functions.php';
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

      <!-- 1. เรื่อง -->
      <div class="mb-4 flex items-center gap-3">
        <label class="lbl whitespace-nowrap">
          1. เรื่อง :
        </label>
        <input type="text" name="subject" class="flex-1 border rounded-md p-2">
      </div>


      <!-- 2. ภาคเรียน + 3. ปีการศึกษา -->
      <div class="mb-4 flex items-center gap-4">
        <label class="lbl whitespace-nowrap w-56">
          2. ภาคการศึกษา / ปีการศึกษา :
        </label>

        <select name="semester" class="border rounded-md p-2 w-32">
          <option value="">ภาค</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="summer">ฤดูร้อน</option>
        </select>

        <span>/</span>

        <input type="text" name="academic_year" class="border rounded-md p-2 w-32" placeholder="เช่น 2567">
      </div>


      <!-- 3. ชื่อ–นามสกุล + 5. ตำแหน่ง -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 items-end">
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="fullname">3. ชื่อ–นามสกุล
            :</label>
          <input type="text" name="teacher_name" class="flex-1 border rounded-md p-2"
            placeholder="เช่น ผศ.ดร.สมชาย ใจดี">
        </div>
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="position">ตำแหน่ง :</label>
          <input type="text" name="position" class="flex-1 border rounded-md p-2" id="position"
            value="อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ" />
        </div>
      </div>


      <!-- 6. จำนวนรายวิชา -->
      <div class="mb-4 flex items-center gap-3">
        <label class="lbl whitespace-nowrap w-56">
          4. จำนวนรายวิชาที่ขอเพิ่มตารางสอบ :
        </label>

        <div class="flex items-center gap-2 ml-4">
          <input type="number" name="subject_count" id="subjectCount" class="w-32 border rounded-md p-2" min="1">
          <span>รายวิชา</span>
        </div>
      </div>


      <!-- กลุ่มรายวิชา -->
      <div id="subjectList" class="space-y-4 ml-8"></div>

      <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
      <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

      <script>
      flatpickr.localize(flatpickr.l10ns.th);

      const subjectCountInput = document.getElementById("subjectCount");
      const subjectList = document.getElementById("subjectList");

      subjectCountInput.addEventListener("input", () => {
        const count = parseInt(subjectCountInput.value);
        subjectList.innerHTML = "";
        if (!count || count < 1) return;

        for (let i = 1; i <= count; i++) {
          const block = document.createElement("div");
          block.className = "border rounded-md p-4 bg-gray-50 mb-4";

          block.innerHTML = `
            <div class="font-bold mb-3">รายวิชาที่ ${i}</div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                <input type="text" name="course_code[]"
                    class="border rounded-md p-2"
                    placeholder="รหัสรายวิชา">

                <input type="text" name="course_name[]"
                    class="border rounded-md p-2"
                    placeholder="ชื่อรายวิชา">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                <input type="text" name="instructors[]"
                    class="border rounded-md p-2"
                    placeholder="ผู้สอน (เช่น อ.ก, อ.ข)">

                <input type="text" name="section[]"
                    class="border rounded-md p-2"
                    placeholder="ตอนเรียน / กลุ่มเรียน">
            </div>

            <!-- วันที่สอบ -->
            <input type="text"
                name="exam_date[]"
                class="examDate border rounded-md p-2 w-full mt-2 cursor-pointer"
                placeholder="วันที่สอบ"
                readonly>

            <!-- เวลา -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                <input type="text"
                    name="exam_time_start[]"
                    class="examTimeStart border rounded-md p-2 cursor-pointer"
                    placeholder="เวลาเริ่ม"
                    readonly>

                <input type="text"
                    name="exam_time_end[]"
                    class="examTimeEnd border rounded-md p-2 cursor-pointer"
                    placeholder="เวลาสิ้นสุด"
                    readonly>
            </div>
        `;

          subjectList.appendChild(block);
        }

        /* ===== ผูก flatpickr ===== */

        // วันที่สอบ
        document.querySelectorAll(".examDate").forEach(input => {
          flatpickr(input, {
            dateFormat: "d/m/Y",
            disableMobile: true
          });
        });

        // เวลาเริ่ม
        document.querySelectorAll(".examTimeStart").forEach(input => {
          flatpickr(input, {
            enableTime: true,
            noCalendar: true,
            time_24hr: true,
            dateFormat: "H:i",
            minuteIncrement: 5,
            disableMobile: true
          });
        });

        // เวลาสิ้นสุด
        document.querySelectorAll(".examTimeEnd").forEach(input => {
          flatpickr(input, {
            enableTime: true,
            noCalendar: true,
            time_24hr: true,
            dateFormat: "H:i",
            minuteIncrement: 5,
            disableMobile: true
          });
        });
      });
      </script>


      <script>
      flatpickr.localize(flatpickr.l10ns.th);

      const monthsTH = [
        "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
      ];

      // ===== ฟังก์ชันกลาง =====
      function formatRange(start, end) {
        const sd = start.getDate();
        const ed = end.getDate();
        const sm = monthsTH[start.getMonth()];
        const em = monthsTH[end.getMonth()];
        const sy = start.getFullYear() + 543;
        const ey = end.getFullYear() + 543;

        if (
          start.getMonth() === end.getMonth() &&
          start.getFullYear() === end.getFullYear()
        ) {
          return `${sd} - ${ed} ${em} ${ey}`;
        }
        return `${sd} ${sm} ${sy} - ${ed} ${em} ${ey}`;
      }

      // ===== ข้อ 9 =====
      const internStartPicker = flatpickr("#internStart", {
        dateFormat: "d/m/Y",
        disableMobile: true,
        onChange: updateIntern
      });
      const internEndPicker = flatpickr("#internEnd", {
        dateFormat: "d/m/Y",
        disableMobile: true,
        onChange: updateIntern
      });

      function updateIntern() {
        const s = internStartPicker.selectedDates[0];
        const e = internEndPicker.selectedDates[0];
        if (!s || !e) return;

        const text = formatRange(s, e);
        internRangeDisplay.value = text;
        internPeriod.value = text;
      }

      // ===== ข้อ 10 =====
      const travelStartPicker = flatpickr("#travelStart", {
        dateFormat: "d/m/Y",
        disableMobile: true,
        onChange: updateTravel
      });
      const travelEndPicker = flatpickr("#travelEnd", {
        dateFormat: "d/m/Y",
        disableMobile: true,
        onChange: updateTravel
      });

      function updateTravel() {
        const s = travelStartPicker.selectedDates[0];
        const e = travelEndPicker.selectedDates[0];
        if (!s || !e) return;

        const text = formatRange(s, e);
        travelRangeDisplay.value = text;
        travelPeriod.value = text;
      }
      </script>



      <script>
      const amountInput = document.getElementById("amountInput");
      const noCostCheckbox = document.getElementById("noCostCheckbox");

      function syncNoCostUI() {
        if (noCostCheckbox.checked) {
          amountInput.value = "0.00";
          amountInput.disabled = true;
          amountInput.classList.add("bg-gray-100", "text-gray-400");
        } else {
          amountInput.disabled = false;
          amountInput.classList.remove("bg-gray-100", "text-gray-400");
          amountInput.focus();
        }
      }

      // เมื่อคลิก checkbox
      noCostCheckbox.addEventListener("change", syncNoCostUI);

      // ตั้งค่าสถานะเริ่มต้น (เผื่อโหลดข้อมูลเก่า)
      syncNoCostUI();
      </script>

      <script>
      flatpickr.localize(flatpickr.l10ns.th);

      flatpickr("#docDate, #missedDate, #makeupDate", {
        dateFormat: "d/m/Y",
        disableMobile: true
      });
      </script>

      <script>
      flatpickr.localize(flatpickr.l10ns.th);

      const monthsTH = [
        "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
      ];

      const startPicker = flatpickr("#internStart", {
        dateFormat: "d/m/Y",
        disableMobile: true,
        onChange: updateInternRange
      });

      const endPicker = flatpickr("#internEnd", {
        dateFormat: "d/m/Y",
        disableMobile: true,
        onChange: updateInternRange
      });

      function updateInternRange() {
        const start = startPicker.selectedDates[0];
        const end = endPicker.selectedDates[0];

        if (!start || !end) return;

        const sd = start.getDate();
        const ed = end.getDate();
        const sm = monthsTH[start.getMonth()];
        const em = monthsTH[end.getMonth()];
        const sy = start.getFullYear() + 543;
        const ey = end.getFullYear() + 543;

        let text = "";
        if (start.getMonth() === end.getMonth() && sy === ey) {
          text = `${sd} - ${ed} ${em} ${ey}`;
        } else {
          text = `${sd} ${sm} ${sy} - ${ed} ${em} ${ey}`;
        }

        document.getElementById("internRangeDisplay").value = text;
        document.getElementById("internPeriod").value = text;
      }
      </script>



      <script>
      const studentCountInput = document.getElementById("studentCount");
      const studentList = document.getElementById("studentList");

      studentCountInput.addEventListener("input", () => {
        const count = parseInt(studentCountInput.value);
        studentList.innerHTML = "";

        if (!count || count < 1) return;

        for (let i = 1; i <= count; i++) {
          const row = document.createElement("div");
          row.className = "flex items-center gap-4";

          row.innerHTML = `
            <span class="w-10 text-right">${i}.</span>
            <input type="text" name="student_name[]"
                class="flex-1 border rounded-md p-2"
                placeholder="ชื่อ–นามสกุลนักศึกษา">
            <input type="text" name="student_id[]"
                class="w-40 border rounded-md p-2"
                placeholder="รหัสนักศึกษา">
        `;

          studentList.appendChild(row);
        }
      });
      </script>

      <script>
      const otherRadio = document.getElementById("otherTypeRadio");
      const otherInput = document.getElementById("otherTypeInput");
      const supportRadios = document.querySelectorAll('input[name="support_type"]');

      supportRadios.forEach(radio => {
        radio.addEventListener("change", () => {
          if (otherRadio.checked) {
            otherInput.disabled = false;
            otherInput.classList.remove("bg-gray-100", "text-gray-400");
            otherInput.focus();
          } else {
            otherInput.disabled = true;
            otherInput.value = "";
            otherInput.classList.add("bg-gray-100", "text-gray-400");
          }
        });
      });
      </script>


      <script>
      const purposeOtherRadio = document.getElementById("purposeOtherRadio");
      const purposeOtherInput = document.getElementById("purposeOtherInput");
      const purposeRadios = document.querySelectorAll('input[name="purpose"]');

      purposeRadios.forEach(radio => {
        radio.addEventListener("change", () => {
          if (purposeOtherRadio.checked) {
            purposeOtherInput.disabled = false;
            purposeOtherInput.classList.remove("bg-gray-100", "text-gray-400");
            purposeOtherInput.focus();
          } else {
            purposeOtherInput.disabled = true;
            purposeOtherInput.classList.add("bg-gray-100", "text-gray-400");
            purposeOtherInput.value = "";
          }
        });
      });
      </script>
      <script>
      const otherRadio = document.getElementById("otherTypeRadio");
      const otherInput = document.getElementById("otherTypeInput");
      const typeRadios = document.querySelectorAll('input[name="presentation_type"]');

      typeRadios.forEach(radio => {
        radio.addEventListener("change", () => {
          if (otherRadio.checked) {
            otherInput.disabled = false;
            otherInput.classList.remove("bg-gray-100", "text-gray-400");
            otherInput.focus();
          } else {
            otherInput.disabled = true;
            otherInput.classList.add("bg-gray-100", "text-gray-400");
            otherInput.value = "";
          }
        });
      });
      </script>


      <script>
      // ✅ ดึง element ที่เกี่ยวข้อง
      const onlineCheckbox = document.getElementById("onlineCheckbox");
      const onsiteCheckbox = document.getElementById("onsiteCheckbox");
      const placeInput = document.getElementById("placeInput");

      // ✅ ฟังก์ชันจัดการให้เลือกได้เพียง 1 ช่อง
      function selectOnly(selected) {
        if (selected === "online") {
          onlineCheckbox.checked = true;
          onsiteCheckbox.checked = false;
          placeInput.value = "";
          placeInput.disabled = true;
          placeInput.classList.add("bg-gray-100", "text-gray-400");
        } else if (selected === "onsite") {
          onsiteCheckbox.checked = true;
          onlineCheckbox.checked = false;
          placeInput.disabled = false;
          placeInput.classList.remove("bg-gray-100", "text-gray-400");
          placeInput.focus();
        } else {
          // ถ้าไม่มีการเลือกเลย
          placeInput.value = "";
          placeInput.disabled = true;
          placeInput.classList.add("bg-gray-100", "text-gray-400");
        }
      }

      // ✅ ผูก event ให้เลือกได้ช่องเดียวทันที
      onlineCheckbox.addEventListener("click", () => selectOnly("online"));
      onsiteCheckbox.addEventListener("click", () => selectOnly("onsite"));

      // ✅ ตั้งค่าเริ่มต้น
      selectOnly();
      </script>

      <!-- ปุ่ม -->
      <div class="relative mt-20">
        <div class="absolute right-0 bottom-0">
          <a href="../form_memo_academic_1.php"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md flex items-center justify-center transition">
            ดำเนินการ
          </a>
        </div>

      </div>
    </div>
  </form>

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

  const optSingle = byId("optSingle");
  const singleDate = byId("singleDate");
  const optRange = byId("optRange");
  const rangeDate = byId("rangeDate");
  const dateLabel = byId("dateLabel");

  const online_Checkbox = byId("onlineCheckbox");
  const place_Input = byId("placeInput");

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

  function validate() {
    let firstInvalid = null;
    // ล้างดอกจันทั้งหมด
    $$(".lbl").forEach((l) => setStar(l, false));

    // 1) วันที่เอกสาร
    if (!docDate.value) {
      setErr(docDate, true);
      setStar(labelFor("docDate"), true);
      firstInvalid = firstInvalid || docDate;
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
    if (!onlineCheckbox.checked && !placeInput.value.trim()) {
      setErr(placeInput, true);
      setStar(labelFor("placeInput"), true);
      firstInvalid = firstInvalid || placeInput;
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
  form.addEventListener("submit", (e) => {
    if (!validate()) {
      e.preventDefault();
    }
  });
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
    train: "form_Memo.php",
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