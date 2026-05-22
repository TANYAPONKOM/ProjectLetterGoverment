<?php
// /update_memo.php
session_start();

/** DEV */
$DEV_AUTO_LOGIN = false;
$DEBUG_ERRORS = true;
if ($DEV_AUTO_LOGIN && empty($_SESSION['user_id'])) {
  $_SESSION['user_id'] = 1;
}

require_once __DIR__ . '/../functions.php';

try {
  if (empty($_SESSION['user_id'])) {
    header('Location: /documents/form_Memo.html?err=unauthorized', true, 302);
    exit;
  }
  $userId = (int) $_SESSION['user_id'];

  $documentId = (int) ($_POST['document_id'] ?? 0);
  $templateId = (int) ($_POST['template_id'] ?? 1);

  if ($documentId <= 0) {
    header('Location: /documents/form_Memo.html?err=nodoc', true, 302);
    exit;
  }

  // ตรวจสิทธิ์เป็นเจ้าของเอกสาร
  $pdo = db();
  $chk = $pdo->prepare("SELECT owner_id FROM documents WHERE document_id = :id LIMIT 1");
  $chk->execute([':id' => $documentId]);
  $owner = (int) $chk->fetchColumn();
  if ($owner !== $userId) {
    header('Location: /documents/form_Memo.html?err=forbidden', true, 302);
    
    exit;
  }

  // รับค่า
  $docDate = trim($_POST['doc_date'] ?? '');

  $purpose = trim($_POST['purpose'] ?? '');
  $redirectTo = trim($_POST['redirect_to'] ?? '');
  $targetForm = trim($_POST['target_form'] ?? '');

  $isSpeakerMemo = (
    $purpose === 'speaker_workshop'
    || $redirectTo === 'form_memo_speaker.php'
    || $targetForm === 'form_memo_speaker.php'
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
  );

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

  $isOnline = isset($_POST['is_online']) ? 1 : 0;
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
  $coopAdditionalDetail = trim($_POST['additional_detail'] ?? '');
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
  $carPlate = trim($_POST['car_plate'] ?? '');

  $faculty = trim($_POST['faculty'] ?? '');
  $department = trim($_POST['department'] ?? '');
  $headerText = trim($_POST['header_text'] ?? '');
  $docNo = trim($_POST['doc_no'] ?? '');
  $docDateDisp = trim($_POST['doc_date_display'] ?? '');

  // ตรวจขั้นต่ำ
  $errors = [];

  if ($isCoopEvaluation) {
    if ($docDate === '') {
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
    if ($coopEvaluationEmail === '' || !filter_var($coopEvaluationEmail, FILTER_VALIDATE_EMAIL)) {
      $errors['evaluation_email'] = 'required';
    }
    if ($coopReceiverName === '') {
      $errors['receiver_name'] = 'required';
    }
    if ($coopReceiverPosition === '') {
      $errors['receiver_position'] = 'required';
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
    if ($projectReceiverName === '') {
      $errors['receiver_name'] = 'required';
    }
    if ($projectReceiverPosition === '') {
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
    if ($docDate === '') $errors['doc_date'] = 'required';
    if ($memoSubject === '') $errors['subject'] = 'required';
    if ($toPerson === '') $errors['to_person'] = 'required';
    if ($eventTitle === '') $errors['thesis_title'] = 'required';
    if ($inviteStatement === '') $errors['invite_statement'] = 'required';
    if ($objectiveText === '') $errors['objective'] = 'required';
    if ($joinDates === '') $errors['intern_period'] = 'required';
    if ($place === '') $errors['location_input'] = 'required';

  } elseif ($isRoomRequest) {
    if ($docDate === '') $errors['doc_date'] = 'required';
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
    if ($docDate === '') $errors['doc_date'] = 'required';
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
    if ($docDate === '') $errors['doc_date'] = 'required';
    if ($purpose === '') $errors['purpose'] = 'required';
    if ($eventTitle === '') $errors['event_title'] = 'required';

    if ($purpose === 'academic') {
      if ($memoSubject === '') $errors['memo_subject'] = 'required';
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
    if ($isCoopEvaluation) {
        header('Location: /Pro_letter/documents/infor_coop_evaluation.php?id=' . $documentId . '&edit=1&err=validate');
    } elseif ($isProjectActivity) {
        header('Location: /Pro_letter/documents/infor_project_activity.php?id=' . $documentId . '&edit=1&err=validate');
    } elseif ($isResearchData) {
        header('Location: /Pro_letter/documents/infor_research_data.php?id=' . $documentId . '&err=validate');
    } elseif ($isInviteMemo) {
        header('Location: /Pro_letter/form_Memo/Request/infor_invite.php?id=' . $documentId . '&err=validate');
    } elseif ($isRoomRequest) {
        header('Location: /Pro_letter/documents/infor_room_request.php?id=' . $documentId . '&err=validate');
    } elseif ($isSpeakerMemo) {
        header('Location: /Pro_letter/documents/infor_speaker_workshop.php?id=' . $documentId . '&err=validate');
    } elseif ($isStudyVisit) {
        header('Location: /Pro_letter/documents/infor_study_visit.php?id=' . $documentId . '&err=validate');
    } else {
        header('Location: /Pro_letter/documents/view_memo.php?id=' . $documentId . '&err=validate');
    }
    exit;
}

  // 🟢 ตรวจสอบสถานะเอกสารก่อนเริ่ม transaction
  $stmtStatus = $pdo->prepare("SELECT status FROM documents WHERE document_id = :id");
  $stmtStatus->execute([':id' => $documentId]);
  $currentStatus = $stmtStatus->fetchColumn();

  // ถ้าเอกสารเดิมเป็น “รอการแก้ไข” (rejected) → เปลี่ยนกลับเป็น “รอตรวจสอบ” (submitted)
  if ($currentStatus === 'rejected') {
    $pdo->prepare("UPDATE documents SET status = 'submitted', updated_at = NOW() WHERE document_id = :id")
      ->execute([':id' => $documentId]);
  }
error_log("DEBUG SESSION: user_id=" . $_SESSION['user_id'] . " role_id=" . ($_SESSION['role_id'] ?? 'NULL'));
error_log("DEBUG USER LOGIN => SESSION user_id=" . $_SESSION['user_id']);


// ---- DEBUG: ยืนยันว่าใช้ฐานข้อมูลไหนแน่ ----
$whichDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
error_log("DEBUG DB=" . $whichDb . " user_id=" . $userId);

// --- ตรวจสิทธิ์ document.edit ---
// 1) ห้ามแก้ถ้าไม่มีสิทธิ์ document.edit
$sql = "
  SELECT COUNT(*) 
  FROM user_permissions up
  JOIN permissions p ON p.perm_id = up.perm_id
  WHERE up.user_id = :uid AND p.perm_code = 'document.edit'
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid'=>$userId]);
$canEdit = (int)$stmt->fetchColumn() > 0;

if (!$canEdit) {
    header("Location: /Pro_letter/documents/view_memo.php?id={$documentId}&err=no_permission");
    exit;
}







  $pdo->beginTransaction();


  if ($isCoopEvaluation) {
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
      'training' => 'เข้ารับการฝึกอบรมหลักสูตร',
      'meeting' => 'เข้าร่วมประชุมวิชาการในงาน',
      default => 'อื่นๆ',
    };

    $subject = ($purpose === 'academic' && $memoSubject !== '')
      ? $memoSubject
      : trim($joinType . $eventTitle);
  }

  $up = $pdo->prepare("
    UPDATE documents 
    SET doc_no = :doc_no,
        doc_date = :doc_date,
        subject = :subject,
        header_text = :header_text,
        updated_at = NOW() 
    WHERE document_id = :id
");
  $up->execute([
    ':doc_no' => $docNo,
    ':doc_date' => $docDate,
    ':subject' => $subject,
    ':header_text' => $headerText,
    ':id' => $documentId
  ]);

  // ค่า field อื่น ๆ
$valuesByKey = [];

if ($isCoopEvaluation) {
    $coopStudentsJson = json_encode($coopStudents, JSON_UNESCAPED_UNICODE);

    $values = [
        1  => $docDate,
        4  => $joinType,
        10 => $faculty,
        11 => $department,
        14 => $coopSubject,
        26 => $coopToPerson,
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
        'coop_additional_detail'  => $coopAdditionalDetail,
        'coop_receiver_name'      => $coopReceiverName,
        'coop_receiver_position'  => $coopReceiverPosition,
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
        'project_receiver_name'     => $projectReceiverName,
        'project_receiver_position' => $projectReceiverPosition,
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
        'invite_statement' => $inviteStatement,
        'event_time'       => $eventTime,
    ];

} elseif ($isRoomRequest) {
    $values = [
        1  => $docDate,
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
      1  => $docDate,
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
      6 => ($dateOption === 'single') ? $singleDate : $rangeDate,
      7 => $isOnline ? 'เข้าร่วมรูปแบบออนไลน์' : $place,
      8 => number_format($amount, 2, '.', ''),
      9 => $carUsed ? $carPlate : '',
      10 => $faculty,
      11 => $department,
      12 => (string)$noCost,

      // เฉพาะกรณีเลือก "นำเสนอผลงานวิจัย"
      13 => in_array($purpose, ['academic', 'consent_research_presentation'], true) ? $academicTopic : '',
      14 => in_array($purpose, ['academic', 'consent_research_presentation'], true) ? $memoSubject : '',
      15 => in_array($purpose, ['academic', 'consent_research_presentation'], true) ? $academicLevel : '',
      16 => in_array($purpose, ['academic', 'consent_research_presentation'], true) ? $eventDate : '',
      17 => ($purpose === 'consent_research_presentation') ? $signatureAffiliation : '',
    ];
  }

  $q = $pdo->prepare("SELECT field_id FROM template_fields WHERE template_id = :tid");
  $q->execute([':tid' => $templateId]);
  $allowIds = array_flip($q->fetchAll(PDO::FETCH_COLUMN));

  $qKey = $pdo->prepare("SELECT field_id, field_key FROM template_fields WHERE template_id = :tid AND field_key IS NOT NULL AND field_key <> ''");
  $qKey->execute([':tid' => $templateId]);
  $fieldIdByKey = [];
  foreach ($qKey->fetchAll(PDO::FETCH_ASSOC) as $fieldRow) {
    $fieldIdByKey[$fieldRow['field_key']] = (int)$fieldRow['field_id'];
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

        if (!in_array($t, ['registration', 'transport', 'accommodation', 'per_diem', 'other'], true)) {
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
    if (!$noCost && !$isCoopEvaluation && !$isProjectActivity && !$isResearchData && !$isInviteMemo && !$isRoomRequest && !$isSpeakerMemo && !$isStudyVisit) {
        header("Location: /Pro_letter/documents/form_Calcu.php?id={$documentId}&from=update");
        exit;
    }

    if ($isCoopEvaluation) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_coop_evaluation.php?id={$documentId}";
} elseif ($isProjectActivity) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_project_activity.php?id={$documentId}";
} elseif ($isResearchData) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_request_research_data.php?id={$documentId}";
} elseif ($isInviteMemo) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_invite.php?id={$documentId}";
} elseif ($isRoomRequest) {
    $redirectUrl = "/Pro_letter/documents/infor_room_request.php?id={$documentId}";
} elseif ($isSpeakerMemo) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_speaker.php?id={$documentId}";
} elseif ($isStudyVisit) {
    $redirectUrl = "/Pro_letter/form_Memo/form_memo_sut_wellness.php?id={$documentId}";
    } elseif ($purpose === 'consent_research_presentation') {
        $redirectUrl = "/Pro_letter/form_Memo/form_consent_research_presentation.php?id={$documentId}";
    } elseif ($purpose === 'academic') {
        $redirectUrl = "/Pro_letter/form_Memo/form_memo_academic_1.php?id={$documentId}";
    } else {
        $redirectUrl = "/Pro_letter/documents/view_memo.php?id={$documentId}";
    }

    header("Location: {$redirectUrl}&saved=1&from=update");
    exit;
}


/* ถ้าไม่มี referer แต่เป็นฟอร์มเฉพาะ → กลับไปหน้าเอกสารเฉพาะเลย */
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
    header("Location: /Pro_letter/form_Memo/form_memo_invite.php?id={$documentId}&saved=1&from=update");
    exit;
}

if ($isRoomRequest) {
    header("Location: /Pro_letter/documents/infor_room_request.php?id={$documentId}&saved=1&from=update");
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

/* ถ้าไม่มี referer → fallback ไปหน้าตาม role */
$role = strtolower($_SESSION['role_name'] ?? '');

switch ($role) {
    case 'admin':
        $homePath = "/Pro_letter/admin/home.php";
        break;
    case 'officer':
        $homePath = "/Pro_letter/officer/home.php";
        break;
    default:
        $homePath = "/Pro_letter/user/home.php";
}

header("Location: {$homePath}?saved=1&from=update");
exit;



} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction())
    $pdo->rollBack();
  if ($DEBUG_ERRORS) {
    echo 'server error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  } else {
    if (!empty($isProjectActivity)) {
      header('Location: /Pro_letter/documents/infor_project_activity.php?id=' . $documentId . '&edit=1&err=server', true, 302);
    } elseif (!empty($isResearchData)) {
      header('Location: /Pro_letter/documents/infor_research_data.php?id=' . $documentId . '&err=server', true, 302);
    } elseif (!empty($isInviteMemo)) {
      header('Location: /Pro_letter/documents/infor_invite.php?id=' . $documentId . '&err=server', true, 302);
    } elseif (!empty($isStudyVisit)) {
      header('Location: /Pro_letter/documents/infor_study_visit.php?id=' . $documentId . '&err=server', true, 302);
    } else {
      header('Location: /Pro_letter/documents/view_memo.php?id=' . $documentId . '&err=server', true, 302);
    }
  }
}