<?php 
// ต้องวางตรงนี้! บรรทัดแรกของไฟล์
$CURRENT_MAIN = $_GET['main'] ?? 'internal';
$CURRENT_SUB  = $_GET['sub']  ?? 'หนังสือเรียนเชิญวิทยากร';

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'internal';
}
?>
<!--หนังสือเรียนเชิญวิทยากร Pro_letter/documents/infor_invite.php  -->
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
    $currentQuestionPath = '/documents/' . basename(__FILE__);

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

        if ($url === $currentQuestionPath) {
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


/* =========================================================
   โหลดข้อมูลเดิมตอนกลับมาแก้ไข
   ใช้เมื่อ URL มี ?id=DOCUMENT_ID
   ========================================================= */
$documentId = (int)($_GET['id'] ?? $_POST['document_id'] ?? 0);
$isEdit = $documentId > 0;

$formData = [];
$formDataByKey = [];
$docRow = [];

$defaultFaculty = 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
$defaultDepartment = 'เทคโนโลยีสารสนเทศ';

if ($isEdit) {
    try {
        $pdo = db();

        $stmtDoc = $pdo->prepare("SELECT * FROM documents WHERE document_id = :id LIMIT 1");
        $stmtDoc->execute([':id' => $documentId]);
        $docRow = $stmtDoc->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmtVals = $pdo->prepare("
            SELECT 
                dv.field_id,
                dv.value_text,
                tf.field_key
            FROM document_values dv
            LEFT JOIN template_fields tf ON tf.field_id = dv.field_id
            WHERE dv.document_id = :id
        ");
        $stmtVals->execute([':id' => $documentId]);

        foreach ($stmtVals->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fid = (int)($row['field_id'] ?? 0);
            $val = (string)($row['value_text'] ?? '');

            if ($fid > 0) {
                $formData[$fid] = $val;
            }

            $key = trim((string)($row['field_key'] ?? ''));
            if ($key !== '') {
                $formDataByKey[$key] = $val;
            }
        }
    } catch (Throwable $e) {
        // ถ้าโหลดข้อมูลเดิมไม่ได้ ให้เปิดฟอร์มต่อได้ แต่ไม่ให้หน้าเว็บพัง
        $formData = [];
        $formDataByKey = [];
        $docRow = [];
    }
}

$subjectValue = $formData[14] ?? ($docRow['subject'] ?? '');

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

$toPersonValue = $formData[26] ?? '';
$docDateValue = $formData[1] ?? ($docRow['doc_date'] ?? '');
$projectTitleValue = $formData[5] ?? '';
$inviteStatementValue = $formDataByKey['invite_statement'] ?? '';
$objectiveValue = $formData[25] ?? '';
$eventDateValue = $formData[6] ?? ($formData[16] ?? '');
$locationValue = $formData[7] ?? '';
$facultyValue = $formData[10] ?? $defaultFaculty;
$departmentValue = $formData[11] ?? $defaultDepartment;
$eventTimeValue = $formDataByKey['event_time'] ?? '';

$timeStartValue = '';
$timeEndValue = '';
if ($eventTimeValue !== '') {
    $timeParts = preg_split('/\s*(?:-|ถึง)\s*/u', $eventTimeValue);
    $timeStartValue = trim($timeParts[0] ?? '');
    $timeEndValue = trim($timeParts[1] ?? '');
}

$formAction = $isEdit ? '/Pro_letter/documents/update_memo.php' : '/Pro_letter/documents/save_memo.php';
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

  <form method="post" action="<?= h($formAction) ?>" id="memoForm">
    <input type="hidden" name="department_id" id="selectedDepartmentId" value="<?= (int)$currentUserDepartmentId ?>">
    <?php if ($isEdit): ?>
    <input type="hidden" name="document_id" value="<?= (int)$documentId ?>">
    <input type="hidden" name="mode" value="update">
    <input type="hidden" name="redirect_back"
      value="/Pro_letter/form_Memo/form_memo_invite_speaker.php?id=<?= (int)$documentId ?>">
    <?php else: ?>
    <input type="hidden" name="mode" value="create">
    <?php endif; ?>
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="document_type_name" value="หนังสือเรียนเชิญวิทยากร">
    <input type="hidden" name="purpose" value="invite_speaker_student">
    <input type="hidden" name="form_type" value="invite">
    <input type="hidden" name="document_type" value="infor_invite">
    <input type="hidden" name="target_form" value="form_memo_invite_speaker.php">
    <input type="hidden" name="redirect_to" value="form_memo_invite_speaker.php">
    <input type="hidden" name="template_page" value="form_memo_invite_speaker.php">
    <!-- กล่องเนื้อหา -->
    <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
      <h1 class="text-center font-bold mb-6 text-black">
        แบบฟอร์มหนังสือเรียนเชิญวิทยากร
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
        <label class="lbl whitespace-nowrap w-22 pt-2">
          1. เรื่อง :
        </label>
        <div class="w-full">
          <input type="text" name="subject" id="subjectInput" data-spell-field="subject"
            class="w-full border rounded-md p-2" placeholder="ขอเรียนเชิญเป็นวิทยากรบรรยาย"
            value="<?= h($subjectValue) ?>">

          <div id="subjectInputSpellBox" class="spell-box hidden"></div>
          <div id="subjectInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 2. เรียน -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-22 pt-2">
          2. เรียน :
        </label>
        <div class="w-full">
          <textarea name="to_person" id="toPerson" data-spell-field="to_person" rows="3"
            class="w-full border rounded-md p-2"
            placeholder="คุณภาคิน วรากุล Astro Media Hub ตำแหน่ง บรรณาธิการข่าววิทยาศาสตร์อวกาศ แอสโทร มีเดีย ฮับ รายงานข่าวสาร ปรากฏการณ์ดาราศาสตร์ พร้อมดูแลสื่อ Pakin Space"><?= h($toPersonValue) ?></textarea>

          <div id="toPersonSpellBox" class="spell-box hidden"></div>
          <div id="toPersonSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 3. วัน เดือน ปี -->
      <?php
      $docDateSaved = trim((string)$docDateValue);
      $hasSavedDocDateField = array_key_exists(1, $formData);
      $docDateOption = ($hasSavedDocDateField && $docDateSaved === '') ? 'no_date' : 'use_date';
      ?>
      <div class="mb-4">
        <div class="flex flex-col gap-2">
          <label class="lbl text-gray-800 whitespace-nowrap" for="docDateDisplay">
            3. วัน เดือน ปี :
          </label>

          <div class="flex items-center gap-3 flex-nowrap pl-4 w-full overflow-x-auto">
            <label class="flex items-center gap-2 text-gray-800 whitespace-nowrap shrink-0">
              <input type="radio" name="doc_date_option" id="docDateUse" value="use_date" class="accent-black"
                <?= ($docDateOption === 'use_date') ? 'checked' : '' ?>>
              วันที่
            </label>

            <div class="relative shrink-0" id="docDatePickerWrap">
              <input type="text" id="docDateDisplay" value="<?= h($docDateSaved) ?>"
                class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly />
              <input type="hidden" name="doc_date" id="docDate" value="<?= h($docDateSaved) ?>" />
              <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>

            <label class="lbl text-gray-800 whitespace-nowrap shrink-0">ที่ต้องการให้ปรากฏบนบันทึกข้อความ</label>
          </div>

          <label class="flex items-center gap-2 text-gray-800 whitespace-nowrap pl-4">
            <input type="radio" name="doc_date_option" id="docDateNone" value="no_date" class="accent-black"
              <?= ($docDateOption === 'no_date') ? 'checked' : '' ?>>
            ไม่ประสงค์ใส่วันที่
          </label>
        </div>
      </div>


      <!-- 4. ชื่อโครงการ / ชื่ออบรม : -->
      <div class="mb-4 flex items-start gap-10">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          4. ชื่อโครงการ / ชื่ออบรม :
        </label>
        <div class="w-full">
          <textarea name="thesis_title" id="projectTitle" data-spell-field="project_title" rows="2"
            class="w-full border rounded-md p-2"
            placeholder="โครงการอบรมเชิงสัมมนา เรื่อง อวกาศกับปัญญาประดิษฐ์: อนาคตและความท้าทาย"><?= h($projectTitleValue) ?></textarea>

          <div id="projectTitleSpellBox" class="spell-box hidden"></div>

          <div id="projectTitleSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 5. คำกล่าวเชิญ -->
      <div class="mb-4 flex items-start gap-10">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          5. คำกล่าวเชิญ :
        </label>
        <div class="w-full">
          <textarea name="invite_statement" id="inviteStatement" data-spell-field="invite_statement" rows="4"
            class="w-full border rounded-md p-2"
            placeholder="เห็นว่าท่านเป็นผู้มีความเชี่ยวชาญและมีประสบการณ์สูง ในสาขาวิชาชีพดังกล่าว ซึ่งจะเป็นประโยชน์แก่นักศึกษาผู้เข้าร่วมโครงการเป็นอย่างดี"><?= h($inviteStatementValue) ?></textarea>

          <div id="inviteStatementSpellBox" class="spell-box hidden"></div>

          <div id="inviteStatementSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <div class="mb-4 flex items-start gap-16">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          6. วัตถุประสงค์ :
        </label>
        <div class="w-full">
          <textarea name="objective" id="objectiveInput" data-spell-field="objective" rows="2"
            class="w-full border rounded-md p-2"
            placeholder="เพื่อให้นักศึกษาภาควิชาเทคโนโลยีสารสนเทศ ได้รับฟังบรรยายจากผู้เชี่ยวชาญในวงการอวกาศและปัญญาประดิษฐ์ ในการเสริมสร้างแรงบันดาลใจและความคิดสร้างสรรค์ เพื่อเป็นแนวทางในการศึกษาและการประกอบอาชีพของนักศึกษาต่อไปในอนาคต"><?= h($objectiveValue) ?></textarea>

          <div id="objectiveInputSpellBox" class="spell-box hidden"></div>

          <div id="objectiveInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>


      <!-- 7. วันที่จัดกิจกรรม -->
      <div class="mb-6 flex flex-col gap-3">

        <!-- 🔹 บรรทัดที่ 1 -->
        <div class="flex items-start gap-4">
          <label class="lbl whitespace-nowrap w-48 pt-2">
            7. วันที่จัดกิจกรรม :
          </label>

          <div class="flex items-center gap-4">
            <!-- วันที่จัดกิจกรรม เลือกวันเดียว -->
            <div class="relative">
              <input type="text" id="eventDateDisplay" class="border rounded-md p-2 w-44 pr-10 cursor-pointer"
                placeholder="เลือกวันที่" value="<?= h($eventDateValue) ?>" readonly>
              <input type="hidden" name="intern_period" id="internPeriod" value="<?= h($eventDateValue) ?>">
              <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
        </div>

        <!-- 🔹 บรรทัดที่ 2 (เวลา) -->
        <div class="flex items-start gap-4">
          <div class="w-48"></div>

        </div>
        <div class="flex items-center  gap-4 " style="margin-left: 15px;">
          <label class="lbl whitespace-nowrap w-44">
            เวลา :
          </label>

          <input type="time" id="timeStart" class="border rounded-md p-2 w-40" value="<?= h($timeStartValue) ?>">

          <span>ถึง</span>

          <input type="time" id="timeEnd" class="border rounded-md p-2 w-40" value="<?= h($timeEndValue) ?>">

          <input type="hidden" name="event_time" id="eventTime" value="<?= h($eventTimeValue) ?>">
        </div>

      </div>

      <div class="mb-4 flex items-start gap-16">
        <label class="lbl whitespace-nowrap w-48 pt-2">
          8. สถานที่จัดกิจกรรม :
        </label>
        <div class="w-full">
          <textarea name="location_input" rows="2" id="locationInput" data-spell-field="location_input"
            class="w-full border rounded-md p-2"
            placeholder="ณ ห้องประชุม ๒๑๖ อาคารบริหาร มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี"><?= h($locationValue) ?></textarea>

          <div id="locationInputSpellBox" class="spell-box hidden"></div>

          <div id="locationInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
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
  const profileBtn = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");

  if (profileBtn && profileMenu) {
    profileBtn.addEventListener("click", function(event) {
      event.stopPropagation();
      profileMenu.classList.toggle("hidden");
    });

    document.addEventListener("click", function(event) {
      if (!profileBtn.contains(event.target) && !profileMenu.contains(event.target)) {
        profileMenu.classList.add("hidden");
      }
    });
  }

  function closeMenu() {
    if (profileMenu) {
      profileMenu.classList.add("hidden");
    }
  }
  </script>
  <script>
  const byId = (id) => document.getElementById(id);
  const form = byId("memoForm");
  window.memoInviteForm = form;

  const subjectInput = byId("subjectInput");
  const toPerson = byId("toPerson");
  const projectTitle = byId("projectTitle");
  const inviteStatement = byId("inviteStatement");
  const objectiveInput = byId("objectiveInput");
  const locationInput = byId("locationInput");

  const spellFields = [
    subjectInput,
    toPerson,
    projectTitle,
    inviteStatement,
    objectiveInput,
    locationInput
  ].filter(Boolean);

  const spellState = {
    subject: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    to_person: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    project_title: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    invite_statement: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    objective: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    },
    location_input: {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ""
    }
  };

  const spellCache = {};
  const approvedWords = new Set();
  const approvedTexts = {};
  const correctedTexts = {};

  const SPELL_API_URL = "https://checkspell-api.onrender.com/api/spell-check";

  function getFieldName(el) {
    return el?.dataset?.spellField || "";
  }

  function getSpellBoxByField(el) {
    if (!el) return null;

    const map = {
      subjectInput: "subjectInputSpellBox",
      toPerson: "toPersonSpellBox",
      projectTitle: "projectTitleSpellBox",
      inviteStatement: "inviteStatementSpellBox",
      objectiveInput: "objectiveInputSpellBox",
      locationInput: "locationInputSpellBox"
    };

    return byId(map[el.id]);
  }

  function getSpellLoadingByField(el) {
    if (!el) return null;

    const map = {
      subjectInput: "subjectInputSpellLoading",
      toPerson: "toPersonSpellLoading",
      projectTitle: "projectTitleSpellLoading",
      inviteStatement: "inviteStatementSpellLoading",
      objectiveInput: "objectiveInputSpellLoading",
      locationInput: "locationInputSpellLoading"
    };

    return byId(map[el.id]);
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

    const text = (el.value || "").trim();
    if (text !== "") {
      el.classList.add("spell-ok");
    }
  }

  function showSpellApiError(el, message = "ไม่สามารถเชื่อมต่อระบบตรวจคำผิดได้ กรุณาตรวจสอบว่า API เปิดอยู่") {
    clearSpellResult(el);
    el.classList.add("spell-error");

    const box = getSpellBoxByField(el);
    if (!box) return;

    box.innerHTML = `
      <div class="spell-result-box">
        <div class="spell-warning">${escapeHtml(message)}</div>
      </div>
    `;
    box.classList.remove("hidden");
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

  function normalizeErrorItem(item) {
    if (!item) return null;

    const wrongWord = String(
      item.wrongWord ??
      item.wrong_word ??
      item.word ??
      item.token ??
      item.error ??
      item.text ??
      ""
    ).trim();

    if (!wrongWord) return null;

    let suggestions = item.suggestions ?? item.suggestion ?? item.correct ?? item.candidates ?? [];

    if (typeof suggestions === "string") {
      suggestions = [suggestions];
    }

    if (!Array.isArray(suggestions)) {
      suggestions = [];
    }

    suggestions = suggestions
      .map(s => String(s || "").trim())
      .filter(Boolean)
      .filter(s => s !== wrongWord)
      .filter((s, i, arr) => arr.indexOf(s) === i)
      .slice(0, 5);

    return {
      wrongWord,
      suggestions
    };
  }

  function normalizeErrors(errors = [], originalText = "") {
    if (!Array.isArray(errors)) return [];

    const seen = new Set();
    const normalized = [];

    for (const item of errors) {
      const data = normalizeErrorItem(item);
      if (!data) continue;

      const wrongWord = data.wrongWord;
      if (originalText && !originalText.includes(wrongWord)) continue;
      if (seen.has(wrongWord)) continue;

      seen.add(wrongWord);
      normalized.push(data);
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
      apiError: false,
      errors: [],
      lastText: text
    };

    showSpellOk(el);
  }

  function shouldCheckSpell(el) {
    if (!el) return false;
    if (el.disabled || el.readOnly) return false;
    return true;
  }

  function showSpellError(el, errors = []) {
    clearSpellResult(el);
    el.classList.add("spell-error");

    const box = getSpellBoxByField(el);
    if (!box) return;

    errors = normalizeErrors(errors, el.value || "");

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
      } else {
        html += `<div class="spell-help-text">ไม่มีคำแนะนำอัตโนมัติ</div>`;
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
          "http://127.0.0.1:8001" : "https://checkspell-api.onrender.com";

        const SPELL_CHECK_API_URL = `${SPELL_API_BASE_URL}/api/spell-check`;
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
    let allPassed = true;

    for (const el of spellFields) {
      if (!el || !shouldCheckSpell(el)) continue;

      const text = (el.value || "").trim();
      if (!text) continue;

      const fieldName = getFieldName(el);
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

      const passed = await checkSpellField(el);
      if (!passed) {
        allPassed = false;
      }
    }

    for (const key in spellState) {
      const state = spellState[key];
      const remainingErrors = filterApprovedErrors(state.errors || []);

      if (state.apiError) {
        alert("ระบบตรวจคำผิดเชื่อมต่อไม่ได้ กรุณาตรวจสอบว่า API เปิดอยู่ แล้วลองกดดำเนินการอีกครั้ง");
        return false;
      }

      if (state.checked && state.hasError && remainingErrors.length > 0) {
        alert("กรุณาเลือกคำแนะนำ หรือกดใช้ข้อความเดิมก่อนดำเนินการ");
        return false;
      }
    }

    return allPassed;
  }

  document.addEventListener("click", (e) => {
    const ignoreBtn = e.target.closest(".spell-ignore-btn");
    if (!ignoreBtn) return;

    const target = byId(ignoreBtn.dataset.target);
    if (!target) return;

    const fieldName = getFieldName(target);
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

    const beforeText = target.value || "";
    const afterText = replaceWholeWordOnce(beforeText, wrongWord, word);
    target.value = afterText;

    const fieldName = getFieldName(target);
    const currentText = (target.value || "").trim();

    correctedTexts[fieldName] = currentText;
    approvedWords.add(word);

    setSpellPassed(target, fieldName, currentText, false);
  });

  spellFields.forEach((el) => {
    // ไม่ตรวจคำผิดระหว่างพิมพ์หรือเมื่อออกจากช่อง
    // ให้ตรวจเฉพาะตอนกดปุ่ม "ดำเนินการ" ผ่าน checkAllSpellFields() เท่านั้น
    el.addEventListener("input", () => {
      const fieldName = getFieldName(el);
      const state = spellState[fieldName];

      if (state) {
        state.checked = false;
        state.hasError = false;
        state.apiError = false;
        state.errors = [];
        state.lastText = "";
      }

      clearSpellResult(el);
    });
  });

  function getTrimValue(el) {
    return (el?.value || "").trim();
  }

  function getErrorTarget(el) {
    if (!el) return null;

    if (el.id === "docDate") return byId("docDateDisplay");
    if (el.id === "internPeriod") return byId("eventDateDisplay");

    return el;
  }

  function clearFieldError(el) {
    const target = getErrorTarget(el);
    if (!target) return;

    target.classList.remove("error", "shake");

    const next = target.nextElementSibling;
    if (next && next.classList.contains("hint")) {
      next.remove();
    }
  }

  function setFieldError(el, message) {
    const target = getErrorTarget(el);
    if (!target) return null;

    clearFieldError(target);

    target.classList.add("error", "shake");
    setTimeout(() => target.classList.remove("shake"), 450);

    const hint = document.createElement("div");
    hint.className = "hint";
    hint.innerHTML = `
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
        viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
      </svg>
      <span>${message}</span>
    `;

    target.insertAdjacentElement("afterend", hint);
    return target;
  }

  function scrollToFirstError(el) {
    if (!el) return;

    el.scrollIntoView({
      behavior: "smooth",
      block: "center"
    });

    setTimeout(() => {
      try {
        el.focus({
          preventScroll: true
        });
      } catch (err) {
        el.focus();
      }
    }, 350);
  }

  function validateRequiredFields() {
    if (typeof updateEventTime === "function") {
      updateEventTime();
    }


    const mainCategory = byId("mainCategory");
    const subCategory = byId("subCategory");
    const faculty = byId("faculty");
    const dept = byId("dept");
    const selectedDepartmentId = byId("selectedDepartmentId");
    const docDate = byId("docDate");
    const docDateDisplay = byId("docDateDisplay");
    const docDateNone = byId("docDateNone");
    const eventDate = byId("internPeriod");
    const timeStartInput = byId("timeStart");
    const timeEndInput = byId("timeEnd");

    const requiredFields = [
      [mainCategory, "กรุณาเลือกหมวดหลัก"],
      [subCategory, "กรุณาเลือกหมวดย่อย"],
      [faculty, "กรุณาเลือกคณะ"],
      [dept, "กรุณาเลือกภาควิชา"],
      [subjectInput, "กรุณากรอกเรื่อง"],
      [toPerson, "กรุณากรอกข้อมูลผู้รับหนังสือ"],
      [projectTitle, "กรุณากรอกชื่อโครงการ / ชื่ออบรม"],
      [inviteStatement, "กรุณากรอกคำกล่าวเชิญ"],
      [objectiveInput, "กรุณากรอกวัตถุประสงค์"],
      [eventDate, "กรุณาเลือกวันที่จัดกิจกรรม"],
      [timeStartInput, "กรุณาเลือกเวลาเริ่มกิจกรรม"],
      [timeEndInput, "กรุณาเลือกเวลาสิ้นสุดกิจกรรม"],
      [locationInput, "กรุณากรอกสถานที่จัดกิจกรรม"]
    ];

    let firstError = null;

    requiredFields.forEach(([el, message]) => {
      clearFieldError(el);

      if (!getTrimValue(el)) {
        const target = setFieldError(el, message);
        if (!firstError && target) firstError = target;
      }
    });

    clearFieldError(docDate);
    if (!docDateNone?.checked && !getTrimValue(docDate)) {
      const target = setFieldError(docDateDisplay || docDate, "กรุณาเลือกวัน เดือน ปี");
      if (!firstError && target) firstError = target;
    }

    clearFieldError(selectedDepartmentId);
    if (!getTrimValue(selectedDepartmentId)) {
      const target = setFieldError(dept, "กรุณาเลือกภาควิชาที่ถูกต้อง");
      if (!firstError && target) firstError = target;
    }

    const startTime = getTrimValue(timeStartInput);
    const endTime = getTrimValue(timeEndInput);
    if (startTime && endTime && endTime <= startTime) {
      const target = setFieldError(timeEndInput, "เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม");
      if (!firstError && target) firstError = target;
    }

    if (firstError) {
      scrollToFirstError(firstError);
      return false;
    }

    return true;
  }

  [
    byId("mainCategory"),
    byId("subCategory"),
    byId("faculty"),
    byId("dept"),
    subjectInput,
    toPerson,
    byId("docDateDisplay"),
    byId("docDate"),
    projectTitle,
    inviteStatement,
    objectiveInput,
    byId("eventDateDisplay"),
    byId("internPeriod"),
    byId("timeStart"),
    byId("timeEnd"),
    locationInput
  ].filter(Boolean).forEach((el) => {
    el.addEventListener("input", () => clearFieldError(el));
    el.addEventListener("change", () => clearFieldError(el));
  });

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (typeof syncDocDateOptionUI === "function") {
      syncDocDateOptionUI();
    }

    const docDateNone = byId("docDateNone");
    const docDateDisplay = byId("docDateDisplay");
    const docDateHidden = byId("docDate");

    if (docDateNone?.checked) {
      if (docDateDisplay) docDateDisplay.value = "";
      if (docDateHidden) docDateHidden.value = "";
    } else if (typeof docPicker !== "undefined" && docPicker?.selectedDates?. [0] && docDateHidden) {
      docDateHidden.value = formatYMDInvite(docPicker.selectedDates[0]);
    }

    if (!validateRequiredFields()) return;

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;

    HTMLFormElement.prototype.submit.call(form);
  });
  </script>


  <script>
  flatpickr.localize(flatpickr.l10ns.th);

  const monthsTH = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน",
    "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม",
    "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
  ];

  function formatThaiSingleDate(date) {
    const day = date.getDate();
    const month = monthsTH[date.getMonth()];
    const year = date.getFullYear() + 543;

    return `${day} ${month} ${year}`;
  }

  function parseDocDateInvite(value) {
    const raw = String(value || "").trim();
    if (!raw || raw === "0000-00-00") return null;

    let m = raw.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    if (m) return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));

    m = raw.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (m) return new Date(Number(m[3]), Number(m[2]) - 1, Number(m[1]));

    return null;
  }

  function formatYMDInvite(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  }

  const docDateUse = document.getElementById("docDateUse");
  const docDateNone = document.getElementById("docDateNone");
  const docDateDisplay = document.getElementById("docDateDisplay");
  const docDateHidden = document.getElementById("docDate");

  const docPicker = flatpickr("#docDateDisplay", {
    dateFormat: "Y-m-d",
    disableMobile: true,
    allowInput: false,
    clickOpens: true,
    onReady: function(selectedDates, dateStr, instance) {
      const savedDate = parseDocDateInvite(docDateHidden?.value);
      if (savedDate) {
        instance.setDate(savedDate, false);
        instance.input.value = formatThaiSingleDate(savedDate);
        if (docDateHidden) docDateHidden.value = formatYMDInvite(savedDate);
      }
    },
    onChange: function(selectedDates, dateStr, instance) {
      if (selectedDates.length > 0) {
        const selectedDate = selectedDates[0];
        instance.input.value = formatThaiSingleDate(selectedDate);
        if (docDateHidden) docDateHidden.value = formatYMDInvite(selectedDate);
      }
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

      if (docDateHidden) {
        docDateHidden.value = "";
      }

      docPicker?.clear();
      docPicker?.set("clickOpens", false);
      docDateDisplay?.classList.remove("error", "shake");
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

  flatpickr("#eventDateDisplay", {
    dateFormat: "d/m/Y",
    disableMobile: true,
    allowInput: false,
    onChange: function(selectedDates) {
      const selectedDate = selectedDates[0];
      if (!selectedDate) return;

      document.getElementById("internPeriod").value = formatThaiSingleDate(selectedDate);
    }
  });
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
      const selectedText = String(
        selectedValue || sub.dataset.current || sub.value || ""
      ).trim();
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
        sub.dataset.current = sub.value || "";
      }
    }

    function syncUI(keepCurrentSub = false) {
      const mainVal = String(main.value || "").trim().toLowerCase();
      const currentSub = keepCurrentSub ?
        String(sub.dataset.current || sub.value || "").trim() :
        "";

      if (mainVal === "internal" || mainVal === "external") {
        if (!window.CATEGORY_LOCKED_BY_STATUS) {
          sub.disabled = false;
        }

        if (!keepCurrentSub) {
          sub.dataset.current = "";
          sub.value = "";
        }

        renderSubOptions(mainVal, currentSub);
      } else {
        sub.disabled = true;
        sub.dataset.current = "";
        sub.innerHTML = '<option value="" selected>-- เลือกหมวดย่อย --</option>';
        sub.value = "";
      }
    }

    main.addEventListener("change", () => {
      if (window.CATEGORY_LOCKED_BY_STATUS) return;
      sub.dataset.current = "";
      syncUI(false);
      sub.dispatchEvent(new Event("change", { bubbles: true }));
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

      if (!subVal) return;

      const selectedOption = sub.options[sub.selectedIndex];
      const target = buildTemplateUrl(selectedOption?.dataset?.url || "");

      if (!target || target === "#") return;

      const mainVal = String(main.value || "").trim();

      let targetPath = "";
      try {
        targetPath = new URL(target, window.location.origin).pathname;
      } catch (err) {
        targetPath = "";
      }

      const currentPath = window.location.pathname;
      const targetFile = targetPath.split("/").pop();
      const currentFile = currentPath.split("/").pop();

      // ถ้าเลือกหมวดย่อยที่เป็นไฟล์หน้าเดิมอยู่แล้ว ไม่ต้อง redirect
      // กันหน้ารีแล้วค่าหมวดย่อยหลุดกลับไปเป็น "-- เลือกหมวดย่อย --"
      if (targetPath === currentPath || targetFile === currentFile) {
        sub.value = subVal;
        sub.dataset.current = subVal;

        if (typeof clearFieldError === "function") {
          clearFieldError(sub);
        }

        return;
      }

      const separator = target.includes("?") ? "&" : "?";
      window.location.href = target + separator + "main=" + encodeURIComponent(mainVal) + "&sub=" +
        encodeURIComponent(subVal);
    });

    window.addEventListener("pageshow", () => {
      syncUI(true);
    });

    syncUI(true);
  });
  </script>
  <script>
  const timeStart = document.getElementById("timeStart");
  const timeEnd = document.getElementById("timeEnd");
  const eventTime = document.getElementById("eventTime");

  function updateEventTime() {
    if (!eventTime) return;

    const start = timeStart ? timeStart.value.trim() : "";
    const end = timeEnd ? timeEnd.value.trim() : "";

    if (start && end) {
      eventTime.value = start + " - " + end;
    } else if (start) {
      eventTime.value = start;
    } else if (end) {
      eventTime.value = end;
    } else {
      eventTime.value = "";
    }
  }

  if (timeStart) {
    timeStart.addEventListener("change", updateEventTime);
    timeStart.addEventListener("input", updateEventTime);
  }

  if (timeEnd) {
    timeEnd.addEventListener("change", updateEventTime);
    timeEnd.addEventListener("input", updateEventTime);
  }

  const memoInviteFormForTime = document.getElementById("memoForm");
  if (memoInviteFormForTime) {
    memoInviteFormForTime.addEventListener("submit", updateEventTime);
  }
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