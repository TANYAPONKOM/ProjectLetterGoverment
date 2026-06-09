<?php
// Pro_letter/documents/download_word_coop_evaluation.php
// ดาวน์โหลด Word (.docx) สำหรับหนังสือขอความอนุเคราะห์ตอบแบบประเมินและแบบสำรวจนักศึกษาปฏิบัติงานสหกิจศึกษา

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

$stmt = $pdo->prepare("\n    SELECT document_id, template_id, owner_id, department_id,\n           doc_no, doc_date, subject, header_text, status\n    FROM documents\n    WHERE document_id = :id\n    LIMIT 1\n");
$stmt->execute([':id' => $docId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$document) {
    exit('ไม่พบเอกสาร');
}

if (!$isAdmin && !$isOfficer && (int)$document['owner_id'] !== $userId) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์ดาวน์โหลดเอกสารนี้');
}

$q = $pdo->prepare("\n    SELECT dv.field_id, dv.value_text, tf.field_key\n    FROM document_values dv\n    LEFT JOIN template_fields tf ON tf.field_id = dv.field_id\n    WHERE dv.document_id = :id\n");
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

function coopField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '') {
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $default;
}

function coopThaiDigit($text) {
    return strtr((string)$text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}

function coopArabicDigit($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

function coopThaiMonths() {
    return [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
}

function coopThaiDateFromParts($year, $month, $day) {
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

    $months = coopThaiMonths();
    return coopThaiDigit($day) . ' ' . $months[$month] . ' ' . coopThaiDigit($thaiYear);
}

function coopThaiDateAny($rawDate) {
    $rawDate = trim((string)$rawDate);
    if ($rawDate === '') {
        return '';
    }

    $date = coopArabicDigit($rawDate);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        return coopThaiDateFromParts($m[1], $m[2], $m[3]);
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) {
        return coopThaiDateFromParts($m[3], $m[2], $m[1]);
    }

    $months = coopThaiMonths();
    $monthRegex = implode('|', array_map('preg_quote', $months));
    if (preg_match('/(\d{1,2})\s+(' . $monthRegex . ')\s+(\d{4})/u', $date, $m)) {
        $monthNumber = array_search($m[2], $months, true);
        if ($monthNumber !== false) {
            return coopThaiDateFromParts($m[3], $monthNumber, $m[1]);
        }
    }

    return coopThaiDigit($rawDate);
}

function coopClean($text) {
    return coopThaiDigit(cleanWordText(str_replace(["\r", "\n"], ' ', (string)$text)));
}

function coopInlineText($text) {
    $text = coopClean($text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function coopThaiWordWrap($text) {
    $text = coopInlineText($text);
    if ($text === '') {
        return '';
    }

    $zwsp = "\u{200B}";

    // ห้ามแทรกกลางตัวอักษรไทยทุกตัว เพราะทำให้สระ/วรรณยุกต์เพี้ยนใน Word
    $text = preg_replace('/([\/\-–—,;:()（）"“”])/u', '$1' . $zwsp, $text);

    $safeWords = [
        'ภาควิชา', 'เทคโนโลยีสารสนเทศ', 'คณะ', 'มหาวิทยาลัย', 'วิทยาเขต', 'ปราจีนบุรี',
        'สหกิจศึกษา', 'ปฏิบัติงาน', 'นักศึกษา', 'หน่วยงาน', 'ของท่าน', 'แบบประเมิน',
        'แบบสำรวจ', 'คุณลักษณะ', 'สถานประกอบการ', 'ความต้องการ', 'พนักงานที่ปรึกษา',
        'ข้อมูล', 'รวบรวม', 'วิเคราะห์', 'สรุปผล', 'ความอนุเคราะห์', 'ดำเนินการครั้งต่อไป',
        'พิจารณา', 'แจ้งผู้เกี่ยวข้อง', 'ขอขอบคุณ', 'โอกาสต่อไป', 'ตั้งแต่วันที่', 'ทั้งนี้',
        'ในการนี้', 'สุดท้ายนี้', 'จึงเรียนมา', 'รายงานการปฏิบัติงาน', 'ผลรายงาน',
    ];

    foreach ($safeWords as $word) {
        $quoted = preg_quote($word, '/');
        $text = preg_replace('/(' . $quoted . ')/u', '$1' . $zwsp, $text);
    }

    return $text;
}

function coopNoBorderCell($valign = 'top') {
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

function addCoopPairRow($section, $label, $value, $spaceAfter = 0) {
    $label = coopInlineText($label);
    $value = coopThaiWordWrap($value);

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => 9072,
    ]);

    $table->addRow();

    $table->addCell(Converter::cmToTwip(1.0), coopNoBorderCell('top'))->addText($label, 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $table->addCell(Converter::cmToTwip(15.0), coopNoBorderCell('top'))->addText($value, 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
        'indentation' => ['left' => Converter::cmToTwip(0.06)],
    ]);
}

function addCoopPara($section, array $parts, $spaceAfter = 20, $firstLineCm = 2.5, $alignment = 'thaiDistribute') {
    $text = '';
    foreach ($parts as $part) {
        $text .= is_array($part) ? ($part[0] ?? '') : $part;
    }

    $text = coopThaiWordWrap($text);
    if ($text === '') {
        return;
    }

    $section->addText($text, 'normalFont', [
        'alignment' => $alignment,
        'lineHeight' => 0.94,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'indentation' => ['firstLine' => Converter::cmToTwip($firstLineCm)],
    ]);
}

function addCoopStudentList($section, array $students) {
    if (count($students) === 0) {
        $section->addText('................................................ รหัสนักศึกษา ....................................', 'normalFont', [
            'indentation' => ['left' => Converter::cmToTwip(2.5)],
            'spaceBefore' => 0,
            'spaceAfter' => 10,
            'lineHeight' => 1.0,
        ]);
        return;
    }

    foreach ($students as $idx => $student) {
        $line = coopInlineText($student['name'] ?? '') .
            ' รหัสนักศึกษา ' . formatCoopStudentId($student['id'] ?? '');

        $section->addText(coopThaiWordWrap($line), 'normalFont', [
            'indentation' => ['left' => Converter::cmToTwip(2.5)],
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);
    }

    $section->addText('', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 10,
        'lineHeight' => 1.0,
    ]);
}

function formatCoopStudentId($sid) {
    $digits = preg_replace('/\D+/', '', coopArabicDigit((string)$sid));
    if (strlen($digits) === 13) {
        $digits = substr($digits, 0, 2) . '-' . substr($digits, 2, 6) . '-' . substr($digits, 8, 4) . '-' . substr($digits, 12, 1);
    }
    return coopThaiDigit($digits !== '' ? $digits : $sid);
}

function decodeCoopStudents($studentsJson, $studentListText = '') {
    $rows = [];
    $decoded = json_decode((string)$studentsJson, true);

    if (is_array($decoded)) {
        foreach ($decoded as $student) {
            if (!is_array($student)) {
                continue;
            }
            $name = trim((string)($student['name'] ?? $student['student_name'] ?? $student['fullname'] ?? ''));
            $id = trim((string)($student['student_id'] ?? $student['id'] ?? ''));
            if ($name === '' && $id === '') {
                continue;
            }
            $rows[] = ['name' => $name, 'id' => $id];
        }
    }

    if (count($rows) === 0 && trim((string)$studentListText) !== '') {
        $lines = preg_split('/\r\n|\r|\n/', (string)$studentListText);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*รหัสนักศึกษา\s*/u', $line, 2);
            $rows[] = [
                'name' => trim($parts[0] ?? ''),
                'id' => trim($parts[1] ?? ''),
            ];
        }
    }

    return $rows;
}

function addCoopHeader($section, $docNo, $displayFaculty, $thaiDocDate, $displayHeaderAgency = '') {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        // ขยายเฉพาะหัวกระดาษ เพื่อให้บรรทัดที่อยู่ด้านขวายาวเลยขอบขวาได้เล็กน้อย
        'width' => Converter::cmToTwip(17.10),
    ]);

    $table->addRow(Converter::cmToTwip(3.1));

    $left = $table->addCell(Converter::cmToTwip(4.95), coopNoBorderCell('top'));
    $left->addText('', 'normalFont', ['spaceAfter' => 800, 'lineHeight' => 1.0]);
    $left->addText('ที่ ' . coopInlineText($docNo ?: ''), 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);

    $middle = $table->addCell(Converter::cmToTwip(3.55), coopNoBorderCell('top'));
    if (file_exists($garuda)) {
        $middle->addImage($garuda, [
            'width' => 80,
            'alignment' => Jc::CENTER,
        ]);
    } else {
        $middle->addText('');
    }

    $right = $table->addCell(Converter::cmToTwip(8.60), coopNoBorderCell('top'));
    $right->addText('', 'normalFont', ['spaceAfter' => 720, 'lineHeight' => 0.95]);
    $right->addText(coopClean($displayFaculty), 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);

    $section->addText(coopClean($thaiDocDate), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 80,
        'spaceAfter' => 160,
        'lineHeight' => 1.0,
        'indentation' => ['left' => Converter::cmToTwip(1.0)],
    ]);
}

function addCoopSignature($section, $receiverName, $receiverPosition) {
    $section->addText('ขอแสดงความนับถือ', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 20,
        'spaceAfter' => 500,
        'lineHeight' => 1.0,
    ]);

    $section->addText('(' . coopInlineText($receiverName) . ')', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $section->addText(coopClean($receiverPosition), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addCoopFooter($section, $displayDepartmentFull) {
    $footer = $section->addFooter();

    $footer->addText(coopClean($displayDepartmentFull), 'normalFont', [
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

function addCoopEvaluationPage($phpWord, array $data) {
    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => Converter::cmToTwip(2),
        'marginBottom' => Converter::cmToTwip(1.15),
        'marginLeft' => Converter::cmToTwip(3.0),
        'marginRight' => Converter::cmToTwip(2.0),
        'footerHeight' => Converter::cmToTwip(0.85),
    ]);

    addCoopHeader($section, $data['docNo'], $data['displayFaculty'], $data['thaiDocDate'], $data['displayHeaderAgency'] ?? '');
    addCoopPairRow($section, 'เรื่อง', $data['coopSubject']);
    addCoopPairRow($section, 'เรียน', $data['coopToPerson'], 20);

    addCoopPara($section, [
        'ตามที่ ', $data['coopOrganizationName'],
        ' ได้ให้ความอนุเคราะห์รับนักศึกษา', $data['displayDepartmentFull'],
        ' ', $data['displayFaculty'],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี ได้แก่',
    ], 8);

    addCoopStudentList($section, $data['coopStudentRows']);

    addCoopPara($section, [
        'เข้าปฏิบัติงานสหกิจศึกษาในหน่วยงานของท่าน ตั้งแต่วันที่ ', $data['coopPeriod'],
    ], 16, 0.0, 'thaiDistribute');

    addCoopPara($section, [
        'ในการนี้ ', $data['displayDepartmentFull'],
        ' ขอความอนุเคราะห์ตอบแบบประเมินผลรายงานการปฏิบัติงานของนักศึกษาสหกิจศึกษา',
        ' และแบบสำรวจคุณลักษณะของนักศึกษาปฏิบัติงานสหกิจศึกษาที่พึงประสงค์ตามความต้องการของสถานประกอบการ (ในปีถัดไป)',
        ' โดยภาควิชาขออนุญาตส่งแบบประเมินและแบบสำรวจดังกล่าวให้กับ “', $data['coopAdvisorName'], '”',
        ' ทั้งนี้ ข้อมูลที่ได้จากแบบประเมินและแบบสำรวจจะนำมารวบรวม วิเคราะห์ และสรุปผล',
        ' ซึ่งภาควิชาจะนำข้อมูลมาเป็นแนวทางสำหรับการดำเนินการครั้งต่อไป',
    ], 20);

    addCoopPara($section, [
        'สุดท้ายนี้ ', $data['displayDepartmentFull'],
        ' ขอขอบคุณในความอนุเคราะห์ของท่านเป็นอย่างยิ่ง และหวังว่าจะได้รับความอนุเคราะห์จากท่านอีกในโอกาสต่อไป',
    ], 20);

    addCoopPara($section, [
        'จึงเรียนมาเพื่อโปรดอนุญาต และพิจารณาแจ้งผู้เกี่ยวข้องดำเนินการต่อไป',
    ], 20);

    addCoopSignature($section, $data['coopReceiverName'], $data['coopReceiverPosition']);
    addCoopFooter($section, $data['displayDepartmentFull']);
}

$docDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$docNo = $document['doc_no'] ?? '';

$faculty = $valueMap[10] ?? '';
$department = $valueMap[11] ?? '';

$displayFaculty = trim($faculty) !== '' ? trim($faculty) : 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
if (mb_strpos($displayFaculty, 'คณะ') !== 0) {
    $displayFaculty = 'คณะ' . $displayFaculty;
}
$displayDepartment = trim($department) !== '' ? trim($department) : 'เทคโนโลยีสารสนเทศ';
$displayDepartmentFull = 'ภาควิชา' . $displayDepartment;
$displayFacultyDean = 'คณบดี' . $displayFaculty;

$selectedDeanName = '';
$selectedDeanPosition = '';
try {
    $deanStmt = $pdo->prepare("
        SELECT dean_name, dean_position, faculty_name
        FROM faculties
        WHERE faculty_name = :faculty
           OR faculty_name = CONCAT('คณะ', :faculty)
           OR REPLACE(faculty_name, 'คณะ', '') = :faculty
        LIMIT 1
    ");
    $deanStmt->execute([':faculty' => $displayFaculty]);
    $deanRow = $deanStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $selectedDeanName = trim((string)($deanRow['dean_name'] ?? ''));
    $selectedDeanPosition = trim((string)($deanRow['dean_position'] ?? ''));
    $selectedFacultyName = trim((string)($deanRow['faculty_name'] ?? ''));

    if ($selectedDeanPosition === '') {
        $selectedDeanPosition = 'คณบดี' . ($selectedFacultyName !== '' ? $selectedFacultyName : $displayFaculty);
    }
} catch (Throwable $e) {
    $selectedDeanName = '';
    $selectedDeanPosition = $displayFacultyDean;
}
$displayHeaderAgency = trim((string)($document['header_text'] ?? ''));
if ($displayHeaderAgency === '') {
    $displayHeaderAgency = trim($displayFaculty . ' ' . $displayDepartmentFull);
}

$coopSubject = coopField($valueMapByKey, $valueMap, 'coop_subject', 70, $document['subject'] ?? 'ขอความอนุเคราะห์ตอบแบบประเมินและแบบสำรวจนักศึกษาปฏิบัติงานสหกิจศึกษา');
$coopToPerson = coopField($valueMapByKey, $valueMap, 'coop_to_person', 71, 'เลขาธิการ สำนักงานคณะกรรมการการรักษาความมั่นคงปลอดภัยไซเบอร์แห่งชาติ (กสมช.)');
$coopOrganizationName = coopField($valueMapByKey, $valueMap, 'coop_organization_name', 72, 'หน่วยงานของท่าน');
$coopStudentCount = coopField($valueMapByKey, $valueMap, 'coop_student_count', 73, '');
$coopStudentsJson = coopField($valueMapByKey, $valueMap, 'coop_students_json', 74, '');
$coopStudentListText = coopField($valueMapByKey, $valueMap, 'coop_student_list_text', 75, '');
$coopPeriod = coopField($valueMapByKey, $valueMap, 'coop_period', 76, '');
$coopStartDate = coopField($valueMapByKey, $valueMap, 'coop_start_date', 77, '');
$coopEndDate = coopField($valueMapByKey, $valueMap, 'coop_end_date', 78, '');
$coopAdvisorName = coopField($valueMapByKey, $valueMap, 'coop_advisor_name', 79, 'พนักงานที่ปรึกษา');
$coopReceiverName = $selectedDeanName !== '' ? $selectedDeanName : '................................';
$coopReceiverPosition = $selectedDeanPosition !== '' ? $selectedDeanPosition : $displayFacultyDean;

if ($coopPeriod === '' && ($coopStartDate !== '' || $coopEndDate !== '')) {
    $startText = coopThaiDateAny($coopStartDate);
    $endText = coopThaiDateAny($coopEndDate);
    $coopPeriod = trim($startText . ($endText !== '' ? ' ถึง ' . $endText : ''));
}
if ($coopPeriod === '') {
    $coopPeriod = '๓ พฤศจิกายน ๒๕๖๘ ถึง ๒๗ กุมภาพันธ์ ๒๕๖๙';
}

$coopStudentRows = decodeCoopStudents($coopStudentsJson, $coopStudentListText);

$phpWord = new PhpWord();
setupWordDefaults($phpWord);
$phpWord->addFontStyle('addressFont', [
    'name' => 'TH SarabunPSK',
    'size' => 15.5,
]);

addCoopEvaluationPage($phpWord, [
    'docNo' => $docNo,
    'displayFaculty' => $displayFaculty,
    'displayHeaderAgency' => $displayHeaderAgency,
    'thaiDocDate' => coopThaiDateAny($docDate),
    'coopSubject' => $coopSubject,
    'coopToPerson' => $coopToPerson,
    'displayDepartmentFull' => $displayDepartmentFull,
    'displayFaculty' => $displayFaculty,
    'coopOrganizationName' => $coopOrganizationName,
    'coopStudentRows' => $coopStudentRows,
    'coopPeriod' => $coopPeriod,
    'coopAdvisorName' => $coopAdvisorName,
    'coopReceiverName' => $coopReceiverName,
    'coopReceiverPosition' => $coopReceiverPosition,
]);

$filename = 'coop_evaluation_' . $docId . '.docx';
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