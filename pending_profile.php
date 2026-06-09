<?php
// Pro_letter/pending_profile.php

session_start();
require_once __DIR__ . '/functions.php';

// ถ้ายังไม่ได้ login ให้กลับไปหน้า login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $pdo = getPDO();

    // ดึงข้อมูลผู้ใช้ล่าสุดจากฐานข้อมูล
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.fullname,
            u.email,
            u.position,
            u.department_id,
            u.role_id,
            u.is_active,
            u.profile_completed,
            d.department_name,
            r.role_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: login.html?error=user');
        exit;
    }

    // ถ้าบัญชีถูกปิด
    if ((int)$user['is_active'] !== 1) {
        session_destroy();
        header('Location: login.html?error=inactive');
        exit;
    }

    // ถ้า Admin เพิ่มข้อมูลครบแล้ว ให้พาไปหน้าหลักตาม role ทันที
    if ((int)$user['profile_completed'] === 1) {
        $_SESSION['profile_completed'] = 1;
        $_SESSION['role_id'] = (int)$user['role_id'];
        $_SESSION['role_name'] = $user['role_name'] ?? '';
        $_SESSION['position'] = $user['position'] ?? '';

        switch ((int)$user['role_id']) {
            case 1:
                header('Location: admin/home.php');
                exit;

            case 2:
                header('Location: officer/home.php');
                exit;

            case 3:
                header('Location: user/home.php');
                exit;

            default:
                header('Location: login.html?error=role');
                exit;
        }
    }

} catch (PDOException $e) {
    header('Location: login.html?error=db');
    exit;
}

$fullname = htmlspecialchars($user['fullname'] ?? '-', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user['email'] ?? '-', ENT_QUOTES, 'UTF-8');
$roleName = htmlspecialchars($user['role_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$position = htmlspecialchars($user['position'] ?? '-', ENT_QUOTES, 'UTF-8');
$departmentName = htmlspecialchars($user['department_name'] ?? '-', ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>รอผู้ดูแลระบบ | Smart Government</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-white font-sans min-h-screen">

  <!-- Header -->
  <header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md">
    <!-- Logo -->
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

    <a href="logout.php" class="inline-block">
      <button class="bg-white text-teal-500 px-4 py-2 rounded-[11px] shadow hover:bg-gray-100 font-bold">
        ออกจากระบบ
      </button>
    </a>
  </header>

  <!-- Breadcrumb -->
  <div class="p-6">
    <nav class="text-sm text-gray-700 space-x-1">
      <span class="font-bold text-gray-900">หน้าแรก</span>
      <span>›</span>
      <span class="text-gray-500">เข้าสู่ระบบ</span>
      <span>›</span>
      <span class="text-teal-600 font-semibold">รอผู้ดูแลระบบ</span>
    </nav>
  </div>

  <!-- Main -->
  <main class="flex justify-center px-4 mt-4 pb-12">
    <div class="w-full max-w-4xl">

      <!-- Status Card -->
      <section class="bg-white border border-gray-200 shadow-xl rounded-2xl overflow-hidden">
        <div class="bg-teal-500 text-white text-center py-5 px-6">
          <div class="text-[20px] font-bold">บัญชีอยู่ระหว่างรอผู้ดูแลระบบ</div>
          <div class="text-sm text-white/90 mt-1">
            Pending Admin Profile Completion
          </div>
        </div>

        <div class="p-8">
          <!-- Icon -->
          <div class="flex justify-center">
            <div class="w-24 h-24 rounded-full bg-teal-50 border-4 border-teal-100 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-teal-500" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>

          <div class="text-center mt-6">
            <h1 class="text-2xl font-bold text-gray-800">
              เข้าสู่ระบบสำเร็จแล้ว
            </h1>

            <p class="text-gray-500 mt-3 leading-relaxed">
              ระบบได้รับข้อมูลชื่อและอีเมลจากบัญชี Google ของคุณแล้ว<br>
              แต่ยังไม่สามารถสร้างเอกสารได้จนกว่าผู้ดูแลระบบจะเพิ่มข้อมูลผู้ใช้ให้ครบถ้วน
            </p>
          </div>

          <!-- Note -->
          <div class="mt-7 bg-teal-50 border border-teal-100 rounded-xl p-5">
            <div class="flex items-start gap-3">
              <div
                class="w-9 h-9 rounded-full bg-teal-500 text-white flex items-center justify-center font-bold shrink-0">
                !
              </div>
              <div>
                <div class="font-bold text-teal-700">
                  หมายเหตุ
                </div>
                <p class="text-gray-600 text-sm leading-relaxed mt-1">
                  กรุณารอ Admin เพิ่มข้อมูล เช่น หน่วยงาน ตำแหน่ง และสิทธิ์การใช้งาน
                  หลังจากเพิ่มข้อมูลเรียบร้อยแล้ว คุณจะสามารถเข้าสู่ระบบและสร้างเอกสารได้ตามสิทธิ์ที่ได้รับ
                </p>
              </div>
            </div>
          </div>

          <!-- User Info -->
          <div class="mt-7">
            <div class="text-gray-800 font-bold mb-3">
              ข้อมูลที่ระบบได้รับจาก Google
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="border border-gray-200 rounded-xl p-4">
                <div class="text-xs text-gray-400 mb-1">ชื่อผู้ใช้</div>
                <div class="text-gray-800 font-semibold"><?= $fullname ?></div>
              </div>

              <div class="border border-gray-200 rounded-xl p-4">
                <div class="text-xs text-gray-400 mb-1">อีเมล</div>
                <div class="text-gray-800 font-semibold break-all"><?= $email ?></div>
              </div>

              <div class="border border-gray-200 rounded-xl p-4">
                <div class="text-xs text-gray-400 mb-1">สิทธิ์ปัจจุบัน</div>
                <div class="text-gray-800 font-semibold"><?= $roleName ?></div>
              </div>

              <div class="border border-gray-200 rounded-xl p-4">
                <div class="text-xs text-gray-400 mb-1">สถานะบัญชี</div>
                <div class="inline-flex items-center gap-2 text-amber-600 font-bold">
                  <span class="w-2.5 h-2.5 bg-amber-400 rounded-full"></span>
                  รอผู้ดูแลระบบเพิ่มข้อมูล
                </div>
              </div>

              <div class="border border-gray-200 rounded-xl p-4">
                <div class="text-xs text-gray-400 mb-1">ตำแหน่ง</div>
                <div class="text-gray-800 font-semibold"><?= $position ?></div>
              </div>

              <div class="border border-gray-200 rounded-xl p-4">
                <div class="text-xs text-gray-400 mb-1">หน่วยงาน / ภาควิชา</div>
                <div class="text-gray-800 font-semibold"><?= $departmentName ?></div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="pending_profile.php"
              class="inline-flex items-center justify-center px-6 py-3 rounded-full bg-teal-500 text-white font-bold shadow hover:bg-teal-600 transition">
              ตรวจสอบสถานะอีกครั้ง
            </a>

            <a href="logout.php"
              class="inline-flex items-center justify-center px-6 py-3 rounded-full bg-gray-100 text-gray-700 font-bold border border-gray-200 hover:bg-gray-200 transition">
              ออกจากระบบ
            </a>
          </div>
        </div>
      </section>

    </div>
  </main>

</body>

</html>