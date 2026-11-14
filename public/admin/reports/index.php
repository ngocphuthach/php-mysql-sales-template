<?php

$pageTitle = 'Báo cáo bán hàng';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$today = date('Y-m-d');
$firstOfMonth = date('Y-m-01');
$startDate = $_GET['start_date'] ?? $firstOfMonth;
$endDate = $_GET['end_date'] ?? $today;
$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
$startValid = preg_match($datePattern, $startDate)
    && DateTime::createFromFormat('!Y-m-d', $startDate)?->format('Y-m-d') === $startDate;
$endValid = preg_match($datePattern, $endDate)
    && DateTime::createFromFormat('!Y-m-d', $endDate)?->format('Y-m-d') === $endDate;
$error = '';

if (!$startValid || !$endValid || $startDate > $endDate) {
    $error = 'Khoảng thời gian không hợp lệ.';
    $startDate = $firstOfMonth;
    $endDate = $today;
}

$summaryStmt = $conn->prepare("
    SELECT
        COUNT(*) AS OrderCount,
        SUM(Status = 'Completed') AS CompletedCount,
        SUM(Status = 'Pending') AS PendingCount,
        COALESCE(SUM(CASE WHEN Status <> 'Cancelled' THEN TotalAmount ELSE 0 END), 0) AS Revenue
    FROM orders
    WHERE DATE(OrderDate) BETWEEN ? AND ?
");
$summaryStmt->bind_param('ss', $startDate, $endDate);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$dailyStmt = $conn->prepare("
    SELECT
        DATE(OrderDate) AS SaleDate,
        COUNT(*) AS OrderCount,
        COALESCE(SUM(CASE WHEN Status <> 'Cancelled' THEN TotalAmount ELSE 0 END), 0) AS Revenue
    FROM orders
    WHERE DATE(OrderDate) BETWEEN ? AND ?
    GROUP BY DATE(OrderDate)
    ORDER BY SaleDate DESC
");
$dailyStmt->bind_param('ss', $startDate, $endDate);
$dailyStmt->execute();
$dailyResults = $dailyStmt->get_result();

$topProductsStmt = $conn->prepare("
    SELECT
        p.ProductName,
        SUM(od.Quantity) AS UnitsSold,
        SUM(od.Quantity * od.UnitPrice * (1 - od.Discount / 100)) AS Revenue
    FROM orderdetail od
    INNER JOIN orders o ON o.OrderID = od.OrderID
    INNER JOIN products p ON p.ProductID = od.ProductID
    WHERE DATE(o.OrderDate) BETWEEN ? AND ?
      AND o.Status <> 'Cancelled'
    GROUP BY p.ProductID, p.ProductName
    ORDER BY UnitsSold DESC, Revenue DESC
    LIMIT 10
");
$topProductsStmt->bind_param('ss', $startDate, $endDate);
$topProductsStmt->execute();
$topProducts = $topProductsStmt->get_result();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-4">
    <h1 class="h2 mb-3">Báo cáo bán hàng</h1>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="get" class="row g-3 align-items-end mb-4">
        <div class="col-sm-4 col-md-3">
            <label for="start_date" class="form-label">Từ ngày</label>
            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
        </div>
        <div class="col-sm-4 col-md-3">
            <label for="end_date" class="form-label">Đến ngày</label>
            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
        </div>
        <div class="col-sm-4 col-md-2">
            <button type="submit" class="btn btn-primary">Xem báo cáo</button>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 text-muted">Tổng đơn</h2>
                <p class="display-6 mb-0"><?= (int) $summary['OrderCount'] ?></p>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 text-muted">Hoàn thành</h2>
                <p class="display-6 mb-0"><?= (int) $summary['CompletedCount'] ?></p>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 text-muted">Chờ xác nhận</h2>
                <p class="display-6 mb-0"><?= (int) $summary['PendingCount'] ?></p>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body">
                <h2 class="h6 text-muted">Doanh thu (không tính đơn hủy)</h2>
                <p class="h3 mb-0"><?= number_format((float) $summary['Revenue'], 0, ',', '.') ?> đ</p>
            </div></div>
        </div>
    </div>

    <h2 class="h4">Theo ngày</h2>
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-striped">
            <thead class="table-dark"><tr><th>Ngày</th><th>Số đơn</th><th>Doanh thu</th></tr></thead>
            <tbody>
                <?php if ($dailyResults->num_rows > 0): ?>
                    <?php while ($day = $dailyResults->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($day['SaleDate']) ?></td>
                            <td><?= (int) $day['OrderCount'] ?></td>
                            <td><?= number_format((float) $day['Revenue'], 0, ',', '.') ?> đ</td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-muted">Không có dữ liệu trong khoảng thời gian này.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 class="h4">Sản phẩm bán chạy</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark"><tr><th>Sản phẩm</th><th>Số lượng</th><th>Doanh thu sản phẩm</th></tr></thead>
            <tbody>
                <?php if ($topProducts->num_rows > 0): ?>
                    <?php while ($product = $topProducts->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['ProductName']) ?></td>
                            <td><?= (int) $product['UnitsSold'] ?></td>
                            <td><?= number_format((float) $product['Revenue'], 0, ',', '.') ?> đ</td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-muted">Không có sản phẩm bán ra trong thời gian này.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$dailyStmt->close();
$topProductsStmt->close();
$conn->close();
?>
