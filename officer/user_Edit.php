<?php
session_start();

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

$permissions = array_map('intval', $_SESSION['permissions'] ?? []);
$isAdmin = ((int)($_SESSION['role_id'] ?? 0) === 1);
$canManageUsers = in_array(3, $permissions, true);

if (!$isAdmin && !$canManageUsers) {
    $permCheck = $pdo->prepare("SELECT 1 FROM user_permissions WHERE user_id = ? AND perm_id = 3 LIMIT 1");
    $permCheck->execute([(int)$_SESSION['user_id']]);
    $canManageUsers = (bool)$permCheck->fetchColumn();

    if ($canManageUsers) {
        $_SESSION['permissions'] = array_values(array_unique(array_merge($permissions, [3])));
    }
}

if (!$isAdmin && !$canManageUsers) {
    header('Location: home.php');
    exit;
}

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT u.*, d.faculty_id
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE u.user_id = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้");
}

// ดึงสิทธิ์ทั้งหมดที่ user_id นี้มี
$permStmt = $pdo->prepare("SELECT perm_id FROM user_permissions WHERE user_id=?");
$permStmt->execute([$id]);
$userPerms = $permStmt->fetchAll(PDO::FETCH_COLUMN);

// ดึงข้อมูลคณะและภาควิชาจากฐานข้อมูลจริง
$facultiesStmt = $pdo->query("
    SELECT faculty_id, faculty_name
    FROM faculties
    ORDER BY faculty_id ASC
");
$faculties = $facultiesStmt->fetchAll(PDO::FETCH_ASSOC);

$departmentsStmt = $pdo->query("
    SELECT department_id, faculty_id, department_name
    FROM departments
    ORDER BY faculty_id ASC, department_id ASC
");
$departments = $departmentsStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedFacultyId = $user['faculty_id'] ?? ($faculties[0]['faculty_id'] ?? '');
$selectedDepartmentId = $user['department_id'] ?? '';
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
  </header>

  <div class="max-w-3xl mx-auto mt-10 bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="bg-teal-500 text-white text-center py-8 relative">
      <div class="flex justify-center">
        <div class="w-20 h-20 rounded-full bg-white flex items-center justify-center">
          <svg class="h-12 w-12 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.33 
                               0 4.487.577 6.879 1.804M15 
                               10a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
      </div>
      <h1 class="text-3xl font-bold mt-4">การแก้ไขผู้ใช้งานระบบ</h1>
      <p class="text-sm text-white/80">ปรับปรุงข้อมูลของผู้ใช้ในระบบ</p>
    </div>

    <form action="user_process.php" method="POST" class="p-8 space-y-6">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" value="<?= h($user['user_id']) ?>">

      <!-- Username + Password -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block font-semibold text-gray-700 mb-1">ชื่อผู้ใช้</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 
                           13.937 0 0112 15c2.33 
                           0 4.487.577 6.879 
                           1.804M15 10a3 3 0 
                           11-6 0 3 3 0 016 0z" />
              </svg>
            </span>
            <input type="text" name="username" value="<?= h($user['username']) ?>"
              class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400"
              placeholder="Username" required>
          </div>
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-1">รหัสผ่าน (ใส่ถ้าต้องการเปลี่ยน)</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.105.895-2 2-2s2 
                           .895 2 2v1h-4v-1zM6 11V9a6 
                           6 0 1112 0v2m-6 4h.01" />
              </svg>
            </span>
            <input type="password" name="password"
              class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400"
              placeholder="Password">
          </div>
        </div>
      </div>

      <!-- Fullname -->
      <div>
        <label class="block font-semibold text-gray-700 mb-1">ชื่อจริง-สกุล</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 
                       0 0112 15c2.33 0 4.487.577 
                       6.879 1.804M15 10a3 3 0 
                       11-6 0 3 3 0 016 0z" />
            </svg>
          </span>
          <input type="text" name="fullname" value="<?= h($user['fullname']) ?>"
            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400"
            placeholder="Full name" required>
        </div>
      </div>

      <!-- Email -->
      <div class="mb-6">
        <label class="block font-semibold text-gray-700 mb-1">อีเมล</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m0 
                         0a2 2 0 00-2-2H5a2 2 0 
                         00-2 2m18 0v8a2 2 0 01-2 
                         2H5a2 2 0 01-2-2V8" />
            </svg>
          </span>
          <input type="email" name="email" value="<?= h($user['email']) ?>"
            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400"
            placeholder="Email" required>
        </div>
      </div>

      <!-- Role + Position -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <!-- Role -->
        <div>
          <label class="block font-semibold text-gray-700 mb-2">สิทธิ์การเข้าถึง</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.105.895-2 2-2s2 
                          .895 2 2v1h-4v-1zM6 11V9a6 6 
                          0 1112 0v2m-6 4h.01" />
              </svg>
            </span>
            <select name="role_id"
              class="w-full pl-10 pr-3 py-2 border rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-teal-400">
              <option value="1" <?= ((int)$user['role_id'] === 1) ? 'selected' : '' ?>>Admin</option>
              <option value="2" <?= ((int)$user['role_id'] === 2) ? 'selected' : '' ?>>Officer</option>
              <option value="3" <?= ((int)$user['role_id'] === 3) ? 'selected' : '' ?>>User</option>
            </select>
          </div>
        </div>

        <!-- Position -->
        <div>
          <label class="block font-semibold text-gray-700 mb-2">ตำแหน่ง</label>

          <!-- ช่องเดียว: พิมพ์เองได้ และคลิกเลือกจากรายการได้ -->
          <div class="relative">
            <input type="text" name="position" id="positionInput" value="<?= h($user['position'] ?? '') ?>"
              class="w-full pl-3 pr-10 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-400"
              placeholder="เลือกหรือพิมพ์ตำแหน่งเอง เช่น อาจารย์ประจำภาควิชา" autocomplete="off" required>

            <button type="button" id="positionToggle"
              class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-teal-600"
              aria-label="เลือกตำแหน่ง">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div id="positionDropdown"
              class="hidden absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
              <button type="button" data-position="เจ้าหน้าที่"
                class="position-option block w-full text-left px-3 py-2 hover:bg-teal-50">เจ้าหน้าที่</button>
              <button type="button" data-position="อาจารย์"
                class="position-option block w-full text-left px-3 py-2 hover:bg-teal-50">อาจารย์</button>
              <button type="button" data-position="นักศึกษา"
                class="position-option block w-full text-left px-3 py-2 hover:bg-teal-50">นักศึกษา</button>
              <button type="button" data-position="บุคลากร"
                class="position-option block w-full text-left px-3 py-2 hover:bg-teal-50">บุคลากร</button>
              <button type="button" data-position="พนักงานมหาวิทยาลัย"
                class="position-option block w-full text-left px-3 py-2 hover:bg-teal-50">พนักงานมหาวิทยาลัย</button>
              <button type="button" data-position="อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ"
                class="position-option block w-full text-left px-3 py-2 hover:bg-teal-50">อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ</button>
              <button type="button" data-position="ผู้ช่วยศาสตราจารย์"
                class="position-option block w-full text-left px-3 py-2 hover:bg-teal-50">ผู้ช่วยศาสตราจารย์</button>
              <button type="button" data-position="รองศาสตราจารย์"
                class="position-option block w-full text-left px-3 py-2 hover:bg-teal-50">รองศาสตราจารย์</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Faculty + Department -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
          <label class="block font-semibold text-gray-700 mb-2">คณะ</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 21h18M4 21V7l8-4 8 4v14M9 21v-6h6v6" />
              </svg>
            </span>
            <select id="faculty_id" name="faculty_id"
              class="w-full pl-10 pr-3 py-2 border rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-teal-400"
              required>
              <?php foreach ($faculties as $faculty): ?>
              <option value="<?= h($faculty['faculty_id']) ?>"
                <?= ((string)$faculty['faculty_id'] === (string)$selectedFacultyId) ? 'selected' : '' ?>>
                <?= h($faculty['faculty_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-2">ภาควิชา</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M9 8h6M9 12h6M9 16h6M4 21V5a2 2 0 012-2h12a2 
                         2 0 012 2v16" />
              </svg>
            </span>
            <select id="department_id" name="department_id"
              class="w-full pl-10 pr-3 py-2 border rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-teal-400"
              required>
              <?php foreach ($departments as $department): ?>
              <option value="<?= h($department['department_id']) ?>"
                data-faculty-id="<?= h($department['faculty_id']) ?>"
                <?= ((string)$department['department_id'] === (string)$selectedDepartmentId) ? 'selected' : '' ?>>
                <?= h(trim($department['department_name'])) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Permissions -->
      <div class="mb-6">
        <label class="block font-semibold text-gray-700 mb-2">สิทธิ์ในการเข้าถึง</label>
        <div class="flex space-x-6 flex-wrap gap-y-3">
          <label class="flex items-center space-x-2">
            <input type="checkbox" name="permissions[]" value="1"
              class="w-5 h-5 text-teal-600 border-2 border-teal-500 rounded focus:ring-teal-400"
              <?= in_array(1, array_map('intval', $userPerms), true) ? 'checked' : '' ?>>
            <span>แก้ไขได้</span>
          </label>

          <label class="flex items-center space-x-2">
            <input type="checkbox" name="permissions[]" value="2"
              class="w-5 h-5 text-teal-600 border-2 border-teal-500 rounded focus:ring-teal-400"
              <?= in_array(2, array_map('intval', $userPerms), true) ? 'checked' : '' ?>>
            <span>ดูได้</span>
          </label>

          <label class="flex items-center space-x-2">
            <input type="checkbox" name="permissions[]" value="3"
              class="w-5 h-5 text-teal-600 border-2 border-teal-500 rounded focus:ring-teal-400"
              <?= in_array(3, array_map('intval', $userPerms), true) ? 'checked' : '' ?>>
            <span>กำหนดสิทธิ์ได้</span>
          </label>
        </div>
      </div>

      <!-- Status -->
      <div class="mb-6">
        <label class="block font-semibold text-gray-700 mb-2">สถานะการใช้งาน</label>
        <div class="flex items-center space-x-6">
          <label class="flex items-center space-x-2">
            <input type="radio" name="is_active" value="1" class="text-teal-500 focus:ring-teal-400"
              <?= ((int)$user['is_active'] === 1) ? 'checked' : '' ?>>
            <span>เปิดการใช้งาน</span>
          </label>

          <label class="flex items-center space-x-2">
            <input type="radio" name="is_active" value="0" class="text-teal-500 focus:ring-teal-400"
              <?= ((int)$user['is_active'] === 0) ? 'checked' : '' ?>>
            <span>ปิดการใช้งาน</span>
          </label>
        </div>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end space-x-3 pt-4">
        <a href="user_Managerment.php"
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

  <script>
  const positionInput = document.getElementById('positionInput');
  const positionToggle = document.getElementById('positionToggle');
  const positionDropdown = document.getElementById('positionDropdown');
  const positionOptions = Array.from(document.querySelectorAll('.position-option'));

  function showPositionDropdown() {
    if (positionDropdown) {
      positionDropdown.classList.remove('hidden');
    }
  }

  function hidePositionDropdown() {
    if (positionDropdown) {
      positionDropdown.classList.add('hidden');
    }
  }

  positionToggle?.addEventListener('click', function(event) {
    event.preventDefault();
    event.stopPropagation();

    if (positionDropdown.classList.contains('hidden')) {
      showPositionDropdown();
      positionInput?.focus();
    } else {
      hidePositionDropdown();
    }
  });

  positionInput?.addEventListener('focus', showPositionDropdown);

  positionOptions.forEach(option => {
    option.addEventListener('mousedown', function(event) {
      event.preventDefault();
      if (positionInput) {
        positionInput.value = this.dataset.position || this.textContent.trim();
        positionInput.focus();
      }
      hidePositionDropdown();
    });
  });

  document.addEventListener('click', function(event) {
    if (
      positionDropdown &&
      positionInput &&
      positionToggle &&
      !positionDropdown.contains(event.target) &&
      event.target !== positionInput &&
      event.target !== positionToggle &&
      !positionToggle.contains(event.target)
    ) {
      hidePositionDropdown();
    }
  });

  const facultySelect = document.getElementById('faculty_id');
  const departmentSelect = document.getElementById('department_id');
  const departmentOptions = Array.from(departmentSelect.options);
  const selectedDepartmentId = "<?= h($selectedDepartmentId) ?>";

  function filterDepartmentsByFaculty(keepSelected = false) {
    const selectedFacultyId = facultySelect.value;
    let firstVisibleValue = '';

    departmentOptions.forEach(option => {
      const isMatch = option.dataset.facultyId === selectedFacultyId;
      option.hidden = !isMatch;
      option.disabled = !isMatch;

      if (isMatch && firstVisibleValue === '') {
        firstVisibleValue = option.value;
      }
    });

    if (keepSelected && selectedDepartmentId) {
      departmentSelect.value = selectedDepartmentId;
    }

    const currentOption = departmentSelect.options[departmentSelect.selectedIndex];
    if (!currentOption || currentOption.disabled) {
      departmentSelect.value = firstVisibleValue;
    }
  }

  facultySelect.addEventListener('change', () => filterDepartmentsByFaculty(false));
  filterDepartmentsByFaculty(true);
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", function () {
    const userForm = document.querySelector('form[action="user_process.php"]');
    if (userForm) {
      userForm.addEventListener("submit", function () {
        sessionStorage.setItem("user_success_popup", "edit");
      });
    }
  });
  </script>

</body>

</html>