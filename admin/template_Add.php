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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateCode = strtoupper(trim($_POST['template_code'] ?? ''));
    $templateName = trim($_POST['template_name'] ?? '');
    $questionPath = trim($_POST['question_path'] ?? '');
    $documentPath = trim($_POST['document_path'] ?? '');
    $templateGroup = trim($_POST['template_group'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 1);
    $isActive = $isActive === 1 ? 1 : 0;
    $createdBy = (int)($_SESSION['user_id'] ?? 1);

    if ($templateCode === '' || $templateName === '' || $questionPath === '' || $documentPath === '' || !in_array($templateGroup, ['internal', 'external'], true)) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM templates WHERE template_code = ?");
        $check->execute([$templateCode]);

        if ((int)$check->fetchColumn() > 0) {
            $error = 'รหัสเทมเพลตนี้มีอยู่แล้ว';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO templates
                (category_id, template_code, template_name, question_path, document_path, template_group, word_path, pdf_path, is_active, sort_order, created_by, created_at)
                VALUES
                (1, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, NOW())
            ");
            $stmt->execute([$templateCode, $templateName, $questionPath, $documentPath, $templateGroup, $isActive, $sortOrder, $createdBy]);

            header('Location: form_Templates.php?status=added');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>เพิ่มเทมเพลต</title>
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
      <h1 class="text-3xl font-bold">เพิ่มเทมเพลต</h1>
      <p class="text-sm text-white/90 mt-1">เพิ่มชื่อแบบฟอร์มคำถามและเส้นทางไฟล์ที่ใช้แสดงใน dropdown</p>
    </div>

    <form method="POST" class="p-8 space-y-6">
      <?php if ($error !== ''): ?>
      <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 font-semibold">
        <?= h($error) ?>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block font-semibold text-gray-700 mb-2">รหัสเทมเพลต</label>
          <input type="text" name="template_code" value="<?= h($_POST['template_code'] ?? '') ?>" required
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400"
            placeholder="เช่น INVITE_SPEAKER">
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-2">ลำดับการแสดงผล</label>
          <input type="number" name="sort_order" value="<?= h($_POST['sort_order'] ?? '0') ?>"
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400">
        </div>

        <div class="md:col-span-2">
          <label class="block font-semibold text-gray-700 mb-2">ชื่อเทมเพลต</label>
          <input type="text" name="template_name" value="<?= h($_POST['template_name'] ?? '') ?>" required
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400"
            placeholder="ชื่อที่ต้องการให้แสดงใน dropdown">
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-2">หมวดหลัก</label>
          <select name="template_group" required
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400 bg-white">
            <option value="">-- เลือกหมวดหลัก --</option>
            <option value="external" <?= (($_POST['template_group'] ?? '') === 'external') ? 'selected' : '' ?>>ภายนอก</option>
            <option value="internal" <?= (($_POST['template_group'] ?? '') === 'internal') ? 'selected' : '' ?>>ภายใน</option>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-2">สถานะการใช้งาน</label>
          <select name="is_active"
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400 bg-white">
            <option value="1" <?= (($_POST['is_active'] ?? '1') === '1') ? 'selected' : '' ?>>เปิดใช้งาน</option>
            <option value="0" <?= (($_POST['is_active'] ?? '') === '0') ? 'selected' : '' ?>>ปิดใช้งาน</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="block font-semibold text-gray-700 mb-2">ไฟล์คำถาม</label>
          <input type="text" name="question_path" value="<?= h($_POST['question_path'] ?? '') ?>" required
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400"
            placeholder="/documents/infor_invite.php">
        </div>

        <div class="md:col-span-2">
          <label class="block font-semibold text-gray-700 mb-2">ไฟล์เอกสารที่เจนออกมา</label>
          <input type="text" name="document_path" value="<?= h($_POST['document_path'] ?? '') ?>" required
            class="w-full border rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-400"
            placeholder="/form_Memo/form_memo_invite_speaker.php">
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-4">
        <a href="form_Templates.php"
          class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-600 hover:bg-gray-50 font-semibold">
          ยกเลิก
        </a>
        <button type="submit"
          class="px-5 py-2.5 rounded-xl bg-[#14b8a6] text-white hover:bg-teal-600 font-semibold shadow">
          บันทึกเทมเพลต
        </button>
      </div>
    </form>
  </main>
</body>

</html>
