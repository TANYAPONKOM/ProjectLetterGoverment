<?php
session_start();
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$userId = $_SESSION['user_id'] ?? 0;
$roleId = $_SESSION['role_id'] ?? 0;

/* ---------------------------------------------
   ROLE:
   1 = Admin → เห็นทั้งหมด
   2 = Officer → เห็นทั้งหมด
   3 = User → เห็นเฉพาะของตัวเอง
----------------------------------------------*/

function homeThaiDigitsToArabic($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9'
    ]);
}

function homeCleanValue($value) {
    $value = homeThaiDigitsToArabic($value ?? '');
    $value = trim(preg_replace('/\s+/u', ' ', $value));
    return $value;
}

function homeThaiDate($value) {
    $value = homeCleanValue($value);

    if ($value === '' || $value === '0000-00-00') {
        return '';
    }

    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/u', $value, $m)) {
        $year = (int)$m[1];
        if ($year < 2400) {
            $year += 543;
        }
        return (int)$m[3] . ' ' . ($months[(int)$m[2]] ?? '') . ' ' . $year;
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/u', $value, $m)) {
        $year = (int)$m[3];
        if ($year < 2400) {
            $year += 543;
        }
        return (int)$m[1] . ' ' . ($months[(int)$m[2]] ?? '') . ' ' . $year;
    }

    return $value;
}

function homeFirstValue(...$values) {
    foreach ($values as $value) {
        $value = homeCleanValue($value);
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function homeBuildDetail(array $row) {
    $joinType = homeCleanValue($row['join_type'] ?? '');
    $subject = homeCleanValue($row['subject'] ?? '');
    $templateCode = homeCleanValue($row['template_code'] ?? '');
    $templateName = homeCleanValue($row['template_name'] ?? '');
    $freeSubject = homeFirstValue($row['free_subject'] ?? '', $subject);
    $hint = $joinType . ' ' . $subject . ' ' . $templateCode . ' ' . $templateName . ' ' . homeCleanValue($row['course_name_raw'] ?? '');

    if ($templateCode === 'FREE_DOCUMENT' || mb_strpos($hint, 'form_memo_free_document') !== false) {
        return $freeSubject !== '' ? 'เรื่อง: ' . $freeSubject : '(ไม่มีรายละเอียด)';
    }

    $coopOrg = homeCleanValue($row['coop_organization_name'] ?? '');
    if ($coopOrg !== '') {
        return 'หน่วยงาน: ' . $coopOrg;
    }

    $roomFor = homeFirstValue($row['room_request_other'] ?? '', $row['room_request'] ?? '');
    $roomType = homeCleanValue($row['room_type'] ?? '');
    $stayDate = homeFirstValue($row['single_date'] ?? '', $row['range_date'] ?? '');
    $stayDate = homeThaiDate($stayDate);

    if ($roomFor !== '' || $roomType !== '') {
        if (mb_strpos($hint, 'จัดกิจกรรมโครงการ') !== false && $roomType !== '') {
            return trim(implode(', ', array_filter([
                $roomFor !== '' ? 'ขอใช้สำหรับ: ' . $roomFor : '',
                'ห้องพัก: ' . $roomType
            ])));
        }

        return trim(implode(', ', array_filter([
            $roomFor !== '' ? 'ขอใช้สำหรับ: ' . $roomFor : '',
            $stayDate !== '' ? 'วันที่เข้าพัก: ' . $stayDate : ($roomType !== '' ? 'ห้องพัก: ' . $roomType : '')
        ])));
    }

    $projectName = homeCleanValue($row['project_main_project'] ?? '');
    $projectActivity = homeCleanValue($row['project_sub_activity'] ?? '');
    if ($projectName !== '' || $projectActivity !== '') {
        return trim(implode(', ', array_filter([
            $projectName !== '' ? 'โครงการ: ' . $projectName : '',
            $projectActivity !== '' ? 'กิจกรรม: ' . $projectActivity : ''
        ])));
    }

    $thesisTitle = homeCleanValue($row['research_thesis_title'] ?? '');
    $researchData = homeCleanValue($row['research_data_detail'] ?? '');
    if ($thesisTitle !== '' || $researchData !== '') {
        return trim(implode(', ', array_filter([
            $thesisTitle !== '' ? 'หัวข้อปริญญานิพนธ์: ' . $thesisTitle : '',
            $researchData !== '' ? 'ข้อมูลที่ขอ: ' . $researchData : ''
        ])));
    }

    $courseName = homeCleanValue($row['course_name_raw'] ?? '');
    return $courseName !== '' ? 'ชื่อหลักสูตร: ' . $courseName : '(ไม่มีรายละเอียด)';
}

$selectSql = "
    SELECT 
      d.document_id,
      d.owner_id,
      u.role_id AS owner_role_id,
      u.role_id AS created_by_role_id,
      CASE WHEN u.role_id = 2 THEN 1 ELSE 0 END AS is_officer_created_document,
      d.doc_date,
      d.status,
      d.subject,
      t.template_code,
      t.template_name,
      t.question_path,
      t.document_path,
      MAX(CASE WHEN f.field_key = 'join_type' THEN v.value_text END) AS join_type,
      MAX(CASE WHEN f.field_key = 'course_name' THEN v.value_text END) AS course_name_raw,
      MAX(CASE WHEN f.field_key IN ('free_subject', 'subject') THEN v.value_text END) AS free_subject,
      MAX(CASE WHEN f.field_key = 'coop_organization_name' THEN v.value_text END) AS coop_organization_name,
      MAX(CASE WHEN f.field_key = 'room_request' THEN v.value_text END) AS room_request,
      MAX(CASE WHEN f.field_key = 'room_request_other' THEN v.value_text END) AS room_request_other,
      MAX(CASE WHEN f.field_key = 'single_date' THEN v.value_text END) AS single_date,
      MAX(CASE WHEN f.field_key = 'range_date' THEN v.value_text END) AS range_date,
      MAX(CASE WHEN f.field_key = 'room_type' THEN v.value_text END) AS room_type,
      MAX(CASE WHEN f.field_key = 'project_main_project' THEN v.value_text END) AS project_main_project,
      MAX(CASE WHEN f.field_key = 'project_sub_activity' THEN v.value_text END) AS project_sub_activity,
      MAX(CASE WHEN f.field_key = 'research_thesis_title' THEN v.value_text END) AS research_thesis_title,
      MAX(CASE WHEN f.field_key = 'research_data_detail' THEN v.value_text END) AS research_data_detail
    FROM documents d
    LEFT JOIN templates t ON d.template_id = t.template_id
    LEFT JOIN users u ON d.owner_id = u.user_id
    LEFT JOIN document_values v ON d.document_id = v.document_id
    LEFT JOIN template_fields f ON v.field_id = f.field_id
";

if ($roleId == 1 || $roleId == 2) {

    // 🟢 admin + officer ทั้งคู่เห็นเอกสารทุกอัน
    $sql = $selectSql . "
        GROUP BY d.document_id, d.owner_id, u.role_id, d.doc_date, d.status, d.subject, t.template_code, t.template_name, t.question_path, t.document_path
        ORDER BY d.created_at DESC
    ";
    $stmt = $pdo->query($sql);

} else {

    // 🔒 user เห็นเฉพาะของตัวเอง
    $sql = $selectSql . "
        WHERE d.owner_id = :u
        GROUP BY d.document_id, d.owner_id, u.role_id, d.doc_date, d.status, d.subject, t.template_code, t.template_name, t.question_path, t.document_path
        ORDER BY d.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':u' => $userId]);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $row['course_name'] = homeBuildDetail($row);
}
unset($row);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows, JSON_UNESCAPED_UNICODE);