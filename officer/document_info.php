<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ประวัติการใช้งานเอกสาร</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <!-- Header -->
  <header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md">
    <div class="flex items-center space-x-3">
      <div class="w-[56px] h-[56px] flex items-center justify-center relative">
        <svg xmlns="http://www.w3.org/2000/svg" class="absolute scale-[1.4] text-white"
          style="width: 60px; height: 60px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8m0 0a2 2 0 00-2-2H5a2 2 0 00-2 2m18 0v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8" />
        </svg>
      </div>
      <div class="leading-tight">
        <div class="text-[16px] font-bold">Smart</div>
        <div class="text-[16px] font-bold -mt-[2px]">Government</div>
        <div class="text-[13px] mt-[0px]">Letter Assistant System</div>
      </div>
    </div>
    </div>
    </div>
  </header>

  <!-- Content -->
  <main class="max-w-6xl mx-auto mt-6 bg-white rounded-lg shadow p-6">
    <h2 class="text-2xl font-bold text-teal-600 mb-4">เกี่ยวกับเอกสารราชการ</h2>
    <p class="mb-4">
      เอกสารราชการเป็นเอกสารที่ใช้ในการติดต่อสื่อสารภายในหน่วยงานราชการ รวมถึงการติดต่อกับบุคคลภายนอก
      โดยมีรูปแบบและข้อกำหนดที่ชัดเจน เพื่อให้เกิดความเป็นมาตรฐานและมีความน่าเชื่อถือ
    </p>

    <!-- Section: ประเภทของเอกสาร -->
    <section class="mb-6">
      <h3 class="text-lg font-semibold mb-2">ประเภทของเอกสารราชการ</h3>
      <ul class="list-disc list-inside space-y-1">
        <li><span class="font-bold">หนังสือภายใน</span> – ใช้ติดต่อสื่อสารระหว่างหน่วยงานราชการ</li>
        <li><span class="font-bold">หนังสือภายนอก</span> – ใช้ติดต่อกับบุคคลหรือองค์กรภายนอก</li>
        <li><span class="font-bold">บันทึกข้อความ</span> – ใช้บันทึกเรื่องภายในองค์กร (Memo)</li>
        <li><span class="font-bold">คำสั่ง/ประกาศ</span> – เอกสารที่มีผลบังคับใช้ตามกฎหมาย</li>
      </ul>
    </section>

    <!-- Section: โครงสร้าง -->
    <section class="mb-6">
      <h3 class="text-lg font-semibold mb-2">โครงสร้างมาตรฐานของเอกสาร</h3>
      <ol class="list-decimal list-inside space-y-1">
        <li>ตราครุฑ</li>
        <li>ชื่อส่วนราชการเจ้าของหนังสือ</li>
        <li>เลขที่หนังสือ</li>
        <li>วัน เดือน ปี ที่ออกหนังสือ</li>
        <li>เรื่องและสิ่งที่ส่งมาด้วย</li>
        <li>ข้อความ/เนื้อหา</li>
        <li>ชื่อ ตำแหน่ง และลายมือชื่อผู้มีอำนาจ</li>
      </ol>
    </section>

    <!-- Section: ตัวอย่าง -->
    <section class="mb-6">
      <h3 class="text-lg font-semibold mb-2">ตัวอย่างรูปแบบบันทึกข้อความ</h3>
      <div class="border rounded-lg p-4 bg-gray-50">
        <p class="text-center font-bold">บันทึกข้อความ</p>
        <p>ส่วนราชการ: คณะเทคโนโลยีและการจัดการอุตสาหกรรม</p>
        <p>ที่: ศธ 123/2567</p>
        <p>วันที่: 1 ตุลาคม 2567</p>
        <p>เรื่อง: ขออนุมัติจัดโครงการอบรม</p>
        <p class="mt-2">เรียน คณบดี...</p>
      </div>
    </section>

    <!-- Section: ประโยชน์ -->
    <section>
      <h3 class="text-lg font-semibold mb-2">ประโยชน์ของการใช้เอกสารราชการในระบบ</h3>
      <ul class="list-disc list-inside space-y-1">
        <li>จัดเก็บและค้นหาเอกสารได้สะดวก</li>
        <li>ลดการใช้กระดาษ (Paperless)</li>
        <li>ติดตามสถานะเอกสารแบบเรียลไทม์</li>
        <li>มีมาตรฐานเดียวกันทุกหน่วยงาน</li>
      </ul>
    </section>

    <!-- ปุ่มควบคุม -->
    <div class="flex justify-end space-x-3 mt-6">
      <!-- ปุ่มดูตัวอย่าง/พิมพ์ -->
      <button onclick="window.print()"
        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md shadow transition">
        ดูตัวอย่าง/พิมพ์
      </button>

      <!-- ปุ่มบันทึก -->
      <form method="post" action="save_document.php">
        <input type="hidden" name="doc_id" value="123"> <!-- ส่งค่า ID เอกสาร -->
        <button type="submit" class="bg-teal-500 hover:bg-teal-600 text-white px-4 py-2 rounded-md shadow transition">
          บันทึก
        </button>
      </form>
    </div>

  </main>

</body>

</html>