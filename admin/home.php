<?php  //pro_letter/admin/home.php
session_start();
require_once __DIR__ . '/../functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

/* รายละเอียดรายการเอกสารในหน้า Home ของผู้ดูแลระบบ: ใช้สำหรับแสดงรายละเอียดใต้ชื่อเอกสาร */
$homeDocDetailMap = [];
$homeDocOwnerMap = [];

try {
    $pdo = getPDO();

    $detailStmt = $pdo->query("
        SELECT 
            d.document_id,
            dv.field_id,
            dv.value_text,
            COALESCE(tf.field_key, '') AS field_key,
            COALESCE(tf.field_label, '') AS field_label
        FROM documents d
        INNER JOIN document_values dv ON dv.document_id = d.document_id
        LEFT JOIN template_fields tf ON tf.field_id = dv.field_id
        WHERE dv.value_text IS NOT NULL
          AND TRIM(dv.value_text) <> ''
    ");

    while ($row = $detailStmt->fetch(PDO::FETCH_ASSOC)) {
        $docId = (int)$row['document_id'];
        $fieldId = (int)$row['field_id'];
        $fieldKey = trim((string)($row['field_key'] ?? ''));
        $fieldLabel = trim((string)($row['field_label'] ?? ''));
        $valueText = trim((string)$row['value_text']);

        if (!isset($homeDocDetailMap[$docId])) {
            $homeDocDetailMap[$docId] = [];
        }

        $homeDocDetailMap[$docId][$fieldId] = $valueText;

        if ($fieldKey !== '') {
            $homeDocDetailMap[$docId][$fieldKey] = $valueText;
            $homeDocDetailMap[$docId][strtolower($fieldKey)] = $valueText;
        }

        if ($fieldLabel !== '') {
            $homeDocDetailMap[$docId][$fieldLabel] = $valueText;
        }
    }

    /* ดึงชื่อเจ้าของเอกสารสำหรับแสดงในรายการ: แตะเฉพาะส่วนรายละเอียดเจ้าของเอกสาร */
    $docColumns = [];
    $userColumns = [];

    foreach ($pdo->query("SHOW COLUMNS FROM documents") as $col) {
        $docColumns[$col['Field']] = true;
    }
    foreach ($pdo->query("SHOW COLUMNS FROM users") as $col) {
        $userColumns[$col['Field']] = true;
    }

    $ownerColumn = null;
    foreach (['owner_id', 'user_id', 'created_by', 'created_by_id', 'created_user_id', 'document_owner_id'] as $candidate) {
        if (isset($docColumns[$candidate])) {
            $ownerColumn = $candidate;
            break;
        }
    }

    if ($ownerColumn !== null) {
        $nameParts = [];

        if (isset($userColumns['first_name']) || isset($userColumns['last_name'])) {
            $firstNameSql = isset($userColumns['first_name']) ? "COALESCE(u.first_name, '')" : "''";
            $lastNameSql  = isset($userColumns['last_name']) ? "COALESCE(u.last_name, '')" : "''";
            $nameParts[] = "NULLIF(TRIM(CONCAT($firstNameSql, ' ', $lastNameSql)), '')";
        }
        foreach (['fullname', 'full_name', 'name', 'username', 'email'] as $candidate) {
            if (isset($userColumns[$candidate])) {
                $nameParts[] = "NULLIF(TRIM(u.`$candidate`), '')";
            }
        }

        $ownerNameSql = $nameParts
            ? 'COALESCE(' . implode(', ', $nameParts) . ", '')"
            : "''";

        $ownerStmt = $pdo->query("
            SELECT 
                d.document_id,
                $ownerNameSql AS owner_name
            FROM documents d
            LEFT JOIN users u ON u.user_id = d.`$ownerColumn`
        ");

        while ($row = $ownerStmt->fetch(PDO::FETCH_ASSOC)) {
            $docId = (int)$row['document_id'];
            $ownerName = trim((string)($row['owner_name'] ?? ''));

            if ($ownerName !== '') {
                $homeDocOwnerMap[$docId] = $ownerName;
            }
        }
    }
} catch (Throwable $e) {
    $homeDocDetailMap = [];
    $homeDocOwnerMap = [];
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>รายการส่งคำขอ (Admin)</title>
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

  #requestListContainer {
    flex: 1;
    overflow-y: auto;
  }
  </style>
</head>

<body class="bg-gray-100">
    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>

  <!-- Content -->
  <main class="max-w-7xl w-full px-8 mx-auto bg-white mt-4 mb-12 p-6 rounded shadow min-h-[85vh]">
    <h2 class="text-xl font-bold mb-4">รายการส่งคำขอ</h2>

    <!-- Tabs -->
    <div class="flex space-x-6 border-b mb-4">
      <button id="tab-pending" class="relative bg-teal-500 text-white px-4 py-2 rounded-t-md font-semibold">
        รอตรวจสอบ
        <span id="pendingCount" class="absolute -top-3 -right-3 min-w-[22px] h-[22px] px-1 flex items-center justify-center rounded-full bg-red-500 text-white text-xs font-bold shadow">0</span>
      </button>
        <button id="tab-edit" class="relative text-gray-500 px-4 py-2 rounded-t-md font-semibold">
        รอการแก้ไข
        <span id="editCount" class="absolute -top-3 -right-3 min-w-[22px] h-[22px] px-1 flex items-center justify-center rounded-full bg-red-500 text-white text-xs font-bold shadow">0</span>
      </button>
      <button id="tab-done" class="relative text-gray-500 px-4 py-2 rounded-t-md font-semibold">
        ผ่านการตรวจสอบแล้ว
        <span id="doneCount" class="absolute -top-3 -right-3 min-w-[22px] h-[22px] px-1 flex items-center justify-center rounded-full bg-red-500 text-white text-xs font-bold shadow">0</span>
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
  let dataAll = [];
  const currentUserId = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
  const currentRoleId = <?= (int)($_SESSION['role_id'] ?? 0) ?>;
  const homeDocDetailMap = <?= json_encode($homeDocDetailMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const homeDocOwnerMap = <?= json_encode($homeDocOwnerMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  async function loadRequests() {
    const res = await fetch("get_requests.php?_=" + Date.now(), { cache: "no-store" });
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

      const routeHint = [
        d.join_type,
        d.template_name,
        d.template_code,
        d.course_name,
        d.subject,
        d.document_path,
        d.question_path,
        d.form_type,
        d.document_type,
        d.redirect_to,
        d.target_form,
        d.word_file,
        d.pdf_file
      ].filter(Boolean).join(" ");

      const isResearchDataDocument =
        routeHint.toLowerCase().includes("research_data") ||
        routeHint.includes("ปริญญานิพนธ์") ||
        routeHint.includes("form_memo_request_research_data.php") ||
        routeHint.includes("infor_research_data.php");

      const displayDate = isResearchDataDocument
        ? (d.updated_at || d.created_at || d.doc_date)
        : d.doc_date;

      return {
        document_id: d.document_id,
        owner_id: Number(d.owner_id || d.user_id || d.created_by || d.created_by_id || d.created_user_id || d.document_owner_id || 0),
        isOwnDocument: [
          d.owner_id,
          d.user_id,
          d.created_by,
          d.created_by_id,
          d.created_user_id,
          d.document_owner_id
        ].map(v => Number(v || 0)).includes(currentUserId) ||
          Number(d.is_own_document || d.isOwnDocument || 0) === 1,
        owner_role_id: Number(d.owner_role_id || d.created_by_role_id || d.ownerRoleId || d.createdByRoleId || 0),
        created_by_role_id: Number(d.created_by_role_id || d.owner_role_id || d.createdByRoleId || d.ownerRoleId || 0),
        is_own_document: Number(d.is_own_document || d.isOwnDocument || 0),
        is_officer_created_document: Number(d.is_officer_created_document || d.isOfficerCreatedDocument || 0),
        isOfficerCreatedDocument:
          Number(d.is_officer_created_document || d.isOfficerCreatedDocument || 0) === 1 ||
          Number(d.owner_role_id || d.created_by_role_id || d.ownerRoleId || d.createdByRoleId || 0) === 2 ||
          (
            currentRoleId === 2 &&
            (
              Number(d.owner_id || d.user_id || d.created_by || d.created_by_id || d.created_user_id || d.document_owner_id || 0) === currentUserId ||
              Number(d.is_own_document || d.isOwnDocument || 0) === 1
            )
          ),
        title: d.join_type || d.template_name || d.subject || "(ไม่มีชื่อเรื่อง)",
        detail: formatDocumentDetail(d),
        ownerName: cleanDetailText(
          d.owner_name ||
          d.owner_fullname ||
          d.created_by_name ||
          d.user_fullname ||
          d.fullname ||
          [d.first_name, d.last_name].filter(Boolean).join(" ") ||
          homeDocOwnerMap[String(d.document_id || d.id || d.doc_id)] ||
          ""
        ),
        date: displayDate,
        status: s, // 🟢 ใช้สถานะที่แปลงแล้ว
        statusText,
        statusClass,
        word: d.word_file,
        pdf: d.pdf_file,
        documentPath:
          d.document_path ||
          d.documentPath ||
          d.document_url ||
          d.documentUrl ||
          d.view_path ||
          d.viewPath ||
          d.form_path ||
          d.formPath ||
          "",
        routeHint
      };
    });

    updateStatusCounts();
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
  const pendingCount = document.getElementById("pendingCount");
  const editCount = document.getElementById("editCount");
  const doneCount = document.getElementById("doneCount");

  function updateStatusCounts() {
    const totalPending = dataAll.filter(d => d.status === "pending").length;
    const totalEdit = dataAll.filter(d => d.status === "edit").length;
    const totalDone = dataAll.filter(d => d.status === "done").length;

    if (pendingCount) pendingCount.textContent = totalPending;
    if (editCount) editCount.textContent = totalEdit;
    if (doneCount) doneCount.textContent = totalDone;
  }

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
    const detailMap = homeDocDetailMap[String(d.document_id)] || {};
    const detailSource = { ...d, ...detailMap };
    const hint = [
      d.join_type,
      d.template_name,
      d.template_code,
      d.course_name,
      d.subject,
      d.document_path,
      d.question_path,
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
      d.template_name,
      hint
    ].filter(Boolean).join(" | ");

    const company = firstDetailValue(detailSource, [
      "company_name",
      "company",
      "establishment_name",
      "establishment",
      "coop_company",
      "coop_company_name",
      "workplace",
      "organization_name",
      "organization",
      "coop_organization_name",
      "coop_establishment_name",
      "agency_name",
      "department_name",
      "หน่วยงาน",
      "ชื่อหน่วยงาน",
      "สถานประกอบการ"
    ]) || cleanDetailText(detailMap[72] || "") || extractDetailByLabel(searchableText, ["สถานประกอบการ", "บริษัท", "หน่วยงาน"]);

    if (hint.includes("สหกิจ") || hint.includes("ประเมินสถานประกอบการ") || hint.includes("coop_evaluation")) {
      return company ? `หน่วยงาน : ${company}` : cleanDetailText(firstDetailValue(detailSource, ["course_name", "ชื่อหลักสูตร"]) || d.course_name || "(ไม่มีรายละเอียด)");
    }

    const requestFor = firstDetailValue(detailSource, [
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

    const roomName = firstDetailValue(detailSource, [
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

    const checkInDateRaw = firstDetailValue(detailSource, [
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

      return roomDetail || cleanDetailText(firstDetailValue(detailSource, ["course_name", "ชื่อหลักสูตร"]) || d.course_name || "(ไม่มีรายละเอียด)");
    }

    const projectName = firstDetailValue(detailSource, [
      "project_name",
      "projectTitle",
      "project_title",
      "project",
      "activity_project",
      "project_activity_name",
      "project_activity_title",
      "ชื่อโครงการ",
      "โครงการ"
    ]) || extractDetailByLabel(searchableText, ["โครงการ"]);

    const activityName = firstDetailValue(detailSource, [
      "activity_name",
      "activityTitle",
      "activity_title",
      "activity",
      "training_name",
      "activity_detail",
      "activity_topic",
      "ชื่อกิจกรรม",
      "กิจกรรม"
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

      return projectDetail || cleanDetailText(firstDetailValue(detailSource, ["course_name", "project_name", "activity_name", "ชื่อโครงการ", "ชื่อกิจกรรม"]) || d.course_name || "(ไม่มีรายละเอียด)");
    }

    const thesisTitle = firstDetailValue(detailSource, [
      "thesis_title",
      "research_title",
      "project_title",
      "projectTitle",
      "topic",
      "topic_name",
      "researchTopic",
      "research_thesis_title",
      "thesis_topic",
      "หัวข้อปริญญานิพนธ์",
      "ชื่อเรื่องปริญญานิพนธ์"
    ]) || extractDetailByLabel(searchableText, ["หัวข้อปริญญานิพนธ์", "หัวข้อ"]);

    const requestData = firstDetailValue(detailSource, [
      "request_data",
      "requested_data",
      "data_request",
      "data_detail",
      "data_needed",
      "information_request",
      "research_data_detail",
      "research_data_amount",
      "data_amount",
      "ข้อมูลที่ขอ",
      "รายละเอียดข้อมูล"
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

      return researchDetail || cleanDetailText(firstDetailValue(detailSource, ["research_thesis_title", "thesis_title", "หัวข้อปริญญานิพนธ์"]) || d.course_name || "(ไม่มีรายละเอียด)");
    }

    const defaultCourseName = cleanDetailText(
      firstDetailValue(detailSource, [
        "course_name",
        "training_course_name",
        "training_course",
        "course_title",
        "course",
        "courseName",
        "join_course_name",
        "training_title",
        "training_subject",
        "course_detail",
        "หลักสูตร",
        "ชื่อหลักสูตร",
        "ชื่อหลักสูตรอบรม"
      ]) || d.course_name || ""
    );
    if (defaultCourseName && defaultCourseName !== "(ไม่มีรายละเอียด)") {
      const courseName = defaultCourseName.replace(/^ชื่อหลักสูตร\s*[:：]\s*/u, "").trim();
      return `ชื่อหลักสูตร: ${courseName}`;
    }

    return cleanDetailText(d.subject || "(ไม่มีรายละเอียด)");
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
          ตรวจสอบแล้ว
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
            ตรวจสอบแล้ว: ผ่าน
          </button>

          <button onclick="rejectDocument(${req.document_id})"
            class="px-6 py-2 bg-red-400 hover:bg-red-500
                   text-white text-sm font-semibold rounded-xl shadow">
            ตรวจสอบแล้ว: ไม่ผ่าน
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

      const ownerHtml = req.ownerName ? `
      <div class="break-words mt-1">
        เจ้าของเอกสาร: ${req.ownerName}
      </div>` : "";

      return `
    <div class="bg-gray-50 p-4 rounded-xl shadow flex justify-between items-start">

     <!-- ซ้าย -->
<div class="flex-1 min-w-0 pr-4">

  <a href="#" onclick="openDocument(${req.document_id}, '${String(req.routeHint || req.title).replace(/\\/g, "\\\\").replace(/'/g, "\\'").replace(/\n/g, " ")}', '${String(req.documentPath || "").replace(/\\/g, "\\\\").replace(/'/g, "\\'").replace(/\n/g, " ")}')"
   class="font-semibold text-teal-600 hover:underline text-lg">
  ${req.title}
</a>

  <div class="text-sm text-gray-500 mt-1">

    <!-- รายละเอียด -->
    <div class="break-words">
      ${req.detail}
    </div>
    ${ownerHtml}

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


  function getDocumentUrl(docId, joinType = "", documentPath = "") {
    const normalizePathToUrl = (path) => {
      const cleanPath = String(path || "").trim();
      if (cleanPath === "") return "";

      if (/^https?:\/\//i.test(cleanPath)) {
        return cleanPath + (cleanPath.includes("?") ? "&" : "?") + "id=" + encodeURIComponent(docId);
      }

      const normalized = cleanPath
        .replace(/^\/Pro_letter\/?/i, "")
        .replace(/^\.\.\//, "")
        .replace(/^\.\//, "")
        .replace(/^\//, "");

      return "../" + normalized + "?id=" + encodeURIComponent(docId);
    };

    const text = String(joinType || "").trim();
    const directPath = String(documentPath || "").trim();
    const directUrl = normalizePathToUrl(directPath);
    const isMemoPath = /(^|\/)view_memo\.php(\?|$)/i.test(directPath);

    const routes = [
      {
        keywords: [
          "RESEARCH_DATA",
          "infor_research_data.php",
          "form_memo_request_research_data.php",
          "หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์",
          "ขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์",
          "ปริญญานิพนธ์"
        ],
        url: "../form_Memo/form_memo_request_research_data.php?id="
      },
      {
        keywords: [
          "COOP_EVALUATION",
          "infor_coop_evaluation.php",
          "form_memo_coop_evaluation.php",
          "ขอประเมินสถานประกอบการสหกิจ",
          "ประเมินสถานประกอบการ",
          "สถานประกอบการสหกิจ",
          "สหกิจ"
        ],
        url: "../form_Memo/form_memo_coop_evaluation.php?id="
      },
      {
        keywords: [
          "PROJECT_ACTIVITY",
          "infor_project_activity.php",
          "form_memo_project_activity.php",
          "ขอเข้าไปจัดกิจกรรมโครงการ",
          "จัดกิจกรรมโครงการ",
          "กิจกรรมโครงการ"
        ],
        url: "../form_Memo/form_memo_project_activity.php?id="
      },
      {
        keywords: [
          "INVITE_SPEAKER",
          "infor_invite.php",
          "form_memo_invite_speaker.php",
          "หนังสือเรียนเชิญวิทยากร",
          "เรียนเชิญวิทยากร"
        ],
        url: "../form_Memo/form_memo_invite_speaker.php?id="
      },
      {
        keywords: [
          "ROOM_REQUEST",
          "infor_room_request.php",
          "form_memo_room_request_1.php",
          "ขอห้องพักรับรอง",
          "ห้องพักรับรอง",
          "ขออนุมัติใช้ห้องพัก"
        ],
        url: "../form_Memo/form_memo_room_request_1.php?id="
      },
      {
        keywords: [
          "SPEAKER_WORKSHOP",
          "SPEAKER",
          "infor_speaker_workshop.php",
          "form_memo_speaker.php",
          "ขออนุมัติตัวบุคคลเป็นวิทยากร",
          "ตัวบุคคลเป็นวิทยากร"
        ],
        url: "../form_Memo/form_memo_speaker.php?id="
      },
      {
        keywords: [
          "STUDY_VISIT",
          "infor_study_visit.php",
          "form_memo_sut_wellness.php",
          "ขอเข้าเยี่ยมศึกษาดูงาน",
          "ศึกษาดูงาน",
          "เข้าเยี่ยมชม",
          "เยี่ยมชมศึกษาดูงาน",
          "SUT Wellness"
        ],
        url: "../form_Memo/form_memo_sut_wellness.php?id="
      },
      {
        keywords: [
          "CONSENT_RESEARCH_PRESENTATION",
          "PRESENT",
          "infor_present.php",
          "form_consent_research_presentation.php",
          "หนังสือยินยอมให้นำเสนอผลงานวิจัย",
          "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ",
          "ยินยอมให้นำเสนอผลงานวิจัย",
          "ยินยอมให้นำเสนอผลงานทางวิชาการ"
        ],
        url: "../form_Memo/form_consent_research_presentation.php?id="
      },
      {
        keywords: [
          "ACADEMIC_PRESENTATION",
          "infor_academic_presentation.php",
          "form_memo_academic_1.php",
          "ขออนุมัติตัวบุคคลเพื่อไปนำเสนอผลงานวิจัย",
          "นำเสนอผลงานวิจัย"
        ],
        url: "../form_Memo/form_memo_academic_1.php?id="
      },
      {
        keywords: [
          "FREE_DOCUMENT",
          "free_document",
          "form_memo_free_document.php",
          "บันทึกข้อความทั่วไป"
        ],
        url: "../form_Memo/form_memo_free_document.php?id="
      },
      {
        keywords: [
          "MEMO",
          "form_Memo.php",
          "view_memo.php",
          "ขออนุมัติไปเข้ารับการฝึกอบรมหลักสูตร"
        ],
        url: "../documents/view_memo.php?id="
      }
    ];


    const matched = routes.find(route =>
      route.keywords.some(keyword => text.includes(keyword))
    );

    // ใช้ path จากฐานข้อมูลก่อน เฉพาะกรณีที่ไม่ใช่ view_memo.php
    // เพราะบางรายการส่ง view_memo.php มาเป็น fallback ทั้งที่จริงเป็น template อื่น
    if (directUrl !== "" && !isMemoPath) {
      return directUrl;
    }

    // ถ้ามี route ของ template เฉพาะ ให้ใช้ route นั้นก่อน ไม่ให้หล่นไป view_memo.php
    if (matched) {
      return matched.url + encodeURIComponent(docId);
    }

    // อนุญาตให้เปิด view_memo.php เฉพาะกรณีที่ path จากฐานข้อมูลเป็น view_memo จริงและหา template เฉพาะไม่เจอ
    if (directUrl !== "") {
      return directUrl;
    }

    return "../documents/view_memo.php?id=" + encodeURIComponent(docId);
  }

  function getDocumentPdfDownloadUrl(docId, routeHint = "") {
    return "../documents/auto_download_pdf.php?id=" + encodeURIComponent(docId) +
      "&hint=" + encodeURIComponent(routeHint || "");
  }


  function escapeHtmlAttr(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function cleanThaiFileName(value) {
    let name = String(value || "").trim();

    if (!name) {
      name = "เอกสาร";
    }

    name = name
      .replace(/[\\/:*?"<>|\r\n\t]+/g, " ")
      .replace(/\s+/g, " ")
      .trim();

    if (name.length > 80) {
      name = name.substring(0, 80).trim();
    }

    return name || "เอกสาร";
  }

  function getThaiWordFileName(docId, routeHint = "") {
    const text = String(routeHint || "").trim();

    const titleRules = [{
        keywords: ["ขอประเมินสถานประกอบการสหกิจ", "ประเมินสถานประกอบการ", "สถานประกอบการสหกิจ", "สหกิจศึกษา",
          "coop_evaluation"
        ],
        title: "ขอประเมินสถานประกอบการสหกิจ"
      },
      {
        keywords: ["ขอเข้าไปจัดกิจกรรมโครงการ", "จัดกิจกรรมโครงการ", "กิจกรรมโครงการ", "โครงการ", "project_activity"],
        title: "ขอเข้าไปจัดกิจกรรมโครงการ"
      },
      {
        keywords: ["หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์", "ขอความอนุเคราะห์ข้อมูล", "ปริญญานิพนธ์",
          "research_data"
        ],
        title: "หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์"
      },
      {
        keywords: ["หนังสือเรียนเชิญวิทยากร", "เรียนเชิญวิทยากร", "เชิญวิทยากร", "invite_speaker"],
        title: "หนังสือเรียนเชิญวิทยากร"
      },
      {
        keywords: ["ขออนุมัติใช้ห้องพักรับรอง", "ขอห้องพักรับรอง", "ห้องพักรับรอง", "room_request"],
        title: "ขออนุมัติใช้ห้องพักรับรอง"
      },
      {
        keywords: ["ขออนุมัติตัวบุคคลเป็นวิทยากร", "ตัวบุคคลเป็นวิทยากร", "เป็นวิทยากร", "speaker_workshop"],
        title: "ขออนุมัติตัวบุคคลเป็นวิทยากร"
      },
      {
        keywords: ["ขอเข้าเยี่ยมศึกษาดูงาน", "ศึกษาดูงาน", "เข้าเยี่ยมชม", "เยี่ยมชมศึกษาดูงาน", "SUT Wellness",
          "study_visit"
        ],
        title: "ขอเข้าเยี่ยมศึกษาดูงาน"
      },
      {
        keywords: ["หนังสือยินยอมให้นำเสนอผลงานวิจัย", "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ",
          "ยินยอมให้นำเสนอผลงานวิจัย", "ยินยอมให้นำเสนอผลงานทางวิชาการ", "หนังสือยินยอม",
          "consent_research_presentation"
        ],
        title: "หนังสือยินยอมให้นำเสนอผลงานวิจัย"
      },
      {
        keywords: ["นำเสนอผลงานวิจัย", "academic_presentation"],
        title: "นำเสนอผลงานวิจัย"
      }
    ];

    const matched = titleRules.find(rule =>
      rule.keywords.some(keyword => text.includes(keyword))
    );

    const baseName = cleanThaiFileName(matched ? matched.title : (text || "บันทึกข้อความ"));
    return baseName + "_เลขที่_" + String(docId || "").replace(/\D+/g, "") + ".docx";
  }

  function downloadWordFromHome(link) {
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
        const blobUrl = window.URL.createObjectURL(blob);
        const a = document.createElement("a");

        a.href = blobUrl;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();

        window.URL.revokeObjectURL(blobUrl);
      })
      .catch(() => {
        window.location.href = url;
      });

    return false;
  }

  function getDocumentWordDownloadUrl(docId, routeHint = "") {
    const text = String(routeHint || "").trim();

    const routes = [{
        keywords: ["ขอประเมินสถานประกอบการสหกิจ", "ประเมินสถานประกอบการ", "สถานประกอบการสหกิจ", "สหกิจศึกษา",
          "coop_evaluation"
        ],
        url: "../documents/download_word_coop_evaluation.php"
      },
      {
        keywords: ["ขอเข้าไปจัดกิจกรรมโครงการ", "จัดกิจกรรมโครงการ", "กิจกรรมโครงการ", "โครงการ", "project_activity"],
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
        keywords: ["ขออนุมัติตัวบุคคลเป็นวิทยากร", "ตัวบุคคลเป็นวิทยากร", "เป็นวิทยากร", "speaker_workshop"],
        url: "../documents/download_word_speaker.php"
      },
      {
        keywords: ["ขอเข้าเยี่ยมศึกษาดูงาน", "ศึกษาดูงาน", "เข้าเยี่ยมชม", "เยี่ยมชมศึกษาดูงาน", "SUT Wellness",
          "study_visit"
        ],
        url: "../documents/download_word_sut_wellness.php"
      },
      {
        keywords: ["หนังสือยินยอมให้นำเสนอผลงานวิจัย", "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ",
          "ยินยอมให้นำเสนอผลงานวิจัย", "ยินยอมให้นำเสนอผลงานทางวิชาการ", "หนังสือยินยอม",
          "consent_research_presentation"
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


function openDocument(docId, joinType = "", documentPath = "") {
  const currentDoc = dataAll.find(item => Number(item.document_id) === Number(docId));
  window.location.href = getDocumentUrl(
    docId,
    joinType,
    documentPath || (currentDoc ? currentDoc.documentPath : "")
  );
  return false;
}

  function showReviewLoadingPopup() {
    Swal.fire({
      html: `
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px 8px 10px;">
          <div style="
            width:58px;
            height:58px;
            border:7px solid #d8f3ef;
            border-top:7px solid #14b8a6;
            border-radius:50%;
            animation: reviewSpin 0.8s linear infinite;
            margin-bottom:18px;
          "></div>
          <div style="font-size:24px;font-weight:700;color:#0f766e;margin-bottom:8px;">
            กำลังบันทึกผลการตรวจสอบ...
          </div>
          <div style="font-size:14px;color:#64748b;">
            กรุณารอสักครู่ ระบบกำลังบันทึกข้อมูล
          </div>
        </div>
      `,
      width: 320,
      padding: "18px",
      showConfirmButton: false,
      allowOutsideClick: false,
      allowEscapeKey: false,
      customClass: {
        popup: "rounded-2xl"
      },
      didOpen: () => {
        if (!document.getElementById("review-loading-spin-style")) {
          const style = document.createElement("style");
          style.id = "review-loading-spin-style";
          style.innerHTML = `
            @keyframes reviewSpin {
              0% { transform: rotate(0deg); }
              100% { transform: rotate(360deg); }
            }
          `;
          document.head.appendChild(style);
        }
      }
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

      showReviewLoadingPopup();

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
            dataAll = dataAll.map(item => {
              if (Number(item.document_id) === Number(id)) {
                return {
                  ...item,
                  status: "done",
                  statusText: "ผ่านการตรวจสอบแล้ว",
                  statusClass: "bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-semibold"
                };
              }
              return item;
            });
            updateStatusCounts();
            renderList();
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

      showReviewLoadingPopup();

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
            dataAll = dataAll.map(item => {
              if (Number(item.document_id) === Number(id)) {
                return {
                  ...item,
                  status: "edit",
                  statusText: "รอการแก้ไข",
                  statusClass: "bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs font-semibold"
                };
              }
              return item;
            });
            updateStatusCounts();
            renderList();
            loadRequests();
          }
        });
    });
  }
  </script>
</body>

</html>