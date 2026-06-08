<?php
// Pro_letter/documents/download_word_invite_speaker.php
// ดาวน์โหลด Word (.docx) สำหรับหนังสือเรียนเชิญวิทยากร
<<<<<<< HEAD
// เวอร์ชัน OpenXML โดยตรง: เปิดได้ + จัดหน้า/รูปครุฑตามโครงเดิม
=======
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a

session_start();
require_once __DIR__ . '/../functions.php';

<<<<<<< HEAD
=======
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

>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
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

<<<<<<< HEAD
function invite_word_thai_digit($text) {
=======
function wordThaiDigit($text) {
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    return strtr((string)$text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}

<<<<<<< HEAD
function invite_word_arabic_digit($text) {
=======
function wordArabicDigit($text) {
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    return strtr((string)$text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
}

<<<<<<< HEAD
function invite_word_thai_months() {
=======
function inviteThaiMonths() {
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    return [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
}

<<<<<<< HEAD
function invite_word_thai_date_from_parts($year, $month, $day, $withWeekday = false) {
    $year = (int)$year;
    $month = (int)$month;
    $day = (int)$day;
    $christYear = $year > 2400 ? $year - 543 : $year;
    $thaiYear = $year > 2400 ? $year : $year + 543;
=======
function inviteThaiDateFromParts($year, $month, $day, $withWeekday = false) {
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
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a

    if (!checkdate($month, $day, $christYear)) {
        return '';
    }

<<<<<<< HEAD
    $months = invite_word_thai_months();
    $dateText = $day . ' ' . ($months[$month] ?? '') . ' ' . $thaiYear;
    if ($withWeekday) {
        $ts = strtotime(sprintf('%04d-%02d-%02d', $christYear, $month, $day));
        if ($ts !== false) {
            $days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
            $dateText = 'วัน' . ($days[(int)date('w', $ts)] ?? '') . 'ที่ ' . $dateText;
        }
    }
    return invite_word_thai_digit($dateText);
}

function invite_word_thai_date_any($rawDate, $withWeekday = false) {
    $rawDate = trim(invite_word_arabic_digit((string)$rawDate));
=======
    $months = inviteThaiMonths();
    $dateText = wordThaiDigit($day) . ' ' . $months[$month] . ' ' . wordThaiDigit($thaiYear);

    if ($withWeekday) {
        $weekdays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        $w = (int)date('w', strtotime(sprintf('%04d-%02d-%02d', $christYear, $month, $day)));
        return 'วัน' . $weekdays[$w] . 'ที่ ' . $dateText;
    }

    return $dateText;
}

function inviteThaiDateAny($rawDate, $withWeekday = false) {
    $rawDate = trim((string)$rawDate);
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    if ($rawDate === '') {
        return '';
    }
    if ($withWeekday && preg_match('/^วัน/u', $rawDate)) {
<<<<<<< HEAD
        return invite_word_thai_digit($rawDate);
    }
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $rawDate, $m)) {
        return invite_word_thai_date_from_parts($m[1], $m[2], $m[3], $withWeekday);
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $rawDate, $m)) {
        return invite_word_thai_date_from_parts($m[3], $m[2], $m[1], $withWeekday);
    }
    return invite_word_thai_digit($rawDate);
}

function invite_word_format_time_range($timeText) {
=======
        return wordThaiDigit($rawDate);
    }

    $date = wordArabicDigit($rawDate);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        return inviteThaiDateFromParts($m[1], $m[2], $m[3], $withWeekday);
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $m)) {
        return inviteThaiDateFromParts($m[3], $m[2], $m[1], $withWeekday);
    }

    $months = inviteThaiMonths();
    $monthRegex = implode('|', array_map('preg_quote', $months));
    if (preg_match('/(\d{1,2})\s+(' . $monthRegex . ')\s+(\d{4})/u', $date, $m)) {
        $monthNumber = array_search($m[2], $months, true);
        if ($monthNumber !== false) {
            return inviteThaiDateFromParts($m[3], $monthNumber, $m[1], $withWeekday);
        }
    }

    return wordThaiDigit($rawDate);
}

function inviteFormatThaiTimeRange($timeText) {
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    $timeText = trim((string)$timeText);
    if ($timeText === '') {
        return '';
    }
    $timeText = str_replace(':', '.', $timeText);
    $timeText = preg_replace('/\s*-\s*/', ' - ', $timeText);
<<<<<<< HEAD
    return invite_word_thai_digit($timeText);
}

function invite_word_clean_text($text, $thaiDigit = true) {
    $text = (string)$text;
    $text = str_replace(["\r", "\n"], ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    // ล้าง control characters ที่ XML/Word ไม่รับ
    if ($text !== '') {
        $cleaned = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text);
        if ($cleaned !== null) {
            $text = $cleaned;
        }
    }

    return $thaiDigit ? invite_word_thai_digit($text) : $text;
}


function invite_word_wrap_thai_text($text) {
    $text = invite_word_clean_text($text, true);
=======
    return wordThaiDigit($timeText);
}

function inviteClean($text) {
    return wordThaiDigit(cleanWordText(str_replace(["\r", "\n"], ' ', (string)$text)));
}

function inviteInlineText($text) {
    $text = inviteClean($text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function inviteThaiWordWrap($text) {
    $text = inviteInlineText($text);
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    if ($text === '') {
        return '';
    }

    $zwsp = "\u{200B}";

<<<<<<< HEAD
    static $words = null;
    if ($words === null) {
        $words = [
            'ผู้ช่วยศาสตราจารย์', 'รองศาสตราจารย์', 'ศาสตราจารย์', 'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ',
            'คณะเทคโนโลยีและการจัดการอุตสาหกรรม', 'ภาควิชาเทคโนโลยีสารสนเทศ', 'เทคโนโลยีสารสนเทศ',
            'วิทยาเขตปราจีนบุรี', 'โครงการอบรมเชิงปฏิบัติการ', 'การพัฒนาเว็บแอปพลิเคชัน', 'เว็บแอปพลิเคชัน',
            'เพื่อสนับสนุนงานองค์กรดิจิทัล', 'สนับสนุนงานองค์กรดิจิทัล', 'องค์กรดิจิทัล', 'ระบบสารสนเทศ',
            'การออกแบบและพัฒนาระบบสารสนเทศ', 'การออกแบบ', 'การพัฒนา', 'ระบบสารสนเทศ', 'หน่วยงาน',
            'รายละเอียดโครงการ', 'ตามสิ่งที่ส่งมาด้วย', 'สิ่งที่ส่งมาด้วย', 'แบบตอบรับการเป็นวิทยากร',
            'การเป็นวิทยากร', 'เป็นวิทยากร', 'วิทยากร', 'ผู้มีความรู้', 'ความรู้', 'ความเชี่ยวชาญ',
            'ประสบการณ์', 'ด้านการพัฒนาระบบสารสนเทศ', 'ด้านการพัฒนา', 'ถ่ายทอดความรู้', 'ผู้เข้าร่วมโครงการ',
            'เข้าร่วมโครงการ', 'โครงการ', 'บรรยาย', 'เรื่องดังกล่าว', 'ดังกล่าว', 'ตามวัน', 'เวลา', 'สถานที่',
            'ข้างต้น', 'จึงขอเรียนเชิญ', 'เรียนเชิญ', 'ท่านเป็นวิทยากร', 'จึงเรียนมา', 'เพื่อโปรดพิจารณา',
            'ให้ความอนุเคราะห์', 'จะขอบคุณยิ่ง', 'ด้วยภาควิชา', 'ภาควิชา', 'คณะ', 'มหาวิทยาลัย', 'ดำเนินการจัด',
            'ได้ดำเนินการจัด', 'อบรมเชิงสัมมนา', 'อบรมเชิงปฏิบัติการ', 'สัมมนา', 'ปฏิบัติการ', 'นวัตกรรม',
            'เทคโนโลยี', 'ดิจิทัล', 'วัตถุประสงค์', 'เกี่ยวกับ', 'สามารถ', 'ประยุกต์ใช้', 'อย่างมีประสิทธิภาพ',
            'ห้องประชุม', 'อาคาร', 'ผู้ช่วย', 'อาจารย์', 'ผู้อำนวยการ', 'ศูนย์พัฒนาทักษะดิจิทัล',
            'และนวัตกรรมสารสนเทศ', 'นวัตกรรมสารสนเทศ', 'ศูนย์พัฒนา', 'ทักษะดิจิทัล',
            'จำนวน', 'ฉบับ', 'ชุด', 'วันที่', 'เดือน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม',
            'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน',
            'ที่', 'ใน', 'ณ', 'น', 'ม', 'จ', 'อ', 'ต', 'หมู่', 'เรื่อง', 'เรียน', 'ด้วย', 'โดย', 'มี', 'เพื่อ',
            'ให้', 'แก่', 'ของ', 'และ', 'เป็น', 'การ', 'งาน', 'ได้', 'ตาม', 'ซึ่ง', 'จาก', 'นี้', 'นั้น', 'พร้อม',
            'ขอ', 'เชิญ', 'ท่าน', 'ดัง', 'กล่าว'
        ];
        usort($words, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
        });
    }

    $wrapThaiRun = function ($thaiText) use ($words, $zwsp) {
        $thaiText = (string)$thaiText;
        $len = mb_strlen($thaiText, 'UTF-8');
        if ($len <= 10) {
            return $thaiText;
        }

        $parts = [];
        $pos = 0;
=======
    $wrapThaiRun = function ($thaiText) use ($zwsp) {
        $thaiText = (string)$thaiText;
        if ($thaiText === '') {
            return '';
        }

        // ใช้ตัวตัดคำของ ICU ถ้าเครื่องเปิด extension intl ไว้
        // เพื่อให้ Word ตัดบรรทัดที่ขอบคำ ไม่ใช่กลางคำไทย
        if (class_exists('IntlBreakIterator')) {
            $breaker = IntlBreakIterator::createWordInstance('th_TH');
            if ($breaker) {
                $breaker->setText($thaiText);
                $parts = [];
                $start = $breaker->first();
                for ($end = $breaker->next(); $end !== IntlBreakIterator::DONE; $start = $end, $end = $breaker->next()) {
                    $part = substr($thaiText, $start, $end - $start);
                    if ($part !== '') {
                        $parts[] = $part;
                    }
                }
                if (count($parts) > 1) {
                    return implode($zwsp, $parts);
                }
            }
        }

        // fallback กรณีเครื่องไม่มี intl:
        // ตัดเฉพาะคำที่รู้จักในเอกสารนี้ และถ้าไม่รู้จักให้เก็บเป็นก้อนเดิม
        // ห้ามแทรก zero-width space ระหว่างตัวอักษรทุกตัว เพราะจะทำให้ชื่อ/ตำแหน่งแตก เช่น "เรื่อ ง" หรือ "สร้ าง"
        static $words = null;
        if ($words === null) {
            $words = [
                'ผู้ช่วยศาสตราจารย์', 'รองศาสตราจารย์', 'ศาสตราจารย์', 'อาจารย์', 'ตำแหน่ง', 'ประจำสาขาวิชา',
                'นวัตกรรมดิจิทัลและสื่อสร้างสรรค์', 'นวัตกรรมดิจิทัล', 'สื่อสร้างสรรค์', 'วัตกรรมดิจิทัลและสื่อสร้างสรรค์',
                'มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม',
                'ภาควิชาเทคโนโลยีสารสนเทศ', 'เทคโนโลยีสารสนเทศ', 'วิทยาเขตปราจีนบุรี', 'วิทยาเขตนนทบุรี',
                'ศูนย์การเรียนรู้นวัตกรรมดิจิทัล', 'โครงการอบรมเชิงปฏิบัติการ', 'การสร้างสื่อดิจิทัล',
                'อย่างสร้างสรรค์', 'เพื่อพัฒนาทักษะการเรียนรู้', 'ในศตวรรษที่', 'ห้องประชุมชั้น',
                'โดยมีวัตถุประสงค์', 'เพื่อให้นักศึกษา', 'ได้รับความรู้', 'เกี่ยวกับ', 'กระบวนการ',
                'การสร้าง', 'สื่อดิจิทัล', 'อย่างมีคุณภาพ', 'สามารถ', 'วางแผน', 'ออกแบบ', 'และพัฒนา',
                'สื่อเพื่อใช้', 'ในการนำเสนอ', 'ข้อมูล', 'ได้อย่างเหมาะสม', 'รวมทั้ง', 'ส่งเสริม',
                'ให้นักศึกษา', 'มีทักษะ', 'ด้านความคิดสร้างสรรค์', 'การทำงานเป็นทีม', 'และการประยุกต์ใช้',
                'เทคโนโลยี', 'ในงานวิชาชีพ', 'รายละเอียดโครงการ', 'ตามสิ่งที่ส่งมาด้วย', 'ด้วยท่านเป็น',
                'ผู้มีความรู้', 'ความสามารถ', 'และประสบการณ์', 'ด้านการออกแบบ', 'การพัฒนานวัตกรรม',
                'เพื่อการเรียนรู้', 'และการประยุกต์ใช้เทคโนโลยี', 'สร้างสรรค์', 'จึงเห็นสมควร',
                'เรียนเชิญ', 'ท่านเป็นวิทยากร', 'ถ่ายทอดความรู้', 'ให้แก่นักศึกษา', 'ตามวัน', 'เวลา',
                'และสถานที่', 'ข้างต้น', 'จึงขอ', 'บรรยาย', 'เรื่องดังกล่าว', 'จึงเรียนมา',
                'เพื่อโปรดพิจารณา', 'ให้ความอนุเคราะห์', 'จะขอบคุณยิ่ง', 'วันอาทิตย์ที่', 'พฤษภาคม',
                'รายละเอียด', 'แบบตอบรับ', 'การเป็นวิทยากร', 'จำนวน', 'ฉบับ', 'ชุด', 'เรื่อง', 'เรียน',
                'อาคาร', 'ห้องประชุม', 'โครงการ', 'อบรม', 'เชิงปฏิบัติการ', 'ดร', 'ณ', 'น', 'ใน', 'ให้',
                'แก่', 'ซึ่ง', 'เป็น', 'และ', 'ของ', 'ที่'
            ];
            usort($words, function ($a, $b) {
                return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
            });
        }

        $outParts = [];
        $len = mb_strlen($thaiText, 'UTF-8');
        $pos = 0;

>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
        while ($pos < $len) {
            $matched = '';
            foreach ($words as $word) {
                $wordLen = mb_strlen($word, 'UTF-8');
                if ($wordLen > 0 && mb_substr($thaiText, $pos, $wordLen, 'UTF-8') === $word) {
                    $matched = $word;
                    break;
                }
            }

            if ($matched !== '') {
<<<<<<< HEAD
                $parts[] = $matched;
=======
                $outParts[] = $matched;
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
                $pos += mb_strlen($matched, 'UTF-8');
                continue;
            }

<<<<<<< HEAD
            // คำเฉพาะ/ชื่อเฉพาะที่ไม่อยู่ใน dictionary ให้เก็บเป็นก้อนยาวพอสมควร
            // ไม่ตัดทีละตัว และไม่ตัดสั้นเกินไป เพราะจะทำให้กระจายไทยห่างผิดธรรมชาติ
=======
            // กรณีเป็นชื่อคน/คำเฉพาะที่ไม่มีในรายการ ให้เก็บต่อเนื่องจนกว่าจะเจอคำรู้จักถัดไป
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
            $unknown = '';
            while ($pos < $len) {
                $nextKnown = false;
                foreach ($words as $word) {
                    $wordLen = mb_strlen($word, 'UTF-8');
                    if ($wordLen > 0 && mb_substr($thaiText, $pos, $wordLen, 'UTF-8') === $word) {
                        $nextKnown = true;
                        break;
                    }
                }
                if ($nextKnown && $unknown !== '') {
                    break;
                }
                $unknown .= mb_substr($thaiText, $pos, 1, 'UTF-8');
                $pos++;
<<<<<<< HEAD

                // ถ้าเป็นคำไม่รู้จักที่ยาวมาก ให้เปิดจุดตัดแบบก้อน 12 ตัวอักษร
                // เพื่อให้ Word มีจุดขึ้นบรรทัด แต่ยังไม่แตกเป็นรายตัว
                if (mb_strlen($unknown, 'UTF-8') >= 12) {
                    break;
                }
            }
            if ($unknown !== '') {
                $parts[] = $unknown;
            }
        }

        $parts = array_values(array_filter($parts, fn($part) => $part !== ''));
        return implode($zwsp, $parts);
    };

    // ตัดเฉพาะช่วงภาษาไทยที่ติดกันยาว ๆ เพื่อให้บรรทัดถูกเติมเต็มก่อนกระจายแบบไทย
    $text = preg_replace_callback('/[\p{Thai}]{11,}/u', function ($m) use ($wrapThaiRun) {
        return $wrapThaiRun($m[0]);
    }, $text);

    // เพิ่มจุดตัดหลังเครื่องหมายที่มักติดคำ โดยไม่เพิ่มช่องว่างจริง
=======
            }
            if ($unknown !== '') {
                $outParts[] = $unknown;
            }
        }

        return implode($zwsp, array_filter($outParts, fn($part) => $part !== ''));
    };

    // ใส่จุดตัดเฉพาะช่วงข้อความไทยติดกันยาว ๆ เท่านั้น ไม่ยุ่งกับช่องว่างเดิม
    $text = preg_replace_callback('/[\p{Thai}]+/u', function ($m) use ($wrapThaiRun) {
        return $wrapThaiRun($m[0]);
    }, $text);

    // เพิ่มจุดตัดหลังเครื่องหมาย ไม่กระทบการเว้นวรรคเดิม
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
    $text = preg_replace('/([\/\-–—,;:()（）])/u', '$1' . $zwsp, $text);

    return $text;
}

<<<<<<< HEAD
function invite_word_xml($text, $thaiDigit = true) {
    $text = invite_word_clean_text($text, $thaiDigit);
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function invite_word_rpr($size = 32, $bold = false) {
    $b = $bold ? '<w:b/>' : '';
    return '<w:rPr><w:rFonts w:ascii="TH SarabunPSK" w:hAnsi="TH SarabunPSK" w:cs="TH SarabunPSK"/><w:sz w:val="' . (int)$size . '"/><w:szCs w:val="' . (int)$size . '"/>' . $b . '</w:rPr>';
}

function invite_word_run($text, $size = 32, $bold = false) {
    return '<w:r>' . invite_word_rpr($size, $bold) . '<w:t xml:space="preserve">' . invite_word_xml($text, true) . '</w:t></w:r>';
}

function invite_word_p($text, $align = 'left', $firstLineTwip = 0, $spaceAfter = 80, $size = 32, $bold = false, $spaceBefore = 0, $line = 240) {
    if ($align === 'thaiDistribute') {
        $text = invite_word_wrap_thai_text($text);
    }
    $indent = $firstLineTwip > 0 ? '<w:ind w:firstLine="' . (int)$firstLineTwip . '"/>' : '';
    return '<w:p><w:pPr><w:jc w:val="' . $align . '"/><w:spacing w:before="' . (int)$spaceBefore . '" w:after="' . (int)$spaceAfter . '" w:line="' . (int)$line . '" w:lineRule="auto"/>' . $indent . '</w:pPr>'
        . invite_word_run($text, $size, $bold) . '</w:p>';
}

function invite_word_empty_p($spaceAfter = 0, $line = 240) {
    return '<w:p><w:pPr><w:spacing w:after="' . (int)$spaceAfter . '" w:line="' . (int)$line . '" w:lineRule="auto"/></w:pPr></w:p>';
}

function invite_word_cell($content, $width, $valign = 'top') {
    return '<w:tc><w:tcPr><w:tcW w:w="' . (int)$width . '" w:type="dxa"/><w:vAlign w:val="' . $valign . '"/><w:tcMar><w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tcMar></w:tcPr>' . $content . '</w:tc>';
}

function invite_word_table($rows, $width = 9697) {
    $grid = '';
    if (!empty($rows[0]['widths'])) {
        foreach ($rows[0]['widths'] as $w) {
            $grid .= '<w:gridCol w:w="' . (int)$w . '"/>';
        }
    }
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . (int)$width . '" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>';
    foreach ($rows as $row) {
        $height = isset($row['height']) ? '<w:trPr><w:trHeight w:val="' . (int)$row['height'] . '" w:hRule="atLeast"/></w:trPr>' : '';
        $xml .= '<w:tr>' . $height;
        foreach ($row['cells'] as $cell) {
            $xml .= invite_word_cell($cell['content'], $cell['width'], $cell['valign'] ?? 'top');
        }
        $xml .= '</w:tr>';
    }
    $xml .= '</w:tbl>';
    return $xml;
}

function invite_word_image_p($relId, $cx = 972000, $cy = 1080000) {
    return '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="0"/></w:pPr><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . (int)$cx . '" cy="' . (int)$cy . '"/><wp:effectExtent l="0" t="0" r="0" b="0"/><wp:docPr id="1" name="Garuda"/><wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="0" name="garuda.jpg"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip r:embed="' . $relId . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . (int)$cx . '" cy="' . (int)$cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
}

function invite_word_header_block($displayFaculty, $thaiDocDate, $hasGaruda) {
    $left = invite_word_empty_p(720) . invite_word_p('ที่', 'left', 0, 0, 32, false, 0, 240);
    $middle = $hasGaruda ? invite_word_image_p('rIdGaruda', 972000, 1080000) : invite_word_empty_p(0);
    $right = invite_word_empty_p(650)
        . invite_word_p($displayFaculty, 'left', 0, 0, 31, false, 0, 228)
        . invite_word_p('มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'left', 0, 0, 31, false, 0, 228)
        . invite_word_p('๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐', 'left', 0, 0, 31, false, 0, 228);

    $xml = invite_word_table([
        [
            'height' => 1800,
            'widths' => [2806, 2013, 4878],
            'cells' => [
                ['width' => 2806, 'content' => $left],
                ['width' => 2013, 'content' => $middle],
                ['width' => 4878, 'content' => $right],
            ],
        ],
    ], 9697);

    $xml .= invite_word_p($thaiDocDate ?: '๗ ตุลาคม ๒๕๖๘', 'center', 0, 160, 32, false, 60, 240);
    return $xml;
}

function invite_word_pair_row($label, $value) {
    return invite_word_table([
        [
            'widths' => [567, 8505],
            'cells' => [
                ['width' => 567, 'content' => invite_word_p($label, 'left', 0, 0, 32, false, 0, 240)],
                ['width' => 8505, 'content' => invite_word_p($value, 'left', 0, 0, 32, false, 0, 240)],
            ],
        ],
    ], 9072);
}

function invite_word_attachment_rows() {
    $rows = [];
    $data = [
        ['สิ่งที่ส่งมาด้วย', '๑. รายละเอียดโครงการ', 'จำนวน ๑ ชุด'],
        ['', '๒. แบบตอบรับการเป็นวิทยากร', 'จำนวน ๑ ฉบับ'],
    ];
    foreach ($data as $r) {
        $rows[] = [
            'widths' => [1247, 4706, 3119],
            'cells' => [
                ['width' => 1247, 'content' => invite_word_p($r[0], 'left', 0, 0, 32, false, 0, 240)],
                ['width' => 4706, 'content' => invite_word_p($r[1], 'left', 0, 0, 32, false, 0, 240)],
                ['width' => 3119, 'content' => invite_word_p($r[2], 'left', 0, 0, 32, false, 0, 240)],
            ],
        ];
    }
    return invite_word_table($rows, 9072) . invite_word_empty_p(0);
}

function invite_word_footer_xml($displayDepartmentFull) {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . invite_word_p($displayDepartmentFull, 'left', 0, 0, 32, false, 0, 216)
        . invite_word_p('โทรศัพท์ ๐-๓๗๒๑-๗๓๔๐-๓ ต่อ ๗๐๖๕-๖', 'left', 0, 0, 32, false, 0, 216)
        . invite_word_p('ไปรษณีย์อิเล็กทรอนิกส์ : it@itm.kmutnb.ac.th', 'left', 0, 0, 32, false, 0, 216)
        . '</w:ftr>';
}

function invite_word_create_docx($path, $documentXml, $footerXml, $garudaPath) {
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $hasGaruda = is_file($garudaPath);
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Default Extension="jpg" ContentType="image/jpeg"/>
<Default Extension="jpeg" ContentType="image/jpeg"/>
<Default Extension="png" ContentType="image/png"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rIdFooter1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>';
    if ($hasGaruda) {
        $rels .= '<Relationship Id="rIdGaruda" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/garuda.jpg"/>';
    }
    $rels .= '</Relationships>';
    $zip->addFromString('word/_rels/document.xml.rels', $rels);

    $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr><w:rPr><w:rFonts w:ascii="TH SarabunPSK" w:hAnsi="TH SarabunPSK" w:cs="TH SarabunPSK"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr></w:style>
</w:styles>');

    $zip->addFromString('word/settings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:compat/><w:decimalSymbol w:val="."/><w:listSeparator w:val=","/></w:settings>');

    $zip->addFromString('word/footer1.xml', $footerXml);
    if ($hasGaruda) {
        $zip->addFile($garudaPath, 'word/media/garuda.jpg');
    }
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->close();

    return is_file($path) && filesize($path) > 0;
=======
function addInviteTextRunPara($section, array $parts, $spaceAfter = 35) {
    $text = '';

    foreach ($parts as $part) {
        if (is_array($part)) {
            $text .= $part[0] ?? '';
        } else {
            $text .= $part;
        }
    }

    $text = inviteThaiWordWrap($text);
    if ($text === '') {
        return;
    }

    $section->addText($text, 'normalFont', [
        // ใช้กระจายแบบไทย แต่ต้องใส่จุดตัดคำก่อน ไม่งั้น Word จะกินพื้นที่/เว้นช่องว่างแปลก
        'alignment' => 'thaiDistribute',
        'lineHeight' => 0.92,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'indentation' => ['firstLine' => Converter::cmToTwip(2.5)],
    ]);
}

function inviteNoBorderCell($valign = 'top') {
    return [
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => 0,
        'cellMarginRight' => 0,
        'valign' => $valign,
    ];
}

function addInvitePairRow($section, $label, $value) {
    $label = inviteInlineText($label);
    $value = inviteThaiWordWrap($value);

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => 9072,
    ]);

    $table->addRow();

    // ช่องหัวข้อ เช่น เรื่อง / เรียน ให้แคบลงนิด เพื่อเพิ่มพื้นที่ข้อความด้านขวา
    $table->addCell(Converter::cmToTwip(1.0), inviteNoBorderCell())->addText($label, 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);

    // ช่องข้อความด้านขวา เพิ่มความกว้างจากเดิม เพื่อไม่ให้ขึ้นบรรทัดใหม่เร็วเกินไป
    $table->addCell(Converter::cmToTwip(15.0), inviteNoBorderCell())->addText($value, 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addInviteAttachmentRows($section) {
    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => 9072,
    ]);

    $rows = [
        ['สิ่งที่ส่งมาด้วย', '๑. รายละเอียดโครงการ', 'จำนวน ๑ ชุด'],
        ['', '๒. แบบตอบรับการเป็นวิทยากร', 'จำนวน ๑ ฉบับ'],
    ];

    foreach ($rows as $r) {
        $table->addRow();

        $table->addCell(Converter::cmToTwip(2.2), inviteNoBorderCell())->addText($r[0], 'normalFont', [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);

        $table->addCell(Converter::cmToTwip(8.3), inviteNoBorderCell())->addText($r[1], 'normalFont', [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
            'indentation' => ['left' => Converter::cmToTwip(0.25)],
        ]);

        $table->addCell(Converter::cmToTwip(5.5), inviteNoBorderCell())->addText($r[2], 'normalFont', [
            'spaceBefore' => 0,
            'spaceAfter' => 0,
            'lineHeight' => 1.0,
        ]);
    }

    // ลดช่องว่างก่อนขึ้นย่อหน้า "ด้วยภาควิชา..."
    $section->addText('', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addInviteHeader($section, $displayFaculty, $thaiDocDate) {
    $garuda = __DIR__ . '/../assets/img/garuda.jpg';

    $table = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'cellSpacing' => 0,
        'layout' => 'fixed',
        'width' => Converter::cmToTwip(17.10),
    ]);

    $table->addRow(Converter::cmToTwip(3.1));

    $left = $table->addCell(Converter::cmToTwip(4.95), inviteNoBorderCell('top'));
    $left->addText('', 'normalFont', ['spaceAfter' => 800, 'lineHeight' => 1.0]);
    $left->addText('ที่', 'normalFont', ['spaceAfter' => 0, 'lineHeight' => 1.0]);

    $middle = $table->addCell(Converter::cmToTwip(3.55), inviteNoBorderCell('top'));
    if (file_exists($garuda)) {
        $middle->addImage($garuda, [
            'width' => 80,
            'alignment' => Jc::CENTER,
        ]);
    } else {
        $middle->addText('');
    }

    $right = $table->addCell(Converter::cmToTwip(8.60), inviteNoBorderCell('top'));
    $right->addText('', 'normalFont', ['spaceAfter' => 720, 'lineHeight' => 0.95]);
    $right->addText(inviteClean($displayFaculty), 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);
    $right->addText('๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐', 'addressFont', ['spaceAfter' => 0, 'lineHeight' => 0.95]);

    $section->addText(inviteClean($thaiDocDate ?: '๗ ตุลาคม ๒๕๖๘'), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceBefore' => 80,
        'spaceAfter' => 160,
        'lineHeight' => 1.0,
        'indentation' => ['left' => Converter::cmToTwip(1.0)],
    ]);
}

function addInviteSignature($section, $displayFacultyDean) {
    $section->addText('จึงเรียนมาเพื่อโปรดพิจารณาให้ความอนุเคราะห์ จะขอบคุณยิ่ง', 'normalFont', [
        'indentation' => ['firstLine' => Converter::cmToTwip(2.5)],
        'lineHeight' => 1.38,
        'spaceAfter' => 220,
    ]);

    $section->addText('ขอแสดงความนับถือ', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 500,
        'lineHeight' => 1.0,
    ]);

    $section->addText('(ผู้ช่วยศาสตราจารย์ ดร.กฤษฎากร บุดดาจันทร์)', 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
    $section->addText(inviteClean($displayFacultyDean), 'normalFont', [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
    ]);
}

function addInviteFooter($section, $displayDepartmentFull) {
    /*
     * อย่าใส่ข้อมูลติดต่อเป็นเนื้อหา body ต่อท้ายเอกสาร
     * เพราะถ้าเนื้อหายาว Word จะดัน 3 บรรทัดนี้ไปหน้าใหม่
     * ให้ใช้ footer ของ Word แทน เพื่อให้เกาะอยู่ท้ายหน้ากระดาษ
     */
    $footer = $section->addFooter();

    $footer->addText(inviteClean($displayDepartmentFull), 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.9,
    ]);
    $footer->addText('โทรศัพท์ ๐-๓๗๒๑-๗๓๔๐-๓ ต่อ ๗๐๖๕-๖', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.9,
    ]);
    $footer->addText('ไปรษณีย์อิเล็กทรอนิกส์ : it@itm.kmutnb.ac.th', 'normalFont', [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 0.9,
    ]);
}

function addInviteSpeakerPage($phpWord, array $data) {
    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => Converter::cmToTwip(2),
        'marginBottom' => Converter::cmToTwip(1.15),
        'marginLeft' => Converter::cmToTwip(3.0),
        'marginRight' => Converter::cmToTwip(2.0),
        // พื้นที่ footer สำหรับ ภาควิชา/โทรศัพท์/อีเมล ไม่ให้โดนดันไปหน้าใหม่
        'footerHeight' => Converter::cmToTwip(0.85),
    ]);

    addInviteHeader($section, $data['displayFaculty'], $data['thaiDocDate']);
    addInvitePairRow($section, 'เรื่อง', $data['displaySubject']);
    addInvitePairRow($section, 'เรียน', $data['displayToPerson']);
    addInviteAttachmentRows($section);

    addInviteTextRunPara($section, [
        'ด้วย', $data['displayDepartmentFull'], ' ', $data['displayFaculty'],
        ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี',
        ' ได้ดำเนินการจัด', $data['displayProjectTitle'], ' ใน', $data['displayEventDate'],
        ' เวลา ', $data['displayEventTime'], ' น. ณ ', $data['displayLocation'],
        ' โดยมีวัตถุประสงค์', $data['displayObjective'],
        ' รายละเอียดโครงการตามสิ่งที่ส่งมาด้วย ๑',
    ]);

    addInviteTextRunPara($section, [
        $data['displayDepartmentFull'], ' ', $data['displayInviteStatement'],
        ' จึงขอเรียนเชิญท่านเป็นวิทยากรบรรยายเรื่องดังกล่าว ตามวัน เวลา และสถานที่ข้างต้น',
    ]);

    addInviteSignature($section, $data['displayFacultyDean']);
    addInviteFooter($section, $data['displayDepartmentFull']);
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
}

$docDate = $valueMap[1] ?? ($document['doc_date'] ?? '');
$projectTitle = $valueMap[5] ?? '';
$eventDate = $valueMap[6] ?? ($valueMap[16] ?? '');
$location = $valueMap[7] ?? '';
$faculty = $valueMap[10] ?? '';
$department = $valueMap[11] ?? '';
$docSubject = $valueMap[14] ?? ($document['subject'] ?? '');
$objective = $valueMap[25] ?? '';
$toPerson = $valueMap[26] ?? '';
$inviteStatement = $valueMapByKey['invite_statement'] ?? '';
$eventTime = $valueMapByKey['event_time'] ?? '';

<<<<<<< HEAD
$thaiDocDate = invite_word_thai_date_any($docDate, false);
$thaiEventDate = invite_word_thai_date_any($eventDate, true);
$thaiEventTime = invite_word_format_time_range($eventTime);
=======
$thaiDocDate = inviteThaiDateAny($docDate, false);
$thaiEventDate = inviteThaiDateAny($eventDate, true);
$thaiEventTime = inviteFormatThaiTimeRange($eventTime);
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a

$displayFaculty = trim($faculty) !== '' ? trim($faculty) : 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม';
$displayDepartment = trim($department) !== '' ? trim($department) : 'เทคโนโลยีสารสนเทศ';
$displayDepartmentFull = 'ภาควิชา' . $displayDepartment;
$displayFacultyDean = 'คณบดี' . $displayFaculty;
$displaySubject = trim($docSubject) !== '' ? $docSubject : 'ขอเรียนเชิญเป็นวิทยากรบรรยาย';
$displayToPerson = trim($toPerson) !== '' ? $toPerson : 'คุณ................................................';
$displayProjectTitle = trim($projectTitle) !== '' ? $projectTitle : '................................................';
$displayInviteStatement = trim($inviteStatement) !== ''
    ? $inviteStatement
    : 'เห็นว่าท่านเป็นผู้มีความเชี่ยวชาญและมีประสบการณ์สูง ในสาขาวิชาชีพดังกล่าว ซึ่งจะเป็นประโยชน์แก่นักศึกษาผู้เข้าร่วมโครงการเป็นอย่างดี';
$displayObjective = trim($objective) !== '' ? $objective : '................................................';
$displayEventDate = trim($thaiEventDate) !== '' ? $thaiEventDate : 'วันที่................................................';
$displayEventTime = trim($thaiEventTime) !== '' ? $thaiEventTime : '................';
$displayLocation = trim($location) !== '' ? $location : '................................................';

<<<<<<< HEAD
$garudaPath = __DIR__ . '/../assets/img/garuda.jpg';
$hasGaruda = is_file($garudaPath);

$body = '';
$body .= invite_word_header_block($displayFaculty, $thaiDocDate, $hasGaruda);
$body .= invite_word_pair_row('เรื่อง', $displaySubject);
$body .= invite_word_pair_row('เรียน', $displayToPerson);
$body .= invite_word_attachment_rows();
$body .= invite_word_p('ด้วย' . $displayDepartmentFull . ' ' . $displayFaculty . ' มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี ได้ดำเนินการจัด' . $displayProjectTitle . ' ใน' . $displayEventDate . ' เวลา ' . $displayEventTime . ' น. ณ ' . $displayLocation . ' โดยมีวัตถุประสงค์' . $displayObjective . ' รายละเอียดโครงการตามสิ่งที่ส่งมาด้วย ๑', 'thaiDistribute', 1417, 80, 32, false, 0, 220);
$body .= invite_word_p($displayDepartmentFull . ' ' . $displayInviteStatement . ' จึงขอเรียนเชิญท่านเป็นวิทยากรบรรยายเรื่องดังกล่าว ตามวัน เวลา และสถานที่ข้างต้น', 'thaiDistribute', 1417, 120, 32, false, 0, 220);
$body .= invite_word_p('จึงเรียนมาเพื่อโปรดพิจารณาให้ความอนุเคราะห์ จะขอบคุณยิ่ง', 'thaiDistribute', 1417, 220, 32, false, 0, 276);
$body .= invite_word_p('ขอแสดงความนับถือ', 'center', 0, 500, 32, false, 0, 240);
$body .= invite_word_p('(ผู้ช่วยศาสตราจารย์ ดร.กฤษฎากร บุดดาจันทร์)', 'center', 0, 0, 32, false, 0, 240);
$body .= invite_word_p($displayFacultyDean, 'center', 0, 0, 32, false, 0, 240);

$sectPr = '<w:sectPr><w:footerReference w:type="default" r:id="rIdFooter1"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="652" w:left="1701" w:header="720" w:footer="482" w:gutter="0"/><w:cols w:space="720"/><w:docGrid w:linePitch="360"/></w:sectPr>';

$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:w10="urn:schemas-microsoft-com:office:word" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup" xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk" xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml" xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" mc:Ignorable="w14 wp14"><w:body>'
    . $body . $sectPr . '</w:body></w:document>';

$footerXml = invite_word_footer_xml($displayDepartmentFull);

$filename = 'invite_speaker_' . $docId . '.docx';
$tmpDocx = tempnam(sys_get_temp_dir(), 'invite_speaker_manual_');
if ($tmpDocx === false) {
    http_response_code(500);
    exit('ไม่สามารถสร้างไฟล์ Word ชั่วคราวได้');
}

if (!invite_word_create_docx($tmpDocx, $documentXml, $footerXml, $garudaPath)) {
    @unlink($tmpDocx);
    http_response_code(500);
    exit('ไม่สามารถสร้างไฟล์ Word ได้: ZipArchive ไม่พร้อมใช้งานหรือสร้างไฟล์ไม่สำเร็จ');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: max-age=0');
header('Pragma: public');
header('Content-Length: ' . filesize($tmpDocx));

readfile($tmpDocx);
@unlink($tmpDocx);
exit;
=======
$phpWord = new PhpWord();
setupWordDefaults($phpWord);
$phpWord->addFontStyle('addressFont', [
    'name' => 'TH SarabunPSK',
    'size' => 15.5,
]);

addInviteSpeakerPage($phpWord, [
    'displayFaculty' => $displayFaculty,
    'thaiDocDate' => $thaiDocDate,
    'displaySubject' => $displaySubject,
    'displayToPerson' => $displayToPerson,
    'displayDepartmentFull' => $displayDepartmentFull,
    'displayFaculty' => $displayFaculty,
    'displayProjectTitle' => $displayProjectTitle,
    'displayEventDate' => $displayEventDate,
    'displayEventTime' => $displayEventTime,
    'displayLocation' => $displayLocation,
    'displayObjective' => $displayObjective,
    'displayInviteStatement' => $displayInviteStatement,
    'displayFacultyDean' => $displayFacultyDean,
]);

$filename = 'invite_speaker_' . $docId . '.docx';
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
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
