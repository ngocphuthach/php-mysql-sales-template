<?php

$pageTitle = 'Quản lý Nhân viên';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$keyword = trim($_GET['q'] ?? '');
$search = '%' . $keyword . '%';
$sql = "
    SELECT EmployeeID, FirstName, LastName, Email, Phone, Role, IsActive
    FROM employees
    WHERE ? = ''
       OR FirstName LIKE ?
       OR LastName LIKE ?
       OR Email LIKE ?
       OR Phone LIKE ?
    ORDER BY LastName, FirstName
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
        <h1 class="h2">Quản lý Nhân viên</h1>
        <a href="/admin/employees/create.php" class="btn btn-primary">
            Thêm nhân viên
        </a>
    </div>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-6">
            <label for="q" class="form-label">Tìm nhân viên</label>
            <input
                type="search"
                id="q"
                name="q"
                class="form-control"
                value="<?= htmlspecialchars($keyword) ?>"
                placeholder="Tên, email hoặc số điện thoại"
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
                    <th>Họ tên</th>
                    <th>Email</th>
                    <th>Điện thoại</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($employee = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int) $employee['EmployeeID'] ?></td>
                            <td><?= htmlspecialchars($employee['FirstName'] . ' ' . $employee['LastName']) ?></td>
                            <td><?= htmlspecialchars($employee['Email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($employee['Phone'] ?? '') ?></td>
                            <td><?= $employee['Role'] === 'admin' ? 'Quản trị viên' : 'Nhân viên' ?></td>
                            <td>
                                <span class="badge <?= (int) $employee['IsActive'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) $employee['IsActive'] === 1 ? 'Đang làm việc' : 'Đã khóa' ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <a
                                    href="/admin/employees/edit.php?id=<?= (int) $employee['EmployeeID'] ?>"
                                    class="btn btn-sm btn-warning"
                                >Sửa</a>
                                <?php if ((int) $employee['EmployeeID'] !== (int) $_SESSION['employee_id']): ?>
                                    <form action="/admin/employees/delete.php" method="post" class="d-inline">
                                        <input type="hidden" name="id" value="<?= (int) $employee['EmployeeID'] ?>">
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="<?= (int) $employee['IsActive'] === 1 ? 'disable' : 'enable' ?>"
                                        >
                                        <button
                                            type="submit"
                                            class="btn btn-sm <?= (int) $employee['IsActive'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                        >
                                            <?= (int) $employee['IsActive'] === 1 ? 'Khóa' : 'Mở khóa' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted">Không tìm thấy nhân viên.</td></tr>
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
