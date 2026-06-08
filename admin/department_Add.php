<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

// ✅ ดึงข้อมูลคณะ
$faculties = $pdo->query("SELECT faculty_id, faculty_name FROM faculties")->fetchAll(PDO::FETCH_ASSOC);

// ✅ ฟอร์มเพิ่มภาควิชา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    $faculty_id = $_POST['faculty_id'] ?? '';
    $department_name = trim($_POST['department_name'] ?? '');
    if ($faculty_id && $department_name) {
        $stmt = $pdo->prepare("INSERT INTO departments (faculty_id, department_name) VALUES (?, ?)");
        $stmt->execute([$faculty_id, $department_name]);
        header("Location: department_Managerment.php?success=add");
        exit;
    }
}

// ✅ ฟอร์มเพิ่มคณะ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    $faculty_name = trim($_POST['faculty_name'] ?? '');
    if ($faculty_name) {
        $stmt = $pdo->prepare("INSERT INTO faculties (faculty_name) VALUES (?)");
        $stmt->execute([$faculty_name]);
        header("Location: department_Add.php");
        exit;
    }
}

// ✅ ลบคณะ
if (isset($_GET['delete_faculty'])) {
    $fid = (int)$_GET['delete_faculty'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE faculty_id=?");
    $check->execute([$fid]);
    if ($check->fetchColumn() > 0) {
        echo "<script>alert('ไม่สามารถลบคณะได้ เนื่องจากยังมีภาควิชาผูกอยู่');window.location='department_Add.php';</script>";
        exit;
    }
    $pdo->prepare("DELETE FROM faculties WHERE faculty_id=?")->execute([$fid]);
    header("Location: department_Add.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>เพิ่มภาควิชา</title>
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
      <h1 class="text-3xl font-bold mt-4">การเพิ่มข้อมูลภาควิชา</h1>
      <p class="text-sm text-white/80">กรอกข้อมูลเพื่อเพิ่มคณะและภาควิชาใหม่เข้าสู่ระบบ</p>
    </div>

    <!-- Form เพิ่มคณะ -->
    <form method="POST" class="p-8 space-y-6 border-b">
      <input type="hidden" name="add_faculty" value="1">
      <h2 class="text-xl font-bold text-purple-600">เพิ่มคณะ</h2>
      <div>
        <label class="block font-semibold text-gray-700 mb-1">ชื่อคณะ</label>
        <input type="text" name="faculty_name"
          class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-400"
          placeholder="Faculty Name" required>
      </div>
      <div class="flex justify-end space-x-3">
        <!-- <a href="department_Add.php"
                    class="px-4 py-2 rounded-lg bg-gray-300 text-gray-700 font-semibold hover:bg-gray-400 transition">
                    ยกเลิก
                </a> -->
        <button type="submit"
          class="px-6 py-2 rounded-lg bg-purple-500 text-white font-semibold hover:bg-purple-600 shadow">
          บันทึก
        </button>
      </div>
    </form>

    <!-- Form เพิ่มภาควิชา -->
    <form method="POST" class="p-8 space-y-6">
      <input type="hidden" name="add_department" value="1">
      <h2 class="text-xl font-bold text-teal-600">เพิ่มภาควิชา</h2>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">เลือกคณะ</label>
        <select name="faculty_id"
          class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400" required>
          <option value="">-- เลือกคณะ --</option>
          <?php foreach ($faculties as $f): ?>
          <option value="<?= $f['faculty_id'] ?>"><?= htmlspecialchars($f['faculty_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">ชื่อภาควิชา</label>
        <input type="text" name="department_name"
          class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400"
          placeholder="Department Name" required>
      </div>

      <div class="flex justify-end space-x-3">
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

    <!-- ตารางแสดงคณะ -->
    <div class="p-8">
      <h2 class="text-lg font-bold mb-4">📋 รายการคณะ</h2>
      <table class="w-full border-collapse border border-gray-200 text-sm rounded-lg overflow-hidden shadow-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="border px-3 py-2">ชื่อคณะ</th>
            <th class="border px-3 py-2 text-center">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($faculties as $f): ?>
          <tr class="hover:bg-gray-50">
            <td class="border px-3 py-2"><?= htmlspecialchars($f['faculty_name']) ?></td>
            <td class="border px-3 py-2 text-center">
              <a href="?delete_faculty=<?= $f['faculty_id'] ?>" onclick="return confirm('คุณแน่ใจว่าต้องการลบคณะนี้?')"
                class="px-3 py-1 bg-red-500 text-white rounded-lg text-xs hover:bg-red-600">
                ลบ
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($faculties)): ?>
          <tr>
            <td colspan="2" class="text-center py-3 text-gray-500">ไม่มีข้อมูลคณะ</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>

</html>