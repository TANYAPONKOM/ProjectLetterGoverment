<?php //department_Managerment.php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();
$current = basename($_SERVER['PHP_SELF']);

// ดึงข้อมูลภาควิชาทั้งหมด
$sql = "SELECT d.department_id, d.faculty_id, d.department_name,
        f.faculty_name
        FROM departments d
        LEFT JOIN faculties f ON d.faculty_id = f.faculty_id
        ORDER BY d.department_id ASC";
$stmt = $pdo->query($sql);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>จัดการภาควิชา</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <!-- Header -->
  <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>

  <!-- Main Content -->
  <main class="max-w-6xl w-full px-8 mx-auto bg-white mt-6 mb-12 p-6 rounded shadow min-h-[85vh]">
    <div class="flex justify-between items-center mb-4 border-b pb-2">
      <h2 class="text-lg font-bold">การจัดการข้อมูลภาควิชา</h2>
      <button onclick="confirmUserAction('add')" class="px-3 py-1 bg-teal-500 text-white rounded">+ เพิ่ม</button>
    </div>

    <table class="w-full text-sm text-left border-separate border-spacing-y-2">
      <thead class="text-gray-600 bg-gray-100">
        <tr>
          <th class="px-4 py-2">ชื่อภาควิชา</th>
          <th class="px-4 py-2">คณะ</th>
          <th class="px-4 py-2 text-center">การจัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($departments): ?>
        <?php foreach ($departments as $row): ?>
        <tr class="bg-white shadow-sm rounded-lg">
          <!-- Avatar + ชื่อภาควิชา -->
          <td class="px-4 py-3 flex items-center space-x-3">
            <div class="w-8 h-8 rounded-full bg-teal-500 text-white flex items-center justify-center font-bold">
              <?= strtoupper(mb_substr($row['department_name'],0,1)) ?>
            </div>
            <span class="font-medium text-gray-800"><?= htmlspecialchars($row['department_name']) ?></span>
          </td>

          <!-- คณะ -->
          <td class="px-4 py-3 text-gray-700">
            <?= htmlspecialchars($row['faculty_name'] ?? '-') ?>
          </td>

          <!-- ปุ่มจัดการ -->
          <td class="px-4 py-3 text-center">
            <div class="flex justify-center space-x-2">
              <!-- ปุ่มแก้ไข -->
              <button onclick="confirmUserAction('edit', <?= $row['department_id'] ?>)"
                class="w-10 h-10 flex items-center justify-center rounded-full bg-purple-100 hover:bg-purple-200 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 20h9M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                </svg>
              </button>

              <!-- ปุ่มลบ -->
              <button onclick="confirmUserAction('delete', <?= $row['department_id'] ?>)"
                class="w-10 h-10 flex items-center justify-center rounded-full bg-red-100 hover:bg-red-200 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8" />
                </svg>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr>
          <td colspan="3" class="text-center py-4 text-gray-500">ไม่พบข้อมูลภาควิชา</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>

  <script>
  function confirmUserAction(action, id = null) {
    if (action === "add") {
      window.location.href = "department_Add.php";
    } else if (action === "edit") {
      window.location.href = "department_Edit.php?id=" + id;
    } else if (action === "delete") {
      if (confirm("คุณแน่ใจว่าต้องการลบภาควิชานี้หรือไม่?")) {
        window.location.href = "department_Delete.php?id=" + id;
      }
  </script>

</body>

</html>