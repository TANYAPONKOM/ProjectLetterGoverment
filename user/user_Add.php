<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

function currentUserCanManageUsers(PDO $pdo): bool
{
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);

    if ($currentUserId <= 0) {
        return false;
    }

    $sessionPermissions = $_SESSION['permissions'] ?? [];

    if (is_string($sessionPermissions)) {
        $sessionPermissions = array_filter(array_map('trim', explode(',', $sessionPermissions)));
    }

    if (!is_array($sessionPermissions)) {
        $sessionPermissions = [];
    }

    $sessionPermissions = array_map('intval', $sessionPermissions);

    if (in_array(3, $sessionPermissions, true)) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_permissions
        WHERE user_id = ?
          AND perm_id = 3
    ");
    $stmt->execute([$currentUserId]);

    return ((int)$stmt->fetchColumn() > 0);
}

if (!currentUserCanManageUsers($pdo)) {
    $roleId = (int)($_SESSION['role_id'] ?? 0);

    if ($roleId === 2) {
        header('Location: ../officer/home.php');
    } elseif ($roleId === 3) {
        header('Location: ../user/home.php');
    } else {
        header('Location: ../login.html');
    }
    exit;
}

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/*
    ดึงข้อมูลคณะและภาควิชาจากฐานข้อมูลจริง
    ตารางที่ใช้:
    - faculties(faculty_id, faculty_name)
    - departments(department_id, faculty_id, department_name, phone)
*/
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

$defaultFacultyId = $faculties[0]['faculty_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>Add User</title>
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

  <!-- Header Card -->
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
      <h1 class="text-3xl font-bold mt-4">การเพิ่มผู้ใช้งานระบบ</h1>
      <p class="text-sm text-white/80">กรอกข้อมูลเพื่อเพิ่มผู้ใช้ใหม่เข้าสู่ระบบ</p>
    </div>

    <!-- Form -->
    <form action="user_process.php" method="POST" class="p-8 space-y-6">
      <input type="hidden" name="action" value="add">

      <!-- Username + Password -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block font-semibold text-gray-700 mb-1">ชื่อผู้ใช้</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.33 
                                       0 4.487.577 6.879 1.804M15 
                                       10a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </span>
            <input type="text" name="username"
              class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" placeholder="Username"
              required>
          </div>
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-1">รหัสผ่าน</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.105.895-2 2-2s2 
                                       .895 2 2v1h-4v-1zM6 11V9a6 
                                       6 0 1112 0v2m-6 4h.01" />
              </svg>
            </span>
            <input type="password" name="password"
              class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" placeholder="Password"
              required>
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
          <input type="text" name="fullname"
            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400"
            placeholder="นายสมชาย ใจดี" required>
        </div>
      </div>

      <!-- Email -->
      <div>
        <label class="block font-semibold text-gray-700 mb-1">อีเมล</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 
                                   0L21 8m0 0a2 2 0 00-2-2H5a2 
                                   2 0 00-2 2m18 0v8a2 2 0 
                                   01-2 2H5a2 2 0 01-2-2V8" />
            </svg>
          </span>
          <input type="email" name="email"
            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" placeholder="Email"
            required>
        </div>
      </div>

      <!-- Role + Position -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block font-semibold text-gray-700 mb-2">สิทธิ์การเข้าถึง</label>
          <select name="role_id" class="w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400">
            <option value="1">Admin</option>
            <option value="2">Officer</option>
            <option value="3">User</option>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-2">ตำแหน่ง</label>
          <input list="positionOptions" name="position"
            class="w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400"
            placeholder="เลือกหรือพิมพ์ตำแหน่งเอง เช่น อาจารย์ประจำภาควิชา" required>

          <datalist id="positionOptions">
            <option value="เจ้าหน้าที่">
            <option value="อาจารย์">
            <option value="นักศึกษา">
            <option value="บุคลากร">
            <option value="พนักงานมหาวิทยาลัย">
            <option value="อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ">
            <option value="ผู้ช่วยศาสตราจารย์">
            <option value="รองศาสตราจารย์">
          </datalist>
        </div>
      </div>

      <!-- Faculty + Department -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block font-semibold text-gray-700 mb-2">คณะ</label>
          <select id="faculty_id" name="faculty_id"
            class="w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" required>
            <?php foreach ($faculties as $faculty): ?>
            <option value="<?= h($faculty['faculty_id']) ?>"
              <?= ((string)$faculty['faculty_id'] === (string)$defaultFacultyId) ? 'selected' : '' ?>>
              <?= h($faculty['faculty_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-2">ภาควิชา</label>
          <select id="department_id" name="department_id"
            class="w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400" required>
            <?php foreach ($departments as $department): ?>
            <option value="<?= h($department['department_id']) ?>"
              data-faculty-id="<?= h($department['faculty_id']) ?>">
              <?= h(trim($department['department_name'])) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <!-- Permissions -->
      <div>
        <label class="block font-semibold text-gray-700 mb-2">สิทธิ์ในการเข้าถึง</label>
        <div class="flex space-x-6 flex-wrap gap-y-3">
          <label class="flex items-center space-x-2">
            <input type="checkbox" name="permissions[]" value="1"
              class="w-5 h-5 text-teal-600 border-2 border-teal-500 rounded focus:ring-teal-400">
            <span>แก้ไขได้</span>
          </label>

          <label class="flex items-center space-x-2">
            <input type="checkbox" name="permissions[]" value="2"
              class="w-5 h-5 text-teal-600 border-2 border-teal-500 rounded focus:ring-teal-400">
            <span>ดูได้</span>
          </label>

          <label class="flex items-center space-x-2">
            <input type="checkbox" name="permissions[]" value="3"
              class="w-5 h-5 text-teal-600 border-2 border-teal-500 rounded focus:ring-teal-400">
            <span>กำหนดสิทธิ์ได้</span>
          </label>
        </div>
      </div>
      <!-- Status -->
      <div>
        <label class="block font-semibold text-gray-700 mb-2">สถานะการใช้งาน</label>
        <div class="flex items-center space-x-6">
          <label class="flex items-center space-x-2">
            <input type="radio" name="is_active" value="1" class="text-teal-500 focus:ring-teal-400" checked>
            <span>เปิดการใช้งาน</span>
          </label>
          <label class="flex items-center space-x-2">
            <input type="radio" name="is_active" value="0" class="text-teal-500 focus:ring-teal-400">
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
  const facultySelect = document.getElementById('faculty_id');
  const departmentSelect = document.getElementById('department_id');
  const departmentOptions = Array.from(departmentSelect.options);

  function filterDepartmentsByFaculty() {
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

    const currentOption = departmentSelect.options[departmentSelect.selectedIndex];
    if (!currentOption || currentOption.disabled) {
      departmentSelect.value = firstVisibleValue;
    }
  }

  facultySelect.addEventListener('change', filterDepartmentsByFaculty);
  filterDepartmentsByFaculty();
  </script>
</body>

</html>