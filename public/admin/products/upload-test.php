<?php
$pageTitle = 'Kiểm tra Nhiều Ảnh';
require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<div class="container mt-4">';
    echo '<h4>Dữ liệu nhận được trong $_FILES[\'product_images\']</h4>';
    echo '<pre>';
    print_r($_FILES['product_images'] ?? []);
    echo '</pre>';
    echo '<a href="/admin/products/upload-test.php" class="btn btn-secondary mt-3">Quay lại thử tiếp</a>';
    echo '</div>';
    require_once '/var/www/src/includes/admin/footer.php';
    exit; // Dừng hệ thống ở đây để quan sát mảng giống hệt yêu cầu đề bài
}
?>

<div class="container mt-4">
    <h2>Kiểm tra Upload Nhiều Ảnh</h2>

    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="productImages" class="form-label">Chọn ảnh sản phẩm</label>
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
                Chọn từ 1 đến 4 ảnh. Chấp nhận JPG, PNG hoặc WebP. Mỗi ảnh tối đa 2 MB. Ảnh đầu tiên là ảnh chính.
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Gửi tất cả file</button>
    </form>
</div>

<?php
require_once '/var/www/src/includes/admin/footer.php';
?>
