<?php
$pageTitle = 'Quản trị hệ thống';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['staff', 'admin']);
require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
require_once '/var/www/src/config/database.php';

$summary = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM products WHERE IsActive = 1) AS ActiveProducts,
        (SELECT COUNT(*) FROM customers) AS Customers,
        (SELECT COUNT(*) FROM orders WHERE Status = 'Pending') AS PendingOrders,
        (SELECT COALESCE(SUM(TotalAmount), 0)
         FROM orders
         WHERE OrderDate >= CURRENT_DATE - INTERVAL 29 DAY
           AND Status <> 'Cancelled') AS RecentRevenue
")->fetch_assoc();

$lowStockResult = $conn->query("
    SELECT ProductID, ProductName, StockQuantity
    FROM products
    WHERE IsActive = 1 AND StockQuantity <= 5
    ORDER BY StockQuantity, ProductName
    LIMIT 8
");
?>

<main class="container py-5">

    <div class="admin-dashboard-intro">
        <div>
            <span class="storefront-eyebrow">TỔNG QUAN CỬA HÀNG</span>
            <h1>Chào mừng trở lại, <?= htmlspecialchars($_SESSION['employee_name'] ?? '') ?></h1>
            <p class="text-muted">
                Theo dõi hoạt động Maison Parfum và truy cập nhanh các công việc cần xử lý.
            </p>
        </div>
        <a href="/admin/orders/" class="btn btn-primary px-4">
            Xem đơn hàng
        </a>
    </div>

    <div class="row g-3 my-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card admin-stat-card h-100">
                <div class="card-body">
                    <span class="admin-stat-icon" aria-hidden="true">SP</span>
                    <h2 class="stat-label">Sản phẩm đang bán</h2>
                    <p class="stat-value"><?= (int) $summary['ActiveProducts'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card admin-stat-card h-100">
                <div class="card-body">
                    <span class="admin-stat-icon" aria-hidden="true">KH</span>
                    <h2 class="stat-label">Khách hàng</h2>
                    <p class="stat-value"><?= (int) $summary['Customers'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card admin-stat-card h-100">
                <div class="card-body">
                    <span class="admin-stat-icon" aria-hidden="true">ĐH</span>
                    <h2 class="stat-label">Đơn chờ xác nhận</h2>
                    <p class="stat-value"><?= (int) $summary['PendingOrders'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card admin-stat-card h-100">
                <div class="card-body">
                    <span class="admin-stat-icon" aria-hidden="true">₫</span>
                    <h2 class="stat-label">Doanh thu 30 ngày</h2>
                    <p class="stat-value">
                        <?= number_format((float) $summary['RecentRevenue'], 0, ',', '.') ?> đ
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if (($_SESSION['employee_role'] ?? '') === 'admin'): ?>
        <section class="mt-5">
            <div class="admin-section-heading">
                <div>
                    <span class="storefront-eyebrow">TRUY CẬP NHANH</span>
                    <h2 class="mt-1">Quản lý cửa hàng</h2>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <a class="admin-shortcut" href="/admin/products/">
                        <span class="admin-stat-icon" aria-hidden="true">SP</span>
                        <span><strong>Sản phẩm</strong><span>Thông tin, giá và tồn kho</span></span>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a class="admin-shortcut" href="/admin/orders/">
                        <span class="admin-stat-icon" aria-hidden="true">ĐH</span>
                        <span><strong>Đơn hàng</strong><span>Xử lý và theo dõi đơn</span></span>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a class="admin-shortcut" href="/admin/customers/">
                        <span class="admin-stat-icon" aria-hidden="true">KH</span>
                        <span><strong>Khách hàng</strong><span>Hồ sơ và thành viên</span></span>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a class="admin-shortcut" href="/admin/reports/">
                        <span class="admin-stat-icon" aria-hidden="true">BC</span>
                        <span><strong>Báo cáo</strong><span>Doanh thu và sản phẩm bán chạy</span></span>
                    </a>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if (($_SESSION['employee_role'] ?? '') === 'admin'): ?>
        <section class="mt-5">
            <div class="admin-section-heading">
                <div>
                    <span class="storefront-eyebrow">CẦN THEO DÕI</span>
                    <h2 class="mt-1">Sản phẩm sắp hết hàng</h2>
                </div>
                <a href="/admin/products/" class="btn btn-outline-primary btn-sm">Quản lý sản phẩm</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Mã</th>
                            <th>Sản phẩm</th>
                            <th>Tồn kho</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lowStockResult->num_rows > 0): ?>
                            <?php while ($product = $lowStockResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int) $product['ProductID'] ?></td>
                                    <td><?= htmlspecialchars($product['ProductName']) ?></td>
                                    <td><?= (int) $product['StockQuantity'] ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-muted">
                                    Không có sản phẩm nào sắp hết hàng.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted">
                Doanh thu tính theo tổng đơn đã đặt, không bao gồm đơn đã hủy.
            </p>
        </section>
    <?php endif; ?>

</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
