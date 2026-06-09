<?php
// Pro_letter/includes/google_admin_notify.php

require_once __DIR__ . '/send_mail.php';

/**
 * ส่งอีเมลแจ้ง Admin เมื่อมีผู้ใช้ใหม่เข้าสู่ระบบด้วย Google
 *
 * เงื่อนไข:
 * - ส่งเฉพาะผู้ใช้ที่ profile_completed = 0
 * - ส่งเฉพาะครั้งแรก หรือเมื่อ admin_notified_at ยังเป็น NULL
 * - ส่งหา Admin ที่ role_id = 1 และ is_active = 1 และมี email
 */
function notifyAdminNewGoogleUser(PDO $pdo, int $newUserId): bool
{
    // ดึงข้อมูลผู้ใช้ใหม่
    $userStmt = $pdo->prepare("
        SELECT 
            user_id,
            fullname,
            email,
            auth_provider,
            profile_completed,
            admin_notified_at,
            created_at
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");
    $userStmt->execute([$newUserId]);
    $newUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$newUser) {
        return false;
    }

    // ต้องเป็นผู้ใช้ Google ที่ยังรอ Admin เพิ่มข้อมูล
    if (
        ($newUser['auth_provider'] ?? '') !== 'google' ||
        (int)$newUser['profile_completed'] === 1
    ) {
        return false;
    }

    // ถ้าเคยแจ้ง Admin แล้ว ไม่ต้องส่งซ้ำ
    if (!empty($newUser['admin_notified_at'])) {
        return true;
    }

    // ดึงอีเมล Admin ทั้งหมด
    $adminStmt = $pdo->prepare("
        SELECT user_id, fullname, email
        FROM users
        WHERE role_id = 1
          AND is_active = 1
          AND email IS NOT NULL
          AND email <> ''
    ");
    $adminStmt->execute();
    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($admins)) {
        return false;
    }

    $adminEmails = [];
    foreach ($admins as $admin) {
        if (!empty($admin['email'])) {
            $adminEmails[] = $admin['email'];
        }
    }

    $adminEmails = array_values(array_unique($adminEmails));

    if (empty($adminEmails)) {
        return false;
    }

    $fullname = htmlspecialchars($newUser['fullname'] ?? '-', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($newUser['email'] ?? '-', ENT_QUOTES, 'UTF-8');
    $createdAt = htmlspecialchars($newUser['created_at'] ?? '-', ENT_QUOTES, 'UTF-8');

    // URL หน้า Login / หน้าเข้าสู่ระบบ Admin
    $loginUrl = 'http://localhost/Pro_letter/login.html';

    $subject = 'มีผู้ใช้ใหม่รอผู้ดูแลระบบเพิ่มข้อมูล';

    $htmlBody = '
    <div style="font-family:Arial, Tahoma, sans-serif; max-width:680px; margin:0 auto; color:#1f2937;">
        <div style="background:#14b8a6; color:white; padding:18px 22px; border-radius:12px 12px 0 0;">
            <h2 style="margin:0; font-size:20px;">
                Smart Government Letter Assistant System
            </h2>
            <p style="margin:6px 0 0; font-size:14px;">
                แจ้งเตือนผู้ใช้ใหม่จาก Google Login
            </p>
        </div>

        <div style="border:1px solid #e5e7eb; border-top:none; padding:22px; border-radius:0 0 12px 12px;">
            <p style="font-size:15px; line-height:1.7; margin-top:0;">
                มีผู้ใช้ใหม่เข้าสู่ระบบด้วยบัญชี Google และระบบได้บันทึกข้อมูลชื่อกับอีเมลไว้แล้ว
                แต่ผู้ใช้งานยังไม่สามารถสร้างเอกสารได้จนกว่าผู้ดูแลระบบจะเพิ่มข้อมูลให้ครบถ้วน
            </p>

            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin:18px 0;">
                <p style="margin:6px 0;"><b>ชื่อผู้ใช้ :</b> ' . $fullname . '</p>
                <p style="margin:6px 0;"><b>อีเมล :</b> ' . $email . '</p>
                <p style="margin:6px 0;"><b>สถานะ :</b> รอผู้ดูแลระบบเพิ่มข้อมูล</p>
                <p style="margin:6px 0;"><b>วันที่เข้าสู่ระบบครั้งแรก :</b> ' . $createdAt . '</p>
            </div>

            <p style="font-size:15px; color:#374151; line-height:1.7;">
                กรุณาเข้าสู่ระบบในฐานะผู้ดูแลระบบ แล้วไปที่หน้าจัดการผู้ใช้
                เพื่อกำหนดข้อมูล เช่น สิทธิ์การใช้งาน หน่วยงาน และตำแหน่ง
            </p>

            <p style="margin-top:18px;">
                <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '" 
                   style="display:inline-block; background:#14b8a6; color:white; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:bold;">
                    เข้าสู่ระบบ
                </a>
            </p>

            <hr style="border:none; border-top:1px solid #e5e7eb; margin:22px 0;">

            <p style="font-size:12px; color:#6b7280; line-height:1.6;">
                อีเมลฉบับนี้ถูกส่งโดยอัตโนมัติจาก Smart Government Letter Assistant System
                กรุณาอย่าตอบกลับอีเมลนี้
            </p>
        </div>
    </div>
    ';

    $mailSent = sendSystemMail($adminEmails, $subject, $htmlBody);

    if ($mailSent) {
        // อัปเดตเวลาที่แจ้ง Admin แล้ว เพื่อไม่ให้ส่งซ้ำ
        $updateStmt = $pdo->prepare("
            UPDATE users
            SET admin_notified_at = NOW()
            WHERE user_id = ?
        ");
        $updateStmt->execute([$newUserId]);

        // เพิ่ม notification ในระบบให้ Admin ด้วย ถ้าตาราง notifications ใช้งานอยู่
        foreach ($admins as $admin) {
            try {
                $notiStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, document_id, channel, title, message, is_read)
                    VALUES (?, NULL, 'email', ?, ?, 0)
                ");
                $notiStmt->execute([
                    $admin['user_id'],
                    'มีผู้ใช้ใหม่รอเพิ่มข้อมูล',
                    'ผู้ใช้ ' . ($newUser['fullname'] ?? '-') . ' (' . ($newUser['email'] ?? '-') . ') เข้าสู่ระบบด้วย Google และรอผู้ดูแลระบบเพิ่มข้อมูล'
                ]);
            } catch (Throwable $e) {
                error_log('Google Admin Notify Notification Error: ' . $e->getMessage());
            }
        }
    }

    return $mailSent;
}