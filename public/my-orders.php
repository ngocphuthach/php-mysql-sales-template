<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Lịch sử đơn hàng';
$customerID = (int) ($_SESSION['customer_id'] ?? 0);
if ($customerID <= 0) {
    header('Location: /login.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT OrderID, OrderDate, TotalAmount, Status, PaymentMethod
    FROM orders
    WHERE CustomerID = ?
    ORDER BY OrderDate DESC, OrderID DESC
    LIMIT 100
");
$stmt->bind_param('i', $customerID);
$stmt->execute();
$orders = $stmt->get_result();

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container py-4">
    <h1 class="h2 mb-4">Lịch sử đơn hàng</h1>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr><th>Mã đơn</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Thanh toán</th><th>Trạng thái</th></tr>
            </thead>
            <tbody>
                <?php if ($orders->num_rows > 0): ?>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="/order-success.php?id=<?= (int) $order['OrderID'] ?>">
                                    #<?= (int) $order['OrderID'] ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($order['OrderDate']) ?></td>
                            <td><?= number_format((float) $order['TotalAmount'], 0, ',', '.') ?> đ</td>
                            <td><?= htmlspecialchars($order['PaymentMethod'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['Status']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted">Bạn chưa có đơn hàng.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted">Hiển thị tối đa 100 đơn hàng gần nhất.</p>
</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$stmt->close();
$conn->close();
?>
