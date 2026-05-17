<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>เพิ่ม Template Field</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <!-- Header -->
  <header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md">
    <div class="flex items-center space-x-3">
      <div class="w-[56px] h-[56px] flex items-center justify-center relative overflow-visible">
        <svg xmlns="http://www.w3.org/2000/svg" class="absolute scale-[1.4] text-white"
          style="width: 60px; height: 60px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m0 
                           0a2 2 0 00-2-2H5a2 2 0 
                           00-2 2m18 0v8a2 2 0 
                           01-2 2H5a2 2 0 
                           01-2-2V8" />
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
      <h1 class="text-3xl font-bold mt-4">การเพิ่ม Template Field</h1>
      <p class="text-sm text-white/80">กรอกข้อมูลเพื่อเพิ่ม Field ใหม่เข้าสู่ระบบ</p>
    </div>

    <!-- Form -->
    <form action="template_process.php" method="POST" class="p-8 space-y-6">
      <input type="hidden" name="action" value="add">

      <!-- Field Key -->
      <div>
        <label class="block font-semibold text-gray-700 mb-1">Field Key</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
            </svg>
          </span>
          <input type="text" name="field_key"
            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" placeholder="Field Key"
            required>
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
          <input type="text" name="field_label"
            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" placeholder="Field Label"
            required>
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
          <select name="field_type" class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400"
            required>
            <option value="text">Text</option>
            <option value="textarea">Textarea</option>
            <option value="date">Date</option>
            <option value="number">Number</option>
            <option value="select">Select</option>
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
          <select name="is_required" class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400">
            <option value="1">NOT NULL (จำเป็น)</option>
            <option value="0">NULL (ไม่จำเป็น)</option>
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
          <input type="number" name="sort_order" value="0"
            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" placeholder="Sort Order">
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