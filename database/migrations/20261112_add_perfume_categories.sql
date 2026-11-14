SET NAMES utf8mb4;

INSERT INTO categories (CategoryName, Description)
SELECT 'Nam', 'Nước hoa dành cho nam'
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE CategoryName = 'Nam'
);

INSERT INTO categories (CategoryName, Description)
SELECT 'Nữ', 'Nước hoa dành cho nữ'
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE CategoryName = 'Nữ'
);

INSERT INTO categories (CategoryName, Description)
SELECT 'Unisex', 'Nước hoa phù hợp cho mọi giới tính'
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE CategoryName = 'Unisex'
);

UPDATE products AS p
JOIN categories AS legacy_category
    ON legacy_category.CategoryID = p.CategoryID
SET p.Brand = legacy_category.CategoryName
WHERE (p.Brand IS NULL OR TRIM(p.Brand) = '')
  AND legacy_category.CategoryName IN ('Dior', 'Kilian', 'Chanel');

UPDATE products AS p
JOIN categories AS audience_category
    ON audience_category.CategoryName = p.Gender
SET p.CategoryID = audience_category.CategoryID
WHERE p.Gender IN ('Nam', 'Nữ', 'Unisex');
