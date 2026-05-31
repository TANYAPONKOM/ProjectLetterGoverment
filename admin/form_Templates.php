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
          throw new Error(
            "ระบบตรวจสอบสิทธิ์ไม่ได้ส่ง JSON กลับมา อาจเกิดจาก path ผิด, redirect ไป login, หรือมี PHP error");
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
        if (confirm(
            "ยืนยันการลบเทมเพลตนี้หรือไม่?\nถ้าเทมเพลตนี้เคยถูกใช้สร้างเอกสาร ระบบจะไม่อนุญาตให้ลบ และควรใช้การปิดใช้งานแทน"
            )) {
          window.location.href = "template_Delete.php?id=" + id;
        }
      }
    });
  }
  </script>
</body>

</html>