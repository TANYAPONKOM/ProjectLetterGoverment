<?php // <pro_letter/documents/update_status.php>
session_start();

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/send_mail.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();

$data = json_decode(file_get_contents("php://input"), true);

$docId  = (int)($data['id'] ?? 0);
$status = $data['status'] ?? '';
$roleId = (int)($_SESSION['role_id'] ?? 0);
$actorId = (int)($_SESSION['user_id'] ?? 0);

// Admin = 1, Officer = 2
if (!in_array($roleId, [1, 2])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์']);
    exit;
}

if (!$docId || !in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

try {
    // ดึงข้อมูลเอกสาร + เจ้าของ + ผู้ดำเนินการ
    $stmt = $pdo->prepare("
        SELECT 
            d.document_id,
            d.subject,
            d.status AS old_status,
            d.owner_id,
            u.fullname AS owner_name,
            u.email AS owner_email,
            u.role_id AS owner_role_id,
            d.document_type_name,
            t.template_name,
            actor.fullname AS actor_name
        FROM documents d
        LEFT JOIN users u ON d.owner_id = u.user_id
        LEFT JOIN templates t ON d.template_id = t.template_id
        LEFT JOIN users actor ON actor.user_id = :actor_id
        WHERE d.document_id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $docId,
        ':actor_id' => $actorId
    ]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบเอกสาร']);
        exit;
    }

    // อัปเดตสถานะ
    $upd = $pdo->prepare("
        UPDATE documents
        SET status = :status, updated_at = NOW()
        WHERE document_id = :id
    ");
    $upd->execute([
        ':status' => $status,
        ':id' => $docId
    ]);

    $statusText = ($status === 'approved')
        ? 'ผ่านการตรวจสอบ'
        : 'ไม่ผ่านการตรวจสอบ';

    $actionCode = ($status === 'approved')
        ? 'REVIEW_PASSED'
        : 'REVIEW_FAILED';

// ดึงความคิดเห็นล่าสุดจากผู้ตรวจเอกสาร เฉพาะกรณีตีกลับ/ไม่ผ่านการตรวจสอบ
$reviewComment = '';

if ($status === 'rejected') {
    // 1) รับคอมเม้นที่ส่งมาจากหน้าเว็บ ถ้ามี
    $reviewComment = trim((string)($data['review_comment'] ?? ''));

    // 2) ถ้าไม่ได้ส่งคอมเม้นมาพร้อมปุ่มไม่ผ่าน ให้ดึงคอมเม้นล่าสุดที่เคยบันทึกไว้
    if ($reviewComment === '') {
        $commentStmt = $pdo->prepare("
            SELECT detail
            FROM audit_logs
            WHERE document_id = :document_id
              AND action = 'REVIEW_COMMENT'
              AND detail IS NOT NULL
              AND TRIM(detail) <> ''
            ORDER BY created_at DESC, log_id DESC
            LIMIT 1
        ");
        $commentStmt->execute([
            ':document_id' => $docId
        ]);
        $reviewComment = trim((string)($commentStmt->fetchColumn() ?: ''));
    }
}

    // บันทึกประวัติ
    $log = $pdo->prepare("
        INSERT INTO audit_logs (user_id, document_id, action, detail)
        VALUES (:user_id, :document_id, :action, :detail)
    ");
    $log->execute([
        ':user_id' => $actorId,
        ':document_id' => $docId,
        ':action' => $actionCode,
        ':detail' => 'เจ้าหน้าที่/ผู้ดูแลระบบเปลี่ยนสถานะเอกสารเป็น ' . $statusText
    ]);


    // บันทึก notification ให้เจ้าของเอกสาร
    $noti = $pdo->prepare("
        INSERT INTO notifications (user_id, document_id, channel, title, message, is_read)
        VALUES (:user_id, :document_id, 'email', :title, :message, 0)
    ");
    $noti->execute([
        ':user_id' => $doc['owner_id'],
        ':document_id' => $docId,
        ':title' => 'เอกสารของคุณ' . $statusText,
        ':message' => 'เอกสารเรื่อง "' . ($doc['subject'] ?: 'ไม่ระบุเรื่องเอกสาร') . '" มีสถานะเป็น ' . $statusText
    ]);

    // เตรียมส่งเมลหาเจ้าของเอกสาร
    // เจ้าของเอกสารอาจเป็น user ปกติหรือ officer ที่สร้างเอกสารเองได้
    // ดังนั้นห้ามจำกัดการส่งอีเมลเฉพาะ role user เท่านั้น ให้ส่งตาม owner_id ของเอกสารเสมอ
    $ownerEmail = trim((string)($doc['owner_email'] ?? ''));
    $ownerRoleId = (int)($doc['owner_role_id'] ?? 0);
    $ownerName = $doc['owner_name'] ?: (($ownerRoleId === 2) ? 'เจ้าหน้าที่' : 'ผู้ใช้งาน');
    $actorName = $doc['actor_name'] ?: 'เจ้าหน้าที่';
    $subjectText = $doc['subject'] ?: 'ไม่ระบุเรื่องเอกสาร';
    $templateName = trim((string)($doc['document_type_name'] ?? '')) !== ''
        ? trim((string)$doc['document_type_name'])
        : ($doc['template_name'] ?: 'ไม่ระบุประเภทเอกสาร');
    $reviewedAt = date('d/m/Y H:i');

    $baseUrl = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? 'https://'
        : 'http://'
    ) . $_SERVER['HTTP_HOST'] . '/Pro_letter';

    $loginUrl = $baseUrl . '/login.html';

    $emailSubject = '[Smart Government Letter Assistant System] เอกสารของคุณ' . $statusText;

    $statusColor = ($status === 'approved') ? '#16a34a' : '#dc2626';
    $statusBg = ($status === 'approved') ? '#f0fdf4' : '#fef2f2';

    $commentHtml = '';
    if ($status === 'rejected' && $reviewComment !== '') {
        $commentHtml = '
            <div style="margin-top:14px; padding-top:14px; border-top:1px solid #fecaca;">
                <p style="margin:0 0 8px 0; font-size:15px; font-weight:bold; color:#991b1b;">
                    ความคิดเห็นจากผู้ตรวจเอกสาร
                </p>
                <div style="background:#ffffff; border:1px solid #fecaca; border-radius:8px; padding:12px 14px; font-size:15px; color:#374151; line-height:1.8; white-space:normal;">
                    ' . nl2br(htmlspecialchars($reviewComment, ENT_QUOTES, 'UTF-8')) . '
                </div>
            </div>';
    }

    $emailBody = '
    <div style="font-family: Arial, Tahoma, sans-serif; max-width:680px; margin:auto; padding:24px; border:1px solid #e5e7eb; border-radius:14px; background:#ffffff;">
        <h2 style="color:#0f766e; margin-top:0;">
            Smart Government Letter Assistant System
        </h2>

        <p style="font-size:15px; color:#111827;">
            เรียน ' . htmlspecialchars($ownerName) . '
        </p>

        <p style="font-size:15px; color:#374151; line-height:1.8;">
            ระบบขอแจ้งให้ทราบว่าเอกสารของคุณได้รับการตรวจสอบแล้ว
        </p>

        <div style="background:' . $statusBg . '; border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin:18px 0;">
            <p><b>ประเภทเอกสาร :</b> ' . htmlspecialchars($templateName) . '</p>
            <p><b>เรื่อง :</b> ' . htmlspecialchars($subjectText) . '</p>
            <p><b>ผู้ดำเนินการ :</b> ' . htmlspecialchars($actorName) . '</p>
            <p><b>วันที่ดำเนินการ :</b> ' . htmlspecialchars($reviewedAt) . ' น.</p>
            <p><b>สถานะปัจจุบัน :</b> 
                <span style="color:' . $statusColor . '; font-weight:bold;">' . htmlspecialchars($statusText) . '</span>
            </p>
            ' . $commentHtml . '
        </div>

        <p style="font-size:15px; color:#374151;">
            กรุณาเข้าสู่ระบบเพื่อตรวจสอบรายละเอียดเอกสาร
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

    // ส่งผลลัพธ์กลับหน้าเว็บทันที เพื่อให้ popup สำเร็จขึ้นเร็วขึ้น
    // จากนั้นค่อยส่งอีเมลต่อด้านหลัง โดยไม่ให้ผู้ใช้ต้องรอการส่งอีเมล
    $response = json_encode([
        'success' => true,
        'mail_sent' => !empty($ownerEmail) ? 'processing' : false
    ], JSON_UNESCAPED_UNICODE);

    session_write_close();
    ignore_user_abort(true);

    if (!headers_sent()) {
        header('Content-Length: ' . strlen($response));
        header('Connection: close');
    }

    echo $response;

    while (ob_get_level() > 0) {
        @ob_end_flush();
    }

    @flush();

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    if (!empty($ownerEmail)) {
        $mailSent = sendSystemMail($ownerEmail, $emailSubject, $emailBody);
        if (!$mailSent) {
            error_log('Update Status Mail Error: cannot send mail to ' . $ownerEmail);
        }
    }

    exit;

} catch (Exception $e) {
    error_log('Update Status Error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบ'
    ]);
    exit;
}