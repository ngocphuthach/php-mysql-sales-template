<?php

$pageTitle = 'Quản lý Nhà vận chuyển';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$keyword = trim($_GET['q'] ?? '');
$search = '%' . $keyword . '%';

$sql = "
    SELECT
        ShipperID,
        ShipperName,
        Phone,
        Email,
        Website,
        DefaultFee,
        IsActive
    FROM shippers
    WHERE ? = ''
       OR ShipperName LIKE ?
       OR Phone LIKE ?
       OR Email LIKE ?
    ORDER BY ShipperName
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $keyword, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

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

    <form method="get" class="row g-2 mb-3">
        <div class="col-md-6">
            <label for="q" class="form-label">Tìm nhà vận chuyển</label>
            <input
                type="search"
                id="q"
                name="q"
                class="form-control"
                value="<?= htmlspecialchars($keyword) ?>"
                placeholder="Tên, số điện thoại hoặc email"
            >
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-outline-primary">Tìm</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Mã</th>
                    <th>Tên</th>
                    <th>Số điện thoại</th>
                    <th>Email</th>
                    <th>Phí mặc định</th>
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
                            <td><?= htmlspecialchars($shipper['Email'] ?? '') ?></td>
                            <td class="text-end">
                                <?= number_format((float) $shipper['DefaultFee'], 0, ',', '.') ?> đ
                            </td>
                            <td>
                                <span class="badge <?= (int) $shipper['IsActive'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) $shipper['IsActive'] === 1 ? 'Đang hợp tác' : 'Ngừng hợp tác' ?>
                                </span>
                            </td>

                            <td>
                                <a href="/admin/shippers/edit.php?id=<?= (int) $shipper['ShipperID'] ?>" class="btn btn-sm btn-warning">
                                    Sửa
                                </a>

                                <form
                                    action="/admin/shippers/delete.php"
                                    method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Bạn có chắc muốn <?= (int) $shipper['IsActive'] === 1 ? 'ngừng hợp tác' : 'kích hoạt lại' ?> với đơn vị này?');"
                                >
                                    <input type="hidden" name="id" value="<?= (int) $shipper['ShipperID'] ?>">
                                    <input type="hidden" name="action" value="<?= (int) $shipper['IsActive'] === 1 ? 'deactivate' : 'activate' ?>">
                                    <button
                                        type="submit"
                                        class="btn btn-sm <?= (int) $shipper['IsActive'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                    >
                                        <?= (int) $shipper['IsActive'] === 1 ? 'Ngừng' : 'Kích hoạt' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">
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
$stmt->close();
$conn->close();
?>
