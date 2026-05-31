<?php
/*
  /Pro_letter/includes/role_header.php
  เมนูกลางสำหรับทุกหน้า
  หลักการ: ใช้ role ของคนที่ล็อกอินอยู่เท่านั้น ไม่ใช้ owner_id ของเอกสาร
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current = basename($_SERVER['PHP_SELF'] ?? '');
$roleId = (int)($_SESSION['role_id'] ?? 0);
$fullname = $_SESSION['fullname'] ?? 'Guest';
$roleName = $_SESSION['role_name'] ?? '';
$permissions = $_SESSION['permissions'] ?? [];

if (!is_array($permissions)) {
    $permissions = [];
}

function nav_active_class($targetFiles)
{
    $current = basename($_SERVER['PHP_SELF'] ?? '');
    $targets = (array)$targetFiles;

    return in_array($current, $targets, true)
        ? 'bg-white text-teal-500 shadow'
        : 'text-white hover:bg-white hover:text-teal-500';
}

function nav_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$homePath = '/Pro_letter/user/home.php';
if ($roleId === 1) {
    $homePath = '/Pro_letter/admin/home.php';
} elseif ($roleId === 2) {
    $homePath = '/Pro_letter/officer/home.php';
}

$logoutPath = '/Pro_letter/logout.php';
?>
<header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md"
  style="font-family: Arial, Helvetica, sans-serif;">
  <div class="flex items-center space-x-3">
    <div class="w-[56px] h-[56px] flex items-center justify-center relative overflow-visible">
      <svg xmlns="http://www.w3.org/2000/svg" class="absolute scale-[1.4] text-white" style="width: 60px; height: 60px;"
        fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

  <div class="flex items-center space-x-4">
    <?php if ($roleId === 1): ?>
    <a href="/Pro_letter/admin/home.php">
      <div class="px-4 py-2 rounded-[11px] font-bold transition <?= nav_active_class('home.php') ?>">หน้าหลัก</div>
    </a>
    <a href="/Pro_letter/admin/history_page.php">
      <div class="px-4 py-2 rounded-[11px] font-bold transition <?= nav_active_class('history_page.php') ?>">
        ประวัติการใช้งานเอกสาร
      </div>
    </a>
    <a href="/Pro_letter/admin/department_report_dashboard.php">
      <div
        class="px-4 py-2 rounded-[11px] font-bold transition <?= nav_active_class('department_report_dashboard.php') ?>">
        รายงานภาควิชา
      </div>
    </a>

    <div class="relative">
      <button type="button" id="templateBtn" class="px-4 py-2 rounded-[11px] font-bold transition
          text-white hover:bg-white hover:text-teal-500 flex items-center space-x-1">
        <span>ตั้งค่าระบบเริ่มต้น</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      <div id="templateMenu" class="hidden absolute bg-white text-gray-700 mt-1 rounded-lg shadow-lg w-48 z-50">
        <a href="/Pro_letter/admin/form_Templates.php" class="block px-4 py-2 hover:bg-teal-100">การจัดการเทมเพลต</a>
        <a href="/Pro_letter/admin/department_Managerment.php"
          class="block px-4 py-2 hover:bg-teal-100">การจัดการภาควิชา</a>
        <a href="/Pro_letter/admin/user_Managerment.php"
          class="block px-4 py-2 hover:bg-teal-100">กำหนดสิทธิ์ผู้ใช้งาน</a>
      </div>
    </div>

    <?php elseif ($roleId === 2): ?>
    <a href="/Pro_letter/officer/home.php">
      <div class="px-4 py-2 rounded-[11px] font-bold transition <?= nav_active_class('home.php') ?>">หน้าหลัก</div>
    </a>
    <a href="/Pro_letter/officer/history_page.php">
      <div class="px-4 py-2 rounded-[11px] font-bold transition <?= nav_active_class('history_page.php') ?>">
        ประวัติการใช้งานเอกสาร
      </div>
    </a>

    <?php if (function_exists('renderAdminExtraMenus') && in_array(3, $permissions, true)): ?>
    <?php renderAdminExtraMenus(); ?>
    <?php endif; ?>

    <?php else: ?>
    <a href="/Pro_letter/user/home.php">
      <div class="px-4 py-2 rounded-[11px] font-bold transition <?= nav_active_class('home.php') ?>">หน้าหลัก</div>
    </a>

    <?php if (function_exists('renderAdminExtraMenus') && in_array(3, $permissions, true)): ?>
    <?php renderAdminExtraMenus(); ?>
    <?php endif; ?>

    <a href="/Pro_letter/documents/form_Memo.php">
      <div class="px-4 py-2 rounded-[11px] font-bold transition <?= nav_active_class('form_Memo.php') ?>">
        แบบฟอร์มบันทึกข้อความ
      </div>
    </a>
    <?php endif; ?>

    <div class="relative">
      <button type="button" id="profileBtn"
        class="bg-white text-teal-500 px-4 py-2 rounded-[11px] shadow flex items-center space-x-2 hover:bg-gray-100">
        <div class="text-right leading-tight">
          <div class="font-bold text-[14px]"><?= nav_h($fullname) ?></div>
          <div class="text-[12px]"><?= nav_h($roleName) ?></div>
        </div>
        <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M5.121 17.804A13.937 13.937 0 0112 15c2.33 0 4.487.577 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
      </button>
      <div id="profileMenu" class="hidden absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-50">
        <a href="<?= nav_h($logoutPath) ?>"
          class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
        <button type="button" onclick="closeMenu()"
          class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
          อยู่ต่อ
        </button>
      </div>
    </div>
  </div>
</header>

<script>
(function() {
  const templateBtn = document.getElementById('templateBtn');
  const templateMenu = document.getElementById('templateMenu');
  const profileBtn = document.getElementById('profileBtn');
  const profileMenu = document.getElementById('profileMenu');

  window.closeMenu = function() {
    if (profileMenu) profileMenu.classList.add('hidden');
    if (templateMenu) templateMenu.classList.add('hidden');
  };

  if (templateBtn && templateMenu) {
    templateBtn.addEventListener('click', function(event) {
      event.stopPropagation();
      templateMenu.classList.toggle('hidden');
      if (profileMenu) profileMenu.classList.add('hidden');
    });
  }

  if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function(event) {
      event.stopPropagation();
      profileMenu.classList.toggle('hidden');
      if (templateMenu) templateMenu.classList.add('hidden');
    });
  }

  document.addEventListener('click', function(event) {
    if (templateMenu && templateBtn && !templateBtn.contains(event.target) && !templateMenu.contains(event
      .target)) {
      templateMenu.classList.add('hidden');
    }
    if (profileMenu && profileBtn && !profileBtn.contains(event.target) && !profileMenu.contains(event.target)) {
      profileMenu.classList.add('hidden');
    }
  });
})();
</script>