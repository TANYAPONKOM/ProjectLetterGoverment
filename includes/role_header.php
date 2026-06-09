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
$settingsVerifyUrl = '/Pro_letter/admin/form_Templates.php';
$userPermissionVerifyUrl = '/Pro_letter/admin/verify_user.php';
$isSettingsPage = in_array($current, ['form_Templates.php', 'department_Managerment.php', 'user_Managerment.php'], true);
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
      <div class="text-[16px] font-bold mt-[0px]">Letter Assistant System</div>
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

    <a href="/Pro_letter/documents/form_Memo.php">
      <div class="px-4 py-2 rounded-[11px] font-bold transition <?= nav_active_class('form_Memo.php') ?>">
        สร้างแบบฟอร์มบันทึกข้อความ
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
        สร้างแบบฟอร์มบันทึกข้อความ
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
  const settingsVerifyUrl = '<?= nav_h($settingsVerifyUrl) ?>';
  const userPermissionVerifyUrl = '<?= nav_h($userPermissionVerifyUrl) ?>';
  const currentRoleId = <?= (int)$roleId ?>;
  const currentPage = '<?= nav_h($current) ?>';
  const isSettingsPage = <?= $isSettingsPage ? 'true' : 'false' ?>;

  function showHeaderPopup(options = {}) {
    return new Promise((resolve) => {
      const overlay = document.createElement("div");
      overlay.style.position = "fixed";
      overlay.style.inset = "0";
      overlay.style.zIndex = "999999";
      overlay.style.background = "rgba(15, 23, 42, 0.45)";
      overlay.style.display = "flex";
      overlay.style.alignItems = "center";
      overlay.style.justifyContent = "center";
      overlay.style.padding = "18px";

      const box = document.createElement("div");
      box.style.width = "420px";
      box.style.maxWidth = "100%";
      box.style.background = "#ffffff";
      box.style.borderRadius = "18px";
      box.style.boxShadow = "0 24px 60px rgba(15, 23, 42, 0.28)";
      box.style.padding = "28px";
      box.style.fontFamily = "Arial, sans-serif";
      box.style.textAlign = "center";

      const icon = document.createElement("div");
      icon.style.width = "64px";
      icon.style.height = "64px";
      icon.style.borderRadius = "999px";
      icon.style.margin = "0 auto 18px";
      icon.style.display = "flex";
      icon.style.alignItems = "center";
      icon.style.justifyContent = "center";
      icon.style.fontSize = "34px";
      icon.style.fontWeight = "700";
      icon.style.background = options.danger ? "#fee2e2" : options.success ? "#dcfce7" : "#ccfbf1";
      icon.style.color = options.danger ? "#dc2626" : options.success ? "#16a34a" : "#0f766e";
      icon.innerHTML = options.danger ? "!" : options.success ? "✓" : "🔐";

      const title = document.createElement("div");
      title.textContent = options.title || "";
      title.style.fontSize = options.success ? "30px" : "22px";
      title.style.fontWeight = "800";
      title.style.color = "#334155";
      title.style.marginBottom = "8px";

      const message = document.createElement("div");
      message.textContent = options.message || "";
      message.style.fontSize = "14px";
      message.style.color = "#64748b";
      message.style.lineHeight = "1.6";
      message.style.marginBottom = "18px";

      let input = null;
      if (options.inputType) {
        input = document.createElement("input");
        input.type = options.inputType;
        input.style.width = "100%";
        input.style.height = "44px";
        input.style.border = "1px solid #cbd5e1";
        input.style.borderRadius = "12px";
        input.style.padding = "0 14px";
        input.style.fontSize = "15px";
        input.style.outline = "none";
        input.style.marginBottom = "20px";
        input.addEventListener("focus", () => {
          input.style.borderColor = "#14b8a6";
          input.style.boxShadow = "0 0 0 4px rgba(20, 184, 166, 0.16)";
        });
        input.addEventListener("blur", () => {
          input.style.borderColor = "#cbd5e1";
          input.style.boxShadow = "none";
        });
      }

      const btnWrap = document.createElement("div");
      btnWrap.style.display = "flex";
      btnWrap.style.justifyContent = "flex-end";
      btnWrap.style.gap = "10px";

      const cancelBtn = document.createElement("button");
      cancelBtn.type = "button";
      cancelBtn.textContent = options.cancelText || "ยกเลิก";
      cancelBtn.style.border = "1px solid #e2e8f0";
      cancelBtn.style.background = "#ffffff";
      cancelBtn.style.color = "#475569";
      cancelBtn.style.borderRadius = "12px";
      cancelBtn.style.padding = "10px 18px";
      cancelBtn.style.cursor = "pointer";
      cancelBtn.style.fontWeight = "700";

      const okBtn = document.createElement("button");
      okBtn.type = "button";
      okBtn.textContent = options.confirmText || "ตกลง";
      okBtn.style.border = "none";
      okBtn.style.background = options.danger ? "#ef4444" : "#14b8a6";
      okBtn.style.color = "#ffffff";
      okBtn.style.borderRadius = "12px";
      okBtn.style.padding = "10px 18px";
      okBtn.style.cursor = "pointer";
      okBtn.style.fontWeight = "800";
      okBtn.style.boxShadow = options.danger ? "0 8px 18px rgba(239, 68, 68, 0.25)" :
        "0 8px 18px rgba(20, 184, 166, 0.25)";

      function close(value) {
        overlay.remove();
        resolve(value);
      }

      cancelBtn.addEventListener("click", () => close(null));
      okBtn.addEventListener("click", () => {
        if (input) {
          close(input.value);
        } else {
          close(true);
        }
      });

      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) close(null);
      });

      box.appendChild(icon);
      box.appendChild(title);
      if (options.message) box.appendChild(message);
      if (input) box.appendChild(input);

      if (!options.hideCancel) {
        btnWrap.appendChild(cancelBtn);
      }
      btnWrap.appendChild(okBtn);
      box.appendChild(btnWrap);
      overlay.appendChild(box);
      document.body.appendChild(overlay);

      if (input) {
        setTimeout(() => input.focus(), 50);
        input.addEventListener("keydown", (e) => {
          if (e.key === "Enter") okBtn.click();
          if (e.key === "Escape") cancelBtn.click();
        });
      }
    });
  }

  async function verifySettingsMenu() {
    const username = await showHeaderPopup({
      title: "ยืนยันตัวตนผู้ดูแลระบบ",
      message: "กรุณากรอกชื่อผู้ใช้ของผู้ดูแลระบบเพื่อยืนยัน",
      inputType: "text",
      confirmText: "ถัดไป"
    });
    if (username === null || username.trim() === "") return false;

    const password = await showHeaderPopup({
      title: "ยืนยันรหัสผ่าน",
      message: "กรุณากรอกรหัสผ่านของผู้ดูแลระบบเพื่อยืนยัน",
      inputType: "password",
      confirmText: "ยืนยัน"
    });
    if (password === null || password.trim() === "") return false;

    try {
      const params = new URLSearchParams();
      params.append("action", "verify_template_admin");
      params.append("admin_username", username.trim());
      params.append("admin_password", password);

      const response = await fetch(settingsVerifyUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "Accept": "application/json"
        },
        credentials: "same-origin",
        body: params.toString()
      });

      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        console.error("verify_template_admin response:", text);
        await showHeaderPopup({
          title: "ยืนยันตัวตนไม่สำเร็จ",
          message: "ระบบตรวจสอบสิทธิ์ไม่ได้ส่ง JSON กลับมา หรือมี PHP error",
          confirmText: "ตกลง",
          hideCancel: true,
          danger: true
        });
        return false;
      }

      if (!response.ok || !data.success) {
        await showHeaderPopup({
          title: "ยืนยันตัวตนไม่สำเร็จ",
          message: data.message || "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง",
          confirmText: "ตกลง",
          hideCancel: true,
          danger: true
        });
        return false;
      }

      return true;
    } catch (err) {
      await showHeaderPopup({
        title: "เกิดข้อผิดพลาด",
        message: "เกิดข้อผิดพลาดในการยืนยันตัวตน: " + err.message,
        confirmText: "ตกลง",
        hideCancel: true,
        danger: true
      });
      return false;
    }
  }


  async function verifyUserPermissionMenu() {
    const username = await showHeaderPopup({
      title: "ยืนยันตัวตน",
      message: "กรุณากรอกชื่อผู้ใช้เพื่อยืนยันก่อนเข้าหน้ากำหนดสิทธิ์ผู้ใช้งาน",
      inputType: "text",
      confirmText: "ถัดไป"
    });
    if (username === null || username.trim() === "") return false;

    const password = await showHeaderPopup({
      title: "ยืนยันรหัสผ่าน",
      message: "กรุณากรอกรหัสผ่านเพื่อยืนยัน",
      inputType: "password",
      confirmText: "ยืนยัน"
    });
    if (password === null || password.trim() === "") return false;

    try {
      const params = new URLSearchParams();
      params.append("username", username.trim());
      params.append("password", password);

      const response = await fetch(userPermissionVerifyUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "Accept": "application/json"
        },
        credentials: "same-origin",
        body: params.toString()
      });

      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        console.error("verify_user.php response:", text);
        await showHeaderPopup({
          title: "ยืนยันตัวตนไม่สำเร็จ",
          message: "ระบบตรวจสอบสิทธิ์ไม่ได้ส่ง JSON กลับมา หรือมี PHP error",
          confirmText: "ตกลง",
          hideCancel: true,
          danger: true
        });
        return false;
      }

      if (!response.ok || !data.success) {
        await showHeaderPopup({
          title: "ยืนยันตัวตนไม่สำเร็จ",
          message: data.message || "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง",
          confirmText: "ตกลง",
          hideCancel: true,
          danger: true
        });
        return false;
      }

      return true;
    } catch (err) {
      await showHeaderPopup({
        title: "เกิดข้อผิดพลาด",
        message: "เกิดข้อผิดพลาดในการยืนยันตัวตน: " + err.message,
        confirmText: "ตกลง",
        hideCancel: true,
        danger: true
      });
      return false;
    }
  }

  window.closeMenu = function() {
    if (profileMenu) profileMenu.classList.add('hidden');
    if (templateMenu) templateMenu.classList.add('hidden');
  };

  if (templateBtn && templateMenu) {
    templateBtn.addEventListener('click', async function(event) {
      event.stopPropagation();

      if (!isSettingsPage) {
        const verified = await verifySettingsMenu();
        if (!verified) return;
      }

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


  document.querySelectorAll('a[href*="user_Managerment.php"]').forEach(function(link) {
    link.addEventListener('click', async function(event) {
      if (currentRoleId === 1 || currentPage === 'user_Managerment.php') {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      const verified = await verifyUserPermissionMenu();
      if (verified) {
        window.location.href = link.href;
      }
    });
  });

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