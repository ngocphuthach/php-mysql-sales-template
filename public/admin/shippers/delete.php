<?php

require_once '/var/www/src/config/database.php';

$conn->query("
    CREATE TABLE IF NOT EXISTS shippers (
        ShipperID INT NOT NULL AUTO_INCREMENT,
        ShipperName VARCHAR(255) NOT NULL,
        Phone VARCHAR(50) DEFAULT NULL,
        IsActive TINYINT(1) NOT NULL DEFAULT 1,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (ShipperID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

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
