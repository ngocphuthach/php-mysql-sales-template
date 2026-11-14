<?php

$pageTitle = 'Thêm Nhà cung cấp';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$fields = [
    'supplier_name' => '',
    'contact_name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'city' => '',
    'postal_code' => '',
    'country' => '',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $default) {
        $fields[$key] = trim($_POST[$key] ?? $default);
    }

    if ($fields['supplier_name'] === '') {
        $error = 'Tên nhà cung cấp không được để trống.';
    } elseif ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không hợp lệ.';
    } else {
        $sql = "
            INSERT INTO suppliers
                (SupplierName, ContactName, Phone, Email, Address, City, PostalCode, Country)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('Không thể chuẩn bị truy vấn thêm nhà cung cấp.');
        }

        $stmt->bind_param(
            'ssssssss',
            $fields['supplier_name'],
            $fields['contact_name'],
            $fields['phone'],
            $fields['email'],
            $fields['address'],
            $fields['city'],
            $fields['postal_code'],
            $fields['country']
        );

        if (!$stmt->execute()) {
            throw new RuntimeException('Không thể thêm nhà cung cấp.');
        }

        $stmt->close();
        $conn->close();
        header('Location: /admin/suppliers/');
        exit;
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-4">
    <h1 class="h2 mb-4">Thêm nhà cung cấp</h1>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="row">
            <?php
            $labels = [
                'supplier_name' => 'Tên nhà cung cấp',
                'contact_name' => 'Người liên hệ',
                'phone' => 'Số điện thoại',
                'email' => 'Email',
                'address' => 'Địa chỉ',
                'city' => 'Thành phố',
                'postal_code' => 'Mã bưu chính',
                'country' => 'Quốc gia',
            ];
            foreach ($labels as $key => $label):
            ?>
                <div class="col-md-6 mb-3">
                    <label for="<?= htmlspecialchars($key) ?>" class="form-label">
                        <?= htmlspecialchars($label) ?>
                    </label>
                    <input
                        type="<?= $key === 'email' ? 'email' : 'text' ?>"
                        class="form-control"
                        id="<?= htmlspecialchars($key) ?>"
                        name="<?= htmlspecialchars($key) ?>"
                        value="<?= htmlspecialchars($fields[$key]) ?>"
                        <?= $key === 'supplier_name' ? 'required' : '' ?>
                    >
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary">Lưu</button>
        <a href="/admin/suppliers/" class="btn btn-secondary">Hủy</a>
    </form>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>
