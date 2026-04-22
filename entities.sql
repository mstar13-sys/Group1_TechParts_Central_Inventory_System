CREATE DATABASE IF NOT EXISTS TechParts;
USE TechParts;

-- 1. Users table for login
CREATE TABLE User (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(100) NOT NULL,
    Email VARCHAR(254) UNIQUE NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Role ENUM('Admin', 'User', 'Viewer') NOT NULL
);

-- 2. Categories for products
CREATE TABLE Category (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) UNIQUE NOT NULL,
    Parts VARCHAR(255) DEFAULT 'N/A',
    Status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Inactive',
    Description TEXT
);

-- 3. Products master list
CREATE TABLE Product (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) UNIQUE NOT NULL,
    Description TEXT,
    Price DECIMAL(10,2) NOT NULL,
    Brand VARCHAR(255) NOT NULL,
    Category_ID INT NOT NULL,
    FOREIGN KEY (Category_ID) REFERENCES Category(ID),
    CONSTRAINT chk_price CHECK (Price > 0)
);

-- 4. Suppliers info
CREATE TABLE Supplier (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(255) NOT NULL,
    Phone VARCHAR(20) UNIQUE NOT NULL,
    Email VARCHAR(254) UNIQUE NOT NULL,
    Address VARCHAR(255) NOT NULL
);

-- 5. Stock tracking
CREATE TABLE Stock (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    Quantity INT DEFAULT 0,
    LastUpdated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    Product_ID INT NOT NULL,
    Supplier_ID INT NOT NULL,
    FOREIGN KEY (Product_ID) REFERENCES Product(ID),
    FOREIGN KEY (Supplier_ID) REFERENCES Supplier(ID),
    CONSTRAINT chk_qty CHECK (Quantity >= 0)
);

-- 6. Purchase Orders (Header)
CREATE TABLE PurchaseOrder (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    OrderDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ArrivalDate DATE,
    Status ENUM('Pending', 'Approved', 'Ordered', 'Received', 'Cancelled') DEFAULT 'Pending',
    Supplier_ID INT NOT NULL,
    User_ID INT NOT NULL,
    FOREIGN KEY (Supplier_ID) REFERENCES Supplier(ID),
    FOREIGN KEY (User_ID) REFERENCES User(ID)
);

-- 7. Items inside the Purchase Order (Details)
CREATE TABLE PurchaseOrderItem (
    ID INT PRIMARY KEY AUTO_INCREMENT,
    QuantityOrdered INT NOT NULL,
    UnitCost DECIMAL(10,2) NOT NULL,
    Product_ID INT NOT NULL,
    PurchaseOrder_ID INT NOT NULL,
    FOREIGN KEY (Product_ID) REFERENCES Product(ID),
    FOREIGN KEY (PurchaseOrder_ID) REFERENCES PurchaseOrder(ID) ON DELETE CASCADE,
    CONSTRAINT chk_item_qty CHECK (QuantityOrdered > 0),
    CONSTRAINT chk_item_cost CHECK (UnitCost > 0)
);

-- 8. Many-to-Many junction (Which supplier provides which product)
CREATE TABLE Product_has_Supplier (
    Product_ID INT NOT NULL,
    Supplier_ID INT NOT NULL,
    PRIMARY KEY (Product_ID, Supplier_ID),
    FOREIGN KEY (Product_ID) REFERENCES Product(ID) ON DELETE CASCADE,
    FOREIGN KEY (Supplier_ID) REFERENCES Supplier(ID) ON DELETE CASCADE
);