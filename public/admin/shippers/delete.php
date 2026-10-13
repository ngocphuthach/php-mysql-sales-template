<?php

require_once '/var/www/src/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/shippers/');
    exit;
}

$shipperID = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($shipperID <= 0) {
    header('Location: /admin/shippers/');
    exit;
}

$sql = "DELETE FROM shippers WHERE ShipperID = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param('i', $shipperID);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

header('Location: /admin/shippers/');
exit;
