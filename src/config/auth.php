<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

function requireEmployeeRole(array $allowedRoles): void
{
    global $conn;
    $employeeID = (int) ($_SESSION['employee_id'] ?? 0);

    if ($employeeID <= 0) {
        header('Location: /login.php?next=admin');
        exit;
    }

    $stmt = $conn->prepare("
        SELECT CONCAT(FirstName, ' ', LastName) AS EmployeeName, Role
        FROM employees
        WHERE EmployeeID = ? AND IsActive = 1
    ");
    $stmt->bind_param('i', $employeeID);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$employee || !in_array($employee['Role'], ['staff', 'admin'], true)) {
        unset(
            $_SESSION['employee_id'],
            $_SESSION['employee_role'],
            $_SESSION['employee_name']
        );
        session_regenerate_id(true);
        header('Location: /login.php?next=admin');
        exit;
    }

    $role = $employee['Role'];
    $_SESSION['employee_role'] = $role;
    $_SESSION['employee_name'] = $employee['EmployeeName'];

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        exit('Bạn không có quyền truy cập chức năng này.');
    }
}
