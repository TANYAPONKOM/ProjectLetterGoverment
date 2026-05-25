<?php
// Pro_letter/documents/download_word_speaker.php
// ดาวน์โหลด Word (.docx) สำหรับบันทึกข้อความ: ขออนุมัติตัวบุคคลเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ

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

function speakerField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '') {
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $default;
}

function speakerThaiDigit($text) {
    return strtr((string)$text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}

function speakerArabicDigit($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

function speakerThaiMonths() {
    return [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
}

function speakerThaiDateAny($date) {
    $date = trim(speakerArabicDigit((string)$date));
    if ($date === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        $months = speakerThaiMonths();
        return speakerThaiDigit($d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543));
    }

    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        $months = speakerThaiMonths();
        return speakerThaiDigit($d . ' ' . ($months[$mo] ?? '') . ' ' . ($y > 2400 ? $y : $y + 543));
    }

    return speakerThaiDigit($date);
}

function speakerClean($text) {
    $text = str_replace(["\r", "\n", "\t"], ' ', (string)$text);
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    return trim($text);
}

// รูปแบบตัดคำตาม documents/word_templates/word_speaker.php
function insertSpeakerThaiWordBreaksForDownload($text) {
    $words = [
        'ตามที่', 'ข้าพเจ้า', 'พนักงาน', 'มหาวิทยาลัย', 'สังกัด', 'ภาควิชา',
        'เทคโนโลยี', 'สารสนเทศ', 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม', 'คณะเทคโนโลยี',
        'และการจัดการ', 'อุตสาหกรรม', 'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ',
        'วิทยาเขต', 'ปราจีนบุรี', 'ได้รับ', 'อนุมัติ', 'ตัวบุคคล', 'ให้เข้าร่วม',
        'นำเสนอ', 'ผลงานวิจัย', 'ประชุมเรื่อง', 'ในหัวข้อ', 'ซึ่งจัดขึ้นที่',
        'เข้าร่วม', 'รูปแบบ', 'ออนไลน์', 'ในระหว่างวันที่', 'โดยเอกสาร',
        'งานประชุม', 'วิชาการ', 'จะถูก', 'ตีพิมพ์', 'อยู่ใน', 'ฐานข้อมูล',
        'Scopus', 'นั้น', 'การนี้', 'จึงมี', 'ความประสงค์', 'ขออนุมัติ',
        'เดินทาง', 'เพื่อไป', 'ระดับนานาชาติ', 'รวมเวลา', 'ตามวัน', 'เวลา',
        'และสถานที่', 'ดังกล่าว', 'เป็นประโยชน์', 'ต่อการ', 'พัฒนา', 'การเรียน',
        'การสอน', 'และสร้าง', 'ชื่อเสียง', 'ให้กับ', 'โดยขอใช้', 'งบจัดสรร',
        'ให้หน่วยงาน', 'ประจำปี', 'งบประมาณ', 'พ.ศ.', 'ในส่วนของ', 'แผนงาน',
        'จัดการศึกษา', 'ระดับอุดมศึกษา', 'หมวด', 'ค่าใช้สอย', 'รายละเอียด',
        'ตามเอกสารแนบ', 'จึงเรียนมา', 'เพื่อโปรด', 'พิจารณา',
        'อ้างถึง', 'หนังสือจาก', 'เลขที่', 'ลงวันที่', 'หลักสูตร',
        'ไปร่วมเป็น', 'วิทยากร', 'บรรยาย', 'โครงการอบรม', 'เชิงปฏิบัติการ',
        'รวมระยะเวลาในการเดินทาง', 'ณ'
    ];

    foreach ($words as $word) {
        $text = str_replace($word, $word . "\u{200B}", $text);
    }

    return $text;
}

function addSpeakerDownloadManualPara($section, array $lines, $spaceAfter = 80) {
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
        $cleanLine = cleanWordText($line);

        if (strpos($cleanLine, '(รวมระยะเวลาในการเดินทาง)') !== false) {
            $parts = explode('(รวมระยะเวลาในการเดินทาง)', $cleanLine, 2);
            $run->addText(insertSpeakerThaiWordBreaksForDownload($parts[0]), 'normalFont');
            $run->addText('(รวมระยะเวลาในการเดินทาง)', 'normalFont');
            $run->addText(insertSpeakerThaiWordBreaksForDownload($parts[1] ?? ''), 'normalFont');
        } else {
            $run->addText(insertSpeakerThaiWordBreaksForDownload($cleanLine), 'normalFont');
        }

        if ($index < count($lines) - 1) {
            $run->addTextBreak();
        }
    }
}

function addSpeakerDownloadClosePara($section) {
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


function addSpeakerSignatureFixed($section, $name, $position) {
    // แก้เฉพาะลายเซ็น: ขยับบล็อกลายเซ็นไปทางขวา และยังคงให้คำว่า "สารสนเทศ" อยู่บรรทัดเดียวกัน
    $safePosition = trim(str_replace(["\r", "\n", "\t", "\u{200B}", "\u{2060}"], ' ', (string)$position));
    $safePosition = preg_replace('/[ ]{2,}/u', ' ', $safePosition);

    // เว้นระยะบนเท่าเดิมก่อนวางบล็อกลายเซ็น
    $section->addText('', 'normalFont', [
        'spaceBefore' => 520,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    // ใช้ตารางไร้เส้นเพื่อเลื่อนบล็อกไปทางขวา โดยให้ช่องข้อความกว้างพอ
    // ช่องซ้าย 6.60 ซม. + ช่องลายเซ็น 9.40 ซม. = 16.00 ซม.
    // ถ้าต้องการขยับอีก ให้เพิ่ม/ลด 6.60 แต่ห้ามลดช่องลายเซ็นต่ำกว่า 9.00 ซม.
    $sigTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(16.0),
    ]);

    $sigTable->addRow(null, ['exactHeight' => false]);
    $sigTable->addCell(\PhpOffice\PhpWord\Shared\Converter::cmToTwip(6.60), speakerNoBorderCell('top'))
        ->addText('', 'normalFont', ['spaceAfter' => 0]);

    $sigCell = $sigTable->addCell(\PhpOffice\PhpWord\Shared\Converter::cmToTwip(9.40), speakerNoBorderCell('top'));

    $sigCell->addText('(' . speakerClean($name) . ')', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $sigCell->addText($safePosition, [
        'name' => 'TH SarabunPSK',
        'size' => 15,
    ], [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function speakerNoBorderCell($valign = 'bottom') {
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

function speakerDottedBottomCell($valign = 'bottom') {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
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

function speakerHeaderPara($align = Jc::LEFT, $spaceAfter = 0) {
    return [
        'alignment' => $align,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
    ];
}

function speakerKeepTogetherWords($text) {
    $text = (string)$text;
    $joiner = "\u{2060}";

    // กัน Word ตัดคำไทยเป็นตัวอักษรเดี่ยว เช่น คน -> ค / น และ สารสนเทศ -> ... / ศ
    $keepWords = [
        'การเข้าสังคมของคน',
        'ของคน',
        'สารสนเทศ',
    ];

    foreach ($keepWords as $word) {
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if (!$chars) {
            continue;
        }
        $text = str_replace($word, implode($joiner, $chars), $text);
    }

    return $text;
}

function speakerSplitHeaderLines($text, $limit = 78) {
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    if ($text === '') {
        return [''];
    }

    // เคสหัวเรื่องที่เคยทำให้คำว่า “คน” ถูกตัดจนตัว “น” ตกบรรทัด
    // ให้ตัดขึ้นบรรทัดใหม่ก่อนวลีนี้เลย เพื่อไม่ให้ Word ไปตัดกลางคำเอง
    $safePhrase = 'การเข้าสังคมของคน';
    $safePos = mb_strpos($text, $safePhrase, 0, 'UTF-8');
    if ($safePos !== false && $safePos > 0) {
        $before = trim(mb_substr($text, 0, $safePos, 'UTF-8'));
        $after = trim(mb_substr($text, $safePos, null, 'UTF-8'));
        return array_values(array_filter([$before, $after], static function($line) {
            return trim((string)$line) !== '';
        }));
    }

    $lines = [];
    while (mb_strlen($text, 'UTF-8') > $limit) {
        $cut = mb_substr($text, 0, $limit, 'UTF-8');
        $spacePos = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($spacePos !== false && $spacePos > 25) {
            $lines[] = trim(mb_substr($text, 0, $spacePos, 'UTF-8'));
            $text = trim(mb_substr($text, $spacePos + 1, null, 'UTF-8'));
        } else {
            // ไม่ตัดให้เหลือตัวไทยเดี่ยวท้ายบรรทัด เช่น ค/น หรือ ...เท/ศ
            $nextChar = mb_substr($text, $limit, 1, 'UTF-8');
            $prevChar = mb_substr($text, $limit - 1, 1, 'UTF-8');
            if (preg_match('/[ก-ฮ]/u', $prevChar) && preg_match('/[ก-ฮ]/u', $nextChar)) {
                $limit = max(40, $limit - 2);
                $cut = mb_substr($text, 0, $limit, 'UTF-8');
            }
            $lines[] = trim($cut);
            $text = trim(mb_substr($text, mb_strlen($cut, 'UTF-8'), null, 'UTF-8'));
        }
    }
    if ($text !== '') {
        $lines[] = $text;
    }
    return $lines;
}

function addSpeakerMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText) {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    // คัดรูปแบบส่วนหัวจาก download_word_academic_1.php เพื่อให้ครุฑและเส้นปะตรงกัน
    $titleTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $titleTable->addRow(Converter::cmToTwip(1.65));

    $left = $titleTable->addCell(Converter::cmToTwip(2.2), speakerNoBorderCell('top'));
    if (file_exists($garuda)) {
        $left->addImage($garuda, ['width' => 62, 'alignment' => Jc::LEFT]);
    } else {
        $left->addText('', 'normalFont', speakerHeaderPara());
    }

    $middle = $titleTable->addCell(Converter::cmToTwip(11.6), speakerNoBorderCell('center'));
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
    $titleTable->addCell(Converter::cmToTwip(2.2), speakerNoBorderCell('top'))->addText('', 'normalFont', speakerHeaderPara());

    $agencyTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $agencyTable->addRow(null, ['exactHeight' => false]);
    $agencyTable->addCell(Converter::cmToTwip(2.05), speakerNoBorderCell())->addText('ส่วนราชการ', 'boldFont', speakerHeaderPara());
    $agencyTable->addCell(Converter::cmToTwip(13.95), speakerDottedBottomCell())->addText(speakerClean($headerText), 'normalFont', speakerHeaderPara());

    // แยกช่องวันที่ให้กว้างเหมือน academic_1 เพื่อไม่ให้วันที่ตกบรรทัด และให้เส้นปะต่อกับช่องวันที่
    $dateTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $dateTable->addRow(null, ['exactHeight' => false]);
    $dateTable->addCell(Converter::cmToTwip(0.45), speakerNoBorderCell())->addText('ที่', 'boldFont', speakerHeaderPara());
    $dateTable->addCell(Converter::cmToTwip(5.25), speakerDottedBottomCell())->addText(speakerClean($docNo), 'normalFont', speakerHeaderPara());
    $dateTable->addCell(Converter::cmToTwip(1.10), speakerNoBorderCell())->addText('วันที่', 'boldFont', speakerHeaderPara(Jc::CENTER));
    $dateTable->addCell(Converter::cmToTwip(9.20), speakerDottedBottomCell())->addText(speakerClean($thaiDocDate), 'normalFont', speakerHeaderPara());

    $subjectLines = speakerSplitHeaderLines($subjectText, 86);
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
        $subjectTable->addCell(Converter::cmToTwip(0.90), speakerNoBorderCell())->addText($i === 0 ? 'เรื่อง' : '', 'boldFont', speakerHeaderPara());
        $subjectTable->addCell(Converter::cmToTwip(15.10), speakerDottedBottomCell())->addText(speakerKeepTogetherWords(speakerClean($line)), 'normalFont', speakerHeaderPara());
    }

    $section->addText('เรียน ' . speakerKeepTogetherWords(speakerClean($toText)), 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 20,
        'spaceAfter' => 120,
        'lineHeight' => 1.0,
    ]);
}

function buildSpeakerDownloadWord($phpWord, array $data) {
    $section = addSectionPage($phpWord);

    addSpeakerMemoHeaderFixed(
        $section,
        $data['docNo'],
        $data['thaiDocDate'],
        $data['headerText'],
        $data['subjectText'],
        $data['toText']
    );

    addSpeakerDownloadManualPara($section, [
        'อ้างถึง หนังสือจาก ' . $data['referenceOrg'] .
        ' เลขที่ ' . $data['referenceNo'] .
        ' ลงวันที่ ' . $data['referenceDateText'] .
        ' เรื่อง ' . $data['projectTitle'] .
        ' หลักสูตร ' . $data['courseName'] .
        ' ในระหว่างวันที่ ' . $data['eventRange'] .
        ' ณ ' . $data['eventPlace'] . ' นั้น'
    ]);

    addSpeakerDownloadManualPara($section, [
        'ในการนี้ ข้าพเจ้า ' . $data['ownerName'] .
        ' สังกัด' . $data['displayDepartmentFull'] . ' ' . $data['displayFaculty'] .
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี ข้าพเจ้ามีความประสงค์ ' .
        $data['intentionText'] .
        ' หลักสูตร ' . $data['courseName'] .
        ' ระหว่างวันที่ ' . $data['travelPeriod'] .
        ' (รวมระยะเวลาในการเดินทาง) รายละเอียดตามเอกสารแนบ'
    ]);

    addSpeakerDownloadClosePara($section);

    addSpeakerSignatureFixed(
        $section,
        $data['ownerName'],
        $data['position']
    );
}

$docDate = trim((string)($valueMap[1] ?? ''));
if ($docDate === '') {
    $docDate = trim((string)($document['doc_date'] ?? ''));
}

$ownerName = speakerField($valueMapByKey, $valueMap, 'owner_name', 2, '');
$position = speakerField($valueMapByKey, $valueMap, 'position', 3, '');
$joinType = speakerField($valueMapByKey, $valueMap, 'join_type', 4, '');

$projectTitle = speakerField($valueMapByKey, $valueMap, 'project_title', 5, '');
$joinDates = speakerField($valueMapByKey, $valueMap, 'intern_period', 6, '');
$location = speakerField($valueMapByKey, $valueMap, 'location', 7, '');
$amountStr = speakerField($valueMapByKey, $valueMap, 'total_cost', 8, '');
$travelPeriodOld = speakerField($valueMapByKey, $valueMap, 'vehicle', 9, '');
$faculty = speakerField($valueMapByKey, $valueMap, 'faculty', 10, '');
$department = speakerField($valueMapByKey, $valueMap, 'department', 11, '');

$referenceOrg = speakerField($valueMapByKey, $valueMap, 'reference_org', 18, '');
$referenceNo = speakerField($valueMapByKey, $valueMap, 'reference_no', 19, '');
$refDate = speakerField($valueMapByKey, $valueMap, 'reference_date', 21, $docDate);
$courseName = speakerField($valueMapByKey, $valueMap, 'course_name', 23, '');
$travelPeriod = speakerField($valueMapByKey, $valueMap, 'travel_period', 24, $travelPeriodOld);
$intentionText = speakerField($valueMapByKey, $valueMap, 'intention_text', 25, 'ขออนุมัติเดินทางไปร่วมเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ');

$displayFaculty = trim($faculty) !== '' ? trim($faculty) : 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
$displayDepartment = trim($department) !== '' ? trim($department) : 'เทคโนโลยีสารสนเทศ';
$displayDepartmentFull = (mb_strpos($displayDepartment, 'ภาควิชา') === 0)
    ? $displayDepartment
    : 'ภาควิชา' . $displayDepartment;

$displayFacultyDean = 'คณบดี' . $displayFaculty;

$headerText = trim((string)($document['header_text'] ?? ''));
$docNo = trim((string)($document['doc_no'] ?? ''));
$subject = trim((string)($document['subject'] ?? ''));

$thaiDocDate = speakerThaiDateAny($docDate);
$referenceDateText = speakerThaiDateAny($refDate) ?: $refDate;

$subjectText = $subject !== ''
    ? $subject
    : 'ขออนุมัติตัวบุคคลเป็นวิทยากรบรรยายในโครงการอบรมเชิงปฏิบัติการ';

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

buildSpeakerDownloadWord($phpWord, [
    'docNo' => $docNo ?: 'ทส.486/2568',
    'thaiDocDate' => $thaiDocDate ?: speakerThaiDateAny(date('Y-m-d')),
    'headerText' => $headerText ?: 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม ภาคเทคโนโลยีสารสนเทศ โทร. 7064.',
    'subjectText' => $subjectText,
    'toText' => $displayFacultyDean,
    'referenceOrg' => $referenceOrg !== '' ? $referenceOrg : '................................',
    'referenceNo' => $referenceNo !== '' ? $referenceNo : '................................',
    'referenceDateText' => $referenceDateText !== '' ? $referenceDateText : '................................',
    'projectTitle' => $projectTitle !== '' ? $projectTitle : '................................',
    'courseName' => $courseName !== '' ? $courseName : '................................',
    'eventRange' => $joinDates !== '' ? $joinDates : '................................',
    'eventPlace' => $location !== '' ? $location : '................................',
    'ownerName' => $ownerName !== '' ? $ownerName : '................................',
    'position' => $position,
    'displayDepartmentFull' => $displayDepartmentFull,
    'displayFaculty' => $displayFaculty,
    'intentionText' => $intentionText,
    'travelPeriod' => $travelPeriod !== '' ? $travelPeriod : '................................',
]);

$filename = 'speaker_' . $docId . '.docx';

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
