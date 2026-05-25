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

// เรียกใช้ฟังก์ชันกลางสำหรับสร้าง Word
require_once __DIR__ . '/word_templates/word_common.php';
require_once __DIR__ . '/word_templates/word_academic_1.php';

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
setupWordDefaults($phpWord);


// ==========================================
// ฟังก์ชันจัดการระยะและการตัดคำภาษาไทยที่ได้รับการปรับปรุงกลุ่มคำเฉพาะ
// ==========================================

function insertThaiWordBreaksMemo($text) {
    $words = [
        'ตามที่', 'กำหนดจัด', 'อบรมหลักสูตร', 'ระหว่างวันที่', 'ณ', 'นั้น', 'ซึ่งหลักสูตร', 
        'ดังกล่าวเป็น', 'ประโยชน์ต่อ', 'การพัฒนา', 'กระบวนการ', 'จัดการเรียน', 'การสอน', 
        'การนี้', 'ข้าพเจ้า', 'สังกัดภาควิชา', 'มหาวิทยาลัย', 'เทคโนโลยี', 'พระจอมเกล้า', 
        'พระนครเหนือ', 'วิทยาเขต', 'ปราจีนบุรี', 'มีความประสงค์', 'ที่จะขอ', 'อนุมัติ', 
        'เข้ารับการอบรม', 'เป็นเงินจำนวน', 'บาท', 'โดยขอใช้', 'แหล่งเงิน', 'จัดสรรให้', 
        'หน่วยงาน', 'ประจำปี', 'งบประมาณ', 'พ.ศ.', 'แผนงาน', 'จัดการศึกษา', 'ระดับอุดมศึกษา', 
        'กองทุนพัฒนาบุคลากร', 'หมวดค่าใช้สอย', 'รายละเอียด', 'ตามเอกสารแนบ', 'โดยไม่เบิก', 
        'ค่าใช้จ่ายใดๆ', 'ทั้งสิ้น', 'จึงเรียนมา', 'เพื่อโปรด', 'พิจารณาอนุมัติ', 'เดินทางไป', 
        'รถยนต์ส่วนบุคคล', 'หมายเลขทะเบียน', 'ตามวัน', 'เวลา', 'และสถานที่', 'หลักเกณฑ์', 'วิธีของ'
    ];
    
    foreach ($words as $word) {
        $text = str_replace($word, $word . "\u{200B}", $text);
    }
    
    // คลีนตัวตัดคำมั่วซั่วที่เกิดขึ้นรอยต่อระหว่างคำศัพท์ เช่น เทคโนโลยี\u{200B}สารสนเทศ หรือ สารสนเทศ\u{200B}
    $text = str_replace("เทคโนโลยี\u{200B}สารสนเทศ", "เทคโนโลยีสารสนเทศ", $text);
    $text = str_replace("สารสนเทศ\u{200B}", "สารสนเทศ", $text);
    
    return $text;
}

function addMemoManualPara($section, array $textParts, $spaceAfter = 80) {
    $run = $section->addTextRun([
        'alignment' => Jc::BOTH, 
        'lineHeight' => 1.15,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'indentation' => [
            'firstLine' => Converter::cmToTwip(2.5)
        ],
    ]);

    $fullText = '';
    foreach ($textParts as $part) {
        if (is_array($part)) {
            $fullText .= $part[0];
        } else {
            $fullText .= $part;
        }
    }

    $processedText = insertThaiWordBreaksMemo(cleanWordText($fullText));
    $run->addText($processedText, 'normalFont');
}


// ==========================================
// แก้เฉพาะส่วนลายเซ็น: กันคำว่า "สารสนเทศ" ไม่ให้ตัว ศ ตกบรรทัด
// อ้างอิงแนวจัดบล็อกจาก download_word_speaker.php
// ==========================================
function memoSignatureNoBorderCellFixed($valign = 'top', $noWrap = false) {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'valign' => $valign,
        'marginTop' => 0,
        'marginBottom' => 0,
        'marginLeft' => 0,
        'marginRight' => 0,
        'noWrap' => $noWrap,
    ];
}

function memoKeepSignatureWordsTogether($text) {
    $text = trim(str_replace(["\r", "\n", "\t", "\u{200B}", "\u{2060}"], ' ', (string)$text));
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);

    $joiner = "\u{2060}";
    $keepWords = [
        'เทคโนโลยีสารสนเทศ',
        'สารสนเทศ',
    ];

    foreach ($keepWords as $word) {
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars) {
            $text = str_replace($word, implode($joiner, $chars), $text);
        }
    }

    return $text;
}

function addMemoSignatureFixed($section, $ownerName, $position) {
    $section->addText('', 'normalFont', [
        'spaceBefore' => 520,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $sigTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);

    $sigTable->addRow(null, ['exactHeight' => false]);
    $sigTable->addCell(Converter::cmToTwip(5.75), memoSignatureNoBorderCellFixed('top'))
        ->addText('', 'normalFont', ['spaceAfter' => 0]);

    $sigCell = $sigTable->addCell(Converter::cmToTwip(10.25), memoSignatureNoBorderCellFixed('top', true));

    $sigCell->addText('(' . cleanWordText($ownerName ?: '................................') . ')', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $sigCell->addText(memoKeepSignatureWordsTogether($position ?: '................................'), [
        'name' => 'TH SarabunPSK',
        'size' => 15,
    ], [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}


// ==========================================
// แก้เฉพาะส่วนหัวข้อ "เรื่อง" ให้ใช้รูปแบบตัดบรรทัดแบบ download_word_academic_1.php
// เพื่อไม่ให้ตัดคำเร็วเกินไปทั้งที่ยังมีพื้นที่เหลือ
// ==========================================
function memoSubjectNoBorderCellFixed($valign = 'bottom') {
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

function memoSubjectDottedBottomCellFixed($valign = 'bottom') {
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

function memoSubjectHeaderParaFixed($align = Jc::LEFT, $spaceAfter = 0) {
    return [
        'alignment' => $align,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
    ];
}

function memoSplitSubjectLinesFixed($text, $limit = 86) {
    $text = trim(preg_replace('/\s+/u', ' ', cleanWordText((string)$text)));
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

function addMemoHeaderSubjectFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $subjectMaxLines = 2) {
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

    // แก้เฉพาะหัวข้อ "เรื่อง": ใช้ตารางแยก label/value แบบ academic_1
    // และเพิ่ม limit เป็น 86 ตัวอักษร เพื่อให้บรรทัดแรกยาวขึ้นก่อนตัดบรรทัด
    $subjectLines = memoSplitSubjectLinesFixed($subjectText, 86);
    $subjectTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => MEMO_CONTENT_TWIP,
    ]);

    foreach ($subjectLines as $index => $line) {
        $subjectTable->addRow(null, ['exactHeight' => false]);
        $subjectTable->addCell(Converter::cmToTwip(0.90), memoSubjectNoBorderCellFixed())->addText(
            $index === 0 ? 'เรื่อง' : '',
            'boldFont',
            memoSubjectHeaderParaFixed()
        );
        $subjectTable->addCell(Converter::cmToTwip(15.10), memoSubjectDottedBottomCellFixed())->addText(
            cleanWordText($line),
            'normalFont',
            memoSubjectHeaderParaFixed()
        );
    }

    $subjectLabelW = Converter::cmToTwip(0.95);
    $subjectValueW = MEMO_CONTENT_TWIP - $subjectLabelW;

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


// ==========================================
// ปรับปรุงฟังก์ชันสร้างหน้าบันทึกข้อความเดิม
// ==========================================

function addMainMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $department, $faculty, $courseName, $joinDates, $location, $hasExpense, $displayAmountNumber, $displayAmountThai, $thaiYear) {
    $section = addSectionPage($phpWord);
    addMemoHeaderSubjectFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);
    
    // ย่อหน้า 1
    addMemoManualPara($section, [
        'ตามที่ กำหนดจัดอบรมหลักสูตร ', [$courseName ?: 'ชื่อหลักสูตร', true],
        ' ระหว่างวันที่ ', [$joinDates ?: '...', true],
        ' ณ ', [$location ?: '...', true],
        ' นั้น ซึ่งหลักสูตรดังกล่าวเป็นประโยชน์ต่อการพัฒนาทั้งกระบวนการจัดการเรียนการสอน'
    ]);
    
    // ย่อหน้า 2
    $expenseText = $hasExpense
        ? ' เป็นเงินจำนวน ' . $displayAmountNumber . ' บาท (' . $displayAmountThai . ') โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ ' . ($thaiYear ? 'พ.ศ. ' . $thaiYear : 'พ.ศ. ....') . ' แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย (รายละเอียดตามเอกสารแนบ)'
        : ' โดยไม่เบิกค่าใช้จ่ายใดๆ ทั้งสิ้น';
        
    addMemoManualPara($section, [
        'การนี้ ข้าพเจ้า ', [$ownerName ?: 'ชื่อ-นามสกุล', true], ' ', [$position ?: '', true],
        ' สังกัดภาควิชา', [$department ?: '...', true], ' ', [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ที่จะขออนุมัติ เข้ารับการอบรมหลักสูตร ',
        [$courseName ?: 'ชื่อหลักสูตร', true], ' ระหว่างวันที่ ', [$joinDates ?: '', true], ' ณ ', [$location ?: '', true], $expenseText
    ]);
    
    $runClose = $section->addTextRun([
        'alignment' => Jc::LEFT,
        'lineHeight' => 1.15,
        'spaceAfter' => 120,
        'indentation' => [
            'firstLine' => Converter::cmToTwip(2.5)
        ],
    ]);
    $runClose->addText('จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ', 'normalFont');
    
    // เคลียร์ค่ารหัสแบ่งคำหลุดในข้อมูลส่วนลายเซ็นท้ายเอกสาร
    $cleanOwner = str_replace(["\u{200B}", " "], ["", " "], $ownerName);
    $cleanPosition = str_replace("เทคโนโลยี\u{200B}สารสนเทศ", "เทคโนโลยีสารสนเทศ", $position);
    addMemoSignatureFixed($section, $cleanOwner, $cleanPosition);
}

function addExpenseMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $department, $faculty, $subject, $joinDates, $location, $displayAmountNumber, $displayAmountThai, $thaiYear) {
    $section = addSectionPage($phpWord);
    addMemoHeaderSubjectFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);
    
    addMemoManualPara($section, [
        'การนี้ ข้าพเจ้า ', [$ownerName ?: 'ชื่อ-นามสกุล', true], ' ', [$position ?: '', true],
        ' สังกัดภาควิชา', [$department ?: '...', true], ' ', [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ขออนุมัติค่าใช้จ่ายในการเข้าร่วม ',
        [$subject ?: 'ขออนุมัติ...', true], ' ระหว่างวันที่ ', [$joinDates ?: '', true], ' ณ ', [$location ?: '', true],
        ' วงเงินทั้งสิ้น ', [$displayAmountNumber, true], ' บาท (', [$displayAmountThai, true], ') โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ ',
        [($thaiYear ? 'พ.ศ. ' . $thaiYear : 'พ.ศ. ....'), true], ' ในส่วนของภาควิชา', [$department ?: '...', true], ' แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย (รายละเอียดตามเอกสารแนบ)'
    ]);
    
    $runClose = $section->addTextRun([
        'alignment' => Jc::LEFT,
        'lineHeight' => 1.15,
        'spaceAfter' => 120,
        'indentation' => [
            'firstLine' => Converter::cmToTwip(2.5)
        ],
    ]);
    $runClose->addText('จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ', 'normalFont');
    
    $cleanOwner = str_replace(["\u{200B}", " "], ["", " "], $ownerName);
    $cleanPosition = str_replace("เทคโนโลยี\u{200B}สารสนเทศ", "เทคโนโลยีสารสนเทศ", $position);
    addMemoSignatureFixed($section, $cleanOwner, $cleanPosition);
}

function addCarMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $department, $faculty, $courseName, $joinDates, $location, $vehicle, $subject) {
    $section = addSectionPage($phpWord);
    addMemoHeaderSubjectFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);
    
    addMemoManualPara($section, [
        'ตามที่ ข้าพเจ้า ', [$ownerName ?: 'ชื่อ-นามสกุล', true], ' ', [$position ?: '', true],
        ' สังกัดภาควิชา', [$department ?: '...', true], ' ', [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ที่จะขออนุมัติ เข้ารับการอบรมหลักสูตร ',
        [$courseName ?: 'ชื่อหลักสูตร', true], ' ระหว่างวันที่ ', [$joinDates ?: '', true], ' ณ ', [$location ?: '', true], ' นั้น'
    ]);
    
    addMemoManualPara($section, [
        'ในการนี้ ข้าพเจ้าจึงขออนุมัติใช้รถยนต์ส่วนบุคคล หมายเลขทะเบียน ', [$vehicle ?: '...', true],
        ' ในการเดินทางไป', [$subject ?: 'ชื่อหลักสูตร', true], ' ตามวัน เวลา และสถานที่ดังกล่าว ทั้งนี้ โดยให้เป็นไปตามหลักเกณฑ์และวิธีการของมหาวิทยาลัย'
    ]);
    
    $runClose = $section->addTextRun([
        'alignment' => Jc::LEFT,
        'lineHeight' => 1.15,
        'spaceAfter' => 120,
        'indentation' => [
            'firstLine' => Converter::cmToTwip(2.5)
        ],
    ]);
    $runClose->addText('จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ', 'normalFont');
    
    $cleanOwner = str_replace(["\u{200B}", " "], ["", " "], $ownerName);
    $cleanPosition = str_replace("เทคโนโลยี\u{200B}สารสนเทศ", "เทคโนโลยีสารสนเทศ", $position);
    addMemoSignatureFixed($section, $cleanOwner, $cleanPosition);
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