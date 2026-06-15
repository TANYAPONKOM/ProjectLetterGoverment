<?php

if (!function_exists('insertThaiWordBreaksForMemoBody')) {
    function insertThaiWordBreaksForMemoBody($text) {
        $text = (string)$text;
        $zwsp = "\u{200B}";

        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = str_replace($zwsp, '', $text);
        $text = preg_replace('/[ ]{2,}/u', ' ', $text);
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        /*
         * จุดตัดแบบ "คำ/พยางค์" ไม่ใช่ประโยคยาว
         * เพื่อให้ Word มีจุดตัดเยอะขึ้น และไม่ยกทั้งวลีไปบรรทัดใหม่
         */
        $breakWords = [
            // คำราชการ/คำทั่วไป
            'ตามที่',
            'บริษัท',
            'โค้ดดิ้ง',
            'คอนซัลแตนท์',
            'ไทยแลนด์',
            'จำกัด',
            'ได้',
            'ดำเนิน',
            'การจัด',
            'จัด',
            'ฝึก',
            'อบรม',
            'หลักสูตร',
            'พัฒนา',
            'รูปแบบ',
            'ออนไลน์',
            'ระหว่าง',
            'วันที่',
            'และ',
            'ในวันที่',
            'โดย',
            'เข้า',
            'รับ',
            'การ',
            'วัน',
            'เสาร์',
            'อาทิตย์',
            'รวม',
            'ระยะ',
            'เวลา',
            'ซึ่ง',
            'เนื้อหา',
            'ดังกล่าว',
            'เป็น',
            'ประโยชน์',
            'ต่อ',
            'พัฒนา',
            'เรียน',
            'สอน',
            'ให้',
            'นักศึกษา',
            'ได้',
            'อย่างดี',

            // ย่อหน้า 2
            'ในกรณีนี้',
            'ข้าพเจ้า',
            'สังกัด',
            'ภาควิชา',
            'เทคโนโลยี',
            'สารสนเทศ',
            'คณะ',
            'และการ',
            'จัดการ',
            'อุตสาหกรรม',
            'มหาวิทยาลัย',
            'พระจอมเกล้า',
            'พระนครเหนือ',
            'วิทยาเขต',
            'ปราจีนบุรี',
            'มี',
            'ความ',
            'ประสงค์',
            'ขอ',
            'อนุมัติ',
            'ตัว',
            'บุคคล',
            'ตามวัน',
            'เวลา',
            'ดังกล่าว',
            'ขอใช้',
            'แหล่ง',
            'เงิน',
            'จัดสรร',
            'หน่วยงาน',
            'ประจำ',
            'ปีงบ',
            'ประมาณ',
            'พ.ศ.',
            'ส่วน',
            'ของ',
            'แผนงาน',
            'ศึกษา',
            'ระดับ',
            'อุดมศึกษา',
            'กองทุน',
            'บุคลากร',
            'หมวด',
            'ค่าใช้สอย',

            // ตัดได้ แต่อ่านได้
            'ราย',
            'ละ',
            'เอียด',
            'ตาม',
            'เอก',
            'สาร',
            'แนบ',

            // อังกฤษ / ทับศัพท์
            'Mobile',
            'App',
            'React',
            'Native',
            'TypeScript',
            'Expo',
            'codingthailand.com',
            // เพิ่มคำย่อยสำหรับเอกสารวิจัย / ปริญญานิพนธ์
            
            'อนุเคราะห์',
            'ข้อมูล',
            'สำหรับ',
            'ค้นคว้า',
            'นวัตกรรม',
            'ดิจิทัล',
            'เกษตร',

            'ภาค',
            'ปี',
            'สาขา',
            'วิชา',
            'วิทยา',
            'ศาสตร',
            'บัณฑิต',
            'ปริญญา',
            'นิพนธ์',
            'ตรี',
            'หัวข้อ',
            'ข้างต้น',

            'อาจารย์',
            'ที่ปรึกษา',
            'รหัส',
            'รายชื่อ',

            'ทำ',
            'เปิด',
            'รายวิชา',
            'กำหนด',
            'ติดตาม',
            'วิเคราะห์',
            'บริการ',

            'ฐาน',
            'เชิง',
            'โครงสร้าง',
            'เก็บ',
            'เอกสาร',
            'สถานะ',
            'สิทธิ์',
            'เข้าถึง',
            'ผู้ใช้',
            'งาน',

            'ทาง',
            'มายัง',
            'ท่าน',
            'โปรด',
            'นำ',
            'ประกอบ',
            'คน',
            'ดังนี้',
            'ครั้งนี้',

            'โครงการ',
            'กิจกรรม',
            'ค่าใช้จ่าย',  
        ];

        // คำยาวก่อนคำสั้น กันคำสั้นไปแทรกกลางก่อน
        usort($breakWords, function ($a, $b) {
            return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
        });

        foreach ($breakWords as $word) {
            $text = str_replace($word, $word . $zwsp, $text);
        }

        /*
         * เพิ่มจุดตัด fallback ในกลุ่มอักษรไทยที่ยังยาวติดกัน
         * ตัดทุก 3-4 ตัวอักษรไทย โดยไม่ตัดก่อน/หลังสระหรือวรรณยุกต์
         */
        $text = preg_replace_callback('/[\x{0E00}-\x{0E7F}]{7,}/u', function ($m) use ($zwsp) {
            return splitThaiReadableChunk($m[0], $zwsp);
        }, $text);

        // จุดตัดหลังเครื่องหมาย
        $text = preg_replace('/([,;:\/\-–—])/u', '$1' . $zwsp, $text);
        $text = preg_replace('/([)）”"])/u', '$1' . $zwsp, $text);

        return cleanThaiZwspForWord($text);
    }
}

if (!function_exists('splitThaiReadableChunk')) {
    function splitThaiReadableChunk($text, $zwsp) {
    /*
     * คำย่อยที่อ่านได้ ใช้เป็นตัวช่วยตัดคำไทย
     * ไม่ใช่คำเต็มเฉพาะเอกสาร แต่เป็นชิ้นคำที่ใช้ซ้ำได้หลายคำ
     */
    $readableParts = [
        'หน่วย',
        'งาน',
        'เอก',
        'สาร',
        'แนบ',
        'ภาค',
        'วิชา',
        'เทคโน',
        'โลยี',
        'สารสนเทศ',
        'คณะ',
        'จัดการ',
        'อุตสาหกรรม',
        'มหา',
        'วิทยาลัย',
        'วิทยา',
        'เขต',
        'ปราจีน',
        'บุรี',
        'พัฒนา',
        'บุคลากร',
        'แผนงาน',
        'ศึกษา',
        'ระดับ',
        'อุดม',
        'กองทุน',
        'หมวด',
        'ค่าใช้',
        'สอย',
        'ประจำ',
        'ประมาณ',
        'ศาสตร',
'บัณฑิต',
'ปริญญา',
'นิพนธ์',
'สาขา',
'นวัตกรรม',
'ดิจิทัล',
'เกษตร',
'ฐาน',
'ข้อมูล',
'เชิง',
'โครงสร้าง',
'จัดเก็บ',
'สถานะ',
'สิทธิ์',
'เข้าถึง',
'ผู้ใช้',
'บริการ',
'วิเคราะห์',
'ติดตาม',
'อาจารย์',
'ปรึกษา',
'รหัส',
'รายชื่อ',
    ];

    usort($readableParts, function ($a, $b) {
        return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
    });

    $result = '';
    $remaining = $text;

    while ($remaining !== '') {
        $matched = false;

        foreach ($readableParts as $part) {
            if (mb_substr($remaining, 0, mb_strlen($part, 'UTF-8'), 'UTF-8') === $part) {
                $result .= $part . $zwsp;
                $remaining = mb_substr($remaining, mb_strlen($part, 'UTF-8'), null, 'UTF-8');
                $matched = true;
                break;
            }
        }

        if ($matched) {
            continue;
        }

        /*
         * fallback: ตัดทีละประมาณ 3 ตัวอักษรหลัก
         * แต่ห้ามตัดแล้วเหลือท้ายคำแค่ 1 ตัว เช่น งา|น หรือ ย|งาน
         */
        $chars = preg_split('//u', $remaining, -1, PREG_SPLIT_NO_EMPTY);
        $buffer = '';
        $mainCount = 0;
        $takeCount = 0;

        foreach ($chars as $ch) {
            $buffer .= $ch;
            $takeCount++;

            if (!preg_match('/[\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}]/u', $ch)) {
                $mainCount++;
            }

            if ($mainCount >= 3) {
                break;
            }
        }

        $tail = mb_substr($remaining, mb_strlen($buffer, 'UTF-8'), null, 'UTF-8');

        /*
         * ถ้าเหลือท้ายแค่ 1 ตัวอักษรไทย ห้ามตัด ให้รวมไปเลย
         * เช่น หน่วยงา|น จะไม่เกิด
         */
        $tailMainChars = preg_replace('/[\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}]/u', '', $tail);

        if (mb_strlen($tailMainChars, 'UTF-8') <= 1) {
            $result .= $remaining;
            $remaining = '';
        } else {
            $result .= $buffer . $zwsp;
            $remaining = $tail;
        }
    }

    return $result;
}
}
if (!function_exists('protectBadSingleThaiTailBreaks')) {
    function protectBadSingleThaiTailBreaks($text) {
        $zwsp = "\u{200B}";
        $z = preg_quote($zwsp, '/');

        /*
         * กันเคสแบบ:
         * หน่วยงา|น
         * เอกสา|ร
         * บุคค|ล
         *
         * ถ้าหลัง ZWSP เหลืออักษรไทยเดี่ยวก่อนจบคำ ให้ลบจุดตัดนั้นทิ้ง
         */
        $text = preg_replace(
            '/' . $z . '(?=[\x{0E00}-\x{0E7F}](?![\x{0E00}-\x{0E7F}]))/u',
            '',
            $text
        );

        /*
         * ถ้ามีคำว่า งาน อยู่ท้ายกลุ่ม ให้พยายามตัดก่อนคำว่า งาน
         * เช่น หน่วยงาน => หน่วย|งาน
         * โดยไม่ต้องเพิ่มคำเต็มลง breakWords ทุกครั้ง
         */
        $text = preg_replace(
            '/([\x{0E00}-\x{0E7F}]{2,})งาน/u',
            '$1' . $zwsp . 'งาน',
            $text
        );

        return $text;
    }
}


if (!function_exists('cleanThaiZwspForWord')) {
    function cleanThaiZwspForWord($text) {
    $zwsp = "\u{200B}";
    $z = preg_quote($zwsp, '/');

    // ห้ามตัดก่อน/หลังสระและวรรณยุกต์ไทย
    $thaiMarks = "\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}";

    $text = preg_replace('/' . $z . '(?=[' . $thaiMarks . '])/u', '', $text);
    $text = preg_replace('/(?<=[' . $thaiMarks . '])' . $z . '/u', '', $text);

    // กันตัดแบบ เอ|ก, แ|นบ, โ|ครงการ, ไ|ทย
    $text = preg_replace(
        '/([เแโใไ])' . $z . '([\x{0E00}-\x{0E7F}])/u',
        '$1$2',
        $text
    );

    // กันตัดแล้วเหลือตัวไทยเดี่ยวท้ายคำ เช่น งา|น, สา|ร, คค|ล
    if (function_exists('protectBadSingleThaiTailBreaks')) {
        $text = protectBadSingleThaiTailBreaks($text);
    }
// กันคำที่ไม่ควรถูกตัดกลางคำจนอ่านไม่ออก
$protectWords = [
    'อนุเคราะห์',
        'ศาสตร',
        'บัณฑิต',
        'ปริญญา',
        'นิพนธ์',
        'ปริญญานิพนธ์',
        'สารสนเทศ',
        'นวัตกรรม',
        'ดิจิทัล',
        'ฐานข้อมูล',
        'โครงสร้าง',
        'เอกสาร',
        'บริการ',
        'สัมมนา',
        'โอกาส',
        'โอกาสนี้',
        'ขอบคุณ',
];

foreach ($protectWords as $word) {
    $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
    $pattern = implode($z . '?', array_map(function ($ch) {
        return preg_quote($ch, '/');
    }, $chars));

    $text = preg_replace('/' . $pattern . '/u', $word, $text);
}
    // ล้าง ZWSP ซ้ำ
    $text = preg_replace('/' . $z . '{2,}/u', $zwsp, $text);

    // กัน ZWSP ติดช่องว่างเกินจำเป็น
    $text = preg_replace('/' . $z . '\s+/u', ' ', $text);
    $text = preg_replace('/\s+' . $z . '/u', ' ', $text);

    return $text;
}
}