<?php

$cartCount = array_sum($_SESSION['cart'] ?? []);
$isLoggedIn = isset($_SESSION['customer_id'])
    || isset($_SESSION['employee_id']);
$customerName = $_SESSION['customer_name']
    ?? $_SESSION['employee_name']
    ?? '';
$isEmployee = isset($_SESSION['employee_id']);

?>

<nav class="navbar navbar-expand-lg storefront-navbar sticky-top">

    <div class="container">

        <a class="navbar-brand d-flex align-items-center" href="/" aria-label="Maison Parfum - Trang chủ">
            <span class="storefront-brand-mark" aria-hidden="true">M</span>
            Maison Parfum
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#frontendNavbar"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="frontendNavbar"
        >

            <ul class="navbar-nav">

                <li class="nav-item">
                    <a class="nav-link" href="/">
                        Trang chủ
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/products.php">
                        Sản phẩm
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/#thuong-hieu">
                        Thương hiệu
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/#ve-chung-toi">
                        Về chúng tôi
                    </a>
                </li>

            </ul>

            <div class="ms-auto d-flex gap-2 align-items-center">
                <a class="btn btn-outline-dark btn-sm" href="/cart.php">
                    Giỏ hàng (<?= (int) $cartCount ?>)
                </a>

                <?php if ($isEmployee): ?>
                    <a class="btn btn-outline-dark btn-sm" href="/admin/">
                        Quản trị
                    </a>
                <?php elseif (isset($_SESSION['customer_id'])): ?>
                    <a class="btn btn-outline-dark btn-sm" href="/account.php">
                        Tài khoản
                    </a>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <span class="small text-muted">
                        <?= htmlspecialchars($customerName) ?>
                    </span>

                    <a class="btn btn-outline-dark btn-sm" href="/logout.php">
                        Đăng xuất
                    </a>
                <?php else: ?>
                    <a class="btn btn-outline-dark btn-sm" href="/register.php">
                        Đăng ký
                    </a>

                    <a class="btn storefront-btn btn-sm" href="/login.php">
                        Đăng nhập
                    </a>
                <?php endif; ?>
            </div>

        </div>

    </div>

</nav>