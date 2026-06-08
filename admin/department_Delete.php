<?php
session_start();
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header('Location: ../login.html');
    exit;
}
<<<<<<< HEAD

require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

function redirectDepartment(string $params): void {
    header("Location: department_Managerment.php?" . $params);
    exit;
}

function tableColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function countTableUsage(PDO $pdo, string $table, string $column, int $departmentId): int {
    $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$departmentId]);
    return (int)$stmt->fetchColumn();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirectDepartment("error=invalid_id&detail=" . urlencode("ไม่พบรหัสภาควิชาที่ต้องการลบ"));
}

try {
    $existsStmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
    $existsStmt->execute([$id]);
    $departmentName = $existsStmt->fetchColumn();

    if (!$departmentName) {
        redirectDepartment("error=not_found&detail=" . urlencode("ไม่พบข้อมูลภาควิชานี้ในระบบ"));
    }

    $usageMessages = [];
    $checkedKeys = [];

    $knownReferences = [
        ['documents', 'department_id', 'เอกสาร'],
        ['users', 'department_id', 'ผู้ใช้งาน'],
        ['document_values', 'department_id', 'ข้อมูลเอกสาร'],
        ['department_reports', 'department_id', 'รายงานภาควิชา'],
        ['logs', 'department_id', 'ประวัติการใช้งาน'],
    ];

    foreach ($knownReferences as $ref) {
        [$table, $column, $label] = $ref;
        $key = $table . '.' . $column;
        if (isset($checkedKeys[$key])) {
            continue;
        }

        if (tableColumnExists($pdo, $table, $column)) {
            $count = countTableUsage($pdo, $table, $column, $id);
            if ($count > 0) {
                $usageMessages[] = $label . " " . $count . " รายการ";
            }
            $checkedKeys[$key] = true;
        }
    }

    $fkStmt = $pdo->prepare("
        SELECT TABLE_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND REFERENCED_TABLE_NAME = 'departments'
          AND REFERENCED_COLUMN_NAME = 'department_id'
    ");
    $fkStmt->execute();
    $fkRows = $fkStmt->fetchAll(PDO::FETCH_ASSOC);

    $tableLabels = [
        'documents' => 'เอกสาร',
        'users' => 'ผู้ใช้งาน',
        'document_values' => 'ข้อมูลเอกสาร',
        'department_reports' => 'รายงานภาควิชา',
        'logs' => 'ประวัติการใช้งาน',
    ];

    foreach ($fkRows as $fk) {
        $table = $fk['TABLE_NAME'];
        $column = $fk['COLUMN_NAME'];
        $key = $table . '.' . $column;

        if (isset($checkedKeys[$key])) {
            continue;
        }

        $count = countTableUsage($pdo, $table, $column, $id);
        if ($count > 0) {
            $label = $tableLabels[$table] ?? ("ตาราง " . $table);
            $usageMessages[] = $label . " " . $count . " รายการ";
        }
        $checkedKeys[$key] = true;
    }

    if (!empty($usageMessages)) {
        $detail = "ลบไม่ได้ เพราะภาควิชา \"" . $departmentName . "\" ถูกใช้งานอยู่ใน " . implode(", ", $usageMessages) . " กรุณาแก้ไขหรือลบข้อมูลที่เกี่ยวข้องก่อน";
        redirectDepartment("error=delete_in_use&detail=" . urlencode($detail));
    }

    $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
    $stmt->execute([$id]);

    redirectDepartment("success=delete");
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $detail = "ลบไม่ได้ เพราะภาควิชานี้ยังถูกเชื่อมโยงกับข้อมูลอื่นในฐานข้อมูล กรุณาตรวจสอบเอกสาร ผู้ใช้งาน หรือข้อมูลที่เกี่ยวข้องก่อน";
        redirectDepartment("error=delete_in_use&detail=" . urlencode($detail));
    }

    redirectDepartment("error=delete_failed&detail=" . urlencode("เกิดข้อผิดพลาดระหว่างลบข้อมูล: " . $e->getMessage()));
}
=======
require_once __DIR__ . '/../functions.php';
$pdo = getPDO();

$id = $_GET['id'] ?? 0;

if ($id) {
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE department_id=?");
    $checkStmt->execute([$id]);
    $documentCount = (int)$checkStmt->fetchColumn();

    if ($documentCount > 0) {
        echo "<script>alert('ไม่สามารถลบภาควิชานี้ได้ เนื่องจากมีเอกสารที่ใช้งานภาควิชานี้อยู่'); window.location.href='department_Managerment.php';</script>";
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id=?");
    $stmt->execute([$id]);
}

header("Location: department_Managerment.php?success=delete");
exit;
>>>>>>> 74fc84333157a4da620127e2e8ede3798723df6a
?>
