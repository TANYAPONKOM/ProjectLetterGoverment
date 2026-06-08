<?php 
session_start();

// DEV LOGIN
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

require_once __DIR__ . '/functions.php';
$pdo = db();

// โหลดข้อมูลเอกสาร (ถ้ามี)
$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// โหลดข้อมูลผู้ใช้
$userId = $_SESSION['user_id'];

// โหลดข้อมูลเดิมจาก document_values
$valueMap = [];
if ($docId > 0) {
    $vals = $pdo->prepare("SELECT field_id, value_text FROM document_values WHERE document_id = :id");
    $vals->execute([':id' => $docId]);
    foreach ($vals->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $valueMap[(int)$r['field_id']] = (string)$r['value_text'];
    }
}

// Helper
// function h($s){
//   return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
// }

// Map fields
$name = $valueMap[1] ?? '';
$table_data = $valueMap[9] ?? ''; 
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ประมาณการค่าใช้จ่ายเข้ารับการฝึกอบรม</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
    @import url("https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap");

    body {
        background: #f3f3f3;
        font-family: "Sarabun", sans-serif;
    }

    .page {
        width: 794px;
        min-height: 1123px;
        margin: 40px auto;
        background: #fff;
        padding: 50px 60px 20px;
        /* 👈 เปลี่ยนจาก 60px → 20px */
        box-shadow: 0 0 5px rgba(0, 0, 0, .15);
        border: 2px solid #fff;
        position: relative;
    }

    .no-print {
        position: absolute;
        bottom: 15px;
        /* ⭐ ขยับลงจนเกือบชิดขอบล่าง A4 */
        right: 50px;
        /* ⭐ หรือปรับเองได้ */
    }


    .row {
        display: flex;
        justify-content: space-between;
        width: 100%;
        font-size: 16pt;
        margin-bottom: 4px;
    }

    .indent-1 {
        padding-left: 30px;
    }

    .indent-2 {
        padding-left: 60px;
    }

    .right {
        text-align: right;
        min-width: 150px;
    }

    .bold {
        font-weight: bold;
    }

    .underline {
        border-bottom: 1px solid #000;
        padding-bottom: 2px;
    }

    /* .section-title {
        font-size: 16pt;
        font-weight: bold;
        margin-top: 15px;
        margin-bottom: 8px;
        text-decoration: underline;
    } */
    </style>

</head>

<body>

    <div class="page">
        <form action="save_expense.php" method="post" id="expenseForm">
            <!-- หัวข้อแบบ PDF -->
            <div class="text-center leading-[1.4] mb-4">

                <div class="text-[12.5pt] font-bold mb-[6px]">
                    ประมาณการค่าใช้จ่าย
                </div>

                <div class="text-[12.5pt] font-bold mb-[6px]">
                    ค่าลงทะเบียนในการเข้ารับการฝึกอบรม
                </div>

                <div class="text-[12.5pt] font-bold mb-[6px]">
                    หลักสูตร “พัฒนา Mobile App ด้วย React Native, TypeScript และ Expo” ในรูปแบบออนไลน์
                </div>

                <div class="text-[12.5pt] font-bold">
                    ระหว่างวันที่ 14 - 15 ธันวาคม 2567 และในวันที่ 21 - 22 ธันวาคม 2567
                </div>

            </div>


            <style>
            .exp-row {
                display: grid;
                grid-template-columns: 350px 120px 120px 40px;
                font-size: 12pt;
                line-height: 1.45;
                margin-bottom: 8px;
            }

            .main-indent {
                padding-left: 40px;
            }

            .sub-indent {
                padding-left: 75px;
            }

            .sum-row {
                display: grid;
                grid-template-columns: 350px 120px 120px 40px;
                /* รายการ | ตัวเลขซ้าย | ตัวเลขขวา | บาท */
                font-size: 12pt;
                line-height: 1.45;
                margin-bottom: 8px;
            }

            .indent-1 {
                padding-left: 25px;
            }

            .col-item {
                text-align: left;
            }

            .col-leftnum {
                text-align: right;
                padding-right: 6px;
                width: 120px;
            }

            .col-rightnum {
                text-align: right;
                padding-right: 6px;
                width: 120px;
                font-weight: bold;
            }

            .col-baht {
                text-align: left;
            }

            .underline {
                border-bottom: 1px solid #000;
                padding-bottom: 2px;
            }
            </style>


            <h2 class="section-title" style="width:680px; 
           margin:30px auto 10px; 
           font-size:12pt; 
           font-weight:bold;">

                <span style="display:inline-block; 
                 text-decoration: underline; 
                 font-weight:bold; 
                 margin-right:6px;">
                    รายจ่าย
                </span>

                <span style="font-weight:bold;">
                    (ผู้ช่วยศาสตราจารย์สมชัย เชียงพงศ์พันธุ์)
                </span>

            </h2>


            <div style="width:680px; margin:auto;">

                <!-- 1 -->
                <div class="exp-row">
                    <div class="col-item main-indent">1. ค่าตอบแทน</div>
                    <div class="col-leftnum"></div>
                    <div class="col-rightnum" contenteditable="true">0.00</div>
                    <div class="col-baht">บาท</div>
                </div>

                <div class="exp-row">
                    <div class="col-item sub-indent">1.1 -</div>
                    <div class="col-leftnum" contenteditable="true">0.00</div>
                    <div class="col-rightnum"></div>
                    <div class="col-baht"></div>
                </div>

                <!-- 2 -->
                <div class="exp-row">
                    <div class="col-item main-indent">2. ค่าใช้สอย</div>
                    <div class="col-leftnum"></div>
                    <div class="col-rightnum" contenteditable="true">4,900.00</div>
                    <div class="col-baht">บาท</div>
                </div>

                <div class="exp-row">
                    <div class="col-item sub-indent">2.1 ค่าลงทะเบียน (4,900.00 บาท × 1 คน)</div>
                    <div class="col-leftnum" contenteditable="true">4,900.00</div>
                    <div class="col-rightnum"></div>
                    <div class="col-baht"></div>
                </div>

                <!-- 3 -->
                <div class="exp-row">
                    <div class="col-item main-indent">3. ค่าวัสดุ</div>
                    <div class="col-leftnum"></div>
                    <div class="col-rightnum" contenteditable="true">0.00</div>
                    <div class="col-baht">บาท</div>
                </div>

                <div class="exp-row">
                    <div class="col-item sub-indent">3.1 -</div>
                    <div class="col-leftnum" contenteditable="true">0.00</div>
                    <div class="col-rightnum"></div>
                    <div class="col-baht"></div>
                </div>


                <!-- รวม -->
                <div class="sum-row">
                    <div class="col-item" style="grid-column: 1 / span 2; text-align:center;">
                        รวมรายจ่าย
                    </div>


                    <!-- ตัวเลข 4,900.00 + ขีดใต้ -->
                    <div class="col-rightnum" style="text-align:right;">
                        <span style="display:inline-block; border-bottom:1px solid #000; padding-bottom:1px;">
                            4,900.00
                        </span>
                    </div>

                    <!-- บาท + ขีดใต้ -->
                    <div class="col-baht">
                        <span style="display:inline-block; border-bottom:1px solid #000; padding-bottom:1px;">
                            บาท
                        </span>
                    </div>
                </div>


            </div>

            <p style="width:680px;margin:15px auto 0;font-size:12pt;">
                <b>หมายเหตุ</b> ขอถัวจ่ายทุกรายการ
            </p>


            <!-- Hidden -->
            <input type="hidden" name="doc_id" value="<?= $docId ?>">
            <input type="hidden" name="table_data" id="table_data">

            <!-- ปุ่ม -->
            <div class="no-print mt-8 flex justify-end gap-4">
                <button type="button" onclick="window.print()" class="px-6 py-2 bg-blue-500 text-white rounded-lg">
                    พิมพ์
                </button>
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg">
                    บันทึก
                </button>
            </div>

        </form>
    </div>

    <script>
    // เก็บค่าข้อความทั้งหมดก่อน submit
    document.getElementById("expenseForm").addEventListener("submit", () => {

        const rows = [];
        document.querySelectorAll(".row").forEach(row => {
            let cols = [...row.children].map(c => c.innerText.trim());
            rows.push(cols);
        });

        document.getElementById("table_data").value = JSON.stringify(rows);
    });
    </script>

</body>

</html>