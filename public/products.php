<?php

require_once '/var/www/src/config/session.php';
require_once '/var/www/src/config/database.php';

$keyword = isset($_GET['keyword'])
    ? trim($_GET['keyword'])
    : '';

$brand = trim($_GET['brand'] ?? '');
$gender = trim($_GET['gender'] ?? '');

$priceBoundsResult = $conn->query("
    SELECT
        CEIL(MAX(Price * (1 - Discount / 100))) AS MaxPrice
    FROM products p
    INNER JOIN categories c ON c.CategoryID = p.CategoryID
    WHERE p.IsActive = 1
      AND c.IsActive = 1
");
$priceBounds = $priceBoundsResult->fetch_assoc();
$priceBoundsResult->free();
$priceFloor = 0;
$priceCeiling = (int) ($priceBounds['MaxPrice'] ?? 0);

$minPriceInput = trim($_GET['min_price'] ?? '');
$maxPriceInput = trim($_GET['max_price'] ?? '');
$minPrice = is_numeric($minPriceInput)
    ? max($priceFloor, min($priceCeiling, (int) $minPriceInput))
    : $priceFloor;
$maxPrice = is_numeric($maxPriceInput)
    ? max($priceFloor, min($priceCeiling, (int) $maxPriceInput))
    : $priceCeiling;

if (!in_array($gender, ['', 'Nam', 'Nữ', 'Unisex'], true)) {
    $gender = '';
}

if ($minPrice > $maxPrice) {
    [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
}
$minPriceInput = (string) $minPrice;
$maxPriceInput = (string) $maxPrice;

$itemsPerPage = 6;

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}

$conditions = [
    'p.CategoryID = c.CategoryID',
    'p.IsActive = 1',
    'c.IsActive = 1'
];
$filterValues = [];
$filterTypes = '';

if ($keyword !== '') {
    $conditions[] = '(p.ProductName LIKE ? OR p.ProductCode LIKE ?)';
    $searchKeyword = '%' . $keyword . '%';
    $filterValues[] = $searchKeyword;
    $filterValues[] = $searchKeyword;
    $filterTypes .= 'ss';
}

if ($brand !== '') {
    $conditions[] = "COALESCE(NULLIF(p.Brand, ''), c.CategoryName) = ?";
    $filterValues[] = $brand;
    $filterTypes .= 's';
}

if ($gender !== '') {
    $conditions[] = 'COALESCE(NULLIF(p.Gender, \'\'), \'Unisex\') = ?';
    $filterValues[] = $gender;
    $filterTypes .= 's';
}

$conditions[] = 'p.Price * (1 - p.Discount / 100) >= ?';
$filterValues[] = $minPrice;
$filterTypes .= 'd';
$conditions[] = 'p.Price * (1 - p.Discount / 100) <= ?';
$filterValues[] = $maxPrice;
$filterTypes .= 'd';

$whereClause = implode("\n        AND ", $conditions);

$brandResult = $conn->query("
    SELECT DISTINCT COALESCE(NULLIF(p.Brand, ''), c.CategoryName) AS Brand
    FROM products p
    INNER JOIN categories c ON c.CategoryID = p.CategoryID
    WHERE p.IsActive = 1
      AND c.IsActive = 1
    ORDER BY Brand
");
$brands = [];
while ($brandRow = $brandResult->fetch_assoc()) {
    $brands[] = $brandRow['Brand'];
}
$brandResult->free();

$sqlCount = "
    SELECT COUNT(*) AS TotalProducts
    FROM products p, categories c
    WHERE {$whereClause}
";

$stmtCount = $conn->prepare($sqlCount);

if ($stmtCount === false) {
    die('Không thể chuẩn bị truy vấn đếm sản phẩm.');
}

if ($filterValues !== []) {
    $stmtCount->bind_param($filterTypes, ...$filterValues);
}

$stmtCount->execute();

$countResult = $stmtCount->get_result();
$countRow = $countResult->fetch_assoc();

$totalProducts = (int) ($countRow['TotalProducts'] ?? 0);

$countResult->free();
$stmtCount->close();

$totalPages = (int) ceil($totalProducts / $itemsPerPage);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $itemsPerPage;

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Brand,
        p.Volume,
        p.Concentration,
        p.Gender,
        p.Price,
        p.Discount,
        p.Price * (1 - p.Discount / 100) AS SellingPrice,
        c.CategoryName,

        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile

    FROM
        products p,
        categories c

    WHERE
        {$whereClause}
";

$sql .= "
    ORDER BY
        p.ProductID DESC
    LIMIT ?
    OFFSET ?
";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die('Không thể chuẩn bị truy vấn danh sách sản phẩm.');
}

$listValues = [...$filterValues, $itemsPerPage, $offset];
$listTypes = $filterTypes . 'ii';
$stmt->bind_param($listTypes, ...$listValues);

$stmt->execute();
$result = $stmt->get_result();

$pageTitle = 'Sản phẩm';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';
?>

<main class="container storefront-catalog py-5">

    <div class="mb-4">
        <span class="storefront-eyebrow">Maison Parfum · Bộ sưu tập</span>
        <h1 class="mt-2">Khám phá nước hoa</h1>

        <p class="text-muted">
            Tìm mùi hương phù hợp với cá tính, phong cách và khoảnh khắc của bạn.
        </p>
    </div>

    <form
        method="get"
        action="/products.php"
        class="row g-3 mb-4 p-3 border bg-white"
    >
        <div class="col-md-6 col-lg-4">
            <label for="keyword" class="form-label">
                Tìm sản phẩm
            </label>

            <input
                type="text"
                name="keyword"
                id="keyword"
                class="form-control"
                value="<?= htmlspecialchars($keyword) ?>"
                placeholder="Nhập tên hoặc mã sản phẩm"
            >
        </div>

        <div class="col-md-6 col-lg-4">
            <label for="brand" class="form-label">Thương hiệu</label>
            <select name="brand" id="brand" class="form-select">
                <option value="">Tất cả thương hiệu</option>
                <?php foreach ($brands as $brandOption): ?>
                    <option
                        value="<?= htmlspecialchars($brandOption) ?>"
                        <?= $brand === $brandOption ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($brandOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 col-lg-4">
            <label for="gender" class="form-label">Danh mục</label>
            <select name="gender" id="gender" class="form-select">
                <option value="">Tất cả danh mục</option>
                <?php foreach (['Nam', 'Nữ', 'Unisex'] as $genderOption): ?>
                    <option
                        value="<?= htmlspecialchars($genderOption) ?>"
                        <?= $gender === $genderOption ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($genderOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-lg-8">
            <fieldset class="price-range">
                <legend class="form-label mb-1">Khoảng giá</legend>
                <div class="d-flex justify-content-between small mb-2">
                    <span>Từ <strong id="minPriceLabel"><?= number_format($minPrice, 0, ',', '.') ?> đ</strong></span>
                    <span>Đến <strong id="maxPriceLabel"><?= number_format($maxPrice, 0, ',', '.') ?> đ</strong></span>
                </div>
                <div class="price-range-sliders">
                    <input
                        type="range"
                        class="form-range"
                        id="min_price"
                        name="min_price"
                        min="<?= $priceFloor ?>"
                        max="<?= $priceCeiling ?>"
                        step="1"
                        value="<?= $minPrice ?>"
                        aria-label="Giá thấp nhất"
                    >
                    <input
                        type="range"
                        class="form-range"
                        id="max_price"
                        name="max_price"
                        min="<?= $priceFloor ?>"
                        max="<?= $priceCeiling ?>"
                        step="1"
                        value="<?= $maxPrice ?>"
                        aria-label="Giá cao nhất"
                    >
                </div>
                <div class="d-flex justify-content-between text-muted small">
                    <span><?= number_format($priceFloor, 0, ',', '.') ?> đ</span>
                    <span><?= number_format($priceCeiling, 0, ',', '.') ?> đ</span>
                </div>
            </fieldset>
        </div>

        <div class="col-md-auto align-self-end">
            <button type="submit" class="btn btn-primary">
                Tìm kiếm
            </button>

            <a href="/products.php" class="btn btn-outline-secondary">
                Xóa bộ lọc
            </a>
        </div>
    </form>

    <p class="text-muted">
        Tìm thấy <strong><?= $totalProducts ?></strong> sản phẩm.
    </p>

    <?php if ($result->num_rows > 0): ?>
        <div class="row g-4">
            <?php while ($product = $result->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card storefront-product-card h-100">
                        <?php if (!empty($product['ImageFile'])): ?>
                            <div
                                class="storefront-product-image"
                            >
                                <img
                                    src="/uploads/products/<?= htmlspecialchars($product['ImageFile']) ?>"
                                    alt="<?= htmlspecialchars($product['ProductName']) ?>"
                                    loading="lazy"
                                >
                            </div>
                        <?php else: ?>
                            <div
                                class="storefront-product-image"
                            >
                                <span class="storefront-product-placeholder" aria-hidden="true">M</span>
                            </div>
                        <?php endif; ?>

                        <div class="card-body d-flex flex-column">
                            <?php if (!empty($product['Brand'])): ?>
                                <p class="text-muted small mb-1">
                                    <?= htmlspecialchars($product['Brand']) ?>
                                    <?php if ($product['CategoryName'] !== $product['Brand']): ?>
                                        · <?= htmlspecialchars($product['CategoryName']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p class="text-muted small mb-1">
                                    <?= htmlspecialchars($product['CategoryName']) ?>
                                </p>
                            <?php endif; ?>

                            <h2 class="card-title">
                                <?= htmlspecialchars($product['ProductName']) ?>
                            </h2>

                            <p class="text-muted small">
                                Mã sản phẩm:
                                <?= htmlspecialchars($product['ProductCode']) ?>
                            </p>

                            <?php if (
                                !empty($product['Volume'])
                                || !empty($product['Concentration'])
                                || !empty($product['Gender'])
                            ): ?>
                                <p class="small text-muted mb-2">
                                    <?= $product['Volume'] !== null ? htmlspecialchars((string) $product['Volume']) . ' ml' : '' ?>
                                    <?= !empty($product['Concentration']) ? ' · ' . htmlspecialchars($product['Concentration']) : '' ?>
                                    <?= !empty($product['Gender']) ? ' · ' . htmlspecialchars($product['Gender']) : '' ?>
                                </p>
                            <?php endif; ?>

                            <p class="storefront-price fw-bold fs-5 mb-3">
                                <?= number_format((float) $product['SellingPrice'], 0, ',', '.') ?> đ
                                <?php if ((float) $product['Discount'] > 0): ?>
                                    <span class="text-muted small text-decoration-line-through">
                                        <?= number_format((float) $product['Price'], 0, ',', '.') ?> đ
                                    </span>
                                    <span class="badge bg-danger">
                                        -<?= rtrim(rtrim(number_format((float) $product['Discount'], 2, '.', ''), '0'), '.') ?>%
                                    </span>
                                <?php endif; ?>
                            </p>

                            <a
                                href="/product-detail.php?id=<?= (int) $product['ProductID'] ?>"
                                class="btn storefront-btn mt-auto"
                            >
                                Xem chi tiết
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Hiện chưa có sản phẩm nào để hiển thị.
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-5" aria-label="Phân trang sản phẩm">
            <ul class="pagination justify-content-center flex-wrap">
                <?php
                $previousQuery = http_build_query([
                    'keyword' => $keyword,
                    'brand' => $brand,
                    'gender' => $gender,
                    'min_price' => $minPriceInput,
                    'max_price' => $maxPriceInput,
                    'page' => max(1, $page - 1)
                ]);
                ?>

                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="/products.php?<?= $previousQuery ?>">
                        &laquo; Trước
                    </a>
                </li>

                <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                    <?php
                    $query = http_build_query([
                        'keyword' => $keyword,
                        'brand' => $brand,
                        'gender' => $gender,
                        'min_price' => $minPriceInput,
                        'max_price' => $maxPriceInput,
                        'page' => $pageNumber
                    ]);
                    ?>

                    <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                        <a class="page-link" href="/products.php?<?= $query ?>">
                            <?= $pageNumber ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php
                $nextQuery = http_build_query([
                    'keyword' => $keyword,
                    'brand' => $brand,
                    'gender' => $gender,
                    'min_price' => $minPriceInput,
                    'max_price' => $maxPriceInput,
                    'page' => min($totalPages, $page + 1)
                ]);
                ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="/products.php?<?= $nextQuery ?>">
                        Sau &raquo;
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</main>

<script>
    const minPriceSlider = document.getElementById('min_price');
    const maxPriceSlider = document.getElementById('max_price');
    const priceRangeTrack = document.querySelector('.price-range-sliders');
    const minPriceLabel = document.getElementById('minPriceLabel');
    const maxPriceLabel = document.getElementById('maxPriceLabel');
    const formatPrice = (price) => new Intl.NumberFormat('vi-VN').format(price) + ' đ';

    function updatePriceRange(changedSlider) {
        let minPrice = Number(minPriceSlider.value);
        let maxPrice = Number(maxPriceSlider.value);

        if (minPrice > maxPrice) {
            if (changedSlider === minPriceSlider) {
                minPriceSlider.value = maxPrice;
                minPrice = maxPrice;
            } else {
                maxPriceSlider.value = minPrice;
                maxPrice = minPrice;
            }
        }

        minPriceLabel.textContent = formatPrice(minPrice);
        maxPriceLabel.textContent = formatPrice(maxPrice);

        const range = Number(maxPriceSlider.max) - Number(minPriceSlider.min);
        const startPercent = range === 0
            ? 0
            : ((minPrice - Number(minPriceSlider.min)) / range) * 100;
        const endPercent = range === 0
            ? 100
            : ((maxPrice - Number(minPriceSlider.min)) / range) * 100;
        priceRangeTrack.style.setProperty('--range-start', startPercent + '%');
        priceRangeTrack.style.setProperty('--range-end', endPercent + '%');
    }

    minPriceSlider.addEventListener('input', () => updatePriceRange(minPriceSlider));
    maxPriceSlider.addEventListener('input', () => updatePriceRange(maxPriceSlider));
    updatePriceRange();
</script>

<?php

$result->free();
$stmt->close();

require_once '/var/www/src/includes/frontend/footer.php';
$conn->close();
?>
