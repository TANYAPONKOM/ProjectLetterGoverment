<?php
// /Pro_letter/documents/auto_download_pdf.php
// ใช้สำหรับเปิดหน้าเอกสารที่ถูกต้อง แล้วเรียก downloadPdf() อัตโนมัติจากหน้าหลัก
session_start();
require_once __DIR__ . '/../functions.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0);
$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$hint = trim((string)($_GET['hint'] ?? ''));

if ($docId <= 0) {
    http_response_code(400);
    exit('Invalid document id');
}

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

function cleanPath($path) {
    $path = trim((string)$path);
    $path = str_replace(["\r", "\n", "\0"], '', $path);
    return $path;
}

function pathToProLetterUrl($path, $docId) {
    $path = cleanPath($path);

    if ($path === '') {
        $path = '/documents/view_memo.php';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        $url = $path;
    } else {
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        if (strpos($path, '/Pro_letter/') !== 0) {
            $path = '/Pro_letter' . $path;
        }
        $url = $path;
    }

    $sep = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $sep . 'id=' . rawurlencode((string)$docId) . '&auto_pdf=1';
}

function documentPathFromHint($hint) {
    $text = trim((string)$hint);

    if ($text === '') {
        return '';
    }

    $routes = [
        [
            'keywords' => ['หนังสือยินยอมให้นำเสนอผลงานทางวิชาการ', 'ยินยอมให้นำเสนอผลงานทางวิชาการ', 'ยินยอมให้นำเสนอผลงานวิจัย', 'consent_research_presentation', 'infor_present', 'form_consent_research_presentation'],
            'path' => '/form_Memo/form_consent_research_presentation.php',
        ],
        [
            'keywords' => ['สหกิจ', 'ประเมินสถานประกอบการ', 'coop_evaluation'],
            'path' => '/form_Memo/form_memo_coop_evaluation.php',
        ],
        [
            'keywords' => ['จัดกิจกรรมโครงการ', 'กิจกรรมโครงการ', 'project_activity'],
            'path' => '/form_Memo/form_memo_project_activity.php',
        ],
        [
            'keywords' => ['ขอความอนุเคราะห์ข้อมูลจัดทำปริญญานิพนธ์', 'ปริญญานิพนธ์', 'research_data'],
            'path' => '/form_Memo/form_memo_request_research_data.php',
        ],
        [
            'keywords' => ['เข้าเยี่ยมศึกษาดูงาน', 'ศึกษาดูงาน', 'study_visit', 'sut_wellness'],
            'path' => '/form_Memo/form_memo_sut_wellness.php',
        ],
        [
            'keywords' => ['เรียนเชิญวิทยากร', 'เชิญวิทยากร', 'invite_speaker'],
            'path' => '/form_Memo/form_memo_invite_speaker.php',
        ],
        [
            'keywords' => ['เป็นวิทยากร', 'speaker_workshop', 'form_memo_speaker'],
            'path' => '/form_Memo/form_memo_speaker.php',
        ],
        [
            'keywords' => ['ห้องพักรับรอง', 'room_request'],
            'path' => '/form_Memo/form_memo_room_request_1.php',
        ],
        [
            'keywords' => ['นำเสนอผลงานวิจัย', 'นำเสนอผลงานทางวิชาการ', 'academic_presentation'],
            'path' => '/form_Memo/form_memo_academic_1.php',
        ],
    ];

    foreach ($routes as $route) {
        foreach ($route['keywords'] as $keyword) {
            if ($keyword !== '' && mb_strpos($text, $keyword) !== false) {
                return $route['path'];
            }
        }
    }

    return '';
}

$pdo = db();
$stmt = $pdo->prepare("\n    SELECT d.document_id, d.template_id, d.owner_id, d.subject, t.document_path\n    FROM documents d\n    LEFT JOIN templates t ON t.template_id = d.template_id\n    WHERE d.document_id = :id\n    LIMIT 1\n");
$stmt->execute([':id' => $docId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    http_response_code(404);
    exit('ไม่พบเอกสาร');
}

if ($roleId !== 1 && $roleId !== 2 && (int)$document['owner_id'] !== $userId) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์ดูเอกสารนี้');
}

$documentPath = documentPathFromHint($hint);
if ($documentPath === '') {
    $documentPath = cleanPath($document['document_path'] ?? '');
}
if ($documentPath === '') {
    $documentPath = '/documents/view_memo.php';
}

$targetUrl = pathToProLetterUrl($documentPath, $docId);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>กำลังดาวน์โหลด PDF</title>
  <style>
    body {
      margin: 0;
      font-family: "Sarabun", "TH Sarabun New", Arial, sans-serif;
      background: #f3f4f6;
    }

    iframe {
      width: 0;
      height: 0;
      border: 0;
      position: absolute;
      left: -9999px;
      top: -9999px;
    }

    .pdf-loading-overlay {
      position: fixed;
      inset: 0;
      z-index: 99999;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.62);
      backdrop-filter: blur(5px);
      -webkit-backdrop-filter: blur(5px);
    }

    .pdf-loading-card {
      width: 292px;
      min-height: 214px;
      border-radius: 18px;
      background: #ffffff;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 28px 24px;
      text-align: center;
    }

    .pdf-spinner {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      border: 7px solid #d7f3ee;
      border-top-color: #12b8a6;
      animation: pdfSpin 0.95s linear infinite;
      margin-bottom: 22px;
    }

    @keyframes pdfSpin {
      to { transform: rotate(360deg); }
    }

    .pdf-loading-title {
      color: #087d76;
      font-size: 22px;
      font-weight: 700;
      line-height: 1.25;
      margin-bottom: 8px;
    }

    .pdf-loading-text {
      color: #6b7280;
      font-size: 15px;
      line-height: 1.5;
    }

    .pdf-error-title {
      color: #b91c1c;
    }

    .pdf-error-button {
      margin-top: 18px;
      border: 0;
      border-radius: 999px;
      padding: 9px 22px;
      background: #14b8a6;
      color: #ffffff;
      font-size: 15px;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <iframe id="pdfFrame" src="<?= h($targetUrl) ?>"></iframe>

  <div id="pdfLoadingOverlay" class="pdf-loading-overlay">
    <div class="pdf-loading-card">
      <div id="pdfSpinner" class="pdf-spinner"></div>
      <div id="pdfLoadingTitle" class="pdf-loading-title">กำลังสร้าง PDF...</div>
      <div id="pdfLoadingText" class="pdf-loading-text">กรุณารอสักครู่ ระบบกำลังเตรียมเอกสาร</div>
      <button id="pdfCloseButton" class="pdf-error-button" type="button" style="display:none;">ปิด</button>
    </div>
  </div>

  <script>
    const frame = document.getElementById('pdfFrame');
    const overlay = document.getElementById('pdfLoadingOverlay');
    const spinner = document.getElementById('pdfSpinner');
    const title = document.getElementById('pdfLoadingTitle');
    const text = document.getElementById('pdfLoadingText');
    const closeButton = document.getElementById('pdfCloseButton');

    let tried = false;

    function closeLoading() {
      if (overlay) {
        overlay.style.display = 'none';
      }
    }

    function showPdfError(message) {
      if (spinner) spinner.style.display = 'none';
      if (title) {
        title.textContent = 'ไม่สามารถสร้าง PDF ได้';
        title.classList.add('pdf-error-title');
      }
      if (text) {
        text.textContent = message || 'กรุณาเปิดหน้าเอกสารแล้วกดดาวน์โหลด PDF จากหน้านั้น';
      }
      if (closeButton) {
        closeButton.style.display = 'inline-block';
      }
    }

    if (closeButton) {
      closeButton.addEventListener('click', () => {
        closeLoading();
        try { window.close(); } catch (e) {}
      });
    }

    function startDownload() {
      if (tried) return;
      tried = true;

      try {
        const win = frame.contentWindow;
        if (win && typeof win.downloadPdf === 'function') {
          const result = win.downloadPdf();
          Promise.resolve(result).finally(() => {
            closeLoading();
            setTimeout(() => {
              try { window.close(); } catch (e) {}
            }, 800);
          });
        } else {
          showPdfError('ไม่พบฟังก์ชันดาวน์โหลด PDF ในหน้าเอกสารนี้');
        }
      } catch (err) {
        showPdfError('ระบบไม่สามารถเรียกดาวน์โหลด PDF จากหน้าเอกสารได้');
      }
    }

    frame.addEventListener('load', () => {
      setTimeout(startDownload, 900);
    });

    setTimeout(() => {
      if (!tried) {
        showPdfError('ใช้เวลานานเกินไป กรุณาเปิดหน้าเอกสารแล้วกดดาวน์โหลด PDF จากหน้านั้น');
      }
    }, 20000);
  </script>
</body>
</html>
