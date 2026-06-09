<?php
// Pro_letter/includes/google_user_notify.php

require_once __DIR__ . '/send_mail.php';

/**
 * ส่งอีเมลแจ้งผู้ใช้ Google Login ว่าผู้ดูแลระบบเพิ่มข้อมูลครบแล้ว
 * และสามารถเข้าใช้งานระบบได้
 */
function notifyGoogleUserProfileApproved(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.fullname,
            u.email,
            u.auth_provider,
            u.profile_completed,
            u.is_active,
            u.position,
            d.department_name,
            r.role_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return false;
    }

    // ส่งเฉพาะ Google user ที่ข้อมูลครบแล้ว และบัญชีเปิดใช้งาน
    if (
        ($user['auth_provider'] ?? '') !== 'google' ||
        (int)$user['profile_completed'] !== 1 ||
        (int)$user['is_active'] !== 1 ||
        empty($user['email'])
    ) {
        return false;
    }

    $fullname = htmlspecialchars($user['fullname'] ?? '-', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($user['email'] ?? '-', ENT_QUOTES, 'UTF-8');
    $roleName = htmlspecialchars($user['role_name'] ?? '-', ENT_QUOTES, 'UTF-8');
    $position = htmlspecialchars($user['position'] ?? '-', ENT_QUOTES, 'UTF-8');
    $departmentName = htmlspecialchars($user['department_name'] ?? '-', ENT_QUOTES, 'UTF-8');

    // ตอนใช้ในเครื่อง
    $loginUrl = 'http://localhost/Pro_letter/login.html';

    // ตอนอัปขึ้นเว็บจริง ให้เปลี่ยนเป็น:
    // $loginUrl = 'https://proletterdemo.infinityfreeapp.com/Pro_letter/login.html';

    $subject = 'บัญชีของคุณพร้อมใช้งานแล้ว';

    $htmlBody = '
    <div style="font-family:Arial, Tahoma, sans-serif; max-width:680px; margin:0 auto; color:#1f2937;">
        <div style="background:#14b8a6; color:white; padding:18px 22px; border-radius:12px 12px 0 0;">
            <h2 style="margin:0; font-size:20px;">
                Smart Government Letter Assistant System
            </h2>
            <p style="margin:6px 0 0; font-size:14px;">
                แจ้งผลการอนุมัติข้อมูลผู้ใช้งาน
            </p>
        </div>

        <div style="border:1px solid #e5e7eb; border-top:none; padding:22px; border-radius:0 0 12px 12px;">
            <p style="font-size:15px; line-height:1.7; margin-top:0;">
                เรียนคุณ <b>' . $fullname . '</b>
            </p>

            <p style="font-size:15px; line-height:1.7;">
                ผู้ดูแลระบบได้ตรวจสอบและเพิ่มข้อมูลบัญชีผู้ใช้งานของคุณเรียบร้อยแล้ว
                ขณะนี้คุณสามารถเข้าสู่ระบบและใช้งาน Smart Government Letter Assistant System ได้ตามสิทธิ์ที่ได้รับ
            </p>

            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin:18px 0;">
                <p style="margin:6px 0;"><b>ชื่อผู้ใช้ :</b> ' . $fullname . '</p>
                <p style="margin:6px 0;"><b>อีเมล :</b> ' . $email . '</p>
                <p style="margin:6px 0;"><b>สิทธิ์การใช้งาน :</b> ' . $roleName . '</p>
                <p style="margin:6px 0;"><b>ตำแหน่ง :</b> ' . $position . '</p>
                <p style="margin:6px 0;"><b>หน่วยงาน / ภาควิชา :</b> ' . $departmentName . '</p>
                <p style="margin:6px 0;"><b>สถานะ :</b> สามารถใช้งานระบบได้แล้ว</p>
            </div>

            <p style="font-size:15px; color:#374151; line-height:1.7;">
                กรุณาเข้าสู่ระบบด้วยบัญชี Google เดิมที่ใช้ลงทะเบียนไว้
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

    return sendSystemMail($user['email'], $subject, $htmlBody);
}