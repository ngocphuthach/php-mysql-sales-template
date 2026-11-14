<?php

$pageTitle = 'Quản lý Nhà cung cấp';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$keyword = trim($_GET['q'] ?? '');
$search = '%' . $keyword . '%';
$sql = "
    SELECT SupplierID, SupplierName, ContactName, Phone, Email, City, IsActive
    FROM suppliers
    WHERE ? = ''
       OR SupplierName LIKE ?
       OR ContactName LIKE ?
       OR Phone LIKE ?
       OR Email LIKE ?
    ORDER BY SupplierName
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sssss', $keyword, $search, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h2">Quản lý Nhà cung cấp</h1>
        <a href="/admin/suppliers/create.php" class="btn btn-primary">
            Thêm nhà cung cấp
        </a>
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-md-6">
            <label for="q" class="form-label">Tìm nhà cung cấp</label>
            <input
                type="search"
                id="q"
                name="q"
                class="form-control"
                value="<?= htmlspecialchars($keyword) ?>"
                placeholder="Tên, người liên hệ, điện thoại hoặc email"
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
                    <th>Tên nhà cung cấp</th>
                    <th>Người liên hệ</th>
                    <th>Điện thoại</th>
                    <th>Email</th>
                    <th>Thành phố</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($supplier = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int) $supplier['SupplierID'] ?></td>
                            <td><?= htmlspecialchars($supplier['SupplierName']) ?></td>
                            <td><?= htmlspecialchars($supplier['ContactName'] ?? '') ?></td>
                            <td><?= htmlspecialchars($supplier['Phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($supplier['Email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($supplier['City'] ?? '') ?></td>
                            <td>
                                <span class="badge <?= (int) $supplier['IsActive'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) $supplier['IsActive'] === 1 ? 'Đang hoạt động' : 'Ngừng hoạt động' ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <a
                                    href="/admin/suppliers/edit.php?id=<?= (int) $supplier['SupplierID'] ?>"
                                    class="btn btn-sm btn-warning"
                                >Sửa</a>
                                <form
                                    action="/admin/suppliers/delete.php"
                                    method="post"
                                    class="d-inline"
                                >
                                    <input type="hidden" name="id" value="<?= (int) $supplier['SupplierID'] ?>">
                                    <input
                                        type="hidden"
                                        name="active"
                                        value="<?= (int) $supplier['IsActive'] === 1 ? '0' : '1' ?>"
                                    >
                                    <button
                                        type="submit"
                                        class="btn btn-sm <?= (int) $supplier['IsActive'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                    >
                                        <?= (int) $supplier['IsActive'] === 1 ? 'Ngừng' : 'Kích hoạt' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            Không tìm thấy nhà cung cấp.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$stmt->close();
$conn->close();
?>
