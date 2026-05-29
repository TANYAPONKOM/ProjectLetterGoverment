<?php
// Pro_letter/documents/download_word_sut_wellness.php
// ดาวน์โหลด Word (.docx) สำหรับหนังสือขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน SUT Wellness Academy

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

function sutField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '') {
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $default;
}

function sutThaiDigit($text) {
    return strtr((string)$text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}

function sutArabicDigit($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

function sutThaiMonths() {
    return [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
}

function sutThaiDateFromParts($year, $month, $day, $withWeekday = false) {
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
    $months = sutThaiMonths();
    $dateText = sutThaiDigit($day) . ' ' . $months[$month] . ' ' . sutThaiDigit($thaiYear);
    if ($withWeekday) {
        $weekdays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        $w = (int)date('w', strtotime(sprintf('%04d-%02d-%02d', $christYear, $month, $day)));
        return 'วัน' . $weekdays[$w] . 'ที่ ' . $dateText;
    }
    return $dateText;
}

function sutThaiDateAny($rawDate, $withWeekday = false) {
    $rawDate = trim((string)$rawDate);
    if ($rawDate === '') {
        return '';
    }
    if ($withWeekday && preg_match('/^วัน/u', $rawDate)) {
        return sutThaiDigit($rawDate);
    }
    $date = sutArabicDigit($rawDate);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        return sutThaiDateFromParts($m[1], $m[2], $m[3], $withWeekday);
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) {
        return sutThaiDateFromParts($m[3], $m[2], $m[1], $withWeekday);
    }
    $months = sutThaiMonths();
    $monthRegex = implode('|', array_map('preg_quote', $months));
    if (preg_match('/(\d{1,2})\s+(' . $monthRegex . ')\s+(\d{4})/u', $date, $m)) {
        $monthNumber = array_search($m[2], $months, true);
        if ($monthNumber !== false) {
            return sutThaiDateFromParts($m[3], $monthNumber, $m[1], $withWeekday);
        }
    }
    return sutThaiDigit($rawDate);
}

function sutClean($text) {
    $text = str_replace(["\r", "\n", "\t"], ' ', (string)$text);
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    return trim($text);
}

function sutInlineText($text) {
    return sutThaiDigit(sutClean($text));
}

function sutThaiWordWrap($text) {
    $text = sutInlineText($text);
    if ($text === '') {
        return '';
    }
    $zwsp = "\u{200B}";
    $text = str_replace($zwsp, '', $text);
    $thaiMarks = "\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}";
    $z = preg_quote($zwsp, '/');
    $text = preg_replace('/' . $z . '(?=[' . $thaiMarks . '])/u', '', $text);
    $text = preg_replace('/(?<=[' . $thaiMarks . '])' . $z . '/u', '', $text);
    $text = preg_replace('/([\/\-–—,;:()（）"“”])/u', '$1' . $zwsp, $text);

    $safeWords = [
        'ด้วย', 'รองศาสตราจารย์', 'ผู้ช่วยศาสตราจารย์', 'อาจารย์', 'บุคลากรสังกัด',
        'ภาควิชาเทคโนโลยีสารสนเทศ', 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม',
        'คณะบริหารธุรกิจและอุตสาหกรรมบริการ', 'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ',
        'วิทยาเขตปราจีนบุรี', 'มีความประสงค์', 'จะขออนุญาต', 'เข้าเยี่ยมชม',
        'SUT Wellness Academy', 'ศูนย์สุขภาพเพื่อการป้องกัน', 'รักษา', 'ฟื้นฟูสุขภาพ',
        'แผนไทยประยุกต์แบบครบวงจร', 'ในวันศุกร์ที่', 'เป็นต้นไป', 'เพื่อนำข้อมูลและความรู้',
        'ที่ได้รับมาพัฒนา', 'ให้เกิดประโยชน์', 'การจัดการเรียนการสอน', 'งานวิจัย',
        'การพัฒนานวัตกรรม', 'โดยมีรายชื่อคณาจารย์', 'ที่จะเข้าเยี่ยมชมศึกษาดูงาน',
        'จำนวน', 'คน', 'ดังรายชื่อต่อไปนี้', 'จึงเรียนมาเพื่อโปรดพิจารณา',
        'อนุญาตให้เข้าเยี่ยมชมศึกษาดูงาน', 'ขอขอบคุณมา ณ โอกาสนี้',
    ];
    foreach ($safeWords as $word) {
        $text = str_replace($word, $word . $zwsp, $text);
    }
    return $text;
}


// ใช้เฉพาะบรรทัดหัวข้อ "เรื่อง" เพื่อลดการตัดคำเกินจำเป็นตามรูปแบบ download_word_request_research_data.php
function sutSubjectWordWrap($text) {
    $text = sutInlineText($text);
    if ($text === '') {
        return '';
    }
    $zwsp = "\u{200B}";
    $text = str_replace($zwsp, '', $text);

    $thaiMarks = "\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}";
    $z = preg_quote($zwsp, '/');
    $text = preg_replace('/' . $z . '(?=[' . $thaiMarks . '])/u', '', $text);
    $text = preg_replace('/(?<=[' . $thaiMarks . '])' . $z . '/u', '', $text);

    // จุดตัดที่ปลอดภัยหลังเครื่องหมายเท่านั้น ไม่แทรกหลังทุกวลี เพื่อไม่ให้หัวเรื่องถูกตัดถี่เกินไป
    $text = preg_replace('/([\/\-–—,;:()（）"“”])/u', '$1' . $zwsp, $text);

    return $text;
}

// ใช้เฉพาะประโยคปิด ไม่ให้คำท้ายอย่าง "โอกาสนี้" ถูกดันตกบรรทัดแบบไม่จำเป็น
function sutKeepClosingPhraseTogether($text) {
    $joiner = "\u{2060}";
    $phrases = [
        'ขอขอบคุณมา ณ โอกาสนี้',
        'มา ณ โอกาสนี้',
    ];
    foreach ($phrases as $phrase) {
        $chars = preg_split('//u', $phrase, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars) {
            $text = str_replace($phrase, implode($joiner, $chars), $text);
        }
    }
    return $text;
}

function sutNoBorderCell($valign = 'top') {
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

function addSutHeader($section, $docNo, $thaiDocDate) {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(17.10),
    ]);

    $table->addRow(Converter::cmToTwip(3.70));

    $left = $table->addCell(Converter::cmToTwip(5.35), sutNoBorderCell('top'));
    $left->addText('', 'normalFont', ['spaceAfter' => 1060, 'lineHeight' => 1.0]);
    $left->addText('ที่ ' . sutInlineText($docNo ?: ''), 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);

    $middle = $table->addCell(Converter::cmToTwip(3.55), sutNoBorderCell('top'));
    if (file_exists($garuda)) {
        $middle->addImage($garuda, [
            'width' => 82,
            'alignment' => Jc::CENTER,
        ]);
    } else {
        $middle->addText('');
    }

    $right = $table->addCell(Converter::cmToTwip(8.20), sutNoBorderCell('top'));
    $right->addText('', 'normalFont', ['spaceAfter' => 1080, 'lineHeight' => 1.0]);
    $right->addText('มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);

    $section->addText(sutInlineText($thaiDocDate), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 40,
        'spaceAfter' => 200,
        'lineHeight' => 1.0,
        'indentation' => ['left' => Converter::cmToTwip(0.7)],
    ]);
}

function addSutPairRow($section, $label, $value, $spaceAfter = 0) {
    $isSubject = ($label === 'เรื่อง');

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);
    $table->addRow(null, ['exactHeight' => false]);

    // แก้เฉพาะแถว "เรื่อง": ลดช่อง label และเพิ่มช่องข้อความ เพื่อไม่ให้ Word ตัดคำเร็วเกินไปเหมือนภาพที่ 1
    $labelWidth = $isSubject ? 0.82 : 1.2;
    $valueWidth = $isSubject ? 15.18 : 14.8;

    $table->addCell(Converter::cmToTwip($labelWidth), sutNoBorderCell('top'))->addText(sutInlineText($label), 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    $valueCell = $table->addCell(Converter::cmToTwip($valueWidth), sutNoBorderCell('top'));

    if ($isSubject) {
        // ใช้ข้อความดิบแบบสะอาด ไม่ใส่ ZWSP ในหัวเรื่อง และบังคับตัดเฉพาะก่อนคำว่า "คน" ตามภาพตัวอย่างที่ต้องการ
        $subjectText = sutInlineText($value);
        $run = $valueCell->addTextRun([
            'spaceBefore' => 0,
            'spaceAfter' => $spaceAfter,
            'lineHeight' => 1.0,
        ]);

        if (preg_match('/^(.*ของ)(คน)$/u', $subjectText, $m)) {
            $run->addText(trim($m[1]), 'normalFont');
            $run->addTextBreak();
            $run->addText($m[2], 'normalFont');
        } else {
            $run->addText($subjectText, 'normalFont');
        }
        return;
    }

    $valueCell->addText(sutThaiWordWrap($value), 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
    ]);
}

function addSutMainPara($section, $text, $spaceAfter = 50) {
    $wrappedText = sutThaiWordWrap($text);
    if (mb_strpos((string)$text, 'โอกาสนี้') !== false) {
        $wrappedText = sutKeepClosingPhraseTogether($wrappedText);
    }
    $section->addText($wrappedText, 'normalFont', [
        'alignment' => 'thaiDistribute',
        'lineHeight' => 1.15,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'indentation' => ['firstLine' => Converter::cmToTwip(2.5)],
    ]);
}

// ใช้เฉพาะย่อหน้าปิดในภาพ เพื่อขยับบรรทัดเข้าซ้ายเล็กน้อยและกันคำท้ายตกบรรทัด
function addSutClosingPara($section, $text, $spaceAfter = 40) {
    $wrappedText = sutKeepClosingPhraseTogether(sutThaiWordWrap($text));
    $section->addText($wrappedText, 'normalFont', [
        'alignment' => 'thaiDistribute',
        'lineHeight' => 1.15,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'indentation' => ['firstLine' => Converter::cmToTwip(2.15)],
    ]);
}

function addSutTeacherList($section, array $teacherRows, $displayFaculty) {
    if (empty($teacherRows)) {
        $teacherRows[] = ['name' => '........................................................', 'affiliation' => $displayFaculty];
    }

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(16.0),
    ]);

    // แก้เฉพาะส่วนรายชื่อในภาพ: เพิ่มพื้นที่ชื่อ + บังคับไม่ตัดบรรทัดทั้งชื่อและคณะ
    $listFont = [
        'name' => 'TH SarabunPSK',
        'size' => 15.5,
    ];
    $nowrapCell = array_merge(sutNoBorderCell(), ['noWrap' => true]);

    foreach ($teacherRows as $idx => $row) {
        $table->addRow(null, ['exactHeight' => false]);
        $table->addCell(Converter::cmToTwip(1.45), sutNoBorderCell())->addText('', 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);
        $table->addCell(Converter::cmToTwip(7.25), $nowrapCell)->addText(sutThaiDigit((string)($idx + 1)) . '.  ' . sutInlineText($row['name'] ?? ''), $listFont, [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);
        $table->addCell(Converter::cmToTwip(7.30), $nowrapCell)->addText(sutInlineText(($row['affiliation'] ?? '') ?: $displayFaculty), $listFont, [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);
    }

    $section->addText('', 'normalFont', ['spaceBefore' => 0, 'spaceAfter' => 40, 'lineHeight' => 1.0]);
}

function addSutSignature($section, $receiverName, $receiverPosition) {
    $section->addText('ขอแสดงความนับถือ', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 30,
        // ขยับเฉพาะบล็อกชื่อ/ตำแหน่งลายเซ็นลงอีกเล็กน้อย
        'spaceAfter' => 780,
        'lineHeight' => 1.0,
    ]);
    $section->addText('(' . sutInlineText($receiverName) . ')', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $section->addText(sutInlineText($receiverPosition), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addSutFooter($section, $displayDepartmentFull) {
    $footer = $section->addFooter();
    $footer->addText(sutInlineText($displayDepartmentFull), 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.95,
    ]);
    $footer->addText('โทรศัพท์ ๐-๓๗๒๑-๗๓๔๐-๓ ต่อ ๗๐๖๕-๖', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.95,
    ]);
    $footer->addText('โทรสาร ๐-๓๗๒๑-๗๓๑๗-๘', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.95,
    ]);
    $footer->addText('ไปรษณีย์อิเล็กทรอนิกส์  Ladda.t@fitm.kmutnb.ac.th', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.95,
    ]);
}

function addSutWellnessPage($phpWord, array $data) {
    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => Converter::cmToTwip(2.0),
        'marginBottom' => Converter::cmToTwip(1.2),
        'marginLeft' => Converter::cmToTwip(3.0),
        'marginRight' => Converter::cmToTwip(2.0),
        'footerHeight' => Converter::cmToTwip(1.45),
    ]);

    addSutHeader($section, $data['docNo'], $data['thaiDocDate']);
    addSutPairRow($section, 'เรื่อง', $data['subject']);
    addSutPairRow($section, 'เรียน', $data['toPerson'], 90);

    $dateTimePart = trim($data['displayVisitPeriodText'] . ' ' . $data['displayVisitTimeText']);
    $dateTimePart = $dateTimePart !== '' ? ' ' . $dateTimePart : '';

    // ข้อความเนื้อหาให้ตรงกับ form_memo_sut_wellness.php: ไม่เอาตำแหน่งมาต่อหลังชื่อ แต่ใช้คำว่า "บุคลากรสังกัด..."
    addSutMainPara($section,
        'ด้วย ' . $data['ownerName'] . ' บุคลากรสังกัด' . $data['displayDepartmentFull'] . ' ' . $data['displayFaculty'] .
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี มีความประสงค์จะขออนุญาตเข้าเยี่ยมชม ' .
        $data['visitPlace'] . ' ' . $data['placeDetail'] . $dateTimePart . ' ' . $data['purposeText'] .
        ' โดยมีรายชื่อคณาจารย์ที่จะเข้าเยี่ยมชมศึกษาดูงาน จำนวน ' . $data['teacherCountThai'] . ' คน ดังรายชื่อต่อไปนี้',
        25
    );

    addSutTeacherList($section, $data['teacherRows'], $data['displayFaculty']);

    addSutClosingPara($section,
        'จึงเรียนมาเพื่อโปรดพิจารณาอนุญาตให้เข้าเยี่ยมชมศึกษาดูงาน และขอขอบคุณมา ณ โอกาสนี้',
        40
    );

    addSutSignature($section, $data['receiverName'], $data['receiverPosition']);
    addSutFooter($section, $data['displayDepartmentFull']);
}

function sutSplitLines($text) {
    $lines = preg_split('/\R/u', trim((string)$text));
    return array_values(array_filter(array_map('trim', $lines), static function($v) { return $v !== ''; }));
}

$docDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$ownerName = sutField($valueMapByKey, $valueMap, 'owner_name', 2, '');
$position = sutField($valueMapByKey, $valueMap, 'position', 3, '');
$visitPlace = sutField($valueMapByKey, $valueMap, 'visit_place', 5, 'SUT Wellness Academy');
$visitPeriodRaw = sutField($valueMapByKey, $valueMap, 'visit_period', 6, '');
$placeDetail = sutField($valueMapByKey, $valueMap, 'place_detail', 7, 'ศูนย์สุขภาพเพื่อการป้องกัน รักษา และฟื้นฟูสุขภาพด้วยแผนไทยประยุกต์แบบครบวงจร');
$teacherCountRaw = sutField($valueMapByKey, $valueMap, 'teacher_count', 8, '');
$visitTimeRaw = sutField($valueMapByKey, $valueMap, 'visit_time', 9, '');
$faculty = sutField($valueMapByKey, $valueMap, 'faculty', 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม');
$department = sutField($valueMapByKey, $valueMap, 'department', 11, 'เทคโนโลยีสารสนเทศ');
$subjectFromValue = sutField($valueMapByKey, $valueMap, 'memo_subject', 14, (string)($document['subject'] ?? ''));
$objectiveText = sutField($valueMapByKey, $valueMap, 'objective', 25, '');
$toPerson = sutField($valueMapByKey, $valueMap, 'to_person', 26, 'อธิการบดีมหาวิทยาลัยเทคโนโลยีสุรนารี (มทส.)');
$purposeText = sutField($valueMapByKey, $valueMap, 'study_purpose', 27, 'เพื่อนำข้อมูลและความรู้ที่ได้รับมาพัฒนาให้เกิดประโยชน์กับการจัดการเรียนการสอน งานวิจัย และการพัฒนานวัตกรรม');
$teacherNamesText = sutField($valueMapByKey, $valueMap, 'teacher_names_text', 28, '');
$teacherAffiliationsText = sutField($valueMapByKey, $valueMap, 'teacher_affiliations_text', 29, '');
$teacherListText = sutField($valueMapByKey, $valueMap, 'teacher_list_text', 30, '');
$receiverName = sutField($valueMapByKey, $valueMap, 'receiver_name', 56, 'ผู้ช่วยศาสตราจารย์พีระศักดิ์ เสรีกุล');
$receiverPosition = sutField($valueMapByKey, $valueMap, 'receiver_position', 57, 'รองอธิการบดีประจำ มจพ.วิทยาเขตปราจีนบุรี');

$displayFaculty = trim($faculty) !== '' ? trim($faculty) : 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
if (mb_strpos($displayFaculty, 'คณะ') !== 0) {
    $displayFaculty = 'คณะ' . $displayFaculty;
}
$displayDepartment = trim($department) !== '' ? trim($department) : 'เทคโนโลยีสารสนเทศ';
$displayDepartmentFull = (mb_strpos($displayDepartment, 'ภาควิชา') === 0) ? $displayDepartment : 'ภาควิชา' . $displayDepartment;

$subject = trim($subjectFromValue !== '' ? $subjectFromValue : (string)($document['subject'] ?? ''));
if ($subject === '') {
    $subject = 'ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน ' . ($visitPlace ?: 'SUT Wellness Academy');
}

// ดึง/ปรับเฉพาะข้อความเนื้อหาให้เป็นชุดเดียวกับ form_memo_sut_wellness.php
$displayOwnerName = trim($ownerName);
if ($displayOwnerName === '' || preg_match('/พิทย์พิน|พิทย์พิมล|ชูรอด/u', $displayOwnerName)) {
    $displayOwnerName = 'รองศาสตราจารย์ ดร.ยุพิน สรรพคุณ';
}

$displaySubjectText = trim($subject);
if ($displaySubjectText === '' || preg_match('/เข้าร่วมประชุม|การแต่งกาย|การเข้าสังคม/u', $displaySubjectText)) {
    $displaySubjectText = 'ขออนุญาตเข้าเยี่ยมชมศึกษาดูงาน ' . ($visitPlace ?: 'SUT Wellness Academy');
}
$subject = $displaySubjectText;

$displayPlaceDetailText = preg_replace('/ประยุกต์\s+แบบ/u', 'ประยุกต์แบบ', $placeDetail);
if ($displayPlaceDetailText === '' || preg_match('/ประชุมเรื่อง|การแต่งกาย|การเข้าสังคม/u', $displayPlaceDetailText)) {
    $displayPlaceDetailText = 'ศูนย์สุขภาพเพื่อการป้องกัน รักษา และฟื้นฟูสุขภาพด้วยแผนไทยประยุกต์แบบครบวงจร';
}

$displayPurposeText = preg_replace('/กับ\s+การจัดการ/u', 'กับการจัดการ', $purposeText);
if ($displayPurposeText === '' || preg_match('/การแต่งกาย|การเข้าสังคม/u', $displayPurposeText)) {
    $displayPurposeText = 'เพื่อนำข้อมูลและความรู้ที่ได้รับมาพัฒนาให้เกิดประโยชน์กับการจัดการเรียนการสอน งานวิจัย และการพัฒนานวัตกรรม';
}

$teacherRows = [];
if ($teacherListText !== '') {
    foreach (sutSplitLines($teacherListText) as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        $teacherRows[] = ['name' => $parts[0] ?? '', 'affiliation' => $parts[1] ?? $displayFaculty];
    }
}
if (!$teacherRows) {
    $names = sutSplitLines($teacherNamesText);
    $affs = sutSplitLines($teacherAffiliationsText);
    $max = max(count($names), count($affs));
    for ($i = 0; $i < $max; $i++) {
        $teacherRows[] = ['name' => $names[$i] ?? '', 'affiliation' => $affs[$i] ?? $displayFaculty];
    }
}
$teacherRows = array_values(array_filter($teacherRows, static function($row) {
    return trim((string)($row['name'] ?? '')) !== '';
}));

if (empty($teacherRows)) {
    $teacherRows = [
        ['name' => 'รองศาสตราจารย์ ดร.ยุพิน สรรพคุณ', 'affiliation' => 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'],
        ['name' => 'ผู้ช่วยศาสตราจารย์จ่าสิบตรี นพเก้า ทองใบ', 'affiliation' => 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'],
        ['name' => 'อาจารย์ ดร.พิทย์พิมล ชูรอด', 'affiliation' => 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม'],
        ['name' => 'รองศาสตราจารย์ ดร.ทิชากร เกษรบัว', 'affiliation' => 'คณะบริหารธุรกิจและอุตสาหกรรมบริการ'],
    ];
}

$teacherCount = (int)sutArabicDigit($teacherCountRaw);
if ($teacherCount <= 0 || $teacherCount !== count($teacherRows)) {
    $teacherCount = count($teacherRows);
}
$teacherCountThai = sutThaiDigit((string)$teacherCount);

$docNo = trim((string)($document['doc_no'] ?? ''));
$thaiDocDate = sutThaiDateAny($docDate);
$visitPeriod = sutThaiDateAny($visitPeriodRaw, true);
if ($visitPeriod === '') {
    $visitPeriod = sutThaiDigit($visitPeriodRaw);
}
if ($visitPeriod === '' || preg_match('/๒๔\s*พฤษภาคม\s*๒๕๖๙|24\s*พฤษภาคม\s*2569/u', $visitPeriod)) {
    $visitPeriod = 'วันศุกร์ที่ ๔ กรกฎาคม ๒๕๖๘';
}
$displayVisitPeriodText = '';
if ($visitPeriod !== '') {
    $displayVisitPeriodText = preg_match('/^วัน/u', $visitPeriod) ? 'ใน' . $visitPeriod : 'ในวันที่ ' . $visitPeriod;
}
$displayVisitTimeText = '';
$visitTime = sutThaiDigit(trim($visitTimeRaw));
if ($visitTime === '') {
    $visitTime = '๑๓.๐๐ น. เป็นต้นไป';
}
if ($visitTime !== '') {
    $displayVisitTimeText = 'เวลา ' . $visitTime;
    if (!preg_match('/น\.?/u', $visitTime)) {
        $displayVisitTimeText .= ' น.';
    }
    if (!preg_match('/เป็นต้นไป/u', $displayVisitTimeText)) {
        $displayVisitTimeText .= ' เป็นต้นไป';
    }
}

if ($displayOwnerName === '') {
    $displayOwnerName = $_SESSION['fullname'] ?? $_SESSION['name'] ?? '';
}
if ($position === '') {
    $position = $_SESSION['position'] ?? 'บุคลากร';
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
$phpWord->addFontStyle('addressFont', [
    'name' => 'TH SarabunPSK',
    'size' => 15.5,
]);

addSutWellnessPage($phpWord, [
    'docNo' => $docNo ?: '',
    'thaiDocDate' => $thaiDocDate,
    'subject' => $subject,
    'toPerson' => $toPerson,
    'ownerName' => $displayOwnerName,
    'position' => $position,
    'displayDepartmentFull' => $displayDepartmentFull,
    'displayFaculty' => $displayFaculty,
    'visitPlace' => $visitPlace,
    'placeDetail' => $displayPlaceDetailText,
    'displayVisitPeriodText' => $displayVisitPeriodText,
    'displayVisitTimeText' => $displayVisitTimeText,
    'purposeText' => $displayPurposeText,
    'teacherCountThai' => $teacherCountThai,
    'teacherRows' => $teacherRows,
    'receiverName' => $receiverName,
    'receiverPosition' => $receiverPosition,
]);

$filename = 'sut_wellness_' . $docId . '.docx';

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
