<?php  // pro_letter/admin/department_report_dashboard.php
session_start();

if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
  header('Location: ../login.html');
  exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();
$current = basename($_SERVER['PHP_SELF']);

if (!function_exists('h')) {
  function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
  }
}

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$departmentId = $_GET['department_id'] ?? 'all';

/*
  จุดที่แก้:
  ห้าม JOIN users กับ documents ตรง ๆ แล้ว COUNT(d.document_id)
  เพราะถ้าภาควิชามี user 167 คน และมีเอกสาร 20 ฉบับ
  ผลลัพธ์จะกลายเป็น 167 x 20 = 3,340 ฉบับทันที

  วิธีที่ถูก:
  1) รวมจำนวนผู้ใช้ใน subquery แยก
  2) รวมจำนวนเอกสารใน subquery แยก
  3) ค่อยเอาผลรวมมา JOIN กับ departments
*/

$docDateSql = "";
$docParams = [];

if ($dateFrom !== '') {
  $docDateSql .= " AND DATE(d.created_at) >= ?";
  $docParams[] = $dateFrom;
}
if ($dateTo !== '') {
  $docDateSql .= " AND DATE(d.created_at) <= ?";
  $docParams[] = $dateTo;
}

$departmentWhereSql = "";
$departmentParams = [];
if ($departmentId !== '' && $departmentId !== 'all') {
  $departmentWhereSql = " WHERE dep.department_id = ?";
  $departmentParams[] = (int)$departmentId;
}

$departmentSql = "
  SELECT
    dep.department_id,
    dep.department_name,
    COALESCE(ua.total_users, 0) AS total_users,
    COALESCE(da.total_docs, 0) AS total_docs,
    COALESCE(da.submitter_count, 0) AS submitter_count,
    COALESCE(da.pending_docs, 0) AS pending_docs,
    COALESCE(da.approved_docs, 0) AS approved_docs,
    COALESCE(da.rejected_docs, 0) AS rejected_docs,
    da.last_used_at
  FROM departments dep
  LEFT JOIN (
    SELECT
      department_id,
      COUNT(DISTINCT user_id) AS total_users
    FROM users
    WHERE is_active = 1
      AND role_id = 3
      AND department_id IS NOT NULL
    GROUP BY department_id
  ) ua ON ua.department_id = dep.department_id
  LEFT JOIN (
    SELECT
      COALESCE(d.department_id, owner.department_id) AS department_id,
      COUNT(DISTINCT d.document_id) AS total_docs,
      COUNT(DISTINCT d.owner_id) AS submitter_count,
      SUM(CASE WHEN d.status IN ('submitted','reviewing') THEN 1 ELSE 0 END) AS pending_docs,
      SUM(CASE WHEN d.status = 'approved' THEN 1 ELSE 0 END) AS approved_docs,
      SUM(CASE WHEN d.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_docs,
      MAX(d.created_at) AS last_used_at
    FROM documents d
    LEFT JOIN users owner ON owner.user_id = d.owner_id
    WHERE 1=1 {$docDateSql}
    GROUP BY COALESCE(d.department_id, owner.department_id)
  ) da ON da.department_id = dep.department_id
  {$departmentWhereSql}
  ORDER BY total_docs DESC, dep.department_name ASC
";

$stmt = $pdo->prepare($departmentSql);
$stmt->execute(array_merge($docParams, $departmentParams));
$departmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$templateParams = [];
$templateDateSql = "";
if ($dateFrom !== '') {
  $templateDateSql .= " AND DATE(d.created_at) >= ?";
  $templateParams[] = $dateFrom;
}
if ($dateTo !== '') {
  $templateDateSql .= " AND DATE(d.created_at) <= ?";
  $templateParams[] = $dateTo;
}
$templateWhereSql = "";
if ($departmentId !== '' && $departmentId !== 'all') {
  $templateWhereSql = " AND dep.department_id = ?";
  $templateParams[] = (int)$departmentId;
}

$templateSql = "
  SELECT
    dep.department_name,
    COALESCE(t.template_name, 'ไม่ระบุประเภทเอกสาร') AS template_name,
    COUNT(DISTINCT d.document_id) AS total_docs
  FROM documents d
  LEFT JOIN users owner ON owner.user_id = d.owner_id
  LEFT JOIN departments dep ON dep.department_id = COALESCE(d.department_id, owner.department_id)
  LEFT JOIN templates t ON d.template_id = t.template_id
  WHERE 1=1 {$templateDateSql} {$templateWhereSql}
  GROUP BY dep.department_id, dep.department_name, t.template_id, t.template_name
  ORDER BY total_docs DESC
  LIMIT 10
";
$templateStmt = $pdo->prepare($templateSql);
$templateStmt->execute($templateParams);
$templateRows = $templateStmt->fetchAll(PDO::FETCH_ASSOC);

$departmentList = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_id ASC")->fetchAll(PDO::FETCH_ASSOC);

$totalDocs = array_sum(array_map(fn($r) => (int)$r['total_docs'], $departmentRows));
$totalUsers = array_sum(array_map(fn($r) => (int)$r['total_users'], $departmentRows));
$totalApproved = array_sum(array_map(fn($r) => (int)$r['approved_docs'], $departmentRows));
$totalPending = array_sum(array_map(fn($r) => (int)$r['pending_docs'], $departmentRows));
$totalRejected = array_sum(array_map(fn($r) => (int)$r['rejected_docs'], $departmentRows));
$activeDepartments = count(array_filter($departmentRows, fn($r) => (int)$r['total_docs'] > 0));
$topDepartment = '-';
foreach ($departmentRows as $r) {
  if ((int)$r['total_docs'] > 0) {
    $topDepartment = $r['department_name'];
    break;
  }
}

$chartLabels = array_map(fn($r) => $r['department_name'], $departmentRows);
$chartTotalDocs = array_map(fn($r) => (int)$r['total_docs'], $departmentRows);
$chartApproved = array_map(fn($r) => (int)$r['approved_docs'], $departmentRows);
$chartPending = array_map(fn($r) => (int)$r['pending_docs'], $departmentRows);
$chartRejected = array_map(fn($r) => (int)$r['rejected_docs'], $departmentRows);

function statusPercent($part, $total) {
  $total = (int)$total;
  if ($total <= 0) return 0;
  return round(((int)$part / $total) * 100, 1);
}

function thaiDateTime($date) {
  if (empty($date)) return '-';
  return date('d/m/Y H:i', strtotime($date));
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>รายงานการใช้งานเอกสารตามภาควิชา</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100">
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
          class="px-4 py-2 rounded-[11px] font-bold transition <?= $current === 'home.php' ? 'bg-white text-teal-500 shadow' : 'text-white hover:bg-white hover:text-teal-500' ?>">
          หน้าหลัก
        </div>
      </a>

      <a href="/Pro_letter/admin/history_page.php">
        <div
          class="px-4 py-2 rounded-[11px] font-bold transition <?= $current === 'history_page.php' ? 'bg-white text-teal-500 shadow' : 'text-white hover:bg-white hover:text-teal-500' ?>">
          ประวัติการใช้งานเอกสาร
        </div>
      </a>

      <a href="/Pro_letter/admin/department_report_dashboard.php">
        <div class="px-4 py-2 rounded-[11px] font-bold transition bg-white text-teal-500 shadow">
          รายงานภาควิชา
        </div>
      </a>

      <div class="relative">
        <button id="templateBtn"
          class="px-4 py-2 rounded-[11px] font-bold transition text-white hover:bg-white hover:text-teal-500 flex items-center space-x-1">
          <span>ตั้งค่าระบบเริ่มต้น</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div id="templateMenu" class="hidden absolute bg-white text-gray-700 mt-1 rounded-lg shadow-lg w-56 z-50">
          <a href="form_Templates.php" class="block px-4 py-2 hover:bg-teal-100">การจัดการเทมเพลต</a>
          <a href="department_Managerment.php" class="block px-4 py-2 hover:bg-teal-100">การจัดการภาควิชา</a>
          <a href="user_Managerment.php" class="block px-4 py-2 hover:bg-teal-100">กำหนดสิทธิ์ผู้ใช้งาน</a>
        </div>
      </div>

      <div class="relative">
        <button id="profileBtn"
          class="bg-white text-teal-500 px-4 py-2 rounded-[11px] shadow flex items-center space-x-2 hover:bg-gray-100">
          <div class="text-right leading-tight">
            <div class="font-bold text-[14px]"><?= h($_SESSION['fullname'] ?? '') ?></div>
            <div class="text-[12px]"><?= h($_SESSION['role_name'] ?? '') ?></div>
          </div>
          <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
              stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M5.121 17.804A13.937 13.937 0 0112 15c2.33 0 4.487.577 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
        </button>

        <div id="profileMenu" class="hidden absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-50">
          <a href="/Pro_letter/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
          <button onclick="closeMenu()"
            class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">อยู่ต่อ</button>
        </div>
      </div>
    </div>
  </header>

  <main class="max-w-7xl w-full px-8 mx-auto mt-4 mb-12">
    <div class="bg-white rounded-xl shadow p-6 mb-5">
      <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">รายงานการใช้งานเอกสารแยกตามภาควิชา</h1>
          <p class="text-sm text-gray-500 mt-1">
            สำหรับ Admin วิเคราะห์จำนวนเอกสาร ผู้ใช้งาน และสถานะคำขอของแต่ละภาควิชา
          </p>
        </div>

        <form method="get" class="flex flex-wrap gap-2 items-end justify-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">ภาควิชา</label>
            <select name="department_id" class="border rounded-lg px-3 py-2 text-sm min-w-[240px]">
              <option value="all">ทุกภาควิชา</option>
              <?php foreach ($departmentList as $dep): ?>
              <option value="<?= (int)$dep['department_id'] ?>"
                <?= (string)$departmentId === (string)$dep['department_id'] ? 'selected' : '' ?>>
                <?= h($dep['department_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">จากวันที่</label>
            <input type="date" name="date_from" value="<?= h($dateFrom) ?>" class="border rounded-lg px-3 py-2 text-sm">
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">ถึงวันที่</label>
            <input type="date" name="date_to" value="<?= h($dateTo) ?>" class="border rounded-lg px-3 py-2 text-sm">
          </div>

          <button type="submit"
            class="bg-teal-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-teal-600">
            ค้นหา
          </button>

          <a href="department_report_dashboard.php"
            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-300">
            ล้าง
          </a>
        </form>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="rounded-xl border border-teal-100 bg-teal-50 p-4">
          <div class="text-sm text-teal-700">เอกสารทั้งหมด</div>
          <div class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($totalDocs) ?></div>
        </div>
        <div class="rounded-xl border border-sky-100 bg-sky-50 p-4">
          <div class="text-sm text-sky-700">ผู้ใช้งานในภาควิชา</div>
          <div class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($totalUsers) ?></div>
        </div>
        <div class="rounded-xl border border-yellow-100 bg-yellow-50 p-4">
          <div class="text-sm text-yellow-700">รอตรวจสอบ</div>
          <div class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($totalPending) ?></div>
        </div>
        <div class="rounded-xl border border-green-100 bg-green-50 p-4">
          <div class="text-sm text-green-700">ผ่านการตรวจสอบ</div>
          <div class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($totalApproved) ?></div>
        </div>
        <div class="rounded-xl border border-purple-100 bg-purple-50 p-4">
          <div class="text-sm text-purple-700">ภาควิชาที่มีการใช้งาน</div>
          <div class="text-3xl font-bold text-gray-800 mt-1"><?= number_format($activeDepartments) ?></div>
        </div>
      </div>

      <div class="mt-4 text-sm text-gray-600">
        ภาควิชาที่ใช้งานสูงสุด: <span class="font-semibold text-gray-800"><?= h($topDepartment) ?></span>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
      <div class="xl:col-span-2 bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold text-gray-800">จำนวนเอกสารต่อภาควิชา</h2>
          <span class="text-xs text-gray-500">อ้างอิงจาก documents.department_id</span>
        </div>
        <canvas id="departmentBarChart" height="110"></canvas>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">สัดส่วนสถานะเอกสาร</h2>
        <canvas id="statusDoughnutChart" height="180"></canvas>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
      <div class="xl:col-span-2 bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">ตารางสรุปตามภาควิชา</h2>
        <div class="overflow-x-auto rounded-lg border">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100 text-gray-700 text-sm">
              <tr>
                <th class="px-4 py-3 text-left font-semibold">ภาควิชา</th>
                <th class="px-4 py-3 text-center font-semibold">ผู้ใช้</th>
                <th class="px-4 py-3 text-center font-semibold">ผู้ยื่นจริง</th>
                <th class="px-4 py-3 text-center font-semibold">ทั้งหมด</th>
                <th class="px-4 py-3 text-center font-semibold">รอตรวจสอบ</th>
                <th class="px-4 py-3 text-center font-semibold">ผ่าน</th>
                <th class="px-4 py-3 text-center font-semibold">ไม่ผ่าน</th>
                <th class="px-4 py-3 text-center font-semibold">% ผ่าน</th>
                <th class="px-4 py-3 text-left font-semibold">ใช้งานล่าสุด</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100 text-sm">
              <?php if (empty($departmentRows)): ?>
              <tr>
                <td colspan="9" class="px-4 py-6 text-center text-gray-500">ไม่พบข้อมูล</td>
              </tr>
              <?php endif; ?>

              <?php foreach ($departmentRows as $row): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-semibold text-gray-800 min-w-[260px]"><?= h($row['department_name']) ?></td>
                <td class="px-4 py-3 text-center"><?= number_format((int)$row['total_users']) ?></td>
                <td class="px-4 py-3 text-center"><?= number_format((int)$row['submitter_count']) ?></td>
                <td class="px-4 py-3 text-center font-semibold"><?= number_format((int)$row['total_docs']) ?></td>
                <td class="px-4 py-3 text-center text-yellow-700"><?= number_format((int)$row['pending_docs']) ?></td>
                <td class="px-4 py-3 text-center text-green-700"><?= number_format((int)$row['approved_docs']) ?></td>
                <td class="px-4 py-3 text-center text-red-700"><?= number_format((int)$row['rejected_docs']) ?></td>
                <td class="px-4 py-3 text-center"><?= statusPercent($row['approved_docs'], $row['total_docs']) ?>%</td>
                <td class="px-4 py-3 text-gray-600 min-w-[130px]"><?= h(thaiDateTime($row['last_used_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">ประเภทเอกสารที่ถูกใช้มากสุด</h2>
        <div class="space-y-3">
          <?php if (empty($templateRows)): ?>
          <div class="text-sm text-gray-500">ไม่พบข้อมูลประเภทเอกสาร</div>
          <?php endif; ?>

          <?php foreach ($templateRows as $item): ?>
          <div class="border rounded-lg p-3">
            <div class="text-sm font-semibold text-gray-800"><?= h($item['template_name']) ?></div>
            <div class="text-xs text-gray-500 mt-1"><?= h($item['department_name'] ?? 'ไม่ระบุภาควิชา') ?></div>
            <div class="mt-2 flex items-center justify-between text-sm">
              <span class="text-gray-500">จำนวนเอกสาร</span>
              <span class="font-bold text-teal-600"><?= number_format((int)$item['total_docs']) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>

  <script>
  const chartLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
  const chartTotalDocs = <?= json_encode($chartTotalDocs) ?>;
  const chartApproved = <?= json_encode($chartApproved) ?>;
  const chartPending = <?= json_encode($chartPending) ?>;
  const chartRejected = <?= json_encode($chartRejected) ?>;

  const departmentBarChart = document.getElementById("departmentBarChart");
  if (departmentBarChart) {
    new Chart(departmentBarChart, {
      type: "bar",
      data: {
        labels: chartLabels,
        datasets: [{
            label: "เอกสารทั้งหมด",
            data: chartTotalDocs,
            backgroundColor: "#14b8a6"
          },
          {
            label: "ผ่านการตรวจสอบ",
            data: chartApproved,
            backgroundColor: "#22c55e"
          },
          {
            label: "รอตรวจสอบ",
            data: chartPending,
            backgroundColor: "#f59e0b"
          },
          {
            label: "ไม่ผ่าน",
            data: chartRejected,
            backgroundColor: "#ef4444"
          }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: "bottom"
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          }
        }
      }
    });
  }

  const statusDoughnutChart = document.getElementById("statusDoughnutChart");
  if (statusDoughnutChart) {
    new Chart(statusDoughnutChart, {
      type: "doughnut",
      data: {
        labels: ["รอตรวจสอบ", "ผ่านการตรวจสอบ", "ไม่ผ่าน"],
        datasets: [{
          data: [<?= (int)$totalPending ?>, <?= (int)$totalApproved ?>, <?= (int)$totalRejected ?>],
          backgroundColor: ["#f59e0b", "#22c55e", "#ef4444"]
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: "bottom"
          }
        }
      }
    });
  }

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

  const templateBtn = document.getElementById("templateBtn");
  const templateMenu = document.getElementById("templateMenu");
  if (templateBtn && templateMenu) {
    templateBtn.addEventListener("click", () => {
      templateMenu.classList.toggle("hidden");
    });

    document.addEventListener("click", (e) => {
      if (!templateBtn.contains(e.target) && !templateMenu.contains(e.target)) {
        templateMenu.classList.add("hidden");
      }
    });
  }
  </script>
</body>

</html>