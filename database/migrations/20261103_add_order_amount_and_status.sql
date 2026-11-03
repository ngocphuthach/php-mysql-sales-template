ALTER TABLE orders
    ADD COLUMN TotalAmount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER OrderDate,
    ADD COLUMN Status VARCHAR(30) NOT NULL DEFAULT 'Pending' AFTER TotalAmount;

UPDATE orders AS o
LEFT JOIN (
    SELECT
        OrderID,
        SUM(Quantity * UnitPrice) AS CalculatedTotal
    FROM orderdetail
    GROUP BY OrderID
) AS details
    ON details.OrderID = o.OrderID
SET o.TotalAmount = COALESCE(details.CalculatedTotal, 0);
