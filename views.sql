-- Product x Category ( "Catalog" View )
CREATE VIEW View_Product_Catalog AS
SELECT p.ID AS ProductID, p.Name AS ProductName, p.Description AS ProductDescription, p.Price, p.Brand, c.Name AS CategoryName
FROM Product p
JOIN Category c ON p.Category_ID = c.ID;

-- Product x Stock ( "Warehouse" View )
CREATE VIEW View_Inventory_Status AS
SELECT 
    p.Name AS Product, p.Brand, 
    s.Quantity, s.LastUpdated,
    CASE 
        WHEN s.Quantity = 0 THEN 'Out of Stock'
        WHEN s.Quantity < 10 THEN 'Low Stock'
        ELSE 'In Stock'
    END AS Availability
FROM Product p
JOIN Stock s ON p.ID = s.Product_ID;

-- Supplier x Product ("Sourcing" View)
CREATE VIEW View_Supplier_Sources AS
SELECT 
    sup.Name AS Supplier_Name, sup.Email, sup.Phone,
    p.Name AS Product_Name, p.Brand, p.Price AS Market_Price
FROM Supplier sup
JOIN Product_has_Supplier phs ON sup.ID = phs.Supplier_ID
JOIN Product p ON phs.Product_ID = p.ID;

-- Category x Stock ( "Department" View )
CREATE VIEW View_Category_Stock_Levels AS
SELECT 
    c.Name AS Category, 
    SUM(s.Quantity) AS Total_Items_In_Category,
    COUNT(p.ID) AS Unique_Products
FROM Category c
LEFT JOIN Product p ON c.ID = p.Category_ID
LEFT JOIN Stock s ON p.ID = s.Product_ID
GROUP BY c.Name;

-- Product x Supplier x Supplier ( "Master Archive" View )
CREATE VIEW View_Master_Archive AS
SELECT 
    p.Name AS Product, p.Brand, c.Name AS Category, 
    sup.Name AS Supplier, s.Quantity AS Stock, p.Price
FROM Product p
JOIN Category c ON p.Category_ID = c.ID
JOIN Stock s ON p.ID = s.Product_ID
JOIN Supplier sup ON s.Supplier_ID = sup.ID;

-- Purchase Order x Supplier x Purchase Order Item x Product ( "Purchase History" View )
CREATE OR REPLACE VIEW View_Purchase_History AS
SELECT 
    po.ID AS Order_Number,
    DATE(po.OrderDate) AS Order_Date,
    sup.Name AS Supplier,
    p.Name AS Product,
    poi.QuantityOrdered AS Qty,
    poi.UnitCost AS Unit_Price,
    (poi.QuantityOrdered * poi.UnitCost) AS Total_Line_Price,
    po.Status AS Status
FROM PurchaseOrder po
JOIN Supplier sup ON po.Supplier_ID = sup.ID
JOIN PurchaseOrderItem poi ON po.ID = poi.PurchaseOrder_ID
JOIN Product p ON poi.Product_ID = p.ID;