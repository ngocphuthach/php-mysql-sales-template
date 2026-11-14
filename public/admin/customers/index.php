<?php

$pageTitle = 'Quản lý Khách hàng';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerID = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    $points = filter_input(INPUT_POST, 'loyalty_point', FILTER_VALIDATE_INT);

    if (!$customerID || $customerID < 1 || $points === false || $points === null || $points < 0) {
        $error = 'Mã khách hàng hoặc điểm thành viên không hợp lệ.';
    } else {
        $stmt = $conn->prepare('UPDATE customers SET LoyaltyPoint = ? WHERE CustomerID = ?');
        $stmt->bind_param('ii', $points, $customerID);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $exists = $conn->prepare('SELECT CustomerID FROM customers WHERE CustomerID = ?');
            $exists->bind_param('i', $customerID);
            $exists->execute();
            $found = $exists->get_result()->num_rows === 1;
            $exists->close();
            if (!$found) {
                $error = 'Không tìm thấy khách hàng.';
            }
        }
        $stmt->close();
    }
}

$keyword = trim($_GET['q'] ?? '');
$search = '%' . $keyword . '%';
$sql = "
    SELECT
        c.CustomerID,
        c.CustomerName,
        c.Email,
        c.Phone,
        c.City,
        c.LoyaltyPoint,
        COUNT(o.OrderID) AS OrderCount
    FROM customers c
    LEFT JOIN orders o ON o.CustomerID = c.CustomerID
    WHERE ? = ''
       OR c.CustomerName LIKE ?
       OR c.Email LIKE ?
       OR c.Phone LIKE ?
    GROUP BY c.CustomerID
    ORDER BY c.CustomerID DESC
    LIMIT 200
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $keyword, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-4">
    <h1 class="h2 mb-3">Quản lý Khách hàng</h1>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-6">
            <label for="q" class="form-label">Tìm khách hàng</label>
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
                    <th>Khách hàng</th>
                    <th>Email</th>
                    <th>Điện thoại</th>
                    <th>Thành phố</th>
                    <th>Đơn hàng</th>
                    <th>Điểm thành viên</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($customer = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <a href="/admin/customers/detail.php?id=<?= (int) $customer['CustomerID'] ?>">
                                    #<?= (int) $customer['CustomerID'] ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($customer['CustomerName']) ?></td>
                            <td><?= htmlspecialchars($customer['Email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($customer['Phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($customer['City'] ?? '') ?></td>
                            <td><?= (int) $customer['OrderCount'] ?></td>
                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input
                                        type="hidden"
                                        name="customer_id"
                                        value="<?= (int) $customer['CustomerID'] ?>"
                                    >
                                    <input
                                        type="number"
                                        class="form-control form-control-sm"
                                        name="loyalty_point"
                                        min="0"
                                        value="<?= (int) $customer['LoyaltyPoint'] ?>"
                                        aria-label="Điểm thành viên"
                                        required
                                    >
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        Lưu
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted">Không tìm thấy khách hàng.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted">Danh sách hiển thị tối đa 200 khách hàng mới nhất.</p>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$stmt->close();
$conn->close();
?>
