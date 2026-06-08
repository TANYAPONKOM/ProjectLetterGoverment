<?php   //pro_letter/admin/home.php
session_start();
require_once __DIR__ . '/../functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}
$current = basename($_SERVER['PHP_SELF']); 
?>
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

#requestListContainer {
  flex: 1;
  overflow-y: auto;
}
</style>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
<<<<<<< HEAD
  <title>รายการส่งคำขอ</title>
=======
  <title>ประวัติการใช้งานเอกสาร</title>
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body class="bg-gray-100">
<<<<<<< HEAD
   <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>
=======
  <!-- Header -->
  <header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md">
    <div class="flex items-center space-x-3">
      <div class="w-[56px] h-[56px] flex items-center justify-center relative">
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
      <div class="flex items-center space-x-4">
        <a href="home.php">
          <div class="px-4 py-2 rounded-[11px] font-bold transition bg-white text-teal-500 shadow">หน้าหลัก</div>
        </a>
        <a href="history_page.php">
          <div class="px-4 py-2 rounded-[11px] font-bold transition text-white hover:bg-white hover:text-teal-500">
            ประวัติการใช้งานเอกสาร
          </div>
        </a>
        <a href="department_report_dashboard.php">
          <div class="px-4 py-2 rounded-[11px] font-bold transition text-white hover:bg-white hover:text-teal-500">
            รายงานภาควิชา
          </div>
        </a>

        <!-- เมนู: ตั้งค่าระบบเริ่มต้น -->
        <div class="relative">
          <button id="templateBtn" class="px-4 py-2 rounded-[11px] font-bold transition 
                text-white hover:bg-white hover:text-teal-500 flex items-center space-x-1">
            <span>ตั้งค่าระบบเริ่มต้น</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- เมนูย่อย -->
          <div id="templateMenu" class="hidden absolute bg-white text-gray-700 mt-1 rounded-lg shadow-lg w-48 z-50">
            <a href="form_Templates.php" class="block px-4 py-2 hover:bg-teal-100">การจัดการเทมเพลต</a>
            <a href="department_Managerment.php" class="block px-4 py-2 hover:bg-teal-100">การจัดการภาควิชา</a>
            <a href="user_Managerment.php" class="block px-4 py-2 hover:bg-teal-100">กำหนดสิทธิ์ผู้ใช้งาน</a>
          </div>
        </div>

        <div class="relative">
          <button id="profileBtn"
            class="bg-white text-teal-500 px-4 py-2 rounded-[11px] shadow flex items-center space-x-2 hover:bg-gray-100">
            <div class="text-right leading-tight">
              <div class="font-bold text-[14px]"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
              <div class="text-[12px]"><?= htmlspecialchars($_SESSION['role_name']) ?></div>
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
            <a href="/Pro_letter/logout.php"
              class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
            <button onclick="closeMenu()" class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
              อยู่ต่อ
            </button>
          </div>
        </div>
      </div>
      </a>

    </div>
  </header>
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a

  <main class="max-w-7xl w-full px-8 mx-auto bg-white mt-4 mb-12 p-6 rounded shadow min-h-[85vh]">
    <h2 class="text-xl font-bold mb-4">รายการส่งคำขอ</h2>

    <!-- Tabs -->
    <div class="flex space-x-6 border-b mb-4">
      <button id="tab-pending" class="bg-teal-500 text-white px-4 py-2 rounded-t-md font-semibold">รอตรวจสอบ</button>
<<<<<<< HEAD
        <button id="tab-edit" class="text-gray-500 px-4 py-2 rounded-t-md font-semibold">รอการแก้ไข</button>
      <button id="tab-done" class="text-gray-500 px-4 py-2 rounded-t-md font-semibold">ผ่านการตรวจสอบแล้ว</button>
      
=======
      <button id="tab-done" class="text-gray-500 px-4 py-2 rounded-t-md font-semibold">ผ่านการตรวจสอบแล้ว</button>
      <button id="tab-edit" class="text-gray-500 px-4 py-2 rounded-t-md font-semibold">รอการแก้ไข</button>
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
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
  let dataAll = [];

  async function loadRequests() {
    const res = await fetch("get_requests.php");
    const data = await res.json();

    dataAll = data.map(d => {
      // 🟢 แปลงสถานะจากฐานข้อมูล
      let s = d.status;
      if (s === "submitted") s = "pending";
      if (s === "approved") s = "done";
      if (s === "rejected") s = "edit";

      let statusText = "";
      let statusClass = "";

      if (s === "pending") {
        statusText = "รอตรวจสอบ";
        statusClass = "bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full text-xs font-semibold";
      } else if (s === "done") {
        statusText = "ผ่านการตรวจสอบแล้ว";
        statusClass = "bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-semibold";
      } else if (s === "edit") {
        statusText = "รอการแก้ไข";
        statusClass = "bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs font-semibold";
      }

      return {
        document_id: d.document_id,
        title: d.join_type || "(ไม่มีชื่อเรื่อง)",
        detail: formatDocumentDetail(d),
        subject: d.subject || "",
        date: d.doc_date,
        status: s, // 🟢 ใช้สถานะที่แปลงแล้ว
        statusText,
        statusClass,
        word: d.word_file,
        pdf: d.pdf_file,
        // ใช้เป็น hint ตอนหา route ไปหน้าเอกสารจริง กันกรณี join_type เคยถูกบันทึกผิดเป็น "อื่นๆ"
        routeHint: [
          d.join_type,
          d.course_name,
          d.subject,
          d.form_type,
          d.document_type,
          d.redirect_to,
          d.target_form,
          d.word_file,
          d.pdf_file
        ].filter(Boolean).join(" | ")
      };
    });

    renderList();
  }


  let currentPage = 1;
  let itemsPerPage = 10;
  let sortAsc = false;
  let activeTab = "pending";

  const requestList = document.getElementById("requestList");
  const pagination = document.getElementById("pagination");
  const itemsPerPageEl = document.getElementById("itemsPerPage");
  const sortBtn = document.getElementById("sortBtn");
  const sortIcon = document.getElementById("sortIcon");
  const tabPending = document.getElementById("tab-pending");
  const tabDone = document.getElementById("tab-done");
  const tabEdit = document.getElementById("tab-edit");

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


  function cleanDetailText(value) {
    return String(value ?? "")
      .replace(/\s+/g, " ")
      .replace(/\s*\/\s*/g, ", ")
      .trim();
  }

  function firstDetailValue(d, keys) {
    for (const key of keys) {
      if (d && d[key] !== undefined && d[key] !== null) {
        const value = cleanDetailText(d[key]);
        if (value !== "") return value;
      }
    }
    return "";
  }

  function extractDetailByLabel(text, labels) {
    const source = String(text || "");
    for (const label of labels) {
      const pattern = new RegExp(label + "\\s*[:：]\\s*([^,|\\n\\/]+)", "u");
      const matched = source.match(pattern);
      if (matched && matched[1]) {
        return cleanDetailText(matched[1]);
      }
    }
    return "";
  }

  function joinDetailParts(parts) {
    return parts
      .filter(part => part && String(part.value || "").trim() !== "")
      .map(part => `${part.label}: ${cleanDetailText(part.value)}`)
      .join(", ");
  }

  function formatDocumentDetail(d) {
    const hint = [
      d.join_type,
      d.course_name,
      d.subject,
      d.form_type,
      d.document_type,
      d.redirect_to,
      d.target_form,
      d.word_file,
      d.pdf_file
    ].filter(Boolean).join(" | ");

    const searchableText = [
      d.course_name,
      d.subject,
      hint
    ].filter(Boolean).join(" | ");

    const company = firstDetailValue(d, [
      "company_name",
      "company",
      "establishment_name",
      "establishment",
      "coop_company",
      "coop_company_name",
      "workplace",
      "organization_name",
      "organization"
    ]) || extractDetailByLabel(searchableText, ["สถานประกอบการ", "บริษัท", "หน่วยงาน"]);

    if (hint.includes("สหกิจ") || hint.includes("ประเมินสถานประกอบการ") || hint.includes("coop_evaluation")) {
      return company ? `สถานประกอบการ: ${company}` : cleanDetailText(d.course_name || "(ไม่มีรายละเอียด)");
    }

    const requestFor = firstDetailValue(d, [
      "request_for",
      "room_request_for",
      "use_for",
      "purpose",
      "room_purpose",
      "guest_type",
      "room_guest_type",
      "for_whom",
      "room_for",
      "room_request_text"
    ]) || extractDetailByLabel(searchableText, ["ขอใช้สำหรับ", "ใช้สำหรับ", "สำหรับ"]);

    const roomName = firstDetailValue(d, [
      "room_name",
      "room",
      "room_place",
      "room_type",
      "accommodation",
      "accommodation_name",
      "building",
      "room_building",
      "guesthouse",
      "house_name"
    ]) || extractDetailByLabel(searchableText, ["ห้องพัก", "อาคาร", "สถานที่พัก"]);

    const checkInDateRaw = firstDetailValue(d, [
      "checkin_date",
      "check_in_date",
      "stay_date",
      "room_date",
      "date_in",
      "start_date",
      "startDate",
      "arrival_date",
      "room_start_date"
    ]) || extractDetailByLabel(searchableText, ["วันที่เข้าพัก", "วันเข้าพัก"]);

    const checkInDate = checkInDateRaw ? formatDate(checkInDateRaw) : "";

    if (roomName || hint.includes("ห้องพักรับรอง") || hint.includes("room_request")) {
      const roomDetail = roomName ?
        joinDetailParts([{
            label: "ขอใช้สำหรับ",
            value: requestFor
          },
          {
            label: "ห้องพัก",
            value: roomName
          }
        ]) :
        joinDetailParts([{
            label: "ขอใช้สำหรับ",
            value: requestFor
          },
          {
            label: "วันที่เข้าพัก",
            value: checkInDate
          }
        ]);

      return roomDetail || cleanDetailText(d.course_name || "(ไม่มีรายละเอียด)");
    }

    const projectName = firstDetailValue(d, [
      "project_name",
      "projectTitle",
      "project_title",
      "project",
      "activity_project"
    ]) || extractDetailByLabel(searchableText, ["โครงการ"]);

    const activityName = firstDetailValue(d, [
      "activity_name",
      "activityTitle",
      "activity_title",
      "activity",
      "training_name"
    ]) || extractDetailByLabel(searchableText, ["กิจกรรม"]);

    if (hint.includes("จัดกิจกรรมโครงการ") || hint.includes("กิจกรรมโครงการ") || hint.includes("project_activity")) {
      const projectDetail = joinDetailParts([{
          label: "โครงการ",
          value: projectName
        },
        {
          label: "กิจกรรม",
          value: activityName
        }
      ]);

      return projectDetail || cleanDetailText(d.course_name || "(ไม่มีรายละเอียด)");
    }

    const thesisTitle = firstDetailValue(d, [
      "thesis_title",
      "research_title",
      "project_title",
      "projectTitle",
      "topic",
      "topic_name",
      "researchTopic"
    ]) || extractDetailByLabel(searchableText, ["หัวข้อปริญญานิพนธ์", "หัวข้อ"]);

    const requestData = firstDetailValue(d, [
      "request_data",
      "requested_data",
      "data_request",
      "data_detail",
      "data_needed",
      "information_request"
    ]) || extractDetailByLabel(searchableText, ["ข้อมูลที่ขอ", "ข้อมูล"]);

    if (hint.includes("ปริญญานิพนธ์") || hint.includes("ขอความอนุเคราะห์ข้อมูล") || hint.includes("research_data")) {
      const researchDetail = joinDetailParts([{
          label: "หัวข้อปริญญานิพนธ์",
          value: thesisTitle
        },
        {
          label: "ข้อมูลที่ขอ",
          value: requestData
        }
      ]);

      return researchDetail || cleanDetailText(d.course_name || "(ไม่มีรายละเอียด)");
    }

    return cleanDetailText(d.course_name || "(ไม่มีรายละเอียด)");
  }


  function renderList() {
    const dataFiltered = dataAll.filter(d => d.status === activeTab);

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

      /* ===== badge สถานะ (เหมือน user) ===== */
      let statusBadge = "";
      if (req.status === "pending") {
        statusBadge = `
        <span class="px-2 py-1 rounded-full text-xs font-semibold
                     bg-yellow-100 text-yellow-700">
          รอตรวจสอบ
        </span>`;
      } else if (req.status === "done") {
        statusBadge = `
        <span class="px-2 py-1 rounded-full text-xs font-semibold
                     bg-green-100 text-green-700">
          ผ่านการตรวจสอบแล้ว
        </span>`;
      } else if (req.status === "edit") {
        statusBadge = `
        <span class="px-2 py-1 rounded-full text-xs font-semibold
                     bg-red-100 text-red-700">
          รอการแก้ไข
        </span>`;
      }

      /* ===== action (เหมือน user เป๊ะ) ===== */
      let actionHtml = "";

      if (req.status === "pending") {
        actionHtml = `
        <div class="mt-3 flex gap-2">
          <button onclick="approveDocument(${req.document_id})"
            class="px-6 py-2 bg-teal-500 hover:bg-teal-600
                   text-white text-sm font-semibold rounded-xl shadow">
<<<<<<< HEAD
            ผ่าน
=======
            ยืนยันการตรวจสอบ
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
          </button>

          <button onclick="rejectDocument(${req.document_id})"
            class="px-6 py-2 bg-red-400 hover:bg-red-500
                   text-white text-sm font-semibold rounded-xl shadow">
<<<<<<< HEAD
            ไม่ผ่าน
=======
            ไม่ผ่านการตรวจสอบ
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
          </button>
        </div>`;
      } else if (req.status === "done") {
        actionHtml = `
        <div class="mt-3 px-4 py-2 rounded-xl
                    bg-green-50 text-green-700
                    text-sm font-semibold border border-green-300">
          📄 เอกสารถูกตรวจสอบแล้ว
        </div>`;
      } else if (req.status === "edit") {
        actionHtml = `
        <div class="mt-3 px-4 py-2 rounded-xl
                    bg-red-50 text-red-700
                    text-sm font-semibold border border-red-300">
          ✏️ รอผู้ยื่นแก้ไขเอกสาร
        </div>`;
      }

      return `
    <div class="bg-gray-50 p-4 rounded-xl shadow flex justify-between items-start">

      <!-- ซ้าย -->
<div class="flex-1 min-w-0 pr-4">

  <a href="#" onclick="openDocument(${req.document_id}, '${String(req.routeHint || req.title).replace(/\\/g, "\\\\").replace(/'/g, "\\'")}')"
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
      ${statusBadge}
    </div>

  </div>

</div>

      <!-- ขวา (โครงเดียวกับ USER) -->
      <div class="text-right flex flex-col items-end text-sm text-gray-600 min-w-[200px]">

        <!-- วันที่ -->
        <div class="font-medium mb-2">
          ${formatDate(req.date)}
        </div>

        

        <!-- ปุ่ม / ข้อความ -->
        ${actionHtml}

      </div>
    </div>`;
    }).join("");

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
    renderList();
  };
  itemsPerPageEl.onchange = () => {
    itemsPerPage = parseInt(itemsPerPageEl.value);
    currentPage = 1;
    renderList();
  };
<<<<<<< HEAD
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
  activeTab = "pending";
  currentPage = 1;
  setActiveTab(tabPending);
  renderList();
};

tabDone.onclick = () => {
  activeTab = "done";
  currentPage = 1;
  setActiveTab(tabDone);
  renderList();
};

tabEdit.onclick = () => {
  activeTab = "edit";
  currentPage = 1;
  setActiveTab(tabEdit);
  renderList();
};

  loadRequests();

=======
  tabPending.onclick = () => {
    activeTab = "pending";
    renderList();
    tabPending.classList.add("bg-teal-500", "text-white");
    tabDone.classList.remove("bg-teal-500", "text-white");
    tabEdit.classList.remove("bg-teal-500", "text-white");
    currentPage = 1;
  };
  tabDone.onclick = () => {
    activeTab = "done";
    renderList();
    tabDone.classList.add("bg-teal-500", "text-white");
    tabPending.classList.remove("bg-teal-500", "text-white");
    tabEdit.classList.remove("bg-teal-500", "text-white");
    currentPage = 1;
  };
  tabEdit.onclick = () => {
    activeTab = "edit";
    renderList();
    tabEdit.classList.add("bg-teal-500", "text-white");
    tabPending.classList.remove("bg-teal-500", "text-white");
    tabDone.classList.remove("bg-teal-500", "text-white");
    currentPage = 1;
  };

  loadRequests();

  const profileBtn = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");
  profileBtn.addEventListener("click", () => {
    profileMenu.classList.toggle("hidden");
  });

  function closeMenu() {
    profileMenu.classList.add("hidden");
  }
  const templateBtn = document.getElementById("templateBtn");
  const templateMenu = document.getElementById("templateMenu");

  templateBtn.addEventListener("click", () => {
    templateMenu.classList.toggle("hidden");
  });

  // ปิด dropdown ถ้าคลิกนอกเมนู
  document.addEventListener("click", (e) => {
    if (!templateBtn.contains(e.target) && !templateMenu.contains(e.target)) {
      templateMenu.classList.add("hidden");
    }
  });
  // กดนอกเมนูให้ปิด
  window.addEventListener("click", (e) => {
    if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
      profileMenu.classList.add("hidden");
    }
  });
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a

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

  function getDocumentViewUrl(docId, joinType = "") {
    const type = String(joinType || "").trim();

    const routes = [{
        keywords: ["ขอประเมินสถานประกอบการสหกิจ", "ประเมินสถานประกอบการ", "สหกิจศึกษา"],
        url: "../form_Memo/form_memo_coop_evaluation.php"
      },
      {
        keywords: ["ขอเข้าไปจัดกิจกรรมโครงการ", "จัดกิจกรรมโครงการ", "กิจกรรมโครงการ"],
        url: "../form_Memo/form_memo_project_activity.php"
      },
      {
        keywords: ["ขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์", "ข้อมูลจัดทำปริญญานิพนธ์", "ปริญญานิพนธ์"],
        url: "../form_Memo/form_memo_request_research_data.php"
      },
      {
        keywords: ["หนังสือเรียนเชิญวิทยากร", "เรียนเชิญวิทยากร"],
        url: "../form_Memo/form_memo_invite_speaker.php"
      },
      {
        keywords: ["ขออนุมัติใช้ห้องพักรับรอง", "ขอห้องพักรับรอง", "ห้องพักรับรอง"],
        url: "../form_Memo/form_memo_room_request_1.php"
      },
      {
        keywords: ["ขออนุมัติตัวบุคคลเป็นวิทยากร", "ตัวบุคคลเป็นวิทยากร"],
        url: "../form_Memo/form_memo_speaker.php"
      },
      {
        keywords: ["ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน", "ขอเข้าเยี่ยมศึกษาดูงาน", "ศึกษาดูงาน", "เข้าเยี่ยมชม"],
        url: "../form_Memo/form_memo_sut_wellness.php"
      },
      {
        keywords: [
          "consent_research_presentation",
          "infor_present",
          "form_consent_research_presentation",
          "หนังสือยินยอมให้นำเสนอผลงานวิจัย",
          "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ",
          "ยินยอมให้นำเสนอผลงาน",
          "ยินยอมนำเสนอ"
        ],
        url: "../form_Memo/form_consent_research_presentation.php"
      },
      {
        keywords: ["นำเสนอผลงานวิจัย"],
        url: "../form_Memo/form_memo_academic_1.php"
      }
    ];

    for (const route of routes) {
      if (route.keywords.some(keyword => type.includes(keyword))) {
        return route.url + "?id=" + encodeURIComponent(docId);
      }
    }

    return "../documents/view_memo.php?id=" + encodeURIComponent(docId);
  }

  function getDocumentPdfDownloadUrl(docId, routeHint = "") {
    return "../documents/auto_download_pdf.php?id=" + encodeURIComponent(docId) +
      "&hint=" + encodeURIComponent(routeHint || "");
  }


  function sanitizeDownloadFileName(name) {
    return String(name || "เอกสาร")
      .replace(/[\\\/:*?"<>|\r\n\t]+/g, " ")
      .replace(/\s+/g, " ")
      .trim()
      .slice(0, 120) || "เอกสาร";
  }

  function escapeHtmlAttribute(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function getWordThaiFileName(req) {
    const baseName = sanitizeDownloadFileName(req.subject || req.title || req.detail || "เอกสาร");
    return baseName + "_เลขที่_" + encodeURIComponent(req.document_id).replace(/%/g, "") + ".docx";
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
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = blobUrl;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(blobUrl);
      })
      .catch(() => {
        window.location.href = url;
      });

    return false;
  }

  function getDocumentWordDownloadUrl(docId, routeHint = "") {
    const text = String(routeHint || "").trim();

    const routes = [{
        keywords: ["ขอประเมินสถานประกอบการสหกิจ", "ประเมินสถานประกอบการ", "สหกิจศึกษา", "coop_evaluation"],
        url: "../documents/download_word_coop_evaluation.php"
      },
      {
        keywords: ["ขอเข้าไปจัดกิจกรรมโครงการ", "จัดกิจกรรมโครงการ", "กิจกรรมโครงการ", "project_activity"],
        url: "../documents/download_word_project_activity.php"
      },
      {
        keywords: ["หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์", "ขอความอนุเคราะห์ข้อมูล", "ปริญญานิพนธ์",
          "research_data"
        ],
        url: "../documents/download_word_request_research_data.php"
      },
      {
        keywords: ["หนังสือเรียนเชิญวิทยากร", "เรียนเชิญวิทยากร", "เชิญวิทยากร", "invite_speaker"],
        url: "../documents/download_word_invite_speaker.php"
      },
      {
        keywords: ["ขออนุมัติใช้ห้องพักรับรอง", "ขอห้องพักรับรอง", "ห้องพักรับรอง", "room_request"],
        url: "../documents/download_word_room_request.php"
      },
      {
        keywords: ["ขออนุมัติตัวบุคคลเป็นวิทยากร", "ตัวบุคคลเป็นวิทยากร", "speaker_workshop"],
        url: "../documents/download_word_speaker.php"
      },
      {
        keywords: ["ขอเข้าเยี่ยมศึกษาดูงาน", "ศึกษาดูงาน", "เข้าเยี่ยมชม", "study_visit"],
        url: "../documents/download_word_sut_wellness.php"
      },
      {
        keywords: ["หนังสือยินยอมให้นำเสนอผลงานวิจัย", "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ",
          "ยินยอมให้นำเสนอผลงาน", "consent_research_presentation"
        ],
        url: "../documents/download_word_consent_research_presentation.php"
      },
      {
        keywords: ["นำเสนอผลงานวิจัย", "academic_presentation"],
        url: "../documents/download_word_academic_1.php"
      }
    ];

    const matched = routes.find(route =>
      route.keywords.some(keyword => text.includes(keyword))
    );

    const baseUrl = matched ? matched.url : "../documents/download_word_memo.php";
    return baseUrl + "?id=" + encodeURIComponent(docId);
  }


  function openDocument(docId, joinType = "") {
    fetch("../check_view_permission.php?id=" + encodeURIComponent(docId))
      .then(r => r.json())
      .then(res => {
        console.log("Returned JSON:", res);

        if (!res || typeof res.allowed === "undefined") {
          Swal.fire("Error", "ข้อมูลที่ส่งกลับไม่ถูกต้อง", "error");
          return;
        }

        if (res.allowed === true) {
          window.location.href = getDocumentViewUrl(docId, joinType);
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
  }

  function approveDocument(id) {
    Swal.fire({
      title: "ยืนยันการตรวจสอบเอกสาร?",
      text: "เอกสารจะถูกระบุว่าตรวจสอบเรียบร้อยแล้ว",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "ยืนยัน",
      cancelButtonText: "ยกเลิก"
    }).then(result => {
      if (!result.isConfirmed) return;

      Swal.fire({
        html: `
          <style>
            @keyframes reviewSpin {
              0% { transform: rotate(0deg); }
              100% { transform: rotate(360deg); }
            }
          </style>
          <div style="padding: 10px 6px 4px; text-align: center;">
            <div style="
              width: 58px;
              height: 58px;
              border-radius: 50%;
              border: 7px solid #d9f3ef;
              border-top-color: #14b8a6;
              margin: 0 auto 18px;
              animation: reviewSpin 0.8s linear infinite;
            "></div>
            <div style="font-size: 24px; font-weight: 700; color: #0f766e; margin-bottom: 6px;">
              กำลังบันทึกผลการตรวจสอบ...
            </div>
            <div style="font-size: 15px; color: #64748b;">
              กรุณารอสักครู่ ระบบกำลังบันทึกข้อมูลและส่งอีเมลแจ้งผู้ใช้
            </div>
          </div>
        `,
        width: 360,
        padding: "24px 20px 26px",
        background: "#ffffff",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false
      });

      fetch("../documents/update_status.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            id,
            status: "approved"
          })
        })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            Swal.fire({
              icon: "success",
              title: "ตรวจสอบเรียบร้อย",
              timer: 1500,
              showConfirmButton: false
            });
            loadRequests();
          }
        });
    });
  }

  function rejectDocument(id) {
    Swal.fire({
      title: "เอกสารไม่ผ่านการตรวจสอบ?",
      text: "เอกสารจะถูกส่งกลับให้ผู้ยื่นแก้ไข",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "ยืนยัน",
      cancelButtonText: "ยกเลิก"
    }).then(result => {
      if (!result.isConfirmed) return;

      Swal.fire({
        html: `
          <style>
            @keyframes reviewSpin {
              0% { transform: rotate(0deg); }
              100% { transform: rotate(360deg); }
            }
          </style>
          <div style="padding: 10px 6px 4px; text-align: center;">
            <div style="
              width: 58px;
              height: 58px;
              border-radius: 50%;
              border: 7px solid #d9f3ef;
              border-top-color: #14b8a6;
              margin: 0 auto 18px;
              animation: reviewSpin 0.8s linear infinite;
            "></div>
            <div style="font-size: 24px; font-weight: 700; color: #0f766e; margin-bottom: 6px;">
              กำลังบันทึกผลการตรวจสอบ...
            </div>
            <div style="font-size: 15px; color: #64748b;">
              กรุณารอสักครู่ ระบบกำลังบันทึกข้อมูลและส่งอีเมลแจ้งผู้ใช้
            </div>
          </div>
        `,
        width: 360,
        padding: "24px 20px 26px",
        background: "#ffffff",
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false
      });

      fetch("../documents/update_status.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            id,
            status: "rejected"
          })
        })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            Swal.fire({
              icon: "success",
              title: "ส่งกลับให้แก้ไขแล้ว",
              timer: 1500,
              showConfirmButton: false
            });
            loadRequests();
          }
        });
    });
  }
  </script>
</body>

</html>