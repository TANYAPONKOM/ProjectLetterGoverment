<?php  // pro_letter/officer/home.php
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
        WHEN al.action = 'REVIEW_PASSED' THEN 'ผ่านการตรวจสอบ'
        WHEN al.action = 'REVIEW_FAILED' THEN 'ไม่ผ่านการตรวจสอบ'
        WHEN al.action = 'REVIEW_COMMENT' THEN 'บันทึกความคิดเห็นผู้ตรวจเอกสาร'
        WHEN al.action = 'APPROVED' THEN 'อนุมัติเอกสาร'
        WHEN al.action = 'REJECTED' THEN 'ตีกลับเอกสาร'
        ELSE al.action
            END AS action_label,
      CASE
        WHEN al.action IN ('REVIEW_FAILED', 'REJECTED') THEN
          COALESCE(
            (
              SELECT rc.detail
              FROM audit_logs rc
              WHERE rc.document_id = al.document_id
                AND rc.action = 'REVIEW_COMMENT'
                AND rc.detail IS NOT NULL
                AND TRIM(rc.detail) <> ''
                AND rc.log_id < al.log_id
              ORDER BY rc.created_at DESC, rc.log_id DESC
              LIMIT 1
            ),
            al.detail
          )
        ELSE al.detail
      END AS detail,
      d.document_id,
      d.doc_no,
      d.subject,
      CASE
        WHEN al.action IN ('REVIEW_PASSED', 'APPROVED') THEN 'approved'
        WHEN al.action IN ('REVIEW_FAILED', 'REJECTED') THEN 'rejected'
        WHEN al.action IN ('SUBMITTED', 'REVIEW_COMMENT') THEN 'submitted'
        WHEN al.action IN ('CREATED') THEN 'draft'
        WHEN al.action = 'UPDATED' THEN
          CASE
            WHEN COALESCE(actor.role_id, 0) IN (1, 2) THEN 'submitted'
            ELSE 'draft'
          END
        ELSE d.status
      END AS status,
      t.template_name,
      COALESCE(actor.fullname, owner.fullname, 'ไม่ระบุผู้ดำเนินการ') AS actor_name,
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
      d.status AS status,
      t.template_name,
      owner.fullname AS actor_name,
      owner.fullname AS owner_name
    FROM documents d
    LEFT JOIN users owner ON d.owner_id = owner.user_id
    LEFT JOIN templates t ON d.template_id = t.template_id
  ) h
  $whereSql
  ORDER BY h.history_at DESC
";

$stmt = $pdo->prepare($historySql);
$stmt->execute($params);
$filteredDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$totalDocs = (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$approvedDocs = (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'approved'")->fetchColumn();
$pendingDocs = (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE status IN ('submitted', 'reviewing')")->fetchColumn();
$rejectedDocs = (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'rejected'")->fetchColumn();

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
  <title>ประวัติการใช้งานเอกสาร</title>
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


  <!-- Main -->
  <main class="max-w-7xl w-full px-8 mx-auto bg-white mt-4 mb-12 p-6 rounded shadow min-h-[70vh]">

    <!-- Operation History -->
    <div class="mb-5">
      <h2 class="text-xl font-bold mb-4">ประวัติการใช้งานเอกสาร</h2>

      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-teal-500">
          <div class="text-sm text-gray-500">จำนวนผู้ใช้งาน</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($totalUsers) ?></div>
        </div>

        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-sky-500">
          <div class="text-sm text-gray-500">เอกสารทั้งหมด</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($totalDocs) ?></div>
        </div>

        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-yellow-400">
          <div class="text-sm text-gray-500">รอตรวจสอบ</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($pendingDocs) ?></div>
        </div>

        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-green-500">
          <div class="text-sm text-gray-500">อนุมัติแล้ว</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($approvedDocs) ?></div>
        </div>

        <div class="bg-white rounded-xl shadow p-4 border-l-4 border-red-500">
          <div class="text-sm text-gray-500">ถูกตีกลับ</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($rejectedDocs) ?></div>
        </div>
      </div>
    </div>

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
          ผ่านการตรวจสอบ
        </a>
        <a href="?tab=rejected&keyword=<?= urlencode($keyword) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
          class="px-4 py-2 rounded-t-md font-semibold <?= $activeTab === 'rejected' ? 'bg-teal-500 text-white' : 'text-gray-500' ?>">
          รอแก้ไข
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