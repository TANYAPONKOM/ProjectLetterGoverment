<?php
ob_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Pro_letter/documents/download_word_project_activity.php
// ดาวน์โหลด Word (.docx) สำหรับหนังสือขอเข้าไปจัดกิจกรรมโครงการ

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

function projectField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '') {
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $default;
}

function projectThaiDigit($text) {
    return strtr((string)$text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}

function projectArabicDigit($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

function projectThaiMonths() {
    return [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
}

function projectThaiDateAny($date) {
    $date = trim(projectArabicDigit((string)$date));
    if ($date === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        $months = projectThaiMonths();
        return projectThaiDigit($d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543));
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        $months = projectThaiMonths();
        return projectThaiDigit($d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543));
    }

    return projectThaiDigit($date);
}

function projectClean($text) {
    $text = str_replace(["\r", "\n", "\t"], ' ', (string)$text);
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    $text = projectThaiDigit(trim($text));

    // สำคัญ: PHPWord รุ่นที่ใช้อยู่ไม่ได้ escape XML special chars ให้ครบ
    // ถ้าข้อความมี & เช่น "Space Technology & AI" จะทำให้ word/document.xml เสียและ Word เปิดไม่ได้
    // จึง escape เฉพาะตัวอักษรที่ทำให้ XML พังก่อนส่งเข้า addText()
    $text = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;|#[0-9]+;|#x[0-9A-Fa-f]+;)/u', '&amp;', $text);
    $text = str_replace(['<', '>'], ['&lt;', '&gt;'], $text);

    return $text;
}

function projectInlineText($text) {
    return projectClean($text);
}

function projectThaiWordWrap($text) {
    $text = projectInlineText($text);
    if ($text === '') {
        return '';
    }

    $zwsp = "\u{200B}";

    /*
     * ห้ามแทรก ZWSP ระหว่างตัวอักษรไทยทุกตัว
     * เพราะจะทำให้สระ/วรรณยุกต์ เช่น นี้, ที่, ผู้ เพี้ยนใน Word
     */
    $text = str_replace($zwsp, '', $text);

    $thaiMarks = "\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}";
    $z = preg_quote($zwsp, '/');
    $text = preg_replace('/' . $z . '(?=[' . $thaiMarks . '])/u', '', $text);
    $text = preg_replace('/(?<=[' . $thaiMarks . '])' . $z . '/u', '', $text);

    // จุดตัดที่ปลอดภัยหลังเครื่องหมาย
    $text = preg_replace('/([\/\-–—,;:()（）"“”])/u', '$1' . $zwsp, $text);

    // จุดตัดคำ/วลีที่ใช้บ่อยในเอกสารโครงการ เพื่อให้ Word ไม่ยกทั้งก้อนลงบรรทัดใหม่
    $safeWords = [
        'ขออนุญาต',
        'ดำเนินการ',
        'จัดโครงการ',
        'โครงการอบรม',
        'โครงการอบรมเชิงปฏิบัติการ',
        'กิจกรรมย่อย',
        'รายละเอียดโครงการ',
        'สิ่งที่ส่งมาด้วย',
        'ด้วยภาควิชา',
        'ภาควิชาเทคโนโลยีสารสนเทศ',
        'คณะเทคโนโลยีและการจัดการอุตสาหกรรม',
        'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ',
        'วิทยาเขตปราจีนบุรี',
        'ได้ดำเนินการ',
        'โดยมีวัตถุประสงค์',
        'ให้แก่',
        'จำนวน',
        'ในการนี้',
        'จึงขออนุญาต',
        'เป็นวิทยากร',
        'ผู้ดำเนินกิจกรรม',
        'ในโครงการฯ',
        'ตามวัน เวลา และสถานที่',
        'ดังกล่าวข้างต้น',
        'จึงเรียนมาเพื่อโปรดพิจารณา',
        'จะขอบคุณยิ่ง',
        'และ',
        'หรือ',
        'โดย',
        'เพื่อ',
        'ซึ่ง',
        'ของ',
        'กับ',
        'จาก',
        'ตาม',
        'เป็น',
        'ให้',
        'ได้',
        'มี',
        'ณ',
    ];

    foreach ($safeWords as $word) {
        $text = str_replace($word, $word . $zwsp, $text);
    }

    return $text;
}

function projectNoBorderCell($valign = 'center') {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'valign' => $valign,
        'marginTop' => 0,
        'marginBottom' => 0,
        'marginLeft' => 0,
        'marginRight' => 0,
    ];
}

function addProjectHeader($section, $docNo, $displayFaculty, $thaiDocDate) {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(18.40),
    ]);

    $table->addRow(Converter::cmToTwip(3.1));

    $left = $table->addCell(Converter::cmToTwip(6.20), projectNoBorderCell('top'));
    $left->addText('', 'normalFont', ['spaceAfter' => 700, 'lineHeight' => 1.0]);
    $left->addText('ที่ ' . projectInlineText($docNo ?: ''), 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);

$middle = $table->addCell(Converter::cmToTwip(3.55), projectNoBorderCell('top'));

if (file_exists($garuda)) {
    $middle->addImage($garuda, [
        'width' => 80,
        'alignment' => Jc::CENTER,
    ]);
} else {
    $middle->addText('');
}

    $right = $table->addCell(Converter::cmToTwip(8.65), projectNoBorderCell('top'));
    $right->addText('', 'normalFont', ['spaceAfter' => 620, 'lineHeight' => 0.95]);
    $right->addText(projectClean($displayFaculty), 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
$section->addText(str_repeat("\u{00A0}", 28) . projectClean($thaiDocDate), 'normalFont', [
    'alignment' => Jc::CENTER,
    'spaceBefore' => 40,
    'spaceAfter' => 160,
    'lineHeight' => 1.0,
]);
}

function addProjectPairRow($section, $label, $value, $spaceAfter = 0) {
    $label = projectInlineText($label);
    $value = projectThaiWordWrap($value);

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => 9072,
    ]);

    $table->addRow(null, ['exactHeight' => false]);

    // ต้องใช้ valign = top ไม่ใช่ center
    // เพราะถ้าข้อความฝั่งขวายาว 2 บรรทัด Word จะดันคำว่า เรื่อง/เรียนไปอยู่กลางแถว
    // ทำให้ดูเหมือนคำว่า เรื่อง ไม่อยู่บรรทัดเดียวกับข้อความบรรทัดแรก
    $table->addCell(Converter::cmToTwip(1.05), projectNoBorderCell('top'))->addText($label, 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    // ช่องข้อความยังคงกว้างเต็มพื้นที่เอกสาร แต่จัดชิดซ้ายเพื่อไม่ให้หัวเรื่องถ่างเกิน
    $table->addCell(Converter::cmToTwip(14.95), projectNoBorderCell('top'))->addText($value, 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
    ]);
}

function addProjectAttachmentRows($section) {
    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => 9072,
    ]);

    $table->addRow();

    $table->addCell(Converter::cmToTwip(2.35), projectNoBorderCell())->addText('สิ่งที่ส่งมาด้วย', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $table->addCell(Converter::cmToTwip(9.30), projectNoBorderCell())->addText('๑. รายละเอียดโครงการ', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
        'indentation' => ['left' => Converter::cmToTwip(0.15)],
    ]);

    $table->addCell(Converter::cmToTwip(4.35), projectNoBorderCell())->addText('จำนวน ๑ ชุด', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $section->addText('', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 40,
        'lineHeight' => 1.0,
    ]);
}

function addProjectPara($section, array $parts, $spaceAfter = 28, $firstLineCm = 2.5) {
    $text = '';
    foreach ($parts as $part) {
        $text .= is_array($part) ? ($part[0] ?? '') : $part;
    }

    $text = projectThaiWordWrap($text);
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

function addProjectSignature($section, $receiverName, $receiverPosition) {
    $section->addText('ขอแสดงความนับถือ', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 20,
        'spaceAfter' => 600,
        'lineHeight' => 1.0,
    ]);

    $section->addText('(' . projectClean($receiverName) . ')', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $section->addText(projectClean($receiverPosition), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addProjectFooter($section, $displayDepartmentFull) {
    $footer = $section->addFooter();

    $footer->addText(projectClean($displayDepartmentFull), 'normalFont', [
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

function addProjectActivityPage($phpWord, array $data) {
    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => Converter::cmToTwip(1.5),
        'marginBottom' => Converter::cmToTwip(1.15),
        'marginLeft' => Converter::cmToTwip(3.0),
        'marginRight' => Converter::cmToTwip(2.0),
        'footerHeight' => Converter::cmToTwip(0.85),
    ]);

    addProjectHeader($section, $data['docNo'], $data['displayFaculty'], $data['thaiDocDate']);
    addProjectPairRow($section, 'เรื่อง', $data['projectSubject']);
    addProjectPairRow($section, 'เรียน', $data['projectToPerson'], 20);
    addProjectAttachmentRows($section);

    addProjectPara($section, [
        'ด้วย', $data['displayDepartmentFull'], ' ',
        $data['displayFaculty'], ' ',
        'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี ได้ดำเนินการ ',
        $data['projectMainProject'],
        ' ในกิจกรรมย่อย “', $data['projectSubActivity'], '”',
        ' โดยมีวัตถุประสงค์', $data['projectObjectiveDetail'],
        ' ให้แก่ ', $data['projectTargetGroup'],
        ' จำนวน ', $data['projectParticipantText'],
        ' ณ ', $data['projectActivityPlace'],
        ' รายละเอียดโครงการตามสิ่งที่ส่งมาด้วย ๑',
    ], 28);

    addProjectPara($section, [
        'ในการนี้ ', $data['displayDepartmentFull'], ' ',
        $data['displayFaculty'],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี',
        ' จึงขออนุญาตดำเนินการจัด “', $data['projectSubActivity'], '” ',
        $data['projectActivityPeriod'],
        ' ให้แก่ ', $data['projectTargetGroup'],
        ' ณ ', $data['projectActivityPlace'],
        ' โดยมี ', $data['projectLecturerNames'],
        ' เป็นวิทยากรผู้ดำเนินกิจกรรมในโครงการฯ',
        ' ตามวัน เวลา และสถานที่ดังกล่าวข้างต้น',
    ], 28);

    addProjectPara($section, [
        'จึงเรียนมาเพื่อโปรดพิจารณาอนุญาตให้ดำเนินการจัดโครงการอบรมเชิงปฏิบัติการ จะขอบคุณยิ่ง',
    ], 30);

    addProjectSignature($section, $data['projectReceiverName'], $data['projectReceiverPosition']);
    addProjectFooter($section, $data['displayDepartmentFull']);
}

$docDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$docNo = trim((string)($document['doc_no'] ?? ''));

$faculty = projectField($valueMapByKey, $valueMap, 'faculty', 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม');
$department = projectField($valueMapByKey, $valueMap, 'department', 11, 'เทคโนโลยีสารสนเทศ');

$displayFaculty = projectClean($faculty);
if ($displayFaculty === '') {
    $displayFaculty = 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
}
if (mb_strpos($displayFaculty, 'คณะ') !== 0) {
    $displayFaculty = 'คณะ' . $displayFaculty;
}

$displayDepartment = projectClean($department);
$displayDepartmentFull = (mb_strpos($displayDepartment, 'ภาควิชา') === 0)
    ? $displayDepartment
    : 'ภาควิชา' . $displayDepartment;

$displayFacultyDean = 'คณบดี' . $displayFaculty;
$deanName = '';
$deanPosition = '';
$deanFacultyName = $displayFaculty;
try {
    $deanStmt = $pdo->prepare("
        SELECT dean_name, dean_position, faculty_name
        FROM faculties
        WHERE faculty_name = :faculty
           OR faculty_name = CONCAT('คณะ', :faculty)
           OR REPLACE(faculty_name, 'คณะ', '') = :faculty
        LIMIT 1
    ");
    $deanStmt->execute([':faculty' => trim((string)$displayFaculty)]);
    $deanRow = $deanStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $deanName = trim((string)($deanRow['dean_name'] ?? ''));
    $deanPosition = trim((string)($deanRow['dean_position'] ?? ''));
    $deanFacultyName = trim((string)($deanRow['faculty_name'] ?? $deanFacultyName));
} catch (Throwable $e) {
    $deanName = '';
    $deanPosition = '';
}
if ($deanName === '') {
    $deanName = '................................';
}
if ($deanPosition === '') {
    $deanPosition = 'คณบดี' . ($deanFacultyName !== '' ? $deanFacultyName : $displayFaculty);
}
$displayFacultyDean = $deanPosition;

$projectSubject = projectField($valueMapByKey, $valueMap, 'project_subject', 0, $document['subject'] ?? 'ขออนุญาตเข้าไปจัดกิจกรรมโครงการ');
$projectToPerson = projectField($valueMapByKey, $valueMap, 'project_to_person', 0, '');
$projectActivityPlace = projectField($valueMapByKey, $valueMap, 'project_activity_place', 0, '');
$projectMainProject = projectField($valueMapByKey, $valueMap, 'project_main_project', 0, '');
$projectSubActivity = projectField($valueMapByKey, $valueMap, 'project_sub_activity', 0, '');
$projectObjectiveDetail = projectField($valueMapByKey, $valueMap, 'project_objective_detail', 0, '');
$projectTargetGroup = projectField($valueMapByKey, $valueMap, 'project_target_group', 0, '');
$projectParticipantCount = projectField($valueMapByKey, $valueMap, 'project_participant_count', 0, '');
$projectActivityPeriod = projectField($valueMapByKey, $valueMap, 'project_activity_period', 0, '');
$projectLecturerNames = projectField($valueMapByKey, $valueMap, 'project_lecturer_names', 0, '');
$projectReceiverName = $deanName;
$projectReceiverPosition = $deanPosition;

$projectParticipantText = projectClean($projectParticipantCount);
if ($projectParticipantText !== '' && mb_strpos($projectParticipantText, 'คน') === false) {
    $projectParticipantText .= ' คน';
}

$thaiDocDate = projectThaiDateAny($docDate);

$phpWord = new PhpWord();
setupWordDefaults($phpWord);

$phpWord->addFontStyle('normalFont', [
    'name' => 'TH SarabunPSK',
    'size' => 16,
]);
$phpWord->addFontStyle('boldFont', [
    'name' => 'TH SarabunPSK',
    'size' => 16,
    'bold' => true,
]);
$phpWord->addFontStyle('addressFont', [
    'name' => 'TH SarabunPSK',
    'size' => 15.5,
]);

addProjectActivityPage($phpWord, [
    'docNo' => $docNo,
    'thaiDocDate' => $thaiDocDate,
    'displayFaculty' => $displayFaculty,
    'displayDepartmentFull' => $displayDepartmentFull,
    'projectSubject' => $projectSubject,
    'projectToPerson' => $projectToPerson,
    'projectActivityPlace' => $projectActivityPlace,
    'projectMainProject' => $projectMainProject,
    'projectSubActivity' => $projectSubActivity,
    'projectObjectiveDetail' => $projectObjectiveDetail,
    'projectTargetGroup' => $projectTargetGroup,
    'projectParticipantText' => $projectParticipantText,
    'projectActivityPeriod' => $projectActivityPeriod,
    'projectLecturerNames' => $projectLecturerNames,
    'projectReceiverName' => $projectReceiverName,
    'projectReceiverPosition' => $projectReceiverPosition,
]);

$filename = 'project_activity_' . $docId . '.docx';

$tmpFile = tempnam(sys_get_temp_dir(), 'project_activity_word_');
if ($tmpFile === false) {
    http_response_code(500);
    exit('ไม่สามารถสร้างไฟล์ชั่วคราวสำหรับ Word ได้');
}

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($tmpFile);

// ตรวจว่าเป็นไฟล์ docx จริง ต้องขึ้นต้นด้วย PK ไม่ใช่ HTML/PHP warning
$fh = fopen($tmpFile, 'rb');
$signature = $fh ? fread($fh, 2) : '';
if ($fh) {
    fclose($fh);
}
if ($signature !== 'PK') {
    @unlink($tmpFile);
    http_response_code(500);
    exit('สร้างไฟล์ Word ไม่สำเร็จ');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Content-Length: ' . filesize($tmpFile));

readfile($tmpFile);
@unlink($tmpFile);
exit;