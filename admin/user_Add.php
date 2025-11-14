<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}
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
                <div class="text-[13px] mt-[0px]">Letter Management System</div>
            </div>
        </div>
        <!-- <a href="user_Managerment.php" class="bg-white text-teal-500 px-3 py-1 rounded">กลับ</a> -->
    </header>

    <body class="bg-gray-100">
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
                <div class="grid grid-cols-2 gap-4">
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
                                class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400"
                                placeholder="Username" required>
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
                                class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400"
                                placeholder="Password" required>
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
                            placeholder="Full name" required>
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
                            class="w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400"
                            placeholder="Email" required>
                    </div>
                </div>

                <!-- Role + Position -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-gray-700 mb-2">สิทธิ์การเข้าถึง</label>
                        <select name="role_id"
                            class="w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400">
                            <option value="1">Admin</option>
                            <option value="2">Officer</option>
                            <option value="3">User</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-700 mb-2">ตำแหน่ง</label>
                        <select name="position"
                            class="w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400">
                            <option value="เจ้าหน้าที่">เจ้าหน้าที่</option>
                            <option value="อาจารย์">อาจารย์</option>
                            <option value="นักศึกษา">นักศึกษา</option>
                            <option value="บุคลากร">บุคลากร</option>
                        </select>
                    </div>
                </div>

                <!-- Department -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-2">ภาควิชา</label>
                    <select name="department_id"
                        class="w-full pl-3 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-teal-400">
                        <option value="1">เทคโนโลยีสารสนเทศ</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block font-semibold text-gray-700 mb-2">สถานะการใช้งาน</label>
                    <div class="flex items-center space-x-6">
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="is_active" value="1" class="text-teal-500 focus:ring-teal-400"
                                checked>
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
                    <a href="../user_Managerment.php"
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
    </body>

</html>