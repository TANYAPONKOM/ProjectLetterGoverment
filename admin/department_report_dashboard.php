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
    doc_type.template_name,
    COUNT(DISTINCT doc_type.document_id) AS total_docs
  FROM (
    SELECT
      d.document_id,
      COALESCE(d.department_id, owner.department_id) AS department_id,
      CASE
        WHEN d.document_type_name IS NOT NULL AND TRIM(d.document_type_name) <> ''
          THEN d.document_type_name

        WHEN vf.has_free_doc = 1
          THEN 'เอกสารอิสระ'

        WHEN vf.has_consent_research_presentation = 1
          THEN 'หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ'

        WHEN vf.has_coop_evaluation = 1
          THEN 'ขอประเมินสถานประกอบการสหกิจ'

        WHEN vf.has_project_activity = 1
          THEN 'ขอเข้าไปจัดกิจกรรมโครงการ'

        WHEN vf.has_research_data = 1
          THEN 'หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์'

        WHEN vf.has_room_request = 1
          THEN 'ขอห้องพักรับรอง'

        WHEN vf.has_invite_speaker = 1
          THEN 'หนังสือเรียนเชิญวิทยากร'

        WHEN vf.has_speaker_workshop = 1
          THEN 'ขออนุมัติตัวบุคคลเป็นวิทยากร'

        WHEN vf.has_study_visit = 1
          THEN 'ขอเข้าเยี่ยมศึกษาดูงาน'

        WHEN vf.has_academic_presentation = 1
          OR d.subject LIKE '%นำเสนอ%'
          THEN 'ขออนุมัติตัวบุคคลเพื่อไปนำเสนอผลงานวิจัย'

        WHEN d.subject LIKE '%ยินยอม%' AND d.subject LIKE '%นำเสนอ%'
          THEN 'หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ'

        WHEN d.subject LIKE '%ห้องพัก%'
          THEN 'ขอห้องพักรับรอง'

        WHEN d.subject LIKE '%สหกิจ%'
          THEN 'ขอประเมินสถานประกอบการสหกิจ'

        WHEN d.subject LIKE '%เข้าเยี่ยม%' OR d.subject LIKE '%ศึกษาดูงาน%'
          THEN 'ขอเข้าเยี่ยมศึกษาดูงาน'

        WHEN d.subject LIKE '%เชิญ%' AND d.subject LIKE '%วิทยากร%'
          THEN 'หนังสือเรียนเชิญวิทยากร'

        WHEN d.subject LIKE '%เป็นวิทยากร%'
          THEN 'ขออนุมัติตัวบุคคลเป็นวิทยากร'

        WHEN d.subject LIKE '%ขอความอนุเคราะห์%' AND d.subject LIKE '%วิจัย%'
          THEN 'หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์'

        WHEN d.subject LIKE '%โครงการ%' OR d.subject LIKE '%กิจกรรม%'
          THEN 'ขอเข้าไปจัดกิจกรรมโครงการ'

        ELSE COALESCE(t.template_name, 'ขออนุมัติไปเข้ารับการฝึกอบรมหลักสูตร')
      END AS template_name
    FROM documents d
    LEFT JOIN users owner ON owner.user_id = d.owner_id
    LEFT JOIN templates t ON d.template_id = t.template_id
    LEFT JOIN (
      SELECT
        document_id,

        MAX(CASE WHEN field_id IN (94,95,96,97,98,99,100,101,102)
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_free_doc,

        MAX(CASE WHEN field_id = 17
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_consent_research_presentation,

        MAX(CASE WHEN field_id IN (70,71,72,73,74,75,76,77,78,79,80,81,82,83,90,91)
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_coop_evaluation,

        MAX(CASE WHEN field_id IN (58,59,60,61,62,63,64,65,66,67,68,69,88,89)
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_project_activity,

        MAX(CASE WHEN field_id IN (40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,92,93)
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_research_data,

        MAX(CASE WHEN field_id IN (26,27,28,29,30,31,32,33,34,35,36,37)
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_room_request,

        MAX(CASE WHEN field_id IN (38,39,84,85)
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_invite_speaker,

        MAX(CASE WHEN field_id IN (18,19,21,23,24,25)
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_speaker_workshop,

        MAX(CASE WHEN field_id IN (56,57,86,87)
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_study_visit,

        MAX(CASE WHEN (
                    field_id IN (13,14,15,16)
                    OR (field_id = 4 AND value_text LIKE '%นำเสนอ%')
                  )
                  AND TRIM(COALESCE(value_text, '')) <> '' THEN 1 ELSE 0 END) AS has_academic_presentation
      FROM document_values
      GROUP BY document_id
    ) vf ON vf.document_id = d.document_id
    WHERE 1=1 {$templateDateSql}
  ) doc_type
  LEFT JOIN departments dep ON dep.department_id = doc_type.department_id
  WHERE 1=1 {$templateWhereSql}
  GROUP BY doc_type.template_name
  ORDER BY total_docs DESC, doc_type.template_name ASC
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
  <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>

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
            <div class="text-xs text-gray-500 mt-1">
              <?= ($departmentId !== '' && $departmentId !== 'all') ? 'เฉพาะภาควิชาที่เลือก' : 'รวมทุกภาควิชา' ?></div>
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
  </script>
</body>

</html>