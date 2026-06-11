<?php
// pro_letter/documents/infor_free_document.php
$CURRENT_MAIN = $_GET['main'] ?? 'internal';
$CURRENT_SUB  = $_GET['sub']  ?? 'เอกสารอิสระ';

$ALLOWED_MAIN = ['internal', 'external'];
if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
    $CURRENT_MAIN = 'internal';
}

session_start();
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/require_profile_completed.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Pro_letter/login.html');
    exit;
}

function hv($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0);

if ($roleId === 1) {
    $homePath = '/Pro_letter/admin/home.php';
} elseif ($roleId === 2) {
    $homePath = '/Pro_letter/officer/home.php';
} else {
    $homePath = '/Pro_letter/user/home.php';
}

$pdo = db();
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $documentId > 0;
$doc = [];
$formData = [];
$categoryLocked = false;

$templateId = 0;
try {
    $tplStmt = $pdo->prepare("SELECT template_id FROM templates WHERE template_code = 'FREE_DOCUMENT' LIMIT 1");
    $tplStmt->execute();
    $templateId = (int)$tplStmt->fetchColumn();
} catch (Throwable $e) {
    $templateId = 0;
}

if ($templateId <= 0) {
    $templateId = 11;
}

if ($isEdit) {
    $docStmt = $pdo->prepare("SELECT * FROM documents WHERE document_id = :id LIMIT 1");
    $docStmt->execute([':id' => $documentId]);
    $doc = $docStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$doc) {
        header('Location: ' . $homePath . '?err=notfound');
        exit;
    }

    // ใช้ template จริงของเอกสารตอนแก้ไข เพื่อไม่ให้ dropdown ไปเลือกหมวดย่อยตัวแรกผิดเป็นฟอร์มอื่น
    try {
        $editTemplateId = (int)($doc['template_id'] ?? 0);
        if ($editTemplateId > 0) {
            $editTplStmt = $pdo->prepare("
                SELECT template_id, template_name, template_group
                FROM templates
                WHERE template_id = :template_id
                LIMIT 1
            ");
            $editTplStmt->execute([':template_id' => $editTemplateId]);
            $editTpl = $editTplStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if ($editTpl) {
                $templateId = (int)($editTpl['template_id'] ?? $templateId);
                $CURRENT_MAIN = (string)($editTpl['template_group'] ?? $CURRENT_MAIN);
                $CURRENT_SUB = (string)($editTpl['template_name'] ?? $CURRENT_SUB);

                if (!in_array($CURRENT_MAIN, $ALLOWED_MAIN, true)) {
                    $CURRENT_MAIN = 'internal';
                }
            }
        }
    } catch (Throwable $e) {
        // ไม่ต้องทำอะไร ถ้าดึง template ไม่ได้ให้ใช้ค่าเดิม
    }

    $status = trim((string)($doc['status'] ?? ''));
    $checkedStatuses = ['approved', 'ผ่านการตรวจสอบ', 'ผ่านการตรวจสอบแล้ว', 'ตรวจสอบแล้ว'];
    if (in_array($status, $checkedStatuses, true)) {
        header('Location: /Pro_letter/form_Memo/form_memo_free_document.php?id=' . $documentId . '&err=no_permission');
        exit;
    }

    $lockedStatuses = ['draft', 'submitted', 'reviewing', 'rejected', 'รอยืนยันการส่ง', 'รอตรวจสอบ', 'รอแก้ไข'];
    $categoryLocked = in_array($status, $lockedStatuses, true);

    $valStmt = $pdo->prepare("
        SELECT tf.field_key, dv.value_text
        FROM document_values dv
        JOIN template_fields tf ON tf.field_id = dv.field_id
        WHERE dv.document_id = :id
    ");
    $valStmt->execute([':id' => $documentId]);
    foreach ($valStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $formData[(string)$row['field_key']] = (string)($row['value_text'] ?? '');
    }
}

$currentUserFacultyId = 0;
$currentUserDepartmentId = (int)($doc['department_id'] ?? 0);
$currentUserFacultyName = $formData['free_faculty'] ?? $formData['faculty'] ?? 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
$currentUserDepartmentName = $formData['free_department'] ?? $formData['department'] ?? 'เทคโนโลยีสารสนเทศ';
$facultyOptions = [];
$departmentOptions = [];

try {
    $userOrgStmt = $pdo->prepare("
        SELECT u.department_id, d.department_name, d.faculty_id, f.faculty_name
        FROM users u
        LEFT JOIN departments d ON d.department_id = u.department_id
        LEFT JOIN faculties f ON f.faculty_id = d.faculty_id
        WHERE u.user_id = :user_id
        LIMIT 1
    ");
    $userOrgStmt->execute([':user_id' => $userId]);
    $userOrg = $userOrgStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$isEdit) {
        $currentUserDepartmentId = (int)($userOrg['department_id'] ?? 0);
        $currentUserFacultyId = (int)($userOrg['faculty_id'] ?? 0);
        $currentUserFacultyName = trim((string)($userOrg['faculty_name'] ?? '')) ?: $currentUserFacultyName;
        $currentUserDepartmentName = trim((string)($userOrg['department_name'] ?? '')) ?: $currentUserDepartmentName;
    }

    $facultyOptions = $pdo->query("SELECT faculty_id, faculty_name FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $departmentOptions = $pdo->query("SELECT department_id, department_name, faculty_id FROM departments ORDER BY faculty_id ASC, department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $facultyOptions = [];
    $departmentOptions = [];
}

$templateOptions = [];
try {
    $templateStmt = $pdo->query("
        SELECT template_id, template_name, question_path, document_path, template_group, sort_order
        FROM templates
        WHERE is_active = 1
        ORDER BY
            CASE
                WHEN template_group = 'internal' THEN 1
                WHEN template_group = 'external' THEN 2
                ELSE 3
            END,
            sort_order ASC,
            template_id ASC
    ");
    $templateOptions = $templateStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $templateOptions = [];
}

$formAction = $isEdit ? '/Pro_letter/documents/update_memo.php' : '/Pro_letter/documents/save_memo.php';

$docDateValue = $formData['free_doc_date'] ?? $formData['doc_date'] ?? ($doc['doc_date'] ?? date('Y-m-d'));
$docDateSaved = trim((string)$docDateValue);
$hasSavedDocDateField = array_key_exists('free_doc_date', $formData) || array_key_exists('doc_date', $formData) || array_key_exists('doc_date', $doc);
$docDateOption = ($hasSavedDocDateField && $docDateSaved === '') ? 'no_date' : 'use_date';
$subjectValue = $formData['free_subject'] ?? $formData['subject'] ?? ($doc['subject'] ?? '');
$toPersonValue = $formData['free_to_person'] ?? $formData['to_person'] ?? 'คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม';
$departmentPhoneValue = $formData['free_department_phone'] ?? $formData['department_phone'] ?? '';
$paragraph1Value = $formData['free_paragraph_1'] ?? $formData['paragraph_1'] ?? '';
$paragraph2Value = $formData['free_paragraph_2'] ?? $formData['paragraph_2'] ?? '';
$paragraph3Value = $formData['free_paragraph_3'] ?? $formData['paragraph_3'] ?? '';
$signerNameValue = $formData['free_signer_name'] ?? $formData['signer_name'] ?? '';
$signerPositionValue = $formData['free_signer_position'] ?? $formData['signer_position'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>แบบฟอร์มเอกสารอิสระ</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url("https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap");

    html, :root { --base-fs: 16px; }
    body, label, input, textarea, select, option, button, span, div {
      font-family: "Sarabun", sans-serif;
      font-size: var(--base-fs);
    }

    select, input, textarea { line-height: 1.4; }
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
    .lbl {
      font-weight: 600;
      color: #1f2937;
      white-space: nowrap;
    }
    .lbl.asterisk::after {
      content: " *";
      color: #ef4444;
      font-weight: 700;
      margin-left: 4px;
    }
    .field-input {
      width: 100%;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      padding: 0.5rem 0.75rem;
      background: #fff;
      box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .field-input:focus {
      outline: none;
      border-color: #11c2b9;
      box-shadow: 0 0 0 3px rgba(17, 194, 185, 0.16);
    }
    .section-box {
      background-color:#e3f9f8;
      border:2px solid #11c2b9;
      border-radius:25px;
    }
    .btn-main {
      background:#11C2B9;
      color:#fff;
      font-weight:700;
      width:170px;
      height:38px;
      border-radius:0.375rem;
      transition:0.2s;
    }
    .btn-main:hover { background:#0fa39c; }
    .form-row-inline {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-wrap: nowrap;
    }
    .form-row-inline .row-label {
      min-width: 145px;
      margin-bottom: 0;
      flex-shrink: 0;
    }
    .add-paragraph-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      border: 1px solid #d1d5db;
      background: #f9fafb;
      color: #111827;
      font-weight: 600;
      border-radius: 0.375rem;
      padding: 0.55rem 1rem;
      transition: 0.2s;
    }
    .add-paragraph-btn:hover:not(:disabled) {
      background: #f3f4f6;
      border-color: #9ca3af;
    }
    .add-paragraph-btn:disabled {
      background: #e5e7eb;
      color: #9ca3af;
      cursor: not-allowed;
      border-color: #d1d5db;
    }
    .paragraph-box {
      margin-top: 0.75rem;
    }
    .paragraph-toolbar {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 0.35rem;
    }
    .remove-paragraph-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border: 1px solid #fecaca;
      background: #fff1f2;
      color: #dc2626;
      font-weight: 600;
      border-radius: 0.375rem;
      padding: 0.4rem 0.75rem;
      transition: 0.2s;
    }
    .remove-paragraph-btn:hover {
      background: #fee2e2;
      border-color: #fca5a5;
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
      color: #0f766e;
      font-size: 13px;
    }
    .spell-loading-row {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .spell-spinner {
      width: 14px;
      height: 14px;
      border: 2px solid #99f6e4;
      border-top-color: #0f766e;
      border-radius: 999px;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    @media (max-width: 768px) {
      .form-row-inline {
        align-items: flex-start;
        flex-direction: column;
      }
      .form-row-inline .row-label {
        min-width: 0;
      }
    }
  </style>
  <script>
    window.CATEGORY_LOCKED_BY_STATUS = <?= $categoryLocked ? 'true' : 'false' ?>;
  </script>
</head>

<body class="bg-gray-100">
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/Pro_letter/includes/role_header.php'; ?>

<form method="post" action="<?= hv($formAction) ?>" id="memoForm">
  <input type="hidden" name="department_id" id="selectedDepartmentId" value="<?= (int)$currentUserDepartmentId ?>">
  <?php if ($isEdit): ?>
    <input type="hidden" name="document_id" value="<?= (int)$documentId ?>">
    <input type="hidden" name="mode" value="update">
    <input type="hidden" name="redirect_back" value="/Pro_letter/form_Memo/form_memo_free_document.php?id=<?= (int)$documentId ?>">
  <?php else: ?>
    <input type="hidden" name="mode" value="create">
  <?php endif; ?>

  <input type="hidden" name="template_id" value="<?= (int)$templateId ?>">
  <input type="hidden" name="document_type_name" value="เอกสารอิสระ">
  <input type="hidden" name="document_type" value="infor_free_document">
  <input type="hidden" name="purpose" value="free_document">
  <input type="hidden" name="target_form" value="form_memo_free_document.php">
  <input type="hidden" name="redirect_to" value="form_memo_free_document.php">
  <input type="hidden" name="template_page" value="form_memo_free_document.php">

  <div class="w-[900px] mx-auto mt-16 mb-8 bg-white shadow-md rounded-md p-8" style="min-height:1122px">
    <h1 class="text-center font-bold mb-6 text-black">แบบฟอร์มเอกสารอิสระ</h1>

    <div class="mb-8 p-6 section-box" style="min-height:170px;">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="flex items-center gap-3">
          <label class="lbl w-28 text-right">หมวดหลัก:</label>
          <select name="main_category" class="custom-select w-full" id="mainCategory" <?= $categoryLocked ? 'disabled data-category-locked="1"' : '' ?>>
            <option value="internal" <?= ($CURRENT_MAIN === 'internal' ? 'selected' : '') ?>>ภายใน</option>
            <option value="external" <?= ($CURRENT_MAIN === 'external' ? 'selected' : '') ?>>ภายนอก</option>
          </select>
        </div>

        <div class="flex items-center gap-3">
          <label class="lbl w-28 text-right">หมวดย่อย:</label>
          <select name="sub_category" class="custom-select w-full" id="subCategory" data-current="<?= hv($CURRENT_SUB) ?>" <?= $categoryLocked ? 'data-category-locked="1" disabled' : '' ?>></select>
          <?php if ($categoryLocked): ?>
            <input type="hidden" name="main_category" value="<?= hv($CURRENT_MAIN) ?>">
            <input type="hidden" name="sub_category" value="<?= hv($CURRENT_SUB) ?>">
            <input type="hidden" name="main_category_locked_value" value="1">
          <?php endif; ?>
        </div>

        <div class="flex items-center gap-3">
          <label class="lbl w-28 text-right">คณะ:</label>
          <select name="faculty" class="custom-select w-full" id="faculty" data-current-faculty-id="<?= (int)$currentUserFacultyId ?>">
            <?php foreach ($facultyOptions as $fac): ?>
              <?php $facId = (int)($fac['faculty_id'] ?? 0); $facName = trim((string)($fac['faculty_name'] ?? '')); ?>
              <option value="<?= hv($facName) ?>" data-faculty-id="<?= $facId ?>" <?= ($facName === $currentUserFacultyName ? 'selected' : '') ?>><?= hv($facName) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex items-center gap-3">
          <label class="lbl w-28 text-right">ภาควิชา:</label>
          <select name="department" class="custom-select w-full" id="dept" data-current-department-id="<?= (int)$currentUserDepartmentId ?>">
            <?php foreach ($departmentOptions as $deptRow): ?>
              <?php
                $deptId = (int)($deptRow['department_id'] ?? 0);
                $deptFacultyId = (int)($deptRow['faculty_id'] ?? 0);
                $deptName = trim((string)($deptRow['department_name'] ?? ''));
              ?>
              <option value="<?= hv($deptName) ?>" data-department-id="<?= $deptId ?>" data-faculty-id="<?= $deptFacultyId ?>" <?= ($deptName === $currentUserDepartmentName || $deptId === $currentUserDepartmentId ? 'selected' : '') ?>><?= hv($deptName) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="space-y-5">
      <div>
        <div class="flex flex-col gap-2">
          <label class="lbl text-gray-800 whitespace-nowrap" for="docDateDisplay">1.วัน เดือน ปี :</label>

          <div class="flex items-center gap-3 flex-nowrap pl-4 w-full overflow-x-auto">
            <label class="flex items-center gap-2 text-gray-800 whitespace-nowrap shrink-0">
              <input type="radio" name="doc_date_option" id="docDateUse" value="use_date" class="accent-black"
                <?= ($docDateOption === 'use_date') ? 'checked' : '' ?>>
              วันที่
            </label>

            <div class="relative shrink-0" id="docDatePickerWrap">
              <input type="text" id="docDateDisplay" value="<?= hv($docDateSaved) ?>"
                class="border rounded-md p-2 shadow-sm w-48 pr-10 cursor-pointer" placeholder="เลือกวันที่"
                readonly />
              <input type="hidden" name="doc_date" id="docDate" value="<?= hv($docDateSaved) ?>" />
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

      <div class="form-row-inline">
        <label class="lbl asterisk row-label">2. เรื่อง :</label>
        <div class="w-full">
          <textarea name="subject" id="subjectInput" data-spell-field="subject" rows="2" class="field-input" placeholder="เช่น ขอความอนุเคราะห์ / ขออนุมัติ / ขอแจ้งเพื่อทราบ" required><?= hv($subjectValue) ?></textarea>
          <div id="subjectInputSpellBox" class="spell-box hidden"></div>
          <div id="subjectInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <div class="form-row-inline">
        <label class="lbl asterisk row-label">3. เรียน :</label>
        <div class="w-full">
          <input type="text" name="to_person" id="toPersonInput" data-spell-field="to_person" class="field-input" placeholder="เช่น ผู้อำนวยการศูนย์พัฒนาศักยภาพบุคลากรและบริการวิชาการ" required>
          <div id="toPersonInputSpellBox" class="spell-box hidden"></div>
          <div id="toPersonInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>

      <div class="form-row-inline">
        <label class="lbl asterisk row-label">4. เบอร์โทรภาควิชา :</label>
        <span class="text-gray-800 whitespace-nowrap">โทร.</span>
        <input type="text" name="department_phone" class="field-input max-w-[235px]" value="<?= hv($departmentPhoneValue) ?>" placeholder="เช่น 7064" required>
        <span class="text-gray-800 whitespace-nowrap">ที่ต้องการให้ขึ้นที่ส่วนราชการ</span>
      </div>

      <div>
        <label class="lbl asterisk block mb-1">5. เนื้อหาเอกสาร :</label>
        <textarea name="free_paragraph_1" id="paragraph1Input" data-spell-field="free_paragraph_1" rows="6" class="field-input" placeholder="กรอกเนื้อหาเอกสารย่อหน้าแรก" required><?= hv($paragraph1Value) ?></textarea>
        <div id="paragraph1InputSpellBox" class="spell-box hidden"></div>
        <div id="paragraph1InputSpellLoading" class="spell-loading hidden">
          <div class="spell-loading-row">
            <div class="spell-spinner"></div>
            <span>กำลังตรวจคำผิด...</span>
          </div>
        </div>

        <div id="paragraph2Box" class="paragraph-box <?= trim((string)$paragraph2Value) !== '' ? '' : 'hidden' ?>">
          <div class="paragraph-toolbar">
            <button type="button" id="removeParagraph2Btn" class="remove-paragraph-btn">
              <span>−</span>
              <span>ลบย่อหน้านี้</span>
            </button>
          </div>
          <textarea name="free_paragraph_2" id="paragraph2Input" data-spell-field="free_paragraph_2" rows="6" class="field-input" placeholder="กรอกเนื้อหาเพิ่มเติมได้ ถ้าไม่มีสามารถเว้นว่างได้"><?= hv($paragraph2Value) ?></textarea>
          <div id="paragraph2InputSpellBox" class="spell-box hidden"></div>
          <div id="paragraph2InputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>

        <div id="paragraph3Box" class="paragraph-box <?= trim((string)$paragraph3Value) !== '' ? '' : 'hidden' ?>">
          <div class="paragraph-toolbar">
            <button type="button" id="removeParagraph3Btn" class="remove-paragraph-btn">
              <span>−</span>
              <span>ลบย่อหน้านี้</span>
            </button>
          </div>
          <textarea name="free_paragraph_3" id="paragraph3Input" data-spell-field="free_paragraph_3" rows="6" class="field-input" placeholder="กรอกเนื้อหาเพิ่มเติมได้ ถ้าไม่มีสามารถเว้นว่างได้"><?= hv($paragraph3Value) ?></textarea>
          <div id="paragraph3InputSpellBox" class="spell-box hidden"></div>
          <div id="paragraph3InputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <button type="button" id="addParagraphBtn" class="add-paragraph-btn">
            <span>+</span>
            <span>เพิ่มย่อหน้า</span>
          </button>
        </div>
      </div>

      <div class="form-row-inline">
        <label class="lbl asterisk row-label">6. ผู้ลงนาม :</label>
        <div class="w-full">
          <input type="text" name="free_signer_name" id="signerNameInput" data-spell-field="free_signer_name" class="field-input" value="<?= hv($signerNameValue) ?>" placeholder="กรอกชื่อผู้ลงนาม" required>
          <div id="signerNameInputSpellBox" class="spell-box hidden"></div>
          <div id="signerNameInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
        <label class="lbl asterisk row-label">ตำแหน่ง :</label>
        <div class="w-full">
          <input type="text" name="free_signer_position" id="signerPositionInput" data-spell-field="free_signer_position" class="field-input" value="<?= hv($signerPositionValue) ?>" placeholder="กรอกตำแหน่งผู้ลงนาม" required>
          <div id="signerPositionInputSpellBox" class="spell-box hidden"></div>
          <div id="signerPositionInputSpellLoading" class="spell-loading hidden">
            <div class="spell-loading-row">
              <div class="spell-spinner"></div>
              <span>กำลังตรวจคำผิด...</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-24 flex justify-end">
      <button type="submit" class="btn-main">
        ดำเนินการ
      </button>
    </div>
  </div>
</form>

<script>
const templateOptions = <?= json_encode($templateOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const currentQuestionPath = '/documents/infor_free_document.php';
const mainCategory = document.getElementById('mainCategory');
const subCategory = document.getElementById('subCategory');
const facultySelect = document.getElementById('faculty');
const deptSelect = document.getElementById('dept');
const selectedDepartmentId = document.getElementById('selectedDepartmentId');

const docDateDisplay = document.getElementById('docDateDisplay');
const docDateHidden = document.getElementById('docDate');
const docDateUse = document.getElementById('docDateUse');
const docDateNone = document.getElementById('docDateNone');
const paragraph2Box = document.getElementById('paragraph2Box');
const paragraph3Box = document.getElementById('paragraph3Box');
const addParagraphBtn = document.getElementById('addParagraphBtn');
const removeParagraph2Btn = document.getElementById('removeParagraph2Btn');
const removeParagraph3Btn = document.getElementById('removeParagraph3Btn');

const memoForm = document.getElementById('memoForm');

const spellFields = Array.from(document.querySelectorAll('[data-spell-field]'));
const spellState = {};
const spellCache = {};
const approvedWords = new Set();
const approvedTexts = {};
const correctedTexts = {};

spellFields.forEach(el => {
  const fieldName = el.dataset.spellField || '';
  if (!fieldName) return;
  spellState[fieldName] = {
    checked: false,
    hasError: false,
    ignored: false,
    apiError: false,
    errors: [],
    lastText: ''
  };
});

const SPELL_TIMEOUT_MS = 60000;
const SPELL_CHUNK_LIMIT = 350;
const SPELL_API_BASE_URL =
  (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
    ? 'http://127.0.0.1:8001'
    : 'https://checkspell-api.onrender.com';
const SPELL_CHECK_API_URL = `${SPELL_API_BASE_URL}/api/spell-check`;

function getFieldName(el) {
  return el?.dataset?.spellField || '';
}

function getSpellBoxByField(el) {
  if (!el) return null;
  return document.getElementById(`${el.id}SpellBox`);
}

function getSpellLoadingByField(el) {
  if (!el) return null;
  return document.getElementById(`${el.id}SpellLoading`);
}

function showSpellLoading(el) {
  const box = getSpellLoadingByField(el);
  if (box) box.classList.remove('hidden');
}

function hideSpellLoading(el) {
  const box = getSpellLoadingByField(el);
  if (box) box.classList.add('hidden');
}

function clearSpellResult(el) {
  if (!el) return;

  el.classList.remove('spell-error', 'spell-ok');

  const box = getSpellBoxByField(el);
  if (box) {
    box.innerHTML = '';
    box.classList.add('hidden');
  }
}

function showSpellOk(el) {
  clearSpellResult(el);

  const text = (el.value || '').trim();
  if (text !== '') {
    el.classList.add('spell-ok');
  }
}

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function escapeRegExp(str) {
  return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function replaceWholeWordOnce(text, wrongWord, newWord) {
  if (!text || !wrongWord || !newWord) return text;
  return text.replace(new RegExp(escapeRegExp(wrongWord)), newWord);
}

function splitTextForSpellCheck(text, limit = SPELL_CHUNK_LIMIT) {
  const clean = String(text || '').trim();
  if (!clean) return [];
  if (clean.length <= limit) return [clean];

  const parts = clean
    .split(/(\n+|[.!?！？。]|[;；]|[,，])/)
    .reduce((acc, part) => {
      if (!part) return acc;
      const last = acc[acc.length - 1] || '';
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
  let current = '';

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
        current = '';
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

function normalizeSpellErrorItem(item) {
  if (!item) return null;

  const wrongWord = String(
    item.wrongWord ??
    item.wrong_word ??
    item.word ??
    item.token ??
    item.error ??
    item.text ??
    ''
  ).trim();

  if (!wrongWord) return null;

  let suggestions = item.suggestions ?? item.suggestion ?? item.correct ?? item.candidates ?? [];
  if (typeof suggestions === 'string') suggestions = [suggestions];
  if (!Array.isArray(suggestions)) suggestions = [];

  suggestions = suggestions
    .map(s => String(s || '').trim())
    .filter(Boolean)
    .filter(s => s !== wrongWord)
    .filter((s, i, arr) => arr.indexOf(s) === i)
    .slice(0, 5);

  return { wrongWord, suggestions };
}

function normalizeSpellErrors(errors = [], originalText = '') {
  if (!Array.isArray(errors)) return [];

  const seen = new Set();
  const normalized = [];

  for (const item of errors) {
    const data = normalizeSpellErrorItem(item);
    if (!data) continue;
    if (originalText && !originalText.includes(data.wrongWord)) continue;
    if (seen.has(data.wrongWord)) continue;

    seen.add(data.wrongWord);
    normalized.push(data);
  }

  return normalized;
}

function extractThaiWords(text = '') {
  return String(text)
    .split(/[^\u0E00-\u0E7Fa-zA-Z0-9]+/g)
    .map(w => w.trim())
    .filter(Boolean);
}

function rememberApprovedText(fieldName, text) {
  const cleanText = String(text || '').trim();
  if (!fieldName || !cleanText) return;

  approvedTexts[fieldName] = cleanText;

  extractThaiWords(cleanText).forEach(word => {
    approvedWords.add(word);
  });
}

function isApprovedText(fieldName, text) {
  const cleanText = String(text || '').trim();
  return !!(fieldName && cleanText && approvedTexts[fieldName] === cleanText);
}

function filterApprovedErrors(errors = []) {
  return errors.filter(item => {
    const wrongWord = String(item?.wrongWord || '').trim();
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

function showSpellError(el, errors = []) {
  clearSpellResult(el);
  el.classList.add('spell-error');

  const box = getSpellBoxByField(el);
  if (!box) return;

  errors = normalizeSpellErrors(errors, el.value || '');

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
  box.classList.remove('hidden');
}

async function fetchSpellChunk(fieldName, chunkText) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), SPELL_TIMEOUT_MS);

  try {
    const response = await fetch(SPELL_CHECK_API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
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

    if (error && error.name === 'AbortError') {
      return {
        aborted: true,
        hasError: false,
        has_error: false,
        errors: []
      };
    }

    throw error;
  }
}

async function checkSpellField(el) {
  if (!el) return true;
  if (el.disabled || el.readOnly) return true;

  clearSpellResult(el);

  const text = (el.value || '').trim();
  if (!text) return true;

  const fieldName = getFieldName(el);
  if (!fieldName) return true;

  const cacheKey = `${fieldName}::${text}`;

  if (isApprovedText(fieldName, text) || correctedTexts[fieldName] === text) {
    setSpellPassed(el, fieldName, text, false);
    return true;
  }

  if (spellCache[cacheKey]) {
    const cached = spellCache[cacheKey];
    const cachedErrors = filterApprovedErrors(normalizeSpellErrors(cached.errors || [], text));

    if ((cached.hasError || cached.has_error) && cachedErrors.length > 0) {
      spellState[fieldName] = {
        checked: true,
        hasError: true,
        ignored: false,
        apiError: false,
        errors: cachedErrors,
        lastText: text
      };
      showSpellError(el, cachedErrors);
      return false;
    }

    setSpellPassed(el, fieldName, text, false);
    return true;
  }

  el.classList.add('opacity-50');
  showSpellLoading(el);

  try {
    const chunks = splitTextForSpellCheck(text);
    let allErrors = [];

    for (const chunk of chunks) {
      const result = await fetchSpellChunk(fieldName, chunk);
      if (result.aborted) continue;

      const chunkErrors = normalizeSpellErrors(result.errors || [], chunk);
      if ((result.hasError || result.has_error) && chunkErrors.length > 0) {
        allErrors = allErrors.concat(chunkErrors);
      }
    }

    allErrors = filterApprovedErrors(normalizeSpellErrors(allErrors, text));

    const finalResult = {
      hasError: allErrors.length > 0,
      has_error: allErrors.length > 0,
      errors: allErrors
    };

    spellCache[cacheKey] = finalResult;

    if (allErrors.length > 0) {
      spellState[fieldName] = {
        checked: true,
        hasError: true,
        ignored: false,
        apiError: false,
        errors: allErrors,
        lastText: text
      };
      showSpellError(el, allErrors);
      return false;
    }

    setSpellPassed(el, fieldName, text, false);
    return true;
  } catch (error) {
    console.error('Spell check API error:', error);

    spellState[fieldName] = {
      checked: false,
      hasError: false,
      ignored: false,
      apiError: false,
      errors: [],
      lastText: ''
    };

    clearSpellResult(el);
    return true;
  } finally {
    el.classList.remove('opacity-50');
    hideSpellLoading(el);
  }
}

async function checkFreeDocumentSpell() {
  let allPassed = true;
  let firstError = null;

  for (const el of spellFields) {
    if (!el || el.disabled || el.readOnly) continue;

    const text = (el.value || '').trim();
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

    const passed = await checkSpellField(el);
    if (!passed) {
      allPassed = false;
      if (!firstError) firstError = el;
    }
  }

  for (const key in spellState) {
    const state = spellState[key];
    const remainingErrors = filterApprovedErrors(state.errors || []);

    if (state.checked && state.hasError && remainingErrors.length > 0) {
      allPassed = false;
      const el = spellFields.find(field => getFieldName(field) === key);
      if (!firstError && el) firstError = el;
    }
  }

  if (firstError) {
    firstError.scrollIntoView({
      behavior: 'smooth',
      block: 'center'
    });

    setTimeout(() => {
      try {
        firstError.focus({ preventScroll: true });
      } catch (err) {
        firstError.focus();
      }
    }, 350);
  }

  return allPassed;
}

document.addEventListener('click', (e) => {
  const ignoreBtn = e.target.closest('.spell-ignore-btn');
  if (!ignoreBtn) return;

  const target = document.getElementById(ignoreBtn.dataset.target);
  if (!target) return;

  const fieldName = getFieldName(target);
  const currentText = (target.value || '').trim();

  setSpellPassed(target, fieldName, currentText, true);
});

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.spell-suggestion-btn');
  if (!btn) return;

  const target = document.getElementById(btn.dataset.target);
  const word = btn.dataset.word;
  const wrongWord = btn.dataset.wrongWord;

  if (!target || !word || !wrongWord) return;

  const beforeText = target.value || '';
  const afterText = replaceWholeWordOnce(beforeText, wrongWord, word);
  target.value = afterText;

  const fieldName = getFieldName(target);
  const currentText = (target.value || '').trim();

  correctedTexts[fieldName] = currentText;
  approvedWords.add(word);

  setSpellPassed(target, fieldName, currentText, false);
});

spellFields.forEach((el) => {
  el.addEventListener('input', () => {
    const fieldName = getFieldName(el);
    const state = spellState[fieldName];

    if (state) {
      state.checked = false;
      state.hasError = false;
      state.apiError = false;
      state.errors = [];
      state.lastText = '';
    }

    clearSpellResult(el);
  });
});

const monthsTH = [
  'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
  'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
];

function parseYMD(value) {
  if (!value) return null;
  const parts = String(value).split('-').map(Number);
  if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
  return new Date(parts[0], parts[1] - 1, parts[2]);
}

function toThaiDisplay(date) {
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
  return `${date.getDate()} ${monthsTH[date.getMonth()]} ${date.getFullYear() + 543}`;
}

function syncDocDateOptionUI() {
  const isNoDate = !!docDateNone?.checked;

  if (isNoDate) {
    if (docDateDisplay) {
      docDateDisplay.value = '';
      docDateDisplay.disabled = true;
      docDateDisplay.classList.add('bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
    }

    if (docDateHidden) {
      docDateHidden.value = '';
    }
  } else {
    if (docDateDisplay) {
      docDateDisplay.disabled = false;
      docDateDisplay.classList.remove('bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
    }
  }
}

let docPicker = null;
if (docDateDisplay && window.flatpickr) {
  docPicker = flatpickr(docDateDisplay, {
    locale: 'th',
    disableMobile: true,
    allowInput: false,
    clickOpens: true,
    dateFormat: 'Y-m-d',
    onReady: (selectedDates, dateStr, inst) => {
      const d = parseYMD(docDateHidden?.value);
      if (d) {
        inst.setDate(d, false);
        docDateDisplay.value = toThaiDisplay(d);
        docDateHidden.value = inst.formatDate(d, 'Y-m-d');
      }
    },
    onChange: (selectedDates, dateStr, inst) => {
      const d = selectedDates[0];
      if (!d) return;
      docDateDisplay.value = toThaiDisplay(d);
      docDateHidden.value = inst.formatDate(d, 'Y-m-d');
    }
  });
}

docDateUse?.addEventListener('change', syncDocDateOptionUI);
docDateNone?.addEventListener('change', syncDocDateOptionUI);
docDateDisplay?.addEventListener('click', () => {
  if (!docDateNone?.checked && docPicker) docPicker.open();
});

function syncAddParagraphButton() {
  if (!addParagraphBtn || !paragraph2Box || !paragraph3Box) return;
  const paragraph2Visible = !paragraph2Box.classList.contains('hidden');
  const paragraph3Visible = !paragraph3Box.classList.contains('hidden');

  if (paragraph2Visible && paragraph3Visible) {
    addParagraphBtn.disabled = true;
  } else {
    addParagraphBtn.disabled = false;
  }
}

addParagraphBtn?.addEventListener('click', () => {
  if (!paragraph2Box || !paragraph3Box) return;

  if (paragraph2Box.classList.contains('hidden')) {
    paragraph2Box.classList.remove('hidden');
  } else if (paragraph3Box.classList.contains('hidden')) {
    paragraph3Box.classList.remove('hidden');
  }

  syncAddParagraphButton();
});

removeParagraph2Btn?.addEventListener('click', () => {
  if (!paragraph2Box || !paragraph3Box) return;

  const paragraph2Textarea = paragraph2Box.querySelector('textarea');
  const paragraph3Textarea = paragraph3Box.querySelector('textarea');

  if (!paragraph3Box.classList.contains('hidden')) {
    if (paragraph2Textarea && paragraph3Textarea) {
      paragraph2Textarea.value = paragraph3Textarea.value;
      paragraph3Textarea.value = '';
    }
    paragraph3Box.classList.add('hidden');
  } else {
    if (paragraph2Textarea) paragraph2Textarea.value = '';
    paragraph2Box.classList.add('hidden');
  }

  syncAddParagraphButton();
});

removeParagraph3Btn?.addEventListener('click', () => {
  if (!paragraph3Box) return;

  const paragraph3Textarea = paragraph3Box.querySelector('textarea');
  if (paragraph3Textarea) paragraph3Textarea.value = '';
  paragraph3Box.classList.add('hidden');

  syncAddParagraphButton();
});

function goToSelectedForm() {
  const opt = subCategory.options[subCategory.selectedIndex];
  if (!opt || !opt.dataset.path) return;
  const path = opt.dataset.path;
  if (path === currentQuestionPath) return;
  window.location.href = '/Pro_letter' + path + '?main=' + encodeURIComponent(mainCategory.value) + '&sub=' + encodeURIComponent(opt.textContent.trim());
}

function updateSubCategory(autoRedirect = false) {
  const group = mainCategory.value || 'internal';
  const current = subCategory.dataset.current || '';
  const filtered = templateOptions.filter(item => item.template_group === group);
  subCategory.innerHTML = '';

  if (!filtered.length) {
    const option = document.createElement('option');
    option.value = '';
    option.textContent = '-- เลือกหมวดย่อย --';
    subCategory.appendChild(option);
    return;
  }

  filtered.forEach(item => {
    const option = document.createElement('option');
    option.value = item.template_name;
    option.textContent = item.template_name;
    option.dataset.path = item.question_path;
    option.dataset.documentPath = item.document_path || '';
    if (current && current === item.template_name) option.selected = true;
    subCategory.appendChild(option);
  });

  if (subCategory.selectedIndex < 0) subCategory.selectedIndex = 0;
  if (autoRedirect) goToSelectedForm();
}

function syncDepartmentByFaculty() {
  if (!facultySelect || !deptSelect) return;
  const selectedFacultyId = facultySelect.options[facultySelect.selectedIndex]?.dataset.facultyId || '';
  let firstVisible = null;

  Array.from(deptSelect.options).forEach(opt => {
    const sameFaculty = !selectedFacultyId || opt.dataset.facultyId === selectedFacultyId;
    opt.hidden = !sameFaculty;
    if (sameFaculty && !firstVisible) firstVisible = opt;
  });

  if (deptSelect.selectedOptions[0]?.hidden && firstVisible) firstVisible.selected = true;
  selectedDepartmentId.value = deptSelect.options[deptSelect.selectedIndex]?.dataset.departmentId || '';
}

mainCategory?.addEventListener('change', () => updateSubCategory(true));
subCategory?.addEventListener('change', goToSelectedForm);
facultySelect?.addEventListener('change', syncDepartmentByFaculty);
deptSelect?.addEventListener('change', () => {
  selectedDepartmentId.value = deptSelect.options[deptSelect.selectedIndex]?.dataset.departmentId || '';
});

memoForm?.addEventListener('submit', async (event) => {
  event.preventDefault();

  if (!memoForm.checkValidity()) {
    memoForm.reportValidity();
    return;
  }

  try {
    const spellPassed = await checkFreeDocumentSpell();

    if (!spellPassed) {
      return;
    }

    HTMLFormElement.prototype.submit.call(memoForm);
  } catch (error) {
    console.error('Spell check error:', error);
    alert('ไม่สามารถตรวจคำผิดได้ กรุณาตรวจสอบว่าเปิดระบบตรวจคำผิดแล้ว');
  }
});

document.addEventListener('DOMContentLoaded', () => {
  updateSubCategory(false);
  syncDepartmentByFaculty();
  syncDocDateOptionUI();
  syncAddParagraphButton();
});
</script>
</body>
</html>
