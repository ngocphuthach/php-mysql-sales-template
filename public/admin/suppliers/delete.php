<?php

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/suppliers/');
    exit;
}

$supplierID = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$active = filter_input(INPUT_POST, 'active', FILTER_VALIDATE_INT);
if (!$supplierID || $supplierID < 1 || !in_array($active, [0, 1], true)) {
    http_response_code(400);
    $conn->close();
    exit('Yêu cầu không hợp lệ.');
}

$stmt = $conn->prepare('UPDATE suppliers SET IsActive = ? WHERE SupplierID = ?');
$stmt->bind_param('ii', $active, $supplierID);

if (!$stmt->execute()) {
    throw new RuntimeException('Không thể cập nhật trạng thái nhà cung cấp.');
}

$stmt->close();
$conn->close();
header('Location: /admin/suppliers/');
exit;
