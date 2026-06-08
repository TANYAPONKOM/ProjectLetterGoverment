<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

// ดึงฟิลด์ทั้งหมดจาก template_fields
$template_id = 1; // สามารถเปลี่ยนเป็น dynamic ได้
$stmt = $pdo->prepare("SELECT * FROM template_fields WHERE template_id=? ORDER BY sort_order ASC");
$stmt->execute([$template_id]);
$fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap');

    html,

    :root {
        --base-fs: 16px;
    }

    body,
    label,
    input,
    textarea,
    select,
    option,
    button,
    span,
    div {
        font-size: var(--base-fs);
    }

    select,
    input,
    textarea {
        line-height: 1.4;
    }

    select option {
        font-size: var(--base-fs);
    }

    #requestListContainer {
        flex: 1;
        overflow-y: auto;
    }

    .custom-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background: white;
        border: 2px solid #11C2B9;
        border-radius: 1rem;
        padding: 0.5rem 2.5rem 0.5rem 0.75rem;
        background-image: url('data:image/svg+xml;utf8,<svg fill="%23000000" height="16" viewBox="0 0 20 20" width="16" xmlns="http://www.w3.org/2000/svg"><path d="M5.516 7.548l4.486 4.448 4.486-4.448L15.56 9l-5.558 5.5L4.444 9z"/></svg>');
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
    }

    .custom-select:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(17, 194, 185, .35);
    }

    /* error styles */
    .error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 2px rgba(239, 68, 68, .15);
    }

    .lbl.asterisk::after {
        content: " *";
        color: #ef4444;
        font-weight: 700;
        margin-left: 4px;
    }

    /* floating hint bubble */
    .hint {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fee2e2;
        border: 1px solid #ef4444;
        color: #991b1b;
        padding: 4px 8px;
        border-radius: 8px;
        margin-top: 6px;
        box-shadow: 0 1px 0 rgba(0, 0, 0, .03);
    }

    .hint svg {
        min-width: 16px;
        min-height: 16px;
    }

    .hint:before {
        content: "";
        position: absolute;
        top: -6px;
        left: 16px;
        border-width: 6px;
        border-style: solid;
        border-color: transparent transparent #ef4444 transparent;
    }

    .hint:after {
        content: "";
        position: absolute;
        top: -5px;
        left: 16px;
        border-width: 5px;
        border-style: solid;
        border-color: transparent transparent #fee2e2 transparent;
    }

    .shake {
        animation: shake .2s linear 0s 2;
    }

    @keyframes shake {

        0%,
        100% {
            transform: translateX(0)
        }

        25% {
            transform: translateX(-3px)
        }

        75% {
            transform: translateX(3px)
        }
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
                <div class="px-4 py-2 rounded-[11px] font-bold transition bg-white text-teal-500 shadow">
                    หน้าหลัก
                </div>
            </a>
            <a href="user_Managerment.php" id="tab-users">
                <div
                    class="px-4 py-2 rounded-[11px] font-bold transition text-white hover:bg-white hover:text-teal-500">
                    จัดการผู้ใช้
                </div>
            </a>
            <a href="form_Templates.php" id="tab-templates">
                <div
                    class="px-4 py-2 rounded-[11px] font-bold transition text-white hover:bg-white hover:text-teal-500">
                    จัดการเทมเพลต
                </div>
            </a>
            <a href="#" id="tab-departments">
                <div
                    class="px-4 py-2 rounded-[11px] font-bold transition text-white hover:bg-white hover:text-teal-500">
                    จัดการภาควิชา
                </div>
            </a>
            <a href="#" id="tab-reports">
                <div
                    class="px-4 py-2 rounded-[11px] font-bold transition text-white hover:bg-white hover:text-teal-500">
                    รายงาน
                </div>
            </a>
            <div class="relative">
                <!-- ปุ่มโปรไฟล์ -->
                <button id="profileBtn"
                    class="bg-white text-teal-500 px-4 py-2 rounded-[11px] shadow flex items-center space-x-2 hover:bg-gray-100">
                    <div class="text-right leading-tight">
                        <div class="font-bold text-[14px]"><?= htmlspecialchars($_SESSION['username']) ?></div>
                        <div class="text-[12px]">Templates</div>
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
                <div id="profileMenu"
                    class="hidden absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-50">
                    <a href="../logout.php"
                        class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
                    <button onclick="closeMenu()"
                        class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
                        อยู่ต่อ
                    </button>
                </div>
            </div>
        </div>
    </header>
</body>

</html>