<?php
// Pro_letter/documents/download_word_room_request.php
// ดาวน์โหลด Word (.docx) สำหรับบันทึกข้อความ: ขออนุมัติใช้ห้องพักรับรอง

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

function roomField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '') {
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $default;
}

function roomThaiDigit($text) {
    return strtr((string)$text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}

function roomArabicDigit($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

function roomThaiMonths() {
    return [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
}

function roomThaiDateAny($date) {
    $date = trim(roomArabicDigit((string)$date));
    if ($date === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        $months = roomThaiMonths();
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543);
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        $months = roomThaiMonths();
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543);
    }

    return $date;
}

function roomClean($text) {
    $text = str_replace(["\r", "\n", "\t"], ' ', (string)$text);
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    return trim($text);
}

// คัดรูปแบบการตัดคำ/กระจายคำจาก word_room_request.php
function insertRoomThaiWordBreaks($text) {
    $words = [
        'ตามที่', 'ภาควิชา', 'เทคโนโลยี', 'สารสนเทศ', 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม',
        'คณะเทคโนโลยี', 'และการจัดการ', 'อุตสาหกรรม', 'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ',
        'วิทยาเขต', 'ปราจีนบุรี', 'มีความประสงค์', 'ขออนุมัติ', 'ใช้ห้องพัก', 'ห้องพักรับรอง',
        'สำหรับ', 'ให้แก่', 'ซึ่งเป็น', 'ในระหว่างวันที่', 'นั้น', 'ในการนี้', 'จึงมี',
        'ณ', 'ทั้งนี้เพื่อ', 'รายละเอียด', 'ตามเอกสาร', 'แนบท้าย', 'จึงเรียนมา', 'เพื่อโปรด', 'พิจารณา', 'อนุมัติ'
    ];

    foreach ($words as $word) {
        $text = str_replace($word, $word . "\u{200B}", $text);
    }

    return $text;
}

// คัดรูปแบบย่อหน้าจาก word_room_request.php: lineHeight 1.15 / firstLine 2.5cm / alignment BOTH
function addRoomRequestPara($section, array $lines, $spaceAfter = 80) {
    $run = $section->addTextRun([
        'alignment' => Jc::BOTH,
        'lineHeight' => 1.15,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'indentation' => [
            'firstLine' => Converter::cmToTwip(2.5)
        ],
    ]);

    foreach ($lines as $index => $line) {
        $processedText = insertRoomThaiWordBreaks(cleanWordText($line));
        $run->addText($processedText, 'normalFont');

        if ($index < count($lines) - 1) {
            $run->addTextBreak();
        }
    }
}

function addRoomRequestClosePara($section) {
    $runClose = $section->addTextRun([
        'alignment' => Jc::LEFT,
        'lineHeight' => 1.15,
        'spaceAfter' => 120,
        'indentation' => [
            'firstLine' => Converter::cmToTwip(2.5)
        ],
    ]);
    $runClose->addText('จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ', 'normalFont');
}


function roomNoBorderCell($valign = 'bottom') {
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

function roomDottedBottomCell($valign = 'bottom') {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        // ใช้ค่าจาก download_word_academic_1.php เพื่อให้เส้นปะเห็นชัดทั้งตอนซูมและไม่ซูม
        'borderBottomSize' => 12,
        'borderBottomColor' => '000000',
        'borderBottomStyle' => 'dotted',
        'valign' => $valign,
        'marginTop' => 0,
        'marginBottom' => 20,
        'marginLeft' => 0,
        'marginRight' => 0,
    ];
}

function roomHeaderPara($align = Jc::LEFT, $spaceAfter = 0) {
    return [
        'alignment' => $align,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
    ];
}

function roomSplitHeaderLines($text, $limit = 78) {
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    if ($text === '') {
        return [''];
    }

    $lines = [];
    while (mb_strlen($text, 'UTF-8') > $limit) {
        $cut = mb_substr($text, 0, $limit, 'UTF-8');
        $spacePos = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($spacePos !== false && $spacePos > 25) {
            $lines[] = trim(mb_substr($text, 0, $spacePos, 'UTF-8'));
            $text = trim(mb_substr($text, $spacePos + 1, null, 'UTF-8'));
        } else {
            $lines[] = trim($cut);
            $text = trim(mb_substr($text, $limit, null, 'UTF-8'));
        }
    }
    if ($text !== '') {
        $lines[] = $text;
    }
    return $lines;
}

function addRoomMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText) {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    // คัดรูปแบบหัวข้อจาก download_word_academic_1.php:
    // ครุฑเล็ก, หัว "บันทึกข้อความ", เส้นปะชัด และช่องวันที่ไม่ตกบรรทัด
    $titleTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $titleTable->addRow(Converter::cmToTwip(1.65));

    $left = $titleTable->addCell(Converter::cmToTwip(2.2), roomNoBorderCell('top'));
    if (file_exists($garuda)) {
        $left->addImage($garuda, ['width' => 62, 'alignment' => Jc::LEFT]);
    } else {
        $left->addText('', 'normalFont', roomHeaderPara());
    }

    $middle = $titleTable->addCell(Converter::cmToTwip(11.6), roomNoBorderCell('center'));
    $middle->addText('บันทึกข้อความ', [
        'name' => 'TH SarabunPSK',
        'size' => 29,
        'bold' => true,
    ], [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 180,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $titleTable->addCell(Converter::cmToTwip(2.2), roomNoBorderCell('top'))->addText('', 'normalFont', roomHeaderPara());

    // ส่วนราชการ
    $agencyTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $agencyTable->addRow(null, ['exactHeight' => false]);
    $agencyTable->addCell(Converter::cmToTwip(2.05), roomNoBorderCell())->addText('ส่วนราชการ', 'boldFont', roomHeaderPara());
    $agencyTable->addCell(Converter::cmToTwip(13.95), roomDottedBottomCell())->addText(roomClean($headerText), 'normalFont', roomHeaderPara());

    // ที่ / วันที่
    $dateTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $dateTable->addRow(null, ['exactHeight' => false]);
    $dateTable->addCell(Converter::cmToTwip(0.45), roomNoBorderCell())->addText('ที่', 'boldFont', roomHeaderPara());
    $dateTable->addCell(Converter::cmToTwip(5.25), roomDottedBottomCell())->addText(roomClean($docNo), 'normalFont', roomHeaderPara());
    $dateTable->addCell(Converter::cmToTwip(1.10), roomNoBorderCell())->addText('วันที่', 'boldFont', roomHeaderPara(Jc::CENTER));
    $dateTable->addCell(Converter::cmToTwip(9.20), roomDottedBottomCell())->addText(roomClean($thaiDocDate), 'normalFont', roomHeaderPara());

    // เรื่อง
    $subjectLines = roomSplitHeaderLines($subjectText, 86);
    $subjectTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    foreach ($subjectLines as $i => $line) {
        $subjectTable->addRow(null, ['exactHeight' => false]);
        $subjectTable->addCell(Converter::cmToTwip(0.90), roomNoBorderCell())->addText($i === 0 ? 'เรื่อง' : '', 'boldFont', roomHeaderPara());
        $subjectTable->addCell(Converter::cmToTwip(15.10), roomDottedBottomCell())->addText(roomClean($line), 'normalFont', roomHeaderPara());
    }

    $section->addText('เรียน ' . roomClean($toText), 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 20,
        'spaceAfter' => 120,
        'lineHeight' => 1.0,
    ]);
}

function addRoomRequestWordPage($phpWord, array $data) {
    $section = addSectionPage($phpWord);

    // ใช้หัวข้อ/ครุฑ/เส้นปะตามตัวอย่าง download_word_academic_1.php
    addRoomMemoHeaderFixed(
        $section,
        $data['docNo'],
        $data['thaiDocDate'],
        $data['headerText'],
        $data['subjectText'],
        $data['toText']
    );

    addRoomRequestPara($section, [
        'ตามที่ ' . $data['displayDepartmentFull'] . ' ' . $data['displayFaculty'] .
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี' .
        ' มีความประสงค์ขออนุมัติใช้ห้องพักรับรองสำหรับ ' . ($data['roomRequestText'] ?: '.................................') .
        ' ให้แก่ ' . ($data['guestFullname'] ?: '.................................') .
        ' ซึ่งเป็น ' . ($data['personTypeText'] ?: '.................................') .
        ' ในระหว่างวันที่ ' . ($data['stayDateText'] ?: '.................................') . ' นั้น'
    ]);

    addRoomRequestPara($section, [
        'ในการนี้ ' . $data['displayDepartmentFull'] .
        ' จึงมีความประสงค์ขออนุมัติใช้ห้องพักรับรอง ณ ' . ($data['roomType'] ?: '.................................') .
        ' ให้แก่ ' . ($data['guestFullname'] ?: '.................................') .
        ' ทั้งนี้เพื่อ ' . ($data['reasonText'] ?: '.................................') .
        ' รายละเอียดตามเอกสารแนบท้าย'
    ]);

    addRoomRequestClosePara($section);

    addSignature(
        $section,
        $data['signatureName'],
        $data['signaturePosition']
    );
}

$docDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$ownerName = roomField($valueMapByKey, $valueMap, 'owner_name', 2, '');
$position = roomField($valueMapByKey, $valueMap, 'position', 3, '');

/* ลายเซ็นท้ายเอกสาร: ใช้ชื่อและตำแหน่งของผู้จัดทำเอกสาร */
$signatureName = roomClean($ownerName);
$signaturePosition = roomClean($position);

if ($signatureName === '') {
    $signatureName = '................................';
}

if ($signaturePosition === '') {
    $signaturePosition = '................................';
}

$faculty = roomField($valueMapByKey, $valueMap, 'faculty', 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม');
$department = roomField($valueMapByKey, $valueMap, 'department', 11, 'เทคโนโลยีสารสนเทศ');

$toPerson = roomField($valueMapByKey, $valueMap, 'to_person', 26, 'ประธานคณะกรรมการบ้านพัก มจพ. วิทยาเขตปราจีนบุรี');
$roomRequest = roomField($valueMapByKey, $valueMap, 'room_request', 27, '');
$roomRequestOther = roomField($valueMapByKey, $valueMap, 'room_request_other', 28, '');
$guestFullname = roomField($valueMapByKey, $valueMap, 'guest_fullname', 29, '');
$personType = roomField($valueMapByKey, $valueMap, 'person_type', 30, '');
$personTypeOther = roomField($valueMapByKey, $valueMap, 'person_type_other', 31, '');
$reason = roomField($valueMapByKey, $valueMap, 'reason', 32, '');
$reasonOther = roomField($valueMapByKey, $valueMap, 'reason_other', 33, '');
$dateOption = roomField($valueMapByKey, $valueMap, 'date_option', 34, 'single');
$singleDate = roomField($valueMapByKey, $valueMap, 'single_date', 35, '');
$rangeDate = roomField($valueMapByKey, $valueMap, 'range_date', 36, '');
$roomType = roomField($valueMapByKey, $valueMap, 'room_type', 37, '');

$displayFaculty = roomClean($faculty);
if ($displayFaculty === '') {
    $displayFaculty = 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
}
if (mb_strpos($displayFaculty, 'คณะ') !== 0) {
    $displayFaculty = 'คณะ' . $displayFaculty;
}

$displayDepartment = roomClean($department);
$displayDepartmentFull = (mb_strpos($displayDepartment, 'ภาควิชา') === 0)
    ? $displayDepartment
    : 'ภาควิชา' . $displayDepartment;

$roomRequestText = (trim($roomRequest) === 'อื่น ๆ' && trim($roomRequestOther) !== '') ? $roomRequestOther : $roomRequest;
$personTypeText = (trim($personType) === 'อื่น ๆ' && trim($personTypeOther) !== '') ? $personTypeOther : $personType;
$reasonText = (trim($reason) === 'อื่น ๆ' && trim($reasonOther) !== '') ? $reasonOther : $reason;
$stayDateText = (trim($dateOption) === 'range' && trim($rangeDate) !== '') ? $rangeDate : $singleDate;

$thaiDocDate = roomThaiDateAny($docDate);
$docNo = trim((string)($document['doc_no'] ?? ''));
$headerText = trim((string)($document['header_text'] ?? ''));
$subjectText = 'ขออนุมัติใช้ห้องพักรับรอง' . (trim($roomRequestText) !== '' ? 'สำหรับ' . roomClean($roomRequestText) : '');

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

addRoomRequestWordPage($phpWord, [
    'docNo' => $docNo ?: '',
    'thaiDocDate' => $thaiDocDate,
    'headerText' => $headerText ?: trim($displayFaculty . ' ' . $displayDepartmentFull),
    'subjectText' => $subjectText,
    'toText' => $toPerson ?: 'ประธานคณะกรรมการบ้านพัก มจพ. วิทยาเขตปราจีนบุรี',
    'displayFaculty' => $displayFaculty,
    'displayDepartmentFull' => $displayDepartmentFull,
    'roomRequestText' => roomClean($roomRequestText),
    'guestFullname' => roomClean($guestFullname),
    'personTypeText' => roomClean($personTypeText),
    'stayDateText' => roomClean($stayDateText),
    'roomType' => roomClean($roomType),
    'reasonText' => roomClean($reasonText),
    'signatureName' => $signatureName,
    'signaturePosition' => $signaturePosition,
]);

$filename = 'room_request_' . $docId . '.docx';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;