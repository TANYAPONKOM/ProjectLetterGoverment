<?php
session_start();
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

// อนุญาตให้เข้าได้เฉพาะ admin หรือผู้ที่มีสิทธิ์กำหนดสิทธิ์ (perm_id = 3)
$sessionPerms = array_map('intval', $_SESSION['permissions'] ?? []);
$hasManageUserPermission = ((int)($_SESSION['role_id'] ?? 0) === 1) || in_array(3, $sessionPerms, true);

if (!$hasManageUserPermission) {
    $permCheck = $pdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = ? AND perm_id = 3 LIMIT 1");
    $permCheck->execute([(int)$_SESSION['user_id']]);
    $hasManageUserPermission = (bool)$permCheck->fetchColumn();

    if ($hasManageUserPermission) {
        $_SESSION['permissions'] = array_values(array_unique(array_merge($sessionPerms, [3])));
    }
}

if (!$hasManageUserPermission) {
    header('Location: home.php');
    exit;
}

// ค่า tab ที่เลือก
$activeTab = $_GET['tab'] ?? 'all';
$current = basename($_SERVER['PHP_SELF']);

// ค่าค้นหา
$search = trim($_GET['search'] ?? '');

// Pagination setup
$limit = 20; // จำนวนแถวต่อหน้า
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// สร้างเงื่อนไขสำหรับ tab และ search
$where = [];
$params = [];

// เพิ่มเงื่อนไขตาม tab
if ($activeTab === 'active') {
    $where[] = "u.is_active = 1";
} elseif ($activeTab === 'inactive') {
    $where[] = "u.is_active = 0";
}

// เพิ่มเงื่อนไขค้นหา
if ($search !== '') {
    $where[] = "(
        u.fullname LIKE :search
        OR u.email LIKE :search
        OR u.position LIKE :search
        OR CASE 
            WHEN u.role_id = 1 THEN 'Admin'
            WHEN u.role_id = 2 THEN 'Officer'
            WHEN u.role_id = 3 THEN 'User'
            ELSE 'Unknown'
        END LIKE :search
        OR CASE 
            WHEN u.department_id = 1 THEN 'เทคโนโลยีสารสนเทศ'
            ELSE 'ไม่ระบุ'
        END LIKE :search
        OR CASE 
            WHEN u.is_active = 1 THEN 'Active'
            ELSE 'Inactive'
        END LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

$whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

// นับจำนวนทั้งหมดตามเงื่อนไข
$countSql = "SELECT COUNT(*) FROM users u" . $whereSql;
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$countStmt->execute();
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// สร้าง SQL พื้นฐาน
$sql = "SELECT u.*, 
        CASE 
            WHEN u.role_id = 1 THEN 'Admin'
            WHEN u.role_id = 2 THEN 'Officer'
            WHEN u.role_id = 3 THEN 'User'
            ELSE 'Unknown'
        END AS role_name,
        CASE 
            WHEN u.department_id = 1 THEN 'เทคโนโลยีสารสนเทศ'
            ELSE 'ไม่ระบุ'
        END AS department_name
        FROM users u
        $whereSql
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$permMap = [];
$permStmt = $pdo->query("SELECT * FROM user_permissions");
while ($r = $permStmt->fetch(PDO::FETCH_ASSOC)) {
    $permMap[$r['user_id']][] = $r['perm_id'];
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>การจัดการสิทธิ์ของผู้ใช้</title>
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
  /* ✅ ทำให้ checkbox ปกติใช้สีฟ้า */
  input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border: 2px solid #7cd3f8ff;
    /* ขอบ teal */
    border-radius: 4px;
    background-color: white;
    cursor: not-allowed;
    position: relative;
  }

  input[type="checkbox"]:checked::before {
    content: "✓";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -70%) scaleY(1.4);
    /* ✅ ขยับขึ้นและหางยาวขึ้น */
    font-size: 14px;
    color: #b6ddf3ff;
    /* ฟ้า Sky Blue */
    font-weight: bold;
  }


  input[type="checkbox"]:checked {
    /* background-color: #cbe9ff; */
    background-color: #53c8f3ff;

    border-color: #a1e3ffff;
  }

  /* ✅ เมื่อ hover (แม้จะ disabled) */
  input[type="checkbox"]:hover {
    filter: brightness(1.1);
  }
  </style>

</head>


<body class="bg-gray-100">
  <!-- Header -->
  <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>

  <!-- Main Content -->
  <main class="max-w-7xl w-full px-8 mx-auto mt-6 mb-12 min-h-[85vh]">
    <div class="bg-white shadow rounded-lg p-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-teal-600 tracking-wide drop-shadow-sm">
          การจัดการสิทธิ์ของผู้ใช้
        </h2>

        <button type="button" onclick="confirmUserAction('add')" class="flex items-center gap-2 border border-teal-500 text-teal-600 font-semibold 
           px-5 py-2 rounded-lg hover:bg-teal-50 hover:shadow-md transition duration-200">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
            class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          เพิ่มผู้ใช้
        </button>

      </div>

      <!-- Search Box -->
      <form method="GET" class="mt-6 mb-4 flex flex-col md:flex-row md:items-center gap-3">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
        <input type="hidden" name="page" value="1">

        <div class="relative w-full md:w-96">
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
            placeholder="ค้นหาชื่อผู้ใช้ อีเมล สิทธิ์ ตำแหน่ง หรือสถานะ"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-teal-400">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 absolute right-3 top-2.5" fill="none"
            viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <button type="submit"
          class="px-6 py-2 rounded-lg bg-teal-500 text-white font-semibold hover:bg-teal-600 transition">
          ค้นหา
        </button>

        <?php if ($search !== ''): ?>
        <a href="?tab=<?= urlencode($activeTab) ?>"
          class="px-6 py-2 rounded-lg border border-gray-300 text-gray-600 font-semibold hover:bg-gray-100 transition text-center">
          ล้างค่า
        </a>
        <?php endif; ?>
      </form>

      <!-- Modern Alternating Row Table -->
      <div class="mt-6 overflow-x-auto rounded-lg shadow">
        <table class="w-full text-sm border-collapse overflow-hidden">
          <!-- Header -->
          <thead class="bg-teal-500 text-white">
            <tr>
              <th class="px-4 py-3 text-left font-semibold">ชื่อผู้ใช้</th>
              <th class="px-4 py-3 text-left font-semibold">อีเมล</th>
              <th class="px-4 py-3 text-left font-semibold">สิทธิ์</th>
              <th class="px-4 py-3 text-left font-semibold">ตำแหน่ง</th>
              <th class="px-4 py-3 text-center font-semibold">แก้ไขได้</th>
              <th class="px-4 py-3 text-center font-semibold">ดูได้</th>
              <th class="px-4 py-3 text-center font-semibold">กำหนดสิทธิ์ได้</th>
              <th class="px-4 py-3 text-center font-semibold">สถานะ</th>
              <th class="px-4 py-3 text-center font-semibold">การจัดการ</th>
            </tr>
          </thead>

          <!-- Body -->
          <tbody>
            <?php if (empty($users)): ?>
            <tr>
              <td colspan="9" class="px-4 py-8 text-center text-gray-500 bg-gray-50">
                ไม่พบข้อมูลที่ค้นหา
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($users as $index => $row): ?>
            <tr class="<?= $index % 2 === 0 ? 'bg-teal-10' : 'bg-teal-50' ?> hover:bg-teal-200/30 transition-colors">
              <!-- ชื่อ -->
              <td class="px-4 py-3 text-gray-800 font-medium">
                <?= htmlspecialchars($row['fullname']) ?>
              </td>

              <!-- Email -->
              <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars($row['email']) ?></td>

              <!-- Role -->
              <td class="px-4 py-3">
                <span class="px-3 py-1 text-xs rounded-full font-medium 
            <?= $row['role_name'] === 'Admin' ? 'bg-white/60 text-teal-800 border border-teal-300' : 
                ($row['role_name'] === 'Officer' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700') ?>">
                  <?= htmlspecialchars($row['role_name']) ?>
                </span>
              </td>

              <!-- Position -->
              <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars($row['position']) ?></td>

              <!-- Permissions -->
              <td class="px-4 py-3 text-center">
                <input type="checkbox" class="w-4 h-4 rounded border-2 border-teal-500 bg-gray-50 cursor-not-allowed"
                  <?= in_array(1, $permMap[$row['user_id']] ?? []) ? 'checked' : '' ?> disabled>
              </td>
              <td class="px-4 py-3 text-center">
                <input type="checkbox" class="w-4 h-4 rounded border-2 border-teal-500 bg-gray-50 cursor-not-allowed"
                  <?= in_array(2, $permMap[$row['user_id']] ?? []) ? 'checked' : '' ?> disabled>
              </td>
              <td class="px-4 py-3 text-center">
                <input type="checkbox" class="w-4 h-4 rounded border-2 border-teal-500 bg-gray-50 cursor-not-allowed"
                  <?= in_array(3, $permMap[$row['user_id']] ?? []) ? 'checked' : '' ?> disabled>
              </td>

              <!-- Status -->
              <td class="px-4 py-3 text-center">
                <div class="flex flex-col items-center gap-1">
                  <?php if ($row['is_active'] == 1): ?>
                  <span
                    class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-700 font-medium shadow-inner">Active</span>
                  <?php else: ?>
                  <span
                    class="px-3 py-1 text-xs rounded-full bg-red-100 text-red-600 font-medium shadow-inner">Inactive</span>
                  <?php endif; ?>

                  <?php if (($row['auth_provider'] ?? '') === 'google' && (int)($row['profile_completed'] ?? 1) === 0): ?>
                  <span class="px-3 py-1 text-xs rounded-full bg-red-100 text-red-600 font-medium shadow-inner">
                    รอเพิ่มข้อมูล
                  </span>
                  <?php elseif ((int)($row['profile_completed'] ?? 1) === 1): ?>
                  <span class="px-3 py-1 text-xs rounded-full bg-teal-100 text-teal-700 font-medium shadow-inner">
                    ข้อมูลครบแล้ว
                  </span>
                  <?php endif; ?>
                </div>
              </td>

              <!-- Actions -->
              <td class="px-4 py-3 text-center">
                <div class="flex justify-center gap-2">
                  <button type="button" onclick="confirmUserAction('edit', <?= $row['user_id'] ?>)"
                    class="p-2 bg-blue-100 text-blue-600 rounded-full hover:bg-blue-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                      stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 20h9M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                    </svg>
                  </button>
                  <button type="button" onclick="confirmUserAction('delete', <?= $row['user_id'] ?>)"
                    class="p-2 bg-red-100 text-red-600 rounded-full hover:bg-red-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                      stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-between items-center mt-6 text-sm text-gray-600">
        <span>
          Showing <?= $totalRows > 0 ? ($offset + 1) : 0 ?>–
          <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> entries
        </span>

        <div class="flex items-center space-x-2">
          <!-- ปุ่ม Prev -->
          <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>&tab=<?= urlencode($activeTab) ?>&search=<?= urlencode($search) ?>"
            class="px-3 py-1 rounded-md text-teal-600 border border-teal-400 hover:bg-teal-100 transition shadow-sm">
            Prev
          </a>
          <?php endif; ?>

          <!-- ปุ่มตัวเลข -->
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&tab=<?= urlencode($activeTab) ?>&search=<?= urlencode($search) ?>"
            class="px-3 py-1 rounded-md font-medium <?= $i == $page ? 'bg-teal-500 text-white shadow-md hover:bg-teal-600' : 'text-teal-600 border border-teal-400 hover:bg-teal-100' ?> transition">
            <?= $i ?>
          </a>
          <?php endfor; ?>

          <!-- ปุ่ม Next -->
          <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page + 1 ?>&tab=<?= urlencode($activeTab) ?>&search=<?= urlencode($search) ?>"
            class="px-3 py-1 rounded-md text-teal-600 border border-teal-400 hover:bg-teal-100 transition shadow-sm">
            Next
          </a>
          <?php endif; ?>
        </div>
      </div>

  </main>
  <script>
  function showUserPopup(options = {}) {
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

  async function confirmUserAction(action, id = null) {
    if (action === "add") {
      window.location.href = "user_Add.php";
      return false;
    }

    if (action === "edit") {
      window.location.href = "user_Edit.php?id=" + encodeURIComponent(id);
      return false;
    }

    if (action === "delete") {
      const confirmed = await showUserPopup({
        title: "ยืนยันการลบผู้ใช้",
        message: "คุณแน่ใจว่าต้องการลบผู้ใช้นี้หรือไม่?",
        confirmText: "ลบผู้ใช้",
        cancelText: "ยกเลิก",
        danger: true
      });

      if (confirmed) {
        window.location.href = "user_Delete.php?id=" + encodeURIComponent(id);
      }
      return false;
    }

    return false;
  }
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", function() {
    let userSuccessAction = <?= json_encode($_GET['success'] ?? '') ?>;
    if (userSuccessAction === "1" || userSuccessAction === "deactivated") {
      userSuccessAction = "delete";
    }



    sessionStorage.removeItem("user_success_popup");

    const userSuccessMessages = {
      add: "เพิ่มผู้ใช้สำเร็จ",
      edit: "แก้ไขผู้ใช้สำเร็จ",
      delete: "ลบผู้ใช้สำเร็จ"
    };

    if (!userSuccessMessages[userSuccessAction]) return;

    const overlay = document.createElement("div");
    overlay.style.position = "fixed";
    overlay.style.inset = "0";
    overlay.style.zIndex = "99999";
    overlay.style.display = "flex";
    overlay.style.alignItems = "center";
    overlay.style.justifyContent = "center";
    overlay.style.background = "rgba(0, 0, 0, 0.38)";

    const box = document.createElement("div");
    box.style.width = "640px";
    box.style.maxWidth = "calc(100vw - 32px)";
    box.style.background = "#ffffff";
    box.style.borderRadius = "6px";
    box.style.padding = "44px 28px 30px";
    box.style.textAlign = "center";
    box.style.boxShadow = "0 22px 55px rgba(15, 23, 42, 0.28)";
    box.style.fontFamily = "Arial, sans-serif";

    const iconWrap = document.createElement("div");
    iconWrap.style.width = "110px";
    iconWrap.style.height = "110px";
    iconWrap.style.margin = "6px auto 28px";
    iconWrap.style.borderRadius = "50%";
    iconWrap.style.border = "5px solid #d9f0ce";
    iconWrap.style.display = "flex";
    iconWrap.style.alignItems = "center";
    iconWrap.style.justifyContent = "center";

    const icon = document.createElement("div");
    icon.innerHTML = "&#10003;";
    icon.style.color = "#9bd68d";
    icon.style.fontSize = "70px";
    icon.style.lineHeight = "1";
    icon.style.fontWeight = "400";

    const title = document.createElement("div");
    title.textContent = userSuccessMessages[userSuccessAction];
    title.style.fontSize = "36px";
    title.style.fontWeight = "800";
    title.style.color = "#555555";
    title.style.marginBottom = "4px";

    const okBtn = document.createElement("button");
    okBtn.type = "button";
    okBtn.textContent = "ตกลง";
    okBtn.style.marginTop = "28px";
    okBtn.style.padding = "10px 34px";
    okBtn.style.border = "none";
    okBtn.style.borderRadius = "8px";
    okBtn.style.background = "#14b8a6";
    okBtn.style.color = "#ffffff";
    okBtn.style.fontSize = "16px";
    okBtn.style.fontWeight = "700";
    okBtn.style.cursor = "pointer";

    function closeSuccessPopup() {
      if (document.body.contains(overlay)) {
        document.body.removeChild(overlay);
      }
      const url = new URL(window.location.href);
      url.searchParams.delete("success");
      window.history.replaceState({}, document.title, url.toString());
    }

    okBtn.addEventListener("click", closeSuccessPopup);
    overlay.addEventListener("click", function(e) {
      if (e.target === overlay) closeSuccessPopup();
    });
    document.addEventListener("keydown", function escHandler(e) {
      if (!document.body.contains(overlay)) {
        document.removeEventListener("keydown", escHandler);
        return;
      }
      if (e.key === "Enter" || e.key === "Escape") {
        document.removeEventListener("keydown", escHandler);
        closeSuccessPopup();
      }
    });

    iconWrap.appendChild(icon);
    box.appendChild(iconWrap);
    box.appendChild(title);
    box.appendChild(okBtn);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    okBtn.focus();
  });
  </script>

</body>

</html>