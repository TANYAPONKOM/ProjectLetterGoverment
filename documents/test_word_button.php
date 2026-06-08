<?php
// Pro_letter/documents/test_word_button.php
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Test Download Word</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 40px;
        }

        .box {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            text-align: center;
        }

        h1 {
            margin-bottom: 10px;
            color: #222;
        }

        p {
            color: #666;
            margin-bottom: 25px;
        }

        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 12px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
        }

        .btn:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

<div class="box">
    <h1>ทดสอบดาวน์โหลด Word</h1>
    <p>กดปุ่มด้านล่างเพื่อจำลองการสร้างไฟล์ .docx จาก PHPWord</p>

    <a class="btn" href="test_word_download.php">
        ดาวน์โหลด Word ทดสอบ
    </a>
</div>

</body>
</html>