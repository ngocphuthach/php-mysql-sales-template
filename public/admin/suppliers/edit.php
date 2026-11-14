<?php

$pageTitle = 'Sửa Nhà cung cấp';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$supplierID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$supplierID || $supplierID < 1) {
    http_response_code(400);
    exit('Mã nhà cung cấp không hợp lệ.');
}

$sql = "
    SELECT SupplierID, SupplierName, ContactName, Phone, Email, Address,
           City, PostalCode, Country, IsActive
    FROM suppliers
    WHERE SupplierID = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $supplierID);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    http_response_code(404);
    exit('Không tìm thấy nhà cung cấp.');
}

$fields = [
    'supplier_name' => $supplier['SupplierName'],
    'contact_name' => $supplier['ContactName'] ?? '',
    'phone' => $supplier['Phone'] ?? '',
    'email' => $supplier['Email'] ?? '',
    'address' => $supplier['Address'] ?? '',
    'city' => $supplier['City'] ?? '',
    'postal_code' => $supplier['PostalCode'] ?? '',
    'country' => $supplier['Country'] ?? '',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $default) {
        $fields[$key] = trim($_POST[$key] ?? '');
    }
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($fields['supplier_name'] === '') {
        $error = 'Tên nhà cung cấp không được để trống.';
    } elseif ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không hợp lệ.';
    } else {
        $updateSql = "
            UPDATE suppliers
            SET SupplierName = ?, ContactName = ?, Phone = ?, Email = ?,
                Address = ?, City = ?, PostalCode = ?, Country = ?, IsActive = ?
            WHERE SupplierID = ?
        ";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param(
            'ssssssssii',
            $fields['supplier_name'],
            $fields['contact_name'],
            $fields['phone'],
            $fields['email'],
            $fields['address'],
            $fields['city'],
            $fields['postal_code'],
            $fields['country'],
            $isActive,
            $supplierID
        );

        if (!$updateStmt->execute()) {
            throw new RuntimeException('Không thể cập nhật nhà cung cấp.');
        }

        $updateStmt->close();
        $conn->close();
        header('Location: /admin/suppliers/');
        exit;
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-4">
    <h1 class="h2 mb-4">Sửa nhà cung cấp #<?= (int) $supplierID ?></h1>
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
        <div class="form-check mb-3">
            <input
                type="checkbox"
                class="form-check-input"
                id="is_active"
                name="is_active"
                value="1"
                <?= ($_SERVER['REQUEST_METHOD'] === 'POST'
                    ? isset($_POST['is_active'])
                    : (int) $supplier['IsActive'] === 1) ? 'checked' : '' ?>
            >
            <label for="is_active" class="form-check-label">Đang hoạt động</label>
        </div>
        <button type="submit" class="btn btn-primary">Cập nhật</button>
        <a href="/admin/suppliers/" class="btn btn-secondary">Hủy</a>
    </form>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>
