<?php
// session_start();
// if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
//     header('Location: login.html');
//     exit;
// }
session_start();

// ถ้ายังไม่ได้ล็อกอินเลย → กลับหน้า login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// ✅ อนุญาตให้เข้าได้เฉพาะ "admin" หรือ "ผู้ที่มีสิทธิ์กำหนดสิทธิ์ (perm_id = 3)"
if ($_SESSION['role_id'] != 1 && !in_array(3, $_SESSION['permissions'] ?? [])) {
    // ถ้าไม่มีสิทธิ์ ก็ให้กลับไปหน้าหลักของ role ตัวเอง
    switch ($_SESSION['role_id']) {
        case 2:
            header('Location: officer/home.php');
            break;
        case 3:
            header('Location: user/home.php');
            break;
        default:
            header('Location: login.html');
    }
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

// ค่า tab ที่เลือก
$activeTab = $_GET['tab'] ?? 'all';
$current = basename($_SERVER['PHP_SELF']);

// Pagination setup
$limit = 20; // จำนวนแถวต่อหน้า
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// นับจำนวนทั้งหมด
$countSql = "SELECT COUNT(*) FROM users";
$totalRows = $pdo->query($countSql)->fetchColumn();
$totalPages = ceil($totalRows / $limit);

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
        FROM users u";

// เพิ่มเงื่อนไขตาม tab
if ($activeTab === 'active') {
    $sql .= " WHERE u.is_active = 1";
} elseif ($activeTab === 'inactive') {
    $sql .= " WHERE u.is_active = 0";
}

// เพิ่ม LIMIT และ OFFSET สำหรับแบ่งหน้า
$sql .= " LIMIT $limit OFFSET $offset";

$stmt = $pdo->query($sql);
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
          class="px-4 py-2 rounded-[11px] font-bold transition <?= in_array($current, ['form_Templates.php', 'department_Managerment.php', 'user_Managerment.php']) ? 'bg-white text-teal-500 shadow' : 'text-white hover:bg-white hover:text-teal-500' ?> flex items-center space-x-1">
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
  <main class="max-w-7xl w-full px-8 mx-auto mt-6 mb-12 min-h-[85vh]">
    <div class="bg-white shadow rounded-lg p-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-teal-600 tracking-wide drop-shadow-sm">
          การจัดการสิทธิ์ของผู้ใช้
        </h2>

        <button onclick="confirmUserAction('add')" class="flex items-center gap-2 border border-teal-500 text-teal-600 font-semibold 
           px-5 py-2 rounded-lg hover:bg-teal-50 hover:shadow-md transition duration-200">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
            class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          เพิ่มผู้ใช้
        </button>

      </div>

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
            <?php foreach ($users as $index => $row): ?>
            <tr class="<?= $index % 2 === 0 ? 'bg-teal-10' : 'bg-teal-50' ?> hover:bg-teal-200/30 transition-colors">
              <!-- ชื่อ -->
              <td class="px-4 py-3 flex items-center space-x-3">
                <div
                  class="w-9 h-9 flex items-center justify-center rounded-full bg-teal-400 text-white font-semibold shadow">
                  <?= mb_substr($row['fullname'],0,1) ?>
                </div>
                <div>
                  <p class="font-medium text-gray-800"><?= htmlspecialchars($row['fullname']) ?></p>
                </div>
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
                <?php if ($row['is_active'] == 1): ?>
                <span
                  class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-700 font-medium shadow-inner">Active</span>
                <?php else: ?>
                <span
                  class="px-3 py-1 text-xs rounded-full bg-red-100 text-red-600 font-medium shadow-inner">Inactive</span>
                <?php endif; ?>
              </td>

              <!-- Actions -->
              <td class="px-4 py-3 text-center">
                <div class="flex justify-center gap-2">
                  <button onclick="confirmUserAction('edit', <?= $row['user_id'] ?>)"
                    class="p-2 bg-blue-100 text-blue-600 rounded-full hover:bg-blue-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                      stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 20h9M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4 12.5-12.5z" />
                    </svg>
                  </button>
                  <button onclick="confirmUserAction('delete', <?= $row['user_id'] ?>)"
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
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex justify-between items-center mt-6 text-sm text-gray-600">
        <span>
          Showing <?= ($offset + 1) ?>–
          <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> entries
        </span>

        <div class="flex items-center space-x-2">
          <!-- ปุ่ม Prev -->
          <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>&tab=<?= $activeTab ?>"
            class="px-3 py-1 rounded-md text-teal-600 border border-teal-400 hover:bg-teal-100 transition shadow-sm">
            Prev
          </a>
          <?php endif; ?>

          <!-- ปุ่มตัวเลข -->
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&tab=<?= $activeTab ?>"
            class="px-3 py-1 rounded-md font-medium <?= $i == $page ? 'bg-teal-500 text-white shadow-md hover:bg-teal-600' : 'text-teal-600 border border-teal-400 hover:bg-teal-100' ?> transition">
            <?= $i ?>
          </a>
          <?php endfor; ?>

          <!-- ปุ่ม Next -->
          <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page + 1 ?>&tab=<?= $activeTab ?>"
            class="px-3 py-1 rounded-md text-teal-600 border border-teal-400 hover:bg-teal-100 transition shadow-sm">
            Next
          </a>
          <?php endif; ?>
        </div>
      </div>

  </main>

  <script>
  const profileBtn = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");
  if (profileBtn && profileMenu) {
    profileBtn.addEventListener("click", () => {
      profileMenu.classList.toggle("hidden");
    });
  }

  function closeMenu() {
    if (profileMenu) {
      profileMenu.classList.add("hidden");
    }
  }
  window.addEventListener("click", (e) => {
    if (profileBtn && profileMenu && !profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
      profileMenu.classList.add("hidden");
    }
  });
  </script>
  <script>
  function confirmUserAction(action, id = null) {
    let username = prompt("กรุณากรอกชื่อผู้ใช้:");
    if (!username) return;
    let password = prompt("กรุณากรอกรหัสผ่าน:");
    if (!password) return;
    fetch("admin/verify_user.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: "username=" + encodeURIComponent(username) + "&password=" + encodeURIComponent(password)
    }).then(res => res.json()).then(data => {
      if (data.success) {
        if (action === "add") {
          window.location.href = "admin/user_Add.php";
        } else if (action === "edit") {
          window.location.href = "admin/user_Edit.php?id=" + id;
        } else if (action === "delete") {
          if (confirm("คุณแน่ใจว่าต้องการลบผู้ใช้นี้หรือไม่?")) {
            window.location.href = "admin/user_Delete.php?id=" + id;
          }
        }
      } else {
        alert("ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง");
      }
    }).catch(err => alert("เกิดข้อผิดพลาด: " + err));
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

  // ปิด dropdown ถ้าคลิกนอกเมนู
  document.addEventListener("click", (e) => {
    if (templateBtn && templateMenu && !templateBtn.contains(e.target) && !templateMenu.contains(e.target)) {
      templateMenu.classList.add("hidden");
    }
  });
  </script>
</body>

</html>