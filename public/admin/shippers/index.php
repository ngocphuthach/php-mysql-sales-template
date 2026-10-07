<?php

$pageTitle = 'Quản lý Nhà vận chuyển';

require_once '/var/www/src/config/database.php';

$conn->query("
    CREATE TABLE IF NOT EXISTS shippers (
        ShipperID INT NOT NULL AUTO_INCREMENT,
        ShipperName VARCHAR(255) NOT NULL,
        Phone VARCHAR(50) DEFAULT NULL,
        IsActive TINYINT(1) NOT NULL DEFAULT 1,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (ShipperID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$sql = "
    SELECT
        ShipperID,
        ShipperName,
        Phone,
        IsActive
    FROM shippers
    ORDER BY ShipperID DESC
";

$result = $conn->query($sql);

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Quản lý Nhà vận chuyển</h2>

        <a href="/admin/shippers/create.php" class="btn btn-primary">
            Thêm nhà vận chuyển
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Mã</th>
                    <th>Tên</th>
                    <th>Số điện thoại</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($shipper = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $shipper['ShipperID']) ?></td>

                            <td><?= htmlspecialchars($shipper['ShipperName'] ?? '') ?></td>

                            <td><?= htmlspecialchars($shipper['Phone'] ?? '') ?></td>

                            <td>
                                <?php if ((int) ($shipper['IsActive'] ?? 1) === 1): ?>
                                    <span class="badge bg-success">Đang hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Ngừng hoạt động</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <a href="/admin/shippers/edit.php?id=<?= (int) $shipper['ShipperID'] ?>" class="btn btn-sm btn-warning">
                                    Sửa
                                </a>

                                <form
                                    action="/admin/shippers/delete.php"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Bạn có chắc muốn xóa nhà vận chuyển này?');"
                                >
                                    <input type="hidden" name="id" value="<?= (int) $shipper['ShipperID'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            Chưa có nhà vận chuyển nào.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php

require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>
