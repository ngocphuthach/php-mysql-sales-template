<?php

require_once '/var/www/src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$keyword = trim($_GET['keyword'] ?? '');
$categoryID = (int) ($_GET['category'] ?? 0);

$sql = "
    SELECT
        p.ProductID,
        p.ProductCode,
        p.ProductName,
        p.Price,
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
        'stock' => (int) $row['StockQuantity'],
        'category' => $row['CategoryName'],
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
