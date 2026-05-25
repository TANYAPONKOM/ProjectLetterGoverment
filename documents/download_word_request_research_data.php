<?php
// Pro_letter/documents/download_word_request_research_data.php
// ดาวน์โหลด Word (.docx) สำหรับหนังสือขอความอนุเคราะห์ข้อมูลเพื่อใช้ในการจัดทำปริญญานิพนธ์

session_start();
require_once __DIR__ . '/../functions.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit('ยังไม่ได้ติดตั้ง PHPWord: กรุณารัน composer require phpoffice/phpword ที่โฟลเดอร์ Pro_letter ก่อน');
}
require_once $autoload;
require_once __DIR__ . '/word_templates/word_common.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$userId = (int)$_SESSION['user_id'];
$roleId = (int)($_SESSION['role_id'] ?? 0);
$isAdmin = ($roleId === 1);
$isOfficer = ($roleId === 2);

$pdo = db();
$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($docId <= 0) {
    exit('ไม่พบรหัสเอกสาร');
}

$stmt = $pdo->prepare("
    SELECT document_id, template_id, owner_id, department_id,
           doc_no, doc_date, subject, header_text, status
    FROM documents
    WHERE document_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $docId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$document) {
    exit('ไม่พบเอกสาร');
}

if (!$isAdmin && !$isOfficer && (int)$document['owner_id'] !== $userId) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์ดาวน์โหลดเอกสารนี้');
}

$q = $pdo->prepare("
    SELECT dv.field_id, dv.value_text, tf.field_key
    FROM document_values dv
    LEFT JOIN template_fields tf ON tf.field_id = dv.field_id
    WHERE dv.document_id = :id
");
$q->execute([':id' => $docId]);

$valueMap = [];
$valueMapByKey = [];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $fid = (int)($row['field_id'] ?? 0);
    $val = (string)($row['value_text'] ?? '');
    if ($fid > 0) {
        $valueMap[$fid] = $val;
    }
    $key = trim((string)($row['field_key'] ?? ''));
    if ($key !== '') {
        $valueMapByKey[$key] = $val;
    }
}

function researchField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '') {
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $default;
}

function wordThaiDigit($text) {
    return strtr((string)$text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}

function wordArabicDigit($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

function researchThaiMonths() {
    return [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
}

function researchThaiDateFromParts($year, $month, $day) {
    $year = (int)$year;
    $month = (int)$month;
    $day = (int)$day;

    if ($year > 2400) {
        $christYear = $year - 543;
        $thaiYear = $year;
    } else {
        $christYear = $year;
        $thaiYear = $year + 543;
    }

    if (!checkdate($month, $day, $christYear)) {
        return '';
    }

    $months = researchThaiMonths();
    return wordThaiDigit($day) . ' ' . $months[$month] . ' ' . wordThaiDigit($thaiYear);
}

function researchThaiDateAny($rawDate) {
    $rawDate = trim((string)$rawDate);
    if ($rawDate === '') {
        return '';
    }

    $date = wordArabicDigit($rawDate);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        return researchThaiDateFromParts($m[1], $m[2], $m[3]);
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) {
        return researchThaiDateFromParts($m[3], $m[2], $m[1]);
    }

    $months = researchThaiMonths();
    $monthRegex = implode('|', array_map('preg_quote', $months));
    if (preg_match('/(\d{1,2})\s+(' . $monthRegex . ')\s+(\d{4})/u', $date, $m)) {
        $monthNumber = array_search($m[2], $months, true);
        if ($monthNumber !== false) {
            return researchThaiDateFromParts($m[3], $monthNumber, $m[1]);
        }
    }

    return wordThaiDigit($rawDate);
}

function researchClean($text) {
    return cleanWordText(str_replace(["\r", "\n"], ' ', (string)$text));
}

function researchInlineText($text) {
    $text = researchClean($text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function researchThaiWordWrap($text) {
    $text = researchInlineText($text);
    if ($text === '') {
        return '';
    }

    $zwsp = "\u{200B}";

    /*
     * ห้ามแทรก ZWSP ระหว่างตัวอักษรไทยทุกตัว
     * เพราะจะทำให้สระ/วรรณยุกต์ เช่น นี้, ที่, ผู้ เพี้ยนใน Word
     * ให้ล้าง ZWSP เดิมออกก่อน แล้วค่อยใส่เฉพาะจุดที่ปลอดภัย
     */
    $text = str_replace($zwsp, '', $text);

    // กันกรณีมี ZWSP หลุดไปอยู่ก่อน/หลังสระหรือวรรณยุกต์ไทย
    $thaiMarks = "\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}";
    $z = preg_quote($zwsp, '/');
    $text = preg_replace('/' . $z . '(?=[' . $thaiMarks . '])/u', '', $text);
    $text = preg_replace('/(?<=[' . $thaiMarks . '])' . $z . '/u', '', $text);

    // จุดตัดที่ปลอดภัยหลังเครื่องหมาย
    $text = preg_replace('/([\/\-–—,;:()（）"“”])/u', '$1' . $zwsp, $text);

    // จุดตัดคำแบบปลอดภัย เฉพาะคำ/วลีที่มักใช้ในเอกสารนี้
    $safeWords = [
        'ข้อมูล',
        'รูปภาพ',
        'กระเป๋าสัมภาระ',
        'ของผู้โดยสาร',
        'ผู้โดยสาร',
        'เพื่อใช้',
        'ในการจัดทำ',
        'การจัดทำ',
        'จัดทำ',
        'ขอความอนุเคราะห์ข้อมูล',
        'ขอความอนุเคราะห์',
        'ด้วยในภาคเรียนที่',
        'ปีการศึกษา',
        'ภาควิชาเทคโนโลยีสารสนเทศ',
        'คณะเทคโนโลยีและการจัดการอุตสาหกรรม',
        'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ',
        'วิทยาเขตปราจีนบุรี',
        'รายวิชา',
        'หลักสูตร',
        'สาขาวิชา',
        'ปริญญาตรี',
        'ชั้นปีที่',
        'ปริญญานิพนธ์',
        'อาจารย์ที่ปรึกษาปริญญานิพนธ์',
        'ทางคณะ',
        'จึงขอความอนุเคราะห์',
        'ให้ความอนุเคราะห์',
        'เพื่อนำข้อมูล',
        'มาประกอบการจัดทำ',
        'หัวข้อดังกล่าวข้างต้น',
        'โดยมีรายชื่อนักศึกษา',
        'ที่จะขอความอนุเคราะห์',
        'ในครั้งนี้',
        'จำนวน',
        'คน ดังนี้',
        'จึงเรียนมาเพื่อโปรดพิจารณา',
        'หากขัดข้องประการใด',
        'กรุณาแจ้งให้',
        'และขอขอบคุณ',
        'มา ณ โอกาสนี้',
    ];

    foreach ($safeWords as $word) {
        $text = str_replace($word, $word . $zwsp, $text);
    }

    return $text;
}

function researchNoBorderCell($valign = 'top') {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => 0,
        'cellMarginRight' => 0,
        'valign' => $valign,
    ];
}

function addResearchPairRow($section, $label, $value, $spaceAfter = 0) {
    $label = researchInlineText($label);
    $value = researchThaiWordWrap($value);

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => 9072,
    ]);

    $table->addRow();

    $table->addCell(Converter::cmToTwip(1.0), researchNoBorderCell())->addText($label, 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $table->addCell(Converter::cmToTwip(15.0), researchNoBorderCell())->addText($value, 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
    ]);
}

function addResearchPara($section, array $parts, $spaceAfter = 35, $firstLineCm = 2.5) {
    $text = '';
    foreach ($parts as $part) {
        $text .= is_array($part) ? ($part[0] ?? '') : $part;
    }

    $text = researchThaiWordWrap($text);
    if ($text === '') {
        return;
    }

    $section->addText($text, 'normalFont', [
        'alignment' => 'thaiDistribute',
        'lineHeight' => 0.94,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'indentation' => ['firstLine' => Converter::cmToTwip($firstLineCm)],
    ]);
}

function addResearchStudentList($section, array $students) {
    if (count($students) === 0) {
        $section->addText('๑. ................................................ รหัสนักศึกษา ....................................', 'normalFont', [
            'indentation' => ['left' => Converter::cmToTwip(2.5)],
            'spaceBefore' => 0,
            'spaceAfter' => 20,
            'lineHeight' => 1.0,
        ]);
        return;
    }

    foreach ($students as $idx => $student) {
        $line = wordThaiDigit((string)($idx + 1)) . '. ' .
            researchInlineText($student['name'] ?? '') .
            ' รหัสนักศึกษา ' . formatResearchStudentId($student['student_id'] ?? '');

        $section->addText(researchThaiWordWrap($line), 'normalFont', [
            'indentation' => ['left' => Converter::cmToTwip(2.5)],
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);
    }

    $section->addText('', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 20,
        'lineHeight' => 1.0,
    ]);
}

function formatResearchStudentId($sid) {
    $digits = preg_replace('/\D+/', '', (string)$sid);
    if (strlen($digits) === 13) {
        $digits = substr($digits, 0, 2) . '-' . substr($digits, 2, 6) . '-' . substr($digits, 8, 4) . '-' . substr($digits, 12, 1);
    }
    return wordThaiDigit($digits);
}

function formatResearchPhone($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if (strlen($digits) === 10) {
        $digits = substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4);
    }
    return wordThaiDigit($digits);
}

function decodeResearchStudents($json) {
    $arr = json_decode((string)$json, true);
    if (!is_array($arr)) {
        return [];
    }

    $students = [];
    foreach ($arr as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = trim((string)($item['name'] ?? ''));
        $sid = trim((string)($item['student_id'] ?? ''));
        $phone = trim((string)($item['phone'] ?? ''));

        if ($name === '' && $sid === '' && $phone === '') {
            continue;
        }

        $students[] = [
            'name' => $name,
            'student_id' => $sid,
            'phone' => $phone,
            'is_contact' => !empty($item['is_contact']),
        ];
    }

    return $students;
}

function addResearchHeader($section, $docNo, $displayFaculty, $thaiDocDate) {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(17.10),
    ]);

    $table->addRow(Converter::cmToTwip(3.1));

    $left = $table->addCell(Converter::cmToTwip(4.95), researchNoBorderCell('top'));
    $left->addText('', 'normalFont', ['spaceAfter' => 800, 'lineHeight' => 1.0]);
    $left->addText('ที่ ' . researchInlineText($docNo ?: 'อว ๗๑๒๐/'), 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);

    $middle = $table->addCell(Converter::cmToTwip(3.55), researchNoBorderCell('top'));
    if (file_exists($garuda)) {
        $middle->addImage($garuda, [
            'width' => 80,
            'alignment' => Jc::CENTER,
        ]);
    } else {
        $middle->addText('');
    }

    $right = $table->addCell(Converter::cmToTwip(8.60), researchNoBorderCell('top'));
    $right->addText('', 'normalFont', ['spaceAfter' => 720, 'lineHeight' => 0.95]);
    $right->addText(researchClean('คณะ' . $displayFaculty), 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);

    $section->addText(researchClean($thaiDocDate), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 80,
        'spaceAfter' => 160,
        'lineHeight' => 1.0,
        'indentation' => ['left' => Converter::cmToTwip(1.0)],
    ]);
}

function addResearchSignature($section, $displayFacultyDean) {
    $section->addText('ขอแสดงความนับถือ', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 20,
        'spaceAfter' => 500,
        'lineHeight' => 1.0,
    ]);

    $section->addText('(ผู้ช่วยศาสตราจารย์ ดร.กฤษฎากร บุดดาจันทร์)', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $section->addText(researchClean($displayFacultyDean), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addResearchFooter($section, $displayDepartmentFull) {
    $footer = $section->addFooter();

    $footer->addText(researchClean($displayDepartmentFull), 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.9,
    ]);
    $footer->addText('โทรศัพท์ ๐-๓๗๒๑-๗๓๔๐-๓ ต่อ ๗๐๖๕-๖', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.9,
    ]);
    $footer->addText('ไปรษณีย์อิเล็กทรอนิกส์ : it@itm.kmutnb.ac.th', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.9,
    ]);
}

function addResearchDataRequestPage($phpWord, array $data) {
    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => Converter::cmToTwip(2),
        'marginBottom' => Converter::cmToTwip(1.15),
        'marginLeft' => Converter::cmToTwip(3.0),
        'marginRight' => Converter::cmToTwip(2.0),
        'footerHeight' => Converter::cmToTwip(0.85),
    ]);

    addResearchHeader($section, $data['docNo'], $data['displayFaculty'], $data['thaiDocDate']);
    addResearchPairRow($section, 'เรื่อง', $data['displaySubject']);
    addResearchPairRow($section, 'เรียน', $data['displayToPerson'], 20);

    addResearchPara($section, [
        'ด้วยในภาคเรียนที่ ', $data['researchSemester'],
        ' ปีการศึกษา ', $data['researchAcademicYear'],
        ' ', $data['displayDepartmentFull'],
        ' คณะ', $data['displayFaculty'],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี',
        ' ได้เปิดทำการสอนรายวิชา ', $data['researchCourseText'],
        ' ในหลักสูตร', $data['researchCurriculumName'],
        ' สาขาวิชา', $data['researchMajorName'],
        ' โดยหลักสูตรกำหนดให้นักศึกษาปริญญาตรี ชั้นปีที่ ', $data['researchStudentYear'],
        ' จัดทำปริญญานิพนธ์ เรื่อง “', $data['researchThesisTitle'], '”',
        ' โดยมี', $data['researchAdvisorName'],
        ' เป็นอาจารย์ที่ปรึกษาปริญญานิพนธ์',
    ], 28);

    addResearchPara($section, [
        'ทางคณะ', $data['displayFaculty'],
        ' จึงขอความอนุเคราะห์มายังท่านได้โปรดให้ความอนุเคราะห์',
        $data['researchDataRequestText'],
        $data['researchDataAmount'] !== '' ? ' จำนวน ' . $data['researchDataAmount'] : '',
        ' เพื่อนำข้อมูลมาประกอบการจัดทำปริญญานิพนธ์หัวข้อดังกล่าวข้างต้น',
        ' โดยมีรายชื่อนักศึกษาที่จะขอความอนุเคราะห์ในครั้งนี้ จำนวน ',
        $data['researchStudentCountText'], ' คน ดังนี้',
    ], 8);

    addResearchStudentList($section, $data['researchStudents']);

    addResearchPara($section, [
        'จึงเรียนมาเพื่อโปรดพิจารณา หากขัดข้องประการใด',
        ' กรุณาแจ้งให้ทางคณะ', $data['displayFaculty'],
        $data['contactText'],
        ' และขอขอบคุณมา ณ โอกาสนี้',
    ], 28);

    addResearchSignature($section, $data['displayFacultyDean']);
    addResearchFooter($section, $data['displayDepartmentFull']);
}

$docDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$docNo = $document['doc_no'] ?? '';

$faculty = $valueMap[10] ?? '';
$department = $valueMap[11] ?? '';

$researchSubject = researchField($valueMapByKey, $valueMap, 'research_subject', 40, $document['subject'] ?? 'ขอความอนุเคราะห์ข้อมูลรูปภาพ X-ray กระเป๋าสัมภาระของผู้โดยสารเพื่อใช้ในการจัดทำปริญญานิพนธ์');
$researchToPerson = researchField($valueMapByKey, $valueMap, 'research_to_person', 41, 'กรรมการผู้อำนวยการใหญ่ บริษัท ท่าอากาศยานไทย จำกัด (มหาชน)');
$researchSemester = researchField($valueMapByKey, $valueMap, 'research_semester', 42, '1');
$researchAcademicYear = researchField($valueMapByKey, $valueMap, 'research_academic_year', 43, '');
$researchCourseCode = researchField($valueMapByKey, $valueMap, 'research_course_code', 44, '');
$researchCourseName = researchField($valueMapByKey, $valueMap, 'research_course_name', 45, '');
$researchCurriculumName = researchField($valueMapByKey, $valueMap, 'research_curriculum_name', 46, '');
$researchMajorName = researchField($valueMapByKey, $valueMap, 'research_major_name', 47, '');
$researchStudentYear = researchField($valueMapByKey, $valueMap, 'research_student_year', 48, '');
$researchThesisTitle = researchField($valueMapByKey, $valueMap, 'research_thesis_title', 49, '');
$researchAdvisorName = researchField($valueMapByKey, $valueMap, 'research_advisor_name', 50, '');
$researchSupportType = researchField($valueMapByKey, $valueMap, 'research_support_type', 52, '');
$researchDataDetail = researchField($valueMapByKey, $valueMap, 'research_data_detail', 53, '');
$researchDataAmount = researchField($valueMapByKey, $valueMap, 'research_data_amount', 54, '');
$researchStudentsJson = researchField($valueMapByKey, $valueMap, 'research_students_json', 55, '[]');

$researchStudents = decodeResearchStudents($researchStudentsJson);
$researchStudentCount = count($researchStudents);

$researchContactStudent = null;
foreach ($researchStudents as $student) {
    if (!empty($student['is_contact'])) {
        $researchContactStudent = $student;
        break;
    }
}
if (!$researchContactStudent && $researchStudentCount > 0) {
    $researchContactStudent = $researchStudents[0];
}

$displayFaculty = trim($faculty) !== '' ? trim($faculty) : 'เทคโนโลยีและการจัดการอุตสาหกรรม';
if (mb_strpos($displayFaculty, 'คณะ') === 0) {
    $displayFacultyNoPrefix = trim(mb_substr($displayFaculty, 4));
} else {
    $displayFacultyNoPrefix = $displayFaculty;
}
$displayFaculty = $displayFacultyNoPrefix;

$displayDepartment = trim($department) !== '' ? trim($department) : 'เทคโนโลยีสารสนเทศ';
$displayDepartmentFull = 'ภาควิชา' . $displayDepartment;
$displayFacultyDean = 'คณบดีคณะ' . $displayFaculty;

$researchCourseText = trim($researchCourseCode . ' ' . $researchCourseName);
$researchDataRequestText = trim(($researchSupportType !== '' ? $researchSupportType . ' ' : '') . $researchDataDetail);

$contactText = '';
if ($researchContactStudent) {
    $contactName = researchInlineText($researchContactStudent['name'] ?? '');
    $contactPhone = formatResearchPhone($researchContactStudent['phone'] ?? '');
    if ($contactName !== '' || $contactPhone !== '') {
        $contactText = ' หรือที่ ' . $contactName . ($contactPhone !== '' ? ' หมายเลขโทรศัพท์ ' . $contactPhone : '');
    }
}

$phpWord = new PhpWord();
setupWordDefaults($phpWord);
$phpWord->addFontStyle('addressFont', [
    'name' => 'TH SarabunPSK',
    'size' => 15.5,
]);

addResearchDataRequestPage($phpWord, [
    'docNo' => $docNo,
    'displayFaculty' => $displayFaculty,
    'thaiDocDate' => researchThaiDateAny($docDate),
    'displaySubject' => $researchSubject,
    'displayToPerson' => $researchToPerson,
    'displayDepartmentFull' => $displayDepartmentFull,
    'researchSemester' => wordThaiDigit($researchSemester),
    'researchAcademicYear' => wordThaiDigit($researchAcademicYear),
    'researchCourseText' => wordThaiDigit($researchCourseText),
    'researchCurriculumName' => $researchCurriculumName,
    'researchMajorName' => $researchMajorName,
    'researchStudentYear' => wordThaiDigit($researchStudentYear),
    'researchThesisTitle' => $researchThesisTitle,
    'researchAdvisorName' => $researchAdvisorName,
    'researchDataRequestText' => $researchDataRequestText !== '' ? $researchDataRequestText : 'ข้อมูล',
    'researchDataAmount' => wordThaiDigit($researchDataAmount),
    'researchStudentCountText' => wordThaiDigit((string)$researchStudentCount),
    'researchStudents' => $researchStudents,
    'contactText' => $contactText,
    'displayFacultyDean' => $displayFacultyDean,
]);

$filename = 'request_research_data_' . $docId . '.docx';
if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;