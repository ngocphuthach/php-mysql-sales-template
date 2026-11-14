<?php

$pageTitle = 'Thêm Nhà vận chuyển';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipperName = trim($_POST['shipper_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $feeInput = trim($_POST['default_fee'] ?? '0');
    $validatedFee = filter_var($feeInput, FILTER_VALIDATE_FLOAT);

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
        $sql = "
            INSERT INTO shippers
                (ShipperName, Phone, Email, Website, DefaultFee, IsActive)
            VALUES (?, ?, ?, ?, ?, 1)
        ";

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $error = 'Không thể chuẩn bị truy vấn thêm nhà vận chuyển.';
        } else {
            $stmt->bind_param(
                'ssssd',
                $shipperName,
                $phone,
                $email,
                $website,
                $defaultFee
            );

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

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input
                type="email"
                class="form-control"
                id="email"
                name="email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
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
                    value="<?= htmlspecialchars($_POST['website'] ?? '') ?>"
                    placeholder="https://example.com"
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
                    value="<?= htmlspecialchars($_POST['default_fee'] ?? '0') ?>"
                    required
                >
            </div>
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
