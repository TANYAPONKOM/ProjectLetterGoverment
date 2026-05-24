<?php
// pro_letter/documents/download_word_memo.php
// ดาวน์โหลด Word (.docx) สำหรับเทมเพลตบันทึกข้อความหลัก (ทดลอง 1 เทมเพลตก่อน)

session_start();
require_once __DIR__ . '/../functions.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit('ยังไม่ได้ติดตั้ง PHPWord: กรุณารัน composer require phpoffice/phpword ที่โฟลเดอร์ Pro_letter ก่อน');
}
require_once $autoload;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Tab;

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

$q = $pdo->prepare("SELECT field_id, value_text FROM document_values WHERE document_id = :id");
$q->execute([':id' => $docId]);
$valueMap = [];
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $valueMap[(int)$row['field_id']] = (string)$row['value_text'];
}

$budgetStmt = $pdo->prepare("\n    SELECT item_type, description, amount\n    FROM budget_items\n    WHERE document_id = :id\n    ORDER BY item_id ASC\n");
$budgetStmt->execute([':id' => $docId]);
$budgetItems = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

function memoThaiDate($ymd) {
    if (!$ymd || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return '';
    [$y, $m, $d] = explode('-', $ymd);
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    return (int)$d . ' ' . $months[(int)$m] . ' ' . ((int)$y + 543);
}

function memoBahtText($amount) {
    $amount = number_format((float)$amount, 2, '.', '');
    [$number, $satang] = explode('.', $amount);
    $txtNumArr = ['ศูนย์','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า'];
    $txtDigitArr = ['','สิบ','ร้อย','พัน','หมื่น','แสน','ล้าน'];
    $convert = function($num) use (&$convert, $txtNumArr, $txtDigitArr) {
        $num = (string)((int)$num);
        $len = strlen($num);
        $result = '';
        for ($i = 0; $i < $len; $i++) {
            $n = (int)$num[$i];
            $pos = $len - $i - 1;
            if ($n === 0) continue;
            if ($pos === 0 && $n === 1 && $len > 1) $result .= 'เอ็ด';
            elseif ($pos === 1 && $n === 2) $result .= 'ยี่';
            elseif ($pos === 1 && $n === 1) $result .= '';
            else $result .= $txtNumArr[$n];
            $result .= $txtDigitArr[$pos];
        }
        return $result;
    };
    $bahtText = ((int)$number === 0) ? 'ศูนย์บาท' : $convert($number) . 'บาท';
    return ((int)$satang === 0) ? $bahtText . 'ถ้วน' : $bahtText . $convert($satang) . 'สตางค์';
}

function cleanWordText($text) {
    $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

$docDate     = $valueMap[1] ?? $document['doc_date'];
$ownerName   = $valueMap[2] ?? '';
$position    = $valueMap[3] ?? '';
$joinType    = $valueMap[4] ?? '';
$courseName  = $valueMap[5] ?? '';
$joinDates   = $valueMap[6] ?? '';
$location    = $valueMap[7] ?? '';
$amountStr   = $valueMap[8] ?? '';
$vehicle     = $valueMap[9] ?? '';
$faculty     = $valueMap[10] ?? '';
$department  = $valueMap[11] ?? '';
$noCost      = (($valueMap[12] ?? '0') === '1');
$researchTitle = $valueMap[13] ?? '';

$headerText = $document['header_text'] ?? '';
$docNo = $document['doc_no'] ?? '';
$subject = $document['subject'] ?? '';
$thaiDocDate = memoThaiDate($docDate);

$budgetTotal = 0;
foreach ($budgetItems as $item) {
    $budgetTotal += (float)$item['amount'];
}
$displayAmount = $budgetTotal > 0 ? $budgetTotal : (float)str_replace(',', '', $amountStr);
$displayAmountNumber = number_format($displayAmount, 2);
$displayAmountThai = memoBahtText($displayAmount);
$hasExpense = (!$noCost && !empty($budgetItems) && $budgetTotal > 0);
$hasCar = trim($vehicle) !== '';
$thaiYear = '';
if ($docDate && preg_match('/^\d{4}/', $docDate)) {
    $thaiYear = ((int)substr($docDate, 0, 4) + 543);
}

$purposeCode = 'other';
switch (trim($joinType)) {
    case 'นำเสนอผลงานทางวิชาการ':
    case 'นำเสนอผลงานวิจัย':
        $purposeCode = 'academic';
        break;
    case 'เข้าร่วมประชุมวิชาการในงาน':
        $purposeCode = 'meeting';
        break;
    case 'เข้ารับการฝึกอบรมหลักสูตร':
        $purposeCode = 'training';
        break;
}

$toText = 'คณบดี' . ($faculty ?: 'คณะ..................................');
$deptText = $department ? 'ภาควิชา' . $department : 'ภาควิชา........................';
$facultyText = $faculty ?: 'คณะ........................';

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('TH SarabunPSK');
$phpWord->setDefaultFontSize(16);

// ===== ค่ามาตรฐานหนังสือภายใน =====
// A4 กว้าง 21 ซม. / ขอบซ้าย 3 ซม. / ขอบขวา 2 ซม. => พื้นที่เขียน 16 ซม.
const MEMO_CONTENT_CM = 16.0;
const MEMO_CONTENT_TWIP = 9072; // 16 ซม. จากขอบซ้าย 3 ซม. / ขอบขวา 2 ซม.

$phpWord->addParagraphStyle('normalPara', [
    'alignment' => Jc::LEFT,
    'lineHeight' => 1.25,
    'spaceBefore' => 0,
    'spaceAfter' => 120,
    // ตามตัวอย่างหนังสือภายใน: ย่อหน้า 2.5 ซม.
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
    // ลด line-height ให้เส้น dotted ที่เป็น border-bottom ขยับขึ้นมาใกล้ข้อความมากขึ้น
    'lineHeight' => 0.58,
]);
$phpWord->addParagraphStyle('centerTitlePara', [
    'alignment' => Jc::CENTER,
    'spaceBefore' => 0,
    'spaceAfter' => 0,
    'lineHeight' => 1.0,
]);
$phpWord->addFontStyle('normalFont', ['name' => 'TH SarabunPSK', 'size' => 16]);
$phpWord->addFontStyle('boldFont', ['name' => 'TH SarabunPSK', 'size' => 16, 'bold' => true]);
$phpWord->addFontStyle('titleFont', ['name' => 'TH SarabunPSK', 'size' => 29, 'bold' => true]);
$phpWord->addFontStyle('labelFont', ['name' => 'TH SarabunPSK', 'size' => 20, 'bold' => true]);

function addSectionPage($phpWord) {
    return $phpWord->addSection([
        'paperSize' => 'A4',
        // อ้างอิงรูปแบบหนังสือภายใน: บนประมาณ 1.5 ซม. / ซ้าย 3 ซม. / ขวา 2 ซม.
        'marginTop' => Converter::cmToTwip(1.5),
        'marginBottom' => Converter::cmToTwip(1.5),
        'marginLeft' => Converter::cmToTwip(3.0),
        'marginRight' => Converter::cmToTwip(2.0),
    ]);
}

function wordCellNoBorder($leftMargin = 0, $rightMargin = 0, $noWrap = false) {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => $leftMargin,
        'cellMarginRight' => $rightMargin,
        'valign' => 'bottom',
        'noWrap' => $noWrap,
    ];
}

function wordDottedBottomCell($leftMargin = 10, $rightMargin = 10, $noWrap = false) {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        // ใช้เส้นล่างของ cell เป็นเส้นประเต็มช่อง
        // ลด margin ล่างและความสูงแถวเพื่อให้เส้นประอยู่ชิดข้อความมากขึ้น
        'borderBottomSize' => 3,
        'borderBottomColor' => '000000',
        'borderBottomStyle' => 'dotted',
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

function addDottedCellText($cell, $text, $fontStyle = 'normalFont') {
    // ใช้เส้นขอบล่างแบบ dotted ของ cell เพื่อให้เส้นประอยู่ใต้ข้อความและเต็มช่อง
    // ไม่ใช้ tab leader เพราะ Word จะทำจุดอยู่บรรทัดเดียวกับตัวอักษรและดูเละ
    $cell->addText(cleanWordText($text ?: ' '), $fontStyle, 'fieldPara');
}

function addMemoTable($section) {
    return $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        // บังคับตารางให้กินความกว้างพื้นที่เขียน 16 ซม. ไม่หดตามข้อความ
        // ทำให้เส้นประของ cell ยาวเต็มบรรทัดตามระยะขอบเอกสาร
        'layout' => 'fixed',
        'width' => MEMO_CONTENT_TWIP,
    ]);
}

function addDottedRow($section, $label, $value, $labelWidth = 1350, $valueWidth = 7721, $spaceAfter = 0, $valueMarginLeft = 10) {
    $table = addMemoTable($section);
    $table->addRow(Converter::cmToTwip(0.31), ['exactHeight' => false]);

    $labelCell = $table->addCell($labelWidth, wordCellNoBorder(0, 0, true));
    addInlineText($labelCell, $label !== '' ? $label : ' ', $label !== '' ? 'labelFont' : 'normalFont');

    $valueCell = $table->addCell($valueWidth, wordDottedBottomCell($valueMarginLeft, 10));
    addDottedCellText($valueCell, $value ?: ' ', 'normalFont');

    if ($spaceAfter > 0) {
        $section->addText('', 'normalFont', ['spaceAfter' => $spaceAfter, 'lineHeight' => 1.0]);
    }
}

function addDocNoDateRow($section, $docNo, $thaiDocDate) {
    $table = addMemoTable($section);
    $table->addRow(Converter::cmToTwip(0.31), ['exactHeight' => false]);

    $wLabel1 = Converter::cmToTwip(0.65);
    // ช่องนี้ต้องยาวไปจนชิดคำว่า วันที่ เพื่อให้เส้นประต่อจาก “ที่” ไปถึง “วันที่”
    $wDocNo  = Converter::cmToTwip(6.05);
    $wLabel2 = Converter::cmToTwip(1.20);
    $wDate   = MEMO_CONTENT_TWIP - $wLabel1 - $wDocNo - $wLabel2;

    $c1 = $table->addCell($wLabel1, wordCellNoBorder(0, 0, true));
    addInlineText($c1, 'ที่', 'labelFont');

    $c2 = $table->addCell($wDocNo, wordDottedBottomCell(10, 10));
    addDottedCellText($c2, $docNo ?: 'ทส.486/2568', 'normalFont');

    // คำว่า วันที่ เป็นช่องคั่น ไม่มีเส้นใต้ เพื่อให้เส้นประขาดเฉพาะตำแหน่งคำนี้
    $c3 = $table->addCell($wLabel2, wordCellNoBorder(0, 0, true));
    addInlineText($c3, 'วันที่', 'labelFont');

    $c4 = $table->addCell($wDate, wordDottedBottomCell(10, 10));
    addDottedCellText($c4, $thaiDocDate ?: ' ', 'normalFont');
}

function splitSubjectLines($subjectText) {
    $subjectText = trim(cleanWordText($subjectText));
    if ($subjectText === '') return [''];

    $len = mb_strlen($subjectText, 'UTF-8');

    // คุมการแบ่งชื่อเรื่องเอง ไม่ปล่อยให้ Word wrap เอง
    // ถ้าปล่อยให้ Word wrap เอง ข้อความจะไปกองอยู่บนเส้นประบรรทัดแรก
    if ($len <= 72) {
        return [$subjectText];
    }

    if ($len <= 150) {
        $target = (int)ceil($len / 2);
        $bestPos = false;
        $bestDistance = 9999;

        // หาเว้นวรรคใกล้กลางข้อความ เพื่อให้ 2 บรรทัดสมดุล
        $offset = 0;
        while (($pos = mb_strpos($subjectText, ' ', $offset, 'UTF-8')) !== false) {
            if ($pos >= 45 && $pos <= 90) {
                $distance = abs($pos - $target);
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestPos = $pos;
                }
            }
            $offset = $pos + 1;
        }

        if ($bestPos !== false) {
            return [
                trim(mb_substr($subjectText, 0, $bestPos, 'UTF-8')),
                trim(mb_substr($subjectText, $bestPos + 1, null, 'UTF-8')),
            ];
        }
    }

    // กรณียาวมาก ค่อยตัดหลายบรรทัด โดยคุมไม่ให้แต่ละบรรทัดยาวจน Word wrap เอง
    $limit = 72;
    $lines = [];
    while (mb_strlen($subjectText, 'UTF-8') > $limit) {
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

function addMemoTitle($section) {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    // ใช้ตารางแบบไม่มีเส้นเพื่อจัดครุฑกับหัวเรื่องให้อยู่บรรทัดเดียวกัน
    // ไม่ใช่ตารางมีกรอบ จึงไม่เกิดเส้นตารางในเอกสาร
    $table = addMemoTable($section);
    $table->addRow(Converter::cmToTwip(1.55));

    $left = $table->addCell(Converter::cmToTwip(2.3), wordCellNoBorder());
    if (file_exists($garuda)) {
        $left->addImage($garuda, [
            // ครุฑหนังสือภายในสูงประมาณ 1.5 ซม.
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

    // ระยะหลังหัวเรื่องก่อนส่วนราชการ
    $section->addText('', 'normalFont', ['spaceAfter' => 80, 'lineHeight' => 1.0]);
}

function addMemoHeader($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText) {
    addMemoTitle($section);

    $labelW = Converter::cmToTwip(2.45);
    $valueW = MEMO_CONTENT_TWIP - $labelW;
    addDottedRow($section, 'ส่วนราชการ', $headerText ?: 'คณะ... ภาค... โทร...', $labelW, $valueW, 0, 10);
    addDocNoDateRow($section, $docNo, $thaiDocDate);

    $subjectLabelW = Converter::cmToTwip(0.95);
    $subjectValueW = MEMO_CONTENT_TWIP - $subjectLabelW;
    $subjectLines = splitSubjectLines($subjectText);
    foreach ($subjectLines as $index => $line) {
        addDottedRow($section, $index === 0 ? 'เรื่อง' : '', $line, $subjectLabelW, $subjectValueW, 0, 360);
    }

    // เรียน ไม่ต้องมีเส้นประ แต่ต้องเริ่มแนวข้อความตรงกับข้อมูลของเรื่อง
    $learnTable = addMemoTable($section);
    $learnTable->addRow(Converter::cmToTwip(0.42), ['exactHeight' => false]);
    $learnLabel = $learnTable->addCell($subjectLabelW, wordCellNoBorder(0, 0, true));
    addInlineText($learnLabel, 'เรียน', 'normalFont');
    $learnText = $learnTable->addCell($subjectValueW, wordCellNoBorder(360, 10));
    addInlineText($learnText, cleanWordText($toText), 'normalFont');

    // ตามตัวอย่าง: 1 Enter + Before 6 pt ก่อนเนื้อหา
    $section->addText('', 'normalFont', ['spaceAfter' => 120, 'lineHeight' => 1.0]);
}

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
        $run->addText(wordRunText($text), $bold ? 'boldFont' : 'normalFont');
    }
}

function addSignature($section, $ownerName, $position) {
    $section->addTextBreak(2);
    $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
    $table->addRow();
    $table->addCell(5000)->addText('');
    $cell = $table->addCell(4300);
    $cell->addText('(' . cleanWordText($ownerName) . ')', 'normalFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $cell->addText(cleanWordText($position), 'normalFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
}

function addMainMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $department, $faculty, $courseName, $joinDates, $location, $hasExpense, $displayAmountNumber, $displayAmountThai, $thaiYear) {
    $section = addSectionPage($phpWord);
    addMemoHeader($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);
    addTextRunPara($section, [
        'ตามที่ กำหนดจัดอบรมหลักสูตร ', [$courseName ?: 'ชื่อหลักสูตร', true],
        ' ระหว่างวันที่ ', [$joinDates ?: '...', true],
        ' ณ ', [$location ?: '...', true],
        ' นั้น ซึ่งหลักสูตรดังกล่าวเป็นประโยชน์ต่อการพัฒนาทั้งกระบวนการจัดการเรียนการสอน'
    ]);
    $expenseText = $hasExpense
        ? ' เป็นเงินจำนวน ' . $displayAmountNumber . ' บาท (' . $displayAmountThai . ') โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ ' . ($thaiYear ? 'พ.ศ. ' . $thaiYear : 'พ.ศ. ....') . ' แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย (รายละเอียดตามเอกสารแนบ)'
        : ' โดยไม่เบิกค่าใช้จ่ายใดๆ ทั้งสิ้น';
    addTextRunPara($section, [
        'การนี้ ข้าพเจ้า ', [$ownerName ?: 'ชื่อ-นามสกุล', true], ' ', [$position ?: '', true],
        ' สังกัดภาควิชา', [$department ?: '...', true], ' ', [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ที่จะขออนุมัติ เข้ารับการอบรมหลักสูตร ',
        [$courseName ?: 'ชื่อหลักสูตร', true], ' ระหว่างวันที่ ', [$joinDates ?: '', true], ' ณ ', [$location ?: '', true], $expenseText
    ]);
    addTextRunPara($section, ['จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ']);
    addSignature($section, $ownerName, $position);
}

function addExpenseMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $department, $faculty, $subject, $joinDates, $location, $displayAmountNumber, $displayAmountThai, $thaiYear) {
    $section = addSectionPage($phpWord);
    addMemoHeader($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);
    addTextRunPara($section, [
        'การนี้ ข้าพเจ้า ', [$ownerName ?: 'ชื่อ-นามสกุล', true], ' ', [$position ?: '', true],
        ' สังกัดภาควิชา', [$department ?: '...', true], ' ', [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ขออนุมัติค่าใช้จ่ายในการเข้าร่วม ',
        [$subject ?: 'ขออนุมัติ...', true], ' ระหว่างวันที่ ', [$joinDates ?: '', true], ' ณ ', [$location ?: '', true],
        ' วงเงินทั้งสิ้น ', [$displayAmountNumber, true], ' บาท (', [$displayAmountThai, true], ') โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ ',
        [($thaiYear ? 'พ.ศ. ' . $thaiYear : 'พ.ศ. ....'), true], ' ในส่วนของภาควิชา', [$department ?: '...', true], ' แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย (รายละเอียดตามเอกสารแนบ)'
    ]);
    addTextRunPara($section, ['จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ']);
    addSignature($section, $ownerName, $position);
}

function addCarMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $department, $faculty, $courseName, $joinDates, $location, $vehicle, $subject) {
    $section = addSectionPage($phpWord);
    addMemoHeader($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);
    addTextRunPara($section, [
        'ตามที่ ข้าพเจ้า ', [$ownerName ?: 'ชื่อ-นามสกุล', true], ' ', [$position ?: '', true],
        ' สังกัดภาควิชา', [$department ?: '...', true], ' ', [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ที่จะขออนุมัติ เข้ารับการอบรมหลักสูตร ',
        [$courseName ?: 'ชื่อหลักสูตร', true], ' ระหว่างวันที่ ', [$joinDates ?: '', true], ' ณ ', [$location ?: '', true], ' นั้น'
    ]);
    addTextRunPara($section, [
        'ในการนี้ ข้าพเจ้าจึงขออนุมัติใช้รถยนต์ส่วนบุคคล หมายเลขทะเบียน ', [$vehicle ?: '...', true],
        ' ในการเดินทางไป', [$subject ?: 'ชื่อหลักสูตร', true], ' ตามวัน เวลา และสถานที่ดังกล่าว ทั้งนี้ โดยให้เป็นไปตามหลักเกณฑ์และวิธีการของมหาวิทยาลัย'
    ]);
    addTextRunPara($section, ['จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ']);
    addSignature($section, $ownerName, $position);
}

function addExpenseTablePage($phpWord, $budgetItems, $budgetTotal, $purposeCode, $ownerName, $department, $faculty, $courseName, $joinDates, $location, $researchTitle, $joinType) {
    $section = addSectionPage($phpWord);
    $section->addText('ประมาณการค่าใช้จ่าย', 'boldFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
    if ($purposeCode === 'academic') {
        $section->addText('การนำเสนอผลงานวิจัยในการประชุมวิชาการ', 'boldFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);
        $info = [
            ['ชื่อ–สกุล', $ownerName ?: '-'],
            ['มหาวิทยาลัยต้นสังกัด', 'ภาควิชา' . ($department ?: '-') . ' ' . ($faculty ?: '-') . ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี'],
            ['ชื่อการประชุมวิชาการ', $courseName ?: '-'],
            ['วันที่', $joinDates ?: '-'],
            ['สถานที่', $location ?: '-'],
            ['ชื่อผลงานวิจัย', $researchTitle ?: '-'],
        ];
        foreach ($info as [$label, $val]) {
            $run = $section->addTextRun(['spaceAfter' => 20]);
            $run->addText($label . '    ', 'normalFont');
            $run->addText(cleanWordText($val), 'normalFont');
        }
        $section->addTextBreak(1);
    } else {
        $section->addText('ค่าใช้จ่ายในการ' . ($joinType ?: 'เข้าร่วม'), 'boldFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $section->addText(($purposeCode === 'training' ? 'หลักสูตร ' : 'หัวข้อ/งาน ') . '“' . ($courseName ?: '-') . '”', 'boldFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $section->addText('ระหว่างวันที่ ' . ($joinDates ?: '-'), 'boldFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 20]);
        $section->addText('สถานที่ ' . ($location ?: '-'), 'boldFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);
    }

    $section->addText('ตารางสรุปค่าใช้จ่ายในการไปนำเสนอผลงานวิจัย', 'boldFont', ['spaceAfter' => 80]);
    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 60,
        'width' => 100 * 50,
        'unit' => 'pct'
    ]);
    $table->addRow();
    $table->addCell(900)->addText('ลำดับที่', 'boldFont', ['alignment' => Jc::CENTER]);
    $table->addCell(6000)->addText('รายการ', 'boldFont', ['alignment' => Jc::CENTER]);
    $table->addCell(1700)->addText('จำนวนเงิน (บาท)', 'boldFont', ['alignment' => Jc::CENTER]);

    if (!empty($budgetItems)) {
        foreach ($budgetItems as $i => $item) {
            $table->addRow();
            $table->addCell(900)->addText((string)($i + 1), 'normalFont', ['alignment' => Jc::CENTER]);
            $desc = cleanWordText($item['description'] ?: $item['item_type']);
            $table->addCell(6000)->addText($desc, 'normalFont');
            $table->addCell(1700)->addText(number_format((float)$item['amount'], 2), 'normalFont', ['alignment' => Jc::END]);
        }
    } else {
        $table->addRow();
        $table->addCell(8600, ['gridSpan' => 3])->addText('ไม่พบข้อมูลประมาณค่าใช้จ่าย', 'normalFont', ['alignment' => Jc::CENTER]);
    }

    $table->addRow();
    $table->addCell(900)->addText('');
    $table->addCell(6000)->addText('รวมเป็นเงิน', 'boldFont');
    $table->addCell(1700)->addText(number_format($budgetTotal, 2), 'boldFont', ['alignment' => Jc::END]);
    $section->addText('หมายเหตุ ขอถัวจ่ายทุกรายการ', 'normalFont', ['spaceBefore' => 120]);
}

$mainSubject = 'ขออนุมัติตัวบุคคลเข้าร่วม' . ($subject ?: 'ขออนุมัติ...');
addMainMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $mainSubject, $toText, $ownerName, $position, $department, $faculty, $courseName, $joinDates, $location, $hasExpense, $displayAmountNumber, $displayAmountThai, $thaiYear);

if ($hasExpense) {
    $expenseSubject = 'ขออนุมัติค่าใช้จ่ายในการเข้าร่วม' . ($subject ?: 'ขออนุมัติ...');
    addExpenseMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $expenseSubject, $toText, $ownerName, $position, $department, $faculty, $subject, $joinDates, $location, $displayAmountNumber, $displayAmountThai, $thaiYear);
}

if ($hasCar) {
    $carSubject = 'ขออนุมัติใช้รถยนต์ส่วนบุคคลในการเดินทางไปเข้าร่วม' . ($subject ?: 'ขออนุมัติ...');
    addCarMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $carSubject, $toText, $ownerName, $position, $department, $faculty, $courseName, $joinDates, $location, $vehicle, $subject);
}

if ($hasExpense) {
    addExpenseTablePage($phpWord, $budgetItems, $budgetTotal, $purposeCode, $ownerName, $department, $faculty, $courseName, $joinDates, $location, $researchTitle, $joinType);
}

$filename = 'memo_' . $docId . '.docx';
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