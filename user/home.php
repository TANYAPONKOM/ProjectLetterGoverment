<?php   //pro_letter/user/home.php
session_start();
require_once __DIR__ . '/../functions.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

/* รายละเอียดรายการเอกสารในหน้า Home: ใช้เฉพาะเอกสารบางฟอร์มที่เดิมแสดง "(ไม่มีรายละเอียด)" */
$homeDocDetailMap = [];

try {
    $pdo = getPDO();

    $detailStmt = $pdo->prepare("
        SELECT d.document_id, dv.field_id, dv.value_text
        FROM documents d
        INNER JOIN document_values dv ON dv.document_id = d.document_id
        WHERE d.owner_id = ?
          AND dv.field_id IN (
            27,28,29,32,35,36,37,
            49,52,54,
            60,61,62,66,
            72,75,76,79
          )
    ");
    $detailStmt->execute([$_SESSION['user_id']]);

    while ($row = $detailStmt->fetch(PDO::FETCH_ASSOC)) {
        $docId = (int)$row['document_id'];
        $fieldId = (int)$row['field_id'];

        if (!isset($homeDocDetailMap[$docId])) {
            $homeDocDetailMap[$docId] = [];
        }

        $homeDocDetailMap[$docId][$fieldId] = trim((string)$row['value_text']);
    }
} catch (Throwable $e) {
    $homeDocDetailMap = [];
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>รายการส่งคำขอ</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
  html,
  body {
    height: 100vh;
    overflow: hidden;
    margin: 0;
  }

  body {
    display: flex;
    flex-direction: column;
  }

  main {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .swal2-actions {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 12px !important;
    flex-wrap: nowrap !important;
  }

  .swal2-actions button {
    margin: 0 !important;
  }

  #holdCancelBtn {
    min-width: 220px;
  }

  .cancel-actions-row {
    display: flex !important;
    flex-direction: row !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 12px !important;
    flex-wrap: nowrap !important;
  }

  .myCancelBtn {
    background: #6b7280 !important;
    color: #fff !important;
    border: none !important;
    padding: 12px 24px !important;
    border-radius: 10px !important;
    font-weight: bold !important;
    font-size: 15px !important;
    min-width: 120px !important;
    margin: 0 !important;
  }

  .myCancelBtn {
    min-width: 140px !important;
  }

  #requestListContainer {
    flex: 1;
    overflow-y: auto;
  }
  </style>
</head>

<body class="bg-gray-100 ">
   <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>


  <!-- Content -->
  <main class="max-w-7xl w-full px-8 mx-auto bg-white mt-4 mb-12 p-6 rounded shadow min-h-[85vh]">
    <h2 class="text-xl font-bold mb-4">รายการส่งคำขอ</h2>

    <!-- Tabs -->
    <div class="flex space-x-6 border-b mb-4">
      <button id="tab-pending" class="relative bg-teal-500 text-white px-4 py-2 rounded-t-md font-semibold">
        รอตรวจสอบ
        <span id="pendingCount" class="absolute -top-2 -right-3 min-w-[22px] h-[22px] px-1 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center shadow">
          0
        </span>
      </button>
      <button id="tab-edit" class="relative text-gray-500 px-4 py-2 rounded-t-md font-semibold">
        รอการแก้ไข
        <span id="editCount" class="absolute -top-2 -right-3 min-w-[22px] h-[22px] px-1 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center shadow">
          0
        </span>
      </button>
      <button id="tab-done" class="relative text-gray-500 px-4 py-2 rounded-t-md font-semibold">
        ผ่านการตรวจสอบแล้ว
        <span id="approvedCount" class="absolute -top-2 -right-3 min-w-[22px] h-[22px] px-1 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center shadow">
          0
        </span>
      </button>

    </div>

    <!-- Filter + Sort -->
    <div class="flex justify-between items-center mb-2">
      <label class="text-sm text-gray-700">
        แสดง:
        <select id="itemsPerPage" class="border rounded px-2 py-1 text-sm">
          <option value="5" selected>5</option>
          <option value="10">10</option>
        </select>
        รายการ/หน้า
      </label>
      <button id="sortBtn" class="flex items-center text-sm text-teal-600">
        วันที่
        <svg id="sortIcon" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24"
          stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
        </svg>
      </button>
    </div>

    <!-- List Container -->
    <div id="requestListContainer">
      <div id="requestList" class="space-y-4"></div>
    </div>
    <div id="pagination" class="flex justify-center mt-6 space-x-2"></div>
  </main>

  <script>
  const homeDocDetailMap = <?= json_encode($homeDocDetailMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function cleanHomeDetailValue(value) {
    return String(value || "").replace(/\s+/g, " ").trim();
  }

  function firstHomeDetailValue(detailMap, fieldIds) {
    for (const fieldId of fieldIds) {
      const value = cleanHomeDetailValue(detailMap[fieldId]);
      if (value) {
        return value;
      }
    }

    return "";
  }

  function formatHomeThaiDateText(value) {
    const raw = cleanHomeDetailValue(value);
    if (!raw) return "";

    const thaiMonths = [
      "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
      "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    ];

    return raw.replace(/(\d{4})-(\d{2})-(\d{2})/g, function(_, y, m, d) {
      const monthName = thaiMonths[parseInt(m, 10) - 1] || m;
      return `${parseInt(d, 10)} ${monthName} ${parseInt(y, 10) + 543}`;
    }).replace(/(\d{1,2})\/(\d{1,2})\/(\d{4})/g, function(_, d, m, y) {
      const monthName = thaiMonths[parseInt(m, 10) - 1] || m;
      return `${parseInt(d, 10)} ${monthName} ${parseInt(y, 10) + 543}`;
    });
  }

  function buildHomeDocumentDetail(d, title, routeHint) {
    const detailMap = homeDocDetailMap[String(d.document_id)] || {};
    const textForType = [
      title || "",
      routeHint || "",
      d.subject || "",
      d.memo_subject || "",
      d.join_type || ""
    ].join(" ");

    const fallback = cleanHomeDetailValue(d.course_name || d.memo_subject || d.subject);
    let detailParts = [];

    if (textForType.includes("ขออนุมัติใช้ห้องพักรับรอง") || textForType.includes("room_request")) {
      const roomFor = firstHomeDetailValue(detailMap, [28, 27]);
      const roomDate = formatHomeThaiDateText(firstHomeDetailValue(detailMap, [36, 35]));
      const roomType = firstHomeDetailValue(detailMap, [37]);

      if (roomFor) detailParts.push(`ขอใช้สำหรับ: ${roomFor}`);
      if (roomDate) detailParts.push(`วันที่เข้าพัก: ${roomDate}`);
      if (!roomDate && roomType) detailParts.push(`ห้องพัก: ${roomType}`);
    } else if (textForType.includes("ขอประเมินสถานประกอบการสหกิจศึกษา") || textForType.includes("coop")) {
      const orgName = firstHomeDetailValue(detailMap, [72]);

      if (orgName) detailParts.push(`สถานประกอบการ: ${orgName}`);
    } else if (textForType.includes("ขอเข้าไปจัดกิจกรรมโครงการ") || textForType.includes("project_activity")) {
      const mainProject = firstHomeDetailValue(detailMap, [61]);
      const subActivity = firstHomeDetailValue(detailMap, [62]);
      const roomFor = firstHomeDetailValue(detailMap, [28, 27]);
      const roomType = firstHomeDetailValue(detailMap, [37]);

      if (mainProject || subActivity) {
        if (mainProject) detailParts.push(`โครงการ: ${mainProject}`);
        if (subActivity) detailParts.push(`กิจกรรม: ${subActivity}`);
      } else {
        if (roomFor) detailParts.push(`ขอใช้สำหรับ: ${roomFor}`);
        if (roomType) detailParts.push(`ห้องพัก: ${roomType}`);
      }
    } else if (textForType.includes("หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์") || textForType.includes(
        "research_data")) {
      const thesisTitle = firstHomeDetailValue(detailMap, [49]);
      const supportType = firstHomeDetailValue(detailMap, [52]);

      if (thesisTitle) detailParts.push(`หัวข้อปริญญานิพนธ์: ${thesisTitle}`);
      if (supportType) detailParts.push(`ข้อมูลที่ขอ: ${supportType}`);
    }

    const detail = cleanHomeDetailValue(detailParts.join(", "));
    return detail || fallback || "(ไม่มีรายละเอียด)";
  }

  let dataAll = [];

  async function loadRequests() {
    const res = await fetch("../documents/get_requests.php?_=" + Date.now(), {
      cache: "no-store"
    });

    const data = await res.json();

    dataAll = (Array.isArray(data) ? data : []).map(d => {

      // ====== สถานะสำหรับแสดงผล ======
      let statusTh = "";
      let userViewStatus = "";

      if (d.status === "draft") {
        // ⚠️ ยังไม่ส่งจริง แต่ให้ user เห็นเหมือนอยู่รอตรวจสอบ
        statusTh = "รอตรวจสอบ";
        userViewStatus = "pending_view";
      } else if (d.status === "submitted") {
        statusTh = "รอตรวจสอบ";
        userViewStatus = "pending_view";
      } else if (d.status === "approved") {
        statusTh = "ผ่านการตรวจสอบแล้ว";
        userViewStatus = "approved";
      } else if (d.status === "rejected") {
        statusTh = "รอการแก้ไข";
        userViewStatus = "rejected";
      }

      const routeHint = [
        d.join_type || "",
        d.course_name || "",
        d.subject || "",
        d.memo_subject || "",
        d.form_type || "",
        d.document_type || "",
        d.target_form || "",
        d.redirect_to || "",
        d.view_file || "",
        d.word_file || "",
        d.pdf_file || ""
      ].join(" ");

      let title = d.join_type || "";

      // แสดงชื่อรายการสำหรับเอกสารนำเสนอผลงานวิจัยให้ตรงกับชื่อฟอร์ม
      if (title.trim() === "นำเสนอผลงานวิจัย") {
        title = "ขออนุมัติตัวบุคคลเพื่อไปนำเสนอผลงานวิจัย";
      }

      if (title.trim() === "อื่นๆ" && (
          routeHint.includes("consent_research_presentation") ||
          routeHint.includes("infor_present") ||
          routeHint.includes("form_consent_research_presentation") ||
          routeHint.includes("หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ") ||
          routeHint.includes("ยินยอมให้นำเสนอผลงานวิจัย")
        )) {
        title = "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ";
      }

      return {
        document_id: d.document_id,
        join_type: d.join_type || "",
        route_hint: routeHint,
        title: title || "(ไม่มีชื่อเรื่อง)",
        detail: buildHomeDocumentDetail(d, title, routeHint),
        date: d.doc_date,

        raw_status: d.status, // ⭐ สถานะจริง DB
        view_status: userViewStatus, // ⭐ สถานะใช้จัดแท็บ user
        status: statusTh, // แสดงผลภาษาไทย

        word: d.word_file,
        pdf: d.pdf_file
      };
    });

    updateStatusCounts();
    renderList();
  }

  loadRequests();

  async function refreshRequestsAfterAction() {
    await loadRequests();
  }


  let currentPage = 1;
  let itemsPerPage = 10;
  let sortAsc = false;

  const requestList = document.getElementById("requestList");
  const pagination = document.getElementById("pagination");
  const itemsPerPageEl = document.getElementById("itemsPerPage");
  const sortBtn = document.getElementById("sortBtn");
  const sortIcon = document.getElementById("sortIcon");
  const tabPending = document.getElementById("tab-pending");
  const tabDone = document.getElementById("tab-done");
  const tabEdit = document.getElementById("tab-edit");
  const pendingCount = document.getElementById("pendingCount");
  const editCount = document.getElementById("editCount");
  const approvedCount = document.getElementById("approvedCount");

  function hasThaiMonth(value) {
    const text = String(value || "").trim();

    return /มกราคม|กุมภาพันธ์|มีนาคม|เมษายน|พฤษภาคม|มิถุนายน|กรกฎาคม|สิงหาคม|กันยายน|ตุลาคม|พฤศจิกายน|ธันวาคม/.test(
      text);
  }

  function parseDateForSort(value) {
    if (!value || String(value).trim() === "" || value === "0000-00-00") {
      return null;
    }

    const text = String(value).trim();

    const thaiMonths = {
      "มกราคม": 0,
      "กุมภาพันธ์": 1,
      "มีนาคม": 2,
      "เมษายน": 3,
      "พฤษภาคม": 4,
      "มิถุนายน": 5,
      "กรกฎาคม": 6,
      "สิงหาคม": 7,
      "กันยายน": 8,
      "ตุลาคม": 9,
      "พฤศจิกายน": 10,
      "ธันวาคม": 11
    };

    // กรณีวันที่ไทย เช่น 30 กันยายน 2569
    let m = text.match(/^(\d{1,2})\s+([ก-ฮ]+)\s+(\d{4})$/);
    if (m && thaiMonths[m[2]] !== undefined) {
      let year = Number(m[3]);

      // ถ้าเป็น พ.ศ. ให้แปลงเป็น ค.ศ. เพื่อใช้เรียงวันที่
      if (year > 2400) {
        year -= 543;
      }

      return new Date(year, thaiMonths[m[2]], Number(m[1]));
    }

    // กรณี 2025-12-16 หรือ 2025-12-16 00:00:00
    m = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
    if (m) {
      return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    }

    // กรณี 27/05/2026 หรือ 27/05/2569
    m = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (m) {
      let year = Number(m[3]);

      // ถ้าเป็น พ.ศ. ให้แปลงเป็น ค.ศ.
      if (year > 2400) {
        year -= 543;
      }

      return new Date(year, Number(m[2]) - 1, Number(m[1]));
    }

    return null;
  }

  function updateStatusCounts() {
    const pendingCountEl = document.getElementById("pendingCount");
    const editCountEl = document.getElementById("editCount");
    const approvedCountEl = document.getElementById("approvedCount");

    const totalPending = dataAll.filter(d => d.view_status === "pending_view").length;
    const totalEdit = dataAll.filter(d => d.view_status === "rejected").length;
    const totalApproved = dataAll.filter(d => d.view_status === "approved").length;

    if (pendingCountEl) pendingCountEl.textContent = totalPending;
    if (editCountEl) editCountEl.textContent = totalEdit;
    if (approvedCountEl) approvedCountEl.textContent = totalApproved;
  }

  function formatDate(value) {
    if (!value || String(value).trim() === "" || value === "0000-00-00") {
      return "-";
    }

    const text = String(value).trim();

    // ถ้าในฐานข้อมูลเป็นวันที่ไทยอยู่แล้ว เช่น 30 กันยายน 2569
    // ให้โชว์เลย ไม่ต้องแปลงด้วย new Date()
    if (hasThaiMonth(text)) {
      return text;
    }

    const date = parseDateForSort(text);

    // ถ้าแปลงไม่ได้ ให้โชว์ค่าจากฐานข้อมูลแทน เพื่อไม่ให้ขึ้น Invalid Date
    if (!date || isNaN(date.getTime())) {
      return text;
    }

    return date.toLocaleDateString("th-TH", {
      day: "numeric",
      month: "long",
      year: "numeric",
    });
  }

  function renderList() {
    const dataFiltered = dataAll.filter(d => d.view_status === activeTab);

    const sorted = dataFiltered.sort((a, b) => {
      const dateA = parseDateForSort(a.date);
      const dateB = parseDateForSort(b.date);

      const timeA = dateA && !isNaN(dateA.getTime()) ? dateA.getTime() : 0;
      const timeB = dateB && !isNaN(dateB.getTime()) ? dateB.getTime() : 0;

      return sortAsc ? timeA - timeB : timeB - timeA;
    });

    const start = (currentPage - 1) * itemsPerPage;
    const shown = sorted.slice(start, start + itemsPerPage);

    requestList.innerHTML = shown.map(req => {

      /* ===== สถานะ ===== */
      let statusClass = "";
      if (req.status === "รอตรวจสอบ") {
        statusClass = "bg-yellow-100 text-yellow-700";
      } else if (req.status === "ผ่านการตรวจสอบแล้ว") {
        statusClass = "bg-green-100 text-green-700";
      } else if (req.status === "รอการแก้ไข") {
        statusClass = "bg-red-100 text-red-700";
      }

      /* ===== ปุ่ม / ข้อความด้านล่าง ===== */
      let actionHtml = "";

      // 🟡 ยังเป็น draft → แสดงปุ่ม
      if (req.raw_status === "draft") {
        actionHtml = `
  <div class="mt-3 flex gap-2">
    <button onclick="submitDocument(${req.document_id})"
      class="px-5 py-2 bg-teal-500 hover:bg-teal-600
             text-white text-sm font-semibold rounded-xl shadow">
      ส่งเพื่อตรวจสอบ
    </button>

    <button onclick="cancelDocument(${req.document_id})"
      class="px-5 py-2 bg-red-500 hover:bg-red-600
             text-white text-sm font-semibold rounded-xl shadow">
      ลบรายการนี้
    </button>
  </div>
`;
      }

      // ⏳ ส่งแล้ว → แสดงข้อความ (ไม่ใช่ปุ่ม)
      else if (req.raw_status === "submitted") {
        actionHtml = `
        <div class="mt-3 px-4 py-2 rounded-xl
                    bg-yellow-50 text-yellow-700
                    text-sm font-semibold border border-yellow-300">
          ⏳ กำลังรอตรวจสอบ
        </div>
      `;
      }

      return `
      <div class="bg-gray-50 p-4 rounded-xl shadow flex justify-between items-start">

        <!-- ซ้าย -->
        <div class="flex-1 min-w-0 pr-4">
          <a href="#" onclick='return openDocument(${req.document_id}, ${JSON.stringify(req.route_hint || req.join_type || "")})'
             class="font-semibold text-teal-600 hover:underline text-lg">
            ${req.title}
          </a>

         <div class="text-sm text-gray-500 mt-1">

            <!-- รายละเอียด -->
            <div class="break-words">
              ${req.detail}
            </div>

            <!-- สถานะ -->
            <div class="mt-2 flex items-center gap-2">
              <span>สถานะ:</span>

              <span class="px-2 py-1 rounded-full text-xs font-semibold whitespace-nowrap ${statusClass}">
                ${req.status}
              </span>
            </div>

          </div>
        </div>

        <!-- ขวา -->
        <div class="text-right flex flex-col items-end text-sm text-gray-600 min-w-[200px]">

          <!-- วันที่ -->
          <div class="font-medium mb-2">
            ${formatDate(req.date)}
          </div>

          

          <!-- ปุ่ม / ข้อความ -->
          ${actionHtml}

        </div>
      </div>
    `;
    }).join("");

    /* ===== Pagination ===== */
    const totalPages = Math.ceil(dataFiltered.length / itemsPerPage);
    pagination.innerHTML = Array.from({
        length: totalPages
      }, (_, i) => i + 1)
      .map(i => `
      <button onclick="goToPage(${i})"
        class="px-3 py-1 rounded border
        ${i === currentPage
          ? "bg-teal-500 text-white"
          : "text-teal-500 border-teal-500"}">
        ${i}
      </button>
    `).join("");
  }




  function goToPage(page) {
    currentPage = page;
    renderList();
  }

  sortBtn.onclick = () => {
    sortAsc = !sortAsc;
    sortIcon.innerHTML = sortAsc ?
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />' :
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />';
    renderList();
  };

  itemsPerPageEl.onchange = () => {
    itemsPerPage = parseInt(itemsPerPageEl.value);
    currentPage = 1;
    renderList();
  };

  let activeTab = "pending_view";

function setActiveTab(activeButton) {
  const tabs = [tabPending, tabEdit, tabDone];

  tabs.forEach(tab => {
    tab.classList.remove("bg-teal-500", "text-white");
    tab.classList.add("text-gray-500");
  });

  activeButton.classList.add("bg-teal-500", "text-white");
  activeButton.classList.remove("text-gray-500");
}

tabPending.onclick = () => {
  activeTab = "pending_view"; // รวม draft + submitted
  setActiveTab(tabPending);
  currentPage = 1;
  renderList();
};

tabDone.onclick = () => {
  activeTab = "approved";
  setActiveTab(tabDone);
  currentPage = 1;
  renderList();
};

tabEdit.onclick = () => {
  activeTab = "rejected";
  setActiveTab(tabEdit);
  currentPage = 1;
  renderList();
};

// อัปเดตตัวเลขสถานะใหม่เมื่อกลับมาหน้านี้ และรีเฟรชข้อมูลเป็นระยะ
window.addEventListener("focus", loadRequests);
setInterval(loadRequests, 10000);


  renderList();





  document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);
    const errType = params.get("err");

    if (errType === "no_view") {
      Swal.fire({
        title: "ไม่มีสิทธิ์ดูเอกสารนี้",
        text: "คุณไม่มีสิทธิ์ในการเข้าถึงเอกสารนี้",
        icon: "error",
        confirmButtonText: "ตกลง",
        confirmButtonColor: "#3085d6",
      }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete("err");
        window.history.replaceState({}, "", url.toString());
      });
    }
  });

  function isConsentResearchPresentationHint(routeHint = "") {
    const text = String(routeHint || "").trim();
    return (
      text.includes("consent_research_presentation") ||
      text.includes("infor_present") ||
      text.includes("form_consent_research_presentation") ||
      text.includes("หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ") ||
      text.includes("ยินยอมให้นำเสนอผลงานทางวิชาการ") ||
      text.includes("ยินยอมให้นำเสนอผลงานวิจัย")
    );
  }

  function getDocumentViewUrl(docId, routeHint = "") {
    const text = String(routeHint || "").trim();

    if (isConsentResearchPresentationHint(text)) {
      return `../form_Memo/form_consent_research_presentation.php?id=${encodeURIComponent(docId)}`;
    }

    // จับจากข้อความที่แสดงในรายการเอกสาร ให้ตรงกับ redirect ที่ save_memo.php ส่งไป
    const routes = [{
        keywords: ["สหกิจ", "ประเมินสถานประกอบการ", "coop_evaluation"],
        url: "../form_Memo/form_memo_coop_evaluation.php"
      },
      {
        keywords: ["จัดกิจกรรมโครงการ", "กิจกรรมโครงการ", "project_activity"],
        url: "../form_Memo/form_memo_project_activity.php"
      },
      {
        keywords: ["ปริญญานิพนธ์", "ขอความอนุเคราะห์ข้อมูล", "research_data"],
        url: "../form_Memo/form_memo_request_research_data.php"
      },
      {
        keywords: ["เรียนเชิญวิทยากร", "เชิญวิทยากร", "invite_speaker_student", "invite"],
        url: "../form_Memo/form_memo_invite_speaker.php"
      },
      {
        keywords: ["ห้องพักรับรอง", "room_request"],
        url: "../form_Memo/form_memo_room_request_1.php"
      },
      {
        keywords: ["ตัวบุคคลเป็นวิทยากร", "speaker_workshop"],
        url: "../form_Memo/form_memo_speaker.php"
      },
      {
        keywords: ["ศึกษาดูงาน", "เข้าเยี่ยมชม", "study_visit"],
        url: "../form_Memo/form_memo_sut_wellness.php"
      },
      {
        keywords: ["ยินยอมให้นำเสนอผลงาน", "consent_research_presentation"],
        url: "../form_Memo/form_consent_research_presentation.php"
      },
      {
        keywords: ["นำเสนอผลงานวิจัย", "academic"],
        url: "../form_Memo/form_memo_academic_1.php"
      }
    ];

    const matched = routes.find(route =>
      route.keywords.some(keyword => text.includes(keyword))
    );

    const baseUrl = matched ? matched.url : "../documents/view_memo.php";
    return `${baseUrl}?id=${encodeURIComponent(docId)}`;
  }

  function getDocumentPdfDownloadUrl(docId, routeHint = "") {
    return `../documents/auto_download_pdf.php?id=${encodeURIComponent(docId)}&hint=${encodeURIComponent(routeHint || "")}`;
  }


  function makeThaiWordFileName(req) {
    const rawTitle = String((req && (req.title || req.join_type || req.detail)) || "เอกสาร");
    const docId = String((req && req.document_id) || "").trim();

    let fileName = rawTitle
      .replace(/[\\\/:*?"<>|\r\n\t]+/g, " ")
      .replace(/\s+/g, " ")
      .trim();

    if (!fileName) {
      fileName = "เอกสาร";
    }

    if (fileName.length > 90) {
      fileName = fileName.substring(0, 90).trim();
    }

    if (docId) {
      fileName += "_เลขที่_" + docId;
    }

    return fileName + ".docx";
  }

  function downloadWordThai(link) {
    const url = link.getAttribute("href");
    const fileName = link.getAttribute("data-word-filename") || "เอกสาร.docx";

    fetch(url, {
        method: "GET",
        credentials: "same-origin"
      })
      .then(response => {
        if (!response.ok) {
          throw new Error("download failed");
        }
        return response.blob();
      })
      .then(blob => {
        const objectUrl = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = objectUrl;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(objectUrl);
      })
      .catch(() => {
        window.location.href = url;
      });

    return false;
  }

  function getDocumentWordDownloadUrl(docId, routeHint = "") {
    const text = String(routeHint || "").trim();

    const routes = [{
        keywords: ["สหกิจ", "ประเมินสถานประกอบการ", "coop_evaluation"],
        url: "../documents/download_word_coop_evaluation.php"
      },
      {
        keywords: ["จัดกิจกรรมโครงการ", "กิจกรรมโครงการ", "project_activity"],
        url: "../documents/download_word_project_activity.php"
      },
      {
        keywords: ["ปริญญานิพนธ์", "ขอความอนุเคราะห์ข้อมูล", "research_data"],
        url: "../documents/download_word_request_research_data.php"
      },
      {
        keywords: ["เรียนเชิญวิทยากร", "เชิญวิทยากร", "invite_speaker_student", "invite"],
        url: "../documents/download_word_invite_speaker.php"
      },
      {
        keywords: ["ห้องพักรับรอง", "room_request"],
        url: "../documents/download_word_room_request.php"
      },
      {
        keywords: ["ตัวบุคคลเป็นวิทยากร", "speaker_workshop"],
        url: "../documents/download_word_speaker.php"
      },
      {
        keywords: ["ศึกษาดูงาน", "เข้าเยี่ยมชม", "study_visit"],
        url: "../documents/download_word_sut_wellness.php"
      },
      {
        keywords: ["ยินยอมให้นำเสนอผลงาน", "consent_research_presentation"],
        url: "../documents/download_word_consent_research_presentation.php"
      },
      {
        keywords: ["นำเสนอผลงานวิจัย", "academic"],
        url: "../documents/download_word_academic_1.php"
      }
    ];

    const matched = routes.find(route =>
      route.keywords.some(keyword => text.includes(keyword))
    );

    const baseUrl = matched ? matched.url : "../documents/download_word_memo.php";
    return `${baseUrl}?id=${encodeURIComponent(docId)}`;
  }

  function openDocument(docId, routeHint = "") {
    fetch("../check_view_permission.php?id=" + encodeURIComponent(docId))
      .then(r => r.json())
      .then(res => {
        console.log("Returned JSON:", res);

        if (!res || typeof res.allowed === "undefined") {
          Swal.fire("Error", "ข้อมูลที่ส่งกลับไม่ถูกต้อง", "error");
          return;
        }

        if (res.allowed === true) {
          window.location.href = getDocumentViewUrl(docId, routeHint);
          return;
        }

        if (res.reason === "not_login") {
          Swal.fire("กรุณาเข้าสู่ระบบ", "", "warning");
          return;
        }

        if (res.reason === "no_permission") {
          Swal.fire({
            title: "ไม่มีสิทธิ์เข้าดูเอกสารนี้",
            text: "คุณไม่สามารถเข้าถึงเอกสารนี้ได้",
            icon: "error",
            confirmButtonText: "ตกลง"
          });
          return;
        }

        Swal.fire("เกิดข้อผิดพลาด", "ไม่สามารถตรวจสอบสิทธิ์ได้", "error");
      })
      .catch(err => {
        console.log("Fetch error:", err);
        Swal.fire("Error", "ไม่สามารถตรวจสอบสิทธิ์ได้", "error");
      });

    return false;
  }

  function submitDocument(id) {
    Swal.fire({
      title: "ยืนยันการส่งเอกสาร?",
      text: "เอกสารจะถูกส่งให้เจ้าหน้าที่ตรวจสอบ",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "ยืนยัน",
      cancelButtonText: "ยกเลิก"
    }).then(result => {
      if (!result.isConfirmed) return;

      fetch("../documents/submit_document.php", {

          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            document_id: id
          })
        })
        .then(r => r.json())
        .then(res => {
          if (res.success) {

            dataAll = dataAll.map(item => {
              if (Number(item.document_id) === Number(id)) {
                return {
                  ...item,
                  raw_status: "submitted",
                  view_status: "pending_view",
                  status: "รอตรวจสอบ"
                };
              }
              return item;
            });
            updateStatusCounts();
            renderList();

            Swal.fire({
              icon: "success",
              title: "ส่งเรียบร้อย",
              text: "เอกสารอยู่ระหว่างรอการตรวจสอบ",
              timer: 1500,
              showConfirmButton: false
            });

            // ✅ อัปเดตตัวเลขและรายการจาก DB อีกครั้งหลังส่งสำเร็จ
            refreshRequestsAfterAction();


          } else {
            Swal.fire("ผิดพลาด", res.message || "ไม่สามารถส่งได้", "error");
          }
        });
    });
  }

  function cancelDocument(id) {
    Swal.fire({
      title: "ลบรายการนี้?",
      html: `
      <p style="font-size:16px; margin-bottom:10px; text-align:center;">
        หากลบรายการนี้แล้ว รายการนี้จะถูกลบออกจากรายการคำขอ
      </p>
    `,
      icon: "warning",
      showConfirmButton: false,
      showCancelButton: true,
      cancelButtonText: "ยกเลิก",
      buttonsStyling: false,
      customClass: {
        actions: "cancel-actions-row",
        cancelButton: "myCancelBtn"
      },
      didOpen: () => {
        const actions = Swal.getActions();

        const holdBtn = document.createElement("button");
        holdBtn.id = "holdCancelBtn";
        holdBtn.type = "button";
        holdBtn.textContent = "กดค้าง 2 วินาทีเพื่อยืนยัน";

        holdBtn.style.cssText = `
        background:#dc2626;
        color:#fff;
        border:none;
        padding:12px 24px;
        border-radius:10px;
        font-weight:bold;
        cursor:pointer;
        font-size:15px;
        min-width:220px;
        margin:0;
      `;

        actions.prepend(holdBtn);

        let timer = null;

        const startHold = (e) => {
          e.preventDefault();

          holdBtn.textContent = "กำลังกดยืนยัน...";
          holdBtn.style.background = "#991b1b";

          timer = setTimeout(() => {
            Swal.close();

            fetch("../documents/cancel_document.php", {
                method: "POST",
                headers: {
                  "Content-Type": "application/json"
                },
                body: JSON.stringify({
                  document_id: id
                })
              })
              .then(r => r.json())
              .then(res => {
                if (res.success) {
                  dataAll = dataAll.filter(item => Number(item.document_id) !== Number(id));
                  updateStatusCounts();
                  renderList();

                  Swal.fire({
                    icon: "success",
                    title: "ยกเลิกคำขอแล้ว",
                    timer: 1200,
                    showConfirmButton: false
                  });

                  // ✅ อัปเดตตัวเลขและรายการจาก DB อีกครั้งหลังลบสำเร็จ
                  refreshRequestsAfterAction();
                } else {
                  Swal.fire(
                    "ผิดพลาด",
                    res.message || "ไม่สามารถยกเลิกได้",
                    "error"
                  );
                }
              });
          }, 2000);
        };

        const stopHold = () => {
          clearTimeout(timer);
          timer = null;

          holdBtn.textContent = "กดค้าง 2 วินาทีเพื่อยืนยัน";
          holdBtn.style.background = "#dc2626";
        };

        holdBtn.addEventListener("mousedown", startHold);
        holdBtn.addEventListener("mouseup", stopHold);
        holdBtn.addEventListener("mouseleave", stopHold);

        holdBtn.addEventListener("touchstart", startHold);
        holdBtn.addEventListener("touchend", stopHold);
        holdBtn.addEventListener("touchcancel", stopHold);
      }
    });
  }
  </script>

</body>

</html>