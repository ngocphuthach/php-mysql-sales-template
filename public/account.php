<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Tài khoản của tôi';
$customerID = (int) ($_SESSION['customer_id'] ?? 0);
if ($customerID <= 0) {
    header('Location: /login.php');
    exit;
}

$error = '';
$success = '';
$stmt = $conn->prepare("
    SELECT CustomerName, Email, Phone, Address, City, PostalCode, Country,
           Gender, BirthDate, LoyaltyPoint
    FROM customers
    WHERE CustomerID = ?
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');

    if ($customerName === '') {
        $error = 'Họ và tên không được để trống.';
    } elseif (!in_array($gender, ['', 'Nam', 'Nữ', 'Khác'], true)) {
        $error = 'Giới tính không hợp lệ.';
    } elseif (
        $birthDate !== ''
        && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)
            || strtotime($birthDate) === false
            || date('Y-m-d', strtotime($birthDate)) !== $birthDate)
    ) {
        $error = 'Ngày sinh không hợp lệ.';
    } else {
        $update = $conn->prepare("
            UPDATE customers
            SET CustomerName = ?, Phone = ?, Address = ?, City = ?,
                PostalCode = ?, Country = ?, Gender = ?, BirthDate = NULLIF(?, '')
            WHERE CustomerID = ?
        ");
        $update->bind_param(
            'ssssssssi',
            $customerName,
            $phone,
            $address,
            $city,
            $postalCode,
            $country,
            $gender,
            $birthDate,
            $customerID
        );
        $update->execute();
        $update->close();

        $_SESSION['customer_name'] = $customerName;
        $success = 'Thông tin tài khoản đã được cập nhật.';
        $customer = array_merge($customer, [
            'CustomerName' => $customerName,
            'Phone' => $phone,
            'Address' => $address,
            'City' => $city,
            'PostalCode' => $postalCode,
            'Country' => $country,
            'Gender' => $gender,
            'BirthDate' => $birthDate,
        ]);
    }
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container py-4">
    <h1 class="h2 mb-4">Tài khoản của tôi</h1>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <form method="post" class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Họ và tên</label>
                        <input
                            class="form-control"
                            id="customer_name"
                            name="customer_name"
                            value="<?= htmlspecialchars($_POST['customer_name'] ?? $customer['CustomerName']) ?>"
                            required
                        >
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input class="form-control" id="email" value="<?= htmlspecialchars($customer['Email']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Điện thoại</label>
                        <input class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $customer['Phone'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Địa chỉ</label>
                        <input class="form-control" id="address" name="address" value="<?= htmlspecialchars($_POST['address'] ?? $customer['Address'] ?? '') ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="city" class="form-label">Thành phố</label>
                            <input class="form-control" id="city" name="city" value="<?= htmlspecialchars($_POST['city'] ?? $customer['City'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="postal_code" class="form-label">Mã bưu chính</label>
                            <input class="form-control" id="postal_code" name="postal_code" value="<?= htmlspecialchars($_POST['postal_code'] ?? $customer['PostalCode'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="country" class="form-label">Quốc gia</label>
                            <input class="form-control" id="country" name="country" value="<?= htmlspecialchars($_POST['country'] ?? $customer['Country'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Giới tính</label>
                            <select class="form-select" id="gender" name="gender">
                                <?php foreach (['' => 'Chưa chọn', 'Nam' => 'Nam', 'Nữ' => 'Nữ', 'Khác' => 'Khác'] as $value => $label): ?>
                                    <option
                                        value="<?= htmlspecialchars($value) ?>"
                                        <?= ($_POST['gender'] ?? $customer['Gender'] ?? '') === $value ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="birth_date" class="form-label">Ngày sinh</label>
                            <input
                                type="date"
                                class="form-control"
                                id="birth_date"
                                name="birth_date"
                                value="<?= htmlspecialchars($_POST['birth_date'] ?? $customer['BirthDate'] ?? '') ?>"
                            >
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Thành viên</h2>
                    <p class="display-6"><?= (int) $customer['LoyaltyPoint'] ?></p>
                    <p class="text-muted mb-0">Điểm tích lũy hiện tại</p>
                    <a href="/my-orders.php" class="btn btn-outline-primary mt-3">Lịch sử đơn hàng</a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$conn->close();
?>
