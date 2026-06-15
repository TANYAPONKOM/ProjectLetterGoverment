<?php
// Pro_letter/documents/download_word_memo.php
// ดาวน์โหลด Word (.docx) สำหรับบันทึกข้อความ: ขออนุมัติตัวบุคคลเพื่อไปฝึกอบรม
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

$stmt = $pdo->prepare("\n    SELECT document_id, template_id, owner_id, department_id,\n           doc_no, doc_date, subject, header_text, status\n    FROM documents\n    WHERE document_id = :id\n    LIMIT 1\n");
$stmt->execute([':id' => $docId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$document) {
    exit('ไม่พบเอกสาร');
}

if (!$isAdmin && !$isOfficer && (int) $document['owner_id'] !== $userId) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์ดาวน์โหลดเอกสารนี้');
}

$q = $pdo->prepare("\n    SELECT dv.field_id, dv.value_text, tf.field_key\n    FROM document_values dv\n    LEFT JOIN template_fields tf ON tf.field_id = dv.field_id\n    WHERE dv.document_id = :id\n");
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

$budgetStmt = $pdo->prepare("\n    SELECT item_type, description, amount\n    FROM budget_items\n    WHERE document_id = :id\n    ORDER BY item_id ASC\n");
$budgetStmt->execute([':id' => $docId]);
$budgetItems = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

$departmentPhone = '';
$rawHeaderTextForPhone = trim((string) ($document['header_text'] ?? ''));

if ($rawHeaderTextForPhone !== '' && preg_match('/โทร\.?\s*([^\s]+)/u', $rawHeaderTextForPhone, $phoneMatch)) {
    $departmentPhone = trim($phoneMatch[1]);
}


function academicField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '')
{
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string) ($v ?? ''));
    return $v !== '' ? $v : $default;
}

function academicThaiDigit($text)
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

function academicArabicDigit($text)
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

function academicThaiMonths()
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

function academicThaiDateAny($date)
{
    $date = trim(academicArabicDigit((string) $date));
    if ($date === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        $months = academicThaiMonths();
        return academicThaiDigit($d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543));
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
        $d = (int) $m[1];
        $mo = (int) $m[2];
        $y = (int) $m[3];
        $months = academicThaiMonths();
        return academicThaiDigit($d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543));
    }

    return academicThaiDigit($date);
}

function academicThaiDateAnyArabicNumber($date)
{
    $date = trim(academicArabicDigit((string) $date));
    if ($date === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        $months = academicThaiMonths();
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543);
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
        $d = (int) $m[1];
        $mo = (int) $m[2];
        $y = (int) $m[3];
        $months = academicThaiMonths();
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543);
    }

    return academicArabicDigit($date);
}

function academicClean($text)
{
    $text = str_replace(["\r", "\n", "\t"], ' ', (string) $text);
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    return academicThaiDigit(trim($text));
}

function academicCleanNoDigit($text)
{
    $text = str_replace(["\r", "\n", "\t"], ' ', (string) $text);
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    return trim($text);
}

function academicCleanSectionSubject($text)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

    $removePrefixList = [
        'ขออนุมัติตัวบุคคลเพื่อไป',
        'ขออนุมัติตัวบุคคลเข้าร่วม',
        'ขออนุมัติตัวบุคคลเพื่อเข้าร่วม',
        'ขออนุมัติค่าใช้จ่ายในการเข้าร่วม',
        'ขออนุมัติใช้รถยนต์ส่วนบุคคลในการเดินทางไปเข้าร่วม',
        'ขออนุมัติใช้รถยนต์ส่วนบุคคล',
    ];

    foreach ($removePrefixList as $prefix) {
        if (mb_strpos($text, $prefix, 0, 'UTF-8') === 0) {
            return trim(mb_substr($text, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8'));
        }
    }

    return $text;
}

function academicBahtText($amount)
{
    $amount = number_format((float) $amount, 2, '.', '');
    [$number, $satang] = explode('.', $amount);

    $txtNumArr = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    $txtDigitArr = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน', 'ล้าน'];

    $convert = function ($num) use (&$convert, $txtNumArr, $txtDigitArr) {
        $num = (string) ((int) $num);
        $len = strlen($num);
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $n = (int) $num[$i];
            $pos = $len - $i - 1;
            if ($n === 0) {
                continue;
            }

            if ($pos === 0 && $n === 1 && $len > 1) {
                $result .= 'เอ็ด';
            } elseif ($pos === 1 && $n === 2) {
                $result .= 'ยี่';
            } elseif ($pos === 1 && $n === 1) {
                $result .= '';
            } else {
                $result .= $txtNumArr[$n];
            }
            $result .= $txtDigitArr[$pos];
        }
        return $result;
    };

    $bahtText = ((int) $number === 0) ? 'ศูนย์บาท' : $convert($number) . 'บาท';
    return ((int) $satang === 0) ? $bahtText . 'ถ้วน' : $bahtText . $convert($satang) . 'สตางค์';
}

// ใช้แนวเดียวกับ download_word_memo.php: ช่วยตัดคำให้ย่อหน้ากระจายตัวดี แต่ไม่ไปยุ่งกับหัวเอกสาร
function addAcademicMemoManualPara($section, array $textParts, $spaceAfter = 28)
{
    $fullText = '';
    foreach ($textParts as $part) {
        $fullText .= is_array($part) ? ($part[0] ?? '') : $part;
    }

    $processedText = insertThaiWordBreaksAcademicMemo($fullText);
    if ($processedText === '') {
        return;
    }

    // แก้เฉพาะย่อหน้าเนื้อหา Word:
    // ใช้ paragraph style แบบจัดเต็มบรรทัด โดยไม่กระจายบรรทัดสุดท้ายให้ไปอยู่กลางหน้า
    $section->addText($processedText, 'normalFont', 'academicBodyThaiDistribute');
}
function insertThaiWordBreaksAcademicMemo($text)
{
    $text = cleanWordText((string) $text);
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

function addAcademicClosePara($section, $spaceAfter = 120)
{
    $runClose = $section->addTextRun([
        'alignment' => Jc::START,
        'lineHeight' => 1.15,
        'spaceAfter' => $spaceAfter,
        'indentation' => [
            'firstLine' => Converter::cmToTwip(2.5),
        ],
    ]);
    $runClose->addText('จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ', 'normalFont');
}


function academicKeepSignaturePositionTogether($text)
{
    $text = trim(str_replace(["\r", "\n", "\t", "\u{200B}", "\u{2060}"], ' ', (string) $text));
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);

    $joiner = "\u{2060}";
    foreach (['เทคโนโลยีสารสนเทศ', 'สารสนเทศ'] as $word) {
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars) {
            $text = str_replace($word, implode($joiner, $chars), $text);
        }
    }

    return $text;
}


function academicKeepSignatureLineTogether($text)
{
    $text = trim(str_replace(["\r", "\n", "\t", "\u{200B}", "\u{2060}"], ' ', (string) $text));
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    if ($text === '') {
        return '';
    }
    $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    $joiner = "\u{2060}";
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (preg_match('/^\s+$/u', $part)) {
            $out .= $part;
            continue;
        }
        $chars = preg_split('//u', $part, -1, PREG_SPLIT_NO_EMPTY);
        $out .= $chars ? implode($joiner, $chars) : $part;
    }
    return $out;
}

function addAcademicSignatureFixed($section, $name, $position)
{
    // แก้เฉพาะลายเซ็น: ให้ช่องตำแหน่งกว้างพอ และกันคำว่า "สารสนเทศ" ไม่ให้ตัว ศ ตกบรรทัด
    $safeName = academicCleanNoDigit($name ?: '................................');
    $safePosition = academicKeepSignaturePositionTogether($position ?: '');

    $section->addText('', 'normalFont', [
        'spaceBefore' => 520,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $noBorder = [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'valign' => 'top',
        'marginTop' => 0,
        'marginBottom' => 0,
        'marginLeft' => 0,
        'marginRight' => 0,
    ];

    $sigTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);

    $sigTable->addRow(null, ['exactHeight' => false]);
    $sigTable->addCell(Converter::cmToTwip(6.30), $noBorder)->addText('', 'normalFont', ['spaceAfter' => 0]);

    $sigCell = $sigTable->addCell(Converter::cmToTwip(9.70), $noBorder);

    $sigCell->addText('(' . $safeName . ')', 'normalFont', [
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


function addAcademicDeanSignatureFixed($section, $deanName, $deanPosition, $deanIndentCm = 1.20)
{
    $safeDeanName = academicCleanNoDigit($deanName ?: '................................');
    $safeDeanPosition = academicKeepSignatureLineTogether(academicCleanNoDigit($deanPosition ?: ''));

    // เว้นพื้นที่เซ็น และให้ (ชื่อคณบดี) เริ่มแนวเดียวกับข้อความหลังคำว่า "เรียน"
    $section->addTextBreak(2, ['size' => 16]);

    $noBorder = academicNoBorderCell('top');
    $sigTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);

    $sigTable->addRow(null, ['exactHeight' => false]);
    $sigTable->addCell(Converter::cmToTwip($deanIndentCm), $noBorder)->addText('', 'normalFont', ['spaceAfter' => 0]);
    $nameCell = $sigTable->addCell(Converter::cmToTwip(16.0 - $deanIndentCm), $noBorder);
    $nameCell->addText('(' . $safeDeanName . ')', 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $sigTable->addRow(null, ['exactHeight' => false]);
    $sigTable->addCell(Converter::cmToTwip($deanIndentCm), $noBorder)->addText('', 'normalFont', ['spaceAfter' => 0]);
    $positionCell = $sigTable->addCell(Converter::cmToTwip(16.0 - $deanIndentCm), $noBorder);
    $positionCell->addText($safeDeanPosition, 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}
function academicPartsToPlainText(array $parts)
{
    $text = '';
    foreach ($parts as $part) {
        $text .= is_array($part) ? ($part[0] ?? '') : $part;
    }

    $text = cleanWordText($text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function academicEstimateWordLines($text, $charsPerLine = 82)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if ($text === '') {
        return 0;
    }

    $len = mb_strlen($text, 'UTF-8');
    return max(1, (int) ceil($len / $charsPerLine));
}

function academicShouldMoveDeanToNextPage(array $bodyTexts, $subjectText = '')
{
    /*
      ให้ขึ้นหน้า -2- เฉพาะตอนเนื้อหายาวจริง ๆ
      ไม่ใช่แค่มีลายเซ็นคณบดีแล้วบังคับขึ้นหน้าใหม่
    */

    $bodyLines = 0;
    foreach ($bodyTexts as $text) {
        $bodyLines += academicEstimateWordLines($text, 95);
    }

    $subjectLines = count(academicSplitHeaderLines($subjectText, 78));

    /*
      ค่านี้คือจำนวนบรรทัดโดยประมาณของส่วนหัว + เรื่อง/เรียน + ระยะลายเซ็นผู้ขอ
      ลดจากของเดิม เพราะของเดิมตีพื้นที่สูงเกินจริง
    */
    $fixedLinesBeforeDean = 12 + $subjectLines;

    /*
      พื้นที่บล็อกคณบดี
    */
    $deanBlockLines = 6;

    /*
      จำนวนบรรทัดที่ให้ใช้ได้ก่อนค่อยย้ายคณบดีขึ้นหน้าใหม่
      ยิ่งเลขมาก = ขึ้นหน้าใหม่ยากขึ้น
      ยิ่งเลขน้อย = ขึ้นหน้าใหม่ง่ายขึ้น
    */
    $maxLinesPerPage = 37;

    return ($fixedLinesBeforeDean + $bodyLines + $deanBlockLines) > $maxLinesPerPage;
}

function addAcademicContinuationPageNumber($section, $pageNo = 2)
{
    $section->addPageBreak();

    $section->addText('-' . $pageNo . '-', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 360,
        'lineHeight' => 1.0,
    ]);
}

function addAcademicDeanApprovalWithAutoPage($section, $deanToText, $deanName, $deanPosition, array $bodyTexts, $subjectText)
{
    if (academicShouldMoveDeanToNextPage($bodyTexts, $subjectText)) {
        addAcademicContinuationPageNumber($section, 2);
    }

    addAcademicDeanApprovalFixed($section, $deanToText, $deanName, $deanPosition);
}
function addAcademicDeanApprovalFixed($section, $deanToText, $deanName, $deanPosition)
{
    $safeDeanToText = academicCleanNoDigit($deanToText ?: '');
    $safeDeanName = academicCleanNoDigit($deanName ?: '................................');
    $safeDeanPosition = academicCleanNoDigit($deanPosition ?: $safeDeanToText);

    // ใช้ตาราง 2 คอลัมน์ เพื่อให้ข้อความหลังคำว่า "เรียน", บรรทัด "เพื่อ...", และ (ชื่อคณบดี)
    // เริ่มจากแนวเดียวกันจริงใน Word
    $deanIndentCm = 1.20;
    $noBorder = academicNoBorderCell('top');
    $approvalTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);

    $approvalTable->addRow(null, ['exactHeight' => false]);
    $labelCell = $approvalTable->addCell(Converter::cmToTwip($deanIndentCm), $noBorder);
    $labelCell->addText('เรียน', 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $toCell = $approvalTable->addCell(Converter::cmToTwip(16.0 - $deanIndentCm), $noBorder);
    $toCell->addText($safeDeanToText, 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $approvalTable->addRow(null, ['exactHeight' => false]);
    $approvalTable->addCell(Converter::cmToTwip($deanIndentCm), $noBorder)->addText('', 'normalFont', ['spaceAfter' => 0]);
    $purposeCell = $approvalTable->addCell(Converter::cmToTwip(16.0 - $deanIndentCm), $noBorder);
    $purposeCell->addText('เพื่อโปรดพิจารณาอนุมัติ', 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    addAcademicDeanSignatureFixed($section, $safeDeanName, $safeDeanPosition, $deanIndentCm);
}

function academicNoBorderCell($valign = 'bottom')
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

function academicDottedBottomCell($valign = 'bottom')
{
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        // Word บาง zoom จะซ่อนเส้น dotted ถ้าเส้นบางเกินไป
        // เพิ่มความหนาเฉพาะเส้นปะด้านล่าง เพื่อให้มองเห็นทั้งตอนซูมและไม่ซูม
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

function academicHeaderPara($align = Jc::LEFT, $spaceAfter = 0)
{
    return [
        'alignment' => $align,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
    ];
}

function academicHeaderParaIndent($leftCm = 0.0, $align = Jc::LEFT, $spaceAfter = 0)
{
    $para = academicHeaderPara($align, $spaceAfter);
    if ($leftCm > 0) {
        $para['indentation'] = ['left' => Converter::cmToTwip($leftCm)];
    }
    return $para;
}
function academicDottedValuePara($align = Jc::LEFT, $spaceBefore = 85)
{
    return [
        'alignment' => $align,
        'spaceBefore' => $spaceBefore,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ];
}

function academicDottedValueParaIndent($leftCm = 0.30, $spaceBefore = 85)
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

function academicHeaderLabelCell($valign = 'bottom')
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

function academicHeaderLabelPara($align = Jc::LEFT)
{
    return [
        'alignment' => $align,
        'spaceBefore' => 40,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ];
}
function academicSplitHeaderLines($text, $limit = 78)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if ($text === '') {
        return [''];
    }

    $safeWords = [
        'หลักสูตร',
        'การอบรม',
        'การพัฒนา',
        'เชิงปฏิบัติการ',
        'ปฏิบัติการ',
        'แอปพลิเคชัน',
        'ฐานข้อมูล',
        'สารสนเทศ',
        'เทคโนโลยี',
        'ระบบ',
        'เข้าร่วม',
        'นำเสนอ',
        'ประชุม',
        'วิชาการ',
        'โครงการ',
        'กิจกรรม',
        'และ',
        'เพื่อ',
        'ในการ',
        'ของ',
        'ด้วย',
        'โดย',
        'จาก',
        'กับ',
        'ใหม่',
        'การ'
    ];

    $lines = [];
    while (mb_strlen($text, 'UTF-8') > $limit) {
        $cutPos = 0;
        $maxBefore = mb_substr($text, 0, $limit, 'UTF-8');
        $spacePos = mb_strrpos($maxBefore, ' ', 0, 'UTF-8');

        if ($spacePos !== false && $spacePos >= 30) {
            $cutPos = $spacePos;
        } else {
            foreach ($safeWords as $word) {
                $offset = 1;
                while (($pos = mb_strpos($text, $word, $offset, 'UTF-8')) !== false) {
                    if ($pos >= 30 && $pos <= $limit) {
                        $cutPos = max($cutPos, $pos);
                    }
                    if ($pos > $limit) {
                        break;
                    }
                    $offset = $pos + mb_strlen($word, 'UTF-8');
                }
            }
        }

        if ($cutPos < 30) {
            $lookAhead = mb_substr($text, $limit, 24, 'UTF-8');
            $nextSpace = mb_strpos($lookAhead, ' ', 0, 'UTF-8');
            if ($nextSpace !== false) {
                $cutPos = $limit + $nextSpace;
            }
        }

        if ($cutPos < 30) {
            $cutPos = $limit;
        }

        $lines[] = trim(mb_substr($text, 0, $cutPos, 'UTF-8'));
        $text = trim(mb_substr($text, $cutPos, null, 'UTF-8'));
    }

    if ($text !== '') {
        $lines[] = $text;
    }

    return $lines;
}

function academicGovAgencyFontSize($text)
{
    $len = mb_strlen(trim((string) $text), 'UTF-8');
    if ($len > 78) {
        return 14;
    }
    if ($len > 66) {
        return 15;
    }
    return 16;
}

function academicGovAgencyFontStyle($text)
{
    $size = academicGovAgencyFontSize($text);
    if ($size < 16) {
        return ['name' => 'TH SarabunPSK', 'size' => $size];
    }
    return 'normalFont';
}

function academicGovAgencyNeedsTightWordLayout($text)
{
    return academicGovAgencyFontSize($text) <= 14;
}

function academicApplyGovAgencyTightRightMargin($section, $headerText)
{
    // แก้เฉพาะ Word: ถ้าบรรทัด "ส่วนราชการ" ยาวจนต้องใช้ฟอนต์ 14 เท่านั้น ค่อยปรับขอบกระดาษ
    $safeHeaderText = academicCleanNoDigit($headerText ?: 'คณะ...ภาควิชา...โทร...');
    $safeHeaderText = preg_replace('/\s+/u', '', $safeHeaderText);
    if (academicGovAgencyNeedsTightWordLayout($safeHeaderText) && method_exists($section, 'getStyle')) {
        $style = $section->getStyle();
        if ($style) {
            if (method_exists($style, 'setMarginLeft')) {
                $style->setMarginLeft(Converter::cmToTwip(2.80));
            }
            if (method_exists($style, 'setMarginRight')) {
                $style->setMarginRight(Converter::cmToTwip(1.80));
            }
        }
    }
}

function addAcademicGovAgencyDottedRowFixed($section, $headerText)
{
    // แก้เฉพาะแถว "ส่วนราชการ": ใช้ความกว้างเดิมก่อน ถ้ายาวจนต้องใช้ฟอนต์ 14 ค่อยเพิ่มความกว้างเฉพาะแถวนี้เล็กน้อย
    $safeHeaderText = academicCleanNoDigit($headerText ?: 'คณะ...ภาควิชา...โทร...');
    $safeHeaderText = preg_replace('/\s+/u', '', $safeHeaderText);
    $useTightWordLayout = academicGovAgencyNeedsTightWordLayout($safeHeaderText);
    // ถ้ายาวเกินเส้นจริง ค่อยใช้ความกว้างตามขอบใหม่: A4 21cm - ซ้าย 2.80cm - ขวา 1.80cm = 16.40cm
    $contentWidth = Converter::cmToTwip($useTightWordLayout ? 16.40 : 16.0);
    $labelWidth = Converter::cmToTwip(2.40);
    $textWidth = $contentWidth - $labelWidth;

    $agencyTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => $contentWidth,
    ]);

    $agencyTable->addRow(Converter::cmToTwip(0.80), ['exactHeight' => true]);

    $labelCell = $agencyTable->addCell($labelWidth, academicHeaderLabelCell());
    $labelCell->addText('ส่วนราชการ', 'headerLabelFont', academicHeaderLabelPara());

    $textCell = $agencyTable->addCell($textWidth, [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'borderBottomSize' => 12,
        'borderBottomColor' => '000000',
        'borderBottomStyle' => 'dotted',
        'valign' => 'bottom',
        'noWrap' => true,
        'marginTop' => 0,
        'marginBottom' => 20,
        'marginLeft' => 0,
        'marginRight' => 0,
    ]);

    $textCell->addText(
        $safeHeaderText,
        academicGovAgencyFontStyle($safeHeaderText),
        academicDottedValuePara()
    );
}

function addAcademicMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText)
{
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';
    academicApplyGovAgencyTightRightMargin($section, $headerText);

    // หัวบันทึกข้อความ: ทำเองในไฟล์นี้เพื่อคุมเส้นประ โดยเฉพาะช่องวันที่ไม่ให้ตกบรรทัด
    $titleTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $titleTable->addRow(Converter::cmToTwip(1.65));

    $left = $titleTable->addCell(Converter::cmToTwip(2.2), academicNoBorderCell('top'));
    if (file_exists($garuda)) {
        $left->addImage($garuda, [
            'height' => 43,
            'alignment' => Jc::LEFT
        ]);
    } else {
        $left->addText('', 'normalFont', academicHeaderPara());
    }

    $middle = $titleTable->addCell(Converter::cmToTwip(11.6), academicNoBorderCell('center'));
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
    $titleTable->addCell(Converter::cmToTwip(2.2), academicNoBorderCell('top'))->addText('', 'normalFont', academicHeaderPara());

    // ส่วนราชการ: ใช้รูปแบบเดียวกับ download_word_memo.php เพื่อกันข้อความยาวตกบรรทัด
    addAcademicGovAgencyDottedRowFixed($section, $headerText);

    // ที่ / วันที่ — แบ่งช่องวันที่ให้กว้างพอ เพื่อให้เส้นประต่อกับคำว่า วันที่ และค่าด้านขวาไม่ขึ้นบรรทัดใหม่
    $dateTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $dateTable->addRow(Converter::cmToTwip(0.72), ['exactHeight' => true]);

    $dateTable->addCell(Converter::cmToTwip(0.45), academicHeaderLabelCell())->addText(
        'ที่',
        'headerLabelFont',
        academicHeaderLabelPara()
    );

    $dateTable->addCell(Converter::cmToTwip(5.25), academicDottedBottomCell())->addText(
        academicCleanNoDigit($docNo ?: ''),
        'normalFont',
        academicDottedValuePara()
    );

    $dateTable->addCell(Converter::cmToTwip(1.10), academicHeaderLabelCell())->addText(
        'วันที่',
        'headerLabelFont',
        academicHeaderLabelPara(Jc::CENTER)
    );

    $dateTable->addCell(Converter::cmToTwip(9.20), academicDottedBottomCell())->addText(
        academicCleanNoDigit($thaiDocDate),
        'normalFont',
        academicDottedValueParaIndent(0.40)
    );
    // เรื่อง: ถ้ายาวให้แยกเป็นหลายแถว แต่ละแถวมีเส้นประของตัวเอง
    $subjectLines = academicSplitHeaderLines($subjectText, 86);
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
        $subjectTable->addCell(Converter::cmToTwip(0.90), academicNoBorderCell())->addText($i === 0 ? 'เรื่อง' : '', 'boldFont', academicHeaderPara());
        $subjectTable->addCell(Converter::cmToTwip(15.10), academicDottedBottomCell())->addText(academicCleanNoDigit($line), 'normalFont', academicHeaderParaIndent(0.30));
    }

    $learnTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $learnTable->addRow(null, ['exactHeight' => false]);
    $learnTable->addCell(Converter::cmToTwip(1.20), academicNoBorderCell())->addText('เรียน', 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 20,
        'spaceAfter' => 120,
        'lineHeight' => 1.0,
    ]);
    $learnTable->addCell(Converter::cmToTwip(14.80), academicNoBorderCell())->addText(academicCleanNoDigit($toText), 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 20,
        'spaceAfter' => 120,
        'lineHeight' => 1.0,
    ]);
}

// หน้าเอกสารหลัก: เนื้อหาฝึกอบรมให้ตรงกับ view_memo.php
function addAcademicMainMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $departmentFull, $faculty, $courseName, $location, $joinDates, $thaiYear, $hasExpense, $displayAmountNumber, $displayAmountThai, $deanName, $deanPosition)
{
    $section = addSectionPage($phpWord);
    addAcademicMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);

    $firstPara = [
        'ตามที่ กำหนดจัดอบรมหลักสูตร ',
        [$courseName ?: 'ชื่อหลักสูตร', true],
        ' ระหว่างวันที่ ',
        [$joinDates ?: '...', true],
        ' ณ ',
        [$location ?: '...', true],
        ' นั้น ',
        'ซึ่งหลักสูตรดังกล่าวเป็นประโยชน์ต่อการพัฒนาทั้งกระบวนการจัดการเรียนการสอน'
    ];

    addAcademicMemoManualPara($section, $firstPara);

    $secondPara = [
        'การนี้ ข้าพเจ้า ',
        [$ownerName ?: 'ชื่อ-นามสกุล', true],
        ' ',
        [$position ?: '', true],
        ' สังกัด',
        [$departmentFull ?: 'ภาควิชา...', true],
        ' ',
        [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี ',
        'จึงมีความประสงค์ที่จะขออนุมัติ เข้ารับการอบรมหลักสูตร ',
        [$courseName ?: 'ชื่อหลักสูตร', true],
        ' ระหว่างวันที่ ',
        [$joinDates ?: '', true],
        ' ณ ',
        [$location ?: '', true]
    ];

    if ($hasExpense) {
        $secondPara = array_merge($secondPara, [
            ' เป็นเงินจำนวน ',
            [$displayAmountNumber, true],
            ' บาท (',
            [$displayAmountThai, true],
            ') ',
            'โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ ',
            ['พ.ศ. ' . $thaiYear, true],
            ' แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย ',
            '(รายละเอียดตามเอกสารแนบ)'
        ]);
    } else {
        $secondPara[] = ' โดยไม่เบิกค่าใช้จ่ายใดๆ ทั้งสิ้น';
    }

    addAcademicMemoManualPara($section, $secondPara);
    addAcademicClosePara($section);
    addAcademicSignatureFixed($section, $ownerName, $position);

    addAcademicDeanApprovalWithAutoPage(
        $section,
        $toText,
        $deanName,
        $deanPosition,
        [
            academicPartsToPlainText($firstPara),
            academicPartsToPlainText($secondPara),
            'จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ'
        ],
        $subjectText
    );
}
function addAcademicExpenseMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $department, $departmentFull, $faculty, $subject, $courseName, $joinDates, $location, $displayAmountNumber, $displayAmountThai, $thaiYear, $deanName, $deanPosition)
{
    $section = addSectionPage($phpWord);
    addAcademicMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);

    $expensePara = [
        'การนี้ ข้าพเจ้า ',
        [$ownerName ?: 'ชื่อ-นามสกุล', true],
        ' ',
        [$position ?: '', true],
        ' สังกัด',
        [$departmentFull ?: ('ภาควิชา' . ($department ?: '...')), true],
        ' ',
        [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ขออนุมัติค่าใช้จ่ายในการเข้าร่วม ',
        [$courseName ?: $subject ?: 'ชื่อหลักสูตร', true],
        ' ระหว่างวันที่ ',
        [$joinDates ?: '', true],
        ' ณ ',
        [$location ?: '', true],
        ' วงเงินทั้งสิ้น ',
        [$displayAmountNumber, true],
        ' บาท (',
        [$displayAmountThai, true],
        ') โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ ',
        ['พ.ศ. ' . $thaiYear, true],
        ' ในส่วนของ',
        [$departmentFull ?: ('ภาควิชา' . ($department ?: '...')), true],
        ' แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย (รายละเอียดตามเอกสารแนบ)'
    ];

    addAcademicMemoManualPara($section, $expensePara);
    addAcademicClosePara($section);
    addAcademicSignatureFixed($section, $ownerName, $position);

    addAcademicDeanApprovalWithAutoPage(
        $section,
        $toText,
        $deanName,
        $deanPosition,
        [
            academicPartsToPlainText($expensePara),
            'จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ'
        ],
        $subjectText
    );
}
function addAcademicCarMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $departmentFull, $faculty, $subject, $courseName, $joinDates, $location, $vehicle, $deanName, $deanPosition)
{
    $section = addSectionPage($phpWord);
    addAcademicMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);

    $carFirstPara = [
        'ตามที่ ข้าพเจ้า ',
        [$ownerName ?: 'ชื่อ-นามสกุล', true],
        ' ',
        [$position ?: '', true],
        ' สังกัด',
        [$departmentFull, true],
        ' ',
        [$faculty, true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ที่จะขออนุมัติ ',
        [$courseName ?: $subject ?: 'ชื่อหลักสูตร', true],
        ' ระหว่างวันที่ ',
        [$joinDates ?: '', true],
        ' ณ ',
        [$location ?: '', true],
        ' นั้น'
    ];

    addAcademicMemoManualPara($section, $carFirstPara);

    $carSecondPara = [
        'ในการนี้ ข้าพเจ้าจึงขออนุมัติใช้รถยนต์ส่วนบุคคล หมายเลขทะเบียน ',
        [$vehicle ?: '...', true],
        ' ในการเดินทางไป ',
[$courseName ?: $subject ?: 'ชื่อหลักสูตร', true],
' ตามวัน เวลา และสถานที่ดังกล่าว ทั้งนี้ โดยให้เป็นไปตามหลักเกณฑ์และวิธีการของมหาวิทยาลัย'
    ];

    addAcademicMemoManualPara($section, $carSecondPara);
    addAcademicClosePara($section);
    addAcademicSignatureFixed($section, $ownerName, $position);

    addAcademicDeanApprovalWithAutoPage(
        $section,
        $toText,
        $deanName,
        $deanPosition,
        [
            academicPartsToPlainText($carFirstPara),
            academicPartsToPlainText($carSecondPara),
            'จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ'
        ],
        $subjectText
    );
}

// หน้าประมาณการค่าใช้จ่าย: จัด layout ให้เหมือนหน้าฟอร์มตัวอย่าง รูปที่ 3
function academicExpenseInfoRow($table, $label, $value, $spaceAfter = 0)
{
    $table->addRow(null, ['exactHeight' => false]);
    $table->addCell(Converter::cmToTwip(4.50), academicNoBorderCell('top'))->addText($label, 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.10,
    ]);
    $table->addCell(Converter::cmToTwip(11.50), academicNoBorderCell('top'))->addText(cleanWordText($value), 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.10,
    ]);
}

function addAcademicExpenseTablePage($phpWord, $budgetItems, $budgetTotal, $joinType, $courseName, $joinDates, $location)
{
    $section = addSectionPage($phpWord);

    $section->addText('ประมาณการค่าใช้จ่าย', 'boldFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 60,
        'lineHeight' => 1.0,
    ]);

    $section->addText('ค่าใช้จ่ายในการ' . ($joinType ?: 'เข้าร่วม'), 'boldFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 20,
        'lineHeight' => 1.0,
    ]);

    $coursePrefix = ($joinType === 'เข้ารับการฝึกอบรมหลักสูตร') ? 'หลักสูตร' : 'หัวข้อ/งาน';
    $section->addText($coursePrefix . ' “' . cleanWordText($courseName ?: '-') . '”', 'boldFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 20,
        'lineHeight' => 1.0,
    ]);

    $section->addText('ระหว่างวันที่ ' . cleanWordText($joinDates ?: '-'), 'boldFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 20,
        'lineHeight' => 1.0,
    ]);

    $section->addText('สถานที่ ' . cleanWordText($location ?: '-'), 'boldFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 160,
        'lineHeight' => 1.0,
    ]);

    $section->addText('ตารางประมาณการค่าใช้จ่าย', 'boldFont', [
        'spaceAfter' => 80,
        'lineHeight' => 1.0,
    ]);

    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 60,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);

    $table->addRow(Converter::cmToTwip(0.75));
    $table->addCell(Converter::cmToTwip(2.00), ['valign' => 'center'])->addText('ลำดับที่', 'boldFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.0]);
    $table->addCell(Converter::cmToTwip(9.80), ['valign' => 'center'])->addText('รายการ', 'boldFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.0]);
    $table->addCell(Converter::cmToTwip(4.20), ['valign' => 'center'])->addText('จำนวนเงิน (บาท)', 'boldFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.0]);

    if (!empty($budgetItems)) {
        foreach ($budgetItems as $i => $item) {
            $table->addRow(Converter::cmToTwip(0.75));
            $table->addCell(Converter::cmToTwip(2.00), ['valign' => 'center'])->addText((string) ($i + 1), 'normalFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.0]);
            $desc = cleanWordText($item['description'] ?: $item['item_type']);
            $table->addCell(Converter::cmToTwip(9.80), ['valign' => 'center'])->addText($desc, 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);
            $table->addCell(Converter::cmToTwip(4.20), ['valign' => 'center'])->addText(number_format((float) $item['amount'], 2), 'normalFont', ['alignment' => Jc::END, 'spaceAfter' => 0, 'lineHeight' => 1.0]);
        }
    } else {
        $table->addRow(Converter::cmToTwip(0.75));
        $table->addCell(Converter::cmToTwip(16.0), ['gridSpan' => 3, 'valign' => 'center'])->addText('ไม่พบข้อมูลประมาณค่าใช้จ่าย', 'normalFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.0]);
    }

    $table->addRow(Converter::cmToTwip(0.75));
    $table->addCell(Converter::cmToTwip(2.00), ['valign' => 'center'])->addText('', 'normalFont', ['spaceAfter' => 0]);
    $table->addCell(Converter::cmToTwip(9.80), ['valign' => 'center'])->addText('รวมเป็นเงิน', 'boldFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);
    $table->addCell(Converter::cmToTwip(4.20), ['valign' => 'center'])->addText(number_format($budgetTotal, 2), 'boldFont', ['alignment' => Jc::END, 'spaceAfter' => 0, 'lineHeight' => 1.0]);

   $noteRun = $section->addTextRun([
    'spaceBefore' => 120,
    'spaceAfter' => 0,
    'lineHeight' => 1.0,
]);

$noteRun->addText('หมายเหตุ', 'boldFont');
$noteRun->addText(' ขอถัวจ่ายทุกรายการ', 'normalFont');
}

$docDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$ownerName = academicField($valueMapByKey, $valueMap, 'owner_name', 2, '');
$position = academicField($valueMapByKey, $valueMap, 'position', 3, '');
$ownerProfileName = '';
$ownerProfilePosition = '';
try {
    $ownerStmt = $pdo->prepare("SELECT fullname, position FROM users WHERE user_id = :owner_id LIMIT 1");
    $ownerStmt->execute([':owner_id' => (int) ($document['owner_id'] ?? 0)]);
    $ownerProfile = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $ownerProfileName = trim((string) ($ownerProfile['fullname'] ?? ''));
    $ownerProfilePosition = trim((string) ($ownerProfile['position'] ?? ''));
} catch (Throwable $e) {
    $ownerProfileName = '';
    $ownerProfilePosition = '';
}
$displayOwnerName = $ownerProfileName !== '' ? $ownerProfileName : $ownerName;
$displayOwnerPosition = $ownerProfilePosition !== '' ? $ownerProfilePosition : $position;
$joinType = academicField($valueMapByKey, $valueMap, 'join_type', 4, '');
$courseName = academicField($valueMapByKey, $valueMap, 'course_name', 5, '');
$joinDates = academicField($valueMapByKey, $valueMap, 'join_date_range', 6, '');
$location = academicField($valueMapByKey, $valueMap, 'location', 7, '');
$amountStr = academicField($valueMapByKey, $valueMap, 'total_cost', 8, '');
$vehicle = academicField($valueMapByKey, $valueMap, 'vehicle', 9, '');
$faculty = academicField($valueMapByKey, $valueMap, 'faculty', 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม');
$department = academicField($valueMapByKey, $valueMap, 'department', 11, 'เทคโนโลยีสารสนเทศ');
$noCost = ((string) ($valueMap[12] ?? '0') === '1');
$academicTopic = academicField($valueMapByKey, $valueMap, 'academic_topic', 13, '');
$memoSubject = academicField($valueMapByKey, $valueMap, 'memo_subject', 14, (string) ($document['subject'] ?? ''));
$academicLevel = academicField($valueMapByKey, $valueMap, 'academic_level', 15, '');
$eventDate = academicField($valueMapByKey, $valueMap, 'event_date', 16, '');

$rawHeaderText = trim((string) ($document['header_text'] ?? ''));
$docNo = trim((string) ($document['doc_no'] ?? ''));
$subject = trim($memoSubject !== '' ? $memoSubject : (string) ($document['subject'] ?? ''));

$displayFaculty = academicCleanNoDigit($faculty);
if ($displayFaculty === '') {
    $displayFaculty = 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
}
if (mb_strpos($displayFaculty, 'คณะ') !== 0) {
    $displayFaculty = 'คณะ' . $displayFaculty;
}

$displayDepartment = academicCleanNoDigit($department);
$displayDepartmentFull = (mb_strpos($displayDepartment, 'ภาควิชา') === 0)
    ? $displayDepartment
    : 'ภาควิชา' . $displayDepartment;
$headerText = trim($displayFaculty . ' ' . $displayDepartmentFull . ($departmentPhone !== '' ? ' โทร. ' . $departmentPhone : ''));
if ($headerText === '') {
    $headerText = $rawHeaderText;
}
$headerText = preg_replace('/\s+/u', '', $headerText);
$displayFacultyDean = 'คณบดี' . $displayFaculty;

$deanName = '................................';
$deanPosition = $displayFacultyDean;
$facultyForDean = trim($displayFaculty);
$facultyForDeanNoPrefix = preg_replace('/^คณะ/u', '', $facultyForDean);
try {
    $deanStmt = $pdo->prepare("
        SELECT dean_name, dean_position
        FROM faculties
        WHERE faculty_name = :faculty_full
           OR faculty_name = :faculty_no_prefix
        LIMIT 1
    ");
    $deanStmt->execute([
        ':faculty_full' => $facultyForDean,
        ':faculty_no_prefix' => $facultyForDeanNoPrefix,
    ]);
    $deanRow = $deanStmt->fetch(PDO::FETCH_ASSOC);
    if ($deanRow) {
        $dbDeanName = trim((string) ($deanRow['dean_name'] ?? ''));
        $dbDeanPosition = trim((string) ($deanRow['dean_position'] ?? ''));
        if ($dbDeanName !== '') {
            $deanName = $dbDeanName;
        }
        if ($dbDeanPosition !== '') {
            $deanPosition = $dbDeanPosition;
        }
    }
} catch (Throwable $e) {
    $deanName = '................................';
    $deanPosition = $displayFacultyDean;
}

$thaiDocDate = academicThaiDateAnyArabicNumber($docDate);

$thaiYear = '';
if ($docDate && preg_match('/^\d{4}/', academicArabicDigit($docDate))) {
    $thaiYear = ((int) substr(academicArabicDigit($docDate), 0, 4) + 543);
}
if ($thaiYear === '') {
    $thaiYear = (int) date('Y') + 543;
}

$budgetTotal = 0;
foreach ($budgetItems as $item) {
    $budgetTotal += (float) ($item['amount'] ?? 0);
}
$hasExpense = (!$noCost && !empty($budgetItems) && $budgetTotal > 0);
$hasCar = trim($vehicle) !== '';
$displayAmount = $budgetTotal > 0 ? $budgetTotal : (float) str_replace(',', '', $amountStr);
$displayAmountNumber = number_format($displayAmount, 2);
$displayAmountThai = academicBahtText($displayAmount);
$sectionSubject = academicCleanSectionSubject($subject ?: $courseName ?: 'ชื่อหลักสูตร');
if ($sectionSubject === '') {
    $sectionSubject = $courseName ?: 'ชื่อหลักสูตร';
}

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

// Style เฉพาะย่อหน้าเนื้อหา: จัดเต็มบรรทัด แต่ไม่บังคับกระจายบรรทัดสุดท้าย
$phpWord->addParagraphStyle('academicBodyThaiDistribute', [
    'alignment' => 'thaiDistribute',
    'lineHeight' => 0.94,
    'spaceBefore' => 0,
    'spaceAfter' => 28,
    'indentation' => [
        'firstLine' => Converter::cmToTwip(2.5),
    ],
]);

addAcademicMainMemoPage(
    $phpWord,
    $docNo,
    $thaiDocDate,
    $headerText,
    'ขออนุมัติตัวบุคคลเข้าร่วม' . $sectionSubject,
    $displayFacultyDean,
    $ownerName,
    $position,
    $displayDepartmentFull,
    $displayFaculty,
    $courseName,
    $location,
    $joinDates,
    $thaiYear,
    $hasExpense,
    $displayAmountNumber,
    $displayAmountThai,
    $deanName,
    $deanPosition
);

if ($hasExpense) {
    addAcademicExpenseMemoPage(
        $phpWord,
        $docNo,
        $thaiDocDate,
        $headerText,
        'ขออนุมัติค่าใช้จ่ายในการเข้าร่วม' . $sectionSubject,
        $displayFacultyDean,
        $ownerName,
        $position,
        $department,
        $displayDepartmentFull,
        $displayFaculty,
        $subject,
        $courseName,
        $joinDates,
        $location,
        $displayAmountNumber,
        $displayAmountThai,
        $thaiYear,
        $deanName,
        $deanPosition
    );
}

if ($hasCar) {
    addAcademicCarMemoPage(
        $phpWord,
        $docNo,
        $thaiDocDate,
        $headerText,
        'ขออนุมัติใช้รถยนต์ส่วนบุคคลในการเดินทางไปเข้าร่วม' . $sectionSubject,
        $displayFacultyDean,
        $ownerName,
        $position,
        $displayDepartmentFull,
        $displayFaculty,
        $subject,
        $courseName,
        $joinDates,
        $location,
        $vehicle,
        $deanName,
        $deanPosition
    );
}

if ($hasExpense) {
    addAcademicExpenseTablePage(
        $phpWord,
        $budgetItems,
        $budgetTotal,
        $joinType,
        $courseName,
        $joinDates,
        $location
    );
}


// บังคับเฉพาะ paragraph style ของเนื้อหาให้เป็น justify ในไฟล์ docx จริง
// เผื่อ PHPWord/Word บางเวอร์ชันไม่ render alignment จาก style ได้ครบ
function academicForceBodyThaiDistributeInDocx($docxPath)
{
    if (!class_exists('ZipArchive') || !is_file($docxPath)) {
        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        return;
    }

    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false || $xml === '') {
        $zip->close();
        return;
    }

    $xml = preg_replace_callback('/<w:p\b[^>]*>.*?<\/w:p>/s', function ($m) {
        $p = $m[0];
        if (strpos($p, 'w:val="academicBodyThaiDistribute"') === false && strpos($p, "w:val='academicBodyThaiDistribute'") === false) {
            return $p;
        }

        if (preg_match('/<w:pPr\b[^>]*>.*?<\/w:pPr>/s', $p, $pm)) {
            $pPr = $pm[0];
            if (preg_match('/<w:jc\b[^\/]*(?:\/>|>.*?<\/w:jc>)/s', $pPr)) {
                $pPrNew = preg_replace('/<w:jc\b[^\/]*(?:\/>|>.*?<\/w:jc>)/s', '<w:jc w:val="both"/>', $pPr, 1);
            } else {
                $pPrNew = str_replace('</w:pPr>', '<w:jc w:val="both"/></w:pPr>', $pPr);
            }
            return str_replace($pPr, $pPrNew, $p);
        }

        return preg_replace('/<w:p\b([^>]*)>/s', '<w:p$1><w:pPr><w:pStyle w:val="academicBodyThaiDistribute"/><w:jc w:val="both"/></w:pPr>', $p, 1);
    }, $xml);

    $zip->addFromString('word/document.xml', $xml);
    $zip->close();
}

$filename = 'memo_' . $docId . '.docx';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$tmpDocx = tempnam(sys_get_temp_dir(), 'memo_word_');
if ($tmpDocx === false) {
    $writer->save('php://output');
    exit;
}
$writer->save($tmpDocx);
// ไม่แก้ XML หลังสร้างไฟล์โดยตรง เพราะอาจทำให้ Word แจ้งไฟล์เสีย
// ใช้ paragraph style 'thaiDistribute' ด้านบนแทน
// academicForceBodyThaiDistributeInDocx($tmpDocx);
readfile($tmpDocx);
@unlink($tmpDocx);
exit;