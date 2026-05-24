<?php  // pro_letter/admin/home.php
session_start();
if (!isset($_SESSION['role_id']) || !in_array((int)$_SESSION['role_id'], [1, 2])) {
  header('Location: ../login.html');
  exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();
$current = basename($_SERVER['PHP_SELF']);

$activeTab = $_GET['tab'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');

$where = [];
$params = [];

if ($activeTab === 'pending') {
  $where[] = "h.status IN ('submitted', 'reviewing')";
} elseif ($activeTab === 'approved') {
  $where[] = "h.status = 'approved'";
} elseif ($activeTab === 'rejected') {
  $where[] = "h.status = 'rejected'";
}

if ($dateFrom !== '') {
  $where[] = "DATE(h.history_at) >= ?";
  $params[] = $dateFrom;
}

if ($dateTo !== '') {
  $where[] = "DATE(h.history_at) <= ?";
  $params[] = $dateTo;
}

if ($keyword !== '') {
  $where[] = "(
    h.actor_name LIKE ?
    OR h.owner_name LIKE ?
    OR h.subject LIKE ?
    OR h.template_name LIKE ?
    OR h.action_label LIKE ?
    OR h.detail LIKE ?
  )";
  for ($i = 0; $i < 6; $i++) {
    $params[] = "%{$keyword}%";
  }
}

$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$historySql = "
  SELECT *
  FROM (
    SELECT
      al.log_id AS history_id,
      al.created_at AS history_at,
      al.action AS action_code,
      CASE
        WHEN al.action = 'SUBMITTED' THEN 'ส่งคำขอเอกสาร'
        WHEN al.action = 'CREATED' THEN 'สร้างเอกสาร'
        WHEN al.action = 'UPDATED' THEN 'แก้ไขเอกสาร'
        WHEN al.action = 'APPROVED' THEN 'อนุมัติเอกสาร'
        WHEN al.action = 'REJECTED' THEN 'ตีกลับเอกสาร'
        ELSE al.action
      END AS action_label,
      al.detail,
      d.document_id,
      d.doc_no,
      d.subject,
      d.status,
      t.template_name,
      actor.fullname AS actor_name,
      owner.fullname AS owner_name
    FROM audit_logs al
    LEFT JOIN documents d ON al.document_id = d.document_id
    LEFT JOIN users actor ON al.user_id = actor.user_id
    LEFT JOIN users owner ON d.owner_id = owner.user_id
    LEFT JOIN templates t ON d.template_id = t.template_id

    UNION ALL

    SELECT
      d.document_id AS history_id,
      d.created_at AS history_at,
      'CREATED' AS action_code,
      'สร้างเอกสาร' AS action_label,
      'สร้างรายการเอกสารเข้าสู่ระบบ' AS detail,
      d.document_id,
      d.doc_no,
      d.subject,
      d.status,
      t.template_name,
      owner.fullname AS actor_name,
      owner.fullname AS owner_name
    FROM documents d
    LEFT JOIN users owner ON d.owner_id = owner.user_id
    LEFT JOIN templates t ON d.template_id = t.template_id

    UNION ALL

    SELECT
      d.document_id AS history_id,
      d.updated_at AS history_at,
      'UPDATED' AS action_code,
      'แก้ไขเอกสาร' AS action_label,
      'มีการแก้ไขข้อมูลเอกสาร' AS detail,
      d.document_id,
      d.doc_no,
      d.subject,
      d.status,
      t.template_name,
      editor.fullname AS actor_name,
      owner.fullname AS owner_name
    FROM documents d
    LEFT JOIN users editor ON d.approved_by = editor.user_id
    LEFT JOIN users owner ON d.owner_id = owner.user_id
    LEFT JOIN templates t ON d.template_id = t.template_id
    WHERE d.updated_at IS NOT NULL
  ) h
  $whereSql
  ORDER BY h.history_at DESC
";

$stmt = $pdo->prepare($historySql);
$stmt->execute($params);
$filteredDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function statusBadge($status)
{
  if (in_array($status, ['submitted', 'reviewing'])) {
    return '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">รอตรวจสอบ</span>';
  }
  if ($status === 'approved') {
    return '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">อนุมัติแล้ว</span>';
  }
  if ($status === 'rejected') {
    return '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">ถูกตีกลับ</span>';
  }
  if ($status === 'draft') {
    return '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">ฉบับร่าง</span>';
  }
  return '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700">' . htmlspecialchars($status ?? '-') . '</span>';
}function thai_date($date)
{
  $time = strtotime($date);
  $d = date("d", $time);
  $m = date("m", $time);
  $y = date("Y", $time) + 543;
  return "$d/$m/$y";
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>รายการส่งคำขอ (admin)</title>
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

      <a href="/Pro_letter/admin/home.php">
        <div
          class="px-4 py-2 rounded-[11px] font-bold transition 
        <?= $current === 'home.php' ? 'bg-white text-teal-500 shadow' : 'text-white hover:bg-white hover:text-teal-500' ?>">
          หน้าหลัก
        </div>
      </a>

      <a href="/Pro_letter/admin/history_page.php">
        <div class="px-4 py-2 rounded-[11px] font-bold transition bg-white text-teal-500 shadow
        <?= $current === 'history_page.php' ?>">
          ประวัติการใช้งานเอกสาร
        </div>
      </a>
      <a href="/Pro_letter/admin/department_report_dashboard.php">
        <div
          class="px-4 py-2 rounded-[11px] font-bold transition
        <?= $current === 'department_report_dashboard.php' ? 'bg-white text-teal-500 shadow' : 'text-white hover:bg-white hover:text-teal-500' ?>">
          รายงานภาควิชา
        </div>
      </a>

      <!-- เมนู: ตั้งค่าระบบเริ่มต้น -->
      <div class=" relative">
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
          <a href="permission_management.php" class="block px-4 py-2 hover:bg-teal-100">กำหนดสิทธิ์ผู้ใช้งาน</a>
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
          <a href="../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
          <button onclick="closeMenu()" class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
            อยู่ต่อ
          </button>
        </div>
      </div>


      <div id="profileMenu" class="hidden absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-50">
        <a href="/Pro_letter/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
        <button onclick="closeMenu()"
          class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">อยู่ต่อ</button>
      </div>
    </div>

    </div>
  </header>


  <!-- Main -->
  <main class="max-w-7xl w-full px-8 mx-auto bg-white mt-4 mb-12 p-6 rounded shadow min-h-[70vh]">
    <!-- Tabs + ฟอร์มเลือกช่วงวัน -->
    <div class="flex items-center justify-between border-b mb-4">
      <!-- Tabs -->
      <div class="flex space-x-6">
        <a href="?tab=all&keyword=<?= urlencode($keyword) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
          class="px-4 py-2 rounded-t-md font-semibold <?= $activeTab === 'all' ? 'bg-teal-500 text-white' : 'text-gray-500' ?>">
          เอกสารทั้งหมด
        </a>
        <a href="?tab=pending&keyword=<?= urlencode($keyword) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
          class="px-4 py-2 rounded-t-md font-semibold <?= $activeTab === 'pending' ? 'bg-teal-500 text-white' : 'text-gray-500' ?>">
          รอตรวจสอบ
        </a>
        <a href="?tab=approved&keyword=<?= urlencode($keyword) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
          class="px-4 py-2 rounded-t-md font-semibold <?= $activeTab === 'approved' ? 'bg-teal-500 text-white' : 'text-gray-500' ?>">
          อนุมัติแล้ว
        </a>
        <a href="?tab=rejected&keyword=<?= urlencode($keyword) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
          class="px-4 py-2 rounded-t-md font-semibold <?= $activeTab === 'rejected' ? 'bg-teal-500 text-white' : 'text-gray-500' ?>">
          ถูกตีกลับ
        </a>
      </div>

      <!-- ฟอร์มเลือกช่วงวัน -->
      <form method="get" class="flex flex-wrap gap-2 items-center justify-end">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

        <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>"
          placeholder="ค้นหา ผู้ดำเนินการ / เจ้าของ / เรื่องเอกสาร" class="border rounded px-3 py-2 text-sm w-72">

        <label class="text-sm text-gray-600">จาก:</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
          class="border rounded px-2 py-2 text-sm">

        <label class="text-sm text-gray-600">ถึง:</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
          class="border rounded px-2 py-2 text-sm">

        <button type="submit" class="bg-teal-500 text-white px-4 py-2 rounded text-sm hover:bg-teal-600 transition">
          ค้นหา
        </button>

        <a href="history_page.php?tab=<?= htmlspecialchars($activeTab) ?>"
          class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-300 transition">
          ล้าง
        </a>
      </form>
    </div>

    <!-- Document Table -->
    <!-- Document History Table -->
    <div class="overflow-x-auto rounded-lg shadow">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-100 text-gray-700 text-sm">
          <tr>
            <th class="px-4 py-3 text-left font-semibold">วัน/เวลา</th>
            <th class="px-4 py-3 text-left font-semibold">ผู้ดำเนินการ</th>
            <th class="px-4 py-3 text-left font-semibold">เจ้าของเอกสาร</th>
            <th class="px-4 py-3 text-left font-semibold">เรื่องเอกสาร</th>
            <th class="px-4 py-3 text-left font-semibold">การกระทำ</th>
            <th class="px-4 py-3 text-left font-semibold">สถานะ</th>
            <th class="px-4 py-3 text-left font-semibold">รายละเอียด</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200 text-sm">
          <?php if (empty($filteredDocs)): ?>
          <tr>
            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
              ไม่พบประวัติการใช้งานเอกสารตามเงื่อนไขที่ค้นหา
            </td>
          </tr>
          <?php endif; ?>

          <?php foreach ($filteredDocs as $doc): ?>
          <tr class="hover:bg-gray-50 transition">
            <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
              <?= date("d/m/Y H:i", strtotime($doc['history_at'])) ?>
            </td>

            <td class="px-4 py-3">
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-full bg-teal-400 flex items-center justify-center font-bold text-white">
                  <?= htmlspecialchars(mb_substr($doc['actor_name'] ?? '-', 0, 1)) ?>
                </div>
                <span class="font-medium text-gray-800">
                  <?= htmlspecialchars($doc['actor_name'] ?? '-') ?>
                </span>
              </div>
            </td>

            <td class="px-4 py-3 text-gray-700">
              <?= htmlspecialchars($doc['owner_name'] ?? '-') ?>
            </td>

            <td class="px-4 py-3 text-gray-700 min-w-[260px]">
              <div class="font-semibold text-gray-800">
                <?= htmlspecialchars($doc['subject'] ?: 'ไม่ระบุเรื่องเอกสาร') ?>
              </div>
              <div class="text-xs text-gray-500 mt-1">
                <?= htmlspecialchars($doc['template_name'] ?? '-') ?>
                <?php if (!empty($doc['doc_no'])): ?>
                · เลขที่ <?= htmlspecialchars($doc['doc_no']) ?>
                <?php endif; ?>
              </div>
            </td>

            <td class="px-4 py-3">
              <span class="px-2 py-1 text-xs rounded-full bg-sky-100 text-sky-700">
                <?= htmlspecialchars($doc['action_label'] ?? '-') ?>
              </span>
            </td>

            <td class="px-4 py-3">
              <?= statusBadge($doc['status'] ?? '') ?>
            </td>

            <td class="px-4 py-3 text-gray-600 max-w-[260px]">
              <?= htmlspecialchars($doc['detail'] ?? '-') ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <script>
  const profileBtn = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");
  if (profileBtn && profileMenu) {
    profileBtn.addEventListener("click", () => {
      profileMenu.classList.toggle("hidden");
    });

    function closeMenu() {
      profileMenu.classList.add("hidden");
    }
    window.addEventListener("click", (e) => {
      if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
        profileMenu.classList.add("hidden");
      }
    });
  }

  function deleteLog(id) {
    if (confirm("คุณแน่ใจหรือไม่ว่าต้องการลบประวัติ?")) {
      window.location.href = "delete_log.php?id=" + id;
    }
  }
  </script>

  <script>
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
  </script>
</body>

</html>