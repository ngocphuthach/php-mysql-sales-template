<?php

$pageTitle = 'Thêm sản phẩm';

require_once '/var/www/src/config/database.php';

$error = '';

$sqlCategories = "
    SELECT CategoryID, CategoryName
    FROM categories
    ORDER BY CategoryName
";

$categories = $conn->query($sqlCategories);

$sqlSuppliers = "
    SELECT SupplierID, SupplierName
    FROM suppliers
    ORDER BY SupplierName
";

$suppliers = $conn->query($sqlSuppliers);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $productCode = trim($_POST['product_code'] ?? '');
    $productName = trim($_POST['product_name'] ?? '');
    $unit = trim($_POST['unit'] ?? 'Chiếc');

    $price = (float) ($_POST['price'] ?? 0);
    $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);

    $categoryID = (int) ($_POST['category_id'] ?? 0);
    $supplierID = (int) ($_POST['supplier_id'] ?? 0);

    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($productCode === '') {
        $error = 'Mã sản phẩm không được để trống.';

    } elseif ($productName === '') {
        $error = 'Tên sản phẩm không được để trống.';

    } elseif ($price < 0) {
        $error = 'Giá sản phẩm không hợp lệ.';

    } elseif ($stockQuantity < 0) {
        $error = 'Số lượng tồn kho không hợp lệ.';

    } elseif ($categoryID <= 0) {
        $error = 'Vui lòng chọn danh mục.';

    } elseif ($supplierID <= 0) {
        $error = 'Vui lòng chọn nhà cung cấp.';

    } else {

        $sql = "
            INSERT INTO products
            (
                ProductCode,
                ProductName,
		Unit,
                Price,
                StockQuantity,
                IsActive,
                SupplierID,
                CategoryID
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            'ssdiiii',
            $productCode,
            $productName,
	    $unit,
            $price,
            $stockQuantity,
            $isActive,
            $supplierID,
            $categoryID
        );

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header('Location: /admin/products/');
            exit;
        }

        $error = 'Không thể thêm sản phẩm.';
        $stmt->close();
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

    <form method="post">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="productCode" class="form-label">Mã sản phẩm</label>
                <input type="text" class="form-control" id="productCode" name="product_code" value="<?= htmlspecialchars($_POST['product_code'] ?? '') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="productName" class="form-label">Tên sản phẩm</label>
                <input type="text" class="form-control" id="productName" name="product_name" value="<?= htmlspecialchars($_POST['product_name'] ?? '') ?>" required>
            </div>
	    <div class="col-md-4 mb-3">
                <label for="unit" class="form-label">Đơn vị tính</label>
                <input type="text" class="form-control" id="unit" name="unit" value="<?= htmlspecialchars($_POST['unit'] ?? 'Chiếc') ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="categoryID" class="form-label">Danh mục</label>
                <select class="form-select" id="categoryID" name="category_id" required>
                    <option value="">-- Chọn danh mục --</option>
                    <?php if ($categories): ?>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['CategoryID'] ?>" <?= (int)($_POST['category_id'] ?? 0) === (int)$cat['CategoryID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['CategoryName']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="supplierID" class="form-label">Nhà cung cấp</label>
                <select class="form-select" id="supplierID" name="supplier_id" required>
                    <option value="">-- Chọn nhà cung cấp --</option>
                    <?php if ($suppliers): ?>
                        <?php while ($sup = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $sup['SupplierID'] ?>" <?= (int)($_POST['supplier_id'] ?? 0) === (int)$sup['SupplierID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sup['SupplierName']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="price" class="form-label">Giá bán (VNĐ)</label>
                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="stockQuantity" class="form-label">Số lượng tồn kho</label>
                <input type="number" class="form-control" id="stockQuantity" name="stock_quantity" value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '0') ?>" required>
            </div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="isActive" name="is_active" value="1" <?= isset($_POST['is_active']) || $_SERVER['REQUEST_METHOD'] === 'GET' ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Kích hoạt trạng thái đang bán</label>
        </div>

        <button type="submit" class="btn btn-primary">Lưu sản phẩm</button>
        <a href="/admin/products/" class="btn btn-secondary">Hủy</a>
    </form>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
?>
