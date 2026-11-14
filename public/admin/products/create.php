<?php

$pageTitle = 'Thêm sản phẩm';

require_once '/var/www/src/config/auth.php';
requireEmployeeRole(['admin']);
require_once '/var/www/src/config/database.php';

$error = '';

$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    WHERE CategoryName IN ('Nam', 'Nữ', 'Unisex')
    ORDER BY CategoryName
";

$categoryResult = $conn->query($sqlCategories);
$categories = $categoryResult->fetch_all(MYSQLI_ASSOC);

$brandResult = $conn->query("
    SELECT DISTINCT Brand
    FROM products
    WHERE Brand IS NOT NULL AND TRIM(Brand) <> ''
    ORDER BY Brand
");
$brands = $brandResult->fetch_all(MYSQLI_ASSOC);

$sqlSuppliers = "
    SELECT SupplierID, SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $conn->query($sqlSuppliers);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $volumeInput = trim($_POST['volume'] ?? '');
    $volume = $volumeInput === '' ? null : (float) $volumeInput;
    $concentration = trim($_POST['concentration'] ?? '');
    $gender = '';
    $origin = trim($_POST['origin'] ?? '');

    $price = (float) ($_POST['price'] ?? 0);
    $discount = (float) ($_POST['discount'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);
    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);
    $categoryName = null;

    foreach ($categories as $category) {
        if ((int) $category['CategoryID'] === $categoryID) {
            $categoryName = $category['CategoryName'];
            break;
        }
    }

    if ($categoryName !== null) {
        $gender = $categoryName;
    }

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $files = $_FILES['product_images'] ?? null;

    if ($productCode === '') {
        $error = 'Mã sản phẩm không được để trống.';
    } elseif ($productName === '') {
        $error = 'Tên sản phẩm không được để trống.';
    } elseif ($price < 0) {
        $error = 'Giá sản phẩm không hợp lệ.';
    } elseif ($discount < 0 || $discount > 100) {
        $error = 'Mức giảm giá phải từ 0 đến 100%.';
    } elseif (
        $volumeInput !== ''
        && !in_array($volumeInput, ['50', '75', '100'], true)
    ) {
        $error = 'Vui lòng chọn dung tích 50, 75 hoặc 100 ml.';
    } elseif ($stockQuantity < 0) {
        $error = 'Số lượng tồn kho không hợp lệ.';
    } elseif ($categoryName === null) {
        $error = 'Vui lòng chọn danh mục.';
    } elseif ($supplierID <= 0) {
        $error = 'Vui lòng chọn nhà cung cấp.';
    } elseif (
        !$files
        || !isset($files['name'])
        || !is_array($files['name'])
    ) {
        $error = 'Vui lòng chọn ảnh sản phẩm.';
    } else {

        $fileCount = count($files['name']);

        if ($fileCount < 1 || $fileCount > 4) {

            $error = 'Chỉ được chọn từ 1 đến 4 ảnh.';

        } else {

            $maxSize = 2 * 1024 * 1024;

            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $preparedImages = [];

            for ($i = 0; $i < $fileCount; $i++) {

                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $error = 'Có file ảnh upload không thành công.';
                    break;
                }

                if ($files['size'][$i] > $maxSize) {
                    $error = 'Mỗi file ảnh không được vượt quá 2 MB.';
                    break;
                }

                $mimeType = $finfo->file(
                    $files['tmp_name'][$i]
                );

                if (!isset($extensionMap[$mimeType])) {
                    $error = 'Chỉ cho phép file JPG, PNG hoặc WebP.';
                    break;
                }

                $extension = $extensionMap[$mimeType];

                $newFileName =
                    'product-'
                    . bin2hex(random_bytes(8))
                    . '.'
                    . $extension;

                $preparedImages[] = [
                    'tmp_name'   => $files['tmp_name'][$i],
                    'file_name'  => $newFileName,
                    'is_primary' => ($i === 0) ? 1 : 0,
                    'sort_order' => $i + 1
                ];
            }

            if ($error === '') {

                $movedFiles = [];

                try {

                    $conn->begin_transaction();

                    $sql = "
                        INSERT INTO products
                        (
                            ProductCode,
                            ProductName,
                            Description,
                            Unit,
                            Brand,
                            Volume,
                            Concentration,
                            Gender,
                            Origin,
                            Price,
                            Discount,
                            StockQuantity,
                            IsActive,
                            SupplierID,
                            CategoryID
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";

                    $stmt = $conn->prepare($sql);

                    $stmt->bind_param(
                        'sssssdsssddiiii',
                        $productCode,
                        $productName,
                        $description,
                        $unit,
                        $brand,
                        $volume,
                        $concentration,
                        $gender,
                        $origin,
                        $price,
                        $discount,
                        $stockQuantity,
                        $isActive,
                        $supplierID,
                        $categoryID
                    );

                    if (!$stmt->execute()) {
                        throw new Exception(
                            'Không thể thêm sản phẩm.'
                        );
                    }

                    $productID = $conn->insert_id;
                    $stmt->close();

                    $sqlImage = "
                        INSERT INTO product_images
                        (
                            ProductID,
                            ImageFile,
                            AltText,
                            IsPrimary,
                            SortOrder
                        )
                        VALUES
                        (?, ?, ?, ?, ?)
                    ";

                    $stmtImage = $conn->prepare($sqlImage);

                    foreach ($preparedImages as $index => $image) {

                        $destination =
                            '/var/www/html/uploads/products/'
                            . $image['file_name'];

                        if (!move_uploaded_file(
                            $image['tmp_name'],
                            $destination
                        )) {
                            throw new Exception(
                                'Không thể lưu một trong các file ảnh.'
                            );
                        }

                        $movedFiles[] = $destination;

                        if ($image['is_primary'] === 1) {
                            $altText =
                                $productName . ' - ảnh chính';
                        } else {
                            $altText =
                                $productName
                                . ' - ảnh '
                                . ($index + 1);
                        }

                        $imageFile = $image['file_name'];
                        $isPrimary = $image['is_primary'];
                        $sortOrder = $image['sort_order'];

                        $stmtImage->bind_param(
                            'issii',
                            $productID,
                            $imageFile,
                            $altText,
                            $isPrimary,
                            $sortOrder
                        );

                        if (!$stmtImage->execute()) {
                            throw new Exception(
                                'Không thể lưu thông tin ảnh.'
                            );
                        }
                    }

                    $stmtImage->close();
                    $conn->commit();

                    header('Location: /admin/products/');
                    exit;

                } catch (Throwable $e) {

                    $conn->rollback();

                    foreach ($movedFiles as $movedFile) {
                        if (file_exists($movedFile)) {
                            unlink($movedFile);
                        }
                    }

                    $error = $e->getMessage();
                }
            }
        }
    }
}

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

?>

<div class="container mt-4">

    <h2 class="mb-4">Thêm sản phẩm</h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

        <div class="row">

            <div class="col-md-4 mb-3">
                <label for="productCode" class="form-label">
                    Mã sản phẩm
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="productCode"
                    name="product_code"
                    value="<?= htmlspecialchars(
                        $_POST['product_code'] ?? ''
                    ) ?>"
                    required
                >
            </div>

            <div class="col-md-8 mb-3">
                <label for="productName" class="form-label">
                    Tên sản phẩm
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="productName"
                    name="product_name"
                    value="<?= htmlspecialchars(
                        $_POST['product_name'] ?? ''
                    ) ?>"
                    required
                >
            </div>

        </div>

        <div class="mb-3">
            <label for="description" class="form-label">
                Mô tả
            </label>
            <textarea
                class="form-control"
                id="description"
                name="description"
                rows="3"
            ><?= htmlspecialchars(
                $_POST['description'] ?? ''
            ) ?></textarea>
        </div>

        <div class="row">

            <div class="col-md-6 mb-3">
                <label for="unit" class="form-label">
                    Đơn vị tính
                </label>
                <input
                    type="text"
                    class="form-control"
                    id="unit"
                    name="unit"
                    value="<?= htmlspecialchars(
                        $_POST['unit'] ?? ''
                    ) ?>"
                >
            </div>

            <div class="col-md-4 mb-3">
                <label for="price" class="form-label">
                    Giá niêm yết (VNĐ)
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="price"
                    name="price"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars(
                        $_POST['price'] ?? '0'
                    ) ?>"
                    required
                >
            </div>

            <div class="col-md-4 mb-3">
                <label for="discount" class="form-label">
                    Giảm giá (%)
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="discount"
                    name="discount"
                    min="0"
                    max="100"
                    step="0.01"
                    value="<?= htmlspecialchars($_POST['discount'] ?? '0') ?>"
                >
            </div>

        </div>

        <div class="row">

            <div class="col-md-4 mb-3">
                <label for="brand" class="form-label">Thương hiệu</label>
                <input
                    type="text"
                    class="form-control"
                    id="brand"
                    name="brand"
                    list="brandSuggestions"
                    maxlength="100"
                    placeholder="Nhập hoặc chọn thương hiệu"
                    value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>"
                >
                <datalist id="brandSuggestions">
                    <?php foreach ($brands as $brandOption): ?>
                        <option value="<?= htmlspecialchars($brandOption['Brand']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-md-4 mb-3">
                <label for="volume" class="form-label">Dung tích (ml)</label>
                <select class="form-select" id="volume" name="volume">
                    <option value="">-- Chọn dung tích --</option>
                    <?php foreach (['50', '75', '100'] as $volumeOption): ?>
                        <option
                            value="<?= $volumeOption ?>"
                            <?= (float) ($_POST['volume'] ?? 0) === (float) $volumeOption ? 'selected' : '' ?>
                        >
                            <?= $volumeOption ?> ml
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label for="concentration" class="form-label">Nồng độ</label>
                <select class="form-select" id="concentration" name="concentration">
                    <option value="">-- Chọn nồng độ --</option>
                    <?php foreach (['EDC', 'EDT', 'EDP', 'Parfum', 'Extrait'] as $option): ?>
                        <option
                            value="<?= htmlspecialchars($option) ?>"
                            <?= ($_POST['concentration'] ?? '') === $option ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>

        <div class="row">

            <div class="col-md-12 mb-3">
                <label for="origin" class="form-label">Xuất xứ</label>
                <input
                    type="text"
                    class="form-control"
                    id="origin"
                    name="origin"
                    value="<?= htmlspecialchars($_POST['origin'] ?? '') ?>"
                >
            </div>

        </div>

        <div class="row">

            <div class="col-md-6 mb-3">
                <label for="stockQuantity" class="form-label">
                    Tồn kho
                </label>
                <input
                    type="number"
                    class="form-control"
                    id="stockQuantity"
                    name="stock_quantity"
                    min="0"
                    value="<?= htmlspecialchars(
                        $_POST['stock_quantity'] ?? '0'
                    ) ?>"
                    required
                >
            </div>

        </div>

        <div class="row">

            <div class="col-md-6 mb-3">
                <label for="categoryID" class="form-label">
                    Danh mục
                </label>

                <select
                    class="form-select"
                    id="categoryID"
                    name="category_id"
                    required
                >
                    <option value="">-- Chọn danh mục --</option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= $category['CategoryID'] ?>"
                            <?= (
                                ($_POST['category_id'] ?? '')
                                == $category['CategoryID']
                            ) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $category['CategoryName']
                            ) ?>
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="supplierID" class="form-label">
                    Nhà cung cấp
                </label>

                <select
                    class="form-select"
                    id="supplierID"
                    name="supplier_id"
                    required
                >
                    <option value="">-- Chọn nhà cung cấp --</option>

                    <?php while (
                        $supplier = $suppliers->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= $supplier['SupplierID'] ?>"
                            <?= (
                                ($_POST['supplier_id'] ?? '')
                                == $supplier['SupplierID']
                            ) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $supplier['SupplierName']
                            ) ?>
                        </option>

                    <?php endwhile; ?>
                </select>
            </div>

        </div>

        <div class="mb-3">
            <label for="productImages" class="form-label">
                Hình ảnh sản phẩm
            </label>

            <input
                type="file"
                class="form-control"
                id="productImages"
                name="product_images[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
                required
            >

            <div class="form-text">
                Chọn từ 1 đến 4 ảnh.
                Chấp nhận JPG, PNG hoặc WebP.
                Mỗi ảnh tối đa 2 MB.
                Ảnh đầu tiên là ảnh chính.
            </div>
        </div>

        <div class="form-check mb-3">
            <input
                type="checkbox"
                class="form-check-input"
                id="isActive"
                name="is_active"
                value="1"
                <?= (
                    isset($_POST['is_active'])
                    || $_SERVER['REQUEST_METHOD'] !== 'POST'
                ) ? 'checked' : '' ?>
            >

            <label class="form-check-label" for="isActive">
                Đang kinh doanh
            </label>
        </div>

        <button type="submit" class="btn btn-primary">
            Lưu
        </button>

        <a href="/admin/products/" class="btn btn-secondary">
            Hủy
        </a>

    </form>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();