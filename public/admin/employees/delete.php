<?php

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/employees/');
    exit;
}

$employeeID = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
if (!$employeeID || $employeeID < 1 || !in_array($action, ['enable', 'disable'], true)) {
    http_response_code(400);
    $conn->close();
    exit('Yêu cầu không hợp lệ.');
}

if ((int) $employeeID === (int) $_SESSION['employee_id'] && $action === 'disable') {
    http_response_code(400);
    $conn->close();
    exit('Bạn không thể khóa tài khoản đang sử dụng.');
}

$lookup = $conn->prepare('SELECT Role, IsActive FROM employees WHERE EmployeeID = ?');
$lookup->bind_param('i', $employeeID);
$lookup->execute();
$employee = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$employee) {
    http_response_code(404);
    $conn->close();
    exit('Không tìm thấy nhân viên.');
}

$isActive = $action === 'enable' ? 1 : 0;
if (
    $action === 'disable'
    && $employee['Role'] === 'admin'
    && (int) $employee['IsActive'] === 1
) {
    $count = $conn->query("
        SELECT COUNT(*) AS Total
        FROM employees
        WHERE Role = 'admin' AND IsActive = 1
    ")->fetch_assoc();
    if ((int) $count['Total'] <= 1) {
        http_response_code(400);
        $conn->close();
        exit('Phải luôn có ít nhất một quản trị viên đang hoạt động.');
    }
}

$stmt = $conn->prepare('UPDATE employees SET IsActive = ? WHERE EmployeeID = ?');
$stmt->bind_param('ii', $isActive, $employeeID);
$stmt->execute();
$stmt->close();
$conn->close();

header('Location: /admin/employees/');
exit;
