<?php

$pageTitle = 'Sửa Nhà vận chuyển';

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
$shipperID = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$formIsActive = 1;

if ($shipperID <= 0) {
    die('Mã nhà vận chuyển không hợp lệ.');
}

$sql = "
    SELECT
        ShipperID,
        ShipperName,
        Phone,
        IsActive
    FROM shippers
    WHERE ShipperID = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $shipperID);
$stmt->execute();
$result = $stmt->get_result();
$shipper = $result->fetch_assoc();
$stmt->close();

if (!$shipper) {
    die('Không tìm thấy nhà vận chuyển.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipperName = trim($_POST['shipper_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $formIsActive = $isActive;

    if ($shipperName === '') {
        $error = 'Tên nhà vận chuyển không được để trống.';
    } else {
        $updateSql = "
            UPDATE shippers
            SET
                ShipperName = ?,
                Phone = ?,
                IsActive = ?
            WHERE ShipperID = ?
        ";

        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt === false) {
            $error = 'Không thể chuẩn bị truy vấn cập nhật nhà vận chuyển.';
        } else {
            $updateStmt->bind_param('ssii', $shipperName, $phone, $isActive, $shipperID);

            if ($updateStmt->execute()) {
                $updateStmt->close();
                $conn->close();
                header('Location: /admin/shippers/');
                exit;
            }

            $error = 'Không thể cập nhật nhà vận chuyển.';
            $updateStmt->close();
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Sửa Nhà vận chuyển</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label for="shipperID" class="form-label">Mã nhà vận chuyển</label>
            <input
                type="text"
                class="form-control"
                id="shipperID"
                value="<?= htmlspecialchars((string) $shipper['ShipperID']) ?>"
                disabled
            >
        </div>

        <div class="mb-3">
            <label for="shipperName" class="form-label">Tên nhà vận chuyển</label>
            <input
                type="text"
                class="form-control"
                id="shipperName"
                name="shipper_name"
                value="<?= htmlspecialchars($_POST['shipper_name'] ?? $shipper['ShipperName']) ?>"
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
                value="<?= htmlspecialchars($_POST['phone'] ?? $shipper['Phone'] ?? '') ?>"
            >
        </div>

        <div class="form-check mb-3">
            <input
                type="checkbox"
                class="form-check-input"
                id="isActive"
                name="is_active"
                value="1"
                <?= (
                    isset($_POST['is_active'])
                    || (isset($shipper['IsActive']) && (int) $shipper['IsActive'] === 1)
                    || (isset($formIsActive) && (int) $formIsActive === 1)
                ) ? 'checked' : '' ?>
            >
            <label class="form-check-label" for="isActive">
                Đang hoạt động
            </label>
        </div>

        <button type="submit" class="btn btn-warning">
            Cập nhật
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
