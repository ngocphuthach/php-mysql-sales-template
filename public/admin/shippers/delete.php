<?php

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/shippers/');
    exit;
}

$shipperID = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$action = $_POST['action'] ?? '';

if ($shipperID <= 0 || !in_array($action, ['activate', 'deactivate'], true)) {
    http_response_code(400);
    $conn->close();
    exit('Yêu cầu không hợp lệ.');
}

$isActive = $action === 'activate' ? 1 : 0;
$sql = "UPDATE shippers SET IsActive = ? WHERE ShipperID = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $conn->close();
    throw new RuntimeException('Không thể chuẩn bị truy vấn cập nhật nhà vận chuyển.');
}

$stmt->bind_param('ii', $isActive, $shipperID);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    throw new RuntimeException('Không thể cập nhật trạng thái nhà vận chuyển.');
}

$stmt->close();
$conn->close();

header('Location: /admin/shippers/');
exit;
