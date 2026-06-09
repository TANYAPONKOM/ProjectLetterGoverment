<?php //department_Managerment.php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();
$current = basename($_SERVER['PHP_SELF']);

// ดึงข้อมูลคณะทั้งหมด
$facultyStmt = $pdo->query("SELECT faculty_id, faculty_name, dean_name, dean_position FROM faculties ORDER BY faculty_id ASC");
$faculties = $facultyStmt->fetchAll(PDO::FETCH_ASSOC);

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
      <div>
        <h2 class="text-2xl font-bold text-teal-700">การจัดการคณะ</h2>
        <p class="text-sm text-gray-500 mt-1">จัดการชื่อคณบดีและตำแหน่งคณบดีสำหรับใช้แสดงท้ายเอกสาร</p>
      </div>
      <button onclick="confirmUserAction('add')"
        class="inline-flex items-center gap-2 px-5 py-2.5 border border-teal-500 text-teal-600 bg-white rounded-lg font-semibold hover:bg-teal-50 transition shadow-sm">+
        เพิ่มคณะ/ภาควิชา</button>
    </div>

    <table class="w-full text-sm text-left border-separate border-spacing-y-2 mb-10">
      <thead class="bg-teal-500 text-white">
        <tr>
          <th class="px-4 py-2 rounded-tl-lg">ชื่อคณะ</th>
          <th class="px-4 py-2">ชื่อคณบดี</th>
          <th class="px-4 py-2">ตำแหน่งคณบดี</th>
          <th class="px-4 py-2 text-center rounded-tr-lg">การจัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($faculties): ?>
        <?php foreach ($faculties as $faculty): ?>
        <tr class="bg-teal-50 shadow-sm rounded-lg hover:bg-teal-100 transition">
          <td class="px-4 py-3 font-medium text-gray-800">
            <?= htmlspecialchars($faculty['faculty_name']) ?>
          </td>
          <td class="px-4 py-3 text-gray-700">
            <?= htmlspecialchars($faculty['dean_name'] ?: '-') ?>
          </td>
          <td class="px-4 py-3 text-gray-700">
            <?= htmlspecialchars($faculty['dean_position'] ?: '-') ?>
          </td>
          <td class="px-4 py-3 text-center">
            <button onclick="confirmUserAction('faculty_edit', <?= $faculty['faculty_id'] ?>)"
              class="w-10 h-10 inline-flex items-center justify-center rounded-full bg-blue-100 hover:bg-blue-200 transition"
              title="แก้ไขคณะ">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 20h9M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
              </svg>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr>
          <td colspan="4" class="text-center py-4 text-gray-500 bg-teal-50">ไม่พบข้อมูลคณะ</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="flex justify-between items-center mb-4 border-b pb-2">
      <div>
        <h2 class="text-2xl font-bold text-teal-700">การจัดการภาควิชา</h2>
        <!-- <p class="text-sm text-gray-500 mt-1">เปิด/ปิดการใช้งานแบบฟอร์มคำถามที่จะแสดงในหน้าเลือกเอกสาร</p> -->
      </div>
      <button onclick="confirmUserAction('add')"
        class="inline-flex items-center gap-2 px-5 py-2.5 border border-teal-500 text-teal-600 bg-white rounded-lg font-semibold hover:bg-teal-50 transition shadow-sm">+
        เพิ่มภาควิชา</button>
    </div>

    <table class="w-full text-sm text-left border-separate border-spacing-y-2">
      <thead class="bg-teal-500 text-white">
        <tr>
          <th class="px-4 py-2 rounded-tl-lg">ชื่อภาควิชา</th>
          <th class="px-4 py-2">คณะ</th>
          <th class="px-4 py-2 text-center rounded-tr-lg">การจัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($departments): ?>
        <?php foreach ($departments as $row): ?>
        <tr class="bg-teal-50 shadow-sm rounded-lg hover:bg-teal-100 transition">
          <!-- ชื่อภาควิชา -->
          <td class="px-4 py-3 font-medium text-gray-800">
            <?= htmlspecialchars($row['department_name']) ?>
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
                class="w-10 h-10 flex items-center justify-center rounded-full bg-blue-100 hover:bg-blue-200 transition"
                title="แก้ไข">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 20h9M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                </svg>
              </button>

              <!-- ปุ่มลบ -->
              <button onclick="confirmUserAction('delete', <?= $row['department_id'] ?>)"
                class="w-10 h-10 flex items-center justify-center rounded-full bg-red-100 hover:bg-red-200 transition"
                title="ลบ">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0H7m3 0V5a2 2 0 012-2h0a2 2 0 012 2v2" />
                </svg>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr>
          <td colspan="3" class="text-center py-4 text-gray-500 bg-teal-50">ไม่พบข้อมูลภาควิชา</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>

  <script>
  function showDepartmentPopup(options = {}) {
    return new Promise((resolve) => {
      const overlay = document.createElement("div");
      overlay.style.position = "fixed";
      overlay.style.inset = "0";
      overlay.style.zIndex = "99999";
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
      btnWrap.style.justifyContent = options.hideCancel ? "center" : "flex-end";
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

  async function confirmUserAction(action, id = null) {
    if (action === "add") {
      window.location.href = "department_Add.php";
    } else if (action === "faculty_edit") {
      window.location.href = "faculty_Edit.php?id=" + encodeURIComponent(id);
    } else if (action === "edit") {
      window.location.href = "department_Edit.php?id=" + encodeURIComponent(id);
    } else if (action === "delete") {
      const confirmed = await showDepartmentPopup({
        title: "ยืนยันการลบภาควิชา",
        message: "หากภาควิชานี้ถูกใช้งานกับเอกสาร ผู้ใช้ หรือตารางอื่นในระบบ จะไม่สามารถลบได้ คุณต้องการลบภาควิชานี้หรือไม่?",
        confirmText: "ลบภาควิชา",
        cancelText: "ยกเลิก",
        danger: true
      });
      if (confirmed) {
        window.location.href = "department_Delete.php?id=" + encodeURIComponent(id);
      }
    }
  }

  document.addEventListener("DOMContentLoaded", async function() {
    const departmentSuccessAction = <?= json_encode($_GET['success'] ?? '') ?>;
    const departmentErrorAction = <?= json_encode($_GET['error'] ?? '') ?>;
    const departmentErrorDetail = <?= json_encode($_GET['detail'] ?? '') ?>;

    const departmentSuccessMessages = {
      add: "เพิ่มภาควิชาสำเร็จ",
      edit: "แก้ไขภาควิชาสำเร็จ",
      delete: "ลบภาควิชาสำเร็จ",
      faculty_add: "เพิ่มคณะสำเร็จ",
      faculty_edit: "แก้ไขข้อมูลคณะสำเร็จ"
    };

    if (departmentSuccessMessages[departmentSuccessAction]) {
      await showDepartmentPopup({
        title: departmentSuccessMessages[departmentSuccessAction],
        message: "",
        confirmText: "ตกลง",
        hideCancel: true,
        success: true
      });

      const url = new URL(window.location.href);
      url.searchParams.delete("success");
      window.history.replaceState({}, document.title, url.toString());
    }

    const departmentErrorMessages = {
      delete_in_use: "ไม่สามารถลบภาควิชานี้ได้",
      delete_failed: "ลบภาควิชาไม่สำเร็จ",
      not_found: "ไม่พบข้อมูลภาควิชาที่ต้องการลบ",
      invalid_id: "ไม่พบรหัสภาควิชาที่ต้องการลบ"
    };

    if (departmentErrorMessages[departmentErrorAction]) {
      await showDepartmentPopup({
        title: departmentErrorMessages[departmentErrorAction],
        message: departmentErrorDetail || "กรุณาตรวจสอบข้อมูลอีกครั้ง",
        confirmText: "ตกลง",
        hideCancel: true,
        danger: true
      });

      const url = new URL(window.location.href);
      url.searchParams.delete("error");
      url.searchParams.delete("detail");
      window.history.replaceState({}, document.title, url.toString());
    }
  });
  </script>

</body>

</html>