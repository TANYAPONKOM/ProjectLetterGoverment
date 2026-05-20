<?php   //pro_letter/user/home.php
session_start();
require_once __DIR__ . '/../functions.php'; 

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
  <title>รายการส่งคำขอ</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
  html,
  body {
    height: 100vh;
    overflow: hidden;
    margin: 0;
  }

  body {
    display: flex;
    flex-direction: column;
  }

  main {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .swal2-actions {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 12px !important;
    flex-wrap: nowrap !important;
  }

  .swal2-actions button {
    margin: 0 !important;
  }

  #holdCancelBtn {
    min-width: 220px;
  }

  .cancel-actions-row {
    display: flex !important;
    flex-direction: row !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 12px !important;
    flex-wrap: nowrap !important;
  }

  .myCancelBtn {
    background: #6b7280 !important;
    color: #fff !important;
    border: none !important;
    padding: 12px 24px !important;
    border-radius: 10px !important;
    font-weight: bold !important;
    font-size: 15px !important;
    min-width: 120px !important;
    margin: 0 !important;
  }

  .myCancelBtn {
    min-width: 140px !important;
  }

  #requestListContainer {
    flex: 1;
    overflow-y: auto;
  }
  </style>
</head>

<body class="bg-gray-100 ">
  <!-- Header -->
  <header class="bg-teal-500 text-white p-4 flex justify-between items-center shadow-md"
    style="font-family: Arial, Helvetica, sans-serif;">
    <div class="flex items-center space-x-3">
      <div class="w-[56px] h-[56px] flex items-center justify-center relative overflow-visible">
        <svg xmlns="http://www.w3.org/2000/svg" class="absolute scale-[1.4] text-white"
          style="width: 60px; height: 60px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
    <div class="flex items-center space-x-4">
      <a href="home.php">
        <div class="px-4 py-2 rounded-[11px] font-bold transition bg-white text-teal-500 shadow">หน้าหลัก</div>
      </a>

      <?php 
                if (isset($_SESSION['permissions']) && in_array(3, $_SESSION['permissions'])) {
                    renderAdminExtraMenus(); 
                }
            ?>

      <a href="../documents/form_Memo.php">
        <div class="px-4 py-2 rounded-[11px] font-bold transition text-white">แบบฟอร์มบันทึกข้อความ</div>
      </a>
      <div class="relative">
        <!-- ปุ่ม Profile -->
        <button id="profileBtn"
          class="bg-white text-teal-500 px-4 py-2 rounded-[11px] shadow flex items-center space-x-2 hover:bg-gray-100">
          <div class="text-right leading-tight">
            <div class="font-bold text-[14px]">
              <?= htmlspecialchars($_SESSION['fullname'] ?? 'Guest') ?>
            </div>
            <div class="text-[12px]">
              <?= htmlspecialchars($_SESSION['role_name'] ?? '') ?>
            </div>

          </div>
          <div class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
              stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M5.121 17.804A13.937 13.937 0 0112 15c2.33 0 4.487.577 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
        </button>

        <!-- เมนู Dropdown -->
        <div id="profileMenu" class="hidden absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-50">
          <a href="../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">ออกจากระบบ</a>
          <button onclick="closeMenu()"
            class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">อยู่ต่อ</button>
        </div>
      </div>

    </div>
  </header>


  <!-- Content -->
  <main class="max-w-7xl w-full px-8 mx-auto bg-white mt-4 mb-12 p-6 rounded shadow min-h-[85vh]">
    <h2 class="text-xl font-bold mb-4">รายการส่งคำขอ</h2>

    <!-- Tabs -->
    <div class="flex space-x-6 border-b mb-4">
      <button id="tab-pending" class="bg-teal-500 text-white px-4 py-2 rounded-t-md font-semibold">
        รอตรวจสอบ
      </button>
      <button id="tab-done" class="text-gray-500 px-4 py-2 rounded-t-md font-semibold">
        ผ่านการตรวจสอบแล้ว
      </button>
      <button id="tab-edit" class="text-gray-500 px-4 py-2 rounded-t-md font-semibold">
        รอการแก้ไข
      </button>
    </div>

    <!-- Filter + Sort -->
    <div class="flex justify-between items-center mb-2">
      <label class="text-sm text-gray-700">
        แสดง:
        <select id="itemsPerPage" class="border rounded px-2 py-1 text-sm">
          <option value="5" selected>5</option>
          <option value="10">10</option>
        </select>
        รายการ/หน้า
      </label>
      <button id="sortBtn" class="flex items-center text-sm text-teal-600">
        วันที่
        <svg id="sortIcon" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24"
          stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
        </svg>
      </button>
    </div>

    <!-- List Container -->
    <div id="requestListContainer">
      <div id="requestList" class="space-y-4"></div>
    </div>
    <div id="pagination" class="flex justify-center mt-6 space-x-2"></div>
  </main>

  <script>
  let dataAll = [];

  async function loadRequests() {
    const res = await fetch("../documents/get_requests.php");

    const data = await res.json();

    dataAll = data.map(d => {

      // ====== สถานะสำหรับแสดงผล ======
      let statusTh = "";
      let userViewStatus = "";

      if (d.status === "draft") {
        // ⚠️ ยังไม่ส่งจริง แต่ให้ user เห็นเหมือนอยู่รอตรวจสอบ
        statusTh = "รอตรวจสอบ";
        userViewStatus = "pending_view";
      } else if (d.status === "submitted") {
        statusTh = "รอตรวจสอบ";
        userViewStatus = "pending_view";
      } else if (d.status === "approved") {
        statusTh = "ผ่านการตรวจสอบแล้ว";
        userViewStatus = "approved";
      } else if (d.status === "rejected") {
        statusTh = "รอการแก้ไข";
        userViewStatus = "rejected";
      }

      return {
        document_id: d.document_id,
        join_type: d.join_type || "",
        title: d.join_type || "(ไม่มีชื่อเรื่อง)",
        detail: d.course_name || "(ไม่มีรายละเอียด)",
        date: d.doc_date,

        raw_status: d.status, // ⭐ สถานะจริง DB
        view_status: userViewStatus, // ⭐ สถานะใช้จัดแท็บ user
        status: statusTh, // แสดงผลภาษาไทย

        word: d.word_file,
        pdf: d.pdf_file
      };
    });

    renderList();
  }

  loadRequests();


  let currentPage = 1;
  let itemsPerPage = 10;
  let sortAsc = false;

  const requestList = document.getElementById("requestList");
  const pagination = document.getElementById("pagination");
  const itemsPerPageEl = document.getElementById("itemsPerPage");
  const sortBtn = document.getElementById("sortBtn");
  const sortIcon = document.getElementById("sortIcon");
  const tabPending = document.getElementById("tab-pending");
  const tabDone = document.getElementById("tab-done");
  const tabEdit = document.getElementById("tab-edit");

  function formatDate(iso) {
    const date = new Date(iso);
    return date.toLocaleDateString("th-TH", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  }

  function renderList() {
    const dataFiltered = dataAll.filter(d => d.view_status === activeTab);

    const sorted = dataFiltered.sort((a, b) =>
      sortAsc ?
      new Date(a.date) - new Date(b.date) :
      new Date(b.date) - new Date(a.date)
    );

    const start = (currentPage - 1) * itemsPerPage;
    const shown = sorted.slice(start, start + itemsPerPage);

    requestList.innerHTML = shown.map(req => {

      /* ===== สถานะ ===== */
      let statusClass = "";
      if (req.status === "รอตรวจสอบ") {
        statusClass = "bg-yellow-100 text-yellow-700";
      } else if (req.status === "ผ่านการตรวจสอบแล้ว") {
        statusClass = "bg-green-100 text-green-700";
      } else if (req.status === "รอการแก้ไข") {
        statusClass = "bg-red-100 text-red-700";
      }

      /* ===== ปุ่ม / ข้อความด้านล่าง ===== */
      let actionHtml = "";

      // 🟡 ยังเป็น draft → แสดงปุ่ม
      if (req.raw_status === "draft") {
        actionHtml = `
  <div class="mt-3 flex gap-2">
    <button onclick="submitDocument(${req.document_id})"
      class="px-5 py-2 bg-teal-500 hover:bg-teal-600
             text-white text-sm font-semibold rounded-xl shadow">
      ยืนยันการส่ง
    </button>

    <button onclick="cancelDocument(${req.document_id})"
      class="px-5 py-2 bg-red-500 hover:bg-red-600
             text-white text-sm font-semibold rounded-xl shadow">
      ยกเลิกคำขอ
    </button>
  </div>
`;
      }

      // ⏳ ส่งแล้ว → แสดงข้อความ (ไม่ใช่ปุ่ม)
      else if (req.raw_status === "submitted") {
        actionHtml = `
        <div class="mt-3 px-4 py-2 rounded-xl
                    bg-yellow-50 text-yellow-700
                    text-sm font-semibold border border-yellow-300">
          ⏳ กำลังรอตรวจสอบ
        </div>
      `;
      }

      return `
      <div class="bg-gray-50 p-4 rounded-xl shadow flex justify-between items-start">

        <!-- ซ้าย -->
        <div class="flex-1 min-w-0 pr-4">
          <a href="#" onclick="openDocument(${req.document_id}, '${String(req.join_type).replace(/'/g, "\\'")}')"
             class="font-semibold text-teal-600 hover:underline text-lg">
            ${req.title}
          </a>

         <div class="text-sm text-gray-500 mt-1">

            <!-- รายละเอียด -->
            <div class="break-words">
              ${req.detail}
            </div>

            <!-- สถานะ -->
            <div class="mt-2 flex items-center gap-2">
              <span>สถานะ:</span>

              <span class="px-2 py-1 rounded-full text-xs font-semibold whitespace-nowrap ${statusClass}">
                ${req.status}
              </span>
            </div>

          </div>
        </div>

        <!-- ขวา -->
        <div class="text-right flex flex-col items-end text-sm text-gray-600 min-w-[200px]">

          <!-- วันที่ -->
          <div class="font-medium mb-2">
            ${formatDate(req.date)}
          </div>

          <!-- PDF / Word -->
          <div class="flex items-center space-x-4">

            ${
              req.word
                ? `
                  <a href="${req.word}" target="_blank"
                     class="flex items-center space-x-1 text-blue-500 hover:underline">
                    <img src="https://cdn-icons-png.flaticon.com/16/281/281760.png">
                    <span>Word</span>
                  </a>
                `
                : `
                  <div class="flex items-center space-x-1 text-blue-500">
                    <img src="https://cdn-icons-png.flaticon.com/16/281/281760.png">
                    <span>Word</span>
                  </div>
                `
            }

            ${
              req.pdf
                ? `
                  <a href="${req.pdf}" target="_blank"
                     class="flex items-center space-x-1 text-red-500 hover:underline">
                    <img src="https://cdn-icons-png.flaticon.com/16/337/337946.png">
                    <span>PDF</span>
                  </a>
                `
                : `
                  <div class="flex items-center space-x-1 text-red-500">
                    <img src="https://cdn-icons-png.flaticon.com/16/337/337946.png">
                    <span>PDF</span>
                  </div>
                `
            }

          </div>

          <!-- ปุ่ม / ข้อความ -->
          ${actionHtml}

        </div>
      </div>
    `;
    }).join("");

    /* ===== Pagination ===== */
    const totalPages = Math.ceil(dataFiltered.length / itemsPerPage);
    pagination.innerHTML = Array.from({
        length: totalPages
      }, (_, i) => i + 1)
      .map(i => `
      <button onclick="goToPage(${i})"
        class="px-3 py-1 rounded border
        ${i === currentPage
          ? "bg-teal-500 text-white"
          : "text-teal-500 border-teal-500"}">
        ${i}
      </button>
    `).join("");
  }




  function goToPage(page) {
    currentPage = page;
    renderList();
  }

  sortBtn.onclick = () => {
    sortAsc = !sortAsc;
    sortIcon.innerHTML = sortAsc ?
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />' :
      '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />';
    renderList();
  };

  itemsPerPageEl.onchange = () => {
    itemsPerPage = parseInt(itemsPerPageEl.value);
    currentPage = 1;
    renderList();
  };

  let activeTab = "pending_view";

  tabPending.onclick = () => {
    activeTab = "pending_view"; // รวม draft + submitted
    tabPending.classList.add("bg-teal-500", "text-white");
    tabDone.classList.remove("bg-teal-500", "text-white");
    tabEdit.classList.remove("bg-teal-500", "text-white");
    currentPage = 1;
    renderList();
  };

  tabDone.onclick = () => {
    activeTab = "approved";
    tabDone.classList.add("bg-teal-500", "text-white");
    tabPending.classList.remove("bg-teal-500", "text-white");
    tabEdit.classList.remove("bg-teal-500", "text-white");
    currentPage = 1;
    renderList();
  };

  tabEdit.onclick = () => {
    activeTab = "rejected";
    tabEdit.classList.add("bg-teal-500", "text-white");
    tabDone.classList.remove("bg-teal-500", "text-white");
    tabPending.classList.remove("bg-teal-500", "text-white");
    currentPage = 1;
    renderList();
  };


  renderList();

  const profileBtn = document.getElementById("profileBtn");
  const profileMenu = document.getElementById("profileMenu");

  profileBtn.addEventListener("click", () => {
    profileMenu.classList.toggle("hidden");
  });

  function closeMenu() {
    profileMenu.classList.add("hidden");
  }

  // กดนอกเมนูให้ปิด
  window.addEventListener("click", (e) => {
    if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
      profileMenu.classList.add("hidden");
    }
  });




  document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);
    const errType = params.get("err");

    if (errType === "no_view") {
      Swal.fire({
        title: "ไม่มีสิทธิ์ดูเอกสารนี้",
        text: "คุณไม่มีสิทธิ์ในการเข้าถึงเอกสารนี้",
        icon: "error",
        confirmButtonText: "ตกลง",
        confirmButtonColor: "#3085d6",
      }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete("err");
        window.history.replaceState({}, "", url.toString());
      });
    }
  });

  function openDocument(docId, joinType = "") {
    fetch("../check_view_permission.php?id=" + docId)

      .then(r => r.json())
      .then(res => {
        console.log("Returned JSON:", res); // ⭐ Debug

        if (!res || typeof res.allowed === "undefined") {
          Swal.fire("Error", "ข้อมูลที่ส่งกลับไม่ถูกต้อง", "error");
          return;
        }

        if (res.allowed === true) {
          const url = (joinType === "นำเสนอผลงานวิจัย") ?
            "../form_Memo/form_memo_academic_1.php?id=" + docId :
            "../documents/view_memo.php?id=" + docId;

          window.location.href = url;
          return;
        }

        if (res.reason === "not_login") {
          Swal.fire("กรุณาเข้าสู่ระบบ", "", "warning");
          return;
        }

        if (res.reason === "no_permission") {
          Swal.fire({
            title: "ไม่มีสิทธิ์เข้าดูเอกสารนี้",
            text: "คุณไม่สามารถเข้าถึงเอกสารนี้ได้",
            icon: "error",
            confirmButtonText: "ตกลง"
          });
          return;
        }

        Swal.fire("เกิดข้อผิดพลาด", "ไม่สามารถตรวจสอบสิทธิ์ได้", "error");
      })
      .catch(err => {
        console.log("Fetch error:", err); // ⭐ Debug
        Swal.fire("Error", "ไม่สามารถตรวจสอบสิทธิ์ได้", "error");
      });
  }

  function submitDocument(id) {
    Swal.fire({
      title: "ยืนยันการส่งเอกสาร?",
      text: "เอกสารจะถูกส่งให้เจ้าหน้าที่ตรวจสอบ",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "ยืนยัน",
      cancelButtonText: "ยกเลิก"
    }).then(result => {
      if (!result.isConfirmed) return;

      fetch("../documents/submit_document.php", {

          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            document_id: id
          })
        })
        .then(r => r.json())
        .then(res => {
          if (res.success) {


            Swal.fire({
              icon: "success",
              title: "ส่งเรียบร้อย",
              text: "เอกสารอยู่ระหว่างรอการตรวจสอบ",
              timer: 1500,
              showConfirmButton: false
            });

            // ✅ โหลดข้อมูลใหม่จาก DB
            loadRequests();


          } else {
            Swal.fire("ผิดพลาด", res.message || "ไม่สามารถส่งได้", "error");
          }
        });
    });
  }

  function cancelDocument(id) {
    Swal.fire({
      title: "ยกเลิกคำขอนี้?",
      html: `
      <p style="font-size:16px; margin-bottom:10px; text-align:center;">
        หากยกเลิกแล้ว รายการนี้จะถูกลบออกจากรายการคำขอ
      </p>
    `,
      icon: "warning",
      showConfirmButton: false,
      showCancelButton: true,
      cancelButtonText: "ไม่ยกเลิก",
      buttonsStyling: false,
      customClass: {
        actions: "cancel-actions-row",
        cancelButton: "myCancelBtn"
      },
      didOpen: () => {
        const actions = Swal.getActions();

        const holdBtn = document.createElement("button");
        holdBtn.id = "holdCancelBtn";
        holdBtn.type = "button";
        holdBtn.textContent = "กดค้าง 2 วินาทีเพื่อยืนยัน";

        holdBtn.style.cssText = `
        background:#dc2626;
        color:#fff;
        border:none;
        padding:12px 24px;
        border-radius:10px;
        font-weight:bold;
        cursor:pointer;
        font-size:15px;
        min-width:220px;
        margin:0;
      `;

        actions.prepend(holdBtn);

        let timer = null;

        const startHold = (e) => {
          e.preventDefault();

          holdBtn.textContent = "กำลังกดยืนยัน...";
          holdBtn.style.background = "#991b1b";

          timer = setTimeout(() => {
            Swal.close();

            fetch("../documents/cancel_document.php", {
                method: "POST",
                headers: {
                  "Content-Type": "application/json"
                },
                body: JSON.stringify({
                  document_id: id
                })
              })
              .then(r => r.json())
              .then(res => {
                if (res.success) {
                  Swal.fire({
                    icon: "success",
                    title: "ยกเลิกคำขอแล้ว",
                    timer: 1200,
                    showConfirmButton: false
                  });

                  loadRequests();
                } else {
                  Swal.fire(
                    "ผิดพลาด",
                    res.message || "ไม่สามารถยกเลิกได้",
                    "error"
                  );
                }
              });
          }, 2000);
        };

        const stopHold = () => {
          clearTimeout(timer);
          timer = null;

          holdBtn.textContent = "กดค้าง 2 วินาทีเพื่อยืนยัน";
          holdBtn.style.background = "#dc2626";
        };

        holdBtn.addEventListener("mousedown", startHold);
        holdBtn.addEventListener("mouseup", stopHold);
        holdBtn.addEventListener("mouseleave", stopHold);

        holdBtn.addEventListener("touchstart", startHold);
        holdBtn.addEventListener("touchend", stopHold);
        holdBtn.addEventListener("touchcancel", stopHold);
      }
    });
  }
  </script>

</body>

</html>