<?php 
// ต้องวางตรงนี้! บรรทัดแรกของไฟล์
$CURRENT_MAIN = $_GET['main'] ?? 'internal';
$CURRENT_SUB  = $_GET['sub']  ?? 'ขอเข้าไปจัดกิจกรรมโครงการ';

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'internal';
}
?>
<!--"ขอเข้าไปจัดกิจกรรมโครงการ", "/Pro_letter/documents/infor_project_activity.php" -->
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

/* ===== โหลดข้อมูลเดิม กรณีแก้ไขเอกสาร ===== */
$editDocId = (int)($_GET['id'] ?? 0);
$isEditMode = $editDocId > 0;

$editDocument = [];
$editValuesByKey = [];
$editValuesByFieldId = [];

if ($isEditMode) {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT *
        FROM documents
        WHERE document_id = :id
          AND owner_id = :owner_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $editDocId,
        ':owner_id' => (int)$_SESSION['user_id']
    ]);
    $editDocument = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$editDocument) {
        header("Location: /Pro_letter/user/home.php?err=notfound");
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT tf.field_key, dv.value_text
        FROM document_values dv
        INNER JOIN template_fields tf ON tf.field_id = dv.field_id
        WHERE dv.document_id = :id
          AND tf.field_key IS NOT NULL
          AND tf.field_key <> ''
    ");
    $stmt->execute([':id' => $editDocId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $editValuesByKey[$row['field_key']] = $row['value_text'] ?? '';
    }

    $stmt = $pdo->prepare("
        SELECT field_id, value_text
        FROM document_values
        WHERE document_id = :id
    ");
    $stmt->execute([':id' => $editDocId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $editValuesByFieldId[(int)$row['field_id']] = $row['value_text'] ?? '';
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

$hasSavedDocDateField = array_key_exists(1, $editValuesByFieldId);
$projectDocDate = $hasSavedDocDateField ? trim((string)($editValuesByFieldId[1] ?? '')) : ($editDocument['doc_date'] ?? date('Y-m-d'));
$projectDocDateOption = ($hasSavedDocDateField && $projectDocDate === '') ? 'no_date' : 'use_date';

$projectSubject = $editValuesByKey['project_subject'] ?? ($editDocument['subject'] ?? '');
$projectToPerson = $editValuesByKey['project_to_person'] ?? '';
$projectActivityPlace = $editValuesByKey['project_activity_place'] ?? '';
$projectMainProject = $editValuesByKey['project_main_project'] ?? '';
$projectSubActivity = $editValuesByKey['project_sub_activity'] ?? '';
$projectObjectiveDetail = $editValuesByKey['project_objective_detail'] ?? '';
$projectTargetGroup = $editValuesByKey['project_target_group'] ?? '';
$projectParticipantCount = $editValuesByKey['project_participant_count'] ?? '';
$projectActivityPeriod = $editValuesByKey['project_activity_period'] ?? '';
$projectLecturerNames = $editValuesByKey['project_lecturer_names'] ?? '';
$projectReceiverName = $editValuesByKey['project_receiver_name'] ?? '';
$projectReceiverPosition = $editValuesByKey['project_receiver_position'] ?? '';

$formAction = $isEditMode ? '/Pro_letter/documents/update_memo.php' : 'save_memo.php';
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

  <form method="post" action="<?= h($formAction) ?>" id="memoForm">
    <input type="hidden" name="department_id" id="selectedDepartmentId" value="<?= (int)$currentUserDepartmentId ?>">
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="document_type_name" value="ขอเข้าไปจัดกิจกรรมโครงการ">
    <input type="hidden" name="purpose" value="project_activity">
    <input type="hidden" name="document_type" value="infor_project_activity">
    <input type="hidden" name="target_form" value="form_memo_project_activity.php">
    <input type="hidden" name="redirect_to" value="form_memo_project_activity.php">
    <input type="hidden" name="mode" value="<?= $isEditMode ? 'update' : 'create' ?>">
    <?php if ($isEditMode): ?>
    <input type="hidden" name="document_id" value="<?= (int)$editDocId ?>">
    <input type="hidden" name="redirect_back" value="form_memo_project_activity.php">
    <?php endif; ?>
    <!-- กล่องเนื้อหา -->
    <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
      <h1 class="text-center font-bold mb-6 text-black">
        แบบฟอร์มขอเข้าไปจัดกิจกรรมโครงการ
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



      <!-- 1. วัน เดือน ปี ที่ต้องการให้ปรากฎบนบันทึกข้อความ -->
      <div class="mb-4">
        <div class="flex flex-col gap-2">
          <label class="lbl text-gray-800 whitespace-nowrap" for="docDateDisplay">
            1. วัน เดือน ปี :
          </label>

          <div class="flex items-center gap-3 flex-nowrap pl-4 w-full overflow-x-auto">
            <label class="flex items-center gap-2 text-gray-800 whitespace-nowrap shrink-0">
              <input type="radio" name="doc_date_option" id="docDateUse" value="use_date" class="accent-black"
                <?= ($projectDocDateOption === 'use_date') ? 'checked' : '' ?>>
              วันที่
            </label>

            <div class="relative shrink-0" id="docDatePickerWrap">
              <input type="text" id="docDateDisplay" value="<?= h($projectDocDate) ?>"
                class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly>
              <input type="hidden" name="doc_date" id="docDate" value="<?= h($projectDocDate) ?>">

              <svg class="pointer-events-none absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>

            <label class="lbl text-gray-800 whitespace-nowrap shrink-0">
              ที่ต้องการให้ปรากฎบนบันทึกข้อความ
            </label>
          </div>

          <label class="flex items-center gap-2 text-gray-800 whitespace-nowrap pl-4">
            <input type="radio" name="doc_date_option" id="docDateNone" value="no_date" class="accent-black"
              <?= ($projectDocDateOption === 'no_date') ? 'checked' : '' ?>>
            ไม่ประสงค์ใส่วันที่
          </label>
        </div>
      </div>

      <!-- 2. เรื่อง -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-20 pt-2">2. เรื่อง :</label>
        <div class="flex-1">
          <input type="text" name="subject" id="subjectInput" data-spell-field="subject"
            class="w-full border rounded-md p-2" value="<?= h($projectSubject) ?>"
            placeholder="เช่น ขออนุญาตดำเนินการจัดโครงการ...">
          <div id="subjectInputSpellBox" class="spell-box hidden"></div>
          <div id="subjectInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. เรียนถึง -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-20 pt-2">3. เรียนถึง :</label>

        <div class="flex-1">
          <input type="text" name="to_person" id="toPerson" data-spell-field="to_person"
            class="w-full border rounded-md p-2" value="<?= h($projectToPerson) ?>"
            placeholder="เช่น ผู้อำนวยการ / หัวหน้าหน่วยงาน / ผู้เกี่ยวข้อง">

          <div id="toPersonSpellBox" class="spell-box hidden"></div>
          <div id="toPersonSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. สถานที่จัดกิจกรรม / หน่วยงาน -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-62 shrink-0 pt-2">4. สถานที่จัดกิจกรรม / หน่วยงาน :</label>

        <div class="flex-1">
          <input type="text" name="school_name" id="schoolName" data-spell-field="activity_place"
            class="w-full border rounded-md p-2" value="<?= h($projectActivityPlace) ?>"
            placeholder="เช่น ห้องประชุม..., มหาวิทยาลัย..., บริษัท..., โรงเรียน..., หน่วยงาน...">

          <div id="schoolNameSpellBox" class="spell-box hidden"></div>
          <div id="schoolNameSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. ชื่อโครงการหลัก -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-40 pt-2">5. ชื่อโครงการหลัก :</label>
        <div class="flex-1">
          <input type="text" name="main_project" id="mainProject" data-spell-field="main_project"
            class="w-full border rounded-md p-2" value="<?= h($projectMainProject) ?>"
            placeholder="เช่น โครงการบริการวิชาการด้านเทคโนโลยีสารสนเทศ">
          <div id="mainProjectSpellBox" class="spell-box hidden"></div>
          <div id="mainProjectSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 6. ชื่อกิจกรรมย่อย / หัวข้ออบรม -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-40 pt-2">6. ชื่อกิจกรรมย่อย :</label>
        <div class="flex-1">
          <input type="text" name="sub_activity" id="subActivity" data-spell-field="sub_activity"
            class="w-full border rounded-md p-2" value="<?= h($projectSubActivity) ?>"
            placeholder="เช่น กิจกรรมอบรมเชิงปฏิบัติการการใช้งานโปรแกรม...">
          <div id="subActivitySpellBox" class="spell-box hidden"></div>
          <div id="subActivitySpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 7. วัตถุประสงค์ / รายละเอียดกิจกรรม -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-40 pt-2">7. วัตถุประสงค์ :</label>
        <div class="flex-1">
          <textarea name="objective_detail" id="objectiveDetail" data-spell-field="objective_detail" rows="3"
            class="w-full border rounded-md p-2"
            placeholder="เช่น เพื่อส่งเสริมความรู้และทักษะด้านเทคโนโลยีสารสนเทศให้แก่ผู้เข้าร่วมกิจกรรม"><?= h($projectObjectiveDetail) ?></textarea>
          <div id="objectiveDetailSpellBox" class="spell-box hidden"></div>
          <div id="objectiveDetailSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 8. กลุ่มเป้าหมาย -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-40 pt-2">8. กลุ่มเป้าหมาย :</label>
        <div class="flex-1">
          <input type="text" name="target_group" id="targetGroup" data-spell-field="target_group"
            class="w-full border rounded-md p-2" value="<?= h($projectTargetGroup) ?>"
            placeholder="กลุ่มเป้าหมาย เช่น นักเรียน นักศึกษา บุคลากร หรือประชาชนทั่วไป">
          <div id="targetGroupSpellBox" class="spell-box hidden"></div>
          <div id="targetGroupSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div><span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 9. จำนวนผู้เข้าร่วม -->
      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-40 pt-2">9. จำนวนผู้เข้าร่วม :</label>
        <div class="flex">
          <input type="number" name="participant_count" id="participantCount" class="w-50 border rounded-md p-2" min="1"
            value="<?= h($projectParticipantCount) ?>" placeholder="เช่น 30">
        </div>
      </div>

      <!-- 10. วันที่จัดกิจกรรม -->
      <div class="mb-6 flex items-start gap-4">
        <label class="lbl text-gray-800 whitespace-nowrap w-40 pt-2" id="dateLabel">
          10. วันที่จัดกิจกรรม :
        </label>

        <div class="space-y-3 text-gray-800">

          <!-- วันเดียว -->
          <div class="flex items-center gap-2">
            <input type="radio" name="date_option" value="single" id="optSingle" class="accent-[#11C2B9]" checked>

            <span>วันเดียว :</span>

            <div class="relative">
              <input type="text" name="single_date" id="singleDate"
                class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly>

              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>
          </div>

          <!-- หลายวัน -->
          <div class="flex flex-wrap items-center gap-2">
            <input type="radio" name="date_option" value="range" id="optRange" class="accent-[#11C2B9]">

            <span>หลายวัน :</span>

            <div class="relative">
              <input type="text" id="startDate" class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer"
                placeholder="เริ่มต้น" readonly>

              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>

            <span>ถึง</span>

            <div class="relative">
              <input type="text" id="endDate" class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer"
                placeholder="สิ้นสุด" readonly>

              <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
              </svg>
            </div>

            <input type="hidden" name="activity_period" id="activityPeriod" value="<?= h($projectActivityPeriod) ?>">
          </div>

        </div>
      </div>

      <!-- 11. วิทยากร / ผู้ดำเนินกิจกรรม -->
      <div class="mb-6 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          11. วิทยากร / ผู้ดำเนินกิจกรรม :
        </label>

        <div class="flex-1">
          <textarea name="lecturer_names" id="lecturerNames" data-spell-field="lecturer_names" rows="2"
            class="w-full border rounded-md p-2"
            placeholder="เช่น อาจารย์ วิทยากร หรือทีมงานผู้ดำเนินกิจกรรม"><?= h($projectLecturerNames) ?></textarea>

          <div id="lecturerNamesSpellBox" class="spell-box hidden"></div>
          <div id="lecturerNamesSpellLoading" class="spell-loading hidden">
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
  const byId = (id) => document.getElementById(id);
  const form = byId("memoForm");

  const subjectInput = byId("subjectInput");
  const toPerson = byId("toPerson");
  const schoolName = byId("schoolName");
  const mainProject = byId("mainProject");
  const subActivity = byId("subActivity");
  const objectiveDetail = byId("objectiveDetail");
  const targetGroup = byId("targetGroup");
  const participantCount = byId("participantCount");
  const optSingle = byId("optSingle");
  const optRange = byId("optRange");
  const singleDate = byId("singleDate");
  const startDate = byId("startDate");
  const endDate = byId("endDate");
  const activityPeriod = byId("activityPeriod");
  const docDateDisplay = byId("docDateDisplay");
  const docDate = byId("docDate");
  const docDateUse = byId("docDateUse");
  const docDateNone = byId("docDateNone");

  const lecturerNames = byId("lecturerNames");
  const initialActivityPeriod = <?= json_encode($projectActivityPeriod, JSON_UNESCAPED_UNICODE) ?>;

  const spellState = {
    subject: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    main_project: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    sub_activity: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    objective_detail: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    target_group: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    to_person: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    activity_place: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    lecturer_names: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
  };

  const spellCache = {};

  function setErr(el, on = true) {
    if (!el) return;
    el.classList.toggle("error", on);
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

  const monthsTH = [
    "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน",
    "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม",
    "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
  ];

  let singlePicker;
  let startPicker;
  let endPicker;

  function formatThaiDate(date) {
    const day = date.getDate();
    const month = monthsTH[date.getMonth()];
    const year = date.getFullYear() + 543;

    return `${day} ${month} ${year}`;
  }

  function formatThaiDateRange(start, end) {
    const startDay = start.getDate();
    const endDay = end.getDate();
    const startMonth = monthsTH[start.getMonth()];
    const endMonth = monthsTH[end.getMonth()];
    const startYear = start.getFullYear() + 543;
    const endYear = end.getFullYear() + 543;

    if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
      return `ในระหว่างวันที่ ${startDay} - ${endDay} ${endMonth} ${endYear}`;
    }

    return `ในระหว่างวันที่ ${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
  }

  function syncDateOptionUI() {
    if (optSingle.checked) {
      singleDate.disabled = false;
      startDate.disabled = true;
      endDate.disabled = true;

      startDate.value = "";
      endDate.value = "";
    } else {
      singleDate.disabled = true;
      startDate.disabled = false;
      endDate.disabled = false;

      singleDate.value = "";
    }

    updateActivityPeriod();
  }

  function updateActivityPeriod() {
    if (optSingle.checked) {
      const date = singlePicker?.selectedDates?. [0];

      if (!date) {
        if (!activityPeriod.value && initialActivityPeriod) {
          activityPeriod.value = initialActivityPeriod;
        }
        return;
      }

      activityPeriod.value = `วันที่ ${formatThaiDate(date)}`;
      return;
    }

    const start = startPicker?.selectedDates?. [0];
    const end = endPicker?.selectedDates?. [0];

    if (!start || !end) {
      if (!activityPeriod.value && initialActivityPeriod) {
        activityPeriod.value = initialActivityPeriod;
      }
      return;
    }

    activityPeriod.value = formatThaiDateRange(start, end);
  }

  if (window.flatpickr) {
    flatpickr.localize(flatpickr.l10ns.th);

    const docDatePicker = flatpickr("#docDateDisplay", {
      dateFormat: "Y-m-d",
      disableMobile: true,
      allowInput: false,
      defaultDate: docDate?.value || null,
      onChange: function(selectedDates, dateStr) {
        if (docDate) docDate.value = dateStr;
      }
    });

    function syncDocDateOptionUI() {
      const isNoDate = !!docDateNone?.checked;

      if (isNoDate) {
        if (docDateDisplay) {
          docDateDisplay.value = "";
          docDateDisplay.disabled = true;
          docDateDisplay.classList.add("bg-gray-100", "text-gray-400", "cursor-not-allowed");
        }

        if (docDate) {
          docDate.value = "";
        }

        if (docDatePicker) {
          docDatePicker.clear();
        }

        setErr(docDateDisplay, false);
        return;
      }

      if (docDateDisplay) {
        docDateDisplay.disabled = false;
        docDateDisplay.classList.remove("bg-gray-100", "text-gray-400", "cursor-not-allowed");
      }
    }

    docDateUse?.addEventListener("change", syncDocDateOptionUI);
    docDateNone?.addEventListener("change", syncDocDateOptionUI);
    syncDocDateOptionUI();

    singlePicker = flatpickr("#singleDate", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      allowInput: false,
      onChange: updateActivityPeriod
    });

    startPicker = flatpickr("#startDate", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      allowInput: false,
      onChange: updateActivityPeriod
    });

    endPicker = flatpickr("#endDate", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      allowInput: false,
      onChange: updateActivityPeriod
    });
  }

  if (initialActivityPeriod) {
    activityPeriod.value = initialActivityPeriod;
    if (!singleDate.value) {
      singleDate.value = initialActivityPeriod;
    }
    optSingle.checked = true;
    optRange.checked = false;
  }

  optSingle?.addEventListener("change", syncDateOptionUI);
  optRange?.addEventListener("change", syncDateOptionUI);

  syncDateOptionUI();

  function getSpellBoxByField(el) {
    if (!el) return null;
    if (el.id === "subjectInput") return byId("subjectInputSpellBox");
    if (el.id === "mainProject") return byId("mainProjectSpellBox");
    if (el.id === "subActivity") return byId("subActivitySpellBox");
    if (el.id === "objectiveDetail") return byId("objectiveDetailSpellBox");
    if (el.id === "targetGroup") return byId("targetGroupSpellBox");
    if (el.id === "toPerson") return byId("toPersonSpellBox");
    if (el.id === "schoolName") return byId("schoolNameSpellBox");
    if (el.id === "lecturerNames") return byId("lecturerNamesSpellBox");
    return null;
  }

  function getSpellLoadingByField(el) {
    if (!el) return null;
    if (el.id === "subjectInput") return byId("subjectInputSpellLoading");
    if (el.id === "mainProject") return byId("mainProjectSpellLoading");
    if (el.id === "subActivity") return byId("subActivitySpellLoading");
    if (el.id === "objectiveDetail") return byId("objectiveDetailSpellLoading");
    if (el.id === "targetGroup") return byId("targetGroupSpellLoading");
    if (el.id === "toPerson") return byId("toPersonSpellLoading");
    if (el.id === "schoolName") return byId("schoolNameSpellLoading");
    if (el.id === "lecturerNames") return byId("lecturerNamesSpellLoading");
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
        item.suggestions.map(s => String(s || "").trim())
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

  function isIgnoredForSameText(fieldName, text) {
    const state = spellState[fieldName];
    return !!(state && state.ignored && state.lastText === text);
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
      subjectInput,
      toPerson,
      schoolName,
      mainProject,
      subActivity,
      objectiveDetail,
      targetGroup,
      lecturerNames,
    ];

    for (const el of fields) {
      await checkSpellField(el);
    }

    for (const key in spellState) {
      const state = spellState[key];
      if (state.checked && state.hasError && !state.ignored) {
        alert("กรุณาเลือกคำแนะนำ หรือกดใช้ข้อความเดิมก่อนดำเนินการ");
        return false;
      }
    }

    return true;
  }

  function validateForm() {
    let firstInvalid = null;

    [
      subjectInput,
      toPerson,
      schoolName,
      mainProject,
      subActivity,
      objectiveDetail,
      targetGroup,
      participantCount,
      singleDate,
      startDate,
      endDate,
      lecturerNames,
      docDateDisplay,
    ].forEach(el => setErr(el, false));

    const requiredFields = [
      subjectInput,
      toPerson,
      schoolName,
      mainProject,
      subActivity,
      objectiveDetail,
      targetGroup,
      participantCount,
      lecturerNames,
    ];

    if (!docDateNone?.checked && !docDate?.value.trim()) {
      setErr(docDateDisplay, true);
      firstInvalid = firstInvalid || docDateDisplay;
    }
    if (optSingle.checked) {
      if (!singleDate.value.trim()) {
        setErr(singleDate, true);
        firstInvalid = firstInvalid || singleDate;
      }
    } else {
      if (!startDate.value.trim()) {
        setErr(startDate, true);
        firstInvalid = firstInvalid || startDate;
      }

      if (!endDate.value.trim()) {
        setErr(endDate, true);
        firstInvalid = firstInvalid || endDate;
      }
    }

    requiredFields.forEach(el => {
      if (!el?.value.trim()) {
        setErr(el, true);
        firstInvalid = firstInvalid || el;
      }
    });

    if (firstInvalid) {
      alert("กรุณากรอกข้อมูลให้ครบถ้วน");
      scrollFocus(firstInvalid);
      return false;
    }

    if (docDateNone?.checked) {
      if (docDateDisplay) docDateDisplay.value = "";
      if (docDate) docDate.value = "";
    }

    updateActivityPeriod();


    return true;
  }

  document.addEventListener("click", (e) => {
    const ignoreBtn = e.target.closest(".spell-ignore-btn");
    if (!ignoreBtn) return;

    const target = byId(ignoreBtn.dataset.target);
    if (!target) return;

    const fieldName = target.dataset.spellField || "";
    const currentText = (target.value || "").trim();

    spellState[fieldName] = {
      checked: true,
      hasError: false,
      ignored: true,
      errors: [],
      lastText: currentText
    };

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
    spellState[fieldName] = {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    };

    checkSpellField(target);
  });

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!validateForm()) return;

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;

    form.submit();
  });
  </script>
  <script>
  // หมวดหมู่ใช้สคริปต์ชุดเดียวด้านล่าง เพื่อไม่ให้ event ซ้ำ และให้เปลี่ยนฟอร์มตามหมวดที่เลือก
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
        sub.dataset.current = selectedText;
        sub.value = selectedText;
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
        sub.innerHTML = '<option value="" selected>-- เลือกหมวดย่อย --</option>';
        sub.value = "";
      }
    }

    function goToSelectedTemplate() {
      const subVal = String(sub.value || "").trim();
      if (!subVal) return;

      const selectedOption = sub.options[sub.selectedIndex];
      const target = buildTemplateUrl(selectedOption?.dataset?.url || "");

      if (!target || target === "#") return;

      const separator = target.includes("?") ? "&" : "?";
      window.location.href = target + separator + "main=" + encodeURIComponent(main.value || "") + "&sub=" +
        encodeURIComponent(subVal);
    }

    main.addEventListener("change", () => {
      if (window.CATEGORY_LOCKED_BY_STATUS) return;
      sub.dataset.current = "";
      syncUI(false);
      goToSelectedTemplate();
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
      goToSelectedTemplate();
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