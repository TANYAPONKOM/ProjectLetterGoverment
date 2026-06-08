<?php
// Pro_letter/documents/test_word_download.php
// ใช้ทดสอบดาวน์โหลด Word จาก template ต่าง ๆ โดยไม่ต้องใช้ id จากฐานข้อมูล

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/word_templates/word_common.php';
require_once __DIR__ . '/word_templates/word_academic_1.php';
require_once __DIR__ . '/word_templates/word_speaker.php';
require_once __DIR__ . '/word_templates/word_room_request.php';
require_once __DIR__ . '/word_templates/word_sut_wellness.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// เลือก template จาก URL เช่น ?template=sut
$template = $_GET['template'] ?? 'sut';

// สร้าง Word
$phpWord = new PhpWord();
setupWordDefaults($phpWord);

/*
|--------------------------------------------------------------------------
| ข้อมูลจำลองสำหรับทดสอบ
|--------------------------------------------------------------------------
| ไฟล์ test นี้ไม่ดึงฐานข้อมูล จึงสร้าง array เปล่าหรือ dummy data ไว้ก่อน
| template บางตัวอาจใช้ $document / $valueMap / $budgetItems
*/
$document = [
    'document_id' => 999,
    'template_id' => 0,
    'owner_id' => 1,
    'department_id' => 1,
    'doc_no' => 'อว ๗๑๐๑.๑๕/',
    'doc_date' => '2026-05-24',
    'subject' => 'เข้าร่วมประชุมวิชาการในงานประชุมเรื่องการแต่งกายนักศึกษาหญิงและระเบียบการเข้าสังคมของคน',
    'header_text' => 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม ภาควิชาเทคโนโลยีสารสนเทศ',
    'status' => 'draft',
];

$valueMap = [
    1 => '2026-05-24',
    2 => 'ดร.พิทย์พิน ชูรอด',
    3 => 'อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ',
    4 => 'เข้าร่วมประชุมวิชาการในงาน',
    5 => 'ประชุมเรื่องการแต่งกายนักศึกษาหญิงและระเบียบการเข้าสังคมของคน',
    6 => '๒๔ พฤษภาคม ๒๕๖๙',
    7 => 'รูปแบบออนไลน์',
    8 => '0',
    9 => '',
    10 => 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม',
    11 => 'เทคโนโลยีสารสนเทศ',
    12 => '1',
    13 => '',
];

$budgetItems = [];

/*
|--------------------------------------------------------------------------
| เลือก template ที่ต้องการทดสอบ
|--------------------------------------------------------------------------
*/
switch ($template) {
    case 'academic':
        buildAcademicOneWord($phpWord, $document, $valueMap, $budgetItems);
        $filename = 'test_academic_word.docx';
        break;

    case 'speaker':
        buildSpeakerMemoWord($phpWord, $document, $valueMap, $budgetItems);
        $filename = 'test_speaker_word.docx';
        break;

    case 'room':
        buildRoomRequestMemoWord($phpWord, $document, $valueMap, $budgetItems);
        $filename = 'test_room_request_word.docx';
        break;

    case 'sut':
    default:
        buildSutWellnessWord($phpWord, $document, $valueMap, $budgetItems);
        $filename = 'test_sut_wellness_word_' . date('His') . '.docx';
        break;
}

// ล้าง output buffer กันไฟล์ Word เสีย
while (ob_get_level() > 0) {
    ob_end_clean();
}

// ส่งออกไฟล์ Word
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;