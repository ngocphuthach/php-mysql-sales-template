<?php

$pageTitle = 'Thêm Nhân viên';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$fields = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'staff',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $default) {
        $fields[$key] = trim($_POST[$key] ?? $default);
    }
    $password = $_POST['password'] ?? '';

    if ($fields['first_name'] === '' || $fields['last_name'] === '') {
        $error = 'Vui lòng nhập họ và tên nhân viên.';
    } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không hợp lệ.';
    } elseif (strlen($password) < 10) {
        $error = 'Mật khẩu phải có ít nhất 10 ký tự.';
    } elseif (!in_array($fields['role'], ['staff', 'admin'], true)) {
        $error = 'Vai trò không hợp lệ.';
    } else {
        $check = $conn->prepare('SELECT EmployeeID FROM employees WHERE Email = ?');
        $check->bind_param('s', $fields['email']);
        $check->execute();
        $emailExists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($emailExists) {
            $error = 'Email này đã được sử dụng.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO employees
                    (FirstName, LastName, Email, PasswordHash, Phone, Role, IsActive)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param(
                'ssssss',
                $fields['first_name'],
                $fields['last_name'],
                $fields['email'],
                $passwordHash,
                $fields['phone'],
                $fields['role']
            );
            $stmt->execute();
            $stmt->close();
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
    <h1 class="h2 mb-4">Thêm nhân viên</h1>
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
            <label for="password" class="form-label">Mật khẩu tạm thời (tối thiểu 10 ký tự)</label>
            <input type="password" class="form-control" id="password" name="password" minlength="10" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="role" class="form-label">Vai trò</label>
            <select class="form-select" id="role" name="role">
                <option value="staff" <?= $fields['role'] === 'staff' ? 'selected' : '' ?>>Nhân viên</option>
                <option value="admin" <?= $fields['role'] === 'admin' ? 'selected' : '' ?>>Quản trị viên</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Tạo tài khoản</button>
            <a href="/admin/employees/" class="btn btn-secondary">Hủy</a>
        </div>
    </form>
</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>
