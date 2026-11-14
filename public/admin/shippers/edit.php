<?php

$pageTitle = 'Sửa Nhà vận chuyển';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$error = '';
$shipperID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($shipperID <= 0) {
    die('Mã nhà vận chuyển không hợp lệ.');
}

$sql = "
    SELECT
        ShipperID,
        ShipperName,
        Phone,
        Email,
        Website,
        DefaultFee,
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
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $feeInput = trim($_POST['default_fee'] ?? '0');
    $validatedFee = filter_var($feeInput, FILTER_VALIDATE_FLOAT);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($shipperName === '') {
        $error = 'Tên nhà vận chuyển không được để trống.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không hợp lệ.';
    } elseif ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        $error = 'Địa chỉ website không hợp lệ.';
    } elseif ($validatedFee === false || !is_finite((float) $validatedFee) || $validatedFee < 0) {
        $error = 'Phí vận chuyển phải là số không âm.';
    } else {
        $defaultFee = (float) $validatedFee;
        $updateSql = "
            UPDATE shippers
            SET
                ShipperName = ?,
                Phone = ?,
                Email = ?,
                Website = ?,
                DefaultFee = ?,
                IsActive = ?
            WHERE ShipperID = ?
        ";

        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt === false) {
            $error = 'Không thể chuẩn bị truy vấn cập nhật nhà vận chuyển.';
        } else {
            $updateStmt->bind_param(
                'ssssdii',
                $shipperName,
                $phone,
                $email,
                $website,
                $defaultFee,
                $isActive,
                $shipperID
            );

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

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input
                type="email"
                class="form-control"
                id="email"
                name="email"
                value="<?= htmlspecialchars($_POST['email'] ?? $shipper['Email'] ?? '') ?>"
            >
        </div>

        <div class="row">
            <div class="col-md-8 mb-3">
                <label for="website" class="form-label">Website</label>
                <input
                    type="url"
                    class="form-control"
                    id="website"
                    name="website"
                    value="<?= htmlspecialchars($_POST['website'] ?? $shipper['Website'] ?? '') ?>"
                >
            </div>
            <div class="col-md-4 mb-3">
                <label for="defaultFee" class="form-label">Phí mặc định (VNĐ)</label>
                <input
                    type="number"
                    class="form-control"
                    id="defaultFee"
                    name="default_fee"
                    min="0"
                    step="1000"
                    value="<?= htmlspecialchars($_POST['default_fee'] ?? $shipper['DefaultFee'] ?? '0') ?>"
                    required
                >
            </div>
        </div>

        <div class="form-check mb-3">
            <input
                type="checkbox"
                class="form-check-input"
                id="isActive"
                name="is_active"
                value="1"
                <?= ($_SERVER['REQUEST_METHOD'] === 'POST'
                    ? isset($_POST['is_active'])
                    : (int) $shipper['IsActive'] === 1) ? 'checked' : '' ?>
            >
            <label class="form-check-label" for="isActive">
                Đang hợp tác
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
