<?php
$pageTitle = 'Sửa sản phẩm';
require_once '/var/www/src/config/database.php';

$error = '';

// 1. Kiểm tra và lấy mã ProductID từ thanh địa chỉ (GET)
$productID = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($productID <= 0) {
    die('Mã sản phẩm không hợp lệ.');
}

// ==========================================
// MỤC 5.2: XỬ LÝ POST ĐỔI ẢNH CHÍNH (ĐẶT TRƯỚC)
// ==========================================
if (isset($_POST['set_primary_image'])) {

    $imageID = (int) $_POST['set_primary_image'];

    try {
        $conn->begin_transaction();

        $sqlResetPrimary = "
            UPDATE product_images
            SET IsPrimary = 0
            WHERE ProductID = ?
        ";

        $stmtResetPrimary = $conn->prepare($sqlResetPrimary);
        $stmtResetPrimary->bind_param('i', $productID);
        $stmtResetPrimary->execute();
        $stmtResetPrimary->close();

        $sqlSetPrimary = "
            UPDATE product_images
            SET IsPrimary = 1
            WHERE ProductImageID = ?
              AND ProductID = ?
        ";

        $stmtSetPrimary = $conn->prepare($sqlSetPrimary);
        $stmtSetPrimary->bind_param('ii', $imageID, $productID);
        $stmtSetPrimary->execute();

        if ($stmtSetPrimary->affected_rows !== 1) {
            throw new Exception('Không thể đặt ảnh chính.');
        }

        $stmtSetPrimary->close();
        $conn->commit();

        header(
            'Location: /products/edit.php?id='
            . $productID
            . '&primary_updated=1'
        );
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}
	// ========================================================
// MỤC 6.2: TẠO NHÁNH XỬ LÝ ADD_IMAGES (KIỂM TRA BẢO MẬT)
// ========================================================
if (isset($_POST['add_images'])) {
    $files = $_FILES['product_images'] ?? null;

    if (
        !$files
        || !isset($files['name'])
        || !is_array($files['name'])
    ) {
        $error = 'Vui lòng chọn ít nhất một ảnh.';
    } else {
        $maxSize = 2 * 1024 * 1024;

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        $validImages = [];
        $fileCount = count($files['name']);

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $error = 'Có lỗi xảy ra khi upload ảnh.';
                break;
            }

            if ($files['size'][$i] > $maxSize) {
                $error = 'Mỗi ảnh chỉ được có kích thước tối đa 2 MB.';
                break;
            }

            $mimeType = $finfo->file($files['tmp_name'][$i]);

            if (!isset($extensionMap[$mimeType])) {
                $error = 'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.';
                break;
            }

            $extension = $extensionMap[$mimeType];

            $fileName =
                'product-'
                . bin2hex(random_bytes(8))
                . '.'
                . $extension;

            $validImages[] = [
                'tmp_name'  => $files['tmp_name'][$i],
                'file_name' => $fileName
            ];
        }

        if (!$error && count($validImages) === 0) {
            $error = 'Vui lòng chọn ít nhất một ảnh.';
        }
        
        // ========================================================
        // MỤC 6.3: XÁC ĐỊNH SORTORDER VÀ TRẠNG THÁI ẢNH CHÍNH
        // ========================================================
        if (!$error) {
            $sqlImageState = "
                SELECT
                    COUNT(*) AS ImageCount,
                    COALESCE(MAX(SortOrder), 0) AS MaxSortOrder
                FROM product_images
                WHERE ProductID = ?
            ";

            $stmtImageState = $conn->prepare($sqlImageState);
            $stmtImageState->bind_param('i', $productID);
            $stmtImageState->execute();

            $imageState = $stmtImageState->get_result()->fetch_assoc();

            $stmtImageState->close();

            $imageCount = (int) $imageState['ImageCount'];
            $nextSortOrder = (int) $imageState['MaxSortOrder'] + 1;
            
            // ========================================================
            // MỤC 6.4: LƯU ẢNH BẰNG TRANSACTION
            // ========================================================
            $movedFiles = [];

            try {
                $conn->begin_transaction();

                $sqlInsertImage = "
                    INSERT INTO product_images
                    (
                        ProductID,
                        ImageFile,
                        AltText,
                        IsPrimary,
                        SortOrder
                    )
                    VALUES (?, ?, ?, ?, ?)
                ";

                $stmtInsertImage = $conn->prepare($sqlInsertImage);

                foreach ($validImages as $index => $image) {
                    $destination =
                        '/var/www/html/uploads/products/'
                        . $image['file_name'];

                    if (!move_uploaded_file(
                        $image['tmp_name'],
                        $destination
                    )) {
                        throw new Exception(
                            'Không thể lưu một trong các ảnh.'
                        );
                    }

                    $movedFiles[] = $destination;

                    $isPrimary =
                        ($imageCount === 0 && $index === 0)
                        ? 1
                        : 0;

                    $sortOrder = $nextSortOrder + $index;

                    // Lưu ý: Đoạn mã sử dụng đúng tên biến $product kế thừa từ block đọc dữ liệu của HO05 cũ
                    $altText =
                        $product['ProductName']
                        . (
                            $isPrimary === 1
                            ? ' - ảnh chính'
                            : ' - ảnh ' . $sortOrder
                        );

                    $stmtInsertImage->bind_param(
                        'issii',
                        $productID,
                        $image['file_name'],
                        $altText,
                        $isPrimary,
                        $sortOrder
                    );

                    if (!$stmtInsertImage->execute()) {
                        throw new Exception(
                            'Không thể lưu thông tin ảnh.'
                        );
                    }
                }

                $stmtInsertImage->close();
                $conn->commit();

                header(
                    'Location: /products/edit.php?id='
                    . $productID
                    . '&images_added=1'
                );
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
	// ========================================================
// MỤC 10: GHÉP HOÀN CHỈNH NHÁNH XỬ LÝ XÓA ẢNH (TRANSACTION)
// ========================================================
if (isset($_POST['delete_image'])) {
    $imageID = (int) $_POST['delete_image'];

    try {
        $conn->begin_transaction();

        $sqlImage = "
            SELECT
                ProductImageID,
                ImageFile,
                IsPrimary,
                SortOrder
            FROM product_images
            WHERE ProductImageID = ?
              AND ProductID = ?
        ";

        $stmtImage = $conn->prepare($sqlImage);
        $stmtImage->bind_param(
            'ii',
            $imageID,
            $productID
        );
        $stmtImage->execute();

        $imageToDelete =
            $stmtImage->get_result()->fetch_assoc();

        $stmtImage->close();

        if (!$imageToDelete) {
            throw new Exception(
                'Không tìm thấy ảnh cần xóa.'
            );
        }

        $sqlDelete = "
            DELETE FROM product_images
            WHERE ProductImageID = ?
              AND ProductID = ?
        ";

        $stmtDelete = $conn->prepare($sqlDelete);
        $stmtDelete->bind_param(
            'ii',
            $imageID,
            $productID
        );
        $stmtDelete->execute();

        if ($stmtDelete->affected_rows !== 1) {
            throw new Exception(
                'Không thể xóa ảnh.'
            );
        }

        $stmtDelete->close();

        if ((int) $imageToDelete['IsPrimary'] === 1) {
            $sqlNewPrimary = "
                UPDATE product_images
                SET IsPrimary = 1
                WHERE ProductImageID = (
                    SELECT ProductImageID
                    FROM (
                        SELECT ProductImageID
                        FROM product_images
                        WHERE ProductID = ?
                        ORDER BY
                            SortOrder,
                            ProductImageID
                        LIMIT 1
                    ) AS remaining_images
                )
            ";

            $stmtNewPrimary =
                $conn->prepare($sqlNewPrimary);

            $stmtNewPrimary->bind_param(
                'i',
                $productID
            );

            $stmtNewPrimary->execute();
            $stmtNewPrimary->close();
        }

        $deletedSortOrder =
            (int) $imageToDelete['SortOrder'];

        $sqlReorder = "
            UPDATE product_images
            SET SortOrder = SortOrder - 1
            WHERE ProductID = ?
              AND SortOrder > ?
        ";

        $stmtReorder = $conn->prepare($sqlReorder);
        $stmtReorder->bind_param(
            'ii',
            $productID,
            $deletedSortOrder
        );
        $stmtReorder->execute();
        $stmtReorder->close();

        $conn->commit();

        $filePath =
            '/var/www/html/uploads/products/'
            . $imageToDelete['ImageFile'];

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        header(
            'Location: /products/edit.php?id='
            . $productID
            . '&image_deleted=1'
        );
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}



// 2. Lấy dữ liệu động cho các ô chọn Category và Supplier
$sqlCategories = "SELECT CategoryID, CategoryName FROM categories ORDER BY CategoryName";
$categories = $conn->query($sqlCategories);

$sqlSuppliers = "SELECT SupplierID, SupplierName FROM suppliers ORDER BY SupplierName";
$suppliers = $conn->query($sqlSuppliers);

// 3. Đọc dữ liệu hiện tại của sản phẩm cần sửa
$sqlProduct = "SELECT ProductID, ProductCode, ProductName, Unit, Price, StockQuantity, IsActive, SupplierID, CategoryID FROM products WHERE ProductID = ?";
$stmtProduct = $conn->prepare($sqlProduct);
$stmtProduct->bind_param('i', $productID);
$stmtProduct->execute();
$product = $stmtProduct->get_result()->fetch_assoc();
$stmtProduct->close();

if (!$product) {
    die('Không tìm thấy sản phẩm yêu cầu.');
}

// 4. Đọc danh sách ảnh của sản phẩm (Mục 3)
$sqlImages = "
    SELECT
        ProductImageID,
        ImageFile,
        AltText,
        IsPrimary,
        SortOrder
    FROM product_images
    WHERE ProductID = ?
    ORDER BY SortOrder, ProductImageID
";

$stmtImages = $conn->prepare($sqlImages);
$stmtImages->bind_param('i', $productID);
$stmtImages->execute();
$productImages = $stmtImages->get_result();
$stmtImages->close();

require_once '/var/www/src/includes/header.php';
require_once '/var/www/src/includes/navbar.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Chỉnh sửa sản phẩm</h2>
	 <?php if (
        isset($_GET['primary_updated'])
        && $_GET['primary_updated'] === '1'
    ): ?>
        <div class="alert alert-success">
            Đã cập nhật ảnh chính.
        </div>
    <?php endif; ?>
	    <!-- ======================================================== -->
    <!-- MỤC 10.2: FEEDBACK XÓA ẢNH THÀNH CÔNG                    -->
    <!-- ======================================================== -->
    <?php if (
        isset($_GET['image_deleted'])
        && $_GET['image_deleted'] === '1'
    ): ?>
        <div class="alert alert-success">
            Đã xóa hình ảnh sản phẩm.
        </div>
    <?php endif; ?>
    <!-- ======================================================== -->
	
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <!-- KHỐI THÔNG TIN SẢN PHẨM -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="productCode" class="form-label">Mã sản phẩm</label>
                <input type="text" class="form-control" id="productCode" name="product_code" value="<?= htmlspecialchars($_POST['product_code'] ?? $product['ProductCode']) ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="productName" class="form-label">Tên sản phẩm</label>
                <input type="text" class="form-control" id="productName" name="product_name" value="<?= htmlspecialchars($_POST['product_name'] ?? $product['ProductName']) ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label for="unit" class="form-label">Đơn vị tính</label>
                <input type="text" class="form-control" id="unit" name="unit" value="<?= htmlspecialchars($_POST['unit'] ?? $product['Unit'] ?? 'Chiếc') ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="categoryID" class="form-label">Danh mục</label>
                <select class="form-select" id="categoryID" name="category_id" required>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['CategoryID'] ?>" <?= (int)($_POST['category_id'] ?? $product['CategoryID']) === (int)$cat['CategoryID'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['CategoryName']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="supplierID" class="form-label">Nhà cung cấp</label>
                <select class="form-select" id="supplierID" name="supplier_id" required>
                    <?php while ($sup = $suppliers->fetch_assoc()): ?>
                        <option value="<?= $sup['SupplierID'] ?>" <?= (int)($_POST['supplier_id'] ?? $product['SupplierID']) === (int)$sup['SupplierID'] ? 'selected' : '' ?>><?= htmlspecialchars($sup['SupplierName']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="price" class="form-label">Giá bán (VNĐ)</label>
                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?= htmlspecialchars($_POST['price'] ?? $product['Price']) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="stockQuantity" class="form-label">Số lượng tồn kho</label>
                <input type="number" class="form-control" id="stockQuantity" name="stock_quantity" value="<?= htmlspecialchars($_POST['stock_quantity'] ?? $product['StockQuantity']) ?>" required>
            </div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="isActive" name="is_active" value="1" <?= (isset($_POST['is_active']) ? $_POST['is_active'] : $product['IsActive']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Kích hoạt trạng thái đang bán</label>
        </div>

        <!-- KHỐI HIỂN THỊ ẢNH -->
        <hr class="my-4">
        <h4 class="mb-3">Hình ảnh sản phẩm</h4>

        <div class="row">
            <?php while ($image = $productImages->fetch_assoc()): ?>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <img
                            src="/uploads/products/<?= htmlspecialchars($image['ImageFile']) ?>"
                            class="card-img-top"
                            alt="<?= htmlspecialchars($image['AltText'] ?? '') ?>"
                        >

                        <div class="card-body d-flex flex-column justify-content-between">
                            <div>
                                <small class="text-muted">
                                    Thứ tự: <?= $image['SortOrder'] ?>
                                </small>

                                <?php if ((int) $image['IsPrimary'] === 1): ?>
                                    <div class="mt-2 mb-2">
                                        <span class="badge bg-success">Ảnh chính</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- ========================================== -->
                            <!-- MỤC 5.1: THÊM NÚT TRÊN ẢNH PHỤ -->
                            <!-- ========================================== -->
                            <?php if ((int) $image['IsPrimary'] !== 1): ?>
                                <div class="mt-2">
                                    <button
                                        type="submit"
                                        class="btn btn-outline-primary btn-sm w-100"
                                        name="set_primary_image"
                                        value="<?= $image['ProductImageID'] ?>"
                                        formaction="/products/edit.php?id=<?= $productID ?>"
                                        formmethod="post"
                                    >
                                        Đặt làm ảnh chính
                                                    </button>
                                </div>
                            <?php endif; ?>
				<!-- ======================================================== -->
                                <!-- MỤC 7.1: THÊM NÚT XÓA ẢNH (HIỆN Ở CẢ ẢNH CHÍNH & PHỤ)     -->
                                <!-- ======================================================== -->
                                <button
                                    type="submit"
                                    class="btn btn-outline-danger btn-sm <?= (int)$image['IsPrimary'] === 1 ? 'w-100' : 'ms-2 flex-grow-1' ?>"
                                    name="delete_image"
                                    value="<?= $image['ProductImageID'] ?>"
                                    formaction="/products/edit.php?id=<?= $productID ?>"
                                    formmethod="post"
                                    onclick="return confirm('Bạn có chắc muốn xóa ảnh này?');"
                                >
                                    Xóa ảnh
                                </button>
                            <!-- ========================================== -->
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
                <!-- ======================================================== -->
        <!-- MỤC 6.1: BỔ SUNG INPUT NHIỀU ẢNH                         -->
        <!-- ======================================================== -->
        <hr class="my-4">
        <div class="mb-3">
            <label for="productImages" class="form-label">
                Thêm hình ảnh
            </label>

            <input
                type="file"
                class="form-control"
                id="productImages"
                name="product_images[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
            >

            <div class="form-text">
                Chấp nhận JPG, PNG hoặc WebP.
                Mỗi ảnh tối đa 2 MB.
            </div>
        </div>

        <button
            type="submit"
            class="btn btn-outline-success mb-4"
            name="add_images"
            value="1"
            formaction="/products/edit.php?id=<?= $productID ?>"
            formmethod="post"
        >
            Thêm ảnh
        </button>
        <!-- ======================================================== -->


        <hr class="my-4">
        <button type="submit" class="btn btn-warning">Cập nhật sản phẩm</button>
        <a href="/products/" class="btn btn-secondary">Hủy</a>
    </form>
</div>

<?php
require_once '/var/www/src/includes/footer.php';
$conn->close();
?>
