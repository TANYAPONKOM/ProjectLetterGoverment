<?php 
// ต้องวางตรงนี้! บรรทัดแรกของไฟล์
$CURRENT_MAIN = $_GET['main'] ?? 'internal';
$CURRENT_SUB  = $_GET['sub']  ?? 'ขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ)';

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'internal';
}
?>
<!--"ขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ) ", "/Pro_letter/documents/infor_coop_evaluation.php" -->
<?php
session_start();
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
   ดึงเฉพาะ templates.is_active = 1 เพื่อให้หน้า Admin เปิด/ปิดเทมเพลตได้จริง
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
            LOWER(TRIM(template_group)) AS template_group,
            sort_order,
            is_active
        FROM templates
        WHERE CAST(COALESCE(is_active, 0) AS UNSIGNED) = 1
          AND LOWER(TRIM(template_group)) IN ('internal', 'external')
        ORDER BY sort_order ASC, template_id ASC
    ");
    $templateRows = $templateStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($templateRows as $tpl) {
        if ((int)($tpl['is_active'] ?? 0) !== 1) {
            continue;
        }

        $group = strtolower(trim((string)($tpl['template_group'] ?? '')));
        if ($group !== 'internal' && $group !== 'external') {
            continue;
        }

        $templateName = trim((string)($tpl['template_name'] ?? ''));
        $questionPath = trim((string)($tpl['question_path'] ?? ''));

        if ($templateName === '' || $questionPath === '') {
            continue;
        }

        $templateDropdownOptions[$group][] = [
            'id' => (int)($tpl['template_id'] ?? 0),
            'code' => trim((string)($tpl['template_code'] ?? '')),
            'name' => $templateName,
            'url' => $questionPath,
            'is_active' => (int)($tpl['is_active'] ?? 0)
        ];
    }

    $templateDropdownOptions['internal'] = array_values($templateDropdownOptions['internal']);
    $templateDropdownOptions['external'] = array_values($templateDropdownOptions['external']);
} catch (Throwable $e) {
    $templateDropdownOptions = [
        'internal' => [],
        'external' => []
    ];
}

// ===== รองรับบันทึกใหม่ + แก้ไขเอกสารเดิม =====
$docId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $docId > 0;

$formDataById  = [];
$formDataByKey = [];

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

    if (!$doc) {
        exit("ไม่พบเอกสาร");
    }

    $roleId = (int)($_SESSION['role_id'] ?? 0);
    $isAdmin   = ($roleId === 1);
    $isOfficer = ($roleId === 2);

    if (!$isAdmin && !$isOfficer) {
        if ((int)$doc['owner_id'] !== (int)$_SESSION['user_id']) {
            header("Location: /Pro_letter/documents/view_memo.php?id={$docId}&err=no_permission");
            exit;
        }

        if (!in_array($doc['status'], ['draft', 'rejected'], true)) {
            header("Location: /Pro_letter/documents/view_memo.php?id={$docId}&err=no_permission");
            exit;
        }
    }

    $q = $pdo->prepare("
        SELECT dv.field_id, tf.field_key, dv.value_text
        FROM document_values dv
        LEFT JOIN template_fields tf ON tf.field_id = dv.field_id
        WHERE dv.document_id = :id
    ");
    $q->execute([':id' => $docId]);

    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $formDataById[(int)$row['field_id']] = $row['value_text'];
        if (!empty($row['field_key'])) {
            $formDataByKey[$row['field_key']] = $row['value_text'];
        }
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

// ค่าเริ่มต้นสำหรับฟอร์มนี้
$projectDocDate = $_POST['doc_date'] ?? ($formDataById[1] ?? '');
$projectDocDate = trim((string)$projectDocDate);
$hasSavedDocDateField = array_key_exists(1, $formDataById);
$docDateOption = ($_POST['doc_date_option'] ?? '') === 'no_date'
    ? 'no_date'
    : (($hasSavedDocDateField && $projectDocDate === '') ? 'no_date' : 'use_date');
$coopSubject = $_POST['subject'] ?? ($formDataByKey['coop_subject'] ?? ($formDataById[14] ?? 'ขอความอนุเคราะห์ประเมินผลนักศึกษาสหกิจศึกษา'));
$coopToPerson = $_POST['to_person'] ?? ($formDataByKey['coop_to_person'] ?? ($formDataById[26] ?? ''));
$coopOrganizationName = $_POST['organization_name'] ?? ($formDataByKey['coop_organization_name'] ?? '');
$coopStudentCount = $_POST['student_count'] ?? ($formDataByKey['coop_student_count'] ?? '');
$coopStudentsJson = $_POST['student_list_json'] ?? ($formDataByKey['coop_students_json'] ?? '[]');
$coopStudentListText = $_POST['student_list_text'] ?? ($formDataByKey['coop_student_list_text'] ?? '');
$coopPeriod = $_POST['coop_period'] ?? ($formDataByKey['coop_period'] ?? '');
$coopStartDateValue = $_POST['coop_start_date'] ?? ($formDataByKey['coop_start_date'] ?? '');
$coopEndDateValue = $_POST['coop_end_date'] ?? ($formDataByKey['coop_end_date'] ?? '');
$coopAdvisorName = $_POST['advisor_name'] ?? ($formDataByKey['coop_advisor_name'] ?? '');
$projectReceiverName = $_POST['receiver_name'] ?? ($formDataByKey['coop_receiver_name'] ?? '');
$projectReceiverPosition = $_POST['receiver_position'] ?? ($formDataByKey['coop_receiver_position'] ?? '');

$departmentPhoneValue = $_POST['department_phone'] ?? '';
if ($departmentPhoneValue === '' && $isEdit && !empty($doc['header_text']) && preg_match('/โทร\.\s*([^\s]+)/u', (string)$doc['header_text'], $phoneMatch)) {
    $departmentPhoneValue = trim($phoneMatch[1]);
}

$coopStudents = json_decode($coopStudentsJson, true);
if (!is_array($coopStudents)) {
    $coopStudents = [];
}

if (!$coopStudentCount && count($coopStudents) > 0) {
    $coopStudentCount = count($coopStudents);
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

  <form method="post" action="<?= $isEdit ? '/Pro_letter/documents/update_memo.php' : 'save_memo.php' ?>" id="memoForm">
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="document_type_name" value="ขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ)">
    <input type="hidden" name="department_id" id="selectedDepartmentId" value="<?= (int)$currentUserDepartmentId ?>">
    <input type="hidden" name="document_type" value="infor_coop_evaluation">
    <input type="hidden" name="form_type" value="coop_evaluation">
    <input type="hidden" name="purpose" value="coop_evaluation">
    <input type="hidden" name="target_form" value="infor_coop_evaluation.php">
    <input type="hidden" name="redirect_to" value="form_memo_coop_evaluation.php">
    <?php if ($isEdit): ?>
    <input type="hidden" name="document_id" value="<?= (int)$docId ?>">
    <input type="hidden" name="mode" value="update">
    <input type="hidden" name="redirect_back" value="1">
    <?php else: ?>
    <input type="hidden" name="mode" value="create">
    <?php endif; ?>
    <!-- กล่องเนื้อหา -->
    <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
      <h1 class="text-center font-bold mb-6 text-black">
        แบบฟอร์มขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ)
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
              <option value="external" <?= ($CURRENT_MAIN=="external"?"selected":"") ?>>ภายนอก</option>
              <option value="internal" <?= ($CURRENT_MAIN=="internal"?"selected":"") ?>>ภายใน</option>
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
          <label class="lbl text-gray-800 whitespace-nowrap" for="docDateDisplay">1. วัน เดือน ปี :</label>

          <div class="flex items-center gap-3 flex-nowrap pl-4 w-full overflow-x-auto">
            <label class="flex items-center gap-2 text-gray-800 whitespace-nowrap shrink-0">
              <input type="radio" name="doc_date_option" id="docDateUse" value="use_date" class="accent-black"
                <?= ($docDateOption === 'use_date') ? 'checked' : '' ?>>
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
              <?= ($docDateOption === 'no_date') ? 'checked' : '' ?>>
            ไม่ประสงค์ใส่วันที่
          </label>
        </div>
      </div>


      <!-- 2. เรื่อง -->
      <div class="mb-4 flex items-start gap-1">
        <label class="lbl whitespace-nowrap w-48 pt-2">2. เรื่อง :</label>
        <div class="flex-1">
          <input type="text" name="subject" id="subjectInput" data-spell-field="subject" value="<?= h($coopSubject) ?>"
            class="w-full border rounded-md p-2"
            placeholder="ขอความอนุเคราะห์ตอบแบบประเมินและแบบสำรวจนักศึกษาปฏิบัติงานสหกิจศึกษา">

          <div id="subjectInputSpellBox" class="spell-box hidden"></div>
          <div id="subjectInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. เรียนถึง -->
      <div class="mb-4 flex items-start gap-1">
        <label class="lbl whitespace-nowrap w-48 pt-2">3. เรียนถึง :</label>
        <div class="flex-1">
          <input type="text" name="to_person" id="toPerson" data-spell-field="to_person"
            class="w-full border rounded-md p-2"
            placeholder="เช่น เลขาธิการ สำนักงานคณะกรรมการการรักษาความมั่นคงปลอดภัยไซเบอร์แห่งชาติ"
            value="<?= h($coopToPerson) ?>">

          <div id="toPersonSpellBox" class="spell-box hidden"></div>
          <div id="toPersonSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 4. หน่วยงาน / สถานประกอบการ -->
      <div class="mb-4 flex items-start gap-1">
        <label class="lbl whitespace-nowrap w-48 pt-2">4. หน่วยงาน :</label>
        <div class="flex-1">
          <input type="text" name="organization_name" id="organizationName" data-spell-field="organization_name"
            value="<?= h($coopOrganizationName) ?>" class="w-full border rounded-md p-2"
            placeholder="เช่น สำนักงานคณะกรรมการการรักษาความมั่นคงปลอดภัยไซเบอร์แห่งชาติ">

          <div id="organizationNameSpellBox" class="spell-box hidden"></div>
          <div id="organizationNameSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. จำนวนนักศึกษาสหกิจศึกษา -->
      <div class="mb-4 flex items-start gap-1">
        <label class="lbl whitespace-nowrap w-48 pt-2">5. จำนวนนักศึกษา :</label>
        <div class="flex-1">
          <input type="number" name="student_count" id="studentCount" class="w-40 border rounded-md p-2" min="1"
            max="50" value="<?= h($coopStudentCount) ?>" placeholder="เช่น 2">

          <div id="studentListContainer" class="mt-3 space-y-3"></div>

          <input type="hidden" name="student_list_text" id="studentListText" value="<?= h($coopStudentListText) ?>">
          <input type="hidden" name="student_list_json" id="studentListJson" value="<?= h($coopStudentsJson) ?>">
        </div>
      </div>

      <!-- 6. วันที่ปฏิบัติงานสหกิจศึกษา -->
      <div class="mb-4 flex items-start gap-1">
        <label class="lbl whitespace-nowrap w-48 pt-2">6. วันที่ปฏิบัติงาน :</label>

        <div class="flex items-center gap-3">
          <div class="relative">
            <input type="text" id="coopStartDate" name="coop_start_date" value="<?= h($coopStartDateValue) ?>"
              class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer" placeholder="เริ่มต้น" readonly>
            <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <span>ถึง</span>

          <div class="relative">
            <input type="text" id="coopEndDate" name="coop_end_date" value="<?= h($coopEndDateValue) ?>"
              class="border rounded-md p-2 shadow-sm w-44 pr-10 cursor-pointer" placeholder="สิ้นสุด" readonly>
            <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <input type="hidden" name="coop_period" id="coopPeriod" value="<?= h($coopPeriod) ?>">
        </div>
      </div>

      <!-- 7. ส่งแบบประเมินและแบบสำรวจดังกล่าวให้กับ -->
      <div class="mb-4 flex items-start gap-1">
        <label class="lbl whitespace-nowrap w-62 pt-2">7. ส่งแบบประเมินและแบบสำรวจดังกล่าวให้กับ :</label>
        <div class="flex-1">
          <input type="text" name="advisor_name" id="advisorName" data-spell-field="advisor_name"
            class="w-full border rounded-md p-2" value="<?= h($coopAdvisorName) ?>"
            placeholder="เช่น พนักงานที่ปรึกษา / ผู้รับผิดชอบนักศึกษาสหกิจศึกษา">

          <div id="advisorNameSpellBox" class="spell-box hidden"></div>
          <div id="advisorNameSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 8. ชื่อผู้ลงนามท้ายเอกสาร -->
      <div class="mb-4 flex items-start gap-1">
        <label class="lbl text-gray-800 whitespace-nowrap w-48 pt-2" for="receiverNameInput">
          8. ชื่อผู้ลงนาม :
        </label>

        <div class="flex-1">
          <input type="text" name="receiver_name" id="receiverNameInput" class="w-full border rounded-md p-2"
            value="<?= h($projectReceiverName) ?>" placeholder="เช่น ผู้ช่วยศาสตราจารย์พีระศักดิ์ เสรีกุล">
          <p class="text-sm text-gray-500 mt-1">
            ชื่อนี้จะใช้แสดงบริเวณลายเซ็นท้ายเอกสาร เช่น (ผู้ช่วยศาสตราจารย์พีระศักดิ์ เสรีกุล)
          </p>
        </div>
      </div>

      <!-- 9. ตำแหน่งผู้ลงนามท้ายเอกสาร -->
      <div class="mb-4 flex items-start gap-1">
        <label class="lbl text-gray-800 whitespace-nowrap w-48 pt-2" for="receiverPositionInput">
          9. ตำแหน่งผู้ลงนาม :
        </label>

        <div class="flex-1">
          <input type="text" name="receiver_position" id="receiverPositionInput" data-spell-field="receiver_position"
            class="w-full border rounded-md p-2" value="<?= h($projectReceiverPosition) ?>"
            placeholder="เช่น คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม">

          <div id="receiverPositionInputSpellBox" class="spell-box hidden"></div>
          <div id="receiverPositionInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>
      <!-- 10. เบอร์โทรภาควิชา -->
      <div class="mb-4 flex items-center gap-3 flex-nowrap">
        <label class="lbl text-gray-800 whitespace-nowrap w-48" for="departmentPhone">10. เบอร์โทรภาควิชา :</label>
        <span class="text-gray-800 whitespace-nowrap">โทร.</span>
        <input type="text" name="department_phone" id="departmentPhone"
          class="border rounded-md p-2 shadow-sm w-[260px]" placeholder="เช่น 7064"
          value="<?= h($departmentPhoneValue) ?>">
        <span class="text-gray-700 whitespace-nowrap">ที่ต้องการให้ขึ้นที่ส่วนราชการ</span>
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

  const docDateDisplay = byId("docDateDisplay");
  const docDate = byId("docDate");
  const docDateUse = byId("docDateUse");
  const docDateNone = byId("docDateNone");
  const subjectInput = byId("subjectInput");
  const toPerson = byId("toPerson");
  const organizationName = byId("organizationName");
  const studentCount = byId("studentCount");
  const studentListContainer = byId("studentListContainer");
  const studentListText = byId("studentListText");
  const studentListJson = byId("studentListJson");
  const existingStudents = <?= json_encode($coopStudents, JSON_UNESCAPED_UNICODE) ?>;
  const coopStartDate = byId("coopStartDate");
  const coopEndDate = byId("coopEndDate");
  const coopPeriod = byId("coopPeriod");
  const advisorName = byId("advisorName");
  const receiverNameInput = byId("receiverNameInput");
  const receiverPositionInput = byId("receiverPositionInput");

  const spellState = {
    subject: {
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
    organization_name: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },

    advisor_name: {
      checked: false,
      hasError: false,
      ignored: false,
      errors: [],
      lastText: ""
    },
    receiver_position: {
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

  let coopStartPicker;
  let coopEndPicker;

  function formatThaiDate(date) {
    const day = date.getDate();
    const month = monthsTH[date.getMonth()];
    const year = date.getFullYear() + 543;

    return `${day} ${month} ${year}`;
  }

  function parseYMDCoop(value) {
    const text = String(value || "").trim();
    const m = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return null;
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return Number.isNaN(d.getTime()) ? null : d;
  }

  function formatYMDCoop(date) {
    if (!date) return "";
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  }

  function updateCoopPeriod() {
    const start = coopStartPicker?.selectedDates?. [0];
    const end = coopEndPicker?.selectedDates?. [0];

    if (!start || !end) {
      coopPeriod.value = "";
      return;
    }

    coopPeriod.value = `ตั้งแต่วันที่ ${formatThaiDate(start)} ถึง ${formatThaiDate(end)}`;
  }

  if (window.flatpickr) {
    flatpickr.localize(flatpickr.l10ns.th);

    let docPicker = null;

    if (docDateDisplay && docDate) {
      docPicker = flatpickr("#docDateDisplay", {
        dateFormat: "Y-m-d",
        disableMobile: true,
        allowInput: false,
        clickOpens: true,
        onReady: function(selectedDates, dateStr, instance) {
          const savedDate = parseYMDCoop(docDate.value);
          if (savedDate) {
            instance.setDate(savedDate, false);
            instance.input.value = formatThaiDate(savedDate);
            docDate.value = formatYMDCoop(savedDate);
          }
        },
        onChange: function(selectedDates, dateStr, instance) {
          if (selectedDates.length > 0) {
            const selectedDate = selectedDates[0];
            instance.input.value = formatThaiDate(selectedDate);
            docDate.value = formatYMDCoop(selectedDate);
          }
        }
      });
    }

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
  }

  if (window.flatpickr && coopStartDate && coopEndDate) {
    coopStartPicker = flatpickr("#coopStartDate", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      allowInput: false,
      onChange: updateCoopPeriod
    });

    coopEndPicker = flatpickr("#coopEndDate", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      allowInput: false,
      onChange: updateCoopPeriod
    });
  }

  function rebuildStudentInputs() {
    const count = parseInt(studentCount.value, 10);

    studentListContainer.innerHTML = "";
    studentListText.value = "";
    if (studentListJson) studentListJson.value = "[]";

    if (!count || count < 1) return;

    for (let i = 1; i <= count; i++) {
      const oldStudent = Array.isArray(existingStudents) ? (existingStudents[i - 1] || {}) : {};
      const oldName = oldStudent.name || "";
      const oldId = oldStudent.student_id || oldStudent.id || "";

      const row = document.createElement("div");
      row.className = "grid grid-cols-1 md:grid-cols-2 gap-3";

      row.innerHTML = `
      <div>
        <label class="block mb-1 text-gray-700">ชื่อนักศึกษาคนที่ ${i}</label>
        <input type="text"
          name="student_names[]"
          class="student-name-input w-full border rounded-md p-2"
          placeholder="เช่น นายปุณนที ปิ่นวิเศษ">
      </div>

      <div>
        <label class="block mb-1 text-gray-700">รหัสนักศึกษาคนที่ ${i}</label>
        <input type="text"
          name="student_ids[]"
          class="student-id-input w-full border rounded-md p-2"
          placeholder="เช่น 65-060216-3003-8">
      </div>
    `;

      studentListContainer.appendChild(row);
      row.querySelector(".student-name-input").value = oldName;
      row.querySelector(".student-id-input").value = oldId;
    }

    updateStudentListText();
  }

  function updateStudentListText() {
    const rows = Array.from(studentListContainer.querySelectorAll(".grid"));
    const students = [];

    const lines = rows.map(row => {
      const name = row.querySelector(".student-name-input")?.value.trim() || "";
      const id = row.querySelector(".student-id-input")?.value.trim() || "";

      if (name || id) {
        students.push({
          name: name,
          student_id: id
        });
      }

      if (!name && !id) return "";
      return `${name} รหัสนักศึกษา ${id}`;
    }).filter(Boolean);

    studentListText.value = lines.join("\n");
    if (studentListJson) {
      studentListJson.value = JSON.stringify(students);
    }
  }

  studentCount?.addEventListener("input", rebuildStudentInputs);
  if (studentCount?.value) rebuildStudentInputs();
  studentListContainer?.addEventListener("input", updateStudentListText);

  function getSpellBoxByField(el) {
    if (!el) return null;
    if (el.id === "subjectInput") return byId("subjectInputSpellBox");
    if (el.id === "toPerson") return byId("toPersonSpellBox");
    if (el.id === "organizationName") return byId("organizationNameSpellBox");
    if (el.id === "studentName") return byId("studentNameSpellBox");
    if (el.id === "advisorName") return byId("advisorNameSpellBox");
    if (el.id === "receiverPositionInput") return byId("receiverPositionInputSpellBox");
    return null;
  }

  function getSpellLoadingByField(el) {
    if (!el) return null;
    if (el.id === "subjectInput") return byId("subjectInputSpellLoading");
    if (el.id === "toPerson") return byId("toPersonSpellLoading");
    if (el.id === "organizationName") return byId("organizationNameSpellLoading");
    if (el.id === "studentName") return byId("studentNameSpellLoading");
    if (el.id === "advisorName") return byId("advisorNameSpellLoading");
    if (el.id === "receiverPositionInput") return byId("receiverPositionInputSpellLoading");
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
    return String(text).split(/[^\u0E00-\u0E7Fa-zA-Z0-9]+/g).map(w => w.trim()).filter(Boolean);
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
      return wrongWord && !approvedWords.has(wrongWord);
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

  async function checkAllSpellFields() {
    const fields = [
      subjectInput,
      toPerson,
      organizationName,
      advisorName,
      receiverPositionInput
    ];

    for (const el of fields) {
      if (!el) continue;

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

  function validateForm() {
    let firstInvalid = null;

    const studentRows = Array.from(studentListContainer.querySelectorAll(".grid"));

    [
      subjectInput,
      toPerson,
      organizationName,
      studentCount,
      coopStartDate,
      coopEndDate,
      advisorName,
      receiverNameInput,
      receiverPositionInput,
      ...studentRows.flatMap(row => [
        row.querySelector(".student-name-input"),
        row.querySelector(".student-id-input")
      ])
    ].forEach(el => setErr(el, false));

    const requiredFields = [
      subjectInput,
      toPerson,
      organizationName,
      studentCount,
      coopStartDate,
      coopEndDate,
      advisorName,
      receiverNameInput,
      receiverPositionInput
    ];

    requiredFields.forEach(el => {
      if (!el?.value.trim()) {
        setErr(el, true);
        firstInvalid = firstInvalid || el;
      }
    });

    if (!studentCount?.value.trim() || parseInt(studentCount.value, 10) < 1) {
      setErr(studentCount, true);
      firstInvalid = firstInvalid || studentCount;
    }

    if (studentRows.length === 0) {
      setErr(studentCount, true);
      firstInvalid = firstInvalid || studentCount;
    }

    studentRows.forEach(row => {
      const nameInput = row.querySelector(".student-name-input");
      const idInput = row.querySelector(".student-id-input");

      if (!nameInput.value.trim()) {
        setErr(nameInput, true);
        firstInvalid = firstInvalid || nameInput;
      }

      if (!idInput.value.trim()) {
        setErr(idInput, true);
        firstInvalid = firstInvalid || idInput;
      }
    });

    if (firstInvalid) {
      alert("กรุณากรอกข้อมูลให้ครบถ้วน");
      scrollFocus(firstInvalid);
      return false;
    }

    updateStudentListText();
    updateCoopPeriod();

    if (docDateNone?.checked) {
      if (docDate) docDate.value = "";
      if (docDateDisplay) docDateDisplay.value = "";
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

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();

    if (!validateForm()) return;

    const okSpell = await checkAllSpellFields();
    if (!okSpell) return;

    form.submit();
  });
  </script>
  <script>
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", () => {

    const main = document.getElementById("mainCategory");
    const sub = document.getElementById("subCategory");
    if (!main || !sub) return;

    const TEMPLATE_OPTIONS =
      <?= json_encode($templateDropdownOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function buildTemplateUrl(path) {
      const cleanPath = String(path || "").trim();
      if (!cleanPath || cleanPath === "#") return "";
      if (/^https?:\/\//i.test(cleanPath) || cleanPath.startsWith("/Pro_letter/")) {
        return cleanPath;
      }
      return "/Pro_letter" + (cleanPath.startsWith("/") ? cleanPath : "/" + cleanPath);
    }

    function renderSubOptions(list, selectedValue = "") {
      const selectedText = String(selectedValue || "").trim();
      let hasSelectedText = false;

      sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';

      (Array.isArray(list) ? list : []).forEach(item => {
        const isActive = Number(item?.is_active || 0) === 1;
        const name = String(item?.name || "").trim();
        const url = String(item?.url || "").trim();

        if (!isActive || !name || !url) return;

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

      if (!selectedText || !hasSelectedText) {
        sub.selectedIndex = 0;
        sub.value = "";
        if (selectedText) sub.dataset.current = "";
      }
    }

    function syncUI(keepCurrentSub = false) {
      const mainVal = String(main.value || "").trim();
      const currentSub = keepCurrentSub ? String(sub.dataset.current || "").trim() : "";

      if (mainVal === "internal" || mainVal === "external") {
        if (!window.CATEGORY_LOCKED_BY_STATUS) {
          sub.disabled = false;
        }
        renderSubOptions(TEMPLATE_OPTIONS[mainVal] || [], currentSub);
      } else {
        sub.disabled = true;
        sub.dataset.current = "";
        sub.innerHTML = '<option value="">-- เลือกหมวดย่อย --</option>';
        sub.value = "";
      }
    }

    main.addEventListener("change", () => {
      if (window.CATEGORY_LOCKED_BY_STATUS) return;
      sub.dataset.current = "";
      syncUI(false);
    });

    sub.addEventListener("change", () => {
      const subVal = String(sub.value || "").trim();
      sub.dataset.current = subVal;

      if (!subVal) return;

      const selectedOption = sub.options[sub.selectedIndex];
      const target = buildTemplateUrl(selectedOption?.dataset?.url || "");
      if (!target || target === "#") return;

      const separator = target.includes("?") ? "&" : "?";
      window.location.href =
        target +
        separator +
        "main=" + encodeURIComponent(String(main.value || "").trim()) +
        "&sub=" + encodeURIComponent(subVal);
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