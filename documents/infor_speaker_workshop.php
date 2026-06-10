<?php 
// ต้องวางตรงนี้! บรรทัดแรกของไฟล์
$CURRENT_MAIN = $_GET['main'] ?? 'internal';
$CURRENT_SUB  = $_GET['sub']  ?? 'ขออนุมัติตัวบุคคลเป็นวิทยากร';

$ALLOWED_MAIN = ['external', 'internal'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'external';
}
?>
<!--Pro_letter/documents/infor_speaker_workshop.php  ขออนุมัติตัวบุคคลเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ -->
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

// function h($value) {
//     return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
// }

function speaker_arabic_digits_form($value) {
    return strtr((string)$value, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

$pdo = db();

$documentId = (int)($_GET['id'] ?? $_GET['document_id'] ?? 0);
$isEdit = $documentId > 0;

$document = [];
$valueMap = [];
$templateId = 1;

if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT document_id, template_id, owner_id, doc_no, doc_date, subject, header_text, status
        FROM documents
        WHERE document_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

   if (!$document) {
    header("Location: " . $homePath . "?err=notfound");
    exit;
}

$isAdminOrOfficer = in_array((int)($_SESSION['role_id'] ?? 0), [1, 2], true);
$isOwner = ((int)($document['owner_id'] ?? 0) === (int)$userId);

if (!$isAdminOrOfficer && !$isOwner) {
    header("Location: " . $homePath . "?err=forbidden");
    exit;
}

    $templateId = (int)($document['template_id'] ?? 1);

    $stmt = $pdo->prepare("
        SELECT field_id, value_text
        FROM document_values
        WHERE document_id = :id
    ");
    $stmt->execute([':id' => $documentId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $valueMap[(int)$row['field_id']] = $row['value_text'] ?? '';
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

$formAction = $isEdit ? '/Pro_letter/documents/update_memo.php' : '/Pro_letter/documents/save_memo.php';

$oldSubject = $document['subject'] ?? '';
$oldTeacherName = $valueMap[2] ?? ($_SESSION['fullname'] ?? '');
$oldPosition = $valueMap[3] ?? 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ';

$oldProjectTitle = $valueMap[5] ?? '';
$oldInternPeriod = $valueMap[6] ?? '';
$oldLocation = $valueMap[7] ?? ($valueMap[22] ?? '');

$oldReferenceOrg = $valueMap[18] ?? '';
$oldReferenceNo = $valueMap[19] ?? '';
$oldHeaderDocDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$oldReferenceDate = $valueMap[21] ?? '';
$oldCourseName = $valueMap[23] ?? '';
$oldTravelPeriod = $valueMap[24] ?? ($valueMap[9] ?? '');
$oldIntentionText = $valueMap[25] ?? '';
$oldDepartmentPhone = '';
if (!empty($document['header_text']) && preg_match('/โทร\.?\s*([^\s]+)/u', (string)$document['header_text'], $phoneMatch)) {
    $oldDepartmentPhone = speaker_arabic_digits_form(trim((string)($phoneMatch[1] ?? '')));
    $oldDepartmentPhone = preg_replace('/^โทร\.?\s*/u', '', $oldDepartmentPhone);
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

  /* ===== Spell Check ตาม form_Memo.php ===== */
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

  .spell-box.hidden {
    display: none !important;
  }

  .spell-result-box {
    display: block;
  }

  .spell-warning {
    font-weight: 700;
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
    margin-top: 6px;
  }

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

  .spell-suggestion-btn:hover {
    background: #ffedd5;
    border-color: #fb923c;
  }

  .spell-suggestion-btn:active {
    transform: scale(0.96);
  }

  .spell-suggestion-btn:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.2);
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
    <input type="hidden" name="template_id" value="1">
    <input type="hidden" name="department_id" id="selectedDepartmentId" value="<?= (int)$currentUserDepartmentId ?>">
    <input type="hidden" name="header_text" id="headerTextInput" value="<?= h($document['header_text'] ?? '') ?>">
    <input type="hidden" name="purpose" value="speaker_workshop">
    <input type="hidden" name="form_type" value="speaker_workshop">
    <input type="hidden" name="document_type_name" value="ขออนุมัติตัวบุคคลเป็นวิทยากร">
    <input type="hidden" name="document_type" value="infor_speaker_workshop">
    <input type="hidden" name="redirect_to" value="form_memo_speaker.php">
    <input type="hidden" name="target_form" value="form_memo_speaker.php">
    <input type="hidden" name="template_page" value="form_memo_speaker.php">
    <?php if ($isEdit): ?>
    <input type="hidden" name="document_id" value="<?= (int)$documentId ?>">
    <input type="hidden" name="mode" value="update">
    <input type="hidden" name="redirect_back"
      value="/Pro_letter/form_Memo/form_memo_speaker.php?id=<?= (int)$documentId ?>">
    <?php else: ?>
    <input type="hidden" name="mode" value="create">
    <?php endif; ?>
    <!-- กล่องเนื้อหา -->
    <div class="w-[900px] mx-auto mt-16 mb-6 bg-white shadow-md rounded-md p-8" style="min-height: 1122px">
      <h1 class="text-center font-bold mb-6 text-black">
        แบบฟอร์มขออนุมัติตัวบุคคลเป็นวิทยากร
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
              <?= $categoryLocked ? ' data-category-locked="1"' : '' ?>
              data-current="<?= htmlspecialchars($CURRENT_SUB ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled>
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
      <div class="mb-4 flex items-center gap-3">
        <label class="lbl whitespace-nowrap">
          1. เรื่อง :
        </label>
        <div class="flex-1">
          <input type="text" name="subject" id="memoSubject" data-spell-field="memo_subject"
            class="w-full border rounded-md p-2"
            placeholder="เช่น ขออนุมัติตัวบุคคลเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ"
            value="<?= h($oldSubject) ?>">

          <div id="memoSubjectSpellBox" class="spell-box hidden"></div>
          <div id="memoSubjectSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. ชื่อ–นามสกุล -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 items-end">
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="fullname">2. ชื่อ–นามสกุล
            :</label>
          <input type="text" name="teacher_name" id="teacherName" class="flex-1 border rounded-md p-2 bg-gray-50"
            value="<?= h($oldTeacherName) ?>">
        </div>
        <div class="flex items-center gap-3">
          <label class="lbl text-gray-800 whitespace-nowrap" for="position">ตำแหน่ง :</label>
          <input type="text" name="position" class="flex-1 border rounded-md p-2" id="position"
            value="<?= h($oldPosition) ?>" />
        </div>
      </div>
      <!-- 3. วัน เดือน ปี ที่ต้องการให้ปรากฎบนบันทึกข้อความ -->
      <?php
      $docDateSaved = trim((string)$oldHeaderDocDate);
      $hasSavedDocDateField = array_key_exists(1, $valueMap);
      $docDateOption = ($hasSavedDocDateField && $docDateSaved === '') ? 'no_date' : 'use_date';
      ?>
      <div class="mb-4">
        <div class="flex flex-col gap-2">
          <label class="lbl text-gray-800 whitespace-nowrap" for="docDateDisplay">3. วัน เดือน ปี :</label>

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

            <label class="lbl text-gray-800 whitespace-nowrap shrink-0">ที่ต้องการให้ปรากฎบนบันทึกข้อความ</label>
          </div>

          <label class="flex items-center gap-2 text-gray-800 whitespace-nowrap pl-4">
            <input type="radio" name="doc_date_option" id="docDateNone" value="no_date" class="accent-black"
              <?= ($docDateOption === 'no_date') ? 'checked' : '' ?>>
            ไม่ประสงค์ใส่วันที่
          </label>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-1 gap-6 mb-6">
        <!-- หน่วยงานผู้ออกหนังสืออ้างอิง -->
        <div>
          <label class="lbl block text-gray-800 mb-1">
            4. หน่วยงานผู้ออกหนังสืออ้างอิง :
          </label>

          <textarea name="reference_org" id="referenceOrg" data-spell-field="reference_org"
            class="w-full border rounded-md p-2"
            placeholder="เช่น ศูนย์นวัตกรรมและการจัดการเทคโนโลยีดิจิทัล วิทยาลัยศิลปะ สื่อ และเทคโนโลยี มหาวิทยาลัยเชียงใหม่"><?= h($oldReferenceOrg) ?></textarea>

          <div id="referenceOrgSpellBox" class="spell-box hidden"></div>
          <div id="referenceOrgSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>
      <div class="mb-6 flex items-center gap-3">

        <label class="lbl whitespace-nowrap w-56 ">
          เลขที่หนังสืออ้างอิง :
        </label>
        <input type="text" name="reference_no" class="w-full border rounded-md p-2" placeholder="เช่น อว.1234/2568"
          value="<?= h($oldReferenceNo) ?>">

      </div>

      <div class="mb-6 flex items-center gap-3">
        <label class="lbl whitespace-nowrap w-56">
          5. วันที่หนังสืออ้างอิง :
        </label>

        <div class="relative">
          <input type="text" name="reference_date" id="referenceDate"
            class="border rounded-md p-2 w-44 pr-10 cursor-pointer" placeholder="เลือกวันที่" readonly
            value="<?= h($oldReferenceDate) ?>">

          <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
          </svg>
        </div>
      </div>





      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          6. ชื่อโครงการอบรม :
        </label>
        <div class="flex-1">
          <textarea name="project_title" id="projectTitle" data-spell-field="project_title" rows="2"
            class="w-full border rounded-md p-2"
            placeholder="เช่น ขอเรียนเชิญ บุคลากรในสังกัดเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ"><?= h($oldProjectTitle) ?></textarea>

          <div id="projectTitleSpellBox" class="spell-box hidden"></div>
          <div id="projectTitleSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>





      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          7. ชื่อหลักสูตร :
        </label>
        <div class="flex-1">
          <textarea name="course_name" id="courseName" data-spell-field="course_name" rows="2"
            class="w-full border rounded-md p-2"
            placeholder="เช่น หลักสูตรการปรับเปลี่ยนองค์กรภาครัฐ สู่ดิจิทัลด้วยกระบวนการคิดเชิงออกแบบ (Government Digital Transformation by Design Thinking) "><?= h($oldCourseName) ?></textarea>

          <div id="courseNameSpellBox" class="spell-box hidden"></div>
          <div id="courseNameSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>






      <div class="mb-4 flex items-center gap-4">
        <label class="lbl whitespace-nowrap w-56">
          8. สถานที่จัดงาน :
        </label>
        <div class="flex-1">
          <input type="text" name="location" id="eventLocation" data-spell-field="location"
            class="w-full border rounded-md p-2" placeholder="เช่น อุทยานหลวงราชพฤกษ์ จังหวัดเชียงใหม่"
            value="<?= h($oldLocation) ?>">

          <div id="eventLocationSpellBox" class="spell-box hidden"></div>
          <div id="eventLocationSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-4 flex items-start gap-4">
        <label class="lbl whitespace-nowrap w-56 pt-2">
          9. ความประสงค์ :
        </label>

        <div class="flex-1">
          <textarea name="intention_text" id="intentionText" data-spell-field="intention_text" rows="2"
            class="w-full border rounded-md p-2"
            placeholder="ขออนุมัติเดินทางไปร่วมเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ"><?= h($oldIntentionText ?? '') ?></textarea>

          <div id="intentionTextSpellBox" class="spell-box hidden"></div>
          <div id="intentionTextSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-6">
        <!-- บรรทัดที่ 1 : label -->
        <label class="lbl block mb-2">
          10. วันที่เริ่ม - วันที่สิ้นสุดโครงการ :
        </label>

        <!-- บรรทัดที่ 2 : วันที่ -->
        <div class="flex items-center gap-3 ml-6 flex-wrap">
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

          <!-- แสดงผลรวม -->
          <input type="text" id="internRangeDisplay" class="border rounded-md p-2 w-64 bg-gray-50 text-gray-600"
            placeholder="10 - 11 กรกฎาคม 2568" readonly value="<?= h($oldInternPeriod) ?>">

          <!-- ค่าที่ส่งจริง -->
          <input type="hidden" name="intern_period" id="internPeriod" value="<?= h($oldInternPeriod) ?>">
        </div>
      </div>



      <div class="mb-6">
        <!-- บรรทัดที่ 1 : label -->
        <label class="lbl block mb-2">
          11. วันที่เดินทางไป - วันที่เดินทางกลับ :
        </label>

        <!-- บรรทัดที่ 2 : วันที่ทั้งหมด (เลื่อนลงมา) -->
        <div class="flex items-center gap-3 ml-6 flex-wrap">
          <!-- วันที่เริ่ม -->
          <div class="relative">
            <input type="text" id="travelStart" class="border rounded-md p-2 w-44 pr-10 cursor-pointer"
              placeholder="วันที่เดินทางไป" readonly>
            <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <span>ถึง</span>

          <!-- วันที่สิ้นสุด -->
          <div class="relative">
            <input type="text" id="travelEnd" class="border rounded-md p-2 w-44 pr-10 cursor-pointer"
              placeholder="วันที่เดินทางกลับ" readonly>
            <svg class="absolute right-3 top-2.5 w-5 h-5 text-[#11C2B9]" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v11a2 2 0 002 2z" />
            </svg>
          </div>

          <!-- แสดงผลรวม -->
          <input type="text" id="travelRangeDisplay" class="border rounded-md p-2 w-64 bg-gray-50 text-gray-600"
            placeholder="20 - 21 ธันวาคม 2568" readonly value="<?= h($oldTravelPeriod) ?>">

          <input type="hidden" name="travel_period" id="travelPeriod" value="<?= h($oldTravelPeriod) ?>">
        </div>
      </div>

      <div class="mb-4 flex items-center gap-3">
        <label class="lbl whitespace-nowrap">
          12. เบอร์โทรภาควิชา :
        </label>
        <div class="flex items-center gap-2 flex-1 whitespace-nowrap">
          <span class="lbl whitespace-nowrap">โทร.</span>
          <input type="text" name="department_phone" id="departmentPhone" class="border rounded-md p-2 w-48"
            placeholder="เช่น 7064" value="<?= h($oldDepartmentPhone) ?>">
          <span class="text-gray-800 whitespace-nowrap">ที่ต้องการให้ขึ้นที่ส่วนราชการ</span>
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
  document.addEventListener("DOMContentLoaded", () => {
    if (window.flatpickr && flatpickr.l10ns?.th) {
      flatpickr.localize(flatpickr.l10ns.th);
    }

    const monthsTHSpeaker = [
      "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
      "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    ];

    function formatThaiDate(date) {
      if (!date) return "";
      return `${date.getDate()} ${monthsTHSpeaker[date.getMonth()]} ${date.getFullYear() + 543}`;
    }

    function parseYMDSpeaker(value) {
      const m = String(value || "").trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (!m) return null;
      const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
      return Number.isNaN(d.getTime()) ? null : d;
    }

    function formatYMDSpeaker(date) {
      if (!date) return "";
      const y = date.getFullYear();
      const m = String(date.getMonth() + 1).padStart(2, "0");
      const d = String(date.getDate()).padStart(2, "0");
      return `${y}-${m}-${d}`;
    }

    function formatThaiRange(start, end) {
      if (!start || !end) return "";
      const sd = start.getDate();
      const ed = end.getDate();
      const sm = monthsTHSpeaker[start.getMonth()];
      const em = monthsTHSpeaker[end.getMonth()];
      const sy = start.getFullYear() + 543;
      const ey = end.getFullYear() + 543;

      if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
        return `${sd} - ${ed} ${em} ${ey}`;
      }
      return `${sd} ${sm} ${sy} - ${ed} ${em} ${ey}`;
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
        const savedDate = parseYMDSpeaker(docDateHidden?.value);
        if (savedDate) {
          instance.setDate(savedDate, false);
          instance.input.value = formatThaiDate(savedDate);
          if (docDateHidden) docDateHidden.value = formatYMDSpeaker(savedDate);
        }
      },
      onChange: function(selectedDates, dateStr, instance) {
        if (selectedDates.length > 0) {
          const selectedDate = selectedDates[0];
          instance.input.value = formatThaiDate(selectedDate);
          if (docDateHidden) docDateHidden.value = formatYMDSpeaker(selectedDate);
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

    flatpickr("#referenceDate", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      onChange: function(selectedDates, dateStr, instance) {
        if (selectedDates.length > 0) {
          instance.input.value = formatThaiDate(selectedDates[0]);
        }
      }
    });

    const internStartPicker = flatpickr("#internStart", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      onChange: updateInternRange
    });

    const internEndPicker = flatpickr("#internEnd", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      onChange: updateInternRange
    });

    function updateInternRange() {
      const start = internStartPicker.selectedDates[0];
      const end = internEndPicker.selectedDates[0];
      const text = formatThaiRange(start, end);
      if (!text) return;

      document.getElementById("internRangeDisplay").value = text;
      document.getElementById("internPeriod").value = text;
    }

    const travelStartPicker = flatpickr("#travelStart", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      onChange: updateTravelRange
    });

    const travelEndPicker = flatpickr("#travelEnd", {
      dateFormat: "d/m/Y",
      disableMobile: true,
      onChange: updateTravelRange
    });

    function updateTravelRange() {
      const start = travelStartPicker.selectedDates[0];
      const end = travelEndPicker.selectedDates[0];
      const text = formatThaiRange(start, end);
      if (!text) return;

      document.getElementById("travelRangeDisplay").value = text;
      document.getElementById("travelPeriod").value = text;
    }

    function parseThaiDateText(text) {
      if (!text) return null;

      const monthMap = {
        "มกราคม": 0,
        "กุมภาพันธ์": 1,
        "มีนาคม": 2,
        "เมษายน": 3,
        "พฤษภาคม": 4,
        "มิถุนายน": 5,
        "กรกฎาคม": 6,
        "สิงหาคม": 7,
        "กันยายน": 8,
        "ตุลาคม": 9,
        "พฤศจิกายน": 10,
        "ธันวาคม": 11
      };

      const clean = String(text)
        .replace(/วันที่/g, "")
        .replace(/พ\.ศ\./g, "")
        .replace(/,/g, " ")
        .replace(/\s+/g, " ")
        .trim();

      const match = clean.match(/(\d{1,2})\s+([ก-๙]+)\s+(\d{4})/);
      if (!match) return null;

      const day = parseInt(match[1], 10);
      const month = monthMap[match[2]];
      let year = parseInt(match[3], 10);

      if (Number.isNaN(day) || month === undefined || Number.isNaN(year)) return null;
      if (year > 2400) year -= 543;

      return new Date(year, month, day);
    }

    function parseThaiRangeText(text) {
      if (!text) return [null, null];

      const clean = String(text)
        .replace(/–/g, "-")
        .replace(/—/g, "-")
        .replace(/ถึง/g, "-")
        .replace(/\s+/g, " ")
        .trim();

      const sameMonth = clean.match(/(\d{1,2})\s*-\s*(\d{1,2})\s+([ก-๙]+)\s+(\d{4})/);
      if (sameMonth) {
        const monthText = sameMonth[3];
        const yearText = sameMonth[4];
        const start = parseThaiDateText(`${sameMonth[1]} ${monthText} ${yearText}`);
        const end = parseThaiDateText(`${sameMonth[2]} ${monthText} ${yearText}`);
        return [start, end];
      }

      const parts = clean.split(/\s*-\s*/);
      if (parts.length >= 2) {
        return [parseThaiDateText(parts[0]), parseThaiDateText(parts.slice(1).join(" - "))];
      }

      return [null, null];
    }

    function preloadRangeToPickers(rangeText, startPicker, endPicker, updateFn) {
      const [start, end] = parseThaiRangeText(rangeText);
      if (!start || !end) return;

      startPicker.setDate(start, false);
      endPicker.setDate(end, false);

      startPicker.input.value = formatThaiDate(start);
      endPicker.input.value = formatThaiDate(end);

      updateFn();
    }

    preloadRangeToPickers(document.getElementById("internPeriod")?.value, internStartPicker, internEndPicker,
      updateInternRange);
    preloadRangeToPickers(document.getElementById("travelPeriod")?.value, travelStartPicker, travelEndPicker,
      updateTravelRange);

    const memoForm = document.getElementById("memoForm");
    const submitBtn = document.getElementById("submitBtn");

    const memoSubject = document.getElementById("memoSubject");
    const referenceOrg = document.getElementById("referenceOrg");
    const projectTitle = document.getElementById("projectTitle");
    const courseName = document.getElementById("courseName");
    const eventLocation = document.getElementById("eventLocation");
    const intentionText = document.getElementById("intentionText");

    const spellState = {
      memo_subject: {
        checked: false,
        hasError: false,
        ignored: false,
        errors: [],
        lastText: ""
      },
      reference_org: {
        checked: false,
        hasError: false,
        ignored: false,
        errors: [],
        lastText: ""
      },
      project_title: {
        checked: false,
        hasError: false,
        ignored: false,
        errors: [],
        lastText: ""
      },
      course_name: {
        checked: false,
        hasError: false,
        ignored: false,
        errors: [],
        lastText: ""
      },
      location: {
        checked: false,
        hasError: false,
        ignored: false,
        errors: [],
        lastText: ""
      },
      intention_text: {
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
      if (el.id === "memoSubject") return document.getElementById("memoSubjectSpellBox");
      if (el.id === "referenceOrg") return document.getElementById("referenceOrgSpellBox");
      if (el.id === "projectTitle") return document.getElementById("projectTitleSpellBox");
      if (el.id === "courseName") return document.getElementById("courseNameSpellBox");
      if (el.id === "eventLocation") return document.getElementById("eventLocationSpellBox");
      if (el.id === "intentionText") return document.getElementById("intentionTextSpellBox");
      return null;
    }

    function getSpellLoadingByField(el) {
      if (!el) return null;
      if (el.id === "memoSubject") return document.getElementById("memoSubjectSpellLoading");
      if (el.id === "referenceOrg") return document.getElementById("referenceOrgSpellLoading");
      if (el.id === "projectTitle") return document.getElementById("projectTitleSpellLoading");
      if (el.id === "courseName") return document.getElementById("courseNameSpellLoading");
      if (el.id === "eventLocation") return document.getElementById("eventLocationSpellLoading");
      if (el.id === "intentionText") return document.getElementById("intentionTextSpellLoading");
      return null;
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

    function replaceWrongWordOnce(text, wrongWord, newWord) {
      if (!text || !wrongWord || !newWord) return text;
      return text.replace(new RegExp(escapeRegExp(wrongWord)), newWord);
    }

    function normalizeErrors(errors = [], originalText = "") {
      if (!Array.isArray(errors)) return [];
      const seen = new Set();

      return errors
        .map(item => {
          const wrongWord = String(item?.wrongWord || item?.word || item?.token || "").trim();
          const suggestionsRaw = item?.suggestions || item?.suggestion || [];
          const suggestions = (Array.isArray(suggestionsRaw) ? suggestionsRaw : [suggestionsRaw])
            .map(s => String(s || "").trim())
            .filter(Boolean)
            .filter(s => s !== wrongWord)
            .filter((s, index, arr) => arr.indexOf(s) === index)
            .slice(0, 5);

          return {
            wrongWord,
            suggestions
          };
        })
        .filter(item => {
          if (!item.wrongWord) return false;
          if (originalText && !originalText.includes(item.wrongWord)) return false;
          if (seen.has(item.wrongWord)) return false;
          seen.add(item.wrongWord);
          return true;
        });
    }

    function extractThaiWords(text = "") {
      return String(text)
        .split(/[^\u0E00-\u0E7Fa-zA-Z0-9]+/g)
        .map(word => word.trim())
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

    function clearSpellResult(el) {
      if (!el) return;

      el.classList.remove("spell-error", "spell-ok", "opacity-50");

      const box = getSpellBoxByField(el);
      if (box) {
        box.innerHTML = "";
        box.classList.add("hidden");
      }

      const loading = getSpellLoadingByField(el);
      if (loading) {
        loading.classList.add("hidden");
      }
    }

    function showSpellLoading(el) {
      const loading = getSpellLoadingByField(el);
      if (loading) loading.classList.remove("hidden");
      el?.classList.add("opacity-50");
    }

    function hideSpellLoading(el) {
      const loading = getSpellLoadingByField(el);
      if (loading) loading.classList.add("hidden");
      el?.classList.remove("opacity-50");
    }

    function showSpellOk(el) {
      clearSpellResult(el);

      if ((el?.value || "").trim() !== "") {
        el.classList.add("spell-ok");
      }
    }

    function setSpellPassed(el, fieldName, text, remember = false) {
      if (!el || !fieldName) return;

      const cleanText = String(text || el.value || "").trim();

      if (remember) {
        rememberApprovedText(fieldName, cleanText);
      }

      spellState[fieldName] = {
        checked: true,
        hasError: false,
        ignored: remember,
        errors: [],
        lastText: cleanText
      };

      showSpellOk(el);
    }

    function showSpellError(el, errors = []) {
      if (!el) return;

      clearSpellResult(el);
      el.classList.add("spell-error");

      const fieldName = el.dataset.spellField || "";
      const box = getSpellBoxByField(el);
      if (!box) return;

      const normalized = normalizeErrors(errors, el.value || "");

      if (normalized.length === 0) {
        setSpellPassed(el, fieldName, el.value, false);
        return;
      }

      let html = `<div class="spell-result-box">`;
      html += `<div class="spell-warning">พบคำแนะนำ ${normalized.length} จุด</div>`;

      normalized.forEach((errorItem, index) => {
        html += `
      <div class="mt-2">
        <div class="spell-help-text">คำที่ ${index + 1}: <b>${escapeHtml(errorItem.wrongWord)}</b></div>
    `;

        if (errorItem.suggestions.length > 0) {
          html += `<div class="spell-suggestions">`;

          errorItem.suggestions.forEach(word => {
            html += `
          <button type="button"
            class="spell-suggestion-btn"
            data-field="${escapeHtml(fieldName)}"
            data-target="${escapeHtml(el.id)}"
            data-word="${escapeHtml(word)}"
            data-wrong-word="${escapeHtml(errorItem.wrongWord)}">
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
      <button type="button"
        class="spell-ignore-btn"
        data-field="${escapeHtml(fieldName)}"
        data-target="${escapeHtml(el.id)}">
        ใช้ข้อความเดิม
      </button>
    </div>
  `;

      html += `</div>`;

      box.innerHTML = html;
      box.classList.remove("hidden");
    }

    function shouldCheckSpell(el) {
      if (!el) return false;
      if (el.disabled || el.readOnly) return false;
      return true;
    }


    const SPELL_TIMEOUT_MS = 60000;
    const SPELL_CHUNK_LIMIT = 350;

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
        memoSubject,
        referenceOrg,
        projectTitle,
        courseName,
        eventLocation,
        intentionText
      ];

      let hasAnySpellError = false;
      let firstSpellErrorEl = null;

      for (const el of fields) {
        if (!el || !shouldCheckSpell(el)) continue;

        const fieldName = el.dataset.spellField || "";
        const text = (el.value || "").trim();

        if (!fieldName || !text) continue;

        const state = spellState[fieldName];

        if (
          state &&
          state.checked &&
          !state.hasError &&
          state.lastText === text
        ) {
          continue;
        }

        const ok = await checkSpellField(el);

        if (!ok) {
          hasAnySpellError = true;
          firstSpellErrorEl = firstSpellErrorEl || el;
        }
      }

      if (hasAnySpellError) {
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

    function clearError(el) {
      if (!el) return;
      el.classList.remove("error", "shake");
      const hint = el.parentElement?.querySelector(".hint");
      if (hint) hint.remove();
    }

    function setError(el, msg) {
      if (!el) return false;

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

      return false;
    }

    function focusFirstError(el) {
      if (!el) return;
      el.scrollIntoView({
        behavior: "smooth",
        block: "center"
      });
      setTimeout(() => el.focus?.(), 150);
    }

    function validateSpeakerWorkshopForm() {
      const memoSubject = document.getElementById("memoSubject");
      const referenceOrg = document.getElementById("referenceOrg");
      const referenceNo = document.querySelector('[name="reference_no"]');
      const docDate = document.getElementById("docDateDisplay");
      const docDateNone = document.getElementById("docDateNone");
      const referenceDate = document.getElementById("referenceDate");
      const teacherName = document.getElementById("teacherName");
      const position = document.getElementById("position");
      const projectTitle = document.getElementById("projectTitle");
      const courseName = document.getElementById("courseName");
      const eventLocation = document.getElementById("eventLocation");
      const internStart = document.getElementById("internStart");
      const internEnd = document.getElementById("internEnd");
      const internPeriod = document.getElementById("internPeriod");
      const travelStart = document.getElementById("travelStart");
      const travelEnd = document.getElementById("travelEnd");
      const travelPeriod = document.getElementById("travelPeriod");
      const intentionText = document.getElementById("intentionText");
      const departmentPhone = document.getElementById("departmentPhone");

      [
        memoSubject,
        referenceOrg,
        referenceNo,
        docDate,
        referenceDate,
        teacherName,
        position,
        projectTitle,
        courseName,
        eventLocation,
        intentionText,
        internStart,
        internEnd,
        travelStart,
        travelEnd,
        departmentPhone
      ].forEach(clearError);

      let firstError = null;

      function requireField(el, msg) {
        if (!el?.value?.trim()) {
          firstError = firstError || el;
          setError(el, msg);
          return false;
        }
        return true;
      }

      requireField(memoSubject, "กรุณากรอกเรื่อง");
      requireField(teacherName, "ไม่พบชื่อเจ้าของเอกสาร กรุณาตรวจสอบข้อมูลผู้ใช้งาน");
      requireField(position, "กรุณากรอกตำแหน่ง");
      requireField(referenceOrg, "กรุณากรอกหน่วยงานผู้ออกหนังสืออ้างอิง");

      if (!docDateNone?.checked) {
        requireField(docDate, "กรุณาเลือกวัน เดือน ปี ที่ต้องการให้ปรากฎบนบันทึกข้อความ");
      }

      requireField(referenceNo, "กรุณากรอกเลขที่หนังสืออ้างอิง");
      requireField(referenceDate, "กรุณาเลือกวันที่หนังสืออ้างอิง");
      requireField(projectTitle, "กรุณากรอกชื่อโครงการอบรม");
      requireField(courseName, "กรุณากรอกชื่อหลักสูตร");
      requireField(eventLocation, "กรุณากรอกสถานที่จัดงาน");
      requireField(intentionText, "กรุณากรอกความประสงค์");

      if (!internPeriod?.value?.trim()) {
        firstError = firstError || internStart;
        setError(internStart, "กรุณาเลือกวันที่เริ่มโครงการ");
        setError(internEnd, "กรุณาเลือกวันที่สิ้นสุดโครงการ");
      }

      if (!travelPeriod?.value?.trim()) {
        firstError = firstError || travelStart;
        setError(travelStart, "กรุณาเลือกวันที่เดินทางไป");
        setError(travelEnd, "กรุณาเลือกวันที่เดินทางกลับ");
      }

      requireField(departmentPhone, "กรุณากรอกเบอร์โทรภาควิชา");

      if (firstError) {
        focusFirstError(firstError);
        return false;
      }

      return true;
    }

    document.addEventListener("input", event => {
      const input = event.target;
      if (!input?.dataset?.spellField) return;

      const fieldName = input.dataset.spellField || "";

      if (!spellState[fieldName]) return;

      spellState[fieldName] = {
        checked: false,
        hasError: false,
        ignored: false,
        errors: [],
        lastText: ""
      };

      delete correctedTexts[fieldName];
      clearSpellResult(input);
    });

    document.addEventListener("click", event => {
      const suggestionBtn = event.target.closest(".spell-suggestion-btn");

      if (suggestionBtn) {
        const input = document.getElementById(suggestionBtn.dataset.target);
        if (!input) return;

        const fieldName = input.dataset.spellField || "";
        const wrongWord = suggestionBtn.dataset.wrongWord || "";
        const newWord = suggestionBtn.dataset.word || "";

        if (!fieldName || !wrongWord || !newWord) return;

        input.value = replaceWrongWordOnce(input.value, wrongWord, newWord);

        correctedTexts[fieldName] = input.value.trim();
        setSpellPassed(input, fieldName, input.value.trim(), false);
        return;
      }

      const ignoreBtn = event.target.closest(".spell-ignore-btn");

      if (ignoreBtn) {
        const input = document.getElementById(ignoreBtn.dataset.target);
        if (!input) return;

        const fieldName = input.dataset.spellField || "";
        if (!fieldName) return;

        setSpellPassed(input, fieldName, input.value.trim(), true);
      }
    });

    memoForm?.addEventListener("submit", async event => {
      event.preventDefault();
      if (submitBtn) submitBtn.disabled = true;

      try {
        syncDocDateOptionUI();

        if (docDateNone?.checked) {
          if (docDateDisplay) docDateDisplay.value = "";
          if (docDateHidden) docDateHidden.value = "";
        } else if (docPicker?.selectedDates?. [0] && docDateHidden) {
          docDateHidden.value = formatYMDSpeaker(docPicker.selectedDates[0]);
        }

        if (!validateSpeakerWorkshopForm()) return;
        const okSpell = await checkAllSpellFields();
        if (!okSpell) return;
        memoForm.submit();
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
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
        sub.dataset.current = sub.value;
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

    main.addEventListener("change", () => {
      if (window.CATEGORY_LOCKED_BY_STATUS) return;
      sub.dataset.current = "";
      syncUI(false);

      const selectedOption = sub.options[sub.selectedIndex];
      const target = buildTemplateUrl(selectedOption?.dataset?.url || "");

      if (!target || target === "#") return;
      window.location.href = target;
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
      window.location.href = target;
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
    const headerTextInput = document.getElementById("headerTextInput");
    const departmentPhoneInput = document.getElementById("departmentPhone");

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

    function syncHeaderText() {
      if (!headerTextInput) return;

      const facultyText = (facultySelect.value || "").trim();
      const departmentTextRaw = (departmentSelect.value || "").trim();
      const departmentText = departmentTextRaw ?
        (departmentTextRaw.startsWith("ภาควิชา") ? departmentTextRaw : "ภาควิชา" + departmentTextRaw) :
        "";
      const departmentPhone = (departmentPhoneInput?.value || "").trim();

      let headerText = [facultyText, departmentText].filter(Boolean).join(" ");
      if (departmentPhone !== "") {
        headerText += " โทร. " + departmentPhone;
      }

      headerTextInput.value = headerText;
    }

    function syncSelectedDepartmentId() {
      const selectedOption = departmentSelect.options[departmentSelect.selectedIndex];

      if (!selectedOption) {
        departmentIdInput.value = "";
        departmentSelect.dataset.currentDepartmentId = "";
        syncHeaderText();
        return;
      }

      const selectedDepartmentId = selectedOption.dataset.departmentId || "";
      departmentIdInput.value = selectedDepartmentId;
      departmentSelect.dataset.currentDepartmentId = selectedDepartmentId;
      syncHeaderText();
    }

    facultySelect.addEventListener("change", () => {
      renderDepartmentOptions(false);
    });

    departmentSelect.addEventListener("change", syncSelectedDepartmentId);

    departmentPhoneInput?.addEventListener("input", syncHeaderText);
    document.getElementById("memoForm")?.addEventListener("submit", syncHeaderText);

    // โหลดหน้า: แสดงค่าของ user ก่อน แต่ยังเปลี่ยนเลือกคณะ/ภาควิชาอื่นได้
    renderDepartmentOptions(true);
    syncHeaderText();
  });
  </script>

</body>

</html>