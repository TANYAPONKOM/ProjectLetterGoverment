<?php 

// pro_letter/documents/EXP_Presentation.php
session_start();

// DEV LOGIN
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

require_once __DIR__ . '/../functions.php';
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
// ถ้ามาจาก form_Calcu.php ให้ใช้ข้อมูลจาก $_POST ก่อน
// ถ้าเปิดจาก id เอกสารเดิม ค่อยใช้ $valueMap

$name = $_POST['fullname'] 
    ?? $_POST['owner_name'] 
    ?? ($valueMap[2] ?? '');

$position = $_POST['position'] 
    ?? ($valueMap[3] ?? '');

$faculty = $_POST['faculty'] 
    ?? ($valueMap[10] ?? 'คณะเทคโนโลยีและการจัดการอุตสาหกรรม');

$department = $_POST['department'] 
    ?? ($valueMap[11] ?? 'เทคโนโลยีสารสนเทศ');

$conference = $_POST['event_title'] 
    ?? $_POST['course_name'] 
    ?? ($valueMap[5] ?? '');

$place = $_POST['place'] 
    ?? $_POST['location'] 
    ?? ($valueMap[7] ?? '');

$paper_title = $_POST['academic_topic'] 
    ?? ($valueMap[13] ?? '');

$date_range = $_POST['event_date'] 
    ?? $_POST['join_date'] 
    ?? ($valueMap[6] ?? '');

$table_data = $valueMap[9] ?? '';

// ===============================
// รับข้อมูลค่าใช้จ่ายจาก form_Calcu.php
// ===============================
$expenseJson = $_POST['expense_json'] ?? '';
$postedAmount = $_POST['amount'] ?? '0.00';

$expenseData = json_decode($expenseJson, true);
if (!is_array($expenseData)) {
    $expenseData = [];
}

$expenseRows = [];
$expenseTotal = 0;

function addExpenseRow(&$rows, &$total, $desc, $amount)
{
    $desc = trim((string)$desc);
    $amount = (float)$amount;

    if ($desc !== '' && $amount > 0) {
        $rows[] = [
            'desc' => $desc,
            'amount' => $amount
        ];
        $total += $amount;
    }
}

// 1. ค่าตอบแทน
if (!empty($expenseData['compensation'])) {
    foreach ($expenseData['compensation'] as $item) {
        $desc = $item['desc'] ?? '';
        $amount = (float)($item['amount'] ?? 0);

        addExpenseRow(
            $expenseRows,
            $expenseTotal,
            "ค่าตอบแทน\n- " . $desc,
            $amount
        );
    }
}

// 2.1 ค่าลงทะเบียน
$reg = $expenseData['allowance']['registration'] ?? [];
if (!empty($reg['enabled'])) {
    $price = (float)($reg['price'] ?? 0);
    $people = (int)($reg['people'] ?? 1);
    $amount = $price * $people;

    addExpenseRow(
        $expenseRows,
        $expenseTotal,
        "ค่าลงทะเบียน\n- ค่าลงทะเบียน " . number_format($price, 2) . " บาท × {$people} คน",
        $amount
    );
}

// 2.2 ค่าที่พัก
$lod = $expenseData['allowance']['lodging'] ?? [];
if (!empty($lod['enabled'])) {
    $unit = (float)($lod['unit_price'] ?? 0);
    $nights = (int)($lod['nights'] ?? 0);
    $people = (int)($lod['people'] ?? 0);
    $dateText = trim($lod['date_text'] ?? '');
    $amount = $unit * $nights * $people;

    $desc = "ค่าที่พัก ตามที่จ่ายจริง\n";
    $desc .= "- คืนละ " . number_format($unit, 2) . " บาท รวม {$nights} คืน {$people} คน";
    if ($dateText !== '') {
        $desc .= "\n- {$dateText}";
    }

    addExpenseRow($expenseRows, $expenseTotal, $desc, $amount);
}

// 2.3 ค่าเบี้ยเลี้ยง
$per = $expenseData['allowance']['perdiem'] ?? [];
if (!empty($per['enabled'])) {
    $unit = (float)($per['unit_price'] ?? 0);
    $meals = (int)($per['meals'] ?? 0);
    $people = (int)($per['people'] ?? 0);
    $amount = $unit * $meals * $people;

    addExpenseRow(
        $expenseRows,
        $expenseTotal,
        "ค่าเบี้ยเลี้ยง\n- ค่าเบี้ยเลี้ยง " . number_format($unit, 2) . " บาท × {$meals} มื้อ × {$people} คน",
        $amount
    );
}

// 2.4 ค่าพาหนะ
$transport = $expenseData['allowance']['transport'] ?? [];
if (!empty($transport['enabled']) && !empty($transport['items'])) {
    foreach ($transport['items'] as $item) {
        $type = $item['type'] ?? '';
        $desc = '';
        $amount = 0;

        if ($type === 'fuel') {
            $origin = trim($item['origin'] ?? '');
            $destination = trim($item['destination'] ?? '');
            $distance = (float)($item['distance'] ?? 0);
            $rate = (float)($item['rate'] ?? 4);
            $trips = (int)($item['trips'] ?? 1);

            $amount = $distance * $rate * $trips;

            $desc = "ค่าพาหนะ\n";
            $desc .= "- ค่าน้ำมันรถยนต์ {$origin} ไป {$destination}\n";
            $desc .= "- ระยะทาง {$distance} กม. × {$rate} บาท × {$trips} เที่ยว";
        } elseif ($type === 'flight') {
            $airline = trim($item['airline'] ?? '');
            $route = trim($item['route'] ?? '');
            $ticket = (float)($item['ticket_price'] ?? 0);
            $trips = (int)($item['trips'] ?? 1);
            $people = (int)($item['people'] ?? 1);

            $amount = $ticket * $trips * $people;

            $desc = "ค่าพาหนะ\n";
            $desc .= "- ค่าโดยสารตั๋วเครื่องบิน ไป-กลับ ชั้นประหยัด\n";
            $desc .= "- {$airline} {$route}\n";
            $desc .= "- " . number_format($ticket, 2) . " บาท × {$trips} เที่ยว × {$people} คน";
        } else {
            $route = trim($item['route'] ?? ($item['desc'] ?? ''));
            $unit = (float)($item['unit_price'] ?? 0);
            $trips = (int)($item['trips'] ?? 1);
            $people = (int)($item['people'] ?? 1);

            $amount = $unit * $trips * $people;

            $desc = "ค่าพาหนะ\n";
            $desc .= "- {$route}\n";
            $desc .= "- " . number_format($unit, 2) . " บาท × {$trips} เที่ยว × {$people} คน";
        }

        addExpenseRow($expenseRows, $expenseTotal, $desc, $amount);
    }
}

// 3. ค่าวัสดุ
if (!empty($expenseData['materials'])) {
    foreach ($expenseData['materials'] as $item) {
        $desc = $item['desc'] ?? '';
        $amount = (float)($item['amount'] ?? 0);

        addExpenseRow(
            $expenseRows,
            $expenseTotal,
            "ค่าวัสดุ\n- " . $desc,
            $amount
        );
    }
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>ประมาณค่าใช้จ่ายในการนำเสนอผลงาน</title>

  <script src="https://cdn.tailwindcss.com"></script>

  <style>
  @import url("https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap");

  body {
    background: #f3f3f3;
    font-family: "TH SarabunPSK", sans-serif;
  }

  .page {
    width: 794px;
    min-height: 1123px;
    margin: 20px auto;
    background: #fff;
    padding: 55px 70px 35px;
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



  h1 {
    text-align: center;
    font-size: 26pt;
    font-weight: bold;
    margin-bottom: 20px;
  }

  .doc-label {
    font-size: 18pt;
  }

  .dot-line {
    flex: 1;
    height: 24px;
    position: relative;
    margin-left: 8px;
  }

  .dot-line::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background-image: radial-gradient(circle, #000 1px, transparent 1px);
    background-size: 6px 2px;
    background-repeat: repeat-x;
  }

  .chip {
    font-size: 18pt;
    padding: 0 3px;
    background: #fff;
    position: relative;
    z-index: 2;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
  }

  th,
  td {
    border: 1px solid #000;
    padding: 6px;
    font-size: 15pt;
    /* 🔽 จาก 16pt เหลือ 14pt */
    text-align: center;
  }


  th {
    font-weight: bold;
    background: #f8f8f8;
  }

  .exp-title {
    font-size: 15pt;
    font-weight: bold;
    text-align: center;
    line-height: 1.05;
    margin-bottom: 18px;
  }

  .exp-info {
    font-size: 13.5pt;
    line-height: 1.15;
    width: 560px;
    margin-top: 8px;
    margin-left: 45px;
  }

  .exp-row {
    display: flex;
    margin-bottom: 3px;
  }

  .exp-label {
    width: 145px;
    flex-shrink: 0;
  }

  .exp-value {
    flex: 1;
  }

  .exp-section-title {
    font-size: 14pt;
    font-weight: bold;
    margin-top: 28px;
    margin-bottom: 8px;
    margin-left: 45px;
  }

  #expenseTable {
    width: 500px;
    margin-left: 85px;
    margin-right: auto;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 12.5pt;
    line-height: 1.12;
  }

  #expenseTable th,
  #expenseTable td {
    border: 1px solid #000;
    padding: 3px 5px;
    font-size: 12.5pt;
    line-height: 1.12;
  }

  #expenseTable th {
    background: #fff;
    text-align: center;
    font-weight: bold;
  }

  .exp-note {
    font-size: 13pt;
    margin-top: 18px;
    margin-left: 45px;
  }

  @media print {
    body {
      background: white;
    }

    .page {
      width: 21cm;
      min-height: 29.7cm;
      padding: 2cm;
      box-shadow: none;
      border: none;
    }

    .no-print {
      display: none;
    }
  }
  </style>
</head>

<body>

  <div class="page">
    <form action="save_expense.php" method="post" id="expenseForm">

     <!-- ส่วนข้อมูลผู้ขอ -->
<div class="mt-4 mb-8">

  <div class="exp-title">
    ประมาณการค่าใช้จ่าย<br>
    การนำเสนอผลงานวิจัยในการประชุมวิชาการระดับนานาชาติ
  </div>

  <div class="exp-info">

    <div class="exp-row">
      <div class="exp-label">ชื่อ-สกุล</div>
      <div class="exp-value">ผู้ช่วยศาสตราจารย์ ดร.ธนัฐชา นามี</div>
    </div>

    <div class="exp-row">
      <div class="exp-label">มหาวิทยาลัยต้นสังกัด</div>
      <div class="exp-value">
        ภาควิชาเทคโนโลยีสารสนเทศ คณะเทคโนโลยีและการจัดการอุตสาหกรรม<br>
        มหาวิทยาลัยเทคโนโลยีพระจอมเกล้าพระนครเหนือ วิทยาเขตปราจีนบุรี
      </div>
    </div>

    <div class="exp-row">
      <div class="exp-label">ชื่อการประชุมวิชาการ</div>
      <div class="exp-value">
        The 6th International Conference on Computational Intelligence and<br>
        Intelligent Systems (CIIS 2023)
      </div>
    </div>

    <div class="exp-row">
      <div class="exp-label">วันที่</div>
      <div class="exp-value">24 - 28 พฤศจิกายน 2566</div>
    </div>

    <div class="exp-row">
      <div class="exp-label">สถานที่</div>
      <div class="exp-value">Waseda University, Tokyo ประเทศญี่ปุ่น</div>
    </div>

    <div class="exp-row">
      <div class="exp-label">ชื่อผลงานวิจัย</div>
      <div class="exp-value">
        Enhancing Indoor Positioning Accuracy: A Comprehensive Study on<br>
        Euclidean Distance, Trilateration, Wi-Fi RTT and FTM Protocol<br>
        Integration
      </div>
    </div>

  </div>
</div>

<div class="exp-section-title">
  ตารางสรุปค่าใช้จ่ายในการไปนำเสนอผลงานวิจัย
</div>

  <table id="expenseTable">
  <tr>
    <th style="
      width:48px;
      border:1px solid #000;
      padding:3px 4px;
      text-align:center;
      font-weight:bold;
      vertical-align:middle;
    ">
      ลำดับ<br>ที่
    </th>

    <th style="
      width:340px;
      border:1px solid #000;
      padding:3px 6px;
      text-align:center;
      font-weight:bold;
      vertical-align:middle;
    ">
      รายการ
    </th>

    <th style="
      width:112px;
      border:1px solid #000;
      padding:3px 4px;
      text-align:center;
      font-weight:bold;
      vertical-align:middle;
    ">
      จำนวนเงิน (บาท)
    </th>
  </tr>

  <?php if (!empty($expenseRows)): ?>
    <?php foreach ($expenseRows as $index => $row): ?>
      <tr>
        <td style="
          border:1px solid #000;
          padding:3px 4px;
          text-align:center;
          vertical-align:top;
          <?= in_array($index + 1, [1,2,4,5]) ? 'color:red;' : '' ?>
        ">
          <?= $index + 1 ?>
        </td>

        <td style="
          border:1px solid #000;
          padding:3px 6px;
          text-align:left;
          vertical-align:top;
          <?= in_array($index + 1, [1,2,4,5]) ? 'color:red;' : '' ?>
        ">
          <?= nl2br(h($row['desc'])) ?>
        </td>

        <td style="
          border:1px solid #000;
          padding:3px 4px;
          text-align:right;
          vertical-align:top;
          <?= in_array($index + 1, [1,2,4,5]) ? 'color:red;' : '' ?>
        ">
          <?= number_format((float)$row['amount'], 2) ?>
        </td>
      </tr>
    <?php endforeach; ?>

    <tr>
      <td style="
        border:1px solid #000;
        padding:3px 4px;
        background:#ffffff;
      "></td>

      <td style="
        border:1px solid #000;
        padding:3px 6px;
        text-align:right;
        font-weight:normal;
        background:#ffffff;
      ">
        รวมเป็นเงิน
      </td>

      <td style="
        border:1px solid #000;
        padding:3px 4px;
        text-align:right;
        font-weight:normal;
        background:#ffffff;
      ">
        <?= number_format((float)$expenseTotal, 2) ?>
      </td>
    </tr>

  <?php else: ?>
    <tr>
      <td colspan="3" style="
        border:1px solid #000;
        padding:8px;
        text-align:center;
      ">
        ไม่พบข้อมูลประมาณค่าใช้จ่าย
      </td>
    </tr>
  <?php endif; ?>
</table>

<div class="exp-note">
  <b>หมายเหตุ</b> ขอถัวจ่ายทุกรายการ
</div>

      <!-- Hidden -->
      <input type="hidden" name="doc_id" value="<?= $docId ?>">
      <input type="hidden" name="table_data" id="table_data">
      <input type="hidden" name="total_amount" value="<?= h($expenseTotal) ?>">

      <!-- ปุ่ม -->
      <div class="no-print mt-8 flex justify-end gap-4">
        <button type="button" onclick="window.print()"
          class="px-8 py-2 bg-blue-500 text-white rounded-lg text-[16pt] font-bold shadow-sm">
          พิมพ์
        </button>

        <button type="submit" class="px-8 py-2 bg-green-600 text-white rounded-lg text-[16pt] font-bold shadow-sm">
          บันทึก
        </button>

    </form>
  </div>

  <script>
  // ป้องกันขึ้นบรรทัดใหม่ใน chip
  document.querySelectorAll("[contenteditable]").forEach(el => {
    el.addEventListener("keydown", e => {
      if (e.key === "Enter") e.preventDefault();
    });
  });

  // เก็บตารางเป็น JSON ก่อน submit
  document.getElementById("expenseForm").addEventListener("submit", () => {
    const rows = [];
    document.querySelectorAll("#expenseTable tr").forEach((tr, index) => {
      const cells = [...tr.children].map(td => td.innerText.trim());
      rows.push(cells);
    });
    document.getElementById("table_data").value = JSON.stringify(rows);
  });
  </script>

</body>

</html>