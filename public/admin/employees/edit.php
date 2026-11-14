<?php

$pageTitle = 'Sửa Nhân viên';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$employeeID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$employeeID || $employeeID < 1) {
    http_response_code(400);
    exit('Mã nhân viên không hợp lệ.');
}

$stmt = $conn->prepare("
    SELECT EmployeeID, FirstName, LastName, Email, Phone, Role, IsActive
    FROM employees
    WHERE EmployeeID = ?
");
$stmt->bind_param('i', $employeeID);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employee) {
    http_response_code(404);
    exit('Không tìm thấy nhân viên.');
}

$fields = [
    'first_name' => $employee['FirstName'],
    'last_name' => $employee['LastName'],
    'email' => $employee['Email'] ?? '',
    'phone' => $employee['Phone'] ?? '',
    'role' => $employee['Role'],
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $default) {
        $fields[$key] = trim($_POST[$key] ?? '');
    }
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    if ($fields['first_name'] === '' || $fields['last_name'] === '') {
        $error = 'Vui lòng nhập họ và tên nhân viên.';
    } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không hợp lệ.';
    } elseif (!in_array($fields['role'], ['staff', 'admin'], true)) {
        $error = 'Vai trò không hợp lệ.';
    } elseif ($password !== '' && strlen($password) < 10) {
        $error = 'Mật khẩu mới phải có ít nhất 10 ký tự.';
    } elseif (
        (int) $employee['EmployeeID'] === (int) $_SESSION['employee_id']
        && ($fields['role'] !== 'admin' || $isActive !== 1)
    ) {
        $error = 'Bạn không thể tự hạ quyền hoặc khóa tài khoản đang sử dụng.';
    } else {
        $check = $conn->prepare('SELECT EmployeeID FROM employees WHERE Email = ? AND EmployeeID <> ?');
        $check->bind_param('si', $fields['email'], $employeeID);
        $check->execute();
        $emailExists = $check->get_result()->num_rows > 0;
        $check->close();

        $lastAdmin = false;
        if (
            $employee['Role'] === 'admin'
            && (int) $employee['IsActive'] === 1
            && ($fields['role'] !== 'admin' || $isActive !== 1)
        ) {
            $adminCount = $conn->query("
                SELECT COUNT(*) AS Total
                FROM employees
                WHERE Role = 'admin' AND IsActive = 1
            ")->fetch_assoc();
            $lastAdmin = (int) $adminCount['Total'] <= 1;
        }

        if ($emailExists) {
            $error = 'Email này đã được sử dụng.';
        } elseif ($lastAdmin) {
            $error = 'Phải luôn có ít nhất một quản trị viên đang hoạt động.';
        } else {
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("
                    UPDATE employees
                    SET FirstName = ?, LastName = ?, Email = ?, Phone = ?,
                        Role = ?, IsActive = ?, PasswordHash = ?
                    WHERE EmployeeID = ?
                ");
                $update->bind_param(
                    'sssssisi',
                    $fields['first_name'],
                    $fields['last_name'],
                    $fields['email'],
                    $fields['phone'],
                    $fields['role'],
                    $isActive,
                    $passwordHash,
                    $employeeID
                );
            } else {
                $update = $conn->prepare("
                    UPDATE employees
                    SET FirstName = ?, LastName = ?, Email = ?, Phone = ?,
                        Role = ?, IsActive = ?
                    WHERE EmployeeID = ?
                ");
                $update->bind_param(
                    'sssssii',
                    $fields['first_name'],
                    $fields['last_name'],
                    $fields['email'],
                    $fields['phone'],
                    $fields['role'],
                    $isActive,
                    $employeeID
                );
            }
            $update->execute();
            $update->close();
            $conn->close();
            header('Location: /admin/employees/');
            exit;
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container py-4">
    <h1 class="h2 mb-4">Sửa nhân viên #<?= (int) $employeeID ?></h1>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="row">
        <div class="col-md-6 mb-3">
            <label for="first_name" class="form-label">Tên</label>
            <input class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($fields['first_name']) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="last_name" class="form-label">Họ</label>
            <input class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($fields['last_name']) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="email" class="form-label">Email đăng nhập</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($fields['email']) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="phone" class="form-label">Số điện thoại</label>
            <input class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($fields['phone']) ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label for="password" class="form-label">Mật khẩu mới (để trống để giữ nguyên)</label>
            <input type="password" class="form-control" id="password" name="password" minlength="10">
        </div>
        <div class="col-md-6 mb-3">
            <label for="role" class="form-label">Vai trò</label>
            <select class="form-select" id="role" name="role">
                <option value="staff" <?= $fields['role'] === 'staff' ? 'selected' : '' ?>>Nhân viên</option>
                <option value="admin" <?= $fields['role'] === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
            </select>
        </div>
        <div class="col-12 form-check mb-3 ms-2">
            <input
                type="checkbox"
                class="form-check-input"
                id="is_active"
                name="is_active"
                value="1"
                <?= ($_SERVER['REQUEST_METHOD'] === 'POST'
                    ? isset($_POST['is_active'])
                    : (int) $employee['IsActive'] === 1) ? 'checked' : '' ?>
            >
            <label for="is_active" class="form-check-label">Tài khoản đang hoạt động</label>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Cập nhật</button>
            <a href="/admin/employees/" class="btn btn-secondary">Hủy</a>
        </div>
    </form>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>
