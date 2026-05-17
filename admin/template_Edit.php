<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

// รับ id จาก URL
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM template_fields WHERE field_id=?");
$stmt->execute([$id]);
$field = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$field) {
    die("ไม่พบข้อมูล Field");
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>Edit User</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
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
    <!-- <a href="user_Managerment.php" class="bg-white text-teal-500 px-3 py-1 rounded">กลับ</a> -->
  </header>

  <body class="bg-gray-100">
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
        <h1 class="text-3xl font-bold mt-4">การแก้ไข Template Field</h1>
        <p class="text-sm text-white/80">ปรับปรุงข้อมูล Field ในระบบ</p>
      </div>

      <!-- Form -->
      <form action="template_process.php" method="POST" class="p-8 space-y-6">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="field_id" value="<?= $field['field_id'] ?>">

        <!-- Field Key -->
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Field Key</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
              </svg>
            </span>
            <input type="text" name="field_key" value="<?= htmlspecialchars($field['field_key']) ?>" required
              class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400"
              placeholder="Field Key">
          </div>
        </div>

        <!-- Field Label -->
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Field Label</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </span>
            <input type="text" name="field_label" value="<?= htmlspecialchars($field['field_label']) ?>" required
              class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400"
              placeholder="Field Label">
          </div>
        </div>

        <!-- Field Type -->
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Field Type</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 17v-6h13v6H9zM9 7V4h13v3H9zM4 4h1v16H4z" />
              </svg>
            </span>
            <select name="field_type"
              class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="text" <?= $field['field_type']=='text'?'selected':'' ?>>Text</option>
              <option value="textarea" <?= $field['field_type']=='textarea'?'selected':'' ?>>Textarea
              </option>
              <option value="date" <?= $field['field_type']=='date'?'selected':'' ?>>Date</option>
              <option value="number" <?= $field['field_type']=='number'?'selected':'' ?>>Number</option>
              <option value="select" <?= $field['field_type']=='select'?'selected':'' ?>>Select</option>
            </select>
          </div>
        </div>

        <!-- Required -->
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Required</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </span>
            <select name="is_required"
              class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="1" <?= $field['is_required']==1?'selected':'' ?>>NOT NULL (จำเป็น)</option>
              <option value="0" <?= $field['is_required']==0?'selected':'' ?>>NULL (ไม่จำเป็น)</option>
            </select>
          </div>
        </div>

        <!-- Sort Order -->
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Sort Order</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </span>
            <input type="number" name="sort_order" value="<?= $field['sort_order'] ?>"
              class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400"
              placeholder="Sort Order">
          </div>
        </div>

        <!-- Buttons -->
        <div class="flex justify-end space-x-3 pt-4">
          <a href="form_Templates.php"
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