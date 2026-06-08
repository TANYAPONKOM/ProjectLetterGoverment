<?php
session_start();
session_unset(); // ลบค่า session ทั้งหมด
session_destroy(); // ทำลาย session

// ส่งกลับไปหน้า login
header("Location: login.html");
exit;