<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int)($_GET['id'] ?? $_POST['template_id'] ?? 0);
if ($id <= 0) {
    die("ไม่พบข้อมูลเทมเพลต");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateName = trim($_POST['template_name'] ?? '');
    $templateGroup = trim($_POST['template_group'] ?? '');
    $isActive = (int)($_POST['is_active'] ?? 1);
    $isActive = $isActive === 1 ? 1 : 0;

    if ($templateName === '' || !in_array($templateGroup, ['internal', 'external'], true)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        $stmt = $pdo->prepare("
            UPDATE templates
            SET template_name = ?,
                template_group = ?,
                is_active = ?
            WHERE template_id = ?
        ");
        $stmt->execute([$templateName, $templateGroup, $isActive, $id]);

        header('Location: form_Templates.php?status=saved');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM templates WHERE template_id = ?");
$stmt->execute([$id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die("ไม่พบข้อมูลเทมเพลต");
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>แก้ไขเทมเพลต</title>
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

    <a href="form_Templates.php"
      class="bg-white text-teal-600 px-4 py-2 rounded-[11px] shadow hover:bg-gray-100 font-semibold">
      กลับหน้าจัดการเทมเพลต
    </a>
  </header>

  <main class="max-w-4xl mx-auto mt-10 mb-12 bg-white rounded-2xl shadow-lg overflow-hidden">
    <div class="bg-[#14b8a6] text-white px-8 py-7">
      <h1 class="text-3xl font-bold">แก้ไขเทมเพลต</h1>
      <p class="text-sm text-white/90 mt-1">ปรับชื่อ หมวด และสถานะการใช้งานของเทมเพลต</p>
    </div>

    <form method="POST" class="p-8 space-y-6">
      <input type="hidden" name="template_id" value="<?= (int)$template['template_id'] ?>">

      <?php if ($error !== ''): ?>
      <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 font-semibold">
        <?= h($error) ?>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block font-semibold text-gray-700 mb-2">รหัสเทมเพลต</label>
          <input type="text" value="<?= h($template['template_code']) ?>" readonly
            class="w-full border rounded-xl px-4 py-2.5 bg-gray-100 text-gray-500 cursor-not-allowed focus:outline-none">
        </div>


        <div class="md:col-span-2">
          <label class="block font-semibold text-gray-700 mb-2">ชื่อเทมเพลต</label>
          <input type="text" name="template_name"
            value="<?= h($_POST['template_name'] ?? $template['template_name']) ?>" required
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400">
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-2">หมวดหลัก</label>
          <?php $selectedGroup = $_POST['template_group'] ?? $template['template_group']; ?>
          <select name="template_group" required
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400 bg-white">
            <option value="">-- เลือกหมวดหลัก --</option>
            <option value="external" <?= $selectedGroup === 'external' ? 'selected' : '' ?>>ภายนอก</option>
            <option value="internal" <?= $selectedGroup === 'internal' ? 'selected' : '' ?>>ภายใน</option>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-2">สถานะการใช้งาน</label>
          <?php $selectedActive = (string)($_POST['is_active'] ?? $template['is_active']); ?>
          <select name="is_active"
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400 bg-white">
            <option value="1" <?= $selectedActive === '1' ? 'selected' : '' ?>>เปิดใช้งาน</option>
            <option value="0" <?= $selectedActive === '0' ? 'selected' : '' ?>>ปิดใช้งาน</option>
          </select>
        </div>

      </div>

      <div class="flex justify-end gap-3 pt-4">
        <a href="form_Templates.php"
          class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-50 font-semibold">
          ยกเลิก
        </a>
        <button type="submit"
          class="px-5 py-2.5 rounded-xl bg-[#14b8a6] text-white hover:bg-teal-600 font-semibold shadow">
          บันทึกการแก้ไข
        </button>
      </div>
    </form>
  </main>
</body>

</html>