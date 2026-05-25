<?php
// Pro_letter/documents/word_templates/word_common.php.

use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Tab;

if (!defined('MEMO_CONTENT_CM')) {
    define('MEMO_CONTENT_CM', 16.0);
}

if (!defined('MEMO_CONTENT_TWIP')) {
    define('MEMO_CONTENT_TWIP', 9072);
}

/**
 * ตั้งค่า Font / Paragraph Style ทั้งหมดของ Word
 */
function setupWordDefaults($phpWord) {
    $phpWord->setDefaultFontName('TH SarabunPSK');
    $phpWord->setDefaultFontSize(16);

    $phpWord->addParagraphStyle('normalPara', [
        'alignment' => Jc::THAI_DISTRIBUTE,
        'lineHeight' => 1.25,
        'spaceBefore' => 0,
        'spaceAfter' => 80,
        'indentation' => ['firstLine' => Converter::cmToTwip(2.5)],
    ]);

    $phpWord->addParagraphStyle('singleLinePara', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $phpWord->addParagraphStyle('fieldPara', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.95,
    ]);

    $phpWord->addParagraphStyle('subjectLeaderPara', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.95,
        'tabs' => [
            new Tab('right', 9800, 'dot'),
        ],
    ]);

    $phpWord->addParagraphStyle('centerTitlePara', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $phpWord->addFontStyle('normalFont', [
        'name' => 'TH SarabunPSK',
        'size' => 16,
    ]);

    $phpWord->addFontStyle('fieldFont', [
        'name' => 'TH SarabunPSK',
        'size' => 16,
        'underline' => 'dotted',
    ]);

    // ใช้กับเส้นต่อท้ายเรื่อง ถ้าอยากให้จาง/แทบมองไม่เห็น
    $phpWord->addFontStyle('subjectTailThinFont', [
        'name' => 'TH SarabunPSK',
        'size' => 10,
        'underline' => 'dotted',
        'color' => 'BFBFBF',
    ]);

    $phpWord->addFontStyle('boldFont', [
        'name' => 'TH SarabunPSK',
        'size' => 16,
        'bold' => true,
    ]);

    $phpWord->addFontStyle('titleFont', [
        'name' => 'TH SarabunPSK',
        'size' => 29,
        'bold' => true,
    ]);

    $phpWord->addFontStyle('labelFont', [
        'name' => 'TH SarabunPSK',
        'size' => 20,
        'bold' => true,
    ]);
}

/**
 * ล้างข้อความก่อนใส่ Word
 */
function cleanWordText($text) {
    $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

/**
 * แปลงวันที่ yyyy-mm-dd เป็นวันที่ไทย
 */
function memoThaiDate($ymd) {
    if (!$ymd || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return '';
    }

    [$y, $m, $d] = explode('-', $ymd);

    $months = [
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
        12 => 'ธันวาคม'
    ];

    return (int)$d . ' ' . $months[(int)$m] . ' ' . ((int)$y + 543);
}

/**
 * แปลงจำนวนเงินเป็นข้อความภาษาไทย
 */
function memoBahtText($amount) {
    $amount = number_format((float)$amount, 2, '.', '');
    [$number, $satang] = explode('.', $amount);

    $txtNumArr = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    $txtDigitArr = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน', 'ล้าน'];

    $convert = function($num) use (&$convert, $txtNumArr, $txtDigitArr) {
        $num = (string)((int)$num);
        $len = strlen($num);
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $n = (int)$num[$i];
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

    $bahtText = ((int)$number === 0) ? 'ศูนย์บาท' : $convert($number) . 'บาท';

    return ((int)$satang === 0)
        ? $bahtText . 'ถ้วน'
        : $bahtText . $convert($satang) . 'สตางค์';
}

/**
 * สร้างหน้า A4
 */
function addSectionPage($phpWord) {
    return $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => Converter::cmToTwip(1.5),
        'marginBottom' => Converter::cmToTwip(1.5),
        'marginLeft' => Converter::cmToTwip(3.0),
        'marginRight' => Converter::cmToTwip(2.0),
    ]);
}

function wordCellNoBorder($leftMargin = 0, $rightMargin = 0, $noWrap = false, $topMargin = 0) {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMarginTop' => $topMargin,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => $leftMargin,
        'cellMarginRight' => $rightMargin,
        'valign' => 'bottom',
        'noWrap' => $noWrap,
    ];
}

function wordDottedBottomCell($leftMargin = 10, $rightMargin = 10, $noWrap = true) {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'borderBottomSize' => 0,
        'borderBottomColor' => 'FFFFFF',
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => $leftMargin,
        'cellMarginRight' => $rightMargin,
        'valign' => 'bottom',
        'noWrap' => $noWrap,
    ];
}

function addInlineText($cell, $text, $style = 'normalFont', $para = null) {
    $cell->addText(cleanWordText($text), $style, $para ?: 'singleLinePara');
}

function addDottedCellText($cell, $text, $fontStyle = 'normalFont', $baseFill = 40) {
    $value = cleanWordText($text ?: '');
    $len = mb_strlen($value, 'UTF-8');

    $fillCount = max(4, $baseFill - (int)floor($len * 0.45));
    $fill = str_repeat("\xC2\xA0", $fillCount);

    $cell->addText($value . $fill, 'fieldFont', 'fieldPara');
}

function addMemoTable($section) {
    return $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => MEMO_CONTENT_TWIP,
    ]);
}

/**
 * แถวทั่วไป เช่น ส่วนราชการ
 */
function addDottedRow($section, $label, $value, $labelWidth = 1350, $valueWidth = 7721, $spaceAfter = 0, $valueMarginLeft = 10, $baseFill = 40) {
    $table = addMemoTable($section);
    $table->addRow(Converter::cmToTwip(0.52), ['exactHeight' => false]);

    $cell = $table->addCell(MEMO_CONTENT_TWIP, wordCellNoBorder(0, 0, true));
    $run = $cell->addTextRun('fieldPara');

    if ($label !== '') {
        $run->addText(cleanWordText($label) . ' ', 'labelFont');
    } else {
        $run->addText(str_repeat("\xC2\xA0", 7), 'normalFont');
    }

    $valueText = cleanWordText($value ?: '');
    $len = mb_strlen($valueText, 'UTF-8');

    $fillCount = max(4, $baseFill - (int)floor($len * 0.45));
    $fill = str_repeat("\xC2\xA0", $fillCount);

    $run->addText($valueText . $fill, 'fieldFont');

    if ($spaceAfter > 0) {
        $section->addText('', 'normalFont', [
            'spaceAfter' => $spaceAfter,
            'lineHeight' => 1.0
        ]);
    }
}

/**
 * แถวเรื่อง
 */
function addSubjectDottedRow($section, $label, $value, $targetChars = 95) {
    $table = addMemoTable($section);
    $table->addRow(Converter::cmToTwip(0.52), ['exactHeight' => false]);

    $cell = $table->addCell(MEMO_CONTENT_TWIP, wordCellNoBorder(0, 0, true));
    $run = $cell->addTextRun('subjectLeaderPara');

    $valueText = cleanWordText($value ?: '');

    if ($label !== '') {
        $run->addText(cleanWordText($label) . ' ', 'labelFont');
    } else {
        $run->addText(str_repeat("\xC2\xA0", 7), 'normalFont');
    }

    // ข้อความจริงมีเส้นใต้แบบจุด
    $run->addText($valueText, 'fieldFont');

    // เส้นต่อท้ายหลังข้อความเรื่อง ทำให้จาง/แทบมองไม่เห็น
    $run->addText("\t", 'subjectTailSoftFont');
}

/**
 * แถว ที่ / วันที่
 */
function addDocNoDateRow($section, $docNo, $thaiDocDate) {
    $table = addMemoTable($section);
    $table->addRow(Converter::cmToTwip(0.52), ['exactHeight' => false]);

    $cell = $table->addCell(MEMO_CONTENT_TWIP, wordCellNoBorder(0, 0, true));
    $run = $cell->addTextRun('fieldPara');

    $docNoText = cleanWordText($docNo ?: 'ทส.486/2568');
    $dateText = str_replace(' ', "\xC2\xA0", cleanWordText($thaiDocDate ?: ' '));

    $docFill = str_repeat("\xC2\xA0", 28);
    $dateFill = str_repeat("\xC2\xA0", 43);

    $run->addText('ที่ ', 'labelFont');
    $run->addText($docNoText . $docFill, 'fieldFont');

    $run->addText(str_repeat("\xC2\xA0", 4), 'normalFont');

    $run->addText('วันที่ ', 'labelFont');
    $run->addText($dateText . $dateFill, 'fieldFont');
}

/**
 * ตัดหัวข้อเรื่อง
 */
function splitSubjectLines($subjectText, $maxLines = 2, $limit = 78) {
    $subjectText = trim(cleanWordText($subjectText));

    if ($subjectText === '') {
        return [''];
    }

    $lines = [];

    while (mb_strlen($subjectText, 'UTF-8') > $limit && count($lines) < ($maxLines - 1)) {
        $cut = mb_substr($subjectText, 0, $limit, 'UTF-8');
        $spacePos = mb_strrpos($cut, ' ', 0, 'UTF-8');

        if ($spacePos !== false && $spacePos > 35) {
            $lines[] = trim(mb_substr($subjectText, 0, $spacePos, 'UTF-8'));
            $subjectText = trim(mb_substr($subjectText, $spacePos + 1, null, 'UTF-8'));
        } else {
            $lines[] = trim($cut);
            $subjectText = trim(mb_substr($subjectText, $limit, null, 'UTF-8'));
        }
    }

    if ($subjectText !== '') {
        $lines[] = $subjectText;
    }

    return $lines ?: [''];
}

/**
 * หัวเอกสาร บันทึกข้อความ
 */
function addMemoTitle($section) {
    // word_common.php อยู่ใน documents/word_templates/
    $garuda = __DIR__ . '/../../assets/img/garuda.jpg';

    $table = addMemoTable($section);
    $table->addRow(Converter::cmToTwip(1.55));

    $left = $table->addCell(Converter::cmToTwip(2.3), wordCellNoBorder());

    if (file_exists($garuda)) {
        $left->addImage($garuda, [
            'width' => 43,
            'height' => 43,
            'alignment' => Jc::LEFT,
        ]);
    } else {
        $left->addText('');
    }

    $mid = $table->addCell(Converter::cmToTwip(11.4), wordCellNoBorder());
    $mid->addText('บันทึกข้อความ', 'titleFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 250,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $right = $table->addCell(Converter::cmToTwip(2.3), wordCellNoBorder());
    $right->addText('');

    $section->addText('', 'normalFont', [
        'spaceAfter' => 80,
        'lineHeight' => 1.0
    ]);
}

/**
 * หัวเอกสารเต็ม
 */
function addMemoHeader($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $subjectMaxLines = 2) {
    addMemoTitle($section);

    $labelW = Converter::cmToTwip(2.45);
    $valueW = MEMO_CONTENT_TWIP - $labelW;

    addDottedRow(
        $section,
        'ส่วนราชการ',
        $headerText ?: 'คณะ... ภาค... โทร...',
        $labelW,
        $valueW,
        0,
        10
    );

    addDocNoDateRow($section, $docNo, $thaiDocDate);

    $subjectLabelW = Converter::cmToTwip(0.95);
    $subjectValueW = MEMO_CONTENT_TWIP - $subjectLabelW;

    $subjectLines = splitSubjectLines($subjectText, $subjectMaxLines, 78);

    foreach ($subjectLines as $index => $line) {
        addSubjectDottedRow(
            $section,
            $index === 0 ? 'เรื่อง' : '',
            $line,
            95
        );
    }

    $learnTable = addMemoTable($section);
    $learnTable->addRow(Converter::cmToTwip(0.42), ['exactHeight' => false]);

    $learnLabel = $learnTable->addCell($subjectLabelW, wordCellNoBorder(0, 0, true));
    addInlineText($learnLabel, 'เรียน', 'normalFont');

    $learnText = $learnTable->addCell($subjectValueW, wordCellNoBorder(360, 10));
    addInlineText($learnText, cleanWordText($toText), 'normalFont');

    $section->addText('', 'normalFont', [
        'spaceAfter' => 220,
        'lineHeight' => 1.0
    ]);
}

/**
 * ข้อความในย่อหน้า
 */
function wordRunText($text) {
    $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\r\n\t]+/u', ' ', $text);
    $text = preg_replace('/ {2,}/u', ' ', $text);
    return $text;
}

function addTextRunPara($section, array $parts) {
    $run = $section->addTextRun('normalPara');

    foreach ($parts as $part) {
        $text = is_array($part) ? ($part[0] ?? '') : $part;
        $bold = is_array($part) ? (bool)($part[1] ?? false) : false;

        $run->addText(
            wordRunText($text),
            $bold ? 'boldFont' : 'normalFont'
        );
    }
}

/**
 * ลายเซ็น
 */
function addSignature($section, $ownerName, $position) {
    $section->addTextBreak(2);

    $noBorder = [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'borderTopSize' => 0,
        'borderBottomSize' => 0,
        'borderLeftSize' => 0,
        'borderRightSize' => 0,
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => 0,
        'cellMarginRight' => 0,
    ];

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => MEMO_CONTENT_TWIP,
    ]);

    $table->addRow();
    $table->addCell(Converter::cmToTwip(9.5), $noBorder)->addText('');

    $cell = $table->addCell(Converter::cmToTwip(6.5), $noBorder);

    $cell->addText(
        '(' . cleanWordText($ownerName ?: '................................') . ')',
        'normalFont',
        [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 0,
            'lineHeight' => 1.0
        ]
    );

    $cell->addText(
        cleanWordText($position ?: '................................'),
        'normalFont',
        [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 0,
            'lineHeight' => 1.0
        ]
    );
}