<?php

$pageTitle = 'Thêm Nhà vận chuyển';

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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipperName = trim($_POST['shipper_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($shipperName === '') {
        $error = 'Tên nhà vận chuyển không được để trống.';
    } else {
        $sql = "
            INSERT INTO shippers (ShipperName, Phone)
            VALUES (?, ?)
        ";

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $error = 'Không thể chuẩn bị truy vấn thêm nhà vận chuyển.';
        } else {
            $stmt->bind_param('ss', $shipperName, $phone);

            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header('Location: /admin/shippers/');
                exit;
            }

            $error = 'Không thể thêm nhà vận chuyển.';
            $stmt->close();
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Thêm Nhà vận chuyển</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label for="shipperName" class="form-label">Tên nhà vận chuyển</label>
            <input
                type="text"
                class="form-control"
                id="shipperName"
                name="shipper_name"
                value="<?= htmlspecialchars($_POST['shipper_name'] ?? '') ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Số điện thoại</label>
            <input
                type="text"
                class="form-control"
                id="phone"
                name="phone"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                placeholder="Ví dụ: 0909 123 456"
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Lưu
        </button>

        <a href="/admin/shippers/" class="btn btn-secondary">
            Hủy
        </a>
    </form>
</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>
