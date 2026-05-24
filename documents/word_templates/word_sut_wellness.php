<?php
// documents/word_templates/word_sut_wellness.php

// ฟังก์ชันแทรกตัวตัดคำภาษาไทย (Zero-Width Space) เพื่อให้การกระจายคำสมดุล ไม่ห่างหรือชิดเกินไป
function insertSutThaiWordBreaks($text) {
    $words = [
        'ด้วย', 'ดร.พิทย์พิน', 'ชูรอด', 'อาจารย์ประจำ', 'ภาควิชา', 'เทคโนโลยี', 'สารสนเทศ', 'สังกัด',
        'คณะเทคโนโลยีและการจัดการอุตสาหกรรม', 'คณะเทคโนโลยี', 'และการจัดการ', 'อุตสาหกรรม',
        'มหาวิทยาลัย', 'เทคโนโลยีพระจอมเกล้าพระนครเหนือ', 'วิทยาเขต', 'ปราจีนบุรี', 'มีความประสงค์',
        'จะขออนุญาต', 'เข้าเยี่ยมชม', 'ประชุมเรื่อง', 'การแต่งกาย', 'นักศึกษาหญิง', 'และระเบียบ',
        'การเข้าสังคม', 'ของคน', 'เข้าร่วม', 'รูปแบบ', 'ออนไลน์', 'ในวันที่', '๒๔', 'พฤษภาคม', '๒๕๖๙',
        'เพื่อนำ', 'ข้อมูล', 'และความรู้', 'ที่ได้รับ', 'มาพัฒนา', 'ให้เกิดประโยชน์', 'กับการจัดการ',
        'เรียนการสอน', 'งานวิจัย', 'และการพัฒนา', 'นวัตกรรม', 'โดยมีรายชื่อ', 'คณาจารย์', 'ที่จะเข้า',
        'เยี่ยมชม', 'ศึกษาดูงาน', 'จำนวน', '๐', 'คน', 'ดังรายชื่อ', 'ต่อไปนี้', 'จึงเรียนมา',
        'เพื่อโปรด', 'พิจารณา', 'อนุญาต', 'และขอขอบคุณ', 'ณ', 'โอกาสนี้'
    ];
    
    foreach ($words as $word) {
        $text = str_replace($word, $word . "\u{200B}", $text);
    }
    return $text;
}

// ฟังก์ชันจัดย่อหน้าข้อความหลักให้กระจายขอบขวาเสมอกันตามภาพต้นฉบับ
function addSutPerfectAcademicPara($section, array $lines, $spaceAfter = 120) {
    // ใช้ Jc::BOTH ร่วมกับการทำตัวตัดคำ เพื่อให้ขอบซ้าย-ขวาตรงกันสนิท และคำไม่ยืดถ่างแยกกัน
    $run = $section->addTextRun([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH, 
        'lineHeight' => 1.15,
        'spaceBefore' => 0,
        'spaceAfter' => $spaceAfter,
        'indentation' => [
            'firstLine' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5) 
        ],
    ]);

    foreach ($lines as $index => $line) {
        // ทำความสะอาดและแทรกสัญลักษณ์ตัดคำในย่อหน้าหลัก
        $processedText = insertSutThaiWordBreaks(cleanWordText($line));
        $run->addText($processedText, 'normalFont');
        if ($index < count($lines) - 1) {
            $run->addText(' ', 'normalFont'); 
        }
    }
}

function buildSutWellnessWord($phpWord, $document, $valueMap, $budgetItems = []) {
    // ตั้งค่าระยะขอบหน้ากระดาษหนังสือราชการภายนอก (ซ้าย 3 ซม. / ขวา 2.0 ซม. ตามมาตรฐาน)
    $section = $phpWord->addSection([
        'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5),
        'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5),
        'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3.0),
        'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.0),
    ]);

    // ======================================================================
    // 1) ส่วนหัวเอกสาร: จัดระเบียบพิกัดที่อยู่ฝั่งขวาและ ที่ อว. ให้อยู่ระดับต่ำลงมาแนวเท้าครุฑ
    // ======================================================================
    $tableStyle = ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0];
    $table = $section->addTable($tableStyle);
 // เพิ่มความสูงแถวหัวเอกสาร เพื่อให้ "ที่อยู่" มีพื้นที่ขยับลงได้เยอะ
$table->addRow(\PhpOffice\PhpWord\Shared\Converter::cmToTwip(3.7));

// ช่องฝั่งซ้าย: "ที่ อว" ขยับขึ้นจากตำแหน่งเดิมนิดเดียว
$cellLeft = $table->addCell(
    \PhpOffice\PhpWord\Shared\Converter::cmToTwip(5.5),
    ['valign' => 'top']
);

$cellLeft->addText('ที่ อว ๗๑๐๑.๑๕/', 'normalFont', [
    'spaceBefore' => 1200,
    'spaceAfter' => 0,
]);

// ช่องตรงกลาง: ตราครุฑ คงเดิม
$cellCenter = $table->addCell(
    \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3.5),
    ['valign' => 'top', 'alignment' => 'center']
);

$cellCenter->addImage('../assets/img/garuda.jpg', [
    'width' => 82,
    'height' => 85,
    'alignment' => 'center'
]);

// ช่องฝั่งขวา: ที่อยู่ คงตำแหน่งครุฑเดิม แต่บีบตัวอักษรให้บรรทัดแรกไม่ตก
$cellRight = $table->addCell(
    \PhpOffice\PhpWord\Shared\Converter::cmToTwip(7.5),
    [
        'valign' => 'top',
        'noWrap' => true,
    ]
);

$rightRun = $cellRight->addTextRun([
    'alignment' => 'left',
    'spaceBefore' => 1200,
    'spaceAfter' => 0,
    'lineHeight' => 1.0,
]);

// ลดขนาดเฉพาะบรรทัดที่อยู่ ไม่แตะ normalFont หลัก และไม่ขยับครุฑ
$addressFont = [
    'name' => 'TH SarabunPSK',
    'size' => 14,
];

$rightRun->addText('มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ', $addressFont);
$rightRun->addTextBreak();
$rightRun->addText('๑๒๙ หมู่ ๒๑ ต.เนินหอม อ.เมือง จ.ปราจีนบุรี ๒๕๒๓๐', $addressFont);

   // ลดช่องว่างหลังหัวเอกสาร เพื่อให้วันที่และเนื้อหาใต้ครุฑขยับขึ้น
$section->addTextRun(['spaceBefore' => 0, 'spaceAfter' => 0]);
// การแสดงวันที่ใต้ครุฑ: ลดช่องดันซ้าย เพื่อขยับวันที่ไปทางซ้าย
$dateTable = $section->addTable($tableStyle);
$dateTable->addRow();
$dateTable->addCell(\PhpOffice\PhpWord\Shared\Converter::cmToTwip(7.4));
$dateCell = $dateTable->addCell(\PhpOffice\PhpWord\Shared\Converter::cmToTwip(8.0));
$dateCell->addText('๒๔ พฤษภาคม ๒๕๖๙', 'normalFont', ['spaceAfter' => 180]);

    // ======================================================================
    // 2) ส่วน เรื่อง / เรียน
    // ======================================================================
    $runSubject = $section->addTextRun(['alignment' => 'left', 'spaceAfter' => 120]);
    $runSubject->addText('เรื่อง  ', 'boldFont');
    $runSubject->addText('เข้าร่วมประชุมวิชาการในงานประชุมเรื่องการแต่งกายนักศึกษาหญิงและระเบียบการเข้าสังคมของคน', 'normalFont');

    $runTo = $section->addTextRun(['alignment' => 'left', 'spaceAfter' => 200]);
    $runTo->addText('เรียน  ', 'boldFont');
    $runTo->addText('อธิการบดีมหาวิทยาลัยเทคโนโลยีสุรนารี (มทส.)', 'normalFont');

    // ======================================================================
    // 3) ส่วนเนื้อหาหลัก: เรียกฟังก์ชันเพื่อทำการกระจายคำให้ตรงตามภาพแนบ
    // ======================================================================
    addSutPerfectAcademicPara($section, [
        'ด้วย ดร.พิทย์พิน ชูรอด อาจารย์ประจำภาควิชาเทคโนโลยีสารสนเทศ สังกัดภาควิชาเทคโนโลยีสารสนเทศ คณะเทคโนโลยีและการจัดการอุตสาหกรรม มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี มีความประสงค์จะขออนุญาตเข้าเยี่ยมชม ประชุมเรื่องการแต่งกายนักศึกษาหญิงและระเบียบการเข้าสังคมของคน เข้าร่วมรูปแบบออนไลน์ ในวันที่ ๒๔ พฤษภาคม ๒๕๖๙ เพื่อนำข้อมูลและความรู้ที่ได้รับมาพัฒนาให้เกิดประโยชน์กับการจัดการเรียนการสอน งานวิจัย และการพัฒนานวัตกรรม โดยมีรายชื่อคณาจารย์ที่จะเข้าเยี่ยมชมศึกษาดูงาน จำนวน ๐ คน ดังรายชื่อต่อไปนี้'
    ], 40);

// เว้นระยะก่อนบรรทัดรายชื่อ ไม่ให้ชิดเนื้อหาด้านบน
$section->addText('', 'normalFont', ['spaceAfter' => 120]);

// รายชื่อคณาจารย์: ให้เลข ๑. เริ่มตรงแนวเดียวกับคำว่า "จึง"
$listTable = $section->addTable([
    'borderSize' => 0,
    'borderColor' => 'FFFFFF',
    'cellMargin' => 0,
]);

$listTable->addRow(\PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.65));

// ช่องเว้นซ้าย 0.80 cm ให้ตรงกับ firstLine ของบรรทัด "จึงเรียนมา..."
$listTable->addCell(
    \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.80),
    ['valign' => 'center']
);

// ช่องเลข ๑. อยู่หลังช่องเว้นซ้าย จึงตรงกับคำว่า "จึง"
$listNoCell = $listTable->addCell(
    \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.45),
    ['valign' => 'center']
);
$listNoCell->addText('๑.', 'normalFont', [
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT,
    'spaceAfter' => 0,
]);

// ช่องเส้นประ
$listNameCell = $listTable->addCell(
    \PhpOffice\PhpWord\Shared\Converter::cmToTwip(6.95),
    ['valign' => 'center']
);
$listNameCell->addText('................................................', 'normalFont', [
    'spaceAfter' => 0,
]);

// ช่องคณะด้านขวา
$listFacultyCell = $listTable->addCell(
    \PhpOffice\PhpWord\Shared\Converter::cmToTwip(7.80),
    ['valign' => 'center']
);
$listFacultyCell->addText('คณะเทคโนโลยีและการจัดการอุตสาหกรรม', 'normalFont', [
    'spaceAfter' => 0,
]);

$section->addText('', 'normalFont', ['spaceAfter' => 80]);

// ย่อหน้าบทสรุปตอนท้ายเรื่อง
// ใช้ LEFT และ firstLine เท่ากับตำแหน่งเลข ๑. เพื่อให้ตรงกัน และเพิ่มพื้นที่ไม่ให้ "โอกาสนี้" ตกบรรทัด
$closingRun = $section->addTextRun([
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT,
    'lineHeight' => 1.15,
    'spaceBefore' => 0,
    'spaceAfter' => 240,
    'indentation' => [
        'firstLine' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.80)
    ],
]);

$closingRun->addText(
    cleanWordText('จึงเรียนมาเพื่อโปรดพิจารณาอนุญาตให้เข้าเยี่ยมชมศึกษาดูงาน และขอขอบคุณมา ณ โอกาสนี้'),
    'normalFont'
);

// ======================================================================
// 4) ส่วนท้ายเรื่อง: ลายเซ็นให้อยู่กลางหน้าจริง ไม่ใช้ตารางดันซ้ายขวา
// ======================================================================
$section->addText('ขอแสดงความนับถือ', 'normalFont', [
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
    'spaceBefore' => 0,
    'spaceAfter' => 420,
]);

$section->addText('(ผู้ช่วยศาสตราจารย์พีระศักดิ์ เสรีกุล)', 'normalFont', [
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
    'spaceAfter' => 60,
]);

$section->addText('รองอธิการบดีประจำ มจพ.วิทยาเขตปราจีนบุรี', 'normalFont', [
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
    'spaceAfter' => 260,
]);

// ช่องทางติดต่อด้านล่างซ้าย ขยับลงมาให้มีพื้นที่เหมือนตัวอย่าง
$section->addText('ภาควิชาเทคโนโลยีสารสนเทศ', 'normalFont', [
    'spaceBefore' => 320,
    'spaceAfter' => 40,
]);

$section->addText('โทรศัพท์ ๐-๓๗๒๑-๗๓๔๐-๓ ต่อ ๗๐๖๕-๖', 'normalFont', [
    'spaceAfter' => 40,
]);

$section->addText('ไปรษณีย์อิเล็กทรอนิกส์ Ladda.t@fitm.kmutnb.ac.th', 'normalFont', [
    'spaceAfter' => 0,
]);

}