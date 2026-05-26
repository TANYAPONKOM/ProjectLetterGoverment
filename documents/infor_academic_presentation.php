<?php
// pro_letter/documents/infor_academic_presentation.php
$CURRENT_MAIN = trim($_GET['main'] ?? 'external');
$CURRENT_SUB  = trim($_GET['sub']  ?? 'ขออนุมัติตัวบุคคลไปนำเสนอผลงานวิจัย');

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'external';
}

session_start();
require_once __DIR__ . '/../functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$roleIdForHome = (int)($_SESSION['role_id'] ?? 0);
if ($roleIdForHome === 1) {
    $homePath = '/Pro_letter/admin/home.php';
} elseif ($roleIdForHome === 2) {
    $homePath = '/Pro_letter/officer/home.php';
} else {
    $homePath = '/Pro_letter/user/home.php';
}



/* ===== โหลดคณะ/ภาควิชา =====
   - ค่าเริ่มต้น = คณะ/ภาควิชาของ user ที่ล็อกอิน
   - แต่ dropdown ยังเปลี่ยนไปเลือกคณะ/ภาควิชาอื่นได้
   - department_id hidden จะเปลี่ยนตามภาควิชาที่เลือก
*/
$currentUserFacultyId = 0;
$currentUserDepartmentId = 0;
$currentUserFacultyName = 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
$currentUserDepartmentName = 'เทคโนโลยีสารสนเทศ';

$facultyOptions = [];
$departmentOptions = [];

try {
    $userOrgPdo = db();

    // 1) โหลดคณะ/ภาควิชาของ user ที่ล็อกอิน เพื่อใช้เป็นค่า default
    $userOrgStmt = $userOrgPdo->prepare("
        SELECT
            u.department_id,
            d.department_name,
            d.faculty_id,
            f.faculty_name
        FROM users u
        LEFT JOIN departments d ON d.department_id = u.department_id
        LEFT JOIN faculties f ON f.faculty_id = d.faculty_id
        WHERE u.user_id = :user_id
        LIMIT 1
    ");
    $userOrgStmt->execute([':user_id' => (int)$_SESSION['user_id']]);
    $userOrg = $userOrgStmt->fetch(PDO::FETCH_ASSOC);

    if ($userOrg) {
        $currentUserDepartmentId = (int)($userOrg['department_id'] ?? 0);
        $currentUserFacultyId = (int)($userOrg['faculty_id'] ?? 0);
        $currentUserFacultyName = trim((string)($userOrg['faculty_name'] ?? '')) ?: $currentUserFacultyName;
        $currentUserDepartmentName = trim((string)($userOrg['department_name'] ?? '')) ?: $currentUserDepartmentName;
    }

    // 2) โหลดคณะทั้งหมด เพื่อให้ dropdown เลือกเปลี่ยนได้
    $facultyStmt = $userOrgPdo->query("
        SELECT faculty_id, faculty_name
        FROM faculties
        ORDER BY faculty_name ASC
    ");
    $facultyOptions = $facultyStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3) โหลดภาควิชาทั้งหมด พร้อม faculty_id เพื่อใช้ filter ตามคณะที่เลือก
    $departmentStmt = $userOrgPdo->query("
        SELECT department_id, department_name, faculty_id
        FROM departments
        ORDER BY faculty_id ASC, department_name ASC
    ");
    $departmentOptions = $departmentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    // ถ้าดึงข้อมูลไม่ได้ ให้ใช้ค่า default เพื่อไม่ให้หน้าแบบฟอร์มพัง
    $facultyOptions = [];
    $departmentOptions = [];
}

// fallback กัน dropdown ว่าง ถ้าฐานข้อมูลยังไม่มีข้อมูลคณะ/ภาควิชา
if (empty($facultyOptions)) {
    $facultyOptions[] = [
        'faculty_id' => $currentUserFacultyId ?: 1,
        'faculty_name' => $currentUserFacultyName
    ];
}

if (empty($departmentOptions)) {
    $departmentOptions[] = [
        'department_id' => $currentUserDepartmentId ?: 1,
        'department_name' => $currentUserDepartmentName,
        'faculty_id' => $currentUserFacultyId ?: 1
    ];
}

$docId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $docId > 0;
$formData = [];
$budgetItems = [];
$expenseJsonRaw = "";
$expenseDataForEdit = [];

if ($isEdit) {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT document_id, owner_id, status
        FROM documents
        WHERE document_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) exit("ไม่พบเอกสาร");
   $roleId = (int)($_SESSION['role_id'] ?? 0);
    $isAdmin   = ($roleId === 1);
    $isOfficer = ($roleId === 2);
    if (!$isAdmin && !$isOfficer) {
        if ($doc['owner_id'] != $_SESSION['user_id']) {
            header("Location: view_memo.php?id={$docId}&err=no_permission");
            exit;

        }
        if (!in_array($doc['status'], ['draft','rejected'])) {
           header("Location: view_memo.php?id={$docId}&err=no_permission");
          exit;

        }
    }
    $q = $pdo->prepare("
        SELECT field_id, value_text
        FROM document_values
        WHERE document_id = :id
    ");
    $q->execute([':id' => $docId]);

    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $formData[(int)$row['field_id']] = $row['value_text'];
    }

    // โหลดรายละเอียดประมาณการค่าใช้จ่ายแบบ JSON เพื่อใช้เติมค่ากลับในหน้าแก้ไข
    $expenseJsonRaw = trim((string)($formData[20] ?? ''));
    if ($expenseJsonRaw !== '') {
        $decodedExpenseData = json_decode($expenseJsonRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedExpenseData)) {
            $expenseDataForEdit = $decodedExpenseData;
        }
    }

    // โหลดรายการค่าใช้จ่ายเดิมกลับมาใช้ตอนแก้ไข
    try {
        $bq = $pdo->prepare("
            SELECT item_type, description, amount
            FROM budget_items
            WHERE document_id = :id
            ORDER BY item_id ASC
        ");
        $bq->execute([':id' => $docId]);
        $budgetItems = $bq->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $budgetItems = [];
    }
}


/* ===== โหมดแก้ไขเอกสาร: ใช้คณะ/ภาควิชาจากเอกสารเดิมก่อนข้อมูล user =====
   ใช้เฉพาะตอนเปิดฟอร์มแก้ไข เพื่อให้ dropdown แสดงค่าที่เคยบันทึกไว้กับเอกสารนั้น
*/
$__editOrgDocId = 0;
foreach (['docId', 'editDocId', 'documentId'] as $__editOrgIdVar) {
    if (isset($$__editOrgIdVar) && (int)$$__editOrgIdVar > 0) {
        $__editOrgDocId = (int)$$__editOrgIdVar;
        break;
    }
}

if ($__editOrgDocId > 0) {
    try {
        $__editOrgPdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : db();

        // 1) ให้ความสำคัญกับ documents.department_id ก่อน เพราะเป็นค่าภาควิชาของเอกสารจริง
        $__editOrgStmt = $__editOrgPdo->prepare("
            SELECT
                doc.department_id,
                dep.department_name,
                dep.faculty_id,
                fac.faculty_name
            FROM documents doc
            LEFT JOIN departments dep ON dep.department_id = doc.department_id
            LEFT JOIN faculties fac ON fac.faculty_id = dep.faculty_id
            WHERE doc.document_id = :id
            LIMIT 1
        ");
        $__editOrgStmt->execute([':id' => $__editOrgDocId]);
        $__editOrg = $__editOrgStmt->fetch(PDO::FETCH_ASSOC);

        if ($__editOrg && (int)($__editOrg['department_id'] ?? 0) > 0) {
            $currentUserDepartmentId = (int)($__editOrg['department_id'] ?? 0);
            $currentUserFacultyId = (int)($__editOrg['faculty_id'] ?? 0);
            $currentUserDepartmentName = trim((string)($__editOrg['department_name'] ?? '')) ?: $currentUserDepartmentName;
            $currentUserFacultyName = trim((string)($__editOrg['faculty_name'] ?? '')) ?: $currentUserFacultyName;
        } else {
            // 2) fallback: ถ้าเอกสารเก่าไม่มี documents.department_id ให้ลองเทียบจาก document_values field_id 10/11
            $__savedFacultyName = '';
            $__savedDepartmentName = '';

            if (isset($formData) && is_array($formData)) {
                $__savedFacultyName = trim((string)($formData[10] ?? ''));
                $__savedDepartmentName = trim((string)($formData[11] ?? ''));
            }
            if (isset($formDataById) && is_array($formDataById)) {
                $__savedFacultyName = $__savedFacultyName ?: trim((string)($formDataById[10] ?? ''));
                $__savedDepartmentName = $__savedDepartmentName ?: trim((string)($formDataById[11] ?? ''));
            }
            if (isset($valueMap) && is_array($valueMap)) {
                $__savedFacultyName = $__savedFacultyName ?: trim((string)($valueMap[10] ?? ''));
                $__savedDepartmentName = $__savedDepartmentName ?: trim((string)($valueMap[11] ?? ''));
            }
            if (isset($editValuesByFieldId) && is_array($editValuesByFieldId)) {
                $__savedFacultyName = $__savedFacultyName ?: trim((string)($editValuesByFieldId[10] ?? ''));
                $__savedDepartmentName = $__savedDepartmentName ?: trim((string)($editValuesByFieldId[11] ?? ''));
            }
            if (isset($editValuesByKey) && is_array($editValuesByKey)) {
                $__savedFacultyName = $__savedFacultyName ?: trim((string)($editValuesByKey['faculty'] ?? ''));
                $__savedDepartmentName = $__savedDepartmentName ?: trim((string)($editValuesByKey['department'] ?? ''));
            }

            if ($__savedDepartmentName !== '') {
                $__fallbackSql = "
                    SELECT
                        dep.department_id,
                        dep.department_name,
                        dep.faculty_id,
                        fac.faculty_name
                    FROM departments dep
                    LEFT JOIN faculties fac ON fac.faculty_id = dep.faculty_id
                    WHERE dep.department_name = :department_name
                ";
                $__fallbackParams = [':department_name' => $__savedDepartmentName];

                if ($__savedFacultyName !== '') {
                    $__fallbackSql .= " AND fac.faculty_name = :faculty_name";
                    $__fallbackParams[':faculty_name'] = $__savedFacultyName;
                }

                $__fallbackSql .= " LIMIT 1";
                $__fallbackStmt = $__editOrgPdo->prepare($__fallbackSql);
                $__fallbackStmt->execute($__fallbackParams);
                $__fallbackOrg = $__fallbackStmt->fetch(PDO::FETCH_ASSOC);

                if ($__fallbackOrg && (int)($__fallbackOrg['department_id'] ?? 0) > 0) {
                    $currentUserDepartmentId = (int)($__fallbackOrg['department_id'] ?? 0);
                    $currentUserFacultyId = (int)($__fallbackOrg['faculty_id'] ?? 0);
                    $currentUserDepartmentName = trim((string)($__fallbackOrg['department_name'] ?? '')) ?: $currentUserDepartmentName;
                    $currentUserFacultyName = trim((string)($__fallbackOrg['faculty_name'] ?? '')) ?: $currentUserFacultyName;
                }
            }
        }
    } catch (Throwable $e) {
        // ถ้าโหลดค่าจากเอกสารเดิมไม่ได้ ให้ใช้ค่า default จาก user ตามเดิม
    }
}
unset($__editOrgDocId, $__editOrgIdVar, $__editOrg, $__editOrgStmt, $__editOrgPdo, $__savedFacultyName, $__savedDepartmentName, $__fallbackStmt, $__fallbackOrg, $__fallbackSql, $__fallbackParams);

$docDate     = $formData[1]  ?? '';
$ownerName   = $formData[2]  ?? '';
$position    = $formData[3]  ?? '';
$joinType    = $formData[4]  ?? '';   // purpose (ข้อความไทย)
$courseName  = $formData[5]  ?? '';
$joinDates   = $formData[6]  ?? '';
$location    = $formData[7]  ?? '';
$amountStr   = $formData[8]  ?? '';
$vehicle     = $formData[9]  ?? '';
$faculty     = $formData[10] ?? '';
$department  = $formData[11] ?? '';
$memoSubject   = $formData[14] ?? '';
$academicTopic = $formData[13] ?? '';
$academicLevel = $formData[15] ?? '';
$eventDate     = $formData[16] ?? '';

$isRangeDate = preg_match('/\d+\s*-\s*\d+/', $joinDates);
$isEventRangeDate = preg_match('/\d+\s*-\s*\d+/', $eventDate);

$isOnline = ($location === 'เข้าร่วมรูปแบบออนไลน์');

$purpose = 'other';
if ($joinType === 'นำเสนอผลงานวิจัย') {
    $purpose = 'academic';
} elseif ($joinType === 'เข้าร่วมประชุมวิชาการในงาน') {
    $purpose = 'meeting';
} elseif ($joinType === 'เข้ารับการฝึกอบรมหลักสูตร') {
    $purpose = 'training';
}

// ถ้าเป็นเอกสารใหม่และผู้ใช้เลือกจาก dropdown หมวดย่อย ให้ติ๊กข้อ 3 ให้ตรงอัตโนมัติ
if (!$isEdit) {
    if ($CURRENT_SUB === 'ขออนุมัติตัวบุคคลไปนำเสนอผลงานวิจัย') {
        $purpose = 'academic';
        $joinType = 'นำเสนอผลงานวิจัย';
    } elseif ($CURRENT_SUB === 'ฝึกอบรม') {
        $purpose = 'training';
        $joinType = 'เข้ารับการฝึกอบรมหลักสูตร';
    }
}

$purposeOther = ($purpose === 'other') ? $joinType : '';


?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>แบบฟอร์มนำเสนอผลงานวิจัย</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css" />
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
  @import url("https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap");

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
    border: 2px solid #11c2b9;
    border-radius: 1rem;
    padding: 0.5rem 2.5rem 0.5rem 0.75rem;
    background-image: url('data:image/svg+xml;utf8,<svg fill="%23000000" height="16" viewBox="0 0 20 20" width="16" xmlns="http://www.w3.org/2000/svg"><path d="M5.516 7.548l4.486 4.448 4.486-4.448L15.56 9l-5.558 5.5L4.444 9z"/></svg>');
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
  }

  .custom-select:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(17, 194, 185, 0.35);
  }

  .error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15);
  }

  .lbl.asterisk::after {
    content: " *";
    color: #ef4444;
    font-weight: 700;
    margin-left: 4px;
  }

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
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.03);
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
    animation: shake 0.2s linear 0s 2;
  }

  /* input error */
  .spell-error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
    background-color: #fffafa;
  }

  /* กล่องผลลัพธ์ */
  .spell-box {
    margin-top: 8px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #fff7ed;
    border: 1px solid #fdba74;
    color: #9a3412;
    font-size: 14px;
    line-height: 1.6;
  }

  /* ซ่อน */
  .spell-box.hidden {
    display: none !important;
  }

  /* กล่องผลลัพธ์ภายใน */
  .spell-result-box {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  /* ข้อความแจ้งเตือน */
  .spell-warning {
    font-weight: 600;
    color: #991b1b;
  }

  /* ข้อความช่วย */
  .spell-help-text {
    font-size: 13px;
    color: #9a3412;
    font-weight: 500;
  }

  /* container ของคำแนะนำ */
  .spell-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
  }

  /* ปุ่มคำแนะนำ */
  .spell-suggestion-btn {
    border: 1px solid #fdba74;
    background: #ffffff;
    color: #9a3412;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
  }

  /* hover */
  .spell-suggestion-btn:hover {
    background: #ffedd5;
    border-color: #fb923c;
  }

  /* กด */
  .spell-suggestion-btn:active {
    transform: scale(0.96);
  }

  /* focus */
  .spell-suggestion-btn:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.2);
  }

  .spell-ok {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    background-color: #f0fdf4;
  }

  .spell-loading {
    margin-top: 8px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #eff6ff;
    border: 1px solid #93c5fd;
    color: #1d4ed8;
    font-size: 14px;
    line-height: 1.6;
  }

  .spell-loading.hidden {
    display: none !important;
  }

  .spell-loading-row {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .spell-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #bfdbfe;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
  }

  .spell-ignore-btn {
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
  }

  .spell-ignore-btn:hover {
    background: #e2e8f0;
  }

  ;


  .expense-subrow {
    display: grid;
    grid-template-columns: minmax(240px, 1fr) 145px auto;
    gap: 6px;
    align-items: end;
    padding: 6px;
    margin-top: 6px;
    border: 0.6px solid #111;
    background: #fff;
  }

  .expense-subrow label {
    display: block;
    color: #374151;
    font-size: 13pt;
    margin-bottom: 2px;
  }

  .expense-subrow input {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    padding: 4px 6px;
    font-size: 14pt;
  }

  .expense-subrow .js-del {
    border: 1px solid #f87171;
    color: #dc2626;
    background: #fff;
    border-radius: 4px;
    padding: 4px 8px;
    font-weight: bold;
  }

  .expense-subrow .js-del:hover {
    background: #fef2f2;
  }

  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }

  @keyframes shake {

    0%,
    100% {
      transform: translateX(0);
    }

    25% {
      transform: translateX(-3px);
    }

    75% {
      transform: translateX(3px);
    }
  }
  </style>
</head>

<body class="bg-gray-100">
  <header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md"
    style="font-family: Arial, Helvetica, sans-serif">
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
      <a href="<?= h($homePath) ?>">
        <div class="px-4 py-2 rounded-[11px] font-bold transition text-white">
          หน้าหลัก
        </div>
      </a>
      <?php if (isset($_SESSION['permissions']) && in_array(3, $_SESSION['permissions'])) {
                    renderAdminExtraMenus(); }?>
      <a href="form_Memo.php">
        <div class="px-4 py-2 rounded-[11px] font-bold transition bg-white text-teal-500 shadow">
          แบบฟอร์มบันทึกข้อความ
        </div>
      </a>
      <div class="relative">
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
          <button onclick="closeMenu()"
            class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">อยู่ต่อ</button>
        </div>
      </div>
    </div>
  </header>
  <form method="post" action="<?= $isEdit ? '/Pro_letter/documents/update_memo.php' : 'save_memo.php' ?>" id="memoForm">
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="department_id" id="selectedDepartmentId" value="<?= (int)$currentUserDepartmentId ?>">
    <?php if ($isEdit): ?>
    <input type="hidden" name="document_id" value="<?= (int)$docId ?>">
    <input type="hidden" name="mode" value="update">
    <input type="hidden" name="redirect_back" value="1">
    <input type="hidden" name="target_form" value="form_memo_academic_1.php">
    <input type="hidden" name="form_type" value="academic">
    <input type="hidden" name="document_type" value="infor_academic_presentation">
    <input type="hidden" name="redirect_to" value="form_memo_academic_1.php">
    <?php else: ?>
    <input type="hidden" name="mode" value="create">
    <?php endif; ?>
    <div id="step1">
      <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
        <h1 class="text-center font-bold mb-6 text-black">
          แบบฟอร์มขออนุมัติตัวบุคคลไปนำเสนอผลงานวิจัย
        </h1>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-6 rounded-[25px] border-2" style="
            background-color: #e3f9f8;
            border-color: #11c2b9;
            min-height: 170px;
          ">
          <div class="flex items-center gap-3">
            <label class="lbl text-gray-800 w-28 text-right">หมวดหลัก:</label>
            <div class="relative w-full">
              <select name="main_category" class="custom-select w-full" id="mainCategory">
                <option value="">-- เลือกหมวดหลัก --</option>
                <option value="external" <?= ($CURRENT_MAIN=="external"?"selected":"") ?>>ภายนอก</option>
                <option value="internal" <?= ($CURRENT_MAIN=="internal"?"selected":"") ?>>ภายใน</option>
              </select>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <label class="lbl text-gray-800 w-28 text-right">หมวดย่อย:</label>
            <div class="relative w-full">
              <select name="sub_category" class="custom-select w-full" id="subCategory"
                data-current="<?= h($CURRENT_SUB ?? '') ?>" disabled>
                <option value="">-- เลือกหมวดย่อย --</option>
              </select>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <label class="lbl text-gray-800 w-28 text-right">คณะ:</label>
            <div class="relative w-full">
              <select name="faculty" class="custom-select w-full" id="faculty"
                data-current-faculty-id="<?= (int)$currentUserFacultyId ?>">
                <?php foreach ($facultyOptions as $fac): ?>
                <?php
                    $facId = (int)($fac['faculty_id'] ?? 0);
                    $facName = trim((string)($fac['faculty_name'] ?? ''));
                  ?>
                <option value="<?= h($facName) ?>" data-faculty-id="<?= $facId ?>"
                  <?= ($facId === (int)$currentUserFacultyId || $facName === $currentUserFacultyName) ? 'selected' : '' ?>>
                  <?= h($facName) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <label class="lbl text-gray-800 w-28 text-right">ภาควิชา:</label>
            <div class="relative w-full">
              <select name="department" class="custom-select w-full" id="dept"
                data-current-department-id="<?= (int)$currentUserDepartmentId ?>">
                <?php foreach ($departmentOptions as $deptRow): ?>
                <?php
                    $deptId = (int)($deptRow['department_id'] ?? 0);
                    $deptFacultyId = (int)($deptRow['faculty_id'] ?? 0);
                    $deptName = trim((string)($deptRow['department_name'] ?? ''));
                    $isCurrentFacultyDept = ($currentUserFacultyId <= 0 || $deptFacultyId === (int)$currentUserFacultyId);
                  ?>
                <?php if ($isCurrentFacultyDept): ?>
                <option value="<?= h($deptName) ?>" data-department-id="<?= $deptId ?>"
                  data-faculty-id="<?= $deptFacultyId ?>"
                  <?= ($deptId === (int)$currentUserDepartmentId || $deptName === $currentUserDepartmentName) ? 'selected' : '' ?>>
                  <?= h($deptName) ?>
                </option>
                <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 items-end">
          <div class="flex items-center gap-3">
            <label class="lbl text-gray-800 whitespace-nowrap" for="docDateDisplay">1.วัน เดือน ปี :</label>
            <div class="relative">
              <input type="text" id="docDateDisplay" value="<?= h($formData[1] ?? '') ?>"
                class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly />
              <input type="hidden" name="doc_date" id="docDate" value="<?= h($formData[1] ?? '') ?>" />
              <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>
            <label class="lbl text-gray-800 whitespace-nowrap">ที่ต้องการให้ปรากฎบนบันทึกข้อความ</label>
          </div>
        </div>
        <div class="space-y-4 mb-6">
          <div class="flex items-center gap-3">
            <label class="lbl text-gray-800 whitespace-nowrap" for="fullname">2.ชื่อ - นามสกุล :</label>
            <input type="text" name="fullname" class="flex-1 border rounded-md p-2" id="fullname"
              value="<?= h($ownerName ?: ($_SESSION['fullname'] ?? '')) ?>" />
          </div>
          <div class="flex items-center gap-3">
            <label class="lbl text-gray-800 whitespace-nowrap" for="position">ตำแหน่ง :</label>
            <input type="text" name="position" class="flex-1 border rounded-md p-2" id="position"
              value="<?= h($position ?: ($_SESSION['position'] ?? 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ')) ?>">
          </div>
          <div class="mb-4">
            <div class="flex items-start gap-2">
              <label class="lbl text-gray-800 whitespace-nowrap mt-1" id="purposeLabel">
                3.ขออนุมัติไปเข้าร่วม
              </label>
              <div class="space-y-1 text-gray-800" id="purposeGroup" role="radiogroup" aria-labelledby="purposeLabel">
                <label class="flex items-center gap-2">
                  <input type="radio" name="purpose" value="academic" class="accent-black" checked />
                  นำเสนอผลงานวิจัย
                </label>
              </div>
            </div>
          </div>
          <div class="<?= ($purpose === 'academic') ? '' : 'hidden' ?>" id="academicExtraWrap">

            <div class="mb-4 flex items-start gap-4">
              <label class="lbl text-gray-800 whitespace-nowrap pt-2 ml-3" for="memoSubject">
                เรื่อง :
              </label>
              <div class="w-full">
                <textarea name="memo_subject" id="memoSubject" data-spell-field="memo_subject" rows="2"
                  class="w-full border rounded-md p-2 shadow-sm"
                  placeholder="ขออนุมัติตัวบุคคลเพื่อไปนำเสนอผลงานวิจัยในงานประชุมวิชาการระดับนานาชาติ ACIE 2025"><?= h($memoSubject) ?></textarea>

                <div id="memoSubjectSpellBox" class="spell-box hidden"></div>
                <div id="memoSubjectSpellLoading" class="spell-loading hidden">
                  <div class="spell-loading-row">
                    <div class="spell-spinner"></div>
                    <span>กำลังตรวจคำผิด...</span>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <div class="mb-4 flex items-start gap-4">
            <label class="lbl text-gray-800 whitespace-nowrap pt-2" for="eventTitle">
              4.ชื่อของงานประชุมวิชาการ /<br />ชื่อหลักสูตรอบรม :
            </label>
            <div class="w-full">
              <textarea name="event_title" data-spell-field="event_title" rows="2"
                class="w-full border rounded-md p-2 shadow-sm" id="eventTitle"
                placeholder="ในงานประชุมวิชาการระดับนานาชาติ The 5th Asia Conference on Information Engineering (ACIE 2025)"><?= h($formData[5] ?? '') ?></textarea>
              <div id="eventTitleSpellBox" class="spell-box hidden"></div>
              <div id="eventTitleSpellLoading" class="spell-loading hidden">
                <div class="spell-loading-row">
                  <div class="spell-spinner"></div>
                  <span>กำลังตรวจคำผิด...</span>
                </div>
              </div>

            </div>
          </div>
          <div class="<?= ($purpose === 'academic') ? '' : 'hidden' ?>" id="academicDetailWrap">

            <div class="mb-4 flex items-start gap-4">
              <label class="lbl text-gray-800 whitespace-nowrap pt-2 ml-3" for="academicTopic">
                หัวข้อ :
              </label>
              <div class="w-full">
                <textarea name="academic_topic" id="academicTopic" data-spell-field="academic_topic" rows="2"
                  class="w-full border rounded-md p-2 shadow-sm"
                  placeholder="API-Based Personal Healthcare Application: Securing Data and Ensuring Patient Privacy"><?= h($academicTopic) ?></textarea>

                <div id="academicTopicSpellBox" class="spell-box hidden"></div>
                <div id="academicTopicSpellLoading" class="spell-loading hidden">
                  <div class="spell-loading-row">
                    <div class="spell-spinner"></div>
                    <span>กำลังตรวจคำผิด...</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-4 flex items-start gap-4">
              <label class="lbl text-gray-800 whitespace-nowrap pt-2 ml-3" for="academicLevel">
                ระดับวิชาการ :
              </label>
              <div class="w-full">
                <input type="text" name="academic_level" id="academicLevel" data-spell-field="academic_level"
                  class="w-full border rounded-md p-2 shadow-sm" placeholder="วิชาการระดับนานาชาติ ACIE 2025"
                  value="<?= h($academicLevel) ?>">

                <div id="academicLevelSpellBox" class="spell-box hidden"></div>
                <div id="academicLevelSpellLoading" class="spell-loading hidden">
                  <div class="spell-loading-row">
                    <div class="spell-spinner"></div>
                    <span>กำลังตรวจคำผิด...</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="mb-6">
              <label class="lbl text-gray-800 block mb-2 ml-3">วันที่จัด</label>
              <div class="space-y-4 ml-6 text-gray-800">
                <div class="flex items-center gap-2">
                  <input type="radio" name="event_date_option" value="single" id="eventOptSingle"
                    class="accent-[#11C2B9]" <?= !$isEventRangeDate ? 'checked' : '' ?> />
                  <span>วันเดียว :</span>

                  <div class="relative">
                    <input type="text" name="event_single_date" id="eventSingleDate"
                      class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่"
                      readonly />
                    <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                      fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
                    </svg>
                  </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <input type="radio" name="event_date_option" value="range" id="eventOptRange" class="accent-[#11C2B9]"
                    <?= $isEventRangeDate ? 'checked' : '' ?> />
                  <span>หลายวัน :</span>

                  <div class="relative">
                    <input type="text" id="eventStartDate"
                      class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer" placeholder="เริ่มต้น"
                      readonly />
                    <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                      fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
                    </svg>
                  </div>

                  <span>ถึง</span>

                  <div class="relative">
                    <input type="text" id="eventEndDate"
                      class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer" placeholder="สิ้นสุด"
                      readonly />
                    <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                      fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 002 2v11a2 2 0 002 2z" />
                    </svg>
                  </div>

                  <input type="hidden" name="event_date" id="eventDate" value="<?= h($eventDate) ?>">
                </div>
              </div>
            </div>


          </div>
          <div class="mb-6">
            <label class="lbl text-gray-800 block mb-2" id="dateLabel">5. วันที่เข้าร่วม</label>
            <div class="space-y-4 ml-6 text-gray-800">
              <div class="flex items-center gap-2">
                <input type="radio" name="date_option" value="single" id="optSingle" class="accent-[#11C2B9]"
                  <?= !$isRangeDate ? 'checked' : '' ?> />
                <span>วันเดียว :</span>
                <div class="relative">
                  <input type="text" name="single_date" id="singleDate"
                    class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่"
                    readonly />
                  <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
                  </svg>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <input type="radio" name="date_option" value="range" id="optRange" class="accent-[#11C2B9]"
                  <?= $isRangeDate ? 'checked' : '' ?> />
                <span>หลายวัน :</span>
                <div class="relative">
                  <input type="text" id="startDate" class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer"
                    placeholder="เริ่มต้น" readonly />
                  <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
                  </svg>
                </div>
                <span>ถึง</span>
                <div class="relative">
                  <input type="text" id="endDate" class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer"
                    placeholder="สิ้นสุด" readonly />
                  <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
                  </svg>
                </div>
                <input type="hidden" id="rangeDisplay" name="range_date" value="<?= h($formData[6] ?? '') ?>">
                <input type="hidden" name="join_date" id="joinDate" value="<?= h($formData[6] ?? '') ?>">
              </div>
            </div>
          </div>
          <div class="mb-6">
            <label class="lbl text-gray-800 block mb-2">
              6. ชื่อสถานที่จัดประชุมวิชาการ / สถานที่จัดอบรม / เข้าร่วมรูปแบบออนไลน์
            </label>
            <div class="flex items-center ml-6 gap-2 mb-3">
              <input type="radio" name="is_online" value="1" id="onlineCheckbox" class="accent-black"
                <?= $isOnline ? 'checked' : '' ?>>
              <label for="onlineCheckbox">เข้าร่วมในรูปแบบออนไลน์</label>
            </div>
            <div class="flex items-start ml-6 gap-2">
              <input type="radio" name="is_online" value="0" id="onsiteCheckbox" class="accent-black mt-3"
                <?= !$isOnline ? 'checked' : '' ?>>

              <label for="onsiteCheckbox" class="mt-2">เข้าร่วมในรูปแบบออนไซต์</label>
              <label class="lbl text-gray-800 ml-4 mr-2 mt-2" for="placeOnsite">ระบุสถานที่ไป :</label>

              <div class="flex flex-col">
                <input type="text" name="place" id="placeOnsite" data-spell-field="place" class="border rounded-md p-2 w-[400px]
<?= !$isOnline ? '' : 'bg-gray-100 text-gray-400' ?>" value="<?= !$isOnline ? h($location) : '' ?>"
                  <?= !$isOnline ? '' : 'disabled' ?>>

                <div id="placeOnsiteSpellBox" class="spell-box hidden"></div>

                <div id="placeOnsiteSpellLoading" class="spell-loading hidden">
                  <div class="spell-loading-row">
                    <div class="spell-spinner"></div>
                    <span>กำลังตรวจคำผิด...</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mb-6">
            <?php
            $noCostValue = $formData[12] ?? '';
            if ($noCostValue !== '') {
                $noCostChecked = ((string)$noCostValue === '1') ? 'checked' : '';
            } else {
                $amountForCheck = str_replace(',', '', $formData[8] ?? '');
                $noCostChecked = ($amountForCheck !== '' && (float)$amountForCheck == 0.0) ? 'checked' : '';
            }

            $amountDisplay = $formData[8] ?? '0.00';
            if ($amountDisplay === '') {
                $amountDisplay = '0.00';
            }
            ?>

            <div class="flex items-center gap-3 mb-2">
              <label class="lbl text-gray-800 whitespace-nowrap">
                7.รวมยอดประมาณการค่าใช้จ่าย :
              </label>

              <input type="hidden" name="amount" id="amountInput" value="<?= h($amountDisplay) ?>">
            </div>

            <label class="flex items-center gap-3 ml-6 mt-2">
              <input type="hidden" name="no_cost" value="0">
              <input type="checkbox" name="no_cost" value="1" class="accent-black" id="noCostCheckbox"
                <?= $noCostChecked ?> />
              โดยไม่เบิกค่าใช้จ่ายใดๆทั้งสิ้น
            </label>
          </div>
          <div class="mb-6">
            <label class="lbl block text-gray-800 mb-2" id="carLabel">
              8. กรณีไปรถยนต์ส่วนบุคคล
            </label>

            <div class="flex items-center gap-3 ml-6">

              <input type="checkbox" id="carCheckbox" name="car_used" class="accent-black"
                <?= !empty($formData[9]) ? 'checked' : '' ?> />

              <label for="carCheckbox" class="lbl whitespace-nowrap">
                ใช้รถยนต์ส่วนบุคคล
              </label>

              <input type="text" name="car_plate" id="carPlateInput"
                class="border rounded-md p-2 w-[260px] shadow-sm <?= !empty($formData[9]) ? '' : 'bg-gray-100 text-gray-400' ?>"
                placeholder="เช่น กธ 1234 กรุงเทพมหานคร" value="<?= h($formData[9] ?? '') ?>"
                <?= !empty($formData[9]) ? '' : 'disabled' ?>>

            </div>
          </div>
          <div class="mt-24 flex justify-end gap-3">
            <button type="button" id="nextBtn"
              class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md transition">
              ถัดไป
            </button>

            <button type="submit" id="submitBtn"
              class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md transition">
              ดำเนินการ
            </button>
          </div>
        </div>
      </div>
    </div>

    <div id="step2" class="hidden">
      <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
        <h1 class="text-center font-bold mb-6 text-black">แบบฟอร์มประมาณการค่าใช้จ่าย</h1>
        <div class="mb-8 p-6 rounded-[25px] border-2" style="background-color:#e3f9f8;border-color:#11c2b9;">
          <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="text-gray-800 font-bold">สรุปยอดรวมรายจ่าย</div>
            <div class="flex items-center gap-2">
              <input id="totalAmount" type="text" class="border rounded-md p-2 w-40 bg-gray-50 text-gray-700"
                value="0.00" readonly>
              <span class="text-gray-800 font-bold">บาท</span>
            </div>
          </div>
          <div class="text-gray-600 text-sm mt-2">* ระบบคำนวณให้อัตโนมัติ (บันทึกยอดรวมลงเอกสารให้)</div>
        </div>
        <div class="mb-8">
          <div class="flex items-center justify-between">
            <div class="font-bold text-gray-800">1. ค่าตอบแทน</div>
            <button type="button" id="addCompBtn"
              class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold px-4 py-2 rounded-md transition">
              + เพิ่มรายการ
            </button>
          </div>
          <div id="compList" class="mt-3 space-y-3"></div>
          <div id="compEmpty" class="mt-3 text-gray-500">
            1.1 <span class="italic">ไม่มีรายการ</span> — 0.00 บาท
          </div>
        </div>
        <div class="mb-8">
          <div class="font-bold text-gray-800 mb-3">2. ค่าใช้สอย</div>
          <div class="p-4 rounded-[20px] border-2 mb-4" style="border-color:#11c2b9;background:#f7fffe;">
            <div class="flex items-center justify-between">
              <label class="font-bold text-gray-800 flex items-center gap-2">
                <input type="checkbox" id="regEnabled" class="accent-black">
                2.1 ค่าลงทะเบียน
              </label>
              <div class="text-gray-800 font-bold">
                <span id="regTotal">0.00</span> บาท
              </div>
            </div>
            <div id="regForm" class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label class="text-gray-700">ราคา (บาท)</label>
                <input type="number" id="regPrice" class="w-full border rounded-md p-2" min="0" step="0.01" value="0">
              </div>
              <div>
                <label class="text-gray-700">จำนวนคน</label>
                <input type="number" id="regPeople" class="w-full border rounded-md p-2" min="1" step="1" value="1">
              </div>
              <div class="text-gray-600 flex items-end">
                <span>(ราคา × คน)</span>
              </div>
            </div>
          </div>
          <div class="p-4 rounded-[20px] border-2 mb-4" style="border-color:#11c2b9;background:#f7fffe;">
            <div class="flex items-center justify-between">
              <label class="font-bold text-gray-800 flex items-center gap-2">
                <input type="checkbox" id="lodEnabled" class="accent-black">
                2.2 ค่าที่พักค้างคืน
              </label>
              <div class="text-gray-800 font-bold">
                <span id="lodTotal">0.00</span> บาท
              </div>
            </div>
            <div id="lodForm" class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-3">
              <div class="md:col-span-4">
                <label class="text-gray-700 block mb-2">ช่วงวันที่เข้าพัก</label>

                <div class="space-y-3 text-gray-800">
                  <div class="flex items-center gap-2">
                    <input type="radio" name="lod_date_option" value="single" id="lodOptSingle" class="accent-[#11C2B9]"
                      checked>
                    <span>วันเดียว :</span>
                    <input type="text" id="lodSingleDate" class="border rounded-md p-2 shadow-sm w-48 cursor-pointer"
                      placeholder="เลือกวันที่" readonly>
                  </div>

                  <div class="flex flex-wrap items-center gap-2">
                    <input type="radio" name="lod_date_option" value="range" id="lodOptRange" class="accent-[#11C2B9]">
                    <span>หลายวัน :</span>

                    <input type="text" id="lodStartDate" class="border rounded-md p-2 shadow-sm w-48 cursor-pointer"
                      placeholder="เริ่มต้น" readonly>

                    <span>ถึง</span>

                    <input type="text" id="lodEndDate" class="border rounded-md p-2 shadow-sm w-48 cursor-pointer"
                      placeholder="สิ้นสุด" readonly>
                  </div>

                  <input type="hidden" id="lodDateText">
                </div>
              </div>
              <div class="md:col-span-2">
                <label class="text-gray-700">ราคา/คืน</label>
                <input type="number" id="lodUnit" class="w-full border rounded-md p-2" min="0" step="0.01" value="1500">
                <div class="text-xs text-gray-500 mt-1">ค่าเริ่มต้นราชการ: 1 คน = 1,500/คืน, มากกว่า 1 คน = 1,000/คืน/คน
                </div>
              </div>
              <div>
                <label class="text-gray-700">จำนวนคืน</label>
                <input type="number" id="lodNights"
                  class="w-full border rounded-md p-2 bg-gray-100 text-gray-600 cursor-not-allowed" min="1" step="1"
                  value="1" readonly>
                <div class="text-xs text-gray-500 mt-1">คำนวณจากวันที่เข้าพัก แก้ไขเองไม่ได้</div>
              </div>
              <div>
                <label class="text-gray-700">จำนวนคน</label>
                <input type="number" id="lodPeople" class="w-full border rounded-md p-2" min="1" step="1" value="1">
              </div>
              <div class="text-gray-600 md:col-span-4">
                <span>(ราคา/คืน × คืน × คน)</span>
              </div>
            </div>
          </div>
          <div class="p-4 rounded-[20px] border-2 mb-4" style="border-color:#11c2b9;background:#f7fffe;">
            <div class="flex items-center justify-between">
              <label class="font-bold text-gray-800 flex items-center gap-2">
                <input type="checkbox" id="perEnabled" class="accent-black">
                2.3 ค่าอาหาร
              </label>
              <div class="text-gray-800 font-bold">
                <span id="perTotal">0.00</span> บาท
              </div>
            </div>
            <div id="perForm" class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label class="text-gray-700">ราคา/มื้อ</label>
                <input type="number" id="perUnit" class="w-full border rounded-md p-2" min="0" step="0.01" value="120">
                <div class="text-xs text-gray-500 mt-1">ค่าเริ่มต้นราชการ: มื้อละ 120 บาท</div>
              </div>
              <div>
                <label class="text-gray-700">จำนวนมื้อ</label>
                <input type="number" id="perMeals" class="w-full border rounded-md p-2" min="1" step="1" value="1">
              </div>
              <div>
                <label class="text-gray-700">จำนวนคน</label>
                <input type="number" id="perPeople" class="w-full border rounded-md p-2" min="1" step="1" value="1">
              </div>
              <div class="text-gray-600 md:col-span-3">
                <span>(ราคา/มื้อ × มื้อ × คน)</span>
              </div>
            </div>
          </div>
          <div class="p-4 rounded-[20px] border-2 mb-4" style="border-color:#11c2b9;background:#f7fffe;">
            <div class="flex items-center justify-between">
              <label class="font-bold text-gray-800 flex items-center gap-2">
                <input type="checkbox" id="trEnabled" class="accent-black">
                2.4 ค่าพาหนะ
              </label>
              <div class="text-gray-800 font-bold">
                <span id="trTotal">0.00</span> บาท
              </div>
            </div>
            <div class="mt-3">
              <button type="button" id="addTrItemBtn"
                class="bg-white border-2 border-[#11C2B9] text-[#0f766e] font-bold px-4 py-2 rounded-md hover:bg-gray-50 transition">
                + เพิ่มรายการย่อยพาหนะ
              </button>
            </div>
            <div id="trList" class="mt-3 space-y-3"></div>
            <div id="trEmpty" class="mt-3 text-gray-500">
              - ไม่มีรายการพาหนะ — 0.00 บาท
            </div>
          </div>
        </div>
        <div class="mb-8">
          <div class="flex items-center justify-between">
            <div class="font-bold text-gray-800">3. ค่าวัสดุ</div>
            <button type="button" id="addMatBtn"
              class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold px-4 py-2 rounded-md transition">
              + เพิ่มรายการ
            </button>
          </div>
          <div id="matList" class="mt-3 space-y-3"></div>
          <div id="matEmpty" class="mt-3 text-gray-500">
            3.1 <span class="italic">ไม่มีรายการ</span> — 0.00 บาท
          </div>
        </div>
        <div class="mt-6 text-gray-800">
          <span class="font-bold">หมายเหตุ</span> ขอถัวจ่ายทุกรายการ
        </div>
        <div class="flex justify-between items-end mt-20">
          <input type="hidden" name="total_amount" id="totalAmountHidden" value="0.00">
          <button type="button" id="backBtn"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md transition">
            ย้อนกลับ
          </button>
          <button type="button" id="finalSubmitBtn"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md transition">
            ดำเนินการ
          </button>
        </div>
      </div>
    </div>
  </form>
  <script>
  const byId = (id) => document.getElementById(id);
  const initialBudgetItems = <?= json_encode($budgetItems ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const initialExpenseData =
    <?= json_encode($expenseDataForEdit ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const spellState = {
    purpose_other_detail: {
      checked: false,
      hasError: false,
      ignored: false,
      suggestions: [],
      errors: []
    },
    event_title: {
      checked: false,
      hasError: false,
      ignored: false,
      suggestions: [],
      errors: []
    },
    memo_subject: {
      checked: false,
      hasError: false,
      ignored: false,
      suggestions: [],
      errors: []
    },
    academic_topic: {
      checked: false,
      hasError: false,
      ignored: false,
      suggestions: [],
      errors: []
    },
    academic_level: {
      checked: false,
      hasError: false,
      ignored: false,
      suggestions: [],
      errors: []
    },
    place: {
      checked: false,
      hasError: false,
      ignored: false,
      suggestions: [],
      errors: []
    }
  };

  document.addEventListener("DOMContentLoaded", () => {
    if (typeof flatpickr === "undefined") {
      console.error("flatpickr not loaded");
      return;
    }
    flatpickr.localize(flatpickr.l10ns.th);
    const monthsTH = [
      "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
      "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    ];
    const memoForm = document.getElementById("memoForm");
    const IS_EDIT_MODE = <?= $isEdit ? 'true' : 'false' ?>;

    const mainCategory = document.getElementById("mainCategory");
    const subCategory = document.getElementById("subCategory");

    const purposeOtherInput = document.getElementById("purposeOtherInput");
    const academicExtraWrap = document.getElementById("academicExtraWrap");
    const academicDetailWrap = document.getElementById("academicDetailWrap");

    const memoSubject = document.getElementById("memoSubject");
    const academicTopic = document.getElementById("academicTopic");
    const academicLevel = document.getElementById("academicLevel");

    const eventTitle = document.getElementById("eventTitle");
    const placeOnsite = document.getElementById("placeOnsite");

    const eventOptSingle = document.getElementById("eventOptSingle");
    const eventOptRange = document.getElementById("eventOptRange");
    const eventSingleDate = document.getElementById("eventSingleDate");
    const eventStartDate = document.getElementById("eventStartDate");
    const eventEndDate = document.getElementById("eventEndDate");
    const eventRangeDisplay = null;
    const eventDate = document.getElementById("eventDate");

    const docDateDisplay = document.getElementById("docDateDisplay"); // ไทย พ.ศ.
    const docDateHidden = document.getElementById("docDate"); // YYYY-MM-DD (ส่ง DB)

    const fullname = document.getElementById("fullname");
    const position = document.getElementById("position");

    const purposeOtherSpellBox = document.getElementById("purposeOtherSpellBox");
    const eventTitleSpellBox = document.getElementById("eventTitleSpellBox");
    const memoSubjectSpellBox = document.getElementById("memoSubjectSpellBox");
    const academicTopicSpellBox = document.getElementById("academicTopicSpellBox");
    const academicLevelSpellBox = document.getElementById("academicLevelSpellBox");
    const placeOnsiteSpellBox = document.getElementById("placeOnsiteSpellBox");



    const purposeOtherRadio = document.getElementById("purposeOtherRadio");
    const purposeRadios = document.querySelectorAll('input[name="purpose"]');

    const optSingle = document.getElementById("optSingle");
    const optRange = document.getElementById("optRange");
    const singleDate = document.getElementById("singleDate");
    const startDate = document.getElementById("startDate");
    const endDate = document.getElementById("endDate");
    const rangeDisplay = document.getElementById("rangeDisplay");
    const joinDate = document.getElementById("joinDate"); // hidden (ส่งเข้า PHP)

    const onlineCheckbox = document.getElementById("onlineCheckbox");
    const onsiteCheckbox = document.getElementById("onsiteCheckbox");

    const amountInput = document.getElementById("amountInput");
    const noCostCheckbox = document.getElementById("noCostCheckbox");

    const carCheckbox = document.getElementById("carCheckbox");
    const carPlateInput = document.getElementById("carPlateInput");


    function getSpellLoadingByField(el) {
      if (!el) return null;
      if (el.id === "purposeOtherInput") return document.getElementById("purposeOtherSpellLoading");
      if (el.id === "eventTitle") return document.getElementById("eventTitleSpellLoading");
      if (el.id === "memoSubject") return document.getElementById("memoSubjectSpellLoading");
      if (el.id === "academicTopic") return document.getElementById("academicTopicSpellLoading");
      if (el.id === "academicLevel") return document.getElementById("academicLevelSpellLoading");
      if (el.id === "placeOnsite") return document.getElementById("placeOnsiteSpellLoading");
      return null;
    }

    function showSpellLoading(el) {
      const box = getSpellLoadingByField(el);
      if (box) box.classList.remove("hidden");
    }

    function hideSpellLoading(el) {
      const box = getSpellLoadingByField(el);
      if (box) box.classList.add("hidden");
    }

    function clearError(el) {
      if (!el) return;
      el.classList.remove("error", "shake");
      const old = el.parentElement?.querySelector(".hint");
      if (old) old.remove();
    }

    function setError(el, msg) {
      if (!el) return;
      clearError(el);
      el.classList.add("error", "shake");

      const hint = document.createElement("div");
      hint.className = "hint";
      hint.innerHTML = `
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24">
        <path d="M12 9v4m0 4h.01M10.29 3.86l-7.5 13A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.71-3.14l-7.5-13a2 2 0 0 0-3.42 0Z"
          stroke="#991b1b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>${msg}</span>
    `;
      (el.parentElement || el).appendChild(hint);
    }

    function scrollToFirstError(firstEl) {
      if (!firstEl) return;
      firstEl.scrollIntoView({
        behavior: "smooth",
        block: "center"
      });
      setTimeout(() => firstEl.focus?.(), 150);
    }

    function toThaiDisplay(dateObj) {
      const d = dateObj.getDate();
      const m = monthsTH[dateObj.getMonth()];
      const y = dateObj.getFullYear() + 543;
      return `${d} ${m} ${y}`;
    }

    function parseYMD(ymd) {

      const m = (ymd || "").match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (!m) return null;
      const y = parseInt(m[1], 10);
      const mo = parseInt(m[2], 10) - 1;
      const d = parseInt(m[3], 10);
      return new Date(y, mo, d);
    }

    function parseThaiSingle(raw) {
      raw = (raw || "").trim();
      if (!raw) return null;

      const ymdDate = parseYMD(raw);
      if (ymdDate) return ymdDate;

      const match = raw.match(/(\d{1,2})\s+([^\s]+)\s+(\d{4})/);
      if (!match) return null;

      const d = parseInt(match[1], 10);
      const monthName = match[2].trim();
      let year = parseInt(match[3], 10);
      if (year > 2400) year -= 543;

      const monthIndex = monthsTH.indexOf(monthName);
      if (monthIndex === -1) return null;

      return new Date(year, monthIndex, d);
    }

    function syncPurposeUI() {
      const chosenPurpose = document.querySelector('input[name="purpose"]:checked');
      const isAcademic = chosenPurpose?.value === "academic";

      if (academicExtraWrap) {
        academicExtraWrap.classList.toggle("hidden", !isAcademic);
      }

      if (academicDetailWrap) {
        academicDetailWrap.classList.toggle("hidden", !isAcademic);
      }

      if (!isAcademic) {
        [memoSubject, academicTopic, academicLevel].forEach(el => {
          if (!el) return;
          el.value = "";
          clearError(el);
          clearSpellResult(el);
        });

        if (eventDate) eventDate.value = "";
        if (eventSingleDate) eventSingleDate.value = "";
        if (eventStartDate) eventStartDate.value = "";
        if (eventEndDate) eventEndDate.value = "";

        ["memo_subject", "academic_topic", "academic_level"].forEach(key => {
          spellState[key] = {
            checked: false,
            hasError: false,
            ignored: false,
            suggestions: [],
            errors: []
          };
        });
      }

      if (purposeOtherRadio && purposeOtherRadio.checked) {
        purposeOtherInput.disabled = false;
        purposeOtherInput.classList.remove("bg-gray-100", "text-gray-400");
      } else if (purposeOtherInput) {
        purposeOtherInput.value = "";
        purposeOtherInput.disabled = true;
        purposeOtherInput.classList.add("bg-gray-100", "text-gray-400");
        clearError(purposeOtherInput);
        clearSpellResult(purposeOtherInput);
        spellState.purpose_other_detail = {
          checked: false,
          hasError: false,
          ignored: false,
          suggestions: [],
          errors: []
        };
      }
    }
    purposeRadios.forEach(r => r.addEventListener("change", syncPurposeUI));

    function syncPlaceUI(fromUserAction = false) {
      if (!placeOnsite) return;

      if (onlineCheckbox?.checked) {
        // ถ้าผู้ใช้กดเลือกออนไลน์เอง ค่อยเขียนทับค่า
        // ป้องกันตอนเปิดหน้าแก้ไขแล้วสถานที่เดิมโดนเปลี่ยนเป็นออนไลน์เอง
        if (fromUserAction || !placeOnsite.value.trim()) {
          placeOnsite.value = "เข้าร่วมรูปแบบออนไลน์";
        }

        placeOnsite.readOnly = true;
        placeOnsite.disabled = false;
        placeOnsite.classList.add("bg-gray-100", "text-gray-400");
        return;
      }

      if (onsiteCheckbox?.checked) {
        placeOnsite.readOnly = false;
        placeOnsite.disabled = false;
        placeOnsite.classList.remove("bg-gray-100", "text-gray-400");

        // ล้างคำว่าออนไลน์เฉพาะตอนผู้ใช้กดเลือกออนไซต์เอง
        if (fromUserAction && placeOnsite.value === "เข้าร่วมรูปแบบออนไลน์") {
          placeOnsite.value = "";
        }
      }
    }
    onlineCheckbox?.addEventListener("change", () => syncPlaceUI(true));
    onsiteCheckbox?.addEventListener("change", () => syncPlaceUI(true));


    function preparePlaceForSubmit(showAlert = false) {
      if (!placeOnsite || !onlineCheckbox || !onsiteCheckbox) return true;

      if (onlineCheckbox.checked) {
        placeOnsite.disabled = false;
        placeOnsite.readOnly = true;
        placeOnsite.value = "เข้าร่วมรูปแบบออนไลน์";
        return true;
      }

      if (onsiteCheckbox.checked) {
        placeOnsite.disabled = false;
        placeOnsite.readOnly = false;
        placeOnsite.classList.remove("bg-gray-100", "text-gray-400");

        if (placeOnsite.value.trim() === "เข้าร่วมรูปแบบออนไลน์") {
          placeOnsite.value = "";
        }

        if (!placeOnsite.value.trim()) {
          if (typeof showStep1 === "function") showStep1();
          setError(placeOnsite, "กรุณาระบุสถานที่ไป (ออนไซต์)");
          if (showAlert) alert("กรุณาระบุสถานที่ไป (ออนไซต์)");
          setTimeout(() => {
            placeOnsite.scrollIntoView({
              behavior: "smooth",
              block: "center"
            });
            placeOnsite.focus();
          }, 150);
          return false;
        }

        return true;
      }

      if (showAlert) alert("กรุณาเลือก ออนไลน์ หรือ ออนไซต์");
      return false;
    }

    function syncCostUI() {
      if (!amountInput) return;

      if (noCostCheckbox?.checked) {
        amountInput.value = "0.00";
        amountInput.readOnly = true;
        amountInput.disabled = false;
        amountInput.classList.add("bg-gray-100", "text-gray-400");
      } else {
        amountInput.readOnly = false;
        amountInput.disabled = false;
        amountInput.classList.remove("bg-gray-100", "text-gray-400");
      }
    }
    noCostCheckbox?.addEventListener("change", syncCostUI);

    function syncCarUI() {
      if (carCheckbox?.checked) {
        carPlateInput.disabled = false;
        carPlateInput.classList.remove("bg-gray-100", "text-gray-400");
      } else {
        carPlateInput.value = "";
        carPlateInput.disabled = true;
        carPlateInput.classList.add("bg-gray-100", "text-gray-400");
        clearError(carPlateInput);
      }
    }
    carCheckbox?.addEventListener("change", syncCarUI);
    const docPicker = flatpickr(docDateDisplay, {
      disableMobile: true,
      allowInput: false,
      clickOpens: true,
      dateFormat: "Y-m-d", // internal (เราเอาไปใส่ hidden)
      onReady: (selectedDates, dateStr, inst) => {
        const rawDocDate = (docDateHidden?.value || docDateDisplay?.value || "").trim();
        const d = parseYMD(rawDocDate) || parseThaiSingle(rawDocDate);
        if (d) {
          inst.setDate(d, false);
          docDateDisplay.value = toThaiDisplay(d);
          docDateHidden.value = inst.formatDate(d, "Y-m-d");
        }
      },
      onChange: (selectedDates, dateStr, inst) => {
        const d = selectedDates[0];
        if (!d) return;
        docDateDisplay.value = toThaiDisplay(d); // ไทย พ.ศ.
        docDateHidden.value = inst.formatDate(d, "Y-m-d"); // ✅ ส่ง DB
        clearError(docDateDisplay);
      }
    });
    docDateDisplay?.addEventListener("click", () => docPicker.open());

    const singlePicker = flatpickr("#singleDate", {
      disableMobile: true,
      allowInput: false,
      clickOpens: true,
      onChange: ([d], _, inst) => {
        if (d) {
          inst.input.value = toThaiDisplay(d);
          joinDate.value = toThaiDisplay(d);
        }
      }
    });

    const startPicker = flatpickr("#startDate", {
      disableMobile: true,
      allowInput: false,
      clickOpens: true,
      onChange: updateRangeDisplay
    });
    const endPicker = flatpickr("#endDate", {
      disableMobile: true,
      allowInput: false,
      clickOpens: true,
      onChange: updateRangeDisplay
    });

    function updateRangeDisplay() {
      if (!startPicker.selectedDates[0] || !endPicker.selectedDates[0]) return;

      const d1 = startPicker.selectedDates[0];
      const d2 = endPicker.selectedDates[0];

      const y1 = d1.getFullYear() + 543;
      const y2 = d2.getFullYear() + 543;
      const m1 = monthsTH[d1.getMonth()];
      const m2 = monthsTH[d2.getMonth()];

      let text = "";
      if (m1 === m2 && y1 === y2) text = `${d1.getDate()} - ${d2.getDate()} ${m1} ${y1}`;
      else text = `${d1.getDate()} ${m1} ${y1} - ${d2.getDate()} ${m2} ${y2}`;

      rangeDisplay.value = text;
      joinDate.value = text;
    }
    const eventSinglePicker = flatpickr("#eventSingleDate", {
      disableMobile: true,
      allowInput: false,
      clickOpens: true,
      onChange: ([d], _, inst) => {
        if (d) {
          inst.input.value = toThaiDisplay(d);
          eventDate.value = toThaiDisplay(d);
        }
      }
    });

    const eventStartPicker = flatpickr("#eventStartDate", {
      disableMobile: true,
      allowInput: false,
      clickOpens: true,
      onChange: updateEventRangeDisplay
    });

    const eventEndPicker = flatpickr("#eventEndDate", {
      disableMobile: true,
      allowInput: false,
      clickOpens: true,
      onChange: updateEventRangeDisplay
    });

    function updateEventRangeDisplay() {
      if (!eventStartPicker.selectedDates[0] || !eventEndPicker.selectedDates[0]) return;

      const d1 = eventStartPicker.selectedDates[0];
      const d2 = eventEndPicker.selectedDates[0];

      const y1 = d1.getFullYear() + 543;
      const y2 = d2.getFullYear() + 543;
      const m1 = monthsTH[d1.getMonth()];
      const m2 = monthsTH[d2.getMonth()];

      let text = "";
      if (m1 === m2 && y1 === y2) {
        text = `${d1.getDate()} - ${d2.getDate()} ${m1} ${y1}`;
      } else {
        text = `${d1.getDate()} ${m1} ${y1} - ${d2.getDate()} ${m2} ${y2}`;
      }


      eventDate.value = text;
    }

    function toggleEventDatePickers() {
      const single = eventOptSingle?.checked;

      if (eventSingleDate) eventSingleDate.disabled = !single;
      if (eventStartDate) eventStartDate.disabled = single;
      if (eventEndDate) eventEndDate.disabled = single;


      clearError(eventSingleDate);
      clearError(eventStartDate);
      clearError(eventEndDate);
    }

    eventOptSingle?.addEventListener("change", toggleEventDatePickers);
    eventOptRange?.addEventListener("change", toggleEventDatePickers);

    if (eventDate && eventDate.value.trim()) {
      const raw = eventDate.value.trim();

      if (raw.includes("-") || raw.includes("ถึง")) {
        eventOptRange.checked = true;
        toggleEventDatePickers();

        const dates = parseThaiRange(raw);
        if (dates) {
          eventStartPicker.setDate(dates[0], false);
          eventEndPicker.setDate(dates[1], false);
          eventStartDate.value = toThaiDisplay(dates[0]);
          eventEndDate.value = toThaiDisplay(dates[1]);
          eventDate.value = raw;

        }
      } else {
        eventOptSingle.checked = true;
        toggleEventDatePickers();

        const d = parseThaiSingle(raw);
        if (d) {
          eventSinglePicker.setDate(d, false);
          eventSingleDate.value = raw;
        }
      }
    }

    toggleEventDatePickers();

    function toggleDatePickers() {
      const single = optSingle.checked;
      singleDate.disabled = !single;
      startDate.disabled = single;
      endDate.disabled = single;
      rangeDisplay.disabled = single;

      clearError(singleDate);
      clearError(rangeDisplay);
      clearError(startDate);
      clearError(endDate);
    }
    optSingle?.addEventListener("change", toggleDatePickers);
    optRange?.addEventListener("change",
      toggleDatePickers);

    if (joinDate && joinDate.value.trim()) {
      const raw = joinDate.value.trim();
      if (raw.includes("-") || raw.includes("ถึง")) {
        optRange.checked = true;
        toggleDatePickers();
        const dates = parseThaiRange(raw);
        if (dates) {
          startPicker.setDate(dates[0], false);
          endPicker.setDate(dates[1], false);
          startDate.value = toThaiDisplay(dates[0]);
          endDate.value = toThaiDisplay(dates[1]);
          rangeDisplay.value = raw;
          joinDate.value = raw;
        }
      } else {
        optSingle.checked = true;
        toggleDatePickers();
        const d = parseThaiSingle(raw);
        if (d) {
          singlePicker.setDate(d, false);
          singleDate.value = raw;
        }
      }
    }



    function parseThaiRange(raw) {
      raw = (raw || "").trim().replace(/[–—]/g, "-").replace(/\s*ถึง\s*/g, " - ");
      if (!raw) return null;

      // รูปแบบเต็ม: 1 มกราคม 2568 - 3 กุมภาพันธ์ 2568
      let match = raw.match(/(\d{1,2})\s+([^\s]+)\s+(\d{4})\s*-\s*(\d{1,2})\s+([^\s]+)\s+(\d{4})/);
      if (match) {
        const d1 = parseInt(match[1], 10);
        const m1 = monthsTH.indexOf(match[2]);
        let y1 = parseInt(match[3], 10);
        const d2 = parseInt(match[4], 10);
        const m2 = monthsTH.indexOf(match[5]);
        let y2 = parseInt(match[6], 10);
        if (m1 === -1 || m2 === -1) return null;
        if (y1 > 2400) y1 -= 543;
        if (y2 > 2400) y2 -= 543;
        return [new Date(y1, m1, d1), new Date(y2, m2, d2)];
      }

      // รูปแบบย่อเดือน/ปีเดียวกัน: 1 - 3 มกราคม 2568
      match = raw.match(/(\d{1,2})\s*-\s*(\d{1,2})\s+([^\s]+)\s+(\d{4})/);
      if (match) {
        const d1 = parseInt(match[1], 10);
        const d2 = parseInt(match[2], 10);
        const m = monthsTH.indexOf(match[3]);
        let y = parseInt(match[4], 10);
        if (m === -1) return null;
        if (y > 2400) y -= 543;
        return [new Date(y, m, d1), new Date(y, m, d2)];
      }

      return null;
    }

    const step1 = document.getElementById("step1");
    const step2 = document.getElementById("step2");

    const nextBtn = document.getElementById("nextBtn");
    const backBtn = document.getElementById("backBtn");


    const submitBtnStep1 = document.getElementById("submitBtn"); // ปุ่ม submit ใน step1
    const finalSubmitBtn = document.getElementById("finalSubmitBtn"); // ปุ่ม submit ใน step2

    function showStep1() {
      if (!step1 || !step2) return;
      step1.classList.remove("hidden");
      step2.classList.add("hidden");
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    }

    function showStep2() {
      if (!step1 || !step2) return;
      step1.classList.add("hidden");
      step2.classList.remove("hidden");
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    }


    function syncStepButtons() {
      const noCost = !!noCostCheckbox?.checked;

      if (noCost) {
        nextBtn?.classList.add("hidden");
        submitBtnStep1?.classList.remove("hidden");
        if (amountInput) amountInput.value = "0.00";

        if (!step2.classList.contains("hidden")) showStep1();
      } else {

        nextBtn?.classList.remove("hidden");
        submitBtnStep1?.classList.add("hidden");
      }
    }

    noCostCheckbox?.addEventListener("change", syncStepButtons);
    syncStepButtons();

    function validateStep1Minimal() {
      [
        docDateDisplay, fullname, position,
        memoSubject, academicTopic, academicLevel,
        eventSingleDate, eventStartDate, eventEndDate,
        eventTitle, singleDate, startDate, endDate, rangeDisplay,
        placeOnsite, amountInput
      ].forEach(clearError);
      let firstError = null;
      const chosenPurpose = document.querySelector('input[name="purpose"]:checked');

      if (!chosenPurpose) {
        firstError = firstError || (purposeOtherRadio || purposeRadios[0]);
        setError((purposeOtherRadio || purposeRadios[0]), "กรุณาเลือกข้อ 3");
      } else if (chosenPurpose.value === "academic") {
        if (!memoSubject?.value?.trim()) {
          firstError = firstError || memoSubject;
          setError(memoSubject, "กรุณากรอกเรื่อง");
        }

        if (!academicTopic?.value?.trim()) {
          firstError = firstError || academicTopic;
          setError(academicTopic, "กรุณากรอกหัวข้อ");
        }

        if (!academicLevel?.value?.trim()) {
          firstError = firstError || academicLevel;
          setError(academicLevel, "กรุณากรอกระดับวิชาการ");
        }

        if (eventOptSingle?.checked) {
          if (!eventSingleDate?.value?.trim()) {
            firstError = firstError || eventSingleDate;
            setError(eventSingleDate, "กรุณาเลือกวันที่จัด");
          } else {
            eventDate.value = eventSingleDate.value.trim();
          }
        } else if (eventOptRange?.checked) {
          updateEventRangeDisplay();

          if (!eventDate?.value?.trim()) {
            firstError = firstError || eventStartDate;
            setError(eventStartDate, "กรุณาเลือกวันที่เริ่มต้น");
            setError(eventEndDate, "กรุณาเลือกวันที่สิ้นสุด");
          }
        }
      } else if (chosenPurpose.value === "other") {
        if (!purposeOtherInput?.value?.trim()) {
          firstError = firstError || purposeOtherInput;
          setError(purposeOtherInput, "กรุณาระบุรายละเอียด (อื่น ๆ)");
        }
      }


      if (!docDateHidden?.value?.trim()) {
        firstError = firstError || docDateDisplay;
        setError(docDateDisplay, "กรุณาเลือกวัน เดือน ปี");
      }
      if (!fullname?.value?.trim()) {
        firstError = firstError || fullname;
        setError(fullname, "กรุณาเลือกชื่อ - นามสกุล");
      }
      if (!position?.value?.trim()) {
        firstError = firstError || position;
        setError(position, "กรุณากรอกตำแหน่ง");
      }
      if (!eventTitle?.value?.trim()) {
        firstError = firstError || eventTitle;
        setError(eventTitle, "กรุณากรอกชื่อของงาน/หลักสูตรอบรม");
      }
      if (optSingle?.checked) {
        if (!singleDate?.value?.trim()) {
          firstError = firstError || singleDate;
          setError(singleDate, "กรุณาเลือกวันที่ (วันเดียว)");
        } else {
          joinDate.value = singleDate.value.trim();
        }
      } else if (optRange?.checked) {
        if (!rangeDisplay?.value?.trim()) {
          firstError = firstError || rangeDisplay;
          setError(rangeDisplay, "กรุณาเลือกช่วงวันที่ (หลายวัน)");
        } else {
          joinDate.value = rangeDisplay.value.trim();
        }
      } else {
        firstError = firstError || optSingle;
        setError(optSingle, "กรุณาเลือก วันเดียว หรือ หลายวัน");
      }
      if (onlineCheckbox?.checked) {
        placeOnsite.value = "เข้าร่วมรูปแบบออนไลน์";
      } else if (onsiteCheckbox?.checked) {
        if (!placeOnsite?.value?.trim()) {
          firstError = firstError || placeOnsite;
          setError(placeOnsite, "กรุณาระบุสถานที่ไป (ออนไซต์)");
        }
      } else {
        firstError = firstError || onlineCheckbox;
        setError(onlineCheckbox, "กรุณาเลือก ออนไลน์ หรือ ออนไซต์");
      }
      // หน้านี้เป็นแค่ Step 1 จึงยังไม่บังคับกรอกยอดค่าใช้จ่าย
      // ยอดจริงจะถูกคำนวณ/ตรวจตอนกด "ดำเนินการ" ใน Step 2
      if (noCostCheckbox?.checked && amountInput) {
        amountInput.value = "0.00";
      }
      if (firstError) {
        scrollToFirstError(firstError);
        return false;
      }
      return true;
    }

    async function checkAllSpellFields() {
      const fields = [
        purposeOtherInput,
        memoSubject,
        eventTitle,
        academicTopic,
        academicLevel,
        placeOnsite
      ];

      let hasAnySpellError = false;
      let firstSpellErrorEl = null;

      for (const el of fields) {
        if (!el || !shouldCheckSpell(el)) continue;

        const fieldName = el.dataset.spellField || "";
        const text = (el.value || "").trim();

        if (!text) continue;

        const state = spellState[fieldName];

        if (
          state &&
          state.checked &&
          !state.hasError &&
          state.lastText === text
        ) {
          continue;
        }

        if (isApprovedText(fieldName, text) || correctedTexts[fieldName] === text) {
          setSpellPassed(el, fieldName, text, false);
          continue;
        }

        await checkSpellField(el);

        const updatedState = spellState[fieldName];
        const remainingErrors = filterApprovedErrors(updatedState?.errors || []);

        if (
          updatedState &&
          updatedState.hasError &&
          remainingErrors.length > 0
        ) {
          hasAnySpellError = true;
          firstSpellErrorEl = firstSpellErrorEl || el;
        }
      }

      for (const key in spellState) {
        const state = spellState[key];
        const remainingErrors = filterApprovedErrors(state.errors || []);

        if (state.checked && state.hasError && remainingErrors.length > 0) {
          hasAnySpellError = true;
        }
      }

      if (hasAnySpellError) {
        alert("กรุณาเลือกคำแนะนำ หรือกดใช้ข้อความเดิมก่อนดำเนินการ");

        if (firstSpellErrorEl) {
          firstSpellErrorEl.scrollIntoView({
            behavior: "smooth",
            block: "center"
          });
          firstSpellErrorEl.focus();
        }

        return false;
      }

      return true;
    }

    nextBtn?.addEventListener("click", async (event) => {
      event.preventDefault();
      event.stopPropagation();

      if (noCostCheckbox?.checked) return;

      if (optSingle?.checked && singleDate?.value?.trim()) {
        joinDate.value = singleDate.value.trim();
      }

      if (optRange?.checked) {
        updateRangeDisplay();
        if (rangeDisplay?.value?.trim()) {
          joinDate.value = rangeDisplay.value.trim();
        }
      }

      const okSpell = await checkAllSpellFields();
      if (!okSpell) return;

      if (!validateStep1Minimal()) return;

      showStep2();
    });

    backBtn?.addEventListener("click", () => {
      showStep1();
    });

    memoForm?.addEventListener("submit", async (event) => {
      event.preventDefault(); // กันส่งฟอร์มก่อนเสมอ

      // กันกดรัว
      const submitter = event.submitter || finalSubmitBtn || submitBtnStep1;

      // ✅ ถ้ากดปุ่ม "ดำเนินการ" ใน Step 2 แล้ว ให้ส่งข้อมูลค่าใช้จ่ายทันที
      // ไม่ย้อนกลับไป validate/scroll ช่องใน Step 1 อีกรอบ เพราะ Step 1 ผ่านแล้วตอนกด "ถัดไป"
      const isFinalExpenseSubmit = !!(finalSubmitBtn && submitter === finalSubmitBtn);
      if (isFinalExpenseSubmit) {
        if (submitter) submitter.disabled = true;

        try {
          if (docDateDisplay?.value && !docDateHidden?.value) {
            const d = docPicker.selectedDates[0];
            if (d) docDateHidden.value = docPicker.formatDate(d, "Y-m-d");
          }

          if (eventOptSingle?.checked && eventSingleDate?.value?.trim()) {
            eventDate.value = eventSingleDate.value.trim();
          } else if (eventOptRange?.checked) {
            updateEventRangeDisplay();
          }

          if (optSingle?.checked && singleDate?.value?.trim()) {
            joinDate.value = singleDate.value.trim();
          } else if (optRange?.checked) {
            updateRangeDisplay();
            if (rangeDisplay?.value?.trim()) joinDate.value = rangeDisplay.value.trim();
          }

          if (!preparePlaceForSubmit(true)) {
            return;
          }

          calcAll();
          if (typeof syncExpenseJsonForPresentation === "function") {
            syncExpenseJsonForPresentation();
          }

          amountInput.value = (amountInput.value || totalAmountHidden?.value || totalAmountEl?.value ||
              "0.00")
            .replace(/,/g, "")
            .trim();

          // Step 2 อยู่ในหน้านี้แล้ว จึงส่งตรงไป update/save ไม่ส่งกลับไป Step 1 และไม่ต้องไป form_Calcu.php
          memoForm.action = IS_EDIT_MODE ? "/Pro_letter/documents/update_memo.php" : "save_memo.php";
          memoForm.submit();
          return;
        } finally {
          if (submitter) submitter.disabled = false;
        }
      }

      if (submitter) submitter.disabled = true;

      try {
        // บังคับ sync ค่า display -> hidden ก่อน submit
        if (docDateDisplay?.value && !docDateHidden?.value) {
          const d = docPicker.selectedDates[0];
          if (d) {
            docDateHidden.value = docPicker.formatDate(d, "Y-m-d");
          }
        }

        [
          mainCategory, subCategory,
          docDateDisplay, fullname, position,
          purposeOtherInput,
          memoSubject, academicTopic, academicLevel,
          eventSingleDate, eventStartDate, eventEndDate,
          eventTitle,
          singleDate, startDate, endDate, rangeDisplay,
          placeOnsite, amountInput, carPlateInput
        ].forEach(clearError);

        let firstError = null;

        if (!mainCategory?.value?.trim()) {
          firstError = firstError || mainCategory;
          setError(mainCategory, "กรุณาเลือกหมวดหลัก");
        }

        if (!docDateHidden?.value?.trim()) {
          firstError = firstError || docDateDisplay;
          setError(docDateDisplay, "กรุณาเลือกวัน เดือน ปี");
        }

        if (!fullname?.value?.trim()) {
          firstError = firstError || fullname;
          setError(fullname, "กรุณาเลือกชื่อ - นามสกุล");
        }

        if (!position?.value?.trim()) {
          firstError = firstError || position;
          setError(position, "กรุณากรอกตำแหน่ง");
        }

        const chosenPurpose = document.querySelector('input[name="purpose"]:checked');
        if (!chosenPurpose) {
          firstError = firstError || (purposeOtherRadio || purposeRadios[0]);
          setError((purposeOtherRadio || purposeRadios[0]), "กรุณาเลือกข้อ 3");
        } else if (chosenPurpose.value === "academic") {
          if (!memoSubject?.value?.trim()) {
            firstError = firstError || memoSubject;
            setError(memoSubject, "กรุณากรอกเรื่อง");
          }

          if (!academicTopic?.value?.trim()) {
            firstError = firstError || academicTopic;
            setError(academicTopic, "กรุณากรอกหัวข้อ");
          }

          if (!academicLevel?.value?.trim()) {
            firstError = firstError || academicLevel;
            setError(academicLevel, "กรุณากรอกระดับวิชาการ");
          }

          if (eventOptSingle?.checked) {
            if (!eventSingleDate?.value?.trim()) {
              firstError = firstError || eventSingleDate;
              setError(eventSingleDate, "กรุณาเลือกวันที่จัด");
            } else {
              eventDate.value = eventSingleDate.value.trim();
            }
          } else if (eventOptRange?.checked) {
            updateEventRangeDisplay();

            if (!eventDate?.value?.trim()) {
              firstError = firstError || eventStartDate;
              setError(eventStartDate, "กรุณาเลือกวันที่เริ่มต้น");
              setError(eventEndDate, "กรุณาเลือกวันที่สิ้นสุด");
            }
          }
        } else if (chosenPurpose.value === "other") {
          if (!purposeOtherInput?.value?.trim()) {
            firstError = firstError || purposeOtherInput;
            setError(purposeOtherInput, "กรุณาระบุรายละเอียด (อื่น ๆ)");
          }
        }

        if (!eventTitle?.value?.trim()) {
          firstError = firstError || eventTitle;
          setError(eventTitle, "กรุณากรอกชื่อของงาน/หลักสูตรอบรม");
        }

        if (optSingle?.checked) {
          if (!singleDate?.value?.trim()) {
            firstError = firstError || singleDate;
            setError(singleDate, "กรุณาเลือกวันที่ (วันเดียว)");
          } else {
            joinDate.value = singleDate.value.trim();
          }
        } else if (optRange?.checked) {
          if (!rangeDisplay?.value?.trim()) {
            firstError = firstError || rangeDisplay;
            setError(rangeDisplay, "กรุณาเลือกช่วงวันที่ (หลายวัน)");
          } else {
            joinDate.value = rangeDisplay.value.trim();
          }
        } else {
          firstError = firstError || optSingle;
          setError(optSingle, "กรุณาเลือก วันเดียว หรือ หลายวัน");
        }

        if (onlineCheckbox?.checked) {
          placeOnsite.value = "เข้าร่วมรูปแบบออนไลน์";
        } else if (onsiteCheckbox?.checked) {
          if (!placeOnsite?.value?.trim()) {
            firstError = firstError || placeOnsite;
            setError(placeOnsite, "กรุณาระบุสถานที่ไป (ออนไซต์)");
          }
        } else {
          firstError = firstError || (onlineCheckbox || onsiteCheckbox);
          setError((onlineCheckbox || onsiteCheckbox), "กรุณาเลือก ออนไลน์ หรือ ออนไซต์");
        }

        amountInput.value = (amountInput.value || "").replace(/,/g, "").trim();

        if (noCostCheckbox?.checked) {
          amountInput.value = "0.00";
        } else {
          // ไม่ต้องบังคับกรอกยอดใน form_Memo.php
          // เพราะจะไปคำนวณจริงใน form_Calcu.php
          amountInput.value = amountInput.value || "0.00";
        }

        if (carCheckbox?.checked) {
          if (!carPlateInput?.value?.trim()) {
            firstError = firstError || carPlateInput;
            setError(carPlateInput, "กรุณากรอกทะเบียนรถ");
          }
        }

        if (firstError) {
          scrollToFirstError(firstError);
          return;
        }

        const okSpell = await checkAllSpellFields();
        if (!okSpell) return;

        // กดดำเนินการจาก Step 2 ต้องรวมรายการค่าใช้จ่ายเป็น hidden ก่อนส่ง update/save
        calcAll();
        if (typeof syncExpenseJsonForPresentation === "function") {
          syncExpenseJsonForPresentation();
        }

        // โหมดแก้ไขต้องส่งไป update_memo.php เพื่ออัปเดตเอกสารเดิม
        if (IS_EDIT_MODE) {
          memoForm.action = "/Pro_letter/documents/update_memo.php";
        } else if (noCostCheckbox?.checked) {
          memoForm.action = "save_memo.php";
          amountInput.value = "0.00";
        } else {
          memoForm.action = "form_Calcu.php";
        }

        // ผ่านทุกอย่างแล้วค่อย submit จริง
        memoForm.submit();

      } finally {
        if (submitter) submitter.disabled = false;
      }
    });

    syncPurposeUI();
    syncPlaceUI();
    syncCostUI();
    syncCarUI();
    toggleDatePickers();

    const totalAmountEl = document.getElementById("totalAmount");
    const totalAmountHidden = document.getElementById("totalAmountHidden");
    const compTotal = document.getElementById("compTotal");
    const matTotal = document.getElementById("matTotal");

    const addCompBtn = document.getElementById("addCompBtn");
    const compList = document.getElementById("compList");
    const compEmpty = document.getElementById("compEmpty");

    const addMatBtn = document.getElementById("addMatBtn");
    const matList = document.getElementById("matList");
    const matEmpty = document.getElementById("matEmpty");

    const regEnabled = document.getElementById("regEnabled");
    const regPrice = document.getElementById("regPrice");
    const regPeople = document.getElementById("regPeople");
    const regTotal = document.getElementById("regTotal");

    const lodEnabled = document.getElementById("lodEnabled");
    const lodUnit = document.getElementById("lodUnit");
    const lodNights = document.getElementById("lodNights");
    const lodPeople = document.getElementById("lodPeople");
    const lodDateText = document.getElementById("lodDateText");
    const lodOptSingle = document.getElementById("lodOptSingle");
    const lodOptRange = document.getElementById("lodOptRange");
    const lodSingleDate = document.getElementById("lodSingleDate");
    const lodStartDate = document.getElementById("lodStartDate");
    const lodEndDate = document.getElementById("lodEndDate");
    const lodTotal = document.getElementById("lodTotal");

    const perEnabled = document.getElementById("perEnabled");
    const perUnit = document.getElementById("perUnit");
    const perMeals = document.getElementById("perMeals");
    const perPeople = document.getElementById("perPeople");
    const perTotal = document.getElementById("perTotal");

    const trEnabled = document.getElementById("trEnabled");
    const addTrItemBtn = document.getElementById("addTrItemBtn");
    const trList = document.getElementById("trList");
    const trEmpty = document.getElementById("trEmpty");
    const trTotal = document.getElementById("trTotal");


    document.addEventListener("click", (e) => {
      const ignoreBtn = e.target.closest(".spell-ignore-btn");
      if (!ignoreBtn) return;

      const target = byId(ignoreBtn.dataset.target);
      if (!target) return;

      const fieldName = target.dataset.spellField || "";
      const currentText = (target.value || "").trim();

      // ✅ จำข้อความ/คำนี้ว่าอนุญาตแล้วในหน้านี้
      setSpellPassed(target, fieldName, currentText, true);
    });



    document.addEventListener("click", (e) => {
      const btn = e.target.closest(".spell-suggestion-btn");
      if (!btn) return;

      const target = byId(btn.dataset.target);
      const word = btn.dataset.word;
      const wrongWord = btn.dataset.wrongWord;

      if (!target || !word || !wrongWord) return;

      const beforeText = target.value || "";
      const afterText = replaceWholeWordOnce(beforeText, wrongWord, word);
      target.value = afterText;

      const fieldName = target.dataset.spellField || "";
      const currentText = (target.value || "").trim();

      // ✅ คำที่ผู้ใช้เลือกจากคำแนะนำ ถือว่าผ่านทันที ไม่ต้องตรวจซ้ำทันที
      correctedTexts[fieldName] = currentText;
      approvedWords.add(word);

      setSpellPassed(target, fieldName, currentText, false);
    });


    function n(v) {
      const x = Number(String(v ?? "").replace(/,/g, ""));
      return Number.isFinite(x) ? x : 0;
    }

    function money(x) {
      return (Math.round((x + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    const GOV_LOD_RATE_ONE_PERSON = 1500;
    const GOV_LOD_RATE_MULTI_PERSON = 1000;
    const GOV_MEAL_RATE = 120;

    function defaultLodRateByPeople() {
      return n(lodPeople?.value || 1) > 1 ? GOV_LOD_RATE_MULTI_PERSON : GOV_LOD_RATE_ONE_PERSON;
    }

    function applyDefaultLodRate(force = false) {
      if (!lodUnit) return;
      const current = n(lodUnit.value);
      const oldDefaultValues = [0, GOV_LOD_RATE_ONE_PERSON, GOV_LOD_RATE_MULTI_PERSON];
      if (force || lodUnit.dataset.userEdited !== "1" || oldDefaultValues.includes(current)) {
        lodUnit.value = String(defaultLodRateByPeople());
        lodUnit.dataset.userEdited = "0";
      }
    }

    function applyDefaultMealRate(force = false) {
      if (!perUnit) return;
      const current = n(perUnit.value);
      if (force || perUnit.dataset.userEdited !== "1" || current === 0 || current === GOV_MEAL_RATE) {
        perUnit.value = String(GOV_MEAL_RATE);
        perUnit.dataset.userEdited = "0";
      }
    }
    const lodMonthsTH = [
      "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
      "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
    ];

    function lodThaiShortDate(dateObj) {
      if (!dateObj) return "";
      const d = dateObj.getDate();
      const m = lodMonthsTH[dateObj.getMonth()];
      const y = String(dateObj.getFullYear() + 543).slice(-2);
      return `${d} ${m} ${y}`;
    }

    function normalizeThaiNumber(value) {
      const thaiDigits = "๐๑๒๓๔๕๖๗๘๙";
      return String(value || "").replace(/[๐-๙]/g, ch => {
        const index = thaiDigits.indexOf(ch);
        return index >= 0 ? String(index) : ch;
      });
    }

    function parseLodThaiShortDate(raw) {
      const text = normalizeThaiNumber(String(raw || "").trim());
      const match = text.match(/(\d+)\s+([^\s]+)\s+(\d{2}|\d{4})/);
      if (!match) return null;

      const day = parseInt(match[1], 10);
      const monthIndex = lodMonthsTH.indexOf(match[2]);
      let year = parseInt(match[3], 10);
      if (monthIndex === -1) return null;
      if (year < 100) year = 2500 + year;
      if (year > 2400) year -= 543;

      return new Date(year, monthIndex, day);
    }

    function diffLodNights(startDateObj, endDateObj) {
      if (!startDateObj || !endDateObj) return 1;
      const start = new Date(startDateObj.getFullYear(), startDateObj.getMonth(), startDateObj.getDate());
      const end = new Date(endDateObj.getFullYear(), endDateObj.getMonth(), endDateObj.getDate());
      const diff = Math.round((end - start) / (1000 * 60 * 60 * 24));
      return diff > 0 ? diff : 1;
    }

    function syncLodNightsFromDates() {
      if (!lodNights) return;

      if (lodOptSingle?.checked) {
        lodNights.value = 1;
        return;
      }

      const start = lodStartPicker?.selectedDates?. [0] || parseLodThaiShortDate(lodStartDate?.value || "");
      const end = lodEndPicker?.selectedDates?. [0] || parseLodThaiShortDate(lodEndDate?.value || "");
      if (start && end) {
        lodNights.value = diffLodNights(start, end);
      }
    }

    function updateLodDateText() {
      if (!lodDateText) return;

      if (lodOptSingle?.checked) {
        lodDateText.value = lodSingleDate?.value || "";
        if (lodNights) lodNights.value = 1;
      } else {
        const start = lodStartDate?.value || "";
        const end = lodEndDate?.value || "";
        lodDateText.value = (start && end) ? `${start} – ${end}` : "";
        syncLodNightsFromDates();
      }

      calcAll();
    }

    const lodSinglePicker = flatpickr(lodSingleDate, {
      locale: "th",
      disableMobile: true,
      allowInput: false,
      onChange: ([d]) => {
        lodSingleDate.value = d ? lodThaiShortDate(d) : "";
        updateLodDateText();
      }
    });

    const lodStartPicker = flatpickr(lodStartDate, {
      locale: "th",
      disableMobile: true,
      allowInput: false,
      onChange: ([d]) => {
        lodStartDate.value = d ? lodThaiShortDate(d) : "";
        updateLodDateText();
      }
    });

    const lodEndPicker = flatpickr(lodEndDate, {
      locale: "th",
      disableMobile: true,
      allowInput: false,
      onChange: ([d]) => {
        lodEndDate.value = d ? lodThaiShortDate(d) : "";
        updateLodDateText();
      }
    });

    [lodOptSingle, lodOptRange].forEach(el => {
      el?.addEventListener("change", updateLodDateText);
    });

    function toggleBlock(enabledEl, blockEl) {
      if (!enabledEl || !blockEl) return;
      blockEl.style.opacity = enabledEl.checked ? "1" : "0.55";
      blockEl.style.pointerEvents = enabledEl.checked ? "auto" : "none";
    }

    function makeRow({
      type,
      container,
      emptyEl,
      placeholder
    }) {
      const row = document.createElement("div");
      row.className = "p-3 rounded-[16px] border-2 flex flex-wrap gap-3 items-end";
      row.style.borderColor = "#11c2b9";
      row.style.background = "#ffffff";
      row.innerHTML = `
    <div class="flex-1 min-w-[260px]">
      <label class="text-gray-700">รายละเอียด</label>
      <input type="text" class="w-full border rounded-md p-2 js-desc" placeholder="${placeholder}">
    </div>
    <div class="w-[180px]">
      <label class="text-gray-700">จำนวนเงิน (บาท)</label>
      <input type="number" class="w-full border rounded-md p-2 js-amt" min="0" step="0.01" value="0">
    </div>
    <div>
      <button type="button" class="js-del bg-white border-2 border-red-400 text-red-600 font-bold px-3 py-2 rounded-md hover:bg-red-50">
        ลบ
      </button>
    </div>
    <input type="hidden" class="js-type" value="${type}">
  `;
      row.querySelector(".js-del").addEventListener("click", () => {
        row.remove();
        syncEmpty(container, emptyEl);
        calcAll();
      });
      row.querySelector(".js-desc").addEventListener("input", calcAll);
      row.querySelector(".js-amt").addEventListener("input", calcAll);
      container.appendChild(row);
      syncEmpty(container, emptyEl);
      calcAll();
    }

    function makeTransportRow(data = {}) {
      const row = document.createElement("div");
      row.className = "p-4 rounded-[18px] border-2 bg-white";
      row.style.borderColor = "#11c2b9";

      row.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-4">
          <div class="md:col-span-4">
            <label class="text-gray-800 font-bold block mb-1">ประเภทพาหนะ</label>
            <select class="w-full border-2 border-[#11C2B9] bg-[#e3f9f8] rounded-md p-2 font-bold js-tr-type">
              <option value="fuel">ค่าน้ำมันรถยนต์</option>
              <option value="flight">เครื่องบิน</option>
              <option value="other">อื่น ๆ</option>
            </select>
          </div>

          <div class="md:col-span-5 text-gray-500 text-sm pb-2 js-tr-hint">
            ค่าน้ำมัน = ระยะทาง × บาท/กม. × จำนวนเที่ยว
          </div>

          <div class="md:col-span-3 flex md:justify-end">
            <button type="button"
              class="js-tr-del bg-white border-2 border-red-400 text-red-600 font-bold px-4 py-2 rounded-md hover:bg-red-50">
              ลบรายการ
            </button>
          </div>
        </div>

        <div class="js-tr-section js-tr-fuel space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="text-gray-700 block mb-1">ต้นทาง</label>
              <input type="text" class="w-full border rounded-md p-2 js-tr-origin" placeholder="เช่น ปราจีนบุรี">
            </div>
            <div>
              <label class="text-gray-700 block mb-1">ปลายทาง</label>
              <input type="text" class="w-full border rounded-md p-2 js-tr-destination" placeholder="เช่น กรุงเทพฯ">
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="text-gray-700 block mb-1">ระยะทาง (กม.)</label>
              <input type="number" class="w-full border rounded-md p-2 js-tr-distance" min="0" step="0.01" value="0">
            </div>
            <div>
              <label class="text-gray-700 block mb-1">บาท/กม.</label>
              <input type="number" class="w-full border rounded-md p-2 js-tr-rate" min="0" step="0.01" value="4">
            </div>
            <div>
              <label class="text-gray-700 block mb-1">จำนวนเที่ยว</label>
              <input type="number" class="w-full border rounded-md p-2 js-tr-trips" min="1" step="1" value="1">
            </div>
          </div>
        </div>

        <div class="js-tr-section js-tr-flight hidden space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="text-gray-700 block mb-1">สายการบิน</label>
              <input type="text" class="w-full border rounded-md p-2 js-tr-airline" placeholder="เช่น Thai Airways">
            </div>
            <div>
              <label class="text-gray-700 block mb-1">เส้นทางบิน</label>
              <input type="text" class="w-full border rounded-md p-2 js-tr-route-flight" placeholder="เช่น BKK - CNX">
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="text-gray-700 block mb-1">ราคาตั๋ว/คน</label>
              <input type="number" class="w-full border rounded-md p-2 js-tr-ticket" min="0" step="0.01" value="0">
            </div>
            <div>
              <label class="text-gray-700 block mb-1">จำนวนเที่ยว</label>
              <input type="number" class="w-full border rounded-md p-2 js-tr-flight-trips" min="1" step="1" value="1">
            </div>
            <div>
              <label class="text-gray-700 block mb-1">จำนวนคน</label>
              <input type="number" class="w-full border rounded-md p-2 js-tr-flight-people" min="1" step="1" value="1">
            </div>
          </div>
        </div>

        <div class="js-tr-section js-tr-other hidden space-y-3">
          <div>
            <label class="text-gray-700 block mb-1">รายละเอียด/เส้นทาง</label>
            <input type="text" class="w-full border rounded-md p-2 js-tr-route-other" placeholder="เช่น รถตู้ / แท็กซี่ / ค่าเดินทางอื่น ๆ">
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="text-gray-700 block mb-1">ราคา/เที่ยว/คน</label>
              <input type="number" class="w-full border rounded-md p-2 js-tr-unit" min="0" step="0.01" value="0">
            </div>
            <div>
              <label class="text-gray-700 block mb-1">จำนวนเที่ยว</label>
              <input type="number" class="w-full border rounded-md p-2 js-tr-other-trips" min="1" step="1" value="1">
            </div>
            <div>
              <label class="text-gray-700 block mb-1">จำนวนคน</label>
              <input type="number" class="w-full border rounded-md p-2 js-tr-other-people" min="1" step="1" value="1">
            </div>
          </div>
        </div>

        <div class="mt-4 flex justify-end">
          <div class="bg-gray-50 border rounded-md px-4 py-2 text-gray-800 font-bold">
            รวมรายการนี้: <span class="js-tr-row-total ml-1">0.00</span> บาท
          </div>
        </div>
        <input type="hidden" class="js-type" value="transport">
      `;

      const typeSelect = row.querySelector(".js-tr-type");

      function setVal(selector, value) {
        const el = row.querySelector(selector);
        if (el && value !== undefined && value !== null && value !== "") el.value = value;
      }

      if (data.type) typeSelect.value = data.type;
      setVal(".js-tr-origin", data.origin);
      setVal(".js-tr-destination", data.destination);
      setVal(".js-tr-distance", data.distance);
      setVal(".js-tr-rate", data.rate ?? 4);
      setVal(".js-tr-trips", data.trips ?? 1);

      setVal(".js-tr-airline", data.airline);
      setVal(".js-tr-route-flight", data.route);
      setVal(".js-tr-ticket", data.ticket_price);
      setVal(".js-tr-flight-trips", data.trips ?? 1);
      setVal(".js-tr-flight-people", data.people ?? 1);

      setVal(".js-tr-route-other", data.route || data.desc);
      setVal(".js-tr-unit", data.unit_price);
      setVal(".js-tr-other-trips", data.trips ?? 1);
      setVal(".js-tr-other-people", data.people ?? 1);

      function syncTransportTypeUI() {
        const type = typeSelect.value;
        row.querySelector(".js-tr-fuel").classList.toggle("hidden", type !== "fuel");
        row.querySelector(".js-tr-flight").classList.toggle("hidden", type !== "flight");
        row.querySelector(".js-tr-other").classList.toggle("hidden", type !== "other");

        const hint = row.querySelector(".js-tr-hint");
        if (hint) {
          if (type === "fuel") hint.textContent = "ค่าน้ำมัน = ระยะทาง × บาท/กม. × จำนวนเที่ยว";
          else if (type === "flight") hint.textContent = "เครื่องบิน = ราคาตั๋ว/คน × จำนวนเที่ยว × จำนวนคน";
          else hint.textContent = "อื่น ๆ = ราคา/เที่ยว/คน × จำนวนเที่ยว × จำนวนคน";
        }
        calcAll();
      }

      row.querySelector(".js-tr-del").addEventListener("click", () => {
        row.remove();
        syncEmpty(trList, trEmpty);
        calcAll();
      });

      row.querySelectorAll("input, select").forEach(el => {
        el.addEventListener("input", calcAll);
        el.addEventListener("change", calcAll);
      });
      typeSelect.addEventListener("change", syncTransportTypeUI);

      trList.appendChild(row);
      syncTransportTypeUI();
      syncEmpty(trList, trEmpty);
      calcAll();
      return row;
    }

    function getTransportRowData(row) {
      const type = row.querySelector(".js-tr-type")?.value || "other";

      if (type === "fuel") {
        const distance = n(row.querySelector(".js-tr-distance")?.value);
        const rate = n(row.querySelector(".js-tr-rate")?.value);
        const trips = n(row.querySelector(".js-tr-trips")?.value || 1);
        return {
          type: "fuel",
          origin: (row.querySelector(".js-tr-origin")?.value || "").trim(),
          destination: (row.querySelector(".js-tr-destination")?.value || "").trim(),
          distance,
          rate,
          trips,
          amount: distance * rate * trips
        };
      }

      if (type === "flight") {
        const ticket = n(row.querySelector(".js-tr-ticket")?.value);
        const trips = n(row.querySelector(".js-tr-flight-trips")?.value || 1);
        const people = n(row.querySelector(".js-tr-flight-people")?.value || 1);
        return {
          type: "flight",
          airline: (row.querySelector(".js-tr-airline")?.value || "").trim(),
          route: (row.querySelector(".js-tr-route-flight")?.value || "").trim(),
          ticket_price: ticket,
          trips,
          people,
          amount: ticket * trips * people
        };
      }

      const unit = n(row.querySelector(".js-tr-unit")?.value);
      const trips = n(row.querySelector(".js-tr-other-trips")?.value || 1);
      const people = n(row.querySelector(".js-tr-other-people")?.value || 1);
      return {
        type: "other",
        route: (row.querySelector(".js-tr-route-other")?.value || "").trim(),
        unit_price: unit,
        trips,
        people,
        amount: unit * trips * people
      };
    }

    function formatTransportDesc(data) {
      if (!data) return "ค่าพาหนะ";
      if (data.type === "fuel") {
        return `ค่าพาหนะ\n- ค่าน้ำมันรถยนต์ ${data.origin || ""} ไป ${data.destination || ""}\n- ระยะทาง ${n(data.distance)} กม. × ${n(data.rate)} บาท × ${n(data.trips || 1)} เที่ยว`;
      }
      if (data.type === "flight") {
        return `ค่าพาหนะ\n- ค่าโดยสารตั๋วเครื่องบิน ไป-กลับ ชั้นประหยัด\n- ${data.airline || ""} ${data.route || ""}\n- ${money(n(data.ticket_price))} บาท × ${n(data.trips || 1)} เที่ยว × ${n(data.people || 1)} คน`;
      }
      return `ค่าพาหนะ\n- ${data.route || "ค่าพาหนะ"}\n- ${money(n(data.unit_price))} บาท × ${n(data.trips || 1)} เที่ยว × ${n(data.people || 1)} คน`;
    }

    function calcTransportTotal() {
      let sum = 0;
      [...(trList?.children || [])].forEach(row => {
        const data = getTransportRowData(row);
        sum += n(data.amount);
        const totalEl = row.querySelector(".js-tr-row-total");
        if (totalEl) totalEl.textContent = money(data.amount);
      });
      return sum;
    }

    function parseTransportDescToData(desc, amount = 0) {
      const clean = normalizeThaiNumber(String(desc || ""));

      if (clean.includes("ค่าน้ำมันรถยนต์")) {
        const routeMatch = clean.match(/ค่าน้ำมันรถยนต์\s*(.*?)\s*ไป\s*(.*?)(?:\n|- ระยะทาง|$)/);
        const formulaMatch = clean.match(
          /ระยะทาง\s*([\d.]+)\s*กม\.\s*[×x]\s*([\d.]+)\s*บาท\s*[×x]\s*([\d.]+)\s*เที่ยว/);
        return {
          type: "fuel",
          origin: routeMatch?. [1]?.trim() || "",
          destination: routeMatch?. [2]?.trim() || "",
          distance: formulaMatch ? Number(formulaMatch[1]) : 0,
          rate: formulaMatch ? Number(formulaMatch[2]) : 4,
          trips: formulaMatch ? Number(formulaMatch[3]) : 1
        };
      }

      if (clean.includes("เครื่องบิน") || clean.includes("ตั๋วเครื่องบิน")) {
        const lines = clean.split(/\n+/).map(s => s.replace(/^-+\s*/, "").trim()).filter(Boolean);
        const routeLine = lines.find(line => !line.includes("ค่าพาหนะ") && !line.includes("ตั๋วเครื่องบิน") && !line
          .includes("บาท ×")) || "";
        const formulaMatch = clean.match(/([\d,.]+)\s*บาท\s*[×x]\s*([\d.]+)\s*เที่ยว\s*[×x]\s*([\d.]+)\s*คน/);
        return {
          type: "flight",
          airline: "",
          route: routeLine,
          ticket_price: formulaMatch ? Number(String(formulaMatch[1]).replace(/,/g, "")) : n(amount),
          trips: formulaMatch ? Number(formulaMatch[2]) : 1,
          people: formulaMatch ? Number(formulaMatch[3]) : 1
        };
      }

      const lines = clean.split(/\n+/).map(s => s.replace(/^-+\s*/, "").trim()).filter(Boolean);
      const routeLine = lines.find(line => line && !line.includes("ค่าพาหนะ") && !line.includes("บาท ×")) || "";
      const formulaMatch = clean.match(/([\d,.]+)\s*บาท\s*[×x]\s*([\d.]+)\s*เที่ยว\s*[×x]\s*([\d.]+)\s*คน/);
      return {
        type: "other",
        route: routeLine || clean,
        unit_price: formulaMatch ? Number(String(formulaMatch[1]).replace(/,/g, "")) : n(amount),
        trips: formulaMatch ? Number(formulaMatch[2]) : 1,
        people: formulaMatch ? Number(formulaMatch[3]) : 1
      };
    }

    function syncEmpty(container, emptyEl) {
      if (!container || !emptyEl) return;
      emptyEl.style.display = container.children.length ? "none" : "block";
    }
    addCompBtn?.addEventListener("click", () => {
      makeRow({
        type: "other",
        container: compList,
        emptyEl: compEmpty,
        placeholder: "เช่น ค่าตอบแทนวิทยากร"
      });
    });
    addMatBtn?.addEventListener("click", () => {
      makeRow({
        type: "other",
        container: matList,
        emptyEl: matEmpty,
        placeholder: "เช่น วัสดุสิ้นเปลือง"
      });
    });

    addTrItemBtn?.addEventListener("click", () => {
      makeTransportRow();
    });
    regEnabled?.addEventListener("change", () => {
      calcAll();
    });
    lodEnabled?.addEventListener("change", () => {
      calcAll();
    });
    perEnabled?.addEventListener("change", () => {
      calcAll();
    });
    trEnabled?.addEventListener("change", () => {
      calcAll();
    });

    function calcReg() {
      if (!regEnabled?.checked) return 0;
      return n(regPrice?.value) * n(regPeople?.value || 1);
    }

    function calcLod() {
      if (!lodEnabled?.checked) return 0;
      return n(lodUnit?.value) * n(lodNights?.value || 1) * n(lodPeople?.value || 1);
    }

    function calcPer() {
      if (!perEnabled?.checked) return 0;
      return n(perUnit?.value) * n(perMeals?.value || 1) * n(perPeople?.value || 1);
    }

    function calcDynamic(container, requiredType = null) {
      let sum = 0;
      if (!container) return 0;
      [...container.children].forEach(row => {
        const type = row.querySelector(".js-type")?.value || "other";
        if (requiredType && type !== requiredType) return;
        const amt = n(row.querySelector(".js-amt")?.value);
        sum += amt;
      });
      return sum;
    }

    function calcAll() {
      const compSum = calcDynamic(compList);
      const matSum = calcDynamic(matList);

      const regSum = calcReg();
      const lodSum = calcLod();
      const perSum = calcPer();

      let trSum = 0;
      if (trEnabled?.checked) trSum = calcTransportTotal();
      regTotal.textContent = money(regSum);
      lodTotal.textContent = money(lodSum);
      perTotal.textContent = money(perSum);
      trTotal.textContent = money(trSum);
      const total = compSum + matSum + regSum + lodSum + perSum + trSum;
      if (totalAmountEl) totalAmountEl.value = money(total);
      if (totalAmountHidden) totalAmountHidden.value = money(total);
      if (amountInput && !noCostCheckbox?.checked) {
        amountInput.value = money(total);
      }
      buildBudgetHiddenInputs();
    }
    lodUnit?.addEventListener("input", () => {
      lodUnit.dataset.userEdited = "1";
    });
    perUnit?.addEventListener("input", () => {
      perUnit.dataset.userEdited = "1";
    });
    lodPeople?.addEventListener("input", () => {
      applyDefaultLodRate(false);
      calcAll();
    });
    lodPeople?.addEventListener("change", () => {
      applyDefaultLodRate(false);
      calcAll();
    });

    [regPrice, regPeople, lodUnit, lodNights, lodPeople, perUnit, perMeals, perPeople]
    .forEach(el => el?.addEventListener("input", calcAll));

    [lodSingleDate, lodStartDate, lodEndDate].forEach(el => {
      el?.addEventListener("change", updateLodDateText);
    });

    function clearOldHidden(prefix) {
      memoForm.querySelectorAll(`input[data-budget="${prefix}"]`).forEach(el => el.remove());
    }

    function addHidden(name, value) {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = value;
      input.setAttribute("data-budget", "1");
      memoForm.appendChild(input);
    }

    function setNamedHidden(name, value) {
      let input = memoForm.querySelector(`input[name="${name}"]`);
      if (!input) {
        input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        memoForm.appendChild(input);
      }
      input.value = value;
    }

    function collectRows(container) {
      return [...(container?.children || [])].map(row => ({
        desc: (row.querySelector(".js-desc")?.value || "").trim(),
        amount: n(row.querySelector(".js-amt")?.value)
      })).filter(item => item.desc || item.amount > 0);
    }

    function buildExpenseJsonForPresentation() {
      const compensation = collectRows(compList);
      const materials = collectRows(matList);

      const transportItems = [...(trList?.children || [])].map(row => {
        const data = getTransportRowData(row);
        if (!data || n(data.amount) <= 0) return null;

        if (data.type === "fuel") {
          return {
            type: "fuel",
            origin: data.origin,
            destination: data.destination,
            distance: n(data.distance),
            rate: n(data.rate),
            trips: n(data.trips || 1)
          };
        }

        if (data.type === "flight") {
          return {
            type: "flight",
            airline: data.airline,
            route: data.route,
            ticket_price: n(data.ticket_price),
            trips: n(data.trips || 1),
            people: n(data.people || 1)
          };
        }

        return {
          type: "other",
          route: data.route || "ค่าพาหนะ",
          unit_price: n(data.unit_price),
          trips: n(data.trips || 1),
          people: n(data.people || 1)
        };
      }).filter(Boolean);

      return {
        compensation,
        allowance: {
          registration: {
            enabled: !!regEnabled?.checked,
            price: n(regPrice?.value),
            people: n(regPeople?.value || 1)
          },
          lodging: {
            enabled: !!lodEnabled?.checked,
            date_text: lodDateText?.value || "",
            unit_price: n(lodUnit?.value),
            nights: n(lodNights?.value || 1),
            people: n(lodPeople?.value || 1)
          },
          perdiem: {
            enabled: !!perEnabled?.checked,
            unit_price: n(perUnit?.value),
            meals: n(perMeals?.value || 1),
            people: n(perPeople?.value || 1)
          },
          transport: {
            enabled: !!trEnabled?.checked,
            items: transportItems
          }
        },
        materials
      };
    }

    function syncExpenseJsonForPresentation() {
      const expenseData = buildExpenseJsonForPresentation();
      setNamedHidden("expense_json", JSON.stringify(expenseData));
      setNamedHidden("amount", totalAmountHidden?.value || totalAmountEl?.value || "0.00");
    }

    function buildBudgetHiddenInputs() {
      memoForm.querySelectorAll('input[data-budget="1"]').forEach(el => el.remove());
      if (regEnabled?.checked) {
        const amt = money(calcReg());
        if (amt !== "0.00") {
          addHidden("budget_type[]", "registration");
          addHidden("budget_desc[]", `ค่าลงทะเบียน (${n(regPrice.value)} × ${n(regPeople.value || 1)} คน)`);
          addHidden("budget_amount[]", amt);
        }
      }
      if (lodEnabled?.checked) {
        const amt = money(calcLod());
        if (amt !== "0.00") {
          addHidden("budget_type[]", "accommodation");
          addHidden("budget_desc[]",
            `ค่าที่พัก ${lodDateText?.value || ""} (${n(lodUnit.value)} × ${n(lodNights.value || 1)} คืน × ${n(lodPeople.value || 1)} คน)`
          );
          addHidden("budget_amount[]", amt);
        }
      }
      if (perEnabled?.checked) {
        const amt = money(calcPer());
        if (amt !== "0.00") {
          addHidden("budget_type[]", "per_diem");
          addHidden("budget_desc[]",
            `ค่าอาหาร (${n(perUnit.value)} × ${n(perMeals.value || 1)} มื้อ × ${n(perPeople.value || 1)} คน)`
          );
          addHidden("budget_amount[]", amt);
        }
      }
      if (trEnabled?.checked) {
        [...(trList?.children || [])].forEach(row => {
          const data = getTransportRowData(row);
          const amt = money(n(data?.amount));
          if (amt === "0.00") return;
          addHidden("budget_type[]", "transport");
          addHidden("budget_desc[]", formatTransportDesc(data));
          addHidden("budget_amount[]", amt);
        });
      }
      [...(matList?.children || [])].forEach(row => {
        const desc = (row.querySelector(".js-desc")?.value || "").trim();
        const amt = money(n(row.querySelector(".js-amt")?.value));
        if (!desc && amt === "0.00") return;
        addHidden("budget_type[]", "other");
        addHidden("budget_desc[]", desc || "ค่าวัสดุ");
        addHidden("budget_amount[]", amt);
      });
    }

    function makePresetBudgetRow({
      type,
      container,
      emptyEl,
      desc,
      amount
    }) {
      const row = document.createElement("div");
      row.className = "p-3 rounded-[16px] border-2 flex flex-wrap gap-3 items-end";
      row.style.borderColor = "#11c2b9";
      row.style.background = "#ffffff";
      row.innerHTML = `
    <div class="flex-1 min-w-[260px]">
      <label class="text-gray-700">รายละเอียด</label>
      <input type="text" class="w-full border rounded-md p-2 js-desc" placeholder="รายละเอียด" value="${escapeHtml(desc || "")}">
    </div>
    <div class="w-[180px]">
      <label class="text-gray-700">จำนวนเงิน (บาท)</label>
      <input type="number" class="w-full border rounded-md p-2 js-amt" min="0" step="0.01" value="${money(n(amount))}">
    </div>
    <div>
      <button type="button" class="js-del bg-white border-2 border-red-400 text-red-600 font-bold px-3 py-2 rounded-md hover:bg-red-50">
        ลบ
      </button>
    </div>
    <input type="hidden" class="js-type" value="${type}">
  `;
      row.querySelector(".js-del").addEventListener("click", () => {
        row.remove();
        syncEmpty(container, emptyEl);
        calcAll();
      });
      row.querySelector(".js-desc").addEventListener("input", calcAll);
      row.querySelector(".js-amt").addEventListener("input", calcAll);
      container.appendChild(row);
      syncEmpty(container, emptyEl);
    }

    function parseBudgetFormula(desc, keys = []) {
      const clean = normalizeThaiNumber(String(desc || ""));
      const bracket = clean.match(/\(([^)]*)\)/);
      const source = bracket ? bracket[1] : clean;
      const nums = (source.match(/\d+(?:\.\d+)?/g) || []).map(Number);

      const result = {};
      keys.forEach((key, i) => {
        if (typeof nums[i] !== "undefined" && Number.isFinite(nums[i])) {
          result[key] = nums[i];
        }
      });
      return result;
    }

    function extractLodDateTextFromDesc(desc) {
      const clean = String(desc || "").trim();
      const match = clean.match(/ค่าที่พัก\s*(.*?)\s*\(/);
      if (match && match[1]) return match[1].trim();
      return "";
    }

    function prefillLodDateFromDescription(desc) {
      const dateText = extractLodDateTextFromDesc(desc);
      if (!dateText) return false;

      if (dateText.includes("–") || dateText.includes("-") || dateText.includes("ถึง")) {
        const parts = dateText.split(/\s*(?:–|-|ถึง)\s*/).filter(Boolean);
        if (parts.length >= 2) {
          const startObj = parseLodThaiShortDate(parts[0]);
          const endObj = parseLodThaiShortDate(parts[1]);

          if (lodOptRange) lodOptRange.checked = true;
          if (lodOptSingle) lodOptSingle.checked = false;

          if (lodStartDate) lodStartDate.value = parts[0];
          if (lodEndDate) lodEndDate.value = parts[1];

          if (startObj && lodStartPicker) lodStartPicker.setDate(startObj, false);
          if (endObj && lodEndPicker) lodEndPicker.setDate(endObj, false);

          updateLodDateText();
          return true;
        }
      }

      const singleObj = parseLodThaiShortDate(dateText);
      if (lodOptSingle) lodOptSingle.checked = true;
      if (lodOptRange) lodOptRange.checked = false;
      if (lodSingleDate) lodSingleDate.value = dateText;
      if (singleObj && lodSinglePicker) lodSinglePicker.setDate(singleObj, false);

      updateLodDateText();
      return true;
    }

    function preloadExpenseJsonData() {
      if (!initialExpenseData || typeof initialExpenseData !== "object" || Array.isArray(initialExpenseData)) {
        return false;
      }

      const hasData = Object.keys(initialExpenseData).length > 0;
      if (!hasData) return false;

      const allowance = initialExpenseData.allowance || {};

      const registration = allowance.registration || {};
      if (registration.enabled) {
        if (regEnabled) regEnabled.checked = true;
        if (regPrice) regPrice.value = n(registration.price);
        if (regPeople) regPeople.value = n(registration.people || 1) || 1;
      }

      const lodging = allowance.lodging || {};
      if (lodging.enabled) {
        if (lodEnabled) lodEnabled.checked = true;
        if (lodUnit) {
          lodUnit.value = n(lodging.unit_price);
          lodUnit.dataset.userEdited = "1";
        }
        if (lodPeople) lodPeople.value = n(lodging.people || 1) || 1;

        const dateText = String(lodging.date_text || "").trim();
        if (dateText) {
          prefillLodDateFromDescription(`ค่าที่พัก ${dateText} (`);
        }

        if (lodNights) {
          lodNights.value = n(lodging.nights || lodNights.value || 1) || 1;
        }

        updateLodDateText();
      }

      const perdiem = allowance.perdiem || {};
      if (perdiem.enabled) {
        if (perEnabled) perEnabled.checked = true;
        if (perUnit) {
          perUnit.value = n(perdiem.unit_price);
          perUnit.dataset.userEdited = "1";
        }
        if (perMeals) perMeals.value = n(perdiem.meals || 1) || 1;
        if (perPeople) perPeople.value = n(perdiem.people || 1) || 1;
      }

      const transport = allowance.transport || {};
      if (transport.enabled) {
        if (trEnabled) trEnabled.checked = true;
        if (Array.isArray(transport.items)) {
          transport.items.forEach(item => makeTransportRow(item || {}));
        }
      }

      if (Array.isArray(initialExpenseData.compensation)) {
        initialExpenseData.compensation.forEach(item => {
          makePresetBudgetRow({
            type: "other",
            container: compList,
            emptyEl: compEmpty,
            desc: item?.desc || "",
            amount: item?.amount || 0
          });
        });
      }

      if (Array.isArray(initialExpenseData.materials)) {
        initialExpenseData.materials.forEach(item => {
          makePresetBudgetRow({
            type: "other",
            container: matList,
            emptyEl: matEmpty,
            desc: item?.desc || "",
            amount: item?.amount || 0
          });
        });
      }

      return true;
    }

    function preloadBudgetItems() {
      if (preloadExpenseJsonData()) return;
      if (!Array.isArray(initialBudgetItems) || initialBudgetItems.length === 0) return;

      initialBudgetItems.forEach(item => {
        const type = String(item.item_type || item.type || "other");
        const desc = String(item.description || "");
        const amt = money(n(item.amount));

        if (type === "registration") {
          const f = parseBudgetFormula(desc, ["price", "people"]);
          if (regEnabled) regEnabled.checked = true;
          if (regPrice) regPrice.value = typeof f.price !== "undefined" ? f.price : amt;
          if (regPeople) regPeople.value = typeof f.people !== "undefined" ? f.people : 1;
          return;
        }

        if (type === "accommodation") {
          const f = parseBudgetFormula(desc, ["unit", "nights", "people"]);
          const hasPrefilledDate = prefillLodDateFromDescription(desc);

          if (lodEnabled) lodEnabled.checked = true;
          if (lodUnit) {
            lodUnit.value = typeof f.unit !== "undefined" ? f.unit : amt;
            lodUnit.dataset.userEdited = "1";
          }
          if (lodPeople) lodPeople.value = typeof f.people !== "undefined" ? f.people : 1;
          if (!hasPrefilledDate && lodNights) {
            lodNights.value = typeof f.nights !== "undefined" ? f.nights : 1;
          }

          updateLodDateText();
          return;
        }

        if (type === "per_diem") {
          const f = parseBudgetFormula(desc, ["unit", "meals", "people"]);
          if (perEnabled) perEnabled.checked = true;
          if (perUnit) {
            perUnit.value = typeof f.unit !== "undefined" ? f.unit : amt;
            perUnit.dataset.userEdited = "1";
          }
          if (perMeals) perMeals.value = typeof f.meals !== "undefined" ? f.meals : 1;
          if (perPeople) perPeople.value = typeof f.people !== "undefined" ? f.people : 1;
          return;
        }

        if (type === "transport") {
          if (trEnabled) trEnabled.checked = true;
          makeTransportRow(parseTransportDescToData(desc, amt));
          return;
        }

        const targetIsComp = /ค่าตอบแทน|วิทยากร/.test(desc);
        makePresetBudgetRow({
          type: "other",
          container: targetIsComp ? compList : matList,
          emptyEl: targetIsComp ? compEmpty : matEmpty,
          desc,
          amount: amt
        });
      });
    }

    applyDefaultLodRate(false);
    applyDefaultMealRate(false);
    preloadBudgetItems();
    syncEmpty(compList, compEmpty);
    syncEmpty(matList, matEmpty);
    syncEmpty(trList, trEmpty);
    calcAll();

    function expenseFieldHasValue(el, defaultValue = "0") {
      if (!el) return false;
      const value = String(el.value || "").trim();
      if (value === "") return false;
      if (el.type === "number") {
        return n(value) !== n(defaultValue);
      }
      return true;
    }

    function rowHasAnyExpenseInput(row) {
      if (!row) return false;
      return [...row.querySelectorAll("input, select, textarea")].some(el => {
        if (el.type === "hidden") return false;
        if (el.classList.contains("js-tr-type")) {
          return (el.value || "") !== "fuel";
        }
        if (el.type === "number") {
          const defaultValue = el.classList.contains("js-tr-rate") ? "4" :
            (el.classList.contains("js-tr-trips") ||
              el.classList.contains("js-tr-flight-trips") ||
              el.classList.contains("js-tr-flight-people") ||
              el.classList.contains("js-tr-other-trips") ||
              el.classList.contains("js-tr-other-people")) ? "1" : "0";
          return expenseFieldHasValue(el, defaultValue);
        }
        return String(el.value || "").trim() !== "";
      });
    }

    function hasRegistrationInputWithoutTick() {
      return !regEnabled?.checked && (
        n(regPrice?.value) > 0 ||
        n(regPeople?.value || 1) !== 1
      );
    }

    function hasLodgingInputWithoutTick() {
      return !lodEnabled?.checked && (
        // ราคา/คืนมีค่าเริ่มต้นไว้ให้ตามอัตราราชการอยู่แล้ว จึงไม่ถือว่าเป็นการกรอกข้อมูล
        n(lodNights?.value || 1) !== 1 ||
        n(lodPeople?.value || 1) !== 1 ||
        String(lodSingleDate?.value || "").trim() !== "" ||
        String(lodStartDate?.value || "").trim() !== "" ||
        String(lodEndDate?.value || "").trim() !== ""
      );
    }

    function hasPerDiemInputWithoutTick() {
      return !perEnabled?.checked && (
        // ราคา/มื้อมีค่าเริ่มต้น 120 บาทอยู่แล้ว จึงไม่ถือว่าเป็นการกรอกข้อมูล
        n(perMeals?.value || 1) !== 1 ||
        n(perPeople?.value || 1) !== 1
      );
    }

    function hasTransportInputWithoutTick() {
      return !trEnabled?.checked && [...(trList?.children || [])].some(rowHasAnyExpenseInput);
    }

    function validateExpenseStep2() {
      [regEnabled, lodEnabled, perEnabled, trEnabled].forEach(clearError);

      const checks = [{
          invalid: hasRegistrationInputWithoutTick(),
          el: regEnabled,
          msg: "กรุณาติ๊กเลือกค่าลงทะเบียนก่อน หากต้องการบันทึกรายการนี้"
        },
        {
          invalid: hasLodgingInputWithoutTick(),
          el: lodEnabled,
          msg: "กรุณาติ๊กเลือกค่าที่พักค้างคืนก่อน หากต้องการบันทึกรายการนี้"
        },
        {
          invalid: hasPerDiemInputWithoutTick(),
          el: perEnabled,
          msg: "กรุณาติ๊กเลือกค่าอาหารก่อน หากต้องการบันทึกรายการนี้"
        },
        {
          invalid: hasTransportInputWithoutTick(),
          el: trEnabled,
          msg: "กรุณาติ๊กเลือกค่าพาหนะก่อน หากต้องการบันทึกรายการนี้"
        }
      ];

      const firstInvalid = checks.find(item => item.invalid);
      if (!firstInvalid) return true;

      setError(firstInvalid.el, firstInvalid.msg);
      alert(firstInvalid.msg);
      firstInvalid.el?.scrollIntoView({
        behavior: "smooth",
        block: "center"
      });
      setTimeout(() => firstInvalid.el?.focus?.(), 150);
      return false;
    }

    finalSubmitBtn?.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();

      if (finalSubmitBtn.disabled) return;

      // ถ้ากรอกข้อมูลในหมวดค่าใช้จ่าย แต่ยังไม่ได้ติ๊กเลือกหมวดนั้น ให้หยุดและแจ้งก่อน
      if (!validateExpenseStep2()) return;

      finalSubmitBtn.disabled = true;

      try {
        // sync วันที่เอกสาร
        if (docDateDisplay?.value && !docDateHidden?.value) {
          const d = docPicker.selectedDates[0];
          if (d) docDateHidden.value = docPicker.formatDate(d, "Y-m-d");
        }

        // sync วันที่จัดงาน
        if (eventOptSingle?.checked && eventSingleDate?.value?.trim()) {
          eventDate.value = eventSingleDate.value.trim();
        } else if (eventOptRange?.checked) {
          updateEventRangeDisplay();
        }

        // sync วันที่เข้าร่วม: สำคัญมาก เพราะ update_memo.php ใช้ range_date ด้วย
        if (optSingle?.checked && singleDate?.value?.trim()) {
          joinDate.value = singleDate.value.trim();
          if (rangeDisplay) rangeDisplay.value = "";
        } else if (optRange?.checked) {
          updateRangeDisplay();
          if (rangeDisplay?.value?.trim()) {
            joinDate.value = rangeDisplay.value.trim();
          }
        }

        // sync สถานที่จาก radio ให้ชัดเจนก่อนส่งจาก Step 2
        // ห้ามให้ Step 2 เด้งค่าไปออนไลน์เองถ้าเลือกออนไซต์ไว้
        if (!preparePlaceForSubmit(true)) {
          finalSubmitBtn.disabled = false;
          return;
        }

        // รวมค่าใช้จ่าย + สร้าง budget_type[] / budget_desc[] / budget_amount[]
        calcAll();
        if (typeof syncExpenseJsonForPresentation === "function") {
          syncExpenseJsonForPresentation();
        }

        if (amountInput) {
          amountInput.disabled = false;
          amountInput.value = (totalAmountHidden?.value || totalAmountEl?.value || amountInput.value || "0.00")
            .replace(/,/g, "")
            .trim();
        }

        memoForm.action = IS_EDIT_MODE ? "/Pro_letter/documents/update_memo.php" : "save_memo.php";

        // ใช้ native submit เพื่อไม่เข้า submit handler เดิมที่ validate Step 1 ซ้ำแล้วเด้งกลับ
        HTMLFormElement.prototype.submit.call(memoForm);
      } catch (err) {
        console.error(err);
        finalSubmitBtn.disabled = false;
        alert("ไม่สามารถส่งข้อมูลได้ กรุณาตรวจสอบข้อมูลอีกครั้ง");
      }
    });

    function getSpellBoxByField(el) {
      if (!el) return null;
      if (el.id === "purposeOtherInput") return purposeOtherSpellBox;
      if (el.id === "eventTitle") return eventTitleSpellBox;
      if (el.id === "memoSubject") return memoSubjectSpellBox;
      if (el.id === "academicTopic") return academicTopicSpellBox;
      if (el.id === "academicLevel") return academicLevelSpellBox;
      if (el.id === "placeOnsite") return placeOnsiteSpellBox;
      return null;
    }

    function clearSpellResult(el) {
      if (!el) return;
      el.classList.remove("spell-error", "spell-ok");

      const box = getSpellBoxByField(el);
      if (box) {
        box.innerHTML = "";
        box.classList.add("hidden");
      }
    }

    function escapeHtml(str) {
      return String(str ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    function showSpellError(el, errors = []) {
      if (!el) return;
      clearSpellResult(el);
      el.classList.add("spell-error");

      const box = getSpellBoxByField(el);
      if (!box) return;

      errors = normalizeErrors(errors, el.value || "");

      if (!Array.isArray(errors) || errors.length === 0) {
        showSpellOk(el);
        return;
      }

      let html = `<div class="spell-result-box">`;
      html += `<div class="spell-warning">พบคำแนะนำ ${errors.length} จุด</div>`;

      errors.forEach((item, index) => {
        const wrongWord = item?.wrongWord || "";
        const suggestions = Array.isArray(item?.suggestions) ? item.suggestions.slice(0, 5) : [];

        html += `<div class="mt-2">`;
        html += `<div class="spell-help-text">คำที่ ${index + 1}: <b>${escapeHtml(wrongWord)}</b></div>`;

        if (suggestions.length > 0) {
          html += `<div class="spell-suggestions">`;
          suggestions.forEach(word => {
            html += `
          <button
            type="button"
            class="spell-suggestion-btn"
            data-target="${el.id}"
            data-word="${escapeHtml(word)}"
            data-wrong-word="${escapeHtml(wrongWord)}"
          >${escapeHtml(word)}</button>
        `;
          });
          html += `</div>`;
        }
        html += `</div>`;
      });

      html += `
    <div class="spell-suggestions">
      <button type="button" class="spell-ignore-btn" data-target="${el.id}">
        ใช้ข้อความเดิม
      </button>
    </div>
  `;

      html += `</div>`;

      box.innerHTML = html;
      box.classList.remove("hidden");
    }

    function showSpellOk(el) {
      if (!el) return;
      clearSpellResult(el);
      if ((el.value || "").trim() !== "") {
        el.classList.add("spell-ok");
      }
    }

    function shouldCheckSpell(el) {
      if (!el) return false;
      if (el.disabled || el.readOnly) return false;

      if (el.id === "purposeOtherInput") {
        return !!purposeOtherRadio?.checked;
      }

      if (el.id === "placeOnsite") {
        return !!onsiteCheckbox?.checked;
      }

      return true;
    }



    function escapeRegExp(str) {
      return String(str).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    }

    function replaceWholeWordOnce(text, wrongWord, newWord) {
      if (!text || !wrongWord || !newWord) return text;

      const escaped = escapeRegExp(wrongWord);

      // พยายามแทนทั้งคำก่อน
      const wordBoundaryRegex = new RegExp(`(^|\\s|[,(\\[{"'“”‘’])(${escaped})(?=$|\\s|[),.\\]}",'“”‘’!?])`);
      if (wordBoundaryRegex.test(text)) {
        return text.replace(wordBoundaryRegex, (match, p1) => `${p1}${newWord}`);
      }

      // ถ้าไม่เจอจริง ๆ ค่อยแทนครั้งเดียวแบบตรงตัว
      const plainRegex = new RegExp(escaped);
      return text.replace(plainRegex, newWord);
    }

    function normalizeErrors(errors = [], originalText = "") {
      if (!Array.isArray(errors)) return [];

      const seen = new Set();
      const normalized = [];

      for (const item of errors) {
        const wrongWord = String(item?.wrongWord || "").trim();
        if (!wrongWord) continue;

        // เอาเฉพาะคำที่มีอยู่จริงในข้อความปัจจุบัน
        if (originalText && !originalText.includes(wrongWord)) continue;

        // ตัดคำซ้ำ
        if (seen.has(wrongWord)) continue;
        seen.add(wrongWord);

        // ตัด suggestion ที่ซ้ำกับคำเดิม
        const suggestions = Array.isArray(item?.suggestions) ?
          item.suggestions
          .map(s => String(s || "").trim())
          .filter(Boolean)
          .filter(s => s !== wrongWord)
          .filter((s, i, arr) => arr.indexOf(s) === i)
          .slice(0, 5) : [];

        normalized.push({
          wrongWord,
          suggestions
        });
      }

      return normalized;
    }

    function extractThaiWords(text = "") {
      return String(text)
        .split(/[^\u0E00-\u0E7Fa-zA-Z0-9]+/g)
        .map(w => w.trim())
        .filter(Boolean);
    }

    function rememberApprovedText(fieldName, text) {
      const cleanText = String(text || "").trim();
      if (!fieldName || !cleanText) return;

      approvedTexts[fieldName] = cleanText;

      extractThaiWords(cleanText).forEach(word => {
        approvedWords.add(word);
      });
    }

    function isApprovedText(fieldName, text) {
      const cleanText = String(text || "").trim();
      return !!(fieldName && cleanText && approvedTexts[fieldName] === cleanText);
    }

    function filterApprovedErrors(errors = []) {
      return errors.filter(item => {
        const wrongWord = String(item?.wrongWord || "").trim();
        if (!wrongWord) return false;
        return !approvedWords.has(wrongWord);
      });
    }

    function setSpellPassed(el, fieldName, text, remember = false) {
      if (remember) {
        rememberApprovedText(fieldName, text);
      }

      spellState[fieldName] = {
        checked: true,
        hasError: false,
        ignored: remember,
        errors: [],
        lastText: text
      };

      clearSpellResult(el);
      if ((text || "").trim() !== "") {
        el.classList.add("spell-ok");
      }
    }

    const spellCache = {};
    const approvedWords = new Set();
    const approvedTexts = {};
    const correctedTexts = {};

    
    const SPELL_TIMEOUT_MS = 60000;
    const SPELL_CHUNK_LIMIT = 350;

    function splitTextForSpellCheck(text, limit = SPELL_CHUNK_LIMIT) {
      const clean = String(text || "").trim();
      if (!clean) return [];
      if (clean.length <= limit) return [clean];

      const parts = clean
        .split(/(\n+|[.!?！？。]|[;；]|[,，])/)
        .reduce((acc, part) => {
          if (!part) return acc;
          const last = acc[acc.length - 1] || "";
          if (/^(\n+|[.!?！？。]|[;；]|[,，])$/.test(part) && last) {
            acc[acc.length - 1] = last + part;
          } else {
            acc.push(part.trim());
          }
          return acc;
        }, [])
        .map(s => s.trim())
        .filter(Boolean);

      const chunks = [];
      let current = "";

      function pushLongSegment(segment) {
        let start = 0;
        while (start < segment.length) {
          chunks.push(segment.slice(start, start + limit));
          start += limit;
        }
      }

      for (const part of parts.length ? parts : [clean]) {
        if (part.length > limit) {
          if (current) {
            chunks.push(current);
            current = "";
          }
          pushLongSegment(part);
          continue;
        }

        const next = current ? `${current}\n${part}` : part;
        if (next.length <= limit) {
          current = next;
        } else {
          if (current) chunks.push(current);
          current = part;
        }
      }

      if (current) chunks.push(current);
      return chunks;
    }

    function normalizeSpellErrorsForCurrentText(errors, text) {
      let normalized = (typeof normalizeErrors === "function")
        ? normalizeErrors(errors || [], text)
        : (Array.isArray(errors) ? errors : []);

      if (typeof filterApprovedErrors === "function") {
        normalized = filterApprovedErrors(normalized);
      }

      return Array.isArray(normalized) ? normalized : [];
    }

    function markSpellPassedUnified(el, fieldName, text) {
      if (typeof setSpellPassed === "function") {
        setSpellPassed(el, fieldName, text, false);
        return;
      }

      spellState[fieldName] = {
        checked: true,
        hasError: false,
        ignored: false,
        suggestions: [],
        errors: [],
        lastText: text
      };

      if (typeof clearSpellResult === "function") clearSpellResult(el);
      if (typeof showSpellOk === "function") showSpellOk(el);
    }

    function markSpellErrorUnified(el, fieldName, text, errors) {
      spellState[fieldName] = {
        checked: true,
        hasError: true,
        ignored: false,
        suggestions: [],
        errors,
        lastText: text
      };

      if (typeof showSpellError === "function") {
        showSpellError(el, errors);
      }
    }

    async function fetchSpellChunk(fieldName, chunkText) {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), SPELL_TIMEOUT_MS);

      try {
        const response = await fetch("http://127.0.0.1:8001/api/spell-check", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            field: fieldName,
            text: chunkText
          }),
          signal: controller.signal
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        return await response.json();
      } catch (error) {
        clearTimeout(timeoutId);

        // ข้อความยาวบางครั้ง request เก่าจะโดน abort ระหว่างพิมพ์/เปลี่ยนช่อง
        // ไม่ถือว่า API ล่ม และไม่บล็อกการดำเนินการ
        if (error && error.name === "AbortError") {
          return {
            aborted: true,
            hasError: false,
            errors: []
          };
        }

        throw error;
      }
    }

async function checkSpellField(el) {
      if (!el) return true;

      if (typeof clearSpellResult === "function") {
        clearSpellResult(el);
      }

      if (typeof shouldCheckSpell === "function" && !shouldCheckSpell(el)) {
        return true;
      }

      const text = (el.value || "").trim();
      if (!text) {
        return true;
      }

      const fieldName = el.dataset.spellField || "";
      if (!fieldName) {
        return true;
      }

      const cacheKey = `${fieldName}::${text}`;

      const alreadyApproved =
        (typeof isApprovedText === "function" && isApprovedText(fieldName, text)) ||
        (typeof isIgnoredForSameText === "function" && isIgnoredForSameText(fieldName, text)) ||
        (typeof correctedTexts !== "undefined" && correctedTexts[fieldName] === text);

      if (alreadyApproved) {
        markSpellPassedUnified(el, fieldName, text);
        return true;
      }

      if (typeof spellCache !== "undefined" && spellCache[cacheKey]) {
        const cached = spellCache[cacheKey];
        const cachedErrors = normalizeSpellErrorsForCurrentText(cached.errors || [], text);

        if (cached.hasError && cachedErrors.length > 0) {
          markSpellErrorUnified(el, fieldName, text, cachedErrors);
          return false;
        }

        markSpellPassedUnified(el, fieldName, text);
        return true;
      }

      el.classList.add("opacity-50");
      if (typeof showSpellLoading === "function") {
        showSpellLoading(el);
      }

      try {
        const chunks = splitTextForSpellCheck(text);
        let allErrors = [];
        let wasAborted = false;

        for (const chunk of chunks) {
          const result = await fetchSpellChunk(fieldName, chunk);

          if (result.aborted) {
            wasAborted = true;
            continue;
          }

          const chunkErrors = normalizeSpellErrorsForCurrentText(result.errors || [], chunk);
          if (result.hasError && chunkErrors.length > 0) {
            allErrors = allErrors.concat(chunkErrors);
          }
        }

        allErrors = normalizeSpellErrorsForCurrentText(allErrors, text);

        const finalResult = {
          hasError: allErrors.length > 0,
          errors: allErrors,
          aborted: wasAborted
        };

        if (typeof spellCache !== "undefined" && !wasAborted) {
          spellCache[cacheKey] = finalResult;
        }

        if (allErrors.length > 0) {
          markSpellErrorUnified(el, fieldName, text, allErrors);
          return false;
        }

        // ถ้า request บางส่วนถูก abort ให้ถือว่าไม่พบ error ณ ตอนนี้
        // เพื่อไม่ให้ขึ้นแจ้ง API ล่มหรือบล็อกผู้ใช้
        markSpellPassedUnified(el, fieldName, text);
        return true;

      } catch (error) {
        console.error("Spell check API error:", error);

        // ถ้า API ล่มจริง ให้ไม่บล็อกการทำงาน แต่ไม่แสดงกล่องแดง/ส้มค้าง
        spellState[fieldName] = {
          checked: false,
          hasError: false,
          ignored: false,
          suggestions: [],
          errors: [],
          lastText: ""
        };

        if (typeof clearSpellResult === "function") {
          clearSpellResult(el);
        }

        return true;
      } finally {
        el.classList.remove("opacity-50");
        if (typeof hideSpellLoading === "function") {
          hideSpellLoading(el);
        }
      }
    }


  });
  </script>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const main = document.getElementById("mainCategory");
    const sub = document.getElementById("subCategory");
    if (!main || !sub) return;

    const SUB_OPTIONS = {
      external: [
        "ฝึกอบรม",
        "ขออนุมัติตัวบุคคลไปนำเสนอผลงานวิจัย",
        "ขออนุมัติตัวบุคคลเป็นวิทยากร",
        "ขอห้องพักรับรอง",
        "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ",
      ],
      internal: [
        "หนังสือเรียนเชิญวิทยากร",
        "หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์",
        "ขอเข้าเยี่ยมศึกษาดูงาน",
        "ขอเข้าไปจัดกิจกรรมโครงการ",
        "ขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ)",
      ],
    };

    // ลิงก์เฉพาะไฟล์คำถาม / หน้ากรอกข้อมูลเท่านั้น
    // ไฟล์ใน form_Memo ที่เป็นหน้าเจนเอกสาร ไม่ใส่ใน ROUTE_SUB นี้
    const ROUTE_SUB = {
      "ฝึกอบรม": "/Pro_letter/documents/form_Memo.php",
      "ขออนุมัติตัวบุคคลไปนำเสนอผลงานวิจัย": "/Pro_letter/documents/infor_academic_presentation.php",
      "ขออนุมัติตัวบุคคลเป็นวิทยากร": "/Pro_letter/documents/infor_speaker_workshop.php",
      "ขอห้องพักรับรอง": "/Pro_letter/documents/infor_room_request.php",
      "หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ": "/Pro_letter/documents/infor_present.php",

      "หนังสือเรียนเชิญวิทยากร": "/Pro_letter/documents/infor_invite.php",
      "หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์": "/Pro_letter/documents/infor_research_data.php",
      "ขอเข้าเยี่ยมศึกษาดูงาน": "/Pro_letter/documents/infor_study_visit.php",
      "ขอเข้าไปจัดกิจกรรมโครงการ": "/Pro_letter/documents/infor_project_activity.php",
      "ขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ)": "/Pro_letter/documents/infor_coop_evaluation.php",
    };

    function withSelection(url, mainVal, subVal = "") {
      if (!url || url === "#") return "#";

      const targetUrl = new URL(url, window.location.origin);
      if (mainVal) targetUrl.searchParams.set("main", mainVal);
      if (subVal) targetUrl.searchParams.set("sub", subVal);

      return targetUrl.toString();
    }

    function renderSubOptions(list, selectedValue = "") {
      sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';
      list.forEach(text => {
        const opt = document.createElement("option");
        opt.value = text;
        opt.textContent = text;
        if (text.trim() === String(selectedValue || "").trim()) opt.selected = true;
        sub.appendChild(opt);
      });
    }

    function syncUI() {
      const mainVal = (main.value || "").trim();
      const currentSub = (sub.dataset.current || "").trim();
      if (mainVal === "external" || mainVal === "internal") {
        sub.disabled = false;
        renderSubOptions(SUB_OPTIONS[mainVal] || [], currentSub);
      } else {
        sub.disabled = true;
        sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';
      }
    }

    function goMain() {
      const mainVal = (main.value || "").trim();
      const firstSub = (SUB_OPTIONS[mainVal] || [""])[0] || "";
      const target = ROUTE_SUB[firstSub];

      if (!target || target === "#") return;
      window.location.href = withSelection(target, mainVal, firstSub);
    }

    function goSub() {
      const mainVal = (main.value || "").trim();
      const subVal = (sub.value || "").trim();
      sub.dataset.current = subVal;

      const target = ROUTE_SUB[subVal];
      if (!target || target === "#") return;

      window.location.href = withSelection(target, mainVal, subVal);
    }
    main.addEventListener("change", () => {
      sub.dataset.current = "";
      syncUI();
      sub.value = "";
    });
    sub.addEventListener("change", goSub);
    syncUI();



  });
  </script>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("profileBtn");
    const menu = document.getElementById("profileMenu");
    if (!btn || !menu) return;

    function openMenu() {
      menu.classList.remove("hidden");
    }

    function closeMenu() {
      menu.classList.add("hidden");
    }
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      menu.classList.toggle("hidden");
    });
    document.addEventListener("click", () => closeMenu());
    menu.addEventListener("click", (e) => e.stopPropagation());
    window.closeMenu = closeMenu;
  });
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const facultySelect = document.getElementById("faculty");
    const departmentSelect = document.getElementById("dept");
    const departmentIdInput = document.getElementById("selectedDepartmentId");

    if (!facultySelect || !departmentSelect || !departmentIdInput) return;

    const DEPARTMENT_OPTIONS =
      <?= json_encode($departmentOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function getSelectedFacultyId() {
      const selectedOption = facultySelect.options[facultySelect.selectedIndex];
      return String(selectedOption?.dataset?.facultyId || "");
    }

    function renderDepartmentOptions(keepCurrent = true) {
      const facultyId = getSelectedFacultyId();
      const currentDepartmentId = keepCurrent ?
        String(departmentSelect.dataset.currentDepartmentId || departmentIdInput.value || "") :
        "";

      departmentSelect.innerHTML = "";

      const filteredDepartments = DEPARTMENT_OPTIONS.filter(item => {
        return String(item.faculty_id || "") === facultyId;
      });

      filteredDepartments.forEach(item => {
        const opt = document.createElement("option");
        opt.value = item.department_name || "";
        opt.textContent = item.department_name || "";
        opt.dataset.departmentId = item.department_id || "";
        opt.dataset.facultyId = item.faculty_id || "";

        if (currentDepartmentId && String(item.department_id || "") === currentDepartmentId) {
          opt.selected = true;
        }

        departmentSelect.appendChild(opt);
      });

      // ถ้าเปลี่ยนคณะแล้วภาควิชาเดิมไม่อยู่ในคณะนั้น ให้เลือกตัวแรกของคณะใหม่อัตโนมัติ
      if (departmentSelect.options.length > 0 && departmentSelect.selectedIndex < 0) {
        departmentSelect.selectedIndex = 0;
      }

      syncSelectedDepartmentId();
    }

    function syncSelectedDepartmentId() {
      const selectedOption = departmentSelect.options[departmentSelect.selectedIndex];

      if (!selectedOption) {
        departmentIdInput.value = "";
        departmentSelect.dataset.currentDepartmentId = "";
        return;
      }

      const selectedDepartmentId = selectedOption.dataset.departmentId || "";
      departmentIdInput.value = selectedDepartmentId;
      departmentSelect.dataset.currentDepartmentId = selectedDepartmentId;
    }

    facultySelect.addEventListener("change", () => {
      renderDepartmentOptions(false);
    });

    departmentSelect.addEventListener("change", syncSelectedDepartmentId);

    // โหลดหน้า: แสดงค่าของ user ก่อน แต่ยังเปลี่ยนเลือกคณะ/ภาควิชาอื่นได้
    renderDepartmentOptions(true);
  });
  </script>

</body>

</html>