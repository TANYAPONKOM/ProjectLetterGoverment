<?php //form_Templates.php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();
$current = basename($_SERVER['PHP_SELF']);

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('findExistingColumn')) {
    function findExistingColumn(PDO $pdo, string $tableName, array $candidates): ?string {
        foreach ($candidates as $columnName) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$tableName, $columnName]);
            if ((int)$stmt->fetchColumn() > 0) {
                return $columnName;
            }
        }
        return null;
    }
}

if (!function_exists('verifyTemplateAdminCredentials')) {
    function verifyTemplateAdminCredentials(PDO $pdo, string $login, string $password): bool {
        $login = trim($login);
        if ($login === '' || $password === '') {
            return false;
        }

        $loginColumn = findExistingColumn($pdo, 'users', ['username', 'user_name', 'email', 'login_name', 'account']);
        $passwordColumn = findExistingColumn($pdo, 'users', ['password', 'password_hash', 'user_password', 'user_pass']);
        $roleColumn = findExistingColumn($pdo, 'users', ['role_id']);

        if ($loginColumn === null || $passwordColumn === null) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `$loginColumn` = ? LIMIT 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if ($roleColumn !== null && (int)($user[$roleColumn] ?? 0) !== 1) {
            return false;
        }

        $storedPassword = (string)($user[$passwordColumn] ?? '');
        if ($storedPassword === '') {
            return false;
        }

        return password_verify($password, $storedPassword)
            || hash_equals($storedPassword, $password)
            || hash_equals($storedPassword, md5($password));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_template_admin') {
    header('Content-Type: application/json; charset=utf-8');

    $username = (string)($_POST['admin_username'] ?? '');
    $password = (string)($_POST['admin_password'] ?? '');

    if (verifyTemplateAdminCredentials($pdo, $username, $password)) {
<<<<<<< HEAD
        $_SESSION['settings_menu_verified'] = true;
=======
        $_SESSION['template_admin_verified_until'] = time() + 60;
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือไม่มีสิทธิ์ผู้ดูแลระบบ'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// อัปเดตสถานะเปิด/ปิดการใช้งานเทมเพลต
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_template') {
<<<<<<< HEAD
=======
    $verifiedUntil = (int)($_SESSION['template_admin_verified_until'] ?? 0);
    if ($verifiedUntil < time()) {
        header('Location: form_Templates.php?status=auth_required');
        exit;
    }

    unset($_SESSION['template_admin_verified_until']);

>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    $templateId = (int)($_POST['template_id'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 0);
    $isActive = $isActive === 1 ? 1 : 0;

    if ($templateId > 0) {
        $updateStmt = $pdo->prepare("UPDATE templates SET is_active = ? WHERE template_id = ?");
        $updateStmt->execute([$isActive, $templateId]);
    }

    header('Location: form_Templates.php?status=status_changed');
    exit;
}

// ดึงข้อมูลเทมเพลต
$sql = "SELECT template_id, template_code, template_name, question_path, document_path, template_group, is_active, sort_order
        FROM templates
        ORDER BY sort_order ASC, template_id ASC";
$stmt = $pdo->query($sql);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flashStatus = $_GET['status'] ?? '';
$flashMessage = '';
if ($flashStatus === 'deleted') {
    $flashMessage = 'ลบเทมเพลตเรียบร้อยแล้ว';
} elseif ($flashStatus === 'delete_blocked') {
    $flashMessage = 'ไม่สามารถลบเทมเพลตนี้ได้ เพราะมีเอกสารที่เคยใช้งานอยู่ แนะนำให้ปิดใช้งานแทน';
} elseif ($flashStatus === 'saved') {
    $flashMessage = 'แก้ไขเทมเพลตสำเร็จ';
} elseif ($flashStatus === 'added') {
    $flashMessage = 'เพิ่มเทมเพลตเรียบร้อยแล้ว';
} elseif ($flashStatus === 'status_changed') {
    $flashMessage = 'เปลี่ยนสถานะการใช้งานเทมเพลตเรียบร้อยแล้ว';
} elseif ($flashStatus === 'auth_required') {
    $flashMessage = 'กรุณายืนยันชื่อผู้ใช้และรหัสผ่านก่อนดำเนินการ';
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>การจัดการเทมเพลต</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <!-- Header -->
  <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>


  <!-- Main Content -->
  <main class="max-w-7xl w-full px-8 mx-auto bg-white mt-6 mb-12 p-6 rounded shadow min-h-[85vh]">
    <div class="flex justify-between items-center mb-5">
      <div>
        <h2 class="text-2xl font-bold text-teal-700">การจัดการเทมเพลต</h2>
        <!-- <p class="text-sm text-gray-500 mt-1">เปิด/ปิดการใช้งานแบบฟอร์มคำถามที่จะแสดงในหน้าเลือกเอกสาร</p> -->
      </div>

    </div>

    <?php if ($flashMessage !== ''): ?>
    <div
      class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?= $flashStatus === 'delete_blocked' || $flashStatus === 'auth_required' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700' ?>">
      <?= h($flashMessage) ?>
    </div>
    <?php endif; ?>

    <div class="overflow-x-auto rounded-xl shadow-sm border border-gray-100">
      <table class="w-full text-sm text-left border-collapse">
        <thead class="bg-[#14b8a6] text-white">
          <tr>
            <th class="px-4 py-4 text-center w-16">ลำดับ</th>
            <th class="px-4 py-4 min-w-[240px]">ชื่อเทมเพลต</th>
            <th class="px-4 py-4 text-center min-w-[110px]">หมวด</th>
            <th class="px-4 py-4 text-center min-w-[150px]">สถานะการใช้งาน</th>
            <th class="px-4 py-4 text-center min-w-[120px]">การจัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($templates): ?>
          <?php foreach ($templates as $row): ?>
          <?php
            $isActive = (int)($row['is_active'] ?? 0) === 1;
            $group = trim((string)($row['template_group'] ?? ''));
            $groupText = $group === 'internal' ? 'ภายใน' : ($group === 'external' ? 'ภายนอก' : '-');
          ?>
          <tr
            class="border-b border-gray-100 <?= $isActive ? 'bg-white hover:bg-teal-50/40' : 'bg-gray-50 hover:bg-gray-100' ?> transition">
            <td class="px-4 py-4 text-center text-gray-600 font-medium">
              <?= h($row['sort_order'] ?: $row['template_id']) ?>
            </td>

            <td class="px-4 py-4">
              <div class="font-semibold text-gray-800"><?= h($row['template_name']) ?></div>
              <div class="text-xs text-gray-400 mt-1"><?= h($row['template_code']) ?></div>
            </td>

            <td class="px-4 py-4 text-center">
              <span
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $group === 'internal' ? 'bg-cyan-50 text-cyan-700' : 'bg-amber-50 text-amber-700' ?>">
                <?= h($groupText) ?>
              </span>
            </td>

            <td class="px-4 py-4 text-center">
              <form method="post" class="inline-flex flex-col items-center gap-2">
                <input type="hidden" name="action" value="toggle_template">
                <input type="hidden" name="template_id" value="<?= (int)$row['template_id'] ?>">
                <input type="hidden" name="is_active" value="0">

                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" name="is_active" value="1" class="sr-only peer"
                    data-current="<?= $isActive ? '1' : '0' ?>" onchange="requestToggleTemplate(this)"
                    <?= $isActive ? 'checked' : '' ?>>
                  <div class="w-12 h-6 bg-gray-300 rounded-full peer peer-checked:bg-teal-500 transition"></div>
                  <div
                    class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-6">
                  </div>
                </label>

                <?php if ($isActive): ?>
                <span class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-700 font-semibold">เปิดใช้งาน</span>
                <?php else: ?>
                <span class="px-3 py-1 text-xs rounded-full bg-red-100 text-red-700 font-semibold">ปิดใช้งาน</span>
                <?php endif; ?>
              </form>
            </td>

            <td class="px-4 py-4 text-center">
              <div class="flex justify-center space-x-2">
                <button onclick="confirmTemplateAction('edit', <?= (int)$row['template_id'] ?>)"
                  class="w-10 h-10 flex items-center justify-center rounded-full bg-blue-100 hover:bg-blue-200 transition"
                  title="แก้ไข">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 20h9M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                  </svg>
                </button>

              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php else: ?>
          <tr>
            <td colspan="5" class="text-center py-8 text-gray-500">ไม่พบข้อมูลเทมเพลต</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <script>
  function showTemplatePopup(options = {}) {
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
      icon.textContent = options.danger ? "!" : options.success ? "✓" : "🔐";

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
      okBtn.style.minWidth = options.hideCancel ? "110px" : "auto";
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

  async function verifyTemplateAdminByPrompt(callback) {
    const username = await showTemplatePopup({
      title: "ยืนยันตัวตนผู้ดูแลระบบ",
      message: "กรุณากรอกชื่อผู้ใช้ของผู้ดูแลระบบเพื่อยืนยัน",
      inputType: "text",
      confirmText: "ถัดไป"
    });
    if (username === null || username.trim() === "") return;

    const password = await showTemplatePopup({
      title: "ยืนยันรหัสผ่าน",
      message: "กรุณากรอกรหัสผ่านของผู้ดูแลระบบเพื่อยืนยัน",
      inputType: "password",
      confirmText: "ยืนยัน"
    });
    if (password === null || password.trim() === "") return;

    const formData = new FormData();
    formData.append("action", "verify_template_admin");
    formData.append("admin_username", username.trim());
    formData.append("admin_password", password);

    try {
      const response = await fetch("form_Templates.php", {
        method: "POST",
        body: formData
      });

      const text = await response.text();
      let data;

      try {
        data = JSON.parse(text);
      } catch (error) {
        console.error("Response is not JSON:", text);
        await showTemplatePopup({
          title: "ยืนยันตัวตนไม่สำเร็จ",
          message: "ระบบตรวจสอบสิทธิ์ไม่ได้ส่ง JSON กลับมา อาจเกิดจาก path ผิด, redirect ไป login, หรือมี PHP error",
          confirmText: "ตกลง",
          hideCancel: true,
          danger: true
        });
        return;
      }

      if (data && data.success) {
        if (typeof callback === "function") {
          callback();
        }
      } else {
        await showTemplatePopup({
          title: "ยืนยันตัวตนไม่สำเร็จ",
          message: (data && data.message) ? data.message : "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง",
          confirmText: "ตกลง",
          hideCancel: true,
          danger: true
        });
      }
    } catch (error) {
      await showTemplatePopup({
        title: "เกิดข้อผิดพลาด",
        message: "เกิดข้อผิดพลาด: " + error.message,
        confirmText: "ตกลง",
        hideCancel: true,
        danger: true
      });
    }
  }

  function requestToggleTemplate(checkbox) {
    const currentValue = checkbox.dataset.current === "1" ? 1 : 0;
    const nextValue = checkbox.checked ? 1 : 0;

    checkbox.checked = currentValue === 1;

<<<<<<< HEAD
    const form = checkbox.form;
    if (!form) return;

    const activeInput = form.querySelector('input[type="hidden"][name="is_active"]');
    if (activeInput) {
      activeInput.value = String(nextValue);
    }

    checkbox.dataset.current = String(nextValue);
    checkbox.checked = nextValue === 1;
    form.submit();
  }

  async function confirmTemplateAction(action, id = null) {
    if (action === "add") {
      window.location.href = "template_Add.php";
    } else if (action === "edit") {
      window.location.href = "template_Edit.php?id=" + id;
    } else if (action === "delete") {
      const confirmed = await showTemplatePopup({
        title: "ยืนยันการลบเทมเพลต",
        message: "ถ้าเทมเพลตนี้เคยถูกใช้สร้างเอกสาร ระบบจะไม่อนุญาตให้ลบ และควรใช้การปิดใช้งานแทน",
        confirmText: "ลบเทมเพลต",
        cancelText: "ยกเลิก",
        danger: true
      });

      if (confirmed) {
        window.location.href = "template_Delete.php?id=" + id;
      }
    }
  }
=======
    verifyTemplateAdminByPrompt(() => {
      const form = checkbox.form;
      if (!form) return;

      const activeInput = form.querySelector('input[type="hidden"][name="is_active"]');
      if (activeInput) {
        activeInput.value = String(nextValue);
      }

      checkbox.dataset.current = String(nextValue);
      checkbox.checked = nextValue === 1;
      form.submit();
    });
  }

  function confirmTemplateAction(action, id = null) {
    verifyTemplateAdminByPrompt(async () => {
      if (action === "add") {
        window.location.href = "template_Add.php";
      } else if (action === "edit") {
        window.location.href = "template_Edit.php?id=" + id;
      } else if (action === "delete") {
        const confirmed = await showTemplatePopup({
          title: "ยืนยันการลบเทมเพลต",
          message: "ถ้าเทมเพลตนี้เคยถูกใช้สร้างเอกสาร ระบบจะไม่อนุญาตให้ลบ และควรใช้การปิดใช้งานแทน",
          confirmText: "ลบเทมเพลต",
          cancelText: "ยกเลิก",
          danger: true
        });

        if (confirmed) {
          window.location.href = "template_Delete.php?id=" + id;
        }
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
      }
    });
  }

  document.addEventListener("DOMContentLoaded", async function() {
    const templateStatus = <?= json_encode($flashStatus) ?>;

    const successMessages = {
      saved: "แก้ไขเทมเพลตสำเร็จ",
      added: "เพิ่มเทมเพลตสำเร็จ",
      deleted: "ลบเทมเพลตสำเร็จ",
      status_changed: "แก้ไขสถานะเทมเพลตสำเร็จ"
    };

    const dangerMessages = {
      delete_blocked: "ไม่สามารถลบเทมเพลตนี้ได้ เพราะมีเอกสารที่เคยใช้งานอยู่ แนะนำให้ปิดใช้งานแทน",
      auth_required: "กรุณายืนยันชื่อผู้ใช้และรหัสผ่านก่อนดำเนินการ"
    };

    if (successMessages[templateStatus]) {
      await showTemplatePopup({
        title: successMessages[templateStatus],
        message: "",
        confirmText: "ตกลง",
        hideCancel: true,
        success: true
      });
    } else if (dangerMessages[templateStatus]) {
      await showTemplatePopup({
        title: "แจ้งเตือน",
        message: dangerMessages[templateStatus],
        confirmText: "ตกลง",
        hideCancel: true,
        danger: true
      });
    }

    if (successMessages[templateStatus] || dangerMessages[templateStatus]) {
      const url = new URL(window.location.href);
      url.searchParams.delete("status");
      window.history.replaceState({}, document.title, url.toString());
    }
  });
  </script>

</body>

</html>