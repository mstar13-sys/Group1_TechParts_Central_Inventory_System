-- ============================================================
--  TechParts Migration / Patch Script
--  Run this if you have an existing DB that's missing columns.
--  Safe to run multiple times (uses IF NOT EXISTS checks).
-- ============================================================

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
