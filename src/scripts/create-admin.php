<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 4) {
    fwrite(
        STDERR,
        "Usage: php create-admin.php <email> <first-name> <last-name>\n"
    );
    exit(2);
}

[$script, $email, $firstName, $lastName] = $argv;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email không hợp lệ.\n");
    exit(2);
}

if (trim($firstName) === '' || trim($lastName) === '') {
    fwrite(STDERR, "Cần nhập họ và tên.\n");
    exit(2);
}

fwrite(STDOUT, "Mật khẩu (tối thiểu 10 ký tự): ");
$isTTY = function_exists('stream_isatty') && stream_isatty(STDIN);
if ($isTTY) {
    exec('stty -echo');
}
$password = trim(str_replace("\0", '', (string) fgets(STDIN)));
if ($isTTY) {
    exec('stty echo');
    fwrite(STDOUT, "\n");
}

if (strlen($password) < 10) {
    fwrite(STDERR, "Mật khẩu phải có ít nhất 10 ký tự.\n");
    exit(2);
}

require_once '/var/www/src/config/database.php';

$result = $conn->query("
    SELECT COUNT(*) AS Total
    FROM employees
    WHERE Role = 'admin' AND IsActive = 1
");
$activeAdmins = (int) $result->fetch_assoc()['Total'];
$result->free();

if ($activeAdmins > 0) {
    fwrite(STDERR, "Đã có quản trị viên hoạt động; hãy tạo tài khoản mới từ trang quản trị.\n");
    $conn->close();
    exit(1);
}

$check = $conn->prepare('SELECT EmployeeID FROM employees WHERE Email = ?');
$check->bind_param('s', $email);
$check->execute();
$emailExists = $check->get_result()->num_rows > 0;
$check->close();

if ($emailExists) {
    fwrite(STDERR, "Email này đã được sử dụng.\n");
    $conn->close();
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("
    INSERT INTO employees (FirstName, LastName, Email, PasswordHash, Role, IsActive)
    VALUES (?, ?, ?, ?, 'admin', 1)
");
$stmt->bind_param('ssss', $firstName, $lastName, $email, $passwordHash);
$stmt->execute();
$employeeID = $conn->insert_id;
$stmt->close();

$verify = $conn->prepare('SELECT PasswordHash FROM employees WHERE EmployeeID = ?');
$verify->bind_param('i', $employeeID);
$verify->execute();
$createdAccount = $verify->get_result()->fetch_assoc();
$verify->close();

if (!$createdAccount || !password_verify($password, $createdAccount['PasswordHash'])) {
    $remove = $conn->prepare('DELETE FROM employees WHERE EmployeeID = ?');
    $remove->bind_param('i', $employeeID);
    $remove->execute();
    $remove->close();
    $conn->close();
    throw new RuntimeException('Không thể xác minh tài khoản quản trị viên vừa tạo.');
}

$conn->close();

fwrite(STDOUT, "Đã tạo tài khoản quản trị viên.\n");
