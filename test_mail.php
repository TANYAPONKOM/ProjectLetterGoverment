<?php
require_once __DIR__ . '/includes/send_mail.php';

$subject = '[Smart Government Letter Assistant System] ทดสอบไฟล์กลางส่งอีเมล';

$body = '
<div style="font-family: Arial, sans-serif; max-width:600px; margin:auto; padding:20px; border:1px solid #ddd; border-radius:10px;">
    <h2 style="color:#0f766e;">Smart Government Letter Assistant System</h2>
    <p>นี่คือการทดสอบส่งอีเมลผ่านไฟล์กลาง <b>includes/send_mail.php</b></p>
    <p>ถ้าได้รับเมลนี้ แปลว่าระบบพร้อมเชื่อมกับปุ่มส่งเอกสารและปุ่มเปลี่ยนสถานะแล้ว</p>
</div>
';

$result = sendSystemMail(
    'tanyapornkomkham1@gmail.com',
    $subject,
    $body
);

if ($result) {
    echo "<h2 style='color:green'>ส่งอีเมลผ่านไฟล์กลางสำเร็จ</h2>";
} else {
    echo "<h2 style='color:red'>ส่งอีเมลไม่สำเร็จ</h2>";
}