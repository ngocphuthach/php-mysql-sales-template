<?php

$pageTitle = 'Chi tiết Khách hàng';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$customerID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$customerID || $customerID < 1) {
    http_response_code(400);
    exit('Mã khách hàng không hợp lệ.');
}

$stmt = $conn->prepare("
    SELECT CustomerID, CustomerName, ContactName, Email, Phone,
           Address, City, PostalCode, Country, Gender, BirthDate,
           LoyaltyPoint, CreatedAt
    FROM customers
    WHERE CustomerID = ?
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    http_response_code(404);
    exit('Không tìm thấy khách hàng.');
}

$ordersStmt = $conn->prepare("
    SELECT OrderID, OrderDate, TotalAmount, Status
    FROM orders
    WHERE CustomerID = ?
    ORDER BY OrderDate DESC, OrderID DESC
");
$ordersStmt->bind_param('i', $customerID);
$ordersStmt->execute();
$orders = $ordersStmt->get_result();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-4">
    <h1 class="h2 mb-3"><?= htmlspecialchars($customer['CustomerName']) ?></h1>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Mã:</strong> <?= (int) $customer['CustomerID'] ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($customer['Email'] ?? '') ?></p>
                    <p><strong>Điện thoại:</strong> <?= htmlspecialchars($customer['Phone'] ?? '') ?></p>
                    <p><strong>Điểm:</strong> <?= (int) $customer['LoyaltyPoint'] ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Người liên hệ:</strong> <?= htmlspecialchars($customer['ContactName'] ?? '') ?></p>
                    <p><strong>Địa chỉ:</strong> <?= htmlspecialchars($customer['Address'] ?? '') ?></p>
                    <p><strong>Thành phố/Quốc gia:</strong>
                        <?= htmlspecialchars(trim(($customer['City'] ?? '') . ', ' . ($customer['Country'] ?? ''), ', ')) ?>
                    </p>
                    <p><strong>Ngày tham gia:</strong> <?= htmlspecialchars($customer['CreatedAt'] ?? '') ?></p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h4">Lịch sử đơn hàng</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr><th>Mã đơn</th><th>Ngày</th><th>Tổng tiền</th><th>Trạng thái</th><th></th></tr>
            </thead>
            <tbody>
                <?php if ($orders->num_rows > 0): ?>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= (int) $order['OrderID'] ?></td>
                            <td><?= htmlspecialchars($order['OrderDate']) ?></td>
                            <td><?= number_format((float) $order['TotalAmount'], 0, ',', '.') ?> đ</td>
                            <td><?= htmlspecialchars($order['Status']) ?></td>
                            <td>
                                <a href="/admin/orders/detail.php?id=<?= (int) $order['OrderID'] ?>">Xem</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted">Khách hàng chưa có đơn hàng.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <a href="/admin/customers/" class="btn btn-secondary">Quay lại</a>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$ordersStmt->close();
$conn->close();
?>
