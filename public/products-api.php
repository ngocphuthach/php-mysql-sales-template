<?php

require_once '/var/www/src/config/session.php';

$pageTitle = 'Sản phẩm từ REST API';

require_once '/var/www/src/includes/frontend/header.php';
require_once '/var/www/src/includes/frontend/navbar.php';

?>

<div class="container py-4">
    <h1 class="h3 mb-4">
        Sản phẩm từ REST API
    </h1>

    <form id="filter-form" class="row g-3 mb-4">
        <div class="col-md-6">
            <label for="keyword" class="form-label">
                Từ khóa
            </label>

            <input
                type="text"
                id="keyword"
                class="form-control"
                placeholder="Tên hoặc mã sản phẩm"
            >
        </div>

        <div class="col-md-4">
            <label for="category" class="form-label">
                Danh mục
            </label>

            <select id="category" class="form-select">
                <option value="">
                    Tất cả danh mục
                </option>
            </select>
        </div>

        <div class="col-md-4">
            <label for="brand" class="form-label">Thương hiệu</label>
            <input
                type="text"
                id="brand"
                class="form-control"
                placeholder="Nhập thương hiệu"
            >
        </div>

        <div class="col-md-4">
            <label for="gender" class="form-label">Đối tượng</label>
            <select id="gender" class="form-select">
                <option value="">Tất cả</option>
                <option value="Nam">Nam</option>
                <option value="Nữ">Nữ</option>
                <option value="Unisex">Unisex</option>
            </select>
        </div>

        <div class="col-md-3">
            <label for="min-price" class="form-label">Giá từ</label>
            <input type="number" min="0" id="min-price" class="form-control">
        </div>

        <div class="col-md-3">
            <label for="max-price" class="form-label">Đến giá</label>
            <input type="number" min="0" id="max-price" class="form-control">
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                Tìm kiếm
            </button>
        </div>
    </form>

    <div id="loading" class="alert alert-info d-none">
        Đang tải dữ liệu sản phẩm...
    </div>

    <div
        id="error-message"
        class="alert alert-danger d-none"
    ></div>

    <div id="product-list" class="row g-4"></div>
</div>

<script>
const filterForm = document.getElementById('filter-form');
const keywordElement = document.getElementById('keyword');
const categoryElement = document.getElementById('category');
const brandElement = document.getElementById('brand');
const genderElement = document.getElementById('gender');
const minPriceElement = document.getElementById('min-price');
const maxPriceElement = document.getElementById('max-price');
const loadingElement = document.getElementById('loading');
const errorElement = document.getElementById('error-message');
const productListElement = document.getElementById('product-list');

function formatPrice(price) {
    return Number(price).toLocaleString('vi-VN') + ' đ';
}

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value ?? '');
    return element.innerHTML;
}

function createProductCard(product) {
    const productName = escapeHtml(product.name);
    const productCode = escapeHtml(product.code);
    const categoryName = escapeHtml(product.category);
    const brandName = escapeHtml(product.brand);
    const perfumeDetails = [
        product.volume ? `${escapeHtml(product.volume)} ml` : '',
        product.concentration ? escapeHtml(product.concentration) : '',
        product.gender ? escapeHtml(product.gender) : ''
    ].filter(Boolean).join(' · ');

    const imageUrl = product.image
        ? '/uploads/products/' + encodeURIComponent(product.image)
        : '';

    const imageHtml = imageUrl
        ? `
            <img
                src="${imageUrl}"
                class="card-img-top"
                alt="${productName}"
                style="height: 220px; object-fit: contain;"
            >
        `
        : `
            <div
                class="d-flex align-items-center justify-content-center bg-light text-muted"
                style="height: 220px;"
            >
                Chưa có ảnh
            </div>
        `;

    return `
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                ${imageHtml}

                <div class="card-body">
                    <div class="text-muted small mb-1">
                        ${categoryName}
                    </div>

                    <h2 class="h5">
                        ${productName}
                    </h2>

                    <div class="mb-2">
                        Mã: ${productCode}
                    </div>

                    ${brandName ? `<div class="text-muted small">${brandName}</div>` : ''}
                    ${perfumeDetails ? `<div class="text-muted small mb-2">${perfumeDetails}</div>` : ''}

                    <div class="fw-bold mb-2">
                        ${formatPrice(product.sellingPrice)}
                        ${product.discount > 0
                            ? `<span class="text-muted small text-decoration-line-through">${formatPrice(product.price)}</span>
                               <span class="badge bg-danger">-${escapeHtml(product.discount)}%</span>`
                            : ''}
                    </div>

                    <div class="mb-3">
                        Tồn kho: ${product.stock}
                    </div>

                    <a
                        href="/product-detail.php?id=${product.id}"
                        class="btn btn-primary"
                    >
                        Xem chi tiết
                    </a>
                </div>
            </div>
        </div>
    `;
}

async function loadCategories() {
    try {
        const response = await fetch('/api/categories.php');

        if (!response.ok) {
            throw new Error('Không thể tải danh mục.');
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error('Dữ liệu danh mục không hợp lệ.');
        }

        result.data.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            categoryElement.appendChild(option);
        });

    } catch (error) {
        errorElement.textContent = error.message;
        errorElement.classList.remove('d-none');
    }
}

async function loadProducts() {
    loadingElement.classList.remove('d-none');
    errorElement.classList.add('d-none');
    productListElement.innerHTML = '';

    const keyword = keywordElement.value.trim();
    const category = categoryElement.value;
    const brand = brandElement.value.trim();
    const gender = genderElement.value;
    const minPrice = minPriceElement.value;
    const maxPrice = maxPriceElement.value;

    const params = new URLSearchParams();

    if (keyword !== '') {
        params.set('keyword', keyword);
    }

    if (category !== '') {
        params.set('category', category);
    }

    if (brand !== '') {
        params.set('brand', brand);
    }

    if (gender !== '') {
        params.set('gender', gender);
    }

    if (minPrice !== '') {
        params.set('min_price', minPrice);
    }

    if (maxPrice !== '') {
        params.set('max_price', maxPrice);
    }

    let apiUrl = '/api/products.php';

    if (params.toString() !== '') {
        apiUrl += '?' + params.toString();
    }

    try {
        const response = await fetch(apiUrl);

        if (!response.ok) {
            throw new Error('Không thể tải dữ liệu sản phẩm.');
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error('API trả về kết quả không hợp lệ.');
        }

        if (result.data.length === 0) {
            productListElement.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-warning">
                        Không tìm thấy sản phẩm phù hợp.
                    </div>
                </div>
            `;

            return;
        }

        productListElement.innerHTML =
            result.data.map(createProductCard).join('');

    } catch (error) {
        errorElement.textContent = error.message;
        errorElement.classList.remove('d-none');

    } finally {
        loadingElement.classList.add('d-none');
    }
}

filterForm.addEventListener('submit', function (event) {
    event.preventDefault();
    loadProducts();
});

async function initializePage() {
    await loadCategories();
    await loadProducts();
}

initializePage();
</script>

<?php

require_once '/var/www/src/includes/frontend/footer.php';

?>
