<?php
$CURRENT_MAIN = $_GET['main'] ?? 'external';
$CURRENT_SUB  = $_GET['sub']  ?? 'ขอห้องพักรับรอง';

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'external';
}
?>
<!-- ขอห้องพักรับรอง (ของอาจารย์) Pro_letter/documents/infor_room_request.php-->
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

        if ($url === '/documents/infor_room_request.php') {
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

$docId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $docId > 0;
$formData = [];

if ($isEdit) {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT document_id, owner_id, status, header_text
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
    if ((int)$doc['owner_id'] !== (int)$_SESSION['user_id']) {
        header("Location: view_memo.php?id={$docId}&err=no_permission");
        exit;
    }

    $blockedEditStatuses = [
        'รอตรวจสอบ',
        'รอการตรวจสอบ',
        'รอตรวจ',
        'ผ่านการตรวจสอบ',
        'ผ่านการตรวจสอบแล้ว',
        'approved',
        'checked',
        'reviewed'
    ];

    if (in_array(trim((string)$doc['status']), $blockedEditStatuses, true)) {
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

$toPerson           = $formData[26] ?? '';
$roomRequest        = $formData[27] ?? '';
$roomRequestOther   = $formData[28] ?? '';
$guestFullname      = $formData[29] ?? '';
$personType         = $formData[30] ?? '';
$personTypeOther    = $formData[31] ?? '';
$reason             = $formData[32] ?? '';
$reasonOther        = $formData[33] ?? '';
$dateOption         = $formData[34] ?? 'single';
$singleDateValue    = $formData[35] ?? '';
$rangeDateValue     = $formData[36] ?? '';
$roomTypeValue      = $formData[37] ?? '';

function room_arabic_digits_for_form($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

$departmentPhoneValue = '';
if ($isEdit && !empty($doc['header_text']) && preg_match('/โทร\.?\s*([^\s]+)/u', (string)$doc['header_text'], $phoneMatch)) {
    $departmentPhoneValue = trim(room_arabic_digits_for_form($phoneMatch[1]));
}

// แยกข้อความช่วงวันที่เดิมให้เอากลับไปโชว์ในช่อง วันที่เริ่มต้น/วันที่สิ้นสุด ตอนแก้ไข
$rangeStartDisplay = '';
$rangeEndDisplay = '';
if (trim($rangeDateValue) !== '') {
    $rangeText = trim($rangeDateValue);
    if (preg_match('/^([0-9]{1,2})\s*-\s*([0-9]{1,2})\s+([^\s]+)\s+([0-9]{4})$/u', $rangeText, $m)) {
        $rangeStartDisplay = $m[1] . ' ' . $m[3] . ' ' . $m[4];
        $rangeEndDisplay   = $m[2] . ' ' . $m[3] . ' ' . $m[4];
    } elseif (strpos($rangeText, ' - ') !== false) {
        [$rangeStartDisplay, $rangeEndDisplay] = array_map('trim', explode(' - ', $rangeText, 2));
    } else {
        $rangeStartDisplay = $rangeText;
    }
}

function checked_value($a, $b) {
    return ((string)$a === (string)$b) ? 'checked' : '';
}
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

  <form method="post" action="<?= $isEdit ? '/Pro_letter/documents/update_memo.php' : 'save_memo.php' ?>" id="memoForm">
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="document_type_name" value="ขอห้องพักรับรอง">
    <input type="hidden" name="department_id" id="selectedDepartmentId" value="<?= (int)$currentUserDepartmentId ?>">
    <input type="hidden" name="purpose" value="room_request">
    <input type="hidden" name="target_form" value="infor_room_request.php">
    <input type="hidden" name="redirect_to" value="infor_room_request.php">
    <input type="hidden" name="mode" value="<?= $isEdit ? 'update' : 'create' ?>">
    <input type="hidden" name="document_id" value="<?= (int)$docId ?>">
    <!-- กล่องเนื้อหา -->
    <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
      <h1 class="text-center font-bold mb-6 text-black">
        แบบฟอร์มขอห้องพักรับรอง
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

      <!-- ข้อ 1 -->
      <?php
      $docDateSaved = trim((string)$docDate);
      $hasSavedDocDateField = array_key_exists(1, $formData);
      $docDateOption = ($hasSavedDocDateField && $docDateSaved === '') ? 'no_date' : 'use_date';
      ?>
      <div class="mb-4">
        <div class="flex flex-col gap-2">
          <label class="lbl text-gray-800 whitespace-nowrap" for="docDateDisplay">1. วันที่บนบันทึกข้อความ :</label>

          <div class="flex items-center gap-3 flex-nowrap pl-4 w-full overflow-x-auto">
            <label class="flex items-center gap-2 text-gray-800 whitespace-nowrap shrink-0">
              <input type="radio" name="doc_date_option" id="docDateUse" value="use_date" class="accent-black"
                <?= ($docDateOption === 'use_date') ? 'checked' : '' ?>>
              วันที่
            </label>

            <div class="relative shrink-0" id="docDatePickerWrap">
              <input type="text" id="docDateDisplay" class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer"
                placeholder="เลือกวันที่" readonly value="<?= htmlspecialchars($docDateSaved) ?>" />

              <input type="hidden" name="doc_date" id="docDate" value="<?= htmlspecialchars($docDateSaved) ?>" />

              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9] pointer-events-none"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>

            <label class="lbl text-gray-800 whitespace-nowrap shrink-0">ที่ต้องการให้ปรากฎบนบันทึกข้อความ</label>
          </div>

          <label class="flex items-center gap-2 text-gray-800 whitespace-nowrap pl-4">
            <input type="radio" name="doc_date_option" id="docDateNone" value="no_date" class="accent-black"
              <?= ($docDateOption === 'no_date') ? 'checked' : '' ?>>
            ไม่ประสงค์ใส่วันที่
          </label>
        </div>
      </div>

      <!-- ข้อ 2 -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap pt-2" for="toPerson">2. เรียน :</label>

        <div class="w-full">
          <input type="text" name="to_person" id="toPerson" data-spell-field="to_person"
            class="w-full border rounded-md p-2 shadow-sm"
            placeholder="เช่น ประธานคณะกรรมการบ้านพัก มจพ. วิทยาเขตปราจีนบุรี"
            value="<?= htmlspecialchars($toPerson) ?>" />

          <div id="toPersonSpellBox" class="spell-box hidden"></div>

          <div id="toPersonSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ข้อ 3 -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 items-end">
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="fullname">3. ชื่อ - นามสกุลผู้ขอ :</label>
          <input type="text" name="fullname" class="flex-1 border rounded-md p-2" id="fullname"
            value="<?= htmlspecialchars($ownerName ?: ($_SESSION['fullname'] ?? '')) ?>" />
        </div>

        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="position">ตำแหน่ง :</label>
          <input type="text" name="position" class="flex-1 border rounded-md p-2" id="position"
            value="<?= htmlspecialchars($position ?: 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ') ?>" />
        </div>
      </div>

      <!-- ข้อ 4 -->
      <div class="mb-4">
        <label class="lbl text-gray-800 block mb-2">4. ขออนุมัติใช้ห้องพักรับรองสำหรับ :</label>

        <div class="ml-6 space-y-2 text-gray-800" id="roomRequestGroup">
          <label class="flex items-center gap-2">
            <input type="radio" name="room_request" value="วิทยากร" class="accent-black"
              <?= checked_value($roomRequest, 'วิทยากร') ?>>
            วิทยากร
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="room_request" value="ผู้ทรงคุณวุฒิ" class="accent-black"
              <?= checked_value($roomRequest, 'ผู้ทรงคุณวุฒิ') ?>>
            ผู้ทรงคุณวุฒิ
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="room_request" value="แขกของมหาวิทยาลัย" class="accent-black"
              <?= checked_value($roomRequest, 'แขกของมหาวิทยาลัย') ?>>
            แขกของมหาวิทยาลัย
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="room_request" value="บุคลากรภายนอก" class="accent-black"
              <?= checked_value($roomRequest, 'บุคลากรภายนอก') ?>>
            บุคลากรภายนอก
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="room_request" value="บุคลากรบรรจุใหม่" class="accent-black"
              <?= checked_value($roomRequest, 'บุคลากรบรรจุใหม่') ?>>
            บุคลากรบรรจุใหม่
          </label>

          <div class="flex items-start gap-3">
            <div class="flex items-center gap-2 h-[42px]">
              <input type="radio" name="room_request" value="อื่น ๆ" id="roomRequestOtherRadio" class="accent-black"
                <?= checked_value($roomRequest, 'อื่น ๆ') ?>>
              <span class="whitespace-nowrap">อื่น ๆ (ระบุ)</span>
            </div>

            <div class="flex flex-col">
              <input type="text" name="room_request_other" id="roomRequestOtherInput"
                data-spell-field="room_request_other" class="border rounded-md p-2 w-[300px] bg-gray-100 text-gray-400"
                placeholder="โปรดระบุ เช่น ผู้เข้าร่วมโครงการ" value="<?= htmlspecialchars($roomRequestOther) ?>"
                disabled>

              <div id="roomRequestOtherSpellBox" class="spell-box hidden"></div>

              <div id="roomRequestOtherSpellLoading" class="spell-loading hidden">
                <div class="spell-loading-row">
                  <div class="spell-spinner"></div>
                  <span>กำลังตรวจคำผิด...</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ข้อ 5 -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap pt-2">5. ชื่อ - นามสกุลผู้เข้าพัก :</label>
        <div class="w-full">
          <input type="text" name="guest_fullname" id="guestFullname" data-spell-field="guest_fullname"
            class="w-full border rounded-md p-2 shadow-sm" placeholder="กรอกชื่อผู้ที่จะเข้าพัก เช่น นายสมชาย ใจดี"
            value="<?= htmlspecialchars($guestFullname) ?>" />

          <div id="guestFullnameSpellBox" class="spell-box hidden"></div>

          <div id="guestFullnameSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ข้อ 6 -->
      <div class="mb-4">
        <label class="lbl text-gray-800 block mb-2">6. ประเภทผู้เข้าพัก :</label>

        <div class="ml-6 space-y-2 text-gray-800" id="personTypeGroup">
          <label class="flex items-center gap-2">
            <input type="radio" name="person_type" value="อาจารย์" class="accent-black"
              <?= checked_value($personType, 'อาจารย์') ?>>
            อาจารย์
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="person_type" value="เจ้าหน้าที่" class="accent-black"
              <?= checked_value($personType, 'เจ้าหน้าที่') ?>>
            เจ้าหน้าที่
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="person_type" value="วิทยากร" class="accent-black"
              <?= checked_value($personType, 'วิทยากร') ?>>
            วิทยากร
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="person_type" value="ผู้ทรงคุณวุฒิ" class="accent-black"
              <?= checked_value($personType, 'ผู้ทรงคุณวุฒิ') ?>>
            ผู้ทรงคุณวุฒิ
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="person_type" value="บุคลากรภายนอก" class="accent-black"
              <?= checked_value($personType, 'บุคลากรภายนอก') ?>>
            บุคลากรภายนอก
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="person_type" value="แขกของมหาวิทยาลัย" class="accent-black"
              <?= checked_value($personType, 'แขกของมหาวิทยาลัย') ?>>
            แขกของมหาวิทยาลัย
          </label>

          <div class="flex items-start gap-3">
            <div class="flex items-center gap-2 h-[42px]">
              <input type="radio" name="person_type" value="อื่น ๆ" id="otherTypeRadio" class="accent-black"
                <?= checked_value($personType, 'อื่น ๆ') ?>>
              <span class="whitespace-nowrap">อื่น ๆ (ระบุ)</span>
            </div>

            <div class="flex flex-col">
              <input type="text" name="person_type_other" id="otherTypeInput" data-spell-field="person_type_other"
                class="border rounded-md p-2 w-[260px] bg-gray-100 text-gray-400" placeholder="โปรดระบุ"
                value="<?= htmlspecialchars($personTypeOther) ?>" disabled>

              <div id="personTypeOtherSpellBox" class="spell-box hidden"></div>

              <div id="personTypeOtherSpellLoading" class="spell-loading hidden">
                <div class="spell-loading-row">
                  <div class="spell-spinner"></div>
                  <span>กำลังตรวจคำผิด...</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ข้อ 7 -->
      <div class="mb-4">
        <label class="lbl text-gray-800 block mb-2">7. เหตุผลในการขอใช้ห้องพักรับรอง :</label>

        <div class="ml-6 space-y-2 text-gray-800">
          <label class="flex items-center gap-2">
            <input type="radio" name="reason" value="เพื่อปฏิบัติงาน" class="accent-black"
              <?= checked_value($reason, 'เพื่อปฏิบัติงาน') ?>>
            เพื่อปฏิบัติงาน
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="reason" value="เพื่อเป็นวิทยากร" class="accent-black"
              <?= checked_value($reason, 'เพื่อเป็นวิทยากร') ?>>
            เพื่อเป็นวิทยากร
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="reason" value="เพื่อเข้าร่วมโครงการ" class="accent-black"
              <?= checked_value($reason, 'เพื่อเข้าร่วมโครงการ') ?>>
            เพื่อเข้าร่วมโครงการ
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="reason" value="เพื่อปฏิบัติภารกิจราชการ" class="accent-black"
              <?= checked_value($reason, 'เพื่อปฏิบัติภารกิจราชการ') ?>>
            เพื่อปฏิบัติภารกิจราชการ
          </label>

          <label class="flex items-center gap-2">
            <input type="radio" name="reason" value="เพื่อรับรองแขกของมหาวิทยาลัย" class="accent-black"
              <?= checked_value($reason, 'เพื่อรับรองแขกของมหาวิทยาลัย') ?>>
            เพื่อรับรองแขกของมหาวิทยาลัย
          </label>

          <div class="flex items-start gap-3">
            <div class="flex items-center gap-2 h-[42px]">
              <input type="radio" name="reason" value="อื่น ๆ" id="reasonOtherRadio" class="accent-black"
                <?= checked_value($reason, 'อื่น ๆ') ?>>
              <span class="whitespace-nowrap">อื่น ๆ (ระบุ)</span>
            </div>

            <div class="flex flex-col">
              <input type="text" name="reason_other" id="reasonOtherInput" data-spell-field="reason_other"
                class="border rounded-md p-2 w-[300px] bg-gray-100 text-gray-400" placeholder="โปรดระบุ"
                value="<?= htmlspecialchars($reasonOther) ?>" disabled>

              <div id="reasonOtherSpellBox" class="spell-box hidden"></div>

              <div id="reasonOtherSpellLoading" class="spell-loading hidden">
                <div class="spell-loading-row">
                  <div class="spell-spinner"></div>
                  <span>กำลังตรวจคำผิด...</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ข้อ 8 -->
      <div class="mb-6">
        <label class="lbl text-gray-800 block mb-2" id="dateLabel">8. วันที่เข้าพัก :</label>

        <div class="space-y-4 ml-6 text-gray-800">
          <!-- วันเดียว -->
          <div class="flex items-center gap-2">
            <input type="radio" name="date_option" value="single" id="optSingle" class="accent-[#11C2B9]"
              <?= checked_value($dateOption, 'single') ?> />
            <span>วันเดียว :</span>

            <div class="relative">
              <input type="text" name="single_date" id="singleDate"
                class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly
                value="<?= htmlspecialchars($singleDateValue) ?>" />

              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>
          </div>

          <!-- หลายวัน -->
          <div class="flex flex-wrap items-center gap-2">
            <input type="radio" name="date_option" value="range" id="optRange" class="accent-[#11C2B9]"
              <?= checked_value($dateOption, 'range') ?> />
            <span>หลายวัน :</span>

            <div class="relative">
              <input type="text" id="startDate" class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer"
                placeholder="วันที่เริ่มต้น" readonly value="<?= htmlspecialchars($rangeStartDisplay) ?>" />

              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>

            <span>ถึง</span>

            <div class="relative">
              <input type="text" id="endDate" class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer"
                placeholder="วันที่สิ้นสุด" readonly value="<?= htmlspecialchars($rangeEndDisplay) ?>" />

              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>

            <input type="hidden" name="range_date" id="rangeDate" value="<?= htmlspecialchars($rangeDateValue) ?>" />
          </div>
        </div>
      </div>

      <!-- ข้อ 9 -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap pt-2">9. ห้องพัก/สถานที่ที่ต้องการใช้ :</label>

        <div class="w-full">
          <input type="text" name="room_type" id="roomType" data-spell-field="room_type"
            class="w-full border rounded-md p-2 shadow-sm"
            placeholder="เช่น อาคารบ้านพักรับรอง ห้อง VIP, ห้องพักรับรองชั้น 3, ห้องปกติ เป็นต้น"
            value="<?= htmlspecialchars($roomTypeValue) ?>">

          <div id="roomTypeOtherSpellBox" class="spell-box hidden"></div>

          <div id="roomTypeOtherSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ข้อ 10 -->
      <div class="mb-4 flex items-center gap-3 flex-nowrap">
        <label class="lbl text-gray-800 whitespace-nowrap" for="departmentPhone">10. เบอร์โทรภาควิชา :</label>
        <span class="text-gray-800 whitespace-nowrap">โทร.</span>
        <input type="text" name="department_phone" id="departmentPhone"
          class="border rounded-md p-2 shadow-sm w-[260px]" placeholder="เช่น 7064"
          value="<?= htmlspecialchars($departmentPhoneValue) ?>" />
        <span class="text-gray-800 whitespace-nowrap">ที่ต้องการให้ขึ้นที่ส่วนราชการ</span>
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
  const docDate = byId("docDate");
  const docDateDisplay = byId("docDateDisplay");
  const docDateUse = byId("docDateUse");
  const docDateNone = byId("docDateNone");
  const roomRequestOtherInput = byId("roomRequestOtherInput");
  const roomRequestOtherRadio = byId("roomRequestOtherRadio");
  const guestFullname = byId("guestFullname");
  const toPerson = byId("toPerson");
  const otherTypeInput = byId("otherTypeInput");
  const otherTypeRadio = byId("otherTypeRadio");
  const reasonOtherInput = byId("reasonOtherInput");
  const reasonOtherRadio = byId("reasonOtherRadio");
  const roomType = byId("roomType");
  const departmentPhone = byId("departmentPhone");
  const startDate = byId("startDate");
  const endDate = byId("endDate");

  const personTypeRadios = $$('input[name="person_type"]');
  const reasonRadios = $$('input[name="reason"]');

  const optSingle = byId("optSingle");
  const singleDate = byId("singleDate");
  const optRange = byId("optRange");
  const rangeDate = byId("rangeDate");
  const dateLabel = byId("dateLabel");

  const spellState = {
    to_person: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    room_request_other: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    guest_fullname: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    person_type_other: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    reason_other: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    room_type: {
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

  function getSpellBoxByField(el) {
    if (!el) return null;
    if (el.id === "toPerson") return byId("toPersonSpellBox");
    if (el.id === "roomRequestOtherInput") return byId("roomRequestOtherSpellBox");
    if (el.id === "guestFullname") return byId("guestFullnameSpellBox");
    if (el.id === "otherTypeInput") return byId("personTypeOtherSpellBox");
    if (el.id === "reasonOtherInput") return byId("reasonOtherSpellBox");
    if (el.id === "roomType") return byId("roomTypeOtherSpellBox");
    return null;
  }

  function getSpellLoadingByField(el) {
    if (!el) return null;
    if (el.id === "toPerson") return byId("toPersonSpellLoading");
    if (el.id === "roomRequestOtherInput") return byId("roomRequestOtherSpellLoading");
    if (el.id === "guestFullname") return byId("guestFullnameSpellLoading");
    if (el.id === "otherTypeInput") return byId("personTypeOtherSpellLoading");
    if (el.id === "reasonOtherInput") return byId("reasonOtherSpellLoading");
    if (el.id === "roomType") return byId("roomTypeOtherSpellLoading");
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

    if ((text || "").trim() !== "") {
      el.classList.add("spell-ok");
    }
  }

  function shouldCheckSpell(el) {
    if (!el) return false;
    if (el.disabled || el.readOnly) return false;

    if (el.id === "roomRequestOtherInput") {
      return !!roomRequestOtherRadio?.checked;
    }

    if (el.id === "otherTypeInput") {
      return !!otherTypeRadio?.checked;
    }

    if (el.id === "reasonOtherInput") {
      return !!reasonOtherRadio?.checked;
    }

    return true;
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
      toPerson,
      roomRequestOtherInput,
      guestFullname,
      otherTypeInput,
      reasonOtherInput,
      roomType
    ];

    for (const el of fields) {
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

  function syncOtherInput(radio, input, stateKey) {
    if (radio?.checked) {
      input.disabled = false;
      input.classList.remove("bg-gray-100", "text-gray-400");
    } else {
      input.value = "";
      input.disabled = true;
      input.classList.add("bg-gray-100", "text-gray-400");
      clearSpellResult(input);
      spellState[stateKey] = {
        checked: false,
        hasError: false,
        ignored: false,
        errors: [],
        lastText: ""
      };
    }
  }
  const roomRequestRadios = $$('input[name="room_request"]');

  roomRequestRadios.forEach(radio => {
    radio.addEventListener("change", () => {
      syncOtherInput(roomRequestOtherRadio, roomRequestOtherInput, "room_request_other");
    });
  });
  personTypeRadios.forEach(radio => {
    radio.addEventListener("change", () => {
      syncOtherInput(otherTypeRadio, otherTypeInput, "person_type_other");
    });
  });

  reasonRadios.forEach(radio => {
    radio.addEventListener("change", () => {
      syncOtherInput(reasonOtherRadio, reasonOtherInput, "reason_other");
    });
  });

  function syncDateOptionUI() {
    if (optSingle.checked) {
      singleDate.disabled = false;
    } else {
      singleDate.disabled = true;
    }
  }

  optSingle.addEventListener("change", syncDateOptionUI);
  optRange.addEventListener("change", syncDateOptionUI);

  syncOtherInput(roomRequestOtherRadio, roomRequestOtherInput, "room_request_other");
  syncOtherInput(otherTypeRadio, otherTypeInput, "person_type_other");
  syncOtherInput(reasonOtherRadio, reasonOtherInput, "reason_other");
  syncDateOptionUI();

  [
    docDateDisplay,
    toPerson,
    roomRequestOtherInput,
    guestFullname,
    otherTypeInput,
    reasonOtherInput,
    singleDate,
    startDate,
    endDate,
    rangeDate,
    roomType,
    departmentPhone
  ].forEach((el) => {
    if (!el) return;
    el.addEventListener("input", () => setErr(el, false));
    el.addEventListener("change", () => setErr(el, false));
  });

  function clearFieldHint(el) {
    if (!el) return;
    el.classList.remove("error", "shake", "border-red-500", "ring-2", "ring-red-300");
    const parent = el.parentElement;
    const oldHint = parent?.querySelector(":scope > .hint");
    if (oldHint) oldHint.remove();
  }

  function setErr(el, isError, msg = "กรุณากรอกข้อมูลให้ครบถ้วน") {
    if (!el) return;

    clearFieldHint(el);

    if (!isError) return;

    el.classList.add("error", "shake", "border-red-500", "ring-2", "ring-red-300");
    setTimeout(() => el.classList.remove("shake"), 250);

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

    [
      docDateDisplay,
      toPerson,
      roomRequestOtherInput,
      guestFullname,
      otherTypeInput,
      reasonOtherInput,
      singleDate,
      startDate,
      endDate,
      roomType,
      departmentPhone,
      ...roomRequestRadios,
      ...personTypeRadios,
      ...reasonRadios
    ].forEach(el => setErr(el, false));

    if (!docDateNone?.checked && !docDate.value) {
      setErr(docDateDisplay, true, "กรุณาเลือกวันที่บนบันทึกข้อความ");
      firstInvalid = firstInvalid || docDateDisplay;
    }

    if (!toPerson.value.trim()) {
      setErr(toPerson, true, "กรุณากรอกผู้รับหนังสือ");
      firstInvalid = firstInvalid || toPerson;
    }

    const hasRoomRequest = roomRequestRadios.some(r => r.checked);
    if (!hasRoomRequest) {
      setErr(roomRequestRadios[0], true, "กรุณาเลือกขออนุมัติใช้ห้องพักรับรองสำหรับ");
      firstInvalid = firstInvalid || roomRequestRadios[0];
    }

    if (roomRequestOtherRadio.checked && !roomRequestOtherInput.value.trim()) {
      setErr(roomRequestOtherInput, true, "กรุณาระบุรายละเอียดอื่น ๆ");
      firstInvalid = firstInvalid || roomRequestOtherInput;
    }

    if (!guestFullname.value.trim()) {
      setErr(guestFullname, true, "กรุณากรอกชื่อ - นามสกุลผู้เข้าพัก");
      firstInvalid = firstInvalid || guestFullname;
    }

    const hasPersonType = personTypeRadios.some(r => r.checked);
    if (!hasPersonType) {
      setErr(personTypeRadios[0], true, "กรุณาเลือกประเภทผู้เข้าพัก");
      firstInvalid = firstInvalid || personTypeRadios[0];
    }

    if (otherTypeRadio.checked && !otherTypeInput.value.trim()) {
      setErr(otherTypeInput, true, "กรุณาระบุประเภทผู้เข้าพัก");
      firstInvalid = firstInvalid || otherTypeInput;
    }

    const hasReason = reasonRadios.some(r => r.checked);
    if (!hasReason) {
      setErr(reasonRadios[0], true, "กรุณาเลือกเหตุผลในการขอใช้ห้องพักรับรอง");
      firstInvalid = firstInvalid || reasonRadios[0];
    }

    if (reasonOtherRadio.checked && !reasonOtherInput.value.trim()) {
      setErr(reasonOtherInput, true, "กรุณาระบุเหตุผลอื่น ๆ");
      firstInvalid = firstInvalid || reasonOtherInput;
    }

    if (optSingle.checked) {
      if (!singleDate.value.trim()) {
        setErr(singleDate, true, "กรุณาเลือกวันที่เข้าพัก");
        firstInvalid = firstInvalid || singleDate;
      }
    } else if (optRange.checked) {
      if (!rangeDate.value.trim()) {
        setErr(startDate, true, "กรุณาเลือกวันที่เริ่มต้น");
        setErr(endDate, true, "กรุณาเลือกวันที่สิ้นสุด");
        firstInvalid = firstInvalid || startDate;
      }
    }

    if (!roomType.value.trim()) {
      setErr(roomType, true, "กรุณากรอกห้องพัก/สถานที่ที่ต้องการใช้");
      firstInvalid = firstInvalid || roomType;
    }

    if (!departmentPhone.value.trim()) {
      setErr(departmentPhone, true, "กรุณากรอกเบอร์โทรภาควิชา");
      firstInvalid = firstInvalid || departmentPhone;
    }

    if (firstInvalid) {
      scrollFocus(firstInvalid);
      return false;
    }

    return true;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    syncDocDateOptionUI();

    if (docDateNone?.checked) {
      if (docDateDisplay) docDateDisplay.value = "";
      if (docDate) docDate.value = "";
    } else if (docPicker?.selectedDates?. [0] && docDate) {
      docDate.value = docPicker.formatDate(docPicker.selectedDates[0], "Y-m-d");
    }

    if (!validate()) return;

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;

    form.submit();
  });
  </script>

  <script>
  flatpickr.localize(flatpickr.l10ns.th);

  const monthsTH = [
    "มกราคม",
    "กุมภาพันธ์",
    "มีนาคม",
    "เมษายน",
    "พฤษภาคม",
    "มิถุนายน",
    "กรกฎาคม",
    "สิงหาคม",
    "กันยายน",
    "ตุลาคม",
    "พฤศจิกายน",
    "ธันวาคม",
  ];

  function toThaiDisplay(dateObj) {
    const d = dateObj.getDate();
    const m = monthsTH[dateObj.getMonth()];
    const y = dateObj.getFullYear() + 543;
    return `${d} ${m} ${y}`;
  }

  function parseYMD(dateText) {
    const text = String(dateText || "").trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;

    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    if (Number.isNaN(date.getTime())) return null;

    return date;
  }

  const docPicker = flatpickr("#docDateDisplay", {
    dateFormat: "Y-m-d",
    disableMobile: true,
    allowInput: false,
    clickOpens: true,
    onReady: function(selectedDates, dateStr, instance) {
      const savedDate = parseYMD(docDate?.value);
      if (savedDate) {
        instance.setDate(savedDate, false);
        instance.input.value = toThaiDisplay(savedDate);
        if (docDate) docDate.value = instance.formatDate(savedDate, "Y-m-d");
      }
    },
    onChange: function(selectedDates, dateStr, instance) {
      const d = selectedDates[0];
      if (!d) return;

      byId("docDateDisplay").value = toThaiDisplay(d);
      byId("docDate").value = instance.formatDate(d, "Y-m-d");
    }
  });

  function syncDocDateOptionUI() {
    const isNoDate = !!docDateNone?.checked;

    if (isNoDate) {
      if (docDateDisplay) {
        docDateDisplay.value = "";
        docDateDisplay.disabled = true;
        docDateDisplay.classList.add("bg-gray-100", "text-gray-400", "cursor-not-allowed");
        docDateDisplay.classList.remove("cursor-pointer");
      }

      if (docDate) {
        docDate.value = "";
      }

      docPicker?.clear();
      docPicker?.set("clickOpens", false);
      setErr(docDateDisplay, false);
    } else {
      if (docDateDisplay) {
        docDateDisplay.disabled = false;
        docDateDisplay.classList.remove("bg-gray-100", "text-gray-400", "cursor-not-allowed");
        docDateDisplay.classList.add("cursor-pointer");
      }

      docPicker?.set("clickOpens", true);
    }
  }

  docDateUse?.addEventListener("change", syncDocDateOptionUI);
  docDateNone?.addEventListener("change", syncDocDateOptionUI);
  syncDocDateOptionUI();
  // ✅ ปฏิทินวันเดียว
  flatpickr("#singleDate", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: function(selectedDates, dateStr, instance) {
      if (selectedDates.length > 0) {
        const date = selectedDates[0];
        const day = date.getDate();
        const month = monthsTH[date.getMonth()];
        const year = date.getFullYear() + 543;
        const formatted = `${day} ${month} ${year}`;

        // 🔹 แสดงผลรูปแบบไทยในช่อง input (แทนค่าเก่า)
        instance.input.value = formatted;
      }
    },
  });

  // ===== ปฏิทินช่วงวันที่ (เริ่มต้น / สิ้นสุด) =====
  const startPicker = flatpickr("#startDate", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: updateRangeDisplay,
  });

  const endPicker = flatpickr("#endDate", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    onChange: updateRangeDisplay,
  });

  // ===== ฟังก์ชันแปลงและแสดงผล =====
  function updateRangeDisplay() {
    const start = startPicker.selectedDates[0];
    const end = endPicker.selectedDates[0];
    if (start && end) {
      const months = [
        "มกราคม",
        "กุมภาพันธ์",
        "มีนาคม",
        "เมษายน",
        "พฤษภาคม",
        "มิถุนายน",
        "กรกฎาคม",
        "สิงหาคม",
        "กันยายน",
        "ตุลาคม",
        "พฤศจิกายน",
        "ธันวาคม",
      ];

      const startDay = start.getDate();
      const endDay = end.getDate();
      const startMonth = months[start.getMonth()];
      const endMonth = months[end.getMonth()];
      const startYear = start.getFullYear() + 543;
      const endYear = end.getFullYear() + 543;

      let displayText = "";

      // ✅ ถ้าเดือนเดียวกันและปีเดียวกัน
      if (
        start.getMonth() === end.getMonth() &&
        start.getFullYear() === end.getFullYear()
      ) {
        displayText = `${startDay} - ${endDay} ${endMonth} ${endYear}`;
      }
      // ✅ ถ้าเดือนหรือปีต่างกัน
      else {
        displayText = `${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
      }

      // ✅ แสดงผลในช่องรูปแบบและช่องซ่อน
      document.getElementById("rangeDate").value = displayText;
    }
  }

  // ===== สลับสถานะช่องเมื่อเลือก radio =====
  document
    .getElementById("optSingle")
    .addEventListener("change", toggleDatePickers);
  document
    .getElementById("optRange")
    .addEventListener("change", toggleDatePickers);

  function toggleDatePickers() {
    const single = document.getElementById("singleDate");
    const start = document.getElementById("startDate");
    const end = document.getElementById("endDate");
    const display = null;

    if (document.getElementById("optSingle").checked) {
      single.disabled = false;
      start.disabled = true;
      end.disabled = true;
      if (display) display.disabled = true;
    } else {
      single.disabled = true;
      start.disabled = false;
      end.disabled = false;
      if (display) display.disabled = false;
    }
  }
  // เรียกครั้งแรกให้ตรงตามค่า checked เริ่มต้น
  toggleDatePickers();
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
        sub.innerHTML = '<option value="" selected>-- เลือกหมวดย่อย --</option>';
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
        sub.dataset.current = String(sub.value || "").trim();
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
        sub.innerHTML = '<option value="" selected>-- เลือกหมวดย่อย --</option>';
        sub.value = "";
      }
    }

    function redirectToSelectedSub() {
      const subVal = String(sub.value || "").trim();
      if (!subVal) return;

      const selectedOption = sub.options[sub.selectedIndex];
      const target = buildTemplateUrl(selectedOption?.dataset?.url || "");

      if (!target || target === "#") return;
      window.location.href = target + (target.includes("?") ? "&" : "?") +
        "main=" + encodeURIComponent(String(main.value || "").trim()) +
        "&sub=" + encodeURIComponent(subVal);
    }

    main.addEventListener("change", () => {
      if (window.CATEGORY_LOCKED_BY_STATUS) return;
      sub.dataset.current = "";
      syncUI(false);
      redirectToSelectedSub();
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
      const subVal = String(sub.value || "").trim();
      sub.dataset.current = subVal;
      redirectToSelectedSub();
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