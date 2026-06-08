<?php
// pro_letter/documents/submit_document.php
session_start();

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/send_mail.php';

header('Content-Type: application/json; charset=utf-8');

/*
 * ส่ง JSON กลับไปให้หน้าเว็บก่อน แล้วค่อยทำงานที่ช้า เช่น ส่งอีเมล ต่อหลังบ้าน
 * เพื่อให้ปุ่ม "ยืนยันการส่ง" ตอบสนองเร็วขึ้น โดยไม่ต้องปรับ popup ฝั่งหน้าเว็บ
 */
function sendJsonResponseAndContinue(array $payload): void
{
    ignore_user_abort(true);

    $json = json_encode($payload);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Connection: close');
    header('Content-Length: ' . strlen($json));

    echo $json;

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        flush();
    }
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'not_login']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$docId = (int)($data['document_id'] ?? 0);

if ($docId <= 0) {
    echo json_encode(['success' => false, 'message' => 'invalid_id']);
    exit;
}

$pdo = db();

try {
    // ตรวจเอกสาร + ดึงข้อมูลสำหรับใส่ในอีเมล
    $stmt = $pdo->prepare("
        SELECT 
            d.document_id,
            d.doc_no,
            d.doc_date,
            d.subject,
            d.status,
            d.owner_id,
            u.fullname AS owner_name,
            u.email AS owner_email,
            COALESCE(NULLIF(d.document_type_name, ''), t.template_name) AS template_name
        FROM documents d
        LEFT JOIN users u ON d.owner_id = u.user_id
        LEFT JOIN templates t ON d.template_id = t.template_id
        WHERE d.document_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'not_found']);
        exit;
    }

    if ((int)$doc['owner_id'] !== (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'no_permission']);
        exit;
    }

    if ($doc['status'] !== 'draft') {
        echo json_encode(['success' => false, 'message' => 'already_submitted']);
        exit;
    }

    // ดึงอีเมล Admin + Officer
    $mailStmt = $pdo->prepare("
        SELECT user_id, fullname, email
        FROM users
        WHERE role_id IN (1, 2)
          AND is_active = 1
          AND email IS NOT NULL
          AND email <> ''
    ");
    $mailStmt->execute();
    $receivers = $mailStmt->fetchAll(PDO::FETCH_ASSOC);

    // อัปเดตสถานะเอกสารก่อน
    $upd = $pdo->prepare("
        UPDATE documents
        SET status = 'submitted'
        WHERE document_id = :id
    ");
    $upd->execute([':id' => $docId]);

    // บันทึกประวัติ
    $log = $pdo->prepare("
        INSERT INTO audit_logs (user_id, document_id, action, detail)
        VALUES (:user_id, :document_id, :action, :detail)
    ");
    $log->execute([
        ':user_id' => $_SESSION['user_id'],
        ':document_id' => $docId,
        ':action' => 'SUBMITTED',
        ':detail' => 'ผู้ใช้งานส่งเอกสารเข้าสู่ระบบเพื่อรอการตรวจสอบ'
    ]);

    // เตรียมข้อมูลอีเมล
    $docNo = !empty($doc['doc_no']) ? $doc['doc_no'] : 'ยังไม่มีเลขที่เอกสาร';
    $subjectText = !empty($doc['subject']) ? $doc['subject'] : 'ไม่ระบุเรื่องเอกสาร';
    $templateName = !empty($doc['template_name']) ? $doc['template_name'] : 'ไม่ระบุประเภทเอกสาร';
    $ownerName = !empty($doc['owner_name']) ? $doc['owner_name'] : ($_SESSION['fullname'] ?? 'ผู้ใช้งาน');
    $submittedAt = date('d/m/Y H:i');

    $baseUrl = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https://'
        : 'http://'
    ) . $_SERVER['HTTP_HOST'] . '/Pro_letter';

    $loginUrl = $baseUrl . '/login.html';

    $emailSubject = '[Smart Government Letter Assistant System] มีเอกสารใหม่รอตรวจสอบ';

    $emailBody = '
    <div style="font-family: Arial, Tahoma, sans-serif; max-width:680px; margin:auto; padding:24px; border:1px solid #e5e7eb; border-radius:14px; background:#ffffff;">
        <h2 style="color:#0f766e; margin-top:0;">
            Smart Government Letter Assistant System
        </h2>

        <p style="font-size:15px; color:#111827;">
            เรียน เจ้าหน้าที่ผู้ดูแลระบบ
        </p>

        <p style="font-size:15px; color:#374151; line-height:1.8;">
            ระบบขอแจ้งว่ามีเอกสารใหม่ถูกส่งเข้าสู่ระบบ และอยู่ระหว่างรอการตรวจสอบรายละเอียด
        </p>

        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin:18px 0;">
            <p><b>ประเภทเอกสาร :</b> ' . htmlspecialchars($templateName) . '</p>
            <p><b>เรื่อง :</b> ' . htmlspecialchars($subjectText) . '</p>
            <p><b>ผู้ส่งเอกสาร :</b> ' . htmlspecialchars($ownerName) . '</p>
            <p><b>วันที่ส่ง :</b> ' . htmlspecialchars($submittedAt) . ' น.</p>
            <p><b>สถานะปัจจุบัน :</b> รอตรวจสอบ</p>
        </div>

        <p style="font-size:15px; color:#374151;">
            กรุณาเข้าสู่ระบบเพื่อตรวจสอบเอกสาร
        </p>

        <p>
            <a href="' . htmlspecialchars($loginUrl) . '" 
               style="display:inline-block; background:#14b8a6; color:white; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:bold;">
                เข้าสู่ระบบ
            </a>
        </p>

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:22px 0;">

        <p style="font-size:12px; color:#6b7280;">
            อีเมลฉบับนี้ถูกส่งโดยอัตโนมัติจาก Smart Government Letter Assistant System กรุณาอย่าตอบกลับอีเมลนี้
        </p>
    </div>
    ';

    $emailList = [];
    foreach ($receivers as $receiver) {
        $emailList[] = $receiver['email'];

        // บันทึก notification channel email ไว้ด้วย
        $noti = $pdo->prepare("
            INSERT INTO notifications (user_id, document_id, channel, title, message, is_read)
            VALUES (:user_id, :document_id, 'email', :title, :message, 0)
        ");
        $noti->execute([
            ':user_id' => $receiver['user_id'],
            ':document_id' => $docId,
            ':title' => 'มีเอกสารใหม่รอตรวจสอบ',
            ':message' => 'เอกสารเรื่อง "' . $subjectText . '" ถูกส่งเข้าสู่ระบบเพื่อรอการตรวจสอบ'
        ]);
    }

    // ตอบกลับหน้าเว็บทันทีหลังอัปเดตสถานะ + บันทึก notification เสร็จ
    // ส่วนการส่งอีเมลทำต่อหลังส่ง response แล้ว เพื่อลดเวลารอของผู้ใช้
    sendJsonResponseAndContinue([
        'success' => true,
        'mail_sent' => !empty($emailList) ? 'processing' : false
    ]);

    if (!empty($emailList)) {
        $mailSent = sendSystemMail($emailList, $emailSubject, $emailBody);
        if (!$mailSent) {
            error_log('Submit Document Mail Error: document_id=' . $docId);
        }
    }

    exit;

} catch (Exception $e) {
    error_log('Submit Document Error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'server_error'
    ]);
    exit;
}