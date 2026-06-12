<?php
// Pro_letter/documents/download_word_free_document.php
// ดาวน์โหลด Word (.docx) สำหรับบันทึกข้อความทั่วไป
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
    SELECT d.document_id, d.template_id, d.owner_id, d.department_id,
           d.doc_no, d.doc_date, d.subject, d.header_text, d.status,
           t.template_code, t.template_name
    FROM documents d
    LEFT JOIN templates t ON t.template_id = d.template_id
    WHERE d.document_id = :id
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
        'alignment' => Jc::CENTER,
        'lineHeight' => 1.15,
        'spaceAfter' => $spaceAfter,
        'indentation' => [
            'left' => Converter::cmToTwip(3),
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

    $safeWords = [
        'หลักสูตร', 'การอบรม', 'การพัฒนา', 'เชิงปฏิบัติการ', 'ปฏิบัติการ',
        'แอปพลิเคชัน', 'ฐานข้อมูล', 'สารสนเทศ', 'เทคโนโลยี', 'ระบบ',
        'เข้าร่วม', 'นำเสนอ', 'ประชุม', 'วิชาการ', 'โครงการ', 'กิจกรรม',
        'และ', 'เพื่อ', 'ในการ', 'ของ', 'ด้วย', 'โดย', 'จาก', 'กับ', 'ใหม่', 'การ'
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

function academicGovAgencyFontSize($text) {
    $len = mb_strlen(trim((string)$text), 'UTF-8');
    if ($len > 78) {
        return 14;
    }
    if ($len > 66) {
        return 15;
    }
    return 16;
}

function academicGovAgencyFontStyle($text) {
    $size = academicGovAgencyFontSize($text);
    if ($size < 16) {
        return ['name' => 'TH SarabunPSK', 'size' => $size];
    }
    return 'normalFont';
}

function academicGovAgencyNeedsTightWordLayout($text) {
    return academicGovAgencyFontSize($text) <= 14;
}

function academicApplyGovAgencyTightRightMargin($section, $headerText) {
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

function addAcademicGovAgencyDottedRowFixed($section, $headerText) {
    // แก้เฉพาะแถว "ส่วนราชการ": ใช้ความกว้างเดิมก่อน ถ้ายาวจนต้องใช้ฟอนต์ 14 ค่อยเพิ่มความกว้างเฉพาะแถวนี้เล็กน้อย
    $safeHeaderText = academicCleanNoDigit($headerText ?: 'คณะ...ภาควิชา...โทร...');
    $safeHeaderText = preg_replace('/\s+/u', '', $safeHeaderText);
    $useTightWordLayout = academicGovAgencyNeedsTightWordLayout($safeHeaderText);
    // ถ้ายาวเกินเส้นจริง ค่อยใช้ความกว้างตามขอบใหม่: A4 21cm - ซ้าย 2.80cm - ขวา 1.80cm = 16.40cm
    $contentWidth = Converter::cmToTwip($useTightWordLayout ? 16.40 : 16.0);
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
        $safeHeaderText,
        academicGovAgencyFontStyle($safeHeaderText),
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



$docNo = trim((string)($document['doc_no'] ?? ''));
$docDate = academicField($valueMapByKey, $valueMap, 'free_doc_date', 1, (string)($document['doc_date'] ?? ''));
$thaiDocDate = academicThaiDateAnyArabicNumber($docDate);

$subject = academicField($valueMapByKey, $valueMap, 'free_subject', 0, (string)($document['subject'] ?? 'บันทึกข้อความทั่วไป'));
$toPerson = academicField($valueMapByKey, $valueMap, 'free_to_person', 0, 'คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม');

$faculty = academicField($valueMapByKey, $valueMap, 'free_faculty', 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม');
$department = academicField($valueMapByKey, $valueMap, 'free_department', 11, 'เทคโนโลยีสารสนเทศ');
$departmentPhone = academicField($valueMapByKey, $valueMap, 'free_department_phone', 0, '');

/* เพิ่มเฉพาะส่วนผู้ลงนามของเอกสารบันทึกข้อความทั่วไป */
$signerName = academicField($valueMapByKey, $valueMap, 'free_signer_name', 0, '');
$signerPosition = academicField($valueMapByKey, $valueMap, 'free_signer_position', 0, '');

$headerText = trim(
    ($faculty ?: 'คณะ..................................') . ' ' .
    ($department ? 'ภาควิชา' . $department : 'ภาควิชา........................') .
    ($departmentPhone ? ' โทร. ' . academicArabicDigit($departmentPhone) : '')
);
$headerText = academicArabicDigit(preg_replace('/\s+/u', ' ', $headerText));

$paragraphs = [
    trim(academicField($valueMapByKey, $valueMap, 'free_paragraph_1', 0, '')),
    trim(academicField($valueMapByKey, $valueMap, 'free_paragraph_2', 0, '')),
    trim(academicField($valueMapByKey, $valueMap, 'free_paragraph_3', 0, '')),
];

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

$phpWord->addParagraphStyle('academicBodyThaiDistribute', [
    'alignment' => 'thaiDistribute',
    'lineHeight' => 0.94,
    'spaceBefore' => 0,
    'spaceAfter' => 28,
    'indentation' => [
        'firstLine' => Converter::cmToTwip(2.5),
    ],
]);

$section = addSectionPage($phpWord);
addAcademicMemoHeaderFixed(
    $section,
    $docNo,
    $thaiDocDate,
    $headerText,
    $subject ?: 'บันทึกข้อความทั่วไป',
    $toPerson
);

$hasParagraph = false;
foreach ($paragraphs as $paragraph) {
    if (trim($paragraph) !== '') {
        addAcademicMemoManualPara($section, [$paragraph]);
        $hasParagraph = true;
    }
}

if (!$hasParagraph) {
    addAcademicMemoManualPara($section, ['................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................................']);
}

addAcademicClosePara($section);

/* เพิ่มเฉพาะส่วนผู้ลงนามของเอกสารบันทึกข้อความทั่วไป */
if ($signerName !== '' || $signerPosition !== '') {
    addAcademicSignatureFixed($section, $signerName, $signerPosition);
}

$filenameSubject = trim(preg_replace('/[\\\\\/\:\*\?\"\<\>\|\r\n\t]+/u', ' ', $subject ?: 'บันทึกข้อความทั่วไป'));
$filenameSubject = preg_replace('/\s+/u', ' ', $filenameSubject);
if (function_exists('mb_strlen') && mb_strlen($filenameSubject, 'UTF-8') > 60) {
    $filenameSubject = mb_substr($filenameSubject, 0, 60, 'UTF-8');
}
$filename = 'บันทึกข้อความทั่วไป_' . $filenameSubject . '_เลขที่_' . $docId . '.docx';
$filename = preg_replace('/[\\\\\/\:\*\?\"\<\>\|\r\n\t]+/u', '_', $filename);
$asciiFilename = 'free_document_' . $docId . '.docx';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $asciiFilename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$tmpDocx = tempnam(sys_get_temp_dir(), 'free_document_word_');
if ($tmpDocx === false) {
    $writer->save('php://output');
    exit;
}
$writer->save($tmpDocx);
readfile($tmpDocx);
@unlink($tmpDocx);
exit;
