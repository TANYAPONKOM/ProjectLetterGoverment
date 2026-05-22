<?php
// pro_letter/documents/form_Calcu.php

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
$docDate     = $formData[1]  ?? '';
$ownerName   = $formData[2]  ?? '';
$position    = $formData[3]  ?? '';
$joinType    = $formData[4]  ?? '';   // purpose (ข้อความไทย)
$courseName  = $formData[5]  ?? '';
$joinDates   = $formData[6]  ?? '';
$location    = $formData[7]  ?? '';
$amountStr   = $formData[8]  ?? '';
$vehicle     = $formData[9]  ?? '';
$faculty     = $formData[10] ?? '';
$department  = $formData[11] ?? '';


// ===============================
// EXPENSE JSON (field_id = 20)
// ===============================
$expenseJson = $formData[20] ?? ''; // <<<<< ใช้ field_id 20 เก็บ JSON

$expenseData = [];
if ($expenseJson) {
  $tmp = json_decode($expenseJson, true);
  if (is_array($tmp)) $expenseData = $tmp;
}

// ค่าเริ่มต้น (กันหน้าแตกตอน create)
$expenseData = $expenseData ?: [
  "compensation" => [], // หมวด 1
  "allowance" => [      // หมวด 2
    "registration" => ["enabled"=>false, "price"=>0, "people"=>1],
    "lodging"      => ["enabled"=>false, "date_text"=>"", "unit_price"=>0, "nights"=>1, "people"=>1],
    "perdiem"      => ["enabled"=>false, "unit_price"=>0, "meals"=>1, "people"=>1],
    "transport"    => ["enabled"=>false, "items"=>[]],
    "others"       => []
  ],
  "materials" => []     // หมวด 3
];


// ===============================
// FIX BUG ❌ ข้อ 5 : วันที่ (radio)
// ===============================
$isRangeDate = preg_match('/\d+\s*-\s*\d+/', $joinDates);


// ===============================
// FIX BUG ❌ ข้อ 6 : ออนไลน์ / ออนไซต์
// ===============================
$isOnline = ($location === 'เข้าร่วมรูปแบบออนไลน์');

// ===============================
// PURPOSE (map เป็น code สำหรับ radio)
// ===============================
$purpose = 'other';
if ($joinType === 'นำเสนอผลงานทางวิชาการ') {
    $purpose = 'academic';
} elseif ($joinType === 'เข้าร่วมประชุมวิชาการในงาน') {
    $purpose = 'meeting';
} elseif ($joinType === 'เข้ารับการฝึกอบรมหลักสูตร') {
    $purpose = 'training';
}

// กรณีอื่น ๆ
$purposeOther = ($purpose === 'other') ? $joinType : '';


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

 <form method="post" action="/ProjectLetterGoverment/ProjectLetterGoverment/documents/EXP_Presentation.php" id="memoForm">
    <?php
    $skipPassFields = ['expense_json', 'amount', 'total_amount'];

    foreach ($_POST as $key => $value) {
        if (in_array($key, $skipPassFields, true)) continue;
        if (is_array($value)) continue;
        echo '<input type="hidden" name="' . h($key) . '" value="' . h($value) . '">' . "\n";
    }
    ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="document_id" value="<?= (int)$docId ?>">
    <input type="hidden" name="mode" value="update">
    <?php else: ?>
    <input type="hidden" name="mode" value="create">
    <?php endif; ?>
    <input type="hidden" name="expense_json" id="expenseJsonInput" value="<?= h($expenseJson) ?>">
    <input type="hidden" name="amount" id="amountHidden" value="<?= h($formData[8] ?? '0.00') ?>">
    <input type="hidden" name="no_cost" value="0">


    <!-- กล่องเนื้อหา -->
    <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">


      <h1 class="text-center font-bold mb-6 text-black">แบบฟอร์มประมาณการค่าใช้จ่าย</h1>

      <!-- ====== SUMMARY ====== -->
      <div class="mb-8 p-6 rounded-[25px] border-2" style="background-color:#e3f9f8;border-color:#11c2b9;">
        <div class="flex items-center justify-between gap-4 flex-wrap">
          <div class="text-gray-800 font-bold">สรุปยอดรวมรายจ่าย</div>
          <div class="flex items-center gap-2">
            <input id="totalAmount" type="text" class="border rounded-md p-2 w-40 bg-gray-50 text-gray-700" value="0.00"
              readonly>
            <span class="text-gray-800 font-bold">บาท</span>
          </div>
        </div>
        <div class="text-gray-600 text-sm mt-2">* ระบบคำนวณให้อัตโนมัติ (บันทึกยอดรวมลงเอกสารให้)</div>
      </div>

      <!-- =========================
  1) ค่าตอบแทน
========================= -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div class="font-bold text-gray-800">1. ค่าตอบแทน</div>
          <button type="button" id="addCompBtn"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold px-4 py-2 rounded-md transition">
            + เพิ่มรายการ
          </button>
        </div>

        <div id="compList" class="mt-3 space-y-3"></div>

        <!-- placeholder ถ้าไม่มี -->
        <div id="compEmpty" class="mt-3 text-gray-500">
          1.1 <span class="italic">ไม่มีรายการ</span> — 0.00 บาท
        </div>
      </div>

      <!-- =========================
  2) ค่าใช้สอย
========================= -->
      <div class="mb-8">
        <div class="font-bold text-gray-800 mb-3">2. ค่าใช้สอย</div>

        <!-- 2.1 ค่าลงทะเบียน -->
        <div class="p-4 rounded-[20px] border-2 mb-4" style="border-color:#11c2b9;background:#f7fffe;">
          <div class="flex items-center justify-between">
            <label class="font-bold text-gray-800 flex items-center gap-2">
              <input type="checkbox" id="regEnabled" class="accent-black">
              2.1 ค่าลงทะเบียน
            </label>
            <div class="text-gray-800 font-bold">
              <span id="regTotal">0.00</span> บาท
            </div>
          </div>

          <div id="regForm" class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="text-gray-700">ราคา (บาท)</label>
              <input type="number" id="regPrice" class="w-full border rounded-md p-2" min="0" step="0.01" value="0">
            </div>
            <div>
              <label class="text-gray-700">จำนวนคน</label>
              <input type="number" id="regPeople" class="w-full border rounded-md p-2" min="1" step="1" value="1">
            </div>
            <div class="text-gray-600 flex items-end">
              <span>(ราคา × คน)</span>
            </div>
          </div>
        </div>

<!-- 2.2 ค่าที่พัก -->
<div class="p-4 rounded-[20px] border-2 mb-4" style="border-color:#11c2b9;background:#f7fffe;">
  <div class="flex items-center justify-between">
    <label class="font-bold text-gray-800 flex items-center gap-2">
      <input type="checkbox" id="lodEnabled" class="accent-black">
      2.2 ค่าที่พักค้างคืน
    </label>

    <div class="text-gray-800 font-bold">
      <span id="lodTotal">0.00</span> บาท
    </div>
  </div>

  <div id="lodForm" class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-3">
    <!-- ช่วงวันที่เข้าพัก -->
    <div class="md:col-span-4">
      <label class="text-gray-700 block mb-2">ช่วงวันที่เข้าพัก</label>

      <div class="space-y-3 text-gray-800">
        <!-- วันเดียว -->
        <div id="lodSingleWrap" class="flex items-center gap-2">
          <input type="radio" name="lod_date_option" value="single" id="lodOptSingle" class="accent-[#11C2B9]" checked>
          <label for="lodOptSingle" class="whitespace-nowrap">วันเดียว :</label>

          <input type="text" id="lodSingleDate"
            class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer"
            placeholder="เลือกวันที่"
            readonly>
        </div>

        <!-- หลายวัน -->
        <div id="lodRangeWrap" class="flex flex-wrap items-center gap-2">
          <input type="radio" name="lod_date_option" value="range" id="lodOptRange" class="accent-[#11C2B9]">
          <label for="lodOptRange" class="whitespace-nowrap">หลายวัน :</label>

          <input type="text" id="lodStartDate"
            class="border rounded-md p-2 shadow-sm w-40 cursor-pointer"
            placeholder="เริ่มต้น"
            readonly>

          <span>ถึง</span>

          <input type="text" id="lodEndDate"
            class="border rounded-md p-2 shadow-sm w-40 cursor-pointer"
            placeholder="สิ้นสุด"
            readonly>
        </div>

        <input type="hidden" id="lodDateText">
      </div>
    </div>

    <!-- ราคา/คืน จำนวนคืน จำนวนคน -->
    <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-3 mt-2">
      <div>
        <label class="text-gray-700">ราคา/คืน</label>
        <input type="text" id="lodUnit"
          class="w-full border rounded-md p-2 bg-gray-100 text-gray-500"
          value="0"
          readonly>
      </div>

      <div>
        <label class="text-gray-700">จำนวนคืน</label>
        <input type="number" id="lodNights"
          class="w-full border rounded-md p-2"
          min="0"
          step="1"
          value="0">
      </div>

      <div>
        <label class="text-gray-700">จำนวนคน</label>
        <input type="number" id="lodPeople"
          class="w-full border rounded-md p-2"
          min="0"
          step="1"
          value="0">
      </div>
    </div>

    <div class="text-gray-600 md:col-span-4">
      <span>(ราคา/คืน × คืน × คน)</span>
    </div>
  </div>
</div>

        <!-- 2.3 ค่าเบี้ยเลี้ยง -->
        <div class="p-4 rounded-[20px] border-2 mb-4" style="border-color:#11c2b9;background:#f7fffe;">
          <div class="flex items-center justify-between">
            <label class="font-bold text-gray-800 flex items-center gap-2">
              <input type="checkbox" id="perEnabled" class="accent-black">
              2.3 ค่าเบี้ยเลี้ยง
            </label>
            <div class="text-gray-800 font-bold">
              <span id="perTotal">0.00</span> บาท
            </div>
          </div>

          <div id="perForm" class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="text-gray-700">ราคา/มื้อ</label>
           <input type="number" id="perUnit" class="w-full border rounded-md p-2" min="0" step="0.01" value="0">
            </div>
            <div>
              <label class="text-gray-700">จำนวนมื้อ</label>
            <input type="number" id="perMeals" class="w-full border rounded-md p-2" min="0" step="1" value="0">
            </div>
            <div>
              <label class="text-gray-700">จำนวนคน</label>
              <input type="number" id="perPeople" class="w-full border rounded-md p-2" min="0" step="1" value="0">
            </div>
            <div class="text-gray-600 md:col-span-3">
              <span>(ราคา/มื้อ × มื้อ × คน)</span>
            </div>
          </div>
        </div>

<!-- 2.4 ค่าพาหนะ -->
<div class="p-4 rounded-[20px] border-2 mb-4" style="border-color:#11c2b9;background:#f7fffe;">
  <div class="flex items-center justify-between">
    <label class="font-bold text-gray-800 flex items-center gap-2">
      <input type="checkbox" id="trEnabled" class="accent-black">
      2.4 ค่าพาหนะ
    </label>

    <div class="text-gray-800 font-bold">
      <span id="trTotal">0.00</span> บาท
    </div>
  </div>

  <div class="mt-3">
    <button type="button" id="addTrItemBtn"
      class="bg-white border-2 border-[#11C2B9] text-[#0f766e] font-bold px-4 py-2 rounded-md hover:bg-gray-50 transition">
      + เพิ่มรายการพาหนะ
    </button>
  </div>

  <div id="trList" class="mt-3 space-y-4"></div>

  <div id="trEmpty" class="mt-3 text-gray-500">
    - ไม่มีรายการพาหนะ — 0.00 บาท
  </div>
</div>

      <!-- =========================
              3) ค่าวัสดุ
          ========================= -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div class="font-bold text-gray-800">3. ค่าวัสดุ</div>
          <button type="button" id="addMatBtn"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold px-4 py-2 rounded-md transition">
            + เพิ่มรายการ
          </button>
        </div>

        <div id="matList" class="mt-3 space-y-3"></div>

        <div id="matEmpty" class="mt-3 text-gray-500">
          3.1 <span class="italic">ไม่มีรายการ</span> — 0.00 บาท
        </div>
      </div>

      <!-- หมายเหตุ -->
      <div class="mt-6 text-gray-800">
        <span class="font-bold">หมายเหตุ</span> ขอถัวจ่ายทุกรายการ
      </div>







      <!-- ปุ่ม -->
      <div class="relative mt-20">
        <div class="absolute right-0 bottom-0">
          <button type="button" id="submitBtn"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md transition">
            ดำเนินการ
          </button>
        </div>
      </div>
    </div>
  </form>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    // ====== LOAD FROM PHP (expenseJsonInput) ======
    const expenseJsonInput = document.getElementById("expenseJsonInput");
    const amountHidden = document.getElementById("amountHidden");
    const totalAmount = document.getElementById("totalAmount");

    let state = {};
    try {
      state = expenseJsonInput?.value ? JSON.parse(expenseJsonInput.value) : {};
    } catch (e) {
      state = {};
    }

    // defaults
    state = Object.keys(state).length ? state : {
      compensation: [],
      allowance: {
        registration: {
          enabled: false,
          price: 0,
          people: 1
        },
        lodging: {
          enabled: false,
          date_text: "",
          unit_price: 0,
          nights: 1,
          people: 1
        },
        perdiem: {
          enabled: false,
          unit_price: 0,
          meals: 0,
          people: 0
        },
        transport: {
          enabled: false,
          items: []
        },
        others: []
      },
      materials: []
    };

    // ====== helpers ======
// ====== helpers ======
const fmt = (n) => (Number(n || 0)).toLocaleString("en-US", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2
});

const num = (v) => {
  const x = Number(String(v ?? "").replace(/,/g, ""));
  return Number.isFinite(x) ? x : 0;
};

const fmtNoDecimal = (n) => {
  return Number(n || 0).toLocaleString("en-US", {
    maximumFractionDigits: 0
  });
};

const TH_MONTH_SHORT = [
      "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
      "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
    ];

    function thaiShortDate(dateObj) {
      if (!dateObj) return "";
      const d = dateObj.getDate();
      const m = TH_MONTH_SHORT[dateObj.getMonth()];
      const y = String(dateObj.getFullYear() + 543).slice(-2);
      return `${d} ${m} ${y}`;
    }

    function parseThaiShortDate(text) {
  const m = String(text || "").trim().match(/^(\d{1,2})\s+([ก-ฮ.]+)\s+(\d{2})$/);
  if (!m) return null;

  const day = parseInt(m[1], 10);
  const monthIndex = TH_MONTH_SHORT.indexOf(m[2]);
  const buddhistYearShort = parseInt(m[3], 10);

  if (monthIndex === -1) return null;

  const christianYear = 2500 + buddhistYearShort - 543;
  return new Date(christianYear, monthIndex, day);
}

function diffNights(startText, endText) {
  const start = parseThaiShortDate(startText);
  const end = parseThaiShortDate(endText);

  if (!start || !end) return 0;

  const oneDay = 1000 * 60 * 60 * 24;
  const diff = Math.round((end - start) / oneDay);

  return diff > 0 ? diff : 0;
}

function syncLodgingAutoCalc(autoSetPeople = false) {
  const isSingle = lodOptSingle.checked;

  let hasDate = false;
  let nights = 0;

  if (isSingle) {
    hasDate = !!lodSingleDate.value;
    nights = hasDate ? 1 : 0;
  } else {
    hasDate = !!lodStartDate.value && !!lodEndDate.value;
    nights = hasDate ? diffNights(lodStartDate.value, lodEndDate.value) : 0;
  }

  if (!hasDate || nights <= 0) {
    lodUnit.value = "0";
    lodNights.value = "0";
    lodPeople.value = "0";

    state.allowance.lodging.unit_price = 0;
    state.allowance.lodging.nights = 0;
    state.allowance.lodging.people = 0;

    calc();
    return;
  }

  lodNights.value = nights;

  let people = num(lodPeople.value);

  if (autoSetPeople && people <= 0) {
    people = 1;
    lodPeople.value = "1";
  }

  let unitPrice = 0;

  if (people > 0) {
    unitPrice = people === 1 ? 1500 : 1000;

    lodEnabled.checked = true;
    state.allowance.lodging.enabled = true;
  }

  lodUnit.value = fmtNoDecimal(unitPrice);

  state.allowance.lodging.unit_price = unitPrice;
  state.allowance.lodging.nights = nights;
  state.allowance.lodging.people = people;

  calc();
}


function updateLodDateText() {
  if (!lodDateText) return;

  if (lodOptSingle?.checked) {
    lodDateText.value = lodSingleDate?.value || "";
  } else {
    const s = lodStartDate?.value || "";
    const e = lodEndDate?.value || "";
    lodDateText.value = (s && e) ? `${s} – ${e}` : "";
  }

  state.allowance.lodging.date_text = lodDateText.value;

  // เลือกวันที่แล้วให้ระบบเริ่มจำนวนคนเป็น 1 อัตโนมัติ
  syncLodgingAutoCalc(true);
}
   

    function calc() {
      // 1 compensation

      const compSum = state.compensation.reduce((s, it) => s + num(it.amount), 0);
      // 2 allowance
      let reg = 0,
        lod = 0,
        per = 0,
        tr = 0;

      if (state.allowance.registration.enabled) {
        reg = num(state.allowance.registration.price) * num(state.allowance.registration.people);
      }
      if (state.allowance.lodging.enabled) {
        lod = num(state.allowance.lodging.unit_price) * num(state.allowance.lodging.nights) * num(state.allowance
          .lodging.people);
      }
      if (state.allowance.perdiem.enabled) {
        per = num(state.allowance.perdiem.unit_price) * num(state.allowance.perdiem.meals) * num(state.allowance
          .perdiem.people);
      }
      if (state.allowance.transport.enabled) {
        tr = (state.allowance.transport.items || []).reduce((s, it) => {
          if (it.type === "fuel") {
            return s + (num(it.distance) * num(it.rate || 4) * num(it.trips));
      }

    if (it.type === "flight") {
      return s + (num(it.ticket_price) * num(it.people) * num(it.trips));
    }

    return s + (num(it.unit_price) * num(it.trips) * num(it.people));
  }, 0);
}

      const allowSum = reg + lod + per + tr;

      // 3 materials
      const matSum = state.materials.reduce((s, it) => s + num(it.amount), 0);

      const total = compSum + allowSum + matSum;

      // update UI totals
      document.getElementById("regTotal").innerText = fmt(reg);
      document.getElementById("lodTotal").innerText = fmt(lod);
      document.getElementById("perTotal").innerText = fmt(per);
      document.getElementById("trTotal").innerText = fmt(tr);

      totalAmount.value = fmt(total);
      amountHidden.value = String(total.toFixed(2));
    }

    // ====== render compensation list ======
    const compList = document.getElementById("compList");
    const compEmpty = document.getElementById("compEmpty");
    document.getElementById("addCompBtn").addEventListener("click", () => {
      state.compensation.push({
        desc: "",
        amount: 0
      });
      renderComp();
      calc();
    });

    function renderComp() {
      compList.innerHTML = "";
      compEmpty.style.display = state.compensation.length ? "none" : "block";

      state.compensation.forEach((it, idx) => {
        const row = document.createElement("div");
        row.className = "grid grid-cols-1 md:grid-cols-12 gap-3 items-end";

        row.innerHTML = `
        <div class="md:col-span-8">
          <label class="text-gray-700">รายการ (1.${idx+1})</label>
          <input type="text" class="w-full border rounded-md p-2" value="${it.desc ?? ""}" data-k="desc" data-i="${idx}">
        </div>
        <div class="md:col-span-3">
          <label class="text-gray-700">จำนวนเงิน (บาท)</label>
          <input type="number" class="w-full border rounded-md p-2" min="0" step="0.01" value="${num(it.amount)}" data-k="amount" data-i="${idx}">
        </div>
        <div class="md:col-span-1">
          <button type="button" class="w-full bg-red-50 text-red-600 border border-red-200 rounded-md px-3 py-2 font-bold hover:bg-red-100"
            data-del="${idx}">ลบ</button>
        </div>
      `;

        row.addEventListener("input", (e) => {
          const t = e.target;
          if (!t.dataset || t.dataset.i === undefined) return;
          const i = Number(t.dataset.i);
          const k = t.dataset.k;
          if (k === "amount") state.compensation[i][k] = num(t.value);
          else state.compensation[i][k] = t.value;
          calc();
        });

        row.querySelector(`[data-del="${idx}"]`).addEventListener("click", () => {
          state.compensation.splice(idx, 1);
          renderComp();
          calc();
        });

        compList.appendChild(row);
      });
    }

    // ====== materials ======
    const matList = document.getElementById("matList");
    const matEmpty = document.getElementById("matEmpty");
    document.getElementById("addMatBtn").addEventListener("click", () => {
      state.materials.push({
        desc: "",
        amount: 0
      });
      renderMat();
      calc();
    });

    function renderMat() {
      matList.innerHTML = "";
      matEmpty.style.display = state.materials.length ? "none" : "block";

      state.materials.forEach((it, idx) => {
        const row = document.createElement("div");
        row.className = "grid grid-cols-1 md:grid-cols-12 gap-3 items-end";
        row.innerHTML = `
        <div class="md:col-span-8">
          <label class="text-gray-700">รายการ (3.${idx+1})</label>
          <input type="text" class="w-full border rounded-md p-2" value="${it.desc ?? ""}" data-k="desc" data-i="${idx}">
        </div>
        <div class="md:col-span-3">
          <label class="text-gray-700">จำนวนเงิน (บาท)</label>
          <input type="number" class="w-full border rounded-md p-2" min="0" step="0.01" value="${num(it.amount)}" data-k="amount" data-i="${idx}">
        </div>
        <div class="md:col-span-1">
          <button type="button" class="w-full bg-red-50 text-red-600 border border-red-200 rounded-md px-3 py-2 font-bold hover:bg-red-100"
            data-del="${idx}">ลบ</button>
        </div>
      `;
        row.addEventListener("input", (e) => {
          const t = e.target;
          if (!t.dataset || t.dataset.i === undefined) return;
          const i = Number(t.dataset.i);
          const k = t.dataset.k;
          if (k === "amount") state.materials[i][k] = num(t.value);
          else state.materials[i][k] = t.value;
          calc();
        });
        row.querySelector(`[data-del="${idx}"]`).addEventListener("click", () => {
          state.materials.splice(idx, 1);
          renderMat();
          calc();
        });
        matList.appendChild(row);
      });
    }

    // ====== allowance bindings ======
    const regEnabled = document.getElementById("regEnabled");
    const regPrice = document.getElementById("regPrice");
    const regPeople = document.getElementById("regPeople");
    const regForm = document.getElementById("regForm");

    const lodEnabled = document.getElementById("lodEnabled");
    const lodDateText = document.getElementById("lodDateText");
    const lodOptSingle = document.getElementById("lodOptSingle");
    const lodOptRange = document.getElementById("lodOptRange");
    const lodSingleDate = document.getElementById("lodSingleDate");
    const lodStartDate = document.getElementById("lodStartDate");
    const lodEndDate = document.getElementById("lodEndDate");
    const lodUnit = document.getElementById("lodUnit");
    const lodNights = document.getElementById("lodNights");
    const lodPeople = document.getElementById("lodPeople");
    const lodForm = document.getElementById("lodForm");

    const perEnabled = document.getElementById("perEnabled");
    const perUnit = document.getElementById("perUnit");
    const perMeals = document.getElementById("perMeals");
    const perPeople = document.getElementById("perPeople");
    const perForm = document.getElementById("perForm");

    const trEnabled = document.getElementById("trEnabled");
    const trList = document.getElementById("trList");
    const trEmpty = document.getElementById("trEmpty");
    const addTrItemBtn = document.getElementById("addTrItemBtn");

    const lodSingleWrap = document.getElementById("lodSingleWrap");
    const lodRangeWrap = document.getElementById("lodRangeWrap");
    
    
let lodStartDateObj = null;
let lodEndDateObj = null;
let lodSingleDateObj = null;

const lodSinglePicker = flatpickr(lodSingleDate, {
  locale: "th",
  dateFormat: "Y-m-d",
  disableMobile: true,
  allowInput: false,
  clickOpens: true,
  onChange: function(selectedDates) {
    lodSingleDateObj = selectedDates[0] || null;
    lodSingleDate.value = lodSingleDateObj ? thaiShortDate(lodSingleDateObj) : "";
    updateLodDateText();
  }
});

const lodStartPicker = flatpickr(lodStartDate, {
  locale: "th",
  dateFormat: "Y-m-d",
  disableMobile: true,
  allowInput: false,
  clickOpens: true,
  onChange: function(selectedDates) {
    lodStartDateObj = selectedDates[0] || null;
    lodStartDate.value = lodStartDateObj ? thaiShortDate(lodStartDateObj) : "";
    updateLodDateText();
  }
});

const lodEndPicker = flatpickr(lodEndDate, {
  locale: "th",
  dateFormat: "Y-m-d",
  disableMobile: true,
  allowInput: false,
  clickOpens: true,
  onChange: function(selectedDates) {
    lodEndDateObj = selectedDates[0] || null;
    lodEndDate.value = lodEndDateObj ? thaiShortDate(lodEndDateObj) : "";
    updateLodDateText();
  }
});

    function setLodGroupDisabled(wrapper, inputs, disabled) {
      if (!wrapper) return;

      wrapper.classList.toggle("opacity-50", disabled);
      wrapper.classList.toggle("text-gray-400", disabled);

      inputs.forEach(input => {
        if (!input) return;

        input.disabled = disabled;
        input.classList.toggle("bg-gray-100", disabled);
        input.classList.toggle("text-gray-400", disabled);
        input.classList.toggle("cursor-not-allowed", disabled);
        input.classList.toggle("cursor-pointer", !disabled);
      });
    }

  function applyLodDateModeStyle() {
  const isSingle = lodOptSingle.checked;

  setLodGroupDisabled(lodSingleWrap, [lodSingleDate], !isSingle);
  setLodGroupDisabled(lodRangeWrap, [lodStartDate, lodEndDate], isSingle);
}

function syncLodDateModeUI() {
  const isSingle = lodOptSingle.checked;

  applyLodDateModeStyle();

  if (isSingle) {
    lodStartDate.value = "";
    lodEndDate.value = "";
    lodStartDateObj = null;
    lodEndDateObj = null;
  } else {
    lodSingleDate.value = "";
    lodSingleDateObj = null;
  }

  lodUnit.value = "0";
  lodNights.value = "0";
  lodPeople.value = "0";

  state.allowance.lodging.unit_price = 0;
  state.allowance.lodging.nights = 0;
  state.allowance.lodging.people = 0;

  updateLodDateText();
  calc();
}

    [lodOptSingle, lodOptRange].forEach(el => {
      el.addEventListener("change", syncLodDateModeUI);
    });

function syncPerdiemDefault() {
  if (perEnabled.checked) {
    // เมื่อติ๊ก 2.3 ค่อยเติมค่าอัตโนมัติ
    perUnit.value = 120;
    perMeals.value = 3;

    if (num(perPeople.value) <= 0) {
      perPeople.value = 1;
    }

    state.allowance.perdiem.enabled = true;
    state.allowance.perdiem.unit_price = 120;
    state.allowance.perdiem.meals = 3;
    state.allowance.perdiem.people = num(perPeople.value);
  } else {
    // ยังไม่ติ๊ก / เอาติ๊กออก ให้โชว์ 0
    perUnit.value = 0;
    perMeals.value = 0;
    perPeople.value = 0;

    state.allowance.perdiem.enabled = false;
    state.allowance.perdiem.unit_price = 0;
    state.allowance.perdiem.meals = 0;
    state.allowance.perdiem.people = 0;
  }

  calc();
}

    function syncAllowUI() {
  // ไม่ทำให้หัวข้อ 2.1, 2.2, 2.3 เป็นสีเทาก่อนเลือก checkbox แล้ว
  // checkbox ใช้คุมแค่ว่าจะเอายอดไปคำนวณหรือไม่

    regPrice.disabled = false;
    regPeople.disabled = false;

    lodDateText.disabled = false;
    lodOptSingle.disabled = false;
    lodOptRange.disabled = false;
    lodUnit.disabled = false;
    lodNights.disabled = false;
    lodPeople.disabled = false;

    perUnit.disabled = false;
    perMeals.disabled = false;
    perPeople.disabled = false;

  // ให้สีเทาเฉพาะช่องวันที่ของ 2.2 ตามที่เลือก วันเดียว / หลายวัน
  applyLodDateModeStyle();

  // ส่วนค่าพาหนะ จะให้ปุ่มเพิ่มรายการเปิดตาม checkbox เหมือนเดิม
  trList.style.opacity = trEnabled.checked ? "1" : "0.5";
  addTrItemBtn.disabled = !trEnabled.checked;
  trEmpty.style.display = (trEnabled.checked && (state.allowance.transport.items || []).length === 0)
    ? "block"
    : "none";
}

function renderTransport() {
  trList.innerHTML = "";

  const items = state.allowance.transport.items || [];
  trEmpty.style.display = (trEnabled.checked && items.length === 0) ? "block" : "none";

  items.forEach((it, idx) => {
    const type = it.type || "fuel";

    let amount = 0;
    if (type === "fuel") {
      amount = num(it.distance) * num(it.rate || 4) * num(it.trips);
    } else if (type === "flight") {
      amount = num(it.ticket_price) * num(it.people) * num(it.trips);
    } else {
      amount = num(it.unit_price) * num(it.trips) * num(it.people);
    }

    const wrap = document.createElement("div");
    wrap.className = "p-4 bg-white rounded-xl border border-gray-200 shadow-sm";

    wrap.innerHTML = `
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-4">
          <label class="text-gray-700">ประเภทพาหนะ</label>
          <select class="w-full border rounded-md p-2 custom-select" data-k="type" data-i="${idx}">
            <option value="fuel" ${type === "fuel" ? "selected" : ""}>รถยนต์ / ค่าน้ำมันในประเทศ</option>
            <option value="flight" ${type === "flight" ? "selected" : ""}>เครื่องบิน</option>
            <option value="other" ${type === "other" ? "selected" : ""}>อื่น ๆ / รถโดยสาร / รถไฟ / แท็กซี่</option>
          </select>
        </div>

        <div class="md:col-span-7">
          <label class="text-gray-700">รายละเอียดรายการ</label>
          <input type="text" class="w-full border rounded-md p-2"
            value="${it.desc ?? ""}"
            data-k="desc"
            data-i="${idx}"
            placeholder="เช่น เดินทางจากมหาวิทยาลัยไปกรุงเทพฯ">
        </div>

        <div class="md:col-span-1">
          <button type="button"
            class="w-full bg-red-50 text-red-600 border border-red-200 rounded-md px-3 py-2 font-bold hover:bg-red-100"
            data-del="${idx}">
            ลบ
          </button>
        </div>
      </div>

      <div class="mt-4 transport-detail"></div>

      <div class="mt-3 text-gray-700 bg-gray-50 border rounded-lg p-3">
        รวมรายการนี้:
        <span class="font-bold text-[#0f766e] tr-item-total">${fmt(amount)}</span>
        บาท
      </div>
    `;

    const detail = wrap.querySelector(".transport-detail");

    if (type === "fuel") {
      detail.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
          <div class="md:col-span-3">
            <label class="text-gray-700">ต้นทาง</label>
            <input type="text" class="w-full border rounded-md p-2"
              value="${it.origin ?? ""}"
              data-k="origin"
              data-i="${idx}"
              placeholder="เช่น มจพ. ปราจีนบุรี">
          </div>

          <div class="md:col-span-3">
            <label class="text-gray-700">ปลายทาง</label>
            <input type="text" class="w-full border rounded-md p-2"
              value="${it.destination ?? ""}"
              data-k="destination"
              data-i="${idx}"
              placeholder="เช่น กรุงเทพฯ">
          </div>

          <div class="md:col-span-2">
            <label class="text-gray-700">ระยะทาง (กม.)</label>
            <input type="number" class="w-full border rounded-md p-2"
              min="0"
              step="0.01"
              value="${num(it.distance)}"
              data-k="distance"
              data-i="${idx}">
          </div>

          <div class="md:col-span-2">
            <label class="text-gray-700">จำนวนเที่ยว</label>
            <input type="number" class="w-full border rounded-md p-2"
              min="1"
              step="1"
              value="${num(it.trips) || 1}"
              data-k="trips"
              data-i="${idx}">
          </div>

          <div class="md:col-span-2">
            <label class="text-gray-700">อัตรา บาท/กม.</label>
            <input type="number" class="w-full border rounded-md p-2 bg-gray-100 text-gray-500"
              min="0"
              step="0.01"
              value="${num(it.rate || 4)}"
              data-k="rate"
              data-i="${idx}"
              readonly>
          </div>

          <div class="md:col-span-12 text-gray-600">
            สูตร: ระยะทาง × 4 บาท × จำนวนเที่ยว
          </div>
        </div>
      `;
    }

    if (type === "flight") {
      detail.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
          <div class="md:col-span-3">
            <label class="text-gray-700">สายการบิน</label>
            <input type="text" class="w-full border rounded-md p-2"
              value="${it.airline ?? ""}"
              data-k="airline"
              data-i="${idx}"
              placeholder="เช่น Thai AirAsia">
          </div>

          <div class="md:col-span-3">
            <label class="text-gray-700">เส้นทาง</label>
            <input type="text" class="w-full border rounded-md p-2"
              value="${it.route ?? ""}"
              data-k="route"
              data-i="${idx}"
              placeholder="เช่น กรุงเทพฯ - เชียงใหม่">
          </div>

          <div class="md:col-span-2">
            <label class="text-gray-700">ชั้นโดยสาร</label>
            <input type="text" class="w-full border rounded-md p-2 bg-gray-100 text-gray-500"
              value="ชั้นประหยัด"
              data-k="seat_class"
              data-i="${idx}"
              readonly>
          </div>

          <div class="md:col-span-2">
            <label class="text-gray-700">ราคาตั๋วชั้นประหยัด/คน</label>
            <input type="number" class="w-full border rounded-md p-2"
              min="0"
              step="0.01"
              value="${num(it.ticket_price)}"
              data-k="ticket_price"
              data-i="${idx}"
              placeholder="กรอกราคาตามจริง">
          </div>

          <div class="md:col-span-1">
            <label class="text-gray-700">จำนวนเที่ยว</label>
            <input type="number" class="w-full border rounded-md p-2"
              min="1"
              step="1"
              value="${num(it.trips) || 1}"
              data-k="trips"
              data-i="${idx}">
          </div>

          <div class="md:col-span-1">
            <label class="text-gray-700">คน</label>
            <input type="number" class="w-full border rounded-md p-2"
              min="1"
              step="1"
              value="${num(it.people) || 1}"
              data-k="people"
              data-i="${idx}">
          </div>

          <div class="md:col-span-12 text-gray-600">
            สูตร: ราคาตั๋วชั้นประหยัด/คนตามจริง × จำนวนเที่ยว × จำนวนคน
            <br>
            <span class="text-red-500">* เครื่องบินต้องเป็นชั้นประหยัดเท่านั้น และต้องกรอกราคาตามหลักฐานจริง</span>
          </div>
        </div>
      `;
    }

    if (type === "other") {
      detail.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
          <div class="md:col-span-5">
            <label class="text-gray-700">รายการ/เส้นทาง</label>
            <input type="text" class="w-full border rounded-md p-2"
              value="${it.route ?? ""}"
              data-k="route"
              data-i="${idx}"
              placeholder="เช่น รถโดยสาร กรุงเทพฯ - ปราจีนบุรี">
          </div>

          <div class="md:col-span-3">
            <label class="text-gray-700">ราคา/เที่ยว</label>
            <input type="number" class="w-full border rounded-md p-2"
              min="0"
              step="0.01"
              value="${num(it.unit_price)}"
              data-k="unit_price"
              data-i="${idx}">
          </div>

          <div class="md:col-span-2">
            <label class="text-gray-700">จำนวนเที่ยว</label>
            <input type="number" class="w-full border rounded-md p-2"
              min="1"
              step="1"
              value="${num(it.trips) || 1}"
              data-k="trips"
              data-i="${idx}">
          </div>

          <div class="md:col-span-2">
            <label class="text-gray-700">จำนวนคน</label>
            <input type="number" class="w-full border rounded-md p-2"
              min="1"
              step="1"
              value="${num(it.people) || 1}"
              data-k="people"
              data-i="${idx}">
          </div>

          <label class="text-gray-700">จำนวนเที่ยว <span class="text-xs text-gray-500">(เที่ยวเดียว=1, ไป-กลับ=2)</span></label>
        </div>
      `;
    }

    wrap.addEventListener("input", (e) => {
      const t = e.target;
      if (!t.dataset || t.dataset.i === undefined) return;

      const i = Number(t.dataset.i);
      const k = t.dataset.k;

      if (["distance", "rate", "trips", "people", "ticket_price", "unit_price"].includes(k)) {
        state.allowance.transport.items[i][k] = num(t.value);
      } else {
        state.allowance.transport.items[i][k] = t.value;
      }

      const item = state.allowance.transport.items[i];
      let amount = 0;

      if (item.type === "fuel") {
        amount = num(item.distance) * num(item.rate || 4) * num(item.trips);
      } else if (item.type === "flight") {
        amount = num(item.ticket_price) * num(item.people) * num(item.trips);
      } else {
        amount = num(item.unit_price) * num(item.trips) * num(item.people);
      }

      const totalEl = wrap.querySelector(".tr-item-total");
      if (totalEl) {
        totalEl.innerText = fmt(amount);
      }

      calc();
    });

    wrap.addEventListener("change", (e) => {
      const t = e.target;
      if (!t.dataset || t.dataset.i === undefined) return;

      const i = Number(t.dataset.i);
      const k = t.dataset.k;

      if (k === "type") {
        state.allowance.transport.items[i] = {
          type: t.value,
          desc: "",
          origin: "",
          destination: "",
          distance: 0,
          rate: 4,
          airline: "",
          route: "",
          seat_class: "economy",
          ticket_price: 0,
          unit_price: 0,
          trips: 1,
          people: 1
        };
      } else if (["distance", "rate", "trips", "people", "ticket_price", "unit_price"].includes(k)) {
        state.allowance.transport.items[i][k] = num(t.value);
      } else {
        state.allowance.transport.items[i][k] = t.value;
      }

      renderTransport();
      calc();
    });

    wrap.querySelector(`[data-del="${idx}"]`).addEventListener("click", () => {
      state.allowance.transport.items.splice(idx, 1);
      renderTransport();
      calc();
    });

    trList.appendChild(wrap);
  });
}

    // enable toggles
    regEnabled.addEventListener("change", () => {
      state.allowance.registration.enabled = regEnabled.checked;
      syncAllowUI();
      calc();
    });
    lodEnabled.addEventListener("change", () => {
      state.allowance.lodging.enabled = lodEnabled.checked;
      syncAllowUI();
      calc();
    });
    perEnabled.addEventListener("change", () => {
      state.allowance.perdiem.enabled = perEnabled.checked;
      syncAllowUI();
      syncPerdiemDefault();
    });
    trEnabled.addEventListener("change", () => {
      state.allowance.transport.enabled = trEnabled.checked;
      syncAllowUI();
      calc();
    });

    // inputs
    [regPrice, regPeople].forEach(el => el.addEventListener("input", () => {
      state.allowance.registration.price = num(regPrice.value);
      state.allowance.registration.people = num(regPeople.value) || 1;
      calc();
    }));

[lodNights, lodPeople].forEach(el => {
  el.addEventListener("input", () => syncLodgingAutoCalc(false));
  el.addEventListener("change", () => syncLodgingAutoCalc(false));
  el.addEventListener("keyup", () => syncLodgingAutoCalc(false));
});

[perUnit, perMeals, perPeople].forEach(el => {
  el.addEventListener("input", () => {
    state.allowance.perdiem.unit_price = num(perUnit.value);
    state.allowance.perdiem.meals = num(perMeals.value);
    state.allowance.perdiem.people = num(perPeople.value);
    calc();
  });

  el.addEventListener("change", () => {
    state.allowance.perdiem.unit_price = num(perUnit.value);
    state.allowance.perdiem.meals = num(perMeals.value);
    state.allowance.perdiem.people = num(perPeople.value);
    calc();
  });
});

addTrItemBtn.addEventListener("click", () => {
  state.allowance.transport.items = state.allowance.transport.items || [];

  state.allowance.transport.items.push({
    type: "fuel",
    desc: "",
    origin: "",
    destination: "",
    distance: 0,
    rate: 4,
    trips: 1,
    people: 1,
    airline: "",
    route: "",
    seat_class: "economy",
    ticket_price: 0,
    unit_price: 0
  });

  trEnabled.checked = true;
  state.allowance.transport.enabled = true;

  syncAllowUI();
  renderTransport();
  calc();
});

    // ====== restore from state to UI ======
    function hydrate() {
      // allowance
      regEnabled.checked = !!state.allowance.registration.enabled;
      regPrice.value = num(state.allowance.registration.price);
      regPeople.value = num(state.allowance.registration.people) || 1;

      lodEnabled.checked = !!state.allowance.lodging.enabled;
      lodDateText.value = state.allowance.lodging.date_text || "";

      if (lodDateText.value.includes("–") || lodDateText.value.includes("-")) {
        lodOptRange.checked = true;
        lodOptSingle.checked = false;
        const parts = lodDateText.value.split(/[–-]/).map(x => x.trim());
        lodStartDate.value = parts[0] || "";
        lodEndDate.value = parts[1] || "";
      } else {
        lodOptSingle.checked = true;
        lodOptRange.checked = false;
        lodSingleDate.value = lodDateText.value || "";
      }
      lodUnit.value = num(state.allowance.lodging.unit_price);
      lodNights.value = num(state.allowance.lodging.nights) || 1;
      lodPeople.value = num(state.allowance.lodging.people) || 1;

      perEnabled.checked = !!state.allowance.perdiem.enabled;

      if (perEnabled.checked) {
        perUnit.value = num(state.allowance.perdiem.unit_price) || 120;
        perMeals.value = num(state.allowance.perdiem.meals) || 3;
        perPeople.value = num(state.allowance.perdiem.people) || 1;
      } else {
        perUnit.value = 0;
        perMeals.value = 0;
        perPeople.value = 0;
      }

      trEnabled.checked = !!state.allowance.transport.enabled;

      syncAllowUI();
      renderComp();
      renderMat();
      renderTransport();
      applyLodDateModeStyle();
      syncLodgingAutoCalc();
      calc();
    }
    hydrate();

// ====== pack JSON before real submit ======
function packExpenseJsonBeforeSubmit() {
  // ตรวจสอบค่าพาหนะประเภทเครื่องบิน
  if (trEnabled.checked) {
    const flightItems = state.allowance.transport.items || [];

    for (let i = 0; i < flightItems.length; i++) {
      const item = flightItems[i];

      if (item.type === "flight") {
        if (num(item.ticket_price) <= 0) {
          alert("กรุณากรอกราคาตั๋วเครื่องบินชั้นประหยัดตามจริง ในรายการพาหนะลำดับที่ " + (i + 1));
          return false;
        }

        if (num(item.trips) <= 0) {
          alert("กรุณากรอกจำนวนเที่ยวของเครื่องบิน ในรายการพาหนะลำดับที่ " + (i + 1));
          return false;
        }

        if (num(item.people) <= 0) {
          alert("กรุณากรอกจำนวนคนของเครื่องบิน ในรายการพาหนะลำดับที่ " + (i + 1));
          return false;
        }

        item.seat_class = "economy";
      }
    }
  }

  // ถ้าไม่ได้ติ๊ก checkbox ให้ล้างค่าหมวดนั้นก่อนส่ง
  if (!regEnabled.checked) {
    state.allowance.registration.price = 0;
    state.allowance.registration.people = 0;
  }

  if (!lodEnabled.checked) {
    state.allowance.lodging.unit_price = 0;
    state.allowance.lodging.nights = 0;
    state.allowance.lodging.people = 0;
  }

  if (!perEnabled.checked) {
    state.allowance.perdiem.unit_price = 0;
    state.allowance.perdiem.meals = 0;
    state.allowance.perdiem.people = 0;
  }

  if (!trEnabled.checked) {
    state.allowance.transport.items = [];
  }

  expenseJsonInput.value = JSON.stringify(state);

  const totalValue = Number(String(totalAmount.value || "0").replace(/,/g, "")) || 0;
  amountHidden.value = totalValue.toFixed(2);

  const amountInput = document.getElementById("amountInput");
  if (amountInput) {
    amountInput.value = totalValue.toFixed(2);
  }

  return true;
}

const memoForm = document.getElementById("memoForm");
const submitBtn = document.getElementById("submitBtn");

submitBtn?.addEventListener("click", () => {
  const okExpense = packExpenseJsonBeforeSubmit();

  if (!okExpense) {
    return;
  }

  memoForm.submit();
});
  });
  </script>


</body>

</html>