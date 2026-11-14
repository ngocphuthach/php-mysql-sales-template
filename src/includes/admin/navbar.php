<nav class="navbar navbar-expand-xxl admin-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="/">Maison Parfum</a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav">

                <li class="nav-item">
                    <a class="nav-link" href="/">Xem cửa hàng</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/admin/orders/">
                        Đơn hàng
                    </a>
                </li>

                <?php if (($_SESSION['employee_role'] ?? '') === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/categories/">Danh mục</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/products/">Sản phẩm</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/suppliers/">Nhà cung cấp</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/shippers/">Nhà vận chuyển</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/customers/">Khách hàng</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/employees/">Nhân viên</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/reports/">Báo cáo</a>
                    </li>
                <?php endif; ?>

            </ul>
            <div class="d-flex align-items-center gap-2 ms-xxl-auto mt-2 mt-xxl-0">
                <span class="small text-muted d-none d-xl-inline">
                    <?= htmlspecialchars($_SESSION['employee_name'] ?? '') ?>
                    <span class="badge text-bg-light">
                        <?= ($_SESSION['employee_role'] ?? '') === 'admin' ? 'Quản trị' : 'Nhân viên' ?>
                    </span>
                </span>
                <a class="nav-link admin-logout" href="/logout.php">Đăng xuất</a>
            </div>
        </div>
    </div>
</nav>