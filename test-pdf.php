<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;

// สร้าง object dompdf
$dompdf = new Dompdf();

// ใส่ HTML ที่ต้องการแปลงเป็น PDF
$html = '<h1 style="text-align:center">ทดสอบ PDF ด้วย Dompdf</h1>
         <p>นี่คือไฟล์ PDF ที่สร้างขึ้นจาก PHP + Dompdf</p>';

$dompdf->loadHtml($html);

// ตั้งค่า กระดาษ และแนวหน้า
$dompdf->setPaper('A4', 'portrait');

// เรนเดอร์เป็น PDF
$dompdf->render();

// แสดง PDF บน Browser
$dompdf->stream("test.pdf", ["Attachment" => false]);
?>