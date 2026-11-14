<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$pageTitle = 'Maison Parfum | Cửa hàng nước hoa chính hãng';

$brandsResult = $conn->query("
    SELECT COALESCE(NULLIF(p.Brand, ''), c.CategoryName) AS Brand,
           COUNT(*) AS ProductCount
    FROM products p
    INNER JOIN categories c ON c.CategoryID = p.CategoryID
    WHERE p.IsActive = 1
      AND c.IsActive = 1
    GROUP BY COALESCE(NULLIF(p.Brand, ''), c.CategoryName)
    ORDER BY Brand
    LIMIT 8
");
$brands = $brandsResult->fetch_all(MYSQLI_ASSOC);
$brandsResult->free();

$productsResult = $conn->query("
    SELECT
        p.ProductID,
        p.ProductName,
        COALESCE(NULLIF(p.Brand, ''), c.CategoryName) AS Brand,
        p.Volume,
        p.Price,
        p.Discount,
        p.Price * (1 - p.Discount / 100) AS SellingPrice,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
            ORDER BY pi.IsPrimary DESC, pi.SortOrder, pi.ProductImageID
            LIMIT 1
        ) AS ImageFile
    FROM products p
    INNER JOIN categories c ON c.CategoryID = p.CategoryID
    WHERE p.IsActive = 1
      AND c.IsActive = 1
    ORDER BY p.ProductID DESC
    LIMIT 4
");
$featuredProducts = $productsResult->fetch_all(MYSQLI_ASSOC);
$productsResult->free();

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main>
    <section class="storefront-hero" aria-label="Khám phá Maison Parfum">
        <div
            id="perfumeHero"
            class="carousel slide carousel-fade"
            data-bs-ride="carousel"
            data-bs-interval="6000"
        >
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#perfumeHero" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Banner 1"></button>
                <button type="button" data-bs-target="#perfumeHero" data-bs-slide-to="1" aria-label="Banner 2"></button>
                <button type="button" data-bs-target="#perfumeHero" data-bs-slide-to="2" aria-label="Banner 3"></button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item hero-slide-one active">
                    <div class="carousel-caption">
                        <span class="storefront-eyebrow">MAISON PARFUM · SCENT OF YOU</span>
                        <h1>Mùi hương lưu dấu phong cách riêng</h1>
                        <p>
                            Chạm đến những nốt hương tinh tế, được tuyển chọn để đồng hành
                            cùng cá tính và những khoảnh khắc đáng nhớ của bạn.
                        </p>
                        <a href="/products.php" class="btn storefront-btn px-4 py-2">
                            Khám phá bộ sưu tập
                        </a>
                    </div>
                </div>
                <div class="carousel-item hero-slide-two">
                    <div class="carousel-caption">
                        <span class="storefront-eyebrow">CHỌN HƯƠNG · CHỌN PHONG CÁCH</span>
                        <h2>Mỗi nốt hương, một câu chuyện của riêng bạn</h2>
                        <p>
                            Từ hương hoa thanh lịch đến tầng hương gỗ ấm áp, khám phá
                            những sáng tạo làm nên dấu ấn khác biệt trong từng khoảnh khắc.
                        </p>
                        <a href="/products.php" class="btn storefront-btn px-4 py-2">
                            Tìm hương thơm của bạn
                        </a>
                    </div>
                </div>
                <div class="carousel-item hero-slide-three">
                    <div class="carousel-caption">
                        <span class="storefront-eyebrow">THƯƠNG HIỆU ĐƯỢC TUYỂN CHỌN</span>
                        <h2>Khám phá những biểu tượng hương thơm</h2>
                        <p>
                            Từ những nhà chế tác danh tiếng đến các sáng tạo đầy cá tính,
                            tìm thấy mùi hương khiến bạn muốn quay lại thêm lần nữa.
                        </p>
                        <a href="#thuong-hieu" class="btn storefront-btn px-4 py-2">
                            Khám phá thương hiệu
                        </a>
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#perfumeHero" data-bs-slide="prev" aria-label="Banner trước">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#perfumeHero" data-bs-slide="next" aria-label="Banner tiếp theo">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
            </button>
        </div>
    </section>

    <section class="storefront-benefits">
        <div class="container">
            <div class="row">
                <div class="col-md-4 storefront-benefit">✦ &nbsp; Bộ sưu tập hương thơm tuyển chọn</div>
                <div class="col-md-4 storefront-benefit">✦ &nbsp; Tư vấn chọn mùi hương phù hợp</div>
                <div class="col-md-4 storefront-benefit">✦ &nbsp; Đặt hàng dễ dàng, giao tận nơi</div>
            </div>
        </div>
    </section>

    <section class="container storefront-section">
        <div class="storefront-section-heading">
            <span class="storefront-eyebrow">Chọn theo cảm hứng</span>
            <h2>Khám phá thế giới hương thơm</h2>
            <p>Mỗi cá tính có một mùi hương. Chọn danh mục phù hợp và bắt đầu hành trình khám phá của bạn.</p>
        </div>
        <div class="row g-3">
            <?php
            $audiences = [
                ['value' => 'Nam', 'label' => 'Nước hoa nam', 'detail' => 'Mạnh mẽ · Bản lĩnh · Cuốn hút'],
                ['value' => 'Nữ', 'label' => 'Nước hoa nữ', 'detail' => 'Tinh tế · Quyến rũ · Thanh lịch'],
                ['value' => 'Unisex', 'label' => 'Hương thơm unisex', 'detail' => 'Tự do · Khác biệt · Không giới hạn'],
            ];
            ?>
            <?php foreach ($audiences as $index => $audience): ?>
                <div class="col-md-4">
                    <a
                        class="storefront-category-card h-100"
                        href="/products.php?gender=<?= rawurlencode($audience['value']) ?>"
                    >
                        <span>Bộ sưu tập 0<?= $index + 1 ?></span>
                        <strong><?= htmlspecialchars($audience['label']) ?></strong>
                        <small class="mt-2"><?= htmlspecialchars($audience['detail']) ?></small>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($featuredProducts !== []): ?>
        <section class="storefront-section bg-light">
            <div class="container">
                <div class="storefront-section-heading">
                    <span class="storefront-eyebrow">Tuyển chọn dành cho bạn</span>
                    <h2>Hương thơm nổi bật</h2>
                    <p>Khám phá những lựa chọn đang có tại Maison Parfum và tìm hương thơm dành riêng cho bạn.</p>
                </div>
                <div class="row g-4">
                    <?php foreach ($featuredProducts as $product): ?>
                        <div class="col-sm-6 col-lg-3">
                            <article class="card storefront-product-card h-100">
                                <a
                                    class="storefront-product-image d-block"
                                    href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                                    aria-label="Xem <?= htmlspecialchars($product['ProductName']) ?>"
                                >
                                    <?php if (!empty($product['ImageFile'])): ?>
                                        <img
                                            src="/uploads/products/<?= htmlspecialchars($product['ImageFile']) ?>"
                                            alt="<?= htmlspecialchars($product['ProductName']) ?>"
                                            loading="lazy"
                                        >
                                    <?php else: ?>
                                        <span class="storefront-product-placeholder" aria-hidden="true">M</span>
                                    <?php endif; ?>
                                </a>
                                <div class="card-body d-flex flex-column">
                                    <p class="small text-muted mb-2">
                                        <?= htmlspecialchars($product['Brand'] ?: 'Maison Parfum') ?>
                                        <?php if ($product['Volume'] !== null): ?>
                                            · <?= htmlspecialchars((string) $product['Volume']) ?> ml
                                        <?php endif; ?>
                                    </p>
                                    <h3 class="card-title">
                                        <?= htmlspecialchars($product['ProductName']) ?>
                                    </h3>
                                    <p class="storefront-price fw-semibold mb-3">
                                        <?= number_format((float) $product['SellingPrice'], 0, ',', '.') ?> đ
                                        <?php if ((float) $product['Discount'] > 0): ?>
                                            <span class="small text-muted text-decoration-line-through">
                                                <?= number_format((float) $product['Price'], 0, ',', '.') ?> đ
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    <a
                                        href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                                        class="btn storefront-btn mt-auto"
                                    >
                                        Khám phá sản phẩm
                                    </a>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-4">
                    <a href="/products.php" class="btn btn-outline-dark px-4 py-2">
                        Xem tất cả nước hoa
                    </a>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section id="thuong-hieu" class="container storefront-section">
        <div class="storefront-section-heading">
            <span class="storefront-eyebrow">Những cái tên được yêu mến</span>
            <h2>Thương hiệu nước hoa</h2>
            <p>Khám phá các thương hiệu hiện đang có trong bộ sưu tập của chúng tôi.</p>
        </div>
        <?php if ($brands !== []): ?>
            <div class="row g-3 justify-content-center">
                <?php foreach ($brands as $brand): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a
                            class="storefront-brand-link h-100"
                            href="/products.php?brand=<?= rawurlencode($brand['Brand']) ?>"
                        >
                            <?= htmlspecialchars($brand['Brand']) ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-center text-muted">Các thương hiệu sẽ sớm được cập nhật.</p>
        <?php endif; ?>
    </section>

    <section id="ve-chung-toi" class="storefront-about storefront-section">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-7">
                    <span class="storefront-eyebrow">Câu chuyện của chúng tôi</span>
                    <h2 class="mt-2 mb-3">Một mùi hương đẹp bắt đầu từ cảm xúc</h2>
                    <p class="storefront-about-copy">
                        Maison Parfum được tạo nên cho những ai tin rằng hương thơm
                        không chỉ để lưu lại trên làn da, mà còn kể câu chuyện về
                        phong cách và những khoảnh khắc đáng nhớ.
                    </p>
                    <p class="storefront-about-copy">
                        Chúng tôi tuyển chọn các dòng nước hoa theo thương hiệu,
                        nồng độ và cá tính để bạn dễ dàng tìm thấy lựa chọn phù hợp.
                        Dù là món quà dành tặng ai đó hay một dấu ấn cho riêng mình,
                        hành trình khám phá hương thơm luôn bắt đầu từ điều bạn yêu.
                    </p>
                    <a href="/products.php" class="btn storefront-btn px-4 py-2">
                        Tìm mùi hương của bạn
                    </a>
                </div>
                <div class="col-lg-5">
                    <div class="storefront-about-note d-flex flex-column justify-content-between">
                        <span class="storefront-eyebrow">The art of fragrance</span>
                        <blockquote>
                            “Hương thơm là điều vô hình, nhưng luôn khiến người ta nhớ.”
                        </blockquote>
                        <span class="small text-white-50">MAISON PARFUM · SINCE ALWAYS</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
require_once '/var/www/src/includes/frontend/footer.php';
$conn->close();
?>
