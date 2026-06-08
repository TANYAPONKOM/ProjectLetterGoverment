<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

function sendSystemMail($to, $subject, $htmlBody)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // Gmail ที่ใช้เป็นผู้ส่ง
        $mail->Username   = 'tanyapornkomkham1@gmail.com';

        // App Password 16 ตัว
        $mail->Password   = 'kfdr nybv axjs lpec';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom(
            'tanyapornkomkham1@gmail.com',
            'Smart Government Letter Assistant System'
        );

        if (is_array($to)) {
            foreach ($to as $email) {
                if (!empty($email)) {
                    $mail->addAddress($email);
                }
            }
        } else {
            $mail->addAddress($to);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}