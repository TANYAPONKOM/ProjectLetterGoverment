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
require_once __DIR__ . '/../includes/thai_word_breaks.php';

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
    // ใช้ชื่อเดิมเพื่อไม่กระทบจุดเรียกใช้เดิม แต่รอบนี้ต้องการให้เลขทั้งหมดเป็นเลขอารบิก
    return speakerArabicDigit((string)$text);
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
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543);
    }

    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        $months = speakerThaiMonths();
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y > 2400 ? $y : $y + 543);
    }

    return speakerArabicDigit($date);
}

function speakerThaiDateTextAny($text) {
    $text = speakerArabicDigit((string)$text);
    $text = preg_replace_callback('/(\d{4})-(\d{1,2})-(\d{1,2})/u', function ($m) {
        return speakerThaiDateAny($m[1] . '-' . $m[2] . '-' . $m[3]);
    }, $text);
    $text = preg_replace_callback('/(\d{1,2})\/(\d{1,2})\/(\d{4})/u', function ($m) {
        return speakerThaiDateAny($m[1] . '/' . $m[2] . '/' . $m[3]);
    }, $text);
    return $text;
}

function speakerClean($text) {
    $text = str_replace(["\r", "\n", "\t"], ' ', speakerArabicDigit((string)$text));
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    return trim($text);
}

function speakerRemoveGovSpaces($text) {
    return preg_replace('/\s+/u', '', speakerClean($text));
}

function speakerGovHeaderFontSize($text) {
    $len = mb_strlen(speakerClean($text), 'UTF-8');
    if ($len > 82) {
        return 14;
    }
    if ($len > 72) {
        return 15;
    }
    return 16;
}

// รูปแบบตัดคำตาม documents/word_templates/word_speaker.php
function insertSpeakerThaiWordBreaksForDownload($text) {
    $text = speakerClean((string)$text);
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

function addSpeakerDownloadManualPara($section, array $lines, $spaceAfter = 80) {
    $fullText = '';

    foreach ($lines as $line) {
        $fullText .= ' ' . speakerClean($line);
    }

    $fullText = trim(preg_replace('/\s+/u', ' ', $fullText));

    if ($fullText === '') {
        return;
    }

    $section->addText(
        insertSpeakerThaiWordBreaksForDownload($fullText),
        'normalFont',
        'academicBodyThaiDistribute'
    );
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
function speakerHeaderLabelCell($valign = 'bottom') {
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

function speakerHeaderLabelPara($align = Jc::LEFT) {
    return [
        'alignment' => $align,
        'spaceBefore' => 40,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ];
}

function speakerDottedValuePara($align = Jc::LEFT, $spaceBefore = 85) {
    return [
        'alignment' => $align,
        'spaceBefore' => $spaceBefore,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ];
}

function speakerDottedValueParaIndent($leftCm = 0.30, $spaceBefore = 85) {
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

    $govFontSize = speakerGovHeaderFontSize($headerText);
    $cleanHeaderText = speakerClean($headerText);
    $headerTextForDisplay = speakerRemoveGovSpaces($cleanHeaderText);
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

    $left = $titleTable->addCell(Converter::cmToTwip(2.2), speakerNoBorderCell('top'));
    if (file_exists($garuda)) {
        $left->addImage($garuda, [
            'height' => 43,
            'alignment' => Jc::LEFT,
        ]);
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

    $titleTable->addCell(Converter::cmToTwip(2.2), speakerNoBorderCell('top'))
        ->addText('', 'normalFont', speakerHeaderPara());

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

    $agencyTable->addCell(Converter::cmToTwip(2.40), speakerHeaderLabelCell())
        ->addText('ส่วนราชการ', 'headerLabelFont', speakerHeaderLabelPara());

    $agencyTextCellStyle = speakerDottedBottomCell();
    $agencyTextCellStyle['noWrap'] = true;

    $agencyTable->addCell(Converter::cmToTwip($contentWidthCm - 2.40), $agencyTextCellStyle)
        ->addText(
            $headerTextForDisplay,
            [
                'name' => 'TH SarabunPSK',
                'size' => $govFontSize,
            ],
            speakerDottedValuePara()
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

    $dateTable->addCell(Converter::cmToTwip(0.45), speakerHeaderLabelCell())
        ->addText('ที่', 'headerLabelFont', speakerHeaderLabelPara());

    $dateTable->addCell(Converter::cmToTwip(7.00), speakerDottedBottomCell())
        ->addText(speakerClean($docNo), 'normalFont', speakerDottedValuePara());

    $dateTable->addCell(Converter::cmToTwip(1.10), speakerHeaderLabelCell())
        ->addText('วันที่', 'headerLabelFont', speakerHeaderLabelPara(Jc::CENTER));

    $dateTable->addCell(Converter::cmToTwip(7.45), speakerDottedBottomCell())
        ->addText(speakerClean($thaiDocDate), 'normalFont', speakerDottedValueParaIndent(0.40));

    // เรื่อง
    $subjectLines = speakerSplitHeaderLines($subjectText, 86);
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

        $subjectTable->addCell(Converter::cmToTwip(0.90), speakerHeaderLabelCell())
            ->addText($i === 0 ? 'เรื่อง' : '', 'headerLabelFont', speakerHeaderLabelPara());

        $subjectTable->addCell(Converter::cmToTwip($contentWidthCm - 0.90), speakerDottedBottomCell())
            ->addText(
                speakerKeepTogetherWords(speakerClean($line)),
                'normalFont',
                speakerDottedValueParaIndent(0.30)
            );
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

    $learnTable->addCell(Converter::cmToTwip(1.20), speakerNoBorderCell())
        ->addText('เรียน', 'normalFont', [
            'alignment' => Jc::LEFT,
            'spaceBefore' => 20,
            'spaceAfter' => 120,
            'lineHeight' => 1.0,
        ]);

    $learnTable->addCell(Converter::cmToTwip(14.80), speakerNoBorderCell())
        ->addText(speakerClean($toText), 'normalFont', [
            'alignment' => Jc::LEFT,
            'spaceBefore' => 20,
            'spaceAfter' => 120,
            'lineHeight' => 1.0,
        ]);
}
function speakerEstimateWordLines($text, $charsPerLine = 95)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));

    if ($text === '') {
        return 0;
    }

    $len = mb_strlen($text, 'UTF-8');

    return max(1, (int)ceil($len / $charsPerLine));
}

function speakerShouldMoveDeanToNextPage(array $bodyTexts, $subjectText = '')
{
    /*
      Word วัดความสูงจริงแบบ Preview/PDF ไม่ได้
      เลยประเมินจากจำนวนบรรทัด
      ให้ขึ้นหน้า -2- เฉพาะตอนเนื้อหายาวจนบล็อกคณบดีมีโอกาสล้นหน้า
    */

    $bodyLines = 0;

    foreach ($bodyTexts as $text) {
        $bodyLines += speakerEstimateWordLines($text, 95);
    }

    $subjectLines = count(speakerSplitHeaderLines($subjectText, 78));

    // ส่วนหัวเอกสาร + เรื่อง/เรียน + ระยะลายเซ็นผู้ขอ
    $fixedLinesBeforeDean = 12 + $subjectLines;

    // พื้นที่บล็อกคณบดี
    $deanBlockLines = 6;

    // ยิ่งเลขมาก = ขึ้นหน้าใหม่ยากขึ้น / ยิ่งเลขน้อย = ขึ้นหน้าใหม่ง่ายขึ้น
    $maxLinesPerPage = 37;

    return ($fixedLinesBeforeDean + $bodyLines + $deanBlockLines) > $maxLinesPerPage;
}

function addSpeakerContinuationPageNumber($section, $pageNo = 2)
{
    $section->addPageBreak();

    $section->addText('-' . $pageNo . '-', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 360,
        'lineHeight' => 1.0,
    ]);
}

function addSpeakerDeanApprovalWithAutoPage($section, $toText, $deanName, $deanPosition, array $bodyTexts, $subjectText)
{
    if (speakerShouldMoveDeanToNextPage($bodyTexts, $subjectText)) {
        addSpeakerContinuationPageNumber($section, 2);
    }

    addSpeakerDeanApprovalFixed($section, $toText, $deanName, $deanPosition);
}

function addSpeakerDeanApprovalFixed($section, $toText, $deanName, $deanPosition) {
    $safeToText = speakerClean($toText);
    $safeDeanName = speakerClean($deanName !== '' ? $deanName : '................................');
    $safeDeanPosition = speakerClean($deanPosition !== '' ? $deanPosition : $toText);

    $section->addText('', 'normalFont', [
        'spaceBefore' => 120,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $approvalTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);

    $approvalTable->addRow(null, ['exactHeight' => false]);
    $approvalTable->addCell(Converter::cmToTwip(1.35), speakerNoBorderCell('top'))
        ->addText('เรียน', 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);

    $contentCell = $approvalTable->addCell(Converter::cmToTwip(14.65), speakerNoBorderCell('top'));
    $contentCell->addText($safeToText, 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $contentCell->addText('เพื่อโปรดพิจารณาอนุมัติ', 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $contentCell->addText('', 'normalFont', [
    'spaceBefore' => 360,
    'spaceAfter' => 0,
    'lineHeight' => 1.0,
]);

    $contentCell->addText('(' . $safeDeanName . ')', 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $deanPositionTable = $contentCell->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(5.5),
    ]);
    $deanPositionTable->addRow(null, ['exactHeight' => false]);
    $deanPositionTable->addCell(Converter::cmToTwip(5.5), speakerNoBorderCell('top'))
        ->addText($safeDeanPosition, 'normalFont', [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 0,
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

    $firstParaText =
        'อ้างถึง หนังสือจาก ' . $data['referenceOrg'] .
        ' เลขที่ ' . $data['referenceNo'] .
        ' ลงวันที่ ' . $data['referenceDateText'] .
        ' เรื่อง ' . $data['projectTitle'] .
        ' หลักสูตร ' . $data['courseName'] .
        ' ในระหว่างวันที่ ' . $data['eventRange'] .
        ' ณ ' . $data['eventPlace'] . ' นั้น';

    addSpeakerDownloadManualPara($section, [
        $firstParaText
    ]);

    $secondParaText =
        'ในการนี้ ข้าพเจ้า ' . $data['ownerName'] .
        ' สังกัด' . $data['displayDepartmentFull'] . ' ' . $data['displayFaculty'] .
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี ข้าพเจ้ามีความประสงค์ ' .
        $data['intentionText'] .
        ' หลักสูตร ' . $data['courseName'] .
        ' ระหว่างวันที่ ' . $data['travelPeriod'] .
        ' (รวมระยะเวลาในการเดินทาง) รายละเอียดตามเอกสารแนบ';

    addSpeakerDownloadManualPara($section, [
        $secondParaText
    ]);

    addSpeakerDownloadClosePara($section);

    addSpeakerSignatureFixed(
        $section,
        $data['ownerName'],
        $data['position']
    );

    addSpeakerDeanApprovalWithAutoPage(
        $section,
        $data['toText'],
        $data['deanName'],
        $data['deanPosition'],
        [
            $firstParaText,
            $secondParaText,
            'จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ'
        ],
        $data['subjectText']
    );
}

$hasSavedDocDateField = array_key_exists(1, $valueMap) || array_key_exists('doc_date', $valueMapByKey);
$docDate = $hasSavedDocDateField
    ? trim((string)($valueMapByKey['doc_date'] ?? ($valueMap[1] ?? '')))
    : trim((string)($document['doc_date'] ?? ''));

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

$deanName = '';
$deanPosition = $displayFacultyDean;
try {
    $deanStmt = $pdo->prepare("
        SELECT dean_name, dean_position
        FROM faculties
        WHERE faculty_name = :faculty_name
        LIMIT 1
    ");
    $deanStmt->execute([':faculty_name' => $displayFaculty]);
    $deanRow = $deanStmt->fetch(PDO::FETCH_ASSOC);
    if ($deanRow) {
        $deanName = trim((string)($deanRow['dean_name'] ?? ''));
        $dbDeanPosition = trim((string)($deanRow['dean_position'] ?? ''));
        if ($dbDeanPosition !== '') {
            $deanPosition = $dbDeanPosition;
        }
    }
} catch (Throwable $e) {
    $deanName = '';
    $deanPosition = $displayFacultyDean;
}

$headerText = trim((string)($document['header_text'] ?? ''));
if ($headerText === '') {
    $headerText = trim($displayFaculty . ' ' . $displayDepartmentFull);
}
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
$phpWord->addFontStyle('headerLabelFont', [
    'name' => 'TH SarabunPSK',
    'size' => 20,
    'bold' => true,
    'position' => -4,
]);

$phpWord->addParagraphStyle('academicBodyThaiDistribute', [
    'alignment' => 'thaiDistribute',
    'lineHeight' => 0.94,
    'spaceBefore' => 0,
    'spaceAfter' => 28,
    'indentation' => [
        'firstLine' => Converter::cmToTwip(2.5),
    ],
]);

buildSpeakerDownloadWord($phpWord, [
    'docNo' => $docNo ?: '',
    'thaiDocDate' => $thaiDocDate,
    'headerText' => speakerClean($headerText ?: 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม ภาควิชาเทคโนโลยีสารสนเทศ โทร. 7064.'),
    'subjectText' => $subjectText,
    'toText' => $displayFacultyDean,
    'deanName' => $deanName,
    'deanPosition' => $deanPosition,
    'referenceOrg' => $referenceOrg !== '' ? $referenceOrg : '................................',
    'referenceNo' => $referenceNo !== '' ? $referenceNo : '................................',
    'referenceDateText' => $referenceDateText !== '' ? $referenceDateText : '................................',
    'projectTitle' => $projectTitle !== '' ? $projectTitle : '................................',
    'courseName' => $courseName !== '' ? $courseName : '................................',
    'eventRange' => $joinDates !== '' ? speakerThaiDateTextAny($joinDates) : '................................',
    'eventPlace' => $location !== '' ? $location : '................................',
    'ownerName' => $ownerName !== '' ? $ownerName : '................................',
    'position' => $position,
    'displayDepartmentFull' => $displayDepartmentFull,
    'displayFaculty' => $displayFaculty,
    'intentionText' => $intentionText,
    'travelPeriod' => $travelPeriod !== '' ? speakerThaiDateTextAny($travelPeriod) : '................................',
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