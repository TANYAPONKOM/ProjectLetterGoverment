<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ?");
$stmt->execute([$id]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    die("ไม่พบข้อมูลภาควิชา");
}

// ✅ ดึงข้อมูลคณะ
$faculties = $pdo->query("SELECT faculty_id, faculty_name FROM faculties")->fetchAll(PDO::FETCH_ASSOC);

// ✅ ถ้ามีการ submit ฟอร์ม -> ทำการ UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faculty_id = $_POST['faculty_id'];
    $department_name = $_POST['department_name'];

    $stmt = $pdo->prepare("UPDATE departments SET faculty_id=?, department_name=? WHERE department_id=?");
    $stmt->execute([$faculty_id, $department_name, $id]);

    header("Location: department_Managerment.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>Edit Department</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <!-- Header -->
  <header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md">
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
  </header>

  <!-- Header Card -->
  <div class="max-w-3xl mx-auto mt-10 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="bg-teal-500 text-white text-center py-8 relative">
      <div class="flex justify-center">
        <div class="w-20 h-20 rounded-full bg-white flex items-center justify-center">
          <svg class="h-12 w-12 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
          </svg>
        </div>
      </div>
      <h1 class="text-3xl font-bold mt-4">การแก้ไขข้อมูลภาควิชา</h1>
      <p class="text-sm text-white/80">ปรับปรุงข้อมูลภาควิชาในระบบ</p>
    </div>

    <!-- Form -->
    <form method="POST" class="p-8 space-y-6">
      <div>
        <label class="block font-semibold text-gray-700 mb-1">เลือกคณะ</label>
        <select name="faculty_id" class="w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400"
          required>
          <option value="">-- เลือกคณะ --</option>
          <?php foreach ($faculties as $f): ?>
          <option value="<?= $f['faculty_id'] ?>"
            <?= $f['faculty_id'] == $department['faculty_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($f['faculty_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">ชื่อภาควิชา</label>
        <input type="text" name="department_name" value="<?= htmlspecialchars($department['department_name']) ?>"
          class="w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" required>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end space-x-3 pt-4">
        <a href="department_Managerment.php"
          class="px-4 py-2 rounded-lg bg-gray-300 text-gray-700 font-semibold hover:bg-gray-400 transition">
          ยกเลิก
        </a>
        <button type="submit"
          class="px-6 py-2 rounded-lg bg-teal-500 text-white font-semibold hover:bg-teal-600 shadow">
          บันทึก
        </button>
      </div>
    </form>
  </div>
</body>

</html>