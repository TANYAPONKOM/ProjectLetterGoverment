<?php
// Pro_letter/documents/infor_present.php
session_start();

$CURRENT_MAIN = $_GET['main'] ?? 'external';
$CURRENT_SUB  = $_GET['sub']  ?? 'หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ';

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'external';
}
require_once __DIR__ . '/../functions.php';
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

        $checkedStatusesForEditGuard = [
            'ผ่านการตรวจสอบ', 'ผ่านการตรวจสอบแล้ว', 'ได้รับการตรวจสอบ',
            'ได้รับการตรวจสอบแล้ว', 'ตรวจสอบแล้ว', 'approved', 'checked', 'reviewed'
        ];
        $isCheckedStatusForEditGuard = in_array($editGuardDocStatus, $checkedStatusesForEditGuard, true);

        // รองรับระบบเดิม: ถ้ายังไม่เคยกำหนดสิทธิ์รายบุคคล ให้เจ้าของเอกสารยังแก้เอกสารตัวเองได้
        // แต่ถ้ามีการกำหนดสิทธิ์แล้วและไม่มี document.edit ให้ถือว่าเป็นสิทธิ์ดูอย่างเดียว
        $legacyOwnerCanEditForEditGuard = ($editGuardOwnerId === $userId && !$hasAnyExplicitPermissionForEditGuard);

        $canEditThisFormForEditGuard = (!$isCheckedStatusForEditGuard) && (
            $isAdminOrOfficerForEditGuard
            || $hasDocumentEditPermissionForEditGuard
            || $legacyOwnerCanEditForEditGuard
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
    }
} catch (Throwable $e) {
    $templateDropdownOptions = [
        'internal' => [],
        'external' => []
    ];
}

$docId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $docId > 0;
$formData = [];

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

  .spell-error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
    background-color: #fffafa;
  }

  .spell-ok {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    background-color: #f0fdf4;
  }

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

  .spell-box.hidden,
  .spell-loading.hidden {
    display: none !important;
  }

  .spell-result-box {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .spell-warning {
    font-weight: 600;
    color: #991b1b;
  }

  .spell-help-text {
    font-size: 13px;
    color: #9a3412;
    font-weight: 500;
  }

  .spell-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
  }

  .spell-suggestion-btn {
    border: 1px solid #fdba74;
    background: #ffffff;
    color: #9a3412;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
  }

  .spell-ignore-btn {
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 13px;
  }

  .spell-loading {
    margin-top: 8px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #eff6ff;
    border: 1px solid #93c5fd;
    color: #1d4ed8;
    font-size: 14px;
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

  <form method="post" action="save_memo.php" id="memoForm">
    <input type="hidden" name="mode" value="<?= $isEdit ? 'update' : 'create' ?>">
    <input type="hidden" name="document_id" value="<?= (int)$docId ?>">
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="document_type_name" value="หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ">
    <input type="hidden" name="department_id" id="selectedDepartmentId" value="<?= (int)$currentUserDepartmentId ?>">

    <input type="hidden" name="purpose" value="consent_research_presentation">
    <input type="hidden" name="doc_date" value="<?= h($docDate ?: date('Y-m-d')) ?>">
    <input type="hidden" name="fullname" id="fullnameHidden"
      value="<?= htmlspecialchars($ownerName ?: ($_SESSION['fullname'] ?? '')) ?>">
    <input type="hidden" name="position" value="<?= htmlspecialchars($position ?? '') ?>">

    <input type="hidden" name="event_title" id="eventTitleHidden">
    <input type="hidden" name="join_date" id="joinDateHidden">
    <input type="hidden" name="place" id="placeHidden">
    <input type="hidden" name="academic_topic" id="academicTopicHidden">
    <input type="hidden" name="academic_level" id="academicLevelHidden">
    <input type="hidden" name="presenter_name_hidden" id="presenterNameHidden">
    <input type="hidden" name="signature_affiliation" id="signatureAffiliationHidden">
    <input type="hidden" name="no_cost" value="1">
    <input type="hidden" name="is_online" value="0">
    <input type="hidden" name="amount" value="0">

    <!-- กล่องเนื้อหา -->
    <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
      <h1 class="text-center font-bold mb-6 text-black">
        แบบฟอร์มหนังสือยินยอมให้นำเสนอผลงานทางวิชาการ
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


      <!-- 1. ชื่อ–นามสกุลผู้ยื่นขอ -->
      <div class="mb-4 flex items-center gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-56">1. ชื่อ–นามสกุลผู้ยื่นขอ:</label>
        <input type="text" id="fullName" name="full_name" class="flex border rounded-md p-2"
          value="<?= htmlspecialchars($formData[2] ?? $ownerName ?: ($_SESSION['fullname'] ?? '')) ?>" />

      </div>


      <!-- 2. ชื่อ–นามสกุลผู้เสนอผลงาน -->
      <div class="mb-4 flex items-start gap-4">
        <div class="flex items-center h-[42px] w-56">
          <label class="lbl whitespace-nowrap">
            2. ชื่อ–นามสกุลผู้เสนอผลงาน :
          </label>
        </div>

        <div class="flex flex-col">
          <input type="text" name="presenter_name" id="presenterName" data-spell-field="presenter_name"
            class="border rounded-md p-2 w-[330px]" placeholder="เช่น นางสาวสมหญิง ตั้งใจ"
            value="<?= htmlspecialchars($formData[14] ?? '') ?>">

          <div id="presenterNameSpellBox" class="spell-box hidden"></div>

          <div id="presenterNameSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 3. ชื่อผลงานวิจัย -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          3. ชื่อผลงานวิจัย :
        </label>

        <div class="flex-1">
          <textarea name="research_title" id="researchTitle" data-spell-field="research_title"
            class="w-full border rounded-md p-2" rows="2"
            placeholder="ระบุชื่อผลงานวิจัย"><?= htmlspecialchars($formData[13] ?? '') ?></textarea>

          <div id="researchTitleSpellBox" class="spell-box hidden"></div>

          <div id="researchTitleSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. ชื่องานประชุมวิชาการ -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          4. ชื่องานประชุมวิชาการ :
        </label>

        <div class="flex-1">
          <textarea name="conference_name" id="conferenceName" data-spell-field="conference_name"
            class="w-full border rounded-md p-2" rows="2"
            placeholder="เช่น International Conference on Information Technology"><?= htmlspecialchars($formData[5] ?? '') ?></textarea>

          <div id="conferenceNameSpellBox" class="spell-box hidden"></div>
          <div id="conferenceNameSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. ระดับการประชุมวิชาการ -->
      <div class="mb-4 flex items-center gap-4">
        <label class="lbl whitespace-nowrap w-56">
          5. ระดับการประชุมวิชาการ :
        </label>

        <select name="conference_level" id="conferenceLevel" class="border rounded-md p-2 flex-1">
          <option value="">-- เลือกระดับการประชุม --</option>
          <option value="ระดับชาติ" <?= (($formData[15] ?? '') === 'ระดับชาติ') ? 'selected' : '' ?>>ระดับชาติ</option>
          <option value="ระดับนานาชาติ" <?= (($formData[15] ?? '') === 'ระดับนานาชาติ') ? 'selected' : '' ?>>
            ระดับนานาชาติ</option>
        </select>
      </div>

      <!-- 6. สถานที่จัดงาน -->
      <div class="mb-6 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          6. สถานที่จัดงาน :
        </label>

        <div class="flex-1">
          <textarea name="conference_place" id="conferencePlace" data-spell-field="conference_place"
            class="w-full border rounded-md p-2" rows="2"
            placeholder="เช่น โรงแรม... กรุงเทพมหานคร ประเทศไทย / ประเทศญี่ปุ่น / รูปแบบออนไลน์"><?= htmlspecialchars($formData[7] ?? '') ?></textarea>

          <div id="conferencePlaceSpellBox" class="spell-box hidden"></div>
          <div id="conferencePlaceSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>



      <!-- 7. วันที่นำเสนอ -->
      <div class="mb-6 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          7. วันที่นำเสนอ :
        </label>

        <div class="flex items-center gap-3">
          <!-- วันที่เริ่ม -->
          <div class="relative">
            <input type="text" id="internStart" class="border rounded-md p-2 w-44 pr-10 cursor-pointer"
              placeholder="เริ่มต้น" readonly>
            <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <span>ถึง</span>

          <!-- วันที่สิ้นสุด -->
          <div class="relative">
            <input type="text" id="internEnd" class="border rounded-md p-2 w-44 pr-10 cursor-pointer"
              placeholder="สิ้นสุด" readonly>
            <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <!-- ค่าที่ส่งจริง -->
          <input type="hidden" name="intern_period" id="internPeriod" value="<?= h($formData[6] ?? '') ?>">
        </div>
      </div>
      <!-- 8. หน่วยงาน/มหาวิทยาลัยใต้ลายเซ็น -->
      <div class="mb-6 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          8. หน่วยงานใต้ลายเซ็น :
        </label>

        <div class="flex-1">
          <input type="text" name="signature_affiliation_input" id="signatureAffiliation"
            class="w-full border rounded-md p-2" placeholder="เช่น University in Hagen, Germany"
            value="<?= htmlspecialchars($formData[17] ?? '') ?>">
        </div>
      </div>


      <!-- ปุ่ม -->
      <div class="relative mt-20">
        <div class="absolute right-0 bottom-0">
          <button type="submit" id="submitBtn"
            class="bg-[#11C2B9] hover:bg-[#0fa39c] text-white font-bold w-[130px] h-[35px] rounded-md flex items-center justify-center transition">
            ดำเนินการ
          </button>
        </div>

      </div>
    </div>
  </form>

  <script>
  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));
  const byId = (id) => document.getElementById(id);

  const form = byId("memoForm");

  const spellState = {
    research_title: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    conference_name: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    conference_place: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    }
  };

  const spellCache = {};
  const approvedWords = new Set();
  const approvedTexts = {};
  const correctedTexts = {};

  function setErr(el, on = true) {
    if (!el) return;
    el.classList.toggle("error", on);
    if (on) {
      el.classList.add("shake");
      setTimeout(() => el.classList.remove("shake"), 250);
    }
  }

  function clearHintError(el) {
    if (!el) return;
    el.classList.remove("error", "shake");
    const oldHint = el.parentElement?.querySelector(".hint");
    if (oldHint) oldHint.remove();
  }

  function setHintError(el, msg) {
    if (!el) return;
    clearHintError(el);
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

  function getSpellBoxByField(el) {
    if (!el) return null;
    if (el.id === "researchTitle") return document.getElementById("researchTitleSpellBox");
    if (el.id === "presenterName") return document.getElementById("presenterNameSpellBox");
    if (el.id === "conferenceName") return document.getElementById("conferenceNameSpellBox");
    if (el.id === "conferencePlace") return document.getElementById("conferencePlaceSpellBox");
    return null;
  }

  function getSpellLoadingByField(el) {
    if (!el) return null;
    if (el.id === "researchTitle") return document.getElementById("researchTitleSpellLoading");
    if (el.id === "presenterName") return document.getElementById("presenterNameSpellLoading");
    if (el.id === "conferenceName") return document.getElementById("conferenceNameSpellLoading");
    if (el.id === "conferencePlace") return document.getElementById("conferencePlaceSpellLoading");
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
    if ((el.value || "").trim() !== "") el.classList.add("spell-ok");
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

    extractThaiWords(cleanText).forEach(word => {
      approvedWords.add(word);
    });
  }

  function isApprovedText(fieldName, text) {
    const cleanText = String(text || "").trim();

    return !!(
      fieldName &&
      cleanText &&
      approvedTexts[fieldName] === cleanText
    );
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

  function isIgnoredForSameText(fieldName, text) {
    const state = spellState[fieldName];
    return !!(state && state.ignored && state.lastText === text);
  }

  function shouldCheckSpell(el) {
    if (!el) return false;
    if (el.disabled || el.readOnly) return false;

    if (el.id === "otherTypeInput") return !!otherTypeRadio?.checked;
    if (el.id === "reasonOtherInput") return !!reasonOtherRadio?.checked;

    return true;
  }

  function showSpellError(el, errors = []) {
    clearSpellResult(el);
    el.classList.add("spell-error");

    const box = getSpellBoxByField(el);
    if (!box) return;

    errors = filterApprovedErrors(
      normalizeErrors(errors, el.value || "")
    );

    if (!errors.length) {
      showSpellOk(el);
      return;
    }

    let html = `<div class="spell-result-box">`;
    html += `<div class="spell-warning">พบคำแนะนำ ${errors.length} จุด</div>`;

    errors.forEach((item, index) => {
      html += `<div class="mt-2">`;
      html += `<div class="spell-help-text">คำที่ ${index + 1}: <b>${escapeHtml(item.wrongWord)}</b></div>`;
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

      html += `</div></div>`;
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
    (window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1") ?
    "http://127.0.0.1:8001" :
    "https://checkspell-api.onrender.com";

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
    const fields = [
      document.getElementById("researchTitle"),
      document.getElementById("presenterName"),
      document.getElementById("conferenceName"),
      document.getElementById("conferencePlace")
    ];

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

      if (
        isApprovedText(fieldName, text) ||
        correctedTexts[fieldName] === text
      ) {
        setSpellPassed(el, fieldName, text, false);
        continue;
      }

      await checkSpellField(el);
    }

    for (const key in spellState) {
      const state = spellState[key];
      const remainingErrors = filterApprovedErrors(state.errors || []);

      if (
        state.checked &&
        state.hasError &&
        remainingErrors.length > 0
      ) {
        alert("กรุณาเลือกคำแนะนำ หรือกดใช้ข้อความเดิมก่อนดำเนินการ");
        return false;
      }
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

    clearSpellResult(target);
    target.classList.add("spell-ok");
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





  function scrollFocus(el) {
    if (!el) return;
    el.scrollIntoView({
      behavior: "smooth",
      block: "center"
    });
    setTimeout(() => el.focus?.(), 200);
  }

  function validate() {
    let firstInvalid = null;

    const fullName = byId("fullName");
    const presenterName = byId("presenterName");
    const researchTitle = byId("researchTitle");
    const conferenceName = byId("conferenceName");
    const conferenceLevel = byId("conferenceLevel");
    const conferencePlace = byId("conferencePlace");
    const internPeriod = byId("internPeriod");
    const signatureAffiliation = byId("signatureAffiliation");

    [
      fullName,
      presenterName,
      researchTitle,
      conferenceName,
      conferenceLevel,
      conferencePlace
    ].forEach(el => setErr(el, false));
    clearHintError(signatureAffiliation);

    if (!fullName?.value.trim()) {
      setErr(fullName, true);
      firstInvalid = firstInvalid || fullName;
    }

    if (!presenterName?.value.trim()) {
      setErr(presenterName, true);
      firstInvalid = firstInvalid || presenterName;
    }

    if (!researchTitle?.value.trim()) {
      setErr(researchTitle, true);
      firstInvalid = firstInvalid || researchTitle;
    }

    if (!conferenceName?.value.trim()) {
      setErr(conferenceName, true);
      firstInvalid = firstInvalid || conferenceName;
    }

    if (!conferenceLevel?.value.trim()) {
      setErr(conferenceLevel, true);
      firstInvalid = firstInvalid || conferenceLevel;
    }

    if (!conferencePlace?.value.trim()) {
      setErr(conferencePlace, true);
      firstInvalid = firstInvalid || conferencePlace;
    }

    if (!signatureAffiliation?.value.trim()) {
      setHintError(signatureAffiliation, "กรุณากรอกหน่วยงานใต้ลายเซ็น");
      firstInvalid = firstInvalid || signatureAffiliation;
    }

    if (!internPeriod?.value.trim()) {
      alert("กรุณาเลือกวันที่นำเสนอ");
      firstInvalid = firstInvalid || byId("internStart");
    }

    if (firstInvalid) {
      scrollFocus(firstInvalid);
      return false;
    }

    return true;
  }

  function syncPresentFormToSaveMemo() {
    const fullName = document.getElementById("fullName")?.value.trim() || "";
    const presenterName = document.getElementById("presenterName")?.value.trim() || "";
    const researchTitle = document.getElementById("researchTitle")?.value.trim() || "";
    const conferenceName = document.getElementById("conferenceName")?.value.trim() || "";
    const conferenceLevel = document.getElementById("conferenceLevel")?.value.trim() || "";
    const conferencePlace = document.getElementById("conferencePlace")?.value.trim() || "";
    const internPeriod = document.getElementById("internPeriod")?.value.trim() || "";

    document.getElementById("hidden_fullname").value = fullName;
    document.getElementById("hidden_presenter_name").value = presenterName;
    document.getElementById("hidden_research_title").value = researchTitle;
    document.getElementById("hidden_intern_period").value = internPeriod;
    document.getElementById("hidden_conference_place").value = conferencePlace;

    document.getElementById("hidden_conference_level").value = conferenceLevel;
    document.getElementById("hidden_conference_name").value = conferenceName;
    document.getElementById("hidden_event_date").value = internPeriod;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!validate()) return;

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;


    byId("eventTitleHidden").value = byId("conferenceName")?.value.trim() || "";
    byId("joinDateHidden").value = byId("internPeriod")?.value.trim() || "";
    byId("placeHidden").value = byId("conferencePlace")?.value.trim() || "";
    byId("academicTopicHidden").value = byId("researchTitle")?.value.trim() || "";
    byId("academicLevelHidden").value = byId("conferenceLevel")?.value.trim() || "";
    byId("presenterNameHidden").value = byId("presenterName")?.value.trim() || "";
    byId("signatureAffiliationHidden").value = byId("signatureAffiliation")?.value.trim() || "";
    byId("fullnameHidden").value = document.querySelector('input[name="full_name"]')?.value.trim() || "";

    form.submit();
  });
  </script>


  <script>
  flatpickr.localize(flatpickr.l10ns.th);

  const monthsTH = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน",
    "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม",
    "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
  ];

  let startPicker;
  let endPicker;

  function formatThaiRange(start, end) {
    const startDay = start.getDate();
    const endDay = end.getDate();
    const startMonth = monthsTH[start.getMonth()];
    const endMonth = monthsTH[end.getMonth()];
    const startYear = start.getFullYear() + 543;
    const endYear = end.getFullYear() + 543;

    if (
      start.getMonth() === end.getMonth() &&
      start.getFullYear() === end.getFullYear()
    ) {
      return `${startDay} - ${endDay} ${endMonth} ${endYear}`;
    }

    return `${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
  }

  function updateInternRange() {
    const start = startPicker.selectedDates[0];
    const end = endPicker.selectedDates[0];

    if (!start && !end) return;

    let text = "";

    if (start && end) {
      text = formatThaiRange(start, end);
    } else if (start) {
      const d = start.getDate();
      const m = monthsTH[start.getMonth()];
      const y = start.getFullYear() + 543;
      text = `${d} ${m} ${y}`;
    }

    document.getElementById("internPeriod").value = text;

    const rangeDisplay = document.getElementById("internRangeDisplay");
    if (rangeDisplay) {
      rangeDisplay.value = text;
    }
  }

  startPicker = flatpickr("#internStart", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: updateInternRange
  });

  endPicker = flatpickr("#internEnd", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: updateInternRange
  });

  function parseThaiDateForIntern(raw) {
    raw = String(raw || "").trim();
    if (!raw) return null;

    const thaiDigits = "๐๑๒๓๔๕๖๗๘๙";
    raw = raw.replace(/[๐-๙]/g, ch => {
      const index = thaiDigits.indexOf(ch);
      return index >= 0 ? String(index) : ch;
    });

    const m = raw.match(/(\d{1,2})\s+([^\s]+)\s+(\d{4})/);
    if (!m) return null;

    const day = parseInt(m[1], 10);
    const monthIndex = monthsTH.indexOf(m[2].trim());
    let year = parseInt(m[3], 10);

    if (monthIndex === -1) return null;
    if (year > 2400) year -= 543;

    return new Date(year, monthIndex, day);
  }

  function parseThaiRangeForIntern(raw) {
    raw = String(raw || "")
      .trim()
      .replace(/[–—]/g, "-")
      .replace(/\s*ถึง\s*/g, " - ");

    if (!raw) return null;

    // แบบเต็ม: 10 กรกฎาคม 2568 - 12 สิงหาคม 2568
    let m = raw.match(/(\d{1,2})\s+([^\s]+)\s+(\d{4})\s*-\s*(\d{1,2})\s+([^\s]+)\s+(\d{4})/);
    if (m) {
      const start = parseThaiDateForIntern(`${m[1]} ${m[2]} ${m[3]}`);
      const end = parseThaiDateForIntern(`${m[4]} ${m[5]} ${m[6]}`);
      return start && end ? [start, end] : null;
    }

    // แบบย่อ: 10 - 12 กรกฎาคม 2568
    m = raw.match(/(\d{1,2})\s*-\s*(\d{1,2})\s+([^\s]+)\s+(\d{4})/);
    if (m) {
      const start = parseThaiDateForIntern(`${m[1]} ${m[3]} ${m[4]}`);
      const end = parseThaiDateForIntern(`${m[2]} ${m[3]} ${m[4]}`);
      return start && end ? [start, end] : null;
    }

    const single = parseThaiDateForIntern(raw);
    return single ? [single, single] : null;
  }

  // โหลดวันที่นำเสนอเดิมกลับมาโชว์ตอนแก้ไขเอกสาร
  const oldInternPeriod = (document.getElementById("internPeriod")?.value || "").trim();
  if (oldInternPeriod) {
    const parsedRange = parseThaiRangeForIntern(oldInternPeriod);
    if (parsedRange) {
      startPicker.setDate(parsedRange[0], false);
      endPicker.setDate(parsedRange[1], false);
      document.getElementById("internPeriod").value = oldInternPeriod;
    }
  }
  </script>
  <script>
  const main = document.getElementById("mainCategory");
  const sub = document.getElementById("subCategory");

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

    sub.innerHTML = "";

    if (list.length === 0) {
      sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';
      sub.value = "";
      sub.dataset.current = "";
      return;
    }

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
    } else {
      sub.selectedIndex = 0;
      sub.dataset.current = sub.value;
    }
  }

  function syncUI(keepCurrentSub = false) {
    const mainVal = String(main.value || "").trim().toLowerCase();
    const currentSub = keepCurrentSub ? String(sub.dataset.current || "").trim() : "";

    if (mainVal === "internal" || mainVal === "external") {
      if (!window.CATEGORY_LOCKED_BY_STATUS) {
        sub.disabled = false;
      }
      renderSubOptions(mainVal, currentSub);
    } else {
      sub.disabled = true;
      sub.dataset.current = "";
      sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';
      sub.value = "";
    }
  }

  function goToSelectedTemplate() {
    const subVal = String(sub.value || "").trim();
    sub.dataset.current = subVal;

    if (!subVal) return;

    const selectedOption = sub.options[sub.selectedIndex];
    const target = buildTemplateUrl(selectedOption?.dataset?.url || "");

    if (!target || target === "#") return;

    const connector = target.includes("?") ? "&" : "?";
    window.location.href =
      target +
      connector +
      "main=" + encodeURIComponent(String(main.value || "").trim()) +
      "&sub=" + encodeURIComponent(subVal);
  }

  main.addEventListener("change", () => {
    sub.dataset.current = "";
    syncUI(false);
    goToSelectedTemplate();
  });

  sub.addEventListener("focus", () => {
    syncUI(true);
  });

  sub.addEventListener("pointerdown", () => {
    syncUI(true);
  });

  sub.addEventListener("change", () => {
    goToSelectedTemplate();
  });

  window.addEventListener("pageshow", () => {
    syncUI(true);
  });

  syncUI(true);
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