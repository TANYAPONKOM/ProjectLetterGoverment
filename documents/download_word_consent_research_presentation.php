<?php
// Pro_letter/documents/download_word_consent_research_presentation.php
// ดาวน์โหลด Word (.docx) สำหรับหนังสือยินยอมให้นำเสนอผลงานวิจัย

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

function consentField(array $valueMapByKey, array $valueMap, string $key, int $fieldId = 0, string $default = '') {
    $v = $valueMapByKey[$key] ?? ($fieldId > 0 ? ($valueMap[$fieldId] ?? null) : null);
    $v = trim((string)($v ?? ''));
    return $v !== '' ? $v : $default;
}

function consentArabicDigit($text) {
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

function consentThaiDateArabic($text) {
    $text = consentArabicDigit((string)$text);

    $thaiMonths = [
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

    $formatDate = function ($year, $month, $day) use ($thaiMonths) {
        $year = (int)$year;
        $month = (int)$month;
        $day = (int)$day;

        if ($year < 2400) {
            $year += 543;
        }

        return $day . ' ' . ($thaiMonths[$month] ?? '') . ' ' . $year;
    };

    // รองรับรูปแบบ 2026-06-04
    $text = preg_replace_callback('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u', function ($m) use ($formatDate) {
        return $formatDate($m[1], $m[2], $m[3]);
    }, $text);

    // รองรับรูปแบบ 04/06/2026 หรือ 04-06-2026
    $text = preg_replace_callback('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/u', function ($m) use ($formatDate) {
        return $formatDate($m[3], $m[2], $m[1]);
    }, $text);

    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function consentClean($text) {
    $text = str_replace(["\r", "\n", "\t"], ' ', (string)$text);
    $text = cleanWordText($text);
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);

    // Word ไฟล์นี้ให้เลขทั้งหมดเป็นเลขอารบิกเหมือนหน้าปกติ
    return trim(consentArabicDigit($text));
}

function consentInlineText($text) {
    return consentClean($text);
}

function consentThaiWordWrap($text) {
    $text = consentInlineText($text);
    if ($text === '') {
        return '';
    }

    $zwsp = "\u{200B}";

    // ห้ามแทรก ZWSP กลางตัวอักษรไทยทุกตัว เพราะทำให้สระ/วรรณยุกต์เพี้ยนใน Word
    $text = str_replace($zwsp, '', $text);

    // จุดตัดที่ปลอดภัยหลังเครื่องหมาย
    $text = preg_replace('/([\/\-–—,;:()（）"“”])/u', '$1' . $zwsp, $text);

    // จุดตัดคำ/วลีที่ใช้บ่อยในเอกสารนี้ เพื่อไม่ให้ Word ยกข้อความยาวทั้งก้อนลงบรรทัดใหม่
    $safeWords = [
        // วลีในเอกสาร
        'ข้าพเจ้า',
        'ได้ยอมให้',
        'อาจารย์สังกัด',
        'ภาควิชา',
        'เทคโนโลยีสารสนเทศ',
        'คณะเทคโนโลยี',
        'และการจัดการอุตสาหกรรม',
        'มหาวิทยาลัยเทคโนโลยี',
        'พระจอมเกล้าพระนครเหนือ',
        'วิทยาเขตปราจีนบุรี',
        'ได้นำเสนอ',
        'นำเสนอผลงานวิจัย',
        'ผลงานวิจัย',
        'ในงานการประชุมวิชาการ',
        'งานการประชุม',
        'การประชุมวิชาการ',
        'ระดับนานาชาติ',
        'โดยงานการประชุม',
        'จัดขึ้นที่',
        'ในระหว่างวันที่',

        // คำเชื่อม/คำแบ่งประโยคที่ปลอดภัย
        'และ', 'หรือ', 'โดย', 'เพื่อ', 'ซึ่ง', 'ของ', 'กับ', 'จาก', 'ตาม',
        'เป็น', 'ให้', 'ได้', 'มี', 'ที่', 'ใน', 'งาน', 'เรื่อง', 'อาจารย์', 'สังกัด', 'ณ',
    ];

    foreach ($safeWords as $word) {
        $text = str_replace($word, $word . $zwsp, $text);
    }

    // เพิ่มจุดตัดระหว่างไทยกับอังกฤษ/ตัวเลข เพื่อกัน Word ยกอังกฤษทั้งก้อนผิดจังหวะ
    $text = preg_replace('/(?<=[\p{Thai}])(?=[A-Za-z0-9])/u', $zwsp, $text);
    $text = preg_replace('/(?<=[A-Za-z0-9])(?=[\p{Thai}])/u', $zwsp, $text);

    // จุดตัดหลังช่องว่างจริง ช่วยให้ thaiDistribute กระจายเฉพาะช่วงที่ควรกระจาย
    $text = preg_replace('/\s+/u', ' ' . $zwsp, $text);

    return $text;
}

function consentPt($pt) {
    return (int)round(((float)$pt) * 20);
}

function addConsentTitle($section) {
    // กำหนดฟอนต์ตรงนี้เลย ไม่พึ่ง style name เพื่อให้ Word ไม่ดึงขนาดเดิมค้าง
    $section->addText('หนังสือยินยอมให้นำเสนอผลงานวิจัย', [
        'name' => 'TH SarabunPSK',
        'size' => 16,
        'bold' => true,
    ], [
        'alignment' => Jc::CENTER,
        'spaceBefore' => consentPt(6),
        'spaceAfter' => consentPt(24),
        'lineHeight' => 1.0,
    ]);
}
function addConsentValue($run, $text) {
    $text = consentClean($text);

    /*
     * ต้องแยก space หน้า/หลังออกมาเป็น addText คนละตัว
     * เพราะ consentThaiWordWrap() เรียก consentClean() ซึ่ง trim ช่องว่างหัว-ท้ายทิ้ง
     * ถ้าใส่ ' ' . $text . ' ' เข้าไปใน consentThaiWordWrap() ช่องว่างจะหายเหมือนเดิม
     * โดย space ทั้งสองข้างยังใช้ valueFont เพื่อให้เส้นประต่อเนื่องกับข้อมูล
     */
    $run->addText(' ', 'valueFont');

    if ($text !== '') {
        $run->addText(consentThaiWordWrap($text), 'valueFont');
    }

    $run->addText(' ', 'valueFont');
}

function addConsentParagraph($section, array $data) {
    /*
     * ใช้ thaiDistribute ได้ แต่ต้องให้ข้อความผ่าน consentThaiWordWrap()
     * เพื่อใส่จุดตัดคำแบบปลอดภัยก่อน ไม่อย่างนั้น Word จะกระจายตัวอักษรทั้งก้อน
     * จนช่องไฟห่างแบบรูปที่ 1
     */
    $run = $section->addTextRun([
        'alignment' => 'thaiDistribute',
        'lineHeight' => 1.16,
        'spaceBefore' => 0,
        'spaceAfter' => consentPt(12),
        'indentation' => ['firstLine' => Converter::cmToTwip(2.15)],
    ]);

    $run->addText(consentThaiWordWrap('ข้าพเจ้า '), 'normalFont');
    addConsentValue($run, $data['ownerName']);
    $run->addText(consentThaiWordWrap(' ได้ยอมให้ '), 'normalFont');
    addConsentValue($run, $data['presenterName']);
    $run->addText(consentThaiWordWrap(' อาจารย์สังกัด' . $data['displayDepartmentFull'] . ' ' . $data['displayFaculty'] . ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี ได้นำเสนอผลงานวิจัย เรื่อง '), 'normalFont');
    addConsentValue($run, $data['researchTitle']);
    $run->addText(consentThaiWordWrap(' ในงานการประชุมวิชาการ' . $data['conferenceLevel'] . ' '), 'normalFont');
    addConsentValue($run, $data['conferenceName']);
    $run->addText(consentThaiWordWrap(' โดยงานการประชุมจัดขึ้นที่ '), 'normalFont');
    addConsentValue($run, $data['conferencePlace']);
    $run->addText(consentThaiWordWrap(' ในระหว่างวันที่ '), 'normalFont');
    addConsentValue($run, $data['presentationDate']);
}

function addConsentSignature($section, $ownerName, $signatureAffiliation) {
    $section->addText('', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => Converter::cmToTwip(2.75),
        'lineHeight' => 1.0,
    ]);

    $section->addText('(' . consentClean($ownerName) . ')', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
        'indentation' => ['left' => Converter::cmToTwip(8.0)],
    ]);

    if (trim((string)$signatureAffiliation) !== '') {
        $section->addText(consentClean($signatureAffiliation), 'normalFont', [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
            'indentation' => ['left' => Converter::cmToTwip(8.0)],
        ]);
    }
}

function addConsentPage($phpWord, array $data) {
    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => Converter::cmToTwip(2.0),
        'marginBottom' => Converter::cmToTwip(1.5),
        'marginLeft' => Converter::cmToTwip(3.0),
        'marginRight' => Converter::cmToTwip(2.0),
    ]);

    addConsentTitle($section);
    addConsentParagraph($section, $data);
    addConsentSignature($section, $data['ownerName'], $data['signatureAffiliation']);
}

$ownerName = consentField($valueMapByKey, $valueMap, 'owner_name', 2, '');
$faculty = consentField($valueMapByKey, $valueMap, 'faculty', 10, 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม');
$department = consentField($valueMapByKey, $valueMap, 'department', 11, 'เทคโนโลยีสารสนเทศ');
$presenterName = consentField($valueMapByKey, $valueMap, 'presenter_name', 14, '');
$researchTitle = consentField($valueMapByKey, $valueMap, 'research_title', 13, '');
$conferenceLevel = consentField($valueMapByKey, $valueMap, 'conference_level', 15, '');
$conferenceName = consentField($valueMapByKey, $valueMap, 'conference_name', 5, '');
$conferencePlace = consentField($valueMapByKey, $valueMap, 'conference_place', 7, '');
$presentationDate = consentThaiDateArabic(consentField($valueMapByKey, $valueMap, 'presentation_date', 16, ''));
$signatureAffiliation = consentField($valueMapByKey, $valueMap, 'signature_affiliation', 17, '');

$displayFaculty = consentClean($faculty);
if ($displayFaculty === '') {
    $displayFaculty = 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
}
if (mb_strpos($displayFaculty, 'คณะ') !== 0) {
    $displayFaculty = 'คณะ' . $displayFaculty;
}

$displayDepartment = consentClean($department);
$displayDepartmentFull = (mb_strpos($displayDepartment, 'ภาควิชา') === 0)
    ? $displayDepartment
    : 'ภาควิชา' . $displayDepartment;

$phpWord = new PhpWord();
setupWordDefaults($phpWord);

$phpWord->addFontStyle('normalFont', [
    'name' => 'TH SarabunPSK',
    'size' => 16,
]);
$phpWord->addFontStyle('valueFont', [
    'name' => 'TH SarabunPSK',
    'size' => 16,
    'bold' => true,
    'underline' => 'dotted',
]);
$phpWord->addFontStyle('titleFont', [
    'name' => 'TH SarabunPSK',
    'size' => 16,
    'bold' => true,
]);

addConsentPage($phpWord, [
    'ownerName' => $ownerName,
    'presenterName' => $presenterName,
    'displayDepartmentFull' => $displayDepartmentFull,
    'displayFaculty' => $displayFaculty,
    'researchTitle' => $researchTitle,
    'conferenceLevel' => $conferenceLevel,
    'conferenceName' => $conferenceName,
    'conferencePlace' => $conferencePlace,
    'presentationDate' => $presentationDate,
    'signatureAffiliation' => $signatureAffiliation,
]);

$filename = 'consent_research_presentation_' . $docId . '.docx';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;