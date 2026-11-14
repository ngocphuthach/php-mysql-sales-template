SET NAMES utf8mb4;

CREATE TABLE categories (
    CategoryID INT AUTO_INCREMENT PRIMARY KEY,
    CategoryName VARCHAR(200) NOT NULL,
    Description TEXT,
    ImageUrl VARCHAR(255),
    IsActive BOOLEAN NOT NULL DEFAULT TRUE
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE suppliers (
    SupplierID INT AUTO_INCREMENT PRIMARY KEY,
    SupplierName VARCHAR(200) NOT NULL,
    ContactName VARCHAR(100),
    Address VARCHAR(200),
    City VARCHAR(100),
    PostalCode VARCHAR(20),
    Country VARCHAR(100),
    Phone VARCHAR(20),
    Email VARCHAR(100),
    IsActive BOOLEAN NOT NULL DEFAULT TRUE
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE customers (
    CustomerID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerName VARCHAR(100) NOT NULL,
    ContactName VARCHAR(100),
    Address VARCHAR(200),
    City VARCHAR(100),
    PostalCode VARCHAR(20),
    Country VARCHAR(100),
    Phone VARCHAR(20),
    Email VARCHAR(255) NULL,
    PasswordHash VARCHAR(255) NULL,
    Gender VARCHAR(20),
    BirthDate DATE,
    LoyaltyPoint INT NOT NULL DEFAULT 0,
    CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_customers_loyalty CHECK (LoyaltyPoint >= 0),
    CONSTRAINT uq_customers_email UNIQUE (Email)
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE employees (
    EmployeeID INT AUTO_INCREMENT PRIMARY KEY,
    LastName VARCHAR(50) NOT NULL,
    FirstName VARCHAR(50) NOT NULL,
    BirthDate DATE,
    Photo VARCHAR(255),
    Notes TEXT,
    Email VARCHAR(255) NULL,
    PasswordHash VARCHAR(255) NULL,
    Phone VARCHAR(20),
    Role VARCHAR(30) NOT NULL DEFAULT 'staff',
    HireDate DATE,
    Address VARCHAR(255),
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_employees_email UNIQUE (Email)
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE shippers (
    ShipperID INT AUTO_INCREMENT PRIMARY KEY,
    ShipperName VARCHAR(100) NOT NULL,
    Phone VARCHAR(20),
    Email VARCHAR(100),
    Website VARCHAR(255),
    DefaultFee DECIMAL(12,2) NOT NULL DEFAULT 0,
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT chk_shippers_fee CHECK (DefaultFee >= 0)
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE products (
    ProductID INT AUTO_INCREMENT PRIMARY KEY,
    ProductCode VARCHAR(50) NOT NULL UNIQUE,
    ProductName VARCHAR(255) NOT NULL,
    Description TEXT,
    Unit VARCHAR(20),
    Brand VARCHAR(100),
    Volume DECIMAL(7,2),
    Concentration VARCHAR(50),
    Gender VARCHAR(20),
    Origin VARCHAR(100),
    Price DECIMAL(12,2) NOT NULL DEFAULT 0,
    Discount DECIMAL(5,2) NOT NULL DEFAULT 0,
    StockQuantity INT NOT NULL DEFAULT 0,
    IsActive BOOLEAN NOT NULL DEFAULT TRUE,
    CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    SupplierID INT NOT NULL,
    CategoryID INT NOT NULL,

    CONSTRAINT chk_products_price CHECK (Price >= 0),
    CONSTRAINT chk_products_discount CHECK (Discount >= 0 AND Discount <= 100),
    CONSTRAINT chk_products_volume CHECK (Volume IS NULL OR Volume > 0),
    CONSTRAINT chk_products_stock CHECK (StockQuantity >= 0),
    CONSTRAINT fk_products_supplier
        FOREIGN KEY (SupplierID) REFERENCES suppliers(SupplierID),
    CONSTRAINT fk_products_category
        FOREIGN KEY (CategoryID) REFERENCES categories(CategoryID)
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE INDEX idx_products_brand ON products(Brand);
CREATE INDEX idx_products_gender ON products(Gender);
CREATE INDEX idx_products_price ON products(Price);

CREATE TABLE product_images (
    ProductImageID INT AUTO_INCREMENT PRIMARY KEY,
    ProductID INT NOT NULL,
    ImageFile VARCHAR(255) NOT NULL,
    AltText VARCHAR(255),
    IsPrimary BOOLEAN NOT NULL DEFAULT FALSE,
    SortOrder INT NOT NULL DEFAULT 0,

    CONSTRAINT uq_product_image UNIQUE (ProductID, ImageFile),
    CONSTRAINT chk_product_image_sort CHECK (SortOrder >= 0),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (ProductID)
        REFERENCES products(ProductID)
        ON DELETE CASCADE
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE orders (
    OrderID INT AUTO_INCREMENT PRIMARY KEY,
    OrderDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    RequiredDate DATE NULL,
    ShippedDate DATETIME NULL,
    TotalAmount DECIMAL(12,2) NOT NULL DEFAULT 0,
    Status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    ReceiverName VARCHAR(100),
    ShipAddress VARCHAR(255),
    ShipCity VARCHAR(100),
    ShipPhone VARCHAR(20),
    Freight DECIMAL(12,2) NOT NULL DEFAULT 0,
    PaymentMethod VARCHAR(50),
    CustomerID INT NOT NULL,
    EmployeeID INT NULL,
    ShipperID INT NULL,

    CONSTRAINT chk_orders_total CHECK (TotalAmount >= 0),
    CONSTRAINT chk_orders_freight CHECK (Freight >= 0),
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (CustomerID) REFERENCES customers(CustomerID),
    CONSTRAINT fk_orders_employee
        FOREIGN KEY (EmployeeID) REFERENCES employees(EmployeeID),
    CONSTRAINT fk_orders_shipper
        FOREIGN KEY (ShipperID) REFERENCES shippers(ShipperID)
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE orderdetail (
    OrderDetailID INT AUTO_INCREMENT PRIMARY KEY,
    Quantity INT NOT NULL,
    UnitPrice DECIMAL(12,2) NOT NULL,
    Discount DECIMAL(5,2) NOT NULL DEFAULT 0,
    OrderID INT NOT NULL,
    ProductID INT NOT NULL,

    CONSTRAINT chk_orderdetail_quantity CHECK (Quantity > 0),
    CONSTRAINT chk_orderdetail_unitprice CHECK (UnitPrice >= 0),
    CONSTRAINT chk_orderdetail_discount CHECK (Discount >= 0 AND Discount <= 100),
    CONSTRAINT fk_orderdetail_order
        FOREIGN KEY (OrderID)
        REFERENCES orders(OrderID)
        ON DELETE CASCADE,
    CONSTRAINT fk_orderdetail_product
        FOREIGN KEY (ProductID) REFERENCES products(ProductID)
) CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;