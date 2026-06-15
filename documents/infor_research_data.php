<?php 
// ต้องวางตรงนี้! บรรทัดแรกของไฟล์
$CURRENT_MAIN = $_GET['main'] ?? 'internal';
$CURRENT_SUB  = $_GET['sub']  ?? 'หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์';

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'internal';
}
?>
<!--หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์ (ของนักศึกษา) /Pro_letter/documents/infor_research_data.php-->
<?php
session_start();
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/require_profile_completed.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}



/* ===== Edit permission + category lock guard (role/session based) ===== */
$userId = (int)($_SESSION['user_id'] ?? 0);
$roleIdForEditGuard = (int)($_SESSION['role_id'] ?? 0);
$isAdminOrOfficerForEditGuard = in_array($roleIdForEditGuard, [1, 2], true);

if (!isset($homePath)) {
    if ($roleIdForEditGuard === 1) {
        $homePath = '/Pro_letter/admin/home.php';
    } elseif ($roleIdForEditGuard === 2) {
        $homePath = '/Pro_letter/officer/home.php';
    } else {
        $homePath = '/Pro_letter/user/home.php';
    }
}

$editGuardDocId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['document_id'] ?? 0);
$isEditModeForGuard = ($editGuardDocId > 0 && ((($_GET['edit'] ?? '') === '1') || isset($_GET['id']) || isset($_POST['document_id'])));
$editGuardDocStatus = '';
$categoryLocked = false;

if ($isEditModeForGuard) {
    try {
        $editGuardPdo = db();
        $editGuardStmt = $editGuardPdo->prepare("SELECT document_id, owner_id, status FROM documents WHERE document_id = :id LIMIT 1");
        $editGuardStmt->execute([':id' => $editGuardDocId]);
        $editGuardDoc = $editGuardStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (!$editGuardDoc) {
            header('Location: ' . $homePath . '?err=notfound');
            exit;
        }

        $editGuardOwnerId = (int)($editGuardDoc['owner_id'] ?? 0);
        $editGuardDocStatus = trim((string)($editGuardDoc['status'] ?? ''));

        $hasAnyExplicitPermissionForEditGuard = false;
        $hasDocumentEditPermissionForEditGuard = false;

        try {
            $permAnyStmt = $editGuardPdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = :uid");
            $permAnyStmt->execute([':uid' => $userId]);
            $hasAnyExplicitPermissionForEditGuard = ((int)$permAnyStmt->fetchColumn() > 0);

            $permEditStmt = $editGuardPdo->prepare("
                SELECT COUNT(*)
                FROM user_permissions up
                JOIN permissions p ON p.perm_id = up.perm_id
                WHERE up.user_id = :uid
                  AND p.perm_code = 'document.edit'
            ");
            $permEditStmt->execute([':uid' => $userId]);
            $hasDocumentEditPermissionForEditGuard = ((int)$permEditStmt->fetchColumn() > 0);
        } catch (Throwable $permError) {
            $hasAnyExplicitPermissionForEditGuard = false;
            $hasDocumentEditPermissionForEditGuard = false;
        }

       $blockedEditStatusesForEditGuard = [
          'รอตรวจสอบ',
          'รอการตรวจสอบ',
          'รอตรวจ',
          'ผ่านการตรวจสอบ',
          'ผ่านการตรวจสอบแล้ว',
          'ได้รับการตรวจสอบ',
          'ได้รับการตรวจสอบแล้ว',
          'ตรวจสอบแล้ว',
          'approved',
          'checked',
          'reviewed'
      ];

      $isBlockedEditStatusForEditGuard = in_array($editGuardDocStatus, $blockedEditStatusesForEditGuard, true);

      $canEditThisFormForEditGuard = !$isBlockedEditStatusForEditGuard && (
          $isAdminOrOfficerForEditGuard
          || $editGuardOwnerId === $userId
          || $hasDocumentEditPermissionForEditGuard
      );

      if (!$canEditThisFormForEditGuard) {
          header('Location: /Pro_letter/documents/view_memo.php?id=' . $editGuardDocId . '&err=no_permission');
          exit;
      }
        $categoryLockedStatusesForEditGuard = [
            'draft', 'submitted', 'reviewing', 'pending', 'pending_review',
            'เค้าโครง', 'รอยืนยันการส่ง', 'ส่งแล้ว', 'รอตรวจ', 'รอตรวจสอบ',
            'รอการตรวจสอบ', 'รอแก้ไข', 'รอแก้เอกสาร', 'rejected'
        ];
        $categoryLocked = in_array($editGuardDocStatus, $categoryLockedStatusesForEditGuard, true);
    } catch (Throwable $editGuardError) {
        header('Location: ' . $homePath . '?err=server');
        exit;
    }
}
/* ===== End edit permission + category lock guard ===== */
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


/* ===== โหลดรายการเทมเพลตสำหรับ dropdown หมวดหลัก/หมวดย่อย =====
   ใช้เฉพาะ templates.is_active = 1 เท่านั้น
   เพื่อให้หน้า admin เปิด/ปิดเทมเพลตแล้ว dropdown แสดงผลตามฐานข้อมูลจริง
*/
$templateDropdownOptions = [
    'internal' => [],
    'external' => []
];

// template_id ของฟอร์มนี้ ต้องใช้ร่วมกันทั้ง User / Officer
// ห้าม hardcode เป็น 1 เพราะในฐานข้อมูล template_id=1 เป็นเทมเพลตอื่น
$researchTemplateId = 0;

try {
    $templatePdo = db();
    $templateStmt = $templatePdo->query("
        SELECT
            template_id,
            template_code,
            template_name,
            question_path,
            template_group,
            is_active,
            sort_order
        FROM templates
        WHERE is_active = 1
          AND question_path IS NOT NULL
          AND question_path <> ''
          AND template_group IN ('internal', 'external')
        ORDER BY
            CASE
              WHEN template_group = 'internal' THEN 1
              WHEN template_group = 'external' THEN 2
              ELSE 3
            END,
            sort_order ASC,
            template_id ASC
    ");

    $templateRows = $templateStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($templateRows as $tpl) {
        $group = strtolower(trim((string)($tpl['template_group'] ?? '')));
        $name  = trim((string)($tpl['template_name'] ?? ''));
        $url   = trim((string)($tpl['question_path'] ?? ''));

        if (!in_array($group, ['internal', 'external'], true)) {
            continue;
        }

        if ($name === '' || $url === '') {
            continue;
        }

        $templateDropdownOptions[$group][] = [
            'id' => (int)($tpl['template_id'] ?? 0),
            'code' => (string)($tpl['template_code'] ?? ''),
            'name' => $name,
            'url' => $url,
            'group' => $group,
            'is_active' => 1
        ];

        if (basename(parse_url($url, PHP_URL_PATH) ?: $url) === 'infor_research_data.php') {
            $researchTemplateId = (int)($tpl['template_id'] ?? 0);
            $CURRENT_MAIN = $group;
            $CURRENT_SUB = $name;
        }
    }
} catch (Throwable $e) {
    $templateDropdownOptions = [
        'internal' => [],
        'external' => []
    ];
    $CURRENT_SUB = "";
}

// กันพลาดกรณี dropdown query ไม่พบ แต่ตาราง templates มี RESEARCH_DATA อยู่
if ($researchTemplateId <= 0) {
    try {
        $researchTplStmt = db()->prepare("
            SELECT template_id
            FROM templates
            WHERE template_code = 'RESEARCH_DATA'
               OR question_path LIKE '%infor_research_data.php%'
               OR document_path LIKE '%form_memo_request_research_data.php%'
            ORDER BY
                CASE WHEN template_code = 'RESEARCH_DATA' THEN 0 ELSE 1 END,
                template_id ASC
            LIMIT 1
        ");
        $researchTplStmt->execute();
        $researchTemplateId = (int)($researchTplStmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $researchTemplateId = 0;
    }
}


// ✅ โหมดแก้ไขเอกสาร: รับ id จาก URL แล้วดึงข้อมูลเดิมมาแสดงในฟอร์ม
$editDocId  = (int)($_GET['id'] ?? $_POST['document_id'] ?? 0);
$isEditMode = $editDocId > 0;

$editDoc = [];
$editValuesByKey = [];
$editValuesByFieldId = [];
$editStudents = [];

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if ($isEditMode) {
    try {
        $pdo = db();

        $docStmt = $pdo->prepare("SELECT * FROM documents WHERE document_id = :id LIMIT 1");
        $docStmt->execute([':id' => $editDocId]);
        $editDoc = $docStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (!$editDoc) {
            header('Location: /Pro_letter/user/home.php?err=notfound');
            exit;
        }

        $valStmt = $pdo->prepare("\n            SELECT dv.field_id, dv.value_text, tf.field_key\n            FROM document_values dv\n            LEFT JOIN template_fields tf ON tf.field_id = dv.field_id\n            WHERE dv.document_id = :id\n        ");
        $valStmt->execute([':id' => $editDocId]);

        while ($row = $valStmt->fetch(PDO::FETCH_ASSOC)) {
            $fieldId = (int)($row['field_id'] ?? 0);
            $fieldKey = (string)($row['field_key'] ?? '');
            $valueText = (string)($row['value_text'] ?? '');

            if ($fieldId > 0) {
                $editValuesByFieldId[$fieldId] = $valueText;
            }
            if ($fieldKey !== '') {
                $editValuesByKey[$fieldKey] = $valueText;
            }
        }

        $studentsJson = trim($editValuesByKey['research_students_json'] ?? '');
        if ($studentsJson !== '') {
            $decodedStudents = json_decode($studentsJson, true);
            if (is_array($decodedStudents)) {
                foreach ($decodedStudents as $student) {
                    $editStudents[] = [
                        'name' => (string)($student['name'] ?? ''),
                        'id' => (string)($student['student_id'] ?? ($student['id'] ?? '')),
                        'phone' => (string)($student['phone'] ?? ''),
                        'checked' => !empty($student['is_contact']),
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        header('Location: /Pro_letter/user/home.php?err=loadfail');
        exit;
    }
}

function edit_value(string $key, int $fieldId = 0, string $default = ''): string {
    global $editValuesByKey, $editValuesByFieldId;

    if ($key !== '' && array_key_exists($key, $editValuesByKey)) {
        return (string)$editValuesByKey[$key];
    }
    if ($fieldId > 0 && array_key_exists($fieldId, $editValuesByFieldId)) {
        return (string)$editValuesByFieldId[$fieldId];
    }
    return $default;
}

$editSubject = $isEditMode
    ? (string)($editDoc['subject'] ?? edit_value('research_subject', 14))
    : '';
$editDocDate = $isEditMode
    ? (string)($editDoc['doc_date'] ?? edit_value('doc_date', 1, date('Y-m-d')))
    : date('Y-m-d');
$editStudentsJsonForJs = json_encode($editStudents, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>แบบฟอร์มบันทึกข้อความ</title>

  <!-- ✅ เพิ่มส่วนนี้ -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css" />
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
  <!-- ✅ จบส่วนที่เพิ่ม -->

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

  /* error styles */
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

  <script>
  window.CATEGORY_LOCKED_BY_STATUS = <?= $categoryLocked ? 'true' : 'false' ?>;
  </script>
</head>

<body class="bg-gray-100">
  <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>

  <form method="post"
    action="<?= $isEditMode ? '/Pro_letter/documents/update_memo.php' : '/Pro_letter/documents/save_memo.php' ?>"
    id="memoForm">
    <?php if ($isEditMode): ?>
    <input type="hidden" name="document_id" value="<?= (int)$editDocId ?>">
    <input type="hidden" name="edit" value="1">
    <?php endif; ?>
    <input type="hidden" name="purpose" value="research_data">
    <input type="hidden" name="form_type" value="research_data">
    <input type="hidden" name="document_type" value="infor_research_data">
    <input type="hidden" name="target_form" value="infor_research_data.php">
    <input type="hidden" name="redirect_to" value="form_memo_request_research_data.php">
    <input type="hidden" name="template_id" value="<?= (int)$researchTemplateId ?>">
    <input type="hidden" name="document_type_name" value="หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์">
    <input type="hidden" name="department_id" id="selectedDepartmentId" value="<?= (int)$currentUserDepartmentId ?>">
    <input type="hidden" name="doc_date" value="<?= h($editDocDate) ?>">

    <!-- กล่องเนื้อหา -->
    <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
      <h1 class="text-center font-bold mb-6 text-black">
        แบบฟอร์มหนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์
      </h1>

      <!-- หมวดหมู่ -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-6 rounded-[25px] border-2" style="
            background-color: #e3f9f8;
            border-color: #11c2b9;
            min-height: 170px;
          ">
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 w-28 text-right">หมวดหลัก:</label>
          <div class="relative w-full">
            <select name="main_category" class="custom-select w-full" id="mainCategory"
              <?= $categoryLocked ? ' disabled data-category-locked="1"' : '' ?>>
              <option value="">-- เลือกหมวดหลัก --</option>
              <option value="internal" <?= ($CURRENT_MAIN=="internal"?"selected":"") ?>>ภายใน</option>
              <option value="external" <?= ($CURRENT_MAIN=="external"?"selected":"") ?>>ภายนอก</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 w-28 text-right">หมวดย่อย:</label>
          <div class="relative w-full">
            <select name="sub_category" class="custom-select w-full" id="subCategory"
              <?= $categoryLocked ? ' data-category-locked="1"' : '' ?> data-current="<?= h($CURRENT_SUB ?? '') ?>"
              disabled>
              <option value="">-- เลือกหมวดย่อย --</option>
            </select>
            <?php if ($categoryLocked): ?>
            <input type="hidden" name="main_category" value="<?= h($CURRENT_MAIN ?? '') ?>">
            <input type="hidden" name="sub_category" value="<?= h($CURRENT_SUB ?? '') ?>">
            <input type="hidden" name="main_category_locked_value" value="1">
            <?php endif; ?>
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

      <!-- 1. เรื่อง -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">1. เรื่อง :</label>
        <div class="flex-1">
          <input type="text" name="subject" id="subjectInput" data-spell-field="research_subject"
            class="w-full border rounded-md p-2" value="<?= h($editSubject) ?>"
            placeholder="เช่น ขอความอนุเคราะห์ข้อมูลเพื่อจัดทำปริญญานิพนธ์">
          <div id="subjectInputSpellBox" class="spell-box hidden"></div>
          <div id="subjectInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. เรียนถึง -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">2. เรียนถึง :</label>
        <div class="flex-1">
          <input type="text" name="to_person" id="toPerson" data-spell-field="research_to_person"
            class="w-full border rounded-md p-2" value="<?= h(edit_value('research_to_person', 26)) ?>"
            placeholder="เช่น ผู้อำนวยการโรงพยาบาล...">
          <div id="toPersonSpellBox" class="spell-box hidden"></div>
          <div id="toPersonSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. ภาคเรียน / ปีการศึกษา -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">3. ภาคเรียน / ปีการศึกษา :</label>
        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <select name="semester" id="semesterInput" class="w-full border rounded-md p-2">
              <option value="">-- เลือกภาคเรียน --</option>
              <option value="1" <?= edit_value('research_semester') === '1' ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
              <option value="2" <?= edit_value('research_semester') === '2' ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
              <option value="summer" <?= edit_value('research_semester') === 'summer' ? 'selected' : '' ?>>ภาคฤดูร้อน
              </option>
            </select>
          </div>
          <div>
            <input type="text" name="academic_year" id="academicYearInput" class="w-full border rounded-md p-2"
              value="<?= h(edit_value('research_academic_year')) ?>" placeholder="เช่น 2568" inputmode="numeric"
              maxlength="4" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4)">
          </div>
        </div>
      </div>

      <!-- 4. รายวิชา -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">4. รายวิชา :</label>
        <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
          <input type="text" name="course_code" id="courseCodeInput" class="w-full border rounded-md p-2"
            value="<?= h(edit_value('research_course_code')) ?>" placeholder="รหัสวิชา เช่น 060243202">
          <div class="md:col-span-2">
            <input type="text" name="course_name" id="courseNameInput" data-spell-field="research_course_name"
              class="w-full border rounded-md p-2" value="<?= h(edit_value('research_course_name')) ?>"
              placeholder="ชื่อรายวิชา เช่น โครงงานเทคโนโลยีสารสนเทศ 1">
            <div id="courseNameInputSpellBox" class="spell-box hidden"></div>
            <div id="courseNameInputSpellLoading" class="spell-loading hidden">
              <div class="spell-loading-row">
                <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. หลักสูตร / สาขาวิชา -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">5. หลักสูตร / สาขาวิชา :</label>
        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <input type="text" name="curriculum_name" id="curriculumNameInput"
              data-spell-field="research_curriculum_name" class="w-full border rounded-md p-2"
              value="<?= h(edit_value('research_curriculum_name')) ?>" placeholder="เช่น วิทยาศาสตรบัณฑิต">
            <div id="curriculumNameInputSpellBox" class="spell-box hidden"></div>
            <div id="curriculumNameInputSpellLoading" class="spell-loading hidden">
              <div class="spell-loading-row">
                <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
              </div>
            </div>
          </div>

          <div>
            <input type="text" name="major_name" id="majorNameInput" data-spell-field="research_major_name"
              class="w-full border rounded-md p-2" value="<?= h(edit_value('research_major_name')) ?>"
              placeholder="เช่น เทคโนโลยีสารสนเทศ">
            <div id="majorNameInputSpellBox" class="spell-box hidden"></div>
            <div id="majorNameInputSpellLoading" class="spell-loading hidden">
              <div class="spell-loading-row">
                <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 6. ชั้นปีนักศึกษา -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">6. ชั้นปีนักศึกษา :</label>
        <div class="flex-1">
          <input type="text" name="student_year" id="studentYearInput" class="w-full border rounded-md p-2"
            value="<?= h(edit_value('research_student_year')) ?>" placeholder="เช่น 4" inputmode="numeric" maxlength="1"
            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 1)">
        </div>
      </div>

      <!-- 7. ชื่อเรื่องปริญญานิพนธ์ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">7. ชื่อเรื่องปริญญานิพนธ์ :</label>
        <div class="flex-1">
          <textarea name="thesis_title" id="thesisTitle" data-spell-field="research_thesis_title" rows="2"
            class="w-full border rounded-md p-2"
            placeholder="ระบุชื่อเรื่องปริญญานิพนธ์"><?= h(edit_value('research_thesis_title')) ?></textarea>
          <div id="thesisTitleSpellBox" class="spell-box hidden"></div>
          <div id="thesisTitleSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 8. อาจารย์ที่ปรึกษา -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">8. อาจารย์ที่ปรึกษา :</label>
        <div class="flex-1">
          <input type="text" name="advisor_name" id="advisorNameInput" class="w-full border rounded-md p-2"
            value="<?= h(edit_value('research_advisor_name')) ?>" placeholder="เช่น ผู้ช่วยศาสตราจารย์ ดร. ...">
        </div>
      </div>

      <!-- 9. วัตถุประสงค์ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-56 pt-2">9. วัตถุประสงค์ :</label>
        <div class="flex-1">
          <textarea name="project_detail" id="projectDetail" data-spell-field="research_project_detail" rows="3"
            class="w-full border rounded-md p-2 shadow-sm"
            placeholder="ระบุวัตถุประสงค์ของการขอข้อมูลเพื่อจัดทำปริญญานิพนธ์"><?= h(edit_value('research_project_detail')) ?></textarea>
          <div id="projectDetailSpellBox" class="spell-box hidden"></div>
          <div id="projectDetailSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 10. ประเภทข้อมูลที่ขอ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-56 pt-2">10. ประเภทข้อมูลที่ขอ :</label>
        <div class="flex-1 space-y-1 mt-2" id="presentationType">
          <label class="flex items-center gap-2">
            <input type="radio" name="support_type" value="ข้อมูลรูปภาพ" class="accent-black"
              <?= edit_value('research_support_type') === 'ข้อมูลรูปภาพ' ? 'checked' : '' ?>>
            ข้อมูลรูปภาพ
          </label>
          <label class="flex items-center gap-2">
            <input type="radio" name="support_type" value="ข้อมูลเอกสาร / ข้อความ" class="accent-black"
              <?= edit_value('research_support_type') === 'ข้อมูลเอกสาร / ข้อความ' ? 'checked' : '' ?>>
            ข้อมูลเอกสาร / ข้อความ
          </label>
          <label class="flex items-center gap-2">
            <input type="radio" name="support_type" value="ข้อมูลเชิงฐานข้อมูล" class="accent-black"
              <?= edit_value('research_support_type') === 'ข้อมูลเชิงฐานข้อมูล' ? 'checked' : '' ?>>
            ข้อมูลเชิงฐานข้อมูล
          </label>
        </div>
      </div>

      <!-- 11. รายละเอียดข้อมูลที่ขอ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">11. รายละเอียดข้อมูลที่ขอ :</label>
        <div class="flex-1">
          <textarea name="data_detail" id="dataDetail" data-spell-field="research_data_detail" rows="3"
            class="w-full border rounded-md p-2"
            placeholder="เช่น ภาพ X-ray, ข้อมูลผู้โดยสาร, ข้อมูลสถิติ ฯลฯ"><?= h(edit_value('research_data_detail')) ?></textarea>
          <div id="dataDetailSpellBox" class="spell-box hidden"></div>
          <div id="dataDetailSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 12. จำนวนข้อมูลที่ต้องการ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">12. จำนวนข้อมูลที่ต้องการ :</label>
        <div class="flex-1">
          <input type="text" name="data_amount" id="dataAmount" data-spell-field="research_data_amount"
            class="w-full border rounded-md p-2" value="<?= h(edit_value('research_data_amount')) ?>"
            placeholder="เช่น 500 ภาพ / 3 ชุดข้อมูล">
          <div id="dataAmountSpellBox" class="spell-box hidden"></div>
          <div id="dataAmountSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 13. รายชื่อนักศึกษาและผู้ติดต่อ -->
      <div class="mb-6 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">13. รายชื่อนักศึกษา :</label>
        <div class="flex-1">
          <div id="studentList" class="space-y-4"></div>

          <button type="button" id="addStudentBtn"
            class="mt-3 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold px-4 py-2 rounded-md border">
            + เพิ่มนักศึกษา
          </button>

          <p class="text-sm text-gray-500 mt-2">
            เลือกนักศึกษา 1 คนเป็นผู้ติดต่อ ระบบจะแสดงช่องเบอร์โทรศัพท์เฉพาะคนที่ถูกเลือก
          </p>
        </div>
      </div>


      <!-- ปุ่ม -->
      <div class="relative mt-20 h-[45px]">
        <div class="absolute right-0 bottom-0">
          <button type="submit" id="submitBtn"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[150px] h-[35px] rounded-md flex items-center justify-center transition">
            ดำเนินการ
          </button>
        </div>
      </div>
    </div>
  </form>

  <script>
  const byId = (id) => document.getElementById(id);

  const form = byId("memoForm");

  const subjectInput = byId("subjectInput");
  const toPerson = byId("toPerson");
  const semesterInput = byId("semesterInput");
  const academicYearInput = byId("academicYearInput");
  const courseCodeInput = byId("courseCodeInput");
  const courseNameInput = byId("courseNameInput");
  const curriculumNameInput = byId("curriculumNameInput");
  const majorNameInput = byId("majorNameInput");
  const studentYearInput = byId("studentYearInput");
  const thesisTitle = byId("thesisTitle");
  const advisorNameInput = byId("advisorNameInput");
  const projectDetail = byId("projectDetail");
  const dataDetail = byId("dataDetail");
  const dataAmount = byId("dataAmount");
  const studentList = byId("studentList");
  const addStudentBtn = byId("addStudentBtn");

  const spellFields = [
    subjectInput,
    toPerson,
    courseNameInput,
    curriculumNameInput,
    majorNameInput,
    thesisTitle,
    projectDetail,
    dataDetail,
    dataAmount
  ];

  const spellState = {};
  spellFields.forEach(el => {
    if (!el?.dataset?.spellField) return;
    spellState[el.dataset.spellField] = {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    };
  });

  const spellCache = {};
  const approvedWords = new Set();
  const approvedTexts = {};
  const correctedTexts = {};
  let studentRowSeq = 0;

  function setErr(el, on = true) {
    if (!el) return;
    el.classList.toggle("error", on);
    el.classList.toggle("spell-error", on);
    if (on) {
      el.classList.add("shake");
      setTimeout(() => el.classList.remove("shake"), 250);
    }
  }

  function scrollFocus(el) {
    if (!el) return;
    el.scrollIntoView({
      behavior: "smooth",
      block: "center"
    });
    setTimeout(() => el.focus?.(), 200);
  }

  function getSpellBoxByField(el) {
    if (!el?.id) return null;
    return byId(`${el.id}SpellBox`);
  }

  function getSpellLoadingByField(el) {
    if (!el?.id) return null;
    return byId(`${el.id}SpellLoading`);
  }

  function showSpellLoading(el) {
    const box = getSpellLoadingByField(el);
    if (box) box.classList.remove("hidden");
  }

  function hideSpellLoading(el) {
    const box = getSpellLoadingByField(el);
    if (box) box.classList.add("hidden");
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

  function showSpellOk(el) {
    clearSpellResult(el);
    if ((el.value || "").trim() !== "") {
      el.classList.add("spell-ok");
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

  function escapeRegExp(str) {
    return String(str).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function replaceWholeWordOnce(text, wrongWord, newWord) {
    if (!text || !wrongWord || !newWord) return text;
    return text.replace(new RegExp(escapeRegExp(wrongWord)), newWord);
  }

  function normalizeErrors(errors = [], originalText = "") {
    if (!Array.isArray(errors)) return [];

    const seen = new Set();
    const normalized = [];

    for (const item of errors) {
      const wrongWord = String(item?.wrongWord || "").trim();
      if (!wrongWord) continue;
      if (originalText && !originalText.includes(wrongWord)) continue;
      if (seen.has(wrongWord)) continue;

      seen.add(wrongWord);

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
    extractThaiWords(cleanText).forEach(word => approvedWords.add(word));
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
    if (remember) rememberApprovedText(fieldName, text);

    spellState[fieldName] = {
      checked: true,
      hasError: false,
      ignored: remember,
      errors: [],
      lastText: text
    };

    clearSpellResult(el);
    if ((text || "").trim() !== "") el.classList.add("spell-ok");
  }

  function shouldCheckSpell(el) {
    if (!el) return false;
    if (el.disabled || el.readOnly) return false;
    return !!el.dataset.spellField;
  }

  function showSpellError(el, errors = []) {
    clearSpellResult(el);
    el.classList.add("spell-error");

    const box = getSpellBoxByField(el);
    if (!box) return;

    errors = filterApprovedErrors(normalizeErrors(errors, el.value || ""));

    if (!errors.length) {
      showSpellOk(el);
      return;
    }

    let html = `<div class="spell-result-box">`;
    html += `<div class="spell-warning">พบคำแนะนำ ${errors.length} จุด</div>`;

    errors.forEach((item, index) => {
      html += `<div class="mt-2">`;
      html += `<div class="spell-help-text">คำที่ ${index + 1}: <b>${escapeHtml(item.wrongWord)}</b></div>`;

      if (item.suggestions.length > 0) {
        html += `<div class="spell-suggestions">`;

        item.suggestions.forEach(word => {
          html += `
          <button type="button"
            class="spell-suggestion-btn"
            data-target="${el.id}"
            data-word="${escapeHtml(word)}"
            data-wrong-word="${escapeHtml(item.wrongWord)}">
            ${escapeHtml(word)}
          </button>
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


  const SPELL_TIMEOUT_MS = 60000;
  const SPELL_CHUNK_LIMIT = 350;

  /*
    Spell Check API URL
    - ถ้ารันระบบบนเครื่องตัวเองผ่าน localhost / 127.0.0.1
      จะเรียก API ที่ http://127.0.0.1:8001
    - ถ้ารันบนเว็บจริง
      จะเรียก API ที่ Render
  */
  const SPELL_API_BASE_URL =
    (window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1")
      ? "http://127.0.0.1:8001"
      : "https://checkspell-api.onrender.com";

  const SPELL_CHECK_API_URL = `${SPELL_API_BASE_URL}/api/spell-check`;

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
    let normalized = (typeof normalizeErrors === "function") ?
      normalizeErrors(errors || [], text) :
      (Array.isArray(errors) ? errors : []);

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
      const response = await fetch(SPELL_CHECK_API_URL, {
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

  async function checkAllSpellFields() {
    for (const el of spellFields) {
      if (!el || !shouldCheckSpell(el)) continue;

      const fieldName = el.dataset.spellField || "";
      const text = (el.value || "").trim();

      if (!text) continue;

      const state = spellState[fieldName];

      if (state && state.checked && !state.hasError && state.lastText === text) {
        continue;
      }

      if (isApprovedText(fieldName, text) || correctedTexts[fieldName] === text) {
        setSpellPassed(el, fieldName, text, false);
        continue;
      }

      await checkSpellField(el);
    }

    for (const key in spellState) {
      const state = spellState[key];
      const remainingErrors = filterApprovedErrors(state.errors || []);

      if (state.checked && state.hasError && remainingErrors.length > 0) {
        alert("กรุณาเลือกคำแนะนำ หรือกดใช้ข้อความเดิมก่อนดำเนินการ");
        return false;
      }
    }

    return true;
  }

  function createStudentRow(values = {}) {
    studentRowSeq += 1;
    const rowId = `studentRow${studentRowSeq}`;
    const row = document.createElement("div");
    row.className = "student-row border rounded-xl p-4 bg-gray-50";
    row.dataset.rowId = rowId;

    row.innerHTML = `
      <div class="flex items-center justify-between mb-3">
        <div class="font-bold text-gray-800 student-title">นักศึกษาคนที่ ${studentList.children.length + 1}</div>
        <button type="button" class="remove-student-btn text-red-600 hover:text-red-800 font-bold">
          ลบ
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-gray-700 mb-1">ชื่อ - นามสกุล</label>
          <input type="text" name="student_name[]" class="student-name w-full border rounded-md p-2"
            placeholder="ชื่อ - นามสกุล" value="${escapeHtml(values.name || "")}">
        </div>

        <div>
          <label class="block text-gray-700 mb-1">รหัสนักศึกษา</label>
          <input type="text" name="student_id[]" class="student-id w-full border rounded-md p-2"
            placeholder="รหัสนักศึกษา 10 หลัก" inputmode="numeric" maxlength="13"
            value="${escapeHtml(values.id || "")}">
        </div>
      </div>

      <label class="flex items-center gap-2 mt-3">
        <input type="radio" name="student_contact_index" class="contact-radio accent-[#11C2B9]">
        กำหนดให้เป็นผู้ติดต่อ
      </label>

      <div class="student-phone-wrap mt-3 hidden">
        <label class="block text-gray-700 mb-1">เบอร์โทรศัพท์ผู้ติดต่อ</label>
        <input type="tel" name="student_phone[]" class="student-phone w-full border rounded-md p-2"
          placeholder="เบอร์โทรศัพท์ 10 หลัก" inputmode="numeric" maxlength="10"
          value="${escapeHtml(values.phone || "")}">
      </div>
    `;

    studentList.appendChild(row);

    if (values.checked) {
      row.querySelector(".contact-radio").checked = true;
    }

    refreshStudentRows();

    const idInput = row.querySelector(".student-id");
    const phoneInput = row.querySelector(".student-phone");
    idInput.addEventListener("input", () => {
      idInput.value = idInput.value.replace(/[^0-9]/g, "").slice(0, 13);
    });
    phoneInput.addEventListener("input", () => {
      phoneInput.value = phoneInput.value.replace(/[^0-9]/g, "").slice(0, 10);
    });

  }

  function refreshStudentRows() {
    const rows = [...document.querySelectorAll(".student-row")];

    rows.forEach((row, index) => {
      row.querySelector(".student-title").textContent = `นักศึกษาคนที่ ${index + 1}`;

      const radio = row.querySelector(".contact-radio");
      const phoneWrap = row.querySelector(".student-phone-wrap");
      const phoneInput = row.querySelector(".student-phone");

      radio.value = String(index);
      phoneInput.disabled = !radio.checked;
      if (!radio.checked) {
        phoneInput.value = "";
        phoneWrap.classList.add("hidden");
      } else {
        phoneWrap.classList.remove("hidden");
      }

      const removeBtn = row.querySelector(".remove-student-btn");
      removeBtn.classList.toggle("hidden", rows.length <= 1);
    });

    if (rows.length && !rows.some(row => row.querySelector(".contact-radio").checked)) {
      rows[0].querySelector(".contact-radio").checked = true;
      refreshStudentRows();
    }
  }

  function validateRequiredAndNumbers() {
    let firstInvalid = null;

    const requiredFields = [
      subjectInput,
      toPerson,
      semesterInput,
      academicYearInput,
      courseCodeInput,
      courseNameInput,
      curriculumNameInput,
      majorNameInput,
      studentYearInput,
      thesisTitle,
      advisorNameInput,
      projectDetail,
      dataDetail,
      dataAmount
    ];

    [...requiredFields, ...document.querySelectorAll(".student-name, .student-id, .student-phone")]
    .forEach(el => setErr(el, false));

    requiredFields.forEach(el => {
      if (!el?.value.trim()) {
        setErr(el, true);
        firstInvalid = firstInvalid || el;
      }
    });

    const supportChecked = !!document.querySelector('input[name="support_type"]:checked');
    if (!supportChecked) {
      alert("กรุณาเลือกประเภทข้อมูลที่ขอ");
      firstInvalid = firstInvalid || document.querySelector('input[name="support_type"]');
    }

    if (!/^\d{4}$/.test(academicYearInput?.value || "")) {
      setErr(academicYearInput, true);
      alert("กรุณากรอกปีการศึกษาเป็นตัวเลข 4 หลัก");
      firstInvalid = firstInvalid || academicYearInput;
    }

    if (!/^\d+$/.test(studentYearInput?.value || "")) {
      setErr(studentYearInput, true);
      alert("กรุณากรอกชั้นปีเป็นตัวเลข");
      firstInvalid = firstInvalid || studentYearInput;
    }

    const rows = [...document.querySelectorAll(".student-row")];

    if (!rows.length) {
      alert("กรุณาเพิ่มรายชื่อนักศึกษาอย่างน้อย 1 คน");
      firstInvalid = firstInvalid || addStudentBtn;
    }

    let hasContact = false;

    rows.forEach((row) => {
      const nameInput = row.querySelector(".student-name");
      const idInput = row.querySelector(".student-id");
      const radio = row.querySelector(".contact-radio");
      const phoneInput = row.querySelector(".student-phone");

      if (!nameInput.value.trim()) {
        setErr(nameInput, true);
        firstInvalid = firstInvalid || nameInput;
      }

      if (!/^\d{13}$/.test(idInput.value || "")) {
        setErr(idInput, true);
        firstInvalid = firstInvalid || idInput;
      }

      if (radio.checked) {
        hasContact = true;
        phoneInput.disabled = false;
        if (!/^\d{10}$/.test(phoneInput.value || "")) {
          setErr(phoneInput, true);
          firstInvalid = firstInvalid || phoneInput;
        }
      }
    });

    if (rows.length && !hasContact) {
      alert("กรุณาเลือกนักศึกษา 1 คนเป็นผู้ติดต่อ");
      firstInvalid = firstInvalid || rows[0].querySelector(".contact-radio");
    }

    if (firstInvalid) {
      if (firstInvalid.classList?.contains("student-id")) {
        alert("กรุณากรอกรหัสนักศึกษาให้ครบ 13 ตัวเลข");
      } else if (firstInvalid.classList?.contains("student-phone")) {
        alert("กรุณากรอกเบอร์โทรศัพท์ผู้ติดต่อให้ครบ 10 ตัวเลข");
      }

      scrollFocus(firstInvalid);
      return false;
    }

    return true;
  }

  document.addEventListener("click", (e) => {
    const ignoreBtn = e.target.closest(".spell-ignore-btn");
    if (!ignoreBtn) return;

    const target = byId(ignoreBtn.dataset.target);
    if (!target) return;

    const fieldName = target.dataset.spellField || "";
    const currentText = (target.value || "").trim();

    setSpellPassed(target, fieldName, currentText, true);
  });

  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".spell-suggestion-btn");
    if (!btn) return;

    const target = byId(btn.dataset.target);
    const word = btn.dataset.word;
    const wrongWord = btn.dataset.wrongWord;

    if (!target || !word || !wrongWord) return;

    target.value = replaceWholeWordOnce(target.value || "", wrongWord, word);

    const fieldName = target.dataset.spellField || "";
    const currentText = (target.value || "").trim();

    correctedTexts[fieldName] = currentText;
    approvedWords.add(word);

    setSpellPassed(target, fieldName, currentText, false);
  });

  studentList?.addEventListener("click", (e) => {
    const removeBtn = e.target.closest(".remove-student-btn");
    if (!removeBtn) return;

    const rows = document.querySelectorAll(".student-row");
    if (rows.length <= 1) return;

    removeBtn.closest(".student-row")?.remove();
    refreshStudentRows();
  });

  studentList?.addEventListener("change", (e) => {
    if (!e.target.classList.contains("contact-radio")) return;
    refreshStudentRows();
  });

  addStudentBtn?.addEventListener("click", () => {
    createStudentRow();
  });

  spellFields.forEach(el => {
    el?.addEventListener("input", () => {
      const fieldName = el.dataset.spellField || "";
      if (spellState[fieldName]) {
        spellState[fieldName].checked = false;
        spellState[fieldName].hasError = false;
        spellState[fieldName].errors = [];
        spellState[fieldName].lastText = "";
      }
      clearSpellResult(el);
    });
  });

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();

    refreshStudentRows();

    if (!validateRequiredAndNumbers()) return;

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;

    document.querySelectorAll(".student-phone").forEach(input => {
      input.disabled = false;
    });

    form.submit();
  });

  const EDIT_STUDENTS = <?= $editStudentsJsonForJs ?: '[]' ?>;

  if (Array.isArray(EDIT_STUDENTS) && EDIT_STUDENTS.length > 0) {
    EDIT_STUDENTS.forEach(student => createStudentRow(student));
  } else {
    createStudentRow({
      checked: true
    });
  }
  </script>
  <script>
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", () => {

    const main = document.getElementById("mainCategory");
    const sub = document.getElementById("subCategory");
    if (!main || !sub) return;

    const TEMPLATE_OPTIONS =
      <?= json_encode($templateDropdownOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {
        internal: [],
        external: []
      };

    function buildTemplateUrl(path) {
      const cleanPath = String(path || "").trim();
      if (!cleanPath) return "";
      if (/^https?:\/\//i.test(cleanPath) || cleanPath.startsWith("/Pro_letter/")) {
        return cleanPath;
      }
      return "/Pro_letter" + (cleanPath.startsWith("/") ? cleanPath : "/" + cleanPath);
    }

    function getCurrentSubText() {
      return String(sub.dataset.current || sub.getAttribute("data-current") || "").trim();
    }

    function getActiveTemplates(group) {
      const groupName = String(group || "").trim().toLowerCase();
      const rows = Array.isArray(TEMPLATE_OPTIONS[groupName]) ? TEMPLATE_OPTIONS[groupName] : [];

      return rows.filter(item => {
        const isActive = Number(item?.is_active || 0) === 1;
        const itemGroup = String(item?.group || "").trim().toLowerCase();
        const name = String(item?.name || "").trim();
        const url = String(item?.url || "").trim();

        return isActive && itemGroup === groupName && name !== "" && url !== "";
      });
    }

    function renderSubOptions(group, selectedValue = "") {
      const selectedText = String(selectedValue || "").trim();
      const list = getActiveTemplates(group);
      let hasSelectedText = false;

      sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';

      list.forEach(item => {
        const name = String(item.name || "").trim();
        const url = String(item.url || "").trim();

        const opt = document.createElement("option");
        opt.value = name;
        opt.textContent = name;
        opt.dataset.url = url;
        opt.dataset.templateId = item.id || "";
        opt.dataset.templateCode = item.code || "";

        if (selectedText && name === selectedText) {
          opt.selected = true;
          hasSelectedText = true;
        }

        sub.appendChild(opt);
      });

      if (selectedText && hasSelectedText) {
        sub.value = selectedText;
        sub.dataset.current = selectedText;
      } else if (list.length > 0) {
        sub.selectedIndex = 1;
        sub.dataset.current = sub.value;
        sub.setAttribute("data-current", sub.value);
      } else {
        sub.selectedIndex = 0;
        sub.value = "";
        sub.dataset.current = "";
        sub.setAttribute("data-current", "");
      }
    }

    function syncUI(keepCurrentSub = false) {
      const mainVal = String(main.value || "").trim().toLowerCase();
      const currentSub = keepCurrentSub ? getCurrentSubText() : "";

      if (mainVal === "internal" || mainVal === "external") {
        if (!window.CATEGORY_LOCKED_BY_STATUS) {
          sub.disabled = false;
        }
        renderSubOptions(mainVal, currentSub);
      } else {
        sub.disabled = true;
        sub.dataset.current = "";
        sub.innerHTML = '<option value="" selected>-- เลือกหมวดย่อย --</option>';
        sub.value = "";
      }
    }

    function redirectToSelectedTemplate() {
      const subVal = String(sub.value || "").trim();
      sub.dataset.current = subVal;
      sub.setAttribute("data-current", subVal);

      if (!subVal) return;

      const selectedOption = sub.options[sub.selectedIndex];
      const target = buildTemplateUrl(selectedOption?.dataset?.url || "");

      if (!target || target === "#") return;

      const nextUrl = new URL(target, window.location.origin);
      nextUrl.searchParams.set("main", String(main.value || "").trim());
      nextUrl.searchParams.set("sub", subVal);
      window.location.href = nextUrl.toString();
    }

    main.addEventListener("change", () => {
      sub.dataset.current = "";
      sub.setAttribute("data-current", "");
      syncUI(false);
      redirectToSelectedTemplate();
    });

    sub.addEventListener("focus", () => {
      if (window.CATEGORY_LOCKED_BY_STATUS) return;
      syncUI(true);
    });

    sub.addEventListener("pointerdown", () => {
      if (window.CATEGORY_LOCKED_BY_STATUS) return;
      syncUI(true);
    });

    sub.addEventListener("change", () => {
      redirectToSelectedTemplate();
    });

    window.addEventListener("pageshow", () => {
      syncUI(true);
    });

    syncUI(true);
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