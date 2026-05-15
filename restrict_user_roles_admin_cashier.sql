-- Run this on an existing TechParts database to keep only Admin and Cashier users.
-- This is safer than dropping the whole database because it preserves products,
-- suppliers, stock, purchase orders, and transactions.

SET @old_sql_safe_updates := @@SQL_SAFE_UPDATES;
SET SQL_SAFE_UPDATES = 0;

START TRANSACTION;

-- 1) Make sure one admin and one cashier account exist.
-- Password for both inserted demo accounts is: password123
INSERT INTO User (Name, Email, Password, Role, IsActive)
SELECT 'Admin User',
       'admin@techparts.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       'Admin',
       1
WHERE NOT EXISTS (
    SELECT 1 FROM User WHERE Role = 'Admin' LIMIT 1
);

INSERT INTO User (Name, Email, Password, Role, IsActive)
SELECT 'Cashier User',
       'cashier@techparts.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       'Cashier',
       1
WHERE NOT EXISTS (
    SELECT 1 FROM User WHERE Role = 'Cashier' LIMIT 1
);

-- 2) Store the IDs we will keep.
SET @admin_id := (
    SELECT ID
    FROM User
    WHERE Role = 'Admin'
    ORDER BY ID
    LIMIT 1
);

SET @cashier_id := (
    SELECT ID
    FROM User
    WHERE Role = 'Cashier'
    ORDER BY ID
    LIMIT 1
);

-- 3) Reassign records that point to users we are about to remove.
UPDATE Transaction
SET Cashier_ID = @cashier_id
WHERE Cashier_ID NOT IN (@admin_id, @cashier_id);

UPDATE PurchaseOrder
SET User_ID = @admin_id
WHERE User_ID NOT IN (@admin_id, @cashier_id);

-- 4) Recheck kept IDs and reassign foreign-key records before deleting users.
-- This also protects you if you run this lower section separately in Workbench.
SET @admin_id := (
    SELECT ID
    FROM User
    WHERE Role = 'Admin'
    ORDER BY ID
    LIMIT 1
);

SET @cashier_id := (
    SELECT ID
    FROM User
    WHERE Role = 'Cashier'
    ORDER BY ID
    LIMIT 1
);

UPDATE `Transaction`
SET Cashier_ID = @cashier_id
WHERE Cashier_ID <> @cashier_id;

UPDATE PurchaseOrder
SET User_ID = @admin_id
WHERE User_ID NOT IN (@admin_id, @cashier_id);

-- 5) Remove users with old roles, then remove duplicate extra admins/cashiers.
DELETE FROM User
WHERE Role NOT IN ('Admin', 'Cashier');

DELETE FROM User
WHERE Role = 'Admin'
  AND ID <> @admin_id;

DELETE FROM User
WHERE Role = 'Cashier'
  AND ID <> @cashier_id;

-- 6) Restrict the Role column so future rows cannot use User or Viewer.
ALTER TABLE User
MODIFY Role ENUM('Admin','Cashier') NOT NULL;

COMMIT;

SET SQL_SAFE_UPDATES = @old_sql_safe_updates;

-- Check result:
SELECT ID, Name, Email, Role, IsActive
FROM User
ORDER BY ID;
