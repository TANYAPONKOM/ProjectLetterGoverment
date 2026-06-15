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
require_once __DIR__ . '/../includes/thai_word_breaks.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$userId = (int) $_SESSION['user_id'];
$roleId = (int) ($_SESSION['role_id'] ?? 0);
$isAdmin = ($roleId === 1);
$isOfficer = ($roleId === 2);

$pdo = db();
$docId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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

if (!$isAdmin && !$isOfficer && (int) $document['owner_id'] !== $userId) {
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
    $fid = (int) ($row['field_id'] ?? 0);
    $val = (string) ($row['value_text'] ?? '');
    if ($fid > 0) {
        $valueMap[$fid] = $val;
    }
    $key = trim((string) ($row['field_key'] ?? ''));
    if ($key !== '') {
        $valueMapByKey[$key] = $val;
    }
}

function roomField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '')
{
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string) ($v ?? ''));
    return $v !== '' ? $v : $default;
}

function roomThaiDigit($text)
{
    return strtr((string) $text, [
        '0' => '๐',
        '1' => '๑',
        '2' => '๒',
        '3' => '๓',
        '4' => '๔',
        '5' => '๕',
        '6' => '๖',
        '7' => '๗',
        '8' => '๘',
        '9' => '๙',
    ]);
}

function roomArabicDigit($text)
{
    return strtr((string) $text, [
        '๐' => '0',
        '๑' => '1',
        '๒' => '2',
        '๓' => '3',
        '๔' => '4',
        '๕' => '5',
        '๖' => '6',
        '๗' => '7',
        '๘' => '8',
        '๙' => '9',
    ]);
}

function roomThaiMonths()
{
    return [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม',
    ];
}

function roomThaiDateAny($date)
{
    $date = trim(roomArabicDigit((string) $date));
    if ($date === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        $months = roomThaiMonths();
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543);
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
        $d = (int) $m[1];
        $mo = (int) $m[2];
        $y = (int) $m[3];
        $months = roomThaiMonths();
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543);
    }

    return $date;
}

function roomThaiDateTextAny($text)
{
    $text = roomArabicDigit((string) $text);
    $text = preg_replace_callback('/(\d{4})-(\d{1,2})-(\d{1,2})/u', function ($m) {
        return roomThaiDateAny($m[1] . '-' . $m[2] . '-' . $m[3]);
    }, $text);
    $text = preg_replace_callback('/(\d{1,2})\/(\d{1,2})\/(\d{4})/u', function ($m) {
        return roomThaiDateAny($m[1] . '/' . $m[2] . '/' . $m[3]);
    }, $text);
    return $text;
}

function roomClean($text)
{
    $text = str_replace(["\r", "\n", "\t"], ' ', roomArabicDigit((string) $text));
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    return trim($text);
}

function roomRemoveGovSpaces($text)
{
    return preg_replace('/\s+/u', '', roomClean($text));
}

function roomGovHeaderFontSize($text)
{
    $len = mb_strlen(roomClean($text), 'UTF-8');
    if ($len > 82) {
        return 14;
    }
    if ($len > 72) {
        return 15;
    }
    return 16;
}

// คัดรูปแบบการตัดคำ/กระจายคำจาก word_room_request.php
function insertRoomThaiWordBreaks($text)
{
    $text = roomClean((string) $text);
    $text = str_replace(["\r", "\n", "\t"], ' ', $text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    if (function_exists('insertThaiWordBreaksForMemoBody')) {
        return insertThaiWordBreaksForMemoBody($text);
    }

    return $text;
}

// คัดรูปแบบย่อหน้าจาก word_room_request.php: lineHeight 1.15 / firstLine 2.5cm / alignment BOTH
function addRoomRequestPara($section, array $lines, $spaceAfter = 80)
{
    $fullText = '';

    foreach ($lines as $line) {
        $fullText .= ' ' . roomClean($line);
    }

    $fullText = trim(preg_replace('/\s+/u', ' ', $fullText));

    if ($fullText === '') {
        return;
    }

    $section->addText(
        insertRoomThaiWordBreaks($fullText),
        'normalFont',
        [
            'alignment' => 'thaiDistribute',
            'lineHeight' => 0.94,
            'spaceBefore' => 0,
            'spaceAfter' => $spaceAfter,
            'indentation' => [
                'firstLine' => Converter::cmToTwip(2.5),
            ],
        ]
    );
}

function addRoomRequestClosePara($section)
{
    $runClose = $section->addTextRun([
        'alignment' => Jc::LEFT,
        'lineHeight' => 1.15,
        'spaceAfter' => 120,
        'indentation' => [
            'firstLine' => Converter::cmToTwip(2.5)
        ],
    ]);
    $runClose->addText(roomClean('จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ'), 'normalFont');
}


function addRoomOwnerSignature($section, string $signatureName, string $signaturePosition)
{
    $leftWidth = 9.00;
    $signWidth = 7.20;

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip($leftWidth + $signWidth),
    ]);
    $table->addRow(null, ['exactHeight' => false]);
    $table->addCell(Converter::cmToTwip($leftWidth), roomNoBorderCell('top'))->addText('', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $cell = $table->addCell(Converter::cmToTwip($signWidth), roomNoBorderCell('top'));
    $cell->addText('(' . roomClean($signatureName) . ')', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $cell->addText(roomClean($signaturePosition), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}
function roomPartsToPlainText(array $parts)
{
    $text = '';

    foreach ($parts as $part) {
        $text .= is_array($part) ? ($part[0] ?? '') : $part;
    }

    $text = roomClean($text);
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim($text);
}

function roomEstimateWordLines($text, $charsPerLine = 95)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

    if ($text === '') {
        return 0;
    }

    $len = mb_strlen($text, 'UTF-8');

    return max(1, (int) ceil($len / $charsPerLine));
}

function roomShouldMoveDeanToNextPage(array $bodyTexts, $subjectText = '')
{
    /*
      Word วัดความสูงจริงแบบ Preview ไม่ได้
      เลยประเมินจากจำนวนบรรทัดแทน
      ให้ขึ้นหน้า -2- เฉพาะตอนเนื้อหายาวจนลายเซ็นคณบดีมีโอกาสล้นหน้า
    */

    $bodyLines = 0;

    foreach ($bodyTexts as $text) {
        $bodyLines += roomEstimateWordLines($text, 95);
    }

    $subjectLines = count(roomSplitHeaderLines($subjectText, 78));

    // ส่วนหัวเอกสาร + เรื่อง/เรียน + ระยะลายเซ็นผู้ขอ
    $fixedLinesBeforeDean = 12 + $subjectLines;

    // บล็อกคณบดีทั้งก้อน
    $deanBlockLines = 6;

    // ยิ่งเลขมาก = ขึ้นหน้าใหม่ยากขึ้น / ยิ่งเลขน้อย = ขึ้นหน้าใหม่ง่ายขึ้น
    $maxLinesPerPage = 39;

    return ($fixedLinesBeforeDean + $bodyLines + $deanBlockLines) > $maxLinesPerPage;
}

function addRoomContinuationPageNumber($section, $pageNo = 2)
{
    /*
      ไฟล์ห้องพักต้องบังคับให้เลข -2- เริ่มหน้าใหม่
      เพราะถ้าไม่ใส่ pageBreakBefore เลข -2- จะไปต่อท้ายหน้าแรก
    */
    $section->addText('-' . $pageNo . '-', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 360,
        'lineHeight' => 1.0,
        'pageBreakBefore' => true,
    ]);
}

function addRoomDeanApprovalWithAutoPage($section, string $deanToText, string $deanName, string $deanPosition, array $bodyTexts, string $subjectText)
{
    if (roomShouldMoveDeanToNextPage($bodyTexts, $subjectText)) {
        addRoomContinuationPageNumber($section, 2);
    }

    addRoomDeanApprovalSignature($section, $deanToText, $deanName, $deanPosition);
}
function addRoomDeanApprovalSignature($section, string $deanToText, string $deanName, string $deanPosition)
{
    $labelWidth = 1.15;
    $textWidth = 8.40;

    $approvalTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip($labelWidth + $textWidth),
    ]);
    $approvalTable->addRow(null, ['exactHeight' => false]);
    $approvalTable->addCell(Converter::cmToTwip($labelWidth), roomNoBorderCell('top'))
        ->addText('เรียน', 'normalFont', [
            'alignment' => Jc::LEFT,
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);
    $approvalCell = $approvalTable->addCell(Converter::cmToTwip($textWidth), roomNoBorderCell('top'));
    $approvalCell->addText(roomClean($deanToText), 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $approvalCell->addText(roomClean('เพื่อโปรดพิจารณาอนุมัติ'), 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $signTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip($labelWidth + $textWidth),
    ]);
    $signTable->addRow(null, ['exactHeight' => false]);
    $signTable->addCell(Converter::cmToTwip($labelWidth), roomNoBorderCell('top'))->addText('', 'normalFont', roomHeaderPara());
    $signCell = $signTable->addCell(Converter::cmToTwip($textWidth), roomNoBorderCell('top'));
    $signCell->addText('', 'normalFont', ['spaceBefore' => 0, 'spaceAfter' => 600, 'lineHeight' => 1.0]);
    $signCell->addText('(' . roomClean($deanName) . ')', 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $signCell->addText(roomClean($deanPosition), 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}


function roomNoBorderCell($valign = 'bottom')
{
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

function roomDottedBottomCell($valign = 'bottom')
{
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

function roomHeaderPara($align = Jc::LEFT, $spaceAfter = 0)
{
    return [
        'alignment' => $align,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
    ];
}
function roomDottedValuePara($align = Jc::LEFT, $spaceBefore = 85)
{
    return [
        'alignment' => $align,
        'spaceBefore' => $spaceBefore,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ];
}

function roomDottedValueParaIndent($leftCm = 0.30, $spaceBefore = 85)
{
    return [
        'alignment' => Jc::LEFT,
        'spaceBefore' => $spaceBefore,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
        'indentation' => [
            'left' => Converter::cmToTwip($leftCm),
        ],
    ];
}

function roomHeaderLabelCell($valign = 'bottom')
{
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'valign' => $valign,
        'noWrap' => true,
        'marginTop' => 0,
        'marginBottom' => 0,
        'marginLeft' => 0,
        'marginRight' => 0,
    ];
}

function roomHeaderLabelPara($align = Jc::LEFT)
{
    return [
        'alignment' => $align,
        'spaceBefore' => 40,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ];
}
function roomSplitHeaderLines($text, $limit = 86)
{
    $text = trim(preg_replace('/\s+/u', ' ', roomClean($text)));
    if ($text === '') {
        return [''];
    }

    $lines = [];
    while (mb_strlen($text, 'UTF-8') > $limit) {
        $cut = mb_substr($text, 0, $limit, 'UTF-8');
        $spacePos = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($spacePos !== false && $spacePos > 28) {
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

function addRoomMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText)
{
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    $cleanHeaderText = roomClean($headerText);
    $govFontSize = roomGovHeaderFontSize($cleanHeaderText);
    $headerTextForDisplay = roomRemoveGovSpaces($cleanHeaderText);
    $contentWidthCm = 16.0;

    $titleTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip($contentWidthCm),
    ]);

    $titleTable->addRow(Converter::cmToTwip(1.65));

    $left = $titleTable->addCell(Converter::cmToTwip(2.2), roomNoBorderCell('top'));
    if (file_exists($garuda)) {
        $left->addImage($garuda, [
            'height' => 43,
            'alignment' => Jc::LEFT,
        ]);
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

    $titleTable->addCell(Converter::cmToTwip(2.2), roomNoBorderCell('top'))
        ->addText('', 'normalFont', roomHeaderPara());

    // ส่วนราชการ
    $agencyTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip($contentWidthCm),
    ]);

    $agencyTable->addRow(Converter::cmToTwip(0.80), ['exactHeight' => true]);

    $agencyTable->addCell(Converter::cmToTwip(2.40), roomHeaderLabelCell())
        ->addText('ส่วนราชการ', 'headerLabelFont', roomHeaderLabelPara());

    $agencyTextCellStyle = roomDottedBottomCell();
    $agencyTextCellStyle['noWrap'] = true;

    $agencyTable->addCell(Converter::cmToTwip($contentWidthCm - 2.40), $agencyTextCellStyle)
        ->addText(
            $headerTextForDisplay,
            [
                'name' => 'TH SarabunPSK',
                'size' => $govFontSize,
            ],
            roomDottedValuePara()
        );

    // ที่ / วันที่
    $dateTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip($contentWidthCm),
    ]);

    $dateTable->addRow(Converter::cmToTwip(0.72), ['exactHeight' => true]);

    $dateTable->addCell(Converter::cmToTwip(0.45), roomHeaderLabelCell())
        ->addText('ที่', 'headerLabelFont', roomHeaderLabelPara());

    $dateTable->addCell(Converter::cmToTwip(5.25), roomDottedBottomCell())
        ->addText(roomClean($docNo), 'normalFont', roomDottedValuePara());

    $dateTable->addCell(Converter::cmToTwip(1.10), roomHeaderLabelCell())
        ->addText('วันที่', 'headerLabelFont', roomHeaderLabelPara(Jc::CENTER));

    $dateTable->addCell(Converter::cmToTwip(9.20), roomDottedBottomCell())
        ->addText(roomClean($thaiDocDate), 'normalFont', roomDottedValueParaIndent(0.40));

    // เรื่อง
    $subjectLines = roomSplitHeaderLines($subjectText, 86);
    $subjectTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip($contentWidthCm),
    ]);

    foreach ($subjectLines as $i => $line) {
        $subjectTable->addRow(Converter::cmToTwip(0.80), ['exactHeight' => true]);

        $subjectTable->addCell(Converter::cmToTwip(0.90), roomHeaderLabelCell())
            ->addText($i === 0 ? 'เรื่อง' : '', 'headerLabelFont', roomHeaderLabelPara());

        $subjectTable->addCell(Converter::cmToTwip($contentWidthCm - 0.90), roomDottedBottomCell())
            ->addText(roomClean($line), 'normalFont', roomDottedValueParaIndent(0.30));
    }

    // เรียน
    $learnTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip($contentWidthCm),
    ]);

    $learnTable->addRow(null, ['exactHeight' => false]);

    $learnTable->addCell(Converter::cmToTwip(1.20), roomNoBorderCell())
        ->addText('เรียน', 'normalFont', [
            'alignment' => Jc::LEFT,
            'spaceBefore' => 20,
            'spaceAfter' => 120,
            'lineHeight' => 1.0,
        ]);

    $learnTable->addCell(Converter::cmToTwip(14.80), roomNoBorderCell())
        ->addText(roomClean($toText), 'normalFont', [
            'alignment' => Jc::LEFT,
            'spaceBefore' => 20,
            'spaceAfter' => 120,
            'lineHeight' => 1.0,
        ]);
}

function addRoomRequestWordPage($phpWord, array $data)
{
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

    $firstPara = [
        'ตามที่ ' . $data['displayDepartmentFull'] . ' ' . $data['displayFaculty'] .
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี' .
        ' มีความประสงค์ขออนุมัติใช้ห้องพักรับรองสำหรับ ' . ($data['roomRequestText'] ?: '.................................') .
        ' ให้แก่ ' . ($data['guestFullname'] ?: '.................................') .
        ' ซึ่งเป็น ' . ($data['personTypeText'] ?: '.................................') .
        ' ในระหว่างวันที่ ' . ($data['stayDateText'] ?: '.................................') . ' นั้น'
    ];

    addRoomRequestPara($section, $firstPara);

    $secondPara = [
        'ในการนี้ ' . $data['displayDepartmentFull'] .
        ' จึงมีความประสงค์ขออนุมัติใช้ห้องพักรับรอง ณ ' . ($data['roomType'] ?: '.................................') .
        ' ให้แก่ ' . ($data['guestFullname'] ?: '.................................') .
        ' ทั้งนี้เพื่อ ' . ($data['reasonText'] ?: '.................................') .
        ' รายละเอียดตามเอกสารแนบท้าย'
    ];

    addRoomRequestPara($section, $secondPara);

    addRoomRequestClosePara($section);

    addRoomOwnerSignature(
        $section,
        $data['signatureName'],
        $data['signaturePosition']
    );

    addRoomDeanApprovalWithAutoPage(
        $section,
        $data['deanToText'],
        $data['deanName'],
        $data['deanPosition'],
        [
            roomPartsToPlainText($firstPara),
            roomPartsToPlainText($secondPara),
            'จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ'
        ],
        $data['subjectText']
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

$deanName = '';
$deanFacultyName = $displayFaculty;
$deanPosition = 'คณบดี' . $displayFaculty;
try {
    if ($displayFaculty !== '') {
        $deanStmt = $pdo->prepare("
            SELECT faculty_name, dean_name, dean_position
            FROM faculties
            WHERE faculty_name = :faculty
            LIMIT 1
        ");
        $deanStmt->execute([':faculty' => $displayFaculty]);
        $deanRow = $deanStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $deanFacultyName = roomClean($deanRow['faculty_name'] ?? $deanFacultyName);
        $deanName = roomClean($deanRow['dean_name'] ?? '');
        $dbDeanPosition = roomClean($deanRow['dean_position'] ?? '');
        $deanPosition = $dbDeanPosition !== '' ? $dbDeanPosition : ('คณบดี' . $deanFacultyName);
    }
} catch (Throwable $e) {
    $deanName = '';
}
if ($deanName === '') {
    $deanName = '................................';
}
$deanToText = 'คณบดี' . ($deanFacultyName !== '' ? $deanFacultyName : $displayFaculty);

$roomRequestText = (trim($roomRequest) === 'อื่น ๆ' && trim($roomRequestOther) !== '') ? $roomRequestOther : $roomRequest;
$personTypeText = (trim($personType) === 'อื่น ๆ' && trim($personTypeOther) !== '') ? $personTypeOther : $personType;
$reasonText = (trim($reason) === 'อื่น ๆ' && trim($reasonOther) !== '') ? $reasonOther : $reason;
$stayDateTextRaw = (trim($dateOption) === 'range' && trim($rangeDate) !== '') ? $rangeDate : $singleDate;
$stayDateText = roomThaiDateTextAny($stayDateTextRaw);

$thaiDocDate = roomThaiDateAny($docDate);
$docNo = trim((string) ($document['doc_no'] ?? ''));
$headerText = trim((string) ($document['header_text'] ?? ''));
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
$phpWord->addFontStyle('headerLabelFont', [
    'name' => 'TH SarabunPSK',
    'size' => 20,
    'bold' => true,
    'position' => -4,
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
    'deanToText' => $deanToText,
    'deanName' => $deanName,
    'deanPosition' => $deanPosition,
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