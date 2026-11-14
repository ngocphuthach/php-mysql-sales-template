<?php

require_once '/var/www/src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$keyword = trim($_GET['keyword'] ?? '');
$categoryID = (int) ($_GET['category'] ?? 0);
$brand = trim($_GET['brand'] ?? '');
$gender = trim($_GET['gender'] ?? '');
$minPrice = filter_var($_GET['min_price'] ?? null, FILTER_VALIDATE_FLOAT);
$maxPrice = filter_var($_GET['max_price'] ?? null, FILTER_VALIDATE_FLOAT);

if (!in_array($gender, ['', 'Nam', 'Nữ', 'Unisex'], true)) {
    $gender = '';
}

if ($minPrice === false || $minPrice < 0) {
    $minPrice = null;
}

if ($maxPrice === false || $maxPrice < 0) {
    $maxPrice = null;
}

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
        p.StockQuantity,
        c.CategoryName,
        (
            SELECT pi.ImageFile
            FROM product_images pi
            WHERE pi.ProductID = p.ProductID
              AND pi.IsPrimary = 1
            LIMIT 1
        ) AS ImageFile
    FROM products p
    INNER JOIN categories c ON p.CategoryID = c.CategoryID
    WHERE p.IsActive = 1
      AND c.IsActive = 1
";

$params = [];
$types = '';

if ($keyword !== '') {
    $searchKeyword = '%' . $keyword . '%';

    $sql .= "
        AND (
            p.ProductName LIKE ?
            OR p.ProductCode LIKE ?
        )
    ";

    $params[] = $searchKeyword;
    $params[] = $searchKeyword;
    $types .= 'ss';
}

if ($categoryID > 0) {
    $sql .= "
        AND p.CategoryID = ?
    ";

    $params[] = $categoryID;
    $types .= 'i';
}

if ($brand !== '') {
    $sql .= ' AND p.Brand = ?';
    $params[] = $brand;
    $types .= 's';
}

if ($gender !== '') {
    $sql .= ' AND p.Gender = ?';
    $params[] = $gender;
    $types .= 's';
}

if ($minPrice !== null) {
    $sql .= ' AND p.Price * (1 - p.Discount / 100) >= ?';
    $params[] = $minPrice;
    $types .= 'd';
}

if ($maxPrice !== null) {
    $sql .= ' AND p.Price * (1 - p.Discount / 100) <= ?';
    $params[] = $maxPrice;
    $types .= 'd';
}

$sql .= "
    ORDER BY p.ProductID DESC
";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' => 'Không thể chuẩn bị truy vấn sản phẩm.'
        ],
        JSON_UNESCAPED_UNICODE
    );

    $conn->close();
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' => 'Không thể thực thi truy vấn sản phẩm.'
        ],
        JSON_UNESCAPED_UNICODE
    );

    $stmt->close();
    $conn->close();
    exit;
}

$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = [
        'id' => (int) $row['ProductID'],
        'code' => $row['ProductCode'],
        'name' => $row['ProductName'],
        'price' => (float) $row['Price'],
        'sellingPrice' => (float) $row['SellingPrice'],
        'discount' => (float) $row['Discount'],
        'stock' => (int) $row['StockQuantity'],
        'category' => $row['CategoryName'],
        'brand' => $row['Brand'],
        'volume' => $row['Volume'] !== null ? (float) $row['Volume'] : null,
        'concentration' => $row['Concentration'],
        'gender' => $row['Gender'],
        'image' => $row['ImageFile']
    ];
}

$result->free();
$stmt->close();
$conn->close();

echo json_encode(
    [
        'success' => true,
        'data' => $products
    ],
    JSON_UNESCAPED_UNICODE
);
