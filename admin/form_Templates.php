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
        $_SESSION['template_admin_verified_until'] = time() + 60;
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
    $verifiedUntil = (int)($_SESSION['template_admin_verified_until'] ?? 0);
    if ($verifiedUntil < time()) {
        header('Location: form_Templates.php?status=auth_required');
        exit;
    }

    unset($_SESSION['template_admin_verified_until']);

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
    $flashMessage = 'บันทึกข้อมูลเทมเพลตเรียบร้อยแล้ว';
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

    <div class="flex items-center space-x-4">
      <a href="home.php">
        <div
          class="px-4 py-2 rounded-[11px] font-bold transition <?= $current === 'home.php' ? 'bg-white text-teal-500 shadow' : 'text-white hover:bg-white hover:text-teal-500' ?>">
          หน้าหลัก
        </div>
      </a>

      <a href="history_page.php">
        <div
          class="px-4 py-2 rounded-[11px] font-bold transition <?= $current === 'history_page.php' ? 'bg-white text-teal-500 shadow' : 'text-white hover:bg-white hover:text-teal-500' ?>">
          ประวัติการใช้งานเอกสาร
        </div>
      </a>

      <a href="department_report_dashboard.php">
        <div
          class="px-4 py-2 rounded-[11px] font-bold transition <?= $current === 'department_report_dashboard.php' ? 'bg-white text-teal-500 shadow' : 'text-white hover:bg-white hover:text-teal-500' ?>">
          รายงานภาควิชา
        </div>
      </a>

      <!-- Dropdown ตั้งค่าระบบเริ่มต้น -->
      <div class="relative">
        <button id="templateBtn"
          class="px-4 py-2 rounded-[11px] font-bold transition <?= in_array($current, ['form_Templates.php', 'department_Managerment.php', 'permission_management.php']) ? 'bg-white text-teal-500 shadow' : 'text-white hover:bg-white hover:text-teal-500' ?> flex items-center space-x-1">
          <span>ตั้งค่าระบบเริ่มต้น</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div id="templateMenu" class="hidden absolute bg-white text-gray-700 mt-1 rounded-lg shadow-lg w-56 z-50">
          <a href="form_Templates.php"
            class="block px-4 py-2 hover:bg-teal-100 <?= $current === 'form_Templates.php' ? 'bg-teal-50 text-teal-600 font-semibold' : '' ?>">
            การจัดการเทมเพลต
          </a>
          <a href="department_Managerment.php"
            class="block px-4 py-2 hover:bg-teal-100 <?= $current === 'department_Managerment.php' ? 'bg-teal-50 text-teal-600 font-semibold' : '' ?>">
            การจัดการภาควิชา
          </a>
          <?php if (isset($_SESSION['permissions']) && in_array(3, $_SESSION['permissions'])): ?>
          <a href="user_Managerment.php"
            class="block px-4 py-2 hover:bg-teal-100 <?= $current === 'user_Managerment.php' ? 'bg-teal-50 text-teal-600 font-semibold' : '' ?>">
            กำหนดสิทธิ์ผู้ใช้งาน
          </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="relative">
        <button id="profileBtn"
          class="bg-white text-teal-500 px-4 py-2 rounded-[11px] shadow flex items-center space-x-2 hover:bg-gray-100">
          <div class="text-right leading-tight">
            <div class="font-bold text-[14px]"><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></div>
            <div class="text-[12px]"><?= htmlspecialchars($_SESSION['role_name'] ?? '') ?></div>
          </div>
          <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
              stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M5.121 17.804A13.937 13.937 0 0112 15c2.33 0 4.487.577 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
        </button>

        <div id="profileMenu" class="hidden absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-50">
          <a href="../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
          <button type="button" onclick="closeMenu()"
            class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
            อยู่ต่อ
          </button>
        </div>
      </div>
    </div>
  </header>


  <!-- Main Content -->
  <main class="max-w-7xl w-full px-8 mx-auto bg-white mt-6 mb-12 p-6 rounded shadow min-h-[85vh]">
    <div class="flex justify-between items-center mb-5">
      <div>
        <h2 class="text-2xl font-bold text-teal-700">การจัดการเทมเพลต</h2>
        <!-- <p class="text-sm text-gray-500 mt-1">เปิด/ปิดการใช้งานแบบฟอร์มคำถามที่จะแสดงในหน้าเลือกเอกสาร</p> -->
      </div>

    </div>

    <?php if ($flashMessage !== ''): ?>
    <div class="mb-5 rounded-xl border px-4 py-3 text-sm font-semibold <?= $flashStatus === 'delete_blocked' || $flashStatus === 'auth_required' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700' ?>">
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
          <tr class="border-b border-gray-100 <?= $isActive ? 'bg-white hover:bg-teal-50/40' : 'bg-gray-50 hover:bg-gray-100' ?> transition">
            <td class="px-4 py-4 text-center text-gray-600 font-medium">
              <?= h($row['sort_order'] ?: $row['template_id']) ?>
            </td>

            <td class="px-4 py-4">
              <div class="font-semibold text-gray-800"><?= h($row['template_name']) ?></div>
              <div class="text-xs text-gray-400 mt-1"><?= h($row['template_code']) ?></div>
            </td>

            <td class="px-4 py-4 text-center">
              <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $group === 'internal' ? 'bg-cyan-50 text-cyan-700' : 'bg-amber-50 text-amber-700' ?>">
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
                    data-current="<?= $isActive ? '1' : '0' ?>"
                    onchange="requestToggleTemplate(this)"
                    <?= $isActive ? 'checked' : '' ?>>
                  <div class="w-12 h-6 bg-gray-300 rounded-full peer peer-checked:bg-teal-500 transition"></div>
                  <div class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full shadow transition peer-checked:translate-x-6"></div>
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

                <button onclick="confirmTemplateAction('delete', <?= (int)$row['template_id'] ?>)"
                  class="w-10 h-10 flex items-center justify-center rounded-full bg-red-100 hover:bg-red-200 transition"
                  title="ลบ">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0H7m3-3h4a1 1 0 011 1v2H9V5a1 1 0 011-1z" />
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
  const profileBtn = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");

  function closeMenu() {
    if (profileMenu) {
      profileMenu.classList.add("hidden");
    }
  }

  if (profileBtn) {
    profileBtn.addEventListener("click", () => {
      profileMenu.classList.toggle("hidden");
    });
  }
  </script>

  <script>
  function verifyTemplateAdminByPrompt(callback) {
    const username = prompt("กรุณากรอกชื่อผู้ใช้:");
    if (!username) return;

    const password = prompt("กรุณากรอกรหัสผ่าน:");
    if (!password) return;

    const formData = new FormData();
    formData.append("action", "verify_template_admin");
    formData.append("admin_username", username);
    formData.append("admin_password", password);

    fetch("form_Templates.php", {
      method: "POST",
      body: formData
    })
      .then(async (response) => {
        const text = await response.text();

        try {
          return JSON.parse(text);
        } catch (error) {
          console.error("Response is not JSON:", text);
          throw new Error("ระบบตรวจสอบสิทธิ์ไม่ได้ส่ง JSON กลับมา อาจเกิดจาก path ผิด, redirect ไป login, หรือมี PHP error");
        }
      })
      .then((data) => {
        if (data && data.success) {
          if (typeof callback === "function") {
            callback();
          }
        } else {
          alert((data && data.message) ? data.message : "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง");
        }
      })
      .catch((error) => {
        alert("เกิดข้อผิดพลาด: " + error.message);
      });
  }

  function requestToggleTemplate(checkbox) {
    const currentValue = checkbox.dataset.current === "1" ? 1 : 0;
    const nextValue = checkbox.checked ? 1 : 0;

    checkbox.checked = currentValue === 1;

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
    verifyTemplateAdminByPrompt(() => {
      if (action === "add") {
        window.location.href = "template_Add.php";
      } else if (action === "edit") {
        window.location.href = "template_Edit.php?id=" + id;
      } else if (action === "delete") {
        if (confirm("ยืนยันการลบเทมเพลตนี้หรือไม่?\nถ้าเทมเพลตนี้เคยถูกใช้สร้างเอกสาร ระบบจะไม่อนุญาตให้ลบ และควรใช้การปิดใช้งานแทน")) {
          window.location.href = "template_Delete.php?id=" + id;
        }
      }
    });
  }
  </script>

  <script>
  const templateBtn = document.getElementById("templateBtn");
  const templateMenu = document.getElementById("templateMenu");

  if (templateBtn && templateMenu) {
    templateBtn.addEventListener("click", () => {
      templateMenu.classList.toggle("hidden");
    });
  }

  document.addEventListener("click", (e) => {
    if (templateBtn && templateMenu && !templateBtn.contains(e.target) && !templateMenu.contains(e.target)) {
      templateMenu.classList.add("hidden");
    }
  });
  </script>

</body>

</html>
