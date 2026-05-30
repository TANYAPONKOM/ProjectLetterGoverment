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
    $hint = $joinType . ' ' . $subject . ' ' . homeCleanValue($row['course_name_raw'] ?? '');

    $coopOrg = homeCleanValue($row['coop_organization_name'] ?? '');
    if ($coopOrg !== '') {
        return 'สถานประกอบการ: ' . $coopOrg;
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
    return $courseName !== '' ? $courseName : '(ไม่มีรายละเอียด)';
}

$selectSql = "
    SELECT 
      d.document_id,
      d.doc_date,
      d.status,
      d.subject,
      MAX(CASE WHEN f.field_key = 'join_type' THEN v.value_text END) AS join_type,
      MAX(CASE WHEN f.field_key = 'course_name' THEN v.value_text END) AS course_name_raw,
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
    LEFT JOIN document_values v ON d.document_id = v.document_id
    LEFT JOIN template_fields f ON v.field_id = f.field_id
";

if ($roleId == 1 || $roleId == 2) {

    // 🟢 admin + officer ทั้งคู่เห็นเอกสารทุกอัน
    $sql = $selectSql . "
        GROUP BY d.document_id, d.doc_date, d.status, d.subject
        ORDER BY d.created_at DESC
    ";
    $stmt = $pdo->query($sql);

} else {

    // 🔒 user เห็นเฉพาะของตัวเอง
    $sql = $selectSql . "
        WHERE d.owner_id = :u
        GROUP BY d.document_id, d.doc_date, d.status, d.subject
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