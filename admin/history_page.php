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
  <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>


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
  function deleteLog(id) {
    if (confirm("คุณแน่ใจหรือไม่ว่าต้องการลบประวัติ?")) {
      window.location.href = "delete_log.php?id=" + id;
    }
  </script>
</body>

</html>