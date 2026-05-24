<?php
// Pro_letter/documents/download_word_invite_speaker.php
// ดาวน์โหลด Word (.docx) สำหรับหนังสือเรียนเชิญวิทยากร

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

function inviteThaiMonths() {
    return [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
}

function inviteThaiDateFromParts($year, $month, $day, $withWeekday = false) {
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

    $months = inviteThaiMonths();
    $dateText = wordThaiDigit($day) . ' ' . $months[$month] . ' ' . wordThaiDigit($thaiYear);

    if ($withWeekday) {
        $weekdays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        $w = (int)date('w', strtotime(sprintf('%04d-%02d-%02d', $christYear, $month, $day)));
        return 'วัน' . $weekdays[$w] . 'ที่ ' . $dateText;
    }

    return $dateText;
}

function inviteThaiDateAny($rawDate, $withWeekday = false) {
    $rawDate = trim((string)$rawDate);
    if ($rawDate === '') {
        return '';
    }
    if ($withWeekday && preg_match('/^วัน/u', $rawDate)) {
        return wordThaiDigit($rawDate);
    }

    $date = wordArabicDigit($rawDate);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        return inviteThaiDateFromParts($m[1], $m[2], $m[3], $withWeekday);
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) {
        return inviteThaiDateFromParts($m[3], $m[2], $m[1], $withWeekday);
    }

    $months = inviteThaiMonths();
    $monthRegex = implode('|', array_map('preg_quote', $months));
    if (preg_match('/(\d{1,2})\s+(' . $monthRegex . ')\s+(\d{4})/u', $date, $m)) {
        $monthNumber = array_search($m[2], $months, true);
        if ($monthNumber !== false) {
            return inviteThaiDateFromParts($m[3], $monthNumber, $m[1], $withWeekday);
        }
    }

    return wordThaiDigit($rawDate);
}

function inviteFormatThaiTimeRange($timeText) {
    $timeText = trim((string)$timeText);
    if ($timeText === '') {
        return '';
    }
    $timeText = str_replace(':', '.', $timeText);
    $timeText = preg_replace('/\s*-\s*/', ' - ', $timeText);
    return wordThaiDigit($timeText);
}

function inviteClean($text) {
    return cleanWordText(str_replace(["\r", "\n"], ' ', (string)$text));
}

function inviteInlineText($text) {
    $text = inviteClean($text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function inviteThaiWordWrap($text) {
    $text = inviteInlineText($text);
    if ($text === '') {
        return '';
    }

    // ใส่ zero-width space เพื่อบอก Word ว่า “ภาษาไทยตัดบรรทัดตรงนี้ได้”
    // ไม่แสดงผลในเอกสาร แต่ช่วยไม่ให้ Word เอาข้อความไทยยาว ๆ ไปตัดมั่วแล้วเหลือพื้นที่ว่าง
    $zwsp = "\u{200B}";

    // จุดตัดระหว่างตัวอักษรไทยกับตัวอักษรไทย
    $text = preg_replace('/(?<=[\p{Thai}])(?=[\p{Thai}])/u', $zwsp, $text);

    // เพิ่มจุดตัดหลังเครื่องหมาย/วงเล็บ/ทับ เผื่อมีข้อความยาวติดกัน
    $text = preg_replace('/([\/\-–—,;:()（）])/u', '$1' . $zwsp, $text);

    return $text;
}

function addInviteTextRunPara($section, array $parts, $spaceAfter = 35) {
    $text = '';

    foreach ($parts as $part) {
        if (is_array($part)) {
            $text .= $part[0] ?? '';
        } else {
            $text .= $part;
        }
    }

    $text = inviteThaiWordWrap($text);
    if ($text === '') {
        return;
    }

    $section->addText($text, 'normalFont', [
        // ใช้กระจายแบบไทย แต่ต้องใส่จุดตัดคำก่อน ไม่งั้น Word จะกินพื้นที่/เว้นช่องว่างแปลก
        'alignment' => 'thaiDistribute',
        'lineHeight' => 0.92,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'indentation' => ['firstLine' => Converter::cmToTwip(2.5)],
    ]);
}

function inviteNoBorderCell($valign = 'top') {
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

function addInvitePairRow($section, $label, $value) {
    $label = inviteInlineText($label);
    $value = inviteThaiWordWrap($value);

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => 9072,
    ]);

    $table->addRow();

    // ช่องหัวข้อ เช่น เรื่อง / เรียน ให้แคบลงนิด เพื่อเพิ่มพื้นที่ข้อความด้านขวา
    $table->addCell(Converter::cmToTwip(1.0), inviteNoBorderCell())->addText($label, 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    // ช่องข้อความด้านขวา เพิ่มความกว้างจากเดิม เพื่อไม่ให้ขึ้นบรรทัดใหม่เร็วเกินไป
    $table->addCell(Converter::cmToTwip(15.0), inviteNoBorderCell())->addText($value, 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addInviteAttachmentRows($section) {
    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => 9072,
    ]);

    $rows = [
        ['สิ่งที่ส่งมาด้วย', '๑. รายละเอียดโครงการ', 'จำนวน ๑ ชุด'],
        ['', '๒. แบบตอบรับการเป็นวิทยากร', 'จำนวน ๑ ฉบับ'],
    ];

    foreach ($rows as $r) {
        $table->addRow();

        $table->addCell(Converter::cmToTwip(2.2), inviteNoBorderCell())->addText($r[0], 'normalFont', [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);

        $table->addCell(Converter::cmToTwip(8.3), inviteNoBorderCell())->addText($r[1], 'normalFont', [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
            'indentation' => ['left' => Converter::cmToTwip(0.25)],
        ]);

        $table->addCell(Converter::cmToTwip(5.5), inviteNoBorderCell())->addText($r[2], 'normalFont', [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);
    }

    // ลดช่องว่างก่อนขึ้นย่อหน้า "ด้วยภาควิชา..."
    $section->addText('', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addInviteHeader($section, $displayFaculty, $thaiDocDate) {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => 9072,
    ]);

    $table->addRow(Converter::cmToTwip(3.1));

    $left = $table->addCell(Converter::cmToTwip(4.95), inviteNoBorderCell('top'));
    $left->addText('', 'normalFont', ['spaceAfter' => 800, 'lineHeight' => 1.0]);
    $left->addText('ที่ อว ๗๑๒๐/', 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);

    $middle = $table->addCell(Converter::cmToTwip(3.55), inviteNoBorderCell('top'));
    if (file_exists($garuda)) {
        $middle->addImage($garuda, [
            'width' => 80,
            'alignment' => Jc::CENTER,
        ]);
    } else {
        $middle->addText('');
    }

    $right = $table->addCell(Converter::cmToTwip(7.50), inviteNoBorderCell('top'));
    $right->addText('', 'normalFont', ['spaceAfter' => 720, 'lineHeight' => 0.95]);
    $right->addText(inviteClean($displayFaculty), 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);

    $section->addText(inviteClean($thaiDocDate ?: '๗ ตุลาคม ๒๕๖๘'), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 80,
        'spaceAfter' => 160,
        'lineHeight' => 1.0,
        'indentation' => ['left' => Converter::cmToTwip(1.0)],
    ]);
}

function addInviteSignature($section, $displayFacultyDean) {
    $section->addText('จึงเรียนมาเพื่อโปรดพิจารณาให้ความอนุเคราะห์ จะขอบคุณยิ่ง', 'normalFont', [
        'indentation' => ['firstLine' => Converter::cmToTwip(2.5)],
        'lineHeight' => 1.38,
        'spaceAfter' => 220,
    ]);

    $section->addText('ขอแสดงความนับถือ', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 500,
        'lineHeight' => 1.0,
    ]);

    $section->addText('(ผู้ช่วยศาสตราจารย์ ดร.กฤษฎากร บุดดาจันทร์)', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $section->addText(inviteClean($displayFacultyDean), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addInviteFooter($section, $displayDepartmentFull) {
    /*
     * อย่าใส่ข้อมูลติดต่อเป็นเนื้อหา body ต่อท้ายเอกสาร
     * เพราะถ้าเนื้อหายาว Word จะดัน 3 บรรทัดนี้ไปหน้าใหม่
     * ให้ใช้ footer ของ Word แทน เพื่อให้เกาะอยู่ท้ายหน้ากระดาษ
     */
    $footer = $section->addFooter();

    $footer->addText(inviteClean($displayDepartmentFull), 'normalFont', [
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

function addInviteSpeakerPage($phpWord, array $data) {
    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => Converter::cmToTwip(2),
        'marginBottom' => Converter::cmToTwip(1.15),
        'marginLeft' => Converter::cmToTwip(3.0),
        'marginRight' => Converter::cmToTwip(2.0),
        // พื้นที่ footer สำหรับ ภาควิชา/โทรศัพท์/อีเมล ไม่ให้โดนดันไปหน้าใหม่
        'footerHeight' => Converter::cmToTwip(0.85),
    ]);

    addInviteHeader($section, $data['displayFaculty'], $data['thaiDocDate']);
    addInvitePairRow($section, 'เรื่อง', $data['displaySubject']);
    addInvitePairRow($section, 'เรียน', $data['displayToPerson']);
    addInviteAttachmentRows($section);

    addInviteTextRunPara($section, [
        'ด้วย', $data['displayDepartmentFull'], ' ', $data['displayFaculty'],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี',
        ' ได้ดำเนินการจัด', $data['displayProjectTitle'], ' ใน', $data['displayEventDate'],
        ' เวลา ', $data['displayEventTime'], ' น. ณ ', $data['displayLocation'],
        ' โดยมีวัตถุประสงค์', $data['displayObjective'],
        ' รายละเอียดโครงการตามสิ่งที่ส่งมาด้วย ๑',
    ]);

    addInviteTextRunPara($section, [
        $data['displayDepartmentFull'], ' ', $data['displayInviteStatement'],
        ' จึงขอเรียนเชิญท่านเป็นวิทยากรบรรยายเรื่องดังกล่าว ตามวัน เวลา และสถานที่ข้างต้น',
    ]);

    addInviteSignature($section, $data['displayFacultyDean']);
    addInviteFooter($section, $data['displayDepartmentFull']);
}

$docDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$projectTitle = $valueMap[5] ?? '';
$eventDate = $valueMap[6] ?? ($valueMap[16] ?? '');
$location = $valueMap[7] ?? '';
$faculty = $valueMap[10] ?? '';
$department = $valueMap[11] ?? '';
$docSubject = $valueMap[14] ?? ($document['subject'] ?? '');
$objective = $valueMap[25] ?? '';
$toPerson = $valueMap[26] ?? '';
$inviteStatement = $valueMapByKey['invite_statement'] ?? '';
$eventTime = $valueMapByKey['event_time'] ?? '';

$thaiDocDate = inviteThaiDateAny($docDate, false);
$thaiEventDate = inviteThaiDateAny($eventDate, true);
$thaiEventTime = inviteFormatThaiTimeRange($eventTime);

$displayFaculty = trim($faculty) !== '' ? trim($faculty) : 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
$displayDepartment = trim($department) !== '' ? trim($department) : 'เทคโนโลยีสารสนเทศ';
$displayDepartmentFull = 'ภาควิชา' . $displayDepartment;
$displayFacultyDean = 'คณบดี' . $displayFaculty;
$displaySubject = trim($docSubject) !== '' ? $docSubject : 'ขอเรียนเชิญเป็นวิทยากรบรรยาย';
$displayToPerson = trim($toPerson) !== '' ? $toPerson : 'คุณ................................................';
$displayProjectTitle = trim($projectTitle) !== '' ? $projectTitle : '................................................';
$displayInviteStatement = trim($inviteStatement) !== ''
    ? $inviteStatement
    : 'เห็นว่าท่านเป็นผู้มีความเชี่ยวชาญและมีประสบการณ์สูง ในสาขาวิชาชีพดังกล่าว ซึ่งจะเป็นประโยชน์แก่นักศึกษาผู้เข้าร่วมโครงการเป็นอย่างดี';
$displayObjective = trim($objective) !== '' ? $objective : '................................................';
$displayEventDate = trim($thaiEventDate) !== '' ? $thaiEventDate : 'วันที่................................................';
$displayEventTime = trim($thaiEventTime) !== '' ? $thaiEventTime : '................';
$displayLocation = trim($location) !== '' ? $location : '................................................';

$phpWord = new PhpWord();
setupWordDefaults($phpWord);
$phpWord->addFontStyle('addressFont', [
    'name' => 'TH SarabunPSK',
    'size' => 15.5,
]);

addInviteSpeakerPage($phpWord, [
    'displayFaculty' => $displayFaculty,
    'thaiDocDate' => $thaiDocDate,
    'displaySubject' => $displaySubject,
    'displayToPerson' => $displayToPerson,
    'displayDepartmentFull' => $displayDepartmentFull,
    'displayFaculty' => $displayFaculty,
    'displayProjectTitle' => $displayProjectTitle,
    'displayEventDate' => $displayEventDate,
    'displayEventTime' => $displayEventTime,
    'displayLocation' => $displayLocation,
    'displayObjective' => $displayObjective,
    'displayInviteStatement' => $displayInviteStatement,
    'displayFacultyDean' => $displayFacultyDean,
]);

$filename = 'invite_speaker_' . $docId . '.docx';
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