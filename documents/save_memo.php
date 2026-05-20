<?php
// documents/save_memo.php
session_start();

/** ==== DEV FLAGS ==== */
$DEV_AUTO_LOGIN = true;   // เปิดทดสอบ: ผ่านแม้ยังไม่ล็อกอิน (ตั้ง user_id=1)
$DEBUG_ERRORS = true;   // ส่งรายละเอียด error (อย่าเปิดในโปรดักชัน)

if ($DEV_AUTO_LOGIN && empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // ผู้ใช้ทดสอบ ที่มีอยู่ในตาราง users
}

require_once __DIR__ . '/../functions.php';

try {
    // ต้องมี user_id ใน session (ถ้า DEV_AUTO_LOGIN=false ต้องล็อกอินจริง)
  if (empty($_SESSION['user_id'])) {
    header('Location: /Pro_letter/documents/form_Memo.php?err=unauthorized');
    exit;
}

    $userId = (int) $_SESSION['user_id'];

    /** ===== รับค่า POST ===== */
    $templateId = (int) ($_POST['template_id'] ?? 1);
    $departmentId = (int) ($_POST['department_id'] ?? 1);
    
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
    || $redirectTo === 'Request_3.php'
    || $targetForm === 'Request_3.php'
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

$isResearchData = (
    $purpose === 'research_data'
    || $redirectTo === 'form_memo_request_research_data.php'
    || $targetForm === 'infor_research_data.php'
    || ($_POST['form_type'] ?? '') === 'research_data'
    || ($_POST['document_type'] ?? '') === 'infor_research_data'
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

// รองรับทั้งชื่อ field ของ form_Memo.php เดิม และ infor_speaker_workshop.php


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
$fullname = trim($_POST['fullname'] ?? $_POST['teacher_name'] ?? '');
$position = trim($_POST['position'] ?? '');

$memoSubject = trim($_POST['memo_subject'] ?? $_POST['subject'] ?? '');
$eventTitle = trim($_POST['event_title'] ?? $_POST['project_title'] ?? $_POST['thesis_title'] ?? '');

$academicTopic = trim($_POST['academic_topic'] ?? '');
$academicLevel = trim($_POST['academic_level'] ?? '');
$eventDate = trim($_POST['event_date'] ?? '');
$presenterName = trim($_POST['presenter_name_hidden'] ?? '');
$signatureAffiliation = trim($_POST['signature_affiliation'] ?? '');

$joinDates = trim($_POST['join_date'] ?? $_POST['intern_period'] ?? '');
$place = trim($_POST['place'] ?? $_POST['location'] ?? $_POST['location_input'] ?? '');

$courseName = trim($_POST['course_name'] ?? '');
$referenceOrg = trim($_POST['reference_org'] ?? '');
$referenceNo = trim($_POST['reference_no'] ?? '');
$travelPeriod = trim($_POST['travel_period'] ?? '');
$intentionText = trim($_POST['intention_text'] ?? '');
$referenceDate = trim($_POST['reference_date'] ?? '');
$toPerson         = trim($_POST['to_person'] ?? '');
$receiverName     = trim($_POST['receiver_name'] ?? '');
$receiverPosition = trim($_POST['receiver_position'] ?? '');
$inviteStatement  = trim($_POST['invite_statement'] ?? '');
$objectiveText     = trim($_POST['objective'] ?? '');
$eventTime         = trim($_POST['event_time'] ?? '');

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
$studentCount = max(count($studentNamesRaw), count($studentIdsRaw));

for ($i = 0; $i < $studentCount; $i++) {
    $name  = trim((string)($studentNamesRaw[$i] ?? ''));
    $sid   = preg_replace('/\D+/', '', (string)($studentIdsRaw[$i] ?? ''));
    $phone = preg_replace('/\D+/', '', (string)($studentPhonesRaw[$i] ?? ''));

    if ($name === '' && $sid === '' && $phone === '') {
        continue;
    }

    $researchStudents[] = [
        'name' => $name,
        'student_id' => $sid,
        'phone' => $phone,
        'is_contact' => ($i === $researchContactIndex),
    ];
}

$researchContactStudent = $researchStudents[$researchContactIndex] ?? ($researchStudents[0] ?? null);

$isOnline = ($_POST['is_online'] ?? '1') === '1' ? 1 : 0;

   $noCost = (($_POST['no_cost'] ?? '0') === '1') ? 1 : 0;

$amountRaw = str_replace(',', '', trim($_POST['amount'] ?? '0'));
$amount = $noCost ? 0.00 : (is_numeric($amountRaw) ? (float) $amountRaw : 0.00);

    $carUsed = isset($_POST['car_used']) ? 1 : 0;
    $carPlate = trim($_POST['car_plate'] ?? '');

    // เก็บข้อความคณะ/ภาควิชา ใน document_values (field_id 10,11)
    $faculty = trim($_POST['faculty'] ?? '');
    $department = trim($_POST['department'] ?? '');

    $mode = $_POST['mode'] ?? 'create';   // create | update
$documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;

   /** ===== ตรวจฝั่งเซิร์ฟเวอร์ ===== */
$errors = [];

if ($isProjectActivity) {
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

    if ($docDate === '') {
        $errors['doc_date'] = 'required';
    }
    if ($researchSubject === '') {
        $errors['subject'] = 'required';
    }
    if ($researchToPerson === '') {
        $errors['to_person'] = 'required';
    }
    if ($researchSemester === '') {
        $errors['semester'] = 'required';
    }
    if ($researchAcademicYear === '' || !preg_match('/^\d{4}$/', $researchAcademicYear)) {
        $errors['academic_year'] = 'required';
    }
    if ($researchCourseCode === '') {
        $errors['course_code'] = 'required';
    }
    if ($researchCourseName === '') {
        $errors['course_name'] = 'required';
    }
    if ($researchCurriculumName === '') {
        $errors['curriculum_name'] = 'required';
    }
    if ($researchMajorName === '') {
        $errors['major_name'] = 'required';
    }
    if ($researchStudentYear === '' || !preg_match('/^\d+$/', $researchStudentYear)) {
        $errors['student_year'] = 'required';
    }
    if ($researchThesisTitle === '') {
        $errors['thesis_title'] = 'required';
    }
    if ($researchAdvisorName === '') {
        $errors['advisor_name'] = 'required';
    }
    if ($researchProjectDetail === '') {
        $errors['project_detail'] = 'required';
    }
    if ($researchSupportType === '') {
        $errors['support_type'] = 'required';
    }
    if ($researchDataDetail === '') {
        $errors['data_detail'] = 'required';
    }
    if ($researchDataAmount === '') {
        $errors['data_amount'] = 'required';
    }
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
    if ($docDate === '') {
        $errors['doc_date'] = 'required';
    }
    if ($memoSubject === '') {
        $errors['subject'] = 'required';
    }
    if ($toPerson === '') {
        $errors['to_person'] = 'required';
    }
    if ($eventTitle === '') {
        $errors['thesis_title'] = 'required';
    }
    if ($inviteStatement === '') {
        $errors['invite_statement'] = 'required';
    }
    if ($objectiveText === '') {
        $errors['objective'] = 'required';
    }
    if ($joinDates === '') {
        $errors['intern_period'] = 'required';
    }
    if ($place === '') {
        $errors['location_input'] = 'required';
    }

} elseif ($isRoomRequest) {
    if ($docDate === '') {
        $errors['doc_date'] = 'required';
    }

    if ($toPerson === '') {
        $errors['to_person'] = 'required';
    }

    if ($fullname === '') {
        $errors['fullname'] = 'required';
    }

    if ($position === '') {
        $errors['position'] = 'required';
    }

    if ($roomRequest === '') {
        $errors['room_request'] = 'required';
    }

    if ($roomRequest === 'อื่น ๆ' && $roomRequestOther === '') {
        $errors['room_request_other'] = 'required';
    }

    if ($guestFullname === '') {
        $errors['guest_fullname'] = 'required';
    }

    if ($personType === '') {
        $errors['person_type'] = 'required';
    }

    if ($personType === 'อื่น ๆ' && $personTypeOther === '') {
        $errors['person_type_other'] = 'required';
    }

    if ($reason === '') {
        $errors['reason'] = 'required';
    }

    if ($reason === 'อื่น ๆ' && $reasonOther === '') {
        $errors['reason_other'] = 'required';
    }

    if ($dateOption === 'single' && $singleDate === '') {
        $errors['single_date'] = 'required';
    }

    if ($dateOption === 'range' && $rangeDate === '') {
        $errors['range_date'] = 'required';
    }

    if ($roomType === '') {
        $errors['room_type'] = 'required';
    }

} elseif ($isSpeakerMemo) {
    if ($memoSubject === '') {
        $errors['memo_subject'] = 'required';
    }

    if ($fullname === '') {
        $errors['fullname'] = 'required';
    }

    if ($position === '') {
        $errors['position'] = 'required';
    }

    if ($referenceOrg === '') {
        $errors['reference_org'] = 'required';
    }

    if ($referenceNo === '') {
        $errors['reference_no'] = 'required';
    }

    if ($docDate === '') {
        $errors['doc_date'] = 'required';
    }
    if ($referenceDate === '') {
        $errors['reference_date'] = 'required';
    }
    if ($eventTitle === '') {
        $errors['project_title'] = 'required';
    }

    if ($courseName === '') {
        $errors['course_name'] = 'required';
    }

    if ($place === '') {
        $errors['location'] = 'required';
    }

    if ($joinDates === '') {
        $errors['intern_period'] = 'required';
    }

    if ($travelPeriod === '') {
        $errors['travel_period'] = 'required';
    }
    if ($intentionText === '') {
    $errors['intention_text'] = 'required';
    }
} elseif ($isStudyVisit) {
    if ($docDate === '') {
        $errors['doc_date'] = 'required';
    }
    if ($memoSubject === '') {
        $errors['subject'] = 'required';
    }
    if ($toPerson === '') {
        $errors['to_person'] = 'required';
    }
    if ($receiverName === '') {
        $errors['receiver_name'] = 'required';
    }
    if ($receiverPosition === '') {
        $errors['receiver_position'] = 'required';
    }
    if ($fullname === '') {
        $errors['fullname'] = 'required';
    }
    if ($position === '') {
        $errors['position'] = 'required';
    }
    if ($studyVisitPlace === '') {
        $errors['visit_place'] = 'required';
    }
    if ($studyPlaceDetail === '') {
        $errors['place_detail'] = 'required';
    }
    if ($studyObjectiveText === '') {
        $errors['objective'] = 'required';
    }
    if ($studyPurposeText === '') {
        $errors['purpose'] = 'required';
    }
    if ($studyVisitPeriod === '') {
        $errors['visit_period'] = 'required';
    }
    if ($studyVisitTime === '') {
        $errors['visit_time'] = 'required';
    }
    if ($studyTeacherCount < 1 || count($studyTeacherRows) < 1) {
        $errors['teacher_count'] = 'required';
    }

    foreach ($studyTeacherRows as $idx => $teacher) {
        if (trim($teacher['name'] ?? '') === '') {
            $errors['teacher_name_' . $idx] = 'required';
        }
        if (trim($teacher['affiliation'] ?? '') === '') {
            $errors['teacher_affiliation_' . $idx] = 'required';
        }
    }
} else {
    if ($docDate === '') {
        $errors['doc_date'] = 'required';
    }

    if ($purpose === 'other') {
        $other = trim($_POST['purpose_other_detail'] ?? '');
        if ($other === '') {
            $errors['purpose_other_detail'] = 'required';
        }
    }

    if ($purpose === 'academic') {
        if ($memoSubject === '') {
            $errors['memo_subject'] = 'required';
        }

        if ($academicTopic === '') {
            $errors['academic_topic'] = 'required';
        }

        if ($academicLevel === '') {
            $errors['academic_level'] = 'required';
        }

        if ($eventDate === '') {
            $errors['event_date'] = 'required';
        }
    }

    if ($eventTitle === '') {
        $errors['event_title'] = 'required';
    }

    if ($joinDates === '') {
        $errors['join_date'] = 'required';
    }

    if (!$isOnline && $place === '') {
        $errors['place'] = 'required';
    }

    if (!$noCost && !is_numeric($amountRaw)) {
        $errors['amount'] = 'number';
    }

    if ($carUsed && $carPlate === '') {
        $errors['car_plate'] = 'required';
    }
}
    

if (!empty($errors)) {
    if ($isProjectActivity) {
        header('Location: /Pro_letter/documents/infor_project_activity.php?err=validate');
    } elseif ($isResearchData) {
        header('Location: /Pro_letter/documents/infor_research_data.php?err=validate');
    } elseif ($isInviteMemo) {
        header('Location: /Pro_letter/form_Memo/Request/infor_invite.php?err=validate');
    } elseif ($isRoomRequest) {
        header('Location: /Pro_letter/user/Request_3.php?err=validate');
    } elseif ($isSpeakerMemo) {
        header('Location: /Pro_letter/documents/infor_speaker_workshop.php?err=validate');
    } elseif ($isStudyVisit) {
        header('Location: /Pro_letter/documents/infor_study_visit.php?err=validate');
    } else {
        header('Location: /Pro_letter/documents/form_Memo.php?err=validate');
    }
    exit;
}


    /** ===== เขียนฐานข้อมูล ===== */
    $pdo = db();
    $pdo->beginTransaction();

    
    // 1) map ฟิลด์
if ($isProjectActivity) {
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
} elseif ($purpose === 'other') {
    $joinType = trim($_POST['purpose_other_detail'] ?? '');
    if ($joinType === '') {
        $joinType = 'อื่นๆ';
    }

    $subject = trim($joinType . $eventTitle);
} else {
    $joinType = match ($purpose) {
        'academic' => 'นำเสนอผลงานวิจัย',
        'training' => 'เข้ารับการฝึกอบรมหลักสูตร',
        'meeting'  => 'เข้าร่วมประชุมวิชาการในงาน',
        default    => 'อื่นๆ',
    };

    $subject = ($purpose === 'academic' && $memoSubject !== '')
        ? $memoSubject
        : trim($joinType . $eventTitle);
}
    $q = $pdo->prepare("SELECT d.department_name, d.phone, f.faculty_name
                    FROM departments d
                    JOIN faculties f ON d.faculty_id = f.faculty_id
                    WHERE d.department_id = :id LIMIT 1");
    $q->execute([':id' => $departmentId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);

    $hdrAgency = '';
    if ($row) {
        $hdrAgency = $row['faculty_name'] . ' ภาค' . $row['department_name'] . ' โทร. ' . $row['phone'];
    }


    if ($mode === 'update' && $documentId <= 0) {
    throw new Exception("Invalid document id for update");
}

   if ($mode === 'update') {

    // ตรวจว่าเป็นเจ้าของ + สถานะแก้ได้
    $chk = $pdo->prepare("
        SELECT owner_id, status
        FROM documents
        WHERE document_id = :id
        LIMIT 1
    ");
    $chk->execute([':id' => $documentId]);
    $doc = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        throw new Exception("Document not found");
    }
    $roleId = (int)($_SESSION['role_id'] ?? 0);
    $isAdmin   = ($roleId === 1);
    $isOfficer = ($roleId === 2);

    // 🔒 ถ้าเอกสารถูกตรวจแล้วหรืออนุมัติแล้ว → ใครก็แก้ไม่ได้
    if (in_array($doc['status'], ['checked', 'approved'])) {
        throw new Exception("Document locked");
    }

        // 👤 User ธรรมดา (ไม่ใช่ Admin / Officer)
    if (!$isAdmin && !$isOfficer) {

        // ต้องเป็นเจ้าของเอกสาร
        if ($doc['owner_id'] != $userId) {
            throw new Exception("No permission");
        }

        // User แก้ได้เฉพาะ draft / rejected
        if (!in_array($doc['status'], ['draft', 'rejected'])) {
            throw new Exception("Document locked");
        }
    }


    // 🔄 UPDATE documents
    $stmt = $pdo->prepare("
        UPDATE documents
        SET
            template_id   = :template_id,
            department_id = :department_id,
            doc_date      = :doc_date,
            subject       = :subject,
            header_text   = :header_text,
            updated_at    = NOW()
        WHERE document_id = :id
    ");
    $stmt->execute([
        ':template_id' => $templateId,
        ':department_id' => $departmentId,
        ':doc_date' => $docDate,
        ':subject' => $subject,
        ':header_text' => $hdrAgency,
        ':id' => $documentId
    ]);

} else {
    // 🆕 CREATE
    $stmt = $pdo->prepare("
        INSERT INTO documents
        (template_id, owner_id, department_id, doc_no, doc_date, subject, header_text, status, remark)
        VALUES
        (:template_id, :owner_id, :department_id, NULL, :doc_date, :subject, :header_text, 'draft', NULL)
    ");
    $stmt->execute([
        ':template_id' => $templateId,
        ':owner_id' => $userId,
        ':department_id' => $departmentId,
        ':doc_date' => $docDate,
        ':subject' => $subject,
        ':header_text' => $hdrAgency
    ]);
    $documentId = (int) $pdo->lastInsertId();
}
$totalAmount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : null;

// ถ้ามี step2 และไม่ติ๊ก no_cost -> ให้ใช้ total เป็น amount หลักของเอกสาร
if (!$noCost && $totalAmount !== null && $totalAmount >= 0) {
    $amount = $totalAmount;
}

$valuesByKey = [];

if ($isProjectActivity) {
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
        6 => $joinDates,
        7 => $isOnline ? 'เข้าร่วมรูปแบบออนไลน์' : $place,
        8 => number_format($amount, 2, '.', ''),
        9 => $carUsed ? $carPlate : '',
        10 => $faculty,
        11 => $department,
        12 => (string)$noCost,

        // เฉพาะกรณีเลือก "นำเสนอผลงานวิจัย"
        13 => in_array($purpose, ['academic', 'consent_research_presentation']) ? $academicTopic : '',
        14 => in_array($purpose, ['academic', 'consent_research_presentation']) ? $presenterName : '',
        15 => in_array($purpose, ['academic', 'consent_research_presentation']) ? $academicLevel : '',
        16 => in_array($purpose, ['academic', 'consent_research_presentation']) ? $joinDates : '',
        17 => ($purpose === 'consent_research_presentation') ? $signatureAffiliation : '',
    ];
}

    // อนุญาตเฉพาะ field_id ที่ template นี้มีจริง
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

    // =====================
    // SAVE STEP2 -> budget_items
    // =====================
    $types   = $_POST['budget_type'] ?? [];
    $descs   = $_POST['budget_desc'] ?? [];
    $amounts = $_POST['budget_amount'] ?? [];

    

    // ลบรายการเก่าก่อน (กรณี update)
    $pdo->prepare("DELETE FROM budget_items WHERE document_id = :id")
        ->execute([':id' => $documentId]);

    // insert ใหม่ เฉพาะกรณีมีค่าใช้จ่ายเท่านั้น
if (!$noCost && is_array($types) && is_array($descs) && is_array($amounts)) {
        $insB = $pdo->prepare("
            INSERT INTO budget_items (document_id, item_type, description, amount)
            VALUES (:doc, :type, :desc, :amt)
        ");

        $count = min(count($types), count($descs), count($amounts));
        for ($i = 0; $i < $count; $i++) {
            $t = $types[$i] ?? 'other';
            $d = trim((string)($descs[$i] ?? ''));
            $aRaw = str_replace(',', '', (string)($amounts[$i] ?? '0'));
            $a = is_numeric($aRaw) ? (float)$aRaw : 0.0;

            // กัน type หลุด enum
            if (!in_array($t, ['registration','transport','accommodation','per_diem','other'], true)) {
                $t = 'other';
            }
            // กันแถวว่าง
            if ($d === '' && $a == 0.0) continue;

            $insB->execute([
                ':doc'  => $documentId,
                ':type' => $t,
                ':desc' => $d,
                ':amt'  => number_format($a, 2, '.', '')
            ]);
        }
    }
    

    $pdo->commit();

if ($isProjectActivity) {
    $redirectUrl = '/Pro_letter/form_Memo/form_memo_project_activity.php?id=' . $documentId;
} elseif ($isResearchData) {
    $redirectUrl = '/Pro_letter/form_Memo/form_memo_request_research_data.php?id=' . $documentId;
} elseif ($isInviteMemo) {
    $redirectUrl = '/Pro_letter/form_Memo/form_memo_invite_speaker.php?id=' . $documentId;
} elseif ($isRoomRequest) {
    $redirectUrl = '/Pro_letter/form_Memo/form_memo_room_request_1.php?id=' . $documentId;
} elseif ($isSpeakerMemo) {
    $redirectUrl = '/Pro_letter/form_Memo/form_memo_speaker.php?id=' . $documentId;
} elseif ($isStudyVisit) {
    $redirectUrl = '/Pro_letter/form_Memo/form_memo_sut_wellness.php?id=' . $documentId;
} elseif ($purpose === 'consent_research_presentation') {
    $redirectUrl = '/Pro_letter/form_Memo/form_consent_research_presentation.php?id=' . $documentId;
} elseif ($purpose === 'academic') {
    $redirectUrl = '/Pro_letter/form_Memo/form_memo_academic_1.php?id=' . $documentId;
} else {
    $redirectUrl = '/Pro_letter/documents/view_memo.php?id=' . $documentId;
}

header('Location: ' . $redirectUrl . '&saved=1&from=' . $mode);

exit;




} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($DEBUG_ERRORS) {
        echo "<pre>";
        echo htmlspecialchars($e->getMessage());
        echo "</pre>";
        exit;
    }

  if (!empty($isProjectActivity)) {
    header('Location: /Pro_letter/documents/infor_project_activity.php?err=server');
} elseif (!empty($isResearchData)) {
    header('Location: /Pro_letter/documents/infor_research_data.php?err=server');
} elseif (!empty($isInviteMemo)) {
    header('Location: /Pro_letter/documents/infor_invite.php?err=server');
} elseif (!empty($isSpeakerMemo)) {
    header('Location: /Pro_letter/documents/infor_speaker_workshop.php?err=server');
} elseif (!empty($isStudyVisit)) {
    header('Location: /Pro_letter/documents/infor_study_visit.php?err=server');
} else {
    header('Location: /Pro_letter/documents/form_Memo.php?err=server');
}
exit;
}