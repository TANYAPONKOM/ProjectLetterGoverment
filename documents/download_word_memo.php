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

$budgetStmt = $pdo->prepare("\n    SELECT item_type, description, amount\n    FROM budget_items\n    WHERE document_id = :id\n    ORDER BY item_id ASC\n");
$budgetStmt->execute([':id' => $docId]);
$budgetItems = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

$departmentPhone = '';
$docDepartmentId = (int)($document['department_id'] ?? 0);
if ($docDepartmentId > 0) {
    try {
        $deptPhoneStmt = $pdo->prepare("
            SELECT phone
            FROM departments
            WHERE department_id = :department_id
            LIMIT 1
        ");
        $deptPhoneStmt->execute([':department_id' => $docDepartmentId]);
        $deptPhoneRow = $deptPhoneStmt->fetch(PDO::FETCH_ASSOC);
        if ($deptPhoneRow) {
            $departmentPhone = trim((string)($deptPhoneRow['phone'] ?? ''));
        }
    } catch (Throwable $e) {
        $departmentPhone = '';
    }
}

function academicField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '') {
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $default;
}

function academicThaiDigit($text) {
    return strtr((string)$text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}

function academicArabicDigit($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

function academicThaiMonths() {
    return [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
}

function academicThaiDateAny($date) {
    $date = trim(academicArabicDigit((string)$date));
    if ($date === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        $months = academicThaiMonths();
        return academicThaiDigit($d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543));
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        $months = academicThaiMonths();
        return academicThaiDigit($d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543));
    }

    return academicThaiDigit($date);
}

function academicThaiDateAnyArabicNumber($date) {
    $date = trim(academicArabicDigit((string)$date));
    if ($date === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        $months = academicThaiMonths();
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543);
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        $months = academicThaiMonths();
        return $d . ' ' . ($months[$mo] ?? '') . ' ' . ($y + 543);
    }

    return academicArabicDigit($date);
}

function academicClean($text) {
    $text = str_replace(["\r", "\n", "\t"], ' ', (string)$text);
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    return academicThaiDigit(trim($text));
}

function academicCleanNoDigit($text) {
    $text = str_replace(["\r", "\n", "\t"], ' ', (string)$text);
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    return trim($text);
}

function academicCleanSectionSubject($text) {
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    $removePrefixList = [
        'ขออนุมัติตัวบุคคลเพื่อไป',
        'ขออนุมัติตัวบุคคลเข้าร่วม',
        'ขออนุมัติตัวบุคคลเพื่อเข้าร่วม',
    ];

    foreach ($removePrefixList as $prefix) {
        if (mb_strpos($text, $prefix, 0, 'UTF-8') === 0) {
            return trim(mb_substr($text, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8'));
        }
    }
    return $text;
}

function academicBahtText($amount) {
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
    return ((int)$satang === 0) ? $bahtText . 'ถ้วน' : $bahtText . $convert($satang) . 'สตางค์';
}

// ใช้แนวเดียวกับ download_word_memo.php: ช่วยตัดคำให้ย่อหน้ากระจายตัวดี แต่ไม่ไปยุ่งกับหัวเอกสาร
function insertThaiWordBreaksAcademicMemo($text) {
    $text = cleanWordText((string)$text);
    $text = str_replace(["\r", "\n", "\t"], ' ', $text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    $zwsp = "\u{200B}";

    // เอา ZWSP เก่าออกก่อน เพื่อไม่ให้ตัดคำซ้อนกันมั่ว
    $text = str_replace($zwsp, '', $text);

    // ห้ามแทรกจุดตัดติดกับสระ/วรรณยุกต์ไทย เช่น ที่, นี้, ผู้
    $thaiMarks = "\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}";
    $z = preg_quote($zwsp, '/');
    $text = preg_replace('/' . $z . '(?=[' . $thaiMarks . '])/u', '', $text);
    $text = preg_replace('/(?<=[' . $thaiMarks . '])' . $z . '/u', '', $text);

    // จุดตัดหลังเครื่องหมาย เพื่อให้ Word มีตำแหน่งตัดบรรทัดเพิ่มขึ้น
    $text = preg_replace('/([\/\-–—,;:()（）"“”])/u', '$1' . $zwsp, $text);

    // ชุดคำเฉพาะของ memo academic + ชุดคำที่ใช้ใน download_word_memo.php
    // ช่วยให้ Word กระจายคำและตัดคำไทย ไม่ยกทั้งก้อนลงบรรทัดใหม่จนเกิดพื้นที่ว่าง
    $safeWords = [
        'ตามที่',
        'ข้าพเจ้า',
        'พนักงานมหาวิทยาลัย',
        'สังกัดภาควิชา',
        'สังกัด',
        'ภาควิชา',
        'คณะบริหารธุรกิจและอุตสาหกรรมบริการ',
        'คณะเทคโนโลยีและการจัดการอุตสาหกรรม',
        'เทคโนโลยีสารสนเทศ',
        'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ',
        'วิทยาเขตปราจีนบุรี',
        'ได้รับอนุมัติ',
        'ตัวบุคคล',
        'ให้เข้าร่วม',
        'นำเสนอผลงานวิจัย',
        'ในหัวข้อ',
        'ซึ่งจัดขึ้นที่',
        'ในระหว่างวันที่',
        'โดยเอกสาร',
        'งานประชุมวิชาการ',
        'จะถูกตีพิมพ์',
        'อยู่ในฐานข้อมูล',
        'Scopus',
        'นั้น',
        'การนี้',
        'จึงมีความประสงค์',
        'มีความประสงค์',
        'ขออนุมัติ',
        'เดินทาง',
        'เพื่อไป',
        'ในงานประชุม',
        'วิชาการระดับนานาชาติ',
        'รวมเวลาเดินทาง',
        'ตามวัน',
        'เวลา',
        'และสถานที่',
        'ดังกล่าว',
        'เป็นประโยชน์ต่อ',
        'การพัฒนา',
        'การพัฒนาการเรียนการสอน',
        'การพัฒนาทั้งกระบวนการจัดการเรียนการสอน',
        'กระบวนการจัดการเรียนการสอน',
        'จัดการเรียนการสอน',
        'งานวิจัย',
        'และสร้างชื่อเสียง',
        'ให้กับมหาวิทยาลัย',
        'โดยขอใช้',
        'งบจัดสรรให้หน่วยงาน',
        'จัดสรรให้',
        'หน่วยงาน',
        'ประจำปีงบประมาณ',
        'ประจำปี',
        'งบประมาณ',
        'พ.ศ.',
        'ในส่วนของ',
        'แผนงานจัดการศึกษาระดับอุดมศึกษา',
        'แผนงาน',
        'จัดการศึกษา',
        'ระดับอุดมศึกษา',
        'กองทุนพัฒนาบุคลากร',
        'หมวดค่าใช้สอย',
        'รายละเอียดตามเอกสารแนบ',
        'รายละเอียด',
        'ตามเอกสารแนบ',
        'วงเงินทั้งสิ้น',
        'บาท',
        'โดยขอใช้แหล่งเงิน',
        'แหล่งเงิน',
        'รถยนต์ส่วนบุคคล',
        'หมายเลขทะเบียน',
        'หลักเกณฑ์และวิธีการของมหาวิทยาลัย',
        'หลักเกณฑ์',
        'วิธีการ',
        'วิธีของ',
        'จึงเรียนมา',
        'เพื่อโปรด',
        'พิจารณาอนุมัติ',
        'และ',
        'หรือ',
        'โดย',
        'เพื่อ',
        'ซึ่ง',
        'ของ',
        'กับ',
        'จาก',
        'ตาม',
        'เป็น',
        'ให้',
        'ได้',
        'มี',
        'ณ',
    ];

    foreach ($safeWords as $word) {
        $text = str_replace($word, $word . $zwsp, $text);
    }

    // ไม่แทรก ZWSP รายตัวอักษร เพราะ Word จะแยกคำไทยผิด เช่น กา ร / เ ชิง
    // ให้ใช้เฉพาะจุดตัดจาก safeWords และเครื่องหมายเท่านั้น

    // กันคำสำคัญไม่ให้ถูกแทรกจุดตัดผิดกลางคำ
    $keepWords = [
        'เทคโนโลยีสารสนเทศ',
        'สารสนเทศ',
        'ดิจิทัล',
        'Scopus',
    ];

    foreach ($keepWords as $word) {
        $text = str_replace(str_replace($zwsp, '', $word), $word, $text);
    }

    // ล้าง ZWSP ที่ไปชิดสระ/วรรณยุกต์อีกครั้งหลังแทนคำ
    $text = preg_replace('/' . $z . '(?=[' . $thaiMarks . '])/u', '', $text);
    $text = preg_replace('/(?<=[' . $thaiMarks . '])' . $z . '/u', '', $text);

    return $text;
}

function addAcademicMemoManualPara($section, array $textParts, $spaceAfter = 28) {
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

function addAcademicClosePara($section, $spaceAfter = 120) {
    $runClose = $section->addTextRun([
        'alignment' => Jc::LEFT,
        'lineHeight' => 1.15,
        'spaceAfter' => $spaceAfter,
        'indentation' => [
            'firstLine' => Converter::cmToTwip(2.5)
        ],
    ]);
    $runClose->addText('จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ', 'normalFont');
}


function academicKeepSignaturePositionTogether($text) {
    $text = trim(str_replace(["\r", "\n", "\t", "\u{200B}", "\u{2060}"], ' ', (string)$text));
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

function addAcademicSignatureFixed($section, $name, $position) {
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



function academicNoBorderCell($valign = 'bottom') {
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

function academicDottedBottomCell($valign = 'bottom') {
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

function academicHeaderPara($align = Jc::LEFT, $spaceAfter = 0) {
    return [
        'alignment' => $align,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'lineHeight' => 1.0,
    ];
}

function academicSplitHeaderLines($text, $limit = 78) {
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

function addAcademicGovAgencyDottedRowFixed($section, $headerText) {
    // แก้เฉพาะแถว "ส่วนราชการ" ให้ใช้รูปแบบเดียวกับ download_word_memo.php
    // cell แรกไม่มีเส้นประ, cell สองมีเส้นประและ noWrap เพื่อกันข้อความยาวตกบรรทัด
    $contentWidth = Converter::cmToTwip(16.0);
    $labelWidth = Converter::cmToTwip(1.95);
    $textWidth = $contentWidth - $labelWidth;

    $agencyTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => $contentWidth,
    ]);

    $agencyTable->addRow(Converter::cmToTwip(0.42), ['exactHeight' => false]);

    $labelCell = $agencyTable->addCell($labelWidth, [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'valign' => 'bottom',
        'noWrap' => true,
        'marginTop' => 0,
        'marginBottom' => 20,
        'marginLeft' => 0,
        'marginRight' => 0,
    ]);

    $labelCell->addText('ส่วนราชการ', 'boldFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

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
        academicCleanNoDigit($headerText ?: 'คณะ... ภาควิชา... โทร...'),
        'normalFont',
        [
            'alignment' => Jc::LEFT,
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]
    );
}

function addAcademicMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText) {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

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
        $left->addImage($garuda, ['width' => 62, 'alignment' => Jc::LEFT]);
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
    $dateTable->addRow(null, ['exactHeight' => false]);
    $dateTable->addCell(Converter::cmToTwip(0.45), academicNoBorderCell())->addText('ที่', 'boldFont', academicHeaderPara());
    // แก้เฉพาะช่องข้อมูลหลังคำว่า "ที่": ถ้า doc_no ว่าง ให้ยังแสดงเลขที่เอกสารตามรูปแบบมาตรฐาน ไม่ปล่อยให้ช่องหาย
    $dateTable->addCell(Converter::cmToTwip(5.25), academicDottedBottomCell())->addText(
        academicCleanNoDigit($docNo ?: ''),
        'normalFont',
        academicHeaderPara()
    );
    $dateTable->addCell(Converter::cmToTwip(1.10), academicNoBorderCell())->addText('วันที่', 'boldFont', academicHeaderPara(Jc::CENTER));
    $dateTable->addCell(Converter::cmToTwip(9.20), academicDottedBottomCell())->addText(academicCleanNoDigit($thaiDocDate), 'normalFont', academicHeaderPara());

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
        $subjectTable->addCell(Converter::cmToTwip(15.10), academicDottedBottomCell())->addText(academicCleanNoDigit($line), 'normalFont', academicHeaderPara());
    }

    $section->addText('เรียน ' . academicCleanNoDigit($toText), 'normalFont', [
        'alignment' => Jc::LEFT,
        'spaceBefore' => 20,
        'spaceAfter' => 120,
        'lineHeight' => 1.0,
    ]);
}

// หน้าเอกสารหลัก: เนื้อหาฝึกอบรมให้ตรงกับ view_memo.php
function addAcademicMainMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $departmentFull, $faculty, $courseName, $location, $joinDates, $thaiYear, $hasExpense, $displayAmountNumber, $displayAmountThai) {
    $section = addSectionPage($phpWord);
    addAcademicMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);

    addAcademicMemoManualPara($section, [
        'ตามที่ กำหนดจัดอบรมหลักสูตร ', [$courseName ?: 'ชื่อหลักสูตร', true],
        ' ระหว่างวันที่ ', [$joinDates ?: '...', true],
        ' ณ ', [$location ?: '...', true], ' นั้น ',
        'ซึ่งหลักสูตรดังกล่าวเป็นประโยชน์ต่อการพัฒนาทั้งกระบวนการจัดการเรียนการสอน'
    ]);

    $secondPara = [
        'การนี้ ข้าพเจ้า ', [$ownerName ?: 'ชื่อ-นามสกุล', true], ' ', [$position ?: '', true],
        ' สังกัด', [$departmentFull ?: 'ภาควิชา...', true], ' ', [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี ',
        'จึงมีความประสงค์ที่จะขออนุมัติ เข้ารับการอบรมหลักสูตร ', [$courseName ?: 'ชื่อหลักสูตร', true],
        ' ระหว่างวันที่ ', [$joinDates ?: '', true],
        ' ณ ', [$location ?: '', true]
    ];

    if ($hasExpense) {
        $secondPara = array_merge($secondPara, [
            ' เป็นเงินจำนวน ', [$displayAmountNumber, true], ' บาท (', [$displayAmountThai, true], ') ',
            'โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ ', ['พ.ศ. ' . $thaiYear, true],
            ' แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย ',
            '(รายละเอียดตามเอกสารแนบ)'
        ]);
    } else {
        $secondPara[] = ' โดยไม่เบิกค่าใช้จ่ายใดๆ ทั้งสิ้น';
    }

    addAcademicMemoManualPara($section, $secondPara);
    addAcademicClosePara($section);
    addAcademicSignatureFixed($section, $ownerName, $position);
}

function addAcademicExpenseMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $department, $departmentFull, $faculty, $subject, $joinDates, $location, $displayAmountNumber, $displayAmountThai, $thaiYear) {
    $section = addSectionPage($phpWord);
    addAcademicMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);

    addAcademicMemoManualPara($section, [
        'การนี้ ข้าพเจ้า ', [$ownerName ?: 'ชื่อ-นามสกุล', true], ' ', [$position ?: '', true],
        ' สังกัด', [$departmentFull ?: ('ภาควิชา' . ($department ?: '...')), true], ' ', [$faculty ?: '...', true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ขออนุมัติค่าใช้จ่ายในการเข้าร่วม ',
        [$subject ?: 'ขออนุมัติ...', true], ' ระหว่างวันที่ ', [$joinDates ?: '', true], ' ณ ', [$location ?: '', true],
        ' วงเงินทั้งสิ้น ', [$displayAmountNumber, true], ' บาท (', [$displayAmountThai, true], ') โดยขอใช้แหล่งเงินจัดสรรให้หน่วยงาน ประจำปีงบประมาณ ',
        ['พ.ศ. ' . $thaiYear, true], ' ในส่วนของ', [$departmentFull ?: ('ภาควิชา' . ($department ?: '...')), true],
        ' แผนงานจัดการศึกษาระดับอุดมศึกษา กองทุนพัฒนาบุคลากร หมวดค่าใช้สอย (รายละเอียดตามเอกสารแนบ)'
    ]);

    addAcademicClosePara($section);
    addAcademicSignatureFixed($section, $ownerName, $position);
}

function addAcademicCarMemoPage($phpWord, $docNo, $thaiDocDate, $headerText, $subjectText, $toText, $ownerName, $position, $departmentFull, $faculty, $subject, $joinDates, $location, $vehicle) {
    $section = addSectionPage($phpWord);
    addAcademicMemoHeaderFixed($section, $docNo, $thaiDocDate, $headerText, $subjectText, $toText);

    addAcademicMemoManualPara($section, [
        'ตามที่ ข้าพเจ้า ', [$ownerName ?: 'ชื่อ-นามสกุล', true], ' ', [$position ?: '', true],
        ' สังกัด', [$departmentFull, true], ' ', [$faculty, true],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี จึงมีความประสงค์ที่จะขออนุมัติ ',
        [$subject ?: 'ชื่อหลักสูตร', true], ' ระหว่างวันที่ ', [$joinDates ?: '', true], ' ณ ', [$location ?: '', true], ' นั้น'
    ]);

    addAcademicMemoManualPara($section, [
        'ในการนี้ ข้าพเจ้าจึงขออนุมัติใช้รถยนต์ส่วนบุคคล หมายเลขทะเบียน ', [$vehicle ?: '...', true],
        ' ในการเดินทางไป ', [$subject ?: 'ชื่อหลักสูตร', true],
        ' ตามวัน เวลา และสถานที่ดังกล่าว ทั้งนี้ โดยให้เป็นไปตามหลักเกณฑ์และวิธีการของมหาวิทยาลัย'
    ]);

    addAcademicClosePara($section);
    addAcademicSignatureFixed($section, $ownerName, $position);
}

// หน้าประมาณการค่าใช้จ่าย: จัด layout ให้เหมือนหน้าฟอร์มตัวอย่าง รูปที่ 3
function academicExpenseInfoRow($table, $label, $value, $spaceAfter = 0) {
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

function addAcademicExpenseTablePage($phpWord, $budgetItems, $budgetTotal, $joinType, $courseName, $joinDates, $location) {
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
            $table->addCell(Converter::cmToTwip(2.00), ['valign' => 'center'])->addText((string)($i + 1), 'normalFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.0]);
            $desc = cleanWordText($item['description'] ?: $item['item_type']);
            $table->addCell(Converter::cmToTwip(9.80), ['valign' => 'center'])->addText($desc, 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);
            $table->addCell(Converter::cmToTwip(4.20), ['valign' => 'center'])->addText(number_format((float)$item['amount'], 2), 'normalFont', ['alignment' => Jc::END, 'spaceAfter' => 0, 'lineHeight' => 1.0]);
        }
    } else {
        $table->addRow(Converter::cmToTwip(0.75));
        $table->addCell(Converter::cmToTwip(16.0), ['gridSpan' => 3, 'valign' => 'center'])->addText('ไม่พบข้อมูลประมาณค่าใช้จ่าย', 'normalFont', ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.0]);
    }

    $table->addRow(Converter::cmToTwip(0.75));
    $table->addCell(Converter::cmToTwip(2.00), ['valign' => 'center'])->addText('', 'normalFont', ['spaceAfter' => 0]);
    $table->addCell(Converter::cmToTwip(9.80), ['valign' => 'center'])->addText('รวมเป็นเงิน', 'boldFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);
    $table->addCell(Converter::cmToTwip(4.20), ['valign' => 'center'])->addText(number_format($budgetTotal, 2), 'boldFont', ['alignment' => Jc::END, 'spaceAfter' => 0, 'lineHeight' => 1.0]);

    $section->addText('หมายเหตุ ขอถัวจ่ายทุกรายการ', 'normalFont', [
        'spaceBefore' => 120,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

$docDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$ownerName = academicField($valueMapByKey, $valueMap, 'owner_name', 2, '');
$position = academicField($valueMapByKey, $valueMap, 'position', 3, '');
$joinType = academicField($valueMapByKey, $valueMap, 'join_type', 4, '');
$courseName = academicField($valueMapByKey, $valueMap, 'course_name', 5, '');
$joinDates = academicField($valueMapByKey, $valueMap, 'join_date_range', 6, '');
$location = academicField($valueMapByKey, $valueMap, 'location', 7, '');
$amountStr = academicField($valueMapByKey, $valueMap, 'total_cost', 8, '');
$vehicle = academicField($valueMapByKey, $valueMap, 'vehicle', 9, '');
$faculty = academicField($valueMapByKey, $valueMap, 'faculty', 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม');
$department = academicField($valueMapByKey, $valueMap, 'department', 11, 'เทคโนโลยีสารสนเทศ');
$noCost = ((string)($valueMap[12] ?? '0') === '1');
$academicTopic = academicField($valueMapByKey, $valueMap, 'academic_topic', 13, '');
$memoSubject = academicField($valueMapByKey, $valueMap, 'memo_subject', 14, (string)($document['subject'] ?? ''));
$academicLevel = academicField($valueMapByKey, $valueMap, 'academic_level', 15, '');
$eventDate = academicField($valueMapByKey, $valueMap, 'event_date', 16, '');

$rawHeaderText = trim((string)($document['header_text'] ?? ''));
$docNo = trim((string)($document['doc_no'] ?? ''));
$subject = trim($memoSubject !== '' ? $memoSubject : (string)($document['subject'] ?? ''));

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
$headerText = trim($displayFaculty . ' ' . $displayDepartmentFull . ($departmentPhone !== '' ? ' โทร.' . $departmentPhone : ''));
if ($headerText === '') {
    $headerText = $rawHeaderText;
}
$displayFacultyDean = 'คณบดี' . $displayFaculty;
$thaiDocDate = academicThaiDateAnyArabicNumber($docDate);

$thaiYear = '';
if ($docDate && preg_match('/^\d{4}/', academicArabicDigit($docDate))) {
    $thaiYear = ((int)substr(academicArabicDigit($docDate), 0, 4) + 543);
}
if ($thaiYear === '') {
    $thaiYear = (int)date('Y') + 543;
}

$budgetTotal = 0;
foreach ($budgetItems as $item) {
    $budgetTotal += (float)($item['amount'] ?? 0);
}
$hasExpense = (!$noCost && !empty($budgetItems) && $budgetTotal > 0);
$hasCar = trim($vehicle) !== '';
$displayAmount = $budgetTotal > 0 ? $budgetTotal : (float)str_replace(',', '', $amountStr);
$displayAmountNumber = number_format($displayAmount, 2);
$displayAmountThai = academicBahtText($displayAmount);
$sectionSubject = academicCleanSectionSubject($subject ?: 'ขออนุมัติ...');

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
    'ขออนุมัติตัวบุคคลเข้าร่วม' . ($subject ?: 'ขออนุมัติ...'),
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
    $displayAmountThai
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
        $joinDates,
        $location,
        $displayAmountNumber,
        $displayAmountThai,
        $thaiYear
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
        $joinDates,
        $location,
        $vehicle
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
function academicForceBodyThaiDistributeInDocx($docxPath) {
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