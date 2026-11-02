<?php

require_once '/var/www/src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$sql = "
    SELECT
        CategoryID,
        CategoryName
    FROM categories
    ORDER BY CategoryName
";

$result = $conn->query($sql);

if ($result === false) {
    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' => 'Không thể truy vấn danh mục.'
        ],
        JSON_UNESCAPED_UNICODE
    );

    $conn->close();
    exit;
}

$categories = [];

while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'id' => (int) $row['CategoryID'],
        'name' => $row['CategoryName']
    ];
}

$result->free();
$conn->close();

echo json_encode(
    [
        'success' => true,
        'data' => $categories
    ],
    JSON_UNESCAPED_UNICODE
);
