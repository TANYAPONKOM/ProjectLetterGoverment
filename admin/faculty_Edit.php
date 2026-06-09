<?php //faculty_Edit.php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$faculty_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($faculty_id <= 0) {
    header("Location: department_Managerment.php?error=invalid_id");
    exit;
}

$stmt = $pdo->prepare("SELECT faculty_id, faculty_name, dean_name, dean_position FROM faculties WHERE faculty_id = ?");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
    header("Location: department_Managerment.php?error=not_found");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faculty_name = trim($_POST['faculty_name'] ?? '');
    $dean_name = trim($_POST['dean_name'] ?? '');
    $dean_position = trim($_POST['dean_position'] ?? '');

    if ($faculty_name !== '') {
        $update = $pdo->prepare("
            UPDATE faculties
            SET faculty_name = ?,
                dean_name = ?,
                dean_position = ?
            WHERE faculty_id = ?
        ");
        $update->execute([$faculty_name, $dean_name, $dean_position, $faculty_id]);

        header("Location: department_Managerment.php?success=faculty_edit");
        exit;
    }

    $error = "กรุณากรอกชื่อคณะ";
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>แก้ไขข้อมูลคณะ</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>

  <main class="max-w-3xl mx-auto bg-white mt-10 mb-12 rounded-xl shadow-lg overflow-hidden">
    <div class="bg-teal-500 text-white text-center py-8">
      <h1 class="text-3xl font-bold">แก้ไขข้อมูลคณะ</h1>
      <p class="text-sm text-white/80 mt-1">แก้ไขชื่อคณะ ชื่อคณบดี และตำแหน่งคณบดี</p>
    </div>

    <form method="POST" class="p-8 space-y-6">
      <?php if (!empty($error)): ?>
      <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">ชื่อคณะ</label>
        <input type="text" name="faculty_name" value="<?= htmlspecialchars($faculty['faculty_name'] ?? '') ?>"
          class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400" required>
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">ชื่อคณบดี</label>
        <input type="text" name="dean_name" value="<?= htmlspecialchars($faculty['dean_name'] ?? '') ?>"
          class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400"
          placeholder="Dean Name">
      </div>

      <div>
        <label class="block font-semibold text-gray-700 mb-1">ตำแหน่งคณบดี</label>
        <input type="text" name="dean_position" value="<?= htmlspecialchars($faculty['dean_position'] ?? '') ?>"
          class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-teal-400"
          placeholder="เช่น คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม">
      </div>

      <div class="flex justify-end space-x-3 pt-4">
        <a href="department_Managerment.php"
          class="px-4 py-2 rounded-lg bg-gray-300 text-gray-700 font-semibold hover:bg-gray-400 transition">
          ยกเลิก
        </a>
        <button type="submit"
          class="px-6 py-2 rounded-lg bg-teal-500 text-white font-semibold hover:bg-teal-600 shadow">
          บันทึกการแก้ไข
        </button>
      </div>
    </form>
  </main>
</body>

</html>