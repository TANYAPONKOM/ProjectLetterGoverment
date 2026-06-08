<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../functions.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (empty($_SESSION['user_id'])) {
    die("Unauthorized");
}
$userId = $_SESSION['user_id'];
$pdo = db();

// -------------------------------
// ดึงข้อมูล document จากฐานข้อมูล
// -------------------------------
$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q = $pdo->prepare("SELECT * FROM documents WHERE document_id = :id AND owner_id = :u");
$q->execute([':id' => $docId, ':u' => $userId]);
$document = $q->fetch(PDO::FETCH_ASSOC);
if (!$document) {
    die("ไม่พบเอกสาร");
}

// -------------------------------
// เตรียมค่าตัวแปรที่ template ต้องใช้
// (กรณีทดสอบ กำหนดค่าคงที่ไว้ก่อน)
// -------------------------------
$faculty      = "คณะเทคโนโลยีและการจัดการอุตสาหกรรม";
$department   = "เทคโนโลยีสารสนเทศ";
$thaiDocDate  = "1 มกราคม 2568";
$joinType     = "เข้ารับการฝึกอบรมหลักสูตร";
$courseName   = "การอบรมเชิงปฏิบัติการ";
$joinDates    = "10-12 กุมภาพันธ์ 2568";
$location     = "กรุงเทพฯ";
$ownerName    = "นายทดสอบ ทดสอบ";
$position     = "อาจารย์";
$prettyAmount = "5,000";
$thaiYear     = "2568";
$hdr_to       = "คณบดีคณะเทคโนโลยีและการจัดการอุตสาหกรรม";

// -------------------------------
// เริ่ม Dompdf
// -------------------------------
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', __DIR__ . '/..');   // ให้เข้าถึงโฟลเดอร์ SGLMS
$options->set('defaultFont', 'THSarabun');  // กำหนด default font
$dompdf = new Dompdf($options);

// -------------------------------
// Register ฟอนต์ THSarabun
// -------------------------------
$fontDir = realpath(__DIR__ . '/../fonts');
$dompdf->getFontMetrics()->registerFont(
    ['family' => 'THSarabun', 'style' => 'normal', 'weight' => 'normal'],
    $fontDir . '/THSarabun.ttf'
);
$dompdf->getFontMetrics()->registerFont(
    ['family' => 'THSarabun', 'style' => 'normal', 'weight' => 'bold'],
    $fontDir . '/THSarabun Bold.ttf'
);
$dompdf->getFontMetrics()->registerFont(
    ['family' => 'THSarabun', 'style' => 'italic', 'weight' => 'normal'],
    $fontDir . '/THSarabun Italic.ttf'
);
$dompdf->getFontMetrics()->registerFont(
    ['family' => 'THSarabun', 'style' => 'italic', 'weight' => 'bold'],
    $fontDir . '/THSarabun Bold Italic.ttf'
);

// -------------------------------
// โหลด HTML จาก template
// -------------------------------
ob_start();
include __DIR__ . '/documents/view_memo.php';
$html = ob_get_clean();

// -------------------------------
// สร้าง PDF
// -------------------------------
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// -------------------------------
// stream ไฟล์ออกมา
// -------------------------------
$dompdf->stream("document_$docId.pdf", ["Attachment" => false]);