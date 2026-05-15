-- ============================================================
--  TechParts Database  |  Full Schema + Seed Data
-- ============================================================
CREATE DATABASE IF NOT EXISTS TechParts;
USE TechParts;

-- -----------------------------------------------------------
-- 1. USERS
-- -----------------------------------------------------------
CREATE TABLE User (
    ID       INT PRIMARY KEY AUTO_INCREMENT,
    Name     VARCHAR(100) NOT NULL,
    Email    VARCHAR(254) UNIQUE NOT NULL,
    Password VARCHAR(255) NOT NULL,           -- store bcrypt hash
    Role     ENUM('Admin','Cashier') NOT NULL,
    IsActive TINYINT(1) DEFAULT 1,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------
-- 2. CATEGORIES
-- -----------------------------------------------------------
CREATE TABLE Category (
    ID          INT PRIMARY KEY AUTO_INCREMENT,
    Name        VARCHAR(255) UNIQUE NOT NULL,
    Parts       VARCHAR(255) DEFAULT 'N/A',
    Status      ENUM('Active','Inactive','Archived') DEFAULT 'Inactive',
    Description TEXT
);

-- -----------------------------------------------------------
-- 3. PRODUCTS
-- -----------------------------------------------------------
CREATE TABLE Product (
    ID          INT PRIMARY KEY AUTO_INCREMENT,
    Name        VARCHAR(255) UNIQUE NOT NULL,
    Description TEXT,
    Price       DECIMAL(10,2) NOT NULL,
    Brand       VARCHAR(255) NOT NULL,
    ImageURL    VARCHAR(500) DEFAULT NULL,
    Category_ID INT NOT NULL,
    FOREIGN KEY (Category_ID) REFERENCES Category(ID),
    CONSTRAINT chk_price CHECK (Price > 0)
);

-- -----------------------------------------------------------
-- 4. SUPPLIERS
-- -----------------------------------------------------------
CREATE TABLE Supplier (
    ID      INT PRIMARY KEY AUTO_INCREMENT,
    Name    VARCHAR(255) NOT NULL,
    Phone   VARCHAR(20)  UNIQUE NOT NULL,
    Email   VARCHAR(254) UNIQUE NOT NULL,
    Address VARCHAR(255) NOT NULL,
    IsActive TINYINT(1) DEFAULT 1
);

-- -----------------------------------------------------------
-- 5. STOCK
-- -----------------------------------------------------------
CREATE TABLE Stock (
    ID          INT PRIMARY KEY AUTO_INCREMENT,
    Quantity    INT DEFAULT 0,
    MinStock    INT DEFAULT 5,          -- reorder alert threshold
    LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    Product_ID  INT NOT NULL,
    Supplier_ID INT NOT NULL,
    FOREIGN KEY (Product_ID)  REFERENCES Product(ID),
    FOREIGN KEY (Supplier_ID) REFERENCES Supplier(ID),
    CONSTRAINT chk_qty CHECK (Quantity >= 0)
);

-- -----------------------------------------------------------
-- 6. PURCHASE ORDERS (Header)
-- -----------------------------------------------------------
CREATE TABLE PurchaseOrder (
    ID          INT PRIMARY KEY AUTO_INCREMENT,
    OrderDate   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ArrivalDate DATE,
    Status      ENUM('Pending','Approved','Ordered','Received','Cancelled') DEFAULT 'Pending',
    Notes       TEXT,
    Supplier_ID INT NOT NULL,
    User_ID     INT NOT NULL,
    FOREIGN KEY (Supplier_ID) REFERENCES Supplier(ID),
    FOREIGN KEY (User_ID)     REFERENCES User(ID)
);

-- -----------------------------------------------------------
-- 7. PURCHASE ORDER ITEMS (Detail)
-- -----------------------------------------------------------
CREATE TABLE PurchaseOrderItem (
    ID               INT PRIMARY KEY AUTO_INCREMENT,
    QuantityOrdered  INT NOT NULL,
    UnitCost         DECIMAL(10,2) NOT NULL,
    Product_ID       INT NOT NULL,
    PurchaseOrder_ID INT NOT NULL,
    FOREIGN KEY (Product_ID)       REFERENCES Product(ID),
    FOREIGN KEY (PurchaseOrder_ID) REFERENCES PurchaseOrder(ID) ON DELETE CASCADE,
    CONSTRAINT chk_item_qty  CHECK (QuantityOrdered > 0),
    CONSTRAINT chk_item_cost CHECK (UnitCost > 0)
);

-- -----------------------------------------------------------
-- 8. PRODUCT ↔ SUPPLIER JUNCTION
-- -----------------------------------------------------------
CREATE TABLE Product_has_Supplier (
    Product_ID  INT NOT NULL,
    Supplier_ID INT NOT NULL,
    PRIMARY KEY (Product_ID, Supplier_ID),
    FOREIGN KEY (Product_ID)  REFERENCES Product(ID)  ON DELETE CASCADE,
    FOREIGN KEY (Supplier_ID) REFERENCES Supplier(ID) ON DELETE CASCADE
);

-- -----------------------------------------------------------
-- 9. TRANSACTIONS  (Cashier POS — offline sales header)
-- -----------------------------------------------------------
CREATE TABLE Transaction (
    ID              INT PRIMARY KEY AUTO_INCREMENT,
    TransactionDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CustomerName    VARCHAR(150) DEFAULT 'Walk-in Customer',
    CustomerPhone   VARCHAR(20)  DEFAULT NULL,
    PaymentMethod   ENUM('Cash','Card','GCash','PayMaya','Other') DEFAULT 'Cash',
    AmountTendered  DECIMAL(10,2) DEFAULT 0.00,
    Discount        DECIMAL(5,2)  DEFAULT 0.00,  -- percent 0-100
    TotalAmount     DECIMAL(10,2) NOT NULL,
    Status          ENUM('Completed','Voided','Refunded') DEFAULT 'Completed',
    Notes           TEXT,
    Cashier_ID      INT NOT NULL,
    FOREIGN KEY (Cashier_ID) REFERENCES User(ID),
    CONSTRAINT chk_total     CHECK (TotalAmount >= 0),
    CONSTRAINT chk_tendered  CHECK (AmountTendered >= 0),
    CONSTRAINT chk_discount  CHECK (Discount BETWEEN 0 AND 100)
);

-- -----------------------------------------------------------
-- 10. SALE ITEMS  (Line items per transaction)
-- -----------------------------------------------------------
CREATE TABLE SaleItem (
    ID             INT PRIMARY KEY AUTO_INCREMENT,
    Quantity       INT            NOT NULL,
    UnitPrice      DECIMAL(10,2)  NOT NULL,
    Subtotal       DECIMAL(10,2)  GENERATED ALWAYS AS (Quantity * UnitPrice) STORED,
    Product_ID     INT NOT NULL,
    Transaction_ID INT NOT NULL,
    FOREIGN KEY (Product_ID)     REFERENCES Product(ID),
    FOREIGN KEY (Transaction_ID) REFERENCES Transaction(ID) ON DELETE CASCADE,
    CONSTRAINT chk_sale_qty   CHECK (Quantity > 0),
    CONSTRAINT chk_sale_price CHECK (UnitPrice > 0)
);

-- -----------------------------------------------------------
-- TRIGGERS
-- -----------------------------------------------------------

-- Deduct stock on completed transaction sale item insert
DELIMITER $$
CREATE TRIGGER trg_deduct_stock_after_sale
AFTER INSERT ON SaleItem
FOR EACH ROW
BEGIN
    UPDATE Stock
    SET Quantity = Quantity - NEW.Quantity
    WHERE Product_ID = NEW.Product_ID
    LIMIT 1;
END$$

-- Restore stock on voided / refunded transaction
CREATE TRIGGER trg_restore_stock_on_void
AFTER UPDATE ON Transaction
FOR EACH ROW
BEGIN
    IF NEW.Status IN ('Voided','Refunded') AND OLD.Status = 'Completed' THEN
        UPDATE Stock s
        INNER JOIN SaleItem si ON si.Product_ID = s.Product_ID
        SET s.Quantity = s.Quantity + si.Quantity
        WHERE si.Transaction_ID = NEW.ID;
    END IF;
END$$

-- Add stock when PO is received
CREATE TRIGGER trg_add_stock_on_po_received
AFTER UPDATE ON PurchaseOrder
FOR EACH ROW
BEGIN
    IF NEW.Status = 'Received' AND OLD.Status != 'Received' THEN
        INSERT INTO Stock (Quantity, Product_ID, Supplier_ID)
        SELECT poi.QuantityOrdered, poi.Product_ID, NEW.Supplier_ID
        FROM PurchaseOrderItem poi
        WHERE poi.PurchaseOrder_ID = NEW.ID
        ON DUPLICATE KEY UPDATE Quantity = Quantity + VALUES(Quantity);
    END IF;
END$$
DELIMITER ;

-- ============================================================
--  SEED DATA
-- ============================================================

-- Users (passwords are bcrypt of 'password123')
INSERT INTO User (Name, Email, Password, Role) VALUES
('Admin User',       'admin@techparts.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin'),
('Cashier User',     'cashier@techparts.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier');

-- Categories
INSERT INTO Category (Name, Parts, Status, Description) VALUES
('CPU / Processors',     'Processors, Coolers',           'Active',   'Central Processing Units and cooling solutions'),
('Motherboards',         'ATX, mATX, ITX',                'Active',   'Desktop and server motherboards'),
('Memory (RAM)',         'DDR4, DDR5',                    'Active',   'System memory modules'),
('Storage',              'SSD, HDD, NVMe',                'Active',   'Solid-state and hard disk drives'),
('Graphics Cards (GPU)', 'NVIDIA, AMD',                   'Active',   'Dedicated graphics processing units'),
('Power Supply (PSU)',   'ATX PSU',                       'Active',   'Power supply units'),
('Cases & Cabinets',     'ATX, mATX, ITX Cases',          'Active',   'PC chassis and enclosures'),
('Peripherals',          'Keyboard, Mouse, Monitor',      'Active',   'Input devices and displays'),
('Networking',           'NIC, Router, Switch',           'Active',   'Network interface cards and equipment'),
('Cooling',              'Fans, Liquid Cooling',          'Active',   'CPU and case cooling solutions');

-- Suppliers
INSERT INTO Supplier (Name, Phone, Email, Address) VALUES
('TechDistrib PH',      '09171234567', 'sales@techdistrib.ph',    'Fuente Osmeña, Cebu City'),
('PC Parts Express',    '09281234567', 'orders@pcpartsexpress.ph','Colon St., Cebu City'),
('DataLink Wholesale',  '09391234567', 'info@datalink.ph',        'A.S. Fortuna, Mandaue City'),
('NovaTech Supply',     '09451234567', 'supply@novatech.ph',      'Mactan, Lapu-Lapu City'),
('ByteSource Corp',     '09561234567', 'contact@bytesource.ph',   'IT Park, Cebu City');

-- Products
INSERT INTO Product (Name, Description, Price, Brand, Category_ID) VALUES
-- CPUs
('Intel Core i5-13600K',     '14-core (6P+8E) LGA1700 processor, 125W TDP',         15990.00, 'Intel',   1),
('Intel Core i7-13700K',     '16-core (8P+8E) LGA1700 processor, 125W TDP',         24990.00, 'Intel',   1),
('AMD Ryzen 5 7600X',        '6-core/12-thread AM5 processor, PCIe 5.0',            14990.00, 'AMD',     1),
('AMD Ryzen 7 7700X',        '8-core/16-thread AM5 processor, PCIe 5.0',            22990.00, 'AMD',     1),
-- Motherboards
('ASUS ROG Strix B660-F',    'LGA1700, DDR5, PCIe 5.0, ATX',                        12990.00, 'ASUS',    2),
('MSI MAG B650 TOMAHAWK',    'AM5, DDR5, PCIe 5.0, ATX',                            13490.00, 'MSI',     2),
('Gigabyte B660M DS3H',      'LGA1700, DDR4, mATX, budget pick',                     4990.00, 'Gigabyte',2),
-- RAM
('Corsair Vengeance DDR5 32GB','2x16GB DDR5-5600 CL36',                              7490.00, 'Corsair', 3),
('G.Skill Trident Z5 32GB',  '2x16GB DDR5-6000 CL30 RGB',                           8990.00, 'G.Skill', 3),
('Kingston Fury Beast 16GB', '2x8GB DDR4-3200 CL16',                                2990.00, 'Kingston',3),
-- Storage
('Samsung 990 Pro 1TB NVMe', 'PCIe 4.0 NVMe SSD, 7450/6900 MB/s',                   6490.00, 'Samsung', 4),
('WD Blue 1TB SSD',          'SATA SSD 2.5", 560/530 MB/s',                          2490.00, 'WD',      4),
('Seagate Barracuda 2TB HDD','3.5" 7200 RPM SATA HDD',                               2290.00, 'Seagate', 4),
-- GPUs
('NVIDIA RTX 4070 Super',    'Ada Lovelace, 12GB GDDR6X, DLSS 3',                   44990.00, 'NVIDIA',  5),
('AMD RX 7800 XT',           'RDNA3, 16GB GDDR6, 256-bit',                          36990.00, 'AMD',     5),
('NVIDIA RTX 4060',          'Ada Lovelace, 8GB GDDR6, excellent 1080p',            22990.00, 'NVIDIA',  5),
-- PSUs
('Seasonic Focus GX-750',    '750W 80+ Gold, fully modular',                         5990.00, 'Seasonic',6),
('Corsair RM850x',           '850W 80+ Gold, fully modular, silent',                 6990.00, 'Corsair', 6),
-- Cases
('Lian Li O11 Dynamic EVO',  'Mid-tower, dual-chamber, tempered glass',              6990.00, 'Lian Li', 7),
('NZXT H510',                'Compact ATX mid-tower, PSU shroud',                    3990.00, 'NZXT',    7),
-- Peripherals
('Logitech MX Keys',         'Wireless mechanical-like keyboard, backlit',           3490.00, 'Logitech',8),
('Razer DeathAdder V3',      'Ergonomic gaming mouse, 30000 DPI',                    2990.00, 'Razer',   8),
('LG 27GP850-B 27"',         '27" IPS 165Hz 1ms QHD gaming monitor',               18990.00, 'LG',      8),
-- Networking
('TP-Link AX3000 Wi-Fi 6',   'PCIe Wi-Fi 6 card, Bluetooth 5.0',                    1490.00, 'TP-Link', 9),
-- Cooling
('Noctua NH-D15',            'Dual-tower CPU cooler, 2x 140mm fans',                 4490.00, 'Noctua',  10),
('NZXT Kraken X63 280mm',    'AIO liquid cooler, 2x140mm, LCD cap',                  7990.00, 'NZXT',    10);

-- Product ↔ Supplier
INSERT INTO Product_has_Supplier (Product_ID, Supplier_ID) VALUES
(1,1),(1,2),(2,1),(3,2),(3,3),(4,2),(5,1),(6,3),(7,2),
(8,4),(9,4),(10,2),(11,1),(12,3),(13,5),(14,1),(15,2),
(16,5),(17,5),(18,3),(19,1),(20,2),(21,4),(22,1),(23,3),
(24,2),(25,4),(26,1);

-- Stock (initial quantities)
INSERT INTO Stock (Quantity, MinStock, Product_ID, Supplier_ID) VALUES
(25,5,1,1),(18,5,2,1),(30,5,3,2),(22,5,4,2),(15,3,5,1),(20,3,6,3),
(40,5,7,2),(35,5,8,4),(28,5,9,4),(60,10,10,2),(20,5,11,1),(50,10,12,3),
(45,10,13,5),(8,3,14,1),(12,3,15,2),(25,5,16,2),(30,5,17,5),(25,5,18,3),
(18,5,19,3),(20,5,20,2),(35,5,21,1),(40,5,22,2),(5,2,23,4),(50,10,24,2),
(15,5,25,4),(10,3,26,1);

-- Purchase Orders
INSERT INTO PurchaseOrder (OrderDate, ArrivalDate, Status, Notes, Supplier_ID, User_ID) VALUES
('2025-03-10 09:00:00','2025-03-17','Received', 'Monthly stock replenishment', 1, 1),
('2025-03-20 10:00:00','2025-03-27','Received', 'GPU restock order',           2, 1),
('2025-04-01 08:30:00','2025-04-08','Approved', 'Q2 initial order',            3, 1),
('2025-04-10 11:00:00', NULL,       'Pending',  'Awaiting quote confirmation', 4, 1),
('2025-04-15 14:00:00','2025-04-22','Ordered',  'Urgent NVMe restock',         5, 1);

-- Purchase Order Items
INSERT INTO PurchaseOrderItem (QuantityOrdered, UnitCost, Product_ID, PurchaseOrder_ID) VALUES
(20, 13500.00, 1, 1),(15, 21000.00, 2, 1),(10,  4200.00, 7, 1),
(10, 40000.00,14, 2),(12, 32000.00,15, 2),(15, 20000.00,16, 2),
(25,  2100.00,12, 3),(30,  1900.00,13, 3),(20,  2600.00,10, 3),
(20,  6400.00, 8, 4),(15,  7800.00, 9, 4),(25,  2500.00,10, 4),
(20,  5500.00,11, 5),(15,  6100.00,25, 5),(10,  7200.00,26, 5);

-- Transactions (sample POS sales)
INSERT INTO Transaction (TransactionDate, CustomerName, CustomerPhone, PaymentMethod, AmountTendered, Discount, TotalAmount, Status, Cashier_ID) VALUES
('2025-04-14 10:23:00','Carlo Reyes',    '09171110001','Cash',   20000.00, 0,  15990.00,'Completed',2),
('2025-04-14 13:45:00','Walk-in Customer',NULL,        'Cash',    5000.00, 0,   4980.00,'Completed',2),
('2025-04-15 09:10:00','Ana Villanueva', '09281110002','Cash',      0.00, 5,  21241.00,'Completed',2),
('2025-04-15 11:30:00','Mark Lim',       '09391110003','Card',       0.00, 0,  44990.00,'Completed',2),
('2025-04-16 14:00:00','Jenny Tan',      '09451110004','Cash',   10000.00, 0,   9480.00,'Completed',2),
('2025-04-17 16:20:00','Walk-in Customer',NULL,        'Cash',    3000.00, 0,   2990.00,'Completed',2),
('2025-04-18 10:05:00','Roel Santos',    '09171110005','Cash',    0.00,10,  16191.00,'Completed',2),
('2025-04-19 15:40:00','Walk-in Customer',NULL,        'Cash',    8000.00, 0,   7490.00,'Completed',2);

-- Sale Items
INSERT INTO SaleItem (Quantity, UnitPrice, Product_ID, Transaction_ID) VALUES
(1, 15990.00, 1, 1),
(1,  2490.00,12, 2),(1,  2490.00,10, 2),
(1, 22990.00,16, 3),(1, 14990.00, 3, 3),  -- before 5% discount applied
(1, 44990.00,14, 4),
(2,  2990.00,22, 5),(1,  3490.00,21, 5),
(1,  2990.00,22, 6),
(1, 14990.00, 3, 7),(1,  2990.00,25, 7),  -- before 10% discount
(1,  7490.00, 8, 8);




USE TechParts;

-- Supplier.IsActive
ALTER TABLE Supplier
    MODIFY COLUMN IsActive TINYINT(1) DEFAULT 1;

-- Add IsActive if it flat out doesn't exist
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'TechParts'
      AND TABLE_NAME   = 'Supplier'
      AND COLUMN_NAME  = 'IsActive'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE Supplier ADD COLUMN IsActive TINYINT(1) DEFAULT 1',
    'SELECT "Supplier.IsActive already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- User.IsActive
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'TechParts'
      AND TABLE_NAME   = 'User'
      AND COLUMN_NAME  = 'IsActive'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE User ADD COLUMN IsActive TINYINT(1) DEFAULT 1',
    'SELECT "User.IsActive already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- User.CreatedAt
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'TechParts'
      AND TABLE_NAME   = 'User'
      AND COLUMN_NAME  = 'CreatedAt'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE User ADD COLUMN CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT "User.CreatedAt already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Category.Parts
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'TechParts'
      AND TABLE_NAME   = 'Category'
      AND COLUMN_NAME  = 'Parts'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE Category ADD COLUMN Parts VARCHAR(255) DEFAULT "N/A"',
    'SELECT "Category.Parts already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Category.Description
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'TechParts'
      AND TABLE_NAME   = 'Category'
      AND COLUMN_NAME  = 'Description'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE Category ADD COLUMN Description TEXT',
    'SELECT "Category.Description already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Category.Status (ENUM)
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'TechParts'
      AND TABLE_NAME   = 'Category'
      AND COLUMN_NAME  = 'Status'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE Category ADD COLUMN Status ENUM(''Active'',''Inactive'',''Archived'') DEFAULT ''Inactive''',
    'SELECT "Category.Status already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Stock.MinStock
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'TechParts'
      AND TABLE_NAME   = 'Stock'
      AND COLUMN_NAME  = 'MinStock'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE Stock ADD COLUMN MinStock INT DEFAULT 5',
    'SELECT "Stock.MinStock already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Stock.LastUpdated
SET @col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'TechParts'
      AND TABLE_NAME   = 'Stock'
      AND COLUMN_NAME  = 'LastUpdated'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE Stock ADD COLUMN LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT "Stock.LastUpdated already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Product_has_Supplier table (junction)
CREATE TABLE IF NOT EXISTS Product_has_Supplier (
    Product_ID  INT NOT NULL,
    Supplier_ID INT NOT NULL,
    PRIMARY KEY (Product_ID, Supplier_ID),
    FOREIGN KEY (Product_ID)  REFERENCES Product(ID)  ON DELETE CASCADE,
    FOREIGN KEY (Supplier_ID) REFERENCES Supplier(ID) ON DELETE CASCADE
);

SELECT 'Migration complete.' AS result;
