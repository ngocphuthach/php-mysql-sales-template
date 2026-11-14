<?php

$pageTitle = 'Chi tiết đơn hàng';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['staff', 'admin']);
require_once '/var/www/src/config/database.php';

$orderID = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($orderID <= 0) {
    header('Location: /admin/orders/');
    exit;
}

$sqlOrder = "
    SELECT
        o.OrderID,
        o.OrderDate,
        o.ShippedDate,
        o.TotalAmount,
        o.Status,
        o.ReceiverName,
        o.ShipAddress,
        o.ShipCity,
        o.ShipPhone,
        o.Freight,
        o.PaymentMethod,
        sh.ShipperName,
        c.CustomerName,
        c.ContactName,
        c.Phone,
        c.Address,
        c.City,
        c.PostalCode,
        c.Country
    FROM
        orders AS o
        INNER JOIN customers AS c ON o.CustomerID = c.CustomerID
        LEFT JOIN shippers AS sh ON o.ShipperID = sh.ShipperID
    WHERE
        o.OrderID = ?
";

$stmtOrder = $conn->prepare($sqlOrder);
$stmtOrder->bind_param('i', $orderID);
$stmtOrder->execute();

$orderResult = $stmtOrder->get_result();
$order = $orderResult->fetch_assoc();

$orderResult->free();
$stmtOrder->close();

if (!$order) {
    header('Location: /admin/orders/');
    exit;
}

$allowedTransitions = [
    'Pending' => [
        'Confirmed',
        'Cancelled'
    ],
    'Confirmed' => [
        'Shipping',
        'Cancelled'
    ],
    'Shipping' => [
        'Completed',
        'Cancelled'
    ],
    'Completed' => [],
    'Cancelled' => []
];

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_status'])) {

    $newStatus = $_POST['status'] ?? '';

    try {

        $conn->begin_transaction();

        $sqlLockOrder = "
            SELECT Status, CustomerID, TotalAmount, Freight
            FROM orders
            WHERE OrderID = ?
            FOR UPDATE
        ";

        $stmtLockOrder = $conn->prepare($sqlLockOrder);
        $stmtLockOrder->bind_param('i', $orderID);
        $stmtLockOrder->execute();

        $lockResult = $stmtLockOrder->get_result();
        $lockedOrder = $lockResult->fetch_assoc();

        $lockResult->free();
        $stmtLockOrder->close();

        if (!$lockedOrder) {
            throw new Exception('Không tìm thấy đơn hàng.');
        }

        $currentStatus = $lockedOrder['Status'];
        $allowedStatuses = $allowedTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new Exception('Không thể chuyển sang trạng thái đã chọn.');
        }

        if ($newStatus === 'Cancelled') {
            $sqlItems = "
                SELECT
                    ProductID,
                    Quantity
                FROM orderdetail
                WHERE OrderID = ?
            ";

            $stmtItems = $conn->prepare($sqlItems);
            $stmtItems->bind_param('i', $orderID);
            $stmtItems->execute();

            $itemsResult = $stmtItems->get_result();
            $orderItems = [];

            while ($item = $itemsResult->fetch_assoc()) {
                $orderItems[] = $item;
            }

            $itemsResult->free();
            $stmtItems->close();

            $sqlRestoreStock = "
                UPDATE products
                SET StockQuantity = StockQuantity + ?
                WHERE ProductID = ?
            ";

            $stmtRestoreStock = $conn->prepare($sqlRestoreStock);

            foreach ($orderItems as $item) {
                $quantity = (int) $item['Quantity'];
                $productID = (int) $item['ProductID'];

                $stmtRestoreStock->bind_param('ii', $quantity, $productID);
                $stmtRestoreStock->execute();

                if ($stmtRestoreStock->affected_rows !== 1) {
                    throw new Exception('Không thể hoàn lại tồn kho.');
                }
            }

            $stmtRestoreStock->close();
        }

        if ($newStatus === 'Completed') {
            $customerID = (int) $lockedOrder['CustomerID'];
            $orderTotal = (float) $lockedOrder['TotalAmount']
                - (float) $lockedOrder['Freight'];
            $stmtAwardPoints = $conn->prepare("
                UPDATE customers
                SET LoyaltyPoint = LoyaltyPoint + FLOOR(? / 10000)
                WHERE CustomerID = ?
            ");
            $stmtAwardPoints->bind_param('di', $orderTotal, $customerID);
            $stmtAwardPoints->execute();
            $stmtAwardPoints->close();
        }

        $sqlUpdate = "
            UPDATE orders
            SET
                Status = ?,
                EmployeeID = COALESCE(EmployeeID, ?),
                ShippedDate = CASE
                    WHEN ? = 'Shipping' THEN CURRENT_TIMESTAMP
                    ELSE ShippedDate
                END
            WHERE OrderID = ?
        ";

        $stmtUpdate = $conn->prepare($sqlUpdate);
        $employeeID = (int) $_SESSION['employee_id'];
        $stmtUpdate->bind_param('sisi', $newStatus, $employeeID, $newStatus, $orderID);
        $stmtUpdate->execute();

        if ($stmtUpdate->affected_rows !== 1) {
            throw new Exception('Không thể cập nhật trạng thái đơn hàng.');
        }

        $stmtUpdate->close();

        $conn->commit();

        header('Location: /admin/orders/detail.php?id=' . $orderID);
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        $errorMessage = $e->getMessage();
    }
}

$stmtOrder = $conn->prepare($sqlOrder);
$stmtOrder->bind_param('i', $orderID);
$stmtOrder->execute();

$orderResult = $stmtOrder->get_result();
$order = $orderResult->fetch_assoc();

$orderResult->free();
$stmtOrder->close();

$sqlDetail = "
    SELECT
        od.Quantity,
        od.UnitPrice,
        od.Discount,
        p.ProductCode,
        p.ProductName
    FROM
        orderdetail AS od,
        products AS p
    WHERE
        od.ProductID = p.ProductID
        AND od.OrderID = ?
    ORDER BY
        od.OrderDetailID
";

$stmtDetail = $conn->prepare($sqlDetail);
$stmtDetail->bind_param('i', $orderID);
$stmtDetail->execute();

$detailResult = $stmtDetail->get_result();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>
            Chi tiết đơn hàng #<?= (int) $order['OrderID'] ?>
        </h2>

        <a
            href="/admin/orders/"
            class="btn btn-outline-secondary"
        >
            Quay lại
        </a>
    </div>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">

        <div class="col-md-6">

            <div class="card h-100">

                <div class="card-header">
                    <strong>Thông tin đơn hàng</strong>
                </div>

                <div class="card-body">

                    <p>
                        <strong>Mã đơn:</strong>
                        #<?= (int) $order['OrderID'] ?>
                    </p>

                    <p>
                        <strong>Nhà vận chuyển:</strong>
                        <?= htmlspecialchars($order['ShipperName'] ?? 'Chưa chỉ định') ?>
                    </p>

                    <p>
                        <strong>Phí vận chuyển:</strong>
                        <?= number_format((float) $order['Freight'], 0, ',', '.') ?> đ
                    </p>

                    <p>
                        <strong>Thanh toán:</strong>
                        <?= htmlspecialchars($order['PaymentMethod'] ?? '') ?>
                    </p>

                    <p>
                        <strong>Người nhận:</strong>
                        <?= htmlspecialchars($order['ReceiverName'] ?? '') ?>
                    </p>

                    <p>
                        <strong>Địa chỉ giao:</strong>
                        <?= htmlspecialchars(trim(
                            ($order['ShipAddress'] ?? '')
                            . ', '
                            . ($order['ShipCity'] ?? ''),
                            ', '
                        )) ?>
                    </p>

                    <p>
                        <strong>Điện thoại nhận:</strong>
                        <?= htmlspecialchars($order['ShipPhone'] ?? '') ?>
                    </p>

                    <p>
                        <strong>Ngày đặt:</strong>
                        <?= htmlspecialchars($order['OrderDate']) ?>
                    </p>

                    <?php if (!empty($order['ShippedDate'])): ?>
                        <p>
                            <strong>Ngày giao cho vận chuyển:</strong>
                            <?= htmlspecialchars($order['ShippedDate']) ?>
                        </p>
                    <?php endif; ?>

                    <p>
                        <strong>Trạng thái:</strong>
                        <?= htmlspecialchars($order['Status']) ?>
                    </p>

                    <p>
                        <strong>Tổng tiền:</strong>
                        <?= number_format(
                            (float) $order['TotalAmount'],
                            0,
                            ',',
                            '.'
                        ) ?> đ
                    </p>

                    <?php
                    $nextStatuses = $allowedTransitions[$order['Status']] ?? [];
                    ?>

                    <?php if (!empty($nextStatuses)): ?>

                        <hr>

                        <form method="post">
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    Cập nhật trạng thái
                                </label>

                                <select
                                    name="status"
                                    id="status"
                                    class="form-select"
                                    required
                                >
                                    <option value="">
                                        -- Chọn trạng thái --
                                    </option>

                                    <?php foreach ($nextStatuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                            </div>

                            <button
                                type="submit"
                                name="update_status"
                                class="btn btn-primary"
                            >
                                Cập nhật
                            </button>
                        </form>

                    <?php else: ?>

                        <div class="alert alert-secondary mb-0">
                            Đơn hàng đã ở trạng thái kết thúc.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <div class="col-md-6">

            <div class="card h-100">

                <div class="card-header">
                    <strong>Thông tin khách hàng</strong>
                </div>

                <div class="card-body">

                    <p>
                        <strong>Khách hàng:</strong>
                        <?= htmlspecialchars($order['CustomerName']) ?>
                    </p>

                    <p>
                        <strong>Người liên hệ:</strong>
                        <?= htmlspecialchars($order['ContactName'] ?? '') ?>
                    </p>

                    <p>
                        <strong>Điện thoại:</strong>
                        <?= htmlspecialchars($order['Phone'] ?? '') ?>
                    </p>

                    <p>
                        <strong>Địa chỉ:</strong>
                        <?= htmlspecialchars($order['Address'] ?? '') ?>
                    </p>

                    <p>
                        <strong>Thành phố:</strong>
                        <?= htmlspecialchars($order['City'] ?? '') ?>
                    </p>

                    <p>
                        <strong>Mã bưu chính:</strong>
                        <?= htmlspecialchars($order['PostalCode'] ?? '') ?>
                    </p>

                    <p class="mb-0">
                        <strong>Quốc gia:</strong>
                        <?= htmlspecialchars($order['Country'] ?? '') ?>
                    </p>

                </div>

            </div>

        </div>

    </div>

    <div class="card">

        <div class="card-header">
            <strong>Sản phẩm trong đơn hàng</strong>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th>Mã SP</th>
                            <th>Tên sản phẩm</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-end">Số lượng</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while ($item = $detailResult->fetch_assoc()): ?>

                        <?php
                        $sellingPrice = (float) $item['UnitPrice']
                            * (1 - (float) $item['Discount'] / 100);
                        $subtotal = $sellingPrice * (int) $item['Quantity'];
                        ?>

                        <tr>
                            <td>
                                <?= htmlspecialchars($item['ProductCode']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item['ProductName']) ?>
                            </td>

                            <td class="text-end">
                                <?= number_format(
                                    $sellingPrice,
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                                <?php if ((float) $item['Discount'] > 0): ?>
                                    <br><small class="text-muted">
                                        Giảm <?= rtrim(rtrim(number_format((float) $item['Discount'], 2, '.', ''), '0'), '.') ?>%
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <?= (int) $item['Quantity'] ?>
                            </td>

                            <td class="text-end">
                                <?= number_format($subtotal, 0, ',', '.') ?> đ
                            </td>
                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Tổng cộng</th>
                            <th class="text-end">
                                <?= number_format(
                                    (float) $order['TotalAmount'],
                                    0,
                                    ',',
                                    '.'
                                ) ?> đ
                            </th>
                        </tr>
                    </tfoot>

                </table>

            </div>

        </div>

    </div>

</div>

<?php

$detailResult->free();
$stmtDetail->close();

require_once '/var/www/src/includes/admin/footer.php';

$conn->close();
