  <!-- Header -->
  <?php $current = basename($_SERVER['PHP_SELF']); ?>
  <header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md"
      style="font-family: Arial, Helvetica, sans-serif;">
      <div class="flex items-center space-x-3">
          <div class="w-[56px] h-[56px] flex items-center justify-center relative overflow-visible">
              <svg xmlns="http://www.w3.org/2000/svg" class="absolute scale-[1.4] text-white"
                  style="width: 60px; height: 60px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
      <div class="flex items-center space-x-4">
          <a href="user/home.php">
              <div class="px-4 py-2 rounded-[11px] font-bold transition 
            <?= $current === 'home.php' 
                    ? 'bg-white text-teal-500 shadow' 
                : 'text-white hover:bg-white hover:text-teal-500' ?>">
                  หน้าหลัก
              </div>
          </a>
          <?php 
                if (isset($_SESSION['permissions']) && in_array(3, $_SESSION['permissions'])) {
                    renderAdminExtraMenus(); 
                }
            ?>
          <a href="user/form_Memo.php">
              <div class="px-4 py-2 rounded-[11px] font-bold transition text-white">แบบฟอร์มบันทึกข้อความ</div>
          </a>
          <div class="relative">
              <!-- ปุ่ม Profile -->
              <button id="profileBtn"
                  class="bg-white text-teal-500 px-4 py-2 rounded-[11px] shadow flex items-center space-x-2 hover:bg-gray-100">
                  <div class="text-right leading-tight">
                      <div class="font-bold text-[14px]">
                          <?= htmlspecialchars($_SESSION['fullname'] ?? 'Guest') ?>
                      </div>
                      <div class="text-[12px]">
                          <?= htmlspecialchars($_SESSION['role_name'] ?? '') ?>
                      </div>

                  </div>
                  <div
                      class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                          stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round"
                              d="M5.121 17.804A13.937 13.937 0 0112 15c2.33 0 4.487.577 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                  </div>
              </button>

              <!-- เมนู Dropdown -->
              <div id="profileMenu" class="hidden absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-50">
                  <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
                  <button onclick="closeMenu()"
                      class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
                      อยู่ต่อ
                  </button>
              </div>

          </div>
  </header>