<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Đặt hàng';

$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header('Location: /cart.php');
    exit;
}

$isLoggedIn = isset($_SESSION['customer_id']);
$customerID = null;
$customerName = '';
$email = '';
$phone = '';
$address = '';
$errorMessage = '';

if ($isLoggedIn) {
    $customerID = (int) $_SESSION['customer_id'];

    $sqlCustomerAccount = "
        SELECT
            CustomerID,
            CustomerName,
            Email,
            Phone,
            Address
        FROM customers
        WHERE CustomerID = ?
          AND Email IS NOT NULL
    ";

    $stmtCustomerAccount = $conn->prepare($sqlCustomerAccount);
    $stmtCustomerAccount->bind_param('i', $customerID);
    $stmtCustomerAccount->execute();

    $customerResult = $stmtCustomerAccount->get_result();
    $customer = $customerResult->fetch_assoc();

    $customerResult->free();
    $stmtCustomerAccount->close();

    if (!$customer) {
        unset($_SESSION['customer_id'], $_SESSION['customer_name']);
        header('Location: /login.php');
        exit;
    }

    $customerName = $customer['CustomerName'];
    $email = $customer['Email'] ?? '';
    $phone = $customer['Phone'] ?? '';
    $address = $customer['Address'] ?? '';
}

function getCartItems($conn, $cart)
{
    $items = [];
    $total = 0;

    $sql = "
        SELECT
            p.ProductID,
            p.ProductCode,
            p.ProductName,
            p.Price,
            p.StockQuantity,
            (
                SELECT pi.ImageFile
                FROM product_images pi
                WHERE pi.ProductID = p.ProductID
                  AND pi.IsPrimary = 1
                LIMIT 1
            ) AS ImageFile
        FROM products p
        WHERE p.ProductID = ?
          AND p.IsActive = 1
    ";

    $stmt = $conn->prepare($sql);

    foreach ($cart as $productID => $quantity) {
        $productID = (int) $productID;
        $quantity = (int) $quantity;

        if ($productID <= 0 || $quantity <= 0) {
            continue;
        }

        $stmt->bind_param('i', $productID);
        $stmt->execute();

        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $result->free();

        if (!$product) {
            continue;
        }

        $product['Quantity'] = $quantity;
        $product['Subtotal'] = (float) $product['Price'] * $quantity;

        $total += $product['Subtotal'];
        $items[] = $product;
    }

    $stmt->close();

    return [
        'items' => $items,
        'total' => $total,
    ];
}

$cartData = getCartItems($conn, $cart);
$cartItems = $cartData['items'];
$total = $cartData['total'];

if (empty($cartItems)) {
    header('Location: /cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if ($isLoggedIn) {
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
    } else {
        $customerName = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
    }

    if ($isLoggedIn) {
        if ($phone === '' || $address === '') {
            $errorMessage = 'Vui lòng nhập đầy đủ thông tin liên hệ.';
        }
    } else {
        if ($customerName === '' || $phone === '' || $address === '') {
            $errorMessage = 'Vui lòng nhập đầy đủ thông tin khách hàng.';
        }
    }

    if ($errorMessage === '') {
        try {
            $conn->begin_transaction();

            $orderItems = [];
            $orderTotal = 0;

            $sqlProduct = "
                SELECT
                    ProductID,
                    ProductName,
                    Price,
                    StockQuantity
                FROM products
                WHERE ProductID = ?
                  AND IsActive = 1
                FOR UPDATE
            ";

            $stmtProduct = $conn->prepare($sqlProduct);

            foreach ($cart as $productID => $quantity) {
                $productID = (int) $productID;
                $quantity = (int) $quantity;

                if ($productID <= 0 || $quantity <= 0) {
                    throw new Exception('Dữ liệu giỏ hàng không hợp lệ.');
                }

                $stmtProduct->bind_param('i', $productID);
                $stmtProduct->execute();

                $productResult = $stmtProduct->get_result();
                $product = $productResult->fetch_assoc();
                $productResult->free();

                if (!$product) {
                    throw new Exception('Có sản phẩm không còn khả dụng.');
                }

                if ($quantity > (int) $product['StockQuantity']) {
                    throw new Exception(
                        'Sản phẩm "' . $product['ProductName'] . '" không đủ số lượng tồn kho.'
                    );
                }

                $unitPrice = (float) $product['Price'];
                $subtotal = $unitPrice * $quantity;
                $orderTotal += $subtotal;

                $orderItems[] = [
                    'ProductID' => $productID,
                    'Quantity' => $quantity,
                    'UnitPrice' => $unitPrice,
                ];
            }

            $stmtProduct->close();

            if ($isLoggedIn) {
                $sqlAccount = "
                    SELECT CustomerID
                    FROM customers
                    WHERE CustomerID = ?
                      AND Email IS NOT NULL
                    FOR UPDATE
                ";

                $stmtAccount = $conn->prepare($sqlAccount);
                $stmtAccount->bind_param('i', $customerID);
                $stmtAccount->execute();

                $accountResult = $stmtAccount->get_result();
                $account = $accountResult->fetch_assoc();

                $accountResult->free();
                $stmtAccount->close();

                if (!$account) {
                    throw new Exception('Tài khoản khách hàng không còn hợp lệ.');
                }

                $sqlUpdateCustomer = "
                    UPDATE customers
                    SET
                        Phone = ?,
                        Address = ?
                    WHERE CustomerID = ?
                ";

                $stmtUpdateCustomer = $conn->prepare($sqlUpdateCustomer);
                $stmtUpdateCustomer->bind_param('ssi', $phone, $address, $customerID);
                $stmtUpdateCustomer->execute();
                $stmtUpdateCustomer->close();
            } else {
                $sqlCustomer = "
                    INSERT INTO customers (
                        CustomerName,
                        Address,
                        Phone
                    )
                    VALUES (?, ?, ?)
                ";

                $stmtCustomer = $conn->prepare($sqlCustomer);
                $stmtCustomer->bind_param('sss', $customerName, $address, $phone);
                $stmtCustomer->execute();

                $customerID = $conn->insert_id;
                $stmtCustomer->close();
            }

            $status = 'Pending';

            $sqlOrder = "
                INSERT INTO orders
                    (TotalAmount, Status, CustomerID)
                VALUES (?, ?, ?)
            ";

            $stmtOrder = $conn->prepare($sqlOrder);
            $stmtOrder->bind_param('dsi', $orderTotal, $status, $customerID);
            $stmtOrder->execute();

            $orderID = $conn->insert_id;
            $stmtOrder->close();

            $sqlDetail = "
                INSERT INTO orderdetail
                    (Quantity, UnitPrice, OrderID, ProductID)
                VALUES (?, ?, ?, ?)
            ";

            $stmtDetail = $conn->prepare($sqlDetail);

            $sqlStock = "
                UPDATE products
                SET StockQuantity = StockQuantity - ?
                WHERE ProductID = ?
            ";

            $stmtStock = $conn->prepare($sqlStock);

            foreach ($orderItems as $item) {
                $quantity = $item['Quantity'];
                $unitPrice = $item['UnitPrice'];
                $productID = $item['ProductID'];

                $stmtDetail->bind_param('idii', $quantity, $unitPrice, $orderID, $productID);
                $stmtDetail->execute();

                $stmtStock->bind_param('ii', $quantity, $productID);
                $stmtStock->execute();
            }

            $stmtDetail->close();
            $stmtStock->close();

            $conn->commit();

            $_SESSION['cart'] = [];

            header('Location: /order-success.php?id=' . $orderID);
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $errorMessage = $e->getMessage();
        }
    }
}

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<div class="container py-4">

    <h1 class="h3 mb-4">Đặt hàng</h1>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="post">
                        <?php if ($isLoggedIn): ?>
                            <div class="mb-3">
                                <label class="form-label">Họ và tên</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    value="<?= htmlspecialchars($customerName) ?>"
                                    readonly
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input
                                    type="email"
                                    class="form-control"
                                    value="<?= htmlspecialchars($email) ?>"
                                    readonly
                                >
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">
                                    Họ và tên
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="customer_name"
                                    name="customer_name"
                                    value="<?= htmlspecialchars($customerName) ?>"
                                    required
                                >
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="phone" class="form-label">
                                Số điện thoại
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?= htmlspecialchars($phone) ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">
                                Địa chỉ
                            </label>
                            <textarea
                                class="form-control"
                                id="address"
                                name="address"
                                rows="3"
                                required
                            ><?= htmlspecialchars($address) ?></textarea>
                        </div>

                        <button type="submit" name="place_order" class="btn btn-primary">
                            Xác nhận đặt hàng
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Giỏ hàng</h2>

                    <?php foreach ($cartItems as $item): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                            <div>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($item['ProductName']) ?>
                                </div>
                                <small class="text-muted">
                                    <?= (int) $item['Quantity'] ?> ×
                                    <?= number_format((float) $item['Price'], 0, ',', '.') ?> đ
                                </small>
                            </div>
                            <div class="text-end">
                                <?= number_format((float) $item['Subtotal'], 0, ',', '.') ?> đ
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex justify-content-between align-items-center mt-3 fw-bold">
                        <span>Tổng tiền</span>
                        <span><?= number_format((float) $total, 0, ',', '.') ?> đ</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

$conn->close();
