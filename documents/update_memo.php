<?php
// /documents/update_memo.php
session_start();

/** DEV */
$DEV_AUTO_LOGIN = false;
$DEBUG_ERRORS = true;
if ($DEV_AUTO_LOGIN && empty($_SESSION['user_id'])) {
  $_SESSION['user_id'] = 1;
}

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/require_profile_completed.php';

try {
  if (empty($_SESSION['user_id'])) {
    header('Location: /Pro_letter/documents/form_Memo.php?err=unauthorized', true, 302);
    exit;
  }
  $userId = (int) $_SESSION['user_id'];

  $documentId = (int) ($_POST['document_id'] ?? 0);
  $templateId = (int) ($_POST['template_id'] ?? 1);
  $documentTypeName = trim($_POST['document_type_name'] ?? '');

  if ($documentId <= 0) {
    header('Location: /Pro_letter/documents/form_Memo.php?err=nodoc', true, 302);
    exit;
  }

  // ตรวจสิทธิ์แก้ไขเอกสาร: admin/officer / ผู้มีสิทธิ์ document.edit / เจ้าของเอกสารเดิมที่ยังไม่ถูกกำหนดสิทธิ์รายบุคคล
  $pdo = db();
  $chk = $pdo->prepare("SELECT owner_id, status FROM documents WHERE document_id = :id LIMIT 1");
  $chk->execute([':id' => $documentId]);
  $docForPermission = $chk->fetch(PDO::FETCH_ASSOC) ?: [];
  $owner = (int)($docForPermission['owner_id'] ?? 0);
  $currentStatusForPermission = trim((string)($docForPermission['status'] ?? ''));

  $roleId = (int)($_SESSION['role_id'] ?? 0);
  $roleName = strtolower(trim((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? '')));

  // ใช้ role ของคนที่ล็อกอินจริงในการตัดสินสถานะหลังแก้ไข
  // ถ้า session ไม่มี role_id ให้ดึงจากตาราง users สำรอง
  if ($roleId <= 0) {
    try {
      $roleStmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = :uid LIMIT 1");
      $roleStmt->execute([':uid' => $userId]);
      $roleId = (int)($roleStmt->fetchColumn() ?: 0);
    } catch (Throwable $roleError) {
      $roleId = 0;
    }
  }

  $isAdminOrOfficer = in_array($roleId, [1, 2], true)
    || in_array($roleName, ['admin', 'administrator', 'officer', 'เจ้าหน้าที่', 'ผู้ดูแลระบบ'], true);
  $canEditByPermission = false;
  $hasAnyExplicitPermission = false;

  try {
    $permAnyStmt = $pdo->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = :uid");
    $permAnyStmt->execute([':uid' => $userId]);
    $hasAnyExplicitPermission = ((int)$permAnyStmt->fetchColumn() > 0);

    $permStmt = $pdo->prepare("
      SELECT COUNT(*)
      FROM user_permissions up
      JOIN permissions p ON p.perm_id = up.perm_id
      WHERE up.user_id = :uid
        AND p.perm_code = 'document.edit'
    ");
    $permStmt->execute([':uid' => $userId]);
    $canEditByPermission = ((int)$permStmt->fetchColumn() > 0);
  } catch (Throwable $permError) {
    $canEditByPermission = false;
    $hasAnyExplicitPermission = false;
  }

$checkedStatusesForPermission = [
  'ผ่านการตรวจสอบ',
  'ผ่านการตรวจสอบแล้ว',
  'ได้รับการตรวจสอบ',
  'ได้รับการตรวจสอบแล้ว',
  'ตรวจสอบแล้ว',
  'approved',
  'checked',
  'reviewed'
];

$isCheckedStatusForPermission = in_array($currentStatusForPermission, $checkedStatusesForPermission, true);

$ownerCanEdit = ($owner === $userId);

if ($isCheckedStatusForPermission) {
  $canEditThisDocument = false;
} elseif ($isAdminOrOfficer) {
  $canEditThisDocument = true;
} else {
  $canEditThisDocument = (
    $canEditByPermission
    || $ownerCanEdit
  );
}
  if (!$canEditThisDocument) {
    header('Location: /Pro_letter/documents/view_memo.php?id=' . $documentId . '&err=no_permission', true, 302);
    exit;
  }

  // รับค่า
  $docDate = trim($_POST['doc_date'] ?? '');
  $docDateOptionForHeader = trim($_POST['doc_date_option'] ?? 'use_date');
  $hideDocDateOnDocument = ($docDateOptionForHeader === 'no_date');
  $docDateForDocumentTable = $hideDocDateOnDocument ? date('Y-m-d') : $docDate;
  $docDateForDisplay = $hideDocDateOnDocument ? '' : $docDate;

  $purpose = trim($_POST['purpose'] ?? '');
  $redirectTo = trim($_POST['redirect_to'] ?? '');
  $targetForm = trim($_POST['target_form'] ?? '');

  $isSpeakerMemo = (
    $purpose === 'speaker_workshop'
    || $redirectTo === 'form_memo_speaker.php'
    || $targetForm === 'form_memo_speaker.php'
    || ($_POST['form_type'] ?? '') === 'speaker_workshop'
    || ($_POST['document_type'] ?? '') === 'infor_speaker_workshop'
  );
  $isRoomRequest = (
    $purpose === 'room_request'
    || $redirectTo === 'infor_room_request.php'
    || $targetForm === 'infor_room_request.php'
    || isset($_POST['room_request'])
);

  $isInviteMemo = (
    $purpose === 'invite_speaker_student'
    || $purpose === 'invite'
    || $redirectTo === 'infor_invite.php'
    || $targetForm === 'infor_invite.php'
    || ($_POST['form_type'] ?? '') === 'invite'
    || ($_POST['document_type'] ?? '') === 'infor_invite'
  );


  $isProjectActivity = (
    $purpose === 'project_activity'
    || $redirectTo === 'form_memo_project_activity.php'
    || $targetForm === 'form_memo_project_activity.php'
    || ($_POST['form_type'] ?? '') === 'project_activity'
    || ($_POST['document_type'] ?? '') === 'infor_project_activity'
    || isset($_POST['main_project'])
    || isset($_POST['sub_activity'])
  );

  $isResearchData = (
    $purpose === 'research_data'
    || $redirectTo === 'form_memo_request_research_data.php'
    || $targetForm === 'infor_research_data.php'
    || ($_POST['form_type'] ?? '') === 'research_data'
    || ($_POST['document_type'] ?? '') === 'infor_research_data'
    // กันกรณี officer ใช้ฟอร์มเดียวกับ user แต่ hidden field บางตัวไม่ถูกส่งมา
    // ให้ตรวจจาก field เฉพาะของ infor_research_data.php เพื่อไม่ให้ไปชน project_activity
    || isset($_POST['data_detail'])
    || isset($_POST['data_amount'])
    || isset($_POST['support_type'])
    || isset($_POST['curriculum_name'])
    || isset($_POST['student_contact_index'])
  );

  // ถ้าเป็นฟอร์มขอความอนุเคราะห์ข้อมูลวิจัยจริง ห้ามให้เงื่อนไข project_activity แทรก
  // เพราะจะทำให้หลังบันทึก/หลังแก้ไขเปิดไปหน้า form_memo_project_activity.php
  if ($isResearchData) {
    $isProjectActivity = false;
    $purpose = ($purpose !== '') ? $purpose : 'research_data';
  }

  $isStudyVisit = (
    $redirectTo === 'form_memo_sut_wellness.php'
    || $targetForm === 'form_memo_sut_wellness.php'
    || ($_POST['form_type'] ?? '') === 'study_visit'
    || ($_POST['document_type'] ?? '') === 'infor_study_visit'
    || isset($_POST['visit_place'])
    || isset($_POST['visit_period'])
    || isset($_POST['visit_time'])
    || isset($_POST['teacher_count'])
  );

  $isCoopEvaluation = (
    $purpose === 'coop_evaluation'
    || $redirectTo === 'form_memo_coop_evaluation.php'
    || $targetForm === 'infor_coop_evaluation.php'
    || $targetForm === 'form_memo_coop_evaluation.php'
    || ($_POST['form_type'] ?? '') === 'coop_evaluation'
    || ($_POST['document_type'] ?? '') === 'infor_coop_evaluation'
    || isset($_POST['organization_name'])
    || isset($_POST['student_count'])
    || isset($_POST['student_list_json'])
  );

  $isFreeDocument = (
    $purpose === 'free_document'
    || $redirectTo === 'form_memo_free_document.php'
    || $targetForm === 'form_memo_free_document.php'
    || ($_POST['form_type'] ?? '') === 'free_document'
    || ($_POST['document_type'] ?? '') === 'infor_free_document'
  );

  if ($isFreeDocument) {
    $purpose = 'free_document';
  }

  $isAcademicPresentation = (
    $purpose === 'academic'
    || $redirectTo === 'form_memo_academic_1.php'
    || $targetForm === 'form_memo_academic_1.php'
    || $targetForm === 'infor_academic_presentation.php'
    || ($_POST['form_type'] ?? '') === 'academic'
    || ($_POST['form_type'] ?? '') === 'academic_presentation'
    || ($_POST['document_type'] ?? '') === 'infor_academic_presentation'
  );

  if ($isAcademicPresentation && $purpose === '') {
    $purpose = 'academic';
  }

  // รองรับทั้ง field เดิมของ form_Memo.php และ field ของ infor_speaker_workshop.php
  $fullname = trim($_POST['fullname'] ?? $_POST['teacher_name'] ?? '');
  $position = trim($_POST['position'] ?? '');
  $eventTitle = trim($_POST['event_title'] ?? $_POST['project_title'] ?? $_POST['thesis_title'] ?? '');

  $memoSubject = trim($_POST['memo_subject'] ?? $_POST['subject'] ?? '');
  $academicTopic = trim($_POST['academic_topic'] ?? '');
  $academicLevel = trim($_POST['academic_level'] ?? '');
  $eventDate = trim($_POST['event_date'] ?? '');

  $presenterName = trim($_POST['presenter_name_hidden'] ?? '');
  $signatureAffiliation = trim($_POST['signature_affiliation'] ?? '');

  $dateOption = $_POST['date_option'] ?? '';
  $singleDate = trim($_POST['single_date'] ?? '');
  $rangeDate = trim($_POST['range_date'] ?? '');

  $joinDates = trim($_POST['join_date'] ?? $_POST['intern_period'] ?? '');

  // infor_academic_presentation.php ส่งช่วงวันที่ผ่าน join_date ไม่ได้ส่ง range_date
  // ถ้าไม่เติมค่านี้ update จะ validate fail แล้วเด้งกลับหน้า Step 1
  if ($rangeDate === '' && $joinDates !== '') {
    $rangeDate = $joinDates;
  }
  if ($singleDate === '' && $dateOption === 'single' && $joinDates !== '') {
    $singleDate = $joinDates;
  }

  $isOnline = (($_POST['is_online'] ?? '0') === '1') ? 1 : 0;
  $place = trim($_POST['place'] ?? $_POST['location'] ?? $_POST['location_input'] ?? '');

$courseName = trim($_POST['course_name'] ?? '');
$referenceOrg = trim($_POST['reference_org'] ?? '');
$referenceNo = trim($_POST['reference_no'] ?? '');
$travelPeriod = trim($_POST['travel_period'] ?? '');
$referenceDate = trim($_POST['reference_date'] ?? '');
$intentionText = trim($_POST['intention_text'] ?? '');
$toPerson         = trim($_POST['to_person'] ?? '');
$receiverName     = trim($_POST['receiver_name'] ?? '');
$receiverPosition = trim($_POST['receiver_position'] ?? '');
$inviteStatement  = trim($_POST['invite_statement'] ?? '');
$objectiveText    = trim($_POST['objective'] ?? '');
$eventTime        = trim($_POST['event_time'] ?? '');

$projectSubject          = trim($_POST['subject'] ?? '');
$projectToPerson         = trim($_POST['to_person'] ?? '');
$projectActivityPlace    = trim($_POST['school_name'] ?? $_POST['activity_place'] ?? '');
$projectMainProject      = trim($_POST['main_project'] ?? '');
$projectSubActivity      = trim($_POST['sub_activity'] ?? '');
$projectObjectiveDetail  = trim($_POST['objective_detail'] ?? '');
$projectTargetGroup      = trim($_POST['target_group'] ?? '');
$projectParticipantCount = trim($_POST['participant_count'] ?? '');
$projectActivityPeriod   = trim($_POST['activity_period'] ?? '');
$projectLecturerNames    = trim($_POST['lecturer_names'] ?? '');
$projectReceiverName     = trim($_POST['receiver_name'] ?? '');
$projectReceiverPosition = trim($_POST['receiver_position'] ?? '');


  $coopSubject          = trim($_POST['subject'] ?? '');
  $coopToPerson         = trim($_POST['to_person'] ?? '');
  $coopOrganizationName = trim($_POST['organization_name'] ?? '');
  $coopStudentCount     = trim($_POST['student_count'] ?? '');
  $coopPeriod           = trim($_POST['coop_period'] ?? '');
  $coopStartDate        = trim($_POST['coop_start_date'] ?? '');
  $coopEndDate          = trim($_POST['coop_end_date'] ?? '');
  $coopAdvisorName      = trim($_POST['advisor_name'] ?? '');
  $coopEvaluationEmail  = trim($_POST['evaluation_email'] ?? '');
  $coopReceiverName     = trim($_POST['receiver_name'] ?? '');
  $coopReceiverPosition = trim($_POST['receiver_position'] ?? '');
  $coopStudentListText  = trim($_POST['student_list_text'] ?? '');
  $coopStudentsJsonRaw  = trim($_POST['student_list_json'] ?? '');

  $coopStudentNamesRaw = $_POST['student_names'] ?? [];
  $coopStudentIdsRaw   = $_POST['student_ids'] ?? [];

  if (!is_array($coopStudentNamesRaw)) {
    $coopStudentNamesRaw = [$coopStudentNamesRaw];
  }
  if (!is_array($coopStudentIdsRaw)) {
    $coopStudentIdsRaw = [$coopStudentIdsRaw];
  }

  $coopStudents = [];
  $coopStudentMax = max(count($coopStudentNamesRaw), count($coopStudentIdsRaw));
  for ($i = 0; $i < $coopStudentMax; $i++) {
    $studentName = trim((string)($coopStudentNamesRaw[$i] ?? ''));
    $studentId   = trim((string)($coopStudentIdsRaw[$i] ?? ''));

    if ($studentName === '' && $studentId === '') {
      continue;
    }

    $coopStudents[] = [
      'name' => $studentName,
      'student_id' => $studentId,
    ];
  }

  if (!$coopStudents && $coopStudentsJsonRaw !== '') {
    $decodedCoopStudents = json_decode($coopStudentsJsonRaw, true);
    if (is_array($decodedCoopStudents)) {
      foreach ($decodedCoopStudents as $row) {
        $studentName = trim((string)($row['name'] ?? ''));
        $studentId   = trim((string)($row['student_id'] ?? ($row['id'] ?? '')));

        if ($studentName === '' && $studentId === '') {
          continue;
        }

        $coopStudents[] = [
          'name' => $studentName,
          'student_id' => $studentId,
        ];
      }
    }
  }

  if ($coopStudentListText === '') {
    $lines = [];
    foreach ($coopStudents as $row) {
      $lines[] = trim(($row['name'] ?? '') . ' รหัสนักศึกษา ' . ($row['student_id'] ?? ''));
    }
    $coopStudentListText = implode("\n", array_filter($lines));
  }

$roomRequest      = trim($_POST['room_request'] ?? '');
$roomRequestOther = trim($_POST['room_request_other'] ?? '');
$guestFullname    = trim($_POST['guest_fullname'] ?? '');
$personType       = trim($_POST['person_type'] ?? '');
$personTypeOther  = trim($_POST['person_type_other'] ?? '');
$reason           = trim($_POST['reason'] ?? '');
$reasonOther      = trim($_POST['reason_other'] ?? '');
$dateOption       = trim($_POST['date_option'] ?? 'single');
$singleDate       = trim($_POST['single_date'] ?? '');
$rangeDate        = trim($_POST['range_date'] ?? '');
$roomType         = trim($_POST['room_type'] ?? '');
$departmentPhone = trim((string)($_POST['department_phone'] ?? ''));
$departmentPhone = strtr($departmentPhone, [
  '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
  '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
]);
$departmentPhone = preg_replace('/^โทร\.?\s*/u', '', $departmentPhone);
$departmentPhone = preg_replace('/\s+/u', ' ', $departmentPhone);
$studyVisitPlace  = trim($_POST['visit_place'] ?? '');
$studyPlaceDetail = trim($_POST['place_detail'] ?? '');
$studyObjectiveText = trim($_POST['objective'] ?? '');
$studyPurposeText = trim($_POST['study_purpose'] ?? $_POST['purpose'] ?? '');
$studyVisitPeriod = trim($_POST['visit_period'] ?? '');
$studyVisitTime   = trim($_POST['visit_time'] ?? '');
$studyTeacherCount = isset($_POST['teacher_count']) ? (int) $_POST['teacher_count'] : 0;

$studyTeacherNamesRaw = $_POST['teacher_names'] ?? [];
$studyTeacherAffiliationsRaw = $_POST['teacher_affiliations'] ?? [];
if (!is_array($studyTeacherNamesRaw)) {
    $studyTeacherNamesRaw = [$studyTeacherNamesRaw];
}
if (!is_array($studyTeacherAffiliationsRaw)) {
    $studyTeacherAffiliationsRaw = [$studyTeacherAffiliationsRaw];
}

$studyTeacherRows = [];
$studyMaxTeachers = max(count($studyTeacherNamesRaw), count($studyTeacherAffiliationsRaw), $studyTeacherCount);
for ($i = 0; $i < $studyMaxTeachers; $i++) {
    $teacherName = trim((string)($studyTeacherNamesRaw[$i] ?? ''));
    $teacherAffiliation = trim((string)($studyTeacherAffiliationsRaw[$i] ?? ''));

    if ($teacherName === '' && $teacherAffiliation === '') {
        continue;
    }

    $studyTeacherRows[] = [
        'name' => $teacherName,
        'affiliation' => $teacherAffiliation,
    ];
}

$studyTeacherNamesText = trim($_POST['teacher_names_text'] ?? '');
$studyTeacherAffiliationsText = trim($_POST['teacher_affiliations_text'] ?? '');
$studyTeacherListText = trim($_POST['teacher_list_text'] ?? '');

if ($studyTeacherNamesText === '') {
    $studyTeacherNamesText = implode("\n", array_column($studyTeacherRows, 'name'));
}
if ($studyTeacherAffiliationsText === '') {
    $studyTeacherAffiliationsText = implode("\n", array_column($studyTeacherRows, 'affiliation'));
}
if ($studyTeacherListText === '') {
    $pairs = [];
    foreach ($studyTeacherRows as $row) {
        $pairs[] = ($row['name'] ?? '') . '|' . ($row['affiliation'] ?? '');
    }
    $studyTeacherListText = implode("\n", $pairs);
}

$researchSubject        = trim($_POST['subject'] ?? '');
$researchToPerson       = trim($_POST['to_person'] ?? '');
$researchSemester       = trim($_POST['semester'] ?? '');
$researchAcademicYear   = trim($_POST['academic_year'] ?? '');
$researchCourseCode     = trim($_POST['course_code'] ?? '');
$researchCourseName     = trim($_POST['course_name'] ?? '');
$researchCurriculumName = trim($_POST['curriculum_name'] ?? '');
$researchMajorName      = trim($_POST['major_name'] ?? '');
$researchStudentYear    = trim($_POST['student_year'] ?? '');
$researchThesisTitle    = trim($_POST['thesis_title'] ?? '');
$researchAdvisorName    = trim($_POST['advisor_name'] ?? '');
$researchProjectDetail  = trim($_POST['project_detail'] ?? '');
$researchSupportType    = trim($_POST['support_type'] ?? '');
$researchDataDetail     = trim($_POST['data_detail'] ?? '');
$researchDataAmount     = trim($_POST['data_amount'] ?? '');
$researchContactIndex   = isset($_POST['student_contact_index']) ? (int)$_POST['student_contact_index'] : 0;

$freeSubject = trim($_POST['subject'] ?? $_POST['free_subject'] ?? '');
$freeToPerson = trim($_POST['to_person'] ?? $_POST['free_to_person'] ?? '');
$freeDepartmentPhone = trim($_POST['department_phone'] ?? $_POST['free_department_phone'] ?? '');
$freeDepartmentPhone = strtr($freeDepartmentPhone, [
  '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
  '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
]);
$freeDepartmentPhone = preg_replace('/^โทร\.?\s*/u', '', $freeDepartmentPhone);
$freeDepartmentPhone = preg_replace('/\s+/u', ' ', $freeDepartmentPhone);
$freeParagraph1 = trim($_POST['free_paragraph_1'] ?? $_POST['paragraph_1'] ?? '');
$freeParagraph2 = trim($_POST['free_paragraph_2'] ?? $_POST['paragraph_2'] ?? '');
$freeParagraph3 = trim($_POST['free_paragraph_3'] ?? $_POST['paragraph_3'] ?? '');
$freeSignerName = trim($_POST['free_signer_name'] ?? $_POST['signer_name'] ?? '');
$freeSignerPosition = trim($_POST['free_signer_position'] ?? $_POST['signer_position'] ?? '');

$studentNamesRaw  = $_POST['student_name'] ?? [];
$studentIdsRaw    = $_POST['student_id'] ?? [];
$studentPhonesRaw = $_POST['student_phone'] ?? [];

if (!is_array($studentNamesRaw)) {
    $studentNamesRaw = [$studentNamesRaw];
}
if (!is_array($studentIdsRaw)) {
    $studentIdsRaw = [$studentIdsRaw];
}
if (!is_array($studentPhonesRaw)) {
    $studentPhonesRaw = [$studentPhonesRaw];
}

$researchStudents = [];
$researchContactStudent = null;
$studentCount = max(count($studentNamesRaw), count($studentIdsRaw));

for ($i = 0; $i < $studentCount; $i++) {
    $name  = trim((string)($studentNamesRaw[$i] ?? ''));
    $sid   = preg_replace('/\D+/', '', (string)($studentIdsRaw[$i] ?? ''));
    $phone = preg_replace('/\D+/', '', (string)($studentPhonesRaw[$i] ?? ''));

    if ($name === '' && $sid === '' && $phone === '') {
        continue;
    }

    $student = [
        'name' => $name,
        'student_id' => $sid,
        'phone' => $phone,
        'is_contact' => ($i === $researchContactIndex),
    ];

    if ($student['is_contact']) {
        $researchContactStudent = $student;
    }

    $researchStudents[] = $student;
}

if (!$researchContactStudent && count($researchStudents) > 0) {
    $researchStudents[0]['is_contact'] = true;
    $researchContactStudent = $researchStudents[0];
}

  $noCost = (($_POST['no_cost'] ?? '0') === '1') ? 1 : 0;

  $amountRaw = str_replace(',', '', trim($_POST['amount'] ?? '0'));
  $amount = $noCost ? 0.00 : (is_numeric($amountRaw) ? (float) $amountRaw : 0.00);

  $carUsed = isset($_POST['car_used']) ? 1 : 0;

  $expenseJson = trim((string)($_POST['expense_json'] ?? ''));
  if ($noCost) {
    $expenseJson = '';
  }
  if ($expenseJson !== '') {
    json_decode($expenseJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $expenseJson = '';
    }
  }
  $carPlate = trim($_POST['car_plate'] ?? '');

  $departmentId = (int)($_POST['department_id'] ?? 0);
  $faculty = trim($_POST['faculty'] ?? '');
  $department = trim($_POST['department'] ?? '');

  /*
    คณะ/ภาควิชา:
    - ใช้ department_id ที่ผู้ใช้เลือกจาก dropdown เป็นหลัก
    - ถ้า department_id ไม่มา ให้ลองหา id จากชื่อภาควิชา/คณะ
    - ถ้ายังไม่เจอ ให้ใช้ department_id เดิมของเอกสาร
    - จากนั้นดึงชื่อคณะ/ภาควิชาจาก DB กลับมาใช้กับ document_values field_id 10/11
  */
  try {
    if ($departmentId <= 0 && $department !== '') {
      $findDeptSql = "
        SELECT d.department_id, d.department_name, f.faculty_name
        FROM departments d
        JOIN faculties f ON f.faculty_id = d.faculty_id
        WHERE d.department_name = :department_name
      ";
      $findDeptParams = [':department_name' => $department];

      if ($faculty !== '') {
        $findDeptSql .= " AND f.faculty_name = :faculty_name";
        $findDeptParams[':faculty_name'] = $faculty;
      }

      $findDeptSql .= " LIMIT 1";
      $findDeptStmt = $pdo->prepare($findDeptSql);
      $findDeptStmt->execute($findDeptParams);
      $foundDept = $findDeptStmt->fetch(PDO::FETCH_ASSOC);

      if ($foundDept) {
        $departmentId = (int)$foundDept['department_id'];
      }
    }

    if ($departmentId <= 0) {
      $oldDeptStmt = $pdo->prepare("
        SELECT department_id
        FROM documents
        WHERE document_id = :document_id
        LIMIT 1
      ");
      $oldDeptStmt->execute([':document_id' => $documentId]);
      $departmentId = (int)$oldDeptStmt->fetchColumn();
    }

    if ($departmentId > 0) {
      $orgStmt = $pdo->prepare("
        SELECT
          d.department_id,
          d.department_name,
          f.faculty_name
        FROM departments d
        JOIN faculties f ON f.faculty_id = d.faculty_id
        WHERE d.department_id = :department_id
        LIMIT 1
      ");
      $orgStmt->execute([':department_id' => $departmentId]);
      $orgRow = $orgStmt->fetch(PDO::FETCH_ASSOC);

      if ($orgRow) {
        $departmentId = (int)$orgRow['department_id'];
        $faculty = trim((string)$orgRow['faculty_name']);
        $department = trim((string)$orgRow['department_name']);
      }
    }

    if ($departmentId <= 0) {
      throw new Exception('Invalid department_id');
    }
  } catch (Throwable $orgError) {
    $departmentId = $departmentId > 0 ? $departmentId : 1;
    $faculty = $faculty !== '' ? $faculty : 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
    $department = $department !== '' ? $department : 'เทคโนโลยีสารสนเทศ';
  }

  $headerText = trim($_POST['header_text'] ?? '');

  // ถ้าหน้าแบบฟอร์มไม่ได้ส่ง header_text มา อย่าล้างค่าเดิมใน documents.header_text
  // ให้ประกอบส่วนราชการจากตาราง departments/faculties เหมือนตอนบันทึกใหม่
  if ($headerText === '') {
    $qHdr = $pdo->prepare("
      SELECT d.department_name, d.phone, f.faculty_name
      FROM departments d
      JOIN faculties f ON d.faculty_id = f.faculty_id
      WHERE d.department_id = :id
      LIMIT 1
    ");
    $qHdr->execute([':id' => $departmentId]);
    $hdrRow = $qHdr->fetch(PDO::FETCH_ASSOC);

    if ($hdrRow) {
      $phoneForHeader = $departmentPhone !== '' ? $departmentPhone : trim((string)($hdrRow['phone'] ?? ''));
      $headerText = trim($hdrRow['faculty_name'] . ' ภาควิชา' . $hdrRow['department_name'] . ($phoneForHeader !== '' ? ' โทร. ' . $phoneForHeader : ''));
    } else {
      $headerText = trim(($faculty !== '' ? $faculty : '') . ' ' . ($department !== '' ? 'ภาควิชา' . $department : '') . ($departmentPhone !== '' ? ' โทร. ' . $departmentPhone : ''));
    }
  }

  if ($departmentPhone !== '' && !preg_match('/โทร\.?/u', $headerText)) {
    $headerText = trim($headerText . ' โทร. ' . $departmentPhone);
  }

  $headerText = strtr($headerText, [
    '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
    '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
  ]);

  $docNo = trim($_POST['doc_no'] ?? '');
  $docDateDisp = trim($_POST['doc_date_display'] ?? '');

  // ตรวจขั้นต่ำ
  $errors = [];

  if ($isFreeDocument) {
    // ตรวจเฉพาะช่องที่มีจริงในฟอร์ม infor_free_document.php เท่านั้น
    if (!$hideDocDateOnDocument && $docDate === '') {
      $errors['doc_date'] = 'required';
    }
    if ($freeSubject === '') {
      $errors['subject'] = 'required';
    }
    if ($freeToPerson === '') {
      $errors['to_person'] = 'required';
    }
    if ($freeDepartmentPhone === '') {
      $errors['department_phone'] = 'required';
    }
    if ($freeParagraph1 === '') {
      $errors['free_paragraph_1'] = 'required';
    }

} elseif ($isCoopEvaluation) {
    if (!$hideDocDateOnDocument && $docDate === '') {
        $errors['doc_date'] = 'required';
    }
    if ($coopSubject === '') {
      $errors['subject'] = 'required';
    }
    if ($coopToPerson === '') {
      $errors['to_person'] = 'required';
    }
    if ($coopOrganizationName === '') {
      $errors['organization_name'] = 'required';
    }
    if ($coopStudentCount === '' || (int)$coopStudentCount < 1) {
      $errors['student_count'] = 'required';
    }
    if (count($coopStudents) < 1) {
      $errors['student_list'] = 'required';
    }
    foreach ($coopStudents as $idx => $student) {
      if (($student['name'] ?? '') === '') {
        $errors['student_name_' . $idx] = 'required';
      }
      if (($student['student_id'] ?? '') === '') {
        $errors['student_id_' . $idx] = 'required';
      }
    }
    if ($coopPeriod === '') {
      $errors['coop_period'] = 'required';
    }
    if ($coopAdvisorName === '') {
      $errors['advisor_name'] = 'required';
    }
    // ฟอร์ม infor_coop_evaluation.php ปัจจุบันไม่มีช่อง evaluation_email
    // จึงตรวจรูปแบบเฉพาะกรณีที่มีการส่งค่ามาเท่านั้น
    if ($coopEvaluationEmail !== '' && !filter_var($coopEvaluationEmail, FILTER_VALIDATE_EMAIL)) {
      $errors['evaluation_email'] = 'invalid';
    }
 
  } elseif ($isProjectActivity) {
    if ($docDate === '') {
      $errors['doc_date'] = 'required';
    }
    if ($projectSubject === '') {
      $errors['subject'] = 'required';
    }
    if ($projectToPerson === '') {
      $errors['to_person'] = 'required';
    }
    if ($projectActivityPlace === '') {
      $errors['activity_place'] = 'required';
    }
    if ($projectMainProject === '') {
      $errors['main_project'] = 'required';
    }
    if ($projectSubActivity === '') {
      $errors['sub_activity'] = 'required';
    }
    if ($projectObjectiveDetail === '') {
      $errors['objective_detail'] = 'required';
    }
    if ($projectTargetGroup === '') {
      $errors['target_group'] = 'required';
    }
    if ($projectParticipantCount === '') {
      $errors['participant_count'] = 'required';
    }
    if ($projectActivityPeriod === '') {
      $errors['activity_period'] = 'required';
    }
    if ($projectLecturerNames === '') {
      $errors['lecturer_names'] = 'required';
    }
    if (isset($_POST['receiver_name']) && $projectReceiverName === '') {
  $errors['receiver_name'] = 'required';
    }
    if (isset($_POST['receiver_position']) && $projectReceiverPosition === '') {
      $errors['receiver_position'] = 'required';
    }
  } elseif ($isResearchData) {

    if ($docDate === '') $errors['doc_date'] = 'required';
    if ($researchSubject === '') $errors['subject'] = 'required';
    if ($researchToPerson === '') $errors['to_person'] = 'required';
    if ($researchSemester === '') $errors['semester'] = 'required';
    if ($researchAcademicYear === '' || !preg_match('/^\d{4}$/', $researchAcademicYear)) {
        $errors['academic_year'] = 'required';
    }
    if ($researchCourseCode === '') $errors['course_code'] = 'required';
    if ($researchCourseName === '') $errors['course_name'] = 'required';
    if ($researchCurriculumName === '') $errors['curriculum_name'] = 'required';
    if ($researchMajorName === '') $errors['major_name'] = 'required';
    if ($researchStudentYear === '' || !preg_match('/^\d+$/', $researchStudentYear)) {
        $errors['student_year'] = 'required';
    }
    if ($researchThesisTitle === '') $errors['thesis_title'] = 'required';
    if ($researchAdvisorName === '') $errors['advisor_name'] = 'required';
    if ($researchProjectDetail === '') $errors['project_detail'] = 'required';
    if ($researchSupportType === '') $errors['support_type'] = 'required';
    if ($researchDataDetail === '') $errors['data_detail'] = 'required';
    if ($researchDataAmount === '') $errors['data_amount'] = 'required';

    if (count($researchStudents) < 1) {
        $errors['student_name'] = 'required';
    }

    foreach ($researchStudents as $idx => $student) {
        if ($student['name'] === '') {
            $errors['student_name_' . $idx] = 'required';
        }
        if (!preg_match('/^\d{13}$/', $student['student_id'])) {
            $errors['student_id_' . $idx] = 'required';
        }
    }

    if (!$researchContactStudent) {
        $errors['student_contact_index'] = 'required';
    } elseif (!preg_match('/^\d{10}$/', $researchContactStudent['phone'] ?? '')) {
        $errors['student_phone'] = 'required';
    }

  } elseif ($isInviteMemo) {
    if (!$hideDocDateOnDocument && $docDate === '') $errors['doc_date'] = 'required';
    if ($memoSubject === '') $errors['subject'] = 'required';
    if ($toPerson === '') $errors['to_person'] = 'required';
    if ($eventTitle === '') $errors['thesis_title'] = 'required';
    if ($inviteStatement === '') $errors['invite_statement'] = 'required';
    if ($objectiveText === '') $errors['objective'] = 'required';
    if ($joinDates === '') $errors['intern_period'] = 'required';
    if ($place === '') $errors['location_input'] = 'required';

  } elseif ($isRoomRequest) {
    if (!$hideDocDateOnDocument && $docDate === '') $errors['doc_date'] = 'required';
    if ($toPerson === '') $errors['to_person'] = 'required';
    if ($fullname === '') $errors['fullname'] = 'required';
    if ($position === '') $errors['position'] = 'required';

    if ($roomRequest === '') $errors['room_request'] = 'required';
    if ($roomRequest === 'อื่น ๆ' && $roomRequestOther === '') {
        $errors['room_request_other'] = 'required';
    }

    if ($guestFullname === '') $errors['guest_fullname'] = 'required';

    if ($personType === '') $errors['person_type'] = 'required';
    if ($personType === 'อื่น ๆ' && $personTypeOther === '') {
        $errors['person_type_other'] = 'required';
    }

    if ($reason === '') $errors['reason'] = 'required';
    if ($reason === 'อื่น ๆ' && $reasonOther === '') {
        $errors['reason_other'] = 'required';
    }

    if ($dateOption === 'single' && $singleDate === '') {
        $errors['single_date'] = 'required';
    }

    if ($dateOption === 'range' && $rangeDate === '') {
        $errors['range_date'] = 'required';
    }

    if ($roomType === '') $errors['room_type'] = 'required';

} elseif ($isSpeakerMemo) {
    // ถ้าเลือก “ไม่ประสงค์ใส่วันที่” ไม่ต้องบังคับ doc_date ตอนแก้ไข
    if (!$hideDocDateOnDocument && $docDate === '') $errors['doc_date'] = 'required';
    if ($memoSubject === '') $errors['memo_subject'] = 'required';
    if ($fullname === '') $errors['fullname'] = 'required';
    if ($position === '') $errors['position'] = 'required';
    if ($referenceOrg === '') $errors['reference_org'] = 'required';
    if ($referenceNo === '') $errors['reference_no'] = 'required';
    if ($eventTitle === '') $errors['project_title'] = 'required';
    if ($referenceDate === '') $errors['reference_date'] = 'required';
    if ($courseName === '') $errors['course_name'] = 'required';
    if ($place === '') $errors['location'] = 'required';
    if ($joinDates === '') $errors['intern_period'] = 'required';
    if ($travelPeriod === '') $errors['travel_period'] = 'required';
    if ($intentionText === '') $errors['intention_text'] = 'required';
  } elseif ($isStudyVisit) {
    if ($docDate === '') $errors['doc_date'] = 'required';
    if ($memoSubject === '') $errors['subject'] = 'required';
    if ($toPerson === '') $errors['to_person'] = 'required';
    if ($receiverName === '') $errors['receiver_name'] = 'required';
    if ($receiverPosition === '') $errors['receiver_position'] = 'required';
    if ($fullname === '') $errors['fullname'] = 'required';
    if ($position === '') $errors['position'] = 'required';
    if ($studyVisitPlace === '') $errors['visit_place'] = 'required';
    if ($studyPlaceDetail === '') $errors['place_detail'] = 'required';
    if ($studyObjectiveText === '') $errors['objective'] = 'required';
    if ($studyPurposeText === '') $errors['purpose'] = 'required';
    if ($studyVisitPeriod === '') $errors['visit_period'] = 'required';
    if ($studyVisitTime === '') $errors['visit_time'] = 'required';
    if ($studyTeacherCount < 1 || count($studyTeacherRows) < 1) $errors['teacher_count'] = 'required';

    foreach ($studyTeacherRows as $idx => $teacher) {
      if (trim($teacher['name'] ?? '') === '') $errors['teacher_name_' . $idx] = 'required';
      if (trim($teacher['affiliation'] ?? '') === '') $errors['teacher_affiliation_' . $idx] = 'required';
    }
    } else {
    // ถ้าเลือก “ไม่ประสงค์ใส่วันที่” ไม่ต้องบังคับ doc_date
    if (!$hideDocDateOnDocument && $docDate === '') {
      $errors['doc_date'] = 'required';
    }

        if ($purpose === '') {
      $purpose = 'training';
    }

    if ($memoSubject === '') $errors['memo_subject'] = 'required';
    if ($eventTitle === '') $errors['event_title'] = 'required';

    if ($purpose === 'academic') {
      if ($academicTopic === '') $errors['academic_topic'] = 'required';
      if ($academicLevel === '') $errors['academic_level'] = 'required';
      if ($eventDate === '') $errors['event_date'] = 'required';
    }

    if ($dateOption === 'single' && $singleDate === '') $errors['single_date'] = 'required';
    if ($dateOption === 'range' && $rangeDate === '') $errors['range_date'] = 'required';
    if (!$isOnline && $place === '') $errors['place'] = 'required';
    if (!$noCost && !is_numeric($amountRaw)) $errors['amount'] = 'number';
    if ($carUsed && $carPlate === '') $errors['car_plate'] = 'required';
  }

 if (!empty($errors)) {
    if ($isFreeDocument) {
        header('Location: /Pro_letter/documents/infor_free_document.php?id=' . $documentId . '&edit=1&err=validate');
    } elseif ($isCoopEvaluation) {
        header('Location: /Pro_letter/documents/infor_coop_evaluation.php?id=' . $documentId . '&edit=1&err=validate');
    } elseif ($isProjectActivity) {
        header('Location: /Pro_letter/documents/infor_project_activity.php?id=' . $documentId . '&edit=1&err=validate');
    } elseif ($isResearchData) {
        header('Location: /Pro_letter/documents/infor_research_data.php?id=' . $documentId . '&err=validate');
    } elseif ($isInviteMemo) {
        header('Location: /Pro_letter/documents/infor_invite.php?id=' . $documentId . '&edit=1&err=validate');
    } elseif ($isRoomRequest) {
        header('Location: /Pro_letter/documents/infor_room_request.php?id=' . $documentId . '&err=validate');
    } elseif ($isSpeakerMemo) {
        header('Location: /Pro_letter/documents/infor_speaker_workshop.php?id=' . $documentId . '&edit=1&err=validate');
    } elseif ($isStudyVisit) {
        header('Location: /Pro_letter/documents/infor_study_visit.php?id=' . $documentId . '&edit=1&err=validate');
    } elseif (!empty($isAcademicPresentation) || $purpose === 'academic') {
        header('Location: /Pro_letter/documents/infor_academic_presentation.php?id=' . $documentId . '&edit=1&err=validate');
    } else {
        header('Location: /Pro_letter/documents/view_memo.php?id=' . $documentId . '&err=validate');
    }
    exit;
}

  // 🟢 ตรวจสอบสถานะเอกสารก่อนเริ่ม transaction
  $stmtStatus = $pdo->prepare("SELECT status FROM documents WHERE document_id = :id");
  $stmtStatus->execute([':id' => $documentId]);
  $currentStatus = (string) $stmtStatus->fetchColumn();

  // กำหนดสถานะหลังบันทึกตามผู้แก้ไข
  // admin/officer แก้แล้วให้กลับไปรอตรวจสอบทันที (submitted)
  // user แก้แล้วให้กลับไปเป็นเค้าโครง/รอยืนยันการส่ง (draft)
  $newStatusAfterEdit = $isAdminOrOfficer ? 'submitted' : 'draft';
  $pdo->beginTransaction();


  // บังคับให้เอกสารขอความอนุเคราะห์ข้อมูลวิจัยใช้ template_id ที่ถูกต้อง
  // แก้เฉพาะฟอร์ม infor_research_data.php เพื่อให้หน้ารายการรอตรวจสอบเปิดกลับไป
  // form_memo_request_research_data.php ไม่ใช่หน้าเอกสารประเภทอื่น
  if ($isResearchData) {
    $findResearchTemplate = $pdo->prepare("
      SELECT template_id
      FROM templates
      WHERE template_code = 'RESEARCH_DATA'
         OR question_path LIKE '%infor_research_data.php%'
         OR document_path LIKE '%form_memo_request_research_data.php%'
      ORDER BY
        CASE WHEN template_code = 'RESEARCH_DATA' THEN 0 ELSE 1 END,
        template_id ASC
      LIMIT 1
    " );
    $findResearchTemplate->execute();
    $researchTemplateId = (int) ($findResearchTemplate->fetchColumn() ?: 0);

    if ($researchTemplateId > 0) {
      $templateId = $researchTemplateId;
    }

    $documentTypeName = 'หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์';
  }

  // บังคับให้เอกสารประเมินสหกิจใช้ template_id ที่ถูกต้อง
  // แก้เฉพาะฟอร์ม infor_coop_evaluation.php เพื่อให้ตอนแก้ไขบันทึกค่ากลับเข้าฟิลด์ของ template สหกิจจริง
if ($isCoopEvaluation) {
  $findCoopTemplate = $pdo->prepare("
    SELECT template_id
    FROM templates
    WHERE template_code = 'COOP_EVALUATION'
       OR question_path LIKE '%infor_coop_evaluation.php%'
       OR document_path LIKE '%form_memo_coop_evaluation.php%'
    ORDER BY
      CASE WHEN template_code = 'COOP_EVALUATION' THEN 0 ELSE 1 END,
      template_id ASC
    LIMIT 1
  ");
  $findCoopTemplate->execute();
  $coopTemplateId = (int) ($findCoopTemplate->fetchColumn() ?: 0);

  if ($coopTemplateId > 0) {
    $templateId = $coopTemplateId;
  }

  $purpose = 'coop_evaluation';
  $redirectTo = 'form_memo_coop_evaluation.php';
  $targetForm = 'infor_coop_evaluation.php';
  $documentTypeName = 'ขอประเมินสถานประกอบการสหกิจ(ประเมินเด็กสหกิจ)';
}

  if ($isFreeDocument) {
    $joinType = 'บันทึกข้อความทั่วไป';
    $subject = $freeSubject !== '' ? $freeSubject : 'บันทึกข้อความทั่วไป';
  } elseif ($isCoopEvaluation) {
    $joinType = 'ขอประเมินสถานประกอบการสหกิจศึกษา';
    $subject = $coopSubject !== '' ? $coopSubject : 'ขอความอนุเคราะห์ประเมินผลนักศึกษาสหกิจศึกษา';
  } elseif ($isProjectActivity) {
    $joinType = 'ขอเข้าไปจัดกิจกรรมโครงการ';
    $subject = $projectSubject !== '' ? $projectSubject : 'ขออนุญาตดำเนินการจัดกิจกรรมโครงการ';
  } elseif ($isResearchData) {
    $joinType = 'หนังสือขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์';
    $subject = $researchSubject !== '' ? $researchSubject : 'ขอความอนุเคราะห์ข้อมูลเพื่อจัดทำปริญญานิพนธ์';
  } elseif ($isInviteMemo) {
    $joinType = 'หนังสือเรียนเชิญวิทยากร';
    $subject = $memoSubject !== '' ? $memoSubject : 'ขอเรียนเชิญเป็นวิทยากรบรรยาย';
  } elseif ($isRoomRequest) {
    $joinType = 'ขออนุมัติใช้ห้องพักรับรอง';
    $subject = 'ขออนุมัติใช้ห้องพักรับรอง';
} elseif ($isSpeakerMemo) {
    $joinType = 'ขออนุมัติตัวบุคคลเป็นวิทยากร';
    $subject = $memoSubject !== '' ? $memoSubject : 'ขออนุมัติตัวบุคคลเป็นวิทยากร';
} elseif ($isStudyVisit) {
    $joinType = 'ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน';
    $subject = $memoSubject !== ''
      ? $memoSubject
      : trim('ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน ' . $studyVisitPlace);
} else {
    $joinType = match ($purpose) {
      'consent_research_presentation' => 'หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ',
      'academic' => 'นำเสนอผลงานวิจัย',
      'meeting' => 'เข้าร่วมประชุมวิชาการในงาน',
      'training' => 'เข้ารับการฝึกอบรมหลักสูตร',
      default => 'เข้ารับการฝึกอบรมหลักสูตร',
    };

    $subjectDetail = $memoSubject !== '' ? $memoSubject : $eventTitle;
    $subject = 'ขออนุมัติตัวบุคคลเข้าร่วม' . $subjectDetail;
  }

 $up = $pdo->prepare("
    UPDATE documents 
    SET doc_no = :doc_no,
        template_id = :template_id,
        department_id = :department_id,
        doc_date = :doc_date,
        subject = :subject,
        header_text = :header_text,
        document_type_name = COALESCE(NULLIF(:document_type_name, ''), document_type_name),
        status = :new_status,
        updated_at = NOW() 
    WHERE document_id = :id
");
$up->execute([
    ':doc_no' => $docNo,
    ':template_id' => $templateId,
    ':department_id' => $departmentId,
    ':doc_date' => $docDateForDocumentTable,
    ':subject' => $subject,
    ':header_text' => $headerText,
    ':document_type_name' => $documentTypeName,
    ':new_status' => $newStatusAfterEdit,
    ':id' => $documentId
]);

  // บันทึกประวัติการแก้ไขเอกสาร โดยใช้ user_id ของคนที่ล็อกอินอยู่จริง
  // เพื่อให้หน้า History แสดงผู้ดำเนินการเป็น admin/officer/user ที่แก้จริง ไม่ใช่เจ้าของเอกสาร
  $log = $pdo->prepare("
      INSERT INTO audit_logs (user_id, document_id, action, detail)
      VALUES (:user_id, :document_id, 'UPDATED', :detail)
  ");
  $log->execute([
      ':user_id' => $userId,
      ':document_id' => $documentId,
      ':detail' => 'มีการแก้ไขข้อมูลเอกสาร'
  ]);



  // ค่า field อื่น ๆ
$valuesByKey = [];

if ($isFreeDocument) {
    $values = [
        1  => $docDateForDisplay,
        4  => $joinType,
        10 => $faculty,
        11 => $department,
        14 => $freeSubject,
        26 => $freeToPerson,
    ];

    $valuesByKey = [
        'free_doc_date'          => $docDateForDisplay,
        'free_subject'           => $freeSubject,
        'free_to_person'         => $freeToPerson,
        'free_faculty'           => $faculty,
        'free_department'        => $department,
        'free_department_phone'  => $freeDepartmentPhone,
        'free_paragraph_1'       => $freeParagraph1,
        'free_paragraph_2'       => $freeParagraph2,
        'free_paragraph_3'       => $freeParagraph3,
        'free_signer_name'       => $freeSignerName,
        'free_signer_position'   => $freeSignerPosition,

        // รองรับกรณี template_fields ใช้ key แบบไม่มี prefix
        'doc_date'               => $docDateForDisplay,
        'subject'                => $freeSubject,
        'to_person'              => $freeToPerson,
        'faculty'                => $faculty,
        'department'             => $department,
        'department_phone'       => $freeDepartmentPhone,
        'paragraph_1'            => $freeParagraph1,
        'paragraph_2'            => $freeParagraph2,
        'paragraph_3'            => $freeParagraph3,
        'signer_name'            => $freeSignerName,
        'signer_position'        => $freeSignerPosition,
    ];

} elseif ($isCoopEvaluation) {
    $coopStudentsJson = json_encode($coopStudents, JSON_UNESCAPED_UNICODE);

    $values = [
        1  => $docDate,
        4  => $joinType,
        10 => $faculty,
        11 => $department,
        14 => $coopSubject,
        26 => $coopToPerson,

        // legacy field_id ของฟอร์มประเมินสหกิจ เผื่อ template_fields ไม่มี field_key
        70 => $coopSubject,
        71 => $coopToPerson,
        72 => $coopOrganizationName,
        73 => (string)$coopStudentCount,
        74 => $coopStudentsJson,
        75 => $coopStudentListText,
        76 => $coopPeriod,
        77 => $coopStartDate,
        78 => $coopEndDate,
        79 => $coopAdvisorName,
        80 => $coopEvaluationEmail,
    ];

    $valuesByKey = [
        'coop_subject'            => $coopSubject,
        'coop_to_person'          => $coopToPerson,
        'coop_organization_name'  => $coopOrganizationName,
        'coop_student_count'      => (string)$coopStudentCount,
        'coop_students_json'      => $coopStudentsJson,
        'coop_student_list_text'  => $coopStudentListText,
        'coop_period'             => $coopPeriod,
        'coop_start_date'         => $coopStartDate,
        'coop_end_date'           => $coopEndDate,
        'coop_advisor_name'       => $coopAdvisorName,
        'coop_evaluation_email'   => $coopEvaluationEmail,
    ];

} elseif ($isProjectActivity) {
    $values = [
        1  => $docDate,
        4  => $joinType,
        10 => $faculty,
        11 => $department,
        14 => $projectSubject,
        26 => $projectToPerson,
    ];

    $valuesByKey = [
        'project_subject'           => $projectSubject,
        'project_to_person'         => $projectToPerson,
        'project_activity_place'    => $projectActivityPlace,
        'project_main_project'      => $projectMainProject,
        'project_sub_activity'      => $projectSubActivity,
        'project_objective_detail'  => $projectObjectiveDetail,
        'project_target_group'      => $projectTargetGroup,
        'project_participant_count' => $projectParticipantCount,
        'project_activity_period'   => $projectActivityPeriod,
        'project_lecturer_names'    => $projectLecturerNames,
    ];

} elseif ($isResearchData) {
    $researchStudentsJson = json_encode($researchStudents, JSON_UNESCAPED_UNICODE);

    $values = [
        1  => $docDate,
        4  => $joinType,
        10 => $faculty,
        11 => $department,
        14 => $researchSubject,
        26 => $researchToPerson,
    ];

    $valuesByKey = [
        'research_subject'         => $researchSubject,
        'research_to_person'       => $researchToPerson,
        'research_semester'        => $researchSemester,
        'research_academic_year'   => $researchAcademicYear,
        'research_course_code'     => $researchCourseCode,
        'research_course_name'     => $researchCourseName,
        'research_curriculum_name' => $researchCurriculumName,
        'research_major_name'      => $researchMajorName,
        'research_student_year'    => $researchStudentYear,
        'research_thesis_title'    => $researchThesisTitle,
        'research_advisor_name'    => $researchAdvisorName,
        'research_project_detail'  => $researchProjectDetail,
        'research_support_type'    => $researchSupportType,
        'research_data_detail'     => $researchDataDetail,
        'research_data_amount'     => $researchDataAmount,
        'research_students_json'   => $researchStudentsJson,
    ];

} elseif ($isInviteMemo) {
    $values = [
        1  => $docDate,
        4  => $joinType,
        5  => $eventTitle,
        6  => $joinDates,
        7  => $place,
        10 => $faculty,
        11 => $department,
        14 => $memoSubject,
        16 => $joinDates,
        25 => $objectiveText,
        26 => $toPerson,
    ];

    $valuesByKey = [
        // เก็บซ้ำแบบ field_key เฉพาะฟอร์มเชิญวิทยากร เพื่อให้หน้าเจนเอกสารดึงข้อมูลได้แน่นอน
        'doc_date' => $docDateForDisplay,
        'subject' => $memoSubject,
        'to_person' => $toPerson,
        'faculty' => $faculty,
        'department' => $department,
        'project_title' => $eventTitle,
        'thesis_title' => $eventTitle,
        'event_date' => $joinDates,
        'intern_period' => $joinDates,
        'event_time' => $eventTime,
        'location_input' => $place,
        'objective' => $objectiveText,
        'invite_statement' => $inviteStatement,
    ];

} elseif ($isRoomRequest) {
    $values = [
        1  => $docDateForDisplay,
        2  => $fullname,
        3  => $position,
        4  => $joinType,
        10 => $faculty,
        11 => $department,

        26 => $toPerson,
        27 => $roomRequest,
        28 => $roomRequestOther,
        29 => $guestFullname,
        30 => $personType,
        31 => $personTypeOther,
        32 => $reason,
        33 => $reasonOther,
        34 => $dateOption,
        35 => $singleDate,
        36 => $rangeDate,
        37 => $roomType,
    ];
} elseif ($isStudyVisit) {
    $values = [
      1  => $docDate,
      2  => $fullname,
      3  => $position,
      4  => $joinType,
      5  => $studyVisitPlace,
      6  => $studyVisitPeriod,
      7  => $studyPlaceDetail,
      8  => (string)$studyTeacherCount,
      9  => $studyVisitTime,
      10 => $faculty,
      11 => $department,
      14 => $subject,
      25 => $studyObjectiveText,
      26 => $toPerson,
      27 => $studyPurposeText,
      28 => $studyTeacherNamesText,
      29 => $studyTeacherAffiliationsText,
      30 => $studyTeacherListText,
      31 => $receiverName,
      32 => $receiverPosition,
    ];

    $valuesByKey = [
      'study_subject'                   => $subject,
      'study_to_person'                 => $toPerson,
      'study_receiver_name'             => $receiverName,
      'study_receiver_position'         => $receiverPosition,
      'study_fullname'                  => $fullname,
      'study_position'                  => $position,
      'study_visit_place'               => $studyVisitPlace,
      'study_place_detail'              => $studyPlaceDetail,
      'study_objective'                 => $studyObjectiveText,
      'study_purpose_text'              => $studyPurposeText,
      'study_visit_period'              => $studyVisitPeriod,
      'study_visit_time'                => $studyVisitTime,
      'study_teacher_count'             => (string)$studyTeacherCount,
      'study_teacher_names_text'        => $studyTeacherNamesText,
      'study_teacher_affiliations_text' => $studyTeacherAffiliationsText,
      'study_teacher_list_text'         => $studyTeacherListText,
    ];
} elseif ($isSpeakerMemo) {
    $values = [
      1  => $docDateForDisplay,
      2  => $fullname,
      3  => $position,
      4  => $joinType,

      // ชื่อโครงการอบรม
      5  => $eventTitle,

      // วันที่จัดโครงการ / สถานที่ / วันที่เดินทาง
      6  => $joinDates,
      7  => $place,
      8  => number_format($amount, 2, '.', ''),
      9  => $travelPeriod,
      10 => $faculty,
      11 => $department,
      12 => (string)$noCost,

      // field เฉพาะฟอร์มวิทยากร
      18 => $referenceOrg,
      19 => $referenceNo,

      // ห้ามใช้ 20 เพราะ 20 คือ expense_json
     21 => $referenceDate,

      // ชื่อหลักสูตร
      23 => $courseName,

      // วันที่เดินทาง เผื่อ form_memo_speaker.php อ่านจาก 24
      24 => $travelPeriod,

      // ความประสงค์
      25 => $intentionText,
    ];
  } else {
    $values = [
      1 => $docDate,
      2 => $fullname,
      3 => $position,
      4 => $joinType,
      5 => $eventTitle,
      6 => ($dateOption === 'single') ? $singleDate : ($rangeDate !== '' ? $rangeDate : $joinDates),
      7 => $isOnline ? 'เข้าร่วมรูปแบบออนไลน์' : $place,
      8 => number_format($amount, 2, '.', ''),
      9 => $carUsed ? $carPlate : '',
      10 => $faculty,
      11 => $department,
      12 => (string)$noCost,

            // เฉพาะกรณีเลือก "นำเสนอผลงานวิจัย"
      13 => in_array($purpose, ['academic', 'consent_research_presentation'], true) ? $academicTopic : '',
      14 => $memoSubject,
      15 => in_array($purpose, ['academic', 'consent_research_presentation'], true) ? $academicLevel : '',
      16 => in_array($purpose, ['academic', 'consent_research_presentation'], true) ? $eventDate : '',
      17 => ($purpose === 'consent_research_presentation') ? $signatureAffiliation : '',
    ];
  }


  // เก็บรายละเอียดค่าใช้จ่ายแบบ JSON สำหรับโหลดกลับตอนแก้ไข
  // โดยเฉพาะข้อ 1 ค่าตอบแทน และข้อ 2.4 ค่าพาหนะ/เครื่องบิน
  $values[20] = $expenseJson;
  $valuesByKey['expense_json'] = $expenseJson;

  $q = $pdo->prepare("SELECT field_id FROM template_fields WHERE template_id = :tid");
  $q->execute([':tid' => $templateId]);
  $allowIds = array_flip($q->fetchAll(PDO::FETCH_COLUMN));

  $qKey = $pdo->prepare("SELECT field_id, field_key FROM template_fields WHERE template_id = :tid AND field_key IS NOT NULL AND field_key <> ''");
  $qKey->execute([':tid' => $templateId]);
  $fieldIdByKey = [];
  foreach ($qKey->fetchAll(PDO::FETCH_ASSOC) as $fieldRow) {
    $fieldIdByKey[$fieldRow['field_key']] = (int)$fieldRow['field_id'];
  }

  // เพิ่มเฉพาะ field ของผู้ลงนามสำหรับ FREE_DOCUMENT กรณีใน template_fields ยังไม่มี
  if ($isFreeDocument) {
    $ensureFreeSignerFields = [
      'free_signer_name' => ['ชื่อผู้ลงนาม', 100],
      'free_signer_position' => ['ตำแหน่งผู้ลงนาม', 110],
      'signer_name' => ['ชื่อผู้ลงนาม', 101],
      'signer_position' => ['ตำแหน่งผู้ลงนาม', 111],
    ];

    $insertFieldStmt = $pdo->prepare("
      INSERT INTO template_fields (template_id, field_key, field_label, field_type, is_required, sort_order)
      VALUES (:template_id, :field_key, :field_label, 'text', 0, :sort_order)
    ");

    foreach ($ensureFreeSignerFields as $fieldKey => $fieldMeta) {
      if (isset($fieldIdByKey[$fieldKey])) {
        continue;
      }

      $insertFieldStmt->execute([
        ':template_id' => $templateId,
        ':field_key' => $fieldKey,
        ':field_label' => $fieldMeta[0],
        ':sort_order' => $fieldMeta[1],
      ]);

      $fieldIdByKey[$fieldKey] = (int)$pdo->lastInsertId();
      $allowIds[$fieldIdByKey[$fieldKey]] = $fieldIdByKey[$fieldKey];
    }
  }

  // เพิ่มเฉพาะ field ของแบบฟอร์มสหกิจ กรณี template_fields ของ template_id สหกิจยังไม่มี
  // ปัญหาเดิมคือ documents.template_id เป็นของ COOP_EVALUATION แล้ว แต่ template_fields ไม่มี field_key ของสหกิจ
  // ทำให้ valuesByKey ไม่ถูกบันทึกตอนแก้ไข ข้อมูลที่หน้าเจนเอกสารจึงไม่อัปเดต
  if ($isCoopEvaluation) {
    $ensureCoopFields = [
      'coop_subject'            => ['เรื่องประเมินสหกิจศึกษา', 170, 'textarea', 1],
      'coop_to_person'          => ['เรียน', 171, 'text', 1],
      'coop_organization_name'  => ['หน่วยงาน / สถานประกอบการ', 172, 'text', 1],
      'coop_student_count'      => ['จำนวนนักศึกษาสหกิจศึกษา', 173, 'text', 1],
      'coop_students_json'      => ['รายชื่อนักศึกษาสหกิจศึกษา (JSON)', 174, 'textarea', 1],
      'coop_student_list_text'  => ['รายชื่อนักศึกษาสหกิจศึกษา', 175, 'textarea', 1],
      'coop_period'             => ['วันที่ปฏิบัติงานสหกิจศึกษา', 176, 'text', 1],
      'coop_start_date'         => ['วันที่เริ่มปฏิบัติงานสหกิจศึกษา', 177, 'text', 0],
      'coop_end_date'           => ['วันที่สิ้นสุดปฏิบัติงานสหกิจศึกษา', 178, 'text', 0],
      'coop_advisor_name'       => ['พนักงานที่ปรึกษา', 179, 'text', 1],
      'coop_evaluation_email'   => ['อีเมลสำหรับส่งแบบประเมิน', 180, 'text', 0],
    ];

    $insertCoopFieldStmt = $pdo->prepare("
      INSERT INTO template_fields (template_id, field_key, field_label, field_type, is_required, sort_order)
      VALUES (:template_id, :field_key, :field_label, :field_type, :is_required, :sort_order)
    ");

    foreach ($ensureCoopFields as $fieldKey => $fieldMeta) {
      if (isset($fieldIdByKey[$fieldKey])) {
        continue;
      }

      $insertCoopFieldStmt->execute([
        ':template_id' => $templateId,
        ':field_key' => $fieldKey,
        ':field_label' => $fieldMeta[0],
        ':field_type' => $fieldMeta[2],
        ':is_required' => $fieldMeta[3],
        ':sort_order' => $fieldMeta[1],
      ]);

      $fieldIdByKey[$fieldKey] = (int)$pdo->lastInsertId();
      $allowIds[$fieldIdByKey[$fieldKey]] = $fieldIdByKey[$fieldKey];
    }
  }


  // เพิ่มเฉพาะ field ของฟอร์มเชิญวิทยากร กรณี template_fields ของ template_id นี้ยังไม่มี
  // เพื่อให้ valuesByKey ถูกบันทึก และหน้า form_memo_invite_speaker.php ดึงข้อมูลกลับมาแสดงได้
  if ($isInviteMemo) {
    $ensureInviteFields = [
      'doc_date' => ['วันที่', 101, 'text', 0],
      'subject' => ['เรื่อง', 102, 'textarea', 1],
      'to_person' => ['เรียน', 103, 'textarea', 1],
      'faculty' => ['คณะ', 104, 'text', 0],
      'department' => ['ภาควิชา', 105, 'text', 0],
      'project_title' => ['ชื่อโครงการ/กิจกรรม', 201, 'textarea', 1],
      'thesis_title' => ['ชื่อโครงการ/กิจกรรม', 202, 'textarea', 1],
      'event_date' => ['วันที่จัดกิจกรรม', 203, 'text', 1],
      'intern_period' => ['วันที่จัดกิจกรรม', 204, 'text', 1],
      'event_time' => ['เวลา', 205, 'text', 1],
      'location_input' => ['สถานที่', 206, 'textarea', 1],
      'objective' => ['วัตถุประสงค์', 207, 'textarea', 1],
      'invite_statement' => ['คำกล่าวเชิญ', 801, 'textarea', 1],
    ];

    $insertInviteFieldStmt = $pdo->prepare("
      INSERT INTO template_fields (template_id, field_key, field_label, field_type, is_required, sort_order)
      VALUES (:template_id, :field_key, :field_label, :field_type, :is_required, :sort_order)
    ");

    foreach ($ensureInviteFields as $fieldKey => $fieldMeta) {
      if (isset($fieldIdByKey[$fieldKey])) {
        continue;
      }

      $insertInviteFieldStmt->execute([
        ':template_id' => $templateId,
        ':field_key' => $fieldKey,
        ':field_label' => $fieldMeta[0],
        ':field_type' => $fieldMeta[2],
        ':is_required' => $fieldMeta[3],
        ':sort_order' => $fieldMeta[1],
      ]);

      $fieldIdByKey[$fieldKey] = (int)$pdo->lastInsertId();
      $allowIds[$fieldIdByKey[$fieldKey]] = $fieldIdByKey[$fieldKey];
    }
  }

  $ins = $pdo->prepare("
        INSERT INTO document_values (document_id, field_id, value_text)
        VALUES (:document_id, :field_id, :value_text)
        ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)
  ");
  foreach ($values as $fieldId => $val) {
    if (!isset($allowIds[$fieldId]))
      continue;
    $ins->execute([
      ':document_id' => $documentId,
      ':field_id' => $fieldId,
      ':value_text' => $val
    ]);
  }

  foreach ($valuesByKey as $fieldKey => $val) {
    if (!isset($fieldIdByKey[$fieldKey]))
      continue;

    $fieldId = $fieldIdByKey[$fieldKey];
    if (!isset($allowIds[$fieldId]))
      continue;

    $ins->execute([
      ':document_id' => $documentId,
      ':field_id' => $fieldId,
      ':value_text' => $val
    ]);
  }

  // บันทึก/อัปเดตรายการค่าใช้จ่ายจากหน้าประมาณการ
  // ถ้าไม่เบิกค่าใช้จ่าย ให้ลบรายการเดิมทั้งหมด
  // ถ้ามีค่าใช้จ่าย ให้ลบรายการเก่าแล้ว insert รายการใหม่จาก budget_type[] / budget_desc[] / budget_amount[]
  $pdo->prepare("DELETE FROM budget_items WHERE document_id = :id")
    ->execute([':id' => $documentId]);

  if (!$noCost) {
    $types   = $_POST['budget_type'] ?? [];
    $descs   = $_POST['budget_desc'] ?? [];
    $amounts = $_POST['budget_amount'] ?? [];

    if (is_array($types) && is_array($descs) && is_array($amounts)) {
      $insB = $pdo->prepare("
        INSERT INTO budget_items (document_id, item_type, description, amount)
        VALUES (:doc, :type, :desc, :amt)
      ");

      $count = min(count($types), count($descs), count($amounts));
      for ($i = 0; $i < $count; $i++) {
        $t = (string)($types[$i] ?? 'other');
        $d = trim((string)($descs[$i] ?? ''));
        $aRaw = str_replace(',', '', (string)($amounts[$i] ?? '0'));
        $a = is_numeric($aRaw) ? (float)$aRaw : 0.0;

                if (!in_array($t, ['compensation', 'registration', 'transport', 'accommodation', 'per_diem', 'other'], true)) {
          $t = 'other';
        }

        if ($d === '' && $a == 0.0) {
          continue;
        }

        $insB->execute([
          ':doc'  => $documentId,
          ':type' => $t,
          ':desc' => $d,
          ':amt'  => number_format($a, 2, '.', '')
        ]);
      }
    }
  }


$pdo->commit();

/* =======================================================
   🔄 ทำให้กลับไปหน้าเดิม (admin/officer/user)
   ======================================================= */
$redirectBack = $_POST['redirect_back'] ?? '';
$redirectBack = trim($redirectBack);

/* ถ้ามี referer ที่ส่งมา → กลับไปหน้าตามประเภทเอกสาร */
if ($redirectBack !== '') {
    if (!$noCost && !$isFreeDocument && !$isCoopEvaluation && !$isProjectActivity && !$isResearchData && !$isInviteMemo && !$isRoomRequest && !$isSpeakerMemo && !$isStudyVisit && !$isAcademicPresentation) {
        header("Location: /Pro_letter/documents/form_Calcu.php?id={$documentId}&from=update");
        exit;
    }

    if ($isFreeDocument) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_free_document.php?id={$documentId}";
} elseif ($isCoopEvaluation) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_coop_evaluation.php?id={$documentId}";
} elseif ($isProjectActivity) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_project_activity.php?id={$documentId}";
} elseif ($isResearchData) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_request_research_data.php?id={$documentId}";
} elseif ($isInviteMemo) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_invite_speaker.php?id={$documentId}";
} elseif ($isRoomRequest) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_room_request_1.php?id={$documentId}";
} elseif ($isSpeakerMemo) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_speaker.php?id={$documentId}";
} elseif ($isStudyVisit) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_sut_wellness.php?id={$documentId}";
    } elseif ($purpose === 'consent_research_presentation') {
    $redirectUrl = "/Pro_letter/form_Memo/form_consent_research_presentation.php?id={$documentId}";
    } elseif (!empty($isAcademicPresentation) || $purpose === 'academic') {
        $redirectUrl = "/Pro_letter/form_Memo/form_memo_academic_1.php?id={$documentId}";
    } else {
        $redirectUrl = "/Pro_letter/documents/view_memo.php?id={$documentId}";
    }

    header("Location: {$redirectUrl}&saved=1&from=update");
    exit;
}


/* ถ้าไม่มี referer แต่เป็นฟอร์มเฉพาะ → กลับไปหน้าเอกสารเฉพาะเลย */
if ($isFreeDocument) {
    header("Location: /Pro_letter/form_Memo/form_memo_free_document.php?id={$documentId}&saved=1&from=update");
    exit;
}

if ($isCoopEvaluation) {
    header("Location: /Pro_letter/form_Memo/form_memo_coop_evaluation.php?id={$documentId}&saved=1&from=update");
    exit;
}

if ($isProjectActivity) {
    header("Location: /Pro_letter/form_Memo/form_memo_project_activity.php?id={$documentId}&saved=1&from=update");
    exit;
}

if ($isResearchData) {
    header("Location: /Pro_letter/form_Memo/form_memo_request_research_data.php?id={$documentId}&saved=1&from=update");
    exit;
}

if ($isInviteMemo) {
    header("Location: /Pro_letter/form_Memo/form_memo_invite_speaker.php?id={$documentId}&saved=1&from=update");
    exit;
}

if ($isRoomRequest) {
    header("Location: /Pro_letter/form_Memo/form_memo_room_request_1.php?id={$documentId}&saved=1&from=update");
    exit;
}

if ($isSpeakerMemo) {
    header("Location: /Pro_letter/form_Memo/form_memo_speaker.php?id={$documentId}&saved=1&from=update");
    exit;
}

if ($isStudyVisit) {
    header("Location: /Pro_letter/form_Memo/form_memo_sut_wellness.php?id={$documentId}&saved=1&from=update");
    exit;
}

if (!empty($isAcademicPresentation) || $purpose === 'academic') {
    header("Location: /Pro_letter/form_Memo/form_memo_academic_1.php?id={$documentId}&saved=1&from=update");
    exit;
}

/* ถ้าไม่มี referer และเป็นเอกสาร form_Memo ปกติ → กลับไปหน้า view_memo */
header("Location: /Pro_letter/documents/view_memo.php?id={$documentId}&saved=1&from=update");
exit;



} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction())
    $pdo->rollBack();
  if ($DEBUG_ERRORS) {
    echo 'server error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  } else {
    if (!empty($isFreeDocument)) {
      header('Location: /Pro_letter/documents/infor_free_document.php?id=' . $documentId . '&edit=1&err=server', true, 302);
    } elseif (!empty($isCoopEvaluation)) {
      header('Location: /Pro_letter/documents/infor_coop_evaluation.php?id=' . $documentId . '&edit=1&err=server', true, 302);
    } elseif (!empty($isProjectActivity)) {
      header('Location: /Pro_letter/documents/infor_project_activity.php?id=' . $documentId . '&edit=1&err=server', true, 302);
    } elseif (!empty($isResearchData)) {
      header('Location: /Pro_letter/documents/infor_research_data.php?id=' . $documentId . '&err=server', true, 302);
    } elseif (!empty($isInviteMemo)) {
      header('Location: /Pro_letter/documents/infor_invite.php?id=' . $documentId . '&edit=1&err=server', true, 302);
    } elseif (!empty($isStudyVisit)) {
      header('Location: /Pro_letter/documents/infor_study_visit.php?id=' . $documentId . '&edit=1&err=server', true, 302);
    } elseif (!empty($isAcademicPresentation) || $purpose === 'academic') {
      header('Location: /Pro_letter/documents/infor_academic_presentation.php?id=' . $documentId . '&edit=1&err=server', true, 302);
    } else {
      header('Location: /Pro_letter/documents/view_memo.php?id=' . $documentId . '&err=server', true, 302);
    }
  }
}